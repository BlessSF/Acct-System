<?php
// ============================================================
//  demic_purchases.php — DemicLab Purchases / Disbursement Ledger
//  Mirrors the "PURCHASES" sheet from DISBURSEMENT_DEMIC_2026.xlsx:
//  DATE, NAME OF SUPPLIER, DESCRIPTION, ADDRESS, O.R./C.R. NO.,
//  TOTAL AMOUNT (auto-sum of every category column), then 23
//  expense-category columns (Materials Purchases, Amortization
//  Expense, WTax Rent, Service Fee, Rent, Office Supplies,
//  Employees Benefit, Advertisement, Bio, Janitorial & Cleaning
//  Supplies, Freight Charge, Fare/Travel, Fuel/Oil, Repairs &
//  Maintenance, Communication, Electricity, Representation
//  Expense, Salary, Professional Fee, Management Fee, Cash
//  Advances, Misc, Other Equipment).
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only DemicLab branch and management can access
if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── AJAX: Vendor lookup (same autocomplete used elsewhere) ─
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendor_lookup'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (!$q) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT tin, company_name, address
                            FROM vendor_masterlist_unified
                            WHERE company_name LIKE ? OR tin LIKE ?
                            ORDER BY company_name ASC LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_purchases` (
    `id`                      int(11) NOT NULL AUTO_INCREMENT,
    `expense_date`            date NOT NULL,
    `supplier_name`           varchar(255) DEFAULT '',
    `description`             varchar(255) DEFAULT '',
    `address`                 varchar(255) DEFAULT '',
    `or_cr_no`                varchar(100) DEFAULT '',
    `total_amount`            decimal(12,2) DEFAULT 0.00,
    `materials_purchases`     decimal(12,2) DEFAULT 0.00,
    `amortization_expense`    decimal(12,2) DEFAULT 0.00,
    `wtax_rent`               decimal(12,2) DEFAULT 0.00,
    `service_fee`             decimal(12,2) DEFAULT 0.00,
    `rent`                    decimal(12,2) DEFAULT 0.00,
    `office_supplies`         decimal(12,2) DEFAULT 0.00,
    `employees_benefit`       decimal(12,2) DEFAULT 0.00,
    `advertisement`           decimal(12,2) DEFAULT 0.00,
    `bio`                     decimal(12,2) DEFAULT 0.00,
    `janitorial_cleaning`     decimal(12,2) DEFAULT 0.00,
    `freight_charge`          decimal(12,2) DEFAULT 0.00,
    `fare_travel`             decimal(12,2) DEFAULT 0.00,
    `fuel_oil`                decimal(12,2) DEFAULT 0.00,
    `repairs_maintenance`     decimal(12,2) DEFAULT 0.00,
    `communication`           decimal(12,2) DEFAULT 0.00,
    `electricity`             decimal(12,2) DEFAULT 0.00,
    `representation_expense`  decimal(12,2) DEFAULT 0.00,
    `salary`                  decimal(12,2) DEFAULT 0.00,
    `professional_fee`        decimal(12,2) DEFAULT 0.00,
    `management_fee`          decimal(12,2) DEFAULT 0.00,
    `cash_advances`           decimal(12,2) DEFAULT 0.00,
    `taxes_licenses`          decimal(12,2) DEFAULT 0.00,
    `sss_phic_hdmf`           decimal(12,2) DEFAULT 0.00,
    `misc`                    decimal(12,2) DEFAULT 0.00,
    `other_equipment`         decimal(12,2) DEFAULT 0.00,
    `saved_by`                varchar(100) DEFAULT NULL,
    `created_at`              timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`              timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// If this table already existed before taxes_licenses/sss_phic_hdmf were
// added, add them now rather than erroring out.
$existingPurchCols = $pdo->query("SHOW COLUMNS FROM `demic_purchases`")->fetchAll(PDO::FETCH_COLUMN);
foreach (['taxes_licenses', 'sss_phic_hdmf'] as $newCol) {
    if (!in_array($newCol, $existingPurchCols, true)) {
        $pdo->exec("ALTER TABLE `demic_purchases` ADD COLUMN `$newCol` decimal(12,2) DEFAULT 0.00 AFTER `cash_advances`");
    }
}

