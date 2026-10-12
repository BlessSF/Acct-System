<?php
// ============================================================
//  h_carwash_summary_report.php — Hero Carwash Monthly Summary
//  Same structure/columns and behavior as h_summary_report.php's
//  "H Summary Report" — a standalone flat table of pure manual
//  daily entries, one row per day, each with its own Save button.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only H branch and management can access this page
if (isBranch() && currentBranch() !== 'H') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

const CWS_STORE = 'HEROCARWASH';

// Same mapping formulas h_carwash_sales_report.php's ajax_save uses to push
// data here — kept in one place so the on-screen table and the CSV export
// always agree, and so a date's Sales Report figures show up correctly
// whether or not that date has ever been explicitly re-saved since this
// auto-fill feature was added.
function cwMapFromSalesReport(array $sr): array {
    return [
        'z_reading_gross'       => (float)($sr['pos_reading'] ?? 0),
        // Gross Sales Excl Mktg — the Sales Report's own Gross Sales figure
        // (POS Reading − Staff CF), mirrored as-is.
        'gross_sales_excl_mktg' => (float)($sr['gross_sales'] ?? 0),
        'store_gross_excl_mktg' => (float)($sr['gross_sales'] ?? 0) - (float)($sr['marketing_expense'] ?? 0) - (float)($sr['discounts'] ?? 0),
        'cash_for_depo'         => (float)($sr['net_cash'] ?? 0),
        'sales_of_day_swipe'    => (float)($sr['card_payments'] ?? 0),
        'unpaid'                => (float)($sr['unpaids'] ?? 0),
        'advance_payment'       => (float)($sr['sold_gc'] ?? 0),
        // Newly wired-up — same source columns the Carwash Sales Report
        // already computes for itself.
        'discount'              => (float)($sr['discounts'] ?? 0),
        'marketing_pull_out'    => (float)($sr['marketing_expense'] ?? 0),
        'expenses'              => (float)($sr['expenses'] ?? 0),
        // Short/Over is the Sales Report's own cash-reconciliation figure
        // (Cash on Hand − Net Cash) — mirrored here as-is rather than
        // recomputed from the Summary page's deduction columns.
        'short_over'            => (float)($sr['short_over'] ?? 0),
    ];
}

// ── Auto-create Carwash Summary table (safe to run repeatedly) ────
// Column set mirrors h_summary_report.php's "H Summary Report" exactly —
// a plain manual daily-entry sheet (no auto-fill from the Sales Report).
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_summary_entries` (
        `id`                     int(11) NOT NULL AUTO_INCREMENT,
        `report_date`            date NOT NULL,
        `store_name`             varchar(100) NOT NULL DEFAULT 'HEROCARWASH',
        `gross_sales_excl_mktg`  decimal(12,2) DEFAULT 0.00,
        `store_gross_excl_mktg`  decimal(12,2) DEFAULT 0.00,
        `z_reading_gross`        decimal(12,2) DEFAULT 0.00,
        `cash_for_depo`          decimal(12,2) DEFAULT 0.00,
        `sales_of_day_swipe`     decimal(12,2) DEFAULT 0.00,
        `deposit_swipe`          decimal(12,2) DEFAULT 0.00,
        `late_payment`           decimal(12,2) DEFAULT 0.00,
        `cancelled_transaction`  decimal(12,2) DEFAULT 0.00,
        `unpaid`                 decimal(12,2) DEFAULT 0.00,
        `paid`                   decimal(12,2) DEFAULT 0.00,
        `advance_payment`        decimal(12,2) DEFAULT 0.00,
        `grab`                   decimal(12,2) DEFAULT 0.00,
        `bank_trans`             decimal(12,2) DEFAULT 0.00,
        `gc_sponsor_marketing`   decimal(12,2) DEFAULT 0.00,
        `gc_sold`                decimal(12,2) DEFAULT 0.00,
        `discount`               decimal(12,2) DEFAULT 0.00,
        `marketing_pull_out`     decimal(12,2) DEFAULT 0.00,
        `personal`               decimal(12,2) DEFAULT 0.00,
        `expenses`               decimal(12,2) DEFAULT 0.00,
        `other_expenses`         decimal(12,2) DEFAULT 0.00,
        `sc_for_depo`            decimal(12,2) DEFAULT 0.00,
        `total_deductions`       decimal(12,2) DEFAULT 0.00,
        `short_over`             decimal(12,2) DEFAULT 0.00,
        `total_swipe`            decimal(12,2) DEFAULT 0.00,
        `cash_deposit`           decimal(12,2) DEFAULT 0.00,
        `other_sales`            decimal(12,2) DEFAULT 0.00,
        `remarks`                text DEFAULT NULL,
        `remarks2`               text DEFAULT NULL,
        `saved_by`               varchar(100) DEFAULT NULL,
        `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Table may already exist from before this change — add any missing
    // columns instead of dropping/losing whatever's already been saved.
    $newCols = [
        'gross_sales_excl_mktg' => "decimal(12,2) DEFAULT 0.00",
        'store_gross_excl_mktg' => "decimal(12,2) DEFAULT 0.00",
        'z_reading_gross'       => "decimal(12,2) DEFAULT 0.00",
        'cash_for_depo'         => "decimal(12,2) DEFAULT 0.00",
        'sales_of_day_swipe'    => "decimal(12,2) DEFAULT 0.00",
        'deposit_swipe'         => "decimal(12,2) DEFAULT 0.00",
        'late_payment'          => "decimal(12,2) DEFAULT 0.00",
        'cancelled_transaction' => "decimal(12,2) DEFAULT 0.00",
        'unpaid'                => "decimal(12,2) DEFAULT 0.00",
        'paid'                  => "decimal(12,2) DEFAULT 0.00",
        'advance_payment'       => "decimal(12,2) DEFAULT 0.00",
        'grab'                  => "decimal(12,2) DEFAULT 0.00",
        'bank_trans'            => "decimal(12,2) DEFAULT 0.00",
        'gc_sponsor_marketing'  => "decimal(12,2) DEFAULT 0.00",
        'gc_sold'               => "decimal(12,2) DEFAULT 0.00",
        'discount'              => "decimal(12,2) DEFAULT 0.00",
        'marketing_pull_out'    => "decimal(12,2) DEFAULT 0.00",
        'personal'              => "decimal(12,2) DEFAULT 0.00",
        'expenses'              => "decimal(12,2) DEFAULT 0.00",
        'other_expenses'        => "decimal(12,2) DEFAULT 0.00",
        'sc_for_depo'           => "decimal(12,2) DEFAULT 0.00",
        'total_deductions'      => "decimal(12,2) DEFAULT 0.00",
        'short_over'            => "decimal(12,2) DEFAULT 0.00",
        'total_swipe'           => "decimal(12,2) DEFAULT 0.00",
        'cash_deposit'          => "decimal(12,2) DEFAULT 0.00",
        'other_sales'           => "decimal(12,2) DEFAULT 0.00",
        'remarks2'              => "text DEFAULT NULL",
    ];
    foreach ($newCols as $col => $def) {
        try { $pdo->exec("ALTER TABLE h_carwash_summary_entries ADD COLUMN IF NOT EXISTS `$col` $def"); } catch (Throwable $ignored) {}
    }
} catch (Throwable $ignored) {}

