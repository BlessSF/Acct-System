<?php
// ============================================================
//  commissary_cheque_print.php — Cheque Print directory
//  Standalone page (own sidebar entry) listing checks from
//  Released Check Monitoring and Payables Monitoring so any one
//  of them can be printed onto pre-printed check stock via
//  commissary_print_check.php, without adding more buttons to
//  the Accounts page itself.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    http_response_code(403); exit('Access denied.');
}

$pdo = getPDO();

$releasedRows = $pdo->query("SELECT * FROM commissary_ap_released_checks ORDER BY id DESC")->fetchAll();
$payablesRows = $pdo->query("SELECT * FROM commissary_ap_payables ORDER BY entry_date DESC, id DESC")->fetchAll();

$pageTitle  = 'Cheque Print';
$activePage = 'commissary_cheque_print';
include '../layout.php';

function nz2($v) { $v=(float)$v; return $v!=0 ? number_format($v,2) : '0.00'; }
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.cp-tabs { display:flex; gap:2px; border-bottom:1px solid var(--border); margin-bottom:18px; flex-wrap:wrap; }
.cp-tab {
  padding:9px 18px; font-family:var(--font-h); font-size:.82rem; font-weight:600;
  color:var(--subtext); background:none; border:none; cursor:pointer;
  border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s;
}
.cp-tab:hover { color:var(--accent); }
.cp-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.cp-pane { display:none; }
.cp-pane.active { display:block; }

.cp-search {
  width:100%; max-width:340px; padding:8px 12px; border:1px solid var(--border); border-radius:8px;
  font-family:var(--font-m); font-size:.8rem; margin-bottom:14px;
}

.cp-outer {
  width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border);
  background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.cp-table { border-collapse:collapse; width:100%; font-size:.76rem; }
.cp-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.6rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:9px 10px; border:1px solid #2d5480;
  white-space:nowrap; text-align:center;
}
.cp-table td { border:1px solid #e3e6ea; padding:8px 10px; vertical-align:middle; font-family:var(--font-m); }
.cp-table tbody tr:hover td { filter:brightness(.97); }
.cp-table td.tleft { text-align:left; font-family:var(--font-h); }
.cp-table td.tright { text-align:right; }
.cp-table td.tcenter { text-align:center; }

.cp-badge {
  display:inline-block; padding:2px 8px; border-radius:20px; font-size:.62rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.04em;
}
.cp-badge.b2424 { background:#d1fae5; color:#065f46; }
.cp-badge.b4320 { background:#e5d9f5; color:#4c1d95; }
.cp-badge.b0837 { background:#fef3c7; color:#92400e; }

.cp-badge.status-Paid     { background:#d1fae5; color:#065f46; }
.cp-badge.status-Unpaid   { background:#fee2e2; color:#991b1b; }
.cp-badge.status-Released { background:#dbeafe; color:#1e40af; }

.bp-print {
  padding:5px 12px; font-size:.68rem; font-family:var(--font-m); font-weight:700;
  background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:6px; cursor:pointer;
  white-space:nowrap;
}
.bp-print:hover { background:#dbeafe; }

.empty-row td { text-align:center; padding:24px; color:var(--subtext2); font-family:var(--font-m); font-size:.8rem; }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Cheque <span style="color:var(--accent)">Print</span></div>
    <div class="section-subtitle">Select a check from Released Checks or Payables to print onto check stock — Commissary only</div>
  </div>
</div>

<!-- Tabs -->
<div class="cp-tabs">
  <button class="cp-tab active" data-tab="released" onclick="switchCpTab('released')">🏦 Released Checks</button>
  <button class="cp-tab" data-tab="payables" onclick="switchCpTab('payables')">💰 Payables</button>
</div>

<!-- ══════════════ RELEASED CHECKS ══════════════ -->
<div class="cp-pane active" id="cp-pane-released">
  <input type="text" class="cp-search" id="cp-search-released" placeholder="Search by vendor name…" oninput="filterTable('cp-search-released','tbody-cp-released')">
  <div class="cp-outer">
    <table class="cp-table">
      <thead>
        <tr>
          <th style="min-width:260px">NAME</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:110px">BANK ACCOUNT</th>
          <th style="min-width:200px">REMARKS</th>
          <th style="min-width:110px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-cp-released">
        <?php if (!$releasedRows): ?>
        <tr class="empty-row"><td colspan="5">No released checks yet — add some in Commissary Accounts › DATA tab.</td></tr>
        <?php endif; ?>
        <?php foreach ($releasedRows as $r): ?>
        <tr data-name="<?=strtolower(htmlspecialchars($r['vendor_name']))?>">
          <td class="tleft"><?=htmlspecialchars($r['vendor_name'])?></td>
          <td class="tright"><?=nz2($r['amount'])?></td>
          <td class="tcenter"><span class="cp-badge b<?=htmlspecialchars($r['bank_account'])?>">PNB-<?=htmlspecialchars($r['bank_account'])?></span></td>
          <td class="tleft"><?=htmlspecialchars($r['remarks'])?></td>
          <td class="tcenter"><button class="bp-print" onclick="printCheck('released', <?=$r['id']?>)">🖨️ Print Cheque</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════════ PAYABLES ══════════════ -->
<div class="cp-pane" id="cp-pane-payables">
  <input type="text" class="cp-search" id="cp-search-payables" placeholder="Search by vendor name…" oninput="filterTable('cp-search-payables','tbody-cp-payables')">
  <div class="cp-outer">
    <table class="cp-table">
      <thead>
        <tr>
          <th style="min-width:110px">DATE CHECK</th>
          <th style="min-width:260px">NAME</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:110px">STATUS</th>
          <th style="min-width:110px">BANK ACCOUNT</th>
          <th style="min-width:110px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-cp-payables">
        <?php if (!$payablesRows): ?>
        <tr class="empty-row"><td colspan="6">No payables yet — add some in Commissary Accounts › RUNNING_PAYABLES tab.</td></tr>
        <?php endif; ?>
        <?php foreach ($payablesRows as $r): ?>
        <tr data-name="<?=strtolower(htmlspecialchars($r['vendor_name']))?>">
          <td class="tcenter"><?=htmlspecialchars($r['entry_date'])?></td>
          <td class="tleft"><?=htmlspecialchars($r['vendor_name'])?></td>
          <td class="tright"><?=nz2($r['amount'])?></td>
          <td class="tcenter"><span class="cp-badge status-<?=htmlspecialchars($r['status'])?>"><?=htmlspecialchars($r['status'])?></span></td>
          <td class="tcenter"><span class="cp-badge b<?=htmlspecialchars($r['bank_account'])?>">PNB-<?=htmlspecialchars($r['bank_account'])?></span></td>
          <td class="tcenter"><button class="bp-print" onclick="printCheck('payables', <?=$r['id']?>)">🖨️ Print Cheque</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function switchCpTab(tab) {
  document.querySelectorAll('.cp-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.cp-pane').forEach(p => p.classList.toggle('active', p.id === 'cp-pane-' + tab));
}

function filterTable(inputId, tbodyId) {
  const q = document.getElementById(inputId).value.trim().toLowerCase();
  document.querySelectorAll('#' + tbodyId + ' tr[data-name]').forEach(row => {
    row.style.display = row.dataset.name.includes(q) ? '' : 'none';
  });
}

function printCheck(tbl, id) {
  window.open('commissary_print_check.php?tbl=' + tbl + '&id=' + id, '_blank');
}
</script>
