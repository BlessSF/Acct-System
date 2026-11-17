<?php
// ============================================================
//  stella_totals.php — Stella Disbursement "Totals" (per month)
//  Gross per Account Title for the month, PURCHASES TOTAL, DF/FARE/FUEL and TOTAL.
//  Reads live from stella_disbursement. The free-typed line (e.g. LESS EXPENSES BREAKAGE REIMB.) is kept per month.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') { header('Location: ../dashboard.php'); exit; }

$pdo  = getPDO();
$user = currentUser();

$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_acc_titles` (
    `id` int(11) NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL,
    `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses', `sort_order` int(6) NOT NULL DEFAULT 0,
    `saved_by` varchar(100) DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`), UNIQUE KEY `uniq_title` (`title`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_disbursement_total_notes` (
    `ym` char(7) NOT NULL, `note_label` varchar(255) NOT NULL DEFAULT '', `note_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
    `saved_by` varchar(100) DEFAULT NULL, `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`ym`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$fMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $fMonth)) $fMonth = date('Y-m');
$mStart = $fMonth . '-01';
$mEnd   = date('Y-m-d', strtotime($mStart . ' +1 month'));

// PURCHASES TOTAL = Purchases - Non-Vat + Purchases - Vatable
// DF, FARE, FUEL  = Fare & Transportation + Fuel & Oil + Delivery Expense
// TOTAL           = PURCHASES TOTAL + DF, FARE, FUEL
$PT = ['purchases - non-vat', 'purchases - vatable'];
$DF = ['fare & transportation', 'fuel & oil', 'delivery expense'];