// ── Category columns (matches columns H:AD of the Excel sheet) ─
$CATS = [
    'materials_purchases'    => 'Materials Purchases (Consumables/CGS)',
    'amortization_expense'   => 'Amortization Expense',
    'wtax_rent'              => 'WTax Rent',
    'service_fee'            => 'Service Fee',
    'rent'                   => 'Rent',
    'office_supplies'        => 'Office Supplies',
    'employees_benefit'      => 'Employees Benefit',
    'advertisement'          => 'Advertisement',
    'bio'                    => 'Bio',
    'janitorial_cleaning'    => 'Janitorial & Cleaning Supplies',
    'freight_charge'         => 'Freight Charge',
    'fare_travel'            => 'Fare/Travel',
    'fuel_oil'               => 'Fuel/Oil',
    'repairs_maintenance'    => 'Repairs & Maintenance',
    'communication'          => 'Communication',
    'electricity'            => 'Electricity',
    'representation_expense' => 'Representation Expense',
    'salary'                 => 'Salary',
    'professional_fee'       => 'Professional Fee',
    'management_fee'         => 'Management Fee',
    'cash_advances'          => 'Cash Advances',
    'taxes_licenses'         => 'Taxes & Licenses',
    'sss_phic_hdmf'          => 'SSS/Phil/HDMF Contribution',
    'misc'                   => 'Misc',
    'other_equipment'        => 'Other Equipment',
];
$CAT_KEYS = array_keys($CATS);

$NUM_COLS = array_merge(['total_amount'], $CAT_KEYS);
$TXT_COLS = ['supplier_name','description','address','or_cr_no'];

