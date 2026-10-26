<?php
// ============================================================
//  h_summary_report.php — H Breakfast to Bar Daily Summary Entry
//  Mirrors ../dois/dois_summary_report.php pattern — standalone flat table
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

// ── Auto-create H table (safe to run repeatedly) ──────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `h_report_entries` (
        `id`                    int(11) NOT NULL AUTO_INCREMENT,
        `report_date`           date NOT NULL,
        `store_name`            varchar(100) NOT NULL DEFAULT 'H',
        `gross_sales_excl_mktg` decimal(12,2) DEFAULT 0.00,
        `store_gross`           decimal(12,2) DEFAULT 0.00,
        `z_reading_gross`       decimal(12,2) DEFAULT 0.00,
        `cash_for_depo`         decimal(12,2) DEFAULT 0.00,
        `sales_of_day_swipe`    decimal(12,2) DEFAULT 0.00,
        `palawan_gcash`         decimal(12,2) DEFAULT 0.00,
        `deposit_swipe`         decimal(12,2) DEFAULT 0.00,
        `late_payment`          decimal(12,2) DEFAULT 0.00,
        `cancelled_transaction` decimal(12,2) DEFAULT 0.00,
        `unpaid`                decimal(12,2) DEFAULT 0.00,
        `paid`                  decimal(12,2) DEFAULT 0.00,
        `advance_payment`       decimal(12,2) DEFAULT 0.00,
        `grab`                  decimal(12,2) DEFAULT 0.00,
        `bank_trans`            decimal(12,2) DEFAULT 0.00,
        `gc_sponsor_marketing`  decimal(12,2) DEFAULT 0.00,
        `gc_sold`               decimal(12,2) DEFAULT 0.00,
        `discount`              decimal(12,2) DEFAULT 0.00,
        `marketing_pull_out`    decimal(12,2) DEFAULT 0.00,
        `personal`              decimal(12,2) DEFAULT 0.00,
        `expenses`              decimal(12,2) DEFAULT 0.00,
        `other_expenses`        decimal(12,2) DEFAULT 0.00,
        `sc_for_depo`           decimal(12,2) DEFAULT 0.00,
        `total_deductions`      decimal(12,2) DEFAULT 0.00,
        `short_over`            decimal(12,2) DEFAULT 0.00,
        `total_swipe`           decimal(12,2) DEFAULT 0.00,
        `cash_deposit`          decimal(12,2) DEFAULT 0.00,
        `other_sales`           decimal(12,2) DEFAULT 0.00,
        `remarks`               text DEFAULT NULL,
        `remarks2`              text DEFAULT NULL,
        `saved_by`              varchar(100) DEFAULT NULL,
        `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

// Palawan/Gcash column (added later) — add it to existing installs too.
try { $pdo->exec("ALTER TABLE `h_report_entries` ADD COLUMN `palawan_gcash` decimal(12,2) DEFAULT 0.00 AFTER `sales_of_day_swipe`"); } catch (Throwable $ignored) {}

// One row per store per date — remove any old duplicates and enforce it.
try {
    require_once __DIR__ . '/../lib/branch_sales.php';
    ensureUniqueStoreDate($pdo, 'h_report_entries');
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
        'gross_sales_excl_mktg','store_gross','z_reading_gross','cash_for_depo',
        'sales_of_day_swipe','palawan_gcash','deposit_swipe','late_payment',
        'cancelled_transaction','unpaid','paid','advance_payment',
        'grab','bank_trans','gc_sponsor_marketing','gc_sold','discount',
        'marketing_pull_out','personal','expenses','other_expenses','sc_for_depo',
        'total_deductions','short_over','total_swipe','cash_deposit','other_sales'
    ];
    $txtCols = ['remarks','remarks2'];

    $data = ['store_name' => 'H', 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($txtCols as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO h_report_entries ("
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
            ->execute([$month.'-01', 'H', $quota, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle Excel (.xlsx) download ─────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $csvMonthNames = ['','January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    $csvCols = [
        'report_date'           => 'Date',
        'gross_sales_excl_mktg' => 'Gross Sales Excl Marketing',
        'store_gross'           => 'Store Gross Sales (Excl SC/Mktg)',
        'z_reading_gross'       => 'Z Reading Gross (Incl SC/Mktg)',
        'cash_for_depo'         => 'Cash for Depo',
        'sales_of_day_swipe'    => 'Sales of the Day (Swipe)',
        'palawan_gcash'         => 'Palawan/Gcash',
        'deposit_swipe'         => 'Deposit Swipe',
        'late_payment'          => 'Late Payment',
        'cancelled_transaction' => 'Cancelled Transaction',
        'unpaid'                => 'Unpaid',
        'paid'                  => 'Paid',
        'advance_payment'       => 'Advance Payment',
        'grab'                  => 'Grab',
        'bank_trans'            => 'Bank Trans',
        'gc_sponsor_marketing'  => 'GC Sponsor / Marketing',
        'gc_sold'               => 'GC Sold',
        'discount'              => 'Discount',
        'marketing_pull_out'    => 'Marketing Pull Out',
        'personal'              => 'Personal',
        'expenses'              => 'Expenses',
        'other_expenses'        => 'Other Expenses',
        'sc_for_depo'           => 'SC for Depo',
        'total_deductions'      => 'Total Deductions',
        'short_over'            => 'Short / Over',
        'total_swipe'           => 'Total Swipe',
        'cash_deposit'          => 'Cash Deposit',
        'other_sales'           => 'Other Sales',
        'remarks'               => 'Remarks',
        'remarks2'              => 'Remarks (2)',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM h_report_entries WHERE store_name='H' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = 'H_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.xlsx';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('H'));
    $xlsx->title('H Breakfast to Bar — Summary Report');
    $xlsx->subtitle($csvMonthNames[$csvMonth] . ' ' . $csvYear);
    $xlsx->blank();
    $xlsx->header(array_values($csvCols));

    $totals = array_fill_keys(array_keys($csvCols), 0);
    foreach ($csvDays as $ds) {
        $row  = $csvRows[$ds] ?? null;
        $line = [];
        foreach (array_keys($csvCols) as $key) {
            if ($key === 'report_date') {
                $line[] = date('d-M-Y (D)', strtotime($ds));
            } elseif (in_array($key, ['remarks','remarks2'])) {
                $line[] = $row[$key] ?? '';
            } else {
                $val    = (float)($row[$key] ?? 0);
                $line[] = $val != 0 ? number_format($val, 2, '.', '') : '';
                $totals[$key] += $val;
            }
        }
        $xlsx->row($line);
    }
    $totalLine = ['TOTAL'];
    foreach (array_keys($csvCols) as $key) {
        if ($key === 'report_date') continue;
        if (in_array($key, ['remarks','remarks2'])) { $totalLine[] = ''; continue; }
        $totalLine[] = $totals[$key] != 0 ? number_format($totals[$key], 2, '.', '') : '0.00';
    }
    $xlsx->totalRow($totalLine);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths(array_merge([14], array_fill(0, count($csvCols) - 3, 11), [16, 16]));
    $xlsx->output($filename);
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

// ── Load saved rows ───────────────────────────────────────
$savedRows = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM h_report_entries WHERE store_name='H' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull fallback data from the H Sales Report for dates that don't    ──
// have a manually-saved summary row yet. Same pattern as
// ../dois/dois_summary_report.php / ../dois/dois_sales_report.php.
$salesRows = [];
try {
    $ssStmt = $pdo->prepare("SELECT * FROM h_sales_report WHERE store_name='H' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $ssStmt->execute([$fYear, $fMonth]);
    foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;

    // Cancelled Transaction isn't a column on the main sales-report row —
    // it's summed from the DINE IN detail rows, same as Dois/Stella.
    $diStmt = $pdo->prepare("SELECT report_date, COALESCE(SUM(cancelled_transactions),0) AS cancelled_total
        FROM h_dinein_rows WHERE store_name='H' AND YEAR(report_date)=? AND MONTH(report_date)=?
        GROUP BY report_date");
    $diStmt->execute([$fYear, $fMonth]);
    foreach ($diStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $salesRows[$r['report_date']]['_cancelled_total'] = (float)$r['cancelled_total'];
    }
} catch (Throwable $ignored) {}

// Map: Summary Report column => H Sales Report source column (or a
// computed key above). Fields with no clear 1:1 source — Gross Sales
// Excl Mktg, Store Gross, Total Deductions (all computed here), Paid,
// Advance Payment, GC Sponsor/Marketing, Personal, Cash Deposit, Other
// Sales, and both Remarks — have no equivalent in h_sales_report and
// stay manual entries, same as before.
$SALES_MAP = [
    'z_reading_gross'       => 'z_reading_gross',
    'cash_for_depo'         => 'coh',
    'sales_of_day_swipe'    => 'maya_swipe',
    'palawan_gcash'         => 'gcash',
    'deposit_swipe'         => 'deposit_swipe_card',
    'late_payment'          => 'late_payment_card',
    'unpaid'                => 'unpaid_med_credit',
    'grab'                  => 'grab_sales',
    'bank_trans'            => 'bank_transfer_cheque',
    'gc_sold'                => 'gift_card',
    'discount'               => 'discount',
    'marketing_pull_out'     => 'marketing_pull_out',
    'expenses'               => 'pcf_expenses',
    'other_expenses'         => 'other_expenses',
    'sc_for_depo'            => 'service_charge',
    'total_swipe'            => 'total_swipe',
    'short_over'             => 'short_over',
    'cancelled_transaction'  => '_cancelled_total',
];
// Fields that stay editable/never lock even when the Sales Report has
// data for the date — pre-filled from the Sales Report as a starting
// default, but a manually-saved value always takes priority.
$NEVER_LOCK = ['cancelled_transaction'];

// ── Live sync: current H Sales Report values for this month (polled by JS) ──
if (isset($_GET['ajax_sync'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $out = [];
    foreach ($salesRows as $d => $sr) {
        $o = [];
        foreach ($SALES_MAP as $k => $src) $o[$k] = round((float)($sr[$src] ?? 0), 2);
        $out[$d] = $o;
    }
    echo json_encode(['ok' => true, 'rows' => $out]);
    exit;
}

// ── Load saved quota ──────────────────────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='H' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([$fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column keys (numeric, for tfoot totals) ───────────────
$COLS = [
    'gross_sales_excl_mktg','store_gross','z_reading_gross','cash_for_depo',
    'sales_of_day_swipe','palawan_gcash','deposit_swipe','late_payment',
    'cancelled_transaction','unpaid','paid','advance_payment',
    'grab','bank_trans','gc_sponsor_marketing','gc_sold','discount',
    'marketing_pull_out','personal','expenses','other_expenses','sc_for_depo',
    'total_deductions','short_over','total_swipe','cash_deposit','other_sales'
];

$pageTitle  = 'H Summary Report';
$activePage = 'h_summary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* ── Scroll container ── */
.sr-outer {
  width: 100%; overflow: auto;
  max-height: calc(100vh - 260px);   /* refined by fitTable() in the script below */
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

/* 2nd header row sits right under the group row (height set by fitTable()) */
.sr-table thead tr:nth-child(2) th { top: var(--grp-h, 20px); }

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
.asv { font-size:.68rem; font-weight:600; color:var(--subtext); }
.asv.saving { color:#b45309; }
.asv.ok { color:#15803d; }
.asv.err { color:#be123c; }
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
/* TOTAL row stays pinned to the bottom of the table while the rows scroll */
.sr-table tfoot td {
  position: sticky; bottom: 0; z-index: 15;
  box-shadow: 0 -2px 6px rgba(0,0,0,.10);
}
.sr-table tfoot td:first-child { left: 0;  z-index: 35; }
.sr-table tfoot td:last-child  { right: 0; z-index: 35; }

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
    <div class="section-title" style="color:#1a1d23">H <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Auto-saves as you type (empty rows are never saved) · linked cells update by themselves from the Sales Report</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 H
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
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="downloadCSV(event)">⬇ Download Excel</a>
</form>

<!-- Legend -->
<div class="sr-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income / Sales</span>
  <span><span class="ld" style="background:#fecaca;border:1px solid #fca5a5"></span>Deductions</span>
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Auto-calculated</span>
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved</span>
</div>

<!-- Spreadsheet -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <tr class="grp-row">
      <th class="th-date"></th>
      <!-- AUTO-CALC SALES -->
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <!-- SALES / INCOME -->
      <th colspan="6" class="g-income">SALES / INCOME</th>
      <!-- COLLECTIONS -->
      <th colspan="9" class="g-income">COLLECTIONS</th>
      <!-- DEDUCTIONS -->
      <th colspan="5" class="g-deduct">DEDUCTIONS</th>
      <!-- SC FOR DEPO (user input) -->
      <th colspan="1" class="g-deduct">SC FOR DEPO</th>
      <!-- AUTO-CALCULATED -->
      <th colspan="3" class="g-calc">AUTO-CALCULATED</th>
      <!-- OTHER -->
      <th colspan="2" class="g-income">OTHER</th>
      <!-- NOTES + OTHER SALES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th colspan="1" class="g-income">OTHER</th>
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- AUTO-CALC SALES -->
      <th class="g-calc col-num">GROSS SALES EXCL MKTG</th>
      <th class="g-calc col-num">STORE GROSS (EXCL SC/MKTG)</th>
      <!-- SALES / INCOME -->
      <th class="g-income col-num">Z READING GROSS (INCL SC/MKTG)</th>
      <th class="g-income col-num">CASH FOR DEPO</th>
      <th class="g-income col-num">SALES OF THE DAY (SWIPE)</th>
      <th class="g-income col-num">PALAWAN/GCASH</th>
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
      <!-- SC FOR DEPO — user editable -->
      <th class="g-deduct col-num">SC FOR DEPO</th>
      <!-- AUTO-CALCULATED -->
      <th class="g-calc col-num">TOTAL DEDUCTIONS</th>
      <th class="g-calc col-num">SHORT / OVER</th>
      <th class="g-calc col-num">TOTAL SWIPE</th>
      <!-- OTHER INPUT -->
      <th class="g-income col-num">CASH DEPOSIT</th>
      <!-- NOTES 1 -->
      <th class="g-text col-txt">REMARKS</th>
      <!-- OTHER SALES -->
      <th class="g-income col-num">OTHER SALES</th>
      <!-- NOTES 2 -->
      <th class="g-text col-txt">REMARKS (2)</th>
      <th class="th-act">AUTO-SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN  = (int)date('j', strtotime($ds));
    $dayNm = date('D', strtotime($ds));
    $row   = $savedRows[$ds] ?? null;
    $saved = $row !== null;
    $sr    = $salesRows[$ds] ?? null;
    $hasSR = $sr !== null;          // H Sales Report has data for this date
    $fromSR= $hasSR && !$saved;     // used only for the status dot
    $rid   = 'r' . str_replace('-', '', $ds);
    // Sales-Report-mapped fields ALWAYS mirror the Sales Report (source of
    // truth) when it has data for the date. NEVER_LOCK fields stay editable,
    // using the Sales Report figure only as a starting default when nothing's
    // been manually saved yet.
    $isSR  = fn($k) => $hasSR && isset($SALES_MAP[$k]) && !in_array($k, $NEVER_LOCK, true);
    $dv    = function($k) use ($row, $sr, $SALES_MAP, $NEVER_LOCK, $hasSR) {
        if ($hasSR && isset($SALES_MAP[$k]) && !in_array($k, $NEVER_LOCK, true)) {
            $v = (float)($sr[$SALES_MAP[$k]] ?? 0);
            return $v != 0 ? number_format($v, 2, '.', ',') : '';
        }
        if ($row && isset($row[$k]) && (float)$row[$k] != 0) {
            return number_format((float)$row[$k], 2, '.', ',');
        }
        if ($hasSR && isset($SALES_MAP[$k]) && in_array($k, $NEVER_LOCK, true)) {
            $v = (float)($sr[$SALES_MAP[$k]] ?? 0);
            return $v != 0 ? number_format($v, 2, '.', ',') : '';
        }
        return '';
    };
    // Attributes/class for a Sales-Report-mappable cell: readonly blue mirror
    // when locked, otherwise the normal editable input.
    $cls   = fn($k) => $isSR($k) ? 'sri calc' : 'sri';
    $attrs = fn($k) => $isSR($k) ? 'readonly tabindex="-1"' : 'oninput="changed(this)"';
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>" data-fromsr="<?=$hasSR?1:0?>">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':($fromSR?'auto':'new')?>" id="dot_<?=$rid?>" title="<?=$fromSR?'Auto-filled from H Sales Report — saves automatically':''?>"></span>
    </td>

    <!-- AUTO-CALC SALES -->
    <td class="g-calc"><input type="text" inputmode="decimal" autocomplete="off" class="sri calc" data-col="gross_sales_excl_mktg" data-row="<?=$rid?>" value="<?=$dv('gross_sales_excl_mktg')?>" readonly tabindex="-1"></td>
    <td class="g-calc"><input type="text" inputmode="decimal" autocomplete="off" class="sri calc" data-col="store_gross"           data-row="<?=$rid?>" value="<?=$dv('store_gross')?>"           readonly tabindex="-1"></td>
    <!-- SALES / INCOME -->
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('z_reading_gross')?>" data-col="z_reading_gross"       data-row="<?=$rid?>" value="<?=$dv('z_reading_gross')?>"       placeholder="0.00" <?=$attrs('z_reading_gross')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('cash_for_depo')?>" data-col="cash_for_depo"         data-row="<?=$rid?>" value="<?=$dv('cash_for_depo')?>"         placeholder="0.00" <?=$attrs('cash_for_depo')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('sales_of_day_swipe')?>" data-col="sales_of_day_swipe"    data-row="<?=$rid?>" value="<?=$dv('sales_of_day_swipe')?>"    placeholder="0.00" <?=$attrs('sales_of_day_swipe')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('palawan_gcash')?>" data-col="palawan_gcash"         data-row="<?=$rid?>" value="<?=$dv('palawan_gcash')?>"         placeholder="0.00" <?=$attrs('palawan_gcash')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('deposit_swipe')?>" data-col="deposit_swipe"         data-row="<?=$rid?>" value="<?=$dv('deposit_swipe')?>"         placeholder="0.00" <?=$attrs('deposit_swipe')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('late_payment')?>" data-col="late_payment"          data-row="<?=$rid?>" value="<?=$dv('late_payment')?>"          placeholder="0.00" <?=$attrs('late_payment')?>></td>
    <!-- COLLECTIONS -->
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('cancelled_transaction')?>" data-col="cancelled_transaction" data-row="<?=$rid?>" value="<?=$dv('cancelled_transaction')?>" placeholder="0.00" <?=$attrs('cancelled_transaction')?>></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('unpaid')?>" data-col="unpaid"                data-row="<?=$rid?>" value="<?=$dv('unpaid')?>"                placeholder="0.00" <?=$attrs('unpaid')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="paid"                  data-row="<?=$rid?>" value="<?=$dv('paid')?>"                  placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="advance_payment"       data-row="<?=$rid?>" value="<?=$dv('advance_payment')?>"       placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('grab')?>" data-col="grab"                  data-row="<?=$rid?>" value="<?=$dv('grab')?>"                  placeholder="0.00" <?=$attrs('grab')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('bank_trans')?>" data-col="bank_trans"            data-row="<?=$rid?>" value="<?=$dv('bank_trans')?>"            placeholder="0.00" <?=$attrs('bank_trans')?>></td>
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="gc_sponsor_marketing"  data-row="<?=$rid?>" value="<?=$dv('gc_sponsor_marketing')?>"  placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('gc_sold')?>" data-col="gc_sold"               data-row="<?=$rid?>" value="<?=$dv('gc_sold')?>"               placeholder="0.00" <?=$attrs('gc_sold')?>></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('discount')?>" data-col="discount"              data-row="<?=$rid?>" value="<?=$dv('discount')?>"              placeholder="0.00" <?=$attrs('discount')?>></td>
    <!-- DEDUCTIONS -->
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('marketing_pull_out')?>" data-col="marketing_pull_out"    data-row="<?=$rid?>" value="<?=$dv('marketing_pull_out')?>"    placeholder="0.00" <?=$attrs('marketing_pull_out')?>></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="personal"              data-row="<?=$rid?>" value="<?=$dv('personal')?>"              placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('expenses')?>" data-col="expenses"              data-row="<?=$rid?>" value="<?=$dv('expenses')?>"              placeholder="0.00" <?=$attrs('expenses')?>></td>
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('other_expenses')?>" data-col="other_expenses"        data-row="<?=$rid?>" value="<?=$dv('other_expenses')?>"        placeholder="0.00" <?=$attrs('other_expenses')?>></td>
    <!-- SC FOR DEPO -->
    <td class="g-deduct"><input type="text" inputmode="decimal" autocomplete="off" class="<?=$cls('sc_for_depo')?>" data-col="sc_for_depo"           data-row="<?=$rid?>" value="<?=$dv('sc_for_depo')?>"           placeholder="0.00" <?=$attrs('sc_for_depo')?>></td>
    <!-- AUTO-CALCULATED -->
    <td class="g-calc"><input type="text" inputmode="decimal" autocomplete="off" class="sri calc" data-col="total_deductions"   data-row="<?=$rid?>" value="<?=$dv('total_deductions')?>"      readonly tabindex="-1"></td>
    <td class="g-calc"><input type="text" inputmode="decimal" autocomplete="off" class="sri calc" data-col="short_over"         data-row="<?=$rid?>" value="<?=$dv('short_over')?>"            readonly tabindex="-1"></td>
    <td class="g-calc"><input type="text" inputmode="decimal" autocomplete="off" class="sri calc" data-col="total_swipe"        data-row="<?=$rid?>" value="<?=$dv('total_swipe')?>"           readonly tabindex="-1"></td>
    <!-- OTHER INPUT -->
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="cash_deposit"          data-row="<?=$rid?>" value="<?=$dv('cash_deposit')?>"          placeholder="0.00" oninput="changed(this)"></td>
    <!-- REMARKS 1 -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks"  data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks'] ??'')?>"  placeholder="…" oninput="changed(this)"></td>
    <!-- OTHER SALES -->
    <td class="g-income"><input type="text" inputmode="decimal" autocomplete="off" class="sri" data-col="other_sales"           data-row="<?=$rid?>" value="<?=$dv('other_sales')?>"           placeholder="0.00" oninput="changed(this)"></td>
    <!-- REMARKS 2 -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks2" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks2']??'')?>" placeholder="…" oninput="changed(this)"></td>

    <td class="td-act">
      <span class="asv" id="btn_<?=$rid?>"><?=$saved?'Saved':'—'?></span>
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
</div>

<!-- ── TARGET SALES SUMMARY PANEL ── -->
<div class="card" style="margin-top:20px;border-top:2px solid var(--accent3);overflow:hidden">
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">H — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="hQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcHSummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';saveHQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="hSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Store Gross ÷ Target</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="hNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Store Gross</div>
    </div>

    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="hDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="hDaysInput" type="number" min="1" step="1" value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcHSummary()"
          onfocus="this.style.borderColor='#0f7b5c'"
          onblur="this.style.borderColor='#d1d5db'">
        <span style="font-family:var(--font-m);font-size:.58rem;color:var(--subtext)">update daily</span>
      </div>
    </div>

  </div>
</div>

  </div></div>

<script>
const COLS = <?=json_encode($COLS)?>;

// ── Helpers ───────────────────────────────────────────────
// Number cells show thousands commas (1,234,567.89). num() strips them back
// out for math/saving; fmt2() puts them in for display.
function num(v) { return parseFloat(String(v == null ? '' : v).replace(/,/g, '')) || 0; }
function fmt2(n) { return Number(n).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
// Live comma formatting while typing (keeps the cursor in the right place).
function liveFormat(el) {
  const c = el.dataset.col;
  if (c === 'remarks' || c === 'remarks2' || el.readOnly) return;
  const old = el.value, pos = el.selectionStart == null ? old.length : el.selectionStart;
  let sig = 0;
  for (let i = 0; i < pos; i++) if (/[\d.\-]/.test(old[i])) sig++;
  let t = old.replace(/[^\d.\-]/g, '');
  const neg = t.charAt(0) === '-';
  t = t.replace(/-/g, '');
  const dot = t.indexOf('.');
  let ip = dot < 0 ? t : t.slice(0, dot);
  const fp = dot < 0 ? '' : t.slice(dot + 1).replace(/\./g, '').slice(0, 2);
  ip = ip.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const out = (neg ? '-' : '') + ip + (dot < 0 ? '' : '.' + fp);
  if (out !== old) {
    el.value = out;
    let k = 0, q = 0;
    while (q < out.length && k < sig) { if (/[\d.\-]/.test(out[q])) k++; q++; }
    try { el.setSelectionRange(q, q); } catch (e) {}
  }
}
// On leaving a cell, tidy it to 2 decimals (1,234.50).
document.addEventListener('focusout', function(e) {
  const el = e.target;
  if (!el.matches || !el.matches('#srt input.sri[data-col]') || el.readOnly) return;
  const c = el.dataset.col;
  if (c === 'remarks' || c === 'remarks2' || el.value.trim() === '') return;
  el.value = fmt2(num(el.value));
});

function gv(rid, col) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  return e ? num(e.value) : 0;
}

// ── Auto-calculations ─────────────────────────────────────
function recalc(rid) {
  const tr = document.getElementById(rid);
  // Rows where the H Sales Report has data for this date keep that report's
  // own Total Swipe / Short-Over figures exactly (readonly mirrors) instead
  // of recomputing them here — same "preserve" approach Dois/Stella use.
  const preserveSR = !!(tr && tr.dataset.fromsr === '1');

  // Fields that are auto-calculated (excluded from hasInput detection)
  const autoCalcCols = ['gross_sales_excl_mktg','store_gross','total_deductions','short_over','total_swipe'];
  const hasInput = COLS.some(c => !autoCalcCols.includes(c) ? (gv(rid, c) !== 0) : false);

  const zReading    = gv(rid, 'z_reading_gross');
  const mktgPullOut = gv(rid, 'marketing_pull_out');
  const disc        = gv(rid, 'discount');
  const scForDepo   = gv(rid, 'sc_for_depo');
  const cashForDepo = gv(rid, 'cash_for_depo');

  // GROSS SALES EXCL MKTG = Z Reading Gross − Marketing Pull Out   [B = D − R]
  const grossSales = zReading - mktgPullOut;
  const gsEl = document.querySelector(`#${rid} [data-col="gross_sales_excl_mktg"]`);
  if (gsEl) gsEl.value = hasInput ? fmt2(grossSales) : '';

  // STORE GROSS = Z Reading Gross + Discount − SC for Depo − Marketing Pull Out   [C = D + Q − V − R]
  const storeGross = zReading + disc - scForDepo - mktgPullOut;
  const sgEl = document.querySelector(`#${rid} [data-col="store_gross"]`);
  if (sgEl) sgEl.value = hasInput ? fmt2(storeGross) : '';

  // TOTAL DEDUCTIONS = Sales of Day Swipe + Unpaid + Paid + Advance Payment +
  //   Grab + Bank Trans + GC Sponsor/Mktg + GC Sold + Discount +
  //   Expenses + Other Expenses + Personal   [W = F+J+K+L+M+N+O+P+Q+T+U+S]
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
  if (tdEl) tdEl.value = hasInput ? fmt2(td) : '';

  // SHORT / OVER = Total Deductions + Cash for Depo − Store Gross   [X = W + E − C]
  const so = td + cashForDepo - storeGross;
  if (!preserveSR) {
    const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
    if (soEl) {
      soEl.value = hasInput ? fmt2(so) : '';
      soEl.classList.toggle('neg', hasInput && so < 0);
    }
  }

  // TOTAL SWIPE = Sales of Day Swipe + Deposit Swipe + Late Payment + Paid   [Y = F + G + H + K]
  if (!preserveSR) {
    const ts = gv(rid, 'sales_of_day_swipe')
             + gv(rid, 'deposit_swipe')
             + gv(rid, 'late_payment')
             + gv(rid, 'paid');
    const tsEl = document.querySelector(`#${rid} [data-col="total_swipe"]`);
    if (tsEl) tsEl.value = hasInput ? fmt2(ts) : '';
  }
}

