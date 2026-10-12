<?php
// ============================================================
//  pub_express_sales_report.php — Pub Express Branch Daily Sales Report
//  DINE IN detail rows + sub-section detail rows (Marketing
//  Pullout, GRAB, Expenses, Late Payment, Advance Payment,
//  GC Sponsorship, GC Sold) — totals feed Net Sales / Short/Over
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/audit_log.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Pub Express') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Lock stops Cashier edits only — OIC (Accounting) and Manager can
// still edit a locked date.
$canLock = !isCashier();

// ── Main summary table (unchanged columns) ─────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_sales_report` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `store_name`            varchar(50) NOT NULL DEFAULT 'Pub Express',
    `report_date`           date NOT NULL,
    `gross_sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `service_charge`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `z_reading_gross`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `undeclared`            decimal(12,2) NOT NULL DEFAULT 0.00,
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

// Add `declared_tc` to tables created before this column existed (safe to run repeatedly)
try {
    if (!$pdo->query("SHOW COLUMNS FROM `pub_express_sales_report` LIKE 'declared_tc'")->fetch()) {
        $pdo->exec("ALTER TABLE `pub_express_sales_report` ADD COLUMN `declared_tc` decimal(12,2) DEFAULT NULL AFTER `short_over`");
    }
} catch (Throwable $ignored) {}

// Add `total_tc` column (Total TC = Declared TC, stored so it is saved in the DB too)
try {
    if (!$pdo->query("SHOW COLUMNS FROM `pub_express_sales_report` LIKE 'total_tc'")->fetch()) {
        $pdo->exec("ALTER TABLE `pub_express_sales_report` ADD COLUMN `total_tc` decimal(12,2) DEFAULT NULL AFTER `declared_tc`");
    }
} catch (Throwable $ignored) {}

// Repair tables that were created WITHOUT a primary key / unique key (every save inserted a
// brand-new row and the page kept reading the oldest one, so Declared TC / Total TC never stuck).
// Renumber ids, remove duplicate rows (keep the newest per date), then add the keys. Runs once.
try {
    if (!$pdo->query("SHOW KEYS FROM `pub_express_sales_report` WHERE Key_name='PRIMARY'")->fetch()) {
        $pdo->exec("SET @pe_i := 0");
        $pdo->exec("UPDATE `pub_express_sales_report` SET `id` = (@pe_i := @pe_i + 1) ORDER BY `updated_at`, `created_at`");
        $pdo->exec("DELETE a FROM `pub_express_sales_report` a JOIN `pub_express_sales_report` b
                    ON a.store_name=b.store_name AND a.report_date=b.report_date AND a.id < b.id");
        $pdo->exec("ALTER TABLE `pub_express_sales_report`
                    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
    }
    if (!$pdo->query("SHOW KEYS FROM `pub_express_sales_report` WHERE Key_name='uniq_store_date'")->fetch()) {
        $pdo->exec("ALTER TABLE `pub_express_sales_report` ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)");
    }
} catch (Throwable $ignored) {}

// ── DINE IN detail rows table ──────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_dinein_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Pub Express',
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
// section: marketing_pullout | grab | expenses | cash_advance | payroll_salary | late_payment |
//          advance_payment | gc_sponsorship | gc_sold
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_sales_detail_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Pub Express',
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
                VALUES ('Pub Express', ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([$reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM report_locks WHERE store_name='Pub Express' AND report_date=?")
                ->execute([$reportDate]);
        }
        logAudit($pdo, 'Pub Express', 'Sales Report', $lock ? 'locked' : 'unlocked', $reportDate,
            $lock ? 'Locked the sales report for editing' : 'Unlocked the sales report for editing');
        echo json_encode(['ok' => true, 'locked' => $lock]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save main summary ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    ob_start();
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (isDateLocked($pdo, 'Pub Express', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $NUM_COLS = ['gross_sales','service_charge','z_reading_gross','undeclared','total_swipe',
            'deposit_swipe_card','late_payment_card','maya_swipe','unpaid_med_credit',
            'grab_sales','gcash','gift_card','marketing_pull_out','discount',
            'bank_transfer_cheque','pcf_expenses','other_expenses','coh'];
        $vals = [];
        foreach ($NUM_COLS as $f) $vals[$f] = (float)($_POST[$f] ?? 0);

        $secSumStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM pub_express_sales_detail_rows WHERE store_name='Pub Express' AND report_date=? GROUP BY section");
        $secSumStmt->execute([$reportDate]);
        $secSums = [];
        foreach ($secSumStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $secSums[$r['section']] = (float)$r['total'];
        // Grab Sales: from the Grab section rows if any; otherwise keep the value typed in the Grab Sales box
        $vals['grab_sales']         = isset($secSums['grab']) ? $secSums['grab'] : (float)($_POST['grab_sales'] ?? 0);
        $vals['marketing_pull_out'] = $secSums['marketing_pullout'] ?? 0;
        $vals['pcf_expenses']       = ($secSums['expenses'] ?? 0) + ($secSums['cash_advance'] ?? 0) + ($secSums['payroll_salary'] ?? 0);  // Expenses + Cash Advance + Payroll/Salary
        $unpaidsVal                 = $secSums['unpaids'] ?? 0;

        // GROSS SALES = Declared + Undeclared + Walk-in/POS
        $vals['gross_sales'] = $vals['z_reading_gross'] + $vals['undeclared'] + $vals['total_swipe'];

        // DISCOUNTS = Walk-in/POS + Grab Discounts (Grab Discounts mirrors Grab Sales — see subChanged() in JS)
        $grabDiscounts    = isset($_POST['grab_discounts']) ? (float)$_POST['grab_discounts'] : $vals['grab_sales'];
        $vals['discount'] = $vals['total_swipe'] + $grabDiscounts;

        // Net Sales = Gross - Marketing Pull-out - Unpaids - Grab - Discounts - Maya Card - Maya QR - QR PH - Expenses
        $netSales = $vals['gross_sales']
                  - $vals['marketing_pull_out']
                  - $unpaidsVal
                  - ($vals['grab_sales'] - $grabDiscounts)   // Grab net of Grab Discounts (matches Excel)
                  - $vals['discount']
                  - $vals['maya_swipe']
                  - $vals['gcash']
                  - $vals['deposit_swipe_card']
                  - $vals['pcf_expenses'];
        $shortOver = $vals['coh'] - $netSales;

        $declaredTc = (isset($_POST['declared_tc']) && trim($_POST['declared_tc']) !== '')
            ? (float)$_POST['declared_tc'] : null;
        // Make sure the declared_tc column exists (re-try the migration at save time)
        $hasDeclaredCol = false;
        try {
            $hasDeclaredCol = (bool)$pdo->query("SHOW COLUMNS FROM `pub_express_sales_report` LIKE 'declared_tc'")->fetch();
            if (!$hasDeclaredCol) {
                $pdo->exec("ALTER TABLE `pub_express_sales_report` ADD COLUMN `declared_tc` decimal(12,2) DEFAULT NULL AFTER `short_over`");
                $hasDeclaredCol = true;
            }
        } catch (Throwable $ignored) {}
        // Make sure total_tc exists too
        $hasTotalCol = false;
        try {
            $hasTotalCol = (bool)$pdo->query("SHOW COLUMNS FROM `pub_express_sales_report` LIKE 'total_tc'")->fetch();
            if (!$hasTotalCol) {
                $pdo->exec("ALTER TABLE `pub_express_sales_report` ADD COLUMN `total_tc` decimal(12,2) DEFAULT NULL AFTER `declared_tc`");
                $hasTotalCol = true;
            }
        } catch (Throwable $ignored) {}
        $totalTc = $declaredTc ?? 0.0;   // Total TC = Declared TC only (never the denomination)

        $extraCols = ['net_sales','short_over'];
        $extraVals = [$netSales, $shortOver];
        if ($hasDeclaredCol) { $extraCols[] = 'declared_tc'; $extraVals[] = $declaredTc; }
        if ($hasTotalCol)    { $extraCols[] = 'total_tc';    $extraVals[] = $totalTc; }
        $extraCols[] = 'saved_by'; $extraVals[] = $user['name'];

        $allCols = array_merge($NUM_COLS, $extraCols);
        $allVals = array_merge(array_values($vals), $extraVals);

        // Explicit update-or-insert (does not depend on a unique key existing)
        $ex = $pdo->prepare("SELECT id FROM pub_express_sales_report WHERE store_name='Pub Express' AND report_date=? ORDER BY id DESC LIMIT 1");
        $ex->execute([$reportDate]);
        $existingId = $ex->fetchColumn();
        if ($existingId !== false) {
            $set = implode(',', array_map(fn($f)=>"`$f`=?", $allCols));
            $pdo->prepare("UPDATE pub_express_sales_report SET $set WHERE id=?")
                ->execute(array_merge($allVals, [$existingId]));
        } else {
            $fields = array_merge(['store_name','report_date'], $allCols);
            $data   = array_merge(['Pub Express', $reportDate], $allVals);
            $pdo->prepare("INSERT INTO pub_express_sales_report (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ")
                VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")")->execute($data);
        }
        // Audit logging must never make a successful save look like a failure
        try {
            logAudit($pdo, 'Pub Express', 'Sales Report', 'updated', $reportDate, 'Saved daily summary totals',
                ['net_sales' => $netSales, 'short_over' => $shortOver]);
        } catch (Throwable $ignored) {}
        $out = ['ok'=>true,'net_sales'=>$netSales,'short_over'=>$shortOver,'declared_tc_saved'=>$hasDeclaredCol];
        if (!$hasDeclaredCol) $out['warn'] = 'Saved, but Declared TC could not be stored: the database column declared_tc is missing and could not be created (check ALTER permission).';
        ob_clean();
        echo json_encode($out);
    } catch (Throwable $e) { ob_clean(); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save DINE IN rows ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_dinein'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Pub Express', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM pub_express_dinein_rows WHERE store_name='Pub Express' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO pub_express_dinein_rows (store_name,report_date,cash,palawan_pay,card_swipe_qr,unpaid_credit_name,unpaid_credit_amount,discount,bank_transfer_cheque,cancelled_transactions,sort_order) VALUES ('Pub Express',?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, (float)($r['cash']??0), (float)($r['palawan_pay']??0),
                (float)($r['card_swipe_qr']??0), $r['unpaid_credit_name']??null,
                (float)($r['unpaid_credit_amount']??0), (float)($r['discount']??0),
                (float)($r['bank_transfer_cheque']??0), (float)($r['cancelled_transactions']??0), $i]);
        }
        logAudit($pdo, 'Pub Express', 'Sales Report', 'updated', $reportDate,
            'Updated Dine In rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save sub-section detail rows ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_detail'])) {
    ob_start();
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $section    = $_POST['section'];
        if (isDateLocked($pdo, 'Pub Express', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM pub_express_sales_detail_rows WHERE store_name='Pub Express' AND report_date=? AND section=?")->execute([$reportDate,$section]);
        $ins = $pdo->prepare("INSERT INTO pub_express_sales_detail_rows (store_name,report_date,section,item_name,amount,sort_order) VALUES ('Pub Express',?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $section, $r['name']??null, (float)($r['amount']??0), $i]);
        }
        try {
            logAudit($pdo, 'Pub Express', 'Sales Report', 'updated', $reportDate,
                'Updated "' . $section . '" rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        } catch (Throwable $ignored) {}
        ob_clean();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { ob_clean(); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle Excel (.xlsx) download ─────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM pub_express_sales_report WHERE store_name='Pub Express' AND report_date=?");
    $stmt->execute([$reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');

    $filename = 'Pub_Express_SalesReport_'.$reportDate.'.xlsx';

    $xlsx = new SimpleXlsxWriter(salesReportAccentColor('Pub Express'));
    $xlsx->title('Pub Express Branch — Daily Sales Report');
    $xlsx->subtitle(date('F j, Y', strtotime($reportDate)));
    $xlsx->blank();

    // — CASH COUNT (Denomination) —
    $xlsx->sectionRow('CASH COUNT – DENOMINATION');
    $xlsx->header(['Qty','Denomination','Total Collection']);
    $pubDenoms = [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.25, 0.10];
    $pubDetStmt0 = $pdo->prepare("SELECT * FROM pub_express_sales_detail_rows WHERE store_name='Pub Express' AND report_date=? AND section='denomination' ORDER BY sort_order ASC");
    $pubDetStmt0->execute([$reportDate]);
    $pubDenomData = [];
    foreach ($pubDetStmt0->fetchAll(PDO::FETCH_ASSOC) as $dd) $pubDenomData[(string)(float)$dd['item_name']] = (float)$dd['amount'];
    foreach ($pubDenoms as $denom) {
        $qty = $pubDenomData[(string)$denom] ?? 0;
        if ($qty == 0) continue;
        $xlsx->row([$qty, number_format($denom, $denom < 1 ? 2 : 0, '.', ''), number_format($qty * $denom, 2, '.', '')]);
    }
    $xlsx->blank();

    // — Sub-sections (Marketing Pull-out, Unpaids, Expenses) —
    $pubSubSections = [
        'marketing_pullout' => 'MARKETING PULL-OUT',
        'unpaids'           => 'UNPAIDS',
        'expenses'          => 'EXPENSES',
        'cash_advance'      => 'CASH ADVANCE',
        'payroll_salary'    => 'PAYROLL / SALARY',
    ];
    $pubDetStmt = $pdo->prepare("SELECT * FROM pub_express_sales_detail_rows WHERE store_name='Pub Express' AND report_date=? AND section=? ORDER BY sort_order ASC");
    foreach ($pubSubSections as $sec => $label) {
        $xlsx->sectionRow($label);
        $xlsx->header(['Particular','Amount']);
        $pubDetStmt->execute([$reportDate, $sec]);
        foreach ($pubDetStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $xlsx->row([$d['item_name'], number_format((float)$d['amount'],2,'.','')]);
        }
        $xlsx->blank();
    }

    // — SUMMARY REPORT —
    $xlsx->sectionRow('SUMMARY REPORT');
    $xlsx->header(['Particular', 'Amount']);
    $xlsx->row(['Gross Sales', $g('gross_sales')]);
    $xlsx->row(['Service Charge', $g('service_charge')]);
    $xlsx->row(['Z Reading Gross', $g('z_reading_gross')]);
    $xlsx->row(['Undeclared', $g('undeclared')]);
    $xlsx->row(['Total Swipe', $g('total_swipe')]);
    $xlsx->row(['Deposit Swipe (Card)', $g('deposit_swipe_card')]);
    $xlsx->row(['Late Payment (Card)', $g('late_payment_card')]);
    $xlsx->row(['Sales of the day Swipe (MAYA)', $g('maya_swipe')]);
    $xlsx->row(['Unpaid Med (Credit)', $g('unpaid_med_credit')]);
    $xlsx->row(['Grab Sales', $g('grab_sales')]);
    $xlsx->row(['G-Cash', $g('gcash')]);
    $xlsx->row(['Gift Card', $g('gift_card')]);
    $xlsx->row(['Marketing Pull Out', $g('marketing_pull_out')]);
    $xlsx->row(['Discount', $g('discount')]);
    $xlsx->row(['Bank Transfer/Cheque', $g('bank_transfer_cheque')]);
    $xlsx->row(['PCF/Expenses', $g('pcf_expenses')]);
    $xlsx->row(['Other Expenses', $g('other_expenses')]);
    $xlsx->totalRow(['Net Sales', $g('net_sales')]);
    $xlsx->totalRow(['COH (Cash on Hand)', $g('coh')]);
    $xlsx->totalRow(['Short/Over', $g('short_over')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([34, 18, 18]);
    $xlsx->output($filename);
}

// ── Fetch data for display ─────────────────────────────────
$fDate = $_GET['date'] ?? date('Y-m-d');
$locked = isDateLocked($pdo, 'Pub Express', $fDate);
// Lock stops Cashier edits only — OIC (Accounting) and Manager can still edit a locked date.
$restrictEdit = $locked && isCashier();
$stmt  = $pdo->prepare("SELECT * FROM pub_express_sales_report WHERE store_name='Pub Express' AND report_date=? ORDER BY id DESC LIMIT 1");
$stmt->execute([$fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$fmt = fn($n)  => number_format((float)$n, 2);
// Plain decimal (no thousands separator) — required for <input type="number"> values
$num = fn($n)  => number_format((float)$n, 2, '.', '');

// Fetch DINE IN rows
$diStmt = $pdo->prepare("SELECT * FROM pub_express_dinein_rows WHERE store_name='Pub Express' AND report_date=? ORDER BY sort_order ASC");
$diStmt->execute([$fDate]);
$dineinRows = $diStmt->fetchAll(PDO::FETCH_ASSOC);
$diSum = fn($col) => array_sum(array_column($dineinRows, $col));

// Fetch sub-section detail rows
$detailStmt = $pdo->prepare("SELECT * FROM pub_express_sales_detail_rows WHERE store_name='Pub Express' AND report_date=? ORDER BY section, sort_order ASC");
$detailStmt->execute([$fDate]);
$allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
$details = [];
foreach ($allDetails as $d) $details[$d['section']][] = $d;
$secSum = fn($sec) => array_sum(array_column($details[$sec] ?? [], 'amount'));

$pageTitle  = 'Pub Express Sales Report';
$activePage = 'pub_express_sales_report';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
/* Cash Advance / Payroll headers — kept here so they show correctly even if style.css is old/cached */
.page-pub_express_sales_report .sub-sec-title.teal   { background:#0f6b6b !important; color:#fff !important; }
.page-pub_express_sales_report .sub-sec-title.purple { background:#4a2c7a !important; color:#fff !important; }
.page-pub_express_sales_report .sub-sec-title.teal span,
.page-pub_express_sales_report .sub-sec-title.purple span { color:#fff !important; }
</style>

<!-- Page header -->
<div class="sr-page">
<div class="sr-header-card">
  <div>
    <div class="eyebrow">Pub Express Branch · Sales</div>
    <div class="title">Daily Sales Report</div>
    <div class="subtitle">Denomination count · Marketing Pull-out · Unpaids · Expenses · Full Summary</div>
  </div>
  <span style="background:rgba(255,255,255,.12);color:#fff;padding:5px 14px;border-radius:20px;font-size:.63rem;font-weight:600">📌 Pub Express</span>
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
    <a href="pub_express_sales_report.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download Excel</a>
    <span id="saveStatus" style="font-size:.72rem;color:var(--subtext)"></span>
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

<!-- ══════ TWO-COLUMN: Denomination  |  Right sub-sections ══════ -->
<div class="sr-two-col">

  <!-- LEFT: Cash denomination count -->
  <div class="sr-card">
    <div class="sr-card-title">💵 Cash Count – Denomination</div>
    <table class="denom-table" id="denom-table">
      <thead>
        <tr>
          <th>QTY</th>
          <th>DENOMINATION</th>
          <th>TOTAL COLLECTION</th>
        </tr>
      </thead>
      <tbody id="denom-body">
        <?php
        $denominations = [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.25, 0.10];
        $denomData = [];
        foreach (($details['denomination'] ?? []) as $d) {
            $denomData[(string)(float)$d['item_name']] = (float)$d['amount'];
        }
        foreach ($denominations as $denom):
            $qty = $denomData[(string)$denom] ?? 0;
            $tot = $qty * $denom;
        ?>
        <tr data-denom="<?= $denom ?>">
          <td><input class="denom-inp" type="number" min="0" step="1" value="<?= $qty ?: '' ?>" placeholder="0" oninput="denomChanged()"></td>
          <td class="denom-label"><?= number_format($denom, $denom < 1 ? 2 : 0) ?></td>
          <td class="denom-total-cell" id="denom-tot-<?= str_replace('.','_',$denom) ?>"><?= $tot > 0 ? number_format($tot,2) : '0.00' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="total-lbl" colspan="2">TOTAL</td>
          <td id="denom-grand-total" style="text-align:right"><?= number_format(array_sum(array_map(fn($d)=>($denomData[(string)$d]??0)*$d,$denominations)),2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- RIGHT: Marketing Pull-out, Unpaids, Expenses -->
  <div class="sub-sections">

    <!-- Marketing Pull-out -->
    <div class="sub-sec-card">
      <div class="sub-sec-title blue">
        <span>📋 Marketing Pull-out</span>
        <button class="btn-add-sub" onclick="addSubRow('marketing_pullout')">+ Add</button>
      </div>
      <div class="sub-sec-hdr"><span>Name</span><span>Amount</span><span></span></div>
      <div class="sub-sec-rows" id="rows-marketing_pullout">
        <?php foreach (($details['marketing_pullout'] ?? [['item_name'=>'','amount'=>0]]) as $sr): ?>
        <div class="sub-sec-row">
          <input class="sub-inp" type="text" placeholder="Name…" value="<?= htmlspecialchars($sr['item_name']??'') ?>" oninput="subChanged('marketing_pullout')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('marketing_pullout')">
          <button class="btn-del-sub" onclick="delSubRow(this,'marketing_pullout')">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sub-sec-footer">
        <span><em>Total</em></span>
        <span id="sub-tot-marketing_pullout"><?= number_format($secSum('marketing_pullout'),2) ?></span>
      </div>
    </div>

    <!-- Unpaids -->
    <div class="sub-sec-card">
      <div class="sub-sec-title gold">
        <span>📄 Unpaids</span>
        <button class="btn-add-sub" onclick="addSubRow('unpaids')">+ Add</button>
      </div>
      <div class="sub-sec-hdr"><span>Name</span><span>Amount</span><span></span></div>
      <div class="sub-sec-rows" id="rows-unpaids">
        <?php foreach (($details['unpaids'] ?? [['item_name'=>'','amount'=>0]]) as $sr): ?>
        <div class="sub-sec-row">
          <input class="sub-inp" type="text" placeholder="Name…" value="<?= htmlspecialchars($sr['item_name']??'') ?>" oninput="subChanged('unpaids')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('unpaids')">
          <button class="btn-del-sub" onclick="delSubRow(this,'unpaids')">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sub-sec-footer">
        <span><em>Total</em></span>
        <span id="sub-tot-unpaids"><?= number_format($secSum('unpaids'),2) ?></span>
      </div>
    </div>

    <!-- Expenses -->
    <div class="sub-sec-card">
      <div class="sub-sec-title red">
        <span>💸 Expenses</span>
        <button class="btn-add-sub" onclick="addSubRow('expenses')">+ Add</button>
      </div>
      <div class="sub-sec-hdr"><span>Particular</span><span>Amount</span><span></span></div>
      <div class="sub-sec-rows" id="rows-expenses">
        <?php foreach (($details['expenses'] ?? [['item_name'=>'','amount'=>0]]) as $sr): ?>
        <div class="sub-sec-row">
          <input class="sub-inp" type="text" placeholder="Item…" value="<?= htmlspecialchars($sr['item_name']??'') ?>" oninput="subChanged('expenses')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('expenses')">
          <button class="btn-del-sub" onclick="delSubRow(this,'expenses')">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sub-sec-footer">
        <span><em>Total</em></span>
        <span id="sub-tot-expenses"><?= number_format($secSum('expenses'),2) ?></span>
      </div>
    </div>

    <!-- Cash Advance -->
    <div class="sub-sec-card">
      <div class="sub-sec-title teal">
        <span>💰 Cash Advance</span>
        <button class="btn-add-sub" onclick="addSubRow('cash_advance')">+ Add</button>
      </div>
      <div class="sub-sec-hdr"><span>Particular</span><span>Amount</span><span></span></div>
      <div class="sub-sec-rows" id="rows-cash_advance">
        <?php foreach (($details['cash_advance'] ?? [['item_name'=>'','amount'=>0]]) as $sr): ?>
        <div class="sub-sec-row">
          <input class="sub-inp" type="text" placeholder="Name…" value="<?= htmlspecialchars($sr['item_name']??'') ?>" oninput="subChanged('cash_advance')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('cash_advance')">
          <button class="btn-del-sub" onclick="delSubRow(this,'cash_advance')">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sub-sec-footer">
        <span><em>Total</em></span>
        <span id="sub-tot-cash_advance"><?= number_format($secSum('cash_advance'),2) ?></span>
      </div>
    </div>

    <!-- Payroll / Salary -->
    <div class="sub-sec-card">
      <div class="sub-sec-title purple">
        <span>🧾 Payroll / Salary</span>
        <button class="btn-add-sub" onclick="addSubRow('payroll_salary')">+ Add</button>
      </div>
      <div class="sub-sec-hdr"><span>Particular</span><span>Amount</span><span></span></div>
      <div class="sub-sec-rows" id="rows-payroll_salary">
        <?php foreach (($details['payroll_salary'] ?? [['item_name'=>'','amount'=>0]]) as $sr): ?>
        <div class="sub-sec-row">
          <input class="sub-inp" type="text" placeholder="Name…" value="<?= htmlspecialchars($sr['item_name']??'') ?>" oninput="subChanged('payroll_salary')">
          <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('payroll_salary')">
          <button class="btn-del-sub" onclick="delSubRow(this,'payroll_salary')">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sub-sec-footer">
        <span><em>Total</em></span>
        <span id="sub-tot-payroll_salary"><?= number_format($secSum('payroll_salary'),2) ?></span>
      </div>
    </div>

  </div><!-- /sub-sections -->
</div><!-- /sr-two-col -->

<!-- ══════ SUMMARY REPORT ══════ -->
<?php
// Compute initial values for summary
$declaredCalc   = $v('z_reading_gross');
$undeclaredCalc = $row && isset($row['undeclared']) ? $v('undeclared') : ($v('gross_sales') - $v('z_reading_gross'));
$grabCalcV      = isset($row['grab_sales']) && !$secSum('grab') ? $v('grab_sales') : $secSum('grab');
$walkInPosCalc  = $v('total_swipe'); // manual — Walk-in / POS
$grabDiscCalc   = ($row && isset($row['discount'])) ? max(0, round($v('discount') - $walkInPosCalc, 2)) : $grabCalcV;

// GROSS SALES = Declared + Undeclared + Walk-in / POS  (read-only, auto)
$grossSalesCalc = $declaredCalc + $undeclaredCalc + $walkInPosCalc;
// DISCOUNTS = Walk-in / POS + Grab Discounts  (read-only, auto)
$discountsCalc  = $walkInPosCalc + $grabDiscCalc;

$mayaCardCalc   = $v('maya_swipe');
$mayaQrCalc     = $v('gcash');
$qrPhCalc       = $v('deposit_swipe_card');
$marketingCalcV = $secSum('marketing_pullout');
$unpaidsCalcV   = $secSum('unpaids');
$expensesCalcV  = $secSum('expenses') + $secSum('cash_advance') + $secSum('payroll_salary');  // Expenses + Cash Advance + Payroll/Salary
$totalTcCalc    = $v('coh');            // from denomination
$declaredTcCalc = (isset($row['declared_tc']) && $row['declared_tc'] !== null) ? (float)$row['declared_tc'] : 0.0; // never falls back to denomination
$netSalesCalc   = $grossSalesCalc - $marketingCalcV - $unpaidsCalcV - ($grabCalcV - $grabDiscCalc) - $discountsCalc - $mayaCardCalc - $mayaQrCalc - $qrPhCalc - $expensesCalcV;
$cohCalc        = array_sum(array_map(fn($d)=>($denomData[(string)$d]??0)*$d,$denominations));
$shortOverCalc  = $cohCalc - $netSalesCalc;
?>
<div class="sr-summary">
<div class="sr-sum-card">
  <div class="sr-sum-title">Summary Report</div>

  <!-- Date -->
  <div class="sr-row" style="background:#f5f8ff">
    <div class="sr-lbl" style="text-align:left">For</div>
    <div class="sr-val-wrap"><span class="sr-inp ro" style="display:block;background:#eef2ff;border-radius:6px;text-align:center;padding:6px 10px"><?= date('n/j/Y', strtotime($fDate)) ?></span></div>
  </div>

  <!-- Gross Sales -->
  <div class="sr-row green">
    <div class="sr-lbl">GROSS SALES <span style="font-size:.62rem;color:#999">(auto: Declared + Undeclared + Walk-in/POS)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="gross_sales" value="<?= $fmt($grossSalesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <!-- Declared -->
  <div class="sr-row sub-row">
    <div class="sr-lbl">Declared</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="declared" value="<?= $v('z_reading_gross') ?: '' ?>" placeholder="0.00" oninput="recalc()"></div>
    <div class="sr-note dark">(Z Reading)</div>
  </div>
  <!-- Undeclared (editable, defaults to Gross − Declared until overridden) -->
  <div class="sr-row sub-row">
    <div class="sr-lbl">Undeclared <span style="font-size:.62rem;color:#999">(editable)</span></div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="undeclared"
         value="<?= $undeclaredCalc ? $num($undeclaredCalc) : '' ?>"
         placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- Grab Sales (auto) -->
  <div class="sr-row sub-row">
    <div class="sr-lbl">Grab Sales <span style="font-size:.62rem;color:#999">(auto from Grab section)</span></div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="grab_sales" value="<?= $grabCalcV ? $num($grabCalcV) : '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- Discounts -->
  <div class="sr-row sub-row">
    <div class="sr-lbl">Discounts <span style="font-size:.62rem;color:#999">(auto: Walk-in/POS + Grab Discounts)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="discount" value="<?= $fmt($discountsCalc) ?>" readonly tabindex="-1"></div>
  </div>

  <!-- Divider -->
  <div class="sr-row divider"><div style="font-size:.6rem;color:#8899cc;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Payment Channels</div></div>

  <!-- Walk-in / POS -->
  <div class="sr-row gray-hdr">
    <div class="sr-lbl">Walk-in / POS</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="walkin_pos" value="<?= $v('total_swipe') ?: '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- Grab Discounts (sub) -->
  <div class="sr-row sub-row">
    <div class="sr-lbl">Grab Discounts <span style="font-size:.62rem;color:#999">(auto from Grab, editable)</span></div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="grab_discounts" value="<?= $grabDiscCalc ? $num($grabDiscCalc) : '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- Maya Card -->
  <div class="sr-row gray-hdr">
    <div class="sr-lbl">Maya Card</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="maya_card" value="<?= $v('maya_swipe') ?: '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- Maya QR -->
  <div class="sr-row gray-hdr">
    <div class="sr-lbl">Maya QR</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="maya_qr" value="<?= $v('gcash') ?: '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>
  <!-- QR PH -->
  <div class="sr-row gray-hdr">
    <div class="sr-lbl">QR PH</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="qr_ph" value="<?= $v('deposit_swipe_card') ?: '' ?>" placeholder="0.00" oninput="recalc()"></div>
  </div>

  <!-- Divider -->
  <div class="sr-row divider"><div style="font-size:.6rem;color:#8899cc;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Deductions</div></div>

  <!-- Marketing Pull-out (auto) -->
  <div class="sr-row pink-val">
    <div class="sr-lbl">Marketing Pull-out <span style="font-size:.62rem;color:#999">(auto)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="marketing_pull_out" value="<?= $fmt($marketingCalcV) ?>" readonly tabindex="-1"></div>
  </div>
  <!-- Unpaids (auto) -->
  <div class="sr-row pink-val">
    <div class="sr-lbl">Unpaids <span style="font-size:.62rem;color:#999">(auto)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="unpaids" value="<?= $fmt($unpaidsCalcV) ?>" readonly tabindex="-1"></div>
  </div>
  <!-- Expenses (auto) -->
  <div class="sr-row pink-val">
    <div class="sr-lbl">Expenses <span style="font-size:.62rem;color:#999">(auto: Expenses + Cash Advance + Payroll/Salary)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="pcf_expenses" value="<?= $fmt($expensesCalcV) ?>" readonly tabindex="-1"></div>
  </div>

  <!-- Divider -->
  <div class="sr-row divider"></div>

  <!-- Total TC -->
  <div class="sr-row" style="background:#e8f5e9">
    <div class="sr-lbl" style="font-weight:800;color:#1b5e20">Total TC</div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="total_tc" value="<?= $fmt($declaredTcCalc) ?>" readonly tabindex="-1" style="color:#1b5e20;font-size:.92rem"></div>
    <div class="sr-note dark">(auto from Declared TC)</div>
  </div>
  <!-- Declared TC -->
  <div class="sr-row" style="background:#fff9c4">
    <div class="sr-lbl" style="font-weight:700;color:#3e2f00">Declared TC</div>
    <div class="sr-val-wrap"><input type="number" step="0.01" class="sr-inp" id="declared_tc" value="<?= $declaredTcCalc ? $num($declaredTcCalc) : '' ?>" placeholder="0.00" oninput="recalc()" style="border:2px dashed #e8b64c"></div>
  </div>

  <!-- Divider -->
  <div class="sr-row divider"></div>

  <!-- Net Sales (auto) -->
  <div class="sr-row net">
    <div class="sr-lbl">Net Sales</div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="net_sales" value="<?= $fmt($netSalesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <!-- COH -->
  <div class="sr-row coh-row">
    <div class="sr-lbl">COH (Cash on Hand) <span class="sr-note">(denomination total)</span></div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="coh" value="<?= $fmt($cohCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <!-- Short/Over -->
  <div class="sr-row short-row">
    <div class="sr-lbl">Short / Over</div>
    <div class="sr-val-wrap"><input type="text" class="sr-inp ro" id="short_over" value="<?= $fmt($shortOverCalc) ?>" readonly tabindex="-1"></div>
    <div class="sr-note">(if "−" sign means short)</div>
  </div>

</div>
</div><!-- /sr-summary -->
</div><!-- /sr-page -->

<script>
const FDATE = '<?= $fDate ?>';
const REPORT_LOCKED = <?= $restrictEdit ? 'true' : 'false' ?>;
const REPORT_LOCKED_RAW = <?= $locked ? 'true' : 'false' ?>;
const fmt   = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const gv    = id => parseFloat(String(document.getElementById(id)?.value??'').replace(/,/g,''))||0;
const sv    = (id,v) => { const e=document.getElementById(id); if(e) e.value=v; };

// ── Denomination ─────────────────────────────────────────
const DENOMS = [1000,500,200,100,50,20,10,5,1,0.25,0.10];
function denomChanged() {
  let grand = 0;
  document.querySelectorAll('#denom-body tr[data-denom]').forEach(tr => {
    const denom = parseFloat(tr.dataset.denom);
    const qty   = parseFloat(tr.querySelector('input').value) || 0;
    const tot   = qty * denom;
    const key   = String(denom).replace('.','_');
    const cell  = document.getElementById('denom-tot-'+key);
    if (cell) cell.textContent = fmt(tot);
    grand += tot;
  });
  document.getElementById('denom-grand-total').textContent = fmt(grand);
  sv('coh', fmt(grand));
  recalc();
}

// ── Sub-sections (right col) ──────────────────────────────
function subChanged(sec) {
  const container = document.getElementById('rows-'+sec);
  let tot = 0;
  container.querySelectorAll('input[type=number]').forEach(i => tot += parseFloat(i.value)||0);
  const el = document.getElementById('sub-tot-'+sec);
  if (el) el.textContent = fmt(tot);
  // Feed summary
  if (sec==='marketing_pullout') { sv('marketing_pull_out', fmt(tot)); recalc(); }
  if (sec==='unpaids')           { sv('unpaids', fmt(tot)); recalc(); }
  if (sec==='expenses' || sec==='cash_advance' || sec==='payroll_salary') { updateExpensesTotal(); }
  if (sec==='grab')              { sv('grab_sales', tot ? tot.toFixed(2) : ''); sv('grab_discounts', tot ? tot.toFixed(2) : ''); recalc(); }
}

// Summary Expenses = Expenses + Cash Advance + Payroll/Salary
function updateExpensesTotal() {
  let total = 0;
  ['expenses','cash_advance','payroll_salary'].forEach(sec => {
    const c = document.getElementById('rows-'+sec);
    if (c) c.querySelectorAll('input[type=number]').forEach(i => total += parseFloat(i.value)||0);
  });
  sv('pcf_expenses', fmt(total));
  recalc();
}

function addSubRow(sec) {
  const container = document.getElementById('rows-'+sec);
  const div = document.createElement('div');
  div.className = 'sub-sec-row';
  div.innerHTML = `
    <input class="sub-inp" type="text" placeholder="Name…" oninput="subChanged('${sec}')">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="subChanged('${sec}')">
    <button class="btn-del-sub" onclick="delSubRow(this,'${sec}')">✕</button>`;
  container.appendChild(div);
}

function delSubRow(btn, sec) {
  btn.closest('.sub-sec-row').remove();
  subChanged(sec);
}

// ── Summary recalc ────────────────────────────────────────
function recalc() {
  const declared   = gv('declared');
  const undeclared = gv('undeclared');
  const walkin     = gv('walkin_pos');
  const grab       = gv('grab_sales');
  const grabDisc   = gv('grab_discounts');
  const mayaCard   = gv('maya_card');
  const mayaQr     = gv('maya_qr');
  const qrPh       = gv('qr_ph');
  const marketing  = gv('marketing_pull_out');
  const unpaids    = gv('unpaids');
  const expenses   = gv('pcf_expenses');
  const coh        = gv('coh');

  // GROSS SALES = Declared + Undeclared + Walk-in/POS
  const gross = declared + undeclared + walkin;
  sv('gross_sales', fmt(gross));

  // DISCOUNTS = Walk-in/POS + Grab Discounts
  const discount = walkin + grabDisc;
  sv('discount', fmt(discount));

  // Net Sales = Gross - Marketing Pull-out - Unpaids - Grab - Discounts - Maya Card - Maya QR - QR PH - Expenses
  const netSales = gross - marketing - unpaids - (grab - grabDisc) - discount - mayaCard - mayaQr - qrPh - expenses;
  const shortOver = coh - netSales;

  sv('net_sales',  fmt(netSales));
  // Total TC follows ONLY the Declared TC input (never the Cash Count denomination)
  const declTcRaw = document.getElementById('declared_tc')?.value ?? '';
  sv('total_tc', fmt(String(declTcRaw).trim() !== '' ? gv('declared_tc') : 0));
  sv('short_over', fmt(shortOver));

  const soEl = document.getElementById('short_over');
  if (soEl) soEl.style.color = shortOver < 0 ? '#ffd54f' : '#b71c1c';
}

// ── Save All ──────────────────────────────────────────────
async function postJson(fd) {
  const res = await fetch('pub_express_sales_report.php', {method:'POST', body:fd});
  const txt = await res.text();
  let data;
  try { data = JSON.parse(txt); }
  catch (e) { throw new Error('Server returned an unexpected response: ' + txt.replace(/<[^>]*>/g,' ').trim().slice(0,200)); }
  if (!data.ok) throw new Error(data.msg || 'Save failed');
  return data;
}

async function saveAll() {
  if (REPORT_LOCKED) {
    showToast('🔒 This date is locked and cannot be edited.', 'error');
    return;
  }
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…'; status.style.color = '';
  try {
    // 1. Save denomination as sub-section rows
    const denomRows = [];
    document.querySelectorAll('#denom-body tr[data-denom]').forEach(tr => {
      const qty = parseFloat(tr.querySelector('input').value)||0;
      if (qty > 0) denomRows.push({ name: tr.dataset.denom, amount: qty });
    });
    const fd0 = new FormData();
    fd0.append('ajax_save_detail','1'); fd0.append('report_date',FDATE);
    fd0.append('section','denomination'); fd0.append('rows',JSON.stringify(denomRows));
    await postJson(fd0);

    // 2. Save each right-col sub-section
    for (const sec of ['marketing_pullout','unpaids','expenses','cash_advance','payroll_salary','grab']) {
      const container = document.getElementById('rows-'+sec);
      if (!container) continue;
      const rows = [];
      container.querySelectorAll('.sub-sec-row').forEach(row => {
        const inps = row.querySelectorAll('input');
        rows.push({ name: inps[0]?.value||'', amount: parseFloat(inps[1]?.value)||0 });
      });
      const fd = new FormData();
      fd.append('ajax_save_detail','1'); fd.append('report_date',FDATE);
      fd.append('section',sec); fd.append('rows',JSON.stringify(rows));
      await postJson(fd);
    }

    // 3. Save main summary
    recalc();
    const fd3 = new FormData();
    fd3.append('ajax_save','1'); fd3.append('report_date',FDATE);
    const fieldMap = {
      gross_sales: 'gross_sales', z_reading_gross: 'declared', undeclared: 'undeclared',
      total_swipe: 'walkin_pos', maya_swipe: 'maya_card',
      gcash: 'maya_qr', deposit_swipe_card: 'qr_ph',
      grab_sales: 'grab_sales', marketing_pull_out: 'marketing_pull_out',
      discount: 'discount', pcf_expenses: 'pcf_expenses',
      coh: 'coh', net_sales: 'net_sales',
      late_payment_card: 'walkin_pos', service_charge: 'service_charge',
    };
    for (const [dbField, domId] of Object.entries(fieldMap)) {
      fd3.append(dbField, gv(domId));
    }
    fd3.append('declared_tc', document.getElementById('declared_tc')?.value ?? '');
    fd3.append('grab_discounts', gv('grab_discounts'));
    fd3.append('other_expenses', 0);
    const data = await postJson(fd3);
    if (data.warn) {
      showToast('⚠ ' + data.warn, 'error');
      status.textContent = '⚠ Partially saved';
    } else {
      status.textContent='✓ Saved'; status.style.color='var(--accent)';
      showToast('✓ Sales report saved','success');
    }
  } catch (err) {
    showToast('❌ ' + (err.message || 'Error'), 'error');
    status.textContent = '❌ Not saved';
  }
  setTimeout(()=>{status.textContent='';},6000);
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
    const res  = await fetch('pub_express_sales_report.php', {method:'POST', body:fd});
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

function showToast(msg,type) {
  const t=document.createElement('div');
  t.className='flash flash-'+(type||'success')+' toast';
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),4000);
}

document.addEventListener('DOMContentLoaded',()=>{ denomChanged(); recalc(); if (REPORT_LOCKED) lockPage(); });
</script>
</body>
</html>