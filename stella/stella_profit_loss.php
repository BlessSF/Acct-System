<?php
// ============================================================
//  stella_profit_loss.php — Stella Profit & Loss Statement
//  Pulls expense data from stella_disbursement table
//  Revenue rows are manually entered and stored separately
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create revenue table ──────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_pl_revenue` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `year`       int(4)  NOT NULL,
    `month`      int(2)  NOT NULL,
    `rev_type`   varchar(50) NOT NULL DEFAULT 'vatable',
    `amount`     decimal(15,2) NOT NULL DEFAULT 0.00,
    `saved_by`   varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Safety guard — stella_acc_titles is normally created by
// stella_disbursement.php / stella_acc_title.php, but ensure it exists
// here too in case this page is loaded first.
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_acc_titles` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `title`       varchar(255) NOT NULL,
    `section`     enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
    `sort_order`  int(6) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$YEAR = (int)($_GET['year'] ?? date('Y'));

// Revenue types
$REV_TYPES = ['vatable' => 'Vatable Sales (VAT Inclusive)', 'exempt' => 'Exempt Sales', 'other' => 'Other Income'];

// Expense account titles that map to P&L rows.
// These now come from stella_acc_titles (managed via stella_acc_title.php)
// instead of a hardcoded list — every title regardless of assets/expenses
// section (Assets purchases are shown as spend here too, same as before).
$EXPENSE_ACCOUNTS = $pdo->query("SELECT title FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_COLUMN);

// ── AJAX: Save revenue ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_rev'])) {
    header('Content-Type: application/json');
    $year    = (int)($_POST['year'] ?? $YEAR);
    $month   = (int)($_POST['month'] ?? 0);
    $revType = $_POST['rev_type'] ?? '';
    $amount  = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    if (!$month || !$revType) { echo json_encode(['ok'=>false,'msg'=>'Missing params']); exit; }
    try {
        $pdo->prepare("INSERT INTO stella_pl_revenue (year,month,rev_type,amount,saved_by)
                       VALUES (?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE amount=VALUES(amount), saved_by=VALUES(saved_by)")
            ->execute([$year, $month, $revType, $amount, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Load revenue for the year ─────────────────────────────
$revRows = $pdo->prepare("SELECT month, rev_type, amount FROM stella_pl_revenue WHERE year=? ORDER BY month,rev_type");
$revRows->execute([$YEAR]);
$revenue = []; // [month][rev_type] = amount
foreach ($revRows->fetchAll() as $r) {
    $revenue[$r['month']][$r['rev_type']] = (float)$r['amount'];
}

// ── Load disbursement expenses grouped by account_title + month ──
$expRows = $pdo->prepare("
    SELECT MONTH(entry_date) as mo, account_title, SUM(net_of_vat) as total
    FROM stella_disbursement
    WHERE YEAR(entry_date) = ? AND account_title != ''
    GROUP BY MONTH(entry_date), account_title
    ORDER BY account_title, mo
");
$expRows->execute([$YEAR]);
$expenses = []; // [account_title][month] = total
foreach ($expRows->fetchAll() as $r) {
    $expenses[$r['account_title']][$r['mo']] = (float)$r['total'];
}

// Collect all expense titles that actually have data + the standard list
$allExpTitles = array_unique(array_merge($EXPENSE_ACCOUNTS, array_keys($expenses)));
sort($allExpTitles);

$MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];


// ── XLSX Export (styled — replaces the old plain-CSV download) ────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('MERITONI CORP');
    $xlsx->subtitle('Stella Profit & Loss Statement');
    $xlsx->subtitle('Year ' . $YEAR . ' · Generated ' . date('F d, Y H:i:s'));
    $xlsx->blank();
    $xlsx->header(array_merge(['ACCOUNT TITLE'], $MONTHS, ['TOTAL']));

    // Revenue
    $xlsx->sectionRow('REVENUE');
    $revSubtotals = array_fill(1, 12, 0);
    $revGrandTotal = 0;
    foreach ($REV_TYPES as $rk => $rl) {
        $rowTotal = 0;
        $cells = [$rl];
        for ($m = 1; $m <= 12; $m++) {
            $val = $revenue[$m][$rk] ?? 0;
            $rowTotal += $val;
            $revSubtotals[$m] += $val;
            $cells[] = $val > 0 ? number_format($val, 2, '.', '') : '-';
        }
        $cells[] = $rowTotal > 0 ? number_format($rowTotal, 2, '.', '') : '-';
        $revGrandTotal += $rowTotal;
        $xlsx->row($cells);
    }
    $subCells = ['Subtotal'];
    for ($m = 1; $m <= 12; $m++) $subCells[] = $revSubtotals[$m] > 0 ? number_format($revSubtotals[$m], 2, '.', '') : '-';
    $subCells[] = $revGrandTotal > 0 ? number_format($revGrandTotal, 2, '.', '') : '-';
    $xlsx->totalRow($subCells);

    // Expenses
    $xlsx->blank();
    $xlsx->sectionRow('EXPENSES');
    $expSubtotals = array_fill(1, 12, 0);
    $expGrandTotal = 0;
    foreach ($allExpTitles as $title) {
        $rowTotal = 0;
        $cells = [$title];
        for ($m = 1; $m <= 12; $m++) {
            $val = $expenses[$title][$m] ?? 0;
            $rowTotal += $val;
            $expSubtotals[$m] += $val;
            $cells[] = $val > 0 ? number_format($val, 2, '.', '') : '-';
        }
        $cells[] = $rowTotal > 0 ? number_format($rowTotal, 2, '.', '') : '-';
        $expGrandTotal += $rowTotal;
        $xlsx->row($cells);
    }
    $subCells = ['Subtotal'];
    for ($m = 1; $m <= 12; $m++) $subCells[] = $expSubtotals[$m] > 0 ? number_format($expSubtotals[$m], 2, '.', '') : '-';
    $subCells[] = $expGrandTotal > 0 ? number_format($expGrandTotal, 2, '.', '') : '-';
    $xlsx->totalRow($subCells);

    // Net income
    $xlsx->blank();
    $netCells = ['Income over Expenses'];
    $netGrandTotal = $revGrandTotal - $expGrandTotal;
    for ($m = 1; $m <= 12; $m++) {
        $net = ($revSubtotals[$m] ?? 0) - ($expSubtotals[$m] ?? 0);
        $netCells[] = $net != 0 ? number_format($net, 2, '.', '') : '-';
    }
    $netCells[] = $netGrandTotal != 0 ? number_format($netGrandTotal, 2, '.', '') : '-';
    $xlsx->totalRow($netCells);

    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths(array_merge([26], array_fill(0, 12, 10), [12]));
    $xlsx->output('Stella_PL_' . $YEAR . '.xlsx');
    exit;
}

$pageTitle  = 'Stella Profit & Loss';
$activePage = 'stella_profit_loss';
$bodyClass = 'sp-stella_profit_loss';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title">Stella <span>Profit & Loss</span></div>
    <div class="section-subtitle">MERITONI CORP · Expenses auto-pulled from Disbursement · Revenue is manually entered</div>
  </div>
</div>

<!-- Controls -->
<div class="pl-controls">
  <a href="?year=<?= $YEAR-1 ?>" class="yr-btn">← <?= $YEAR-1 ?></a>
  <span class="yr-current"><?= $YEAR ?></span>
  <a href="?year=<?= $YEAR+1 ?>" class="yr-btn"><?= $YEAR+1 ?> →</a>
  <a href="stella_disbursement.php" class="btn-export">📒 Go to Disbursement</a>
  <a href="stella_acc_title.php" class="sx-4 btn-export" >🏷 Account Titles</a>
  <a href="stella_sum.php?year=<?= $YEAR ?>" class="sx-3 btn-export" >📋 Sum</a>
  <a href="?year=<?= $YEAR ?>&export_csv=1" class="sx-2 btn-export" >⬇ Download</a>
  <span class="save-hint">💡 Click any revenue cell to edit, then press Enter or Tab to save</span>
</div>

<!-- Table -->
<div class="pl-wrap">
<table class="pl-table" id="plt">
  <thead>
    <tr>
      <th class="th-label">ACCOUNT TITLE</th>
      <?php foreach ($MONTHS as $m): ?>
      <th><?= $m ?></th>
      <?php endforeach; ?>
      <th class="th-total">TOTAL</th>
    </tr>
  </thead>
  <tbody>

    <!-- ── REVENUE ── -->
    <tr class="pl-section">
      <td class="td-label" colspan="14">REVENUE</td>
    </tr>
    <?php foreach ($REV_TYPES as $rk => $rl):
      $rowTotal = 0;
    ?>
    <tr data-rev="<?= $rk ?>">
      <td class="td-label"><?= htmlspecialchars($rl) ?></td>
      <?php for ($m = 1; $m <= 12; $m++):
        $val = $revenue[$m][$rk] ?? 0;
        $rowTotal += $val;
      ?>
      <td>
        <input type="text" class="rev-inp"
               data-year="<?= $YEAR ?>" data-month="<?= $m ?>" data-rev="<?= $rk ?>"
               value="<?= $val > 0 ? number_format($val, 2) : '' ?>"
               placeholder="-"
               onchange="saveRev(this)"
               onfocus="this.select()">
      </td>
      <?php endfor; ?>
      <td><span class="cell-total"><?= $rowTotal > 0 ? number_format($rowTotal,2) : '-' ?></span></td>
    </tr>
    <?php endforeach; ?>

    <!-- Revenue subtotal -->
    <tr class="pl-subtotal" id="rev-subtotal-row">
      <td class="td-label">Subtotal</td>
      <?php
        $revSubtotals = [];
        $revGrandTotal = 0;
        for ($m = 1; $m <= 12; $m++) {
            $s = 0;
            foreach ($REV_TYPES as $rk => $rl) $s += $revenue[$m][$rk] ?? 0;
            $revSubtotals[$m] = $s;
            $revGrandTotal += $s;
            echo '<td><span class="cell-total" id="rev-sub-' . $m . '">' . ($s > 0 ? number_format($s,2) : '-') . '</span></td>';
        }
      ?>
      <td><span class="cell-total" id="rev-grand"><?= $revGrandTotal > 0 ? number_format($revGrandTotal,2) : '-' ?></span></td>
    </tr>

    <!-- spacer -->
    <tr><td class="sx-1 td-label" colspan="14" ></td></tr>

    <!-- ── EXPENSES ── -->
    <tr class="pl-section">
      <td class="td-label" colspan="14">EXPENSES</td>
    </tr>

    <?php
    $expSubtotals = array_fill(1, 12, 0);
    $expGrandTotal = 0;
    foreach ($allExpTitles as $title):
        $rowTotal = 0;
    ?>
    <tr>
      <td class="td-label"><?= htmlspecialchars($title) ?></td>
      <?php for ($m = 1; $m <= 12; $m++):
        $val = $expenses[$title][$m] ?? 0;
        $rowTotal += $val;
        $expSubtotals[$m] += $val;
      ?>
      <td>
        <?php if ($val > 0): ?>
          <span class="cell-val"><?= number_format($val,2) ?></span>
        <?php else: ?>
          <span class="cell-val cell-zero">-</span>
        <?php endif; ?>
      </td>
      <?php endfor; ?>
      <td><span class="cell-total"><?= $rowTotal > 0 ? number_format($rowTotal,2) : '-' ?></span></td>
    </tr>
    <?php
      $expGrandTotal += $rowTotal;
    endforeach; ?>

    <!-- Expense subtotal -->
    <tr class="pl-subtotal">
      <td class="td-label">Subtotal.</td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <td><span class="cell-total"><?= $expSubtotals[$m] > 0 ? number_format($expSubtotals[$m],2) : '-' ?></span></td>
      <?php endfor; ?>
      <td><span class="cell-total"><?= $expGrandTotal > 0 ? number_format($expGrandTotal,2) : '-' ?></span></td>
    </tr>

    <!-- spacer -->
    <tr><td class="sx-1 td-label" colspan="14" ></td></tr>

    <!-- ── INCOME OVER EXPENSES ── -->
    <tr class="pl-net" id="net-row">
      <td class="td-label">Income over Expenses</td>
      <?php for ($m = 1; $m <= 12; $m++):
        $net = ($revSubtotals[$m] ?? 0) - ($expSubtotals[$m] ?? 0);
      ?>
      <td id="net-<?= $m ?>">
        <span class="cell-net-val <?= $net < 0 ? 'cell-neg' : '' ?>">
          <?= $net != 0 ? '(' . number_format(abs($net),2) . ')' . ($net < 0 ? '' : '') : '-' ?>
        </span>
      </td>
      <?php endfor; ?>
      <?php $netTotal = $revGrandTotal - $expGrandTotal; ?>
      <td><span class="cell-net-val <?= $netTotal < 0 ? 'cell-neg' : '' ?>">
        <?= $netTotal != 0 ? number_format(abs($netTotal),2) : '-' ?>
      </span></td>
    </tr>

  </tbody>
</table>
</div>

<script>
const YEAR = <?= $YEAR ?>;

// ── Save revenue cell ─────────────────────────────────────
async function saveRev(inp) {
    const raw = inp.value.replace(/,/g,'').trim();
    const amount = parseFloat(raw) || 0;
    inp.value = amount > 0 ? amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',') : '';
    const fd = new FormData();
    fd.append('ajax_save_rev','1');
    fd.append('year',   inp.dataset.year);
    fd.append('month',  inp.dataset.month);
    fd.append('rev_type', inp.dataset.rev);
    fd.append('amount', amount);
    try {
        const res  = await fetch('stella_profit_loss.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            showToast('✓ Saved', 'success');
            recalc();
        } else {
            showToast('❌ ' + data.msg, 'error');
        }
    } catch(e) { showToast('❌ Network error','error'); }
}

// ── Recalculate revenue subtotals + net row client-side ──
function recalc() {
    const revTypes = <?= json_encode(array_keys($REV_TYPES)) ?>;
    const expSubs  = <?= json_encode(array_values($expSubtotals)) ?>; // 12 values

    let revGrand = 0;
    for (let m = 1; m <= 12; m++) {
        let revSum = 0;
        revTypes.forEach(rt => {
            const inp = document.querySelector(`input[data-month="${m}"][data-rev="${rt}"]`);
            if (inp) revSum += parseFloat(inp.value.replace(/,/g,'')) || 0;
        });
        // Update revenue subtotal cell
        const subCell = document.getElementById('rev-sub-' + m);
        if (subCell) subCell.textContent = revSum > 0 ? fmt(revSum) : '-';
        revGrand += revSum;

        // Update net row
        const expSum = expSubs[m-1] || 0;
        const net = revSum - expSum;
        const netCell = document.getElementById('net-' + m);
        if (netCell) {
            const span = netCell.querySelector('span');
            span.textContent = net !== 0 ? '(' + fmt(Math.abs(net)) + ')' : '-';
            span.className = 'cell-net-val' + (net < 0 ? ' cell-neg' : '');
        }

        // Update row totals
        revTypes.forEach(rt => {
            const row = document.querySelector(`tr[data-rev="${rt}"]`);
            if (!row) return;
            let t = 0;
            row.querySelectorAll('input.rev-inp').forEach(i => t += parseFloat(i.value.replace(/,/g,''))||0);
            const lastTd = row.querySelector('td:last-child span');
            if (lastTd) lastTd.textContent = t > 0 ? fmt(t) : '-';
        });
    }
    const grandCell = document.getElementById('rev-grand');
    if (grandCell) grandCell.textContent = revGrand > 0 ? fmt(revGrand) : '-';
}

function fmt(n) {
    return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'flash flash-' + (type||'success') + ' toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>
</body>
</html>