function totalsState(PDO $pdo, string $mStart, string $mEnd, array $PT, array $DF): array {
    $st = $pdo->prepare("SELECT account_title, COALESCE(SUM(gross),0) g FROM stella_disbursement WHERE entry_date >= ? AND entry_date < ? GROUP BY account_title");
    $st->execute([$mStart, $mEnd]);
    $sums = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $k = mb_strtolower(trim((string)$r['account_title'])); $sums[$k] = ($sums[$k] ?? 0) + (float)$r['g']; }
    $of = function (array $n) use ($sums) { $t = 0.0; foreach ($n as $x) $t += $sums[$x] ?? 0; return round($t, 2); };
    $p = $of($PT); $d = $of($DF);
    return ['sums' => $sums, 'purch' => $p, 'df' => $d, 'total' => round($p + $d, 2)];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_state'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + totalsState($pdo, $mStart, $mEnd, $PT, $DF)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_total_note'])) {
    header('Content-Type: application/json');
    $ym = $_POST['month'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { echo json_encode(['ok'=>false,'msg'=>'Invalid month']); exit; }
    $lbl = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 255);
    $amt = (float)str_replace(',', '', (string)($_POST['amount'] ?? 0));
    try {
        $pdo->prepare("INSERT INTO stella_disbursement_total_notes (ym,note_label,note_amount,saved_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE note_label=VALUES(note_label), note_amount=VALUES(note_amount), saved_by=VALUES(saved_by)")
            ->execute([$ym, $lbl, $amt, $user['name'] ?? '']);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

$acctRows = $pdo->query("SELECT title, section FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_ASSOC);
$GROUPS = ['assets' => ['Assets & Payables', '🏦'], 'expenses' => ['Expenses', '🧾'], 'other' => ['Other', '📎']];
$byGroup = ['assets' => [], 'expenses' => [], 'other' => []];
foreach ($acctRows as $r) $byGroup[isset($byGroup[$r['section']]) ? $r['section'] : 'other'][] = $r['title'];
$S = totalsState($pdo, $mStart, $mEnd, $PT, $DF);
$tn = $pdo->prepare("SELECT note_label, note_amount FROM stella_disbursement_total_notes WHERE ym=?");
$tn->execute([$fMonth]);
$totalNote = $tn->fetch(PDO::FETCH_ASSOC) ?: ['note_label' => '', 'note_amount' => 0];
$fmtT = function ($v) { return abs($v) < 0.005 ? '-' : number_format($v, 2); };

$pageTitle  = 'Stella Totals';
$activePage = 'stella_disbursement';
include '../layout.php';
?>
<style>
.tt-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:14px 0; }
.tt-kpi { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:14px 18px; border-top:3px solid var(--accent); }
.tt-kpi.b { border-top-color:#3b82f6; } .tt-kpi.v { border-top-color:#8b5cf6; } .tt-kpi.g { border-top-color:var(--accent); background:linear-gradient(135deg,#0f3d2e,#1a4a2e); border-color:#0f3d2e; }
.tt-kpi .l { font-family:var(--font-m); font-size:.6rem; letter-spacing:.09em; text-transform:uppercase; color:var(--subtext); }
.tt-kpi .f { font-family:var(--font-m); font-size:.58rem; color:var(--muted); margin-top:2px; }
.tt-kpi .n { font-family:var(--font-m); font-size:1.45rem; font-weight:800; margin-top:6px; letter-spacing:-.02em; }
.tt-kpi.b .n { color:#2563eb; } .tt-kpi.v .n { color:#7c3aed; }
.tt-kpi.g .l, .tt-kpi.g .f { color:rgba(255,255,255,.6); } .tt-kpi.g .n { color:#6ee7b7; }
.tt-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:14px; }
.tt-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:11px 18px; background:#0f3d2e; color:#fff; }
.tt-head b { font-family:var(--font-m); font-size:.7rem; letter-spacing:.08em; text-transform:uppercase; }
.tt-head label { font-family:var(--font-m); font-size:.62rem; color:rgba(255,255,255,.75); display:flex; gap:6px; align-items:center; cursor:pointer; }
.tt-note { display:flex; gap:10px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; background:#fffbeb; }
.tt-note .t { font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:#92400e; font-weight:700; }
.tt-note input { font-family:var(--font-m); font-size:.78rem; padding:8px 12px; border:1px solid #fde68a; border-radius:8px; background:#fff; color:var(--text); outline:none; }
.tt-note input:focus { border-color:#d97706; box-shadow:0 0 0 3px rgba(217,119,6,.15); }
#totLabel { flex:1; min-width:240px; } #totAmt { width:150px; text-align:right; font-weight:700; }
.tt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(420px,1fr)); }
.tt-grid.vert { grid-template-columns:1fr; max-width:860px; margin:0 auto; }
.tt-seg { display:inline-flex; border:1px solid rgba(255,255,255,.35); border-radius:8px; overflow:hidden; }
.tt-seg button { background:transparent; color:rgba(255,255,255,.8); border:none; padding:4px 12px; font-family:var(--font-m); font-size:.62rem; font-weight:700; cursor:pointer; }
.tt-seg button.on { background:#fff; color:#0f3d2e; }
.tt-tools { display:flex; gap:10px; align-items:center; padding:10px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; background:var(--bg,#f8fafb); }
.tt-tools input[type=search] { flex:1; min-width:200px; font-family:var(--font-m); font-size:.76rem; padding:8px 12px; border:1px solid var(--border); border-radius:8px; outline:none; background:#fff; }
.tt-tools input[type=search]:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(15,123,92,.12); }
.tt-chip { font-family:var(--font-m); font-size:.62rem; font-weight:700; padding:6px 12px; border-radius:20px; border:1px solid var(--border); background:#fff; color:var(--subtext); cursor:pointer; }
.tt-chip.on { background:#0f3d2e; color:#fff; border-color:#0f3d2e; }
.tt-grp { grid-column:1/-1; display:flex; justify-content:space-between; align-items:center; padding:9px 18px; background:#eef5f1; border-bottom:1px solid var(--border); border-top:1px solid var(--border); font-family:var(--font-m); font-size:.64rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#0f3d2e; }
.tt-grp .gs { font-size:.78rem; letter-spacing:0; color:#0f3d2e; }
.tt-row { position:relative; display:grid; grid-template-columns:28px 1fr auto 56px; align-items:center; gap:10px; padding:10px 18px; border-bottom:1px solid var(--border); font-size:.8rem; transition:background .12s; overflow:hidden; }
.tt-row:hover { background:#f0fdf4; }
.tt-row .ix { font-family:var(--font-m); font-size:.58rem; color:var(--muted); text-align:right; }
.tt-row .nm { color:var(--text); font-weight:600; z-index:1; }
.tt-row .a { font-family:var(--font-m); font-weight:800; color:var(--text); text-align:right; z-index:1; min-width:120px; }
.tt-row .pc { font-family:var(--font-m); font-size:.6rem; color:var(--subtext); text-align:right; z-index:1; }
.tt-row .bar { position:absolute; left:0; bottom:0; height:3px; background:linear-gradient(90deg,#10b981,#34d399); width:0; transition:width .3s; }
.tt-row.pur .bar { background:linear-gradient(90deg,#3b82f6,#60a5fa); } .tt-row.dff .bar { background:linear-gradient(90deg,#8b5cf6,#a78bfa); }
.tt-row.zero .nm { color:var(--muted); font-weight:500; } .tt-row.zero .a { color:var(--muted); font-weight:500; } .tt-row.zero .pc { visibility:hidden; }
.tt-row.pur { box-shadow:inset 3px 0 0 #3b82f6; } .tt-row.dff { box-shadow:inset 3px 0 0 #8b5cf6; }
.tt-tag { font-family:var(--font-m); font-size:.5rem; font-weight:700; letter-spacing:.05em; padding:1px 6px; border-radius:8px; margin-left:8px; vertical-align:middle; }
.tt-tag.p { background:#dbeafe; color:#1d4ed8; } .tt-tag.d { background:#ede9fe; color:#6d28d9; }
.tt-foot { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; background:#0f3d2e; color:#fff; font-family:var(--font-m); }
.tt-foot .l { font-size:.66rem; letter-spacing:.09em; text-transform:uppercase; color:rgba(255,255,255,.7); }
.tt-foot .n { font-size:1.2rem; font-weight:800; color:#6ee7b7; }
@media (max-width:760px) { .tt-kpis { grid-template-columns:1fr; } }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:2px">
  <div>
    <div class="section-title">Stella <span>Totals</span></div>
    <div class="section-subtitle">Gross per Account Title for the month · read live from the Disbursement</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:6px;align-items:center;margin:0">
      <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($fMonth) ?>" onchange="this.form.submit()" style="max-width:170px">
    </form>
    <a href="stella_disbursement.php?month=<?= urlencode($fMonth) ?>" class="btn btn-ghost btn-sm" style="font-weight:600">← Disbursement</a>
  </div>
</div>

<div class="tt-kpis">
  <div class="tt-kpi b"><div class="l">Purchases Total</div><div class="f">Purchases - Non-Vat + Purchases - Vatable</div><div class="n" id="totPurch"><?= number_format($S['purch'], 2) ?></div></div>
  <div class="tt-kpi v"><div class="l">DF, Fare, Fuel</div><div class="f">Delivery Expense + Fare &amp; Transportation + Fuel &amp; Oil</div><div class="n" id="totDF"><?= number_format($S['df'], 2) ?></div></div>
  <div class="tt-kpi g"><div class="l">Total — <?= htmlspecialchars(date('F Y', strtotime($mStart))) ?></div><div class="f">Purchases Total + DF, Fare, Fuel</div><div class="n" id="totAll"><?= number_format($S['total'], 2) ?></div></div>
</div>

<div class="tt-card" id="totBox">
  <div class="tt-head">
    <b>Account Titles · <?= htmlspecialchars(date('F Y', strtotime($mStart))) ?></b>
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <div class="tt-seg" role="group" aria-label="Layout">
        <button type="button" id="lyV" onclick="setLayout('vert')">☰ Vertical</button>
        <button type="button" id="lyH" onclick="setLayout('horiz')">▦ Horizontal</button>
      </div>
    </div>
  </div>
  <div class="tt-note">
    <span class="t">Note / less expense</span>
    <input type="text" id="totLabel" placeholder="e.g. LESS EXPENSES BREAKAGE REIMB." value="<?= htmlspecialchars($totalNote['note_label']) ?>" oninput="saveTotNote()">
    <input type="text" id="totAmt" placeholder="0.00" value="<?= (float)$totalNote['note_amount'] == 0 ? '' : number_format((float)$totalNote['note_amount'], 2) ?>" oninput="saveTotNote()">
    <span id="totSt" style="font-family:var(--font-m);font-size:.64rem;color:#92400e"></span>
  </div>
  <div class="tt-tools">
    <input type="search" id="ttSearch" placeholder="Search account title…" oninput="applyFilters()">
    <button type="button" class="tt-chip" id="chipZero" onclick="toggleChip('chipZero')">Only with amount</button>
    <button type="button" class="tt-chip" id="chipSort" onclick="toggleChip('chipSort')">Sort by amount ↓</button>
  </div>
  <div class="tt-grid" id="totTbl">
    <?php $ix = 0; foreach ($byGroup as $gk => $titles): if (!$titles) continue;
          $gsum = 0; foreach ($titles as $t) $gsum += $S['sums'][mb_strtolower(trim($t))] ?? 0; ?>
    <div class="tt-grp" data-grp="<?= $gk ?>"><span><?= $GROUPS[$gk][1] ?> <?= htmlspecialchars($GROUPS[$gk][0]) ?></span><span class="gs"><?= number_format($gsum, 2) ?></span></div>
    <?php foreach ($titles as $t): $k = mb_strtolower(trim($t)); $v = $S['sums'][$k] ?? 0; $ix++;
          $tag = in_array($k, $PT, true) ? 'p' : (in_array($k, $DF, true) ? 'd' : ''); ?>
    <div class="tt-row <?= abs($v) < 0.005 ? 'zero' : '' ?> <?= $tag === 'p' ? 'pur' : ($tag === 'd' ? 'dff' : '') ?>" data-title="<?= htmlspecialchars($k) ?>" data-grp="<?= $gk ?>" data-ix="<?= $ix ?>" data-v="<?= $v ?>">
      <span class="ix"><?= $ix ?></span>
      <span class="nm"><?= htmlspecialchars($t) ?><?php if ($tag === 'p'): ?><span class="tt-tag p">PURCHASES</span><?php elseif ($tag === 'd'): ?><span class="tt-tag d">DF · FARE · FUEL</span><?php endif; ?></span>
      <span class="a"><?= $fmtT($v) ?></span>
      <span class="pc"></span>
      <span class="bar"></span>
    </div>
    <?php endforeach; endforeach; ?>
  </div>
  <div class="tt-foot"><span class="l">All account titles · <?= htmlspecialchars(date('F Y', strtotime($mStart))) ?></span><span class="n" id="totGrand">0.00</span></div>
</div>
</div>

<script>
const MONTH = <?= json_encode($fMonth) ?>;
const fm = v => Math.abs(v) < 0.005 ? '-' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const fm2 = v => v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
// follows the Disbursement live (entries added there show up here within a few seconds)
function setLayout(m) {
  document.getElementById('totTbl').classList.toggle('vert', m === 'vert');
  document.getElementById('lyV').classList.toggle('on', m === 'vert');
  document.getElementById('lyH').classList.toggle('on', m !== 'vert');
  try { localStorage.setItem('stella_totals_layout', m); } catch (e) {}
}
try { setLayout(localStorage.getItem('stella_totals_layout') === 'horiz' ? 'horiz' : 'vert'); } catch (e) { setLayout('vert'); }
const chips = { chipZero: false, chipSort: false };
function toggleChip(id) { chips[id] = !chips[id]; document.getElementById(id).classList.toggle('on', chips[id]); applyFilters(); }

// amounts, % share bars, group subtotals, grand total, filters — all from the row values
function paint() {
  const rows = [...document.querySelectorAll('#totTbl .tt-row')];
  let grand = 0; const gs = {};
  rows.forEach(r => { const v = +r.dataset.v || 0; grand += v; gs[r.dataset.grp] = (gs[r.dataset.grp] || 0) + v; });
  rows.forEach(r => {
    const v = +r.dataset.v || 0, pct = grand > 0 ? Math.max(0, v / grand * 100) : 0;
    r.querySelector('.a').textContent = fm(v); r.classList.toggle('zero', Math.abs(v) < 0.005);
    r.querySelector('.pc').textContent = pct >= 0.05 ? pct.toFixed(1) + '%' : '';
    r.querySelector('.bar').style.width = Math.min(100, pct) + '%';
  });
  document.querySelectorAll('#totTbl .tt-grp').forEach(g => g.querySelector('.gs').textContent = fm2(gs[g.dataset.grp] || 0));
  document.getElementById('totGrand').textContent = fm2(grand);
}
function applyFilters() {
  const q = document.getElementById('ttSearch').value.trim().toLowerCase();
  const rows = [...document.querySelectorAll('#totTbl .tt-row')];
  rows.forEach(r => {
    const hide = (chips.chipZero && r.classList.contains('zero')) || (q && !r.dataset.title.includes(q));
    r.style.display = hide ? 'none' : '';
  });
  // sort: by amount (within each group) or back to the original order
  document.querySelectorAll('#totTbl .tt-grp').forEach(g => {
    const mine = rows.filter(r => r.dataset.grp === g.dataset.grp);
    mine.sort((a, b) => chips.chipSort ? (+b.dataset.v - +a.dataset.v) : (+a.dataset.ix - +b.dataset.ix));
    let ref = g; mine.forEach(r => { ref.after(r); ref = r; });
    g.style.display = mine.some(r => r.style.display !== 'none') ? '' : 'none';
  });
}
async function refreshTotals() {
  if (document.hidden) return;
  try {
    const j = await (await fetch('stella_totals.php?ajax_state=1&month=' + MONTH)).json();
    if (!j.ok) return;
    document.querySelectorAll('#totTbl .tt-row').forEach(r => { r.dataset.v = (j.sums && j.sums[r.dataset.title]) || 0; });
    document.getElementById('totPurch').textContent = fm2(j.purch);
    document.getElementById('totDF').textContent    = fm2(j.df);
    document.getElementById('totAll').textContent   = fm2(j.total);
    paint(); applyFilters();
  } catch (e) {}
}
paint();
setInterval(refreshTotals, 5000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshTotals(); });

let _totT = null;
function saveTotNote() {
  const st = document.getElementById('totSt'); st.textContent = 'typing…';
  clearTimeout(_totT);
  _totT = setTimeout(async () => {
    const fd = new FormData(); fd.append('ajax_total_note', '1'); fd.append('month', MONTH);
    fd.append('label', document.getElementById('totLabel').value); fd.append('amount', document.getElementById('totAmt').value);
    try { const j = await (await fetch('stella_totals.php', {method:'POST', body:fd})).json(); st.textContent = j.ok ? '✓ saved' : '❌ ' + (j.msg || 'not saved'); }
    catch (e) { st.textContent = '❌ network'; }
  }, 600);
}
</script>
</div></div>
</body>
</html>