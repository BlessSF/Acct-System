<?php
// ============================================================
//  demic_jaro_payables.php — DemicLab Payables Module
//  Full replica of PAYABLES.xlsx (6 sheets, folded into 4 tabs):
//   • SUMMARY            → Summary tab   (jaro_payables_summary)
//   • PAYABLES MONITORING→ Monitoring tab (jaro_payables_releasing + jaro_payables_cash_snapshot)
//   • CASH FLOW / LEDGER → Ledger tab    (jaro_payables_ledger)   [both sheets were the
//                                          same recurring monthly table, unified here]
//   • PDC + SGD SENDOUT  → PDC tab       (jaro_payables_pdc, obligation_type='SGD SEND-OUTS'
//                                          covers the SGD sheet)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only DemicLab branch and management can access
if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create tables ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_summary` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `item_name`   varchar(255) DEFAULT '',
    `amount`      decimal(12,2) DEFAULT 0.00,
    `remarks`     varchar(255) DEFAULT '',
    `rental`      decimal(12,2) DEFAULT 0.00,
    `utz`         decimal(12,2) DEFAULT 0.00,
    `sort_order`  int(11) DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_releasing` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `release_date`  date DEFAULT NULL,
    `description`   varchar(255) DEFAULT '',
    `amount`        decimal(12,2) DEFAULT 0.00,
    `status`        varchar(30) DEFAULT 'pending',
    `saved_by`      varchar(100) DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_cash_snapshot` (
    `id`                 int(11) NOT NULL,
    `as_of_date`         date DEFAULT NULL,
    `secbank_balance`    decimal(14,2) DEFAULT 0.00,
    `aub_balance`        decimal(14,2) DEFAULT 0.00,
    `receivable_hmo`     decimal(14,2) DEFAULT 0.00,
    `receivable_tenants` decimal(14,2) DEFAULT 0.00,
    `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("INSERT IGNORE INTO jaro_payables_cash_snapshot (id) VALUES (1)");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_ledger` (
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`       date NOT NULL,
    `item_name`        varchar(255) DEFAULT '',
    `monthly_payment`  decimal(12,2) DEFAULT 0.00,
    `paid_amount`      decimal(12,2) DEFAULT 0.00,
    `remaining_bal`    decimal(14,2) DEFAULT 0.00,
    `terms`            varchar(255) DEFAULT '',
    `status`           varchar(30) DEFAULT 'NOT YET PAID',
    `remarks`          varchar(255) DEFAULT '',
    `saved_by`         varchar(100) DEFAULT NULL,
    `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`       timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_pdc` (
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `obligation_type`  varchar(50) DEFAULT '',
    `due_date`         date DEFAULT NULL,
    `amount`           decimal(12,2) DEFAULT 0.00,
    `balance`          decimal(14,2) DEFAULT 0.00,
    `status`           varchar(30) DEFAULT 'NOT PAID',
    `remarks`          varchar(255) DEFAULT '',
    `saved_by`         varchar(100) DEFAULT NULL,
    `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`       timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_type` (`obligation_type`),
    KEY `idx_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_payables_pdc_sections` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `name`        varchar(100) NOT NULL,
    `sort_order`  int(11) DEFAULT 0,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Seed the original 6 categories once, on first run only
$secCount = (int)$pdo->query("SELECT COUNT(*) FROM jaro_payables_pdc_sections")->fetchColumn();
if ($secCount === 0) {
    $seedStmt = $pdo->prepare("INSERT INTO jaro_payables_pdc_sections (name, sort_order) VALUES (?,?)");
    foreach (['RENTAL','UTZ MACHINE','BANK LOAN','INCORPORATOR 1','INCORPORATOR 2','SGD SEND-OUTS'] as $i => $seedName) {
        $seedStmt->execute([$seedName, $i]);
    }
}

function pdcSlug(string $name): string {
    return 'pdc_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
}

// ── Field whitelists (used by generic AJAX handlers below) ──
$TBL = [
    'summary' => [
        'table' => 'jaro_payables_summary',
        'txt'   => ['item_name', 'remarks'],
        'num'   => ['amount', 'rental', 'utz'],
        'date'  => [],
        'sel'   => [],
    ],
    'releasing' => [
        'table' => 'jaro_payables_releasing',
        'txt'   => ['description'],
        'num'   => ['amount'],
        'date'  => ['release_date'],
        'sel'   => ['status'],
    ],
    'ledger' => [
        'table' => 'jaro_payables_ledger',
        'txt'   => ['item_name', 'terms', 'remarks'],
        'num'   => ['monthly_payment', 'paid_amount', 'remaining_bal'],
        'date'  => ['entry_date'],
        'sel'   => ['status'],
    ],
    'pdc' => [
        'table' => 'jaro_payables_pdc',
        'txt'   => ['remarks'],
        'num'   => ['amount', 'balance'],
        'date'  => ['due_date'],
        'sel'   => ['obligation_type', 'status'],
    ],
];

function tblFields(array $def): array {
    return array_merge($def['txt'], $def['num'], $def['date'], $def['sel']);
}

