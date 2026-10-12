<?php
// ============================================================
//  recovery_disbursement.php — Recovery Disbursement Journal
//  Auto-fills vendor details from vendor_masterlist by TIN or name
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once __DIR__ . '/../lib/vat_mode.php';
requireLogin();

// Only Recovery branch + management can access
if (isBranch() && currentBranch() !== 'Recovery') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_disbursement` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`    date             DEFAULT NULL,
    `tin`           varchar(100)     DEFAULT '',
    `company_name`  varchar(255)     DEFAULT '',
    `vat_status`    varchar(10)      DEFAULT 'VAT',
    `address`       varchar(255)     DEFAULT '',
    `invoice_no`    varchar(100)     DEFAULT '',
    `account_title` varchar(255)     DEFAULT '',
    `gross`         decimal(15,2)    DEFAULT 0.00,
    `input_tax`     decimal(15,2)    DEFAULT 0.00,
    `net_of_vat`    decimal(15,2)    DEFAULT 0.00,
    `particular`    varchar(255)     DEFAULT '',
    `saved_by`      varchar(100)     DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date`         (`entry_date`),
    KEY `idx_tin`          (`tin`),
    KEY `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$TXT_COLS = ['entry_date','tin','company_name','vat_status','address','invoice_no','account_title','particular'];
$NUM_COLS = ['gross','input_tax','net_of_vat'];

// ── Account Titles (managed via recovery_acc_title.php) ────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_acc_titles` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `title`       varchar(255) NOT NULL,
    `section`     enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
    `sort_order`  int(6) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
try { $pdo->exec("ALTER TABLE `recovery_acc_titles` ADD COLUMN `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses' AFTER `title`"); }
catch (Throwable $ignored) {}

