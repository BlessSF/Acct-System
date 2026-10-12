<?php
// demiclab_cashflow_balance.php — DemicLab-Main Branch Cashflow Balance (Cash In / Cash Out / Running Balance)
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    http_response_code(403); exit('Access denied.');
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_cashflow_balance` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
    `txn_date`      date DEFAULT NULL,
    `description`   varchar(255) DEFAULT NULL,
    `cash_in`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash_out`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `entry_year`    int(4) NOT NULL,
    `entry_month`   tinyint(2) NOT NULL,
    `created_by`    varchar(100) DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Filters ───────────────────────────────────────────────────
$now        = new DateTime();
$fMonth     = (int)($_GET['month'] ?? $now->format('n'));
$fYear      = (int)($_GET['year']  ?? $now->format('Y'));
$fMonth     = max(1, min(12, $fMonth));
$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// ── AJAX: Add row ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    try {
        $date  = $_POST['txn_date']    ?: null;
        $desc  = trim($_POST['description'] ?? '');
        $in    = (float)($_POST['cash_in']  ?? 0);
        $out   = (float)($_POST['cash_out'] ?? 0);
        $yr    = (int)($_POST['entry_year']  ?? $fYear);
        $mo    = (int)($_POST['entry_month'] ?? $fMonth);
        $pdo->prepare("INSERT INTO demiclab_cashflow_balance (store_name,txn_date,description,cash_in,cash_out,entry_year,entry_month,created_by) VALUES ('DemicLab-Main',?,?,?,?,?,?,?)")
            ->execute([$date,$desc,$in,$out,$yr,$mo,$user['name']]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update row ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    try {
        $id   = (int)$_POST['id'];
        $date = $_POST['txn_date'] ?: null;
        $desc = trim($_POST['description'] ?? '');
        $in   = (float)($_POST['cash_in']  ?? 0);
        $out  = (float)($_POST['cash_out'] ?? 0);
        $pdo->prepare("UPDATE demiclab_cashflow_balance SET txn_date=?,description=?,cash_in=?,cash_out=? WHERE id=? AND store_name='DemicLab-Main'")
            ->execute([$date,$desc,$in,$out,$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM demiclab_cashflow_balance WHERE id=? AND store_name='DemicLab-Main'")
            ->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $rows = $pdo->prepare("SELECT * FROM demiclab_cashflow_balance WHERE store_name='DemicLab-Main' AND entry_year=? AND entry_month=? ORDER BY txn_date ASC, id ASC");
    $rows->execute([$fYear,$fMonth]);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="DemicLab-Main_CashflowBalance_'.$monthNames[$fMonth].'_'.$fYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['DemicLab-Main Branch — Cashflow Balance']);
    fputcsv($out,[$monthNames[$fMonth].' '.$fYear]);
    fputcsv($out,[]);
    fputcsv($out,['Date','Description','Cash In','Cash Out','Net Cash Flow','Running Balance']);
    $balance = 0;
    foreach ($rows as $r) {
        $net      = (float)$r['cash_in'] - (float)$r['cash_out'];
        $balance += $net;
        fputcsv($out,[
            $r['txn_date'],
            $r['description'],
            number_format((float)$r['cash_in'],2),
            number_format((float)$r['cash_out'],2),
            number_format($net,2),
            number_format($balance,2),
        ]);
    }
    fclose($out); exit;
}

// ── Fetch rows for display ────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM demiclab_cashflow_balance WHERE store_name='DemicLab-Main' AND entry_year=? AND entry_month=? ORDER BY txn_date ASC, id ASC");
$stmt->execute([$fYear,$fMonth]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Totals ────────────────────────────────────────────────────
$totalIn  = array_sum(array_column($rows,'cash_in'));
$totalOut = array_sum(array_column($rows,'cash_out'));
$netTotal = $totalIn - $totalOut;

$fmt = fn($n) => number_format((float)$n,2);

$pageTitle  = 'Cashflow Balance';
$activePage = 'demiclab_cashflow_balance';
include '../layout.php';
?>

<style>
.cb-wrap { max-width: 960px; margin: 0 auto; }

.cb-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.cb-header-card .eyebrow {
  font-family: var(--font-m); font-size: .58rem; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.cb-header-card .title  { font-size: 1.2rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.cb-header-card .subtitle { font-family: var(--font-m); font-size: .67rem; color: rgba(255,255,255,.5); margin-top: 4px; }

.cb-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
.cb-controls select { font-family: var(--font-m); font-size: .8rem; padding: 7px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); }

/* KPI cards */
.cb-kpi-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 20px; }
.cb-kpi { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 18px; }
.cb-kpi .kpi-label { font-family: var(--font-m); font-size: .65rem; text-transform: uppercase; letter-spacing: .1em; color: var(--subtext); margin-bottom: 6px; }
.cb-kpi .kpi-val   { font-family: var(--font-m); font-size: 1.3rem; font-weight: 800; }
.cb-kpi.in  .kpi-val { color: #166534; }
.cb-kpi.out .kpi-val { color: #991b1b; }
.cb-kpi.net .kpi-val { color: var(--text); }

/* Table */
.cb-table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
.cb-table { width: 100%; border-collapse: collapse; }
.cb-table thead th {
  background: #1e3060; color: #fff; padding: 10px 14px;
  font-family: var(--font-m); font-size: .65rem; text-transform: uppercase;
  letter-spacing: .08em; text-align: left; white-space: nowrap;
}
.cb-table thead th.num { text-align: right; }
.cb-table tbody tr { border-bottom: 1px solid #f0f2f5; transition: background .1s; }
.cb-table tbody tr:hover { background: #fafbfc; }
.cb-table tbody td { padding: 8px 14px; font-size: .8rem; color: var(--text); vertical-align: middle; }
.cb-table tbody td.num { text-align: right; font-family: var(--font-m); }
.cb-table tfoot td {
  padding: 10px 14px; font-family: var(--font-m); font-size: .8rem;
  font-weight: 700; border-top: 2px solid var(--border2); background: #f8f9fb;
}
.cb-table tfoot td.num { text-align: right; }

/* Inline inputs in table */
.cb-inp { width: 100%; border: none; background: transparent; font-family: var(--font-m); font-size: .8rem; color: var(--text); outline: none; }
.cb-inp:focus { background: #f0fdf4; border-radius: 4px; padding: 2px 4px; }
.cb-inp.num { text-align: right; }
.cb-inp.date-inp { width: 120px; }
td.running { font-weight: 700; font-family: var(--font-m); }
td.running.pos { color: #166534; }
td.running.neg { color: #991b1b; }
td.net-pos { color: #166534; font-family: var(--font-m); }
td.net-neg { color: #991b1b; font-family: var(--font-m); }

.btn-del { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 5px; padding: 3px 8px; font-size: .68rem; cursor: pointer; }
.btn-del:hover { background: #fee2e2; }
.cb-save {
  background: #ecfdf5; border: 1px solid #a7f3d0; color: #166534;
  border-radius: 5px; padding: 3px 9px; font-size: .68rem; font-weight: 700;
  cursor: pointer; margin-right: 4px; min-width: 52px;
}
.cb-save:hover  { background: #d1fae5; }
.cb-save.saving { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; cursor: wait; }
.cb-save.ok     { background: #dcfce7; border-color: #86efac; color: #15803d; }
.cb-save.err    { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
</style>

<div class="cb-wrap">

  <!-- Header -->
  <div class="cb-header-card">
    <div>
      <div class="eyebrow">DemicLab-Main Branch · Finance</div>
      <div class="title">Cashflow Balance</div>
      <div class="subtitle">Date · Description · Cash In / Out · Running Balance</div>
    </div>
    <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
                 font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
      📌 DemicLab-Main
    </span>
  </div>

  <!-- Controls -->
  <div class="cb-controls">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <select name="month" onchange="this.form.submit()">
        <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?=$m?>" <?=$m===$fMonth?'selected':''?>><?=$monthNames[$m]?></option>
        <?php endfor; ?>
      </select>
      <select name="year" onchange="this.form.submit()">
        <?php for($y=date('Y')+1;$y>=2023;$y--): ?>
          <option value="<?=$y?>" <?=$y===$fYear?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </form>
    <button onclick="addRow()" class="btn btn-primary btn-sm">+ Add Row</button>
    <a href="demiclab_cashflow_balance.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)">⬇ Download CSV</a>
  </div>

  <!-- KPI cards -->
  <div class="cb-kpi-row">
    <div class="cb-kpi in">
      <div class="kpi-label">Total Cash In</div>
      <div class="kpi-val" id="kpi-in"><?= $fmt($totalIn) ?></div>
    </div>
    <div class="cb-kpi out">
      <div class="kpi-label">Total Cash Out</div>
      <div class="kpi-val" id="kpi-out"><?= $fmt($totalOut) ?></div>
    </div>
    <div class="cb-kpi net">
      <div class="kpi-label">Net / Running Balance</div>
      <div class="kpi-val" id="kpi-net" style="color:<?= $netTotal >= 0 ? '#166534' : '#991b1b' ?>"><?= $fmt($netTotal) ?></div>
    </div>
  </div>

  <!-- Table -->
  <div class="cb-table-wrap">
    <table class="cb-table" id="cb-table">
      <thead>
        <tr>
          <th style="width:120px">Date</th>
          <th>Description</th>
          <th class="num" style="width:130px">Cash In</th>
          <th class="num" style="width:130px">Cash Out</th>
          <th class="num" style="width:130px">Net Cash Flow</th>
          <th class="num" style="width:140px">Running Balance</th>
          <th style="width:110px">Action</th>
        </tr>
      </thead>
      <tbody id="cb-body">
        <?php
        $balance = 0;
        foreach ($rows as $r):
            $net      = (float)$r['cash_in'] - (float)$r['cash_out'];
            $balance += $net;
            $netCls   = $net > 0 ? 'net-pos' : ($net < 0 ? 'net-neg' : '');
            $runCls   = $balance > 0 ? 'running pos' : ($balance < 0 ? 'running neg' : 'running');
        ?>
        <tr data-id="<?= $r['id'] ?>">
          <td><input class="cb-inp date-inp" type="date" value="<?= htmlspecialchars($r['txn_date'] ?? '') ?>" oninput="rowChanged(this)"></td>
          <td><input class="cb-inp" type="text" value="<?= htmlspecialchars($r['description'] ?? '') ?>" placeholder="Description" oninput="rowChanged(this)"></td>
          <td><input class="cb-inp num" type="number" step="0.01" value="<?= (float)$r['cash_in'] ?: '' ?>" placeholder="0.00" oninput="rowChanged(this)"></td>
          <td><input class="cb-inp num" type="number" step="0.01" value="<?= (float)$r['cash_out'] ?: '' ?>" placeholder="0.00" oninput="rowChanged(this)"></td>
          <td class="num <?= $netCls ?>"><?= $net != 0 ? $fmt($net) : '-' ?></td>
          <td class="<?= $runCls ?>"><?= $fmt($balance) ?></td>
          <td><button class="cb-save" onclick="saveRow(this)">Update</button><button class="btn-del" onclick="deleteRow(this)">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" style="font-weight:700;font-size:.78rem">TOTAL</td>
          <td class="num" id="foot-in"><?= $fmt($totalIn) ?></td>
          <td class="num" id="foot-out"><?= $fmt($totalOut) ?></td>
          <td class="num" id="foot-net" style="color:<?= $netTotal >= 0 ? '#166534' : '#991b1b' ?>"><?= $fmt($netTotal) ?></td>
          <td class="num" id="foot-bal" style="font-weight:800;color:<?= $netTotal >= 0 ? '#166534' : '#991b1b' ?>"><?= $fmt($netTotal) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="margin-top:14px">
    <button onclick="addRow()" class="btn btn-primary btn-sm">+ Add Row</button>
  </div>

</div>

<script>
const FMONTH = <?= $fYear ?> * 100 + <?= $fMonth ?>;
const FYEAR  = <?= $fYear ?>;
const FMONTH2= <?= $fMonth ?>;
const fmt    = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const gNum   = td => parseFloat(td.querySelector('input').value) || 0;

let newRowId = -1;

function addRow() {
  const tbody = document.getElementById('cb-body');
  const tr = document.createElement('tr');
  tr.dataset.id = newRowId--;
  tr.innerHTML = `
    <td><input class="cb-inp date-inp" type="date" oninput="rowChanged(this)"></td>
    <td><input class="cb-inp" type="text" placeholder="Description" oninput="rowChanged(this)"></td>
    <td><input class="cb-inp num" type="number" step="0.01" placeholder="0.00" oninput="rowChanged(this)"></td>
    <td><input class="cb-inp num" type="number" step="0.01" placeholder="0.00" oninput="rowChanged(this)"></td>
    <td class="num">-</td>
    <td class="running">0.00</td>
    <td><button class="cb-save" onclick="saveRow(this)">Save</button><button class="btn-del" onclick="deleteRow(this)">Del</button></td>
  `;
  tbody.appendChild(tr);
  tr.querySelector('input[type=date]').focus();
  recalc();
}

function rowChanged(input) {
  recalc();
  const tr  = input.closest('tr');
  const btn = tr.querySelector('.cb-save');
  if (btn) {
    btn.textContent = parseInt(tr.dataset.id) > 0 ? 'Update' : 'Save';
    btn.className   = 'cb-save';
  }
}

function recalc() {
  let totalIn=0, totalOut=0, balance=0;
  document.querySelectorAll('#cb-body tr').forEach(tr => {
    const inputs = tr.querySelectorAll('input[type=number]');
    const inVal  = parseFloat(inputs[0]?.value) || 0;
    const outVal = parseFloat(inputs[1]?.value) || 0;
    const net    = inVal - outVal;
    balance     += net;
    totalIn     += inVal;
    totalOut    += outVal;

    const cells = tr.querySelectorAll('td');
    const netCell = cells[4];
    const runCell = cells[5];

    netCell.textContent = net !== 0 ? fmt(net) : '-';
    netCell.className   = 'num ' + (net > 0 ? 'net-pos' : net < 0 ? 'net-neg' : '');
    runCell.textContent = fmt(balance);
    runCell.className   = 'running ' + (balance > 0 ? 'pos' : balance < 0 ? 'neg' : '');
  });

  const net = totalIn - totalOut;
  document.getElementById('foot-in').textContent  = fmt(totalIn);
  document.getElementById('foot-out').textContent = fmt(totalOut);
  document.getElementById('foot-net').textContent = fmt(net);
  document.getElementById('foot-net').style.color = net >= 0 ? '#166534' : '#991b1b';
  document.getElementById('foot-bal').textContent = fmt(net);
  document.getElementById('foot-bal').style.color = net >= 0 ? '#166534' : '#991b1b';

  document.getElementById('kpi-in').textContent  = fmt(totalIn);
  document.getElementById('kpi-out').textContent = fmt(totalOut);
  document.getElementById('kpi-net').textContent = fmt(net);
  document.getElementById('kpi-net').style.color = net >= 0 ? '#166534' : '#991b1b';
}

async function saveRow(btn) {
  const tr     = btn.closest('tr');
  const id     = parseInt(tr.dataset.id);
  const inputs = tr.querySelectorAll('input');
  const date   = inputs[0].value || '';
  const desc   = inputs[1].value || '';
  const inVal  = parseFloat(inputs[2].value) || 0;
  const outVal = parseFloat(inputs[3].value) || 0;

  const origLabel = btn.textContent;
  btn.textContent = '…'; btn.className = 'cb-save saving'; btn.disabled = true;

  const fd = new FormData();
  fd.append('txn_date',    date);
  fd.append('description', desc);
  fd.append('cash_in',     inVal.toFixed(2));
  fd.append('cash_out',    outVal.toFixed(2));
  fd.append('entry_year',  FYEAR);
  fd.append('entry_month', FMONTH2);
  fd.append(id > 0 ? 'ajax_update' : 'ajax_add', '1');
  if (id > 0) fd.append('id', id);

  try {
    const res  = await fetch('demiclab_cashflow_balance.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      if (id <= 0 && data.id) {
        tr.dataset.id = data.id;
      }
      btn.textContent = 'Update';
      btn.className   = 'cb-save ok';
      btn.disabled    = false;
      showToast('✓ Saved', 'success');
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'cb-save'; }, 2000);
    } else {
      btn.textContent = origLabel;
      btn.className   = 'cb-save err';
      btn.disabled    = false;
      showToast('❌ ' + (data.msg || 'Save failed'), 'error');
    }
  } catch (e) {
    btn.textContent = origLabel;
    btn.className   = 'cb-save err';
    btn.disabled    = false;
    showToast('❌ Network error while saving', 'error');
  }
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id);

  if (id > 0 && !confirm('Delete this entry? This cannot be undone.')) return;

  btn.disabled = true;

  if (id > 0) {
    try {
      const fd = new FormData();
      fd.append('ajax_delete','1');
      fd.append('id', id);
      const res  = await fetch('demiclab_cashflow_balance.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) {
        showToast('❌ ' + (data.msg || 'Delete failed'), 'error');
        btn.disabled = false;
        return;
      }
      showToast('✓ Deleted', 'success');
    } catch (e) {
      showToast('❌ Network error while deleting', 'error');
      btn.disabled = false;
      return;
    }
  }

  tr.remove();
  recalc();
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>
</body>
</html>