// ── AJAX: generic add ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $data = [];
    foreach ($def['txt'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($def['num'] as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($def['date'] as $f) $data[$f] = $_POST[$f] ?: null;
    foreach ($def['sel'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO `{$def['table']}` (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $id  = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($def['txt'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    foreach ($def['num'] as $f) { $sets[]="`$f`=?"; $vals[]=(float)($_POST[$f] ?? 0); }
    foreach ($def['date'] as $f) { $sets[]="`$f`=?"; $vals[]=$_POST[$f] ?: null; }
    foreach ($def['sel'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE `{$def['table']}` SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic delete ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM `{$TBL[$key]['table']}` WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: add a new PDC / Amortization section ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_section'])) {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { echo json_encode(['ok'=>false,'msg'=>'Section name is required']); exit; }
    try {
        $next = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1)+1 FROM jaro_payables_pdc_sections")->fetchColumn();
        $pdo->prepare("INSERT INTO jaro_payables_pdc_sections (name, sort_order) VALUES (?,?)")->execute([$name, $next]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId(),'name'=>$name]);
    } catch (Throwable $e) {
        $msg = str_contains($e->getMessage(), 'uniq_name') ? 'A section with that name already exists' : $e->getMessage();
        echo json_encode(['ok'=>false,'msg'=>$msg]);
    }
    exit;
}

// ── AJAX: delete a PDC / Amortization section (only if empty) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_section'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    try {
        $sec = $pdo->prepare("SELECT name FROM jaro_payables_pdc_sections WHERE id=?");
        $sec->execute([$id]);
        $secRow = $sec->fetch();
        if (!$secRow) { echo json_encode(['ok'=>false,'msg'=>'Section not found']); exit; }
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM jaro_payables_pdc WHERE obligation_type=?");
        $cnt->execute([$secRow['name']]);
        if ((int)$cnt->fetchColumn() > 0) {
            echo json_encode(['ok'=>false,'msg'=>'Remove or move all rows out of this section before deleting it']);
            exit;
        }
        $pdo->prepare("DELETE FROM jaro_payables_pdc_sections WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: save cash snapshot (single row) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_snapshot'])) {
    header('Content-Type: application/json');
    try {
        $pdo->prepare("UPDATE jaro_payables_cash_snapshot SET as_of_date=?, secbank_balance=?, aub_balance=?, receivable_hmo=?, receivable_tenants=? WHERE id=1")
            ->execute([
                $_POST['as_of_date'] ?: null,
                (float)($_POST['secbank_balance'] ?? 0),
                (float)($_POST['aub_balance'] ?? 0),
                (float)($_POST['receivable_hmo'] ?? 0),
                (float)($_POST['receivable_tenants'] ?? 0),
            ]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Filters ───────────────────────────────────────────────
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$fYear      = (int)($_GET['year']  ?? date('Y'));
$fMonth     = (int)($_GET['month'] ?? date('n'));

// ── Load data for each tab ──────────────────────────────────
$summaryRows   = $pdo->query("SELECT * FROM jaro_payables_summary ORDER BY sort_order ASC, id ASC")->fetchAll();
$releasingRows = $pdo->query("SELECT * FROM jaro_payables_releasing ORDER BY release_date ASC, id ASC")->fetchAll();
$snapshot      = $pdo->query("SELECT * FROM jaro_payables_cash_snapshot WHERE id=1")->fetch() ?: [];

$ledgerStmt = $pdo->prepare("SELECT * FROM jaro_payables_ledger WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? ORDER BY entry_date ASC, id ASC");
$ledgerStmt->execute([$fYear, $fMonth]);
$ledgerRows = $ledgerStmt->fetchAll();

// PDC / Amortization — one indicated section per obligation type, plus
// whatever custom sections the user has added. Every section carries its
// own rows and its own running totals.
$pdcSections = $pdo->query("SELECT * FROM jaro_payables_pdc_sections ORDER BY sort_order ASC, id ASC")->fetchAll();
$pdcRowsStmt = $pdo->prepare("SELECT * FROM jaro_payables_pdc WHERE obligation_type=? ORDER BY due_date ASC, id ASC");
foreach ($pdcSections as &$sec) {
    $pdcRowsStmt->execute([$sec['name']]);
    $sec['rows']   = $pdcRowsStmt->fetchAll();
    $sec['slug']   = pdcSlug($sec['name']);
    $sec['totAmt'] = array_sum(array_column($sec['rows'], 'amount'));
    $sec['totBal'] = array_sum(array_column($sec['rows'], 'balance'));
}
unset($sec);

// ── OVERVIEW (read-only, whole-module totals — always ALL data, ignores  ──
// ── the Ledger month filter and PDC obligation-type filter above)        ──
$ovSummary = $pdo->query("SELECT COALESCE(SUM(amount),0) amt, COALESCE(SUM(rental),0) rental, COALESCE(SUM(utz),0) utz FROM jaro_payables_summary")->fetch();

$ovReleasing = $pdo->query("SELECT
        COALESCE(SUM(amount),0) total,
        COALESCE(SUM(CASE WHEN status='pending'  THEN amount ELSE 0 END),0) pending,
        COALESCE(SUM(CASE WHEN status='released' THEN amount ELSE 0 END),0) released
    FROM jaro_payables_releasing")->fetch();

$ovLedger = $pdo->query("SELECT
        COALESCE(SUM(monthly_payment),0) monthly,
        COALESCE(SUM(paid_amount),0)     paid,
        COALESCE(SUM(remaining_bal),0)   remaining
    FROM jaro_payables_ledger")->fetch();

$ovPdcTotals = $pdo->query("SELECT COALESCE(SUM(amount),0) amt, COALESCE(SUM(balance),0) bal FROM jaro_payables_pdc")->fetch();

$ovPdcByType = [];
foreach ($pdo->query("SELECT obligation_type, COALESCE(SUM(amount),0) amt, COALESCE(SUM(balance),0) bal FROM jaro_payables_pdc GROUP BY obligation_type")->fetchAll() as $row) {
    $ovPdcByType[$row['obligation_type']] = $row;
}

// Total still outstanding across every category (pending releases + ledger
// remaining balances + PDC balances). Summary/rental/utz rows have no
// "remaining balance" field of their own, so they aren't part of this figure.
$ovGrandOutstanding = (float)$ovReleasing['pending'] + (float)$ovLedger['remaining'] + (float)$ovPdcTotals['bal'];

$pageTitle  = 'Payables';
$activePage = 'demic_jaro_payables';
include '../layout.php';

function nz($v) { $v=(float)$v; return $v!=0 ? number_format($v,2,'.','') : ''; }
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* Tabs */
.pv-tabs { display:flex; gap:6px; margin-bottom:18px; border-bottom:2px solid var(--border); flex-wrap:wrap; }
.pv-tab {
  padding:9px 18px; font-family:var(--font-h); font-size:.82rem; font-weight:600;
  color:var(--subtext); background:none; border:none; cursor:pointer;
  border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s;
}
.pv-tab:hover { color:var(--accent); }
.pv-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.pv-pane { display:none; }
.pv-pane.active { display:block; }

.pv-outer {
  width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border);
  background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:14px;
}
.pv-table { border-collapse:collapse; width:max-content; min-width:100%; font-size:.72rem; }
.pv-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.56rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:9px 8px; border:1px solid #2d5480;
  white-space:nowrap; text-align:center;
}
.pv-table td { border:1px solid #e3e6ea; padding:0; vertical-align:middle; }
.pv-table tbody tr:hover td { filter:brightness(.97); }
.pv-table tfoot td {
  padding:8px 8px; font-family:var(--font-m); font-size:.7rem; font-weight:800;
  text-align:right; border:1px solid #d1d5db; background:#1e3a5f; color:#fff;
}
.pv-table tfoot td.tfl { text-align:center; text-transform:uppercase; letter-spacing:.07em; font-size:.6rem; }

.pvi {
  width:100%; padding:6px 6px; background:transparent; border:none; outline:none;
  color:#1a1d23; font-family:var(--font-m); font-size:.7rem; text-align:right; display:block; box-sizing:border-box;
}
.pvi.txt  { text-align:left; font-family:var(--font-h); }
.pvi:focus { background:rgba(15,123,92,.07); outline:1px solid rgba(15,123,92,.4); }
select.pvi { text-align:left; -webkit-appearance:none; appearance:none; background:#fff; }

/* Read-only computed cells (Overview tab) */
.pv-ro {
  padding:6px 6px; font-family:var(--font-m); font-size:.7rem; text-align:right;
  color:#1a1d23; background:#fafbfc; box-sizing:border-box;
}
.pv-ro.txt { text-align:left; font-family:var(--font-h); font-weight:600; background:#fff; }

.td-act { text-align:center; padding:4px 6px; white-space:nowrap; }
.btn-add-row {
  display:flex; align-items:center; gap:7px; padding:8px 16px; background:var(--accent); color:#fff;
  border:none; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; font-family:var(--font-h);
  transition:background .15s, transform .1s;
}
.btn-add-row:hover { background:#0a6649; transform:translateY(-1px); }
.bsv {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:5px; cursor:pointer;
  white-space:nowrap; display:inline-block; margin-right:3px;
}
.bsv:hover { background:#dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok { background:#dcfce7; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.bdel {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#fff1f2; color:#be123c; border:1px solid #fecdd3; border-radius:5px; cursor:pointer;
}
.bdel:hover { background:#ffe4e6; }

.pv-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }

.pv-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:18px; }
.pv-card {
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
  padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.pv-card .lbl { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext2); margin-bottom:6px; }
.pv-card .val { font-family:var(--font-m); font-size:1.15rem; font-weight:800; color:#1a1d23; }
.pv-card.accent .val { color:var(--accent); }
.pv-card.warn .val { color:#b45309; }

.pdc-section { margin-bottom:22px; }
.pdc-section-head {
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
  padding:10px 14px; background:#1e3a5f; border-radius:8px 8px 0 0; margin-bottom:0;
}
.pdc-section .pv-outer { border-top-left-radius:0; border-top-right-radius:0; margin-top:0; }
.pdc-section-title { font-family:var(--font-h); font-size:.92rem; font-weight:700; color:#fff; }
.pdc-section-tools { display:flex; align-items:center; gap:10px; }
.pdc-section-total { font-family:var(--font-m); font-size:.68rem; color:#cfe0ef; }
.pdc-section-total b { color:#fff; font-size:.78rem; }
.btn-add-row.sm { padding:5px 12px; font-size:.68rem; }

.pv-snap-form { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:14px 16px; }
.pv-snap-field { display:flex; flex-direction:column; gap:4px; }
.pv-snap-field label { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; color:var(--subtext2); }
.pv-snap-field input { padding:7px 9px; border:1px solid var(--border); border-radius:6px; font-family:var(--font-m); font-size:.78rem; width:150px; }

.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }

/* Section filter boxes + search (PDC / Amortization tab) */
.pv-filter-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
.pv-search {
  flex:1; min-width:200px; max-width:340px; padding:8px 12px; border:1px solid var(--border);
  border-radius:8px; font-family:var(--font-m); font-size:.75rem; outline:none;
}
.pv-search:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(15,123,92,.12); }
.pv-chips { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; margin-bottom:18px; }
.pv-chip {
  background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:10px 12px;
  cursor:pointer; transition:all .15s; box-shadow:0 1px 3px rgba(0,0,0,.05);
}
.pv-chip:hover { border-color:var(--accent); transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.08); }
.pv-chip.active { border-color:var(--accent); background:rgba(15,123,92,.06); }
.pv-chip-name {
  font-family:var(--font-h); font-size:.7rem; font-weight:700; color:#1a1d23; text-transform:uppercase;
  letter-spacing:.03em; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.pv-chip-total { font-family:var(--font-m); font-size:.85rem; font-weight:800; color:var(--accent); }
.pv-chip-empty { grid-column:1/-1; text-align:center; padding:14px; font-family:var(--font-m); font-size:.72rem; color:var(--subtext2); }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Payables <span style="color:var(--accent)">Module</span></div>
    <div class="section-subtitle">Summary · Monitoring / Cash Position · Ledger · PDC — mirrors PAYABLES.xlsx</div>
  </div>
</div>

<!-- Tabs -->
<div class="pv-tabs">
  <button class="pv-tab active" data-tab="summary" onclick="switchTab('summary')">📋 Summary</button>
  <button class="pv-tab" data-tab="monitoring" onclick="switchTab('monitoring')">💰 Payables Monitoring</button>
  <button class="pv-tab" data-tab="ledger" onclick="switchTab('ledger')">📒 Payables Ledger</button>
  <button class="pv-tab" data-tab="pdc" onclick="switchTab('pdc')">🗓️ PDC / Amortization</button>
  <button class="pv-tab" data-tab="overview" onclick="switchTab('overview')">📊 Overview</button>
</div>

<!-- ══════════════ TAB 1: SUMMARY ══════════════ -->
<div class="pv-pane active" id="pane-summary">
  <div class="pv-controls">
    <button type="button" class="btn-add-row" onclick="addRow('summary')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:220px">NAME</th>
          <th style="min-width:110px">AMOUNT</th>
          <th style="min-width:200px">REMARKS</th>
          <th style="min-width:110px">RENTAL</th>
          <th style="min-width:110px">UTZ</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-summary">
        <?php foreach ($summaryRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input class="pvi txt" data-col="item_name" value="<?=htmlspecialchars($r['item_name'])?>" oninput="rowChanged(this,'summary')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'summary')"></td>
          <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'summary')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="rental" value="<?=nz($r['rental'])?>" oninput="rowChanged(this,'summary')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="utz" value="<?=nz($r['utz'])?>" oninput="rowChanged(this,'summary')"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'summary')">Update</button><button class="bdel" onclick="deleteRow(this,'summary')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="tfl">TOTAL</td>
          <td id="tot-summary-amount">—</td>
          <td></td>
          <td id="tot-summary-rental">—</td>
          <td id="tot-summary-utz">—</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 2: PAYABLES MONITORING ══════════════ -->
<div class="pv-pane" id="pane-monitoring">

  <div class="pv-cards" id="cash-cards">
    <div class="pv-card accent"><div class="lbl">Total Releasing Payables</div><div class="val" id="card-total-releasing">—</div></div>
    <div class="pv-card"><div class="lbl">Cash in Bank (SecBank)</div><div class="val" id="card-secbank">—</div></div>
    <div class="pv-card warn"><div class="lbl">Available Funds (SecBank − Releasing)</div><div class="val" id="card-available">—</div></div>
    <div class="pv-card"><div class="lbl">Total Bank Balance (SecBank + AUB)</div><div class="val" id="card-bank-total">—</div></div>
    <div class="pv-card"><div class="lbl">Total Receivables</div><div class="val" id="card-receivables">—</div></div>
    <div class="pv-card accent"><div class="lbl">Net Cash Position</div><div class="val" id="card-net">—</div></div>
  </div>

  <div class="pv-snap-form">
    <div class="pv-snap-field"><label>As of Date</label><input type="date" id="snap-date" value="<?=htmlspecialchars($snapshot['as_of_date'] ?? '')?>"></div>
    <div class="pv-snap-field"><label>SecBank Balance</label><input type="number" step="0.01" id="snap-secbank" value="<?=nz($snapshot['secbank_balance'] ?? 0)?>" oninput="recalcCash()"></div>
    <div class="pv-snap-field"><label>AUB Balance</label><input type="number" step="0.01" id="snap-aub" value="<?=nz($snapshot['aub_balance'] ?? 0)?>" oninput="recalcCash()"></div>
    <div class="pv-snap-field"><label>Receivables — HMO</label><input type="number" step="0.01" id="snap-hmo" value="<?=nz($snapshot['receivable_hmo'] ?? 0)?>" oninput="recalcCash()"></div>
    <div class="pv-snap-field"><label>Receivables — Tenants</label><input type="number" step="0.01" id="snap-tenants" value="<?=nz($snapshot['receivable_tenants'] ?? 0)?>" oninput="recalcCash()"></div>
    <button type="button" class="btn-add-row" onclick="saveSnapshot()">💾 Save Cash Position</button>
  </div>

  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">DemicLab-Jaro Releasing Payables</div>
    <button type="button" class="btn-add-row" onclick="addRow('releasing')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:110px">DATE</th>
          <th style="min-width:260px">DESCRIPTION</th>
          <th style="min-width:110px">AMOUNT</th>
          <th style="min-width:120px">STATUS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-releasing">
        <?php foreach ($releasingRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input type="date" class="pvi" style="text-align:center" data-col="release_date" value="<?=htmlspecialchars($r['release_date'])?>" oninput="rowChanged(this,'releasing')"></td>
          <td><input class="pvi txt" data-col="description" value="<?=htmlspecialchars($r['description'])?>" oninput="rowChanged(this,'releasing')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'releasing');recalcCash()"></td>
          <td>
            <select class="pvi" data-col="status" onchange="rowChanged(this,'releasing')">
              <option value="pending"  <?=$r['status']==='pending'?'selected':''?>>Pending</option>
              <option value="released" <?=$r['status']==='released'?'selected':''?>>Released</option>
            </select>
          </td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'releasing')">Update</button><button class="bdel" onclick="deleteRow(this,'releasing')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="tfl">TOTAL</td><td></td><td id="tot-releasing-amount">—</td><td></td><td></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 3: PAYABLES LEDGER ══════════════ -->
<div class="pv-pane" id="pane-ledger">
  <form method="GET" class="pv-controls" id="ledgerFilterForm">
    <input type="hidden" name="tabkeep" value="ledger">
    <select name="month" class="form-control" style="max-width:130px" onchange="this.form.submit()">
      <?php for ($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$monthNames[$m]?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for ($y=2050;$y>=2023;$y--): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button type="button" class="btn-add-row" onclick="addRow('ledger')">+ Add Row</button>
  </form>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:110px">DATE</th>
          <th style="min-width:220px">NAME</th>
          <th style="min-width:120px">MONTHLY PAYMENT</th>
          <th style="min-width:110px">PAID</th>
          <th style="min-width:120px">REMAINING BAL.</th>
          <th style="min-width:150px">TERMS</th>
          <th style="min-width:130px">STATUS</th>
          <th style="min-width:200px">REMARKS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-ledger">
        <?php foreach ($ledgerRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input type="date" class="pvi" style="text-align:center" data-col="entry_date" value="<?=htmlspecialchars($r['entry_date'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td><input class="pvi txt" data-col="item_name" value="<?=htmlspecialchars($r['item_name'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="monthly_payment" value="<?=nz($r['monthly_payment'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="paid_amount" value="<?=nz($r['paid_amount'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="remaining_bal" value="<?=nz($r['remaining_bal'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td><input class="pvi txt" data-col="terms" value="<?=htmlspecialchars($r['terms'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td>
            <select class="pvi" data-col="status" onchange="rowChanged(this,'ledger')">
              <?php foreach (['PAID','PARTIAL PAYMENT','NOT YET PAID'] as $opt): ?>
              <option value="<?=$opt?>" <?=$r['status']===$opt?'selected':''?>><?=$opt?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'ledger')"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'ledger')">Update</button><button class="bdel" onclick="deleteRow(this,'ledger')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="tfl">TOTAL</td><td></td>
          <td id="tot-ledger-monthly_payment">—</td>
          <td id="tot-ledger-paid_amount">—</td>
          <td id="tot-ledger-remaining_bal">—</td>
          <td></td><td></td><td></td><td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 4: PDC / AMORTIZATION ══════════════ -->
<div class="pv-pane" id="pane-pdc">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">
      <?=count($pdcSections)?> section<?=count($pdcSections)===1?'':'s'?> — one card per obligation, just like PAYABLES.xlsx
    </div>
    <button type="button" class="btn-add-row" id="btn-add-section" onclick="addSection()">➕ Add Section</button>
  </div>

  <div class="pv-filter-bar">
    <input type="text" id="pdc-search" class="pv-search" placeholder="🔎 Search section…" oninput="filterSections('pdc-sections-scope', this.value)">
    <button type="button" class="btn-add-row sm" onclick="clearSectionFilter('pdc-sections-scope','pdc-search')">Show All</button>
  </div>
  <div class="pv-chips">
    <?php foreach ($pdcSections as $sec): ?>
    <div class="pv-chip" data-scope="pdc-sections-scope" data-name="<?=htmlspecialchars($sec['name'], ENT_QUOTES)?>" onclick="chipClick('pdc-sections-scope','pdc-search','<?=htmlspecialchars($sec['name'], ENT_QUOTES)?>')">
      <div class="pv-chip-name"><?=htmlspecialchars($sec['name'])?></div>
      <div class="pv-chip-total"><?=nz($sec['totBal']) ?: '0.00'?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$pdcSections): ?><div class="pv-chip-empty">No sections yet.</div><?php endif; ?>
  </div>

  <div id="pdc-sections-scope">
  <div class="pv-chip-empty" id="pdc-sections-scope-empty" style="display:none">No section matches that search.</div>
  <?php foreach ($pdcSections as $sec): $slug = $sec['slug']; ?>
  <div class="pdc-section" id="section-<?=$slug?>" data-section-name="<?=htmlspecialchars($sec['name'], ENT_QUOTES)?>">
    <div class="pdc-section-head">
      <div class="pdc-section-title"><?=htmlspecialchars($sec['name'])?></div>
      <div class="pdc-section-tools">
        <span class="pdc-section-total">Balance: <b><?=nz($sec['totBal']) ?: '0.00'?></b></span>
        <button type="button" class="btn-add-row sm" onclick="addRow('<?=$slug?>')">+ Add Row</button>
        <button type="button" class="bdel" title="Remove this section (only if empty)" onclick="deleteSection(<?=$sec['id']?>,'<?=$slug?>','<?=htmlspecialchars($sec['name'],ENT_QUOTES)?>')">🗑</button>
      </div>
    </div>
    <div class="pv-outer">
      <table class="pv-table">
        <thead>
          <tr>
            <th style="min-width:120px">DUE DATE</th>
            <th style="min-width:110px">AMOUNT</th>
            <th style="min-width:120px">BALANCE</th>
            <th style="min-width:110px">STATUS</th>
            <th style="min-width:200px">REMARKS</th>
            <th style="min-width:90px">ACTION</th>
          </tr>
        </thead>
        <tbody id="tbody-<?=$slug?>">
          <?php foreach ($sec['rows'] as $r): ?>
          <tr data-id="<?=$r['id']?>">
            <td><input type="date" class="pvi" style="text-align:center" data-col="due_date" value="<?=htmlspecialchars($r['due_date'])?>" oninput="rowChanged(this,'<?=$slug?>')"></td>
            <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'<?=$slug?>')"></td>
            <td><input type="number" step="0.01" class="pvi" data-col="balance" value="<?=nz($r['balance'])?>" oninput="rowChanged(this,'<?=$slug?>')"></td>
            <td>
              <select class="pvi" data-col="status" onchange="rowChanged(this,'<?=$slug?>')">
                <option value="PAID"     <?=$r['status']==='PAID'?'selected':''?>>Paid</option>
                <option value="NOT PAID" <?=$r['status']==='NOT PAID'?'selected':''?>>Not Paid</option>
              </select>
            </td>
            <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'<?=$slug?>')"></td>
            <td class="td-act"><button class="bsv" onclick="saveRow(this,'<?=$slug?>')">Update</button><button class="bdel" onclick="deleteRow(this,'<?=$slug?>')">Del</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td class="tfl">TOTAL</td><td id="tot-<?=$slug?>-amount">—</td><td id="tot-<?=$slug?>-balance">—</td><td></td><td></td><td></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <?php if (!$pdcSections): ?>
  <div class="pv-outer" style="padding:28px;text-align:center;font-family:var(--font-m);color:var(--subtext2)">
    No sections yet — click <b>+ Add Section</b> above to create your first one (e.g. Rental, Bank Loan, Incorporator CAPEX…).
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════ TAB 5: OVERVIEW (read-only, whole-module totals) ══════════════ -->
<div class="pv-pane" id="pane-overview">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Read-only — auto-calculated from all data in every tab, regardless of any month / type filter selected above.</div>
  </div>

  <div class="pv-cards">
    <div class="pv-card accent">
      <div class="lbl">Total Outstanding — All Categories</div>
      <div class="val"><?=nz($ovGrandOutstanding) ?: '0.00'?></div>
    </div>
    <div class="pv-card"><div class="lbl">Pending Releasing Payables</div><div class="val"><?=nz($ovReleasing['pending']) ?: '0.00'?></div></div>
    <div class="pv-card"><div class="lbl">Ledger Remaining Balance (All Months)</div><div class="val"><?=nz($ovLedger['remaining']) ?: '0.00'?></div></div>
    <div class="pv-card"><div class="lbl">PDC Balance (All Types)</div><div class="val"><?=nz($ovPdcTotals['bal']) ?: '0.00'?></div></div>
  </div>

  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:220px">TAB</th>
          <th style="min-width:150px">AMOUNT</th>
          <th style="min-width:150px">PAID / RELEASED</th>
          <th style="min-width:150px">REMAINING / BALANCE</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="pv-ro txt">Summary (Amount / Rental / UTZ)</td>
          <td class="pv-ro"><?=nz($ovSummary['amt'])?></td>
          <td class="pv-ro">Rental: <?=nz($ovSummary['rental']) ?: '0.00'?></td>
          <td class="pv-ro">UTZ: <?=nz($ovSummary['utz']) ?: '0.00'?></td>
        </tr>
        <tr>
          <td class="pv-ro txt">Payables Monitoring (Releasing)</td>
          <td class="pv-ro"><?=nz($ovReleasing['total'])?></td>
          <td class="pv-ro"><?=nz($ovReleasing['released'])?></td>
          <td class="pv-ro"><?=nz($ovReleasing['pending'])?></td>
        </tr>
        <tr>
          <td class="pv-ro txt">Payables Ledger (all months)</td>
          <td class="pv-ro"><?=nz($ovLedger['monthly'])?></td>
          <td class="pv-ro"><?=nz($ovLedger['paid'])?></td>
          <td class="pv-ro"><?=nz($ovLedger['remaining'])?></td>
        </tr>
        <tr>
          <td class="pv-ro txt">PDC / Amortization (all types)</td>
          <td class="pv-ro"><?=nz($ovPdcTotals['amt'])?></td>
          <td class="pv-ro">—</td>
          <td class="pv-ro"><?=nz($ovPdcTotals['bal'])?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="pv-controls" style="margin-top:6px">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">PDC / Amortization — by Obligation Type</div>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:180px">OBLIGATION TYPE</th>
          <th style="min-width:150px">AMOUNT</th>
          <th style="min-width:150px">BALANCE</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pdcSections as $sec): $row = $ovPdcByType[$sec['name']] ?? ['amt'=>0,'bal'=>0]; ?>
        <tr>
          <td class="pv-ro txt"><?=htmlspecialchars($sec['name'])?></td>
          <td class="pv-ro"><?=nz($row['amt'])?></td>
          <td class="pv-ro"><?=nz($row['bal'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="tfl">TOTAL</td>
          <td><?=nz($ovPdcTotals['amt']) ?: '—'?></td>
          <td><?=nz($ovPdcTotals['bal']) ?: '—'?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
// ── Column configs per section (drives generic add/save row-building) ──
const CONFIGS = {
  summary: {
    tbody: 'tbody-summary',
    cols: [
      {col:'item_name', type:'text'},
      {col:'amount', type:'number'},
      {col:'remarks', type:'text'},
      {col:'rental', type:'number'},
      {col:'utz', type:'number'},
    ],
    sums: ['amount','rental','utz'],
  },
  releasing: {
    tbody: 'tbody-releasing',
    cols: [
      {col:'release_date', type:'date'},
      {col:'description', type:'text'},
      {col:'amount', type:'number'},
      {col:'status', type:'select', options:[['pending','Pending'],['released','Released']]},
    ],
    sums: ['amount'],
  },
  ledger: {
    tbody: 'tbody-ledger',
    cols: [
      {col:'entry_date', type:'date'},
      {col:'item_name', type:'text'},
      {col:'monthly_payment', type:'number'},
      {col:'paid_amount', type:'number'},
      {col:'remaining_bal', type:'number'},
      {col:'terms', type:'text'},
      {col:'status', type:'select', options:[['PAID','PAID'],['PARTIAL PAYMENT','PARTIAL PAYMENT'],['NOT YET PAID','NOT YET PAID']]},
      {col:'remarks', type:'text'},
    ],
    sums: ['monthly_payment','paid_amount','remaining_bal'],
  },
  <?php foreach ($pdcSections as $sec): ?>
  <?=$sec['slug']?>: {
    tbody: 'tbody-<?=$sec['slug']?>',
    tbl: 'pdc',
    fixed: { obligation_type: <?=json_encode($sec['name'])?> },
    cols: [
      {col:'due_date', type:'date'},
      {col:'amount', type:'number'},
      {col:'balance', type:'number'},
      {col:'status', type:'select', options:[['PAID','Paid'],['NOT PAID','Not Paid']]},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount','balance'],
  },
  <?php endforeach; ?>
};

const PDC_SECTION_SLUGS = <?= json_encode(array_column($pdcSections, 'slug')) ?>;

// ── Tab switching ──
function switchTab(name) {
  document.querySelectorAll('.pv-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  document.querySelectorAll('.pv-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
}
<?php if (in_array($_GET['tabkeep'] ?? '', ['summary','monitoring','ledger','pdc','overview'])): ?>
switchTab('<?= $_GET['tabkeep'] ?>');
<?php endif; ?>

// ── Section filter boxes + search (PDC / Amortization tab) ──
// Typing in the search box, or clicking a section box, shows only the
// section(s) whose name matches — everything else is hidden. Clearing the
// box (or clicking "Show All") brings every section back.
function filterSections(scopeId, query) {
  const scope = document.getElementById(scopeId);
  if (!scope) return;
  const q = (query || '').trim().toLowerCase();
  let anyShown = false;
  scope.querySelectorAll('[data-section-name]').forEach(sec => {
    const show = !q || sec.dataset.sectionName.toLowerCase().includes(q);
    sec.style.display = show ? '' : 'none';
    if (show) anyShown = true;
  });
  const empty = document.getElementById(scopeId + '-empty');
  if (empty) empty.style.display = anyShown ? 'none' : 'block';
  document.querySelectorAll(`.pv-chip[data-scope="${scopeId}"]`).forEach(ch => {
    ch.classList.toggle('active', !!q && ch.dataset.name.toLowerCase() === q);
  });
}

function chipClick(scopeId, inputId, name) {
  const input = document.getElementById(inputId);
  if (input) input.value = name;
  filterSections(scopeId, name);
  const scope = document.getElementById(scopeId);
  const target = scope && scope.querySelector(`[data-section-name="${CSS.escape(name)}"]`);
  if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearSectionFilter(scopeId, inputId) {
  const input = document.getElementById(inputId);
  if (input) input.value = '';
  filterSections(scopeId, '');
}

function gv(row, col) {
  const el = row.querySelector(`[data-col="${col}"]`);
  if (!el) return 0;
  return el.tagName === 'SELECT' ? el.value : (parseFloat(el.value) || 0);
}

function recalcFooter(section) {
  const cfg = CONFIGS[section];
  cfg.sums.forEach(col => {
    const el = document.getElementById(`tot-${section}-${col}`);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`#${cfg.tbody} [data-col="${col}"]`).forEach(i => s += parseFloat(i.value) || 0);
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  });
  if (section === 'releasing') recalcCash();
}

function rowChanged(el, section) {
  const row = el.closest('tr');
  recalcFooter(section);
  const btn = row.querySelector('.bsv');
  if (btn) { btn.textContent = row.dataset.id ? 'Update' : 'Save'; btn.className = 'bsv'; }
}

let newRowCounter = 0;
function buildCell(col) {
  if (col.type === 'text') {
    return `<td><input class="pvi txt" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'number') {
    return `<td><input type="number" step="0.01" class="pvi" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'date') {
    return `<td><input type="date" class="pvi" style="text-align:center" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'select') {
    const opts = col.options.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
    return `<td><select class="pvi" data-col="${col.col}" onchange="rowChanged(this,'{{SECTION}}')">${opts}</select></td>`;
  }
  return '<td></td>';
}

function addRow(section) {
  const cfg = CONFIGS[section];
  const tbody = document.getElementById(cfg.tbody);
  const tr = document.createElement('tr');
  const tempId = 'new_' + (++newRowCounter);
  tr.id = tempId;
  tr.dataset.id = '';
  let h = '';
  cfg.cols.forEach(c => { h += buildCell(c).replaceAll('{{SECTION}}', section); });
  h += `<td class="td-act"><button class="bsv" onclick="saveRow(this,'${section}')">Save</button></td>`;
  tr.innerHTML = h;
  tbody.appendChild(tr);
  tr.querySelector('input,select')?.focus();
  recalcFooter(section);
}

async function saveRow(btn, section) {
  const cfg = CONFIGS[section];
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.textContent = '…'; btn.className = 'bsv saving';

  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  fd.append('tbl', (cfg && cfg.tbl) || section);
  if (id) fd.append('id', id);
  if (cfg && cfg.fixed) Object.entries(cfg.fixed).forEach(([k, v]) => fd.append(k, v));
  row.querySelectorAll('[data-col]').forEach(el => fd.append(el.dataset.col, el.value || ''));

  try {
    const res  = await fetch('demic_jaro_payables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsv ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        const delBtn = document.createElement('button');
        delBtn.className = 'bdel'; delBtn.textContent = 'Del';
        delBtn.onclick = function(){ deleteRow(this, section); };
        btn.parentElement.appendChild(delBtn);
      }
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
      showToast('✓ Saved', 'success');
    } else {
      btn.textContent = 'Error'; btn.className = 'bsv err';
      showToast('❌ ' + data.msg, 'error');
      setTimeout(() => { btn.textContent = id ? 'Update' : 'Save'; btn.className = 'bsv'; }, 3000);
    }
  } catch (e) {
    btn.textContent = 'Error'; btn.className = 'bsv err';
    showToast('❌ Network error', 'error');
  }
}

async function deleteRow(btn, section) {
  if (!confirm('Delete this row?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcFooter(section); return; }

  const cfg = CONFIGS[section];
  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('tbl', (cfg && cfg.tbl) || section);
  fd.append('id', id);
  try {
    const res  = await fetch('demic_jaro_payables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0'; row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcFooter(section); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

// ── Cash Position (computed cards) ──
function fmt(v) { return v === 0 ? '—' : v.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function recalcCash() {
  let totalReleasing = 0;
  document.querySelectorAll('#tbody-releasing [data-col="amount"]').forEach(i => totalReleasing += parseFloat(i.value) || 0);

  const secbank  = parseFloat(document.getElementById('snap-secbank').value)  || 0;
  const aub      = parseFloat(document.getElementById('snap-aub').value)      || 0;
  const hmo      = parseFloat(document.getElementById('snap-hmo').value)      || 0;
  const tenants  = parseFloat(document.getElementById('snap-tenants').value)  || 0;

  const available   = secbank - totalReleasing;
  const bankTotal    = secbank + aub;
  const receivables  = hmo + tenants;
  const net          = bankTotal + receivables - totalReleasing;

  document.getElementById('card-total-releasing').textContent = fmt(totalReleasing);
  document.getElementById('card-secbank').textContent         = fmt(secbank);
  document.getElementById('card-available').textContent       = fmt(available);
  document.getElementById('card-bank-total').textContent      = fmt(bankTotal);
  document.getElementById('card-receivables').textContent     = fmt(receivables);
  document.getElementById('card-net').textContent              = fmt(net);
}

async function saveSnapshot() {
  const fd = new FormData();
  fd.append('ajax_save_snapshot', '1');
  fd.append('as_of_date', document.getElementById('snap-date').value);
  fd.append('secbank_balance', document.getElementById('snap-secbank').value || '0');
  fd.append('aub_balance', document.getElementById('snap-aub').value || '0');
  fd.append('receivable_hmo', document.getElementById('snap-hmo').value || '0');
  fd.append('receivable_tenants', document.getElementById('snap-tenants').value || '0');
  try {
    const res  = await fetch('demic_jaro_payables.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.ok ? '✓ Cash position saved' : '❌ ' + data.msg, data.ok ? 'success' : 'error');
  } catch (e) { showToast('❌ Network error', 'error'); }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  ['summary','releasing','ledger', ...PDC_SECTION_SLUGS].forEach(recalcFooter);
  recalcCash();
});

// ── PDC sections: add / remove ──
async function addSection() {
  const name = prompt('New section name (e.g. "Bank Loan", "Incorporator 3 CAPEX"):');
  if (!name || !name.trim()) return;
  const btn = document.getElementById('btn-add-section');
  btn.disabled = true;
  const fd = new FormData();
  fd.append('ajax_add_section', '1');
  fd.append('name', name.trim());
  try {
    const res  = await fetch('demic_jaro_payables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast('✓ Section added', 'success');
      location.href = 'demic_jaro_payables.php?tabkeep=pdc';
    } else {
      showToast('❌ ' + data.msg, 'error');
      btn.disabled = false;
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
    btn.disabled = false;
  }
}

async function deleteSection(id, slug, name) {
  if (!confirm(`Remove the "${name}" section? This only works if it has no rows in it.`)) return;
  const fd = new FormData();
  fd.append('ajax_delete_section', '1');
  fd.append('id', id);
  try {
    const res  = await fetch('demic_jaro_payables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast('✓ Section removed', 'success');
      location.href = 'demic_jaro_payables.php?tabkeep=pdc';
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}
</script>