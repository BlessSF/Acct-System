<?php
// ============================================================
//  summary_report.php — Spreadsheet-style daily summary entry
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$pdo  = getPDO();
$user = currentUser();

// Stella's "MARKETING PULL-OUT" column (sits beside Booky, same as the Excel) is stored with the day's row
try {
    if (!$pdo->query("SHOW COLUMNS FROM `summary_report_entries` LIKE 'mkt_pullout_total'")->fetch()) {
        $pdo->exec("ALTER TABLE `summary_report_entries` ADD COLUMN `mkt_pullout_total` decimal(12,2) NOT NULL DEFAULT 0.00");
    }
} catch (Throwable $ignored) {}

// ── Handle AJAX save (per-row) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $store      = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';

    if (!$store || !$reportDate) {
        echo json_encode(['ok' => false, 'msg' => 'Missing store or date.']);
        exit;
    }

    $numCols = [
        'tips_gift_cert','booky_fees_income','mkt_pullout_total','store_gross','total_sales',
        'cash_for_depo','sales_of_day_swipe','deposit_swipe','late_payment',
        'cancelled_transaction','unpaid','paid','advance_payment',
        'grab','bank_trans','gcash','gc_sponsor_marketing',
        'gc_sold','discount','marketing_pull_out','personal','pcf',
        'other_expenses','sc_for_depo','total_deductions','short_over',
        'total_swipe','cash_deposit','other_sales'
    ];
    $txtCols = ['remarks','remarks2'];

    $data = ['store_name' => $store, 'report_date' => $reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($txtCols as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql  = "INSERT INTO summary_report_entries ("
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
    $store  = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? '');
    $month  = trim($_POST['report_month'] ?? '');
    $quota  = (float)($_POST['quota'] ?? 0);
    if (!$store || !$month) { echo json_encode(['ok'=>false,'msg'=>'Missing data']); exit; }
    try {
        $pdo = getPDO();
        // Auto-add column if DB hasn't been migrated yet
        try {
            $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00");
        } catch(Throwable $ignored) {}
        // ONE row per store + month. The table has no unique key, so every save used to add
        // ANOTHER row. Update the existing row in place and remove any leftover copies.
        $mDate = $month.'-01';
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM summary_reports WHERE store_name=? AND report_month=?");
        $cnt->execute([$store, $mDate]);
        $have = (int)$cnt->fetchColumn();
        if ($have > 0) {
            $pdo->prepare("UPDATE summary_reports SET quota_target=?, created_by=? WHERE store_name=? AND report_month=?")
                ->execute([$quota, currentUser()['name'], $store, $mDate]);
            if ($have > 1) {
                $pdo->exec("DELETE FROM summary_reports WHERE store_name=" . $pdo->quote($store) . " AND report_month=" . $pdo->quote($mDate) . " LIMIT " . ($have - 1));
            }
        } else {
            $pdo->prepare("INSERT INTO summary_reports (report_month,store_name,quota_target,created_by) VALUES (?,?,?,?)")
                ->execute([$mDate, $store, $quota, currentUser()['name']]);
        }
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle CSV download ───────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $csvStore = isBranch() ? currentBranch() : ($_GET['store'] ?? 'Stella');
    $csvMonthNames = ['','January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    $csvCols = [
        'report_date'           => 'Date',
        'tips_gift_cert'        => 'Tips Gift Cert (Deposit)',
        'booky_fees_income'     => 'Booky Fees Income',
        'mkt_pullout_total'     => 'Marketing Pull-Out',
        'store_gross'           => 'Store Gross',
        'total_sales'           => 'Total Sales',
        'cash_for_depo'         => 'Cash for Depo',
        'sales_of_day_swipe'    => 'Sales of the Day (Swipe)',
        'deposit_swipe'         => 'Deposit Swipe',
        'late_payment'          => 'Late Payment',
        'cancelled_transaction' => 'Cancelled Transaction',
        'unpaid'                => 'Unpaid',
        'paid'                  => 'Paid',
        'advance_payment'       => 'Advance Payment',
        'grab'                  => 'Grab',
        'gcash'                 => 'GCash',
        'bank_trans'            => 'Bank Trans',
        'gc_sponsor_marketing'  => 'GC Sponsor / Marketing',
        'gc_sold'               => 'GC Sold',
        'discount'              => 'Discount',
        'marketing_pull_out'    => 'Marketing Pull Out',
        'personal'              => 'Personal',
        'pcf'                   => 'PCF',
        'other_expenses'        => 'Other Expenses',
        'sc_for_depo'           => 'SC for Depo',
        'total_deductions'      => 'Total Deductions',
        'short_over'            => 'Short / Over',
        'total_swipe'           => 'Total Swipe',
        'cash_deposit'          => 'Cash Deposit',
        'remarks'               => 'Remarks',
        'other_sales'           => 'Other Sales',
        'remarks2'              => 'Remarks (2)',
    ];

    // Fetch all days of the month
    $daysInCsvMonth = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $csvDays = [];
    for ($d = 1; $d <= $daysInCsvMonth; $d++) {
        $csvDays[] = sprintf('%04d-%02d-%02d', $csvYear, $csvMonth, $d);
    }

    // Fetch saved rows
    $csvStmt = getPDO()->prepare("SELECT * FROM summary_report_entries WHERE store_name=? AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $csvStmt->execute([$csvStore, $csvYear, $csvMonth]);
    $csvRows = [];
    foreach ($csvStmt->fetchAll() as $r) $csvRows[$r['report_date']] = $r;

    $filename = $csvStore . '_' . $csvMonthNames[$csvMonth] . '_' . $csvYear . '_SummaryReport.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');

    // Title rows
    fputcsv($out, [$csvStore . ' — Summary Report']);
    fputcsv($out, [$csvMonthNames[$csvMonth] . ' ' . $csvYear]);
    fputcsv($out, []);

    if ($csvStore !== 'Stella') unset($csvCols['mkt_pullout_total']);
    // Header row
    fputcsv($out, array_values($csvCols));

    // Data rows — one per day
    $totals = array_fill_keys(array_keys($csvCols), 0);
    foreach ($csvDays as $ds) {
        $row = $csvRows[$ds] ?? null;
        $line = [];
        foreach (array_keys($csvCols) as $key) {
            if ($key === 'report_date') {
                $line[] = date('d-M-Y (D)', strtotime($ds));
            } else {
                $val = $row[$key] ?? 0;
                $line[] = (float)$val != 0 ? number_format((float)$val, 2, '.', '') : '';
                if ($key !== 'remarks' && $key !== 'remarks2') {
                    $totals[$key] += (float)$val;
                }
            }
        }
        fputcsv($out, $line);
    }

    // Totals row
    $totalLine = ['TOTAL'];
    foreach (array_keys($csvCols) as $key) {
        if ($key === 'report_date') continue;
        if ($key === 'remarks' || $key === 'remarks2') { $totalLine[] = ''; continue; }
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

$allStores = ['Stella','Dois','H','Pub Express','Commissary','Recovery','DemicLab'];
$fStore    = isBranch() ? currentBranch() : ($_GET['store'] ?? $allStores[0]);

// ── Days of the selected month ────────────────────────────
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
$allDays = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $allDays[] = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $d);
}

// ── Load saved rows ───────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM summary_report_entries
    WHERE store_name = ? AND YEAR(report_date) = ? AND MONTH(report_date) = ?
");
$stmt->execute([$fStore, $fYear, $fMonth]);
$savedRows = [];
foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;

// ── Pull fallback data from the Stella Sales Report for dates that      ──
// don't have a manually-saved summary row yet. Mirrors the same pattern
// dois/dois_summary_report.php uses against dois_sales_report — Stella-only,
// since the other branches (H, Pub Express, Commissary, Recovery,
// DemicLab) don't have an equivalent per-store sales report table yet.
$salesRows = [];
if ($fStore === 'Stella') {
    try {
        $ssStmt = $pdo->prepare("SELECT * FROM stella_sales_report WHERE store_name='Stella' AND YEAR(report_date)=? AND MONTH(report_date)=?");
        $ssStmt->execute([$fYear, $fMonth]);
        foreach ($ssStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $salesRows[$r['report_date']] = $r;

        // Cancelled Transaction isn't a column on the main sales-report row —
        // it's summed from the DINE IN detail rows, same as Dois does for
        // its cash/cancelled totals.
        $diStmt = $pdo->prepare("SELECT report_date, COALESCE(SUM(cancelled_transactions),0) AS cancelled_total
            FROM stella_dinein_rows WHERE store_name='Stella' AND YEAR(report_date)=? AND MONTH(report_date)=?
            GROUP BY report_date");
        $diStmt->execute([$fYear, $fMonth]);
        foreach ($diStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $salesRows[$r['report_date']]['_cancelled_total'] = (float)$r['cancelled_total'];
        }
    } catch (Throwable $ignored) {}
    // TOTAL SALES in the Excel = the POS Z-reading (Gross Sales + Marketing Pull Out).
    // Older Sales Report rows may not have the Z-reading saved, so rebuild it when it's empty.
    foreach ($salesRows as $d => $srr) {
        $z = (float)($srr['pos_z_reading'] ?? 0);
        if ($z == 0) $z = (float)($srr['gross_sales'] ?? 0) + (float)($srr['marketing_pull_out'] ?? 0);
        $salesRows[$d]['_pos_z'] = $z;
    }
}

// Map: Summary Report column => Stella Sales Report source column (or a
// computed key added above). Only fields with a clear 1:1 source are
// mirrored — Booky Fees Income, GC Sponsor/Marketing, Cash Deposit, and
// the always-computed totals (Total Inclusive SC, Store Gross, Total
// Deductions) have no Sales Report equivalent and stay manual entries,
// same as before.
$SALES_MAP = [
    'total_sales'           => '_pos_z',              // Excel TOTAL SALES = POS Z-reading
    'sc_for_depo'            => 'service_charge',
    'deposit_swipe'          => 'deposit_swipe_card',
    'late_payment'           => 'late_payment_card',
    'sales_of_day_swipe'     => 'maya_swipe',
    'unpaid'                 => 'unpaid_med_credit',
    'paid'                   => 'paid_med_card',
    'advance_payment'        => 'advance_paid_appigo',
    'grab'                   => 'grab_sales',
    'gcash'                  => 'gcash',
    'gc_sold'                => 'gift_card',
    'tips_gift_cert'         => 'gift_cert_sold_tips',   // Tips Gift Cert (Deposit)
    'booky_fees_income'      => 'cash_out_interest',     // Booky (Cash out Interest) -> Booky Fees Income
    'marketing_pull_out'     => 'marketing_pull_out',
    'discount'               => 'discount',
    'bank_trans'             => 'bank_transfer_cheque',
    'pcf'                    => 'pcf_expenses',
    'other_expenses'         => 'other_expenses',
    'cash_for_depo'          => 'coh',
    'cancelled_transaction'  => '_cancelled_total',
];
// Total Swipe and Short/Over are NOT copied from the Sales Report any more — they are worked out
// here with the Excel formulas (see recalc() below).

// Fields that stay editable/never lock even when the Sales Report has data
// for the date — they get the Sales Report figure as a starting default,
// but a manually-saved value always takes priority.
$NEVER_LOCK = ['cancelled_transaction'];
// Tips and Booky follow the Sales Report whenever it has an amount for the day; on days where the
// Sales Report has nothing for them they stay typeable (like the Excel) instead of being locked at 0.
$PARTIAL_LOCK = ['tips_gift_cert','booky_fees_income'];

// Live sync: the page asks for the Sales Report figures every few seconds so edits made there
// show up here without pressing anything.
if (isset($_GET['ajax_sync'])) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $out = [];
    foreach ($salesRows as $d => $srr) {
        $o = [];
        foreach ($SALES_MAP as $k => $src) $o[$k] = round((float)($srr[$src] ?? 0), 2);
        $out[$d] = $o;
    }
    echo json_encode(['ok' => true, 'rows' => $out]);
    exit;
}

// ── Load saved quota for this store/month ────────────────
try {
    $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00");
} catch(Throwable $ignored) {}
try {
    // remove old duplicate copies for this store/month (keep the newest one)
    try {
        $pdo->prepare("DELETE a FROM summary_reports a JOIN summary_reports b
            ON a.store_name=b.store_name AND a.report_month=b.report_month AND a.id<b.id
            WHERE a.store_name=? AND YEAR(a.report_month)=? AND MONTH(a.report_month)=?")->execute([$fStore, $fYear, $fMonth]);
    } catch(Throwable $ignored) {}
    $quotaRow = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name=? AND YEAR(report_month)=? AND MONTH(report_month)=? ORDER BY id DESC LIMIT 1");
    $quotaRow->execute([$fStore, $fYear, $fMonth]);
    $savedQuota = (float)($quotaRow->fetchColumn() ?: 0);
} catch(Throwable $e) { $savedQuota = 0; }

// ── Column definitions ────────────────────────────────────
// [key, label, group, computed]
// group: 'income' | 'deduct' | 'calc' | 'text'
$COLS = [
    ['total_inclusive_sc',    'TOTAL INCLUSICE SC',       'calc',   true ],
    ['tips_gift_cert',        'TIPS GIFT CERT (DEPOSIT)',  'income', false],
    ['booky_fees_income',     'BOOKY FEES INCOME',         'income', false],
    ['mkt_pullout_total',     'MARKETING PULL-OUT',        'calc',   true ],
    ['store_gross',           'STORE GROSS',               'calc',   true ],
    ['total_sales',           'TOTAL SALES',               'income', false],
    ['cash_for_depo',         'CASH FOR DEPO',             'income', false],
    ['sales_of_day_swipe',    'SALES OF THE DAY (SWIPE)',  'income', false],
    ['deposit_swipe',         'DEPOSIT SWIPE',             'income', false],
    ['late_payment',          'LATE PAYMENT',              'income', false],
    ['cancelled_transaction', 'CANCELLED TRANSACTION',     'deduct', false],
    ['unpaid',                'UNPAID',                    'deduct', false],
    ['paid',                  'PAID',                      'income', false],
    ['advance_payment',       'ADVANCE PAYMENT',           'income', false],
    ['grab',                  'GRAB',                      'income', false],
    ['gcash',                 'GCASH',                     'income', false],
    ['bank_trans',            'BANK TRANS',                'income', false],
    ['gc_sponsor_marketing',  'GC SPONSOR / MARKETING',    'income', false],
    ['gc_sold',               'GC SOLD',                   'deduct', false],
    ['discount',              'DISCOUNT',                  'deduct', false],
    ['marketing_pull_out',    'MARKETING PULL OUT',        'deduct', false],
    ['personal',              'PERSONAL',                  'deduct', false],
    ['pcf',                   'PCF',                       'deduct', false],
    ['other_expenses',        'OTHER EXPENSES',            'deduct', false],
    ['sc_for_depo',           'SC FOR DEPO',               'deduct', false],
    ['total_deductions',      'TOTAL DEDUCTIONS',          'calc',   true ],
    ['short_over',            'SHORT / OVER',              'calc',   true ],
    ['total_swipe',           'TOTAL SWIPE',               'calc',   true ],
    ['cash_deposit',          'CASH DEPOSIT',              'deduct', false],
    ['remarks',               'REMARKS',                   'text',   false],
    ['other_sales',           'OTHER SALES',               'income', false],
    ['remarks2',              'REMARKS (2)',                'text',   false],
];

// the Marketing Pull-Out column (Excel column E) is a Stella layout item
if ($fStore !== 'Stella') $COLS = array_values(array_filter($COLS, fn($c) => $c[0] !== 'mkt_pullout_total'));

$pageTitle  = $fStore . ' Summary Report';
$activePage = 'summary';
include 'layout.php';
?>

<style>
/* ── Page content ── */
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* ── Outer scroll container ── */
.sr-outer {
  width: 100%; max-width: 100%;
  overflow-x: auto; overflow-y: visible;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--surface);
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: #c1c7d0 #f1f3f5;
  display: block;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.sr-outer::-webkit-scrollbar { height: 8px; }
.sr-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.sr-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }
.sr-outer::-webkit-scrollbar-thumb:hover { background: #9aa3af; }

/* ── Table ── */
.sr-table { border-collapse: collapse; width: max-content; font-size: .69rem; table-layout: fixed; }

/* ── Header ── */
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

/* ── Sticky columns ── */
.th-date {
  position: sticky !important; left: 0; z-index: 30 !important;
  width: 88px; min-width: 88px;
  background: #ebedf0 !important; color: var(--accent) !important;
  box-shadow: 2px 0 5px rgba(0,0,0,.07);
}
.th-act {
  position: sticky !important; right: 0; z-index: 30 !important;
  width: 66px; min-width: 66px;
  background: #ebedf0 !important;
  box-shadow: -2px 0 5px rgba(0,0,0,.07);
}
.td-date {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb !important;
  box-shadow: 2px 0 5px rgba(0,0,0,.06);
  font-family: var(--font-m); font-size: .69rem;
  color: var(--accent); font-weight: 600;
  text-align: center; padding: 0 6px;
  white-space: nowrap; width: 88px; min-width: 88px;
}
.td-act {
  position: sticky; right: 0; z-index: 5;
  background: #f8f9fb !important;
  box-shadow: -2px 0 5px rgba(0,0,0,.06);
  text-align: center; padding: 3px 5px;
  width: 66px; min-width: 66px;
}

/* ── Column group colours ── */
.g-income { background: #f0fdf4 !important; }
.g-deduct { background: #fff7f7 !important; }
.g-calc   { background: #eff6ff !important; }
.g-text   { background: #faf5ff !important; }

/* ── Column widths ── */
.col-num  { width: 90px; min-width: 90px; }
.col-text { width: 140px; min-width: 140px; }

/* ── Body rows ── */
.sr-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.sr-table tbody tr:hover td { filter: brightness(.97); }
.sr-table td { border: 1px solid #e8eaed; padding: 0; vertical-align: middle; }

/* ── Inputs ── */
.sri {
  width: 100%; padding: 6px 6px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-m); font-size: .69rem;
  text-align: right; transition: background .12s;
  display: block; box-sizing: border-box;
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

/* ── Controls row ── */
.sr-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }

/* ── Scroll hint ── */
.scroll-hint {
  font-family: var(--font-m); font-size: .62rem;
  color: var(--subtext); text-align: center;
  padding: 6px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23"><?= htmlspecialchars($fStore) ?> <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Auto-saves as you type (empty rows are never saved) · linked cells update by themselves from the Sales Report</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <?php if (isManagement()): ?>
  <select name="store" class="form-control" style="max-width:145px" onchange="this.form.submit()">
    <?php foreach ($allStores as $st): ?>
    <option value="<?= htmlspecialchars($st) ?>" <?= $fStore===$st?'selected':'' ?>><?= htmlspecialchars($st) ?></option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 <?= htmlspecialchars($fStore) ?>
  </div>
  <input type="hidden" name="store" value="<?= htmlspecialchars($fStore) ?>">
  <?php endif; ?>

  <select name="month" class="form-control" style="max-width:130px" onchange="this.form.submit()">
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$monthNames[$m]?></option>
    <?php endfor; ?>
  </select>
  <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
    <?php for($y=2050;$y>=2023;$y--): ?>
    <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <a id="csvDownloadBtn" href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="downloadCSV(event)">⬇ Download CSV</a>
</form>

<!-- Legend -->
<div class="sr-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income</span>
  <span><span class="ld" style="background:#fecaca;border:1px solid #fca5a5"></span>Deductions</span>
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Auto-calculated</span>
  <span><span class="ld" style="background:#e9d5ff;border:1px solid #d8b4fe"></span>Remarks</span>
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved</span>
</div>

<!-- Spreadsheet -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <!-- Group label row -->
    <tr class="grp-row">
      <th class="th-date"></th>
      <?php
      $grpMap = ['income'=>'INCOME / COLLECTIONS','deduct'=>'DEDUCTIONS','calc'=>'AUTO-CALCULATED','text'=>'NOTES'];
      $prev = null; $span = 0; $groups = [];
      foreach($COLS as $c){ if($c[2]!==$prev){ if($prev) $groups[]=[$prev,$span]; $prev=$c[2]; $span=1; } else $span++; }
      if($prev) $groups[]=[$prev,$span];
      foreach($groups as [$g,$s]): ?>
      <th colspan="<?=$s?>" class="g-<?=$g?>"><?=$grpMap[$g]?></th>
      <?php endforeach; ?>
      <th class="th-act"></th>
    </tr>
    <!-- Column name row -->
    <tr>
      <th class="th-date">DATE</th>
      <?php foreach($COLS as $c): ?>
      <th class="g-<?=$c[2]?> <?=$c[2]==='text'?'col-text':'col-num'?>"><?=htmlspecialchars($c[1])?></th>
      <?php endforeach; ?>
      <th class="th-act">AUTO-SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach($allDays as $ds):
    $dayN  = (int)date('j',strtotime($ds));
    $dayNm = date('D',strtotime($ds));
    $row   = $savedRows[$ds] ?? null;
    $saved = $row !== null;
    $sr    = $salesRows[$ds] ?? null;
    $hasSR = $sr !== null;          // Stella Sales Report has data for this date
    $fromSR= $hasSR && !$saved;     // used only for the status dot
    $rid   = 'r'.str_replace('-','',$ds);
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>" data-fromsr="<?=$hasSR?1:0?>"<?php if ($saved): ?> data-db='<?= htmlspecialchars(json_encode(array_map(fn($k) => round((float)($row[$k] ?? 0), 2), ['total_sales','tips_gift_cert','booky_fees_income','mkt_pullout_total','store_gross','total_deductions','short_over','total_swipe'])), ENT_QUOTES) ?>' data-dbk='total_sales,tips_gift_cert,booky_fees_income,mkt_pullout_total,store_gross,total_deductions,short_over,total_swipe'<?php endif; ?>>
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':($fromSR?'auto':'new')?>" id="dot_<?=$rid?>" title="<?=$fromSR?'Auto-filled from Stella Sales Report — click Save to lock in':''?>"></span>
    </td>

    <?php foreach($COLS as $c):
      [$key,$lbl,$grp,$comp] = $c;
      $isText = $grp==='text';
      // Sales-Report-mapped fields mirror the Stella Sales Report (source of
      // truth) whenever it has data for the date, same as Dois. NEVER_LOCK
      // fields (Cancelled Transaction) stay editable, using the Sales Report
      // figure only as a starting default when nothing's been saved yet.
      $isSR = $hasSR && isset($SALES_MAP[$key]) && !in_array($key, $NEVER_LOCK, true);
      if ($isSR && in_array($key, $PARTIAL_LOCK, true) && (float)($sr[$SALES_MAP[$key]] ?? 0) == 0) $isSR = false;
      if ($isSR) {
          $raw = $sr[$SALES_MAP[$key]] ?? '';
      } else {
          $raw = $row[$key] ?? '';
          if ($raw === '' && $hasSR && isset($SALES_MAP[$key]) && in_array($key, $NEVER_LOCK, true)) {
              $raw = $sr[$SALES_MAP[$key]] ?? '';
          }
      }
      if(!$isText && $raw!=='' && (float)$raw==0) $raw='';
      elseif(!$isText && $raw!=='') $raw=number_format((float)$raw,2,'.',',');
      $ro = $comp || $isSR;
    ?>
    <td class="g-<?=$grp?> <?=$isText?'col-text':'col-num'?>">
      <input
        type="text"<?=$isText?'':' inputmode="decimal" autocomplete="off" data-numfmt="1"'?>
        class="sri <?=$ro?'calc':''?> <?=$isText?'txt':''?>"
        data-col="<?=$key?>" data-row="<?=$rid?>"
        value="<?=htmlspecialchars((string)$raw)?>"
        placeholder="<?=$isText?'':'0.00'?>"
        <?=$ro?'readonly tabindex="-1"':''?>
        <?=!$isText?"oninput=\"changed(this)\" onblur=\"fmtBlur(this)\"":""?>>
    </td>
    <?php endforeach; ?>

    <td class="td-act">
      <span class="asv" id="btn_<?=$rid?>"><?=$saved?'Saved':'—'?></span>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <?php foreach($COLS as $c): ?>
      <?php if($c[2]==='text'): ?><td>—</td>
      <?php else: ?><td id="tot_<?=$c[0]?>">—</td><?php endif; ?>
      <?php endforeach; ?>
      <td class="tfl"></td>
    </tr>
  </tfoot>
</table>
</div><!-- /sr-outer -->


<!-- ── Monthly Summary Panel — Stella only ── -->
<?php if ($fStore === 'Stella'): ?>
<div class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div style="background:#f4f5f7;padding:10px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
    <div style="font-family:var(--font-m);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--accent)">
      📊 Monthly Summary — <?= htmlspecialchars($monthNames[$fMonth].' '.$fYear) ?>
    </div>
    <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext)"><?= htmlspecialchars($fStore) ?></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <!-- MONTHLY SALES QUOTA (inputable) -->
    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Monthly Sales Quota</div>
      <div style="display:flex;align-items:center;gap:6px">
        <input id="quotaInput" type="text" inputmode="decimal" autocomplete="off" data-numfmt="1"
          value="<?= $savedQuota > 0 ? number_format($savedQuota,2,'.',',') : '' ?>"
          placeholder="0.00"
          style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.9rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
          oninput="commaLive(this);recalcSummary();queueQuotaSave()"
          onfocus="this.style.borderColor='#0f7b5c';this.style.boxShadow='0 0 0 3px rgba(15,123,92,.1)'"
          onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none';fmtBlur(this);saveQuota()">
      </div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px" id="quotaHint">Input target amount · saves automatically</div>
    </div>

    <!-- SALES PERCENTAGE -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Sales Percentage (%)</div>
      <div id="summSalesPct" style="font-size:1.4rem;font-weight:800;color:#2563eb;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Total Store Gross ÷ Quota</div>
    </div>

    <!-- NEEDED SALES TO REACH TARGET -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Needed Sales to Reach Target</div>
      <div id="summNeeded" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Quota − Total Store Gross</div>
    </div>

    <!-- DAILY SALES TARGET -->
    <div style="padding:16px 20px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Sales Target</div>
      <div id="summDailyTarget" style="font-size:1.25rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Needed ÷ 5 working days</div>
    </div>

  </div>
</div>
<?php endif; ?>

  </div></div>

<script>
const STORE = <?=json_encode($fStore)?>;
const COLS  = <?=json_encode(array_map(fn($c)=>['key'=>$c[0],'grp'=>$c[2],'comp'=>$c[3]],$COLS))?>;

// ── number helpers: thousands separators (1,234.56) ───────
const numOf = v => { const x = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(x) ? x : 0; };
const fmt2  = n => Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
// live commas while typing (cursor stays where it was)
function commaLive(el) {
  const v = el.value, pos = el.selectionStart == null ? v.length : el.selectionStart;
  const before = v.slice(0, pos).replace(/[^0-9.\-]/g, '').length;
  let c = v.replace(/[^0-9.\-]/g, ''); const neg = c.charAt(0) === '-'; c = c.replace(/-/g, '');
  const parts = c.split('.'); let ip = parts[0].replace(/^0+(?=\d)/, '');
  const dec = parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, 2) : '';
  ip = ip.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const out = (neg ? '-' : '') + ip + dec;
  if (out !== v) { el.value = out; let n = 0, i = 0; for (; i < out.length && n < before; i++) if (/[0-9.\-]/.test(out[i])) n++; try { el.setSelectionRange(i, i); } catch (e) {} }
}
// on leaving a cell show it as 1,234.00 (empty stays empty)
function fmtBlur(el) { if (el.readOnly) return; el.value = el.value.trim() === '' ? '' : fmt2(numOf(el.value)); }

// ── Mark dirty + auto-save (debounced per row) ───────────
const saveTimers = {};
const AUTOCOLS = ['total_inclusive_sc','mkt_pullout_total','store_gross','total_deductions','short_over','total_swipe'];
function rowHasInput(rid) {
  const tr = document.getElementById(rid); if (!tr) return false;
  return [...tr.querySelectorAll('input[data-col]')].some(function(el){
    const c = el.dataset.col;
    if (AUTOCOLS.indexOf(c) >= 0) return false;
    if (c === 'remarks' || c === 'remarks2') return el.value.trim() !== '';
    return numOf(el.value) !== 0;
  });
}
function setStat(rid, cls, txt) {
  const b = document.getElementById('btn_'+rid);
  if (b) { b.className = 'asv ' + cls; b.textContent = txt; }
}
function changed(el) {
  if (el.dataset.numfmt === '1' && !el.readOnly) commaLive(el);
  const rid = el.dataset.row;
  recalc(rid);
  recalcTotals();
  const row = document.getElementById(rid);
  const wasSaved = row.dataset.saved === '1' || row.dataset.wasSaved === '1';
  if (wasSaved) row.dataset.wasSaved = '1';
  if (!rowHasInput(rid) && !wasSaved) {          // nothing entered → nothing to save
    clearTimeout(saveTimers[rid]); setStat(rid, '', '—'); return;
  }
  row.dataset.saved = '0';
  document.getElementById('dot_'+rid).className = 'sdot new';
  setStat(rid, 'saving', 'Typing…');
  clearTimeout(saveTimers[rid]);
  saveTimers[rid] = setTimeout(function(){ saveRow(rid, row.dataset.date); }, 600);
}

// ── Get / Set helpers ─────────────────────────────────────
function gv(rid, col) { const e = document.querySelector(`#${rid} [data-col="${col}"]`); return e ? numOf(e.value) : 0; }
function sv(rid, col, v) { const e = document.querySelector(`#${rid} [data-col="${col}"]`); if(e) e.value = v===0?'':fmt2(v); }

// ── Auto-calculations ─────────────────────────────────────────────────────
// Only 4 fields are auto-calculated:
//   STORE GROSS, TOTAL DEDUCTIONS, SHORT/OVER, TOTAL SWIPE
// All other fields are manual inputs.
function recalc(rid) {
  // MARKETING deducted from the Z-reading (Excel column E = GC SPONSOR / MARKETING)
  // (Stella follows its Excel; the other branches keep their previous behaviour)
  const mkt = STORE === 'Stella' ? gv(rid,'gc_sponsor_marketing') + gv(rid,'marketing_pull_out') : 0;

  // TOTAL INCLUSIVE SC = TOTAL SALES + SC FOR DEPO            (Excel: =G+AE)
  const tisc = gv(rid,'total_sales') + gv(rid,'sc_for_depo');
  sv(rid,'total_inclusive_sc', tisc);

  sv(rid,'mkt_pullout_total', mkt);   // MARKETING PULL-OUT column (beside Booky)

  // STORE GROSS
  //   Stella : TOTAL SALES - MARKETING. In the October Excel the Tips and the Booky are never taken off
  //            the Store Gross (every day they were typed, the formula was edited so they stayed in),
  //            so they are shown in their columns but not deducted here.
  //   Others : TOTAL SALES - TIPS GIFT CERT - BOOKY FEES INCOME (unchanged)
  const sg = STORE === 'Stella'
    ? gv(rid,'total_sales') - mkt
    : gv(rid,'total_sales') - gv(rid,'tips_gift_cert') - gv(rid,'booky_fees_income');
  sv(rid,'store_gross', sg);

  // TOTAL DEDUCTIONS = SALES OF THE DAY (SWIPE) + UNPAID + PAID + ADVANCE + GRAB + GCASH + BANK TRANS
  //   + GC SPONSOR/MARKETING + GC SOLD + DISCOUNT + MARKETING PULL OUT + PERSONAL + PCF + OTHER EXPENSES
  //   (Excel: =I+M+N+O+P+Q+R+S+T+U+V+X+Y+Z+AA+AB+W — Payroll / CA / Direct Purchases have no column here)
  const td = gv(rid,'sales_of_day_swipe')
           + gv(rid,'unpaid')
           + gv(rid,'paid')
           + gv(rid,'advance_payment')
           + gv(rid,'grab')
           + gv(rid,'gcash')
           + gv(rid,'bank_trans')
           + gv(rid,'gc_sponsor_marketing')
           + gv(rid,'gc_sold')
           + gv(rid,'discount')
           + gv(rid,'marketing_pull_out')
           + gv(rid,'personal')
           + gv(rid,'pcf')
           + gv(rid,'other_expenses');
  sv(rid,'total_deductions', td);

  // TOTAL SWIPE = SALES OF THE DAY (SWIPE) + DEPOSIT SWIPE + LATE PAYMENT + PAID      (Excel: =I+J+K+N)
  sv(rid,'total_swipe', gv(rid,'sales_of_day_swipe') + gv(rid,'deposit_swipe') + gv(rid,'late_payment') + gv(rid,'paid'));

  // SHORT / OVER = TOTAL DEDUCTIONS + CASH FOR DEPO - STORE GROSS - MARKETING          (Excel: =AF+H-F-E)
  const so = td + gv(rid,'cash_for_depo') - sg - mkt;
  const soEl = document.querySelector(`#${rid} [data-col="short_over"]`);
  if (soEl) { soEl.value = Math.abs(so) < 0.005 ? '' : fmt2(so); soEl.classList.toggle('neg', so < 0); }
}

// ── Column totals ─────────────────────────────────────────
function recalcTotals() {
  COLS.forEach(c => {
    if(c.grp==='text') return;
    const el = document.getElementById('tot_'+c.key);
    if(!el) return;
    let s=0;
    document.querySelectorAll(`[data-col="${c.key}"]`).forEach(i=>s+=numOf(i.value));
    el.textContent = s===0?'—':s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    el.style.color = (c.key==='short_over'&&s<0)?'var(--accent2)':'';
  });
  // Keep summary panel in sync whenever totals change (Stella only)
  if(typeof recalcSummary === 'function') recalcSummary();
}

// ── Save single row ───────────────────────────────────────
async function saveRow(rid, ds) {
  clearTimeout(saveTimers[rid]);
  const trr = document.getElementById(rid);
  if (!rowHasInput(rid) && trr.dataset.saved !== '1' && trr.dataset.wasSaved !== '1') { setStat(rid, '', '—'); return; }
  setStat(rid, 'saving', 'Saving…');

  const fd = new FormData();
  fd.append('ajax_save','1');
  fd.append('store_name', STORE);
  fd.append('report_date', ds);
  COLS.forEach(c => {
    const el = document.querySelector(`#${rid} [data-col="${c.key}"]`);
    fd.append(c.key, el ? (String(el.value).replace(/,/g,'') || '0') : '0');
  });

  try {
    const res  = await fetch('summary_report.php',{method:'POST',body:fd});
    const data = await res.json();
    if(data.ok){
      setStat(rid, 'ok', 'Saved ✓');
      trr.dataset.saved='1'; trr.dataset.wasSaved='1';
      document.getElementById('dot_'+rid).className='sdot ok';
    } else {
      setStat(rid, 'err', 'Error');
      showToast('❌ '+data.msg,'error');
    }
  } catch(e) {
    setStat(rid, 'err', 'Error');
    showToast('❌ Network error','error');
  }
}

// ── Live sync with the Stella Sales Report (Stella only) ──
// Every few seconds pull the Sales Report figures and refresh the linked cells,
// so a change made in the Sales Report also changes here — and gets saved.
let syncBusy = false;
const SYNC_NEVER = ['cancelled_transaction'];
const SYNC_PARTIAL = ['tips_gift_cert','booky_fees_income'];
async function syncFromSales() {
  if (STORE !== 'Stella' || syncBusy || document.hidden) return;
  syncBusy = true;
  try {
    const res = await fetch('summary_report.php' + location.search + (location.search ? '&' : '?') + 'ajax_sync=1&_=' + Date.now(), {cache:'no-store'});
    const data = await res.json();
    if (!data.ok) return;
    const f2 = v => v != 0 ? fmt2(v) : '';
    Object.keys(data.rows).forEach(function(ds) {
      const rid = 'r' + ds.replace(/-/g, '');
      const tr = document.getElementById(rid); if (!tr) return;
      let any = false; const vals = data.rows[ds];
      Object.keys(vals).forEach(function(col) {
        const el = tr.querySelector(`[data-col="${col}"]`);
        if (!el || el === document.activeElement) return;
        const nv = f2(vals[col]);
        if (SYNC_NEVER.indexOf(col) >= 0) {
          const prev = el.dataset.srv;
          if (prev === undefined) { el.dataset.srv = nv; return; }
          if (el.value === prev || el.value === '') { if (el.value !== nv) { el.value = nv; any = true; } }
          el.dataset.srv = nv; return;
        }
        if (SYNC_PARTIAL.indexOf(col) >= 0 && Number(vals[col]) === 0 && el.readOnly !== true) return;   // nothing in the Sales Report -> leave it typeable
        if (el.readOnly !== true) { el.readOnly = true; el.tabIndex = -1; el.classList.add('calc'); el.removeAttribute('oninput'); }
        if (el.value !== nv) { el.value = nv; any = true; }
      });
      if (tr.dataset.fromsr !== '1') { tr.dataset.fromsr = '1'; any = true; }
      if (any) { recalc(rid); clearTimeout(saveTimers[rid]); saveTimers[rid] = setTimeout(function(){ saveRow(rid, ds); }, 300); }
    });
    recalcTotals();
  } catch(e) { /* offline — try again next tick */ }
  finally { syncBusy = false; }
}

// ── Save all rows ─────────────────────────────────────────
async function saveAll() {
  const rows = [...document.querySelectorAll('#srt tbody tr')];
  for(const row of rows){
    await saveRow(row.id, row.dataset.date);
    await new Promise(r=>setTimeout(r,50));
  }
  showToast(`✓ All ${rows.length} rows saved for ${STORE}`,'success');
}

// ── Toast notification ────────────────────────────────────
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = `flash flash-${type} toast`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(), 4000);
}

// ── CSV Download ──────────────────────────────────────────
function downloadCSV(e) {
  e.preventDefault();
  // Build URL using the current page filters
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv', '1');
  // Ensure store is current
  const storeEl = document.querySelector('select[name="store"]');
  if (storeEl) params.set('store', storeEl.value);
  const monthEl = document.querySelector('select[name="month"]');
  if (monthEl) params.set('month', monthEl.value);
  const yearEl = document.querySelector('select[name="year"]');
  if (yearEl) params.set('year', yearEl.value);
  window.location.href = 'summary_report.php?' + params.toString();
}

// ── Enter key = move down same column ─────────────────────
document.addEventListener('keydown', e=>{
  if(!e.target.classList.contains('sri')||e.key!=='Enter') return;
  e.preventDefault();
  const col=e.target.dataset.col, rid=e.target.dataset.row;
  const next=document.getElementById(rid)?.nextElementSibling;
  if(next){ const ni=next.querySelector(`[data-col="${col}"]`); if(ni){ni.focus();ni.select();} }
});

<?php if ($fStore === 'Stella'): ?>
// ── Monthly summary panel calculations ────────────────────
// Formulas from Excel:
//   Sales %           = Total Store Gross / Monthly Quota   (=E5/E7)
//   Needed to Reach   = Monthly Quota - Total Store Gross   (=E7-E5)
//   Daily Target      = Needed / 5                          (=E9/5)
function recalcSummary() {
  const quota = numOf(document.getElementById('quotaInput')?.value);

  // Get total store_gross from the footer totals cell
  const sgEl = document.getElementById('tot_store_gross');
  const totalGross = sgEl ? (parseFloat(sgEl.textContent.replace(/,/g,'')) || 0) : 0;

  const pctEl    = document.getElementById('summSalesPct');
  const neededEl = document.getElementById('summNeeded');
  const dailyEl  = document.getElementById('summDailyTarget');

  if (quota <= 0) {
    if(pctEl)    pctEl.textContent    = '—';
    if(neededEl) neededEl.textContent = '—';
    if(dailyEl)  dailyEl.textContent  = '—';
    return;
  }

  // Sales % = Total Store Gross / Quota
  const pct = (totalGross / quota) * 100;
  if(pctEl) {
    pctEl.textContent = pct.toFixed(2) + '%';
    pctEl.style.color = pct >= 100 ? 'var(--accent)' : (pct >= 70 ? 'var(--accent3)' : 'var(--accent2)');
  }

  // Needed Sales = Quota - Total Store Gross
  const needed = quota - totalGross;
  if(neededEl) {
    neededEl.textContent = needed <= 0
      ? '✓ Target Met!'
      : needed.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    neededEl.style.color = needed <= 0 ? 'var(--accent)' : 'var(--accent3)';
  }

  // Daily Sales Target = Needed / 5
  const daily = needed <= 0 ? 0 : needed / 5;
  if(dailyEl) {
    dailyEl.textContent = needed <= 0
      ? '✓ Done!'
      : daily.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    dailyEl.style.color = needed <= 0 ? 'var(--accent)' : 'var(--accent)';
  }
}

// ── Save quota to DB (auto-saves a moment after you stop typing) ──
let quotaTimer = null;
function setQuotaHint(msg, color) { const h = document.getElementById('quotaHint'); if (h) { h.textContent = msg; h.style.color = color || ''; } }
function queueQuotaSave() { clearTimeout(quotaTimer); setQuotaHint('Typing…', ''); quotaTimer = setTimeout(saveQuota, 700); }
async function saveQuota() {
  clearTimeout(quotaTimer);
  const quota = numOf(document.getElementById('quotaInput')?.value);
  setQuotaHint('Saving…', '');
  const fd = new FormData();
  fd.append('ajax_quota','1');
  fd.append('store_name', STORE);
  fd.append('report_month', <?=json_encode(sprintf('%04d-%02d',$fYear,$fMonth))?>);
  fd.append('quota', quota);
  try {
    const res = await fetch('summary_report.php',{method:'POST',body:fd});
    const data = await res.json();
    if(data.ok) { setQuotaHint('✓ Saved', 'var(--accent)'); setTimeout(function(){ setQuotaHint('Input target amount · saves automatically', ''); }, 2500); }
    else setQuotaHint('❌ ' + (data.msg || 'Not saved'), 'var(--accent2)');
  } catch(e) { setQuotaHint('❌ Network error — not saved', 'var(--accent2)'); }
}
<?php endif; ?>

// ── Init on load ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('#srt tbody tr').forEach(r=>recalc(r.id));
  recalcTotals();
  if(typeof recalcSummary === 'function') recalcSummary();
  // rows filled from the Sales Report but never saved → save them automatically (staggered)
  let n = 0;
  document.querySelectorAll('#srt tbody tr').forEach(function(r){
    if (r.dataset.saved !== '1' && r.dataset.fromsr === '1' && rowHasInput(r.id)) {
      saveTimers[r.id] = setTimeout(function(){ saveRow(r.id, r.dataset.date); }, 400 + (n++) * 150);
    }
  });
  // rows saved earlier whose stored figures no longer match what's shown (e.g. the Sales Report changed
  // after they were saved) → save the correct figures again so the Income Statement etc. read the right numbers
  document.querySelectorAll('#srt tbody tr[data-db]').forEach(function(r){
    let db; try { db = JSON.parse(r.dataset.db); } catch(e) { return; }
    const keys = (r.dataset.dbk || '').split(',');
    const stale = keys.some(function(k, i){ const el = r.querySelector('[data-col="'+k+'"]'); return el && Math.abs(numOf(el.value) - (db[i] || 0)) > 0.009; });
    if (stale && r.dataset.fromsr === '1' && !saveTimers[r.id]) saveTimers[r.id] = setTimeout(function(){ saveRow(r.id, r.dataset.date); }, 400 + (n++) * 150);
  });
  if (STORE === 'Stella') setInterval(syncFromSales, 5000);
});
</script>
<script src="assets/js/keynav.js"></script>
</body>
</html>