// ── AJAX: Add new row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = ['expense_date' => $_POST['expense_date'] ?? date('Y-m-d')];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($NUM_COLS as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO demic_purchases (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    $sets = [];
    $vals = [];
    foreach ($TXT_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = trim($_POST[$f] ?? ''); }
    foreach ($NUM_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = (float)($_POST[$f] ?? 0); }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $sets[] = '`expense_date`=?'; $vals[] = $_POST['expense_date'] ?? date('Y-m-d');
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE demic_purchases SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM demic_purchases WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ──────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $stmt = $pdo->prepare("SELECT * FROM demic_purchases WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
    $stmt->execute([$csvYear, $csvMonth]);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab_Purchases_'.$monthNames[$csvMonth].'_'.$csvYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['DemicLab — Purchases / Disbursement Ledger']);
    fputcsv($out,[$monthNames[$csvMonth].' '.$csvYear]);
    fputcsv($out,[]);
    fputcsv($out, array_merge(
        ['DATE','NAME OF SUPPLIER','DESCRIPTION','ADDRESS','O.R./C.R. NO.','TOTAL AMOUNT'],
        array_values($CATS)
    ));
    foreach ($rows as $r) {
        $line = [
            date('d-M-Y',strtotime($r['expense_date'])),
            $r['supplier_name'], $r['description'], $r['address'], $r['or_cr_no'],
            $r['total_amount'],
        ];
        foreach ($CAT_KEYS as $c) $line[] = $r[$c];
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── Load rows ─────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM demic_purchases WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
$stmt->execute([$fYear, $fMonth]);
$rows = $stmt->fetchAll();

// ── Column totals for summary card ────────────────────────
$expCols = array_merge(['total_amount'], $CAT_KEYS);
$expTotals = [];
try {
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $expCols));
    $totStmt = $pdo->prepare("SELECT $selectParts FROM demic_purchases WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $totStmt->execute([$fYear, $fMonth]);
    $expTotals = $totStmt->fetch() ?: [];
} catch (Throwable $ignored) {}

$pageTitle  = 'DemicLab Purchases';
$activePage = 'demic_purchases';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.se-outer {
  width: 100%; overflow-x: auto; overflow-y: visible;
  border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.se-outer::-webkit-scrollbar { height: 8px; }
.se-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.se-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

.se-table { border-collapse: collapse; width: max-content; font-size: .68rem; table-layout: fixed; }

.se-table thead th {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .52rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  padding: 8px 5px; border: 1px solid #2d5480;
  white-space: normal; text-align: center; line-height: 1.3;
  position: sticky; top: 0; z-index: 20;
}
.se-table thead tr.grp-row th {
  font-size: .55rem; padding: 5px 6px;
  background: #162d4a; letter-spacing: .06em;
}

.th-date { position: sticky !important; left: 0; z-index: 30 !important; width: 80px; min-width: 80px; background: #162d4a !important; }
.th-act  { position: sticky !important; right: 0; z-index: 30 !important; width: 80px; min-width: 80px; background: #162d4a !important; }

.td-date {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb !important; box-shadow: 2px 0 5px rgba(0,0,0,.06);
  font-family: var(--font-m); font-size: .67rem;
  color: var(--accent); font-weight: 600;
  text-align: center; padding: 0 4px;
  white-space: nowrap; width: 80px; min-width: 80px;
}

.td-act {
  position: sticky; right: 0; z-index: 10;
  width: 80px; min-width: 80px;
  background: #f8f9fb !important;
  box-shadow: -2px 0 5px rgba(0,0,0,.06);
  text-align: center; padding: 3px 4px;
  border-left: 2px solid #d1d5db;
}

/* Column group colors */
.g-info   { background: #1e3a5f !important; }
.g-cat    { background: #3d1a00 !important; }

/* Cell background tints */
.c-info   { background: #f0f4ff; }
.c-cat    { background: #fff9f0; }
.c-total  { background: #f0fdf4; }

.col-txt-sm  { width: 90px;  min-width: 90px; }
.col-txt-md  { width: 140px; min-width: 140px; }
.col-txt-lg  { width: 200px; min-width: 200px; }
.col-num     { width: 92px;  min-width: 92px; }
.col-num-sm  { width: 82px;  min-width: 82px; }

.se-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.se-table tbody tr:hover td { filter: brightness(.97); }
.se-table td { border: 1px solid #e3e6ea; padding: 0; vertical-align: middle; }

.sei {
  width: 100%; padding: 5px 5px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-m); font-size: .67rem;
  text-align: right; display: block; box-sizing: border-box;
}
.sei:focus { background: rgba(15,123,92,.07); outline: 1px solid rgba(15,123,92,.4); }
.sei.txt   { text-align: left; font-family: var(--font-h); font-size: .67rem; }
.sei.calc  { color: #0f7b5c; background: rgba(15,123,92,.06); font-weight: 700; cursor: default; }

.se-table tfoot td {
  padding: 7px 5px; font-family: var(--font-m); font-size: .67rem; font-weight: 700;
  text-align: right; border: 1px solid #d1d5db;
  background: #1e3a5f; color: #fff;
}
.se-table tfoot td.tfl {
  text-align: center; font-size: .58rem;
  text-transform: uppercase; letter-spacing: .07em;
  position: sticky; left: 0; z-index: 5;
}
.se-table tfoot td.tfr { position: sticky; right: 0; z-index: 15; background: #1e3a5f !important; min-width: 80px; width: 80px; border-left: 2px solid #2d5480; }

.btn-add-row {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 16px; background: var(--accent); color: #fff;
  border: none; border-radius: 8px; font-size: .8rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #0a6649; transform: translateY(-1px); }

.bsv {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%; margin-bottom: 3px;
}
.bsv:hover { background: #dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok { background:#dcfce7; color:#15803d; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }

.bdel {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%;
}
.bdel:hover { background: #ffe4e6; }

.se-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  text-align: center; padding: 5px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

/* ── Vendor Autocomplete ── */
.ac-wrap { position: relative; width: 100%; }
.ac-list {
  display: none; position: absolute; top: 100%; left: 0; z-index: 999;
  background: #fff; border: 1px solid #c8d4df; border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  max-height: 220px; overflow-y: auto; min-width: 260px;
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
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">DemicLab <span style="color:var(--accent)">Purchases Ledger</span></div>
    <div class="section-subtitle">Each row is one disbursement · every category column auto-sums into TOTAL AMOUNT</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="se-controls" id="filterForm">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab
  </div>
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
  <button type="button" class="btn-add-row" onclick="addRow()">+ Add Row</button>
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="doExport(event)">⬇ Download CSV</a>
  <a href="demic_purchases_monitoring.php" class="btn btn-ghost btn-sm" style="color:#1e3a5f;border-color:rgba(30,58,95,.25);background:rgba(30,58,95,.06)">📊 Expense Monitoring</a>
</form>

<!-- ── Column Totals Summary ── -->
<div style="margin-bottom:20px">
  <div style="font-family:var(--font-m);font-size:.62rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:var(--subtext2);margin-bottom:10px">
    📊 Column Totals — <?= $monthNames[$fMonth] ?> <?= $fYear ?>
  </div>
  <div style="overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);
              box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:12px">
    <table style="border-collapse:collapse;width:max-content;min-width:100%">
      <thead>
        <tr>
          <td colspan="<?= count($CATS) + 1 ?>"
              style="background:#162d4a;color:rgba(255,255,255,.55);font-family:var(--font-m);
                     font-size:.54rem;text-transform:uppercase;letter-spacing:.1em;
                     padding:6px 14px;border-bottom:1px solid #2d5480">
            PURCHASES
          </td>
        </tr>
        <tr>
          <th style="background:#1a4a2e;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #2d5480;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">TOTAL AMOUNT</th>
          <?php foreach ($CATS as $col => $label): ?>
          <th style="background:#1e3a5f;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #2d5480;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">
            <?= htmlspecialchars($label) ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <?php
            $totVal = (float)($expTotals['total_amount'] ?? 0);
            $totZero = $totVal == 0;
          ?>
          <td style="background:#f0fdf4;border:1px solid #e3e6ea;padding:10px 12px;text-align:right;
                     font-family:var(--font-m);font-size:.74rem;color:var(--accent);font-weight:700;
                     white-space:nowrap;min-width:100px" id="sum_total_amount">
            <?= $totZero ? '<span style="color:var(--muted)">—</span>' : number_format($totVal, 2, '.', ',') ?>
          </td>
          <?php foreach ($CATS as $col => $label):
            $val    = (float)($expTotals[$col] ?? 0);
            $isZero = $val == 0;
          ?>
          <td style="background:<?= $isZero ? '#f8f9fb' : '#fff' ?>;border:1px solid #e3e6ea;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.74rem;
                     color:<?= $isZero ? 'var(--muted)' : 'var(--text)' ?>;font-weight:<?= $isZero ? '400' : '700' ?>;
                     white-space:nowrap;min-width:100px">
            <?= $isZero ? '<span style="color:var(--muted)">—</span>' : number_format($val, 2, '.', ',') ?>
          </td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Table -->
<div class="se-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="se-table" id="set">
  <thead>
    <!-- Group row -->
    <tr class="grp-row">
      <th class="th-date"></th>
      <th colspan="4" class="g-info">TRANSACTION INFO</th>
      <th colspan="1" style="background:#1a4a2e">TOTAL</th>
      <th colspan="<?= count($CATS) ?>" class="g-cat">PURCHASE CATEGORIES</th>
      <th class="th-act"></th>
    </tr>
    <!-- Column headers -->
    <tr>
      <th class="th-date" style="width:80px">DATE</th>
      <th class="g-info col-txt-lg">Name of Supplier</th>
      <th class="g-info col-txt-lg">Description</th>
      <th class="g-info col-txt-lg">Address</th>
      <th class="g-info col-txt-sm">O.R./C.R. No.</th>
      <th style="background:#1a4a2e;color:#fff" class="col-num">TOTAL AMOUNT</th>
      <?php foreach ($CATS as $col => $label): ?>
      <th class="g-cat col-num-sm"><?= htmlspecialchars($label) ?></th>
      <?php endforeach; ?>
      <th class="th-act">ACTION</th>
    </tr>
  </thead>

  <tbody id="se-tbody">
  <?php foreach ($rows as $r): ?>
  <?php $rid = 'row'.$r['id']; ?>
  <tr id="<?=$rid?>" data-id="<?=$r['id']?>">
    <?= renderRow($r, $rid, $CAT_KEYS) ?>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <!-- info cols: no totals -->
      <td></td><td></td><td></td><td></td>
      <td id="tot_total_amount">—</td>
      <?php foreach ($CAT_KEYS as $c): ?>
      <td id="tot_<?=$c?>">—</td>
      <?php endforeach; ?>
      <td class="tfr" style="background:#1e3a5f !important;min-width:80px;width:80px;border-left:2px solid #2d5480;"></td>
    </tr>
  </tfoot>
</table>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
  <button class="btn-add-row" onclick="addRow()">+ Add Row</button>
</div>

<?php
function renderRow(array $r, string $rid, array $catKeys): string {
    $d = function($k) use ($r) {
        $v = $r[$k] ?? '';
        if (is_numeric($v)) return (float)$v != 0 ? number_format((float)$v,2,'.','') : '';
        return htmlspecialchars($v);
    };
    $date = $r['expense_date'] ?? date('Y-m-d');

    $html  = '<td class="td-date">';
    $html .= '<input type="date" class="sei txt" style="width:100%;text-align:center;font-size:.62rem" data-col="expense_date" value="'.$date.'" oninput="rowChanged(this)">';
    $html .= '</td>';

    // Supplier (with vendor autocomplete)
    $html .= '<td class="c-info col-txt-lg"><div class="ac-wrap"><input type="text" class="sei txt" data-col="supplier_name" value="'.$d('supplier_name').'" placeholder="Type to search…" oninput="rowChanged(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>';
    // Description
    $html .= '<td class="c-info col-txt-lg"><input type="text" class="sei txt" data-col="description" value="'.$d('description').'" placeholder="—" oninput="rowChanged(this)"></td>';
    // Address
    $html .= '<td class="c-info col-txt-lg"><input type="text" class="sei txt" data-col="address" value="'.$d('address').'" placeholder="—" oninput="rowChanged(this)"></td>';
    // O.R./C.R. No.
    $html .= '<td class="c-info col-txt-sm"><input type="text" class="sei txt" data-col="or_cr_no" value="'.$d('or_cr_no').'" placeholder="—" oninput="rowChanged(this)"></td>';

    // Total Amount (auto-calc) — always recalculated by JS, never pre-populated from DB
    $html .= '<td class="c-total"><input type="number" step="0.01" class="sei calc" data-col="total_amount" value="" readonly tabindex="-1"></td>';

    // Category cols
    foreach ($catKeys as $col) {
        $html .= '<td class="c-cat"><input type="number" step="0.01" class="sei" data-col="'.$col.'" value="'.$d($col).'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    }

    // Actions
    $isNew = empty($r['id']);
    $html .= '<td class="td-act">';
    $html .= '<button class="bsv" onclick="saveRow(this)">'.($isNew?'Save':'Update').'</button>';
    if (!$isNew) {
        $html .= '<button class="bdel" onclick="deleteRow(this)">Del</button>';
    }
    $html .= '</td>';

    return $html;
}
?>

<script>
const CAT_COLS = <?= json_encode($CAT_KEYS) ?>;
const ALL_TOT_COLS = ['total_amount', ...CAT_COLS];

function gv(row, col) {
  const el = row.querySelector(`[data-col="${col}"]`);
  return el ? (parseFloat(el.value) || 0) : 0;
}

function recalcRow(row) {
  // Total Amount = sum of every category column (mirrors the Excel's F = SUM(H:AD) pattern)
  let total = 0;
  CAT_COLS.forEach(c => total += gv(row, c));
  const taEl = row.querySelector('[data-col="total_amount"]');
  if (taEl) taEl.value = total !== 0 ? total.toFixed(2) : '';
}

function recalcFooter() {
  ALL_TOT_COLS.forEach(col => {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`#set tbody [data-col="${col}"]`).forEach(i => s += parseFloat(i.value)||0);
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  });
  // Also update the Column Totals card up top (live, matches sticky footer)
  const sumEl = document.getElementById('sum_total_amount');
  if (sumEl) {
    let s = 0;
    document.querySelectorAll('#set tbody [data-col="total_amount"]').forEach(i => s += parseFloat(i.value)||0);
    sumEl.innerHTML = s === 0 ? '<span style="color:var(--muted)">—</span>' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  }
}

function rowChanged(el) {
  const row = el.closest('tr');
  recalcRow(row);
  recalcFooter();
  const btn = row.querySelector('.bsv');
  if (btn) { btn.textContent = row.dataset.id ? 'Update' : 'Save'; btn.className = 'bsv'; }
}

let newRowCounter = 0;
function addRow() {
  const tbody = document.getElementById('se-tbody');
  const tr = document.createElement('tr');
  const tempId = 'new_' + (++newRowCounter);
  tr.id = tempId;
  tr.dataset.id = '';

  const today = new Date().toISOString().slice(0,10);
  tr.innerHTML = buildNewRowHTML(today);
  tbody.appendChild(tr);
  tr.querySelector('[data-col="supplier_name"]')?.focus();
  recalcFooter();
}

function buildNewRowHTML(date) {
  let h = '';
  h += `<td class="td-date"><input type="date" class="sei txt" style="width:100%;text-align:center;font-size:.62rem" data-col="expense_date" value="${date}" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-info col-txt-lg"><div class="ac-wrap"><input type="text" class="sei txt" data-col="supplier_name" value="" placeholder="Type to search…" oninput="rowChanged(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>`;
  h += `<td class="c-info col-txt-lg"><input type="text" class="sei txt" data-col="description" value="" placeholder="—" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-info col-txt-lg"><input type="text" class="sei txt" data-col="address" value="" placeholder="—" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-info col-txt-sm"><input type="text" class="sei txt" data-col="or_cr_no" value="" placeholder="—" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-total"><input type="number" step="0.01" class="sei calc" data-col="total_amount" value="" readonly tabindex="-1"></td>`;
  CAT_COLS.forEach(col => {
    h += `<td class="c-cat"><input type="number" step="0.01" class="sei" data-col="${col}" value="" placeholder="0.00" oninput="rowChanged(this)"></td>`;
  });
  h += `<td class="td-act"><button class="bsv" onclick="saveRow(this)">Save</button></td>`;
  return h;
}

async function saveRow(btn) {
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.textContent = '…'; btn.className = 'bsv saving';

  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  if (id) fd.append('id', id);

  row.querySelectorAll('[data-col]').forEach(el => {
    fd.append(el.dataset.col, el.value || '0');
  });

  try {
    const res  = await fetch('demic_purchases.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsv ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        row.id = 'row' + data.id;
        const delBtn = document.createElement('button');
        delBtn.className = 'bdel';
        delBtn.textContent = 'Del';
        delBtn.onclick = function(){ deleteRow(this); };
        btn.parentElement.appendChild(delBtn);
      }
      setTimeout(() => { if(btn.className.includes('ok')) btn.className='bsv'; }, 2000);
      showToast('✓ Saved', 'success');
    } else {
      btn.textContent = 'Error'; btn.className = 'bsv err';
      showToast('❌ ' + data.msg, 'error');
      setTimeout(() => { btn.textContent='Save'; btn.className='bsv'; }, 3000);
    }
  } catch(e) {
    btn.textContent = 'Error'; btn.className = 'bsv err';
    showToast('❌ Network error', 'error');
  }
}

async function deleteRow(btn) {
  if (!confirm('Delete this purchase entry?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcFooter(); return; }

  const fd = new FormData();
  fd.append('ajax_delete','1');
  fd.append('id', id);
  try {
    const res  = await fetch('demic_purchases.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0';
      row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcFooter(); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch(e) { showToast('❌ Network error','error'); }
}

function doExport(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv','1');
  window.location.href = 'demic_purchases.php?' + params.toString();
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// Init: recalc all rows and footer on load
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#set tbody tr[data-id]').forEach(r => recalcRow(r));
  recalcFooter();
});

// ── Vendor Autocomplete ────────────────────────────────────
let acTimer = null;

function triggerAC(inp) {
  clearTimeout(acTimer);
  acTimer = setTimeout(() => doAC(inp), 220);
}

async function doAC(inp) {
  const q = inp.value.trim();
  const list = inp.closest('.ac-wrap')?.querySelector('.ac-list');
  if (!list) return;
  if (q.length < 2) { list.classList.remove('open'); return; }
  try {
    const res  = await fetch(location.pathname + '?vendor_lookup=1&q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.length) {
      list.innerHTML = '<div class="ac-item" style="color:#6b7280;cursor:default"><div class="ac-name">No vendor found</div><div class="ac-tin">Add to Vendor Masterlist first</div></div>';
      list.classList.add('open'); return;
    }
    list.innerHTML = data.map(v => `
      <div class="ac-item" data-name="${escHtml(v.company_name)}" data-addr="${escHtml(v.address)}" onclick="fillVendor(this)">
        <div class="ac-name">${escHtml(v.company_name)}</div>
        ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
      </div>`).join('');
    list.classList.add('open');
  } catch(e) {}
}

function fillVendor(item) {
  const row = item.closest('tr');
  row.querySelector('[data-col="supplier_name"]').value = item.dataset.name;
  row.querySelector('[data-col="address"]').value        = item.dataset.addr;
  document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
  rowChanged(row.querySelector('[data-col="supplier_name"]'));
  row.querySelector('[data-col="description"]')?.focus();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close dropdowns when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});
</script>
</body>
</html>