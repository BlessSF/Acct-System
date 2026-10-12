<?php
// ============================================================
//  commissary_daily_store_billing.php — Commissary: Daily Store Billing
//  Replica of DAILY_STORE_BILLING_2026.xlsx — one tab per store, each
//  keeping that store's own column format from the source workbook,
//  plus a cross-branch Summary tab (Monthly / Weekly billing rollup).
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Commissary branch + management can access
if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Branch format map — mirrors each sheet's own columns in the
//    source workbook. "primary" is the sheet's main figure (AMOUNT or
//    POS); "extra1"/"extra2" are the optional add-on columns (GULAY /
//    LETCHON); "total" is just the label used for the running total —
//    on SM and SPA the workbook actually calls that column "AMOUNT"
//    instead of "TOTAL", everything else works the same. ───────────
$BRANCHES = [
    'STELLA'          => ['label' => 'Stella',           'primary' => 'Amount', 'extra1' => 'Extra',  'extra2' => null,      'total' => 'Total'],
    'DOIS'            => ['label' => 'Dois',              'primary' => 'Amount', 'extra1' => 'Gulay',  'extra2' => 'Letchon', 'total' => 'Total'],
    'SM'              => ['label' => 'Pub SM City',       'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Amount'],
    'HERO'            => ['label' => 'Hero',               'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Total'],
    'WESTERN'         => ['label' => 'Socks Western',      'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Total'],
    'DND'             => ['label' => 'DND',                'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Total'],
    'MAAM_NIKKI'      => ['label' => 'Maam Nikki',         'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Total'],
    'SPA'             => ['label' => 'Spa',                'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Amount'],
    'ILONGGA_EXPRESS' => ['label' => 'Ilongga Express',    'primary' => 'POS',    'extra1' => 'Gulay',  'extra2' => null,      'total' => 'Total'],
];
// Maps the seed file's sheet-name keys (spaces) to our branch_key (underscores)
$SEED_KEY_MAP = [
    'STELLA' => 'STELLA', 'DOIS' => 'DOIS', 'SM' => 'SM', 'HERO' => 'HERO',
    'WESTERN' => 'WESTERN', 'DND' => 'DND', 'MAAM NIKKI' => 'MAAM_NIKKI',
    'SPA' => 'SPA', 'ILONGGA EXPRESS' => 'ILONGGA_EXPRESS',
];

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_store_billing` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `branch_key`    varchar(30) NOT NULL,
    `billing_date`  date NOT NULL,
    `primary_amt`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `extra1_amt`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `extra2_amt`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_amt`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`      varchar(100) DEFAULT NULL,
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_branch_date` (`branch_key`,`billing_date`),
    KEY `idx_branch` (`branch_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── One-time historical import from the source workbook ───
// Runs only the first time the table is empty — seeds every recorded
// day from DAILY_STORE_BILLING_2026.xlsx so history shows up right
// away instead of starting blank.
try {
    $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM commissary_store_billing")->fetchColumn();
    if ($rowCount === 0) {
        $seedPath = __DIR__ . '/data/daily_store_billing_seed.json';
        if (is_file($seedPath)) {
            $seed = json_decode(file_get_contents($seedPath), true) ?: [];
            $ins = $pdo->prepare("INSERT INTO commissary_store_billing
                (branch_key, billing_date, primary_amt, extra1_amt, extra2_amt, total_amt, saved_by)
                VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE primary_amt=VALUES(primary_amt), extra1_amt=VALUES(extra1_amt),
                    extra2_amt=VALUES(extra2_amt), total_amt=VALUES(total_amt)");
            $pdo->beginTransaction();
            foreach ($seed as $sheetKey => $rows) {
                $branchKey = $SEED_KEY_MAP[$sheetKey] ?? null;
                if (!$branchKey || !isset($BRANCHES[$branchKey])) continue;
                foreach ($rows as $r) {
                    $ins->execute([
                        $branchKey, $r['date'],
                        (float)$r['primary'], (float)$r['extra1'], (float)$r['extra2'], (float)$r['total'],
                        'Historical Import',
                    ]);
                }
            }
            $pdo->commit();
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

// ── AJAX: Save one store's month of billing rows ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_billing'])) {
    header('Content-Type: application/json');
    try {
        $branchKey = $_POST['branch_key'] ?? '';
        if (!isset($BRANCHES[$branchKey])) {
            echo json_encode(['ok' => false, 'msg' => 'Unknown store.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $ins = $pdo->prepare("INSERT INTO commissary_store_billing
            (branch_key, billing_date, primary_amt, extra1_amt, extra2_amt, total_amt, saved_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE primary_amt=VALUES(primary_amt), extra1_amt=VALUES(extra1_amt),
                extra2_amt=VALUES(extra2_amt), total_amt=VALUES(total_amt), saved_by=VALUES(saved_by)");
        $monthTotal = 0;
        foreach ($rows as $r) {
            $date = $r['date'] ?? null;
            if (!$date) continue;
            $primary = (float)($r['primary'] ?? 0);
            $extra1  = (float)($r['extra1'] ?? 0);
            $extra2  = (float)($r['extra2'] ?? 0);
            $total   = $primary + $extra1 + $extra2;
            $ins->execute([$branchKey, $date, $primary, $extra1, $extra2, $total, $user['name']]);
            $monthTotal += $total;
        }
        echo json_encode(['ok' => true, 'month_total' => round($monthTotal, 2)]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Fetch data for display ─────────────────────────────────
$tab     = $_GET['tab'] ?? 'STELLA';
$isSummary = ($tab === 'SUMMARY');
$fMonth  = $_GET['month'] ?? date('Y-m');
$fWeek   = $_GET['week']  ?? date('Y-m-d', strtotime('monday this week'));

if (!$isSummary && !isset($BRANCHES[$tab])) $tab = 'STELLA';

$storeRows = [];
$monthTotals = ['primary' => 0, 'extra1' => 0, 'extra2' => 0, 'total' => 0];

if (!$isSummary) {
    [$fYear, $fMon] = array_map('intval', explode('-', $fMonth));
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $fMon, 1, $fYear));

    $stmt = $pdo->prepare("SELECT billing_date, primary_amt, extra1_amt, extra2_amt, total_amt
        FROM commissary_store_billing WHERE branch_key=? AND billing_date BETWEEN ? AND ?");
    $startD = sprintf('%04d-%02d-01', $fYear, $fMon);
    $endD   = sprintf('%04d-%02d-%02d', $fYear, $fMon, $daysInMonth);
    $stmt->execute([$tab, $startD, $endD]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $existing[$r['billing_date']] = $r;

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $fYear, $fMon, $d);
        $ex = $existing[$dateStr] ?? null;
        $primary = $ex ? (float)$ex['primary_amt'] : 0;
        $extra1  = $ex ? (float)$ex['extra1_amt']  : 0;
        $extra2  = $ex ? (float)$ex['extra2_amt']  : 0;
        $total   = $ex ? (float)$ex['total_amt']   : 0;
        $storeRows[] = ['date' => $dateStr, 'primary' => $primary, 'extra1' => $extra1, 'extra2' => $extra2, 'total' => $total];
        $monthTotals['primary'] += $primary;
        $monthTotals['extra1']  += $extra1;
        $monthTotals['extra2']  += $extra2;
        $monthTotals['total']   += $total;
    }
}

// ── Summary tab data: Monthly rollup + Weekly rollup, both
//    computed live from the daily entries (not re-typed by hand) ──
$monthlyRollup = [];
$weeklyRollup  = [];
if ($isSummary) {
    [$fYear, $fMon] = array_map('intval', explode('-', $fMonth));
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $fMon, 1, $fYear));
    $startD = sprintf('%04d-%02d-01', $fYear, $fMon);
    $endD   = sprintf('%04d-%02d-%02d', $fYear, $fMon, $daysInMonth);
    $mStmt = $pdo->prepare("SELECT branch_key, SUM(primary_amt) AS s_primary,
            SUM(extra1_amt + extra2_amt) AS s_others, SUM(total_amt) AS s_total
        FROM commissary_store_billing WHERE billing_date BETWEEN ? AND ? GROUP BY branch_key");
    $mStmt->execute([$startD, $endD]);
    foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $monthlyRollup[$r['branch_key']] = $r;

    $weekStart = $fWeek;
    $weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    $wStmt = $pdo->prepare("SELECT branch_key, SUM(primary_amt) AS s_primary,
            SUM(extra1_amt + extra2_amt) AS s_others, SUM(total_amt) AS s_total
        FROM commissary_store_billing WHERE billing_date BETWEEN ? AND ? GROUP BY branch_key");
    $wStmt->execute([$weekStart, $weekEnd]);
    foreach ($wStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $weeklyRollup[$r['branch_key']] = $r;
}

$pageTitle  = 'Daily Store Billing';
$activePage = 'commissary_daily_store_billing';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.dsb-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:16px; border-bottom:2px solid var(--border); padding-bottom:0; }
.dsb-tab {
  padding:9px 16px; border-radius:8px 8px 0 0; font-family:var(--font-m); font-size:.7rem; font-weight:700;
  color:var(--subtext); text-decoration:none; border:1px solid transparent; border-bottom:none;
  transition:all .15s;
}
.dsb-tab:hover { background:#f8f9fb; color:var(--text); }
.dsb-tab.active { background:var(--surface); color:var(--accent); border-color:var(--border); position:relative; top:2px; }
.dsb-tab.summary-tab { margin-left:auto; }

.dsb-controls { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
.dsb-controls label { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; color:var(--subtext2); }

.dsb-outer { width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border); background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); }
.dsb-table { border-collapse:collapse; width:100%; font-size:.75rem; }
.dsb-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.62rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:9px 8px; border:1px solid #2d5480; text-align:right;
}
.dsb-table thead th:first-child { text-align:left; }
.dsb-table tbody td { padding:5px 8px; border:1px solid var(--border); text-align:right; }
.dsb-table tbody td:first-child { text-align:left; font-family:var(--font-m); font-size:.68rem; color:var(--subtext); white-space:nowrap; }
.dsb-table tbody tr:nth-child(even) { background:#f9fafb; }
.dsb-table tbody tr:hover { background:#eef4ff; }
.dsb-table tbody tr.weekend td:first-child { color:#b45309; }
.dsb-table input {
  width:100%; border:none; background:transparent; text-align:right; font-family:var(--font-m); font-size:.75rem;
  padding:3px 2px; border-radius:4px;
}
.dsb-table input:focus { background:#fff8e1; outline:1px solid var(--accent); }
.dsb-table td.dsb-total-cell { font-weight:700; color:var(--text); background:#f4f6fa; }
.dsb-table tfoot td { padding:8px; border:1px solid var(--border); font-family:var(--font-m); font-weight:800; text-align:right; background:#eef2ff; color:#3730a3; }
.dsb-table tfoot td:first-child { text-align:left; }

.dsb-summary-block { margin-bottom:28px; }
.dsb-summary-title { font-family:var(--font-m); font-size:.85rem; font-weight:800; color:var(--text); margin-bottom:10px; }
</style>

<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Daily Store Billing</div>
    <div class="section-subtitle">Commissary → Store billing ledger · mirrors DAILY_STORE_BILLING_2026.xlsx, one tab per store</div>
  </div>
</div>

<div class="dsb-tabs">
  <?php foreach ($BRANCHES as $key => $meta): ?>
  <a href="?tab=<?= urlencode($key) ?>&month=<?= htmlspecialchars($fMonth) ?>" class="dsb-tab <?= (!$isSummary && $tab===$key) ? 'active' : '' ?>"><?= htmlspecialchars($meta['label']) ?></a>
  <?php endforeach; ?>
  <a href="?tab=SUMMARY&month=<?= htmlspecialchars($fMonth) ?>&week=<?= htmlspecialchars($fWeek) ?>" class="dsb-tab summary-tab <?= $isSummary ? 'active' : '' ?>">📊 Summary</a>
</div>

<?php if (!$isSummary): $meta = $BRANCHES[$tab]; ?>

<div class="dsb-controls">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <label>Month</label>
    <input type="month" name="month" class="form-control" style="width:160px" value="<?= htmlspecialchars($fMonth) ?>" onchange="this.form.submit()">
  </form>
  <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
  <span id="saveStatus" class="sr-save-status"></span>
</div>

<div class="dsb-outer">
<table class="dsb-table" id="dsbTable">
  <thead>
    <tr>
      <th>Date</th>
      <th><?= htmlspecialchars($meta['primary']) ?></th>
      <?php if ($meta['extra1']): ?><th><?= htmlspecialchars($meta['extra1']) ?></th><?php endif; ?>
      <?php if ($meta['extra2']): ?><th><?= htmlspecialchars($meta['extra2']) ?></th><?php endif; ?>
      <th><?= htmlspecialchars($meta['total']) ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($storeRows as $i => $row): $isWeekend = in_array(date('N', strtotime($row['date'])), [6,7]); ?>
    <tr data-date="<?= $row['date'] ?>" class="<?= $isWeekend ? 'weekend' : '' ?>">
      <td><?= date('D, M j', strtotime($row['date'])) ?></td>
      <td><input type="text" class="dsb-primary" value="<?= $row['primary'] ? number_format($row['primary'],2) : '' ?>" oninput="recalcRow(this)"></td>
      <?php if ($meta['extra1']): ?><td><input type="text" class="dsb-extra1" value="<?= $row['extra1'] ? number_format($row['extra1'],2) : '' ?>" oninput="recalcRow(this)"></td><?php endif; ?>
      <?php if ($meta['extra2']): ?><td><input type="text" class="dsb-extra2" value="<?= $row['extra2'] ? number_format($row['extra2'],2) : '' ?>" oninput="recalcRow(this)"></td><?php endif; ?>
      <td class="dsb-total-cell dsb-total"><?= $row['total'] ? number_format($row['total'],2) : '0.00' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td>Total</td>
      <td><?= number_format($monthTotals['primary'],2) ?></td>
      <?php if ($meta['extra1']): ?><td><?= number_format($monthTotals['extra1'],2) ?></td><?php endif; ?>
      <?php if ($meta['extra2']): ?><td><?= number_format($monthTotals['extra2'],2) ?></td><?php endif; ?>
      <td id="monthGrandTotal"><?= number_format($monthTotals['total'],2) ?></td>
    </tr>
  </tfoot>
</table>
</div>

<script>
const BRANCH_KEY = '<?= $tab ?>';
const ENDPOINT   = 'commissary_daily_store_billing.php';

const flt = v => parseFloat(String(v ?? '').replace(/,/g,'')) || 0;
const fmt = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

function recalcRow(el) {
  const tr = el.closest('tr');
  const primary = flt(tr.querySelector('.dsb-primary')?.value);
  const extra1  = flt(tr.querySelector('.dsb-extra1')?.value);
  const extra2  = flt(tr.querySelector('.dsb-extra2')?.value);
  const total = primary + extra1 + extra2;
  tr.querySelector('.dsb-total').textContent = fmt(total);
  recalcGrandTotal();
}

function recalcGrandTotal() {
  let grand = 0;
  document.querySelectorAll('#dsbTable tbody tr').forEach(tr => {
    grand += flt(tr.querySelector('.dsb-total').textContent);
  });
  document.getElementById('monthGrandTotal').textContent = fmt(grand);
}

async function saveAll() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';
  const rows = [];
  document.querySelectorAll('#dsbTable tbody tr').forEach(tr => {
    rows.push({
      date: tr.dataset.date,
      primary: flt(tr.querySelector('.dsb-primary')?.value),
      extra1:  flt(tr.querySelector('.dsb-extra1')?.value),
      extra2:  flt(tr.querySelector('.dsb-extra2')?.value),
    });
  });
  const fd = new FormData();
  fd.append('ajax_save_billing', '1');
  fd.append('branch_key', BRANCH_KEY);
  fd.append('rows', JSON.stringify(rows));
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
      showToast('✓ Billing saved', 'success');
    } else {
      showToast('❌ ' + (data.msg || 'Save failed'), 'error'); status.textContent = '❌ Error';
    }
  } catch (e) {
    showToast('❌ Network error', 'error'); status.textContent = '❌ Error';
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>

<?php else: /* ── SUMMARY TAB ─────────────────────────────── */ ?>

<div class="dsb-controls">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <input type="hidden" name="tab" value="SUMMARY">
    <input type="hidden" name="week" value="<?= htmlspecialchars($fWeek) ?>">
    <label>Month</label>
    <input type="month" name="month" class="form-control" style="width:160px" value="<?= htmlspecialchars($fMonth) ?>" onchange="this.form.submit()">
  </form>
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <input type="hidden" name="tab" value="SUMMARY">
    <input type="hidden" name="month" value="<?= htmlspecialchars($fMonth) ?>">
    <label>Week starting (Mon)</label>
    <input type="date" name="week" class="form-control" style="width:160px" value="<?= htmlspecialchars($fWeek) ?>" onchange="this.form.submit()">
  </form>
</div>

<div class="dsb-summary-block">
  <div class="dsb-summary-title">📅 Store Monthly Billing — <?= date('F Y', strtotime($fMonth.'-01')) ?></div>
  <div class="dsb-outer">
  <table class="dsb-table">
    <thead><tr><th>Branch</th><th>POS / Amount</th><th>Others</th><th>Total</th></tr></thead>
    <tbody>
      <?php $gPrimary=0; $gOthers=0; $gTotal=0; foreach ($BRANCHES as $key => $meta): $r = $monthlyRollup[$key] ?? null;
        $p = $r ? (float)$r['s_primary'] : 0; $o = $r ? (float)$r['s_others'] : 0; $t = $r ? (float)$r['s_total'] : 0;
        $gPrimary += $p; $gOthers += $o; $gTotal += $t; ?>
      <tr>
        <td><?= htmlspecialchars($meta['label']) ?></td>
        <td><?= number_format($p,2) ?></td>
        <td><?= number_format($o,2) ?></td>
        <td class="dsb-total-cell"><?= number_format($t,2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td>Total</td><td><?= number_format($gPrimary,2) ?></td><td><?= number_format($gOthers,2) ?></td><td><?= number_format($gTotal,2) ?></td></tr></tfoot>
  </table>
  </div>
</div>

<div class="dsb-summary-block">
  <div class="dsb-summary-title">🗓️ Store Weekly Billing — <?= date('M j', strtotime($fWeek)) ?> to <?= date('M j, Y', strtotime($fWeek.' +6 days')) ?></div>
  <div class="dsb-outer">
  <table class="dsb-table">
    <thead><tr><th>Branch</th><th>POS / Amount</th><th>Others</th><th>Total</th></tr></thead>
    <tbody>
      <?php $gPrimary=0; $gOthers=0; $gTotal=0; foreach ($BRANCHES as $key => $meta): $r = $weeklyRollup[$key] ?? null;
        $p = $r ? (float)$r['s_primary'] : 0; $o = $r ? (float)$r['s_others'] : 0; $t = $r ? (float)$r['s_total'] : 0;
        $gPrimary += $p; $gOthers += $o; $gTotal += $t; ?>
      <tr>
        <td><?= htmlspecialchars($meta['label']) ?></td>
        <td><?= number_format($p,2) ?></td>
        <td><?= number_format($o,2) ?></td>
        <td class="dsb-total-cell"><?= number_format($t,2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td>Total</td><td><?= number_format($gPrimary,2) ?></td><td><?= number_format($gOthers,2) ?></td><td><?= number_format($gTotal,2) ?></td></tr></tfoot>
  </table>
  </div>
</div>

<?php endif; ?>

</body>
</html>
