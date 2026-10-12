<?php
// ============================================================
//  demiclab_summary_report.php — DemicLab-Main Branch Daily Summary Entry
//  Completely standalone — does NOT touch Stella's tables or code
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only DemicLab-Main branch and management can access this page
if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create DemicLab-Main table (safe to run repeatedly) ───────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_report_entries` (
        `id`                   int(11) NOT NULL AUTO_INCREMENT,
        `report_date`          date NOT NULL,
        `store_name`           varchar(100) NOT NULL DEFAULT 'DemicLab-Main',
        `pos_reading`          decimal(12,2) DEFAULT 0.00,
        `cash`                 decimal(12,2) DEFAULT 0.00,
        `short_over`           decimal(12,2) DEFAULT 0.00,
        `tips`                 decimal(12,2) DEFAULT 0.00,
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

// ── Patch table if it already existed with an older/different schema ──
// (e.g. a legacy `demiclab_report_entries` table that predates this
// report and doesn't have these columns yet — CREATE TABLE IF NOT EXISTS
// above won't add columns to a table that already exists.)
$demiclabReportCols = [
    'pos_reading'           => "decimal(12,2) DEFAULT 0.00",
    'cash'                  => "decimal(12,2) DEFAULT 0.00",
    'short_over'            => "decimal(12,2) DEFAULT 0.00",
    'tips'                  => "decimal(12,2) DEFAULT 0.00",
    'gross_sales'           => "decimal(12,2) DEFAULT 0.00",
    'sales_of_day_swipe'    => "decimal(12,2) DEFAULT 0.00",
    'cancelled_transaction' => "decimal(12,2) DEFAULT 0.00",
    'unpaid_staff'          => "decimal(12,2) DEFAULT 0.00",
    'unpaid_mam'            => "decimal(12,2) DEFAULT 0.00",
    'marketing_pull_out'    => "decimal(12,2) DEFAULT 0.00",
    'late_payment'          => "decimal(12,2) DEFAULT 0.00",
    'advance_payment'       => "decimal(12,2) DEFAULT 0.00",
    'grab'                  => "decimal(12,2) DEFAULT 0.00",
    'gcash'                 => "decimal(12,2) DEFAULT 0.00",
    'gc_sold'               => "decimal(12,2) DEFAULT 0.00",
    'gc_sponsorship'        => "decimal(12,2) DEFAULT 0.00",
    'bank_transfer'         => "decimal(12,2) DEFAULT 0.00",
    'discounted'            => "decimal(12,2) DEFAULT 0.00",
    'personal'              => "decimal(12,2) DEFAULT 0.00",
    'cash_advance'          => "decimal(12,2) DEFAULT 0.00",
    'payroll'               => "decimal(12,2) DEFAULT 0.00",
    'commi_fund'            => "decimal(12,2) DEFAULT 0.00",
    'service_charge_pos'    => "decimal(12,2) DEFAULT 0.00",
    'cancelled_sc'          => "decimal(12,2) DEFAULT 0.00",
    'service_charge_depo'   => "decimal(12,2) DEFAULT 0.00",
    'pcf'                   => "decimal(12,2) DEFAULT 0.00",
    'other_expenses'        => "decimal(12,2) DEFAULT 0.00",
    'total_deductions'      => "decimal(12,2) DEFAULT 0.00",
    'total_swipe'           => "decimal(12,2) DEFAULT 0.00",
    'other_deposits'        => "decimal(12,2) DEFAULT 0.00",
    'lechonan_sales'        => "decimal(12,2) DEFAULT 0.00",
    'remarks'               => "text DEFAULT NULL",
    'saved_by'              => "varchar(100) DEFAULT NULL",
    // ── New columns matching the DemicLab-Main Excel layout ──
    'hmo'                   => "decimal(12,2) DEFAULT 0.00",
    'charge_to_company'     => "decimal(12,2) DEFAULT 0.00",
    'debit'                 => "decimal(12,2) DEFAULT 0.00",
    'credit'                => "decimal(12,2) DEFAULT 0.00",
    'discount_30'           => "decimal(12,2) DEFAULT 0.00",
    'discount_scpwd_20'     => "decimal(12,2) DEFAULT 0.00",
    'discount_15'           => "decimal(12,2) DEFAULT 0.00",
    'discount_10'           => "decimal(12,2) DEFAULT 0.00",
    'discount_5'            => "decimal(12,2) DEFAULT 0.00",
    'total_discounts'       => "decimal(12,2) DEFAULT 0.00",
    'total_after_discounts' => "decimal(12,2) DEFAULT 0.00",
];
foreach ($demiclabReportCols as $colName => $colDef) {
    try {
        $pdo->exec("ALTER TABLE `demiclab_report_entries` ADD COLUMN IF NOT EXISTS `$colName` $colDef");
    } catch (Throwable $ignored) {}
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
        'gross_sales','pos_reading','cash','hmo','charge_to_company',
        'debit','credit',
        'discount_30','discount_scpwd_20','discount_15','discount_10','discount_5',
        'total_discounts','total_after_discounts','late_payment'
    ];

    $data = ['store_name' => 'DemicLab-Main', 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['remarks']  = trim($_POST['remarks'] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO demiclab_report_entries ("
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
            ->execute([$month.'-01', 'DemicLab-Main', $quota, $user['name']]);
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
        'gross_sales'           => 'Gross Sales (POS+Discount)',
        'pos_reading'           => 'POS Reading',
        'cash'                  => 'Cash',
        'hmo'                   => 'HMO',
        'charge_to_company'     => 'Charge to Company',
        'debit'                 => 'Debit (amt less 4%)',
        'credit'                => 'Credit (amt less 3.5%)',
        'discount_30'           => 'Discounts 30%',
        'discount_scpwd_20'     => 'Discounts SC/PWD (20%)',
        'discount_15'           => 'Discounts 15%',
        'discount_10'           => 'Discounts 10%',
        'discount_5'            => 'Discounts 5%',
        'total_discounts'       => 'Total Discounts',
        'total_after_discounts' => 'Total After Discounts',
        'late_payment'          => 'Late Payment (Check)',
        'remarks'               => 'Remarks',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM demiclab_report_entries WHERE store_name='DemicLab-Main' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = 'DemicLab-Main_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['DemicLab-Main — Summary Report']);
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
    $stmt = $pdo->prepare("SELECT * FROM demiclab_report_entries WHERE store_name='DemicLab-Main' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Load saved quota for this month ──────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='DemicLab-Main' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([$fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column key list (for tfoot & JS) — matches the Excel layout order ──
// POS Reading is auto-calculated from Cash+HMO+Charge+Debit+Credit (like
// DemicLab-Jaro) but is still saved to the database.
$COLS = [
    'gross_sales','pos_reading','cash','hmo','charge_to_company',
    'debit','credit',
    'discount_30','discount_scpwd_20','discount_15','discount_10','discount_5',
    'total_discounts','total_after_discounts','late_payment'
];

$pageTitle  = 'DemicLab-Main Summary Report';
$activePage = 'demiclab_summary';
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
    <div class="section-title" style="color:#1a1d23">DemicLab-Main <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily entry · each row has its own Save button · all calculated fields update automatically</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab-Main
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
  <a href="demiclab_summary_overview.php" class="btn btn-ghost btn-sm">📊 View Overview</a>
</form>

<!-- Legend -->
<div class="sr-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income / Manual Entry</span>
  <span><span class="ld" style="background:#fecaca;border:1px solid #fca5a5"></span>Discounts</span>
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
      <!-- GROSS SALES (AUTO) -->
      <th colspan="1" class="g-calc">AUTO</th>
      <!-- POS / CASH / HMO / CHARGE / DEBIT / CREDIT -->
      <th colspan="6" class="g-income">POS / CASH / HMO / CHARGE / DEBIT / CREDIT</th>
      <!-- DISCOUNTS -->
      <th colspan="5" class="g-deduct">DISCOUNTS</th>
      <!-- AUTO-CALCULATED -->
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <!-- LATE PAYMENT -->
      <th colspan="1" class="g-income">LATE PAYMENT</th>
      <!-- NOTES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- AUTO: Gross Sales -->
      <th class="g-calc col-num">GROSS SALES<br>(POS+DISCOUNT)</th>
      <!-- POS / CASH / HMO / CHARGE / DEBIT / CREDIT -->
      <th class="g-income col-num">POS READING</th>
      <th class="g-income col-num">CASH</th>
      <th class="g-income col-num">HMO</th>
      <th class="g-income col-num">CHARGE TO<br>COMPANY</th>
      <th class="g-income col-num">DEBIT<br>(amt less 4%)</th>
      <th class="g-income col-num">CREDIT<br>(amt less 3.5%)</th>
      <!-- DISCOUNTS -->
      <th class="g-deduct col-num">30%</th>
      <th class="g-deduct col-num">SC/PWD (20%)</th>
      <th class="g-deduct col-num">15%</th>
      <th class="g-deduct col-num">10%</th>
      <th class="g-deduct col-num">5%</th>
      <!-- AUTO-CALCULATED -->
      <th class="g-calc col-num">TOTAL<br>DISCOUNTS</th>
      <th class="g-calc col-num">TOTAL AFTER<br>DISCOUNTS</th>
      <!-- LATE PAYMENT -->
      <th class="g-income col-num">LATE PAYMENT<br>(CHECK)</th>
      <!-- NOTES -->
      <th class="g-text col-txt">REMARKS</th>
      <th class="th-act">SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN  = (int)date('j', strtotime($ds));
    $dayNm = date('D', strtotime($ds));
    $row   = $savedRows[$ds] ?? null;
    $saved = $row !== null;
    $rid   = 'r' . str_replace('-', '', $ds);
    $dv    = fn($k) => ($row && isset($row[$k]) && (float)$row[$k] != 0)
                       ? number_format((float)$row[$k], 2, '.', '') : '';
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':'new'?>" id="dot_<?=$rid?>"></span>
    </td>

    <!-- AUTO: Gross Sales = POS Reading + Total Discounts -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="gross_sales" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <!-- POS Reading (auto: cash+hmo+ctc+debit+credit) | Cash | HMO | Charge | Debit | Credit -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="pos_reading" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="cash" data-row="<?=$rid?>" value="<?=$dv('cash')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="hmo" data-row="<?=$rid?>" value="<?=$dv('hmo')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="charge_to_company" data-row="<?=$rid?>" value="<?=$dv('charge_to_company')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="debit" data-row="<?=$rid?>" value="<?=$dv('debit')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="credit" data-row="<?=$rid?>" value="<?=$dv('credit')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- DISCOUNTS -->
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="discount_30" data-row="<?=$rid?>" value="<?=$dv('discount_30')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="discount_scpwd_20" data-row="<?=$rid?>" value="<?=$dv('discount_scpwd_20')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="discount_15" data-row="<?=$rid?>" value="<?=$dv('discount_15')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="discount_10" data-row="<?=$rid?>" value="<?=$dv('discount_10')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="g-deduct"><input type="number" step="0.01" class="sri" data-col="discount_5" data-row="<?=$rid?>" value="<?=$dv('discount_5')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- AUTO-CALCULATED: Total Discounts / Total After Discounts -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_discounts" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_after_discounts" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <!-- LATE PAYMENT -->
    <td class="g-income"><input type="number" step="0.01" class="sri" data-col="late_payment" data-row="<?=$rid?>" value="<?=$dv('late_payment')?>" placeholder="0.00" oninput="changed(this)"></td>
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

<!-- ── RECONCILIATION SUMMARY PANEL (mirrors the Excel's bottom block) ── -->
<div class="card" style="margin-top:20px;overflow:hidden">
  <div style="padding:12px 20px;background:#f8f9fb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">DemicLab-Main — Reconciliation Summary (Month Totals)</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0">
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Cash Sales</div>
      <div id="reconCash" style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)">—</div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">POS Reading</div>
      <div id="reconPos" style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)">—</div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">POS + Discount</div>
      <div id="reconPosDiscount" style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)">—</div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Total Discount</div>
      <div id="reconTotalDiscount" style="font-size:1.05rem;font-weight:800;color:#be123c;font-family:var(--font-m)">—</div>
    </div>
    <div style="padding:14px 18px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Gross Sale</div>
      <div id="reconGrossSale" style="font-size:1.05rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
    </div>
  </div>
</div>

<!-- ── TARGET SALES SUMMARY PANEL ── -->
<div class="card" style="margin-top:20px;border-top:2px solid var(--accent3);overflow:hidden">
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">DemicLab-Main — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <!-- TARGET SALES (inputable) -->
    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="demiclabQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcDemicLabSummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';saveDemicLabQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <!-- SALES PERCENTAGE -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="demiclabSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Gross Sales ÷ Target</div>
    </div>

    <!-- NEEDED SALES TO REACH TARGET -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="demiclabNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Gross Sales</div>
    </div>

    <!-- DAILY SALES QUOTA -->
    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="demiclabDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="demiclabDaysInput" type="number" min="1" step="1"
          value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcDemicLabSummary()"
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

// ── Auto-calculations (matches DemicLab-Jaro's logic) ──
function recalc(rid) {
  const cash   = gv(rid, 'cash');
  const hmo    = gv(rid, 'hmo');
  const ctc    = gv(rid, 'charge_to_company');
  const debit  = gv(rid, 'debit');
  const credit = gv(rid, 'credit');
  const hasCash = cash || hmo || ctc || debit || credit;

  // POS READING (auto) = Cash + HMO + Charge to Company + Debit + Credit
  const pos = cash + hmo + ctc + debit + credit;
  const posEl = document.querySelector(`#${rid} [data-col="pos_reading"]`);
  if (posEl) posEl.value = hasCash ? pos.toFixed(2) : '';

  // DISCOUNT TIER BASE AMOUNTS (the sale amount subject to each rate)
  const d30 = gv(rid, 'discount_30');
  const dsc = gv(rid, 'discount_scpwd_20');
  const d15 = gv(rid, 'discount_15');
  const d10 = gv(rid, 'discount_10');
  const d5  = gv(rid, 'discount_5');
  const hasDiscInput = d30 || dsc || d15 || d10 || d5;

  // TOTAL DISCOUNTS = 30%×I + 20%×J + 15%×K + 10%×L + 5%×M
  const totalDiscounts = (d30 * 0.30) + (dsc * 0.20) + (d15 * 0.15) + (d10 * 0.10) + (d5 * 0.05);
  const tdEl = document.querySelector(`#${rid} [data-col="total_discounts"]`);
  if (tdEl) tdEl.value = hasDiscInput ? totalDiscounts.toFixed(2) : '';

  // TOTAL AFTER DISCOUNTS = (I+J+K+L+M) − Total Discounts
  const discBaseSum = d30 + dsc + d15 + d10 + d5;
  const totalAfterDiscounts = discBaseSum - totalDiscounts;
  const tadEl = document.querySelector(`#${rid} [data-col="total_after_discounts"]`);
  if (tadEl) tadEl.value = hasDiscInput ? totalAfterDiscounts.toFixed(2) : '';

  // GROSS SALES (POS+DISCOUNT) = POS Reading (auto) + Total Discounts
  const grossSales = pos + totalDiscounts;
  const gsEl = document.querySelector(`#${rid} [data-col="gross_sales"]`);
  if (gsEl) gsEl.value = (hasCash || hasDiscInput) ? grossSales.toFixed(2) : '';
}

