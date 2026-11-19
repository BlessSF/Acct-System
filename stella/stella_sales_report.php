<?php
// ============================================================
//  stella_sales_report.php — Stella Branch Daily Sales Report
//  DINE IN detail rows + sub-section detail rows (Marketing
//  Pullout, GRAB, Expenses, Late Payment, Advance Payment,
//  GC Sponsorship, GC Sold) — totals feed Net Sales / Short/Over
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/audit_log.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Lock stops Cashier edits only — OIC (Accounting) and Manager can
// still edit a locked date.
$canLock = !isCashier();

// ── Main summary table — Stella-specific fields ────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_sales_report` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `store_name`            varchar(50) NOT NULL DEFAULT 'Stella',
    `report_date`           date NOT NULL,
    `gross_sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `service_charge`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `pos_z_reading`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_swipe`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `deposit_swipe_card`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `late_payment_card`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `maya_swipe`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `unpaid_med_credit`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `paid_med_card`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `advance_paid_appigo`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `grab_sales`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `gcash`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `gift_card`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `marketing_pull_out`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `discount`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_transfer_cheque`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash_out_interest`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `gift_cert_sold_tips`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `pcf_expenses`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `other_expenses`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `coh`                   decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_sales`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `short_over`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`              varchar(100) DEFAULT NULL,
    `cashier_name`          varchar(150) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Migrate new columns safely (ignore if already exists)
foreach (['paid_med_card','advance_paid_appigo','cash_out_interest','gift_cert_sold_tips','pos_z_reading','cash_replenishment'] as $col) {
    try { $pdo->exec("ALTER TABLE `stella_sales_report` ADD COLUMN `$col` decimal(12,2) NOT NULL DEFAULT 0.00"); }
    catch (Throwable $ignored) {}
}
try { $pdo->exec("ALTER TABLE `stella_sales_report` ADD COLUMN `cashier_name` varchar(150) DEFAULT NULL"); }
catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `stella_sales_report` ADD COLUMN `remarks` text DEFAULT NULL"); }
catch (Throwable $ignored) {}

// ── DINE IN detail rows table ──────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_dinein_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Stella',
    `report_date`   date NOT NULL,
    `cash`          decimal(12,2) DEFAULT 0.00,
    `palawan_pay`   decimal(12,2) DEFAULT 0.00,
    `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
    `unpaid_credit_name`   varchar(100) DEFAULT NULL,
    `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
    `discount`      decimal(12,2) DEFAULT 0.00,
    `gift_card`     decimal(12,2) DEFAULT 0.00,
    `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
    `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Migrate new column onto dine-in rows table safely (ignore if already exists)
try { $pdo->exec("ALTER TABLE `stella_dinein_rows` ADD COLUMN `gift_card` decimal(12,2) DEFAULT 0.00"); }
catch (Throwable $ignored) {}

// ── Sub-section detail rows table ─────────────────────────
// section: marketing_pullout | grab | expenses | late_payment |
//          advance_payment | gc_sponsorship | gc_sold |
//          paid_med | deposit_card | cash_out_interest
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_sales_detail_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Stella',
    `report_date`   date NOT NULL,
    `section`       varchar(40) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── One-time move: Unpaid Med/Corp. (CREDIT) used to live inside the Dine In table.
