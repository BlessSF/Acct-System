<?php
// ============================================================
//  commissary_sales_report.php — Commissary Branch Daily Sales Report
//  DINE IN detail rows + sub-section detail rows (Marketing
//  Pullout, GRAB, Expenses, Late Payment, Advance Payment,
//  GC Sponsorship, GC Sold) — totals feed Net Sales / Short/Over
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/audit_log.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Lock stops Cashier edits only — OIC (Accounting) and Manager can
// still edit a locked date.
$canLock = !isCashier();

// ── Main summary table (unchanged columns) ─────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_sales_report` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `store_name`            varchar(50) NOT NULL DEFAULT 'Commissary',
    `report_date`           date NOT NULL,
    `gross_sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `service_charge`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `z_reading_gross`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_swipe`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `deposit_swipe_card`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `late_payment_card`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `maya_swipe`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `unpaid_med_credit`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `grab_sales`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `gcash`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `gift_card`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `marketing_pull_out`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `discount`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_transfer_cheque`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `pcf_expenses`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `other_expenses`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `coh`                   decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_sales`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `short_over`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`              varchar(100) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── DINE IN detail rows table ──────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_dinein_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Commissary',
    `report_date`   date NOT NULL,
    `cash`          decimal(12,2) DEFAULT 0.00,
    `palawan_pay`   decimal(12,2) DEFAULT 0.00,
    `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
    `unpaid_credit_name`   varchar(100) DEFAULT NULL,
    `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
    `discount`      decimal(12,2) DEFAULT 0.00,
    `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
    `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Sub-section detail rows table ─────────────────────────
// section: marketing_pullout | grab | expenses | late_payment |
//          advance_payment | gc_sponsorship | gc_sold
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_sales_detail_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Commissary',
    `report_date`   date NOT NULL,
    `section`       varchar(40) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Auto-create shared report_locks table (safe to run repeatedly) ─
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `report_locks` (
        `id`          int(11) NOT NULL AUTO_INCREMENT,
        `store_name`  varchar(50) NOT NULL,
        `report_date` date NOT NULL,
        `locked_by`   varchar(100) DEFAULT NULL,
        `locked_at`   timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

function isDateLocked(PDO $pdo, string $store, string $date): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM report_locks WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $date]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// ── AJAX: Lock / unlock a date ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_toggle_lock'])) {
    header('Content-Type: application/json');
    if (!$canLock) {
        echo json_encode(['ok' => false, 'msg' => 'Only accounting can lock or unlock reports.']);
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
            $pdo->prepare("INSERT INTO report_locks (store_name, report_date, locked_by)
                VALUES ('Commissary', ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([$reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM report_locks WHERE store_name='Commissary' AND report_date=?")
                ->execute([$reportDate]);
        }
        logAudit($pdo, 'Commissary', 'Sales Report', $lock ? 'locked' : 'unlocked', $reportDate,
            $lock ? 'Locked the sales report for editing' : 'Unlocked the sales report for editing');
        echo json_encode(['ok' => true, 'locked' => $lock]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save main summary ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (isDateLocked($pdo, 'Commissary', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $NUM_COLS = ['gross_sales','service_charge','z_reading_gross','total_swipe',
            'deposit_swipe_card','late_payment_card','maya_swipe','unpaid_med_credit',
            'grab_sales','gcash','gift_card','marketing_pull_out','discount',
            'bank_transfer_cheque','pcf_expenses','other_expenses','coh'];
        $vals = [];
        foreach ($NUM_COLS as $f) $vals[$f] = (float)($_POST[$f] ?? 0);

        $diSumStmt = $pdo->prepare("SELECT
                COALESCE(SUM(unpaid_credit_amount),0) AS unpaid_med_credit,
                COALESCE(SUM(discount),0)             AS discount,
                COALESCE(SUM(bank_transfer_cheque),0) AS bank_transfer_cheque,
                COALESCE(SUM(palawan_pay),0)           AS gcash
            FROM commissary_dinein_rows WHERE store_name='Commissary' AND report_date=?");
        $diSumStmt->execute([$reportDate]);
        $diSums = $diSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['unpaid_med_credit','discount','bank_transfer_cheque','gcash'] as $f) {
            $vals[$f] = (float)($diSums[$f] ?? 0);
        }

        $secSumStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM commissary_sales_detail_rows WHERE store_name='Commissary' AND report_date=? GROUP BY section");
        $secSumStmt->execute([$reportDate]);
        $secSums = [];
        foreach ($secSumStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $secSums[$r['section']] = (float)$r['total'];
        $vals['grab_sales']         = $secSums['grab'] ?? 0;
        $vals['marketing_pull_out'] = $secSums['marketing_pullout'] ?? 0;
        $vals['pcf_expenses']       = $secSums['expenses'] ?? 0;

        $vals['total_swipe'] = $vals['deposit_swipe_card'] + $vals['late_payment_card'] + $vals['maya_swipe'];

        $vals['gross_sales'] = $vals['z_reading_gross'] - $vals['service_charge']
                              + $vals['discount'] - $vals['marketing_pull_out'];

        $netSales = $vals['gross_sales']
                  - $vals['unpaid_med_credit']
                  - $vals['grab_sales']
                  - $vals['gcash']
                  - $vals['gift_card']
                  - $vals['marketing_pull_out']
                  - $vals['discount']
                  - $vals['bank_transfer_cheque']
                  - $vals['pcf_expenses']
                  - $vals['other_expenses'];
        $shortOver = $vals['coh'] - $netSales;

        $fields    = array_merge(['store_name','report_date'], $NUM_COLS, ['net_sales','short_over','saved_by']);
        $data      = array_merge(['Commissary', $reportDate], array_values($vals), [$netSales, $shortOver, $user['name']]);
        $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", array_merge($NUM_COLS, ['net_sales','short_over','saved_by'])));
        $sql = "INSERT INTO commissary_sales_report (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ")
                VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")
                ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute($data);
        logAudit($pdo, 'Commissary', 'Sales Report', 'updated', $reportDate, 'Saved daily summary totals',
            ['net_sales' => $netSales, 'short_over' => $shortOver]);
        echo json_encode(['ok'=>true,'net_sales'=>$netSales,'short_over'=>$shortOver]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save DINE IN rows ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_dinein'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Commissary', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM commissary_dinein_rows WHERE store_name='Commissary' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO commissary_dinein_rows (store_name,report_date,cash,palawan_pay,card_swipe_qr,unpaid_credit_name,unpaid_credit_amount,discount,bank_transfer_cheque,cancelled_transactions,sort_order) VALUES ('Commissary',?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, (float)($r['cash']??0), (float)($r['palawan_pay']??0),
                (float)($r['card_swipe_qr']??0), $r['unpaid_credit_name']??null,
                (float)($r['unpaid_credit_amount']??0), (float)($r['discount']??0),
                (float)($r['bank_transfer_cheque']??0), (float)($r['cancelled_transactions']??0), $i]);
        }
        logAudit($pdo, 'Commissary', 'Sales Report', 'updated', $reportDate,
            'Updated Dine In rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save sub-section detail rows ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_detail'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $section    = $_POST['section'];
        if (isDateLocked($pdo, 'Commissary', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM commissary_sales_detail_rows WHERE store_name='Commissary' AND report_date=? AND section=?")->execute([$reportDate,$section]);
        $ins = $pdo->prepare("INSERT INTO commissary_sales_detail_rows (store_name,report_date,section,item_name,amount,sort_order) VALUES ('Commissary',?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $section, $r['name']??null, (float)($r['amount']??0), $i]);
        }
        logAudit($pdo, 'Commissary', 'Sales Report', 'updated', $reportDate,
            'Updated "' . $section . '" rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── CSV Export ─────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM commissary_sales_report WHERE store_name='Commissary' AND report_date=?");
    $stmt->execute([$reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Commissary_SalesReport_'.$reportDate.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['SUMMARY REPORT — Commissary Branch', date('F j, Y', strtotime($reportDate))]);
    fputcsv($out, []);
    fputcsv($out, ['Gross Sales', $g('gross_sales')]);
    fputcsv($out, ['Service Charge', $g('service_charge')]);
    fputcsv($out, ['Z Reading Gross', $g('z_reading_gross')]);
    fputcsv($out, ['Total Swipe', $g('total_swipe')]);
    fputcsv($out, ['Deposit Swipe (Card)', $g('deposit_swipe_card')]);
    fputcsv($out, ['Late Payment (Card)', $g('late_payment_card')]);
    fputcsv($out, ['Sales of the day Swipe (MAYA)', $g('maya_swipe')]);
    fputcsv($out, ['Unpaid Med (Credit)', $g('unpaid_med_credit')]);
    fputcsv($out, ['Grab Sales', $g('grab_sales')]);
    fputcsv($out, ['G-Cash', $g('gcash')]);
    fputcsv($out, ['Gift Card', $g('gift_card')]);
    fputcsv($out, ['Marketing Pull Out', $g('marketing_pull_out')]);
    fputcsv($out, ['Discount', $g('discount')]);
    fputcsv($out, ['Bank Transfer/Cheque', $g('bank_transfer_cheque')]);
    fputcsv($out, ['PCF/Expenses', $g('pcf_expenses')]);
    fputcsv($out, ['Other Expenses', $g('other_expenses')]);
    fputcsv($out, ['Net Sales', $g('net_sales')]);
    fputcsv($out, ['COH (Cash on Hand)', $g('coh')]);
    fputcsv($out, ['Short/Over', $g('short_over')]);
    fclose($out); exit;
}

// ── Fetch data for display ─────────────────────────────────
$fDate = $_GET['date'] ?? date('Y-m-d');
$locked = isDateLocked($pdo, 'Commissary', $fDate);
// Lock stops Cashier edits only — OIC (Accounting) and Manager can still edit a locked date.
$restrictEdit = $locked && isCashier();
$stmt  = $pdo->prepare("SELECT * FROM commissary_sales_report WHERE store_name='Commissary' AND report_date=?");
$stmt->execute([$fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$fmt = fn($n)  => number_format((float)$n, 2);

// Fetch DINE IN rows
$diStmt = $pdo->prepare("SELECT * FROM commissary_dinein_rows WHERE store_name='Commissary' AND report_date=? ORDER BY sort_order ASC");
$diStmt->execute([$fDate]);
$dineinRows = $diStmt->fetchAll(PDO::FETCH_ASSOC);
$diSum = fn($col) => array_sum(array_column($dineinRows, $col));

// Fetch sub-section detail rows
$detailStmt = $pdo->prepare("SELECT * FROM commissary_sales_detail_rows WHERE store_name='Commissary' AND report_date=? ORDER BY section, sort_order ASC");
$detailStmt->execute([$fDate]);
$allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
$details = [];
foreach ($allDetails as $d) $details[$d['section']][] = $d;
$secSum = fn($sec) => array_sum(array_column($details[$sec] ?? [], 'amount'));

// Auto-calculated pink-section values (Gift Card and Other Expenses stay manual)
$unpaidMedCalc     = $diSum('unpaid_credit_amount');
$discountCalc      = $diSum('discount');
$bankTransferCalc  = $diSum('bank_transfer_cheque');
$gcashCalc         = $diSum('palawan_pay');
$grabCalc          = $secSum('grab');
$marketingCalc     = $secSum('marketing_pullout');
$pcfCalc           = $secSum('expenses');

$grossSalesCalc = $v('z_reading_gross') - $v('service_charge') + $discountCalc - $marketingCalc;

$pageTitle  = 'Commissary Sales Report';
$activePage = 'commissary_sales_report';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

<!-- Header -->
<div class="sr-header-card">
  <div>
    <div class="eyebrow">Commissary Branch · Sales</div>
    <div class="title">Daily Sales Report</div>
    <div class="subtitle">DINE IN detail + sub-sections · Net Sales &amp; Short/Over auto-calculated</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">📌 Commissary</span>
</div>

<?php if ($restrictEdit): ?>
<div class="locked-banner">
  🔒 <?= date('M j, Y', strtotime($fDate)) ?> is locked by accounting. All fields on this page are read-only.
</div>
<?php elseif ($locked): ?>
<div class="locked-banner" style="background:#eef2ff;color:#3730a3;border-color:#c7d2fe">
  🔒 <?= date('M j, Y', strtotime($fDate)) ?> is locked for Cashier. As Accounting/OIC or Manager, you can still edit this report.
</div>
<?php endif; ?>

<!-- Controls -->
<div class="sr-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
    <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
    <a href="commissary_sales_report.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="sr-save-status"></span>
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
            ? 'This will make the Sales Report editable again for Cashier on this date.'
            : 'This will make the Sales Report read-only for Cashier on this date. Accounting/OIC and Manager can still edit until it’s unlocked.' ?>
      </div>
      <div class="lock-popover-actions">
        <button type="button" class="btn-cancel" onclick="hideLockPopover()">Cancel</button>
        <button type="button" class="btn-confirm <?= $locked ? 'unlock' : '' ?>" onclick="confirmToggleLock()"><?= $locked ? 'Unlock' : 'Lock' ?></button>
      </div>
    </div>
  </div>
  <?php elseif ($locked): ?>
  <span class="lock-badge-static" title="Locked by accounting">🔒 Locked</span>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     DINE IN SECTION
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sr-section-title dark-red">DINE IN</div>
  <div style="overflow-x:auto">
  <table class="di-table" id="di-table">
    <thead>
      <tr>
        <th>Cash</th>
        <th>Palawan Pay</th>
        <th>Card/Swipe/QR</th>
        <th colspan="2">Unpaid / Credit</th>
        <th>Discount</th>
        <th>Bank Transfer/Cheque</th>
        <th>Cancelled Transactions</th>
        <th style="width:40px"></th>
      </tr>
      <tr>
        <th class="sub"></th><th class="sub"></th><th class="sub"></th>
        <th class="sub">NAME</th><th class="sub">AMOUNT</th>
        <th class="sub"></th><th class="sub"></th><th class="sub"></th><th class="sub"></th>
      </tr>
    </thead>
    <tbody id="di-body">
      <?php foreach ($dineinRows as $dr): ?>
      <tr>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['cash'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['palawan_pay'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['card_swipe_qr'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp txt di-inp-name" type="text" value="<?= htmlspecialchars($dr['unpaid_credit_name'] ?? '') ?>" placeholder="Name"></td>
        <td><input class="di-inp di-inp-amt" type="number" step="0.01" value="<?= (float)$dr['unpaid_credit_amount'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['discount'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['bank_transfer_cheque'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['cancelled_transactions'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><button class="btn-del-row" onclick="delDiRow(this)">✕</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($dineinRows)): ?>
      <tr>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp txt di-inp-name" type="text" placeholder="Name"></td>
        <td><input class="di-inp di-inp-amt" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
        <td><button class="btn-del-row" onclick="delDiRow(this)">✕</button></td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td id="di-tot-cash">0.00</td>
        <td id="di-tot-palawan">0.00</td>
        <td id="di-tot-card">0.00</td>
        <td colspan="2" id="di-tot-unpaid">0.00</td>
        <td id="di-tot-disc">0.00</td>
        <td id="di-tot-bank">0.00</td>
        <td id="di-tot-cancelled">0.00</td>
        <td></td>
      </tr>
      <tr>
        <td class="total-label" colspan="3">TOTAL</td>
        <td class="grand-total" colspan="6" id="di-grand-total">0.00</td>
      </tr>
    </tfoot>
  </table>
  </div>
  <button class="btn-add-row" onclick="addDiRow()">+ Add Row</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUB-SECTIONS ROW 1: Marketing Pullout | GRAB | Expenses | Late Payment
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sub-grid" id="subsec-row1">

    <?php
    $subsec1 = [
        'marketing_pullout' => 'Marketing Pullout',
        'grab'              => 'GRAB',
        'expenses'          => 'EXPENSES',
        'late_payment'      => 'LATE PAYMENT',
    ];
    foreach ($subsec1 as $sec => $label):
        $secRows = $details[$sec] ?? [['item_name'=>'','amount'=>0]];
    ?>
    <div class="sub-col" data-section="<?= $sec ?>">
      <div class="sub-col-title"><?= $label ?></div>
      <div class="sub-col-hdr"><span>Name</span><span>Amount</span><span></span></div>
      <div class="sub-rows">
        <?php foreach ($secRows as $sr): ?>
        <div class="sub-row">
          <input class="sub-inp" type="text" placeholder="Enter name…" value="<?= htmlspecialchars($sr['item_name'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('<?= $sec ?>')">
          <button class="btn-del-row" onclick="delSubRow(this,'<?= $sec ?>')" style="margin:2px 4px">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn-add-row" onclick="addSubRow('<?= $sec ?>')">+ Add</button>
      <div class="sub-footer">
        <span>Total</span>
        <span id="sub-tot-<?= $sec ?>">P<?= number_format(array_sum(array_column($secRows,'amount')),2) ?></span>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUB-SECTIONS ROW 2: Advance Payment | GC Sponsorship | GC Sold
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sub-grid three-col" id="subsec-row2">

    <?php
    $subsec2 = [
        'advance_payment'  => 'ADVANCE PAYMENT',
        'gc_sponsorship'   => 'GC SPONSORSHIP',
        'gc_sold'          => 'GC SOLD',
    ];
    foreach ($subsec2 as $sec => $label):
        $secRows = $details[$sec] ?? [['item_name'=>'','amount'=>0]];
    ?>
    <div class="sub-col" data-section="<?= $sec ?>">
      <div class="sub-col-title"><?= $label ?></div>
      <div class="sub-col-hdr"><span>Name</span><span>Amount</span><span></span></div>
      <div class="sub-rows">
        <?php foreach ($secRows as $sr): ?>
        <div class="sub-row">
          <input class="sub-inp" type="text" placeholder="Enter name…" value="<?= htmlspecialchars($sr['item_name'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('<?= $sec ?>')">
          <button class="btn-del-row" onclick="delSubRow(this,'<?= $sec ?>')" style="margin:2px 4px">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn-add-row" onclick="addSubRow('<?= $sec ?>')">+ Add</button>
      <div class="sub-footer">
        <span>Total</span>
        <span id="sub-tot-<?= $sec ?>">P<?= number_format(array_sum(array_column($secRows,'amount')),2) ?></span>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUMMARY REPORT (existing fields — auto-fed from above)
════════════════════════════════════════════════════════════ -->
<div class="sr-wrap">
<div class="sr-card">
  <div class="sr-title-row"><div class="sr-main-title">Summary Report</div></div>

  <div class="sr-row" style="background:#f8f9fb">
    <div class="sr-label" style="text-align:left;flex:1">For</div>
    <div class="sr-value-wrap"><span class="sr-input readout" style="font-size:.8rem"><?= date('n/j/Y', strtotime($fDate)) ?></span></div>
  </div>

  <div class="sr-row green">
    <div class="sr-label">Gross Sales <span style="font-size:.65rem;opacity:.7">(auto: Z Reading − Service Charge + Discount − Marketing Pull Out)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gross_sales" value="<?= $fmt($grossSalesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">Service Charge</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="service_charge" value="<?= $v('service_charge') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#fff">(separate deposit slip)</div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">Z Reading Gross</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="z_reading_gross" value="<?= $v('z_reading_gross') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">Total Swipe <span style="font-size:.65rem;opacity:.7">(auto: Deposit + Late Payment + Maya)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="total_swipe" value="<?= $fmt($v('deposit_swipe_card')+$v('late_payment_card')+$v('maya_swipe')) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row gray-hdr">
    <div class="sr-label">Deposit Swipe (Card)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="deposit_swipe_card" value="<?= $v('deposit_swipe_card') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row gray-hdr">
    <div class="sr-label">Late Payment (Card) <span style="font-size:.65rem;opacity:.7">(auto from Late Payment)</span></div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="late_payment_card" value="<?= $v('late_payment_card') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">Sales of the day Swipe (MAYA)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="maya_swipe" value="<?= $v('maya_swipe') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#fff">(data input based on app)</div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Unpaid Med (Credit) <span style="font-size:.65rem;opacity:.7">(auto from Unpaid/Credit)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="unpaid_med_credit" value="<?= $fmt($unpaidMedCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Grab Sales <span style="font-size:.65rem;opacity:.7">(auto from GRAB)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="grab_sales" value="<?= $fmt($grabCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">G-Cash <span style="font-size:.65rem;opacity:.7">(auto from Palawan Pay)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gcash" value="<?= $fmt($gcashCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Gift Card</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="gift_card" value="<?= $v('gift_card') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Marketing Pull Out <span style="font-size:.65rem;opacity:.7">(auto from Marketing Pullout)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="marketing_pull_out" value="<?= $fmt($marketingCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Discount <span style="font-size:.65rem;opacity:.7">(auto from Discount col)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="discount" value="<?= $fmt($discountCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Bank Transfer/Cheque <span style="font-size:.65rem;opacity:.7">(auto from DINE IN)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="bank_transfer_cheque" value="<?= $fmt($bankTransferCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">PCF/Expenses <span style="font-size:.65rem;opacity:.7">(auto from Expenses)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="pcf_expenses" value="<?= $fmt($pcfCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Other Expenses</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="other_expenses" value="<?= $v('other_expenses') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>

  <div class="sr-row net">
    <div class="sr-label">Net Sales</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="net_sales" value="<?= $fmt($v('gross_sales')-$v('unpaid_med_credit')-$v('grab_sales')-$v('gcash')-$v('gift_card')-$v('marketing_pull_out')-$v('discount')-$v('bank_transfer_cheque')-$v('pcf_expenses')-$v('other_expenses')) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row coh">
    <div class="sr-label">COH (Cash on Hand) <span style="font-size:.65rem;opacity:.7">(auto from Cash col)</span></div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="coh" value="<?= $v('coh') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#cfd8ea">(cash sales only except SC)</div>
  </div>
  <div class="sr-row short">
    <div class="sr-label">Short/Over</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="short_over" value="0.00" readonly tabindex="-1"></div>
    <div class="sr-note" style="color:#ffd8d8">(if "-" sign means short)</div>
  </div>
</div>
</div>

<script>
const FDATE  = '<?= $fDate ?>';
const REPORT_LOCKED = <?= $restrictEdit ? 'true' : 'false' ?>;
const REPORT_LOCKED_RAW = <?= $locked ? 'true' : 'false' ?>;
const fmt    = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const gv     = id => parseFloat(String(document.getElementById(id)?.value ?? '').replace(/,/g,'')) || 0;
const setVal = (id,v) => { const el=document.getElementById(id); if(el) el.value = v; };

// ── DINE IN helpers ────────────────────────────────────────
function diCols(tr) {
  const inps = tr.querySelectorAll('input');
  return {
    cash:                (float(inps[0])),
    palawan_pay:         (float(inps[1])),
    card_swipe_qr:       (float(inps[2])),
    unpaid_credit_name:  inps[3]?.value || '',
    unpaid_credit_amount:(float(inps[4])),
    discount:            (float(inps[5])),
    bank_transfer_cheque:(float(inps[6])),
    cancelled_transactions:(float(inps[7])),
  };
}
function float(inp) { return parseFloat(inp?.value) || 0; }

function diChanged() {
  let totCash=0,totPal=0,totCard=0,totUnpaid=0,totDisc=0,totBank=0,totCan=0;
  document.querySelectorAll('#di-body tr').forEach(tr => {
    const c = diCols(tr);
    totCash   += c.cash; totPal += c.palawan_pay; totCard += c.card_swipe_qr;
    totUnpaid += c.unpaid_credit_amount; totDisc += c.discount;
    totBank   += c.bank_transfer_cheque; totCan += c.cancelled_transactions;
  });
  const grand = totCash + totPal + totCard + totUnpaid;
  document.getElementById('di-tot-cash').textContent      = fmt(totCash);
  document.getElementById('di-tot-palawan').textContent   = fmt(totPal);
  document.getElementById('di-tot-card').textContent      = fmt(totCard);
  document.getElementById('di-tot-unpaid').textContent    = fmt(totUnpaid);
  document.getElementById('di-tot-disc').textContent      = fmt(totDisc);
  document.getElementById('di-tot-bank').textContent      = fmt(totBank);
  document.getElementById('di-tot-cancelled').textContent = fmt(totCan);
  document.getElementById('di-grand-total').textContent   = fmt(grand);

  // Auto-feed summary fields
  setVal('unpaid_med_credit',   fmt(totUnpaid));
  setVal('discount',            fmt(totDisc));
  setVal('bank_transfer_cheque',fmt(totBank));
  setVal('gcash',               fmt(totPal));
  setVal('coh',                 totCash);
  recalc();
}

function addDiRow() {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp txt di-inp-name" type="text" placeholder="Name"></td>
    <td><input class="di-inp di-inp-amt" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><button class="btn-del-row" onclick="delDiRow(this)">✕</button></td>`;
  document.getElementById('di-body').appendChild(tr);
}

