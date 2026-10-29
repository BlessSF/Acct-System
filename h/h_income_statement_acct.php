<?php
// ============================================================
//  h_income_statement_acct.php — H Income Statement (Account Titles)
//
//  Built ONLY from H Disbursement, grouped by Account Title:
//    - every Account Title in the "Expenses" section becomes a line,
//      in the same order as the H Account Titles page (drag to reorder there)
//    - each line = total of the H Disbursement entries for that title
//      in the chosen month (Gross, or Net of VAT)
//    - Net Sales (revenue) comes from the H Summary Report's Store Gross
//  Nothing is typed here and nothing is saved — it is all automatic.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/branch_sales.php';
requireLogin();

if (isBranch() && currentBranch() !== 'H') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Filters ───────────────────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year  < 2000 || $year  > 2100) $year  = (int)date('Y');
if ($month < 1    || $month > 12)   $month = (int)date('n');
$basis = (($_GET['basis'] ?? 'gross') === 'net') ? 'net' : 'gross';   // gross | net of VAT
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));

// ── Account titles (same order as the Account Titles page) ─
$titles = [];
try {
    $titles = $pdo->query("SELECT id, title, section FROM `h_acc_titles` ORDER BY sort_order ASC, title ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── Disbursement totals per account title (this month) ─────
$norm = function ($s) { $s = trim((string)$s); return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };
$sums = [];   // normalised title => [gross, net, count]
try {
    $st = $pdo->prepare("SELECT account_title, COALESCE(SUM(gross),0) AS g, COALESCE(SUM(net_of_vat),0) AS n, COUNT(*) AS c
                         FROM `h_disbursement`
                         WHERE YEAR(entry_date)=? AND MONTH(entry_date)=?
                         GROUP BY account_title");
    $st->execute([$year, $month]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = $norm($r['account_title']);
        if (!isset($sums[$k])) $sums[$k] = ['g' => 0.0, 'n' => 0.0, 'c' => 0];
        $sums[$k]['g'] += (float)$r['g'];
        $sums[$k]['n'] += (float)$r['n'];
        $sums[$k]['c'] += (int)$r['c'];
    }
} catch (Throwable $e) {}
$pick = function (array $s) use ($basis) { return $basis === 'net' ? $s['n'] : $s['g']; };

// ── Revenue: Net Sales = Store Gross total from the H Summary Report ──
$netSales = 0.0;
try { $netSales = round(array_sum(hStoreGrossByDate($pdo, $year, $month)), 2); } catch (Throwable $e) {}

// ── Build the lines ───────────────────────────────────────
$expenseRows = [];                              // [title, amount, entries]
$otherTot = ['assets' => 0.0, 'other' => 0.0, 'unclassified' => 0.0];
$otherCnt = ['assets' => 0,   'other' => 0,   'unclassified' => 0];
$known = [];
foreach ($titles as $t) {
    $k = $norm($t['title']);
    $known[$k] = true;
    $s = $sums[$k] ?? ['g' => 0.0, 'n' => 0.0, 'c' => 0];
    if ($t['section'] === 'expenses') {
        $expenseRows[] = [$t['title'], $pick($s), $s['c']];
    } elseif (isset($otherTot[$t['section']])) {
        $otherTot[$t['section']] += $pick($s);
        $otherCnt[$t['section']] += $s['c'];
    }
}
foreach ($sums as $k => $s) {                   // entries whose title is blank / no longer in the list
    if (!isset($known[$k])) {
        $otherTot['unclassified'] += $pick($s);
        $otherCnt['unclassified'] += $s['c'];
    }
}
$totalExpenses = 0.0;
foreach ($expenseRows as $r) $totalExpenses += $r[1];
$netIncome = $netSales - $totalExpenses;

$money = function ($n) { return number_format((float)$n, 2, '.', ','); };
$pct   = function ($n) use ($netSales) { return $netSales != 0 ? number_format($n / $netSales * 100, 1) . '%' : '—'; };

$pageTitle  = 'Income Statement (Account Titles)';
$activePage = 'h_income_statement_acct';
include '../layout.php';
?>

<style>
.is-wrap { max-width: 780px; margin: 0 auto; }
.is-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow); overflow: hidden; margin-bottom: 20px; }
.is-header { background: #1e3060; color: #fff; padding: 18px 28px 14px; }
.is-header-title { font-family: var(--font-m); font-size: .62rem; text-transform: uppercase;
  letter-spacing: .12em; color: rgba(255,255,255,.5); margin-bottom: 4px; }
.is-header-main { font-size: 1.15rem; font-weight: 700; letter-spacing: -.02em; color: #fff; }
.is-header-date { font-family: var(--font-m); font-size: .72rem; color: rgba(255,255,255,.6); margin-top: 3px; }
.is-section { background: #f0f2f5; padding: 8px 28px; font-family: var(--font-m); font-size: .62rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--subtext2);
  border-top: 1px solid var(--border); }
.is-row { display: flex; align-items: center; padding: 0 28px 0 44px; border-bottom: 1px solid #f4f5f7;
  min-height: 38px; transition: background .1s; }
.is-row:hover { background: #fafbfc; }
.is-row.zero .is-label, .is-row.zero .is-val { color: var(--subtext); }
.is-label { flex: 1; font-size: .78rem; color: var(--text); padding: 6px 0; }
.is-count { font-family: var(--font-m); font-size: .58rem; color: var(--subtext); margin-left: 8px;
  background: #f3f4f6; padding: 2px 7px; border-radius: 10px; }
.is-val { font-family: var(--font-m); font-size: .8rem; color: var(--text); min-width: 140px; text-align: right; }
.is-pct { font-family: var(--font-m); font-size: .7rem; color: var(--subtext); width: 56px; text-align: right; margin-left: 6px; }
.is-total { display: flex; align-items: center; padding: 8px 28px 8px 44px; background: #f8f9fb;
  border-top: 2px solid var(--border2); border-bottom: 1px solid var(--border); }
.is-total-label { flex: 1; font-family: var(--font-m); font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em; color: var(--subtext2); }
.is-total-val { font-family: var(--font-m); font-size: .88rem; font-weight: 800; color: var(--text);
  min-width: 140px; text-align: right; }
.is-total-pct { font-family: var(--font-m); font-size: .7rem; font-weight: 700; width: 56px; text-align: right; margin-left: 6px; }
.is-net { background: #fffbeb; border-top: 2px solid var(--accent3) !important; }
.is-net .is-total-label { color: var(--accent3); font-size: .82rem; }
.is-net .is-total-val   { color: var(--accent3); font-size: 1rem; }
.is-tag { font-family: var(--font-m); font-size: .6rem; color: var(--accent); margin-left: 8px;
  background: rgba(15,123,92,.08); padding: 2px 7px; border-radius: 10px; border: 1px solid rgba(15,123,92,.15); }
.is-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
.is-note { font-family: var(--font-m); font-size: .68rem; color: var(--subtext); line-height: 1.6;
  padding: 12px 28px; border-top: 1px solid var(--border); background: #fcfcfd; }
.is-note b { color: var(--text); }
.neg { color: var(--accent2) !important; }
@media print { .is-controls, .sidebar, aside { display: none !important; } }
</style>

<div class="is-controls">
  <form method="GET" id="isForm" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:150px" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
      <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select name="basis" class="form-control" style="max-width:170px" onchange="this.form.submit()">
      <option value="gross" <?= $basis === 'gross' ? 'selected' : '' ?>>Amount: Gross</option>
      <option value="net"   <?= $basis === 'net'   ? 'selected' : '' ?>>Amount: Net of VAT</option>
    </select>
  </form>
  <label style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext);display:flex;align-items:center;gap:6px;cursor:pointer">
    <input type="checkbox" id="hideZero" onchange="toggleZero()"> Hide zero lines
  </label>
  <a href="h_acc_title.php" class="btn btn-ghost">⚙ Account Titles</a>
  <a href="h_disbursement.php" class="btn btn-ghost">🧾 Disbursement</a>
  <button type="button" class="btn btn-ghost" onclick="window.print()">🖨 Print</button>
</div>

<div class="is-wrap">
<div class="is-card">

  <div class="is-header">
    <div class="is-header-title">H Branch · From Disbursement Account Titles</div>
    <div class="is-header-main">INCOME STATEMENT — <?= htmlspecialchars($monthLabel) ?></div>
    <div class="is-header-date">Expenses shown as <?= $basis === 'net' ? 'Net of VAT' : 'Gross (incl. VAT)' ?></div>
  </div>

  <div class="is-section">Revenue</div>
  <div class="is-row">
    <div class="is-label">Net Sales <span class="is-tag">↳ Auto from Summary Report</span></div>
    <div class="is-val"><?= $money($netSales) ?></div>
    <div class="is-pct"><?= $netSales != 0 ? '100.0%' : '—' ?></div>
  </div>

  <div class="is-section">Expenses <span style="text-transform:none;letter-spacing:0;font-weight:400">— one line per Account Title (Expenses section), same order as Account Titles</span></div>
  <?php if (!$expenseRows): ?>
  <div class="is-row"><div class="is-label" style="color:var(--subtext)">No Account Titles found in the Expenses section.</div></div>
  <?php endif; ?>
  <?php foreach ($expenseRows as $r): $zero = (abs($r[1]) < 0.005); ?>
  <div class="is-row<?= $zero ? ' zero' : '' ?>" data-zero="<?= $zero ? '1' : '0' ?>">
    <div class="is-label"><?= htmlspecialchars($r[0]) ?>
      <?php if ($r[2] > 0): ?><span class="is-count"><?= (int)$r[2] ?> entr<?= $r[2] == 1 ? 'y' : 'ies' ?></span><?php endif; ?>
    </div>
    <div class="is-val"><?= $money($r[1]) ?></div>
    <div class="is-pct"><?= $zero ? '' : $pct($r[1]) ?></div>
  </div>
  <?php endforeach; ?>

  <div class="is-total">
    <div class="is-total-label neg">Total Expenses</div>
    <div class="is-total-val neg"><?= $money($totalExpenses) ?></div>
    <div class="is-total-pct neg"><?= $pct($totalExpenses) ?></div>
  </div>

  <div class="is-total is-net">
    <div class="is-total-label">Net Income (Loss)</div>
    <div class="is-total-val <?= $netIncome < 0 ? 'neg' : '' ?>"><?= $money($netIncome) ?></div>
    <div class="is-total-pct <?= $netIncome < 0 ? 'neg' : '' ?>"><?= $pct($netIncome) ?></div>
  </div>

  <div class="is-note">
    <b>Not part of this statement</b> (Disbursement entries for <?= htmlspecialchars($monthLabel) ?> under other title types):<br>
    Assets titles: <b><?= $money($otherTot['assets']) ?></b> (<?= (int)$otherCnt['assets'] ?>) &nbsp;·&nbsp;
    Other titles (e.g. Accounts Payable): <b><?= $money($otherTot['other']) ?></b> (<?= (int)$otherCnt['other'] ?>)
    <?php if ($otherCnt['unclassified'] > 0): ?>
    &nbsp;·&nbsp; <span class="neg">No / removed account title: <b><?= $money($otherTot['unclassified']) ?></b> (<?= (int)$otherCnt['unclassified'] ?>) — open Disbursement and pick a title for these</span>
    <?php endif; ?>
  </div>

</div>
</div>

<script>
function toggleZero() {
  const hide = document.getElementById('hideZero').checked;
  document.querySelectorAll('.is-row[data-zero="1"]').forEach(function (r) { r.style.display = hide ? 'none' : ''; });
  try { sessionStorage.setItem('hIsAcctHideZero', hide ? '1' : '0'); } catch (e) {}
}
(function () {
  try {
    if (sessionStorage.getItem('hIsAcctHideZero') === '1') {
      document.getElementById('hideZero').checked = true;
      toggleZero();
    }
  } catch (e) {}
})();
</script>

</div></div>
<script src="../assets/js/keynav.js"></script>
</body>
</html>