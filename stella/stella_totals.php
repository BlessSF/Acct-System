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

$acctTitles = $pdo->query("SELECT title FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_COLUMN);
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
.tot-box { margin-top:14px; background:#FFC299; border:1px solid #e0a070; border-radius:10px; padding:14px 18px 18px; }
.tot-box h3 { margin:0 0 4px; text-align:center; font-family:var(--font-h); font-size:.95rem; letter-spacing:.04em; color:#1a1d23; }
.tot-note { display:flex; gap:10px; justify-content:center; align-items:center; margin:6px 0 12px; flex-wrap:wrap; }
.tot-note input { font-family:var(--font-m); font-size:.76rem; font-weight:700; padding:6px 10px; border:1px solid #d79a69; border-radius:6px; background:#fff3e8; color:#1a1d23; }
.tot-tbl { border-collapse:collapse; font-family:Arial,Helvetica,sans-serif; font-size:.82rem; width:100%; }
.tot-tbl td { padding:1px 10px; white-space:nowrap; border-bottom:1px solid rgba(255,255,255,.55); }
.tot-tbl td.amt { text-align:right; font-weight:700; color:#e00000; min-width:110px; }
.tot-tbl td.amt.zero { color:#111; }
.tot-sum { margin-top:18px; width:100%; }
.tot-sum td.lbl { color:#2b5fd9; font-weight:700; }
.tot-sum td.amt { font-size:.9rem; }
.tot-sum tr.grand td { color:#111; font-weight:800; }
.tot-sum tr.grand td.amt { color:#111; border-top:2px solid #111; }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:6px">
  <div>
    <div class="section-title">Stella <span>Totals</span></div>
    <div class="section-subtitle">Gross per Account Title for the month · PURCHASES TOTAL · DF, FARE, FUEL · TOTAL — read live from the Disbursement</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:6px;align-items:center;margin:0">
      <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($fMonth) ?>" onchange="this.form.submit()" style="max-width:170px">
    </form>
    <a href="stella_disbursement.php?month=<?= urlencode($fMonth) ?>" class="btn btn-ghost btn-sm" style="font-weight:600">← Disbursement</a>
  </div>
</div>

<div class="tot-box" id="totBox">
  <h3>TOTAL — <?= strtoupper(date('F Y', strtotime($mStart))) ?></h3>
  <div class="tot-note">
    <input type="text" id="totLabel" size="40" placeholder="e.g. LESS EXPENSES BREAKAGE REIMB." value="<?= htmlspecialchars($totalNote['note_label']) ?>" oninput="saveTotNote()">
    <input type="text" id="totAmt" size="12" style="text-align:right" placeholder="0.00" value="<?= (float)$totalNote['note_amount'] == 0 ? '' : number_format((float)$totalNote['note_amount'], 2) ?>" oninput="saveTotNote()">
    <span id="totSt" style="font-family:var(--font-m);font-size:.64rem;color:#7a4a25"></span>
  </div>
  <table class="tot-tbl" id="totTbl">
    <?php foreach ($acctTitles as $t): $k = mb_strtolower(trim($t)); $v = $S['sums'][$k] ?? 0; ?>
    <tr data-title="<?= htmlspecialchars($k) ?>">
      <td><?= htmlspecialchars($t) ?></td>
      <td class="amt <?= abs($v) < 0.005 ? 'zero' : '' ?>"><?= $fmtT($v) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <table class="tot-tbl tot-sum">
    <tr><td class="lbl">PURCHASES TOTAL</td><td class="amt" id="totPurch"><?= number_format($S['purch'], 2) ?></td></tr>
    <tr><td class="lbl">DF, FARE, FUEL</td><td class="amt" id="totDF"><?= number_format($S['df'], 2) ?></td></tr>
    <tr class="grand"><td class="lbl" style="color:#111" title="PURCHASES TOTAL + DF, FARE, FUEL">TOTAL</td><td class="amt" id="totAll"><?= number_format($S['total'], 2) ?></td></tr>
  </table>
</div>

<script>
const MONTH = <?= json_encode($fMonth) ?>;
const fm = v => Math.abs(v) < 0.005 ? '-' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const fm2 = v => v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
// follows the Disbursement live (entries added there show up here within a few seconds)
async function refreshTotals() {
  if (document.hidden) return;
  try {
    const j = await (await fetch('stella_totals.php?ajax_state=1&month=' + MONTH)).json();
    if (!j.ok) return;
    document.querySelectorAll('#totTbl tr[data-title]').forEach(tr => {
      const v = (j.sums && j.sums[tr.dataset.title]) || 0, td = tr.children[1];
      td.textContent = fm(v); td.classList.toggle('zero', Math.abs(v) < 0.005);
    });
    document.getElementById('totPurch').textContent = fm2(j.purch);
    document.getElementById('totDF').textContent    = fm2(j.df);
    document.getElementById('totAll').textContent   = fm2(j.total);
  } catch (e) {}
}
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