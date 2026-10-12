<?php
// ============================================================
//  dois_summary_report.php — Dois Branch Daily Summary Entry
//  Completely standalone — does NOT touch Stella's tables or code
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Dois branch and management can access this page
if (isBranch() && currentBranch() !== 'Dois') {
    header('Location: ../summary_report.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create Dois table (safe to run repeatedly) ───────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dois_report_entries` (
        `id`                   int(11) NOT NULL AUTO_INCREMENT,
        `report_date`          date NOT NULL,
        `store_name`           varchar(100) NOT NULL DEFAULT 'Dois',
        `pos_reading`          decimal(12,2) DEFAULT 0.00,
        `cash`                 decimal(12,2) DEFAULT 0.00,
        `short_over`           decimal(12,2) DEFAULT 0.00,
        `tips`                 decimal(12,2) DEFAULT 0.00,
        `vat_exemption`        decimal(12,2) DEFAULT 0.00,
        `net_gross_sales`      decimal(12,2) DEFAULT 0.00,
        `gross_sales`          decimal(12,2) DEFAULT 0.00,
        `sales_of_day_swipe`   decimal(12,2) DEFAULT 0.00,
        `cancelled_transaction`decimal(12,2) DEFAULT 0.00,
        `unpaid_staff`         decimal(12,2) DEFAULT 0.00,
        `unpaid_mam`           decimal(12,2) DEFAULT 0.00,
        `marketing_pull_out`   decimal(12,2) DEFAULT 0.00,
        `late_payment`         decimal(12,2) DEFAULT 0.00,
        `advance_payment`      decimal(12,2) DEFAULT 0.00,
        `grab`                 decimal(12,2) DEFAULT 0.00,
        `gcash`                decimal(12,2) DEFAULT 0.00,
        `gc_sold`              decimal(12,2) DEFAULT 0.00,
        `gc_sponsorship`       decimal(12,2) DEFAULT 0.00,
        `bank_transfer`        decimal(12,2) DEFAULT 0.00,
        `discounted`           decimal(12,2) DEFAULT 0.00,
        `personal`             decimal(12,2) DEFAULT 0.00,
        `cash_advance`         decimal(12,2) DEFAULT 0.00,
        `payroll`              decimal(12,2) DEFAULT 0.00,
        `commi_fund`           decimal(12,2) DEFAULT 0.00,
        `service_charge_pos`   decimal(12,2) DEFAULT 0.00,
        `cancelled_sc`         decimal(12,2) DEFAULT 0.00,
        `service_charge_depo`  decimal(12,2) DEFAULT 0.00,
        `pcf`                  decimal(12,2) DEFAULT 0.00,
        `other_expenses`       decimal(12,2) DEFAULT 0.00,
        `total_deductions`     decimal(12,2) DEFAULT 0.00,
        `total_swipe`          decimal(12,2) DEFAULT 0.00,
        `other_deposits`       decimal(12,2) DEFAULT 0.00,
        `lechonan_sales`       decimal(12,2) DEFAULT 0.00,
        `remarks`              text DEFAULT NULL,
        `saved_by`             varchar(100) DEFAULT NULL,
        `created_at`           timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`           timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

// ── Add new columns to existing installations (safe to run repeatedly) ──
foreach (['vat_exemption', 'net_gross_sales'] as $newCol) {
    try { $pdo->exec("ALTER TABLE dois_report_entries ADD COLUMN IF NOT EXISTS `$newCol` decimal(12,2) DEFAULT 0.00"); } catch (Throwable $ignored) {}
}

// ── Handle AJAX save (per-row) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $reportDate = $_POST['report_date'] ?? '';
    if (!$reportDate) {
        echo json_encode(['ok' => false, 'msg' => 'Missing date.']);
        exit;
    }

    $numCols = [
        'pos_reading','cash','short_over','tips','vat_exemption',
        'net_gross_sales','gross_sales',
        'sales_of_day_swipe','cancelled_transaction','unpaid_staff','unpaid_mam',
        'marketing_pull_out','late_payment','advance_payment','grab','gcash',
        'gc_sold','gc_sponsorship','bank_transfer','discounted','personal',
        'cash_advance','payroll','commi_fund','service_charge_pos',
        'cancelled_sc','service_charge_depo','pcf','other_expenses',
        'total_deductions','total_swipe','other_deposits'
    ];

    $data = ['store_name' => 'Dois', 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['remarks']  = trim($_POST['remarks'] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO dois_report_entries ("
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
            ->execute([$month.'-01', 'Dois', $quota, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}


if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $csvMonthNames = ['','January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    $csvCols = [
        'report_date'           => 'Date',
        'pos_reading'           => 'POS Reading',
        'cash'                  => 'Cash',
        'short_over'            => 'Short / Over',
        'tips'                  => 'Tips',
        'vat_exemption'         => 'VAT Exemption',
        'net_gross_sales'       => 'Net Gross Sales',
        'gross_sales'           => 'Gross Sales',
        'sales_of_day_swipe'    => 'Sales of the Day (Swipe)',
        'cancelled_transaction' => 'Cancelled Transaction',
        'unpaid_staff'          => 'Unpaid — Staff',
        'unpaid_mam'            => 'Unpaid — Mam Nikki / Sir Budoy / Corp',
        'marketing_pull_out'    => 'Marketing Pull-Out',
        'late_payment'          => 'Late Payment',
        'advance_payment'       => 'Advance Payment',
        'grab'                  => 'Grab',
        'gcash'                 => 'GCash',
        'gc_sold'               => 'GC Sold',
        'gc_sponsorship'        => 'GC Sponsorship',
        'bank_transfer'         => 'Bank Transfer',
        'discounted'            => 'Discounted',
        'personal'              => 'Personal',
        'cash_advance'          => 'Cash Advance',
        'payroll'               => 'Payroll',
        'commi_fund'            => 'Commi Fund',
        'service_charge_pos'    => 'Service Charge (POS Reading)',
        'cancelled_sc'          => 'Cancelled SC',
        'service_charge_depo'   => 'Service Charge (For Depo)',
        'pcf'                   => 'PCF',
        'other_expenses'        => 'Other Expenses',
        'total_deductions'      => 'Total Deductions',
        'total_swipe'           => 'Total Swipe',
        'other_deposits'        => 'Other Deposits',
        'remarks'               => 'Remarks',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM dois_report_entries WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = 'Dois_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Dois — Summary Report']);
    fputcsv($out, [$csvMonthNames[$csvMonth] . ' ' . $csvYear]);
    fputcsv($out, []);
    fputcsv($out, array_values($csvCols));

    $totals = array_fill_keys(array_keys($csvCols), 0);
    foreach ($csvDays as $ds) {
        $row  = $csvRows[$ds] ?? null;
        $line = [];
        foreach (array_keys($csvCols) as $key) {
            if ($key === 'report_date') {
                $line[] = date('d-M-Y (D)', strtotime($ds));
            } elseif ($key === 'remarks') {
                $line[] = $row['remarks'] ?? '';
            } else {
                $val    = (float)($row[$key] ?? 0);
                $line[] = $val != 0 ? number_format($val, 2, '.', '') : '';
                $totals[$key] += $val;
            }
        }
        fputcsv($out, $line);
    }

    // Totals row
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

// ── Days of the selected month ────────────────────────────
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
$allDays = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $allDays[] = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $d);
}

// ── Load saved rows ───────────────────────────────────────
$savedRows = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM dois_report_entries WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull fallback data from the Sales Report for dates      ──
// that don't have a manually-saved summary row yet
$salesRows = [];
try {
    $ssStmt = $pdo->prepare("SELECT * FROM dois_sales_report WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $ssStmt->execute([$fYear, $fMonth]);
    foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;

    $diStmt = $pdo->prepare("SELECT report_date,
            COALESCE(SUM(cash),0) AS cash_total,
            COALESCE(SUM(cancelled_transactions),0) AS cancelled_total
        FROM dois_dinein_rows WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?
        GROUP BY report_date");
    $diStmt->execute([$fYear, $fMonth]);
    foreach ($diStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $salesRows[$r['report_date']]['_cash_total']      = (float)$r['cash_total'];
        $salesRows[$r['report_date']]['_cancelled_total']  = (float)$r['cancelled_total'];
    }

    // Total Deductions (summary) mirrors Sales Report's Gross Sales − Net Sales,
    // so the Short/Over shown here matches the Sales Report exactly.
    foreach ($salesRows as $d => $sr) {
        $g = (float)($sr['gross_sales'] ?? 0);
        $n = (float)($sr['net_sales'] ?? 0);
        $salesRows[$d]['_total_deductions'] = $g - $n;
    }
} catch (Throwable $ignored) {}

// Map: summary column => sales-report source (either a column name or a computed key above)
$SALES_MAP = [
    'pos_reading'           => 'z_reading_gross',
    'cash'                  => '_cash_total',
    'sales_of_day_swipe'    => 'maya_swipe',
    'cancelled_transaction' => '_cancelled_total',
    'unpaid_staff'          => 'unpaid_med_credit',
    'unpaid_mam'            => 'paid_med',
    'marketing_pull_out'    => 'marketing_pull_out',
    'late_payment'          => 'late_payment_card',
    'advance_payment'       => 'advance_paid',
    'grab'                  => 'grab_sales',
    'gcash'                 => 'gcash',
    'gc_sold'               => 'gc_sold',
    'gc_sponsorship'        => 'gc_availed',
    'bank_transfer'         => 'bank_transfer_cheque',
    'discounted'            => 'discount',
    'personal'              => 'personal_withdrawal',
    'payroll'               => 'gift_card',
    'service_charge_pos'    => 'service_charge',
    'service_charge_depo'   => 'service_charge',
    'pcf'                   => 'pcf_expenses',
    'other_expenses'        => 'other_expenses',
    'total_swipe'           => 'total_swipe',
    'gross_sales'           => 'gross_sales',
    'total_deductions'      => '_total_deductions',
    'short_over'            => 'short_over',
];

// ── Load saved quota for this month ──────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='Dois' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([$fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column key list (for tfoot & JS) ─────────────────────
// NOTE: order here MUST match the physical left-to-right column order in
// the <tbody> rows below, or the tfoot TOTAL row will be misaligned under
// the wrong headers (this previously caused Short/Over's total to render
// under Total Swipe's column, etc).
$COLS = [
    'pos_reading','cash','pos_cash_diff','tips','vat_exemption',
    'net_gross_sales','gross_sales',
    'sales_of_day_swipe','cancelled_transaction','unpaid_staff','unpaid_mam',
    'marketing_pull_out','late_payment','advance_payment','grab','gcash',
    'gc_sold','gc_sponsorship','bank_transfer','discounted','personal',
    'cash_advance','payroll','commi_fund','service_charge_pos',
    'cancelled_sc','service_charge_depo','pcf','other_expenses',
    'total_deductions','short_over','total_swipe','other_deposits'
];

$pageTitle  = 'Dois Summary Report';
$activePage = 'dois_summary';
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
.g-lech   { background: #fefce8 !important; }
.g-text   { background: #faf5ff !important; }

/* ── Column widths ── */
.col-num  { width: 90px; min-width: 90px; }
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
/* Remove number input spinner arrows completely */
.sri::-webkit-outer-spin-button,
.sri::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.sri[type="number"] { -moz-appearance: textfield; appearance: textfield; }

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
.sdot.ok   { background: #22c55e; box-shadow: 0 0 4px #22c55e; }
.sdot.new  { background: #f59e0b; }
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
    <div class="section-title" style="color:#1a1d23">Dois <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily spreadsheet entry · each row has its own Save button · Save All saves every row</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 Dois
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
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income</span>
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
      <!-- POS / CASH -->
      <th colspan="2" class="g-income">POS / CASH</th>
      <th colspan="1" class="g-calc">AUTO</th>
      <!-- INCOME -->
      <th colspan="4" class="g-income">INCOME</th>
      <!-- INCOME / COLLECTIONS -->
      <th colspan="9" class="g-income">INCOME / COLLECTIONS</th>
      <!-- DEDUCTIONS -->
      <th colspan="13" class="g-deduct">DEDUCTIONS</th>
      <!-- AUTO-CALCULATED -->
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <!-- TOTAL SWIPE -->
      <th colspan="1" class="g-income">TOTAL SWIPE</th>
      <!-- OTHER -->
      <th colspan="1" class="g-income">OTHER</th>
      <!-- NOTES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- POS / CASH -->
      <th class="g-income col-num">POS READING</th>
      <th class="g-income col-num">CASH</th>
      <!-- AUTO -->
      <th class="g-calc col-num">SHORT / OVER</th>
      <!-- INCOME -->
      <th class="g-income col-num">TIPS</th>
      <th class="g-income col-num">VAT EXEMPTION</th>
      <th class="g-calc col-num">NET GROSS SALES</th>
      <th class="g-calc col-num">GROSS SALES</th>
      <!-- COLLECTIONS -->
      <th class="g-income col-num">SALES OF THE DAY (SWIPE)</th>
      <th class="g-deduct col-num">CANCELLED TRANSACTION</th>
      <th class="g-deduct col-num">UNPAID — STAFF</th>
      <th class="g-deduct col-num">UNPAID — MAM NIKKI / SIR BUDOY / CORP</th>
      <th class="g-deduct col-num">MARKETING PULL-OUT</th>
      <th class="g-income col-num">LATE PAYMENT</th>
      <th class="g-income col-num">ADVANCE PAYMENT</th>
      <th class="g-income col-num">GRAB</th>
      <th class="g-income col-num">GCASH</th>
      <!-- DEDUCTIONS -->
      <th class="g-deduct col-num">GC SOLD</th>
      <th class="g-deduct col-num">GC SPONSORSHIP</th>
      <th class="g-income col-num">BANK TRANSFER</th>
      <th class="g-deduct col-num">DISCOUNTED</th>
      <th class="g-deduct col-num">PERSONAL</th>
      <th class="g-deduct col-num">CASH ADVANCE</th>
      <th class="g-deduct col-num">PAYROLL</th>
      <th class="g-deduct col-num">COMMI FUND</th>
      <th class="g-deduct col-num">SERVICE CHARGE (POS READING)</th>
      <th class="g-deduct col-num">CANCELLED SC</th>
      <th class="g-calc col-num">SERVICE CHARGE (FOR DEPO)</th>
      <th class="g-calc col-num">PCF</th>
      <th class="g-deduct col-num">OTHER EXPENSES</th>
      <!-- AUTO-CALCULATED -->
      <th class="g-calc col-num">TOTAL DEDUCTIONS</th>
      <th class="g-calc col-num">SHORT / OVER</th>
      <!-- TOTAL SWIPE — user input -->
      <th class="g-income col-num">TOTAL SWIPE</th>
      <!-- OTHER -->
      <th class="g-income col-num">OTHER DEPOSITS</th>
      <!-- NOTES -->
      <th class="g-text col-txt">REMARKS</th>
      <th class="th-act">SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN  = (int)date('j', strtotime($ds));
    $dayNm = date('D', strtotime($ds));
    $row     = $savedRows[$ds] ?? null;
    $saved   = $row !== null;
    $sr      = $salesRows[$ds] ?? null;
    $hasSR   = $sr !== null;          // Sales Report has data for this date
    $fromSR  = $hasSR && !$saved;     // used only for the status dot
    $rid     = 'r' . str_replace('-', '', $ds);
    // Fields listed here stay editable even when the Sales Report has data for
    // the date — they get the Sales Report value as a starting default, but a
    // manually-saved value takes priority and the cell is never greyed out/locked.
    $NEVER_LOCK = ['cancelled_transaction', 'cash'];
    // Sales-Report-mapped fields ALWAYS mirror the Sales Report (source of truth),
    // even if this row was previously saved with stale/old values. Fields not in
    // SALES_MAP (or listed in $NEVER_LOCK) fall back to whatever was manually saved.
    $dv      = function($k) use ($row, $sr, $SALES_MAP, $NEVER_LOCK) {
        if (in_array($k, $NEVER_LOCK, true)) {
            if ($row && isset($row[$k]) && (float)$row[$k] != 0) {
                return number_format((float)$row[$k], 2, '.', '');
            }
            if ($sr && isset($SALES_MAP[$k]) && isset($sr[$SALES_MAP[$k]]) && (float)$sr[$SALES_MAP[$k]] != 0) {
                return number_format((float)$sr[$SALES_MAP[$k]], 2, '.', '');
            }
            return '';
        }
        if ($sr && isset($SALES_MAP[$k]) && isset($sr[$SALES_MAP[$k]]) && (float)$sr[$SALES_MAP[$k]] != 0) {
            return number_format((float)$sr[$SALES_MAP[$k]], 2, '.', '');
        }
        if ($row && isset($row[$k]) && (float)$row[$k] != 0) {
            return number_format((float)$row[$k], 2, '.', '');
        }
        return '';
    };
    // Builds an <input> cell. Sales-Report-mapped fields become read-only mirrors
    // whenever the Sales Report has data for that date (except $NEVER_LOCK fields,
    // which stay editable always) — edit them on the Sales Report page and they'll
    // flow through here automatically.
    $inp = function($col, $cls) use ($SALES_MAP, $hasSR, $dv, $rid, $NEVER_LOCK) {
        $isSR = $hasSR && isset($SALES_MAP[$col]) && !in_array($col, $NEVER_LOCK, true);
        $icls = $isSR ? 'sri calc' : 'sri';
        $ro   = $isSR ? ' readonly tabindex="-1"' : '';
        $oi   = $isSR ? '' : ' oninput="changed(this)"';
        $val  = $dv($col);
        return '<td class="'.$cls.'"><input type="number" step="0.01" class="'.$icls.'" data-col="'.$col.'" data-row="'.$rid.'" value="'.$val.'" placeholder="0.00"'.$oi.$ro.'></td>';
    };
    // Net Gross Sales = Gross Sales - Marketing Pull-Out + VAT Exemption (Inputable).
    // Marketing Pull-Out mirrors the Sales Report (or a manually-saved value) via
    // $dv(), same as the Marketing Pull-Out column itself. VAT Exemption stays a
    // plain manual field.
    $marketingPullOutRaw   = (float)str_replace(',', '', $dv('marketing_pull_out') ?: 0);
    $vatExemptionRaw       = (float)($row['vat_exemption'] ?? 0);
    $grossSalesRaw         = (float)str_replace(',', '', $dv('gross_sales') ?: 0);
    $netGrossSalesRaw      = $grossSalesRaw - $marketingPullOutRaw + $vatExemptionRaw;
    $dvNetGrossSales       = $netGrossSalesRaw != 0 ? number_format($netGrossSalesRaw, 2, '.', '') : '';
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>" data-fromsr="<?=$hasSR?1:0?>" data-touched="0">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':($fromSR?'auto':'new')?>" id="dot_<?=$rid?>" title="<?=$fromSR?'Auto-filled from Sales Report — click Save to lock in':''?>"></span>
    </td>

    <!-- POS / CASH -->
    <?= $inp('pos_reading','g-income') ?>
    <?= $inp('cash','g-income') ?>
    <!-- AUTO: Short/Over (POS - CASH) — display only, not saved -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="pos_cash_diff" data-row="<?=$rid?>" readonly tabindex="-1" placeholder=""></td>
    <!-- INCOME -->
    <?= $inp('tips','g-income') ?>
    <!-- VAT Exemption (Inputable) — manual field, independent from Marketing Pull-Out -->
    <?= $inp('vat_exemption','g-income') ?>
    <!-- AUTO: Net Gross Sales = Gross Sales - Marketing (Inputable) + VAT Exemption (Inputable) -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="net_gross_sales" data-row="<?=$rid?>" readonly tabindex="-1" value="<?=$dvNetGrossSales?>" placeholder=""></td>
    <!-- AUTO: Gross Sales -->
    <?= $inp('gross_sales','g-calc') ?>
    <!-- COLLECTIONS -->
    <?= $inp('sales_of_day_swipe','g-income') ?>
    <?= $inp('cancelled_transaction','g-deduct') ?>
    <?= $inp('unpaid_staff','g-deduct') ?>
    <?= $inp('unpaid_mam','g-deduct') ?>
    <?= $inp('marketing_pull_out','g-deduct') ?>
    <?= $inp('late_payment','g-income') ?>
    <?= $inp('advance_payment','g-income') ?>
    <?= $inp('grab','g-income') ?>
    <?= $inp('gcash','g-income') ?>
    <!-- DEDUCTIONS -->
    <?= $inp('gc_sold','g-deduct') ?>
    <?= $inp('gc_sponsorship','g-deduct') ?>
    <?= $inp('bank_transfer','g-income') ?>
    <?= $inp('discounted','g-deduct') ?>
    <?= $inp('personal','g-deduct') ?>
    <?= $inp('cash_advance','g-deduct') ?>
    <?= $inp('payroll','g-deduct') ?>
    <?= $inp('commi_fund','g-deduct') ?>
    <?= $inp('service_charge_pos','g-deduct') ?>
    <?= $inp('cancelled_sc','g-deduct') ?>
    <?= $inp('service_charge_depo','g-calc') ?>
    <?= $inp('pcf','g-deduct') ?>
    <?= $inp('other_expenses','g-deduct') ?>
    <!-- AUTO-CALCULATED -->
    <?= $inp('total_deductions','g-calc') ?>
    <?= $inp('short_over','g-calc') ?>
    <!-- TOTAL SWIPE -->
    <?= $inp('total_swipe','g-income') ?>
    <!-- OTHER -->
    <?= $inp('other_deposits','g-income') ?>
    <!-- NOTES -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks']??'')?>" placeholder="…" oninput="changed(this)"></td>

    <td class="td-act">
      <button class="bsr" id="btn_<?=$rid?>" onclick="saveRow('<?=$rid?>','<?=$ds?>')">
        <?=$saved?'Update':'Save'?>
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
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">Dois — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <!-- TARGET SALES (inputable) -->
    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="doisQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcDoisSummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';saveDoisQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <!-- SALES PERCENTAGE -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="doisSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Gross Sales ÷ Target</div>
    </div>

    <!-- NEEDED SALES TO REACH TARGET -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="doisNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Gross Sales</div>
    </div>

    <!-- DAILY SALES QUOTA -->
    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="doisDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="doisDaysInput" type="number" min="1" step="1"
          value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcDoisSummary()"
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
function gv(rid, col) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  return e ? (parseFloat(e.value) || 0) : 0;
}
function sv(rid, col, v) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  if (e) e.value = v === 0 ? '' : v.toFixed(2);
}

// ── Auto-calculations ─────────────────────────────────────
// editedCol: the data-col of whichever input the user just typed into.
// When set, we skip re-writing that SAME field from its formula — so a
// manual override in an "auto-calculated" cell (Gross Sales, Total
// Deductions, Short/Over, Service Charge Depo) isn't clobbered the moment
// it's typed. Editing any OTHER field still recalculates these normally.
function recalc(rid, editedCol) {
  const pos = gv(rid, 'pos_reading');
  const tr  = document.getElementById(rid);
  // Rows where the Sales Report has data for this date keep those exact
  // figures (Gross Sales, PCF, Total Deductions, Short/Over, etc.) always —
  // those inputs are readonly mirrors. Only dates with no Sales Report entry
  // fall back to the old spreadsheet-style formulas below.
  const preserveSR = !!(tr && tr.dataset.fromsr === '1');

  // FIRST SHORT/OVER (display only, not saved) = POS Reading − CASH
  // Excel: =B4-C4  — shown near top between POS/CASH columns
  const firstSO = pos - gv(rid,'cash');
  const pcdEl = document.querySelector(`#${rid} [data-col="pos_cash_diff"]`);
  if (pcdEl) {
    pcdEl.value = (pos || gv(rid,'cash')) ? firstSO.toFixed(2) : '';
    pcdEl.classList.toggle('neg', !!(pos || gv(rid,'cash')) && firstSO < 0);
  }

  // GROSS SALES (F) = POS Reading - Cancelled Transaction - Tips
  // Excel: =B4-H4-E4
  if (!preserveSR) {
    const gs = pos - gv(rid,'cancelled_transaction') - gv(rid,'tips');
    const gsEl = document.querySelector(`#${rid} [data-col="gross_sales"]`);
    if (gsEl && editedCol !== 'gross_sales') gsEl.value = (pos || gv(rid,'cancelled_transaction') || gv(rid,'tips')) ? gs.toFixed(2) : '';
  }

  // NET GROSS SALES = Gross Sales − Marketing Pull-Out + VAT Exemption (Inputable)
  // Reads whatever Gross Sales / Marketing Pull-Out currently show (SR-mirrored
  // or just-computed/entered above), so it stays correct either way.
  {
    const grossForNGS = parseFloat(document.querySelector(`#${rid} [data-col="gross_sales"]`)?.value) || 0;
    const marketingPullOut = gv(rid,'marketing_pull_out');
    const vatEx        = gv(rid,'vat_exemption');
    const ngs = grossForNGS - marketingPullOut + vatEx;
    const ngsEl = document.querySelector(`#${rid} [data-col="net_gross_sales"]`);
    if (ngsEl) ngsEl.value = (grossForNGS || marketingPullOut || vatEx) ? ngs.toFixed(2) : '';
  }

  // SERVICE CHARGE FOR DEPO (Z) = Service Charge POS - Cancelled SC
  // Excel: =X4-Y4
  if (!preserveSR) {
    const scDepo = gv(rid,'service_charge_pos') - gv(rid,'cancelled_sc');
    const scEl = document.querySelector(`#${rid} [data-col="service_charge_depo"]`);
    if (scEl && editedCol !== 'service_charge_depo') scEl.value = (gv(rid,'service_charge_pos') || gv(rid,'cancelled_sc')) ? scDepo.toFixed(2) : '';
  }

  // PCF/Expenses — manual input (matches Sales Report's PCF/Expenses exactly,
  // no formula here — it used to be wrongly computed as 14678 minus deductions,
  // which didn't match the real PCF figure entered on the Sales Report)
  const hasInput = gv(rid,'pos_reading') || gv(rid,'cash')
                || gv(rid,'cash_advance') || gv(rid,'payroll')
                || gv(rid,'other_expenses') || gv(rid,'sales_of_day_swipe')
                || gv(rid,'tips') || gv(rid,'gcash') || gv(rid,'grab')
                || gv(rid,'service_charge_pos') || gv(rid,'discounted')
                || gv(rid,'personal') || gv(rid,'commi_fund') || gv(rid,'pcf');
  const pcf = gv(rid,'pcf');

  // TOTAL DEDUCTIONS (AC) = G+K+M+N+O+S+AA+T+V+W+H+R+P+Q+AB+U+J+I
  // Excel: =G4+K4+M4+N4+O4+S4+AA4+T4+V4+W4+H4+R4+P4+Q4+AB4+U4+J4+I4
  if (!preserveSR) {
    const td = gv(rid,'sales_of_day_swipe')
             + gv(rid,'marketing_pull_out')
             + gv(rid,'advance_payment')
             + gv(rid,'grab')
             + gv(rid,'gcash')
             + gv(rid,'discounted')
             + pcf
             + gv(rid,'personal')
             + gv(rid,'payroll')
             + gv(rid,'commi_fund')
             + gv(rid,'cancelled_transaction')
             + gv(rid,'bank_transfer')
             + gv(rid,'gc_sold')
             + gv(rid,'gc_sponsorship')
             + gv(rid,'other_expenses')
             + gv(rid,'cash_advance')
             + gv(rid,'unpaid_mam')
             + gv(rid,'unpaid_staff');
    const tdEl = document.querySelector(`#${rid} [data-col="total_deductions"]`);
    if (tdEl && editedCol !== 'total_deductions') tdEl.value = hasInput ? td.toFixed(2) : '';

    // SHORT / OVER (AD) = Total Deductions − first SHORT/OVER (POS − CASH)
    // Excel: =AC4 - (B4-C4)  →  47682.73 - 46575.51 = 1107.22
    const so = td - firstSO;
    const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
    if (soEl && editedCol !== 'short_over') {
      soEl.value = hasInput ? so.toFixed(2) : '';
      soEl.classList.toggle('neg', hasInput && so < 0);
    }
  }

  // TOTAL SWIPE — user-inputable (not auto-calculated)
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
    el.style.color = (col === 'short_over' && s < 0) ? 'var(--accent2)' : '';
  });
  recalcDoisSummary();
}

