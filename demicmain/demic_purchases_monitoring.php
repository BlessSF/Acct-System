<?php
// ============================================================
//  demic_purchases_monitoring.php — Expense Monitoring
//  Mirrors the "DISBURSEMENT_DEMIC_2026.xlsx" monitoring sheet:
//  rows = expense categories, columns = JAN..DEC, bottom row =
//  TOTAL AMOUNT (highlighted). Pulls live monthly sums straight
//  from the demic_purchases table (same source as the ledger).
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

// ── Category rows, in the exact order/labels of the Excel sheet ─
// Rows using a "__blank_*__" sentinel key aren't tracked as their
// own ledger column yet (CUSA / TAXES & LICENSES) — they render as
// blank so the grid still matches the Excel layout 1:1. (NOTE: an
// earlier version used a real `null` key here — PHP silently casts
// a null array key to '', which broke the SQL query below and made
// every month show as "—" even though the ledger data was fine.)
$ROWS = [
    'materials_purchases'    => 'MATERIALS PURCHASES (CONSUMABLES/CGS)',
    'amortization_expense'   => 'AMORTIZATION EXPENSE',
    'wtax_rent'               => 'WTAX RENT',
    'service_fee'             => 'SERVICE FEE',
    'rent'                    => 'RENT',
    'management_fee'          => 'MANAGEMENT FEE',
    '__blank_cusa__'          => 'CUSA',              // no db column
    'office_supplies'         => 'OFFICE SUPPLIES',
    'employees_benefit'       => 'EMPLOYEES BENEFIT',
    'advertisement'           => 'ADVERTISEMENT',
    'bio'                     => 'BIO',
    'janitorial_cleaning'     => 'JANITORIAL & CLEANING SUPPLIES',
    'freight_charge'          => 'FREIGHT CHARGE',
    'fare_travel'             => 'FARE',
    'fuel_oil'                => 'FUEL/OIL',
    'repairs_maintenance'     => 'REPAIRS & MAINTENANCE',
    'communication'           => 'COMMUNICATION',
    'electricity'             => 'ELECTRICITY',
    'cash_advances'           => 'INSURANCE/CASH ADVANCES',
    'representation_expense'  => 'REPRESENTATION EXPENSE',
    'salary'                  => 'SALARY',
    'professional_fee'        => 'PROFESSIONAL FEE',
    '__blank_tax__'           => 'TAXES & LICENSES',  // no db column
    'misc'                    => 'MISC',
    'other_equipment'         => 'OTHER EQUIPMENT',
];

// Sentinel keys that don't correspond to a real DB column
$BLANK_KEYS = ['__blank_cusa__', '__blank_tax__'];

// Real, queryable DB columns only (skip the two placeholder rows above)
$DB_COLS = array_values(array_diff(array_keys($ROWS), $BLANK_KEYS));

$monthNames  = ['','JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];

// ── Filters ───────────────────────────────────────────────
$fYear = (int)($_GET['year'] ?? date('Y'));

// ── Pull monthly sums per category for the selected year ──
$monthData = array_fill(1, 12, array_fill_keys($DB_COLS, 0.0));
try {
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $DB_COLS));
    $sql = "SELECT MONTH(expense_date) AS mo, $selectParts
            FROM demic_purchases
            WHERE YEAR(expense_date) = ?
            GROUP BY MONTH(expense_date)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fYear]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mo = (int)$row['mo'];
        foreach ($DB_COLS as $c) $monthData[$mo][$c] = (float)$row[$c];
    }
} catch (Throwable $ignored) {}

// Column (month) totals = sum of every category that month
$monthTotals = [];
for ($m = 1; $m <= 12; $m++) {
    $monthTotals[$m] = array_sum($monthData[$m]);
}

// ── CSV Export (same grid, downloadable) ───────────────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab_Expense_Monitoring_'.$fYear.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['DemicLab — Expense Monitoring '.$fYear]);
    fputcsv($out, []);
    fputcsv($out, array_merge(['MONTH'], array_slice($monthNames, 1, 12)));
    foreach ($ROWS as $col => $label) {
        $line = [$label];
        for ($m = 1; $m <= 12; $m++) {
            $line[] = in_array($col, $BLANK_KEYS, true) ? '' : number_format($monthData[$m][$col], 2, '.', '');
        }
        fputcsv($out, $line);
    }
    $totalLine = ['TOTAL AMOUNT'];
    for ($m = 1; $m <= 12; $m++) $totalLine[] = number_format($monthTotals[$m], 2, '.', '');
    fputcsv($out, $totalLine);
    fclose($out);
    exit;
}

