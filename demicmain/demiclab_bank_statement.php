<?php
// ============================================================
//  demiclab_bank_statement.php — DemicLab-Main Branch Bank Statement
//  Cash in Bank / Accounts Receivable / Outstanding Checks
//  detail rows — auto-calculated Closing Balance & Net
//  Increase/Decrease in Cash (mirrors the Excel "Bank Statement"
//  sheet). Built following the same patterns as ../h/h_sales_report.php.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Only management (non-branch users) may lock/unlock a date's statement.
$canLock = !isBranch();

// ── Main summary table ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_bank_statement` (
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `store_name`       varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
    `report_date`      date NOT NULL,
    `opening_balance`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `roi_pull_out`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `closing_balance`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_change`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `remarks`          text DEFAULT NULL,
    `saved_by`         varchar(100) DEFAULT NULL,
    `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`       timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Detail rows table (Cash in Bank / Accounts Receivable /
//    Outstanding Checks — each a free-form Name + Amount list) ──
$pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_bank_statement_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
    `report_date`   date NOT NULL,
    `section`       varchar(40) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Own lock table (kept separate from report_locks so locking a
//    Bank Statement date never affects Sales/Summary Report locks) ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_bank_statement_locks` (
        `id`          int(11) NOT NULL AUTO_INCREMENT,
        `store_name`  varchar(50) NOT NULL,
        `report_date` date NOT NULL,
        `locked_by`   varchar(100) DEFAULT NULL,
        `locked_at`   timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

function isBsDateLocked(PDO $pdo, string $store, string $date): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM demiclab_bank_statement_locks WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $date]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// Sections => label + default seed rows (only used to pre-populate a
// brand-new date so the recurring Cash in Bank lines don't need to be
// retyped every day).
$SECTIONS = [
    'cash_in_bank'        => ['label' => 'CASH IN BANK',        'seed' => ['Ending Balance', 'Deposit in Transit', 'Pettycash Store', 'Pettycash Carwash']],
    'accounts_receivable' => ['label' => 'ACCOUNTS RECEIVABLE', 'seed' => []],
    'outstanding_checks'  => ['label' => 'OUTSTANDING CHECKS',  'seed' => []],
];