// ── Handle AJAX save (per-row) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $reportDate = $_POST['report_date'] ?? '';
    if (!$reportDate) {
        echo json_encode(['ok' => false, 'msg' => 'Missing date.']);
        exit;
    }

    $numCols = [
        'gross_sales_excl_mktg','store_gross_excl_mktg','z_reading_gross',
        'cash_for_depo','sales_of_day_swipe','deposit_swipe','late_payment',
        'cancelled_transaction','unpaid','paid','advance_payment',
        'grab','bank_trans','gc_sponsor_marketing','gc_sold','discount',
        'marketing_pull_out','personal','expenses','other_expenses','sc_for_depo',
        'total_deductions','short_over','total_swipe','cash_deposit','other_sales'
    ];
    $txtCols = ['remarks','remarks2'];

    $data = ['store_name' => CWS_STORE, 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($txtCols as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO h_carwash_summary_entries ("
             . implode(',', array_map(fn($f) => "`$f`", $fields))
             . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")"
             . " ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Handle AJAX quota save ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_quota'])) {
    header('Content-Type: application/json');
    $month = trim($_POST['report_month'] ?? '');
    $quota = (float)($_POST['quota'] ?? 0);
    if (!$month) { echo json_encode(['ok'=>false,'msg'=>'Missing data']); exit; }
    try {
        try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
        $pdo->prepare("INSERT INTO summary_reports (report_month,store_name,quota_target,created_by)
            VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE quota_target=VALUES(quota_target), created_by=VALUES(created_by)")
            ->execute([$month.'-01', CWS_STORE, $quota, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle CSV download ───────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $csvMonthNames = ['','January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    $csvCols = [
        'report_date'            => 'Date',
        'gross_sales_excl_mktg'  => 'Gross Sales Excl Mktg',
        'store_gross_excl_mktg'  => 'Store Gross (Excl SC/Mktg)',
        'z_reading_gross'        => 'Z Reading Gross (Incl SC/Mktg)',
        'cash_for_depo'          => 'Cash for Depo',
        'sales_of_day_swipe'     => 'Sales of the Day (Swipe)',
        'deposit_swipe'          => 'Deposit Swipe',
        'late_payment'           => 'Late Payment',
        'cancelled_transaction'  => 'Cancelled Transaction',
        'unpaid'                 => 'Unpaid',
        'paid'                   => 'Paid',
        'advance_payment'        => 'Advance Payment',
        'grab'                   => 'Grab',
        'bank_trans'             => 'Bank Trans',
        'gc_sponsor_marketing'   => 'GC Sponsor / Marketing',
        'gc_sold'                => 'GC Sold',
        'discount'               => 'Discount',
        'marketing_pull_out'     => 'Marketing Pull Out',
        'personal'               => 'Personal',
        'expenses'               => 'Expenses',
        'other_expenses'         => 'Other Expenses',
        'sc_for_depo'            => 'SC for Depo',
        'total_deductions'       => 'Total Deductions',
        'short_over'             => 'Short / Over',
        'total_swipe'            => 'Total Swipe',
        'cash_deposit'           => 'Cash Deposit',
        'other_sales'            => 'Other Sales',
        'remarks'                => 'Remarks',
        'remarks2'               => 'Remarks (2)',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM h_carwash_summary_entries WHERE store_name=? AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([CWS_STORE, $csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    // Same live-merge the on-screen table uses, so the CSV always matches
    // what's currently displayed even for dates never re-saved on this page.
    $csvSrStmt = $pdo->prepare("SELECT * FROM h_carwash_report WHERE store_name=? AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvSrStmt->execute([CWS_STORE, $csvYear, $csvMonth]);
    $csvSrRows = [];
    foreach ($csvSrStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $csvSrRows[$r['report_date']] = cwMapFromSalesReport($r);

    $filename = 'HeroCarwash_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Hero Carwash — Summary Report']);
    fputcsv($out, [$csvMonthNames[$csvMonth] . ' ' . $csvYear]);
    fputcsv($out, []);
    fputcsv($out, array_values($csvCols));

    $totals = array_fill_keys(array_keys($csvCols), 0);
    foreach ($csvDays as $ds) {
        $row   = $csvRows[$ds] ?? null;
        $srMap = $csvSrRows[$ds] ?? null;
        $line  = [];
        foreach (array_keys($csvCols) as $key) {
            if ($key === 'report_date') {
                $line[] = date('d-M-Y (D)', strtotime($ds));
            } elseif ($key === 'remarks') {
                $line[] = $row[$key] ?? '';
            } else {
                $val = ($srMap && isset($srMap[$key])) ? $srMap[$key] : (float)($row[$key] ?? 0);
                $line[] = $val != 0 ? number_format($val, 2, '.', '') : '';
                $totals[$key] += $val;
            }
        }
        fputcsv($out, $line);
    }
    $totalLine = ['TOTAL'];
    foreach (array_keys($csvCols) as $key) {
        if ($key === 'report_date') continue;
        if ($key === 'remarks') { $totalLine[] = ''; continue; }
        $totalLine[] = $totals[$key] != 0 ? number_format($totals[$key], 2, '.', '') : '0.00';
    }
    fputcsv($out, $totalLine);
    fclose($out);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
$allDays = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $allDays[] = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $d);
}

// ── Load saved (manually-entered) rows ─────────────────────
$savedRows = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM h_carwash_summary_entries WHERE store_name=? AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([CWS_STORE, $fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull live data from the Carwash Sales Report (source of truth) ──
// The Sales Report's ajax_save also pushes these same values into
// h_carwash_summary_entries, but that only happens on a fresh save.
// Reading h_carwash_report directly here too means every date that
// already has a Sales Report — saved before or after this feature
// existed — shows up correctly, with no need to re-save anything.
$salesRows = [];
try {
    $ssStmt = $pdo->prepare("SELECT * FROM h_carwash_report WHERE store_name=? AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $ssStmt->execute([CWS_STORE, $fYear, $fMonth]);
    foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull auto-filled data pushed here by the Carwash Sales Report ──
// Whenever h_carwash_sales_report.php saves a day, it upserts these same
// 7 columns into this table. We still read from h_carwash_summary_entries
// itself (not a second table) — the Sales Report already wrote the mapped
// values in here, so a plain SELECT is all that's needed. The remaining 4
// columns (deposit_swipe, late_payment, cancelled_transaction, paid) have
// no source in the Sales Report and are always manually entered here.
$AUTO_COLS = [
    'gross_sales_excl_mktg', 'store_gross_excl_mktg', 'z_reading_gross',
    'cash_for_depo', 'sales_of_day_swipe', 'unpaid', 'advance_payment',
    'discount', 'marketing_pull_out', 'expenses', 'short_over',
];
// Short/Over is preserved exactly as the Sales Report computed it (Cash on
// Hand − Net Cash) instead of being recalculated client-side from the
// deduction columns here — same "preserve" approach h_summary_report.php
// uses for its own Sales-Report-mirrored fields (see recalc() below).

// ── Load saved quota ──────────────────────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name=? AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([CWS_STORE, $fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column keys (numeric, for tfoot totals) ───────────────
$COLS = [
    'gross_sales_excl_mktg','store_gross_excl_mktg','z_reading_gross',
    'cash_for_depo','sales_of_day_swipe','deposit_swipe','late_payment',
    'cancelled_transaction','unpaid','paid','advance_payment',
    'grab','bank_trans','gc_sponsor_marketing','gc_sold','discount',
    'marketing_pull_out','personal','expenses','other_expenses','sc_for_depo',
    'total_deductions','short_over','total_swipe','cash_deposit','other_sales'
];

$pageTitle  = 'Carwash Summary Report';
$activePage = 'h_carwash_summary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* ── Scroll container ── */
.sr-outer {
  width: 100%; overflow-x: auto; overflow-y: visible;
  border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface); scrollbar-width: thin;
  scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.sr-outer::-webkit-scrollbar { height: 8px; }
.sr-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.sr-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }
.sr-outer::-webkit-scrollbar-thumb:hover { background: #9aa3af; }

/* ── Table ── */
.sr-table { border-collapse: collapse; width: max-content; font-size: .69rem; table-layout: fixed; }
.sr-table thead th {
  background: #f4f5f7; color: #4b5563;
  font-family: var(--font-m); font-size: .52rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  padding: 9px 6px; border: 1px solid #e3e6ea;
  white-space: nowrap; text-align: center;
  position: sticky; top: 0; z-index: 20;
}
.sr-table thead tr.grp-row th {
  font-size: .5rem; padding: 4px 6px;
  background: #ebedf0; letter-spacing: .05em; color: #374151;
}

/* ── Sticky date + action columns ── */
.th-date {
  position: sticky !important; left: 0; z-index: 30 !important;
  width: 88px; min-width: 88px;
  background: #ebedf0 !important; color: var(--accent) !important;
  box-shadow: 2px 0 5px rgba(0,0,0,.07);
}
.th-act {
  position: sticky !important; right: 0; z-index: 30 !important;
  width: 66px; min-width: 66px;
  background: #ebedf0 !important; box-shadow: -2px 0 5px rgba(0,0,0,.07);
}
.td-date {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb !important; box-shadow: 2px 0 5px rgba(0,0,0,.06);
  font-family: var(--font-m); font-size: .69rem;
  color: var(--accent); font-weight: 600;
  text-align: center; padding: 0 6px;
  white-space: nowrap; width: 88px; min-width: 88px;
}
.td-act {
  position: sticky; right: 0; z-index: 5;
  background: #f8f9fb !important; box-shadow: -2px 0 5px rgba(0,0,0,.06);
  text-align: center; padding: 3px 5px;
  width: 66px; min-width: 66px;
}

/* ── Column colours ── */
.g-income { background: #f0fdf4 !important; }
.g-deduct { background: #fff7f7 !important; }
.g-calc   { background: #eff6ff !important; }
.g-text   { background: #faf5ff !important; }

/* ── Column widths ── */
.col-num  { width: 100px; min-width: 100px; }
.col-txt  { width: 160px; min-width: 160px; }

/* ── Body rows ── */
.sr-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.sr-table tbody tr:hover td { filter: brightness(.97); }
.sr-table td { border: 1px solid #e8eaed; padding: 0; vertical-align: middle; }

/* ── Inputs ── */
.sri {
  width: 100%; padding: 6px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-m); font-size: .69rem;
  text-align: right; display: block; box-sizing: border-box;
}
.sri:focus { background: rgba(15,123,92,.07); outline: 1px solid rgba(15,123,92,.4); }
.sri.calc  { color: #1d4ed8; background: rgba(37,99,235,.05); cursor: default; font-weight: 600; }
.sri.txt   { text-align: left; font-family: var(--font-h); font-size: .69rem; }
.sri.neg   { color: #be123c !important; font-weight: 600; }

/* ── Save button ── */
.bsr {
  padding: 4px 8px; font-size: .6rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; letter-spacing: .02em; width: 56px;
}
.bsr:hover  { background: #dcfce7; border-color: #86efac; }
.bsr.saving { opacity: .5; pointer-events: none; }
.bsr.ok     { background: #dcfce7; color: #15803d; border-color: #86efac; }
.bsr.err    { background: #fff1f2; color: #be123c; border-color: #fecdd3; }

/* ── Totals row ── */
.sr-table tfoot td {
  padding: 8px 6px; font-family: var(--font-m); font-size: .69rem; font-weight: 700;
  text-align: right; border: 1px solid #d1d5db;
  background: #f0f2f5; color: #1a1d23;
}
.sr-table tfoot td.tfl {
  text-align: center; color: var(--accent);
  font-size: .6rem; text-transform: uppercase; letter-spacing: .06em;
}

/* ── Status dot ── */
.sdot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-left: 4px; vertical-align: middle; }
.sdot.ok  { background: #22c55e; box-shadow: 0 0 4px #22c55e; }
.sdot.new { background: #f59e0b; }
.sdot.auto { background: #3b82f6; box-shadow: 0 0 4px #3b82f6; }

/* ── Toast ── */
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

/* ── Legend ── */
.sr-leg {
  display: flex; gap: 12px; flex-wrap: wrap;
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  margin-bottom: 10px; align-items: center;
}
.sr-leg span { display: flex; align-items: center; gap: 5px; }
.ld { display: inline-block; width: 9px; height: 9px; border-radius: 3px; }

/* ── Controls ── */
.sr-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }

/* ── Scroll hint ── */
.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  text-align: center; padding: 6px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Hero Carwash <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily spreadsheet entry · full H Summary Report column set · auto-filled from the Carwash Sales Report where a source exists, manual otherwise · each row has its own Save button · Save All saves every row</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 Hero Carwash
  </div>
  <select name="month" class="form-control" style="max-width:130px" onchange="this.form.submit()">
    <?php for ($m = 1; $m <= 12; $m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$monthNames[$m]?></option>
    <?php endfor; ?>
  </select>
  <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
    <?php for ($y = 2050; $y >= 2023; $y--): ?>
    <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="button" class="btn btn-primary btn-sm" onclick="saveAll()">💾 Save All</button>
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="downloadCSV(event)">⬇ Download CSV</a>
</form>

<!-- Legend -->
<div class="sr-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income / Sales</span>
  <span><span class="ld" style="background:#fecaca;border:1px solid #fca5a5"></span>Deductions</span>
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Auto-calculated (from Sales Report)</span>
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved</span>
</div>

<!-- Spreadsheet -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <tr class="grp-row">
      <th class="th-date"></th>
      <!-- AUTO-CALCULATED -->
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <!-- SALES / INCOME -->
      <th colspan="5" class="g-income">SALES / INCOME</th>
      <!-- COLLECTIONS -->
      <th colspan="9" class="g-income">COLLECTIONS</th>
      <!-- DEDUCTIONS -->
      <th colspan="5" class="g-deduct">DEDUCTIONS</th>
      <!-- SC FOR DEPO -->
      <th colspan="1" class="g-deduct">SC FOR DEPO</th>
      <!-- AUTO-CALCULATED -->
      <th colspan="3" class="g-calc">AUTO-CALCULATED</th>
      <!-- OTHER -->
      <th colspan="2" class="g-income">OTHER</th>
      <!-- NOTES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th colspan="1" class="g-income">OTHER</th>
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- AUTO-CALCULATED -->
      <th class="g-calc col-num">GROSS SALES EXCL MKTG</th>
      <th class="g-calc col-num">STORE GROSS (EXCL SC/MKTG)</th>
      <!-- SALES / INCOME -->
      <th class="g-income col-num">Z READING GROSS (INCL SC/MKTG)</th>
      <th class="g-income col-num">CASH FOR DEPO</th>
      <th class="g-income col-num">SALES OF THE DAY (SWIPE)</th>
      <th class="g-income col-num">DEPOSIT SWIPE</th>
      <th class="g-income col-num">LATE PAYMENT</th>
      <!-- COLLECTIONS -->
      <th class="g-deduct col-num">CANCELLED TRANSACTION</th>
      <th class="g-deduct col-num">UNPAID</th>
      <th class="g-income col-num">PAID</th>
      <th class="g-income col-num">ADVANCE PAYMENT</th>
      <th class="g-income col-num">GRAB</th>
      <th class="g-income col-num">BANK TRANS</th>
      <th class="g-income col-num">GC SPONSOR / MARKETING</th>
      <th class="g-deduct col-num">GC SOLD</th>
      <th class="g-deduct col-num">DISCOUNT</th>
      <!-- DEDUCTIONS -->
      <th class="g-deduct col-num">MARKETING PULL OUT</th>
      <th class="g-deduct col-num">PERSONAL</th>
      <th class="g-deduct col-num">EXPENSES</th>
      <th class="g-deduct col-num">OTHER EXPENSES</th>
      <!-- SC FOR DEPO -->
      <th class="g-deduct col-num">SC FOR DEPO</th>
      <!-- AUTO-CALCULATED -->
      <th class="g-calc col-num">TOTAL DEDUCTIONS</th>
      <th class="g-calc col-num">SHORT / OVER</th>
      <th class="g-calc col-num">TOTAL SWIPE</th>
      <!-- OTHER -->
      <th class="g-income col-num">CASH DEPOSIT</th>
      <!-- NOTES -->
      <th class="g-text col-txt">REMARKS</th>
      <!-- OTHER SALES -->
      <th class="g-income col-num">OTHER SALES</th>
      <!-- NOTES 2 -->
      <th class="g-text col-txt">REMARKS (2)</th>
      <th class="th-act">SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN  = (int)date('j', strtotime($ds));
    $dayNm = date('D', strtotime($ds));
    $row   = $savedRows[$ds] ?? null;
    $saved = $row !== null;
    $sr    = $salesRows[$ds] ?? null;
    $hasSR = $sr !== null;
    $srMap = $hasSR ? cwMapFromSalesReport($sr) : [];
    $rid   = 'r' . str_replace('-', '', $ds);
    $dv    = function($k) use ($row, $srMap, $hasSR) {
        if ($hasSR && isset($srMap[$k])) {
            $v = $srMap[$k];
            return $v != 0 ? number_format($v, 2, '.', '') : '';
        }
        if ($row && isset($row[$k]) && (float)$row[$k] != 0) {
            return number_format((float)$row[$k], 2, '.', '');
        }
        return '';
    };
    // A row counts as "saved" (green dot) once the Sales Report has data
    // for that date too, even if no one has clicked Save on this page yet.
    $rowSaved = $saved || $hasSR;
    // Columns the Sales Report writes automatically render readonly with the
    // blue "calc" mirror style; the rest stay editable manual entries.
    $isAuto = fn($k) => $hasSR && in_array($k, $AUTO_COLS, true);
    $cls    = fn($k) => $isAuto($k) ? 'sri calc' : 'sri';
    $attrs  = fn($k) => $isAuto($k) ? 'readonly tabindex="-1"' : 'oninput="changed(this)"';
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$rowSaved?1:0?>" data-fromsr="<?=$hasSR?1:0?>">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$rowSaved?'ok':'new'?>" id="dot_<?=$rid?>" title="<?=($hasSR && !$saved)?'Auto-filled from Carwash Sales Report':''?>"></span>
    </td>

    <!-- AUTO-CALCULATED -->
    <td class="g-calc"><input type="number" step="0.01" class="<?=$cls('gross_sales_excl_mktg')?>" data-col="gross_sales_excl_mktg" data-row="<?=$rid?>" value="<?=$dv('gross_sales_excl_mktg')?>" placeholder="0.00" <?=$attrs('gross_sales_excl_mktg')?>></td>
    <td class="g-calc"><input type="number" step="0.01" class="<?=$cls('store_gross_excl_mktg')?>" data-col="store_gross_excl_mktg" data-row="<?=$rid?>" value="<?=$dv('store_gross_excl_mktg')?>" placeholder="0.00" <?=$attrs('store_gross_excl_mktg')?>></td>
    <!-- SALES / INCOME -->
    <td class="g-income"><input type="number" step="0.01" class="<?=$cls('z_reading_gross')?>" data-col="z_reading_gross" data-row="<?=$rid?>" value="<?=$dv('z_reading_gross')?>" placeholder="0.00" <?=$attrs('z_reading_gross')?>></td>
    <td class="g-income"><input type="number" step="0.01" class="<?=$cls('cash_for_depo')?>" data-col="cash_for_depo" data-row="<?=$rid?>" value="<?=$dv('cash_for_depo')?>" placeholder="0.00" <?=$attrs('cash_for_depo')?>></td>
    <td class="g-income"><input type="number" step="0.01" class="<?=$cls('sales_of_day_swipe')?>" data-col="sales_of_day_swipe" data-row="<?=$rid?>" value="<?=$dv('sales_of_day_swipe')?>" placeholder="0.00" <?=$attrs('sales_of_day_swipe')?>></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="deposit_swipe" data-row="<?=$rid?>" value="<?=$dv('deposit_swipe')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="late_payment" data-row="<?=$rid?>" value="<?=$dv('late_payment')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- COLLECTIONS -->
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="cancelled_transaction" data-row="<?=$rid?>" value="<?=$dv('cancelled_transaction')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="<?=$cls('unpaid')?>" data-col="unpaid" data-row="<?=$rid?>" value="<?=$dv('unpaid')?>" placeholder="0.00" <?=$attrs('unpaid')?>></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="paid" data-row="<?=$rid?>" value="<?=$dv('paid')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="<?=$cls('advance_payment')?>" data-col="advance_payment" data-row="<?=$rid?>" value="<?=$dv('advance_payment')?>" placeholder="0.00" <?=$attrs('advance_payment')?>></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="grab" data-row="<?=$rid?>" value="<?=$dv('grab')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="bank_trans" data-row="<?=$rid?>" value="<?=$dv('bank_trans')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="gc_sponsor_marketing" data-row="<?=$rid?>" value="<?=$dv('gc_sponsor_marketing')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="gc_sold" data-row="<?=$rid?>" value="<?=$dv('gc_sold')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="<?=$cls('discount')?>" data-col="discount" data-row="<?=$rid?>" value="<?=$dv('discount')?>" placeholder="0.00" <?=$attrs('discount')?>></td>
    <!-- DEDUCTIONS -->
    <td class="g-deduct"><input type="number" step="0.01" class="<?=$cls('marketing_pull_out')?>" data-col="marketing_pull_out" data-row="<?=$rid?>" value="<?=$dv('marketing_pull_out')?>" placeholder="0.00" <?=$attrs('marketing_pull_out')?>></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="personal" data-row="<?=$rid?>" value="<?=$dv('personal')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="<?=$cls('expenses')?>" data-col="expenses" data-row="<?=$rid?>" value="<?=$dv('expenses')?>" placeholder="0.00" <?=$attrs('expenses')?>></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="other_expenses" data-row="<?=$rid?>" value="<?=$dv('other_expenses')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- SC FOR DEPO -->
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="sc_for_depo" data-row="<?=$rid?>" value="<?=$dv('sc_for_depo')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- AUTO-CALCULATED -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_deductions" data-row="<?=$rid?>" value="<?=$dv('total_deductions')?>" readonly tabindex="-1"></td>
    <td class="g-calc"><input type="number" step="0.01" class="<?=$cls('short_over')?>" data-col="short_over" data-row="<?=$rid?>" value="<?=$dv('short_over')?>" <?=$attrs('short_over')?>></td>
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_swipe" data-row="<?=$rid?>" value="<?=$dv('total_swipe')?>" readonly tabindex="-1"></td>
    <!-- OTHER -->
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="cash_deposit" data-row="<?=$rid?>" value="<?=$dv('cash_deposit')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- REMARKS -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks']??'')?>" placeholder="…" oninput="changed(this)"></td>
    <!-- OTHER SALES -->
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="other_sales" data-row="<?=$rid?>" value="<?=$dv('other_sales')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- REMARKS 2 -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks2" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks2']??'')?>" placeholder="…" oninput="changed(this)"></td>

    <td class="td-act">
      <button class="bsr" id="btn_<?=$rid?>" onclick="saveRow('<?=$rid?>','<?=$ds?>')">
        <?=$rowSaved?'Update':'Save'?>
      </button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <?php foreach ($COLS as $col): ?>
      <td id="tot_<?=$col?>">—</td>
      <?php endforeach; ?>
      <td>—</td><!-- remarks -->
      <td>—</td><!-- remarks2 -->
      <td class="tfl"></td>
    </tr>
  </tfoot>
</table>
</div>

<div style="display:flex;justify-content:flex-end;margin-top:12px;gap:10px">
  <button class="btn btn-primary" onclick="saveAll()">💾 Save All Rows</button>
</div>

<!-- ── TARGET SALES SUMMARY PANEL ── -->
<div class="card" style="margin-top:20px;border-top:2px solid var(--accent3);overflow:hidden">
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">Hero Carwash — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="cwQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcCwSummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';saveCwQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="cwSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Gross Sales ÷ Target</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="cwNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Gross Sales</div>
    </div>

    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="cwDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="cwDaysInput" type="number" min="1" step="1" value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcCwSummary()"
          onfocus="this.style.borderColor='#0f7b5c'"
          onblur="this.style.borderColor='#d1d5db'">
        <span style="font-family:var(--font-m);font-size:.58rem;color:var(--subtext)">update daily</span>
      </div>
    </div>

  </div>
</div>

<script>
const COLS = <?=json_encode($COLS)?>;

// ── Helpers ───────────────────────────────────────────────
function gv(rid, col) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  return e ? (parseFloat(e.value) || 0) : 0;
}

// ── Auto-calculations ─────────────────────────────────────
// Ported directly from h_summary_report.php's recalc() — same formulas,
// same column names, now that Carwash Summary has the full column set.
function recalc(rid) {
  const tr = document.getElementById(rid);
  // Dates the Carwash Sales Report has data for keep that report's own
  // Short/Over figure exactly (readonly mirror) instead of recomputing it
  // here — same "preserve" approach h_summary_report.php uses.
  const preserveSR = !!(tr && tr.dataset.fromsr === '1');

  const autoCalcCols = ['gross_sales_excl_mktg','store_gross_excl_mktg','total_deductions','short_over','total_swipe'];
  const hasInput = COLS.some(c => !autoCalcCols.includes(c) ? (gv(rid, c) !== 0) : false);

  const zReading    = gv(rid, 'z_reading_gross');
  const mktgPullOut = gv(rid, 'marketing_pull_out');
  const disc        = gv(rid, 'discount');
  const scForDepo   = gv(rid, 'sc_for_depo');
  const cashForDepo = gv(rid, 'cash_for_depo');

  // GROSS SALES EXCL MKTG = Z Reading Gross − Marketing Pull Out
  // (fallback formula for dates with no Sales Report — SR dates keep their
  // own preserved figure, mirrored from the Sales Report's own Gross Sales)
  const grossSales = zReading - mktgPullOut;
  if (!preserveSR) {
    const gsEl = document.querySelector(`#${rid} [data-col="gross_sales_excl_mktg"]`);
    if (gsEl) gsEl.value = hasInput ? grossSales.toFixed(2) : '';
  }

  // STORE GROSS = Z Reading Gross + Discount − SC for Depo − Marketing Pull Out
  const storeGross = zReading + disc - scForDepo - mktgPullOut;
  if (!preserveSR) {
    const sgEl = document.querySelector(`#${rid} [data-col="store_gross_excl_mktg"]`);
    if (sgEl) sgEl.value = hasInput ? storeGross.toFixed(2) : '';
  }

  // TOTAL DEDUCTIONS = Sales of Day Swipe + Unpaid + Paid + Advance Payment +
  //   Grab + Bank Trans + GC Sponsor/Mktg + GC Sold + Discount +
  //   Expenses + Other Expenses + Personal
  const td = gv(rid, 'sales_of_day_swipe')
           + gv(rid, 'unpaid')
           + gv(rid, 'paid')
           + gv(rid, 'advance_payment')
           + gv(rid, 'grab')
           + gv(rid, 'bank_trans')
           + gv(rid, 'gc_sponsor_marketing')
           + gv(rid, 'gc_sold')
           + disc
           + gv(rid, 'expenses')
           + gv(rid, 'other_expenses')
           + gv(rid, 'personal');
  const tdEl = document.querySelector(`#${rid} [data-col="total_deductions"]`);
  if (tdEl) tdEl.value = hasInput ? td.toFixed(2) : '';

  // SHORT / OVER = Total Deductions + Cash for Depo − Store Gross
  // (fallback formula for dates with no Sales Report — SR dates keep their
  // own preserved figure above)
  const so = td + cashForDepo - storeGross;
  if (!preserveSR) {
    const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
    if (soEl) {
      soEl.value = hasInput ? so.toFixed(2) : '';
      soEl.classList.toggle('neg', hasInput && so < 0);
    }
  }

  // TOTAL SWIPE = Sales of Day Swipe + Deposit Swipe + Late Payment + Paid
  const ts = gv(rid, 'sales_of_day_swipe')
           + gv(rid, 'deposit_swipe')
           + gv(rid, 'late_payment')
           + gv(rid, 'paid');
  const tsEl = document.querySelector(`#${rid} [data-col="total_swipe"]`);
  if (tsEl) tsEl.value = hasInput ? ts.toFixed(2) : '';
}

// ── Column totals ─────────────────────────────────────────
function recalcTotals() {
  COLS.forEach(function(col) {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`[data-col="${col}"]`).forEach(function(i) {
      s += parseFloat(i.value) || 0;
    });
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  });
  recalcCwSummary();
}

// ── Mark row dirty on input ───────────────────────────────
function changed(el) {
  const rid = el.dataset.row;
  recalc(rid);
  recalcTotals();
  const btn = document.getElementById('btn_' + rid);
  btn.textContent = 'Save';
  btn.className = 'bsr';
  document.getElementById(rid).dataset.saved = '0';
  document.getElementById('dot_' + rid).className = 'sdot new';
}

// ── Save single row ───────────────────────────────────────
async function saveRow(rid, ds) {
  const btn = document.getElementById('btn_' + rid);
  btn.textContent = '…'; btn.className = 'bsr saving';

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('report_date', ds);

  COLS.forEach(function(col) {
    const el = document.querySelector(`#${rid} [data-col="${col}"]`);
    fd.append(col, el ? (el.value || '0') : '0');
  });
  ['remarks','remarks2'].forEach(function(col) {
    const el = document.querySelector(`#${rid} [data-col="${col}"]`);
    fd.append(col, el ? el.value : '');
  });

  try {
    const res  = await fetch('h_carwash_summary_report.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsr ok';
      document.getElementById(rid).dataset.saved = '1';
      document.getElementById('dot_' + rid).className = 'sdot ok';
      setTimeout(function(){ if (btn.className.includes('ok')) btn.className = 'bsr'; }, 2200);
    } else {
      btn.textContent = 'Error'; btn.className = 'bsr err';
      showToast('❌ ' + data.msg, 'error');
      setTimeout(function(){ btn.textContent = 'Save'; btn.className = 'bsr'; }, 3000);
    }
  } catch(e) {
    btn.textContent = 'Error'; btn.className = 'bsr err';
    showToast('❌ Network error', 'error');
  }
}

// ── Save all rows ─────────────────────────────────────────
async function saveAll() {
  const rows = [...document.querySelectorAll('#srt tbody tr')];
  for (const row of rows) {
    await saveRow(row.id, row.dataset.date);
    await new Promise(r => setTimeout(r, 50));
  }
  showToast('✓ All ' + rows.length + ' rows saved for Hero Carwash', 'success');
}

// ── CSV download ──────────────────────────────────────────
function downloadCSV(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv', '1');
  const monthEl = document.querySelector('select[name="month"]');
  if (monthEl) params.set('month', monthEl.value);
  const yearEl = document.querySelector('select[name="year"]');
  if (yearEl) params.set('year', yearEl.value);
  window.location.href = 'h_carwash_summary_report.php?' + params.toString();
}

// ── Toast ─────────────────────────────────────────────────
function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(function(){ t.remove(); }, 4000);
}

// ── Enter key = move down same column ─────────────────────
document.addEventListener('keydown', function(e) {
  if (!e.target.classList.contains('sri') || e.key !== 'Enter') return;
  e.preventDefault();
  const col = e.target.dataset.col, rid = e.target.dataset.row;
  const next = document.getElementById(rid)?.nextElementSibling;
  if (next) { const ni = next.querySelector(`[data-col="${col}"]`); if(ni){ni.focus();ni.select();} }
});

// ── Monthly performance panel ─────────────────────────────
function recalcCwSummary() {
  const quota = parseFloat(document.getElementById('cwQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('cwDaysInput')?.value)   || 1;

  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="gross_sales_excl_mktg"]').forEach(function(el) {
    totalGross += parseFloat(el.value) || 0;
  });

  const pctEl    = document.getElementById('cwSalesPct');
  const neededEl = document.getElementById('cwNeeded');
  const dailyEl  = document.getElementById('cwDailyQuota');

  if (quota <= 0) {
    if(pctEl)    pctEl.textContent    = '—';
    if(neededEl) neededEl.textContent = '—';
    if(dailyEl)  dailyEl.textContent  = '—';
    return;
  }

  const pct = (totalGross / quota) * 100;
  if(pctEl) {
    pctEl.textContent = pct.toFixed(2) + '%';
    pctEl.style.color = pct >= 100 ? 'var(--accent)' : (pct >= 70 ? 'var(--accent3)' : 'var(--accent2)');
  }

  const needed = quota - totalGross;
  if(neededEl) {
    if (needed <= 0) {
      neededEl.textContent = '✓ Target Met!';
      neededEl.style.color = 'var(--accent)';
    } else {
      neededEl.textContent = '(' + needed.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + ')';
      neededEl.style.color = 'var(--accent2)';
    }
  }

  const daily = needed <= 0 ? 0 : needed / days;
  if(dailyEl) {
    dailyEl.textContent = needed <= 0
      ? '✓ Done!'
      : '(' + daily.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + ')';
    dailyEl.style.color = needed <= 0 ? 'var(--accent)' : 'var(--accent2)';
  }
}

// ── Save Carwash quota via AJAX ─────────────────────────────
async function saveCwQuota() {
  const val = document.getElementById('cwQuotaInput')?.value;
  if (!val) return;
  const monthEl = document.querySelector('select[name="month"]');
  const yearEl  = document.querySelector('select[name="year"]');
  const m = monthEl ? monthEl.value.toString().padStart(2,'0') : '<?= date('m') ?>';
  const y = yearEl  ? yearEl.value  : '<?= $fYear ?>';
  const fd = new FormData();
  fd.append('ajax_quota','1');
  fd.append('report_month', y + '-' + m);
  fd.append('quota', val);
  try {
    await fetch('h_carwash_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#srt tbody tr').forEach(function(r){ recalc(r.id); });
  recalcTotals();
  recalcCwSummary();
});
</script>
</body>
</html>