// ── Column totals ─────────────────────────────────────────
function recalcTotals() {
  COLS.forEach(function(col) {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`[data-col="${col}"]`).forEach(function(i) {
      s += num(i.value);
    });
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    el.style.color = (col === 'short_over' && s < 0) ? 'var(--accent2)' : '';
  });
  recalcHSummary();
}

// ── Auto-save: debounced per row ──────────────────────────
const saveTimers = {};
const AUTOCOLS = ['gross_sales_excl_mktg','store_gross','total_deductions','short_over','total_swipe'];
// true when the row has anything typed/linked (blank rows are never saved)
function rowHasInput(rid) {
  const tr = document.getElementById(rid);
  if (!tr) return false;
  return [...tr.querySelectorAll('input[data-col]')].some(function(el){
    const c = el.dataset.col;
    if (AUTOCOLS.indexOf(c) >= 0) return false;
    if (c === 'remarks' || c === 'remarks2') return el.value.trim() !== '';
    return num(el.value) !== 0;
  });
}
function setStat(rid, cls, txt) {
  const b = document.getElementById('btn_' + rid);
  if (b) { b.className = 'asv ' + cls; b.textContent = txt; }
}
function changed(el) {
  liveFormat(el);
  const rid = el.dataset.row;
  recalc(rid);
  recalcTotals();
  const tr = document.getElementById(rid);
  const wasSaved = tr.dataset.saved === '1' || tr.dataset.wasSaved === '1';
  if (wasSaved) tr.dataset.wasSaved = '1';
  if (!rowHasInput(rid) && !wasSaved) {          // nothing entered → nothing to save
    clearTimeout(saveTimers[rid]);
    setStat(rid, '', '—');
    return;
  }
  tr.dataset.saved = '0';
  document.getElementById('dot_' + rid).className = 'sdot new';
  setStat(rid, 'saving', 'Typing…');
  clearTimeout(saveTimers[rid]);
  saveTimers[rid] = setTimeout(function(){ saveRow(rid, tr.dataset.date); }, 600);
}

