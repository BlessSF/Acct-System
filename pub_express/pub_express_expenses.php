<?php
// ============================================================
//  pub_express_expenses.php — Pub Express Branch Expenses Ledger
//  Mirrors the Excel expense sheet columns exactly
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Pub Express branch and management can access
if (isBranch() && currentBranch() !== 'Pub Express') {
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
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_expenses` (
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

// ── Table for "Selected Totals" VAT amount saved to feed Inventory
//    Purchases on the Cashflow page. One row per store + month/year;
//    saving again overwrites the previous value for that month. ─────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_cf_vat_selection` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Pub Express',
    `sel_year`      int(4) NOT NULL,
    `sel_month`     tinyint(2) NOT NULL,
    `vat_total`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `row_count`     int(11) NOT NULL DEFAULT 0,
    `saved_by`      varchar(100) DEFAULT NULL,
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Add new columns if they don't exist yet (safe migration) ─
foreach (['pdc','ca','withdrawal','depreciation_expense'] as $newCol) {
    try {
        $pdo->exec("ALTER TABLE `pub_express_expenses` ADD COLUMN `$newCol` decimal(12,2) DEFAULT 0.00 AFTER `sales_discounts`");
    } catch (Throwable $ignored) {} // column already exists — ignore
}
foreach (['pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares'] as $newCol) {
    try {
        $pdo->exec("ALTER TABLE `pub_express_expenses` ADD COLUMN `$newCol` decimal(12,2) DEFAULT 0.00 AFTER `staff_meal`");
    } catch (Throwable $ignored) {} // column already exists — ignore
}
// Checkbox state — whether this row is checked in the "Selected Totals" VAT
// card. Persisted so it survives Save/Update and page reloads.
try {
    $pdo->exec("ALTER TABLE `pub_express_expenses` ADD COLUMN `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0 AFTER `row_total`");
} catch (Throwable $ignored) {} // column already exists — ignore

// Disbursement → Expenses auto-posting: link column (see lib/expense_sync.php)
if (!is_file(__DIR__ . '/../lib/expense_sync.php')) {
    http_response_code(500);
    die('Missing file: lib/expense_sync.php — upload it to the "lib" folder next to "h" (same level as lib/vat_mode.php).');
}
require_once __DIR__ . '/../lib/expense_sync.php';
expSyncEnsure($pdo, 'pub_express_acc_titles', 'pub_express_expenses');

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
// Checkbox/flag columns saved as 0 or 1 (not summed into any monetary totals)
$FLAG_COLS = ['selected_for_cf'];

// ── This ledger is VIEW-ONLY: rows are posted automatically from Pub Express Disbursement ──
//    (gross amount → the column set on the Account Title). Block every write.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax_add']) || isset($_POST['ajax_update']) || isset($_POST['ajax_delete']))) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'msg'=>'Pub Express Expenses is view-only — it is filled automatically from Pub Express Disbursement. Add, edit or delete the entry there.']);
    exit;
}