//    It is now its own box (section 'unpaid_med'), so copy any old Dine In unpaid entries
//    across (only for dates that have no 'unpaid_med' rows yet) and clear the old columns. ──
try {
    $old = $pdo->query("SELECT d.report_date, d.unpaid_credit_name, d.unpaid_credit_amount, d.sort_order
        FROM stella_dinein_rows d
        WHERE d.store_name='Stella' AND (d.unpaid_credit_amount<>0 OR (d.unpaid_credit_name IS NOT NULL AND d.unpaid_credit_name<>''))
          AND NOT EXISTS (SELECT 1 FROM stella_sales_detail_rows x WHERE x.store_name='Stella' AND x.report_date=d.report_date AND x.section='unpaid_med')
        ORDER BY d.report_date, d.sort_order")->fetchAll(PDO::FETCH_ASSOC);
    if ($old) {
        $insU = $pdo->prepare("INSERT INTO stella_sales_detail_rows (store_name,report_date,section,item_name,amount,sort_order) VALUES ('Stella',?,'unpaid_med',?,?,?)");
        $n = [];
        foreach ($old as $o) { $k = $o['report_date']; $n[$k] = ($n[$k] ?? -1) + 1; $insU->execute([$o['report_date'], $o['unpaid_credit_name'], (float)$o['unpaid_credit_amount'], $n[$k]]); }
        $pdo->exec("UPDATE stella_dinein_rows SET unpaid_credit_name=NULL, unpaid_credit_amount=0 WHERE store_name='Stella'");
    }
} catch (Throwable $ignored) {}


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
                VALUES ('Stella', ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([$reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM report_locks WHERE store_name='Stella' AND report_date=?")
                ->execute([$reportDate]);
        }
        logAudit($pdo, 'Stella', 'Sales Report', $lock ? 'locked' : 'unlocked', $reportDate,
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
        if (isDateLocked($pdo, 'Stella', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $NUM_COLS = ['gross_sales','service_charge','pos_z_reading','total_swipe',
            'deposit_swipe_card','late_payment_card','maya_swipe',
            'unpaid_med_credit','paid_med_card','advance_paid_appigo',
            'grab_sales','gcash','gift_card','marketing_pull_out','discount',
            'bank_transfer_cheque','cash_out_interest','gift_cert_sold_tips',
            'pcf_expenses','other_expenses','coh'];
        $vals = [];
        foreach ($NUM_COLS as $f) $vals[$f] = (float)($_POST[$f] ?? 0);
        $NUM_COLS[] = 'cash_replenishment';
        $vals['cash_replenishment'] = max(0, (float)($_POST['cash_replenishment'] ?? 0));

        // Authoritative DINE IN sums (Gift Card, PCF/Expenses, Other Expenses excluded here on purpose)
        $diSumStmt = $pdo->prepare("SELECT
                COALESCE(SUM(unpaid_credit_amount),0) AS unpaid_med_credit,
                COALESCE(SUM(discount),0)             AS discount,
                COALESCE(SUM(gift_card),0)             AS gift_card,
                COALESCE(SUM(bank_transfer_cheque),0) AS bank_transfer_cheque,
                COALESCE(SUM(palawan_pay),0)           AS gcash
            FROM stella_dinein_rows WHERE store_name='Stella' AND report_date=?");
        $diSumStmt->execute([$reportDate]);
        $diSums = $diSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['unpaid_med_credit','discount','gift_card','bank_transfer_cheque','gcash'] as $f) {
            $vals[$f] = (float)($diSums[$f] ?? 0);
        }

        // Authoritative sub-section sums
        $secSumStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM stella_sales_detail_rows WHERE store_name='Stella' AND report_date=? GROUP BY section");
        $secSumStmt->execute([$reportDate]);
        $secSums = [];
        foreach ($secSumStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $secSums[$r['section']] = (float)$r['total'];
        $vals['unpaid_med_credit']   = $secSums['unpaid_med'] ?? 0;
        $vals['grab_sales']          = $secSums['grab'] ?? 0;
        $vals['marketing_pull_out']  = $secSums['marketing_pullout'] ?? 0;
        $vals['paid_med_card']       = $secSums['paid_med'] ?? 0;
        $vals['advance_paid_appigo'] = $secSums['advance_payment'] ?? 0;
        // Gift Cert Sold (TIPS) = GC SOLD box + GIFT CERT SOLD (TIPS) box (the latter also feeds Deposit Swipe)
        $vals['gift_cert_sold_tips'] = ($secSums['gc_sold'] ?? 0) + ($secSums['gift_cert_tips'] ?? 0);
        $vals['cash_out_interest']   = $secSums['cash_out_interest'] ?? 0;

        // Total Swipe = Deposit Swipe + Late Payment + Maya Swipe + Paid Med (CARD)
        $vals['total_swipe'] = $vals['deposit_swipe_card'] + $vals['late_payment_card']
                              + $vals['maya_swipe'] + $vals['paid_med_card'];

        // Total Dine In = sum of DINE IN rows just saved for this date
        $tdiStmt = $pdo->prepare("SELECT COALESCE(SUM(cash+palawan_pay+card_swipe_qr+discount+gift_card+bank_transfer_cheque),0) AS total
                                   FROM stella_dinein_rows WHERE store_name='Stella' AND report_date=?");
        $tdiStmt->execute([$reportDate]);
        $totalDineIn = (float)$tdiStmt->fetchColumn();

        // Gross Sales = Grab + Cash out Interest + Gift Cert Sold + Advance Paid
        //             + Paid Med + Unpaid Med + Total Dine In - Service Charge
        // NOTE: G-Cash is NOT added separately — it's the Dine In "Palawan Pay"
        // column, which is already included inside Total Dine In. Adding it
        // again here double-counts it.
        $vals['gross_sales'] = $vals['grab_sales'] + $vals['cash_out_interest']
                              + $vals['gift_cert_sold_tips'] + $vals['advance_paid_appigo']
                              + $vals['paid_med_card'] + $vals['unpaid_med_credit']
                              + $totalDineIn - $vals['service_charge'];

        // POS Z Reading = Gross Sales + Marketing Pull Out
        // (the physical register tape total includes promo/comp value that Gross Sales nets back out)
        $vals['pos_z_reading'] = $vals['gross_sales'] + $vals['marketing_pull_out'];

        // Net Sales = Gross Sales - all deductions
        $netSales = $vals['gross_sales']
                  - $vals['maya_swipe']
                  - $vals['unpaid_med_credit']
                  - $vals['paid_med_card']
                  - $vals['advance_paid_appigo']
                  - $vals['grab_sales']
                  - $vals['gcash']
                  - $vals['gift_card']
                  - $vals['discount']
                  - $vals['bank_transfer_cheque']
                  - $vals['cash_out_interest']
                  - $vals['gift_cert_sold_tips']
                  - $vals['pcf_expenses']
                  - $vals['other_expenses'];
        // Cash replenishment (cash brought from the vault to cover expenses on a day when cash sales
        // were not enough): Short/Over = COH - (Net Sales + Replenishment). Set automatically when Net Sales is negative.
        if ($netSales < 0 && $vals['cash_replenishment'] <= 0) $vals['cash_replenishment'] = round(-$netSales, 2);
        if ($netSales >= 0) $vals['cash_replenishment'] = 0;
        $shortOver = $vals['coh'] - ($netSales + $vals['cash_replenishment']);

        $cashierName = trim((string)($_POST['cashier_name'] ?? ''));
        $remarks     = trim((string)($_POST['remarks'] ?? ''));
        if (function_exists('mb_substr')) $remarks = mb_substr($remarks, 0, 5000);

        $fields    = array_merge(['store_name','report_date'], $NUM_COLS, ['net_sales','short_over','saved_by','cashier_name','remarks']);
        $data      = array_merge(['Stella', $reportDate], array_values($vals), [$netSales, $shortOver, $user['name'], $cashierName, $remarks]);
        $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", array_merge($NUM_COLS, ['net_sales','short_over','saved_by','cashier_name','remarks'])));
        $sql = "INSERT INTO stella_sales_report (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ")
                VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")
                ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute($data);
        logAudit($pdo, 'Stella', 'Sales Report', 'updated', $reportDate, 'Saved daily summary totals',
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
        if (isDateLocked($pdo, 'Stella', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM stella_dinein_rows WHERE store_name='Stella' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO stella_dinein_rows (store_name,report_date,cash,palawan_pay,card_swipe_qr,unpaid_credit_name,unpaid_credit_amount,discount,gift_card,bank_transfer_cheque,cancelled_transactions,sort_order) VALUES ('Stella',?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, (float)($r['cash']??0), (float)($r['palawan_pay']??0),
                (float)($r['card_swipe_qr']??0), $r['unpaid_credit_name']??null,
                (float)($r['unpaid_credit_amount']??0), (float)($r['discount']??0),
                (float)($r['gift_card']??0),
                (float)($r['bank_transfer_cheque']??0), (float)($r['cancelled_transactions']??0), $i]);
        }
        logAudit($pdo, 'Stella', 'Sales Report', 'updated', $reportDate,
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
        if (isDateLocked($pdo, 'Stella', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM stella_sales_detail_rows WHERE store_name='Stella' AND report_date=? AND section=?")->execute([$reportDate,$section]);
        $ins = $pdo->prepare("INSERT INTO stella_sales_detail_rows (store_name,report_date,section,item_name,amount,sort_order) VALUES ('Stella',?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $section, $r['name']??null, (float)($r['amount']??0), $i]);
        }
        logAudit($pdo, 'Stella', 'Sales Report', 'updated', $reportDate,
            'Updated "' . $section . '" rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handle Excel (.xlsx) download ─────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM stella_sales_report WHERE store_name='Stella' AND report_date=?");
    $stmt->execute([$reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');

    $filename = 'Stella_SalesReport_'.$reportDate.'.xlsx';

    $xlsx = new SimpleXlsxWriter(salesReportAccentColor('Stella'));
    $xlsx->title('Stella Branch — Daily Sales Report');
    $xlsx->subtitle(date('F j, Y', strtotime($reportDate)) . ' · Cashier: ' . (string)($r['cashier_name'] ?? '—'));
    $xlsx->blank();

    // — DINE IN —
    $xlsx->sectionRow('DINE IN');
    $xlsx->header(['Cash','Palawan Pay','Card/Swipe/QR','Discount','Gift Cert.','Bank Transfer/Cheque','Cancelled Transactions']);
    $diExpStmt = $pdo->prepare("SELECT * FROM stella_dinein_rows WHERE store_name='Stella' AND report_date=? ORDER BY sort_order ASC");
    $diExpStmt->execute([$reportDate]);
    foreach ($diExpStmt->fetchAll(PDO::FETCH_ASSOC) as $dr) {
        $xlsx->row([
            number_format((float)$dr['cash'],2,'.',''), number_format((float)$dr['palawan_pay'],2,'.',''),
            number_format((float)$dr['card_swipe_qr'],2,'.',''), number_format((float)$dr['discount'],2,'.',''),
            number_format((float)($dr['gift_card'] ?? 0),2,'.',''), number_format((float)$dr['bank_transfer_cheque'],2,'.',''),
            number_format((float)$dr['cancelled_transactions'],2,'.',''),
        ]);
    }
    $xlsx->blank();

    // — Sub-sections (Marketing Pullout, GRAB, Expenses, Late Payment,
    //   Advance Payment, GC Sponsorship, GC Sold, Paid Med/Corp., Deposit
    //   (Card), Gift Cert Sold (TIPS), Cash out Interest) —
    $stellaSubSections = [
        'unpaid_med'        => 'UNPAID MED/CORP. (CREDIT)',
        'marketing_pullout' => 'MARKETING PULLOUT',
        'grab'              => 'GRAB',
        'expenses'          => 'EXPENSES',
        'late_payment'      => 'LATE PAYMENT',
        'advance_payment'   => 'ADVANCE PAYMENT',
        'gc_sponsorship'    => 'GC SPONSORSHIP',
        'gc_sold'           => 'GC SOLD',
        'paid_med'          => 'PAID MED/CORP. (CARD)',
        'deposit_card'      => 'DEPOSIT (CARD)',
        'gift_cert_tips'    => 'GIFT CERT SOLD (TIPS)',
        'cash_out_interest' => 'CASH OUT INTEREST (BOOKY)',
    ];
    $expDetailStmt = $pdo->prepare("SELECT * FROM stella_sales_detail_rows WHERE store_name='Stella' AND report_date=? AND section=? ORDER BY sort_order ASC");
    foreach ($stellaSubSections as $sec => $label) {
        $xlsx->sectionRow($label);
        $xlsx->header(['Particular','Amount']);
        $expDetailStmt->execute([$reportDate, $sec]);
        foreach ($expDetailStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
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
    $xlsx->row(['Total Swipe', $g('total_swipe')]);
    $xlsx->row(['Deposit Swipe (Card)', $g('deposit_swipe_card')]);
    $xlsx->row(['Late Payment (Card)', $g('late_payment_card')]);
    $xlsx->row(['Sales of the day Swipe (MAYA)', $g('maya_swipe')]);
    $xlsx->row(['Unpaid Med (Credit)', $g('unpaid_med_credit')]);
    $xlsx->row(['Paid Med/Corp. (Card)', $g('paid_med_card')]);
    $xlsx->row(['Advance Paid (Appigo)', $g('advance_paid_appigo')]);
    $xlsx->row(['Grab Sales', $g('grab_sales')]);
    $xlsx->row(['G-Cash', $g('gcash')]);
    $xlsx->row(['Gift Card', $g('gift_card')]);
    $xlsx->row(['Marketing Pull Out', $g('marketing_pull_out')]);
    $xlsx->row(['Discount', $g('discount')]);
    $xlsx->row(['Bank Transfer/Cheque', $g('bank_transfer_cheque')]);
    $xlsx->row(['Cash out Interest (Booky)', $g('cash_out_interest')]);
    $xlsx->row(['Gift Cert. Sold (Tips)', $g('gift_cert_sold_tips')]);
    $xlsx->row(['PCF/Expenses', $g('pcf_expenses')]);
    $xlsx->row(['Other Expenses', $g('other_expenses')]);
    $xlsx->totalRow(['Net Sales', $g('net_sales')]);
    $xlsx->totalRow(['COH (Cash on Hand)', $g('coh')]);
    $xlsx->row(['Cash Replenishment', $g('cash_replenishment')]);
    $xlsx->totalRow(['Short/Over', $g('short_over')]);
    if (trim((string)($r['remarks'] ?? '')) !== '') $xlsx->row(['Remarks', (string)$r['remarks']]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([12, 12, 14, 18, 18, 10, 10, 18, 18]);
    $xlsx->output($filename);
}

// ── Fetch data for display ─────────────────────────────────
$fDate = $_GET['date'] ?? date('Y-m-d');
$locked = isDateLocked($pdo, 'Stella', $fDate);
// Lock stops Cashier edits only — OIC (Accounting) and Manager can still edit a locked date.
$restrictEdit = $locked && isCashier();
$stmt  = $pdo->prepare("SELECT * FROM stella_sales_report WHERE store_name='Stella' AND report_date=?");
$stmt->execute([$fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$vs  = fn($k) => (string)($row[$k] ?? '');
$fmt = fn($n)  => number_format((float)$n, 2);

// Fetch DINE IN rows
$diStmt = $pdo->prepare("SELECT * FROM stella_dinein_rows WHERE store_name='Stella' AND report_date=? ORDER BY sort_order ASC");
$diStmt->execute([$fDate]);
$dineinRows = $diStmt->fetchAll(PDO::FETCH_ASSOC);
$diSum = fn($col) => array_sum(array_column($dineinRows, $col));

// Fetch sub-section detail rows
$detailStmt = $pdo->prepare("SELECT * FROM stella_sales_detail_rows WHERE store_name='Stella' AND report_date=? ORDER BY section, sort_order ASC");
$detailStmt->execute([$fDate]);
$allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
$details = [];
foreach ($allDetails as $d) $details[$d['section']][] = $d;
$secSum = fn($sec) => array_sum(array_column($details[$sec] ?? [], 'amount'));

// Auto-calculated pink-section values (Gift Card and Other Expenses stay manual)
$unpaidMedCalc     = $secSum('unpaid_med');
$discountCalc      = $diSum('discount');
$giftCardCalc      = $diSum('gift_card');
$bankTransferCalc  = $diSum('bank_transfer_cheque');
$gcashCalc         = $diSum('palawan_pay');
$grabCalc          = $secSum('grab');
$marketingCalc     = $secSum('marketing_pullout');
$pcfCalc           = $secSum('expenses');
$paidMedCalc       = $secSum('paid_med');
$depositCardCalc   = $secSum('deposit_card');
$cashOutCalc       = $secSum('cash_out_interest');
$advancePaidCalc   = $secSum('advance_payment');
$giftCertSoldCalc  = $secSum('gc_sold') + $secSum('gift_cert_tips');

$totalDineInCalc = $diSum('cash') + $diSum('palawan_pay') + $diSum('card_swipe_qr')
                 + $diSum('discount') + $diSum('gift_card') + $diSum('bank_transfer_cheque');

// Gross Sales = Grab + Cash out Interest + Gift Cert Sold + Advance Paid
//             + Paid Med + Unpaid Med + Total Dine In - Service Charge
// NOTE: G-Cash is NOT added separately — it's the Dine In "Palawan Pay"
// column, which is already included inside Total Dine In.
$grossSalesCalc = $grabCalc + $cashOutCalc + $giftCertSoldCalc
                 + $advancePaidCalc + $paidMedCalc + $unpaidMedCalc
                 + $totalDineInCalc - $v('service_charge');

// POS Z Reading = Gross Sales + Marketing Pull Out
$posZReadingCalc = $grossSalesCalc + $marketingCalc;

$pageTitle  = 'Stella Sales Report';
$activePage = 'stella_sales_report';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

<style id="sr-remarks-css">
.sr-remarks { flex:0 0 auto; padding:10px 12px 12px; background:#f6f8f6; border-top:1px solid #d5ddd7; }
.sr-remarks label { display:block; font-family:var(--font-m); font-size:.72rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#2c3e33; margin-bottom:6px; }
.sr-remarks textarea { width:100%; box-sizing:border-box; min-height:64px; resize:vertical; padding:9px 12px; font:600 .84rem/1.4 var(--font-m); color:#1d2a22; background:#fff; border:1px solid #c5d0c8; border-radius:9px; outline:none; }
.sr-remarks textarea:focus { border-color:#2f855a; box-shadow:0 0 0 3px rgba(47,133,90,.15); }
body.page-stella_sales_report.sr-soft .sr-remarks, body.page-stella_sales_report.sr-dark .sr-remarks { background:var(--sr-card); border-top-color:var(--sr-line); }
body.page-stella_sales_report.sr-soft .sr-remarks label, body.page-stella_sales_report.sr-dark .sr-remarks label { color:var(--sr-text); }
body.page-stella_sales_report.sr-soft .sr-remarks textarea, body.page-stella_sales_report.sr-dark .sr-remarks textarea { background:var(--sr-input); color:var(--sr-text); border-color:var(--sr-input-bd); }
</style>
<style id="sr2-css">
/* ═══ Sales Report — new design (v2) ═══ light + dark. Design only: no formulas / ids / saving changed. ═══ */
body.page-stella_sales_report.sr2 { --f:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif; --m:ui-monospace,'JetBrains Mono',Menlo,Consolas,monospace; font-family:var(--f); }
body.page-stella_sales_report.sr2-light { --bg:#f3f4f6; --card:#fff; --ink:#0f172a; --mut:#64748b; --line:#e5e7eb; --acc:#0f766e; --accbg:#ccfbf1; --neg:#be123c; --negbg:#ffe4e6; --inp:#f8fafc; --hero1:#0f172a; --hero2:#134e4a; --ring:rgba(15,118,110,.22); }
body.page-stella_sales_report.sr2-dark  { --bg:#0b1210; --card:#131d1a; --ink:#e6f2ee; --mut:#8ba39b; --line:#22332e; --acc:#34d399; --accbg:#0f2f27; --neg:#fb7185; --negbg:#3a1520; --inp:#0f1815; --hero1:#071311; --hero2:#0f3a31; --ring:rgba(52,211,153,.28); }
body.page-stella_sales_report.sr2 { background:var(--bg) !important; color:var(--ink); }
body.page-stella_sales_report.sr2 .main-wrapper { background:var(--bg) !important; }
body.page-stella_sales_report.sr2 .topbar { background:var(--card) !important; border-color:var(--line) !important; }
body.page-stella_sales_report.sr2 .topbar * { color:var(--ink); }
body.page-stella_sales_report.sr2 .sr-main { font-family:var(--f); }

/* ── title ── */
body.page-stella_sales_report.sr2 .sr-header-card { background:none !important; box-shadow:none !important; border:0 !important; padding:0 2px !important; margin:0 0 12px !important; border-radius:0 !important; display:block !important; }
body.page-stella_sales_report.sr2 .sr-header-card::before { display:none !important; }
body.page-stella_sales_report.sr2 .sr-header-card > span { display:none !important; }
body.page-stella_sales_report.sr2 .sr-header-card .eyebrow { color:var(--mut) !important; font-family:var(--f) !important; font-size:.66rem !important; letter-spacing:.14em !important; font-weight:700; }
body.page-stella_sales_report.sr2 .sr-header-card .title { color:var(--ink) !important; font-size:1.55rem !important; font-weight:800 !important; letter-spacing:-.025em !important; }
body.page-stella_sales_report.sr2 .sr-header-card .subtitle { color:var(--mut) !important; font-family:var(--f) !important; font-size:.74rem !important; }

/* ── top bar: date stepper · cashier · status · lock · theme · excel ── */
body.page-stella_sales_report.sr2 .sr2-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0 0 18px; }
body.page-stella_sales_report.sr2 .sr2-bar .sr-controls, body.page-stella_sales_report.sr2 .sr2-bar .sr-controls > form { display:contents !important; }
body.page-stella_sales_report.sr2 .sr2-step { order:1; width:38px; height:40px; border:1px solid var(--line); background:var(--card); color:var(--ink); border-radius:12px; font-size:1.1rem; font-weight:700; cursor:pointer; line-height:1; }
body.page-stella_sales_report.sr2 .sr2-step:hover { border-color:var(--acc); color:var(--acc); }
body.page-stella_sales_report.sr2 .sr2-bar input[type=date] { order:2; height:40px; border:1px solid var(--line) !important; background:var(--card) !important; color:var(--ink) !important; border-radius:12px !important; padding:0 12px !important; font:700 .86rem var(--f) !important; width:auto !important; }
body.page-stella_sales_report.sr2 .sr2-step.next { order:3; }
body.page-stella_sales_report.sr2 .sr2-bar .sr-cashier-bar { order:4; flex:1 1 240px; max-width:340px; margin:0 !important; padding:0 12px !important; height:40px; background:var(--card) !important; border:1px solid var(--line) !important; border-radius:12px !important; box-shadow:none !important; gap:8px !important; }
body.page-stella_sales_report.sr2 .sr-cashier-bar label { font:700 .62rem var(--f) !important; letter-spacing:.12em; text-transform:uppercase; color:var(--mut) !important; }
body.page-stella_sales_report.sr2 .sr-cashier-bar input { background:transparent !important; border:0 !important; box-shadow:none !important; color:var(--ink) !important; font:600 .88rem var(--f) !important; padding:0 !important; height:100%; }
body.page-stella_sales_report.sr2 .sr-cashier-bar:focus-within { border-color:var(--acc) !important; box-shadow:0 0 0 3px var(--ring) !important; }
body.page-stella_sales_report.sr2 .sr2-bar .sr-controls > form > span:not(#saveStatus), body.page-stella_sales_report.sr2 .sr2-bar .sr-controls span.sr2-chip { order:5; margin-left:auto; font:700 .74rem var(--f) !important; color:var(--acc) !important; background:var(--accbg); padding:8px 12px !important; border-radius:999px; }
body.page-stella_sales_report.sr2 .sr2-bar #saveStatus { order:6; font:700 .74rem var(--f) !important; color:var(--mut) !important; }
body.page-stella_sales_report.sr2 .sr2-bar #saveStatus:empty { display:none; }
body.page-stella_sales_report.sr2 .sr2-bar .lock-wrap, body.page-stella_sales_report.sr2 .sr2-bar .lock-badge-static { order:7; }
body.page-stella_sales_report.sr2 .sr2-bar #srThemeBtn { order:8; }
body.page-stella_sales_report.sr2 .sr2-bar a.btn { order:9; }
body.page-stella_sales_report.sr2 .sr2-bar a.btn, body.page-stella_sales_report.sr2 #srThemeBtn, body.page-stella_sales_report.sr2 .btn-lock { height:40px; display:inline-flex !important; align-items:center; background:var(--card) !important; color:var(--ink) !important; border:1px solid var(--line) !important; border-radius:12px !important; padding:0 14px !important; font:700 .78rem var(--f) !important; box-shadow:none !important; text-decoration:none; cursor:pointer; }
body.page-stella_sales_report.sr2 .sr2-bar a.btn:hover, body.page-stella_sales_report.sr2 #srThemeBtn:hover { border-color:var(--acc) !important; }

/* ── section headings ── */
body.page-stella_sales_report.sr2 .sr2-eyebrow { display:flex; align-items:center; gap:10px; font:800 .68rem var(--f); letter-spacing:.14em; text-transform:uppercase; color:var(--mut); margin:4px 2px 10px; }
body.page-stella_sales_report.sr2 .sr2-eyebrow::after { content:''; flex:1; height:1px; background:var(--line); }
body.page-stella_sales_report.sr2 .sr2-eyebrow i { font-style:normal; font-weight:500; letter-spacing:.02em; text-transform:none; opacity:.8; }

/* ── entry cards ── */
body.page-stella_sales_report.sr2 .sr-section { background:transparent !important; border:0 !important; box-shadow:none !important; border-radius:0 !important; margin:0 0 16px !important; padding:0 !important; overflow:visible !important; }
body.page-stella_sales_report.sr2 .sr-section:hover { box-shadow:none !important; }
body.page-stella_sales_report.sr2 .sub-grid { display:grid !important; grid-template-columns:repeat(auto-fill,minmax(285px,1fr)) !important; gap:12px !important; border:0 !important; }
body.page-stella_sales_report.sr2 .sub-col { display:grid !important; grid-template-columns:minmax(0,1fr) auto; grid-template-areas:"title total" "rows rows" "add add"; align-content:start; background:var(--card) !important; border:1px solid var(--line) !important; border-radius:14px !important; padding:12px 14px 6px !important; transition:border-color .15s, box-shadow .15s; }
body.page-stella_sales_report.sr2 .sub-col:focus-within { border-color:var(--acc) !important; box-shadow:0 0 0 3px var(--ring); }
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col:nth-child(n) .sub-col-title { grid-area:title; background:none !important; color:var(--ink) !important; padding:0 !important; margin:0 0 8px !important; border:0 !important; font:800 .72rem var(--f) !important; letter-spacing:.05em !important; text-transform:uppercase; display:flex !important; align-items:center; justify-content:flex-start !important; text-align:left !important; gap:8px; align-self:center; box-shadow:none !important; border-radius:0 !important; }
body.page-stella_sales_report.sr2 .sub-col-title::before { content:''; width:9px; height:9px; border-radius:50%; background:var(--acc); flex:none; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-col-title::before { background:var(--neg); }
body.page-stella_sales_report.sr2 .sub-col-hdr { display:none !important; }
body.page-stella_sales_report.sr2 .sub-rows { grid-area:rows; }
body.page-stella_sales_report.sr2 .sub-row { display:grid !important; grid-template-columns:minmax(0,1fr) 98px 22px !important; gap:6px; align-items:center; border:0 !important; border-top:1px solid var(--line) !important; background:none !important; padding:3px 0 !important; }
body.page-stella_sales_report.sr2 .sub-row:hover { background:none !important; }
body.page-stella_sales_report.sr2 .sub-inp { background:transparent !important; border:1px solid transparent !important; border-radius:8px !important; color:var(--ink) !important; font:600 .8rem var(--f) !important; padding:6px 7px !important; box-shadow:none !important; }
body.page-stella_sales_report.sr2 .sub-inp.num { text-align:right; font-family:var(--m) !important; }
body.page-stella_sales_report.sr2 .sub-inp::placeholder { color:var(--mut); opacity:.55; font-weight:500; }
body.page-stella_sales_report.sr2 .sub-inp:hover { background:var(--inp) !important; }
body.page-stella_sales_report.sr2 .sub-inp:focus { background:var(--inp) !important; border-color:var(--acc) !important; box-shadow:0 0 0 3px var(--ring) !important; }
body.page-stella_sales_report.sr2 .sub-col .btn-del-row { opacity:0; width:22px; height:22px; padding:0 !important; border-radius:50% !important; background:var(--negbg) !important; color:var(--neg) !important; font-size:.6rem; margin:0 !important; transition:opacity .15s; }
body.page-stella_sales_report.sr2 .sub-row:hover .btn-del-row, body.page-stella_sales_report.sr2 .sub-row:focus-within .btn-del-row { opacity:1; }
body.page-stella_sales_report.sr2 .sub-col .btn-add-row { grid-area:add; font-size:0 !important; background:none !important; color:var(--mut) !important; border:0 !important; border-top:1px dashed var(--line) !important; border-radius:0 !important; box-shadow:none !important; margin:2px 0 0 !important; padding:9px 2px 8px !important; text-align:left; width:100%; transform:none !important; }
body.page-stella_sales_report.sr2 .sub-col .btn-add-row::before { content:'＋ add a line…'; font:600 .74rem var(--f); }
body.page-stella_sales_report.sr2 .sub-col .btn-add-row:hover { color:var(--acc) !important; }
body.page-stella_sales_report.sr2 .sub-footer { display:contents !important; }
body.page-stella_sales_report.sr2 .sub-footer span { border:0 !important; padding:0 !important; background:none !important; }
body.page-stella_sales_report.sr2 .sub-footer span:first-child { display:none; }
body.page-stella_sales_report.sr2 .sub-footer span:last-child { grid-area:total; align-self:center; margin:0 0 8px; font:800 .78rem var(--m) !important; color:var(--acc) !important; background:var(--accbg) !important; padding:3px 9px !important; border-radius:8px; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { color:var(--neg) !important; background:var(--negbg) !important; }

/* ── dine-in ── */
body.page-stella_sales_report.sr2 .sr-section.sr2-dine { background:var(--card) !important; border:1px solid var(--line) !important; border-radius:14px !important; overflow:hidden !important; }
body.page-stella_sales_report.sr2 .sr2-dine .sr-section-title { display:none !important; background:none !important; color:var(--ink) !important; font:800 .8rem var(--f) !important; letter-spacing:.12em !important; padding:13px 16px !important; }
body.page-stella_sales_report.sr2 .di-table { border-collapse:separate !important; border-spacing:0; width:100% !important; min-width:0 !important; table-layout:fixed; }
body.page-stella_sales_report.sr2 .di-table th:last-child, body.page-stella_sales_report.sr2 .di-table td:last-child { width:42px; }
body.page-stella_sales_report.sr2 .di-table thead tr:nth-child(2) { display:none; }
body.page-stella_sales_report.sr2 .di-table th { background:var(--inp) !important; color:var(--mut) !important; font:800 .6rem var(--f) !important; letter-spacing:.09em; text-transform:uppercase; padding:9px 6px !important; white-space:normal !important; overflow-wrap:anywhere; line-height:1.25; font-size:.56rem !important; border:0 !important; border-top:1px solid var(--line) !important; border-bottom:1px solid var(--line) !important; text-align:right !important; }
body.page-stella_sales_report.sr2 .di-table td { background:transparent !important; border:0 !important; border-bottom:1px solid var(--line) !important; padding:3px 6px !important; color:var(--ink) !important; }
body.page-stella_sales_report.sr2 .di-table tbody tr:hover td { background:var(--inp) !important; }
body.page-stella_sales_report.sr2 .di-inp { background:var(--inp) !important; border:1px solid var(--line) !important;  color:var(--ink) !important; font:600 .82rem var(--m) !important; text-align:right; border-radius:8px; padding:7px 8px !important; width:100% !important; box-shadow:none !important; }
body.page-stella_sales_report.sr2 .di-inp:hover { border-color:var(--mut) !important; }
body.page-stella_sales_report.sr2 .di-inp:focus { background:var(--card) !important; border-color:var(--acc) !important; box-shadow:0 0 0 3px var(--ring) !important; }
body.page-stella_sales_report.sr2 .di-table tfoot td { background:var(--accbg) !important; color:var(--acc) !important; font:800 .82rem var(--m) !important; text-align:right !important; padding:10px 12px !important; border:0 !important; }
body.page-stella_sales_report.sr2 .di-table tfoot tr:last-child td { background:var(--hero2) !important; background-image:none !important; color:#fff !important; font-size:.95rem !important; }
body.page-stella_sales_report.sr2 .di-table tfoot td.grand-total { text-align:right !important; }
body.page-stella_sales_report.sr2 .di-table tfoot td.total-label { text-align:left !important; letter-spacing:.14em; font-family:var(--f) !important; }
body.page-stella_sales_report.sr2 .di-table .btn-del-row { background:var(--negbg) !important; color:var(--neg) !important; border-radius:50% !important; width:22px; height:22px; padding:0 !important; opacity:.35; }
body.page-stella_sales_report.sr2 .di-table tr:hover .btn-del-row { opacity:1; }
body.page-stella_sales_report.sr2 .sr2-dine > .btn-add-row { display:block; width:calc(100% - 24px); margin:10px 12px 12px !important; background:none !important; color:var(--mut) !important; border:1px dashed var(--line) !important; border-radius:10px !important; padding:9px !important; font:700 .74rem var(--f) !important; box-shadow:none !important; }
body.page-stella_sales_report.sr2 .sr2-dine > .btn-add-row:hover { color:var(--acc) !important; border-color:var(--acc) !important; }

/* ── layout: summary rail on the LEFT, always fits the screen (no scrolling) | main ── */
body.page-stella_sales_report.sr2 .sr-split { display:grid !important; grid-template-columns:392px minmax(0,1fr); gap:18px; align-items:start; }
body.page-stella_sales_report.sr2 .sr-split .sr-main { order:1 !important; min-width:0; }
body.page-stella_sales_report.sr2 .sr-split .sr-wrap { order:-1 !important; flex:none !important; width:auto !important; max-width:none !important; margin:0 !important; position:sticky !important; top:12px !important; height:calc(100vh - 104px); max-height:none !important; overflow:hidden !important; border-radius:18px !important; container-type:size; }
@media (max-width:1180px) { body.page-stella_sales_report.sr2 .sr-split { grid-template-columns:1fr; } body.page-stella_sales_report.sr2 .sr-split .sr-wrap { order:0 !important; position:static !important; height:auto !important; container-type:normal; overflow:visible !important; } }

/* ── summary rail (sizes scale with the rail's height so every row is visible at once) ── */
body.page-stella_sales_report.sr2 .sr-split .sr-card { height:100% !important; display:flex !important; flex-direction:column; gap:.8cqh; background:transparent !important; border:0 !important; box-shadow:none !important; overflow:hidden !important; border-radius:0 !important; }
body.page-stella_sales_report.sr2 .sr-title-row { display:none !important; }
body.page-stella_sales_report.sr2 .sr2-hero { flex:0 0 auto; background:linear-gradient(160deg,var(--hero1),var(--hero2)); color:#e2e8f0; border-radius:14px; padding:1.5cqh 14px 1.6cqh; box-shadow:0 10px 26px rgba(15,23,42,.2); }
body.page-stella_sales_report.sr2 .sr2-hero .sr2-cap { display:flex; justify-content:flex-start; gap:8px; font:800 clamp(.5rem,1.2cqh,.62rem) var(--f); letter-spacing:.14em; text-transform:uppercase; color:#7dd3c8; margin-bottom:.4cqh; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row { min-height:0 !important; flex:none !important; background:none !important; border:0 !important; padding:0 !important; overflow:visible !important; gap:8px; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-label { text-align:left !important; padding:0 !important; color:#cbd5e1 !important; font:600 clamp(.62rem,1.55cqh,.78rem) var(--f) !important; font-style:normal !important; flex:1; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-label span { display:inline !important; font-size:.6rem !important; opacity:.7; color:#cbd5e1 !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-value-wrap { width:auto !important; flex:none; display:block !important; align-self:auto !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-note { display:none; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.net { display:block !important; margin:0 0 .8cqh !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.net .sr-label { display:block; flex:none; font:700 clamp(.56rem,1.3cqh,.7rem) var(--f) !important; color:#94a3b8 !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.net .sr-value-wrap { width:100% !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.net .sr-input { width:100%; background:none !important; border:0 !important; padding:0 !important; height:auto !important; max-height:none !important; min-height:0 !important; text-align:left !important; color:#fff !important; -webkit-text-fill-color:#fff !important; font:800 clamp(1.3rem,4.6cqh,2.05rem) var(--m) !important; letter-spacing:-.02em; line-height:1.1; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.coh { display:flex !important; align-items:center; margin:0 0 .7cqh !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.coh .sr-input { width:128px; height:clamp(24px,3.7cqh,34px) !important; max-height:none !important; background:rgba(255,255,255,.1) !important; border:1px solid rgba(255,255,255,.22) !important; border-radius:9px !important; color:#fff !important; -webkit-text-fill-color:#fff !important; text-align:right !important; padding:0 10px !important; font:700 clamp(.7rem,1.8cqh,.9rem) var(--m) !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.coh .sr-input:focus { border-color:#7dd3c8 !important; box-shadow:0 0 0 3px rgba(125,211,200,.25) !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.coh .sr-input::placeholder { color:rgba(255,255,255,.4); }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short { display:flex !important; align-items:center; padding:clamp(5px,1.1cqh,10px) 12px !important; border-radius:11px !important; background:#065f46 !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short[data-state=short] { background:#9f1239 !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short[data-state=over] { background:#92400e !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-label { color:#fff !important; font:800 clamp(.54rem,1.3cqh,.66rem) var(--f) !important; letter-spacing:.12em; text-transform:uppercase; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-value-wrap { display:flex !important; align-items:center; gap:10px; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-input { width:110px; background:none !important; border:0 !important; padding:0 !important; height:auto !important; max-height:none !important; text-align:right !important; color:#fff !important; -webkit-text-fill-color:#fff !important; font:800 clamp(.85rem,2.4cqh,1.15rem) var(--m) !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-value-wrap::after { content:'✓ Tally'; font:800 .72rem var(--f); color:#d1fae5; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short[data-state=short] .sr-value-wrap::after { content:'Short'; color:#ffe4e6; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short[data-state=over] .sr-value-wrap::after { content:'Over'; color:#fef3c7; }

body.page-stella_sales_report.sr2 .sr2-g { flex:var(--n,4) 1 0; min-height:0; display:flex; flex-direction:column; background:var(--card); border:1px solid var(--line); border-radius:12px; margin:0; overflow:hidden; }
body.page-stella_sales_report.sr2 .sr2-gh { flex:0 0 auto; display:flex; align-items:center; gap:8px; padding:clamp(3px,.8cqh,8px) 12px; background:var(--inp); border-bottom:1px solid var(--line); font:800 clamp(.5rem,1.15cqh,.62rem) var(--f); letter-spacing:.12em; text-transform:uppercase; color:var(--mut); }
body.page-stella_sales_report.sr2 .sr2-gh span { margin-left:auto; font:800 clamp(.58rem,1.4cqh,.74rem) var(--m); letter-spacing:0; text-transform:none; color:var(--acc); }
body.page-stella_sales_report.sr2 .sr2-gh.neg span { color:var(--neg); }
body.page-stella_sales_report.sr2 .sr2-g .sr-row { display:flex !important; align-items:center !important; flex:1 1 0 !important; min-height:0 !important; padding:0 12px !important; gap:10px !important; background:transparent !important; border:0 !important; border-bottom:1px solid var(--line) !important; overflow:hidden !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-row:last-child { border-bottom:0 !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-label { text-align:left !important; padding:0 !important; color:var(--ink) !important; font:600 clamp(.58rem,1.55cqh,.8rem)/1.1 var(--f) !important; font-style:normal !important; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
body.page-stella_sales_report.sr2 .sr2-g .sr-label span { display:none !important; }
@container (min-height: 900px) { body.page-stella_sales_report.sr2 .sr2-g .sr-label span { display:inline !important; font:500 .62rem var(--f) !important; color:var(--mut) !important; margin-left:4px; } }
body.page-stella_sales_report.sr2 .sr2-g .sr-value-wrap { width:auto !important; flex:none; display:flex !important; align-items:center; align-self:stretch !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-input.readout { width:118px; background:transparent !important; border:0 !important; box-shadow:none !important; padding:0 !important; height:auto !important; max-height:none !important; min-height:0 !important; text-align:right !important; color:var(--ink) !important; -webkit-text-fill-color:var(--ink) !important; font:700 clamp(.6rem,1.6cqh,.85rem) var(--m) !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-row.pink-val .sr-input.readout { color:var(--neg) !important; -webkit-text-fill-color:var(--neg) !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-input:not(.readout) { width:116px; height:min(82%,30px) !important; max-height:none !important; min-height:16px !important; background:var(--inp) !important; border:1.5px solid var(--acc) !important; border-radius:8px !important; padding:0 10px !important; text-align:right !important; color:var(--ink) !important; -webkit-text-fill-color:var(--ink) !important; font:700 clamp(.62rem,1.6cqh,.85rem) var(--m) !important; box-shadow:none !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-input:not(.readout):focus { box-shadow:0 0 0 3px var(--ring) !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-note { display:none; }
body.page-stella_sales_report.sr2 .sr-split .sr-wrap .sr2-rem { flex:2.2 1 0; min-height:44px; display:flex; flex-direction:column; background:var(--card); border:1px solid var(--line); border-radius:12px; padding:.8cqh 12px 1cqh; margin:0; }
body.page-stella_sales_report.sr2 .sr2-rem label { flex:none; color:var(--mut) !important; font:800 clamp(.5rem,1.15cqh,.62rem) var(--f) !important; letter-spacing:.12em; text-transform:uppercase; margin:0 0 .5cqh !important; }
body.page-stella_sales_report.sr2 .sr2-rem textarea { flex:1; min-height:0; resize:none !important; background:var(--inp) !important; color:var(--ink) !important; border:1px solid var(--line) !important; border-radius:9px !important; font:500 clamp(.6rem,1.5cqh,.8rem)/1.35 var(--f) !important; padding:6px 9px !important; width:100%; box-sizing:border-box; }
body.page-stella_sales_report.sr2 .sr2-rem textarea:focus { border-color:var(--acc) !important; box-shadow:0 0 0 3px var(--ring) !important; }
@media (max-width:1180px) {
  body.page-stella_sales_report.sr2 .sr-split .sr-card { height:auto !important; }
  body.page-stella_sales_report.sr2 .sr2-g { flex:none; } body.page-stella_sales_report.sr2 .sr2-g .sr-row { flex:none !important; min-height:34px !important; }
  body.page-stella_sales_report.sr2 .sr2-rem { min-height:110px; }
}
body.page-stella_sales_report.sr2 .locked-banner { border-radius:12px; }
body.page-stella_sales_report.sr2 #replenRow[style*="display: none"], body.page-stella_sales_report.sr2 #replenRow[style*="display:none"] { display:none !important; }

/* ── entry boxes: coloured header band (no dot), Excel-style Name | Amount row ── */
body.page-stella_sales_report.sr2 .sub-col { grid-template-rows:40px auto auto auto; grid-template-areas:"title total" "hdr hdr" "rows rows" "add add"; padding:0 !important; overflow:hidden; background:linear-gradient(var(--acc) 0 40px, var(--card) 40px) !important; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out { background:linear-gradient(var(--neg) 0 40px, var(--card) 40px) !important; }
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col:nth-child(n) .sub-col-title { color:#fff !important; margin:0 !important; padding:0 14px !important; justify-content:center !important; text-align:center !important; font-size:.8rem !important; letter-spacing:.08em !important; }
body.page-stella_sales_report.sr2 .sub-col-title::before, body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-col-title::before { display:none !important; content:none !important; }
body.page-stella_sales_report.sr2 .sub-col-hdr { grid-area:hdr; display:grid !important; grid-template-columns:minmax(0,1fr) 98px 22px; gap:6px; padding:7px 12px !important; background:var(--inp) !important; border-bottom:1px solid var(--line) !important; border-top:0 !important; }
body.page-stella_sales_report.sr2 .sub-col-hdr span { font:800 .62rem var(--f) !important; letter-spacing:.1em; text-transform:uppercase; color:var(--mut) !important; border:0 !important; padding:0 !important; background:none !important; text-align:left; }
body.page-stella_sales_report.sr2 .sub-col-hdr span:nth-child(2) { text-align:right; padding-right:8px !important; }
body.page-stella_sales_report.sr2 .sub-rows { padding:2px 12px 0; }
body.page-stella_sales_report.sr2 .sub-row:first-child { border-top:0 !important; }
body.page-stella_sales_report.sr2 .sub-col .btn-add-row { margin:2px 12px 0 !important; padding:9px 2px 8px !important; }
body.page-stella_sales_report.sr2 .sub-footer span:last-child { margin:0 12px 0 0 !important; align-self:center; background:rgba(255,255,255,.22) !important; color:#fff !important; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { background:rgba(255,255,255,.22) !important; color:#fff !important; }
body.page-stella_sales_report.sr2 .sub-col:focus-within { box-shadow:0 0 0 3px var(--ring); }

/* ── Add Row buttons: clearly visible ── */
body.page-stella_sales_report.sr2 .sr2-dine > .btn-add-row { display:flex !important; align-items:center; justify-content:center; width:auto !important; margin:12px 14px 14px auto !important; padding:9px 22px !important; background:var(--acc) !important; color:#fff !important; border:0 !important; border-radius:10px !important; font:800 .8rem var(--f) !important; letter-spacing:.04em; box-shadow:0 2px 6px rgba(0,0,0,.18) !important; cursor:pointer; }
body.page-stella_sales_report.sr2 .sr2-dine > .btn-add-row:hover { filter:brightness(1.1); color:#fff !important; border:0 !important; }
body.page-stella_sales_report.sr2 .di-table th { letter-spacing:.03em !important; font-size:.5rem !important; overflow-wrap:normal !important; word-break:normal !important; hyphens:none; }
body.page-stella_sales_report.sr2 .sr2-cap span + span::before { content:'·'; margin-right:8px; opacity:.7; }
body.page-stella_sales_report.sr2 .sr2-hero .sr2-cap span + span::before { content:'·'; margin-right:8px; }
body.page-stella_sales_report.sr2 .sr2-hero { flex:0 0 auto; position:relative; z-index:2; }
/* zoomed-out / big numbers: keep typed values fully visible */
body.page-stella_sales_report.sr2 .sr2-g .sr-input:not(.readout) { width:140px !important; min-width:0; font-size:clamp(.74rem,1.5cqh,.9rem) !important; padding:0 8px !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-input.readout { width:140px !important; font-size:clamp(.74rem,1.5cqh,.9rem) !important; }
body.page-stella_sales_report.sr2 .sr2-g .sr-label { font-size:clamp(.7rem,1.5cqh,.84rem) !important; min-width:0; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.coh .sr-input { width:150px !important; font-size:clamp(.8rem,1.7cqh,.95rem) !important; padding:0 8px !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-input { width:150px !important; font-size:clamp(.85rem,1.9cqh,1.05rem) !important; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-value-wrap { gap:6px; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.short .sr-value-wrap::after { font-size:.62rem; }
body.page-stella_sales_report.sr2 .sr2-hero .sr-row.net .sr-input { font-size:clamp(1.3rem,3.6cqh,1.9rem) !important; }
/* ── box total: just the number, tinted, under the entries ── */
body.page-stella_sales_report.sr2 .sub-col { grid-template-columns:minmax(0,1fr) !important; grid-template-areas:"title" "hdr" "rows" "total" "add" !important; grid-template-rows:40px auto auto auto auto !important; }
body.page-stella_sales_report.sr2 .sub-footer span:last-child, body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { grid-area:total; display:block !important; text-align:right; margin:6px 12px 0 !important; padding:7px 12px !important; border-radius:8px !important; background:var(--accbg) !important; color:var(--acc) !important; font:800 .95rem var(--m) !important; box-shadow:inset 0 0 0 1.5px var(--acc) !important; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { background:var(--negbg) !important; color:var(--neg) !important; box-shadow:inset 0 0 0 1.5px var(--neg) !important; }
body.page-stella_sales_report.sr2 .sub-footer span:last-child::before { content:none !important; }
/* ── Dine-in / extra tables: same coloured title band + readable column headings ── */
body.page-stella_sales_report.sr2 .sr2-dine .sr-section-title { display:flex !important; justify-content:center; align-items:center; background:var(--acc) !important; color:#fff !important; padding:0 14px !important; height:40px; font:800 .8rem var(--f) !important; letter-spacing:.08em !important; text-align:center !important; }
body.page-stella_sales_report.sr2 .sr2-dine.extra-section-col .sr-section-title::before { display:none !important; }
body.page-stella_sales_report.sr2 .di-table th { font-size:.64rem !important; letter-spacing:.06em !important; color:var(--ink) !important; font-weight:800 !important; padding:10px 6px !important; text-align:center !important; }
/* ═══ calmer, cleaner polish (final layer) ═══ */
body.page-stella_sales_report.sr2 .sub-col { background:var(--card) !important; border:1px solid var(--line) !important; border-radius:12px !important; box-shadow:0 1px 2px rgba(15,23,42,.05) !important; grid-template-areas:"title" "hdr" "rows" "add" "total" !important; grid-template-rows:auto auto auto auto auto !important; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out { background:var(--card) !important; }
body.page-stella_sales_report.sr2 .sub-col:focus-within { box-shadow:0 0 0 3px var(--ring) !important; border-color:var(--acc) !important; }
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col:nth-child(n) .sub-col-title { background:var(--accbg) !important; color:var(--acc) !important; justify-content:flex-start !important; text-align:left !important; padding:11px 14px !important; font-size:.72rem !important; letter-spacing:.09em !important; border-bottom:1px solid var(--line) !important; }
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col.sr2-out:nth-child(n) .sub-col-title { background:var(--negbg) !important; color:var(--neg) !important; }
body.page-stella_sales_report.sr2 .sub-col-hdr { background:transparent !important; padding:8px 14px 2px !important; }
body.page-stella_sales_report.sr2 .sub-rows { padding:2px 14px 0 !important; }
body.page-stella_sales_report.sr2 .sub-col .btn-add-row { margin:2px 14px 6px !important; }
body.page-stella_sales_report.sr2 .sub-footer span:last-child, body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { grid-area:total; margin:0 !important; padding:10px 14px !important; border-radius:0 !important; box-shadow:none !important; border-top:1px solid var(--line) !important; background:var(--accbg) !important; color:var(--acc) !important; font:800 1.02rem var(--m) !important; text-align:right; }
body.page-stella_sales_report.sr2 .sub-col.sr2-out .sub-footer span:last-child { background:var(--negbg) !important; color:var(--neg) !important; }
/* dine-in + extra tables */
body.page-stella_sales_report.sr2 .sr2-dine .sr-section-title { background:var(--accbg) !important; color:var(--acc) !important; justify-content:flex-start !important; text-align:left !important; height:auto; padding:11px 16px !important; font-size:.72rem !important; letter-spacing:.09em !important; border-bottom:1px solid var(--line) !important; }
body.page-stella_sales_report.sr2 .di-table th { background:transparent !important; color:var(--mut) !important; font-size:.62rem !important; font-weight:700 !important; text-transform:uppercase; border-bottom:1px solid var(--line) !important; }
body.page-stella_sales_report.sr2 .di-table tfoot tr:first-child td { background:var(--inp) !important; color:var(--ink) !important; font-size:.8rem !important; padding:8px 12px !important; }
body.page-stella_sales_report.sr2 .di-table tfoot tr:last-child td { background:var(--accbg) !important; background-image:none !important; color:var(--acc) !important; font-size:1rem !important; padding:10px 12px !important; border-top:1px solid var(--line) !important; }
body.page-stella_sales_report.sr2 .di-table tfoot td.total-label { color:var(--acc) !important; }
body.page-stella_sales_report.sr2 .sr2-dine > .btn-add-row { padding:8px 18px !important; font-size:.76rem !important; border-radius:8px !important; box-shadow:none !important; }
/* darker title bars */
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col:nth-child(n) .sub-col-title, body.page-stella_sales_report.sr2 .sr2-dine .sr-section-title { background:var(--acc) !important; color:#fff !important; border-bottom:0 !important; }
body.page-stella_sales_report.sr2 .sr-section .sub-grid .sub-col.sr2-out:nth-child(n) .sub-col-title { background:var(--neg) !important; color:#fff !important; }
body.page-stella_sales_report.sr2-dark .sr-section .sub-grid .sub-col:nth-child(n) .sub-col-title, body.page-stella_sales_report.sr2-dark .sr2-dine .sr-section-title, body.page-stella_sales_report.sr2-dark .sr-section .sub-grid .sub-col.sr2-out:nth-child(n) .sub-col-title { color:#0b1210 !important; }
</style>
<script>
// New design: Light (default) / Dark. Remembered per browser. (design only)
(function () {
  var cur = 'sr2-light';
  try { var sv = localStorage.getItem('stellaSr2Theme'); if (sv === 'sr2-light' || sv === 'sr2-dark') cur = sv; } catch (e) {}
  function apply() {
    document.body.classList.remove('sr-soft', 'sr-dark', 'sr-orig', 'sr2-light', 'sr2-dark');
    document.body.classList.add('sr2', cur);
    var b = document.getElementById('srThemeBtn'); if (b) b.textContent = cur === 'sr2-dark' ? '☀ Light' : '🌙 Dark';
  }
  window.srCycleTheme = function () {
    cur = cur === 'sr2-dark' ? 'sr2-light' : 'sr2-dark';
    try { localStorage.setItem('stellaSr2Theme', cur); } catch (e) {}
    apply();
  };
  // ‹ › buttons next to the date
  window.srStepDate = function (d) {
    var f = document.querySelector('.sr-controls form'), i = f && f.querySelector('input[type=date]');
    if (!i || !i.value) return;
    var t = new Date(i.value + 'T00:00:00'); t.setDate(t.getDate() + d);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    i.value = t.getFullYear() + '-' + p(t.getMonth() + 1) + '-' + p(t.getDate());
    i.dispatchEvent(new Event('change', {bubbles: true}));
  };
  document.addEventListener('DOMContentLoaded', function () {
    var ctl = document.querySelector('.sr-controls');
    var btn = document.createElement('button'); btn.type = 'button'; btn.id = 'srThemeBtn'; btn.title = 'Switch Light / Dark';
    btn.onclick = window.srCycleTheme;
    if (ctl) ctl.appendChild(btn); else document.body.appendChild(btn);
    apply();
  });
  document.body && document.body.classList.add('sr2', cur);
  apply();

  // live group subtotals + Short/Over colour (reads the figures the page already calculates)
  function num(id) { var e = document.getElementById(id); if (!e) return 0; var v = parseFloat(String(e.value != null && e.value !== '' ? e.value : e.textContent).replace(/,/g, '')); return isNaN(v) ? 0 : v; }
  function fmt(n) { return '₱' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  var LESS = ['maya_swipe','unpaid_med_credit','paid_med_card','advance_paid_appigo','grab_sales','gcash','gift_card','discount','bank_transfer_cheque','cash_out_interest','gift_cert_sold_tips','pcf_expenses','other_expenses'];
  function tick() {
    var a = document.getElementById('sr2-sum-gross'); if (a) a.textContent = 'Gross ' + fmt(num('gross_sales'));
    var b = document.getElementById('sr2-sum-swipe'); if (b) b.textContent = fmt(num('total_swipe'));
    var c = document.getElementById('sr2-sum-less');  if (c) c.textContent = '− ' + fmt(LESS.reduce(function (s, id) { return s + num(id); }, 0));
    var r = document.querySelector('.sr-row.short');
    if (r) { var so = num('short_over'); r.setAttribute('data-state', Math.abs(so) < 0.005 ? 'ok' : (so < 0 ? 'short' : 'over')); }
  }
  // size the summary rail to exactly the visible screen height (no inner scrolling)
  function fit() {
    var w = document.querySelector('.sr-split .sr-wrap'), sp = document.querySelector('.sr-split');
    if (!w || !sp) return;
    if (window.innerWidth <= 1180) { w.style.height = ''; return; }
    var t = Math.max(12, sp.getBoundingClientRect().top);
    w.style.height = Math.max(430, window.innerHeight - t - 12) + 'px';
  }
  document.addEventListener('scroll', fit, true); window.addEventListener('resize', fit);
  document.addEventListener('DOMContentLoaded', fit); window.addEventListener('load', fit); setTimeout(fit, 250);
  setInterval(tick, 300); document.addEventListener('DOMContentLoaded', tick);
})();
</script>
<div class="sr-split">
<div class="sr-main">
<!-- Header -->
<div class="sr-header-card">
  <div>
    <div class="eyebrow">Stella Branch · Sales</div>
    <div class="title">Daily Sales Report</div>
    <div class="subtitle">DINE IN detail + sub-sections · Net Sales &amp; Short/Over auto-calculated</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">📌 Stella</span>
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

<div class="sr2-bar">
<!-- Cashier -->
<div class="sr-cashier-bar">
  <label for="cashier_name">Cashier:</label>
  <input type="text" id="cashier_name" style="text-transform:uppercase" value="<?= htmlspecialchars($vs('cashier_name')) ?>" placeholder="e.g. CHRISTINE AND JUSTINE">
</div>
<!-- Controls -->
<div class="sr-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <button type="button" class="sr2-step prev" onclick="srStepDate(-1)" title="Previous day">‹</button>
    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
    <button type="button" class="sr2-step next" onclick="srStepDate(1)" title="Next day">›</button>
    <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
    <a href="stella_sales_report.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download Excel</a>
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
</div>

<?php
$renderCol = function ($sec, $label, $cls = '') use ($details) {
    $secRows = $details[$sec] ?? [['item_name'=>'','amount'=>0]];
?>
    <div class="sub-col <?= $cls ?>" data-section="<?= $sec ?>">
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
<?php }; ?>

<div class="sr2-eyebrow">Sales &amp; payments <i>— type each entry; totals feed the Summary</i></div>
<div class="sr-section">
  <div class="sub-grid" id="subsec-in">
    <?php
    $renderCol('grab', 'GRAB');
    $renderCol('deposit_card', 'DEPOSIT (CARD)');
    $renderCol('late_payment', 'LATE PAYMENT (CARD)');
    $renderCol('cash_out_interest', 'CASH OUT INTEREST (BOOKY)');
    $renderCol('advance_payment', 'ADVANCE PAID (APPIGO)');
    $renderCol('gift_cert_tips', 'GIFT CERT SOLD (TIPS)');
    $renderCol('paid_med', 'PAID MED/CORP. (CARD)');
    $renderCol('unpaid_med', 'UNPAID MED/CORP. (CREDIT)');
    ?>
  </div>
</div>

<div class="sr2-eyebrow">Pull-outs &amp; expenses</div>
<div class="sr-section">
  <div class="sub-grid" id="subsec-out">
    <?php
    $renderCol('marketing_pullout', 'MARKETING PULL OUT', 'sr2-out');
    $renderCol('expenses', 'EXPENSES', 'sr2-out');
    $renderCol('gc_sponsorship', 'GC SPONSORSHIP', 'sr2-out');
    $renderCol('gc_sold', 'GC SOLD', 'sr2-out');
    ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     DINE IN SECTION (at the bottom, like the Excel)
════════════════════════════════════════════════════════════ -->
<div class="sr-section sr2-dine">
  <div class="sr-section-title dark-red">DINE IN</div>
  <div style="overflow-x:auto">
  <table class="di-table" id="di-table">
    <thead>
      <tr>
        <th>Cash</th>
        <th>Palawan Pay</th>
        <th>Card/Swipe/QR</th>
        <th>Discount</th>
        <th>Gift Cert.</th>
        <th>Bank Transfer/Cheque</th>
        <th>Cancelled Transactions</th>
        <th style="width:40px"></th>
      </tr>
      <tr>
        <th class="sub"></th><th class="sub"></th><th class="sub"></th>
        <th class="sub"></th><th class="sub"></th><th class="sub"></th><th class="sub"></th><th class="sub"></th>
      </tr>
    </thead>
    <tbody id="di-body">
      <?php foreach ($dineinRows as $dr): ?>
      <tr>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['cash'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['palawan_pay'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['card_swipe_qr'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$dr['discount'] ?: '' ?>" placeholder="" oninput="diChanged()"></td>
        <td><input class="di-inp" type="number" step="0.01" value="<?= (float)($dr['gift_card'] ?? 0) ?: '' ?>" placeholder="" oninput="diChanged()"></td>
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
        <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
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
        <td id="di-tot-disc">0.00</td>
        <td id="di-tot-gift">0.00</td>
        <td id="di-tot-bank">0.00</td>
        <td id="di-tot-cancelled">0.00</td>
        <td></td>
      </tr>
      <tr>
        <td class="total-label" colspan="3">TOTAL</td>
        <td class="grand-total" colspan="5" id="di-grand-total">0.00</td>
      </tr>
    </tfoot>
  </table>
  </div>
  <button class="btn-add-row" onclick="addDiRow()">+ Add Row</button>
</div>

</div><!-- /.sr-main -->

<!-- ══════════════════════════════════════════════════════════
     SUMMARY REPORT (existing fields — auto-fed from above)
════════════════════════════════════════════════════════════ -->
<!-- ══════════════════════════════════════════════════════════
     SUMMARY REPORT — Stella Branch
════════════════════════════════════════════════════════════ -->
<div class="sr-wrap">
<div class="sr-card">
<div class="sr2-g" style="--n:5">
  <div class="sr2-gh">Sales build-up<span id="sr2-sum-gross"></span></div>
  <div class="sr-row green">
    <div class="sr-label">Gross Sales <span style="font-size:.65rem;opacity:.8">(auto: Grab + Cash out Interest + Gift Cert Sold + Advance Paid + Paid Med + Unpaid Med + Total Dine In − Service Charge)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gross_sales" value="<?= $fmt($grossSalesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">Service Charge</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="service_charge" value="<?= $v('service_charge') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">POS Z reading <span style="font-size:.65rem;opacity:.8">(auto: Gross Sales + Marketing Pull Out)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="pos_z_reading" value="<?= $fmt($posZReadingCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Marketing Pull Out <span style="font-size:.65rem;opacity:.7">(auto from Marketing Pullout)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="marketing_pull_out" value="<?= $fmt($marketingCalc) ?>" readonly tabindex="-1"></div>
  </div>
</div>
<div class="sr2-g" style="--n:4">
  <div class="sr2-gh">Swipe breakdown<span id="sr2-sum-swipe"></span></div>
  <div class="sr-row green">
    <div class="sr-label">Total Swipe: <span style="font-size:.65rem;opacity:.8">(auto: Deposit + Late Payment + Maya + Paid Med)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="total_swipe" value="<?= $fmt($v('deposit_swipe_card')+$v('late_payment_card')+$v('maya_swipe')+$paidMedCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row gray-hdr">
    <div class="sr-label">DEPOSIT SWIPE (CARD) <span style="font-size:.65rem;opacity:.7">(auto: Deposit (Card) + Gift Cert Sold (TIPS))</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="deposit_swipe_card" value="<?= $fmt($v('deposit_swipe_card')) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row gray-hdr">
    <div class="sr-label">LATE PAYMENT (CARD) <span style="font-size:.65rem;opacity:.7">(auto from Late Payment sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="late_payment_card" value="<?= $fmt($v('late_payment_card')) ?>" readonly tabindex="-1"></div>
  </div>
</div>
<div class="sr2-g" style="--n:14">
  <div class="sr2-gh neg">Less from Gross Sales → Net Sales<span id="sr2-sum-less"></span></div>
  <div class="sr-row green">
    <div class="sr-label">Sales of the day Swipe: (MAYA) <span style="font-size:.65rem;opacity:.7">(auto from Card/Swipe/QR total)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="maya_swipe" value="<?= $fmt($v('maya_swipe')) ?>" readonly tabindex="-1" style="font-weight:800"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Unpaid Med (CREDIT) <span style="font-size:.65rem;opacity:.7">(auto from Unpaid/Credit)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="unpaid_med_credit" value="<?= $fmt($unpaidMedCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Paid Med (CARD) <span style="font-size:.65rem;opacity:.7">(auto from Paid Med/Corp. sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="paid_med_card" value="<?= $fmt($paidMedCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Advance Paid (APPIGO) <span style="font-size:.65rem;opacity:.7">(auto from Advance Payment)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="advance_paid_appigo" value="<?= $fmt($advancePaidCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Grab Sales: <span style="font-size:.65rem;opacity:.7">(auto from GRAB sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="grab_sales" value="<?= $fmt($grabCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">G-Cash <span style="font-size:.65rem;opacity:.7">(auto from Palawan Pay)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gcash" value="<?= $fmt($gcashCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Gift Card <span style="font-size:.65rem;opacity:.7">(auto from DINE IN Gift Cert.)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gift_card" value="<?= $fmt($giftCardCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Discount <span style="font-size:.65rem;opacity:.7">(auto from DINE IN Discount)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="discount" value="<?= $fmt($discountCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Bank Transfer/Cheque <span style="font-size:.65rem;opacity:.7">(auto from DINE IN)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="bank_transfer_cheque" value="<?= $fmt($bankTransferCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Cash out Interest (BOOKY) <span style="font-size:.65rem;opacity:.7">(auto from Cash out Interest sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="cash_out_interest" value="<?= $fmt($cashOutCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Gift Cert. Sold (TIPS) <span style="font-size:.65rem;opacity:.7">(auto from GC Sold)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gift_cert_sold_tips" value="<?= $fmt($giftCertSoldCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">PCF/Expenses <span style="font-size:.65rem;opacity:.7">(auto from Expenses)</span></div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="pcf_expenses" value="<?= $v('pcf_expenses') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">Other Expenses</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="other_expenses" value="<?= $v('other_expenses') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
</div>
<div class="sr2-hero">
  <div class="sr2-cap"><span>Summary report</span><span><?= date('D, M j, Y', strtotime($fDate)) ?></span></div>
  <div class="sr-row net">
    <div class="sr-label">Net Sales</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="net_sales" value="<?= $fmt($v('net_sales')) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row coh">
    <div class="sr-label">COH(Cash on Hand)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="coh" value="<?= $v('coh') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#cfd8ea">(cash sales only except SC)</div>
  </div>
  <div class="sr-row coh" id="replenRow" style="display:none">
    <div class="sr-label">Cash Replenishment <span style="font-weight:400;font-size:.7em">(auto when Net Sales is negative)</span></div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="cash_replenishment" value="<?= $v('cash_replenishment') ?: '' ?>" oninput="replenTyped()" placeholder="0.00" data-saved="<?= htmlspecialchars((string)($v('cash_replenishment') ?: 0)) ?>"></div>
    <div class="sr-note" style="color:#cfd8ea">(cash from the vault used to cover the shortfall)</div>
  </div>
  <div class="sr-row short">
    <div class="sr-label">Short/Over</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="short_over" value="<?= $fmt($v('short_over')) ?>" readonly tabindex="-1"></div>
    <div class="sr-note" style="color:#ffd8d8">(if "-" sign means short)</div>
  </div>

  
</div>

  <!-- Remarks -->
  <div class="sr-remarks sr2-rem">
    <label for="remarks">Remarks</label>
    <textarea id="remarks" rows="3" maxlength="5000" placeholder="Type any notes for this day here… (saves automatically)" <?= $restrictEdit ? 'readonly' : '' ?>><?= htmlspecialchars($vs('remarks')) ?></textarea>
  </div>
</div>
</div>
</div><!-- /.sr-split -->


<script>
// ── AUTO-SAVE: every change saves by itself — no Save button / Enter needed ──
window.addEventListener('load', function () {
  if (typeof saveAll !== 'function') return;
  if (typeof REPORT_LOCKED !== 'undefined' && REPORT_LOCKED) return;
  const origToast = window.showToast;
  let quiet = false;
  if (typeof origToast === 'function') {
    window.showToast = function (m, t) { if (quiet && t !== 'error') return; return origToast.apply(this, arguments); };
  }
  let timer = null, running = false, again = false, dirty = false;
  async function run() {
    if (running) { again = true; return; }
    running = true; dirty = false; quiet = true;
    try { await saveAll(); }
    catch (e) { quiet = false; if (origToast) origToast('❌ Auto-save failed — check your connection', 'error'); }
    quiet = false; running = false;
    if (again) { again = false; run(); }
  }
  function schedule(ms) { dirty = true; clearTimeout(timer); timer = setTimeout(run, ms); }
  const skip = el => !el || !el.matches || !el.matches('input,select,textarea') || el.readOnly || el.disabled ||
    el.closest('#filterForm,.sr-controls,#lockPopover,.lock-wrap,.se-controls');
  document.addEventListener('input',  e => { if (!skip(e.target)) schedule(900); });
  document.addEventListener('change', e => { if (!skip(e.target)) schedule(200); });
  document.addEventListener('click', e => {
    const b = e.target.closest && e.target.closest('[onclick^="del"],[onclick^="pick"],.btn-del-row,.bdel');
    if (b) schedule(500);
  });
  window.addEventListener('beforeunload', e => { if (dirty || running) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('visibilitychange', () => { if (document.hidden && dirty) { clearTimeout(timer); run(); } });
  const sb = document.querySelector('button[onclick="saveAll()"]');
  if (sb) { const chip = document.createElement('span'); chip.textContent = '✓ Auto-save on';
    chip.style.cssText = 'font-family:var(--font-m);font-size:.72rem;font-weight:700;color:var(--accent);padding:6px 10px';
    sb.replaceWith(chip); }
});
</script>
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
    unpaid_credit_name:  '',
    unpaid_credit_amount: 0,
    discount:            (float(inps[3])),
    gift_card:           (float(inps[4])),
    bank_transfer_cheque:(float(inps[5])),
    cancelled_transactions:(float(inps[6])),
  };
}
function float(inp) { return parseFloat(inp?.value) || 0; }

// true when the saved Cash on Hand was typed by hand (differs from the Dine In Cash total)
let COH_MANUAL = (function () {
  let c = 0; document.querySelectorAll('#di-body tr').forEach(tr => { c += float(tr.querySelectorAll('input')[0]); });
  const sv = gv('coh'); return sv > 0.005 && Math.abs(sv - c) > 0.005;
})();
(function () {
  const el = document.getElementById('coh'); if (!el) return;
  el.addEventListener('input', function () {
    let c = 0; document.querySelectorAll('#di-body tr').forEach(tr => { c += float(tr.querySelectorAll('input')[0]); });
    const v = el.value.trim();
    COH_MANUAL = v !== '' && Math.abs((parseFloat(v) || 0) - c) > 0.005;
  });
})();
function diChanged() {
  let totCash=0,totPal=0,totCard=0,totDisc=0,totGift=0,totBank=0,totCan=0;
  document.querySelectorAll('#di-body tr').forEach(tr => {
    const c = diCols(tr);
    totCash   += c.cash; totPal += c.palawan_pay; totCard += c.card_swipe_qr;
    totDisc += c.discount; totGift += c.gift_card;
    totBank   += c.bank_transfer_cheque; totCan += c.cancelled_transactions;
  });
  const grand = totCash + totPal + totCard + totDisc + totGift + totBank;
  document.getElementById('di-tot-cash').textContent      = fmt(totCash);
  document.getElementById('di-tot-palawan').textContent   = fmt(totPal);
  document.getElementById('di-tot-card').textContent      = fmt(totCard);
  document.getElementById('di-tot-disc').textContent      = fmt(totDisc);
  document.getElementById('di-tot-gift').textContent      = fmt(totGift);
  document.getElementById('di-tot-bank').textContent      = fmt(totBank);
  document.getElementById('di-tot-cancelled').textContent = fmt(totCan);
  document.getElementById('di-grand-total').textContent   = fmt(grand);

  // Auto-feed summary fields
  setVal('discount',             fmt(totDisc));
  setVal('gift_card',            fmt(totGift));
  setVal('bank_transfer_cheque', fmt(totBank));
  setVal('gcash',                fmt(totPal));
  setVal('maya_swipe',           fmt(totCard));
  // COH (Cash on Hand) is typed in by hand (the counted cash) — it is NOT filled from the Dine In Cash total.
  recalc();
}

function addDiRow() {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="diChanged()"></td>
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
  if (sec === 'unpaid_med')        { setVal('unpaid_med_credit',   fmt(tot)); recalc(); }
  if (sec === 'grab')              { setVal('grab_sales',         fmt(tot)); recalc(); }
  if (sec === 'marketing_pullout') { setVal('marketing_pull_out', fmt(tot)); recalc(); }
  if (sec === 'expenses')          { setVal('pcf_expenses',        fmt(tot)); recalc(); }
  if (sec === 'late_payment')      { setVal('late_payment_card',   fmt(tot)); recalc(); }
  if (sec === 'advance_payment')   { setVal('advance_paid_appigo', fmt(tot)); recalc(); }
  if (sec === 'gc_sold')           { recalcGiftCert(); recalc(); }
  if (sec === 'gift_cert_tips')    { recalcGiftCert(); recalcDepositSwipe(); }
  if (sec === 'paid_med')          { setVal('paid_med_card',       fmt(tot)); recalc(); }
  if (sec === 'deposit_card')      { recalcDepositSwipe(); }
  if (sec === 'cash_out_interest') { setVal('cash_out_interest',   fmt(tot)); recalc(); }
}

// Gift Cert. Sold (TIPS) = GC SOLD box + GIFT CERT SOLD (TIPS) box → goes into Gross Sales
function recalcGiftCert() {
  let t = 0;
  ['gc_sold','gift_cert_tips'].forEach(sec => {
    const c = document.querySelector(`.sub-col[data-section="${sec}"]`);
    if (c) c.querySelectorAll('input[type=number]').forEach(i => t += parseFloat(i.value)||0);
  });
  setVal('gift_cert_sold_tips', fmt(t));
}

function recalcDepositSwipe() {
  // DEPOSIT SWIPE (CARD) = DEPOSIT (CARD) sub-section + GIFT CERT SOLD (TIPS) sub-section
  let depTot = 0, gcTot = 0;
  const depCol = document.querySelector('.sub-col[data-section="deposit_card"]');
  if (depCol) depCol.querySelectorAll('input[type=number]').forEach(i => depTot += parseFloat(i.value)||0);
  const gcCol = document.querySelector('.sub-col[data-section="gift_cert_tips"]');
  if (gcCol) gcCol.querySelectorAll('input[type=number]').forEach(i => gcTot += parseFloat(i.value)||0);
  setVal('deposit_swipe_card', fmt(depTot + gcTot));
  recalc();
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
  // Total Swipe = Deposit + Late Payment + Maya + Paid Med (CARD)
  const totalSwipe = gv('deposit_swipe_card') + gv('late_payment_card') + gv('maya_swipe') + gv('paid_med_card');
  document.getElementById('total_swipe').value = fmt(totalSwipe);

  // Total Dine In (grand total from DINE IN table)
  const totalDineIn = parseFloat(String(document.getElementById('di-grand-total')?.textContent ?? '0').replace(/,/g,'')) || 0;

  // Gross Sales = Grab + Cash out Interest + Gift Cert Sold + Advance Paid + Paid Med + Unpaid Med + Total Dine In - Service Charge
  // NOTE: G-Cash is NOT added separately — it's the Dine In "Palawan Pay" column,
  // already included inside Total Dine In. Adding it again would double-count it.
  const grossSales = gv('grab_sales') + gv('cash_out_interest') + gv('gift_cert_sold_tips')
                    + gv('advance_paid_appigo') + gv('paid_med_card') + gv('unpaid_med_credit')
                    + totalDineIn - gv('service_charge');
  document.getElementById('gross_sales').value = fmt(grossSales);

  // POS Z Reading = Gross Sales + Marketing Pull Out
  const posZReading = grossSales + gv('marketing_pull_out');
  document.getElementById('pos_z_reading').value = fmt(posZReading);

  // Net Sales = Gross Sales - Maya Swipe - all deductions (Marketing Pull Out already inside Gross Sales)
  const netSales = gv('gross_sales')
                 - gv('maya_swipe')
                 - gv('unpaid_med_credit')
                 - gv('paid_med_card')
                 - gv('advance_paid_appigo')
                 - gv('grab_sales')
                 - gv('gcash')
                 - gv('gift_card')
                 - gv('discount')
                 - gv('bank_transfer_cheque')
                 - gv('cash_out_interest')
                 - gv('gift_cert_sold_tips')
                 - gv('pcf_expenses')
                 - gv('other_expenses');

  // Cash replenishment: auto = -Net Sales when negative, unless the user typed a different amount
  const rEl = document.getElementById('cash_replenishment');
  if (rEl) {
    if (netSales >= 0) { rEl.value = ''; rEl.dataset.manual = ''; }
    else if (rEl.dataset.manual !== '1') { rEl.value = (Math.round(-netSales * 100) / 100).toFixed(2); }
    document.getElementById('replenRow').style.display = (netSales < 0 || gv('cash_replenishment') > 0) ? '' : 'none';
  }
  const replen = rEl ? gv('cash_replenishment') : 0;
  const shortOver = gv('coh') - (netSales + replen);
  document.getElementById('net_sales').value = fmt(netSales);
  const soEl = document.getElementById('short_over');
  soEl.value = fmt(shortOver);
  soEl.style.color = shortOver < 0 ? '#ffd54f' : '#fff';
}

function replenTyped() {
  const el = document.getElementById('cash_replenishment');
  el.dataset.manual = String(el.value).trim() === '' ? '' : '1';
  recalc();
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
  await fetch('stella_sales_report.php', {method:'POST',body:fd1});

  // 2. Save each sub-section
  const allSecs = ['unpaid_med','marketing_pullout','grab','expenses','late_payment','advance_payment','gc_sponsorship','gc_sold','paid_med','deposit_card','gift_cert_tips','cash_out_interest'];
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
    await fetch('stella_sales_report.php', {method:'POST',body:fd2});
  }

  // 3. Save main summary
  const fd3 = new FormData();
  fd3.append('ajax_save','1');
  fd3.append('report_date', FDATE);
  fd3.append('cashier_name', document.getElementById('cashier_name')?.value || '');
  fd3.append('remarks', document.getElementById('remarks')?.value || '');
  ['gross_sales','service_charge','pos_z_reading','total_swipe',
   'deposit_swipe_card','late_payment_card','maya_swipe',
   'unpaid_med_credit','paid_med_card','advance_paid_appigo',
   'grab_sales','gcash','gift_card','marketing_pull_out','discount',
   'bank_transfer_cheque','cash_out_interest','gift_cert_sold_tips',
   'pcf_expenses','other_expenses','coh','cash_replenishment'].forEach(id => fd3.append(id, gv(id)));

  const res  = await fetch('stella_sales_report.php', {method:'POST',body:fd3});
  const data = await res.json();
  if (data.ok) {
    status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
    try { localStorage.setItem('stella_sales_saved', FDATE + '|' + Date.now()); } catch (e) {}   // tells an open Summary Report tab to refresh now
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
    const res  = await fetch('stella_sales_report.php', {method:'POST', body:fd});
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

document.addEventListener('DOMContentLoaded', () => {
  const rEl = document.getElementById('cash_replenishment');
  const savedR = parseFloat(rEl?.dataset.saved) || 0;
  diChanged(); recalcDepositSwipe();
  recalc();
  // a saved replenishment that differs from -Net Sales was typed by hand: keep it
  if (rEl && savedR > 0 && Math.abs(savedR - gv('cash_replenishment')) > 0.004) { rEl.value = savedR.toFixed(2); rEl.dataset.manual = '1'; }
  recalc();
  // older days saved before this field existed: re-save once so Short/Over uses the replenishment
  if (rEl && !REPORT_LOCKED && savedR === 0 && gv('cash_replenishment') > 0) setTimeout(() => rEl.dispatchEvent(new Event('change', {bubbles:true})), 600);
  if (REPORT_LOCKED) lockPage();
});
</script>
<script>
// Excel's Dine In block has merged cells, so copying it gives blank "spacer" cells after Card/Swipe and after
// Discount (Cash | GCash | Card | _ | Discount | _ | Gift). When a 7-column block like that is pasted at the
// first column, drop the two spacer columns so every number lands under its own heading.
function dineInFixMerged(data) {
  if (!data.length || data.some(r => r.length !== 7)) return data;
  if (data.some(r => String(r[3]).trim() !== '' || String(r[5]).trim() !== '')) return data;
  if (!data.some(r => String(r[4]).trim() !== '' || String(r[6]).trim() !== '')) return data;
  return data.map(r => [r[0], r[1], r[2], r[4], r[6], '', '']);
}
</script>
<script>
// ── Excel-style paste: copy several cells in Excel / Google Sheets, paste into any box,
//    and the values spread across the columns and down the rows (new rows are added). ──
(function () {
  function ctx(el) {
    const sub = el.closest('.sub-row');
    if (sub) {
      const col = sub.closest('.sub-col'); const sec = col && col.dataset.section;
      return { rows: () => [...col.querySelectorAll('.sub-rows .sub-row')], add: () => addSubRow(sec), row: sub };
    }
    const tr = el.closest('tbody tr'), table = el.closest('table');
    if (!tr || !table) return null;
    const body = tr.parentElement;
    let add = null;
    if (table.id === 'di-table') add = () => addDiRow();
    else return null;
    return { rows: () => [...body.querySelectorAll('tr')], add, row: tr };
  }
  const cellsOf = row => [...row.querySelectorAll('input,textarea')].filter(i => !['button','hidden'].includes(i.type) && !i.readOnly && !i.disabled);
  function put(inp, val) {
    val = String(val).trim();
    if (inp.type === 'number') {
      const n = parseFloat(val.replace(/[^0-9.\-]/g, ''));
      if (val === '') inp.value = ''; else if (isFinite(n)) inp.value = n; else return;
    } else inp.value = val;
    inp.dispatchEvent(new Event('input', {bubbles:true}));
  }
  document.addEventListener('paste', function (ev) {
    const el = ev.target;
    if (!el || !el.matches || !el.matches('input,textarea') || el.readOnly || el.disabled) return;
    if (typeof REPORT_LOCKED !== 'undefined' && REPORT_LOCKED) return;
    const c = ctx(el); if (!c) return;
    const text = (ev.clipboardData || window.clipboardData).getData('text');
    if (!text || (text.indexOf('\t') < 0 && !/\r?\n./.test(text))) return;   // single value → normal paste
    let data = text.replace(/\r/g, '').replace(/\n$/, '').split('\n').map(l => l.split('\t'));
    ev.preventDefault();
    const r0 = c.rows().indexOf(c.row), c0 = cellsOf(c.row).indexOf(el);
    if (r0 < 0 || c0 < 0) return;
    if (c0 === 0 && el.closest('#di-table')) data = dineInFixMerged(data);
    data.forEach(function (line, i) {
      let rows = c.rows();
      while (rows.length <= r0 + i) { c.add(); const n = c.rows(); if (n.length === rows.length) return; rows = n; }
      const cells = cellsOf(rows[r0 + i]);
      line.forEach((v, j) => { if (cells[c0 + j]) put(cells[c0 + j], v); });
    });
    if (typeof showToast === 'function') showToast('✓ Pasted ' + data.length + ' row' + (data.length > 1 ? 's' : ''), 'success');
  });
})();
</script>
<style>
.xl-sel { background-color:rgba(37,99,235,.16) !important; box-shadow:inset 0 0 0 1px rgba(37,99,235,.55) !important; }
.xl-selhi { box-shadow:inset 0 0 0 2px #2563eb !important; }
.xl-pv { background-color:rgba(37,99,235,.08) !important; box-shadow:inset 0 0 0 1px dashed #2563eb; outline:1px dashed #2563eb; outline-offset:-1px; }
#xlFill { position:fixed; width:9px; height:9px; background:#2563eb; border:1px solid #fff; cursor:crosshair; z-index:9999; display:none; box-sizing:content-box; }
body.xl-dragging, body.xl-dragging * { user-select:none !important; cursor:crosshair !important; }
</style>
<div id="xlFill" title="Drag to fill down / up / across"></div>
<script>
// ── Excel-style cell selection + fill handle ─────────────────────────────────────
//  • drag across boxes to select a block (blue)      • Ctrl+C copies it, Ctrl+V pastes at the top-left
//  • Delete clears the block                         • drag the little blue square to copy/fill cells
(function () {
  if (typeof REPORT_LOCKED !== 'undefined' && REPORT_LOCKED) return;
  const handle = document.getElementById('xlFill');
  const cellsOf = row => [...row.querySelectorAll('input')].filter(i => !['button','hidden','checkbox','radio','date'].includes(i.type) && !i.readOnly && !i.disabled);
  function gridOf(el) {
    if (!el || !el.closest) return null;
    const sub = el.closest('.sub-row');
    if (sub) { const col = sub.closest('.sub-col'), sec = col.dataset.section;
      return { key:'sub:'+sec, rows:() => [...col.querySelectorAll('.sub-rows .sub-row')], add:() => addSubRow(sec), after:() => subChanged(sec), rowOf:e => e.closest('.sub-row') }; }
    const table = el.closest('table.di-table'); if (!table || !el.closest('tbody')) return null;
    const body = table.querySelector('tbody'), isDi = table.id === 'di-table', sec = table.dataset.section;
    if (!isDi) return null;
    return { key:'di', rows:() => [...body.querySelectorAll('tr')], add:() => addDiRow(), after:() => diChanged(), rowOf:e => e.closest('tr') };
  }
  const pos = (g, el) => { const row = g.rowOf(el), rows = g.rows(), r = rows.indexOf(row); return r < 0 ? null : { r, c: cellsOf(row).indexOf(el) }; };
  const cellAt = (g, r, c) => { const rows = g.rows(); if (r < 0 || r >= rows.length) return null; return cellsOf(rows[r])[c] || null; };
  function setVal(inp, v) {
    v = v == null ? '' : String(v).trim();
    if (inp.type === 'number') { if (v === '') inp.value = ''; else { const neg = /^\(.*\)$/.test(v) || /^-/.test(v); const n = parseFloat(v.replace(/[^0-9.]/g,'')); if (!isFinite(n)) return; inp.value = neg ? -n : n; } }
    else inp.value = v;
    inp.dispatchEvent(new Event('input', {bubbles:true}));
  }
  let sel = null, multi = false, down = null, fill = null, pv = [];
  const clearMarks = () => document.querySelectorAll('.xl-sel,.xl-selhi,.xl-pv').forEach(e => e.classList.remove('xl-sel','xl-selhi','xl-pv'));
  function range(a, b) { return { r1:Math.min(a.r,b.r), r2:Math.max(a.r,b.r), c1:Math.min(a.c,b.c), c2:Math.max(a.c,b.c) }; }
  function paint() {
    clearMarks(); if (!sel) { handle.style.display = 'none'; return; }
    const {g, r1, r2, c1, c2} = sel; let last = null;
    for (let r = r1; r <= r2; r++) for (let c = c1; c <= c2; c++) { const e = cellAt(g, r, c); if (e) { e.classList.add('xl-sel'); last = e; } }
    if (!multi) { const f = cellAt(g, r1, c1); if (f) f.classList.add('xl-selhi'); }
    placeHandle(last);
  }
  function placeHandle(last) {
    if (!last || !last.offsetParent) { handle.style.display = 'none'; return; }
    const b = last.getBoundingClientRect();
    handle.style.left = (b.right - 5) + 'px'; handle.style.top = (b.bottom - 5) + 'px'; handle.style.display = 'block';
  }
  const refresh = () => { if (sel) { const l = cellAt(sel.g, sel.r2, sel.c2); placeHandle(l); } };
  window.addEventListener('scroll', refresh, true); window.addEventListener('resize', refresh);

  function hit(x, y, g) {
    let t = document.elementFromPoint(x, y); if (!t) return null;
    if (t === handle) return null;
    let inp = t.matches && t.matches('input') ? t : null;
    if (!inp) { const host = t.closest('td,.sub-row'); if (host) { const arr = cellsOf(host.matches('td') ? host.parentElement : host); inp = host.matches('td') ? (host.querySelector('input') && cellsOf(host)[0]) : arr[0]; } }
    if (!inp) return null;
    const g2 = gridOf(inp); if (!g2 || g2.key !== g.key) return null;
    return { el: inp, p: pos(g2, inp) };
  }

  document.addEventListener('mousedown', function (ev) {
    if (ev.button !== 0) return;
    if (ev.target === handle) {
      if (!sel) return; ev.preventDefault();
      fill = { g: sel.g, src: {...sel}, to: null }; document.body.classList.add('xl-dragging'); return;
    }
    const inp = ev.target.closest && ev.target.closest('input'); const g = inp && gridOf(inp);
    if (!g || !cellsOf(g.rowOf(inp)).includes(inp)) { sel = null; multi = false; paint(); return; }
    const p = pos(g, inp); if (!p) return;
    down = { g, p, el: inp, moved: false };
    sel = { g, r1:p.r, r2:p.r, c1:p.c, c2:p.c }; multi = false; paint();
  }, true);

  document.addEventListener('mousemove', function (ev) {
    if (fill) {
      const g = fill.g, rows = g.rows(), src = fill.src;
      // find target row / col under pointer; below the last row → extra rows
      let tr = src.r2, tc = src.c2;
      const lastCell = cellAt(g, rows.length - 1, 0), lb = lastCell ? lastCell.getBoundingClientRect() : null;
      let h = hit(ev.clientX, ev.clientY, g);
      if (h) { tr = h.p.r; tc = h.p.c; }
      else if (lb && ev.clientY > lb.bottom) { const rh = Math.max(20, lb.height); tr = rows.length - 1 + Math.ceil((ev.clientY - lb.bottom) / rh); tc = src.c2; }
      // choose the axis with the bigger move
      const dr = tr > src.r2 ? tr - src.r2 : (tr < src.r1 ? src.r1 - tr : 0), dc = tc > src.c2 ? tc - src.c2 : (tc < src.c1 ? src.c1 - tc : 0);
      fill.to = (dr >= dc) ? { r: tr, c: null } : { r: null, c: tc };
      document.querySelectorAll('.xl-pv').forEach(e => e.classList.remove('xl-pv'));
      const R = fill.to.r != null ? [Math.min(src.r1, tr), Math.max(src.r2, tr)] : [src.r1, src.r2];
      const C = fill.to.c != null ? [Math.min(src.c1, tc), Math.max(src.c2, tc)] : [src.c1, src.c2];
      for (let r = R[0]; r <= Math.min(R[1], rows.length - 1); r++) for (let c = C[0]; c <= C[1]; c++) { const e = cellAt(g, r, c); if (e && !e.classList.contains('xl-sel')) e.classList.add('xl-pv'); }
      fill.R = R; fill.C = C; return;
    }
    if (!down || !(ev.buttons & 1)) return;
    const h = hit(ev.clientX, ev.clientY, down.g);
    if (!h || (h.p.r === down.p.r && h.p.c === down.p.c && !down.moved)) return;
    if (!down.moved) { down.moved = true; multi = true; document.body.classList.add('xl-dragging'); try { document.activeElement.blur(); } catch (e) {} }
    const rg = range(down.p, h.p); sel = { g: down.g, ...rg }; paint(); window.getSelection().removeAllRanges();
  }, true);

  document.addEventListener('mouseup', function () {
    if (fill) {
      const f = fill; fill = null; document.body.classList.remove('xl-dragging'); document.querySelectorAll('.xl-pv').forEach(e => e.classList.remove('xl-pv'));
      if (f.R && f.C) doFill(f);
      return;
    }
    if (down) { document.body.classList.remove('xl-dragging'); down = null; }
  }, true);

  function valuesOf(g, r1, r2, c1, c2) { const out = []; for (let r = r1; r <= r2; r++) { const row = []; for (let c = c1; c <= c2; c++) { const e = cellAt(g, r, c); row.push(e ? e.value : ''); } out.push(row); } return out; }
  function doFill(f) {
    const g = f.g, s = f.src, [R1, R2] = f.R, [C1, C2] = f.C;
    const srcVals = valuesOf(g, s.r1, s.r2, s.c1, s.c2), nr = srcVals.length, nc = srcVals[0].length;
    // make sure the rows exist
    for (let guard = 0; g.rows().length <= R2 && guard < 200; guard++) { const before = g.rows().length; g.add(); if (g.rows().length === before) break; }
    // numeric series: 2+ numeric cells in a column continue the step (like Excel)
    const colSeries = c => { if (nr < 2) return null; const v = srcVals.map(r => parseFloat(String(r[c]).replace(/,/g,''))); if (v.some(x => !isFinite(x))) return null; const st = v[1] - v[0]; for (let i = 2; i < v.length; i++) if (Math.abs(v[i] - v[i-1] - st) > 1e-9) return null; return { v, st }; };
    for (let r = R1; r <= R2; r++) for (let c = C1; c <= C2; c++) {
      if (r >= s.r1 && r <= s.r2 && c >= s.c1 && c <= s.c2) continue;
      const e = cellAt(g, r, c); if (!e) continue;
      const dr = r - s.r1, dc = c - s.c1; const ri = ((dr % nr) + nr) % nr, ci = ((dc % nc) + nc) % nc;
      let val = srcVals[ri][ci];
      if (f.to && f.to.r != null && c >= s.c1 && c <= s.c2) { const ser = colSeries(ci); if (ser) val = ser.v[0] + ser.st * dr; }
      setVal(e, val);
    }
    sel = { g, r1: Math.min(s.r1, R1), r2: Math.max(s.r2, R2), c1: Math.min(s.c1, C1), c2: Math.max(s.c2, C2) }; multi = true; paint(); g.after();
    if (typeof showToast === 'function') showToast('✓ Filled', 'success');
  }

  document.addEventListener('focusin', function (ev) {
    if (down || fill) return;
    const g = gridOf(ev.target); if (!g) return; const p = pos(g, ev.target); if (!p || p.c < 0) return;
    if (multi && sel && sel.g.key === g.key && p.r >= sel.r1 && p.r <= sel.r2 && p.c >= sel.c1 && p.c <= sel.c2) return;
    sel = { g, r1:p.r, r2:p.r, c1:p.c, c2:p.c }; multi = false; paint();
  });

  const tsv = () => valuesOf(sel.g, sel.r1, sel.r2, sel.c1, sel.c2).map(r => r.join('\t')).join('\n');
  document.addEventListener('keydown', function (ev) {
    if (!sel || !multi) return;
    const k = ev.key.toLowerCase();
    if ((ev.ctrlKey || ev.metaKey) && (k === 'c' || k === 'x')) {
      ev.preventDefault(); const t = tsv();
      const done = () => { if (typeof showToast === 'function') showToast('✓ Copied — paste into Excel', 'success'); if (k === 'x') clearSel(); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(done, () => legacy(t, done)); else legacy(t, done);
    } else if (k === 'delete' || k === 'backspace') { ev.preventDefault(); clearSel(); }
    else if (k === 'escape') { sel = null; multi = false; paint(); }
  }, true);
  function legacy(t, done) { const ta = document.createElement('textarea'); ta.value = t; ta.style.cssText = 'position:fixed;opacity:0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} ta.remove(); }
  function clearSel() { for (let r = sel.r1; r <= sel.r2; r++) for (let c = sel.c1; c <= sel.c2; c++) { const e = cellAt(sel.g, r, c); if (e) setVal(e, ''); } sel.g.after(); }

  // paste while a block is selected (focus is not inside a box)
  document.addEventListener('paste', function (ev) {
    if (!sel || !multi) return;
    const a = document.activeElement; if (a && a.matches && a.matches('input,textarea') && !a.closest('.xl-sel')) return;
    const text = (ev.clipboardData || window.clipboardData).getData('text'); if (!text) return;
    ev.preventDefault(); ev.stopImmediatePropagation();
    const g = sel.g; let data = text.replace(/\r/g,'').replace(/\n$/,'').split('\n').map(l => l.split('\t'));
    if (g.key === 'di' && sel.c1 === 0) data = dineInFixMerged(data);
    if (data.length === 1 && data[0].length === 1) { for (let r = sel.r1; r <= sel.r2; r++) for (let c = sel.c1; c <= sel.c2; c++) { const e = cellAt(g, r, c); if (e) setVal(e, data[0][0]); } }
    else data.forEach((line, i) => {
      for (let guard = 0; g.rows().length <= sel.r1 + i && guard < 5; guard++) { const b = g.rows().length; g.add(); if (g.rows().length === b) break; }
      line.forEach((v, j) => { const e = cellAt(g, sel.r1 + i, sel.c1 + j); if (e) setVal(e, v); });
    });
    g.after(); if (typeof showToast === 'function') showToast('✓ Pasted', 'success'); paint();
  }, true);
})();
</script>
</body>
</html>