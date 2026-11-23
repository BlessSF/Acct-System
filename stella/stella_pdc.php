<?php
// ============================================================
//  stella_pdc.php — Stella Branch PDC (Post-Dated Checks)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_pdc` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `date_issued`  date NOT NULL,
    `amount`       decimal(12,2) DEFAULT 0.00,
    `saved_by`     varchar(100) DEFAULT NULL,
    `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $date   = $_POST['date_issued'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("INSERT INTO stella_pdc (date_issued, amount, saved_by) VALUES (?,?,?)");
        $stmt->execute([$date, $amount, $user['name']]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id     = (int)($_POST['id'] ?? 0);
    $date   = $_POST['date_issued'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    try {
        $pdo->prepare("UPDATE stella_pdc SET date_issued=?, amount=?, saved_by=? WHERE id=?")
            ->execute([$date, $amount, $user['name'], $id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM stella_pdc WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $rows = $pdo->query("SELECT * FROM stella_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stella_pdc_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Stella Branch — PDC (Post-Dated Checks)']);
    fputcsv($out, ['Generated: ' . date('F d, Y H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['No.', 'Date Issued', 'Amount']);
    foreach ($rows as $i => $r) {
        fputcsv($out, [$i + 1, date('F d, Y', strtotime($r['date_issued'])), number_format($r['amount'], 2)]);
    }
    fputcsv($out, []);
    $total = array_sum(array_column($rows, 'amount'));
    fputcsv($out, ['', 'TOTAL', number_format($total, 2)]);
    fclose($out);
    exit;
}

// ── Load rows ─────────────────────────────────────────────
$rows  = $pdo->query("SELECT * FROM stella_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

$pageTitle  = 'PDC';
$activePage = 'stella_pdc';
$bodyClass = 'sp-stella_pdc';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title">PDC <span>Tracker</span></div>
    <div class="sx-6 section-subtitle" >
      Stella Branch · Post-Dated Checks
    </div>
  </div>
  <a href="stella_pdc.php?export_csv=1" class="sx-5 btn btn-ghost btn-sm"
     >
    ⬇ Download CSV
  </a>
</div>

<!-- KPI -->
<div class="pdc-kpi">
  <div class="pdc-kpi-label">Total Amount</div>
  <div class="pdc-kpi-val">₱<?= number_format($total, 2) ?></div>
  <div class="pdc-kpi-sub"><?= count($rows) ?> check<?= count($rows) !== 1 ? 's' : '' ?></div>
</div>

<!-- Table -->
<div class="pdc-wrap">
  <div class="pdc-header">
    <button class="btn-add-row" onclick="addRow()">+ Add Row</button>
  </div>

  <div class="pdc-table-wrap">
    <table class="pdc-table" id="pdct">
      <thead>
        <tr>
          <th class="th-num">#</th>
          <th class="th-date">Date Issued</th>
          <th class="th-amt">Amount</th>
          <th class="th-act">Action</th>
        </tr>
      </thead>
      <tbody id="pdc-tbody">
        <?php if ($rows): ?>
          <?php foreach ($rows as $i => $r): ?>
          <tr data-id="<?= $r['id'] ?>">
            <td class="td-num"><?= $i + 1 ?></td>
            <td><input type="date" class="pdc-input" data-col="date_issued"
                       value="<?= htmlspecialchars($r['date_issued']) ?>"></td>
            <td><input type="number" step="0.01" min="0" class="pdc-input" data-col="amount"
                       value="<?= number_format((float)$r['amount'], 2, '.', '') ?>"></td>
            <td class="sx-1">
              <button class="btn-save" onclick="saveRow(this)">Update</button>
              <button class="btn-del"  onclick="delRow(this)">Del</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr id="empty-row">
            <td colspan="4" class="sx-4">
              No PDC records yet — click <strong>+ Add Row</strong> to get started
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="sx-3">TOTAL</td>
          <td class="tf-total" id="pdc-total">
            ₱<?= number_format($total, 2) ?>
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="sx-2">
    <button class="btn-add-row" onclick="addRow()">+ Add Row</button>
  </div>
</div>

<script>
let rowCounter = 0;

function addRow() {
  const empty = document.getElementById('empty-row');
  if (empty) empty.remove();

  const tbody = document.getElementById('pdc-tbody');
  const count = tbody.querySelectorAll('tr[data-id]').length;
  const tr = document.createElement('tr');
  tr.dataset.id = '';
  tr.innerHTML = `
    <td class="td-num">${count + 1}</td>
    <td><input type="date" class="pdc-input" data-col="date_issued" value="${new Date().toISOString().slice(0,10)}"></td>
    <td><input type="number" step="0.01" min="0" class="pdc-input" data-col="amount" value="" placeholder="0.00"></td>
    <td class="sx-1">
      <button class="btn-save" onclick="saveRow(this)">Save</button>
    </td>
  `;
  tbody.appendChild(tr);
  tr.querySelector('[data-col="date_issued"]').focus();
}

async function saveRow(btn) {
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.textContent = '…'; btn.className = 'btn-save saving';

  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  if (id) fd.append('id', id);
  row.querySelectorAll('[data-col]').forEach(el => fd.append(el.dataset.col, el.value));

  try {
    const res  = await fetch('stella_pdc.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'btn-save ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        // Add Del button
        const del = document.createElement('button');
        del.className = 'btn-del';
        del.textContent = 'Del';
        del.onclick = function() { delRow(this); };
        btn.parentElement.appendChild(del);
      }
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'btn-save'; }, 2000);
      recalcTotal();
      showToast('✓ Saved', 'success');
    } else {
      btn.textContent = id ? 'Update' : 'Save';
      btn.className = 'btn-save err';
      showToast('❌ ' + (data.msg || 'Error'), 'error');
      setTimeout(() => { btn.className = 'btn-save'; }, 3000);
    }
  } catch (e) {
    btn.textContent = id ? 'Update' : 'Save';
    btn.className = 'btn-save err';
    showToast('❌ Network error', 'error');
  }
}

async function delRow(btn) {
  if (!confirm('Delete this PDC entry?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcTotal(); renumber(); return; }

  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('id', id);
  try {
    const res  = await fetch('stella_pdc.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0'; row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcTotal(); renumber(); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + (data.msg || 'Error'), 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

function recalcTotal() {
  let sum = 0;
  document.querySelectorAll('#pdc-tbody [data-col="amount"]').forEach(el => {
    sum += parseFloat(el.value) || 0;
  });
  const el = document.getElementById('pdc-total');
  if (el) el.textContent = '₱' + sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renumber() {
  document.querySelectorAll('#pdc-tbody tr[data-id]').forEach((tr, i) => {
    const numCell = tr.querySelector('.td-num');
    if (numCell) numCell.textContent = i + 1;
  });
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// Live total update as user types
document.addEventListener('input', e => {
  if (e.target.dataset.col === 'amount') recalcTotal();
});
</script>
</body>
</html>