$pageTitle  = 'Expense Monitoring';
$activePage = 'demic_purchases_monitoring';
include '../layout.php';

function fmtCell($val) {
    $val = (float)$val;
    if ($val == 0) return '<span style="color:var(--muted)">—</span>';
    return number_format($val, 2, '.', ',');
}
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.em-outer {
  width: 100%; overflow-x: auto;
  border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
}
.em-outer::-webkit-scrollbar { height: 8px; }
.em-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.em-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

.em-table { border-collapse: collapse; width: max-content; min-width: 100%; font-size: .72rem; }

.em-table thead th {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .58rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  padding: 9px 8px; border: 1px solid #2d5480;
  text-align: center; white-space: nowrap;
  position: sticky; top: 0; z-index: 10;
}
.em-table thead th.th-month { min-width: 108px; }
.em-table thead th.th-cat {
  position: sticky !important; left: 0; z-index: 20 !important;
  background: #162d4a !important; text-align: left;
  min-width: 260px; width: 260px;
}

.em-table td { border: 1px solid #e3e6ea; padding: 8px 10px; text-align: right; white-space: nowrap; font-family: var(--font-m); }

.em-table tbody td.td-cat {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb; box-shadow: 2px 0 5px rgba(0,0,0,.06);
  text-align: left; font-family: var(--font-h); font-weight: 600;
  font-size: .72rem; color: #1a1d23; white-space: normal;
}
.em-table tbody tr:nth-child(even) td.td-cat { background: #f2f4f7; }
.em-table tbody tr:nth-child(even) td:not(.td-cat) { background: #fafbfc; }
.em-table tbody tr:hover td { filter: brightness(.97); }

.em-table tbody td.td-blank { color: var(--muted); font-style: italic; font-size: .64rem; text-align: center; }

.em-table tr.em-total td {
  background: #fde047 !important; color: #1a1d23; font-weight: 800;
  font-size: .78rem; border: 1px solid #eab308;
}
.em-table tr.em-total td.td-cat {
  position: sticky; left: 0; z-index: 6;
  background: #fde047 !important; box-shadow: 2px 0 5px rgba(0,0,0,.1);
}

.em-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Expense <span style="color:var(--accent)">Monitoring</span></div>
    <div class="section-subtitle">Live monthly rollup of DemicLab purchases · categories × months, mirrors the disbursement workbook</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="em-controls" id="filterForm">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab
  </div>
  <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
    <?php for ($y = 2050; $y >= 2023; $y--): ?>
    <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <a href="demic_purchases.php" class="btn btn-ghost btn-sm" style="color:var(--accent);border-color:rgba(34,211,165,.25);background:var(--accent-dim)">← Back to Purchases Ledger</a>
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="doExport(event)">⬇ Download CSV</a>
</form>

<!-- Table -->
<div class="em-outer">
<table class="em-table">
  <thead>
    <tr>
      <th class="th-cat">MONTH</th>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <th class="th-month"><?= $monthNames[$m] ?></th>
      <?php endfor; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($ROWS as $col => $label): ?>
    <tr>
      <td class="td-cat"><?= htmlspecialchars($label) ?></td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <?php if (in_array($col, $BLANK_KEYS, true)): ?>
        <td class="td-blank">—</td>
        <?php else: ?>
        <td><?= fmtCell($monthData[$m][$col]) ?></td>
        <?php endif; ?>
      <?php endfor; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="em-total">
      <td class="td-cat">TOTAL AMOUNT</td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <td><?= fmtCell($monthTotals[$m]) ?></td>
      <?php endfor; ?>
    </tr>
  </tfoot>
</table>
</div>

<script>
function doExport(e) {
  e.preventDefault();
  const params = new URLSearchParams(window.location.search);
  params.set('export_csv', '1');
  window.location.href = 'demic_purchases_monitoring.php?' + params.toString();
}
</script>