function delDiRow(btn) {
  btn.closest('tr').remove();
  diChanged();
}

// ── Sub-section helpers ────────────────────────────────────
function subChanged(sec) {
  const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
  let tot = 0;
  col.querySelectorAll('input[type=number]').forEach(i => tot += parseFloat(i.value)||0);
  const totEl = document.getElementById(`sub-tot-${sec}`);
  if (totEl) totEl.textContent = 'P' + fmt(tot);

  // Auto-feed summary
  if (sec === 'grab')              { setVal('grab_sales', fmt(tot)); recalc(); }
  if (sec === 'marketing_pullout') { setVal('marketing_pull_out', fmt(tot)); recalc(); }
  if (sec === 'expenses')          { setVal('pcf_expenses', fmt(tot)); recalc(); }
  if (sec === 'late_payment')      { setVal('late_payment_card', fmt(tot)); recalc(); }
}

function addSubRow(sec) {
  const col  = document.querySelector(`.sub-col[data-section="${sec}"]`);
  const rows = col.querySelector('.sub-rows');
  const div  = document.createElement('div');
  div.className = 'sub-row';
  div.innerHTML = `
    <input class="sub-inp" type="text" placeholder="Enter name…" oninput="subChanged('${sec}')">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="subChanged('${sec}')">
    <button class="btn-del-row" onclick="delSubRow(this,'${sec}')" style="margin:2px 4px">✕</button>`;
  rows.appendChild(div);
}

