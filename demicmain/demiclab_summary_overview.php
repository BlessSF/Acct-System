<?php
// ============================================================
//  demiclab_summary_overview.php — DemicLab-Main Yearly Overview
//  Read-only monthly rollup of demiclab_report_entries.
//  Completely standalone — does NOT touch Stella's tables or code.
//  Reads from the same table demiclab_summary_report.php writes to.
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

// ── Column list — matches the Excel layout order ────────────
$COLS = [
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
];

$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// ── Filter: year ─────────────────────────────────────────────
$fYear = (int)($_GET['year'] ?? date('Y'));

// ── Pull monthly sums straight from the daily entries table ──
// (Total Discounts / Total After Discounts / Gross Sales are summed
//  from the values already saved per day, not re-derived from the
//  monthly totals — summing the daily formula results is the
//  accurate figure; re-applying the % formula to monthly totals is not.)
$sumExprs = [];
foreach ($COLS as $col => $label) {
    $sumExprs[] = "COALESCE(SUM(`$col`),0) AS `$col`";
}
$sql = "SELECT MONTH(report_date) AS m, " . implode(', ', $sumExprs) . "
        FROM demiclab_report_entries
        WHERE store_name = 'DemicLab-Main' AND YEAR(report_date) = ?
        GROUP BY MONTH(report_date)";

$monthly = array_fill(1, 12, null);
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fYear]);
    foreach ($stmt->fetchAll() as $row) {
        $monthly[(int)$row['m']] = $row;
    }
} catch (Throwable $ignored) {}

// ── Grand totals for the year ───────────────────────────────
$yearTotals = array_fill_keys(array_keys($COLS), 0.0);
foreach ($monthly as $m => $row) {
    if (!$row) continue;
    foreach ($COLS as $col => $label) {
        $yearTotals[$col] += (float)$row[$col];
    }
}

// ── CSV export ───────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $filename = 'DemicLab-Main_' . $fYear . '_Overview.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['DemicLab-Main — Summary Report Overview']);
    fputcsv($out, [$fYear]);
    fputcsv($out, []);
    fputcsv($out, array_merge(['Month'], array_values($COLS)));

    for ($m = 1; $m <= 12; $m++) {
        $row  = $monthly[$m];
        $line = [$monthNames[$m]];
        foreach ($COLS as $col => $label) {
            $val    = $row ? (float)$row[$col] : 0.0;
            $line[] = $val != 0 ? number_format($val, 2, '.', '') : '';
        }
        fputcsv($out, $line);
    }
    $totalLine = ['TOTAL'];
    foreach ($COLS as $col => $label) {
        $totalLine[] = $yearTotals[$col] != 0 ? number_format($yearTotals[$col], 2, '.', '') : '0.00';
    }
    fputcsv($out, $totalLine);
    fclose($out);
    exit;
}

$pageTitle  = 'DemicLab-Main Summary Overview';
$activePage = 'demiclab_summary_overview';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* ── Scroll container ── */
.so-outer {
  width: 100%; overflow-x: auto; overflow-y: visible;
  border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface); scrollbar-width: thin;
  scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.so-outer::-webkit-scrollbar { height: 8px; }