// ── Save single row ───────────────────────────────────────
async function saveRow(rid, ds) {
  clearTimeout(saveTimers[rid]);
  const trr = document.getElementById(rid);
  if (!rowHasInput(rid) && trr.dataset.saved !== '1' && trr.dataset.wasSaved !== '1') { setStat(rid, '', '—'); return; }
  setStat(rid, 'saving', 'Saving…');

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('report_date', ds);

  COLS.forEach(function(col) {
    const el = document.querySelector(`#${rid} [data-col="${col}"]`);
    fd.append(col, el ? (String(el.value || '0').replace(/,/g, '')) : '0');
  });
  ['remarks','remarks2'].forEach(function(col) {
    const el = document.querySelector(`#${rid} [data-col="${col}"]`);
    fd.append(col, el ? el.value : '');
  });

  try {
    const res  = await fetch('h_summary_report.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      setStat(rid, 'ok', 'Saved ✓');
      document.getElementById(rid).dataset.saved = '1';
      document.getElementById(rid).dataset.wasSaved = '1';
      document.getElementById('dot_' + rid).className = 'sdot ok';
    } else {
      setStat(rid, 'err', 'Error');
      showToast('❌ ' + data.msg, 'error');
    }
  } catch(e) {
    setStat(rid, 'err', 'Error');
    showToast('❌ Network error', 'error');
  }
}

