<?php
// ============================================================
//  commissary_accounts_payable.php — Commissary "Accounts" module
//  Mirrors COMMI_ACCOUNTS_PAYABLES___CASHFLOW_BALANCE.xlsx:
//   • DATA sheet          → Cash Position tab (bank balances,
//                            released-check monitoring, fund pull-out)
//   • RUNNING_PAYABLES    → Payables Monitoring tab
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    http_response_code(403); exit('Access denied.');
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create tables ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_ap_snapshot` (
    `id`                      int(11) NOT NULL,
    `as_of_date`              date DEFAULT NULL,
    `pnb2424_passbook`        decimal(14,2) NOT NULL DEFAULT 0.00,
    `pnb2424_deposit_transit` decimal(14,2) NOT NULL DEFAULT 0.00,
    `pnb4320_passbook`        decimal(14,2) NOT NULL DEFAULT 0.00,
    `pnb4320_deposit_transit` decimal(14,2) NOT NULL DEFAULT 0.00,
    `pnb0837_balance`         decimal(14,2) NOT NULL DEFAULT 0.00,
    `pnb0837_deposit_transit` decimal(14,2) NOT NULL DEFAULT 0.00,
    `receivables_amount`      decimal(14,2) NOT NULL DEFAULT 0.00,
    `updated_at`              timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("INSERT IGNORE INTO commissary_ap_snapshot (id) VALUES (1)");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_ap_released_checks` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `vendor_name` varchar(255) DEFAULT '',
    `amount`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_account`varchar(10) DEFAULT '2424',
    `remarks`     varchar(255) DEFAULT '',
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_ap_payables` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`   date DEFAULT NULL,
    `vendor_name`  varchar(255) DEFAULT '',
    `amount`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `status`       varchar(20) DEFAULT 'Unpaid',
    `bank_account` varchar(10) DEFAULT '2424',
    `remarks`      varchar(255) DEFAULT '',
    `saved_by`     varchar(100) DEFAULT NULL,
    `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_ap_pullout` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `label`       varchar(255) DEFAULT '',
    `amount`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Field whitelists (generic AJAX handlers) ─────────────────
$TBL = [
    'released' => [
        'table' => 'commissary_ap_released_checks',
        'txt'   => ['vendor_name', 'remarks'],
        'num'   => ['amount'],
        'date'  => [],
        'sel'   => ['bank_account'],
    ],
    'payables' => [
        'table' => 'commissary_ap_payables',
        'txt'   => ['vendor_name', 'remarks'],
        'num'   => ['amount'],
        'date'  => ['entry_date'],
        'sel'   => ['status', 'bank_account'],
    ],
    'pullout' => [
        'table' => 'commissary_ap_pullout',
        'txt'   => ['label'],
        'num'   => ['amount'],
        'date'  => [],
        'sel'   => [],
    ],
];

// ── AJAX: generic add ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $data = [];
    foreach ($def['txt'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($def['num'] as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($def['date'] as $f) $data[$f] = $_POST[$f] ?: null;
    foreach ($def['sel'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO `{$def['table']}` (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $id  = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($def['txt'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    foreach ($def['num'] as $f) { $sets[]="`$f`=?"; $vals[]=(float)($_POST[$f] ?? 0); }
    foreach ($def['date'] as $f) { $sets[]="`$f`=?"; $vals[]=$_POST[$f] ?: null; }
    foreach ($def['sel'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE `{$def['table']}` SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic delete ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM `{$TBL[$key]['table']}` WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: save cash snapshot (single row) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_snapshot'])) {
    header('Content-Type: application/json');
    try {
        $pdo->prepare("UPDATE commissary_ap_snapshot SET as_of_date=?, pnb2424_passbook=?, pnb2424_deposit_transit=?, pnb4320_passbook=?, pnb4320_deposit_transit=?, pnb0837_balance=?, pnb0837_deposit_transit=?, receivables_amount=? WHERE id=1")
            ->execute([
                $_POST['as_of_date'] ?: null,
                (float)($_POST['pnb2424_passbook'] ?? 0),
                (float)($_POST['pnb2424_deposit_transit'] ?? 0),
                (float)($_POST['pnb4320_passbook'] ?? 0),
                (float)($_POST['pnb4320_deposit_transit'] ?? 0),
                (float)($_POST['pnb0837_balance'] ?? 0),
                (float)($_POST['pnb0837_deposit_transit'] ?? 0),
                (float)($_POST['receivables_amount'] ?? 0),
            ]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Load data ──────────────────────────────────────────────────
$releasedRows  = $pdo->query("SELECT * FROM commissary_ap_released_checks ORDER BY id ASC")->fetchAll();
$payablesRows  = $pdo->query("SELECT * FROM commissary_ap_payables ORDER BY entry_date ASC, id ASC")->fetchAll();
$pulloutRows   = $pdo->query("SELECT * FROM commissary_ap_pullout ORDER BY id ASC")->fetchAll();
$snapshot      = $pdo->query("SELECT * FROM commissary_ap_snapshot WHERE id=1")->fetch() ?: [];

$pageTitle  = 'Accounts';
$activePage = 'commissary_accounts_payable';
include '../layout.php';

function nz($v) { $v=(float)$v; return $v!=0 ? number_format($v,2,'.','') : ''; }
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.pv-tabs { display:flex; gap:2px; border-bottom:1px solid var(--border); margin-bottom:18px; flex-wrap:wrap; }
.pv-tab {
  padding:9px 18px; font-family:var(--font-h); font-size:.82rem; font-weight:600;
  color:var(--subtext); background:none; border:none; cursor:pointer;
  border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s;
}
.pv-tab:hover { color:var(--accent); }
.pv-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.pv-pane { display:none; }
.pv-pane.active { display:block; }

.pv-outer {
  width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border);
  background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:14px;
}
.pv-table { border-collapse:collapse; width:max-content; min-width:100%; font-size:.72rem; }
.pv-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.56rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:9px 8px; border:1px solid #2d5480;
  white-space:nowrap; text-align:center;
}
.pv-table td { border:1px solid #e3e6ea; padding:0; vertical-align:middle; }
.pv-table tbody tr:hover td { filter:brightness(.97); }
.pv-table tfoot td {
  padding:8px 8px; font-family:var(--font-m); font-size:.7rem; font-weight:800;
  text-align:right; border:1px solid #d1d5db; background:#1e3a5f; color:#fff;
}
.pv-table tfoot td.tfl { text-align:center; text-transform:uppercase; letter-spacing:.07em; font-size:.6rem; }

.pvi {
  width:100%; padding:6px 6px; background:transparent; border:none; outline:none;
  color:#1a1d23; font-family:var(--font-m); font-size:.7rem; text-align:right; display:block; box-sizing:border-box;
}
.pvi.txt  { text-align:left; font-family:var(--font-h); }
.pvi:focus { background:rgba(15,123,92,.07); outline:1px solid rgba(15,123,92,.4); }
select.pvi { text-align:left; -webkit-appearance:none; appearance:none; background:#fff; }

.td-act { text-align:center; padding:4px 6px; white-space:nowrap; }
.btn-add-row {
  display:flex; align-items:center; gap:7px; padding:8px 16px; background:var(--accent); color:#fff;
  border:none; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; font-family:var(--font-h);
  transition:background .15s, transform .1s;
}
.btn-add-row:hover { background:#0a6649; transform:translateY(-1px); }
.bsv {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:5px; cursor:pointer;
  white-space:nowrap; display:inline-block; margin-right:3px;
}
.bsv:hover { background:#dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok { background:#dcfce7; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.bdel {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#fff1f2; color:#be123c; border:1px solid #fecdd3; border-radius:5px; cursor:pointer;
}
.bdel:hover { background:#ffe4e6; }

.pv-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:14px; justify-content:space-between; }

.pv-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:18px; }
.pv-card {
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
  padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.pv-card .lbl { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext2); margin-bottom:6px; }
.pv-card .val { font-family:var(--font-m); font-size:1.15rem; font-weight:800; color:#1a1d23; }
.pv-card.accent .val { color:var(--accent); }
.pv-card.warn .val { color:#b45309; }
.pv-card.bad .val { color:#be123c; }

.pv-snap-form { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:14px 16px; }
.pv-snap-field { display:flex; flex-direction:column; gap:4px; }
.pv-snap-field label { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; color:var(--subtext2); }
.pv-snap-field input { padding:7px 9px; border:1px solid var(--border); border-radius:6px; font-family:var(--font-m); font-size:.78rem; width:150px; }

.pv-group-label {
  font-family:var(--font-m); font-size:.72rem; color:var(--subtext2); text-transform:uppercase;
  letter-spacing:.06em; margin:18px 0 8px;
}

/* ── Ledger (spreadsheet-style DATA layout) ── */
.ledger-wrap { display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap; margin-bottom:20px; }
.ledger-table { border-collapse:collapse; font-family:var(--font-m); font-size:.76rem; min-width:420px; flex:1 1 420px; }
.ledger-table td { border:1px solid #c7ccd3; padding:6px 10px; }
.ledger-table td.led-label { text-align:left; background:#fff; color:#1a1d23; }
.ledger-table td.led-val { text-align:right; width:150px; }
.ledger-section td { background:#a9c6ee; color:#0b1f3a; font-weight:700; text-align:center; text-transform:uppercase; letter-spacing:.03em; }
.ledger-computed { background:#e5e7eb !important; font-weight:800; }
.ledger-input { width:100%; border:none; background:transparent; text-align:right; font-family:var(--font-m); font-size:.76rem; outline:none; }
.ledger-input:focus { background:rgba(15,123,92,.08); }
.ledger-excess-blue td.led-val { background:#1e3a5f; color:#fbbf24; font-weight:800; }
.ledger-excess-green td.led-val { background:#bbf7d0; color:#065f46; font-weight:800; }
.ledger-excess-blue td.led-label { background:#1e3a5f; color:#fff; font-weight:700; }
.ledger-excess-green td.led-label { background:#bbf7d0; color:#065f46; font-weight:700; }
.ledger-total td { background:#e5e7eb; color:#be123c; font-weight:800; }
.ledger-italic td.led-label { font-style:italic; }

.released-badges { display:flex; flex-direction:column; gap:8px; min-width:230px; }
.rb-badge { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:8px 12px; border-radius:6px; font-family:var(--font-m); font-size:.74rem; font-weight:700; }
.rb-badge .rb-amt { font-size:.85rem; }
.rb-2424 { background:#d1fae5; color:#065f46; }
.rb-4320 { background:#e5d9f5; color:#4c1d95; }
.rb-0837 { background:#fef3c7; color:#92400e; }

.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Accounts <span style="color:var(--accent)">Payable</span></div>
    <div class="section-subtitle">DATA · RUNNING_PAYABLES · PAID — Commissary only</div>
  </div>
</div>

<!-- Tabs -->
<div class="pv-tabs">
  <button class="pv-tab active" data-tab="cash" onclick="switchTab('cash')">🏦 DATA</button>
  <button class="pv-tab" data-tab="monitoring" onclick="switchTab('monitoring')">💰 RUNNING_PAYABLES</button>
  <button class="pv-tab" data-tab="paid" onclick="switchTab('paid')">✅ PAID</button>
</div>

<!-- ══════════════ TAB 1: DATA (Cash Position) ══════════════ -->
<div class="pv-pane active" id="pane-cash">

  <div style="margin-bottom:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div class="pv-snap-field" style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.06em;color:var(--subtext2)">As of Date</label>
      <input type="date" id="snap-date" style="padding:7px 9px;border:1px solid var(--border);border-radius:6px;font-family:var(--font-m);font-size:.78rem" value="<?=htmlspecialchars($snapshot['as_of_date'] ?? '')?>">
    </div>
    <button type="button" class="btn-add-row" onclick="saveSnapshot()">💾 Save Cash Position</button>
  </div>

  <div class="pv-group-label">Released Check Monitoring</div>
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Checks released, pending clearing</div>
    <button type="button" class="btn-add-row" onclick="addRow('released')">+ Add Row</button>
  </div>
  <div class="ledger-wrap">
  <div class="pv-outer" style="flex:2 1 520px;margin-bottom:0">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:240px">NAME</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:110px">BANK ACCOUNT</th>
          <th style="min-width:220px">REMARKS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-released">
        <?php foreach ($releasedRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input class="pvi txt" data-col="vendor_name" value="<?=htmlspecialchars($r['vendor_name'])?>" oninput="rowChanged(this,'released')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'released');recalcCash()"></td>
          <td>
            <select class="pvi" data-col="bank_account" onchange="rowChanged(this,'released');recalcCash()">
              <option value="2424" <?=$r['bank_account']==='2424'?'selected':''?>>PNB-2424</option>
              <option value="4320" <?=$r['bank_account']==='4320'?'selected':''?>>PNB-4320</option>
              <option value="0837" <?=$r['bank_account']==='0837'?'selected':''?>>PNB-0837</option>
            </select>
          </td>
          <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'released')"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'released')">Update</button><button class="bdel" onclick="deleteRow(this,'released')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="tfl">TOTAL</td><td id="tot-released-amount">—</td><td></td><td></td><td></td></tr>
      </tfoot>
    </table>
  </div>
  <div class="released-badges">
    <div class="rb-badge rb-2424"><span>PNB-2424 RELEASED CHECK</span><span class="rb-amt" id="rb-2424">—</span></div>
    <div class="rb-badge rb-4320"><span>PNB-4320 RELEASED CHECK</span><span class="rb-amt" id="rb-4320">—</span></div>
    <div class="rb-badge rb-0837"><span>PNB-0837 RELEASED CHECK</span><span class="rb-amt" id="rb-0837">—</span></div>
  </div>
  </div>

  <div class="pv-group-label">Cash Position</div>
  <table class="ledger-table">
    <tr class="ledger-section"><td colspan="2">OPERATIONS (PNB 2424)</td></tr>
    <tr>
      <td class="led-label">PASSBOOK BALANCE</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-2424-passbook" value="<?=nz($snapshot['pnb2424_passbook'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>
    <tr>
      <td class="led-label">AVAILABLE FUNDS</td>
      <td class="led-val ledger-computed" id="led-avail-2424">—</td>
    </tr>
    <tr>
      <td class="led-label">SALES DEPOSIT IN TRANSIT</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-2424-transit" value="<?=nz($snapshot['pnb2424_deposit_transit'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>

    <tr class="ledger-section"><td colspan="2">NEW OPERATIONS (PNB 4320)</td></tr>
    <tr>
      <td class="led-label">PASSBOOK BALANCE</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-4320-passbook" value="<?=nz($snapshot['pnb4320_passbook'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>
    <tr>
      <td class="led-label">AVAILABLE FUNDS</td>
      <td class="led-val ledger-computed" id="led-avail-4320">—</td>
    </tr>
    <tr>
      <td class="led-label">SALES DEPOSIT IN TRANSIT</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-4320-transit" value="<?=nz($snapshot['pnb4320_deposit_transit'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>

    <tr><td colspan="2" style="border:none;background:transparent"></td></tr>
    <tr>
      <td class="led-label" style="font-weight:800">RUNNING PAYABLES</td>
      <td class="led-val ledger-computed" id="led-running-payables">—</td>
    </tr>

    <tr class="ledger-excess-blue">
      <td class="led-label">EXCESS FUND PNB 2424</td>
      <td class="led-val" id="led-excess-2424">—</td>
    </tr>
    <tr class="ledger-excess-green">
      <td class="led-label">EXCESS FUND PNB 4320</td>
      <td class="led-val" id="led-excess-4320">—</td>
    </tr>
    <tr class="ledger-total">
      <td class="led-label">TOTAL OF EXCESS FUND</td>
      <td class="led-val" id="led-total-excess">—</td>
    </tr>

    <tr>
      <td class="led-label">RECEIVABLES (less store advance)</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-receivables" value="<?=nz($snapshot['receivables_amount'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>
    <tr class="ledger-total">
      <td class="led-label">TOTAL EXCESS FUND + RECEIVABLES</td>
      <td class="led-val" id="led-net-receivables">—</td>
    </tr>

    <tr class="ledger-section"><td colspan="2">OTHER OP BANKS — PNB (0837)</td></tr>
    <tr>
      <td class="led-label">PNB (0837)</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-0837-balance" value="<?=nz($snapshot['pnb0837_balance'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>
    <tr>
      <td class="led-label">SALES DEPOSIT IN TRANSIT</td>
      <td class="led-val"><input type="number" step="0.01" class="ledger-input" id="snap-0837-transit" value="<?=nz($snapshot['pnb0837_deposit_transit'] ?? 0)?>" oninput="recalcCash()"></td>
    </tr>
    <tr class="ledger-total">
      <td class="led-label">RUNNING BALANCE (PNB 0837)</td>
      <td class="led-val" id="led-running-balance">—</td>
    </tr>
  </table>

  <div class="pv-group-label">Fund for Pull Out (Funding for upcoming payables / Owner's profit pull out)</div>
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">e.g. Ma'am Nikki, Sir Budoy</div>
    <button type="button" class="btn-add-row" onclick="addRow('pullout')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:260px">LABEL</th>
          <th style="min-width:140px">AMOUNT</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-pullout">
        <?php foreach ($pulloutRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input class="pvi txt" data-col="label" value="<?=htmlspecialchars($r['label'])?>" oninput="rowChanged(this,'pullout')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'pullout');recalcCash()"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'pullout')">Update</button><button class="bdel" onclick="deleteRow(this,'pullout')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="tfl">TOTAL</td><td id="tot-pullout-amount">—</td><td></td></tr>
      </tfoot>
    </table>
  </div>

  <table class="ledger-table" style="min-width:420px">
    <tr class="ledger-excess-blue">
      <td class="led-label">EXCESS FUND PNB (4320) AND PNB (2424)</td>
      <td class="led-val" id="led-pullout-excess">—</td>
    </tr>
  </table>
</div>

<!-- ══════════════ TAB 2: PAYABLES MONITORING ══════════════ -->
<div class="pv-pane" id="pane-monitoring">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Commissary Payables Monitoring</div>
    <button type="button" class="btn-add-row" onclick="addRow('payables')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:110px">DATE CHECK</th>
          <th style="min-width:260px">NAME</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:130px">REMARKS / STATUS</th>
          <th style="min-width:110px">BANK ACCOUNT</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-payables">
        <?php foreach ($payablesRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input type="date" class="pvi" style="text-align:center" data-col="entry_date" value="<?=htmlspecialchars($r['entry_date'])?>" oninput="rowChanged(this,'payables')"></td>
          <td><input class="pvi txt" data-col="vendor_name" value="<?=htmlspecialchars($r['vendor_name'])?>" oninput="rowChanged(this,'payables')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'payables');recalcCash()"></td>
          <td>
            <select class="pvi" data-col="status" onchange="rowChanged(this,'payables');recalcCash()">
              <?php foreach (['Paid','Unpaid','Released'] as $opt): ?>
              <option value="<?=$opt?>" <?=$r['status']===$opt?'selected':''?>><?=$opt?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="pvi" data-col="bank_account" onchange="rowChanged(this,'payables');recalcCash()">
              <option value="2424" <?=$r['bank_account']==='2424'?'selected':''?>>PNB-2424</option>
              <option value="4320" <?=$r['bank_account']==='4320'?'selected':''?>>PNB-4320</option>
              <option value="0837" <?=$r['bank_account']==='0837'?'selected':''?>>PNB-0837</option>
            </select>
          </td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'payables')">Update</button><button class="bdel" onclick="deleteRow(this,'payables')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="tfl">TOTAL</td><td></td>
          <td id="tot-payables-amount">—</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 3: PAID (deducted to Commi account) ══════════════ -->
<div class="pv-pane" id="pane-paid">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Paid (deducted to Commi account) — mirrors rows marked "Paid" in Payables Monitoring</div>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:280px">VENDOR NAME</th>
          <th style="min-width:150px">TOTAL AMOUNT DUE</th>
        </tr>
      </thead>
      <tbody id="tbody-paid">
        <?php $paidTotal = 0; foreach ($payablesRows as $r): if ($r['status'] !== 'Paid') continue; $paidTotal += (float)$r['amount']; ?>
        <tr>
          <td style="padding:6px 10px;font-family:var(--font-h);font-size:.72rem"><?=htmlspecialchars($r['vendor_name'])?></td>
          <td style="padding:6px 10px;font-family:var(--font-m);font-size:.7rem;text-align:right"><?=nz($r['amount'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="tfl">TOTAL</td><td><?=number_format($paidTotal,2)?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.pv-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.pv-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + tab));
}

function fmt(v) { return v === 0 ? '—' : v.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function recalcFooter(section) {
  const tbody = document.getElementById('tbody-' + section);
  if (!tbody) return;
  const numCols = {};
  tbody.querySelectorAll('[data-col]').forEach(el => {
    if (el.type === 'number') {
      const col = el.dataset.col;
      numCols[col] = (numCols[col] || 0) + (parseFloat(el.value) || 0);
    }
  });
  Object.entries(numCols).forEach(([col, sum]) => {
    const cell = document.getElementById('tot-' + section + '-' + col);
    if (cell) cell.textContent = fmt(sum);
  });
}

function rowChanged(el, section) {
  const btn = el.closest('tr').querySelector('.bsv');
  if (btn && !btn.classList.contains('saving')) { btn.classList.remove('ok','err'); }
  recalcFooter(section);
}

let newRowCounter = 0;
const ADD_COLS = {
  released: [
    {col:'vendor_name', type:'text'},
    {col:'amount', type:'number'},
    {col:'bank_account', type:'select', options:[['2424','PNB-2424'],['4320','PNB-4320'],['0837','PNB-0837']]},
    {col:'remarks', type:'text'},
  ],
  pullout: [
    {col:'label', type:'text'},
    {col:'amount', type:'number'},
  ],
  payables: [
    {col:'entry_date', type:'date'},
    {col:'vendor_name', type:'text'},
    {col:'amount', type:'number'},
    {col:'status', type:'select', options:[['Paid','Paid'],['Unpaid','Unpaid'],['Released','Released']]},
    {col:'bank_account', type:'select', options:[['2424','PNB-2424'],['4320','PNB-4320'],['0837','PNB-0837']]},
  ],
};

function buildCell(c) {
  if (c.type === 'text') {
    return `<td><input class="pvi txt" data-col="${c.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (c.type === 'number') {
    return `<td><input type="number" step="0.01" class="pvi" data-col="${c.col}" value="" oninput="rowChanged(this,'{{SECTION}}');recalcCash()"></td>`;
  }
  if (c.type === 'date') {
    return `<td><input type="date" class="pvi" style="text-align:center" data-col="${c.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (c.type === 'select') {
    const opts = c.options.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
    return `<td><select class="pvi" data-col="${c.col}" onchange="rowChanged(this,'{{SECTION}}');recalcCash()">${opts}</select></td>`;
  }
  return '<td></td>';
}

function addRow(section) {
  const cols = ADD_COLS[section];
  const tbody = document.getElementById('tbody-' + section);
  const tr = document.createElement('tr');
  const tempId = 'new_' + (++newRowCounter);
  tr.id = tempId;
  tr.dataset.id = '';
  let h = '';
  cols.forEach(c => { h += buildCell(c).replaceAll('{{SECTION}}', section); });
  h += `<td class="td-act"><button class="bsv" onclick="saveRow(this,'${section}')">Save</button></td>`;
  tr.innerHTML = h;
  tbody.appendChild(tr);
  tr.querySelector('input,select')?.focus();
  recalcFooter(section);
}

async function saveRow(btn, section) {
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.textContent = '…'; btn.className = 'bsv saving';

  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  fd.append('tbl', section);
  if (id) fd.append('id', id);
  row.querySelectorAll('[data-col]').forEach(el => fd.append(el.dataset.col, el.value || ''));

  try {
    const res  = await fetch('commissary_accounts_payable.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsv ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        const delBtn = document.createElement('button');
        delBtn.className = 'bdel'; delBtn.textContent = 'Del';
        delBtn.onclick = function(){ deleteRow(this, section); };
        btn.parentElement.appendChild(delBtn);
      }
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
      showToast('✓ Saved', 'success');
    } else {
      btn.textContent = 'Error'; btn.className = 'bsv err';
      showToast('❌ ' + data.msg, 'error');
      setTimeout(() => { btn.textContent = id ? 'Update' : 'Save'; btn.className = 'bsv'; }, 3000);
    }
  } catch (e) {
    btn.textContent = 'Error'; btn.className = 'bsv err';
    showToast('❌ Network error', 'error');
  }
}

async function deleteRow(btn, section) {
  if (!confirm('Delete this row?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcFooter(section); recalcCash(); return; }

  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('tbl', section);
  fd.append('id', id);
  try {
    const res  = await fetch('commissary_accounts_payable.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0'; row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcFooter(section); recalcCash(); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

// ── Cash Position (computed cards) ──
function sumCol(section, col, filterFn) {
  let total = 0;
  document.querySelectorAll(`#tbody-${section} tr`).forEach(row => {
    if (filterFn && !filterFn(row)) return;
    const el = row.querySelector(`[data-col="${col}"]`);
    total += parseFloat(el?.value) || 0;
  });
  return total;
}

