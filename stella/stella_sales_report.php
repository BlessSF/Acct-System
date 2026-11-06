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
foreach (['paid_med_card','advance_paid_appigo','cash_out_interest','gift_cert_sold_tips','pos_z_reading'] as $col) {
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
        $shortOver = $vals['coh'] - $netSales;

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
<style id="sr-eye-comfort">
/* ═══ Eye-comfort theme for the Stella Sales Report (Soft = default · Dark · Original) ═══ */
body.page-stella_sales_report.sr-soft, body.page-stella_sales_report.sr-dark { --sr-radius: 14px; }
body.page-stella_sales_report.sr-soft {
  --sr-bg:#c8c7ba; --sr-card:#d6d4c7; --sr-card2:#cecdbf; --sr-input:#e2e0d3; --sr-input-bd:#a8a798; --sr-text:#2b2a22; --sr-sub:#5b5a4c;
  --sr-head1:#3f5247; --sr-head2:#34463c; --sr-band:#4d6356; --sr-band-text:#e4e1d3; --sr-mint:#cbd1c2; --sr-line:#b6b5a5;
  --sr-net:#c7b27c; --sr-net-text:#3a2f10; --sr-coh:#35404e; --sr-short:#93515a; --sr-focus:#4d6356;
}
body.page-stella_sales_report.sr-dark {
  --sr-bg:#121a16; --sr-card:#1b2620; --sr-card2:#16201b; --sr-input:#222f28; --sr-input-bd:#33453a; --sr-text:#dbe6de; --sr-sub:#97a89d;
  --sr-head1:#1f4332; --sr-head2:#173226; --sr-band:#24523e; --sr-band-text:#e6f0e9; --sr-mint:#1f2d25; --sr-line:#2a3a31;
  --sr-net:#8a7437; --sr-net-text:#fff4d4; --sr-coh:#1a2638; --sr-short:#8f3943; --sr-focus:#58a47f;
}
body.page-stella_sales_report.sr-soft, body.page-stella_sales_report.sr-dark { background:var(--sr-bg) !important; color:var(--sr-text); }
body.page-stella_sales_report.sr-soft .main-wrapper, body.page-stella_sales_report.sr-dark .main-wrapper { background:var(--sr-bg) !important; }
body.page-stella_sales_report.sr-soft .topbar, body.page-stella_sales_report.sr-dark .topbar { background:var(--sr-card) !important; border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .topbar *, body.page-stella_sales_report.sr-dark .topbar * { color:var(--sr-text); }

/* cards */
body.page-stella_sales_report.sr-soft .sr-header-card, body.page-stella_sales_report.sr-dark .sr-header-card { background:linear-gradient(135deg,var(--sr-head1),var(--sr-head2)) !important; border-radius:var(--sr-radius) !important; box-shadow:0 6px 18px rgba(0,0,0,.18); }
body.page-stella_sales_report.sr-soft .sr-card, body.page-stella_sales_report.sr-soft .sr-section, body.page-stella_sales_report.sr-soft .sr-cashier-bar,
body.page-stella_sales_report.sr-dark .sr-card, body.page-stella_sales_report.sr-dark .sr-section, body.page-stella_sales_report.sr-dark .sr-cashier-bar {
  background:var(--sr-card) !important; border:1px solid var(--sr-line) !important; border-radius:var(--sr-radius) !important; box-shadow:0 3px 12px rgba(0,0,0,.10) !important; }
body.page-stella_sales_report.sr-soft .sr-cashier-bar label, body.page-stella_sales_report.sr-dark .sr-cashier-bar label { color:var(--sr-text) !important; }
body.page-stella_sales_report.sr-soft .sr-cashier-bar input, body.page-stella_sales_report.sr-dark .sr-cashier-bar input { background:var(--sr-input) !important; color:var(--sr-text) !important; border:1px solid var(--sr-input-bd) !important; border-radius:10px !important; }

/* summary column */
body.page-stella_sales_report.sr-soft .sr-title-row, body.page-stella_sales_report.sr-dark .sr-title-row { background:var(--sr-head1) !important; }
body.page-stella_sales_report.sr-soft .sr-row, body.page-stella_sales_report.sr-dark .sr-row { background:transparent; border-bottom:1px solid var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .sr-row.green, body.page-stella_sales_report.sr-dark .sr-row.green { background:var(--sr-band) !important; }
body.page-stella_sales_report.sr-soft .sr-row.green .sr-label, body.page-stella_sales_report.sr-dark .sr-row.green .sr-label, body.page-stella_sales_report.sr-soft .sr-row.green .sr-note, body.page-stella_sales_report.sr-dark .sr-row.green .sr-note { color:var(--sr-band-text) !important; }
body.page-stella_sales_report.sr-soft .sr-row.gray-hdr, body.page-stella_sales_report.sr-dark .sr-row.gray-hdr { background:var(--sr-card2) !important; }
body.page-stella_sales_report.sr-soft .sr-row.gray-hdr .sr-label, body.page-stella_sales_report.sr-dark .sr-row.gray-hdr .sr-label { color:var(--sr-text) !important; }
body.page-stella_sales_report.sr-soft .sr-row.net, body.page-stella_sales_report.sr-dark .sr-row.net { background:var(--sr-net) !important; }
body.page-stella_sales_report.sr-soft .sr-row.net .sr-label, body.page-stella_sales_report.sr-dark .sr-row.net .sr-label { color:var(--sr-net-text) !important; }
body.page-stella_sales_report.sr-soft .sr-row.coh, body.page-stella_sales_report.sr-dark .sr-row.coh { background:var(--sr-coh) !important; }
body.page-stella_sales_report.sr-soft .sr-row.short, body.page-stella_sales_report.sr-dark .sr-row.short { background:var(--sr-short) !important; }
body.page-stella_sales_report.sr-soft .sr-label, body.page-stella_sales_report.sr-dark .sr-label { color:var(--sr-text); }
body.page-stella_sales_report.sr-soft .sr-row.coh .sr-label, body.page-stella_sales_report.sr-dark .sr-row.coh .sr-label, body.page-stella_sales_report.sr-soft .sr-row.short .sr-label, body.page-stella_sales_report.sr-dark .sr-row.short .sr-label { color:#f1f5f2 !important; }
body.page-stella_sales_report.sr-soft .sr-note, body.page-stella_sales_report.sr-dark .sr-note { color:var(--sr-sub) !important; }
body.page-stella_sales_report.sr-soft .sr-row.coh .sr-note, body.page-stella_sales_report.sr-dark .sr-row.coh .sr-note, body.page-stella_sales_report.sr-soft .sr-row.short .sr-note, body.page-stella_sales_report.sr-dark .sr-row.short .sr-note { color:rgba(255,255,255,.75) !important; }

/* inputs: no glaring white */
body.page-stella_sales_report.sr-soft .sr-input, body.page-stella_sales_report.sr-dark .sr-input,
body.page-stella_sales_report.sr-soft .sr-row.coh .sr-input, body.page-stella_sales_report.sr-dark .sr-row.coh .sr-input {
  background:var(--sr-input) !important; color:var(--sr-text) !important; border:1px solid var(--sr-input-bd) !important; border-radius:9px !important; box-shadow:none !important; -webkit-text-fill-color:var(--sr-text); }
body.page-stella_sales_report.sr-soft .sr-input.readout, body.page-stella_sales_report.sr-dark .sr-input.readout { background:var(--sr-mint) !important; border-color:transparent !important; }
body.page-stella_sales_report.sr-soft .sr-row.net .sr-input.readout, body.page-stella_sales_report.sr-dark .sr-row.net .sr-input.readout { background:rgba(255,255,255,.35) !important; color:var(--sr-net-text) !important; -webkit-text-fill-color:var(--sr-net-text); }
body.page-stella_sales_report.sr-soft .sr-row.short .sr-input.readout, body.page-stella_sales_report.sr-dark .sr-row.short .sr-input.readout { background:rgba(255,255,255,.88) !important; color:#5c1119 !important; -webkit-text-fill-color:#5c1119; }
body.page-stella_sales_report.sr-dark .sr-row.short .sr-input.readout { background:rgba(20,10,12,.45) !important; color:#ffd9dd !important; -webkit-text-fill-color:#ffd9dd; }
body.page-stella_sales_report.sr-soft .sr-input:focus, body.page-stella_sales_report.sr-dark .sr-input:focus, body.page-stella_sales_report.sr-soft .sr-cashier-bar input:focus, body.page-stella_sales_report.sr-dark .sr-cashier-bar input:focus { border-color:var(--sr-focus) !important; box-shadow:0 0 0 3px rgba(47,96,73,.25) !important; outline:none; }
body.page-stella_sales_report.sr-soft .sr-input::placeholder, body.page-stella_sales_report.sr-dark .sr-input::placeholder, body.page-stella_sales_report.sr-soft .di-inp::placeholder, body.page-stella_sales_report.sr-dark .di-inp::placeholder { color:var(--sr-sub); opacity:.55; }

/* tables / sub-sections */
body.page-stella_sales_report.sr-soft .sr-section-title, body.page-stella_sales_report.sr-dark .sr-section-title { background:var(--sr-sec-bg,var(--sr-head1)) !important; letter-spacing:.1em; }
body.page-stella_sales_report.sr-soft .di-table th, body.page-stella_sales_report.sr-dark .di-table th { background:var(--sr-band) !important; color:var(--sr-band-text) !important; border-color:rgba(255,255,255,.12) !important; }
body.page-stella_sales_report.sr-soft .di-table th.sub, body.page-stella_sales_report.sr-dark .di-table th.sub { background:var(--sr-head2) !important; }
body.page-stella_sales_report.sr-soft .di-table td, body.page-stella_sales_report.sr-dark .di-table td { background:var(--sr-card); border-color:var(--sr-line) !important; color:var(--sr-text); }
body.page-stella_sales_report.sr-soft .di-table tbody tr:nth-child(odd) td:not([colspan]), body.page-stella_sales_report.sr-dark .di-table tbody tr:nth-child(odd) td:not([colspan]) { background:var(--sr-card); }
body.page-stella_sales_report.sr-soft .di-table tfoot td, body.page-stella_sales_report.sr-dark .di-table tfoot td { background:var(--sr-mint) !important; border-color:var(--sr-line) !important; color:var(--sr-text) !important; }
body.page-stella_sales_report.sr-soft .di-table tfoot td.total-label, body.page-stella_sales_report.sr-soft .di-table tfoot td.grand-total, body.page-stella_sales_report.sr-dark .di-table tfoot td.total-label, body.page-stella_sales_report.sr-dark .di-table tfoot td.grand-total { background:var(--sr-head1) !important; color:var(--sr-band-text) !important; }
body.page-stella_sales_report.sr-soft .di-inp, body.page-stella_sales_report.sr-dark .di-inp { color:var(--sr-text) !important; }
body.page-stella_sales_report.sr-soft .di-inp:focus, body.page-stella_sales_report.sr-dark .di-inp:focus { background:var(--sr-input) !important; box-shadow:inset 0 0 0 2px var(--sr-focus); }
body.page-stella_sales_report.sr-soft .sub-col, body.page-stella_sales_report.sr-dark .sub-col { border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col-title { background:var(--sr-subt-bg,var(--sr-band)) !important; color:var(--sr-subt-fg,var(--sr-band-text)) !important; }
body.page-stella_sales_report.sr-soft .sub-col-hdr, body.page-stella_sales_report.sr-dark .sub-col-hdr { background:var(--sr-mint) !important; }
body.page-stella_sales_report.sr-soft .sub-col-hdr span, body.page-stella_sales_report.sr-dark .sub-col-hdr span { color:var(--sr-text) !important; border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .sub-row, body.page-stella_sales_report.sr-dark .sub-row { border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .sub-row:hover, body.page-stella_sales_report.sr-dark .sub-row:hover { background:var(--sr-card2) !important; }
body.page-stella_sales_report.sr-soft .sub-inp, body.page-stella_sales_report.sr-dark .sub-inp { background:var(--sr-input) !important; color:var(--sr-text) !important; border:1px solid var(--sr-input-bd) !important; }
body.page-stella_sales_report.sr-soft .sub-inp:focus, body.page-stella_sales_report.sr-dark .sub-inp:focus { border-color:var(--sr-focus) !important; box-shadow:0 0 0 2px rgba(47,96,73,.25) !important; }
body.page-stella_sales_report.sr-soft .sub-footer, body.page-stella_sales_report.sr-dark .sub-footer { background:var(--sr-mint) !important; border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .sub-footer span, body.page-stella_sales_report.sr-dark .sub-footer span { color:var(--sr-text) !important; border-color:var(--sr-line) !important; }
body.page-stella_sales_report.sr-soft .btn-add-row, body.page-stella_sales_report.sr-dark .btn-add-row { background:var(--sr-band) !important; color:var(--sr-band-text) !important; }
body.page-stella_sales_report.sr-soft .sr-controls input, body.page-stella_sales_report.sr-dark .sr-controls input, body.page-stella_sales_report.sr-soft #filterForm input, body.page-stella_sales_report.sr-dark #filterForm input { background:var(--sr-input) !important; color:var(--sr-text) !important; border:1px solid var(--sr-input-bd) !important; }
body.page-stella_sales_report.sr-soft .sr-controls .btn, body.page-stella_sales_report.sr-dark .sr-controls .btn, body.page-stella_sales_report.sr-soft .btn-lock, body.page-stella_sales_report.sr-dark .btn-lock { background:var(--sr-card) !important; color:var(--sr-text) !important; border:1px solid var(--sr-input-bd) !important; }
body.page-stella_sales_report.sr-soft .sr-save-status, body.page-stella_sales_report.sr-dark .sr-save-status { color:var(--sr-sub); }

/* theme switch */
#srThemeBtn { font-family:var(--font-m); font-size:.72rem; font-weight:700; padding:7px 12px; border-radius:9px; cursor:pointer;
  background:#eef1ee; color:#1d2a22; border:1px solid #bcc8bf; }
body.page-stella_sales_report.sr-soft .sub-col:nth-child(n) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col:nth-child(n) .sub-col-title,
body.page-stella_sales_report.sr-soft .sub-col:nth-child(4n+2) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col:nth-child(4n+2) .sub-col-title,
body.page-stella_sales_report.sr-soft .sub-col:nth-child(4n+3) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col:nth-child(4n+3) .sub-col-title,
body.page-stella_sales_report.sr-soft .sub-col:nth-child(4n+4) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col:nth-child(4n+4) .sub-col-title,
body.page-stella_sales_report.sr-soft .sub-col:nth-child(4n+1) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-col:nth-child(4n+1) .sub-col-title { background:var(--sr-subt-bg,var(--sr-band)) !important; color:var(--sr-subt-fg,var(--sr-band-text)) !important; }
body.page-stella_sales_report.sr-soft .sr-row.green .sr-input:not(.readout), body.page-stella_sales_report.sr-soft .sr-row .sr-input.readout { background:var(--sr-input) !important; }
body.page-stella_sales_report.sr-soft .sub-grid.three-col .sub-col:nth-child(n) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-grid.three-col .sub-col:nth-child(n) .sub-col-title, body.page-stella_sales_report.sr-soft .sub-grid.three-col .sub-col:nth-child(2n) .sub-col-title, body.page-stella_sales_report.sr-dark .sub-grid.three-col .sub-col:nth-child(2n) .sub-col-title { background:var(--sr-subt-bg,var(--sr-band)) !important; color:var(--sr-subt-fg,var(--sr-band-text)) !important; }
body.page-stella_sales_report.sr-soft #srThemeBtn { background:#e2e0d3; color:#2b2a22; border-color:#a8a798; }
body.page-stella_sales_report.sr-soft .sr-row.short .sr-input.readout { background:rgba(226,224,211,.78) !important; }
body.page-stella_sales_report.sr-soft .sr-header-card, body.page-stella_sales_report.sr-soft .sr-header-card * { color:var(--sr-band-text); }
body.page-stella_sales_report.sr-soft .sr-title-row, body.page-stella_sales_report.sr-soft .sr-section-title, body.page-stella_sales_report.sr-soft .di-table tfoot td.total-label, body.page-stella_sales_report.sr-soft .di-table tfoot td.grand-total { color:var(--sr-sec-fg,var(--sr-band-text)) !important; }
body.page-stella_sales_report.sr-soft .sr-input, body.page-stella_sales_report.sr-soft .sub-inp, body.page-stella_sales_report.sr-soft .di-inp { font-weight:600; }
body.page-stella_sales_report.sr-soft .sr-row.green .sr-label, body.page-stella_sales_report.sr-soft .sr-row.coh .sr-label, body.page-stella_sales_report.sr-soft .sr-row.short .sr-label { color:var(--sr-band-text) !important; }
body.page-stella_sales_report.sr-dark #srThemeBtn { background:#222f28; color:#dbe6de; border-color:#33453a; }

/* plain summary rows + the "For" row (inline pale-mint) */
body.page-stella_sales_report.sr-soft .sr-row:not(.green):not(.net):not(.coh):not(.short):not(.gray-hdr),
body.page-stella_sales_report.sr-dark .sr-row:not(.green):not(.net):not(.coh):not(.short):not(.gray-hdr) { background:var(--sr-card) !important; }
body.page-stella_sales_report.sr-soft .sr-row.pink-val, body.page-stella_sales_report.sr-dark .sr-row.pink-val { background:var(--sr-card2) !important; }
body.page-stella_sales_report.sr-soft .sr-row[style*="f0fdf4"], body.page-stella_sales_report.sr-dark .sr-row[style*="f0fdf4"] { background:var(--sr-card2) !important; }
body.page-stella_sales_report.sr-soft .sr-row[style*="f0fdf4"] .sr-label, body.page-stella_sales_report.sr-dark .sr-row[style*="f0fdf4"] .sr-label { color:var(--sr-text) !important; }
body.page-stella_sales_report.sr-soft .sr-section [style*="background"], body.page-stella_sales_report.sr-dark .sr-section [style*="background"] { filter:none; }

/* ═══ Modern clean layer: calm cards, light table headers, accent bars ═══ */
body.page-stella_sales_report:is(.sr-soft,.sr-dark) { --sr-sec-fg:var(--sr-band-text); --sr-subt-fg:var(--sr-band-text); --sr-subt-bg:var(--sr-band); --sr-sec-bg:var(--sr-head1); --sr-acc:var(--sr-band); }
body.page-stella_sales_report.sr-dark { --sr-acc:#3f8f69; }
body.page-stella_sales_report.sr-soft { --sr-acc:#5a7a68; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-section { border-radius:16px !important; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.06), 0 6px 18px rgba(0,0,0,.07) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-section-title { text-align:left !important; font-size:.74rem !important; letter-spacing:.12em !important; padding:12px 16px !important; border-bottom:1px solid rgba(0,0,0,.12); }
/* Dine-in table */
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table { border-collapse:separate; border-spacing:0; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table th { background:var(--sr-band) !important; color:var(--sr-band-text) !important; font-size:.6rem !important; letter-spacing:.04em; font-weight:800; padding:9px 4px !important; border:0 !important; border-bottom:1px solid rgba(255,255,255,.14) !important; border-right:1px solid rgba(255,255,255,.14) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table th.sub { background:var(--sr-head2) !important; color:var(--sr-band-text) !important; font-size:.6rem !important; padding:5px 8px !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table td { border-right:1px solid var(--sr-line) !important; border-bottom:1px solid var(--sr-line) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table tbody tr:hover td { background:var(--sr-card2) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table tfoot td { font-weight:800; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table tfoot td.total-label, body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table tfoot td.grand-total { background:var(--sr-head1) !important; color:var(--sr-band-text) !important; border-top:0 !important; letter-spacing:.06em; }
/* Sub-section cards */
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-grid { gap:8px !important; padding:8px; background:transparent; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-col { border:1px solid var(--sr-line) !important; border-radius:12px; overflow:hidden; background:var(--sr-card); }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-col-title { text-align:left !important; font-size:.66rem !important; letter-spacing:.08em; padding:9px 10px !important; border-bottom:1px solid rgba(0,0,0,.12); }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-col-hdr { background:var(--sr-mint) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-col-hdr span { color:var(--sr-sub) !important; font-size:.62rem !important; letter-spacing:.08em; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-footer { background:var(--sr-card2) !important; border-top:1px solid var(--sr-line) !important; font-weight:800; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-inp, body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp { border-radius:8px !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .btn-add-row { border-radius:999px !important; padding:6px 14px !important; font-weight:700; letter-spacing:.02em; }
/* Summary column: light rows with an accent bar instead of heavy dark bands */
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-wrap, body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-card { border-radius:16px !important; overflow:hidden; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.green { background:var(--sr-mint) !important; box-shadow:inset 4px 0 0 var(--sr-acc); }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.green .sr-label { color:var(--sr-text) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.green .sr-note { color:var(--sr-sub) !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row:not(.green):not(.net):not(.coh):not(.short):not(.gray-hdr) .sr-label { font-weight:600; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.net { box-shadow:inset 4px 0 0 #a98f4c; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-title-row { letter-spacing:.14em; }
/* ── "Type here" glow: only fields you can type in light up; auto-filled ones stay flat ── */
body.page-stella_sales_report.sr-soft { --sr-glow:63,157,114; --sr-glow-bd:#3f9d72; }
body.page-stella_sales_report.sr-dark { --sr-glow:88,199,149; --sr-glow-bd:#58c795; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-input:not([readonly]):not(.readout),
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row .sr-input:not([readonly]):not(.readout),
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.coh .sr-input:not([readonly]),
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-cashier-bar input,
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp:not([readonly]),
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-inp:not([readonly]),
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-remarks textarea:not([readonly]) {
  border:1px solid var(--sr-glow-bd) !important;
  box-shadow:0 0 0 2px rgba(var(--sr-glow),.22), 0 0 12px rgba(var(--sr-glow),.35) !important;
  transition:box-shadow .15s, border-color .15s;
}
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp:not([readonly]), body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-table td:has(.di-inp:not([readonly])) { background:rgba(var(--sr-glow),.07); }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp:not([readonly]) { border:1px solid rgba(var(--sr-glow),.55) !important; box-shadow:inset 0 0 8px rgba(var(--sr-glow),.22) !important; border-radius:6px !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-input:not([readonly]):focus,
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-cashier-bar input:focus,
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp:focus,
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sub-inp:focus,
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-remarks textarea:focus {
  border-color:var(--sr-glow-bd) !important; box-shadow:0 0 0 3px rgba(var(--sr-glow),.45), 0 0 20px rgba(var(--sr-glow),.6) !important; outline:none; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .di-inp:not([readonly]) { width:100% !important; box-sizing:border-box; min-height:28px; padding:4px 8px !important; }
/* auto-calculated fields: flat, no border, clearly "just a value" */
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-input[readonly], body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-input.readout {
  border:1px dashed transparent !important; box-shadow:none !important; cursor:default; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row.green .sr-input[readonly], body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-row:not(.net):not(.short) .sr-input.readout { background:transparent !important; }
body.page-stella_sales_report:is(.sr-soft,.sr-dark) .sr-input[readonly]:not(#net_sales):not(#short_over) { background:transparent !important; border:1px solid transparent !important; }

body.page-stella_sales_report.sr-dark .sr-row.green .sr-input.readout { background:#2b3b32 !important; border:1px solid #3a4e42 !important; }
body.page-stella_sales_report.sr-dark .sr-row .sr-input.readout { background:#2b3b32 !important; }
</style>
<script>
// Eye-comfort theme: Soft (default) → Dark → Original. Remembered per browser.
(function () {
  var order = ['sr-soft', 'sr-dark', 'sr-orig'], names = {'sr-soft': '🌿 Soft', 'sr-dark': '🌙 Dark', 'sr-orig': '☀ Original'};
  var cur = 'sr-soft';
  try { var sv = localStorage.getItem('stellaSrTheme'); if (order.indexOf(sv) >= 0) cur = sv; } catch (e) {}
  function apply() {
    order.forEach(function (c) { document.body.classList.remove(c); });
    document.body.classList.add(cur);
    var b = document.getElementById('srThemeBtn'); if (b) b.textContent = names[cur];
  }
  window.srCycleTheme = function () {
    cur = order[(order.indexOf(cur) + 1) % order.length];
    try { localStorage.setItem('stellaSrTheme', cur); } catch (e) {}
    apply();
  };
  document.addEventListener('DOMContentLoaded', function () {
    var ctl = document.querySelector('.sr-controls');
    var btn = document.createElement('button'); btn.type = 'button'; btn.id = 'srThemeBtn'; btn.title = 'Change screen brightness / colours';
    btn.onclick = window.srCycleTheme;
    if (ctl) ctl.appendChild(btn); else document.body.appendChild(btn);
    apply();
  });
  document.body && document.body.classList.add(cur);
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

<!-- Cashier -->
<div class="sr-cashier-bar">
  <label for="cashier_name">Cashier:</label>
  <input type="text" id="cashier_name" style="text-transform:uppercase" value="<?= htmlspecialchars($vs('cashier_name')) ?>" placeholder="e.g. CHRISTINE AND JUSTINE">
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

<style>.sub-stack{display:flex;flex-direction:column;gap:14px;min-width:0}.sub-stack .sub-col{width:100%}</style>
<!-- ROW 1 (same order as the Excel): Grab (+ Marketing Pull out under it) | Deposit (Card) | Late Payment (Card) | Cash out Interest (Booky) -->
<div class="sr-section">
  <div class="sub-grid four-col" id="subsec-row1">

    <?php
    $renderCol = function ($sec, $label) use ($details) {
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
    <?php
    };
    ?>

    <div class="sub-stack">
      <?php $renderCol('grab', 'GRAB'); $renderCol('marketing_pullout', 'MARKETING PULL OUT'); ?>
    </div>
    <?php $renderCol('deposit_card', 'DEPOSIT (CARD)'); $renderCol('late_payment', 'LATE PAYMENT (CARD)'); $renderCol('cash_out_interest', 'CASH OUT INTEREST (BOOKY)'); ?>

  </div>
</div>

<!-- ROW 2: Unpaid Med/Corp. (Credit) | Paid Med/Corp. (Card) | Advance Paid (Appigo) | Gift Cert Sold (Tips) -->
<div class="sr-section">
  <div class="sub-grid four-col" id="subsec-row2">

    <?php
    $subsec = [
        'unpaid_med'        => 'UNPAID MED/CORP. (CREDIT)',
        'paid_med'          => 'PAID MED/CORP. (CARD)',
        'advance_payment'   => 'ADVANCE PAID (APPIGO)',
        'gift_cert_tips'    => 'GIFT CERT SOLD (TIPS)',
    ];
    foreach ($subsec as $sec => $label):
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

<!-- ROW 3: Expenses | GC Sponsorship | GC Sold -->
<div class="sr-section">
  <div class="sub-grid three-col" id="subsec-row3">

    <?php
    $subsec = [
        'expenses'          => 'EXPENSES',
        'gc_sponsorship'    => 'GC SPONSORSHIP',
        'gc_sold'           => 'GC SOLD',
    ];
    foreach ($subsec as $sec => $label):
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
     DINE IN SECTION (at the bottom, like the Excel)
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
        <td class="grand-total" colspan="6" id="di-grand-total">0.00</td>
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
  <div class="sr-title-row"><div class="sr-main-title">Summary Report</div></div>

  <div class="sr-row" style="background:#f0fdf4">
    <div class="sr-label" style="text-align:left;flex:1">For</div>
    <div class="sr-value-wrap"><span class="sr-input readout" style="font-size:.8rem"><?= date('n/j/Y', strtotime($fDate)) ?></span></div>
  </div>

  <!-- Green rows -->
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
  <div class="sr-row green">
    <div class="sr-label">Total Swipe: <span style="font-size:.65rem;opacity:.8">(auto: Deposit + Late Payment + Maya + Paid Med)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="total_swipe" value="<?= $fmt($v('deposit_swipe_card')+$v('late_payment_card')+$v('maya_swipe')+$paidMedCalc) ?>" readonly tabindex="-1"></div>
  </div>

  <!-- Gray sub-rows under Total Swipe -->
  <div class="sr-row gray-hdr">
    <div class="sr-label">DEPOSIT SWIPE (CARD) <span style="font-size:.65rem;opacity:.7">(auto: Deposit (Card) + Gift Cert Sold (TIPS))</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="deposit_swipe_card" value="<?= $fmt($v('deposit_swipe_card')) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row gray-hdr">
    <div class="sr-label">LATE PAYMENT (CARD) <span style="font-size:.65rem;opacity:.7">(auto from Late Payment sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="late_payment_card" value="<?= $fmt($v('late_payment_card')) ?>" readonly tabindex="-1"></div>
  </div>

  <!-- Green Maya row -->
  <div class="sr-row green">
    <div class="sr-label">Sales of the day Swipe: (MAYA) <span style="font-size:.65rem;opacity:.7">(auto from Card/Swipe/QR total)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="maya_swipe" value="<?= $fmt($v('maya_swipe')) ?>" readonly tabindex="-1" style="font-weight:800"></div>
  </div>

  <!-- Pink deduction rows -->
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
    <div class="sr-label">Marketing Pull Out <span style="font-size:.65rem;opacity:.7">(auto from Marketing Pullout)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="marketing_pull_out" value="<?= $fmt($marketingCalc) ?>" readonly tabindex="-1"></div>
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

  <!-- Net Sales -->
  <div class="sr-row net">
    <div class="sr-label">Net Sales</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="net_sales" value="<?= $fmt($v('net_sales')) ?>" readonly tabindex="-1"></div>
  </div>

  <!-- COH -->
  <div class="sr-row coh">
    <div class="sr-label">COH(Cash on Hand)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="coh" value="<?= $v('coh') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#cfd8ea">(cash sales only except SC)</div>
  </div>

  <!-- Short/Over -->
  <div class="sr-row short">
    <div class="sr-label">Short/Over</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="short_over" value="<?= $fmt($v('short_over')) ?>" readonly tabindex="-1"></div>
    <div class="sr-note" style="color:#ffd8d8">(if "-" sign means short)</div>
  </div>

  <!-- Remarks -->
  <div class="sr-remarks">
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

  const shortOver = gv('coh') - netSales;
  document.getElementById('net_sales').value = fmt(netSales);
  const soEl = document.getElementById('short_over');
  soEl.value = fmt(shortOver);
  soEl.style.color = shortOver < 0 ? '#ffd54f' : '#fff';
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
   'pcf_expenses','other_expenses','coh'].forEach(id => fd3.append(id, gv(id)));

  const res  = await fetch('stella_sales_report.php', {method:'POST',body:fd3});
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

document.addEventListener('DOMContentLoaded', () => { diChanged(); recalcDepositSwipe(); recalc(); if (REPORT_LOCKED) lockPage(); });
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