if ((int)$pdo->query("SELECT COUNT(*) FROM `recovery_acc_titles`")->fetchColumn() === 0) {
    $DEFAULT_TITLES = [
        "Office Equipment"             => 'assets',
        "Other Equipment"              => 'assets',
        "Service Vehicle"              => 'assets',
        "Leasehold Improvement"        => 'assets',
        "Furniture and Fixtures"       => 'assets',
        "Investments"                  => 'assets',
        "Accounts Payable"             => 'other',
        "EWT Payable"                  => 'other',
        "Purchases - Non-Vat"          => 'expenses',
        "Purchases - Vatable"          => 'expenses',
        "Kitchen Supplies"             => 'expenses',
        "Solane"                       => 'expenses',
        "Miscellaneous"                => 'expenses',
        "Rent"                         => 'expenses',
        "CUSA"                         => 'expenses',
        "Office Supplies"              => 'expenses',
        "Pest Control"                 => 'expenses',
        "Advertisement"                => 'expenses',
        "Bio Augmentation"             => 'expenses',
        "Professional Fee"             => 'expenses',
        "Bookkeeping Fee"              => 'expenses',
        "Fare & Transportation"        => 'expenses',
        "Fuel & Oil"                   => 'expenses',
        "Repairs and Maintenance"      => 'expenses',
        "Telephone, Light & Water"     => 'expenses',
        "Delivery Expense"             => 'expenses',
        "Salaries and Wages"           => 'expenses',
        "Representation Expense"       => 'expenses',
        "Meals"                        => 'expenses',
        "Taxes and Licenses"           => 'expenses',
        "SSS, PHIC, HDMF Contribution" => 'expenses',
        "Commission Expense"           => 'expenses',
        "M'Nikki"                      => 'expenses',
        "c/o Nikki"                    => 'expenses',
        "Others"                       => 'expenses',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `recovery_acc_titles` (`title`,`section`,`sort_order`,`saved_by`) VALUES (?,?,?,?)");
    $i = 0;
    foreach ($DEFAULT_TITLES as $t => $sec) { $ins->execute([$t, $sec, $i, 'system-seed']); $i++; }
}
// One-time fix for rows seeded before `section` existed (see recovery_acc_title.php for details)
$fixToAssets = $pdo->prepare("UPDATE `recovery_acc_titles` SET `section`='assets' WHERE `title`=? AND `saved_by`='system-seed' AND `section`='expenses'");
foreach (["Office Equipment","Other Equipment","Service Vehicle","Leasehold Improvement","Furniture and Fixtures","Investments"] as $t) {
    $fixToAssets->execute([$t]);
}
$fixToOther = $pdo->prepare("UPDATE `recovery_acc_titles` SET `section`='other' WHERE `title`=? AND `saved_by`='system-seed' AND `section`='expenses'");
foreach (["Accounts Payable","EWT Payable"] as $t) {
    $fixToOther->execute([$t]);
}

// ── VAT treatment per Account Title (set on the Account Titles page) ──
vatModeEnsureColumn($pdo, 'recovery_acc_titles');

// ── AJAX: Vendor lookup ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendor_lookup'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (!$q) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT tin, company_name, vat_status, address
                            FROM vendor_masterlist_unified
                            WHERE company_name LIKE ? OR tin LIKE ?
                            ORDER BY company_name ASC LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = [];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($NUM_COLS as $f) $data[$f] = (float)str_replace(',', '', $_POST[$f] ?? 0);
    // Input Tax / Net of VAT come from the account title's VAT setting + the supplier's VAT status
    [$data['input_tax'], $data['net_of_vat']] = vatModeCompute(vatModeFor($pdo, 'recovery_acc_titles', $data['account_title']), $data['vat_status'], $data['gross']);
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO recovery_disbursement (" . implode(',', array_map(fn($f)=>"`$f`", $fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(),
            'input_tax' => number_format($data['input_tax'], 2, '.', ''), 'net_of_vat' => number_format($data['net_of_vat'], 2, '.', '')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($TXT_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = trim($_POST[$f] ?? ''); }
    foreach ($NUM_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = (float)str_replace(',', '', $_POST[$f] ?? 0); }
    // Input Tax / Net of VAT come from the account title's VAT setting + the supplier's VAT status
    [$taxVal, $netVal] = vatModeCompute(vatModeFor($pdo, 'recovery_acc_titles', trim($_POST['account_title'] ?? '')),
        trim($_POST['vat_status'] ?? ''), (float)str_replace(',', '', $_POST['gross'] ?? 0));
    foreach ($sets as $k => $c) {
        if ($c === '`input_tax`=?')  $vals[$k] = $taxVal;
        if ($c === '`net_of_vat`=?') $vals[$k] = $netVal;
    }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE recovery_disbursement SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok' => true, 'input_tax' => number_format($taxVal, 2, '.', ''), 'net_of_vat' => number_format($netVal, 2, '.', '')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM recovery_disbursement WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── XLSX Export ─────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $fMonth = $_GET['month'] ?? '';
    // Compare real dates (not formatted text) so MariaDB 11 never hits "Illegal mix of collations".
    $mStart = preg_match('/^\d{4}-\d{2}$/', $fMonth) ? $fMonth . '-01' : date('Y-m-01');
    $mEnd   = date('Y-m-d', strtotime($mStart . ' +1 month'));
    $where = $fMonth ? "WHERE entry_date >= ? AND entry_date < ?" : "";
    $params = $fMonth ? [$mStart, $mEnd] : [];
    $stmt = $pdo->prepare("SELECT * FROM recovery_disbursement $where ORDER BY entry_date ASC, id ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $stmt2 = $pdo->prepare("SELECT SUM(gross) g, SUM(input_tax) t, SUM(net_of_vat) n FROM recovery_disbursement $where");
    $stmt2->execute($params);
    $tot = $stmt2->fetch();

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Recovery'));
    $xlsx->title('MERITONI CORP');
    $xlsx->subtitle('Recovery Disbursement Journal');
    $xlsx->subtitle(($fMonth ? date('F Y', strtotime($fMonth.'-01')) . ' · ' : '') . 'Generated ' . date('F d, Y H:i:s'));
    $xlsx->blank();
    $xlsx->header(['DATE','TIN','NAME OF SUPPLIER','TYPE','ADDRESS','INVOICE #','ACCOUNT TITLE','GROSS','INPUT TAX','NET OF VAT','PARTICULAR']);
    foreach ($rows as $r) {
        $xlsx->row([
            $r['entry_date'], $r['tin'], $r['company_name'], $r['vat_status'],
            $r['address'], $r['invoice_no'], $r['account_title'],
            $r['gross'], $r['input_tax'], $r['net_of_vat'], $r['particular']
        ]);
    }
    $xlsx->totalRow(['','','','','','','TOTAL', number_format((float)$tot['g'],2,'.',''), number_format((float)$tot['t'],2,'.',''), number_format((float)$tot['n'],2,'.',''), '']);
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([12,16,26,8,22,14,20,14,12,14,20]);
    $xlsx->output('Recovery_Disbursement_' . date('Y-m-d') . '.xlsx');
    exit;
}


// ── Load rows ─────────────────────────────────────────────
$fMonth  = $_GET['month'] ?? date('Y-m');
$fSearch = trim($_GET['q'] ?? '');

// Compare real dates (not formatted text) so MariaDB 11 never hits "Illegal mix of collations".
$mStart = preg_match('/^\d{4}-\d{2}$/', $fMonth) ? $fMonth . '-01' : date('Y-m-01');
$mEnd   = date('Y-m-d', strtotime($mStart . ' +1 month'));
$where  = ["entry_date >= ? AND entry_date < ?"]; $params = [$mStart, $mEnd];
if ($fSearch) {
    $where[]  = "(company_name LIKE ? OR tin LIKE ? OR invoice_no LIKE ? OR particular LIKE ?)";
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
}
$whereSQL = "WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM recovery_disbursement $whereSQL ORDER BY entry_date ASC, id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Totals
$stmtT = $pdo->prepare("SELECT SUM(gross) g, SUM(input_tax) t, SUM(net_of_vat) n FROM recovery_disbursement $whereSQL");
$stmtT->execute($params);
$totals = $stmtT->fetch();

$acctTitles = $pdo->query("SELECT title FROM `recovery_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_COLUMN);
$acctVatModes = vatModeMap($pdo, 'recovery_acc_titles');

$pageTitle  = 'Recovery Disbursement';
$activePage = 'recovery_disbursement';
include '../layout.php';
?>

<style>
/* ── Outer wrapper ── */
.dsb-outer {
  width: 100%; overflow-x: auto;
  border: 2px solid #217346;
  border-radius: var(--radius);
  background: var(--surface);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 2px 8px rgba(0,0,0,.10);
}
.dsb-outer::-webkit-scrollbar { height: 8px; }
.dsb-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.dsb-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

/* ── Table ── */
.dsb-table { border-collapse: collapse; width: 100%; min-width: 1300px; font-size: .74rem; }

/* Header row — green Excel style */
.dsb-table thead tr.head-company td {
  background: #ffffff; color: #1a1a1a;
  font-family: var(--font-m); font-size: .68rem; font-weight: 700;
  padding: 5px 10px; border: 1px solid #c6d9b0; text-align: left;
}
.dsb-table thead tr.head-month td {
  background: #ffffff; color: #1a1a1a;
  font-family: var(--font-m); font-size: .68rem; font-weight: 700;
  padding: 4px 10px; border: 1px solid #c6d9b0;
}
.dsb-table thead tr.head-cols th {
  background: #4CAF50; color: #fff;
  font-family: var(--font-m); font-size: .58rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em;
  padding: 9px 8px; border: 1px solid #388E3C;
  white-space: nowrap; text-align: center;
  position: sticky; top: 0; z-index: 20;
}

/* Column widths */
.th-date { width: 90px;  min-width: 90px; }
.th-tin  { width: 140px; min-width: 140px; }
.th-name { width: 200px; min-width: 200px; }
.th-type { width: 60px;  min-width: 60px; }
.th-addr { width: 180px; min-width: 180px; }
.th-inv  { width: 110px; min-width: 110px; }
.th-acct { width: 160px; min-width: 160px; }
.th-gross{ width: 110px; min-width: 110px; }
.th-itax { width: 100px; min-width: 100px; }
.th-nvat { width: 110px; min-width: 110px; }
.th-part { width: 160px; min-width: 160px; }
.th-act  { width: 80px;  min-width: 80px; position: sticky; right: 0; z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,.08); }
.td-act-sticky { position: sticky; right: 0; z-index: 4; background: #fff; box-shadow: -2px 0 4px rgba(0,0,0,.08); }

/* Row styling */
.dsb-table tbody tr { border-bottom: 1px solid #d0d8e0; transition: background .1s; }
.dsb-table tbody tr td { background: #fff; }
.dsb-table tbody tr:nth-child(even) td { background: #EBF5E0; }
.dsb-table tbody tr:nth-child(even) td.td-act-sticky { background: #EBF5E0; }
.dsb-table tbody tr:hover td { background: #d6efc0 !important; }
.dsb-table td { border: 1px solid #c8d4df; padding: 0; vertical-align: middle; }

/* Inline inputs */
.di {
  width: 100%; padding: 6px 7px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-h); font-size: .74rem;
  display: block; box-sizing: border-box;
}
.di:focus { background: rgba(15,123,92,.06); outline: 1px solid #4CAF50; }
.di.mono  { font-family: var(--font-m); font-size: .70rem; }
.di.num   { font-family: var(--font-m); font-size: .70rem; text-align: right; }
.di.center{ text-align: center; }

.td-no {
  text-align: center; font-family: var(--font-m); font-size: .64rem;
  color: #555; padding: 0 5px; background: #f5f8f2 !important; font-weight: 600;
}

/* VAT select */
.di-sel {
  width: 100%; padding: 5px 4px;
  background: transparent; border: none; outline: none;
  font-family: var(--font-m); font-size: .70rem; font-weight: 700;
  text-align: center; cursor: pointer;
  appearance: none; -webkit-appearance: none;
}
.di-sel:focus { background: rgba(15,123,92,.06); }

/* Account title dropdown */
.acct-sel {
  width: 100%; padding: 6px 4px;
  font-size: .72rem; font-weight: 500; color: #1a1d23;
  text-align: left;
}

/* Totals row */
.dsb-table tfoot td {
  background: #C6EFCE; color: #1a1a1a;
  font-family: var(--font-m); font-size: .65rem; font-weight: 800;
  padding: 8px 8px; border: 1px solid #388E3C;
}
.dsb-table tfoot td.num { text-align: right; }

/* Action buttons */
.bsv {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 4px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%; margin-bottom: 3px;
}
.bsv:hover { background: #dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok  { background:#dcfce7; color:#15803d; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.bdel {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 4px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%;
}
.bdel:hover { background: #ffe4e6; }

/* Controls */
.dsb-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.btn-add-row {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 14px; background: #4CAF50; color: #fff;
  border: none; border-radius: 8px; font-size: .78rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #388E3C; transform: translateY(-1px); }

/* Autocomplete dropdown */
.ac-wrap { position: relative; width: 100%; }
.ac-list {
  display: none; position: absolute; top: 100%; left: 0; z-index: 999;
  background: #fff; border: 1px solid #c8d4df; border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  max-height: 220px; overflow-y: auto; min-width: 280px;
}
.ac-list.open { display: block; }
.ac-item {
  padding: 8px 12px; cursor: pointer; font-size: .74rem;
  font-family: var(--font-h); border-bottom: 1px solid #f0f2f5;
  transition: background .1s;
}
.ac-item:hover { background: #f0fdf4; }
.ac-item .ac-name { font-weight: 600; color: #1a1d23; }
.ac-item .ac-tin  { font-family: var(--font-m); font-size: .64rem; color: #6b7280; margin-top: 1px; }
.ac-item .ac-addr { font-family: var(--font-m); font-size: .62rem; color: #9ca3af; margin-top: 1px; font-style: italic; }

.scroll-hint {
  font-family: var(--font-m); font-size: .60rem; color: #374151;
  text-align: center; padding: 4px 12px; border-bottom: 1px solid #c6d9b0;
  background: #f0fff0;
}
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
.empty-state {
  text-align: center; padding: 50px 20px;
  font-family: var(--font-m); font-size: .78rem; color: var(--subtext);
}
</style>

<!-- Header -->
<div class="section-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div class="section-title">Recovery <span>Disbursement</span></div>
    <div class="section-subtitle">MERITONI CORP · Auto-fills vendor details from Vendor Masterlist</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
  <a href="recovery_acc_title.php"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#6D4C41;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(109,76,65,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#4E342E';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#6D4C41';this.style.transform=''">
    🏷 Account Titles
  </a>
  <a href="recovery_profit_loss.php"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#1565C0;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(21,101,192,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#0D47A1';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#1565C0';this.style.transform=''">
    📊 Profit &amp; Loss
  </a>
  <a href="recovery_sum.php?year=<?= date('Y') ?>"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#F9A825;color:#3E2723;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(249,168,37,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#F57F17';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#F9A825';this.style.transform=''">
    📋 Sum
  </a>
  <a href="recovery_tb.php?year=<?= date('Y') ?>"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#37474F;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(55,71,79,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#263238';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#37474F';this.style.transform=''">
    🗂 TB
  </a>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="dsb-controls" id="filterForm">
  <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($fMonth) ?>"
         style="max-width:160px" onchange="this.form.submit()">
  <input type="text" name="q" class="form-control" placeholder="Search supplier, TIN, invoice…"
         value="<?= htmlspecialchars($fSearch) ?>" style="max-width:240px">
  <button type="submit" class="btn btn-ghost btn-sm">Search</button>
  <?php if ($fSearch): ?>
  <a href="recovery_disbursement.php?month=<?= urlencode($fMonth) ?>" class="btn btn-ghost btn-sm">Reset</a>
  <?php endif; ?>
  <button type="button" class="btn-add-row" onclick="addRow()">+ Add Entry</button>
  <a href="recovery_disbursement.php?export_csv=1&month=<?= urlencode($fMonth) ?>"
     class="btn btn-ghost btn-sm" style="color:#388E3C;border-color:rgba(76,175,80,.3);background:rgba(76,175,80,.06)">
    ⬇ Export XLSX
  </a>
  <a href="recovery_acc_title.php" class="btn btn-ghost btn-sm" style="color:#6D4C41;border-color:rgba(109,76,65,.3);background:rgba(109,76,65,.06);font-weight:600">
    🏷 Account Titles
  </a>
  <a href="recovery_profit_loss.php" class="btn btn-ghost btn-sm" style="color:#1565C0;border-color:rgba(21,101,192,.3);background:rgba(21,101,192,.06);font-weight:600">
    📊 Profit &amp; Loss
  </a>
  <a href="recovery_sum.php?year=<?= date('Y') ?>" class="btn btn-ghost btn-sm" style="color:#5D4037;border-color:rgba(249,168,37,.4);background:rgba(249,168,37,.08);font-weight:600">
    📋 Sum
  </a>
  <a href="recovery_tb.php?year=<?= date('Y') ?>" class="btn btn-ghost btn-sm" style="color:#37474F;border-color:rgba(55,71,79,.3);background:rgba(55,71,79,.06);font-weight:600">
    🗂 TB
  </a>
  <span style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
    <strong><?= count($rows) ?></strong> entr<?= count($rows) !== 1 ? 'ies' : 'y' ?>
  </span>
</form>

<!-- Table -->
<div class="dsb-outer">
  <?php if (count($rows) > 5): ?>
  <div class="scroll-hint">← Scroll horizontally to see all columns →</div>
  <?php endif; ?>
  <table class="dsb-table" id="dsbt">
    <thead>
      <tr class="head-company">
        <td colspan="11" style="font-size:.76rem;font-weight:800;letter-spacing:.04em">MERITONI CORP</td>
      </tr>
      <tr class="head-month">
        <td colspan="11"><?= strtoupper(date('F', strtotime($fMonth . '-01'))) ?></td>
      </tr>
      <tr class="head-cols">
        <th class="th-date">DATE</th>
        <th class="th-tin">TIN</th>

        <th class="th-name">NAME OF SUPPLIER</th>
        <th class="th-type">TYPE</th>
        <th class="th-addr">ADDRESS</th>
        <th class="th-inv">INVOICE #</th>
        <th class="th-acct">ACCOUNT TITLE</th>
        <th class="th-gross">GROSS</th>
        <th class="th-itax">INPUT TAX</th>
        <th class="th-nvat">NET OF VAT</th>
        <th class="th-part">PARTICULAR</th>
        <th class="th-act">ACTION</th>
      </tr>
    </thead>
    <tbody id="dsb-tbody">
    <?php if ($rows): ?>
      <?php foreach ($rows as $r): ?>
      <tr id="row<?= $r['id'] ?>" data-id="<?= $r['id'] ?>">
        <?= renderRow($r, $acctTitles) ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr id="empty-row">
        <td colspan="11" class="empty-state">No entries for <?= htmlspecialchars(date('F Y', strtotime($fMonth . '-01'))) ?> — click <strong>+ Add Entry</strong></td>
      </tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="text-align:right;padding:8px 12px;font-weight:800;font-family:var(--font-m);font-size:.65rem;">
          TOTAL (<span id="dsb-count"><?= count($rows) ?></span> entries)
        </td>
        <td class="num" id="tfoot-gross"><?= number_format((float)$totals['g'], 2) ?></td>
        <td class="num" id="tfoot-itax"><?= number_format((float)$totals['t'], 2) ?></td>
        <td class="num" id="tfoot-nvat"><?= number_format((float)$totals['n'], 2) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:12px">
  <button class="btn-add-row" onclick="addRow()">+ Add Entry</button>
</div>

<?php
function renderRow(array $r, array $acctOpts): string {
    // Account titles now come from the recovery_acc_titles table
    // (managed via recovery_acc_title.php) instead of being hardcoded here.
    $acctSel = '<select class="di-sel acct-sel" data-col="account_title" onchange="markDirty(this)">'
             . '<option value=""' . ($r['account_title'] === '' ? ' selected' : '') . '>— Select —</option>';
    foreach ($acctOpts as $opt) {
        $sel = ($r['account_title'] === $opt) ? ' selected' : '';
        $acctSel .= '<option value="' . htmlspecialchars($opt) . '"' . $sel . '>' . htmlspecialchars($opt) . '</option>';
    }
    // If a row has an account_title that's no longer in the masterlist
    // (e.g. it was deleted), keep it selectable so data isn't silently lost.
    if ($r['account_title'] !== '' && !in_array($r['account_title'], $acctOpts, true)) {
        $acctSel .= '<option value="' . htmlspecialchars($r['account_title']) . '" selected>' . htmlspecialchars($r['account_title']) . ' (removed)</option>';
    }
    $acctSel .= '</select>';
    $d   = fn($k) => htmlspecialchars($r[$k] ?? '');
    $fmt = fn($v) => number_format((float)$v, 2);
    $vat = $r['vat_status'] ?? 'VAT';

    $h  = '<td><input type="date" class="di mono" data-col="entry_date" value="' . $d('entry_date') . '" oninput="markDirty(this)"></td>';
    $h .= '<td><div class="ac-wrap">
             <input type="text" class="di mono" data-col="tin" value="' . $d('tin') . '" placeholder="—"
                    oninput="markDirty(this);triggerAC(this)" autocomplete="off">
             <div class="ac-list"></div></div></td>';
    $h .= '<td><div class="ac-wrap">
             <input type="text" class="di" data-col="company_name" value="' . $d('company_name') . '" placeholder="—"
                    oninput="markDirty(this);triggerAC(this)" autocomplete="off">
             <div class="ac-list"></div></div></td>';
    $h .= '<td style="text-align:center">
             <select class="di-sel" data-col="vat_status" onchange="markDirty(this);calcRow(this)">
               <option value="VAT"' . ($vat==='VAT' ? ' selected' : '') . '>VAT</option>
               <option value="NV"'  . ($vat==='NV'  ? ' selected' : '') . '>NV</option>
             </select></td>';
    $h .= '<td><input type="text" class="di" data-col="address"       value="' . $d('address')       . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td><input type="text" class="di mono" data-col="invoice_no"    value="' . $d('invoice_no')    . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td>' . $acctSel . '</td>';
    $h .= '<td><input type="text" class="di num" data-col="gross"      value="' . $fmt($r['gross'])      . '" oninput="markDirty(this);calcRow(this)"></td>';
    $h .= '<td><input type="text" class="di num" data-col="input_tax"  value="' . $fmt($r['input_tax']) . '" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>';
    $h .= '<td><input type="text" class="di num" data-col="net_of_vat" value="' . $fmt($r['net_of_vat']). '" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>';
    $h .= '<td><input type="text" class="di" data-col="particular"     value="' . $d('particular')      . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td class="td-act-sticky" style="padding:4px 5px;text-align:center">
             <button class="bsv" onclick="saveRow(this)">Update</button>
             <button class="bdel" onclick="deleteRow(this)">Del</button></td>';
    return $h;
}
?>

<script>
// Account titles now come from recovery_acc_titles (manage via recovery_acc_title.php)
const ACCT_OPTIONS = <?= json_encode($acctTitles, JSON_UNESCAPED_UNICODE) ?>;
// VAT setting of each account title (managed on the Account Titles page):
//   'both' = follows the supplier (VAT supplier -> input tax, NV supplier -> none)
//   'vat'  = input tax only (always 12%)    'none' = Non-VAT only (never input tax)
const ACCT_VAT_MODES = <?= json_encode($acctVatModes, JSON_UNESCAPED_UNICODE) ?>;

let newRowCounter = 0;
let acTimer = null;

// ── Dirty flag ─────────────────────────────────────────────
function markDirty(el) {
    const row = el.closest('tr');
    const btn = row?.querySelector('.bsv');
    if (btn) { btn.textContent = row.dataset.id ? 'Update*' : 'Save'; btn.className = 'bsv'; }
}

// ── Auto-calculate: gross → input_tax (12%) → net_of_vat ──
function calcRow(el) {
    const row   = el.closest('tr');
    const gross = parseFloat(row.querySelector('[data-col="gross"]').value.replace(/,/g,'')) || 0;
    const vat   = row.querySelector('[data-col="vat_status"]').value;
    const taxEl = row.querySelector('[data-col="input_tax"]');
    const netEl = row.querySelector('[data-col="net_of_vat"]');
    const acct  = row.querySelector('[data-col="account_title"]')?.value || '';
    // The account title's VAT setting decides: 'none' never carries input tax,
    // 'vat' always does, 'both' follows the supplier's VAT / NV flag.
    const mode  = ACCT_VAT_MODES[acct] || 'both';
    const carriesTax = mode === 'vat' || (mode === 'both' && vat === 'VAT');
    if (carriesTax) {
        const tax = gross / 1.12 * 0.12;
        const net = gross - tax;
        taxEl.value = tax.toFixed(2);
        netEl.value = net.toFixed(2);
    } else {
        taxEl.value = '0.00';
        netEl.value = gross.toFixed(2);
    }
}

// ── Autocomplete ───────────────────────────────────────────
function triggerAC(inp) {
    clearTimeout(acTimer);
    acTimer = setTimeout(() => doAC(inp), 220);
}

async function doAC(inp) {
    const q = inp.value.trim();
    const list = inp.closest('.ac-wrap').querySelector('.ac-list');
    if (q.length < 2) { list.classList.remove('open'); return; }
    try {
        const res  = await fetch('recovery_disbursement.php?vendor_lookup=1&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.length) {
            list.innerHTML = '<div class="ac-item" style="color:#6b7280;cursor:default"><div class="ac-name">No vendor found</div><div class="ac-tin">Add to Vendor Masterlist first</div></div>';
            list.classList.add('open'); return;
        }
        list.innerHTML = data.map(v => `
          <div class="ac-item" data-tin="${escHtml(v.tin)}" data-name="${escHtml(v.company_name)}"
               data-vat="${escHtml(v.vat_status)}" data-addr="${escHtml(v.address)}"
               onclick="fillVendor(this)">
            <div class="ac-name">${escHtml(v.company_name)}</div>
            <div class="ac-tin">TIN: ${escHtml(v.tin)} · ${escHtml(v.vat_status)}</div>
            ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
          </div>`).join('');
        list.classList.add('open');
    } catch(e) {}
}

function fillVendor(item) {
    const row = item.closest('tr');
    // Fill TIN, name, VAT type, and address
    row.querySelector('[data-col="tin"]').value          = item.dataset.tin;
    row.querySelector('[data-col="company_name"]').value = item.dataset.name;
    // DB stores 'VAT' or 'NV' \u2014 match exactly
    const vatSel = row.querySelector('[data-col="vat_status"]');
    vatSel.value = (item.dataset.vat === 'NV') ? 'NV' : 'VAT';
    row.querySelector('[data-col="address"]').value      = item.dataset.addr;
    // Close all dropdowns
    document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
    markDirty(row.querySelector('[data-col="tin"]'));
    // Move focus to invoice_no as the next logical field
    row.querySelector('[data-col="invoice_no"]')?.focus();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close dropdowns when clicking outside
document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});

// ── Account title <option> builder (used when adding a new row) ──
function acctOptionsHtml() {
    let html = '<option value="">— Select —</option>';
    ACCT_OPTIONS.forEach(t => { html += `<option value="${escHtml(t)}">${escHtml(t)}</option>`; });
    return html;
}

// ── Add row ────────────────────────────────────────────────
function addRow() {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('dsb-tbody');
    const today = new Date().toISOString().split('T')[0];
    const tr = document.createElement('tr');
    tr.id = 'new_' + (++newRowCounter); tr.dataset.id = '';
    tr.innerHTML = `
      <td><input type="date" class="di mono" data-col="entry_date" value="${today}" oninput="markDirty(this)"></td>
      <td><div class="ac-wrap"><input type="text" class="di mono" data-col="tin" value="" placeholder="—" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td><div class="ac-wrap"><input type="text" class="di" data-col="company_name" value="" placeholder="Type to search…" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td style="text-align:center"><select class="di-sel" data-col="vat_status" onchange="markDirty(this);calcRow(this)"><option value="VAT">VAT</option><option value="NV">NV</option></select></td>
      <td><input type="text" class="di" data-col="address"       value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="di mono" data-col="invoice_no"    value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><select class="di-sel acct-sel" data-col="account_title" onchange="markDirty(this)">${acctOptionsHtml()}</select></td>
      <td><input type="text" class="di num" data-col="gross"      value="0.00" oninput="markDirty(this);calcRow(this)"></td>
      <td><input type="text" class="di num" data-col="input_tax"  value="0.00" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>
      <td><input type="text" class="di num" data-col="net_of_vat" value="0.00" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>
      <td><input type="text" class="di" data-col="particular"     value="" placeholder="—" oninput="markDirty(this)"></td>
      <td class="td-act-sticky" style="padding:4px 5px;text-align:center">
        <button class="bsv" onclick="saveRow(this)">Save</button>
      </td>`;
    tbody.appendChild(tr);
    tr.querySelector('[data-col="tin"]')?.focus();
    updateTotals();
}

// ── Save row ───────────────────────────────────────────────
async function saveRow(btn) {
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    btn.textContent = '…'; btn.className = 'bsv saving';
    const fd = new FormData();
    fd.append(id ? 'ajax_update' : 'ajax_add', '1');
    if (id) fd.append('id', id);
    row.querySelectorAll('[data-col]').forEach(el => {
        fd.append(el.dataset.col, el.value || '');
    });
    try {
        const res  = await fetch('recovery_disbursement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            btn.textContent = 'Update'; btn.className = 'bsv ok';
            // Show Input Tax / Net of VAT exactly as the server saved them
            if (data.input_tax !== undefined) {
                const tEl = row.querySelector('[data-col="input_tax"]');
                const nEl = row.querySelector('[data-col="net_of_vat"]');
                if (tEl) tEl.value = data.input_tax;
                if (nEl) nEl.value = data.net_of_vat;
            }
            if (!id && data.id) {
                row.dataset.id = data.id; row.id = 'row' + data.id;
                const del = document.createElement('button');
                del.className = 'bdel'; del.textContent = 'Del';
                del.onclick = function(){ deleteRow(this); };
                btn.parentElement.appendChild(del);
            }
            setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
            showToast('✓ Entry saved', 'success');
            updateTotals();
        } else {
            btn.textContent = 'Error'; btn.className = 'bsv err';
            showToast('❌ ' + data.msg, 'error');
            setTimeout(() => { btn.textContent = id ? 'Update' : 'Save'; btn.className = 'bsv'; }, 3000);
        }
    } catch(e) {
        btn.textContent = 'Error'; btn.className = 'bsv err';
        showToast('❌ Network error', 'error');
    }
}

// ── Delete row ─────────────────────────────────────────────
async function deleteRow(btn) {
    if (!confirm('Delete this entry?')) return;
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    if (!id) { row.remove(); updateTotals(); return; }
    const fd = new FormData(); fd.append('ajax_delete','1'); fd.append('id', id);
    try {
        const res  = await fetch('recovery_disbursement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            row.style.opacity = '0'; row.style.transition = 'opacity .3s';
            setTimeout(() => { row.remove(); updateTotals(); }, 300);
            showToast('✓ Entry deleted', 'success');
        } else { showToast('❌ ' + data.msg, 'error'); }
    } catch(e) { showToast('❌ Network error', 'error'); }
}

// ── Live totals ────────────────────────────────────────────
function updateTotals() {
    let g = 0, t = 0, n = 0;
    document.querySelectorAll('#dsb-tbody tr[data-id]').forEach(row => {
        g += parseFloat(row.querySelector('[data-col="gross"]')?.value?.replace(/,/g,'') || 0);
        t += parseFloat(row.querySelector('[data-col="input_tax"]')?.value?.replace(/,/g,'') || 0);
        n += parseFloat(row.querySelector('[data-col="net_of_vat"]')?.value?.replace(/,/g,'') || 0);
    });
    document.getElementById('tfoot-gross').textContent = g.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('tfoot-itax').textContent  = t.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('tfoot-nvat').textContent  = n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    const count = document.querySelectorAll('#dsb-tbody tr[data-id]').length;
    document.getElementById('dsb-count').textContent = count;
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'flash flash-' + (type || 'success') + ' toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
</body>
</html>