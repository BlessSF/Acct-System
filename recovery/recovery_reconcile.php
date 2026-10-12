<?php
// ============================================================
//  recovery_reconcile.php — Recovery Branch Bank Reconciliation
//  Bank Side (Ending Balance per Bank + Deposits in Transit
//  − Outstanding Checks) vs Book Side (Ending Balance per
//  Books, auto-pulled from Cashflow + Bank Credits − Bank
//  Charges). Variance = Adjusted Book Balance − Adjusted Bank
//  Balance, mirrors the standard Excel bank-rec worksheet.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Recovery') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ─────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_reconcile` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `store_name`            varchar(50) NOT NULL DEFAULT 'Recovery',
    `rec_year`              int(4) NOT NULL,
    `rec_month`             tinyint(2) NOT NULL,
    `ending_balance_bank`   decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
    `deposits_in_transit`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `outstanding_checks`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_credits`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_charges`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`              varchar(100) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Previously computed-only fields are now directly editable — add columns
// to store whatever the user has typed (or the last auto-filled value).
foreach (['ending_balance_books', 'adjusted_bank_balance', 'adjusted_book_balance'] as $newCol) {
    try {
        $pdo->exec("ALTER TABLE `recovery_reconcile` ADD COLUMN `$newCol` decimal(12,2) DEFAULT NULL");
    } catch (Throwable $ignored) {} // column already exists — ignore
}

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ─────────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

$lastDay     = date('Y-m-d', mktime(0,0,0,$fMonth+1,0,$fYear));
$displayDate = date('n/j/Y', strtotime($lastDay));

// ── Helper: live-pull Ending Balance per Books from the Cashflow
//    Balance ledger's running total (SUM cash_in − SUM cash_out),
//    with diagnostics ──────
function pullBooksEndingBalance(PDO $pdo, string $store, int $year, int $month): array {
    $debug = ['row_found' => null, 'raw_value' => null, 'error' => null];
    try {
        // Ending Balance per Books = running balance total from the Cashflow
        // Balance ledger (SUM(cash_in) − SUM(cash_out)) for this month/year.
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(cash_in),0) - COALESCE(SUM(cash_out),0) AS running_total, COUNT(*) AS row_count
            FROM recovery_cashflow_balance
            WHERE store_name=? AND entry_year=? AND entry_month=?
        ");
        $stmt->execute([$store, $year, $month]);
        $row = $stmt->fetch();
        if ($row && (int)$row['row_count'] > 0) {
            $debug['row_found'] = true;
            $debug['raw_value'] = $row['running_total'];
            return [(float)$row['running_total'], $debug];
        }
        $debug['row_found'] = false;
        $debug['error'] = "No Cashflow Balance entries saved yet for $month/$year — add rows on the Cashflow Balance page first, or enter Ending Balance per Books manually below.";
        return [0.0, $debug];
    } catch (Throwable $e) {
        $debug['error'] = $e->getMessage();
        return [0.0, $debug];
    }
}

// ── AJAX: Save ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $endBank   = (float)($_POST['ending_balance_bank'] ?? 0);
        $depTrans  = (float)($_POST['deposits_in_transit'] ?? 0);
        $outChecks = (float)($_POST['outstanding_checks']  ?? 0);
        $bankCred  = (float)($_POST['bank_credits']         ?? 0);
        $bankChg   = (float)($_POST['bank_charges']          ?? 0);

        // Ending Balance per Books, Adjusted Bank Balance and Adjusted Book
        // Balance are read-only/auto-computed — always recompute them fresh
        // server-side rather than trusting whatever the client last had
        // loaded (which could be stale if Cashflow Balance changed since).
        [$endBooks, ] = pullBooksEndingBalance($pdo, 'Recovery', $fYear, $fMonth);
        $adjBank   = $endBank + $depTrans - $outChecks;
        $adjBook   = $endBooks + $bankCred - $bankChg;
        $variance  = $adjBook - $adjBank;

        $pdo->prepare("
            INSERT INTO recovery_reconcile (store_name, rec_year, rec_month, ending_balance_bank, deposits_in_transit, outstanding_checks, ending_balance_books, bank_credits, bank_charges, adjusted_bank_balance, adjusted_book_balance, saved_by)
            VALUES ('Recovery', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ending_balance_bank=VALUES(ending_balance_bank),
                deposits_in_transit=VALUES(deposits_in_transit),
                outstanding_checks=VALUES(outstanding_checks),
                ending_balance_books=VALUES(ending_balance_books),
                bank_credits=VALUES(bank_credits),
                bank_charges=VALUES(bank_charges),
                adjusted_bank_balance=VALUES(adjusted_bank_balance),
                adjusted_book_balance=VALUES(adjusted_book_balance),
                saved_by=VALUES(saved_by)
        ")->execute([$fYear, $fMonth, $endBank, $depTrans, $outChecks, $endBooks, $bankCred, $bankChg, $adjBank, $adjBook, $user['name']]);

        echo json_encode(['ok'=>true,'adjusted_bank'=>$adjBank,'adjusted_book'=>$adjBook,'variance'=>$variance]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ──────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $stmt = $pdo->prepare("SELECT * FROM recovery_reconcile WHERE store_name='Recovery' AND rec_year=? AND rec_month=?");
    $stmt->execute([$fYear,$fMonth]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $endBank   = (float)($r['ending_balance_bank'] ?? 0);
    $depTrans  = (float)($r['deposits_in_transit'] ?? 0);
    $outChecks = (float)($r['outstanding_checks']  ?? 0);
    $bankCred  = (float)($r['bank_credits']         ?? 0);
    $bankChg   = (float)($r['bank_charges']          ?? 0);
    [$pulledBooks, ] = pullBooksEndingBalance($pdo, 'Recovery', $fYear, $fMonth);
    $endBooks = $pulledBooks;
    $adjBank  = $endBank + $depTrans - $outChecks;
    $adjBook  = $endBooks + $bankCred - $bankChg;
    $variance = $adjBook - $adjBank;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Recovery_Reconcile_'.$months[$fMonth-1].'_'.$fYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['BANK RECONCILIATION']);
    fputcsv($out, ['Recovery Branch', $months[$fMonth-1].' '.$fYear]);
    fputcsv($out, []);
    fputcsv($out, ['Bank Side']);
    fputcsv($out, ['Ending Balance per Bank', number_format($endBank,2,'.','')]);
    fputcsv($out, ['Add: Deposits in Transit', number_format($depTrans,2,'.','')]);
    fputcsv($out, ['Less: Outstanding Checks', number_format($outChecks,2,'.','')]);
    fputcsv($out, ['Adjusted Bank Balance', number_format($adjBank,2,'.','')]);
    fputcsv($out, []);
    fputcsv($out, ['Book Side']);
    fputcsv($out, ['Ending Balance per Books', number_format($endBooks,2,'.','')]);
    fputcsv($out, ['Add: Bank Credits', number_format($bankCred,2,'.','')]);
    fputcsv($out, ['Less: Bank Charges', number_format($bankChg,2,'.','')]);
    fputcsv($out, ['Adjusted Book Balance', number_format($adjBook,2,'.','')]);
    fputcsv($out, []);
    fputcsv($out, ['Variance', number_format($variance,2,'.','')]);
    fclose($out);
    exit;
}

// ── Fetch existing row for display ─────────────────────────
$stmt = $pdo->prepare("SELECT * FROM recovery_reconcile WHERE store_name='Recovery' AND rec_year=? AND rec_month=?");
$stmt->execute([$fYear,$fMonth]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$endBank   = (float)($row['ending_balance_bank'] ?? 0);
$depTrans  = (float)($row['deposits_in_transit'] ?? 0);
$outChecks = (float)($row['outstanding_checks']  ?? 0);
$bankCred  = (float)($row['bank_credits']         ?? 0);
$bankChg   = (float)($row['bank_charges']          ?? 0);

[$pulledBooks, $booksDebug] = pullBooksEndingBalance($pdo, 'Recovery', $fYear, $fMonth);

// Ending Balance per Books, Adjusted Bank Balance and Adjusted Book Balance
// are all directly editable now. If this month was already saved, use
// exactly what was saved (it may have been manually overridden); otherwise
// fall back to the live auto-pull / computed defaults.
// Ending Balance per Books, Adjusted Bank Balance and Adjusted Book Balance
// are all read-only/auto-computed now — always use the live values, never
// a previously-saved snapshot (which could go stale, e.g. if Cashflow
// Balance entries change after this reconciliation was last saved).
$endBooks = $pulledBooks;
$adjBank  = $endBank + $depTrans - $outChecks;
$adjBook  = $endBooks + $bankCred - $bankChg;
$variance = $adjBook - $adjBank;

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'Recovery Reconcile';
$activePage = 'recovery_reconcile';
include '../layout.php';
?>

<style>
.rc-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.rc-header-card .eyebrow {
  font-family: var(--font-m); font-size: .58rem; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.rc-header-card .title { font-size: 1.2rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.rc-header-card .subtitle { font-family: var(--font-m); font-size: .67rem; color: rgba(255,255,255,.5); margin-top: 4px; }

.rc-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }

.rc-wrap { max-width: 720px; margin: 0 auto; }
.rc-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: 0 2px 12px rgba(0,0,0,.06);
  overflow: hidden;
}

.rc-title-row { background: #1e3060; padding: 14px 28px; text-align: center; }
.rc-title-row .rc-main-title {
  font-size: 1rem; font-weight: 800; color: #fff;
  letter-spacing: .04em; text-transform: uppercase; font-family: var(--font-m);
}

.rc-section-row { background: #f8f9fb; padding: 10px 28px; border-bottom: 1px solid var(--border); }
.rc-section-row .rc-section-title {
  font-family: var(--font-m); font-size: .68rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .1em; color: var(--subtext2);
}

.rc-row {
  display: flex; align-items: center; padding: 0 28px;
  border-bottom: 1px solid #f0f2f5; min-height: 46px; transition: background .1s;
}
.rc-row:hover { background: #fafbfc; }

.rc-label { flex: 1; font-size: .82rem; color: var(--text); font-weight: 500; }
.rc-label.bold { font-weight: 700; }

.rc-input-wrap { display: flex; align-items: center; }
.rc-input {
  width: 150px; padding: 7px 12px; text-align: right;
  font-family: var(--font-m); font-size: .82rem; color: var(--text);
  background: #fff; border: 1px solid var(--border); border-radius: 7px;
  outline: none; transition: border-color .15s, box-shadow .15s;
}
.rc-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }
.rc-input.manual-field { background: #fffbeb; border-color: rgba(217,119,6,.25); }
.rc-input.auto-field {
  background: #f0f4ff; color: #3b5bdb; font-weight: 700;
  cursor: default; border-color: rgba(59,91,219,.2);
}
.auto-badge {
  font-family: var(--font-m); font-size: .58rem;
  color: var(--accent); margin-left: 8px;
  background: rgba(15,123,92,.08); padding: 2px 7px;
  border-radius: 10px; border: 1px solid rgba(15,123,92,.15);
  white-space: nowrap;
}

.rc-total {
  display: flex; align-items: center; padding: 12px 28px;
  border-top: 2px solid var(--border2); border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
.rc-total-label { flex: 1; font-size: .85rem; font-weight: 700; font-style: italic; color: var(--text); }
.rc-total-val { font-family: var(--font-m); font-size: 1rem; font-weight: 800; min-width: 150px; text-align: right; }

.rc-variance-block { padding: 14px 28px; text-align: center; }
.rc-variance-label {
  font-family: var(--font-m); font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .1em; color: var(--subtext2);
  margin-bottom: 6px;
}
.rc-variance-val { font-family: var(--font-m); font-size: 1.6rem; font-weight: 800; }
.rc-variance-note { font-family: var(--font-m); font-size: .66rem; color: var(--subtext); margin-top: 6px; }

.rc-save-status { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

.rc-debug-box {
  max-width: 720px; margin: 0 auto 16px; padding: 10px 16px;
  border-radius: 8px; font-family: var(--font-m); font-size: .68rem;
  line-height: 1.6;
}
.rc-debug-box.ok    { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.rc-debug-box.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
</style>

<!-- Page Header -->
<div class="rc-header-card">
  <div>
    <div class="eyebrow">Recovery Branch · Finance</div>
    <div class="title">Bank Reconciliation</div>
    <div class="subtitle">Bank Side vs Book Side — Ending Balance per Books auto-pulls from Cashflow Balance's running total</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 Recovery
  </span>
</div>

<!-- Controls -->
<div class="rc-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:140px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for($y=date('Y')-5;$y<=date('Y')+10;$y++): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button type="button" class="btn btn-primary" onclick="saveReconcile()">💾 Save</button>
    <a href="recovery_reconcile.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="rc-save-status"></span>
  </form>
</div>

<?php if ($booksDebug['row_found'] === false): ?>
<div class="rc-debug-box error">
  ⚠ ENDING BALANCE PER BOOKS AUTO-PULL: <?= htmlspecialchars($booksDebug['error']) ?>
</div>
<?php elseif ($booksDebug['error']): ?>
<div class="rc-debug-box error">
  ⚠ ENDING BALANCE PER BOOKS AUTO-PULL: <?= htmlspecialchars($booksDebug['error']) ?>
</div>
<?php else: ?>
<div class="rc-debug-box ok">
  ✓ ENDING BALANCE PER BOOKS AUTO-PULL OK — Running Balance total (Cashflow Balance) for <?= $months[$fMonth-1] ?> <?= $fYear ?>: <?= htmlspecialchars((string)$booksDebug['raw_value']) ?>
</div>
<?php endif; ?>

<!-- Statement -->
<div class="rc-wrap">
<div class="rc-card">

  <div class="rc-title-row">
    <div class="rc-main-title">Bank Reconciliation</div>
  </div>

  <div class="rc-row">
    <div class="rc-label">For the month Ending</div>
    <div class="rc-input-wrap">
      <span class="rc-input" style="background:#f8f9fb;color:var(--subtext2);font-weight:700;text-align:right;border-color:transparent"><?= $displayDate ?></span>
    </div>
  </div>

  <!-- ── Bank Side ── -->
  <div class="rc-section-row"><div class="rc-section-title">Bank Side</div></div>

  <div class="rc-row">
    <div class="rc-label">Ending Balance per Bank</div>
    <div class="rc-input-wrap">
      <input type="number" step="0.01" class="rc-input manual-field" id="ending_balance_bank" value="<?= $endBank ? number_format($endBank,2,'.','') : '' ?>" oninput="onBankRawChange()" placeholder="0.00">
    </div>
  </div>

  <div class="rc-row">
    <div class="rc-label">Add: Deposits in Transit</div>
    <div class="rc-input-wrap">
      <input type="number" step="0.01" class="rc-input manual-field" id="deposits_in_transit" value="<?= $depTrans ? number_format($depTrans,2,'.','') : '' ?>" oninput="onBankRawChange()" placeholder="0.00">
    </div>
  </div>

  <div class="rc-row">
    <div class="rc-label">Less: Outstanding Checks</div>
    <div class="rc-input-wrap">
      <input type="number" step="0.01" class="rc-input manual-field" id="outstanding_checks" value="<?= $outChecks ? number_format($outChecks,2,'.','') : '' ?>" oninput="onBankRawChange()" placeholder="0.00">
    </div>
  </div>

  <div class="rc-total">
    <div class="rc-total-label">Adjusted Bank Balance</div>
    <div class="rc-input-wrap">
      <input type="text" class="rc-input auto-field" style="font-weight:800" id="adjusted_bank_balance" value="<?= number_format($adjBank,2,'.','') ?>" readonly tabindex="-1">
    </div>
  </div>

  <!-- ── Book Side ── -->
  <div class="rc-section-row"><div class="rc-section-title">Book Side</div></div>

  <div class="rc-row">
    <div class="rc-label">
      Ending Balance per Books
      <span class="auto-badge">↳ Auto from Cashflow Balance (Running Total)</span>
    </div>
    <div class="rc-input-wrap">
      <input type="text" class="rc-input auto-field" id="ending_balance_books" value="<?= number_format($endBooks,2,'.','') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="rc-row">
    <div class="rc-label">Add: Bank Credits</div>
    <div class="rc-input-wrap">
      <input type="number" step="0.01" class="rc-input manual-field" id="bank_credits" value="<?= $bankCred ? number_format($bankCred,2,'.','') : '' ?>" oninput="onBookRawChange()" placeholder="0.00">
    </div>
  </div>

  <div class="rc-row">
    <div class="rc-label">Less: Bank Charges</div>
    <div class="rc-input-wrap">
      <input type="number" step="0.01" class="rc-input manual-field" id="bank_charges" value="<?= $bankChg ? number_format($bankChg,2,'.','') : '' ?>" oninput="onBookRawChange()" placeholder="0.00">
    </div>
  </div>

  <div class="rc-total">
    <div class="rc-total-label">Adjusted Book Balance</div>
    <div class="rc-input-wrap">
      <input type="text" class="rc-input auto-field" style="font-weight:800" id="adjusted_book_balance" value="<?= number_format($adjBook,2,'.','') ?>" readonly tabindex="-1">
    </div>
  </div>

  <!-- ── Variance ── -->
  <div class="rc-variance-block">
    <div class="rc-variance-label">Variance (Adjusted Book − Adjusted Bank)</div>
    <div class="rc-variance-val" id="variance" style="color:<?= abs($variance) < 0.005 ? '#166534' : '#991b1b' ?>"><?= $fmt($variance) ?></div>
    <div class="rc-variance-note" id="varianceNote"><?= abs($variance) < 0.005 ? '✓ Books and bank are in balance' : '⚠ Balances do not match — review your entries' ?></div>
  </div>

</div>
</div>

  </div></div>

<script>
const R_YEAR  = <?= $fYear ?>;
const R_MONTH = <?= $fMonth ?>;
function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val.toFixed(2); }

// Bank-side raw fields changed → refresh Adjusted Bank Balance (still editable
// afterwards — typing directly into it won't get overwritten unless one of
// these three raw fields changes again).
function onBankRawChange() {
  const adjBank = gv('ending_balance_bank') + gv('deposits_in_transit') - gv('outstanding_checks');
  setVal('adjusted_bank_balance', adjBank);
  recalcVariance();
}

// Book-side raw fields changed → refresh Adjusted Book Balance the same way.
function onBookRawChange() {
  const adjBook = gv('ending_balance_books') + gv('bank_credits') - gv('bank_charges');
  setVal('adjusted_book_balance', adjBook);
  recalcVariance();
}

// Variance is always derived live from whatever is currently in the two
// Adjusted Balance fields — the only field on this page that stays read-only.
function recalcVariance() {
  const adjBank  = gv('adjusted_bank_balance');
  const adjBook  = gv('adjusted_book_balance');
  const variance = adjBook - adjBank;

  const varEl  = document.getElementById('variance');
  const noteEl = document.getElementById('varianceNote');
  const balanced = Math.abs(variance) < 0.005;
  varEl.textContent  = fmt(variance);
  varEl.style.color  = balanced ? '#166534' : '#991b1b';
  noteEl.textContent = balanced ? '✓ Books and bank are in balance' : '⚠ Balances do not match — review your entries';
}

async function saveReconcile() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const fd = new FormData();
  fd.append('ajax_save', '1');
  ['ending_balance_bank','deposits_in_transit','outstanding_checks',
   'bank_credits','bank_charges'].forEach(id => {
    fd.append(id, gv(id));
  });

  try {
    const res  = await fetch('recovery_reconcile.php?year='+R_YEAR+'&month='+R_MONTH, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      showToast('✓ Bank reconciliation saved', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      status.textContent = '❌ Error';
    }
  } catch(e) {
    showToast('❌ Network error', 'error');
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', recalcVariance);
</script>
</body>
</html>