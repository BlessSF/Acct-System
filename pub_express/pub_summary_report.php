<?php
// ============================================================
//  pub_summary_report.php — Pub Express Daily Summary Entry
//  Columns match the PUB SM Excel sheet exactly
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Pub Express branch and management can access this page
if (isBranch() && currentBranch() !== 'Pub Express') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create Pub Express table ─────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pub_report_entries` (
        `id`                    int(11) NOT NULL AUTO_INCREMENT,
        `report_date`           date NOT NULL,
        `store_name`            varchar(100) NOT NULL DEFAULT 'Pub Express',
        `collected_pos`         decimal(12,2) DEFAULT 0.00,
        `discount`              decimal(12,2) DEFAULT 0.00,
        `total_collected`       decimal(12,2) DEFAULT 0.00,
        `uncollected_grab`      decimal(12,2) DEFAULT 0.00,
        `gross_sales`           decimal(12,2) DEFAULT 0.00,
        `total_cash_deposit`    decimal(12,2) DEFAULT 0.00,
        `gcash`                 decimal(12,2) DEFAULT 0.00,
        `swiper`                decimal(12,2) DEFAULT 0.00,
        `payroll_ca`            decimal(12,2) DEFAULT 0.00,
        `marketing`             decimal(12,2) DEFAULT 0.00,
        `unpaid_mam_nikki`      decimal(12,2) DEFAULT 0.00,
        `unpaids`               decimal(12,2) DEFAULT 0.00,
        `direct_purchases`      decimal(12,2) DEFAULT 0.00,
        `pcf`                   decimal(12,2) DEFAULT 0.00,
        `grab`                  decimal(12,2) DEFAULT 0.00,
        `personal_mam_nikki`    decimal(12,2) DEFAULT 0.00,
        `short_over`            decimal(12,2) DEFAULT 0.00,
        `remarks`               text DEFAULT NULL,
        `saved_by`              varchar(100) DEFAULT NULL,
        `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