// ── AJAX: Rebuild one month from Disbursement ──
//    Deletes the old manually-typed ("legacy") rows of that month — they were keyed by hand, can
//    duplicate Disbursement entries and recompute VAT on Non-VAT purchases — then re-posts every
//    Disbursement entry of that month (gross into the column set on its Account Title).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_rebuild_month'])) {
    header('Content-Type: application/json');
    $ry = (int)($_POST['year'] ?? 0); $rm = (int)($_POST['month'] ?? 0);
    if ($ry < 2000 || $rm < 1 || $rm > 12) { echo json_encode(['ok'=>false,'msg'=>'Bad month']); exit; }
    $from = sprintf('%04d-%02d-01', $ry, $rm);
    $to   = date('Y-m-d', strtotime($from . ' +1 month'));
    try {
        $del = $pdo->prepare("DELETE FROM pub_express_expenses WHERE disbursement_id IS NULL AND expense_date >= ? AND expense_date < ?");
        $del->execute([$from, $to]);
        $n = expSyncMany($pdo, 'pub_express_disbursement', 'pub_express_expenses', 'pub_express_acc_titles', null, $from, $to);
        echo json_encode(['ok'=>true,'removed'=>$del->rowCount(),'posted'=>$n['synced'],'skipped'=>$n['unmapped']]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: tick / untick rows for the "Selected Totals" VAT card (the only thing editable here) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_set_checks'])) {
    header('Content-Type: application/json');
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $val = !empty($_POST['checked']) ? 1 : 0;
    try {
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE pub_express_expenses SET selected_for_cf=? WHERE id IN ($in)")->execute(array_merge([$val], $ids));
        }
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save the currently-checked rows' VAT total, so Cashflow's
//    Inventory Purchases can pick it up ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_selected_vat'])) {
    header('Content-Type: application/json');
    $selYear  = (int)($_POST['sel_year']  ?? date('Y'));
    $selMonth = (int)($_POST['sel_month'] ?? date('n'));
    $vatTotal = (float)($_POST['vat_total'] ?? 0);
    $rowCount = (int)($_POST['row_count'] ?? 0);
    try {
        $pdo->prepare("
            INSERT INTO pub_express_cf_vat_selection (store_name, sel_year, sel_month, vat_total, row_count, saved_by)
            VALUES ('Pub Express', ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE vat_total=VALUES(vat_total), row_count=VALUES(row_count), saved_by=VALUES(saved_by)
        ")->execute([$selYear, $selMonth, $vatTotal, $rowCount, $user['name']]);
        echo json_encode(['ok'=>true,'vat_total'=>$vatTotal,'row_count'=>$rowCount]);
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
    $stmt = $pdo->prepare("SELECT * FROM pub_express_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
    $stmt->execute([$csvYear, $csvMonth]);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Pub Express_Expenses_'.$monthNames[$csvMonth].'_'.$csvYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['Pub Express Branch — Expenses Ledger']);
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
$stmt = $pdo->prepare("SELECT * FROM pub_express_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=? ORDER BY expense_date ASC, id ASC");
$stmt->execute([$fYear, $fMonth]);
$rows = $stmt->fetchAll();

$legacyCount = 0;
foreach ($rows as $__r) { if (empty($__r['disbursement_id'])) $legacyCount++; }

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
    $totStmt = $pdo->prepare("SELECT $selectParts FROM pub_express_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $totStmt->execute([$fYear, $fMonth]);
    $expTotals = $totStmt->fetch() ?: [];
} catch (Throwable $ignored) {}
$ef = fn($k) => isset($expTotals[$k]) ? number_format((float)$expTotals[$k], 2, '.', ',') : '0.00';

// ── Currently-saved "Selected VAT" total for this month (feeds Cashflow) ──
$savedSelVat = 0.0; $savedSelCount = 0; $savedSelBy = null;
try {
    $svStmt = $pdo->prepare("SELECT vat_total, row_count, saved_by FROM pub_express_cf_vat_selection WHERE store_name='Pub Express' AND sel_year=? AND sel_month=?");
    $svStmt->execute([$fYear, $fMonth]);
    if ($sv = $svStmt->fetch()) {
        $savedSelVat   = (float)$sv['vat_total'];
        $savedSelCount = (int)$sv['row_count'];
        $savedSelBy    = $sv['saved_by'];
    }
} catch (Throwable $ignored) {}

$pageTitle  = 'Pub Express Expenses';
$activePage = 'pub_express_expenses';
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

.th-chk  { position: sticky !important; left: 0; z-index: 31 !important; width: 32px; min-width: 32px; background: #162d4a !important; text-align: center; }
.th-date { position: sticky !important; left: 32px; z-index: 30 !important; width: 80px; min-width: 80px; background: #162d4a !important; }
.th-act  { position: sticky !important; right: 0; z-index: 30 !important; width: 80px; min-width: 80px; background: #162d4a !important; }

.td-chk {
  position: sticky; left: 0; z-index: 6;
  background: #f8f9fb !important;
  text-align: center; padding: 0; width: 32px; min-width: 32px;
}
.se-table tbody tr:nth-child(even) .td-chk { background: #f9fafb !important; }
.se-table tbody tr:hover .td-chk { background: #eef4ff !important; }
.se-table tbody tr.row-selected td { background: #fff8e1 !important; }
.se-table tbody tr.row-selected .td-chk,
.se-table tbody tr.row-selected .td-date { background: #fef3c7 !important; }
.row-chk, #selectAllChk { width: 14px; height: 14px; cursor: pointer; accent-color: #b45309; vertical-align: middle; }

.td-date {
  position: sticky; left: 32px; z-index: 5;
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
  position: sticky; left: 32px; z-index: 5;
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
    <div class="section-title" style="color:#1a1d23">Pub Express Branch <span style="color:var(--accent)">Expenses Ledger</span></div>
    <div class="section-subtitle">Each row is one expense entry · all category columns auto-sum into TOTAL</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="se-controls" id="filterForm">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 Pub Express
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
            $isTotal = in_array($col, ['total_amount','row_total']); ?>
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
            $val     = (float)($expTotals[$col] ?? 0);
            $isTotal = in_array($col, ['total_amount','row_total']);
            $isZero  = $val == 0;
            $bg      = $isTotal ? '#f0fdf4' : ($isZero ? '#f8f9fb' : '#fff');
            $color   = $isTotal ? 'var(--accent)' : ($isZero ? 'var(--muted)' : 'var(--text)');
            $fw      = ($isTotal || !$isZero) ? '700' : '400';
          ?>
          <td style="background:<?= $bg ?>;border:1px solid #e3e6ea;
                     padding:10px 12px;text-align:right;
                     font-family:var(--font-m);font-size:.74rem;
                     color:<?= $color ?>;font-weight:<?= $fw ?>;
                     white-space:nowrap;min-width:100px">
            <?= $isZero ? '<span style="color:var(--muted)">—</span>' : $ef($col) ?>
          </td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </div>
  <?php if ($groupName === 'VAT BREAKDOWN'): ?>
  <!-- ── Selected Rows Totals (client-side, driven by row checkboxes) ── -->
  <div style="overflow-x:auto;border-radius:var(--radius);border:2px dashed #d97706;
              box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:6px">
    <table style="border-collapse:collapse;width:max-content;min-width:100%">
      <thead>
        <tr>
          <td colspan="5" style="background:#78350f;color:#fde68a;font-family:var(--font-m);
                     font-size:.54rem;text-transform:uppercase;letter-spacing:.1em;
                     padding:6px 14px;border-bottom:1px solid #92400e">
            <span style="display:inline-flex;align-items:center;gap:8px;width:100%">
              ☑️ SELECTED TOTALS — VAT BREAKDOWN
              <span style="opacity:.85;text-transform:none;letter-spacing:0;font-size:.62rem">
                (<span id="selCount">0</span> row<span id="selPlural">s</span> checked below)
              </span>
              <button type="button" onclick="saveSelectedVat(false)" id="btnSaveSelVat"
                      style="margin-left:auto;padding:2px 10px;border-radius:5px;border:1px solid rgba(74,222,128,.5);
                             background:rgba(74,222,128,.15);color:#bbf7d0;font-family:var(--font-m);
                             font-size:.58rem;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:.05em">
                💾 Save VAT to Cashflow
              </button>
              <span id="vatAutoSaveindicator" style="font-size:.55rem;color:#86efac;opacity:0;transition:opacity .4s">✓ auto-saved</span>
              <button type="button" onclick="clearSelection()"
                      style="padding:2px 9px;border-radius:5px;border:1px solid rgba(253,230,138,.4);
                             background:rgba(253,230,138,.12);color:#fde68a;font-family:var(--font-m);
                             font-size:.58rem;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:.05em">
                Clear
              </button>
            </span>
          </td>
        </tr>
        <tr>
          <th style="background:#92400e;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #b45309;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">Amount w/ VAT</th>
          <th style="background:#166534;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:800;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #14532d;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">VAT ↳ feeds Cashflow</th>
          <th style="background:#92400e;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #b45309;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">Amount w/out VAT</th>
          <th style="background:#92400e;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #b45309;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">Non VAT</th>
          <th style="background:#78350f;color:#fff;font-family:var(--font-m);font-size:.52rem;font-weight:700;
                     text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;border:1px solid #b45309;
                     white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">TOTAL AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td id="sel_amount_w_vat" style="background:#fff;border:1px solid #fde9c8;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.74rem;color:var(--muted);
                     white-space:nowrap;min-width:100px">—</td>
          <td id="sel_vat" style="background:#f0fdf4;border:1px solid #bbf7d0;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.76rem;font-weight:800;color:#166534;
                     white-space:nowrap;min-width:100px">—</td>
          <td id="sel_amount_wo_vat" style="background:#fff;border:1px solid #fde9c8;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.74rem;color:var(--muted);
                     white-space:nowrap;min-width:100px">—</td>
          <td id="sel_non_vat" style="background:#fff;border:1px solid #fde9c8;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.74rem;color:var(--muted);
                     white-space:nowrap;min-width:100px">—</td>
          <td id="sel_total_amount" style="background:#fffbeb;border:1px solid #fde9c8;padding:10px 12px;
                     text-align:right;font-family:var(--font-m);font-size:.76rem;font-weight:800;color:#b45309;
                     white-space:nowrap;min-width:100px">—</td>
        </tr>
      </tbody>
    </table>
  </div>
  <div id="selVatStatus" style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext2);margin-bottom:12px;padding:0 2px">
    <?php if ($savedSelCount > 0): ?>
      💾 Currently saved to Cashflow for <?= $monthNames[$fMonth] ?> <?= $fYear ?>:
      <strong style="color:#166534"><?= number_format($savedSelVat,2) ?></strong>
      VAT from <?= $savedSelCount ?> row<?= $savedSelCount===1?'':'s' ?><?= $savedSelBy ? ' — saved by '.htmlspecialchars($savedSelBy) : '' ?>.
    <?php else: ?>
      💾 Nothing saved to Cashflow yet for <?= $monthNames[$fMonth] ?> <?= $fYear ?> — check rows above and click <em>Save VAT to Cashflow</em>.
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="se-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="se-table" id="set">
  <thead>
    <!-- Group row -->
    <tr class="grp-row">
      <th class="th-chk"></th>
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
      <th class="th-chk"><input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)" title="Select all rows"></th>
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
  <tr id="<?=$rid?>" data-id="<?=$r['id']?>"<?= !empty($r['disbursement_id']) ? ' data-locked="1"' : '' ?>>
    <?= renderRow($r, $rid) ?>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="td-chk" style="position:sticky;left:0;z-index:15;background:#1e3a5f !important"></td>
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

<?php if ($legacyCount > 0): ?>
<div style="margin-top:12px;padding:10px 14px;border-radius:8px;background:#FFFBEB;border:1px solid #FCD34D;font-family:var(--font-m);font-size:.7rem;color:#92400E;line-height:1.6">
  ⚠ <strong><?= $legacyCount ?> manual (legacy) row<?= $legacyCount===1?'':'s' ?></strong> in <?= $monthNames[$fMonth] ?> <?= $fYear ?> were typed in by hand before auto-posting.
  They still recompute VAT themselves (so a Non-VAT purchase can show a VAT/net split and a CHECK difference) and can duplicate Disbursement entries.
  <button type="button" onclick="rebuildMonth()" style="margin-left:6px;padding:4px 12px;border:1px solid #D97706;border-radius:6px;background:#fff;color:#92400E;font-weight:700;cursor:pointer;font-size:.68rem">↻ Rebuild <?= $monthNames[$fMonth] ?> from Disbursement</button>
</div>
<?php endif; ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
  <span style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext2)">🔒 View-only · rows with 🔗 are posted automatically from <a href="pub_express_disbursement.php" style="color:#1565C0;font-weight:700">Pub Express Disbursement</a> (gross amount, column chosen per <a href="pub_express_acc_title.php" style="color:#1565C0;font-weight:700">Account Title</a>).</span>
</div>

<?php
function renderRow(array $r, string $rid): string {
    $d = function($k) use ($r) {
        $v = $r[$k] ?? '';
        if (is_numeric($v) && !in_array($k, ['voucher_no','tin','document_no','document_type','company_name','address','particulars'], true)) return (float)$v != 0 ? number_format((float)$v,2,'.','') : '';
        return htmlspecialchars($v);
    };
    $date = $r['expense_date'] ?? date('Y-m-d');
    $dateDisp = $date ? date('d-M', strtotime($date)) : '';
    $isSelected = !empty($r['selected_for_cf']);
    $linked = !empty($r['disbursement_id']);

    $html  = '<td class="td-chk"><input type="checkbox" class="row-chk" data-col="selected_for_cf" '.($isSelected?'checked':'').' onchange="onRowCheck(this)"></td>';
    $html .= '<td class="td-date">';
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
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="vat" value="'.($linked?$d('vat'):'').'" readonly tabindex="-1"></td>';
    // Amount w/out VAT = Amount w/ VAT - VAT  (auto-calc — always recalculated by JS)
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="amount_wo_vat" value="'.($linked?$d('amount_wo_vat'):'').'" readonly tabindex="-1"></td>';
    // Non Vat — manual input
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei" data-col="non_vat" value="'.$d('non_vat').'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    // Total Amount = Amount w/out VAT + Non Vat  (auto-calc — always recalculated by JS)
    $html .= '<td class="c-vat"><input type="number" step="0.01" class="sei calc" data-col="total_amount" value="'.($linked?$d('total_amount'):'').'" readonly tabindex="-1"></td>';

    // Category cols
    $cats = ['purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense'];
    foreach ($cats as $col) {
        $html .= '<td class="c-cat"><input type="number" step="0.01" class="sei" data-col="'.$col.'" value="'.$d($col).'" placeholder="0.00" oninput="rowChanged(this)"></td>';
    }

    // Row total (auto-calc) — always recalculated by JS, never pre-populated from DB
    $html .= '<td class="c-total"><input type="number" step="0.01" class="sei calc bold" data-col="row_total" value="'.($linked?$d('row_total'):'').'" readonly tabindex="-1"></td>';

    // Check cell (TOTAL - TOTAL AMOUNT) — auto-calc by JS
    // Actions (CHECK + buttons merged into single sticky cell)
    $isNew = empty($r['id']);
    // Check cell
    $html .= '<td class="c-check-cell" style="position:sticky;right:80px;z-index:9;min-width:80px;text-align:right;padding:6px 8px;border:1px solid #e3e6ea;background:#fff"><span class="chk-val" style="font-family:var(--font-m);font-size:.72rem;font-weight:700">—</span></td>';

    // Actions — view-only: just show where the row came from
    $html .= '<td class="td-act" style="font-family:var(--font-m);font-size:.58rem;text-align:center;color:#6b7280">'
          .  ($linked ? '<span title="Posted automatically from Pub Express Disbursement entry #'.(int)$r['disbursement_id'].'">🔗 #'.(int)$r['disbursement_id'].'</span>' : '<span title="Entered manually before auto-posting">legacy</span>')
          .  '</td>';

    // View-only: make every input read-only (the row tick-box stays usable)
    $html = str_replace([' oninput="rowChanged(this);triggerAC(this)"',' oninput="rowChanged(this)"'], '', $html);
    $html = preg_replace('/<input (?=[^>]*class="sei)/', '<input readonly ', $html);

    return $html;
}
?>

<script>
const CAT_COLS = ['purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense']; // all included in TOTAL
const F_YEAR  = <?= $fYear ?>;
const F_MONTH = <?= $fMonth ?>;
const VAT_COLS = ['amount_w_vat','vat','amount_wo_vat','non_vat'];
const ALL_TOT_COLS = ['amount_w_vat','vat','amount_wo_vat','non_vat','total_amount','purchases','salaries','rent','medicine','lpg','repairs_maintenance','fuel_trans','communication','transportation','light','drinking_water','water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies','bio_pest_control','representation','miscellaneous','sir_budoy_nikki','staff_meal','pest_control_bio_aug','commission_fees','exhaust_cleaning','bank_fees','admin_salary_shares','marketing','sales_discounts','pdc','ca','withdrawal','depreciation_expense','row_total'];

function gv(row, col) {
  const el = row.querySelector(`[data-col="${col}"]`);
  return el ? (parseFloat(el.value) || 0) : 0;
}

function recalcRow(row) {
  const locked = row.dataset.locked === '1';
  if (locked) {   // posted from Disbursement: show the stored figures, only refresh CHECK
    const rt = parseFloat(row.querySelector('[data-col="row_total"]')?.value) || 0;
    const ta = parseFloat(row.querySelector('[data-col="total_amount"]')?.value) || 0;
    const ck = row.querySelector('.chk-val'), df = Math.round((rt - ta) * 100) / 100;
    if (ck) { ck.textContent = df === 0 ? '0.00' : (df > 0 ? '+' : '') + df.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); ck.style.color = df === 0 ? '#15803d' : '#be123c'; }
    return;
  }
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
  // Keep the "Selected Totals" card and select-all checkbox in sync
  // whenever row data or the row list itself changes.
  recalcSelectedTotals();
  updateSelectAllState();
}

// ── Selected Rows Totals (VAT breakdown for checked rows only) ──────
const SEL_VAT_COLS = ['amount_w_vat','vat','amount_wo_vat','non_vat','total_amount'];

function onRowCheck(cb) {
  const row = cb.closest('tr');
  row?.classList.toggle('row-selected', cb.checked);
  updateSelectAllState();
  recalcSelectedTotals();
  persistChecks(row?.dataset.id ? [row.dataset.id] : [], cb.checked);
}

// Tick state is the only thing saved from this page — straight to the database.
function persistChecks(ids, checked) {
  if (!ids.length) return;
  const fd = new FormData();
  fd.append('ajax_set_checks', '1');
  fd.append('ids', ids.join(','));
  fd.append('checked', checked ? '1' : '0');
  fetch('pub_express_expenses.php', {method:'POST', body:fd}).catch(() => {});
}

function toggleSelectAll(master) {
  document.querySelectorAll('#set tbody .row-chk').forEach(cb => {
    cb.checked = master.checked;
    cb.closest('tr')?.classList.toggle('row-selected', master.checked);
  });
  persistChecks([...document.querySelectorAll('#set tbody tr[data-id]')].map(r => r.dataset.id).filter(Boolean), master.checked);
  recalcSelectedTotals();
}

function clearSelection() {
  document.querySelectorAll('#set tbody .row-chk').forEach(cb => {
    cb.checked = false;
    cb.closest('tr')?.classList.remove('row-selected');
  });
  const master = document.getElementById('selectAllChk');
  if (master) { master.checked = false; master.indeterminate = false; }
  persistChecks([...document.querySelectorAll('#set tbody tr[data-id]')].map(r => r.dataset.id).filter(Boolean), false);
  recalcSelectedTotals();
}

async function saveSelectedVat(silent) {
  const btn = document.getElementById('btnSaveSelVat');
  let vatTotal = 0, rowCount = 0;
  document.querySelectorAll('#set tbody tr').forEach(row => {
    const cb = row.querySelector('.row-chk');
    if (!cb || !cb.checked) return;
    rowCount++;
    vatTotal += gv(row, 'vat');
  });

  const orig = btn ? btn.textContent : null;
  if (btn && !silent) { btn.textContent = '…'; btn.disabled = true; }

  const fd = new FormData();
  fd.append('ajax_save_selected_vat', '1');
  fd.append('sel_year', F_YEAR);
  fd.append('sel_month', F_MONTH);
  fd.append('vat_total', vatTotal.toFixed(2));
  fd.append('row_count', rowCount);

  try {
    const res  = await fetch('pub_express_expenses.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      if (silent) {
        // Flash the auto-saved indicator
        const ind = document.getElementById('vatAutoSaveindicator');
        if (ind) { ind.style.opacity = '1'; setTimeout(() => ind.style.opacity = '0', 1800); }
      } else {
        showToast(`✓ Saved ${vatTotal.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})} VAT (${rowCount} row${rowCount===1?'':'s'}) to Cashflow`, 'success');
      }
      const status = document.getElementById('selVatStatus');
      if (status) {
        status.innerHTML = rowCount > 0
          ? `💾 Auto-saved to Cashflow: <strong style="color:#166534">${vatTotal.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong> VAT from ${rowCount} row${rowCount===1?'':'s'} — Inventory Purchases in Cashflow will reflect this.`
          : `💾 Saved 0.00 — no rows checked. Inventory Purchases in Cashflow uses purchases column only.`;
      }
    } else if (!silent) {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    if (!silent) showToast('❌ Network error saving selected VAT', 'error');
  } finally {
    if (btn && !silent) { btn.textContent = orig; btn.disabled = false; }
  }
}

function updateSelectAllState() {
  const boxes  = [...document.querySelectorAll('#set tbody .row-chk')];
  const master = document.getElementById('selectAllChk');
  if (!master) return;
  if (!boxes.length) { master.checked = false; master.indeterminate = false; return; }
  const checkedCount = boxes.filter(b => b.checked).length;
  master.checked = checkedCount === boxes.length;
  master.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
}

function recalcSelectedTotals() {
  const sums = {}; SEL_VAT_COLS.forEach(c => sums[c] = 0);
  let count = 0;
  document.querySelectorAll('#set tbody tr').forEach(row => {
    const cb = row.querySelector('.row-chk');
    if (!cb || !cb.checked) return;
    count++;
    SEL_VAT_COLS.forEach(c => sums[c] += gv(row, c));
  });

  const countEl  = document.getElementById('selCount');
  const pluralEl = document.getElementById('selPlural');
  if (countEl)  countEl.textContent  = count;
  if (pluralEl) pluralEl.textContent = count === 1 ? '' : 's';

  SEL_VAT_COLS.forEach(c => {
    const el = document.getElementById('sel_' + c);
    if (!el) return;
    if (!count || !sums[c]) {
      el.textContent  = '—';
      el.style.color  = 'var(--muted)';
      el.style.fontWeight = '400';
    } else {
      el.textContent  = sums[c].toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
      el.style.color  = c === 'total_amount' ? '#b45309' : (c === 'vat' ? '#166534' : '#1a1d23');
      el.style.fontWeight = (c === 'vat' || c === 'total_amount') ? '800' : '700';
    }
  });

  // Auto-save VAT selection to cashflow DB whenever checkboxes change (debounced 600ms)
  // so Inventory Purchases in Cashflow updates automatically without needing the manual button
  clearTimeout(window._vatSaveTimer);
  window._vatSaveTimer = setTimeout(() => saveSelectedVat(true), 600);
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
  h += `<td class="td-chk"><input type="checkbox" class="row-chk" data-col="selected_for_cf" onchange="onRowCheck(this)"></td>`;
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
    if (el.type === 'checkbox') {
      fd.append(el.dataset.col, el.checked ? '1' : '0');
    } else {
      fd.append(el.dataset.col, el.value || '0');
    }
  });

  try {
    const res  = await fetch('pub_express_expenses.php', {method:'POST', body:fd});
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
      // Keep Cashflow's Inventory Purchases in sync with whatever is
      // currently checked, now that this row's checkbox state is persisted.
      saveSelectedVat(true);
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
    const res  = await fetch('pub_express_expenses.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0';
      row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcFooter(); saveSelectedVat(true); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch(e) { showToast('❌ Network error','error'); }
}

async function rebuildMonth() {
  if (!confirm('Rebuild this month from Pub Express Disbursement?\n\n• the manually-typed (legacy) rows of this month are DELETED\n• every Disbursement entry of this month is posted again (gross amount, column per Account Title)\n\nThis cannot be undone.')) return;
  const fd = new FormData();
  fd.append('ajax_rebuild_month', '1'); fd.append('year', F_YEAR); fd.append('month', F_MONTH);
  try {
    const data = await (await fetch('pub_express_expenses.php', {method:'POST', body:fd})).json();
    if (data.ok) { showToast('✓ ' + data.removed + ' legacy row(s) removed, ' + data.posted + ' entries posted' + (data.skipped ? ' (' + data.skipped + ' skipped: no Expenses column)' : ''), 'success'); setTimeout(() => location.reload(), 900); }
    else showToast('❌ ' + data.msg, 'error');
  } catch (e) { showToast('❌ Network error', 'error'); }
}

function doExport(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv','1');
  window.location.href = 'pub_express_expenses.php?' + params.toString();
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
  // Highlight rows whose checkbox came pre-checked from the database
  document.querySelectorAll('#set tbody .row-chk').forEach(cb => {
    if (cb.checked) cb.closest('tr')?.classList.add('row-selected');
  });
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
    const res  = await fetch('pub_express_expenses.php?vendor_lookup=1&q=' + encodeURIComponent(q));
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