// ── Live sync with the H Sales Report ─────────────────────
// Every few seconds, pull the Sales Report figures and refresh the linked
// (blue) cells, so edits there show up here with no button pressed.
const SYNC_NEVER = ['cancelled_transaction'];
let syncBusy = false;
async function syncFromSales() {
  if (syncBusy || document.hidden) return;
  syncBusy = true;
  try {
    const res = await fetch('h_summary_report.php' + location.search + (location.search ? '&' : '?') + 'ajax_sync=1&_=' + Date.now(), {cache:'no-store'});
    const data = await res.json();
    if (!data.ok) return;
    const fmt = v => v != 0 ? fmt2(v) : '';
    Object.keys(data.rows).forEach(function(ds) {
      const rid = 'r' + ds.replace(/-/g, '');
      const tr = document.getElementById(rid);
      if (!tr) return;
      let changedAny = false;
      const vals = data.rows[ds];
      Object.keys(vals).forEach(function(col) {
        const el = tr.querySelector(`[data-col="${col}"]`);
        if (!el || el === document.activeElement) return;
        const nv = fmt(vals[col]);
        if (SYNC_NEVER.indexOf(col) >= 0) {
          // editable cell: only follow the Sales Report if the user hasn't overridden it
          const prev = el.dataset.srv;
          if (prev === undefined) { el.dataset.srv = nv; return; }
          if (el.value === prev || el.value === '') {
            if (el.value !== nv) { el.value = nv; changedAny = true; }
          }
          el.dataset.srv = nv;
          return;
        }
        if (el.readOnly !== true) { el.readOnly = true; el.tabIndex = -1; el.classList.add('calc'); el.removeAttribute('oninput'); }
        if (el.value !== nv) { el.value = nv; changedAny = true; }
      });
      if (tr.dataset.fromsr !== '1') { tr.dataset.fromsr = '1'; changedAny = true; }
      if (changedAny) {
        recalc(rid);
        clearTimeout(saveTimers[rid]);
        saveTimers[rid] = setTimeout(function(){ saveRow(rid, ds); }, 300);
      }
    });
    recalcTotals();
  } catch(e) { /* offline — try again next tick */ }
  finally { syncBusy = false; }
}