// ── Mark row dirty on input ───────────────────────────────
function changed(el) {
  const rid = el.dataset.row;
  const col = el.dataset.col;
  const tr = document.getElementById(rid);
  if (tr) tr.dataset.touched = '1';
  recalc(rid, col);
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
  const remEl = document.querySelector(`#${rid} [data-col="remarks"]`);
  fd.append('remarks', remEl ? remEl.value : '');

  try {
    const res  = await fetch('dois_summary_report.php', {method:'POST', body:fd});
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
  showToast('✓ All ' + rows.length + ' rows saved for Dois', 'success');
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
  window.location.href = 'dois_summary_report.php?' + params.toString();
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

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#srt tbody tr').forEach(function(r){ recalc(r.id); });
  recalcTotals();
  recalcDoisSummary();
});

// ── Target sales summary panel ────────────────────────────
// Formulas from Excel:
//   Sales %            = Total Gross Sales / Target Sales   (=F5/F7)
//   Needed to Reach    = Target Sales − Total Gross Sales   (=F7-F5)
//   Daily Sales Quota  = Needed / no. of days (user inputs) (=F9/9)
function recalcDoisSummary() {
  const quota = parseFloat(document.getElementById('doisQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('doisDaysInput')?.value)   || 1;

  // Sum gross_sales directly from tbody inputs
  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="gross_sales"]').forEach(function(el) {
    totalGross += parseFloat(el.value) || 0;
  });

  const pctEl    = document.getElementById('doisSalesPct');
  const neededEl = document.getElementById('doisNeeded');
  const dailyEl  = document.getElementById('doisDailyQuota');

  if (quota <= 0) {
    if(pctEl)    pctEl.textContent    = '—';
    if(neededEl) neededEl.textContent = '—';
    if(dailyEl)  dailyEl.textContent  = '—';
    return;
  }

  // Sales % = Total Gross Sales / Target  (=F5/F7)
  const pct = (totalGross / quota) * 100;
  if(pctEl) {
    pctEl.textContent = pct.toFixed(2) + '%';
    pctEl.style.color = pct >= 100 ? 'var(--accent)' : (pct >= 70 ? 'var(--accent3)' : 'var(--accent2)');
  }

  // Needed = Target − Total Gross Sales  (=F7-F5)
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

  // Daily Sales Quota = Needed ÷ days  (=F9/days — user manually updates days)
  const daily = needed <= 0 ? 0 : needed / days;
  if(dailyEl) {
    dailyEl.textContent = needed <= 0
      ? '✓ Done!'
      : '(' + daily.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + ')';
    dailyEl.style.color = needed <= 0 ? 'var(--accent)' : 'var(--accent2)';
  }
}

// ── Save quota via AJAX ────────────────────────────────────
async function saveDoisQuota() {
  const val = document.getElementById('doisQuotaInput')?.value;
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
    await fetch('dois_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>