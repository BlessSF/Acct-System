<?php
// ============================================================
//  stella_income_statement_acct.php — Stella Income Statement (Account Titles)
//
//  Built ONLY from Stella Disbursement, grouped by Account Title:
//    - every Account Title in the "Expenses" section becomes a line,
//      in the same order as the Stella Account Titles page (drag to reorder there)
//    - each line = total of the Stella Disbursement entries for that title
//      in the chosen month (Gross, or Net of VAT)
//    - Net Sales (revenue) comes from the Stella Summary Report's Store Gross
//  Nothing is typed here and nothing is saved — it is all automatic.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// Same table the Account Titles page uses (created here too so this page never errors on a fresh install)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stella_acc_titles` (
        `id` int(11) NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL,
        `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses', `sort_order` int(6) NOT NULL DEFAULT 0,
        `saved_by` varchar(100) DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`), UNIQUE KEY `uniq_title` (`title`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {}

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
    $titles = $pdo->query("SELECT id, title, section FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── Disbursement totals per account title (this month) ─────
$norm = function ($s) { $s = trim((string)$s); return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };
$sums = [];   // normalised title => [gross, net, count]
try {
    $st = $pdo->prepare("SELECT account_title, COALESCE(SUM(gross),0) AS g, COALESCE(SUM(net_of_vat),0) AS n, COUNT(*) AS c
                         FROM `stella_disbursement`
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

// ── Revenue: Net Sales = Store Gross total from the Stella Summary Report ──
$netSales = 0.0;
try {
    $sg = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='Stella' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sg->execute([$year, $month]);
    $netSales = round((float)$sg->fetchColumn(), 2);
} catch (Throwable $e) {}

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
$activePage = 'stella_income_statement_acct';
$bodyClass  = 'sp-stella_income_statement_acct';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<div class="is-controls">
  <form method="GET" id="isForm" class="is-form">
    <select name="month" class="form-control is-sel-month" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control is-sel-year" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
      <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select name="basis" class="form-control is-sel-basis" onchange="this.form.submit()">
      <option value="gross" <?= $basis === 'gross' ? 'selected' : '' ?>>Amount: Gross</option>
      <option value="net"   <?= $basis === 'net'   ? 'selected' : '' ?>>Amount: Net of VAT</option>
    </select>
  </form>
  <label class="is-hide-label">
    <input type="checkbox" id="hideZero" onchange="toggleZero()"> Hide zero lines
  </label>
  <a href="stella_acc_title.php" class="btn btn-ghost">⚙ Account Titles</a>
  <a href="stella_disbursement.php" class="btn btn-ghost">🧾 Disbursement</a>
  <button type="button" class="btn btn-ghost" onclick="window.print()">🖨 Print</button>
</div>

<div class="is-wrap" id="isWrap">
<div class="is-card">

  <div class="is-header">
    <div class="is-header-title">Stella Branch · From Disbursement Account Titles</div>
    <div class="is-header-main">INCOME STATEMENT — <?= htmlspecialchars($monthLabel) ?></div>
    <div class="is-header-date">Expenses shown as <?= $basis === 'net' ? 'Net of VAT' : 'Gross (incl. VAT)' ?></div>
  </div>

  <div class="is-section">Revenue</div>
  <div class="is-row">
    <div class="is-label">Net Sales <span class="is-tag">↳ Auto from Summary Report</span></div>
    <div class="is-val"><?= $money($netSales) ?></div>
    <div class="is-pct"><?= $netSales != 0 ? '100.0%' : '—' ?></div>
  </div>

  <div class="is-section">Expenses <span class="is-section-hint">— one line per Account Title (Expenses section), same order as Account Titles</span></div>
  <?php if (!$expenseRows): ?>
  <div class="is-row"><div class="is-label is-empty">No Account Titles found in the Expenses section.</div></div>
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
  var hide = document.getElementById('hideZero').checked;
  document.getElementById('isWrap').classList.toggle('is-hide-zero', hide);
  try { sessionStorage.setItem('stellaIsAcctHideZero', hide ? '1' : '0'); } catch (e) {}
}
(function () {
  try {
    if (sessionStorage.getItem('stellaIsAcctHideZero') === '1') {
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