// ── Column totals ─────────────────────────────────────────
function colTotal(col) {
  let s = 0;
  document.querySelectorAll(`[data-col="${col}"]`).forEach(function(i) {
    s += parseFloat(i.value) || 0;
  });
  return s;
}

function recalcTotals() {
  COLS.forEach(function(col) {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    const s = colTotal(col);
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  });
  recalcReconSummary();
  recalcDemicLabSummary();
}

// ── Reconciliation summary panel (mirrors Excel's bottom block) ──
function recalcReconSummary() {
  const fmt = v => v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  const cash      = colTotal('cash');
  const pos       = colTotal('pos_reading');
  const gross     = colTotal('gross_sales');       // POS + Discount
  const totalDisc = colTotal('total_discounts');

  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = fmt(v); };
  set('reconCash', cash);
  set('reconPos', pos);
  set('reconPosDiscount', gross);
  set('reconTotalDiscount', totalDisc);
  set('reconGrossSale', gross);
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
  const remEl = document.querySelector(`#${rid} [data-col="remarks"]`);
  fd.append('remarks', remEl ? remEl.value : '');

  try {
    const res  = await fetch('demiclab_summary_report.php', {method:'POST', body:fd});
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
  showToast('✓ All ' + rows.length + ' rows saved for DemicLab-Main', 'success');
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
  window.location.href = 'demiclab_summary_report.php?' + params.toString();
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
  recalcDemicLabSummary();
});

// ── Target sales summary panel ────────────────────────────
// Formulas from Excel:
//   Sales %            = Total Gross Sales / Target Sales   (=F5/F7)
//   Needed to Reach    = Target Sales − Total Gross Sales   (=F7-F5)
//   Daily Sales Quota  = Needed / no. of days (user inputs) (=F9/9)
function recalcDemicLabSummary() {
  const quota = parseFloat(document.getElementById('demiclabQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('demiclabDaysInput')?.value)   || 1;

  // Sum gross_sales directly from tbody inputs
  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="gross_sales"]').forEach(function(el) {
    totalGross += parseFloat(el.value) || 0;
  });

  const pctEl    = document.getElementById('demiclabSalesPct');
  const neededEl = document.getElementById('demiclabNeeded');
  const dailyEl  = document.getElementById('demiclabDailyQuota');

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
async function saveDemicLabQuota() {
  const val = document.getElementById('demiclabQuotaInput')?.value;
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
    await fetch('demiclab_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>