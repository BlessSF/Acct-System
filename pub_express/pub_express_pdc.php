<?php
// ============================================================
//  pub_express_pdc.php — Stella Branch PDC (Post-Dated Checks)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Pub Express') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_pdc` (
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
        $stmt = $pdo->prepare("INSERT INTO pub_express_pdc (date_issued, amount, saved_by) VALUES (?,?,?)");
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
        $pdo->prepare("UPDATE pub_express_pdc SET date_issued=?, amount=?, saved_by=? WHERE id=?")
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
        $pdo->prepare("DELETE FROM pub_express_pdc WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $rows = $pdo->query("SELECT * FROM pub_express_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pub_express_pdc_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Pub Express Branch — PDC (Post-Dated Checks)']);
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
$rows  = $pdo->query("SELECT * FROM pub_express_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

$pageTitle  = 'PDC';
$activePage = 'pub_express_pdc';
include '../layout.php';
?>

<style>
.pdc-wrap {
  max-width: 600px;
}

.pdc-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
}

.pdc-table-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  overflow: hidden;
}

.pdc-table {
  border-collapse: collapse;
  width: 100%;
  font-size: .78rem;
}

.pdc-table thead th {
  background: #1e3a5f;
  color: #fff;
  font-family: var(--font-m);
  font-size: .6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  padding: 11px 14px;
  text-align: left;
  border: 1px solid #2d5480;
  white-space: nowrap;
}

.pdc-table thead th.th-num   { width: 42px; text-align: center; }
.pdc-table thead th.th-date  { width: 200px; }
.pdc-table thead th.th-amt   { width: 180px; text-align: right; }
.pdc-table thead th.th-act   { width: 120px; text-align: center; }

.pdc-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}
.pdc-table tbody tr:nth-child(even) td { background: #f8fbff; }
.pdc-table tbody tr:hover td { background: #eef4ff !important; }

.pdc-table td {
  border: 1px solid #e3e6ea;
  padding: 0;
  vertical-align: middle;
}

.pdc-input {
  width: 100%;
  padding: 9px 12px;
  background: transparent;
  border: none;
  outline: none;
  color: var(--text);
  font-family: var(--font-h);
  font-size: .78rem;
  display: block;
  box-sizing: border-box;
}
.pdc-input:focus {
  background: rgba(15,123,92,.06);
  outline: 1px solid rgba(15,123,92,.35);
}
.pdc-input[type="number"] {
  font-family: var(--font-m);
  text-align: right;
}
.pdc-input[type="date"] {
  font-family: var(--font-m);
  font-size: .74rem;
  color: var(--text);
}

.td-num {
  text-align: center;
  font-family: var(--font-m);
  font-size: .68rem;
  color: var(--subtext);
  padding: 0 8px;
  background: #f8f9fb;
  width: 42px;
}

.pdc-table tfoot td {
  background: #1e3a5f;
  color: #fff;
  font-family: var(--font-m);
  font-size: .7rem;
  font-weight: 700;
  padding: 10px 14px;
  border: 1px solid #2d5480;
}
.pdc-table tfoot td.tf-total {
  text-align: right;
  color: #4ade80;
  font-size: .82rem;
}

.btn-save {
  padding: 5px 12px;
  font-size: .65rem;
  font-family: var(--font-m);
  font-weight: 700;
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  cursor: pointer;
  transition: all .13s;
  display: block; width: 100%; margin-bottom: 4px;
}
.btn-save:hover { background: #dcfce7; }
.btn-save.saving { opacity: .5; pointer-events: none; }
.btn-save.ok  { background: #dcfce7; color: #15803d; }
.btn-save.err { background: #fff1f2; color: #be123c; border-color: #fecdd3; }

.btn-del {
  padding: 5px 12px;
  font-size: .65rem;
  font-family: var(--font-m);
  font-weight: 700;
  background: #fff1f2;
  color: #be123c;
  border: 1px solid #fecdd3;
  border-radius: 6px;
  cursor: pointer;
  transition: all .13s;
  display: block; width: 100%;
}
.btn-del:hover { background: #ffe4e6; }

.btn-add-row {
  display: flex; align-items: center; gap: 7px;
  padding: 9px 18px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 8px;
  font-size: .8rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #0a6649; transform: translateY(-1px); }

.toast {
  position: fixed; top: 68px; right: 22px;
  z-index: 9999; max-width: 300px;
  animation: fadeSlideDown .3s ease;
}

.pdc-kpi {
  background: var(--surface);
  border: 1px solid var(--border);
  border-top: 3px solid var(--accent);
  border-radius: var(--radius);
  padding: 16px 20px;
  margin-bottom: 20px;
  display: inline-block;
  min-width: 200px;
}
.pdc-kpi-label {
  font-family: var(--font-m);
  font-size: .62rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--subtext);
  margin-bottom: 4px;
}
.pdc-kpi-val {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--accent);
  font-family: var(--font-m);
}
.pdc-kpi-sub {
  font-family: var(--font-m);
  font-size: .7rem;
  color: var(--muted);
  margin-top: 2px;
}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title">PDC <span>Tracker</span></div>
    <div class="section-subtitle" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
      Pub Express Branch · Post-Dated Checks
    </div>
  </div>
  <a href="pub_express_pdc.php?export_csv=1" class="btn btn-ghost btn-sm"
     style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)">
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
            <td style="padding:4px 6px;text-align:center">
              <button class="btn-save" onclick="saveRow(this)">Update</button>
              <button class="btn-del"  onclick="delRow(this)">Del</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr id="empty-row">
            <td colspan="4" style="text-align:center;padding:40px;color:var(--muted);
                font-family:var(--font-m);font-size:.78rem">
              No PDC records yet — click <strong>+ Add Row</strong> to get started
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" style="text-align:right;letter-spacing:.06em">TOTAL</td>
          <td class="tf-total" id="pdc-total">
            ₱<?= number_format($total, 2) ?>
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="margin-top:12px">
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
    <td style="padding:4px 6px;text-align:center">
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
    const res  = await fetch('pub_express_pdc.php', { method: 'POST', body: fd });
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
    const res  = await fetch('pub_express_pdc.php', { method: 'POST', body: fd });
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