function delSubRow(btn, sec) {
  btn.closest('.sub-row').remove();
  subChanged(sec);
}

// ── Summary recalc ─────────────────────────────────────────
function recalc() {
  const totalSwipe = gv('deposit_swipe_card') + gv('late_payment_card') + gv('maya_swipe');
  document.getElementById('total_swipe').value = fmt(totalSwipe);

  const grossSales = gv('z_reading_gross') - gv('service_charge') + gv('discount') - gv('marketing_pull_out');
  document.getElementById('gross_sales').value = fmt(grossSales);

  const netSales = gv('gross_sales')
                 - gv('unpaid_med_credit')
                 - gv('grab_sales')
                 - gv('gcash')
                 - gv('gift_card')
                 - gv('marketing_pull_out')
                 - gv('discount')
                 - gv('bank_transfer_cheque')
                 - gv('pcf_expenses')
                 - gv('other_expenses');
  const shortOver = gv('coh') - netSales;
  document.getElementById('net_sales').value = fmt(netSales);
  const soEl = document.getElementById('short_over');
  soEl.value = fmt(shortOver);
  soEl.style.color = shortOver < 0 ? '#ffd54f' : '#b71c1c';
}

// ── Save All ───────────────────────────────────────────────
async function saveAll() {
  if (REPORT_LOCKED) {
    showToast('🔒 This date is locked and cannot be edited.', 'error');
    return;
  }
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  // 1. Save DINE IN rows
  const diRows = [];
  document.querySelectorAll('#di-body tr').forEach(tr => diRows.push(diCols(tr)));
  const fd1 = new FormData();
  fd1.append('ajax_save_dinein','1');
  fd1.append('report_date', FDATE);
  fd1.append('rows', JSON.stringify(diRows));
  await fetch('commissary_sales_report.php', {method:'POST',body:fd1});

  // 2. Save each sub-section
  const allSecs = ['marketing_pullout','grab','expenses','late_payment','advance_payment','gc_sponsorship','gc_sold'];
  for (const sec of allSecs) {
    const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
    if (!col) continue;
    const rows = [];
    col.querySelectorAll('.sub-row').forEach(row => {
      const inps = row.querySelectorAll('input');
      rows.push({ name: inps[0]?.value||'', amount: parseFloat(inps[1]?.value)||0 });
    });
    const fd2 = new FormData();
    fd2.append('ajax_save_detail','1');
    fd2.append('report_date', FDATE);
    fd2.append('section', sec);
    fd2.append('rows', JSON.stringify(rows));
    await fetch('commissary_sales_report.php', {method:'POST',body:fd2});
  }

  // 3. Save main summary
  const fd3 = new FormData();
  fd3.append('ajax_save','1');
  fd3.append('report_date', FDATE);
  ['gross_sales','service_charge','z_reading_gross','total_swipe',
   'deposit_swipe_card','late_payment_card','maya_swipe','unpaid_med_credit',
   'grab_sales','gcash','gift_card','marketing_pull_out','discount',
   'bank_transfer_cheque','pcf_expenses','other_expenses','coh'].forEach(id => fd3.append(id, gv(id)));

  const res  = await fetch('commissary_sales_report.php', {method:'POST',body:fd3});
  const data = await res.json();
  if (data.ok) {
    status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
    showToast('✓ Sales report saved', 'success');
  } else {
    showToast('❌ ' + data.msg, 'error'); status.textContent = '❌ Error';
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

// ── Lock popover ───────────────────────────────
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
  const willLock = !REPORT_LOCKED_RAW;
  hideLockPopover();

  const fd = new FormData();
  fd.append('ajax_toggle_lock', '1');
  fd.append('report_date', FDATE);
  fd.append('lock', willLock ? '1' : '0');

  try {
    const res  = await fetch('commissary_sales_report.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast(willLock ? ('🔒 Locked ' + FDATE) : ('🔓 Unlocked ' + FDATE), 'success');
      setTimeout(function(){ location.reload(); }, 700);
    } else {
      showToast('❌ ' + (data.msg || 'Could not update lock.'), 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

// ── Lock the whole page (all fields read-only) ────────────
function lockPage() {
  document.querySelectorAll('input, select, textarea').forEach(function(el) {
    if (el.name === 'date' && el.closest('.sr-controls')) return;
    el.disabled = true;
  });
  document.querySelectorAll('.btn-add-row, .btn-del-row').forEach(function(el) {
    el.style.display = 'none';
  });
  document.querySelectorAll('.sr-controls button').forEach(function(btn) {
    if (btn.textContent.includes('Save')) {
      btn.disabled = true;
      btn.style.opacity = '.5';
      btn.style.cursor = 'not-allowed';
    }
  });
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', () => { diChanged(); recalc(); if (REPORT_LOCKED) lockPage(); });
</script>
</body>
</html>