function recalcCash() {
  const totalReleased = sumCol('released', 'amount');
  const released2424 = sumCol('released', 'amount', row => row.querySelector('[data-col="bank_account"]')?.value === '2424');
  const released4320 = sumCol('released', 'amount', row => row.querySelector('[data-col="bank_account"]')?.value === '4320');
  const released0837 = sumCol('released', 'amount', row => row.querySelector('[data-col="bank_account"]')?.value === '0837');

  const p2424 = (parseFloat(document.getElementById('snap-2424-passbook').value) || 0)
              + (parseFloat(document.getElementById('snap-2424-transit').value) || 0);
  const p4320 = (parseFloat(document.getElementById('snap-4320-passbook').value) || 0)
              + (parseFloat(document.getElementById('snap-4320-transit').value) || 0);
  const p0837 = (parseFloat(document.getElementById('snap-0837-balance').value) || 0)
              + (parseFloat(document.getElementById('snap-0837-transit').value) || 0);
  const receivables = parseFloat(document.getElementById('snap-receivables').value) || 0;

  const unpaid2424 = sumCol('payables', 'amount', row => {
    const status = row.querySelector('[data-col="status"]')?.value;
    const bank   = row.querySelector('[data-col="bank_account"]')?.value;
    return status === 'Unpaid' && bank === '2424';
  });
  const unpaid4320 = sumCol('payables', 'amount', row => {
    const status = row.querySelector('[data-col="status"]')?.value;
    const bank   = row.querySelector('[data-col="bank_account"]')?.value;
    return status === 'Unpaid' && bank === '4320';
  });
  const unpaid0837 = sumCol('payables', 'amount', row => {
    const status = row.querySelector('[data-col="status"]')?.value;
    const bank   = row.querySelector('[data-col="bank_account"]')?.value;
    return status === 'Unpaid' && bank === '0837';
  });
  const totalUnpaid = sumCol('payables', 'amount', row => row.querySelector('[data-col="status"]')?.value === 'Unpaid');
  const totalPullout = sumCol('pullout', 'amount');

  const avail2424 = p2424 - unpaid2424;
  const avail4320 = p4320 - unpaid4320;
  const avail0837 = p0837 - unpaid0837;
  const bankTotal = p2424 + p4320 + p0837;

  // EXCESS FUND PNB 2424 = available funds sitting in PNB 2424
  const excess2424 = avail2424;
  // EXCESS FUND PNB 4320 = PNB 4320 available funds net of total running payables
  const excess4320 = avail4320 - totalUnpaid;
  const totalExcess = excess2424 + excess4320;
  const netReceivables = totalExcess + receivables;
  const pulloutExcess  = -totalPullout;

  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = fmt(v); };

  set('rb-2424', released2424);
  set('rb-4320', released4320);
  set('rb-0837', released0837);

  set('led-avail-2424', avail2424);
  set('led-avail-4320', avail4320);
  set('led-running-payables', totalUnpaid);
  set('led-excess-2424', excess2424);
  set('led-excess-4320', excess4320);
  set('led-total-excess', totalExcess);
  set('led-net-receivables', netReceivables);
  set('led-running-balance', avail0837);
  set('led-pullout-excess', pulloutExcess);

  recalcFooter('released');
  recalcFooter('payables');
  recalcFooter('pullout');
}


async function saveSnapshot() {
  const fd = new FormData();
  fd.append('ajax_save_snapshot', '1');
  fd.append('as_of_date', document.getElementById('snap-date').value);
  fd.append('pnb2424_passbook', document.getElementById('snap-2424-passbook').value || '0');
  fd.append('pnb2424_deposit_transit', document.getElementById('snap-2424-transit').value || '0');
  fd.append('pnb4320_passbook', document.getElementById('snap-4320-passbook').value || '0');
  fd.append('pnb4320_deposit_transit', document.getElementById('snap-4320-transit').value || '0');
  fd.append('pnb0837_balance', document.getElementById('snap-0837-balance').value || '0');
  fd.append('pnb0837_deposit_transit', document.getElementById('snap-0837-transit').value || '0');
  fd.append('receivables_amount', document.getElementById('snap-receivables').value || '0');
  try {
    const res  = await fetch('commissary_accounts_payable.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.ok ? '✓ Cash position saved' : '❌ ' + data.msg, data.ok ? 'success' : 'error');
  } catch (e) { showToast('❌ Network error', 'error'); }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  ['released', 'pullout', 'payables'].forEach(recalcFooter);
  recalcCash();
});
</script>
