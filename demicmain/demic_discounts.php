<?php
// ============================================================
//  demic_discounts.php — Demic Lab Discounts Log
//  DATE / TEST / DISC / TXN / PRICE / AMOUNT
//  AMOUNT = PRICE * TXN * (DISC% / 100)  →  peso value of the discount given
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && !in_array(currentBranch(), ['DemicLab-Main','DemicLab-Jaro'], true)) {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_discounts` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`  date NOT NULL,
    `store_name`  varchar(50) NOT NULL DEFAULT 'Demic Lab',
    `test_name`   varchar(200) NOT NULL DEFAULT '',
    `disc_pct`    decimal(5,2) NOT NULL DEFAULT 0.00,
    `txn`         int(11) NOT NULL DEFAULT 0,
    `price`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `amount`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`  int(4) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Master test list (now DB-backed — manage at demic_tests.php) ──
require_once 'demic_tests_data.php';
$TEST_LIST = getDemicTestList($pdo);

$DISC_OPTIONS = [20, 15, 10, 5];

// ── AJAX: Save row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $id         = (int)($_POST['id'] ?? 0);
        $store_name = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');
        $entryDate  = $_POST['entry_date'] ?? date('Y-m-d');
        $testName   = trim($_POST['test_name'] ?? '');
        $discPct    = (float)($_POST['disc_pct'] ?? 0);
        $txn        = (int)($_POST['txn']   ?? 0);
        $price      = (float)($_POST['price'] ?? 0);
        $so         = (int)($_POST['sort_order'] ?? 0);
        $amount     = round($price * $txn * ($discPct / 100), 2);

        if ($id > 0) {
            $pdo->prepare("UPDATE demic_discounts SET entry_date=?,store_name=?,test_name=?,disc_pct=?,txn=?,price=?,amount=?,sort_order=?,saved_by=? WHERE id=?")
                ->execute([$entryDate,$store_name,$testName,$discPct,$txn,$price,$amount,$so,$user['name'],$id]);
        } else {
            $pdo->prepare("INSERT INTO demic_discounts (entry_date,store_name,test_name,disc_pct,txn,price,amount,sort_order,saved_by) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$entryDate,$store_name,$testName,$discPct,$txn,$price,$amount,$so,$user['name']]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'amount'=>$amount]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_row') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM demic_discounts WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));

[$bClause, $bParams] = branchFilter('store_name');
$stmt = $pdo->prepare("SELECT * FROM demic_discounts WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? AND $bClause ORDER BY entry_date,sort_order,id");
$stmt->execute(array_merge([$fYear, $fMonth], $bParams));
$rows = $stmt->fetchAll();

$totalAmount = 0; $totalTxn = 0;
foreach ($rows as $r) { $totalAmount += (float)$r['amount']; $totalTxn += (int)$r['txn']; }

// ── CSV export ────────────────────────────────────────────
if (($_GET['export_csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demic_discounts_' . $fYear . '-' . str_pad($fMonth,2,'0',STR_PAD_LEFT) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Test','Discount %','TXN','Price','Amount']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['entry_date'], $r['test_name'], $r['disc_pct'], $r['txn'], $r['price'], $r['amount']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['', '', '', $totalTxn, '', $totalAmount]);
    fclose($out);
    exit;
}

$pageTitle  = 'Demic Discounts';
$activePage = 'demic_discounts';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Demic <span>Discounts</span></div>
    <div class="section-subtitle">Discount log — <?=$months[$fMonth-1]?> <?=$fYear?></div>
  </div>
</div>

<!-- Month / Year tabs -->
<div class="card" style="padding:12px 18px;margin-bottom:18px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?month=<?=$m?>&year=<?=$fYear?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?=$fMonth===$m?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>">
      <?=substr($months[$m-1],0,3)?>
    </a>
    <?php endfor; ?>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <a href="demic_tests.php" class="btn-csv">⚙ Manage Tests</a>
      <a href="?month=<?=$fMonth?>&year=<?=$fYear?>&export_csv=1" class="btn-csv">⬇ Download CSV</a>
      <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value"
              class="form-control" style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px;
                     background:var(--surf2);border:1px solid rgba(34,211,165,.3);color:var(--accent)">
        <?php for($y=2050;$y>=2020;$y--): ?>
        <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
</div>

<!-- Summary -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:18px">
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
    <div style="padding:13px 16px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px">Entries</div>
      <div style="font-size:1rem;font-weight:700"><?=count($rows)?></div>
    </div>
    <div style="padding:13px 16px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px">Total TXN</div>
      <div style="font-size:1rem;font-weight:700"><?=$totalTxn?></div>
    </div>
    <div style="padding:13px 16px">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px">Total Discount Amount</div>
      <div style="font-size:1rem;font-weight:700;color:var(--accent2)">₱<?=number_format($totalAmount,2)?></div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="inv-tbl" id="discTbl">
    <thead>
      <tr>
        <th style="min-width:130px">DATE</th>
        <th style="min-width:260px;text-align:left">TEST</th>
        <th style="min-width:90px">DISC</th>
        <th style="min-width:70px">TXN</th>
        <th style="min-width:100px">PRICE</th>
        <th style="min-width:110px">AMOUNT</th>
        <th style="min-width:110px">ACTION</th>
      </tr>
    </thead>
    <tbody id="discTbody">
      <?php foreach ($rows as $r): ?>
      <tr data-id="<?=$r['id']?>">
        <td><input class="ci" type="date" value="<?=$r['entry_date']?>" oninput="rowChanged(this)"></td>
        <td class="item-td">
          <div class="test-combo">
            <input type="hidden" class="ci test-sel" value="<?=htmlspecialchars($r['test_name'])?>">
            <div class="test-combo-display" tabindex="0" onclick="openTestDropdown(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTestDropdown(this);}">
              <span class="test-combo-text<?=$r['test_name']?'':' placeholder'?>"><?=$r['test_name']?htmlspecialchars($r['test_name']):'— Select test —'?></span>
              <svg class="test-combo-arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
        </td>
        <td>
          <select class="ci disc-sel" oninput="calcRow(this)">
            <option value="0" <?=((float)$r['disc_pct']===0.0)?'selected':''?>>—</option>
            <?php foreach ($DISC_OPTIONS as $d): ?>
            <option value="<?=$d?>" <?=((float)$r['disc_pct']===(float)$d)?'selected':''?>><?=$d?>%</option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input class="ci" type="number" step="1" min="0" value="<?=$r['txn']?>" placeholder="0" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['price']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci calc" type="number" value="<?=$r['amount']?>" readonly tabindex="-1"></td>
        <td class="act-td">
          <button class="btn-sv" onclick="saveRow(this)">Save</button>
          <button class="btn-dl" onclick="deleteRow(this)">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <button class="btn-add" onclick="addRow()">+ Add Row</button>
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
.inv-tbl .ci.calc { background:var(--surf3); color:var(--subtext2); font-weight:600; }
.inv-tbl .act-td { white-space:nowrap; }
.btn-sv, .btn-dl {
  font-family:var(--font-m); font-size:.68rem; padding:5px 9px; border-radius:6px;
  border:1px solid var(--border); cursor:pointer; margin-right:4px;
}
.btn-sv { background:var(--accent); color:#031a12; border-color:var(--accent); font-weight:600; }
.btn-dl { background:rgba(248,113,113,.08); color:var(--accent2); border-color:rgba(248,113,113,.2); }
.row-st { display:none; font-size:.68rem; color:var(--accent); margin-top:3px; }
.btn-add {
  margin:12px 16px 16px; padding:9px 14px; width:calc(100% - 32px);
  background:rgba(34,211,165,.05); border:1px dashed rgba(34,211,165,.25);
  color:var(--accent); border-radius:8px; cursor:pointer;
  font-family:var(--font-m); font-size:.74rem; letter-spacing:.02em;
}
.btn-add:hover { background:rgba(34,211,165,.1); }

/* ── Searchable test combobox ── */
.test-combo { position:relative; width:100%; }
.test-combo-display {
  display:flex; align-items:center; justify-content:space-between; gap:6px;
  width:100%; padding:6px 9px; border:1px solid var(--border); border-radius:6px;
  background:var(--surface); color:var(--text); font-family:var(--font-m); font-size:.76rem;
  text-align:left; cursor:pointer; user-select:none; transition:border-color .15s,box-shadow .15s;
}
.test-combo-display:hover { border-color:rgba(34,211,165,.45); }
.test-combo-display:focus-visible,
.test-combo.open .test-combo-display { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(34,211,165,.12); }
.test-combo-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.test-combo-text.placeholder { color:var(--subtext); }
.test-combo-arrow { flex-shrink:0; color:var(--subtext); transition:transform .15s; }
.test-combo.open .test-combo-arrow { transform:rotate(180deg); }

.test-dropdown-panel {
  position:fixed; z-index:9999; display:none; flex-direction:column;
  background:var(--surface); border:1px solid var(--border); border-radius:10px;
  box-shadow:0 16px 40px rgba(0,0,0,.4); overflow:hidden; max-height:340px;
}
.test-dropdown-panel.open { display:flex; }
.test-dropdown-search-wrap {
  display:flex; align-items:center; gap:7px; padding:9px 10px;
  border-bottom:1px solid var(--border); background:var(--surf2); flex-shrink:0;
}
.test-dropdown-search-icon { color:var(--subtext); flex-shrink:0; }
.test-dropdown-search {
  flex:1; min-width:0; border:none; background:transparent; outline:none; color:var(--text);
  font-family:var(--font-m); font-size:.78rem;
}
.test-dropdown-search::placeholder { color:var(--subtext); }
.test-dropdown-list { overflow-y:auto; padding:5px; }
.test-dropdown-item {
  padding:7px 10px; border-radius:6px; font-family:var(--font-m); font-size:.76rem;
  color:var(--text); cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  display:flex; align-items:center; justify-content:space-between; gap:8px;
}
.test-dropdown-item:hover, .test-dropdown-item.hl { background:rgba(34,211,165,.13); color:var(--accent); }
.test-dropdown-item.sel { font-weight:700; color:var(--accent); }
.test-dropdown-item.sel::after { content:'✓'; flex-shrink:0; }
.test-dropdown-empty {
  padding:16px 10px; text-align:center; color:var(--subtext); font-size:.74rem; font-family:var(--font-m);
}

/* ── CSV export button ── */
.btn-csv {
  font-family:var(--font-m); font-size:.74rem; font-weight:600; padding:6px 13px;
  border-radius:6px; cursor:pointer; text-decoration:none; white-space:nowrap;
  background:var(--surf2); color:var(--subtext2); border:1px solid var(--border);
}
.btn-csv:hover { background:var(--surf3); }
</style>

<script>
const gv = el => parseFloat(el?.value) || 0;

/* ── Searchable test combobox ─────────────────────────────── */
const TEST_LIST = <?php echo json_encode(array_values($TEST_LIST)); ?>;

let activeCombo = null;
let hlIndex = -1;
let filteredTests = [];

const testPanel = document.createElement('div');
testPanel.className = 'test-dropdown-panel';
testPanel.innerHTML = `
  <div class="test-dropdown-search-wrap">
    <svg class="test-dropdown-search-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" class="test-dropdown-search" placeholder="Search test…" autocomplete="off">
  </div>
  <div class="test-dropdown-list"></div>`;
document.body.appendChild(testPanel);
const testSearchInput = testPanel.querySelector('.test-dropdown-search');
const testListEl = testPanel.querySelector('.test-dropdown-list');

function escHtml(s) {
  return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderTestList(filter) {
  const q = filter.trim().toLowerCase();
  filteredTests = q ? TEST_LIST.filter(t => t.toLowerCase().includes(q)) : TEST_LIST;
  hlIndex = -1;
  if (!filteredTests.length) {
    testListEl.innerHTML = `<div class="test-dropdown-empty">No matching test found</div>`;
    return;
  }
  const currentVal = activeCombo ? activeCombo.querySelector('.test-sel').value : '';
  testListEl.innerHTML = filteredTests.map((t, i) =>
    `<div class="test-dropdown-item${t === currentVal ? ' sel' : ''}" data-i="${i}">${escHtml(t)}</div>`
  ).join('');
}

function positionTestPanel(el) {
  const r = el.getBoundingClientRect();
  const panelWidth = Math.max(r.width, 260);
  const vw = window.innerWidth, vh = window.innerHeight;
  let left = Math.min(r.left, vw - panelWidth - 8);
  left = Math.max(8, left);
  let top = r.bottom + 4;
  if (top + 340 > vh - 8) top = Math.max(8, r.top - 344);
  testPanel.style.left = left + 'px';
  testPanel.style.top = top + 'px';
  testPanel.style.width = panelWidth + 'px';
}

function openTestDropdown(displayEl) {
  const combo = displayEl.closest('.test-combo');
  if (activeCombo === combo && testPanel.classList.contains('open')) { closeTestDropdown(); return; }
  if (activeCombo) activeCombo.classList.remove('open');
  activeCombo = combo;
  combo.classList.add('open');
  testSearchInput.value = '';
  renderTestList('');
  positionTestPanel(displayEl);
  testPanel.classList.add('open');
  setTimeout(() => testSearchInput.focus(), 0);
}

function closeTestDropdown() {
  testPanel.classList.remove('open');
  if (activeCombo) activeCombo.classList.remove('open');
  activeCombo = null;
}

function selectTest(val) {
  if (!activeCombo) return;
  const hidden = activeCombo.querySelector('.test-sel');
  const label = activeCombo.querySelector('.test-combo-text');
  hidden.value = val;
  label.textContent = val || '— Select test —';
  label.classList.toggle('placeholder', !val);
  closeTestDropdown();
  rowChanged(hidden);
}

function updateHl(items) {
  items.forEach((it, i) => it.classList.toggle('hl', i === hlIndex));
  const hlEl = items[hlIndex];
  if (hlEl) hlEl.scrollIntoView({ block: 'nearest' });
}

testListEl.addEventListener('click', e => {
  const item = e.target.closest('.test-dropdown-item');
  if (!item) return;
  selectTest(filteredTests[+item.dataset.i]);
});

testSearchInput.addEventListener('input', () => renderTestList(testSearchInput.value));

testSearchInput.addEventListener('keydown', e => {
  const items = testListEl.querySelectorAll('.test-dropdown-item');
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    hlIndex = Math.min(hlIndex + 1, items.length - 1);
    updateHl(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    hlIndex = Math.max(hlIndex - 1, 0);
    updateHl(items);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (hlIndex >= 0 && filteredTests[hlIndex] !== undefined) selectTest(filteredTests[hlIndex]);
    else if (filteredTests.length === 1) selectTest(filteredTests[0]);
  } else if (e.key === 'Escape') {
    closeTestDropdown();
  }
});

document.addEventListener('click', e => {
  if (!testPanel.classList.contains('open')) return;
  if (testPanel.contains(e.target) || e.target.closest('.test-combo-display')) return;
  closeTestDropdown();
});
window.addEventListener('resize', () => { if (activeCombo) closeTestDropdown(); });
window.addEventListener('scroll', e => {
  if (!activeCombo) return;
  if (testPanel.contains(e.target)) return; // scrolling inside the dropdown list itself
  closeTestDropdown();
}, true);

function calcRow(inp) {
  const tr = inp.closest('tr');
  const ci = tr.querySelectorAll('.ci');
  // ci[0]=date ci[1]=test ci[2]=disc ci[3]=txn ci[4]=price ci[5]=amount(calc)
  const disc  = gv(ci[2]);
  const txn   = gv(ci[3]);
  const price = gv(ci[4]);
  const amount = price * txn * (disc / 100);
  ci[5].value = amount.toFixed(2);
}
function rowChanged(inp) { /* date/test edits don't need recalculation */ }

function addRow() {
  const tbody = document.getElementById('discTbody');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td><input class="ci" type="date" value="<?=date('Y-m-d')?>" oninput="rowChanged(this)"></td>
    <td class="item-td">
      <div class="test-combo">
        <input type="hidden" class="ci test-sel" value="">
        <div class="test-combo-display" tabindex="0" onclick="openTestDropdown(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTestDropdown(this);}">
          <span class="test-combo-text placeholder">— Select test —</span>
          <svg class="test-combo-arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
    </td>
    <td>
      <select class="ci disc-sel" oninput="calcRow(this)">
        <option value="0" selected>—</option>
        <?php foreach ($DISC_OPTIONS as $d): ?>
        <option value="<?=$d?>"><?=$d?>%</option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input class="ci" type="number" step="1" min="0" placeholder="0" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="number" value="0.00" readonly tabindex="-1"></td>
    <td class="act-td">
      <button class="btn-sv" onclick="saveRow(this)">Save</button>
      <button class="btn-dl" onclick="deleteRow(this)">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  openTestDropdown(tr.querySelector('.test-combo-display'));
}

async function saveRow(btn) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const ci = tr.querySelectorAll('.ci');
  const fd = new FormData();
  fd.append('ajax_action','save_row');
  fd.append('id', tr.dataset.id||0);
  fd.append('entry_date', ci[0].value);
  fd.append('test_name',  ci[1].value);
  fd.append('disc_pct',   gv(ci[2]));
  fd.append('txn',        gv(ci[3]));
  fd.append('price',      gv(ci[4]));
  fd.append('sort_order', Array.from(tr.parentElement.children).indexOf(tr));
  btn.textContent='…'; btn.disabled=true;
  try {
    const r = await fetch('demic_discounts.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
      tr.dataset.id = d.id;
      ci[5].value = Number(d.amount).toFixed(2);
      st.textContent='✓'; st.style.display='block';
      setTimeout(()=>st.style.display='none',2000);
    } else { alert('Error: '+d.msg); }
  } catch(e){ alert('Network error'); }
  btn.textContent='Save'; btn.disabled=false;
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  if (id>0 && !confirm('Delete this entry?')) return;
  if (id>0) {
    const fd=new FormData(); fd.append('ajax_action','delete_row'); fd.append('id',id);
    await fetch('demic_discounts.php',{method:'POST',body:fd});
  }
  tr.remove();
}
</script>

  </div></div>
</body>
</html>