.so-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.so-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }
.so-outer::-webkit-scrollbar-thumb:hover { background: #9aa3af; }

/* ── Table ── */
.so-table { border-collapse: collapse; width: max-content; font-size: .69rem; table-layout: fixed; }
.so-table thead th {
  background: #f4f5f7; color: #4b5563;
  font-family: var(--font-m); font-size: .52rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  padding: 9px 6px; border: 1px solid #e3e6ea;
  white-space: nowrap; text-align: center;
  position: sticky; top: 0; z-index: 20;
}
.so-table thead tr.grp-row th {
  font-size: .5rem; padding: 4px 6px;
  background: #ebedf0; letter-spacing: .05em; color: #374151;
}

/* ── Sticky month column ── */
.th-month {
  position: sticky !important; left: 0; z-index: 30 !important;
  width: 100px; min-width: 100px;
  background: #ebedf0 !important; color: var(--accent) !important;
  box-shadow: 2px 0 5px rgba(0,0,0,.07);
}
.td-month {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb !important; box-shadow: 2px 0 5px rgba(0,0,0,.06);
  font-family: var(--font-m); font-size: .72rem;
  color: var(--accent); font-weight: 700;
  text-align: left; padding: 8px 10px;
  white-space: nowrap; width: 100px; min-width: 100px;
}

/* ── Column colours (match the daily entry page) ── */
.g-income { background: #f0fdf4 !important; }
.g-deduct { background: #fff7f7 !important; }
.g-calc   { background: #eff6ff !important; }

/* ── Column widths ── */
.col-num  { width: 100px; min-width: 100px; }

/* ── Body rows ── */
.so-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.so-table tbody tr:hover td { filter: brightness(.97); }
.so-table td {
  border: 1px solid #e8eaed; padding: 6px 8px; vertical-align: middle;
  text-align: right; font-family: var(--font-m); font-size: .69rem; color: #1a1d23;
}
.so-table td.empty-cell { color: #b6bcc6; }
.so-table td.calc-cell  { color: #1d4ed8; font-weight: 600; }

/* ── Totals row ── */
.so-table tfoot td {
  padding: 8px 8px; font-family: var(--font-m); font-size: .72rem; font-weight: 800;
  text-align: right; border: 1px solid #d1d5db;
  background: #1a2b4a; color: #fff;
}
.so-table tfoot td.tfl {
  text-align: left; color: #fff;
  font-size: .68rem; text-transform: uppercase; letter-spacing: .06em;
}

/* ── Controls ── */
.so-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }

/* ── Scroll hint ── */
.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  text-align: center; padding: 6px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}

/* ── Legend ── */
.so-leg {
  display: flex; gap: 12px; flex-wrap: wrap;
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  margin-bottom: 10px; align-items: center;
}
.so-leg span { display: flex; align-items: center; gap: 5px; }
.ld { display: inline-block; width: 9px; height: 9px; border-radius: 3px; }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">DemicLab-Main <span style="color:var(--accent)">Summary Overview</span></div>
    <div class="section-subtitle">Monthly totals rolled up from the daily Summary Report · January – December <?=$fYear?></div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="so-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab-Main
  </div>
  <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
    <?php for ($y = 2050; $y >= 2023; $y--): ?>
    <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>

  <a href="demiclab_summary_report.php" class="btn btn-ghost btn-sm">📝 Go to Daily Entry</a>
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="downloadOverviewCSV(event)">⬇ Download CSV</a>
</form>

<!-- Legend -->
<div class="so-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Income</span>
  <span><span class="ld" style="background:#fecaca;border:1px solid #fca5a5"></span>Discounts</span>
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Calculated</span>
</div>

<!-- Spreadsheet -->
<div class="so-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="so-table" id="sot">
  <thead>
    <tr class="grp-row">
      <th class="th-month"></th>
      <th colspan="1" class="g-calc">AUTO</th>
      <th colspan="6" class="g-income">POS / CASH / HMO / CHARGE / DEBIT / CREDIT</th>
      <th colspan="5" class="g-deduct">DISCOUNTS</th>
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <th colspan="1" class="g-income">LATE PAYMENT</th>
    </tr>
    <tr>
      <th class="th-month">MONTH</th>
      <th class="g-calc col-num">GROSS SALES<br>(POS+DISCOUNT)</th>
      <th class="g-income col-num">POS READING</th>
      <th class="g-income col-num">CASH</th>
      <th class="g-income col-num">HMO</th>
      <th class="g-income col-num">CHARGE TO<br>COMPANY</th>
      <th class="g-income col-num">DEBIT<br>(amt less 4%)</th>
      <th class="g-income col-num">CREDIT<br>(amt less 3.5%)</th>
      <th class="g-deduct col-num">30%</th>
      <th class="g-deduct col-num">SC/PWD (20%)</th>
      <th class="g-deduct col-num">15%</th>
      <th class="g-deduct col-num">10%</th>
      <th class="g-deduct col-num">5%</th>
      <th class="g-calc col-num">TOTAL<br>DISCOUNTS</th>
      <th class="g-calc col-num">TOTAL AFTER<br>DISCOUNTS</th>
      <th class="g-income col-num">LATE PAYMENT<br>(CHECK)</th>
    </tr>
  </thead>

  <tbody>
  <?php for ($m = 1; $m <= 12; $m++):
      $row = $monthly[$m];
      $fmt = function($v) {
          $v = (float)$v;
          return $v != 0 ? number_format($v, 2) : '';
      };
  ?>
  <tr>
    <td class="td-month"><?=$monthNames[$m]?></td>
    <?php foreach ($COLS as $col => $label):
        $isCalc = in_array($col, ['gross_sales','pos_reading','total_discounts','total_after_discounts'], true);
        $val    = $row ? $fmt($row[$col]) : '';
        $cls     = $isCalc ? 'calc-cell' : '';
        $cls    .= $val === '' ? ' empty-cell' : '';
    ?>
    <td class="<?=trim($cls)?>"><?=$val !== '' ? $val : '—'?></td>
    <?php endforeach; ?>
  </tr>
  <?php endfor; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <?php foreach ($COLS as $col => $label):
          $v = $yearTotals[$col];
      ?>
      <td><?=$v != 0 ? number_format($v, 2) : '—'?></td>
      <?php endforeach; ?>
    </tr>
  </tfoot>
</table>
</div>

<!-- ── RECONCILIATION SUMMARY PANEL (mirrors the daily entry page) ── -->
<div class="card" style="margin-top:20px;overflow:hidden">
  <div style="padding:12px 20px;background:#f8f9fb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">DemicLab-Main — Reconciliation Summary (Year Totals)</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0">
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Cash Sales</div>
      <div style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)"><?=number_format($yearTotals['cash'],2)?></div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">POS Reading</div>
      <div style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)"><?=number_format($yearTotals['pos_reading'],2)?></div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">POS + Discount</div>
      <div style="font-size:1.05rem;font-weight:800;color:#1a1d23;font-family:var(--font-m)"><?=number_format($yearTotals['gross_sales'],2)?></div>
    </div>
    <div style="padding:14px 18px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Total Discount</div>
      <div style="font-size:1.05rem;font-weight:800;color:#be123c;font-family:var(--font-m)"><?=number_format($yearTotals['total_discounts'],2)?></div>
    </div>
    <div style="padding:14px 18px;background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.56rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px">Gross Sale</div>
      <div style="font-size:1.05rem;font-weight:800;color:#0f7b5c;font-family:var(--font-m)"><?=number_format($yearTotals['gross_sales'],2)?></div>
    </div>
  </div>
</div>

  </div></div>

<script>
function downloadOverviewCSV(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv', '1');
  window.location.href = 'demiclab_summary_overview.php?' + params.toString();
}
</script>