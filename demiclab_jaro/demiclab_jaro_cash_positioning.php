<?php
// demiclab_jaro_cash_positioning.php — DemicLab-Jaro Branch Cash Positioning
// (Cash in Bank / Deposit in Transit / A/R / Outstanding Check / Net Cash / Starting Fund / CAPEX)
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    http_response_code(403); exit('Access denied.');
}

$pdo  = getPDO();
$user = currentUser();

// ── Create tables if not exists ─────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_jaro_cash_positioning` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `store_name`          varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
    `entry_year`          int(4) NOT NULL,
    `entry_month`         tinyint(2) NOT NULL,
    `cash_in_bank`        decimal(14,2) NOT NULL DEFAULT 0.00,
    `deposit_in_transit`  decimal(14,2) NOT NULL DEFAULT 0.00,
    `accounts_receivable` decimal(14,2) NOT NULL DEFAULT 0.00,
    `outstanding_check`   decimal(14,2) NOT NULL DEFAULT 0.00,
    `starting_fund`       decimal(14,2) NOT NULL DEFAULT 0.00,
    `updated_by`          varchar(100) DEFAULT NULL,
    `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_period` (`store_name`,`entry_year`,`entry_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_jaro_cash_positioning_capex` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
    `entry_year`    int(4) NOT NULL,
    `entry_month`   tinyint(2) NOT NULL,
    `item`          varchar(255) DEFAULT NULL,
    `amount`        decimal(14,2) NOT NULL DEFAULT 0.00,
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

// Last calendar day of the selected month, for "MONTH ENDING"
$monthEndDate = (new DateTime("$fYear-$fMonth-01"))->modify('last day of this month');
$monthEndingLabel = $monthEndDate->format('F j, Y');

// ── AJAX: Save main figures (upsert) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_main'])) {
    header('Content-Type: application/json');
    try {
        $yr  = (int)($_POST['entry_year']  ?? $fYear);
        $mo  = (int)($_POST['entry_month'] ?? $fMonth);
        $cib = (float)($_POST['cash_in_bank']        ?? 0);
        $dit = (float)($_POST['deposit_in_transit']  ?? 0);
        $ar  = (float)($_POST['accounts_receivable'] ?? 0);
        $oc  = (float)($_POST['outstanding_check']   ?? 0);
        $sf  = (float)($_POST['starting_fund']       ?? 0);
        $pdo->prepare("INSERT INTO demiclab_jaro_cash_positioning
                (store_name,entry_year,entry_month,cash_in_bank,deposit_in_transit,accounts_receivable,outstanding_check,starting_fund,updated_by)
             VALUES ('DemicLab-Jaro',?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                cash_in_bank=VALUES(cash_in_bank), deposit_in_transit=VALUES(deposit_in_transit),
                accounts_receivable=VALUES(accounts_receivable), outstanding_check=VALUES(outstanding_check),
                starting_fund=VALUES(starting_fund), updated_by=VALUES(updated_by)")
            ->execute([$yr,$mo,$cib,$dit,$ar,$oc,$sf,$user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Add CAPEX row ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_capex'])) {
    header('Content-Type: application/json');
    try {
        $item = trim($_POST['item'] ?? '');
        $amt  = (float)($_POST['amount'] ?? 0);
        $yr   = (int)($_POST['entry_year']  ?? $fYear);
        $mo   = (int)($_POST['entry_month'] ?? $fMonth);
        $pdo->prepare("INSERT INTO demiclab_jaro_cash_positioning_capex (store_name,entry_year,entry_month,item,amount,created_by) VALUES ('DemicLab-Jaro',?,?,?,?,?)")
            ->execute([$yr,$mo,$item,$amt,$user['name']]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update CAPEX row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_capex'])) {
    header('Content-Type: application/json');
    try {
        $id   = (int)$_POST['id'];
        $item = trim($_POST['item'] ?? '');
        $amt  = (float)($_POST['amount'] ?? 0);
        $pdo->prepare("UPDATE demiclab_jaro_cash_positioning_capex SET item=?, amount=? WHERE id=? AND store_name='DemicLab-Jaro'")
            ->execute([$item,$amt,$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete CAPEX row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_capex'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM demiclab_jaro_cash_positioning_capex WHERE id=? AND store_name='DemicLab-Jaro'")
            ->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Fetch main figures for period ────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM demiclab_jaro_cash_positioning WHERE store_name='DemicLab-Jaro' AND entry_year=? AND entry_month=?");
$stmt->execute([$fYear,$fMonth]);
$main = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'cash_in_bank'=>0,'deposit_in_transit'=>0,'accounts_receivable'=>0,'outstanding_check'=>0,'starting_fund'=>0
];

// ── Fetch CAPEX rows for period ──────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM demiclab_jaro_cash_positioning_capex WHERE store_name='DemicLab-Jaro' AND entry_year=? AND entry_month=? ORDER BY id ASC");
$stmt->execute([$fYear,$fMonth]);
$capexRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── CSV Export ────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $cashInBank = (float)$main['cash_in_bank'];
    $deposit    = (float)$main['deposit_in_transit'];
    $ar         = (float)$main['accounts_receivable'];
    $outCheck   = (float)$main['outstanding_check'];
    $startFund  = (float)$main['starting_fund'];
    $netCash    = $cashInBank + $deposit + $ar - $outCheck;
    $capexTotal = array_sum(array_column($capexRows,'amount'));
    $increase   = $netCash - $startFund;
    $netAfter   = $increase + $capexTotal;
    header('Content-Disposition: attachment; filename="DemicLab-Jaro_CashPositioning_'.$monthNames[$fMonth].'_'.$fYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['DemicLab-Jaro Branch — Cash Positioning']);
    fputcsv($out,['MONTH ENDING', $monthEndingLabel]);
    fputcsv($out,[]);
    fputcsv($out,['CASH IN BANK',        number_format($cashInBank,2)]);
    fputcsv($out,['DEPOSIT IN TRANSIT',  number_format($deposit,2)]);
    fputcsv($out,['', number_format($cashInBank + $deposit,2)]);
    fputcsv($out,[]);
    fputcsv($out,['ACCTS RECEIVABLES', number_format($ar,2)]);
    fputcsv($out,['OUSTANDING CHECK',   number_format($outCheck,2)]);
    fputcsv($out,[]);
    fputcsv($out,['NET CASH',            number_format($netCash,2)]);
    fputcsv($out,['STARTING FUND',       number_format($startFund,2)]);
    fputcsv($out,[]);
    fputcsv($out,['CASH INCREASE (DECREASE)', number_format($increase,2)]);
    fputcsv($out,['CAPEX', number_format($capexTotal,2)]);
    fputcsv($out,['TOTAL CASH INCREASE (DECREASE)', number_format($netAfter,2)]);
    fputcsv($out,[]);
    fputcsv($out,['CAPEX Breakdown','Amount']);
    foreach ($capexRows as $r) {
        fputcsv($out,[$r['item'], number_format((float)$r['amount'],2)]);
    }
    fputcsv($out,['CAPEX TOTAL', number_format($capexTotal,2)]);
    fclose($out); exit;
}

// ── Computations ──────────────────────────────────────────────
$cashInBank = (float)$main['cash_in_bank'];
$deposit    = (float)$main['deposit_in_transit'];
$ar         = (float)$main['accounts_receivable'];
$outCheck   = (float)$main['outstanding_check'];
$startFund  = (float)$main['starting_fund'];

$netCash    = $cashInBank + $deposit + $ar - $outCheck;
$capexTotal = array_sum(array_column($capexRows,'amount'));
$increase   = $netCash - $startFund;
$netAfter   = $increase + $capexTotal;

$fmt = fn($n) => number_format((float)$n,2);

$pageTitle  = 'Cash Positioning';
$activePage = 'demiclab_jaro_cash_positioning';
include '../layout.php';
?>

<style>
.cp-wrap { max-width: 620px; margin: 0 auto; }

.cp-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 16px 22px 13px;
  margin-bottom: 16px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.cp-header-card .eyebrow {
  font-family: var(--font-m); font-size: .58rem; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.cp-header-card .title  { font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.cp-header-card .subtitle { font-family: var(--font-m); font-size: .66rem; color: rgba(255,255,255,.55); margin-top: 3px; }

.cp-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.cp-controls select { font-family: var(--font-m); font-size: .78rem; padding: 6px 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); }

.cp-save-status { font-family: var(--font-m); font-size: .62rem; color: var(--subtext); margin: 8px 2px 18px; min-height: 14px; }

/* ── Compact spreadsheet-style table ── */
.mini-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 22px; }
table.mini { border-collapse: collapse; width: 100%; font-family: var(--font-m); font-size: .8rem; }
table.mini td { border: 1px solid #d7dbe2; padding: 6px 10px; color: #1f2937; }
table.mini td.lbl { font-weight: 600; letter-spacing: .01em; }
table.mini td.val { text-align: right; }
table.mini tr.head td { background: #f8d6de; font-weight: 800; }
table.mini tr.head td.val { font-weight: 700; }
table.mini tr.shade td { background: #dde7fa; }
table.mini tr.shade-strong td { background: #c9d9f4; font-weight: 800; }
table.mini tr.spacer td { border: none; background: transparent; height: 10px; padding: 0; }
table.mini input.mini-inp {
  width: 100%; border: none; background: transparent; text-align: right;
  font-family: var(--font-m); font-size: .8rem; font-weight: inherit; color: inherit; outline: none;
}
table.mini input.mini-inp:focus { background: rgba(255,255,255,.65); border-radius: 4px; }
table.mini tr.shade input.mini-inp,
table.mini tr.shade-strong input.mini-inp { background: transparent; }

.mini-actions { display: flex; justify-content: flex-end; margin-top: 10px; }

/* ── CAPEX breakdown (add section) ── */
.cx-title { font-size: .8rem; font-weight: 800; margin: 0 0 10px 2px; color: var(--text); }
.cp-table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
.cp-table { width: 100%; border-collapse: collapse; }
.cp-table thead th {
  background: #1e3060; color: #fff; padding: 8px 12px;
  font-family: var(--font-m); font-size: .62rem; text-transform: uppercase;
  letter-spacing: .08em; text-align: left; white-space: nowrap;
}
.cp-table thead th.num { text-align: right; }
.cp-table tbody tr { border-bottom: 1px solid #f0f2f5; }
.cp-table tbody tr:hover { background: #fafbfc; }
.cp-table tbody td { padding: 6px 12px; font-size: .78rem; color: var(--text); vertical-align: middle; }
.cp-table tbody td.num { text-align: right; font-family: var(--font-m); }
.cp-table tfoot td { padding: 8px 12px; font-family: var(--font-m); font-size: .78rem; font-weight: 700; border-top: 2px solid var(--border2); background: #f8f9fb; }
.cp-table tfoot td.num { text-align: right; }

.cp-inp { width: 100%; border: none; background: transparent; font-family: var(--font-m); font-size: .78rem; color: var(--text); outline: none; }
.cp-inp:focus { background: #f0fdf4; border-radius: 4px; padding: 2px 4px; }
.cp-inp.num { text-align: right; }

.btn-del { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 5px; padding: 3px 8px; font-size: .66rem; cursor: pointer; }
.btn-del:hover { background: #fee2e2; }
.cp-save { background: #ecfdf5; border: 1px solid #a7f3d0; color: #166534; border-radius: 5px; padding: 3px 9px; font-size: .66rem; font-weight: 700; cursor: pointer; margin-right: 4px; min-width: 50px; }
.cp-save:hover  { background: #d1fae5; }
.cp-save.saving { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; cursor: wait; }
.cp-save.ok     { background: #dcfce7; border-color: #86efac; color: #15803d; }
.cp-save.err    { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
</style>

<div class="cp-wrap">

  <!-- Header -->
  <div class="cp-header-card">
    <div>
      <div class="eyebrow">DemicLab-Jaro Branch · Finance</div>
      <div class="title">Cash Positioning</div>
      <div class="subtitle">Month Ending <?= $monthEndingLabel ?></div>
    </div>
    <span style="background:rgba(255,255,255,.1);color:#fff;padding:4px 12px;border-radius:20px;
                 font-family:var(--font-m);font-size:.62rem;font-weight:600;align-self:flex-start">
      📌 DemicLab-Jaro
    </span>
  </div>

  <!-- Controls -->
  <div class="cp-controls">
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
    <a href="demiclab_jaro_cash_positioning.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)">⬇ Download CSV</a>
  </div>

  <!-- Compact spreadsheet-style summary -->
  <div class="mini-wrap">
    <table class="mini">
      <tr class="head">
        <td class="lbl">MONTH ENDING</td>
        <td class="val"><?= $monthEndingLabel ?></td>
      </tr>
      <tr>
        <td class="lbl">CASH IN BANK</td>
        <td class="val"><input class="mini-inp" type="number" step="0.01" id="f-cash_in_bank" value="<?= $cashInBank ?: '' ?>" placeholder="0.00" oninput="calcAndFlag()"></td>
      </tr>
      <tr>
        <td class="lbl">DEPOSIT IN TRANSIT</td>
        <td class="val"><input class="mini-inp" type="number" step="0.01" id="f-deposit_in_transit" value="<?= $deposit ?: '' ?>" placeholder="0.00" oninput="calcAndFlag()"></td>
      </tr>
      <tr class="shade">
        <td class="lbl">&nbsp;</td>
        <td class="val" id="calc-subtotal"><?= $fmt($cashInBank + $deposit) ?></td>
      </tr>
      <tr class="spacer"><td colspan="2"></td></tr>

      <tr>
        <td class="lbl">ACCTS RECEIVABLES</td>
        <td class="val"><input class="mini-inp" type="number" step="0.01" id="f-accounts_receivable" value="<?= $ar ?: '' ?>" placeholder="0.00" oninput="calcAndFlag()"></td>
      </tr>
      <tr>
        <td class="lbl">OUSTANDING CHECK</td>
        <td class="val"><input class="mini-inp" type="number" step="0.01" id="f-outstanding_check" value="<?= $outCheck ?: '' ?>" placeholder="0.00" oninput="calcAndFlag()"></td>
      </tr>
      <tr class="spacer"><td colspan="2"></td></tr>

      <tr class="shade">
        <td class="lbl">NET CASH</td>
        <td class="val" id="calc-netcash"><?= $fmt($netCash) ?></td>
      </tr>
      <tr>
        <td class="lbl">STARTING FUND</td>
        <td class="val"><input class="mini-inp" type="number" step="0.01" id="f-starting_fund" value="<?= $startFund ?: '' ?>" placeholder="0.00" oninput="calcAndFlag()"></td>
      </tr>
      <tr class="spacer"><td colspan="2"></td></tr>

      <tr class="shade">
        <td class="lbl">CASH INCREASE (DECREASE)</td>
        <td class="val" id="calc-increase"><?= $fmt($increase) ?></td>
      </tr>
      <tr class="shade">
        <td class="lbl">CAPEX</td>
        <td class="val" id="calc-capex"><?= $fmt($capexTotal) ?></td>
      </tr>
      <tr class="shade-strong">
        <td class="lbl">TOTAL CASH INCREASE (DECREASE)</td>
        <td class="val" id="calc-netafter"><?= $fmt($netAfter) ?></td>
      </tr>
    </table>

    <div class="mini-actions">
      <button onclick="saveMain(this)" class="btn btn-primary btn-sm">Save Cash Position</button>
    </div>
    <div class="cp-save-status" id="save-status" style="margin-bottom:0"></div>
  </div>

  <!-- CAPEX Breakdown (add section) -->
  <div class="cx-title">CAPEX Breakdown</div>
  <div class="cp-table-wrap">
    <table class="cp-table" id="cp-table">
      <thead>
        <tr>
          <th>Item</th>
          <th class="num" style="width:140px">Amount</th>
          <th style="width:100px">Action</th>
        </tr>
      </thead>
      <tbody id="cp-body">
        <?php foreach ($capexRows as $r): ?>
        <tr data-id="<?= $r['id'] ?>">
          <td><input class="cp-inp" type="text" value="<?= htmlspecialchars($r['item'] ?? '') ?>" placeholder="e.g. BIR, CCTV System" oninput="rowChanged(this)"></td>
          <td><input class="cp-inp num" type="number" step="0.01" value="<?= (float)$r['amount'] ?: '' ?>" placeholder="0.00" oninput="rowChanged(this)"></td>
          <td><button class="cp-save" onclick="saveCapexRow(this)">Update</button><button class="btn-del" onclick="deleteCapexRow(this)">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td style="font-weight:700">CAPEX TOTAL</td>
          <td class="num" id="foot-capex"><?= $fmt($capexTotal) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div style="margin-top:12px">
    <button onclick="addCapexRow()" class="btn btn-primary btn-sm">+ Add Section / CAPEX Item</button>
  </div>

</div>

<script>
const FYEAR   = <?= $fYear ?>;
const FMONTH  = <?= $fMonth ?>;
const fmt     = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
let newRowId  = -1;

function getVal(id) { return parseFloat(document.getElementById(id).value) || 0; }

function calcAndFlag() {
  const cib = getVal('f-cash_in_bank');
  const dit = getVal('f-deposit_in_transit');
  const ar  = getVal('f-accounts_receivable');
  const oc  = getVal('f-outstanding_check');
  const sf  = getVal('f-starting_fund');
  const capexTotal = currentCapexTotal();

  const subtotal = cib + dit;
  const netCash  = subtotal + ar - oc;
  const increase = netCash - sf;
  const netAfter = increase + capexTotal;

  document.getElementById('calc-subtotal').textContent = fmt(subtotal);
  document.getElementById('calc-netcash').textContent  = fmt(netCash);
  document.getElementById('calc-increase').textContent = fmt(increase);
  document.getElementById('calc-capex').textContent    = fmt(capexTotal);
  document.getElementById('calc-netafter').textContent = fmt(netAfter);

  document.getElementById('save-status').textContent = 'Unsaved changes — click "Save Cash Position" to persist.';
}

function currentCapexTotal() {
  let total = 0;
  document.querySelectorAll('#cp-body tr').forEach(tr => {
    const amt = parseFloat(tr.querySelectorAll('input')[1]?.value) || 0;
    total += amt;
  });
  return total;
}

async function saveMain(btn) {
  const origLabel = btn.textContent;
  btn.textContent = 'Saving…'; btn.disabled = true;

  const fd = new FormData();
  fd.append('ajax_save_main', '1');
  fd.append('entry_year',  FYEAR);
  fd.append('entry_month', FMONTH);
  fd.append('cash_in_bank',        getVal('f-cash_in_bank'));
  fd.append('deposit_in_transit',  getVal('f-deposit_in_transit'));
  fd.append('accounts_receivable', getVal('f-accounts_receivable'));
  fd.append('outstanding_check',   getVal('f-outstanding_check'));
  fd.append('starting_fund',       getVal('f-starting_fund'));

  try {
    const res  = await fetch('demiclab_jaro_cash_positioning.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = origLabel; btn.disabled = false;
      document.getElementById('save-status').textContent = '✓ Saved just now.';
      showToast('✓ Cash position saved', 'success');
    } else {
      btn.textContent = origLabel; btn.disabled = false;
      showToast('❌ ' + (data.msg || 'Save failed'), 'error');
    }
  } catch (e) {
    btn.textContent = origLabel; btn.disabled = false;
    showToast('❌ Network error while saving', 'error');
  }
}

function addCapexRow() {
  const tbody = document.getElementById('cp-body');
  const tr = document.createElement('tr');
  tr.dataset.id = newRowId--;
  tr.innerHTML = `
    <td><input class="cp-inp" type="text" placeholder="e.g. BIR, CCTV System" oninput="rowChanged(this)"></td>
    <td><input class="cp-inp num" type="number" step="0.01" placeholder="0.00" oninput="rowChanged(this)"></td>
    <td><button class="cp-save" onclick="saveCapexRow(this)">Save</button><button class="btn-del" onclick="deleteCapexRow(this)">Del</button></td>
  `;
  tbody.appendChild(tr);
  tr.querySelector('input[type=text]').focus();
  recalcCapexFooter();
  calcAndFlag();
}

function rowChanged(input) {
  recalcCapexFooter();
  calcAndFlag();
  const tr  = input.closest('tr');
  const btn = tr.querySelector('.cp-save');
  if (btn) { btn.textContent = parseInt(tr.dataset.id) > 0 ? 'Update' : 'Save'; btn.className = 'cp-save'; }
}

function recalcCapexFooter() {
  document.getElementById('foot-capex').textContent = fmt(currentCapexTotal());
}

async function saveCapexRow(btn) {
  const tr    = btn.closest('tr');
  const id    = parseInt(tr.dataset.id);
  const inputs = tr.querySelectorAll('input');
  const item  = inputs[0].value || '';
  const amt   = parseFloat(inputs[1].value) || 0;

  const origLabel = btn.textContent;
  btn.textContent = '…'; btn.className = 'cp-save saving'; btn.disabled = true;

  const fd = new FormData();
  fd.append('item', item);
  fd.append('amount', amt.toFixed(2));
  fd.append('entry_year',  FYEAR);
  fd.append('entry_month', FMONTH);
  fd.append(id > 0 ? 'ajax_update_capex' : 'ajax_add_capex', '1');
  if (id > 0) fd.append('id', id);

  try {
    const res  = await fetch('demiclab_jaro_cash_positioning.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      if (id <= 0 && data.id) tr.dataset.id = data.id;
      btn.textContent = 'Update'; btn.className = 'cp-save ok'; btn.disabled = false;
      showToast('✓ Saved', 'success');
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'cp-save'; }, 2000);
    } else {
      btn.textContent = origLabel; btn.className = 'cp-save err'; btn.disabled = false;
      showToast('❌ ' + (data.msg || 'Save failed'), 'error');
    }
  } catch (e) {
    btn.textContent = origLabel; btn.className = 'cp-save err'; btn.disabled = false;
    showToast('❌ Network error while saving', 'error');
  }
  calcAndFlag();
}

async function deleteCapexRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id);
  if (id > 0 && !confirm('Delete this CAPEX item? This cannot be undone.')) return;

  btn.disabled = true;
  if (id > 0) {
    try {
      const fd = new FormData();
      fd.append('ajax_delete_capex','1');
      fd.append('id', id);
      const res  = await fetch('demiclab_jaro_cash_positioning.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) { showToast('❌ ' + (data.msg || 'Delete failed'), 'error'); btn.disabled = false; return; }
      showToast('✓ Deleted', 'success');
    } catch (e) {
      showToast('❌ Network error while deleting', 'error'); btn.disabled = false; return;
    }
  }
  tr.remove();
  recalcCapexFooter();
  calcAndFlag();
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
