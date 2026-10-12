<?php
// ============================================================
//  demic_tests.php — Manage the Demic Tests master list
//  Add / rename / remove tests here; changes show up immediately
//  in the TEST dropdown on demic_discounts.php.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && !in_array(currentBranch(), ['DemicLab-Main','DemicLab-Jaro'], true)) {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

require_once 'demic_tests_data.php';
ensureDemicTestsTable($pdo);

// ── AJAX: Add test ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'add_test') {
    header('Content-Type: application/json');
    try {
        $name = trim($_POST['test_name'] ?? '');
        if ($name === '') { echo json_encode(['ok'=>false,'msg'=>'Test name cannot be empty']); exit; }

        $chk = $pdo->prepare("SELECT id FROM demic_tests WHERE test_name = ?");
        $chk->execute([$name]);
        if ($chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'That test already exists']); exit; }

        $pdo->prepare("INSERT INTO demic_tests (test_name) VALUES (?)")->execute([$name]);
        echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'test_name'=>$name]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Rename test ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'rename_test') {
    header('Content-Type: application/json');
    try {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['test_name'] ?? '');
        if ($id <= 0)      { echo json_encode(['ok'=>false,'msg'=>'Invalid test']); exit; }
        if ($name === '')  { echo json_encode(['ok'=>false,'msg'=>'Test name cannot be empty']); exit; }

        $chk = $pdo->prepare("SELECT id FROM demic_tests WHERE test_name = ? AND id != ?");
        $chk->execute([$name, $id]);
        if ($chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'That test already exists']); exit; }

        $pdo->prepare("UPDATE demic_tests SET test_name = ? WHERE id = ?")->execute([$name, $id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete test ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_test') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM demic_tests WHERE id = ?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

$tests = $pdo->query("SELECT id, test_name FROM demic_tests ORDER BY test_name ASC")->fetchAll();

$pageTitle  = 'Demic Tests';
$activePage = 'demic_discounts';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Demic <span>Tests</span></div>
    <div class="section-subtitle">Manage the test list used in the Demic Discounts dropdown</div>
  </div>
  <a href="demic_discounts.php" class="btn-csv">← Back to Discounts</a>
</div>

<!-- Add new test -->
<div class="card" style="padding:14px 18px;margin-bottom:18px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input id="newTestInput" type="text" class="form-control" placeholder="New test name…"
           style="flex:1;min-width:220px;padding:8px 11px;font-family:var(--font-m);font-size:.8rem">
    <button class="btn-add-test" onclick="addTest()">+ Add Test</button>
  </div>
  <div id="addTestMsg" class="add-test-msg"></div>
</div>

<!-- Search + list -->
<div class="card" style="padding:0">
  <div style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <div class="tests-search-wrap">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="testSearch" type="text" placeholder="Search tests…" oninput="filterTests()">
    </div>
    <div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext);text-transform:uppercase;letter-spacing:.06em">
      <span id="testCount"><?=count($tests)?></span> tests
    </div>
  </div>
  <div style="overflow-x:auto">
  <table class="inv-tbl" id="testsTbl">
    <thead>
      <tr>
        <th style="text-align:left;min-width:320px">TEST NAME</th>
        <th style="min-width:110px">ACTION</th>
      </tr>
    </thead>
    <tbody id="testsTbody">
      <?php foreach ($tests as $t): ?>
      <tr data-id="<?=$t['id']?>">
        <td class="item-td"><input class="ci test-name-inp" type="text" value="<?=htmlspecialchars($t['test_name'])?>" oninput="markDirty(this)"></td>
        <td class="act-td">
          <button class="btn-sv" onclick="saveTest(this)" disabled>Save</button>
          <button class="btn-dl" onclick="deleteTest(this)">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div id="testsEmpty" class="tests-empty" style="display:none">No tests match your search</div>
  </div>
</div>

<style>
.inv-tbl { width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.78rem; }
.inv-tbl thead th {
  background:var(--surf2); color:var(--subtext); text-transform:uppercase;
  font-size:.62rem; letter-spacing:.06em; padding:9px 8px; border-bottom:1px solid var(--border);
  text-align:center; white-space:nowrap;
}
.inv-tbl tbody td { padding:6px 8px; border-bottom:1px solid var(--border); text-align:center; }
.inv-tbl .item-td { text-align:left; }
.inv-tbl .ci {
  width:100%; padding:6px 7px; border:1px solid var(--border); border-radius:6px;
  background:var(--surface); color:var(--text); font-family:var(--font-m); font-size:.76rem;
  text-align:center;
}
.inv-tbl .item-td .ci { text-align:left; }
.inv-tbl .act-td { white-space:nowrap; }
.btn-sv, .btn-dl {
  font-family:var(--font-m); font-size:.68rem; padding:5px 9px; border-radius:6px;
  border:1px solid var(--border); cursor:pointer; margin-right:4px;
}
.btn-sv { background:var(--accent); color:#031a12; border-color:var(--accent); font-weight:600; }
.btn-sv:disabled { opacity:.4; cursor:default; }
.btn-dl { background:rgba(248,113,113,.08); color:var(--accent2); border-color:rgba(248,113,113,.2); }
.row-st { display:none; font-size:.68rem; color:var(--accent); margin-top:3px; }

.btn-csv { display:inline-flex; align-items:center; }
.btn-add-test {
  font-family:var(--font-m); font-size:.76rem; font-weight:600; padding:8px 14px;
  border-radius:6px; cursor:pointer; white-space:nowrap;
  background:var(--accent); color:#031a12; border:1px solid var(--accent);
}
.btn-add-test:hover { filter:brightness(1.05); }
.add-test-msg { font-family:var(--font-m); font-size:.72rem; margin-top:8px; display:none; }
.add-test-msg.ok { display:block; color:var(--accent); }
.add-test-msg.err { display:block; color:var(--accent2); }

.tests-search-wrap {
  display:flex; align-items:center; gap:7px; padding:6px 11px;
  border:1px solid var(--border); border-radius:8px; background:var(--surf2);
  color:var(--subtext); min-width:220px; flex:1; max-width:320px;
}
.tests-search-wrap input {
  flex:1; border:none; background:transparent; outline:none; color:var(--text);
  font-family:var(--font-m); font-size:.78rem;
}
.tests-search-wrap input::placeholder { color:var(--subtext); }

.test-name-inp { text-align:left !important; }
.tests-empty {
  padding:26px; text-align:center; color:var(--subtext); font-family:var(--font-m); font-size:.78rem;
}
</style>

<script>
const gvT = el => (el?.value || '').trim();

function markDirty(inp) {
  const tr = inp.closest('tr');
  tr.querySelector('.btn-sv').disabled = false;
}

function showAddMsg(ok, msg) {
  const el = document.getElementById('addTestMsg');
  el.textContent = msg;
  el.className = 'add-test-msg ' + (ok ? 'ok' : 'err');
  clearTimeout(showAddMsg._t);
  showAddMsg._t = setTimeout(() => { el.className = 'add-test-msg'; }, 3000);
}

async function addTest() {
  const input = document.getElementById('newTestInput');
  const name = input.value.trim();
  if (!name) { showAddMsg(false, 'Type a test name first.'); input.focus(); return; }

  const fd = new FormData();
  fd.append('ajax_action', 'add_test');
  fd.append('test_name', name);
  try {
    const r = await fetch('demic_tests.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      addRowToTable(d.id, d.test_name);
      input.value = '';
      showAddMsg(true, `Added "${d.test_name}" ✓`);
      filterTests();
      updateCount();
    } else {
      showAddMsg(false, d.msg || 'Could not add test.');
    }
  } catch (e) { showAddMsg(false, 'Network error.'); }
}

document.getElementById('newTestInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); addTest(); }
});

function addRowToTable(id, name) {
  const tbody = document.getElementById('testsTbody');
  const tr = document.createElement('tr');
  tr.dataset.id = id;
  tr.innerHTML = `
    <td class="item-td"><input class="ci test-name-inp" type="text" value="${escHtmlT(name)}" oninput="markDirty(this)"></td>
    <td class="act-td">
      <button class="btn-sv" onclick="saveTest(this)" disabled>Save</button>
      <button class="btn-dl" onclick="deleteTest(this)">✕</button>
      <div class="row-st"></div>
    </td>`;
  // insert alphabetically
  const rows = Array.from(tbody.querySelectorAll('tr'));
  const target = rows.find(r => r.querySelector('.test-name-inp').value.toLowerCase() > name.toLowerCase());
  if (target) tbody.insertBefore(tr, target); else tbody.appendChild(tr);
}

function escHtmlT(s) {
  return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function saveTest(btn) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const nameInp = tr.querySelector('.test-name-inp');
  const name = gvT(nameInp);
  if (!name) { alert('Test name cannot be empty.'); return; }

  const fd = new FormData();
  fd.append('ajax_action', 'rename_test');
  fd.append('id', tr.dataset.id);
  fd.append('test_name', name);
  btn.textContent = '…'; btn.disabled = true;
  try {
    const r = await fetch('demic_tests.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      st.textContent = '✓'; st.style.display = 'block';
      setTimeout(() => st.style.display = 'none', 2000);
    } else {
      alert('Error: ' + d.msg);
      btn.disabled = false;
    }
  } catch (e) { alert('Network error'); btn.disabled = false; }
  btn.textContent = 'Save';
}

async function deleteTest(btn) {
  const tr = btn.closest('tr');
  const name = tr.querySelector('.test-name-inp').value;
  if (!confirm(`Remove "${name}" from the test list?\nExisting discount entries that already used it won't be affected.`)) return;
  const fd = new FormData();
  fd.append('ajax_action', 'delete_test');
  fd.append('id', tr.dataset.id);
  try {
    await fetch('demic_tests.php', { method: 'POST', body: fd });
    tr.remove();
    updateCount();
    filterTests();
  } catch (e) { alert('Network error'); }
}

function filterTests() {
  const q = document.getElementById('testSearch').value.trim().toLowerCase();
  const rows = document.querySelectorAll('#testsTbody tr');
  let visible = 0;
  rows.forEach(tr => {
    const name = tr.querySelector('.test-name-inp').value.toLowerCase();
    const show = !q || name.includes(q);
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('testsEmpty').style.display = visible === 0 ? 'block' : 'none';
}

function updateCount() {
  document.getElementById('testCount').textContent = document.querySelectorAll('#testsTbody tr').length;
}
</script>

  </div></div>
</body>
</html>