// ── Save all rows ─────────────────────────────────────────
async function saveAll() {
  const rows = [...document.querySelectorAll('#srt tbody tr')];
  for (const row of rows) {
    await saveRow(row.id, row.dataset.date);
    await new Promise(r => setTimeout(r, 50));
  }
  showToast('✓ All ' + rows.length + ' rows saved for H', 'success');
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
  window.location.href = 'h_summary_report.php?' + params.toString();
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
function recalcHSummary() {
  const quota = parseFloat(document.getElementById('hQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('hDaysInput')?.value)   || 1;

  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="store_gross"]').forEach(function(el) {
    totalGross += num(el.value);
  });

  const pctEl    = document.getElementById('hSalesPct');
  const neededEl = document.getElementById('hNeeded');
  const dailyEl  = document.getElementById('hDailyQuota');

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

// ── Save H quota via AJAX ──────────────────────────────────
async function saveHQuota() {
  const val = document.getElementById('hQuotaInput')?.value;
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
    await fetch('h_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}

// ── Init ──────────────────────────────────────────────────
// Size the table so it fits the screen: header row + TOTAL row stay pinned
// and only the daily rows scroll in between.
function fitTable() {
  const o = document.querySelector('.sr-outer'), t = document.getElementById('srt');
  if (!o || !t) return;
  const g = t.querySelector('thead tr');
  if (g) t.style.setProperty('--grp-h', g.offsetHeight + 'px');
  const h = window.innerHeight - o.getBoundingClientRect().top - 14;
  o.style.maxHeight = Math.max(320, h) + 'px';
}
window.addEventListener('resize', fitTable);

document.addEventListener('DOMContentLoaded', function() {
  fitTable();
  document.querySelectorAll('#srt tbody tr').forEach(function(r){ recalc(r.id); });
  recalcTotals();
  recalcHSummary();
  setInterval(syncFromSales, 4000);
  document.addEventListener('visibilitychange', function(){ if (!document.hidden) syncFromSales(); });
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>