<?php
// ============================================================
//  dois_pdc.php — Stella Branch PDC (Post-Dated Checks)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Dois') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `dois_pdc` (
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
        $stmt = $pdo->prepare("INSERT INTO dois_pdc (date_issued, amount, saved_by) VALUES (?,?,?)");
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
        $pdo->prepare("UPDATE dois_pdc SET date_issued=?, amount=?, saved_by=? WHERE id=?")
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
        $pdo->prepare("DELETE FROM dois_pdc WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $rows = $pdo->query("SELECT * FROM dois_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dois_pdc_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Dois Branch — PDC (Post-Dated Checks)']);
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
$rows  = $pdo->query("SELECT * FROM dois_pdc ORDER BY date_issued ASC, id ASC")->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

$pageTitle  = 'PDC';
$activePage = 'dois_pdc';
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

/* ── Quick-add calculator ── */
.calc-wrap { display: flex; align-items: center; gap: 3px; }
.calc-wrap .pdc-input { flex: 1; min-width: 0; }
.calc-btn {
  flex: none; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
  border-radius: 5px; padding: 3px 6px; font-size: .78rem; line-height: 1;
  cursor: pointer;
}
.calc-btn:hover { background: #dbeafe; }
.calc-popup {
  display: none; position: fixed; z-index: 9999; width: 260px;
  max-height: calc(100vh - 20px); overflow-y: auto;
  background: #fff; border: 1px solid #c8d4df; border-radius: 10px;
  box-shadow: 0 8px 28px rgba(0,0,0,.18); padding: 12px;
  font-family: var(--font-m);
}
.calc-popup.open { display: block; }
.calc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.calc-header .lbl { font-size: .72rem; font-weight: 800; color: #1a1d23; }
.calc-close { background: none; border: none; cursor: pointer; font-size: .85rem; color: #6b7280; line-height: 1; }
.calc-close:hover { color: #1a1d23; }
.calc-tape { max-height: 110px; overflow-y: auto; border: 1px solid #eef1f4; border-radius: 6px; padding: 6px 8px; margin-bottom: 8px; background: #fafbfc; }
.calc-tape-row { display: flex; justify-content: space-between; align-items: center; font-size: .72rem; padding: 2px 0; color: #37474F; }
.calc-tape-row .val { font-family: var(--font-m); white-space: nowrap; }
.calc-tape-row .rm { cursor: pointer; color: #ef4444; font-size: .68rem; margin-left: 6px; }
.calc-tape-empty { color: #9ca3af; font-size: .68rem; text-align: center; padding: 10px 0; }
.calc-total-row {
  display: flex; justify-content: space-between; align-items: center;
  font-weight: 800; font-size: .84rem; padding: 6px 2px;
  border-top: 1px dashed #e2e6ea; margin-bottom: 8px; color: #166534;
}
.calc-input-row { display: flex; gap: 6px; margin-bottom: 8px; }
.calc-input-row input {
  flex: 1; min-width: 0; border: 1px solid var(--border); border-radius: 6px;
  padding: 6px 8px; font-family: var(--font-m); font-size: .8rem;
}
.calc-add-btn { background: #166534; color: #fff; border: none; border-radius: 6px; padding: 0 12px; font-weight: 700; cursor: pointer; font-size: .75rem; }
.calc-add-btn:hover { background: #14532d; }
.calc-keypad { display: grid; grid-template-columns: repeat(4,1fr); gap: 5px; margin-bottom: 8px; }
.calc-keypad button {
  padding: 7px 0; border: 1px solid var(--border); border-radius: 6px;
  background: #f8f9fb; cursor: pointer; font-size: .78rem; font-family: var(--font-m); color: #1a1d23;
}
.calc-keypad button:hover { background: #eef1f4; }
.calc-keypad button.op  { background: #eff6ff; color: #1e40af; }
.calc-keypad button.clr { background: #fef2f2; color: #991b1b; }
.calc-actions {
  display: flex; gap: 6px;
  position: sticky; bottom: -12px; background: #fff;
  margin: 10px -12px -12px; padding: 8px 12px 12px;
  border-top: 1px solid #eef1f4;
}
.calc-actions button { flex: 1; padding: 7px 0; border-radius: 6px; font-size: .74rem; font-weight: 700; cursor: pointer; border: none; }
.calc-actions .calc-clear-tape { background: #f3f4f6; color: #4b5563; }
.calc-actions .calc-clear-tape:hover { background: #e5e7eb; }
.calc-actions .calc-apply { background: #1e3060; color: #fff; }
.calc-actions .calc-apply:hover { background: #16264a; }
</style>

<!-- Shared quick-add calculator popup (attaches to whichever Amount field was clicked) -->
<div class="calc-popup" id="calcPopup">
  <div class="calc-header">
    <span class="lbl">🧮 Quick Add</span>
    <button type="button" class="calc-close" onclick="closeCalc()">✕</button>
  </div>
  <div class="calc-tape" id="calcTape"></div>
  <div class="calc-total-row"><span>Total</span><span id="calcTotalVal">0.00</span></div>
  <div class="calc-input-row">
    <input type="text" id="calcInput" placeholder="e.g. 500 or 500+250" inputmode="decimal"
           onkeydown="if(event.key==='Enter'){event.preventDefault();calcAddEntry();} else if(event.key==='Escape'){closeCalc();}">
    <button type="button" class="calc-add-btn" onclick="calcAddEntry()">+ Add</button>
  </div>
  <div class="calc-keypad">
    <button type="button" onclick="calcKey('7')">7</button>
    <button type="button" onclick="calcKey('8')">8</button>
    <button type="button" onclick="calcKey('9')">9</button>
    <button type="button" class="clr" onclick="calcBackspace()">⌫</button>
    <button type="button" onclick="calcKey('4')">4</button>
    <button type="button" onclick="calcKey('5')">5</button>
    <button type="button" onclick="calcKey('6')">6</button>
    <button type="button" class="op" onclick="calcKey('-')">−</button>
    <button type="button" onclick="calcKey('1')">1</button>
    <button type="button" onclick="calcKey('2')">2</button>
    <button type="button" onclick="calcKey('3')">3</button>
    <button type="button" class="op" onclick="calcKey('+')">+</button>
    <button type="button" onclick="calcKey('0')">0</button>
    <button type="button" onclick="calcKey('.')">.</button>
    <button type="button" class="clr" onclick="calcClearInput()">C</button>
    <button type="button" class="op" onclick="calcAddEntry()">=</button>
  </div>
  <div class="calc-actions">
    <button type="button" class="calc-clear-tape" onclick="calcClearTape()">Clear All</button>
    <button type="button" class="calc-apply" onclick="calcApply()">Use Total →</button>
  </div>
</div>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title">PDC <span>Tracker</span></div>
    <div class="section-subtitle" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
      Dois Branch · Post-Dated Checks
    </div>
  </div>
  <a href="dois_pdc.php?export_csv=1" class="btn btn-ghost btn-sm"
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
            <td><div class="calc-wrap">
              <input type="number" step="0.01" min="0" class="pdc-input" data-col="amount"
                       value="<?= number_format((float)$r['amount'], 2, '.', '') ?>">
              <button type="button" class="calc-btn" onclick="openCalc(this)" title="Quick-add calculator">🧮</button>
            </div></td>
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
    <td><div class="calc-wrap">
          <input type="number" step="0.01" min="0" class="pdc-input" data-col="amount" value="" placeholder="0.00">
          <button type="button" class="calc-btn" onclick="openCalc(this)" title="Quick-add calculator">🧮</button>
        </div></td>
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
    const res  = await fetch('dois_pdc.php', { method: 'POST', body: fd });
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
    const res  = await fetch('dois_pdc.php', { method: 'POST', body: fd });
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

// ── Quick-add calculator ──────────────────────────────────
// Lets a user tally up several amounts (e.g. individual checks counted
// for cash on hand) instead of adding them up by hand before typing
// a single Amount total.
const fmt = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

let calcTarget = null;
let calcTape   = [];
let calcBtn    = null;

function openCalc(btn) {
  const input = btn.previousElementSibling; // the number input right before the 🧮 button
  if (!input) return;
  calcTarget = input;
  calcBtn    = btn;

  // If the field already has an amount, seed the tape with it so
  // "Use Total" doesn't silently wipe out what was there.
  const existing = parseFloat(input.value) || 0;
  calcTape = existing > 0 ? [{ label: 'Existing amount', value: existing }] : [];

  renderCalcTape();
  document.getElementById('calcInput').value = '';
  document.getElementById('calcPopup').classList.add('open'); // must be visible before we can measure its real height
  positionCalcPopup(btn);
  document.getElementById('calcInput').focus();
}

function positionCalcPopup(btn) {
  const pop    = document.getElementById('calcPopup');
  const rect   = btn.getBoundingClientRect();
  const popW   = 260;
  const margin = 10;

  let left = rect.right - popW;
  if (left < margin) left = margin;
  if (left + popW > window.innerWidth - margin) left = window.innerWidth - popW - margin;
  pop.style.left = left + 'px';

  // Measure the popup's actual rendered height (it's already .open at
  // this point) so long tapes / small screens never push the action
  // buttons off-screen — clamp instead of guessing a fixed threshold.
  const popH        = Math.min(pop.offsetHeight, window.innerHeight - margin * 2);
  const spaceBelow  = window.innerHeight - rect.bottom;
  const spaceAbove  = rect.top;

  if (spaceBelow >= popH + margin || spaceBelow >= spaceAbove) {
    // Enough room below (or more room below than above) — open downward,
    // but clamp so the bottom of the popup never runs past the viewport.
    let top = rect.bottom + 6;
    if (top + popH > window.innerHeight - margin) {
      top = Math.max(margin, window.innerHeight - popH - margin);
    }
    pop.style.bottom = '';
    pop.style.top = top + 'px';
  } else {
    // Not enough room below — open upward, clamped so the top of the
    // popup never runs past the top of the viewport.
    let bottom = window.innerHeight - rect.top + 6;
    if (bottom + popH > window.innerHeight - margin) {
      bottom = Math.max(margin, window.innerHeight - popH - margin);
    }
    pop.style.top = '';
    pop.style.bottom = bottom + 'px';
  }
}

function closeCalc() {
  document.getElementById('calcPopup').classList.remove('open');
  calcTarget = null;
  calcBtn    = null;
}

function calcKey(ch) {
  const inp = document.getElementById('calcInput');
  inp.value += ch;
  inp.focus();
}
function calcBackspace() {
  const inp = document.getElementById('calcInput');
  inp.value = inp.value.slice(0, -1);
  inp.focus();
}
function calcClearInput() {
  const inp = document.getElementById('calcInput');
  inp.value = '';
  inp.focus();
}

// Only digits, + - * / . ( ) and whitespace are allowed — never passed
// to eval, so a stray or pasted character can't run arbitrary code.
function calcSafeEval(expr) {
  expr = (expr || '').trim();
  if (!expr) return null;
  if (!/^[0-9+\-*/.() \t]+$/.test(expr)) return null;
  try {
    const val = Function('"use strict";return (' + expr + ')')();
    return Number.isFinite(val) ? val : null;
  } catch (e) { return null; }
}

function calcAddEntry() {
  const inp = document.getElementById('calcInput');
  const val = calcSafeEval(inp.value);
  if (val === null) { inp.focus(); return; }
  calcTape.push({ label: inp.value.trim(), value: val });
  inp.value = '';
  renderCalcTape();
  inp.focus();
}

function calcRemoveEntry(idx) {
  calcTape.splice(idx, 1);
  renderCalcTape();
}

function calcClearTape() {
  calcTape = [];
  renderCalcTape();
  document.getElementById('calcInput').focus();
}

function calcTapeTotalVal() {
  return calcTape.reduce((s, e) => s + e.value, 0);
}

function renderCalcTape() {
  const tapeEl = document.getElementById('calcTape');
  if (!calcTape.length) {
    tapeEl.innerHTML = '<div class="calc-tape-empty">No entries yet — type an amount and press Add</div>';
  } else {
    tapeEl.innerHTML = calcTape.map((e, i) => `
      <div class="calc-tape-row">
        <span>${escHtml(e.label)}</span>
        <span class="val">${fmt(e.value)}<span class="rm" onclick="calcRemoveEntry(${i})" title="Remove">✕</span></span>
      </div>`).join('');
  }
  document.getElementById('calcTotalVal').textContent = fmt(calcTapeTotalVal());
  if (calcBtn && document.getElementById('calcPopup').classList.contains('open')) {
    positionCalcPopup(calcBtn);
  }
}

function calcApply() {
  if (!calcTarget) return;
  const total = calcTapeTotalVal();
  calcTarget.value = total.toFixed(2);
  calcTarget.dispatchEvent(new Event('input', { bubbles: true })); // triggers recalcTotal() via the listener above
  closeCalc();
}

// Close the popup on outside click, or on scroll/resize since it's
// fixed-positioned and won't follow the button on its own.
document.addEventListener('click', e => {
  if (!e.target.closest('.calc-popup') && !e.target.closest('.calc-btn')) closeCalc();
});
document.addEventListener('scroll', () => closeCalc(), true);
window.addEventListener('resize', () => closeCalc());
</script>
</body>
</html>