<?php
// ============================================================
//  recovery_summary_report.php — Recovery Spa Daily Summary
//  Columns match the RECOVERY SPA Excel sheet exactly
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Recovery branch and management can access this page
if (isBranch() && currentBranch() !== 'Recovery') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create Recovery table ────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_report_entries` (
        `id`                    int(11) NOT NULL AUTO_INCREMENT,
        `report_date`           date NOT NULL,
        `store_name`            varchar(100) NOT NULL DEFAULT 'Recovery',
        `pos_reading`           decimal(12,2) DEFAULT 0.00,
        `cash_for_depo`         decimal(12,2) DEFAULT 0.00,
        `short_over`            decimal(12,2) DEFAULT 0.00,
        `gross_sales_excl_mktg` decimal(12,2) DEFAULT 0.00,
        `sales_of_day_swipe`    decimal(12,2) DEFAULT 0.00,
        `unpaid_staff`          decimal(12,2) DEFAULT 0.00,
        `unpaid_corporate`      decimal(12,2) DEFAULT 0.00,
        `unpaid_mam_nikki`      decimal(12,2) DEFAULT 0.00,
        `marketing_pull_out`    decimal(12,2) DEFAULT 0.00,
        `redeemed_gc_voucher`   decimal(12,2) DEFAULT 0.00,
        `sold_product`          decimal(12,2) DEFAULT 0.00,
        `bpi_bank`              decimal(12,2) DEFAULT 0.00,
        `gcash`                 decimal(12,2) DEFAULT 0.00,
        `gc_sold`               decimal(12,2) DEFAULT 0.00,
        `gc_sponsorship`        decimal(12,2) DEFAULT 0.00,
        `bank_transfer`         decimal(12,2) DEFAULT 0.00,
        `discounted_snr_pwd`    decimal(12,2) DEFAULT 0.00,
        `regular_staff_disc`    decimal(12,2) DEFAULT 0.00,
        `personal`              decimal(12,2) DEFAULT 0.00,
        `cash_advance`          decimal(12,2) DEFAULT 0.00,
        `payroll`               decimal(12,2) DEFAULT 0.00,
        `commission_fee_staff`  decimal(12,2) DEFAULT 0.00,
        `pcf_expenses`          decimal(12,2) DEFAULT 0.00,
        `other_expenses`        decimal(12,2) DEFAULT 0.00,
        `total_deductions`      decimal(12,2) DEFAULT 0.00,
        `acctg_short_over`      decimal(12,2) DEFAULT 0.00,
        `total_swipe`           decimal(12,2) DEFAULT 0.00,
        `remarks`               text DEFAULT NULL,
        `saved_by`              varchar(100) DEFAULT NULL,
        `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Add acctg_short_over if it doesn't exist yet (for existing tables)
    try { $pdo->exec("ALTER TABLE recovery_report_entries ADD COLUMN `acctg_short_over` decimal(12,2) DEFAULT 0.00 AFTER `total_deductions`"); } catch (Throwable $ignored) {}
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
        'pos_reading','cash_for_depo','short_over','gross_sales_excl_mktg',
        'sales_of_day_swipe','unpaid_staff','unpaid_corporate','unpaid_mam_nikki',
        'marketing_pull_out','redeemed_gc_voucher','sold_product',
        'bpi_bank','gcash','gc_sold','gc_sponsorship','bank_transfer',
        'discounted_snr_pwd','regular_staff_disc','personal','cash_advance','payroll',
        'commission_fee_staff','pcf_expenses','other_expenses',
        'total_deductions','acctg_short_over','total_swipe'
    ];

    $data = ['store_name' => 'Recovery', 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['remarks']  = trim($_POST['remarks'] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO recovery_report_entries ("
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
            ->execute([$month.'-01', 'Recovery', $quota, $user['name']]);
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
        'report_date'           => 'Date',
        'pos_reading'           => 'POS Reading',
        'cash_for_depo'         => 'Cash for Depo',
        'short_over'            => 'Short / Over',
        'gross_sales_excl_mktg' => 'Gross Sales (Excl Marketing)',
        'sales_of_day_swipe'    => 'Sales of the Day (Swipe)',
        'unpaid_staff'          => 'Unpaid — Staff',
        'unpaid_corporate'      => 'Unpaid — Corporate',
        'unpaid_mam_nikki'      => 'Unpaid — Mam Nikki / Sir Budoy / Corp',
        'marketing_pull_out'    => 'Marketing Pull-Out',
        'redeemed_gc_voucher'   => 'Redeemed GC / Voucher',
        'sold_product'          => 'Sold Product',
        'bpi_bank'              => 'BPI Bank',
        'gcash'                 => 'GCash',
        'gc_sold'               => 'Gift Cert Sold',
        'gc_sponsorship'        => 'Gift Cert Sponsorship',
        'bank_transfer'         => 'Bank Transfer',
        'discounted_snr_pwd'    => 'Discounted SNR/PWD (20%)',
        'regular_staff_disc'    => 'Regular Staff / Special Disc',
        'personal'              => 'Personal',
        'cash_advance'          => 'Cash Advance',
        'payroll'               => 'Payroll',
        'commission_fee_staff'  => 'Commission Fee (Staff)',
        'pcf_expenses'          => 'PCF Expenses',
        'other_expenses'        => 'Other Expenses',
        'total_deductions'      => 'Total Deductions',
        'total_swipe'           => 'Total Swipe',
        'remarks'               => 'Remarks',
    ];

    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    $csvStmt = $pdo->prepare("SELECT * FROM recovery_report_entries WHERE store_name='Recovery' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = 'Recovery_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Recovery Spa — Summary Report']);
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
    $stmt = $pdo->prepare("SELECT * FROM recovery_report_entries WHERE store_name='Recovery' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear, $fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Pull fallback data from the Sales Report for dates      ──
// that don't have a manually-saved summary row yet (same pattern
// as ../dois/dois_summary_report.php auto-fill from dois_sales_report)
$salesRows = [];
try {
    $ssStmt = $pdo->prepare("SELECT * FROM recovery_sales_report WHERE store_name='Recovery' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $ssStmt->execute([$fYear, $fMonth]);
    foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// Map: summary column => Sales Report source column
$SALES_MAP = [
    'pos_reading'           => 'pos_reading',
    'cash_for_depo'         => 'coh',
    'sales_of_day_swipe'    => 'swiper',
    'unpaid_corporate'      => 'unpaids',
    'marketing_pull_out'    => 'marketing_expense',
    'redeemed_gc_voucher'   => 'redeemed_gc',
    'sold_product'          => 'product_sold',
    'gcash'                 => 'gcash_sales',
    'gc_sold'                => 'sold_gc',
    'discounted_snr_pwd'    => 'discounts',
    'commission_fee_staff'  => 'staff_cf',
    'pcf_expenses'          => 'expenses',
];

// ── Load saved quota ──────────────────────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored) {}
    $qRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='Recovery' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qRow->execute([$fYear, $fMonth]);
    $savedQuota = (float)($qRow->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

// ── Column keys (numeric, for tfoot totals) ───────────────
$COLS = [
    'pos_reading','cash_for_depo','short_over','gross_sales_excl_mktg',
    'sales_of_day_swipe','unpaid_staff','unpaid_corporate','unpaid_mam_nikki',
    'marketing_pull_out','redeemed_gc_voucher','sold_product',
    'bpi_bank','gcash','gc_sold','gc_sponsorship','bank_transfer',
    'discounted_snr_pwd','regular_staff_disc','personal','cash_advance','payroll',
    'commission_fee_staff','pcf_expenses','other_expenses',
    'total_deductions','acctg_short_over','total_swipe'
];

$pageTitle  = 'Recovery Summary Report';
$activePage = 'recovery_summary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

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

.g-income { background: #f0fdf4 !important; }
.g-deduct { background: #fff7f7 !important; }
.g-calc   { background: #eff6ff !important; }
.g-text   { background: #faf5ff !important; }

.col-num  { width: 100px; min-width: 100px; }
.col-txt  { width: 160px; min-width: 160px; }

.sr-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.sr-table tbody tr:hover td { filter: brightness(.97); }
.sr-table td { border: 1px solid #e8eaed; padding: 0; vertical-align: middle; }

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

.sr-table tfoot td {
  padding: 8px 6px; font-family: var(--font-m); font-size: .69rem; font-weight: 700;
  text-align: right; border: 1px solid #d1d5db;
  background: #f0f2f5; color: #1a1d23;
}
.sr-table tfoot td.tfl {
  text-align: center; color: var(--accent);
  font-size: .6rem; text-transform: uppercase; letter-spacing: .06em;
}

.sdot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-left: 4px; vertical-align: middle; }
.sdot.ok   { background: #22c55e; box-shadow: 0 0 4px #22c55e; }
.sdot.new  { background: #f59e0b; }
.sdot.auto { background: #3b82f6; box-shadow: 0 0 4px #3b82f6; }

.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

.sr-leg {
  display: flex; gap: 12px; flex-wrap: wrap;
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  margin-bottom: 10px; align-items: center;
}
.sr-leg span { display: flex; align-items: center; gap: 5px; }
.ld { display: inline-block; width: 9px; height: 9px; border-radius: 3px; }

.sr-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }

.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  text-align: center; padding: 6px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Recovery Spa <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily spreadsheet entry · each row has its own Save button · Save All saves every row</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 Recovery
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
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved &nbsp;<span class="sdot auto"></span> Auto-filled from Sales Report</span>
</div>

<!-- Spreadsheet -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <tr class="grp-row">
      <th class="th-date"></th>
      <!-- SALES / INCOME: POS, Cash | AUTO-CALC: Short/Over, Gross | SALES: Swipe -->
      <th colspan="2" class="g-income">SALES / INCOME</th>
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <th colspan="1" class="g-income">SALES / INCOME</th>
      <!-- UNPAID -->
      <th colspan="3" class="g-deduct">UNPAID</th>
      <!-- OTHER INCOME -->
      <th colspan="8" class="g-income">OTHER INCOME / COLLECTIONS</th>
      <!-- DEDUCTIONS -->
      <th colspan="8" class="g-deduct">DEDUCTIONS</th>
      <!-- AUTO-CALC TOTALS -->
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <th colspan="1" class="g-income">TOTAL SWIPE</th>
      <!-- NOTES -->
      <th colspan="1" class="g-text">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- SALES / INCOME -->
      <th class="g-income col-num">POS READING</th>
      <th class="g-income col-num">CASH FOR DEPO</th>
      <th class="g-income col-num">SHORT / OVER</th>
      <!-- AUTO-CALC -->
      <th class="g-calc col-num">GROSS SALES (EXCL MKTG)</th>
      <!-- SALES / INCOME cont. -->
      <th class="g-income col-num">SALES OF THE DAY (SWIPE)</th>
      <!-- UNPAID -->
      <th class="g-deduct col-num">UNPAID STAFF</th>
      <th class="g-deduct col-num">UNPAID CORPORATE</th>
      <th class="g-deduct col-num">UNPAID MAM NIKKI / SIR BUDOY / CORP</th>
      <!-- OTHER INCOME -->
      <th class="g-income col-num">MARKETING PULL-OUT</th>
      <th class="g-income col-num">REDEEMED GC / VOUCHER</th>
      <th class="g-income col-num">SOLD PRODUCT</th>
      <th class="g-income col-num">BPI BANK</th>
      <th class="g-income col-num">GCASH</th>
      <th class="g-income col-num">GC SOLD</th>
      <th class="g-income col-num">GC SPONSORSHIP</th>
      <th class="g-income col-num">BANK TRANSFER</th>
      <!-- DEDUCTIONS -->
      <th class="g-deduct col-num">DISC SNR / PWD (20%)</th>
      <th class="g-deduct col-num">REGULAR (STAFF / SPECIAL DISC)</th>
      <th class="g-deduct col-num">PERSONAL</th>
      <th class="g-deduct col-num">CASH ADVANCE</th>
      <th class="g-deduct col-num">PAYROLL</th>
      <th class="g-deduct col-num">COMMISSION FEE (STAFF)</th>
      <th class="g-deduct col-num">PCF EXPENSES</th>
      <th class="g-deduct col-num">OTHER EXPENSES</th>
      <!-- AUTO-CALC TOTALS -->
      <th class="g-calc col-num">TOTAL DEDUCTIONS</th>
      <th class="g-calc col-num">SHORT / OVER</th>
      <th class="g-income col-num">TOTAL SWIPE</th>
      <!-- REMARKS -->
      <th class="g-text col-txt">REMARKS</th>
      <th class="th-act">SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN   = (int)date('j', strtotime($ds));
    $dayNm  = date('D', strtotime($ds));
    $row    = $savedRows[$ds] ?? null;
    $saved  = $row !== null;
    $sr     = $salesRows[$ds] ?? null;
    $hasSR  = $sr !== null;          // Sales Report has data for this date
    $fromSR = $hasSR && !$saved;     // used only for the status dot
    $rid    = 'r' . str_replace('-', '', $ds);
    // Sales-Report-mapped fields ALWAYS mirror the Sales Report (source of truth),
    // even if this row was previously saved with stale/old values. Fields not in
    // SALES_MAP fall back to whatever was manually saved.
    $dv = function($k) use ($row, $sr, $SALES_MAP) {
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
    <!-- SALES / INCOME -->
    <?= $inp('pos_reading','g-income') ?>
    <?= $inp('cash_for_depo','g-income') ?>
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="short_over"           data-row="<?=$rid?>" value="<?=$dv('short_over')?>"           readonly tabindex="-1"></td>
    <!-- AUTO-CALC -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="gross_sales_excl_mktg" data-row="<?=$rid?>" value="<?=$dv('gross_sales_excl_mktg')?>" readonly tabindex="-1"></td>
    <!-- SALES / INCOME cont. -->
    <?= $inp('sales_of_day_swipe','g-income') ?>
    <!-- UNPAID -->
    <?= $inp('unpaid_staff','g-deduct') ?>
    <?= $inp('unpaid_corporate','g-deduct') ?>
    <?= $inp('unpaid_mam_nikki','g-deduct') ?>
    <!-- OTHER INCOME -->
    <?= $inp('marketing_pull_out','g-income') ?>
    <?= $inp('redeemed_gc_voucher','g-income') ?>
    <?= $inp('sold_product','g-income') ?>
    <?= $inp('bpi_bank','g-income') ?>
    <?= $inp('gcash','g-income') ?>
    <?= $inp('gc_sold','g-income') ?>
    <?= $inp('gc_sponsorship','g-income') ?>
    <?= $inp('bank_transfer','g-income') ?>
    <!-- DEDUCTIONS -->
    <?= $inp('discounted_snr_pwd','g-deduct') ?>
    <?= $inp('regular_staff_disc','g-deduct') ?>
    <?= $inp('personal','g-deduct') ?>
    <?= $inp('cash_advance','g-deduct') ?>
    <?= $inp('payroll','g-deduct') ?>
    <?= $inp('commission_fee_staff','g-deduct') ?>
    <?= $inp('pcf_expenses','g-deduct') ?>
    <?= $inp('other_expenses','g-deduct') ?>
    <!-- AUTO-CALC TOTALS -->
    <td class="g-calc"><input type="number" step="0.01" class="sri calc" data-col="total_deductions"  data-row="<?=$rid?>" value="<?=$dv('total_deductions')?>"     readonly tabindex="-1"></td>
    <td class="g-calc"><input type="number" step="0.01" class="sri calc neg" data-col="acctg_short_over" data-row="<?=$rid?>" value="<?=$dv('acctg_short_over')?>"  readonly tabindex="-1"></td>
    <?= $inp('total_swipe','g-income') ?>
    <!-- REMARKS -->
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
      <td></td><!-- remarks -->
      <td></td><!-- save -->
    </tr>
  </tfoot>
</table>
</div>

<!-- Bottom save all -->
<div style="display:flex;justify-content:flex-end;margin-top:12px">
  <button class="btn btn-primary" onclick="saveAll()">💾 Save All Rows</button>
</div>

<!-- ── TARGET SALES SUMMARY PANEL ── -->
<div class="card" style="margin-top:20px;border-top:2px solid var(--accent3);overflow:hidden">
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">Recovery — Monthly Performance</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Target Sales</div>
      <input id="recoveryQuotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.','') : '' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcRecoverySummary()"
        onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
        onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';saveRecoveryQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input target amount</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage</div>
      <div id="recoverySalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Gross Sales Excl Mktg ÷ Target</div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="recoveryNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Target − Total Gross Sales</div>
    </div>

    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Quota</div>
      <div id="recoveryDailyQuota" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
        <span style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);white-space:nowrap">÷ days:</span>
        <input id="recoveryDaysInput" type="number" min="1" step="1" value="1"
          style="width:60px;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#1a1d23;font-family:var(--font-m);font-size:.82rem;font-weight:700;padding:4px 8px;outline:none;transition:border-color .15s"
          oninput="recalcRecoverySummary()"
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

function gv(rid, col) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  return e ? (parseFloat(e.value) || 0) : 0;
}

function recalc(rid) {
  const autoCalcCols = ['short_over','gross_sales_excl_mktg','total_deductions','acctg_short_over'];
  const hasInput = COLS.some(function(c) {
    return !autoCalcCols.includes(c) ? (gv(rid, c) !== 0) : false;
  });

  const posReading  = gv(rid, 'pos_reading');
  const cashForDepo = gv(rid, 'cash_for_depo');
  const mktgPullOut = gv(rid, 'marketing_pull_out');

  // SHORT / OVER (1st) = POS Reading − Cash for Depo
  const shortOver = posReading - cashForDepo;
  const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
  if (soEl) {
    soEl.value = hasInput ? shortOver.toFixed(2) : '';
    soEl.classList.toggle('neg', hasInput && shortOver < 0);
  }

  // GROSS SALES EXCL MKTG = POS Reading − Marketing Pull-Out
  const grossSales = posReading - mktgPullOut;
  const gsEl = document.querySelector(`#${rid} [data-col="gross_sales_excl_mktg"]`);
  if (gsEl) gsEl.value = hasInput ? grossSales.toFixed(2) : '';

  // TOTAL DEDUCTIONS = SUM(F4:V4) + X4 + Y4
  // F=swipe, G=unpaid_staff, H=unpaid_corporate, I=unpaid_mam_nikki,
  // J=marketing_pull_out, K=redeemed_gc_voucher, L=sold_product,
  // M=bpi_bank, N=gcash, O=gc_sold, P=gc_sponsorship, Q=bank_transfer,
  // R=discounted_snr_pwd, S=regular_staff_disc, T=personal,
  // U=cash_advance, V=payroll  [W=commission_fee_staff is skipped]
  // X=pcf_expenses, Y=other_expenses
  const td = gv(rid,'sales_of_day_swipe')        // F
           + gv(rid,'unpaid_staff')               // G
           + gv(rid,'unpaid_corporate')           // H
           + gv(rid,'unpaid_mam_nikki')           // I
           + gv(rid,'marketing_pull_out')         // J
           + gv(rid,'redeemed_gc_voucher')        // K
           + gv(rid,'sold_product')               // L
           + gv(rid,'bpi_bank')                   // M
           + gv(rid,'gcash')                      // N
           + gv(rid,'gc_sold')                    // O
           + gv(rid,'gc_sponsorship')             // P
           + gv(rid,'bank_transfer')              // Q
           + gv(rid,'discounted_snr_pwd')         // R
           + gv(rid,'regular_staff_disc')         // S
           + gv(rid,'personal')                   // T
           + gv(rid,'cash_advance')               // U
           + gv(rid,'payroll')                    // V
           // commission_fee_staff (W) is NOT in the Excel formula
           + gv(rid,'pcf_expenses')               // X
           + gv(rid,'other_expenses');            // Y
  const tdEl = document.querySelector(`#${rid} [data-col="total_deductions"]`);
  if (tdEl) tdEl.value = hasInput ? td.toFixed(2) : '';

  // ACCOUNTING SHORT / OVER (2nd) = Cash for Depo − (POS Reading − Total Deductions)
  // i.e. Cash for Depo − Net Expected Cash, same logic as the Sales Report's own
  // Short/Over (COH − Net Cash). Previous formula (Total Deductions − Cash for Depo)
  // ignored POS Reading entirely, so a day with no card/gcash/etc breakdown entered
  // couldn't ever surface a real cash shortage — fixed to always reconcile with the
  // Sales Report's number.
  const aso = cashForDepo - (posReading - td);
  const asoEl = document.querySelector(`#${rid} [data-col="acctg_short_over"]`);
  if (asoEl) {
    asoEl.value = hasInput ? aso.toFixed(2) : '';
    asoEl.classList.toggle('neg', hasInput && aso < 0);
  }

  // TOTAL SWIPE is manually entered by the user
}

function recalcTotals() {
  COLS.forEach(function(col) {
    const el = document.getElementById('tot_' + col);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`[data-col="${col}"]`).forEach(function(i) {
      s += parseFloat(i.value) || 0;
    });
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    if (col === 'acctg_short_over') el.style.color = s < 0 ? 'var(--accent2)' : 'var(--accent)';
  });
  recalcRecoverySummary();
}

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
    const res  = await fetch('recovery_summary_report.php', {method:'POST', body:fd});
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

async function saveAll() {
  const rows = [...document.querySelectorAll('#srt tbody tr')];
  for (const row of rows) {
    await saveRow(row.id, row.dataset.date);
    await new Promise(r => setTimeout(r, 50));
  }
  showToast('✓ All ' + rows.length + ' rows saved for Recovery', 'success');
}

function downloadCSV(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv', '1');
  const monthEl = document.querySelector('select[name="month"]');
  if (monthEl) params.set('month', monthEl.value);
  const yearEl = document.querySelector('select[name="year"]');
  if (yearEl) params.set('year', yearEl.value);
  window.location.href = 'recovery_summary_report.php?' + params.toString();
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(function(){ t.remove(); }, 4000);
}

document.addEventListener('keydown', function(e) {
  if (!e.target.classList.contains('sri') || e.key !== 'Enter') return;
  e.preventDefault();
  const col = e.target.dataset.col, rid = e.target.dataset.row;
  const next = document.getElementById(rid)?.nextElementSibling;
  if (next) { const ni = next.querySelector(`[data-col="${col}"]`); if(ni){ni.focus();ni.select();} }
});

function recalcRecoverySummary() {
  const quota = parseFloat(document.getElementById('recoveryQuotaInput')?.value) || 0;
  const days  = parseInt(document.getElementById('recoveryDaysInput')?.value)   || 1;

  let totalGross = 0;
  document.querySelectorAll('#srt tbody [data-col="gross_sales_excl_mktg"]').forEach(function(el) {
    totalGross += parseFloat(el.value) || 0;
  });

  const pctEl    = document.getElementById('recoverySalesPct');
  const neededEl = document.getElementById('recoveryNeeded');
  const dailyEl  = document.getElementById('recoveryDailyQuota');

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

async function saveRecoveryQuota() {
  const val = document.getElementById('recoveryQuotaInput')?.value;
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
    await fetch('recovery_summary_report.php', {method:'POST', body:fd});
  } catch(e) { console.error('Quota save failed', e); }
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#srt tbody tr').forEach(function(r){ recalc(r.id); });
  recalcTotals();
  recalcRecoverySummary();
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>