// Staff Cash Advance column (sits between Payroll/Salary and Marketing) — added to older installs.
try {
    $hasCa = $pdo->query("SHOW COLUMNS FROM `pub_report_entries` LIKE 'staff_cash_advance'")->fetch();
    if (!$hasCa) $pdo->exec("ALTER TABLE `pub_report_entries` ADD COLUMN `staff_cash_advance` decimal(12,2) DEFAULT 0.00 AFTER `payroll_ca`");
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
        'collected_pos','discount','total_collected','uncollected_grab','gross_sales',
        'total_cash_deposit','gcash','swiper','payroll_ca','staff_cash_advance','marketing',
        'unpaid_mam_nikki','unpaids','direct_purchases','pcf','grab',
        'personal_mam_nikki','short_over'
    ];

    $data = ['store_name' => 'Pub Express', 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['remarks']  = trim($_POST['remarks'] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO pub_report_entries ("
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
            ->execute([$month.'-01', 'Pub Express', $quota, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle CSV export ─────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $csvMonthNames = ['','January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    $csvCols = [
        'report_date'        => 'Date',
        'collected_pos'      => 'Collected (POS)',
        'discount'           => 'Discount',
        'total_collected'    => 'Total Collected',
        'uncollected_grab'   => 'Uncollected (Manual OS Grab)',
        'gross_sales'        => 'Gross Sales',
        'total_cash_deposit' => 'Total Cash/Deposit',
        'gcash'              => 'GCash',
        'swiper'             => 'Swiper',
        'payroll_ca'         => 'Payroll/Salary',
        'staff_cash_advance' => 'Staff Cash Advance',
        'marketing'          => 'Marketing',
        'unpaid_mam_nikki'   => 'Unpaid Ma\'am Nikki',
        'unpaids'            => 'Unpaids',
        'direct_purchases'   => 'Direct Purchases',
        'pcf'                => 'PCF',
        'grab'               => 'Grab',
        'personal_mam_nikki' => 'Personal Ma\'am Nikki',
        'short_over'         => '(Short)/Over',
        'remarks'            => 'Remarks',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM pub_report_entries WHERE store_name='Pub Express' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = 'PubExpress_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Pub Express — Summary Report']);
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

// ── Load saved rows ───────────────────────────────────────
$savedRows = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM pub_report_entries WHERE store_name='Pub Express' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull fallback data from the Sales Report for dates that ──
// don't have a manually-saved summary row yet (mirrors Dois's setup)
$salesRows = [];
try {
    $ssStmt = $pdo->prepare("SELECT * FROM pub_express_sales_report WHERE store_name='Pub Express' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $ssStmt->execute([$fYear, $fMonth]);
    foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// Cash Advance and Payroll / Salary typed on the Sales Report (their own boxes) -> Staff Cash Advance
// and Payroll / Salary here. Summed straight from the Sales Report's detail rows so they're always
// current, including older dates.
try {
    $caStmt = $pdo->prepare("SELECT report_date, section, COALESCE(SUM(amount),0) AS t FROM pub_express_sales_detail_rows
                             WHERE store_name='Pub Express' AND section IN ('cash_advance','payroll_salary')
                               AND YEAR(report_date)=? AND MONTH(report_date)=? GROUP BY report_date, section");
    $caStmt->execute([$fYear, $fMonth]);
    foreach ($caStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($salesRows[$r['report_date']])) $salesRows[$r['report_date']][$r['section']] = (float)$r['t'];
    }
} catch (Throwable $ignored) {}

// Map: summary column => sales-report column it should mirror.
// Fields with no equivalent on the Sales Report (unpaids,
// direct_purchases, personal_mam_nikki) stay 100% manual entry here.
// uncollected_grab mirrors the Sales Report's "Undeclared" field.
// total_collected has no Sales Report equivalent either — it's always computed
// locally from Collected (POS) + Discount.
$SALES_MAP = [
    'collected_pos'      => 'z_reading_gross',
    'uncollected_grab'   => 'undeclared',   // Sales Report "Undeclared" -> Uncollected
    'discount'           => 'discount',
    'gross_sales'        => 'gross_sales',
    'grab'               => 'grab_sales',
    'gcash'              => 'gcash',
    'swiper'             => 'maya_swipe',   // Swiper = Maya Card + Maya QR (computed in $dv below)
    'total_cash_deposit' => 'coh',
    'marketing'          => 'marketing_pull_out',
    'unpaid_mam_nikki'   => 'unpaid_med_credit',
    'direct_purchases'   => 'pcf_expenses',   // Sales Report total Expenses (incl. Cash Advance + Payroll) -> Direct Purchases, minus both (own columns now)
    'staff_cash_advance' => 'cash_advance',   // Sales Report Cash Advance -> Staff Cash Advance
    'payroll_ca'         => 'payroll_salary', // Sales Report Payroll / Salary -> Payroll / Salary
    'short_over'         => 'short_over',
];

// ── Load saved quota ──────────────────────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='Pub Express' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([$fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column keys (numeric, for tfoot & JS) ─────────────────
// Auto-calculated: total_collected, gross_sales, short_over
$COLS = [
    'collected_pos','discount','total_collected','uncollected_grab','gross_sales',
    'total_cash_deposit','gcash','swiper','payroll_ca','staff_cash_advance','marketing',
    'unpaid_mam_nikki','unpaids','direct_purchases','pcf','grab',
    'personal_mam_nikki','short_over'
];

$pageTitle  = 'Pub Express Summary Report';
$activePage = 'pub_summary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* ── Hide number-input spinner arrows (type-only entry) ── */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none !important; margin: 0 !important; }
input[type=number] { -moz-appearance: textfield !important; appearance: textfield !important; }

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
.col-num  { width: 108px; min-width: 108px; }
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
.sri.neg   { color: #be123c !important; font-weight: 600; }
.sri.txt   { text-align: left; font-family: var(--font-h); font-size: .69rem; }

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
    <div class="section-title" style="color:#1a1d23">Pub Express <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily spreadsheet entry · each row has its own Save button · Save All saves every row</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 Pub Express
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
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Auto-calculated</span>
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved &nbsp;<span class="sdot auto"></span> Auto-filled</span>
</div>

<!-- Spreadsheet -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <tr class="grp-row">
      <th class="th-date"></th>
      <!-- SALES -->
      <th colspan="2" class="g-income">SALES INPUT</th>
      <th colspan="1" class="g-calc">AUTO-CALC</th>
      <th colspan="1" class="g-income">UNCOLLECTED</th>
      <th colspan="1" class="g-calc">AUTO-CALC</th>
      <!-- CASH / DEPOSITS -->
      <th colspan="3" class="g-income">CASH / DEPOSITS</th>
      <!-- DEDUCTIONS -->
      <th colspan="9" class="g-deduct">DEDUCTIONS</th>
      <!-- RESULT -->
      <th colspan="1" class="g-calc">RESULT</th>
      <!-- NOTES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- SALES INPUT -->
      <th class="g-income col-num">COLLECTED (POS)</th>
      <th class="g-income col-num">DISCOUNT</th>
      <!-- AUTO-CALC -->
      <th class="g-calc col-num">TOTAL COLLECTED</th>
      <!-- UNCOLLECTED -->
      <th class="g-income col-num">UNCOLLECTED (MANUAL OS GRAB)</th>
      <!-- AUTO-CALC -->
      <th class="g-calc col-num">GROSS SALES</th>
      <!-- CASH / DEPOSITS -->
      <th class="g-income col-num">TOTAL CASH / DEPOSIT</th>
      <th class="g-income col-num">GCASH</th>
      <th class="g-income col-num">SWIPER</th>
      <!-- DEDUCTIONS -->
      <th class="g-deduct col-num">PAYROLL / SALARY</th>
      <th class="g-deduct col-num">STAFF CASH ADVANCE</th>
      <th class="g-deduct col-num">MARKETING</th>
      <th class="g-deduct col-num">UNPAID MA'AM NIKKI</th>
      <th class="g-deduct col-num">UNPAIDS</th>
      <th class="g-deduct col-num">DIRECT PURCHASES</th>
      <th class="g-deduct col-num">PCF</th>
      <th class="g-deduct col-num">GRAB</th>
      <th class="g-deduct col-num">PERSONAL MA'AM NIKKI</th>
      <!-- RESULT -->
      <th class="g-calc col-num">(SHORT) / OVER</th>
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
    $sr    = $salesRows[$ds] ?? null;
    $hasSR = $sr !== null;          // Sales Report has data for this date
    $fromSR = $hasSR && !$saved;    // used only for the status dot
    $rid   = 'r' . str_replace('-', '', $ds);
    // Sales-Report-mapped fields ALWAYS mirror the Sales Report (source of truth),
    // even if this row was previously saved with stale/old values. Fields not in
    // SALES_MAP fall back to whatever was manually saved.
    $dv    = function($k) use ($row, $sr, $SALES_MAP) {
        // Uncollected always follows the Sales Report's Undeclared (blank when 0),
        // so a stale manual value can never get stuck in a read-only cell.
        if ($k === 'uncollected_grab' && $sr) {
            $u = (float)($sr['undeclared'] ?? 0);
            return $u != 0 ? number_format($u, 2, '.', '') : '';
        }
        // Swiper = Maya Card + Maya QR from the Sales Report (blank when 0, so a stale
        // saved value can never get stuck in a read-only cell).
        if ($k === 'swiper' && $sr) {
            $sw = (float)($sr['maya_swipe'] ?? 0) + (float)($sr['gcash'] ?? 0);
            return $sw != 0 ? number_format($sw, 2, '.', '') : '';
        }
        // Staff Cash Advance = the Sales Report's Cash Advance total (blank when 0, so a stale
        // saved value can never get stuck in a read-only cell).
        if ($k === 'payroll_ca' && $sr) {
            $pr = (float)($sr['payroll_salary'] ?? 0);
            return $pr != 0 ? number_format($pr, 2, '.', '') : '';
        }
        if ($k === 'staff_cash_advance' && $sr) {
            $ca = (float)($sr['cash_advance'] ?? 0);
            return $ca != 0 ? number_format($ca, 2, '.', '') : '';
        }
        // Direct Purchases = Sales Report Expenses total WITHOUT the Cash Advance and Payroll / Salary
        // (each has its own column now, so they aren't counted twice).
        if ($k === 'direct_purchases' && $sr) {
            $dp = (float)($sr['pcf_expenses'] ?? 0) - (float)($sr['cash_advance'] ?? 0) - (float)($sr['payroll_salary'] ?? 0);
            if ($dp > 0.004) return number_format($dp, 2, '.', '');
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
    // whenever the Sales Report has data for that date — edit them on the Sales
    // Report page and they'll flow through here automatically.
    $inp = function($col, $cls) use ($SALES_MAP, $hasSR, $dv, $rid) {
        $isSR = $hasSR && isset($SALES_MAP[$col]);
        $icls = $isSR ? 'sri calc' : 'sri';
        $ro   = $isSR ? ' readonly tabindex="-1"' : '';
        $oi   = $isSR ? '' : ' oninput="changed(this)"';
        $val  = $dv($col);
        return '<td class="'.$cls.'"><input type="number" step="0.01" class="'.$icls.'" data-col="'.$col.'" data-row="'.$rid.'" value="'.$val.'" placeholder="0.00"'.$oi.$ro.'></td>';
    };
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>" data-fromsr="<?=$hasSR?1:0?>">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':($fromSR?'auto':'new')?>" id="dot_<?=$rid?>" title="<?=$fromSR?'Auto-filled from Sales Report — click Save to lock in':''?>"></span>
    </td>

    <!-- SALES INPUT -->
    <?= $inp('collected_pos','g-income') ?>
    <?= $inp('discount','g-income') ?>
    <!-- AUTO-CALC: TOTAL COLLECTED = Collected POS + Discount (no Sales Report equivalent — always local) -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_collected" data-row="<?=$rid?>" value="<?=$dv('total_collected')?>"    readonly tabindex="-1"></td>
    <!-- UNCOLLECTED -->
    <?= $inp('uncollected_grab','g-income') ?>
    <!-- AUTO-CALC: GROSS SALES -->
    <?= $inp('gross_sales','g-calc') ?>
    <!-- CASH / DEPOSITS -->
    <?= $inp('total_cash_deposit','g-income') ?>
    <?= $inp('gcash','g-income') ?>
    <?= $inp('swiper','g-income') ?>
    <!-- DEDUCTIONS -->
    <?= $inp('payroll_ca','g-deduct') ?>
    <?= $inp('staff_cash_advance','g-deduct') ?>
    <?= $inp('marketing','g-deduct') ?>
    <?= $inp('unpaid_mam_nikki','g-deduct') ?>
    <?= $inp('unpaids','g-deduct') ?>
    <?= $inp('direct_purchases','g-deduct') ?>
    <?= $inp('pcf','g-deduct') ?>
    <?= $inp('grab','g-deduct') ?>
    <?= $inp('personal_mam_nikki','g-deduct') ?>
    <!-- AUTO-CALC: (SHORT)/OVER -->
    <?= $inp('short_over','g-calc') ?>
    <!-- NOTES -->
    <td class="g-text"><input type="text" class="sri txt" data-col="remarks" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks'] ?? '')?>" placeholder="…" oninput="changed(this)"></td>

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
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">Pub Express — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="pubQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcPubSummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';savePubQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="pubSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Gross Sales ÷ Target</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed to Reach Target</div>
      <div id="pubNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Gross Sales</div>
    </div>

    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="pubDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="pubDaysInput" type="number" min="1" step="1" value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcPubSummary()"
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

// ── Auto-calculations ─────────────────────────────────────
function recalc(rid) {
  const autoCalcCols = ['total_collected','gross_sales','short_over'];
  const hasInput = COLS.some(c => !autoCalcCols.includes(c) ? (gv(rid, c) !== 0) : false);

  // Rows where the Sales Report has data for this date keep Gross Sales and
  // Short/Over as read-only mirrors — those two get their values straight
  // from the Sales Report and must not be overwritten by the local formula.
  const tr = document.getElementById(rid);
  const preserveSR = !!(tr && tr.dataset.fromsr === '1');

  // TOTAL COLLECTED = Collected (POS) + Discount   [D = B + C]
  // No Sales Report equivalent exists for this field, so it's always computed locally.
  const totalCollected = gv(rid,'collected_pos') + gv(rid,'discount');
  const tcEl = document.querySelector(`#${rid} [data-col="total_collected"]`);
  if (tcEl) tcEl.value = hasInput ? totalCollected.toFixed(2) : '';

  // GROSS SALES = Total Collected + Uncollected (Manual OS Grab)   [F = D + E]
  if (!preserveSR) {
    const grossSales = totalCollected + gv(rid,'uncollected_grab');
    const gsEl = document.querySelector(`#${rid} [data-col="gross_sales"]`);
    if (gsEl) gsEl.value = hasInput ? grossSales.toFixed(2) : '';
  }

  // (SHORT)/OVER = SUM(G:Q) + Discount − Gross Sales
  //   [R = SUM(G3:Q3) + C3 − F3]
  //   G=total_cash_deposit, H=gcash, I=swiper, J=payroll_ca, K=staff_cash_advance,
  //   L=marketing, M=unpaid_mam_nikki, N=unpaids, O=direct_purchases, P=pcf, Q=grab,
  //   R=personal_mam_nikki
  if (!preserveSR) {
    const grossSales = parseFloat(document.querySelector(`#${rid} [data-col="gross_sales"]`)?.value) || 0;
    const sumGtoQ = gv(rid,'total_cash_deposit')
                  + gv(rid,'gcash')
                  + gv(rid,'swiper')
                  + gv(rid,'payroll_ca')
                  + gv(rid,'staff_cash_advance')
                  + gv(rid,'marketing')
                  + gv(rid,'unpaid_mam_nikki')
                  + gv(rid,'unpaids')
                  + gv(rid,'direct_purchases')
                  + gv(rid,'pcf')
                  + gv(rid,'grab')
                  + gv(rid,'personal_mam_nikki');
    const so = sumGtoQ + gv(rid,'discount') - grossSales;
    const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
    if (soEl) {
      soEl.value = hasInput ? so.toFixed(2) : '';
      soEl.classList.toggle('neg', hasInput && so < 0);
    }
  }
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
  recalcPubSummary();
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
    const res  = await fetch('pub_summary_report.php', {method:'POST', body:fd});
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
  showToast('✓ All ' + rows.length + ' rows saved for Pub Express', 'success');
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
  window.location.href = 'pub_summary_report.php?' + params.toString();
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
function recalcPubSummary() {
  const quota = parseFloat(document.getElementById('pubQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('pubDaysInput')?.value)   || 1;

  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="gross_sales"]').forEach(function(el) {
    totalGross += parseFloat(el.value) || 0;
  });

  const pctEl    = document.getElementById('pubSalesPct');
  const neededEl = document.getElementById('pubNeeded');
  const dailyEl  = document.getElementById('pubDailyQuota');

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

// ── Save quota via AJAX ───────────────────────────────────
async function savePubQuota() {
  const val = document.getElementById('pubQuotaInput')?.value;
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
    await fetch('pub_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#srt tbody tr').forEach(function(r){ recalc(r.id); });
  recalcTotals();
  recalcPubSummary();
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>