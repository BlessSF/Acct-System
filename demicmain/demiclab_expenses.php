<?php
// ============================================================
//  demiclab_expenses.php — DemicLab-Main Branch Expenses Ledger
//  Mirrors the Excel expense sheet columns exactly
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

// ── AJAX: Vendor lookup (same as disbursement) ────────────
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


// ── Auto-create table ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_expenses` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `expense_date`          date NOT NULL,
    `voucher_no`            varchar(100) DEFAULT '',
    `tin`                   varchar(100) DEFAULT '',
    `company_name`          varchar(255) DEFAULT '',
    `address`               varchar(255) DEFAULT '',
    `particulars`           varchar(255) DEFAULT '',
    `document_type`         varchar(100) DEFAULT '',
    `document_no`           varchar(100) DEFAULT '',
    `amount_w_vat`          decimal(12,2) DEFAULT 0.00,
    `vat`                   decimal(12,2) DEFAULT 0.00,
    `amount_wo_vat`         decimal(12,2) DEFAULT 0.00,
    `non_vat`               decimal(12,2) DEFAULT 0.00,
    `total_amount`          decimal(12,2) DEFAULT 0.00,
    `purchases`             decimal(12,2) DEFAULT 0.00,
    `salaries`              decimal(12,2) DEFAULT 0.00,
    `rent`                  decimal(12,2) DEFAULT 0.00,
    `medicine`              decimal(12,2) DEFAULT 0.00,
    `lpg`                   decimal(12,2) DEFAULT 0.00,
    `repairs_maintenance`   decimal(12,2) DEFAULT 0.00,
    `fuel_trans`            decimal(12,2) DEFAULT 0.00,
    `communication`         decimal(12,2) DEFAULT 0.00,
    `transportation`        decimal(12,2) DEFAULT 0.00,
    `light`                 decimal(12,2) DEFAULT 0.00,
    `drinking_water`        decimal(12,2) DEFAULT 0.00,
    `water`                 decimal(12,2) DEFAULT 0.00,
    `sss_phic_hdmf`         decimal(12,2) DEFAULT 0.00,
    `taxes_licences`        decimal(12,2) DEFAULT 0.00,
    `office_supplies`       decimal(12,2) DEFAULT 0.00,
    `kitchen_supplies`      decimal(12,2) DEFAULT 0.00,
    `bio_pest_control`      decimal(12,2) DEFAULT 0.00,
    `representation`        decimal(12,2) DEFAULT 0.00,
    `miscellaneous`         decimal(12,2) DEFAULT 0.00,
    `sir_budoy_nikki`       decimal(12,2) DEFAULT 0.00,
    `staff_meal`            decimal(12,2) DEFAULT 0.00,
    `pest_control_bio_aug`  decimal(12,2) DEFAULT 0.00,
    `commission_fees`       decimal(12,2) DEFAULT 0.00,
    `exhaust_cleaning`      decimal(12,2) DEFAULT 0.00,
    `bank_fees`             decimal(12,2) DEFAULT 0.00,
    `admin_salary_shares`   decimal(12,2) DEFAULT 0.00,
    `marketing`             decimal(12,2) DEFAULT 0.00,
    `sales_discounts`       decimal(12,2) DEFAULT 0.00,
    `pdc`                   decimal(12,2) DEFAULT 0.00,
    `ca`                    decimal(12,2) DEFAULT 0.00,
    `withdrawal`            decimal(12,2) DEFAULT 0.00,
    `depreciation_expense`  decimal(12,2) DEFAULT 0.00,
    `row_total`             decimal(12,2) DEFAULT 0.00,
    `saved_by`              varchar(100) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Add new columns if they don't exist yet (safe migration) ─
foreach (['pdc','ca','withdrawal','depreciation_expense'] as $newCol) {
    try {
        $pdo->exec("ALTER TABLE `demiclab_expenses` ADD COLUMN `$newCol` decimal(12,2) DEFAULT 0.00 AFTER `sales_discounts`");
    } catch (Throwable $ignored) {} // column already exists — ignore
}
foreach (['pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares'] as $newCol) {
    try {
        $pdo->exec("ALTER TABLE `demiclab_expenses` ADD COLUMN `$newCol` decimal(12,2) DEFAULT 0.00 AFTER `staff_meal`");
    } catch (Throwable $ignored) {} // column already exists — ignore
}