// ── AJAX: Lock / unlock a date ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_toggle_lock'])) {
    header('Content-Type: application/json');
    if (!$canLock) {
        echo json_encode(['ok' => false, 'msg' => 'Only management can lock or unlock reports.']);
        exit;
    }
    $reportDate = $_POST['report_date'] ?? '';
    $lock       = ($_POST['lock'] ?? '') === '1';
    if (!$reportDate) {
        echo json_encode(['ok' => false, 'msg' => 'Missing date.']);
        exit;
    }
    try {
        if ($lock) {
            $pdo->prepare("INSERT INTO demiclab_bank_statement_locks (store_name, report_date, locked_by)
                VALUES ('DemicLab-Main', ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([$reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM demiclab_bank_statement_locks WHERE store_name='DemicLab-Main' AND report_date=?")
                ->execute([$reportDate]);
        }
        echo json_encode(['ok' => true, 'locked' => $lock]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save a section's rows (Cash in Bank / Accounts
//          Receivable / Outstanding Checks) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_rows'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $section    = $_POST['section'];
        if (!isset($SECTIONS[$section])) throw new Exception('Unknown section.');
        if (isBsDateLocked($pdo, 'DemicLab-Main', $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM demiclab_bank_statement_rows WHERE store_name='DemicLab-Main' AND report_date=? AND section=?")->execute([$reportDate, $section]);
        $ins = $pdo->prepare("INSERT INTO demiclab_bank_statement_rows (store_name,report_date,section,item_name,amount,sort_order) VALUES ('DemicLab-Main',?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $section, $r['name'] ?? null, (float)($r['amount'] ?? 0), $i]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Save main fields (Opening Balance / ROI Pull Out /
//          Remarks) — Closing Balance & Net Change are derived
//          from the section totals and stored alongside. ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (isBsDateLocked($pdo, 'DemicLab-Main', $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        $roiPullOut     = (float)($_POST['roi_pull_out'] ?? 0);
        $remarks        = trim((string)($_POST['remarks'] ?? ''));

        $sumStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM demiclab_bank_statement_rows WHERE store_name='DemicLab-Main' AND report_date=? GROUP BY section");
        $sumStmt->execute([$reportDate]);
        $sums = [];
        foreach ($sumStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $sums[$r['section']] = (float)$r['total'];

        // CLOSING BALANCE (mirrors the Excel): Cash in Bank + Accounts
        // Receivable − Outstanding Checks.
        $closingBalance = ($sums['cash_in_bank'] ?? 0) + ($sums['accounts_receivable'] ?? 0) - ($sums['outstanding_checks'] ?? 0);
        // NET INCREASE/DECREASE IN CASH: Closing Balance − Opening
        // Balance. ROI Pull Out is a memo line only — the Excel does
        // not subtract it from this figure.
        $netChange = $closingBalance - $openingBalance;

        $pdo->prepare("INSERT INTO demiclab_bank_statement (store_name, report_date, opening_balance, roi_pull_out, closing_balance, net_change, remarks, saved_by)
            VALUES ('DemicLab-Main', ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE opening_balance=VALUES(opening_balance), roi_pull_out=VALUES(roi_pull_out),
            closing_balance=VALUES(closing_balance), net_change=VALUES(net_change), remarks=VALUES(remarks), saved_by=VALUES(saved_by)")
            ->execute([$reportDate, $openingBalance, $roiPullOut, $closingBalance, $netChange, $remarks, $user['name']]);

        echo json_encode(['ok' => true, 'closing_balance' => $closingBalance, 'net_change' => $netChange]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── CSV Export ─────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM demiclab_bank_statement WHERE store_name='DemicLab-Main' AND report_date=?");
    $stmt->execute([$reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab-Main_BankStatement_'.$reportDate.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['BANK STATEMENT — DemicLab-Main Branch', 'Date Ends: '.date('F j, Y', strtotime($reportDate))]);
    fputcsv($out, []);

    foreach ($SECTIONS as $sec => $info) {
        fputcsv($out, [$info['label']]);
        $rowStmt = $pdo->prepare("SELECT item_name, amount FROM demiclab_bank_statement_rows WHERE store_name='DemicLab-Main' AND report_date=? AND section=? ORDER BY sort_order ASC");
        $rowStmt->execute([$reportDate, $sec]);
        $secRows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);
        $secTotal = 0;
        foreach ($secRows as $sr) {
            fputcsv($out, ['', $sr['item_name'], number_format((float)$sr['amount'], 2, '.', '')]);
            $secTotal += (float)$sr['amount'];
        }
        fputcsv($out, ['', 'TOTAL', number_format($secTotal, 2, '.', '')]);
        fputcsv($out, []);
    }

    fputcsv($out, ['OPENING BALANCE', $g('opening_balance')]);
    fputcsv($out, ['ROI PULL OUT', $g('roi_pull_out')]);
    fputcsv($out, ['CLOSING BALANCE', $g('closing_balance')]);
    fputcsv($out, ['NET INCREASE/DECREASE IN CASH', $g('net_change')]);
    fputcsv($out, []);
    fputcsv($out, ['REMARKS', (string)($r['remarks'] ?? '')]);
    fclose($out); exit;
}

// ── Fetch data for display ─────────────────────────────────
$fDate  = $_GET['date'] ?? date('Y-m-d');
$locked = isBsDateLocked($pdo, 'DemicLab-Main', $fDate);

$stmt = $pdo->prepare("SELECT * FROM demiclab_bank_statement WHERE store_name='DemicLab-Main' AND report_date=?");
$stmt->execute([$fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$vs  = fn($k) => (string)($row[$k] ?? '');

$rowStmt = $pdo->prepare("SELECT * FROM demiclab_bank_statement_rows WHERE store_name='DemicLab-Main' AND report_date=? ORDER BY section, sort_order ASC");
$rowStmt->execute([$fDate]);
$allRows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);
$sectionRows = [];
foreach ($allRows as $r) $sectionRows[$r['section']][] = $r;

// Seed brand-new dates with the recurring Cash in Bank line items so
// the layout matches the Excel sheet without retyping every day.
foreach ($SECTIONS as $sec => $info) {
    if (empty($sectionRows[$sec]) && !empty($info['seed'])) {
        $sectionRows[$sec] = array_map(fn($name) => ['item_name' => $name, 'amount' => 0], $info['seed']);
    }
}
$secTotal = fn($sec) => array_sum(array_column($sectionRows[$sec] ?? [], 'amount'));

$cashInBankCalc         = $secTotal('cash_in_bank');
$accountsReceivableCalc = $secTotal('accounts_receivable');
$outstandingChecksCalc  = $secTotal('outstanding_checks');
$closingBalanceCalc     = $cashInBankCalc + $accountsReceivableCalc - $outstandingChecksCalc;
$netChangeCalc          = $closingBalanceCalc - $v('opening_balance');

$pageTitle  = 'DemicLab-Main Bank Statement';
$activePage = 'demiclab_bank_statement';
include '../layout.php';
?>

<style>
.bs-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.bs-header-card .eyebrow { font-family:var(--font-m); font-size:.58rem; text-transform:uppercase; letter-spacing:.14em; color:rgba(255,255,255,.45); margin-bottom:4px; }
.bs-header-card .title   { font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:-.02em; }
.bs-header-card .subtitle{ font-family:var(--font-m); font-size:.67rem; color:rgba(255,255,255,.5); margin-top:4px; }

.bs-controls { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.bs-save-status { font-family:var(--font-m); font-size:.72rem; color:var(--subtext); }

.locked-banner {
  display:flex; align-items:center; gap:8px;
  background:#fff1f2; border:1px solid #fecdd3; color:#9f1239;
  padding:10px 16px; border-radius:8px; margin-bottom:16px;
  font-family:var(--font-m); font-size:.76rem; font-weight:600;
}
input[disabled], textarea[disabled] { opacity:.65; cursor:not-allowed !important; }

/* ── Section blocks (Cash in Bank / Accounts Receivable / Outstanding Checks) ── */
.bs-section {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); box-shadow:0 2px 12px rgba(0,0,0,.06);
  overflow:hidden; margin-bottom:18px;
}
.bs-section-title {
  padding:10px 16px; font-family:var(--font-m); font-size:.75rem;
  font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#fff;
  background:#1e3060;
}
.bs-table { width:100%; border-collapse:collapse; }
.bs-table th {
  background:#5a5a5a; color:#fff; padding:6px 8px;
  font-family:var(--font-m); font-size:.6rem; text-transform:uppercase;
  letter-spacing:.06em; text-align:left; border:1px solid #444;
}
.bs-table th:last-child, .bs-table td:last-child { text-align:center; width:40px; }
.bs-table td { padding:5px 8px; border:1px solid #e5e7eb; font-size:.8rem; vertical-align:middle; }
.bs-inp { width:100%; border:1px solid #e0e0e0; background:#fafafa; border-radius:4px; font-family:var(--font-m); font-size:.78rem; outline:none; padding:6px 8px; }
.bs-inp.num { text-align:right; }
.bs-inp:focus { background:#fffbeb; border-color:#f5c542; box-shadow:0 0 0 2px rgba(245,197,66,.15); }
.bs-table tfoot td {
  background:#e8a4a4; color:#7a1f1f; font-family:var(--font-m);
  font-weight:800; font-size:.82rem; padding:8px;
  border:1px solid #d38a8a;
}
.bs-table tfoot td.total-label { text-align:right; }
.bs-table tfoot td.total-value { text-align:right; }
.btn-add-row { margin:8px 12px; padding:4px 12px; background:#1a4d1a; color:#fff; border:none; border-radius:5px; font-size:.7rem; font-weight:700; cursor:pointer; }
.btn-add-row:hover { background:#155231; }
.btn-del-row { background:#fee2e2; border:none; color:#991b1b; border-radius:4px; padding:2px 6px; font-size:.65rem; cursor:pointer; }

/* ── Balance summary card ── */
.bs-summary { max-width:560px; margin:0 auto 22px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 2px 12px rgba(0,0,0,.06); overflow:hidden; }
.bs-row { display:flex; align-items:center; padding:11px 22px; border-bottom:1px solid rgba(255,255,255,.25); min-height:44px; gap:10px; }
.bs-row:last-child { border-bottom:none; }
.bs-row.balance { background:#33cdd9; }
.bs-row.balance .bs-label { color:#0a3a3d; font-weight:800; font-style:italic; }
.bs-row.netchange { background:#33cdd9; border-top:2px solid #1ea3ad; }
.bs-row.netchange .bs-label { color:#0a3a3d; font-weight:800; font-style:italic; }
.bs-label { flex:1; font-size:.84rem; }
.bs-value-wrap { width:160px; flex-shrink:0; }
.bs-input { width:100%; padding:7px 12px; text-align:center; font-family:var(--font-m); font-size:.84rem; font-weight:700; color:var(--text); background:#fff; border:1px solid var(--border); border-radius:6px; outline:none; transition:border-color .15s; }
.bs-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(15,123,92,.08); }
.bs-input.readout { background:rgba(255,255,255,.9); font-size:1rem; font-weight:800; cursor:default; color:#0a3a3d; border-color:transparent; }
input[type=number] { appearance:textfield; -moz-appearance:textfield; }
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }

/* ── Remarks ── */
.bs-remarks { max-width:560px; margin:0 auto 22px; background:#595959; border-radius:var(--radius); overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.bs-remarks-title { padding:12px 22px; font-family:var(--font-m); font-size:.8rem; font-weight:800; letter-spacing:.08em; color:#ff9db3; text-transform:uppercase; }
.bs-remarks textarea {
  width:calc(100% - 44px); margin:0 22px 18px; min-height:70px; resize:vertical;
  padding:10px 12px; font-family:var(--font-h); font-size:.82rem; color:var(--text);
  background:#fff; border:1px solid var(--border); border-radius:8px; outline:none;
}

/* ── Lock button + popover (mirrors ../h/h_sales_report.php) ── */
.lock-wrap { position:relative; display:inline-block; }
.btn-lock {
  display:inline-flex; align-items:center; gap:6px;
  padding:9px 16px; border-radius:8px; cursor:pointer;
  font-family:var(--font-m); font-size:.78rem; font-weight:700;
  border:1px solid var(--border); background:#fff; color:var(--text);
  transition:all .15s;
}
.btn-lock:hover { background:#f8f9fb; }
.btn-lock.is-locked { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.btn-lock.is-locked:hover { background:#ffe4e6; }
.lock-badge-static {
  display:inline-flex; align-items:center; gap:6px;
  padding:9px 16px; border-radius:8px;
  font-family:var(--font-m); font-size:.78rem; font-weight:700;
  border:1px solid var(--border); background:#f8f9fb; color:var(--subtext);
}
.lock-popover {
  display:none; position:absolute; top:calc(100% + 8px); left:0; z-index:200;
  width:280px; background:#fff; border:1px solid var(--border); border-radius:10px;
  box-shadow:0 10px 30px rgba(0,0,0,.14); padding:16px; text-align:left;
}
.lock-popover-title { font-family:var(--font-m); font-size:.85rem; font-weight:800; color:var(--text); margin-bottom:6px; }
.lock-popover-date  { font-family:var(--font-m); font-size:.8rem; font-weight:700; color:var(--accent); margin-bottom:8px; }
.lock-popover-desc  { font-family:var(--font-h); font-size:.75rem; color:var(--subtext); line-height:1.45; margin-bottom:14px; }
.lock-popover-actions { display:flex; justify-content:flex-end; gap:8px; }
.lock-popover-actions button {
  padding:7px 14px; border-radius:6px; font-family:var(--font-m); font-size:.72rem; font-weight:700;
  cursor:pointer; border:1px solid var(--border); background:#fff; color:var(--text);
}
.lock-popover-actions .btn-confirm { background:#be123c; border-color:#be123c; color:#fff; }
.lock-popover-actions .btn-confirm:hover { background:#9f1239; }
.lock-popover-actions .btn-confirm.unlock { background:#0f7b5c; border-color:#0f7b5c; }
.lock-popover-actions .btn-confirm.unlock:hover { background:#0b5f47; }
.lock-popover-actions .btn-cancel:hover { background:#f8f9fb; }
.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }
</style>

<!-- Header -->
<div class="bs-header-card">
  <div>
    <div class="eyebrow">DemicLab-Main Branch · Bank</div>
    <div class="title">Bank Statement</div>
    <div class="subtitle">Cash in Bank + Accounts Receivable − Outstanding Checks · Closing Balance auto-calculated</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">📌 DemicLab-Main</span>
</div>

<?php if ($locked): ?>
<div class="locked-banner">
  🔒 <?= date('M j, Y', strtotime($fDate)) ?> is locked by management. All fields on this page are read-only.
</div>
<?php endif; ?>

<!-- Controls -->
<div class="bs-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <label style="font-family:var(--font-m);font-size:.78rem;font-weight:700;color:var(--text)">Date Ends:</label>
    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
    <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
    <a href="demiclab_bank_statement.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="bs-save-status"></span>
  </form>

  <?php if ($canLock): ?>
  <div class="lock-wrap">
    <button type="button" class="btn-lock <?= $locked ? 'is-locked' : '' ?>" id="lockBtn" onclick="toggleLockPopover(event)"
      title="<?= $locked ? 'Unlock '.date('M j, Y', strtotime($fDate)) : 'Lock '.date('M j, Y', strtotime($fDate)) ?>">
      <?= $locked ? '🔒 Locked' : '🔓 Lock' ?>
    </button>
    <div class="lock-popover" id="lockPopover">
      <div class="lock-popover-title"><?= $locked ? 'Unlock this date?' : 'Lock this date?' ?></div>
      <div class="lock-popover-date">📅 <?= date('l, M j, Y', strtotime($fDate)) ?></div>
      <div class="lock-popover-desc">
        <?= $locked
            ? 'This will make the Bank Statement editable again for this date.'
            : 'This will make the Bank Statement read-only for this date. No one will be able to edit or save changes until it’s unlocked.' ?>
      </div>
      <div class="lock-popover-actions">
        <button type="button" class="btn-cancel" onclick="hideLockPopover()">Cancel</button>
        <button type="button" class="btn-confirm <?= $locked ? 'unlock' : '' ?>" onclick="confirmToggleLock()"><?= $locked ? 'Unlock' : 'Lock' ?></button>
      </div>
    </div>
  </div>
  <?php elseif ($locked): ?>
  <span class="lock-badge-static" title="Locked by management">🔒 Locked</span>
  <?php endif; ?>
</div>

<?php foreach ($SECTIONS as $sec => $info):
    $rows = $sectionRows[$sec] ?? [];
?>
<!-- ══════════════════════════════════════════════════════════
     <?= $info['label'] ?> SECTION
════════════════════════════════════════════════════════════ -->
<div class="bs-section" data-section="<?= $sec ?>">
  <div class="bs-section-title"><?= $info['label'] ?></div>
  <div style="overflow-x:auto">
  <table class="bs-table">
    <thead>
      <tr><th>Item</th><th style="text-align:right">Amount</th><th></th></tr>
    </thead>
    <tbody class="bs-body">
      <?php if (empty($rows)): $rows = [['item_name'=>'','amount'=>0]]; endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr class="bs-row-tr">
        <td><input class="bs-inp txt" type="text" placeholder="Enter name…" value="<?= htmlspecialchars($r['item_name'] ?? '') ?>" oninput="recalc()"></td>
        <td><input class="bs-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$r['amount'] ?: '' ?>" oninput="recalc()"></td>
        <td><button class="btn-del-row" onclick="delRow(this,'<?= $sec ?>')">✕</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="total-label" colspan="2">TOTAL</td>
        <td class="total-value" id="tot-<?= $sec ?>" style="text-align:right"><?= number_format($secTotal($sec), 2) ?></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <button class="btn-add-row" onclick="addRow('<?= $sec ?>')">+ Add Row</button>
</div>
<?php endforeach; ?>

<!-- ══════════════════════════════════════════════════════════
     BALANCE SUMMARY
════════════════════════════════════════════════════════════ -->
<div class="bs-summary">
  <div class="bs-row balance">
    <div class="bs-label">Opening Balance</div>
    <div class="bs-value-wrap"><input class="bs-input" id="opening_balance" type="number" step="0.01" value="<?= $v('opening_balance') ?: '' ?>" oninput="recalc()"></div>
  </div>
  <div class="bs-row balance">
    <div class="bs-label">ROI Pull Out</div>
    <div class="bs-value-wrap"><input class="bs-input" id="roi_pull_out" type="number" step="0.01" value="<?= $v('roi_pull_out') ?: '' ?>" oninput="recalc()"></div>
  </div>
  <div class="bs-row balance">
    <div class="bs-label">Closing Balance</div>
    <div class="bs-value-wrap"><input class="bs-input readout" id="closing_balance" type="text" readonly value="<?= number_format($closingBalanceCalc, 2) ?>"></div>
  </div>
  <div class="bs-row netchange">
    <div class="bs-label">Net Increase/Decrease in Cash</div>
    <div class="bs-value-wrap"><input class="bs-input readout" id="net_change" type="text" readonly value="<?= number_format($netChangeCalc, 2) ?>"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     REMARKS
════════════════════════════════════════════════════════════ -->
<div class="bs-remarks">
  <div class="bs-remarks-title">Remarks</div>
  <textarea id="remarks" placeholder="Optional notes…"><?= htmlspecialchars($vs('remarks')) ?></textarea>
</div>

<script>
const FDATE = "<?= htmlspecialchars($fDate) ?>";
const REPORT_LOCKED = <?= $locked ? 'true' : 'false' ?>;
const SECTIONS = <?= json_encode(array_keys($SECTIONS)) ?>;

function fmt(n) { return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2); }

// ── Row add/remove ──────────────────────────────────────────
function addRow(section) {
  if (REPORT_LOCKED) return;
  const section_el = document.querySelector(`.bs-section[data-section="${section}"] tbody.bs-body`);
  const tr = document.createElement('tr');
  tr.className = 'bs-row-tr';
  tr.innerHTML = `
    <td><input class="bs-inp txt" type="text" placeholder="Enter name…" oninput="recalc()"></td>
    <td><input class="bs-inp num" type="number" step="0.01" placeholder="0.00" oninput="recalc()"></td>
    <td><button class="btn-del-row" onclick="delRow(this,'${section}')">✕</button></td>`;
  section_el.appendChild(tr);
}
function delRow(btn, section) {
  if (REPORT_LOCKED) return;
  btn.closest('tr').remove();
  recalc();
}

// ── Recalculate section totals + balances ───────────────────
function recalc() {
  let cashInBank = 0, accountsReceivable = 0, outstandingChecks = 0;
  SECTIONS.forEach(sec => {
    const section_el = document.querySelector(`.bs-section[data-section="${sec}"]`);
    if (!section_el) return;
    let total = 0;
    section_el.querySelectorAll('tbody.bs-body tr.bs-row-tr').forEach(tr => {
      const amt = parseFloat(tr.querySelector('.bs-inp.num').value) || 0;
      total += amt;
    });
    const totEl = document.getElementById('tot-' + sec);
    if (totEl) totEl.textContent = fmt(total);
    if (sec === 'cash_in_bank') cashInBank = total;
    if (sec === 'accounts_receivable') accountsReceivable = total;
    if (sec === 'outstanding_checks') outstandingChecks = total;
  });

  const opening = parseFloat(document.getElementById('opening_balance').value) || 0;
  const closing = cashInBank + accountsReceivable - outstandingChecks;
  const netChange = closing - opening;

  document.getElementById('closing_balance').value = fmt(closing);
  document.getElementById('net_change').value = fmt(netChange);
}

// ── Save All ─────────────────────────────────────────────────
async function saveAll() {
  if (REPORT_LOCKED) {
    showToast('🔒 This date is locked and cannot be edited.', 'error');
    return;
  }
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  // 1. Save each section's rows
  for (const sec of SECTIONS) {
    const section_el = document.querySelector(`.bs-section[data-section="${sec}"]`);
    const rows = [];
    section_el.querySelectorAll('tbody.bs-body tr.bs-row-tr').forEach(tr => {
      const inps = tr.querySelectorAll('input');
      rows.push({ name: inps[0]?.value || '', amount: parseFloat(inps[1]?.value) || 0 });
    });
    const fd = new FormData();
    fd.append('ajax_save_rows', '1');
    fd.append('report_date', FDATE);
    fd.append('section', sec);
    fd.append('rows', JSON.stringify(rows));
    await fetch('demiclab_bank_statement.php', { method: 'POST', body: fd });
  }

  // 2. Save main fields
  const fd2 = new FormData();
  fd2.append('ajax_save', '1');
  fd2.append('report_date', FDATE);
  fd2.append('opening_balance', parseFloat(document.getElementById('opening_balance').value) || 0);
  fd2.append('roi_pull_out', parseFloat(document.getElementById('roi_pull_out').value) || 0);
  fd2.append('remarks', document.getElementById('remarks').value || '');

  const res = await fetch('demiclab_bank_statement.php', { method: 'POST', body: fd2 });
  const data = await res.json();
  if (data.ok) {
    status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
    showToast('✓ Bank statement saved', 'success');
  } else {
    showToast('❌ ' + data.msg, 'error'); status.textContent = '❌ Error';
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// ── Lock popover ─────────────────────────────────────────────
function toggleLockPopover(e) {
  e.stopPropagation();
  const pop = document.getElementById('lockPopover');
  if (!pop) return;
  pop.style.display = (pop.style.display === 'block') ? 'none' : 'block';
}
function hideLockPopover() {
  const pop = document.getElementById('lockPopover');
  if (pop) pop.style.display = 'none';
}
document.addEventListener('click', function(e) {
  const pop = document.getElementById('lockPopover');
  const btn = document.getElementById('lockBtn');
  if (!pop || pop.style.display !== 'block') return;
  if (!pop.contains(e.target) && e.target !== btn) hideLockPopover();
});

async function confirmToggleLock() {
  const willLock = !REPORT_LOCKED;
  hideLockPopover();

  const fd = new FormData();
  fd.append('ajax_toggle_lock', '1');
  fd.append('report_date', FDATE);
  fd.append('lock', willLock ? '1' : '0');

  try {
    const res = await fetch('demiclab_bank_statement.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      showToast(willLock ? ('🔒 Locked ' + FDATE) : ('🔓 Unlocked ' + FDATE), 'success');
      setTimeout(function () { location.reload(); }, 700);
    } else {
      showToast('❌ ' + (data.msg || 'Could not update lock.'), 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

// ── Lock the whole page (all fields read-only) ────────────────
function lockPage() {
  document.querySelectorAll('input, select, textarea').forEach(function (el) {
    if (el.name === 'date') return;
    if (el.id === 'closing_balance' || el.id === 'net_change') return; // already readonly
    el.disabled = true;
  });
  document.querySelectorAll('.btn-add-row, .btn-del-row').forEach(function (el) {
    el.style.display = 'none';
  });
  document.querySelectorAll('.bs-controls button').forEach(function (btn) {
    if (btn.textContent.includes('Save')) {
      btn.disabled = true;
      btn.style.opacity = '.5';
      btn.style.cursor = 'not-allowed';
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  recalc();
  if (REPORT_LOCKED) lockPage();
});
</script>