// ── Numeric columns list ──────────────────────────────────
$NUM_COLS = [
    'amount_w_vat','vat','amount_wo_vat','non_vat','total_amount',
    'purchases','salaries','rent','medicine','lpg','repairs_maintenance',
    'fuel_trans','communication','transportation','light','drinking_water',
    'water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies',
    'bio_pest_control','representation','miscellaneous','sir_budoy_nikki',
    'staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts',
    'pdc','ca','withdrawal','depreciation_expense','row_total'
];
$TXT_COLS = ['voucher_no','tin','company_name','address','particulars','document_type','document_no'];

// ── AJAX: Add new row ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = ['expense_date' => $_POST['expense_date'] ?? date('Y-m-d')];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($NUM_COLS as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO demiclab_expenses (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update row ──────────────────────────────────────
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
        $pdo->prepare("UPDATE demiclab_expenses SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM demiclab_expenses WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $stmt = $pdo->prepare("SELECT * FROM demiclab_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
    $stmt->execute([$csvYear, $csvMonth]);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab-Main_Expenses_'.$monthNames[$csvMonth].'_'.$csvYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['DemicLab-Main Branch — Expenses Ledger']);
    fputcsv($out,[$monthNames[$csvMonth].' '.$csvYear]);
    fputcsv($out,[]);
    fputcsv($out,['DATE','VOUCHER NO.','TIN','Company Name','Address','Particulars/Description','Document','Document No.','Amount w/ VAT','VAT','Amount w/out VAT','Non Vat','TOTAL AMOUNT','Purchases','Salaries','Rent','Medicine','LPG','Repairs & Maintenance','Fuel & Trans','Communication','Transportation/Delivery fee','Light','Drinking Water','Water','SSS/PHIC/HDMF','Taxes & Licences','Office Supplies','Kitchen Supplies','Bio Aug./Pest Control','Representation','Miscellaneous','Sir Budoy/M\'Nikki','Staff Meal','Pest Control, Bio Aug','Commission Fees','Exhaust Cleaning','Bank Fees','Admin Salary Shares','Marketing','Sales Discounts','PDC','CA','Withdrawal','Depreciation Expense','TOTAL']);
    foreach ($rows as $r) {
        fputcsv($out,[
            date('d-M-Y',strtotime($r['expense_date'])),
            $r['voucher_no'],$r['tin'],$r['company_name'],$r['address'],
            $r['particulars'],$r['document_type'],$r['document_no'],
            $r['amount_w_vat'],$r['vat'],$r['amount_wo_vat'],$r['non_vat'],$r['total_amount'],
            $r['purchases'],$r['salaries'],$r['rent'],$r['medicine'],$r['lpg'],
            $r['repairs_maintenance'],$r['fuel_trans'],$r['communication'],
            $r['transportation'],$r['light'],$r['drinking_water'],$r['water'],
            $r['sss_phic_hdmf'],$r['taxes_licences'],$r['office_supplies'],
            $r['kitchen_supplies'],$r['bio_pest_control'],$r['representation'],
            $r['miscellaneous'],$r['sir_budoy_nikki'],$r['staff_meal'],
            $r['pest_control_bio_aug'],$r['commission_fees'],$r['exhaust_cleaning'],
            $r['bank_fees'],$r['admin_salary_shares'],
            $r['marketing'],$r['sales_discounts'],
            $r['pdc'],$r['ca'],$r['withdrawal'],$r['depreciation_expense'],
            $r['row_total'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── Load rows ─────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM demiclab_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
$stmt->execute([$fYear, $fMonth]);
$rows = $stmt->fetchAll();

// ── Column totals for summary card ────────────────────────
$expCols = [
    'amount_w_vat','vat','amount_wo_vat','non_vat','total_amount',
    'purchases','salaries','rent','medicine','lpg','repairs_maintenance',
    'fuel_trans','communication','transportation','light','drinking_water',
    'water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies',
    'bio_pest_control','representation','miscellaneous','sir_budoy_nikki',
    'staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts',
    'pdc','ca','withdrawal','depreciation_expense','row_total'
];
$expTotals = [];
try {
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $expCols));
    $totStmt = $pdo->prepare("SELECT $selectParts FROM demiclab_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $totStmt->execute([$fYear, $fMonth]);
    $expTotals = $totStmt->fetch() ?: [];
} catch (Throwable $ignored) {}
$ef = fn($k) => isset($expTotals[$k]) ? number_format((float)$expTotals[$k], 2, '.', ',') : '0.00';

$pageTitle  = 'DemicLab-Main Expenses';
$activePage = 'demiclab_expenses';
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

.c-check-cell { position: sticky; right: 80px; z-index: 9; background: #fff; }
.se-table tbody tr:nth-child(even) .c-check-cell { background: #f8fbff; }
.se-table tbody tr:hover .c-check-cell { background: #eef4ff !important; }
.se-table tfoot .c-check-cell { background: #1e3a5f !important; color: #4ade80; }
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
.g-vat    { background: #1a4a2e !important; }
.g-cat    { background: #3d1a00 !important; }

/* Cell background tints */
.c-info   { background: #f0f4ff; }
.c-vat    { background: #f0fdf4; }
.c-cat    { background: #fff9f0; }
.c-total  { background: #fffbeb; }

.col-txt-sm  { width: 80px;  min-width: 80px; }
.col-txt-md  { width: 140px; min-width: 140px; }
.col-txt-lg  { width: 190px; min-width: 190px; }
.col-num     { width: 88px;  min-width: 88px; }
.col-num-sm  { width: 78px;  min-width: 78px; }

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
.sei.calc  { color: #1d4ed8; background: rgba(37,99,235,.04); font-weight: 700; cursor: default; }
.sei.bold  { font-weight: 700; color: #0f7b5c; }

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
    <div class="section-title" style="color:#1a1d23">DemicLab-Main Branch <span style="color:var(--accent)">Expenses Ledger</span></div>
    <div class="section-subtitle">Each row is one expense entry · all category columns auto-sum into TOTAL</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="se-controls" id="filterForm">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab-Main
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
</form>

<!-- ── Expenses Column Totals Summary ── -->
<?php
$summaryGroups = [
  'VAT BREAKDOWN' => [
    'amount_w_vat'   => 'Amount w/ VAT',
    'vat'            => 'VAT',
    'amount_wo_vat'  => 'Amount w/out VAT',
    'non_vat'        => 'Non VAT',
    'total_amount'   => 'TOTAL AMOUNT',
    'total_purchases_vat' => 'Total Purchases w/ VAT',
  ],
  'EXPENSE CATEGORIES' => [
    'purchases'           => 'Purchases',
    'salaries'            => 'Salaries',
    'rent'                => 'Rent',
    'medicine'            => 'Medicine',
    'lpg'                 => 'LPG',
    'repairs_maintenance' => 'Repairs & Maintenance',
    'fuel_trans'          => 'Fuel & Trans',
    'communication'       => 'Communication',
    'transportation'      => 'Transportation / Delivery fee',
    'light'               => 'Light',
    'drinking_water'      => 'Drinking Water',
    'water'               => 'Water',
    'sss_phic_hdmf'       => 'SSS / PHIC / HDMF',
    'taxes_licences'      => 'Taxes & Licences',
    'office_supplies'     => 'Office Supplies',
    'kitchen_supplies'    => 'Kitchen Supplies',
    'bio_pest_control'    => 'Bio Aug. / Pest Control',
    'representation'      => 'Representation',
    'miscellaneous'       => 'Miscellaneous',
    'sir_budoy_nikki'     => "Sir Budoy / M'Nikki",
    'staff_meal'          => 'Staff Meal',
    'pest_control_bio_aug'=> 'Pest Control, Bio Aug',
    'commission_fees'     => 'Commission Fees',
    'exhaust_cleaning'    => 'Exhaust Cleaning',
    'bank_fees'           => 'Bank Fees',
    'admin_salary_shares' => 'Admin Salary Shares',
    'marketing'           => 'Marketing',
    'sales_discounts'     => 'Sales Discounts',
    'pdc'                 => 'PDC',
    'ca'                  => 'CA',
    'withdrawal'          => 'Withdrawal',
    'depreciation_expense'=> 'Depreciation Expense',
    'row_total'           => 'ROW TOTAL',
  ],
];
?>
<div style="margin-bottom:20px">
  <div style="font-family:var(--font-m);font-size:.62rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:var(--subtext2);margin-bottom:10px">
    📊 Column Totals — <?= $monthNames[$fMonth] ?> <?= $fYear ?>
  </div>

  <?php foreach ($summaryGroups as $groupName => $cols): ?>
  <div style="overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);
              box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:12px">
    <table style="border-collapse:collapse;width:max-content;min-width:100%">
      <thead>
        <tr>
          <td colspan="<?= count($cols) ?>"
              style="background:#162d4a;color:rgba(255,255,255,.55);font-family:var(--font-m);
                     font-size:.54rem;text-transform:uppercase;letter-spacing:.1em;
                     padding:6px 14px;border-bottom:1px solid #2d5480">
            <?= $groupName ?>
          </td>
        </tr>
        <tr>
          <?php foreach ($cols as $col => $label):
            $isTotal = in_array($col, ['total_amount','row_total','total_purchases_vat']); ?>
          <th style="background:<?= $isTotal ? '#1a4a2e' : '#1e3a5f' ?>;color:#fff;
                     font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;
                     padding:8px 10px;border:1px solid #2d5480;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">
            <?= htmlspecialchars($label) ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <?php foreach ($cols as $col => $label):
            $val     = $col === 'total_purchases_vat'
                       ? (float)($expTotals['vat'] ?? 0) + (float)($expTotals['purchases'] ?? 0)
                       : (float)($expTotals[$col] ?? 0);
            $isTotal = in_array($col, ['total_amount','row_total','total_purchases_vat']);
            $isZero  = $val == 0;
            $bg      = $isTotal ? '#f0fdf4' : ($isZero ? '#f8f9fb' : '#fff');
            $color   = $isTotal ? 'var(--accent)' : ($isZero ? 'var(--muted)' : 'var(--text)');
            $fw      = ($isTotal || !$isZero) ? '700' : '400';
          ?>
          <td <?= $col === 'total_purchases_vat' ? 'id="sum_total_purchases_vat"' : '' ?>
              style="background:<?= $bg ?>;border:1px solid #e3e6ea;
                     padding:10px 12px;text-align:right;
                     font-family:var(--font-m);font-size:.74rem;
                     color:<?= $color ?>;font-weight:<?= $fw ?>;
                     white-space:nowrap;min-width:100px">
            <?= $isZero ? '<span style="color:var(--muted)">—</span>' : number_format($val, 2, '.', ',') ?>
          </td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="se-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="se-table" id="set">
  <thead>
    <!-- Group row -->
    <tr class="grp-row">
      <th class="th-date"></th>
      <th colspan="7" class="g-info">TRANSACTION INFO</th>
      <th colspan="4" class="g-vat">VAT BREAKDOWN</th>
      <th colspan="1" style="background:#2d5480">TOTAL</th>
      <th colspan="32" class="g-cat">EXPENSE CATEGORIES</th>
      <th colspan="1" style="background:#2d5480">TOTAL</th>
      <th colspan="1" style="background:#374151">CHECK</th>
      <th class="th-act"></th>
    </tr>
    <!-- Column headers -->
    <tr>
      <th class="th-date" style="width:80px">DATE</th>
      <!-- Transaction Info -->
      <th class="g-info col-txt-sm">VOUCHER NO.</th>
      <th class="g-info col-txt-sm">TIN</th>
      <th class="g-info col-txt-lg">Company Name</th>
      <th class="g-info col-txt-lg">Address</th>
      <th class="g-info col-txt-lg">Particulars / Description</th>
      <th class="g-info col-txt-md">Document</th>
      <th class="g-info col-txt-md">Document No.</th>
      <!-- VAT -->
      <th class="g-vat col-num">Amount w/ VAT</th>
      <th class="g-vat col-num">VAT</th>
      <th class="g-vat col-num">Amount w/out VAT</th>
      <th class="g-vat col-num">Non Vat</th>
      <!-- Total Amount -->
      <th style="background:#1a4a2e;color:#fff" class="col-num">TOTAL AMOUNT</th>
      <!-- Categories -->
      <th class="g-cat col-num-sm">Purchases</th>
      <th class="g-cat col-num-sm">Salaries</th>
      <th class="g-cat col-num-sm">Rent</th>
      <th class="g-cat col-num-sm">Medicine</th>
      <th class="g-cat col-num-sm">LPG</th>
      <th class="g-cat col-num-sm">Repairs &amp; Maintenance</th>
      <th class="g-cat col-num-sm">Fuel &amp; Trans</th>
      <th class="g-cat col-num-sm">Communication</th>
      <th class="g-cat col-num-sm">Transportation / Delivery fee</th>
      <th class="g-cat col-num-sm">Light</th>
      <th class="g-cat col-num-sm">Drinking Water</th>
      <th class="g-cat col-num-sm">Water</th>
      <th class="g-cat col-num-sm">SSS / PHIC / HDMF</th>
      <th class="g-cat col-num-sm">Taxes &amp; Licences</th>
      <th class="g-cat col-num-sm">Office Supplies</th>
      <th class="g-cat col-num-sm">Kitchen Supplies</th>
      <th class="g-cat col-num-sm">Bio Aug. / Pest Control</th>
      <th class="g-cat col-num-sm">Representation</th>
      <th class="g-cat col-num-sm">Miscellaneous</th>
      <th class="g-cat col-num-sm">Sir Budoy / M'Nikki</th>
      <th class="g-cat col-num-sm">Staff Meal</th>
      <th class="g-cat col-num-sm">Pest Control, Bio Aug</th>
      <th class="g-cat col-num-sm">Commission Fees</th>
      <th class="g-cat col-num-sm">Exhaust Cleaning</th>
      <th class="g-cat col-num-sm">Bank Fees</th>
      <th class="g-cat col-num-sm">Admin Salary Shares</th>
      <th class="g-cat col-num-sm">Marketing</th>
      <th class="g-cat col-num-sm">Sales Discounts</th>
      <th class="g-cat col-num-sm">PDC</th>
      <th class="g-cat col-num-sm">CA</th>
      <th class="g-cat col-num-sm">Withdrawal</th>
      <th class="g-cat col-num-sm">Depreciation Expense</th>
      <!-- Row Total -->
      <th style="background:#1a4a2e;color:#fff" class="col-num">TOTAL</th>
      <th style="background:#374151;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:8px 5px;border:1px solid #4b5563;white-space:nowrap;text-align:center;min-width:80px" class="col-num">CHECK<br><span style="font-size:.45rem;opacity:.7;font-weight:400">TOTAL − TOTAL AMT</span></th>
      <th class="th-act">ACTION</th>
    </tr>
  </thead>

  <tbody id="se-tbody">
  <?php foreach ($rows as $r): ?>
  <?php $rid = 'row'.$r['id']; ?>
  <tr id="<?=$rid?>" data-id="<?=$r['id']?>">
    <?= renderRow($r, $rid) ?>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <!-- info cols: no totals -->
      <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
      <!-- vat totals -->
      <?php foreach(['amount_w_vat','vat','amount_wo_vat','non_vat','total_amount'] as $c): ?>
      <td id="tot_<?=$c?>">—</td>
      <?php endforeach; ?>
      <!-- category totals -->
      <?php foreach(['purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense','row_total'] as $c): ?>
      <td id="tot_<?=$c?>">—</td>
      <?php endforeach; ?>
      <td id="tot_check" class="c-check-cell" style="position:sticky;right:80px;z-index:15;background:#1e3a5f !important;font-family:var(--font-m);font-size:.67rem;font-weight:800;text-align:right;padding:7px 8px;border-left:1px solid #2d5480;min-width:80px">—</td>
      <td class="tfr" style="background:#1e3a5f !important;min-width:80px;width:80px;border-left:2px solid #2d5480;"></td>
    </tr>
  </tfoot>
</table>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
  <button class="btn-add-row" onclick="addRow()">+ Add Row</button>
</div>

<?php
function renderRow(array $r, string $rid): string {
    $d = function($k) use ($r) {
        $v = $r[$k] ?? '';
        if (is_numeric($v)) return (float)$v != 0 ? number_format((float)$v,2,'.','') : '';
        return htmlspecialchars($v);
    };
    $date = $r['expense_date'] ?? date('Y-m-d');
    $dateDisp = $date ? date('d-M', strtotime($date)) : '';

    $html  = '<td class="td-date">';
    $html .= '<input type="date" class="sei txt" style="width:100%;text-align:center;font-size:.62rem" data-col="expense_date" value="'.$date.'" oninput="rowChanged(this)">';
    $html .= '</td>';

    // Text cols
    $txtCols = ['voucher_no'=>'c-info col-txt-sm','tin'=>'c-info col-txt-sm','company_name'=>'c-info col-txt-lg','address'=>'c-info col-txt-lg','particulars'=>'c-info col-txt-lg','document_type'=>'c-info col-txt-md','document_no'=>'c-info col-txt-md'];
    foreach ($txtCols as $col => $cls) {
        if ($col === 'tin' || $col === 'company_name') {
            $ph = $col === 'company_name' ? 'Type to search…' : '—';
            $html .= '<td class="'.$cls.'"><div class="ac-wrap"><input type="text" class="sei txt" data-col="'.$col.'" value="'.$d($col).'" placeholder="'.$ph.'" oninput="rowChanged(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>';
        } else {
            $html .= '<td class="'.$cls.'"><input type="text" class="sei txt" data-col="'.$col.'" value="'.$d($col).'" placeholder="—" oninput="rowChanged(this)"></td>';
        }
    }

    // Amount w/ VAT — manual input only
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei" data-col="amount_w_vat" value="'.$d('amount_w_vat').'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    // VAT = Amount w/ VAT / 1.12 * 0.12  (auto-calc — always recalculated by JS)
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="vat" value="" readonly tabindex="-1"></td>';
    // Amount w/out VAT = Amount w/ VAT - VAT  (auto-calc — always recalculated by JS)
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="amount_wo_vat" value="" readonly tabindex="-1"></td>';
    // Non Vat — manual input
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei" data-col="non_vat" value="'.$d('non_vat').'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    // Total Amount = Amount w/out VAT + Non Vat  (auto-calc — always recalculated by JS)
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="total_amount" value="" readonly tabindex="-1"></td>';

    // Category cols
    $cats = ['purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense'];
    foreach ($cats as $col) {
        $html .= '<td class="c-cat"><input type="number" step="0.01" class="sei" data-col="'.$col.'" value="'.$d($col).'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    }

    // Row total (auto-calc) — always recalculated by JS, never pre-populated from DB
    $html .= '<td class="c-total"><input type="number" step="0.01" class="sei calc bold" data-col="row_total" value="" readonly tabindex="-1"></td>';

    // Check cell (TOTAL - TOTAL AMOUNT) — auto-calc by JS
    // Actions (CHECK + buttons merged into single sticky cell)
    $isNew = empty($r['id']);
    // Check cell
    $html .= '<td class="c-check-cell" style="position:sticky;right:80px;z-index:9;min-width:80px;text-align:right;padding:6px 8px;border:1px solid #e3e6ea;background:#fff"><span class="chk-val" style="font-family:var(--font-m);font-size:.72rem;font-weight:700">—</span></td>';

    // Actions
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
const CAT_COLS = ['purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense']; // all included in TOTAL
const VAT_COLS = ['amount_w_vat','vat','amount_wo_vat','non_vat'];
const ALL_TOT_COLS = ['amount_w_vat','vat','amount_wo_vat','non_vat','total_amount','purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense','row_total'];

function gv(row, col) {
  const el = row.querySelector(`[data-col="${col}"]`);
  return el ? (parseFloat(el.value) || 0) : 0;
}

function recalcRow(row) {
  const awv = gv(row,'amount_w_vat');
  const nv  = gv(row,'non_vat');

  // VAT = Amount w/ VAT / 1.12 * 0.12  (=J12/1.12*0.12)
  const vat = awv / 1.12 * 0.12;
  const vatEl = row.querySelector('[data-col="vat"]');
  if (vatEl) vatEl.value = awv ? vat.toFixed(2) : '';

  // Amount w/out VAT = Amount w/ VAT - VAT  (=J12-K12)
  const awov = awv - vat;
  const awovEl = row.querySelector('[data-col="amount_wo_vat"]');
  if (awovEl) awovEl.value = awv ? awov.toFixed(2) : '';

  // Total Amount = Amount w/out VAT + Non Vat  (=M12+L12)
  const totalAmount = awov + nv;
  const taEl = row.querySelector('[data-col="total_amount"]');
  if (taEl) taEl.value = (awov || nv) ? totalAmount.toFixed(2) : '';

  // Row total = sum of all category columns
  let rowTotal = 0;
  CAT_COLS.forEach(c => rowTotal += gv(row, c));
  const rtEl = row.querySelector('[data-col="row_total"]');
  if (rtEl) rtEl.value = rowTotal !== 0 ? rowTotal.toFixed(2) : '';

  // CHECK = TOTAL (row_total) - TOTAL AMOUNT (total_amount)
  // Formula mirrors Excel: =AM12-N12
  // Green 0.00 if equal, Red + difference if not
  const checkEl = row.querySelector('.chk-val');
  if (checkEl) {
    const totalAmt = parseFloat(row.querySelector('[data-col="total_amount"]')?.value) || 0;
    const diff = Math.round((rowTotal - totalAmt) * 100) / 100;
    if (diff === 0) {
      checkEl.textContent = '0.00';
      checkEl.style.color = '#15803d';
    } else {
      checkEl.textContent = (diff > 0 ? '+' : '') + diff.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
      checkEl.style.color = '#be123c';
    }
  }
}

function recalcFooter() {
  ALL_TOT_COLS.forEach(col => {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`#set tbody [data-col="${col}"]`).forEach(i => s += parseFloat(i.value)||0);
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  });
  // Footer CHECK = total row_total - total total_amount
  const totCheck = document.getElementById('tot_check');
  if (totCheck) {
    let sumRowTotal = 0, sumTotalAmt = 0;
    document.querySelectorAll('#set tbody [data-col="row_total"]').forEach(i => sumRowTotal += parseFloat(i.value)||0);
    document.querySelectorAll('#set tbody [data-col="total_amount"]').forEach(i => sumTotalAmt += parseFloat(i.value)||0);
    const diff = Math.round((sumRowTotal - sumTotalAmt) * 100) / 100;
    if (diff === 0) {
      totCheck.textContent = '0.00';
      totCheck.style.color = '#4ade80';
    } else {
      totCheck.textContent = (diff > 0 ? '+' : '') + diff.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
      totCheck.style.color = '#f87171';
    }
  }
  // Total Purchases w/ VAT = total VAT + total Purchases (live, in the Column Totals card up top)
  const tpvEl = document.getElementById('sum_total_purchases_vat');
  if (tpvEl) {
    let sumVat = 0, sumPurchases = 0;
    document.querySelectorAll('#set tbody [data-col="vat"]').forEach(i => sumVat += parseFloat(i.value)||0);
    document.querySelectorAll('#set tbody [data-col="purchases"]').forEach(i => sumPurchases += parseFloat(i.value)||0);
    const total = sumVat + sumPurchases;
    tpvEl.innerHTML = total === 0
      ? '<span style="color:var(--muted)">—</span>'
      : total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
}

function rowChanged(el) {
  const row = el.closest('tr');
  recalcRow(row);
  recalcFooter();
  // Mark unsaved
  const btn = row.querySelector('.bsv');
  if (btn) { btn.textContent = row.dataset.id ? 'Update' : 'Save'; btn.className = 'bsv'; }
}

// Auto-save specific high-priority columns (e.g. PDC) the moment the user
// finishes editing them, so values used elsewhere (like Cashflow) are never
// stale just because someone forgot to click Save/Update.
const AUTO_SAVE_COLS = ['pdc'];
document.addEventListener('change', function(e) {
  const el = e.target;
  if (!el.matches('[data-col]')) return;
  if (!AUTO_SAVE_COLS.includes(el.dataset.col)) return;
  const row = el.closest('tr');
  if (!row) return;
  const btn = row.querySelector('.bsv');
  if (btn) saveRow(btn);
});

let newRowCounter = 0;
function addRow() {
  const tbody = document.getElementById('se-tbody');
  const tr = document.createElement('tr');
  const tempId = 'new_' + (++newRowCounter);
  tr.id = tempId;
  tr.dataset.id = '';

  const today = new Date().toISOString().slice(0,10);
  const fakeRow = { expense_date: today, id: '' };
  // We'll build the inner HTML by cloning structure
  tr.innerHTML = buildNewRowHTML(today);
  tbody.appendChild(tr);
  tr.querySelector('[data-col="voucher_no"]')?.focus();
  recalcFooter();
}

function buildNewRowHTML(date) {
  let h = '';
  h += `<td class="td-date"><input type="date" class="sei txt" style="width:100%;text-align:center;font-size:.62rem" data-col="expense_date" value="${date}" oninput="rowChanged(this)"></td>`;
  const txtCols = {voucher_no:'c-info col-txt-sm',tin:'c-info col-txt-sm',company_name:'c-info col-txt-lg',address:'c-info col-txt-lg',particulars:'c-info col-txt-lg',document_type:'c-info col-txt-md',document_no:'c-info col-txt-md'};
  for (const [col,cls] of Object.entries(txtCols)) {
    if (col === 'tin' || col === 'company_name') {
      const ph = col === 'company_name' ? 'Type to search…' : '—';
      h += `<td class="${cls}"><div class="ac-wrap"><input type="text" class="sei txt" data-col="${col}" value="" placeholder="${ph}" oninput="rowChanged(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>`;
    } else {
      h += `<td class="${cls}"><input type="text" class="sei txt" data-col="${col}" value="" placeholder="—" oninput="rowChanged(this)"></td>`;
    }
  }
  h += `<td class="c-vat"><input type="number" step="0.01" class="sei" data-col="amount_w_vat" value="" placeholder="0.00" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="vat" value="" readonly tabindex="-1"></td>`;
  h += `<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="amount_wo_vat" value="" readonly tabindex="-1"></td>`;
  h += `<td class="c-vat"><input type="number" step="0.01" class="sei" data-col="non_vat" value="" placeholder="0.00" oninput="rowChanged(this)"></td>`;
  h += `<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="total_amount" value="" readonly tabindex="-1"></td>`;
  CAT_COLS.forEach(col => {
    h += `<td class="c-cat"><input type="number" step="0.01" class="sei" data-col="${col}" value="" placeholder="0.00" oninput="rowChanged(this)"></td>`;
  });
  h += `<td class="c-total"><input type="number" step="0.01" class="sei calc bold" data-col="row_total" value="" readonly tabindex="-1"></td>`;
  h += `<td class="c-check-cell" style="position:sticky;right:80px;z-index:9;min-width:80px;text-align:right;padding:6px 8px;border:1px solid #e3e6ea;background:#fff"><span class="chk-val" style="font-family:var(--font-m);font-size:.72rem;font-weight:700">—</span></td>`;
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
    const res  = await fetch('demiclab_expenses.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsv ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        row.id = 'row' + data.id;
        // Add delete button
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
  if (!confirm('Delete this expense entry?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcFooter(); return; }

  const fd = new FormData();
  fd.append('ajax_delete','1');
  fd.append('id', id);
  try {
    const res  = await fetch('demiclab_expenses.php', {method:'POST', body:fd});
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
  window.location.href = 'demiclab_expenses.php?' + params.toString();
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

// Tab key navigation between cells
document.addEventListener('keydown', e => {
  if (!e.target.classList.contains('sei') || e.key !== 'Tab') return;
  // default tab behavior is fine — just ensure footer recalcs
  setTimeout(recalcFooter, 10);
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
      <div class="ac-item" data-tin="${escHtml(v.tin)}" data-name="${escHtml(v.company_name)}"
           data-addr="${escHtml(v.address)}" onclick="fillVendor(this)">
        <div class="ac-name">${escHtml(v.company_name)}</div>
        <div class="ac-tin">TIN: ${escHtml(v.tin)}</div>
        ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
      </div>`).join('');
    list.classList.add('open');
  } catch(e) {}
}

function fillVendor(item) {
  const row = item.closest('tr');
  row.querySelector('[data-col="tin"]').value          = item.dataset.tin;
  row.querySelector('[data-col="company_name"]').value = item.dataset.name;
  row.querySelector('[data-col="address"]').value      = item.dataset.addr;
  document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
  rowChanged(row.querySelector('[data-col="tin"]'));
  row.querySelector('[data-col="voucher_no"]')?.focus();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close dropdowns when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>