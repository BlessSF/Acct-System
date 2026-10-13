<?php
// ============================================================
//  h_carwash_sales_report.php — Hero Carwash Daily Sales Report
//  Cash Breakdown + Transactions (service price lookup, auto
//  commission/net sales) + Expenses / Unpaids / Marketing
//  sub-sections — mirrors the "JUNE.xlsx" workbook exactly.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/audit_log.php';
requireLogin();
enforceCashierAccess();

$pdo  = getPDO();
$user = currentUser();

// Only management (non-branch users) may lock/unlock a date's report.
$canLock = !isBranch();

const CW_STORE = 'HEROCARWASH';

// ── Main summary table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_report` (
    `id`                 int(11) NOT NULL AUTO_INCREMENT,
    `store_name`         varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
    `report_date`        date NOT NULL,
    `opening_cashier`    varchar(150) DEFAULT NULL,
    `closing_cashier`    varchar(150) DEFAULT NULL,
    `sold_gc`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `qr_palawan_pay`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `card_payments`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `gross_sales`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `staff_cf`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `pos_reading`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `expenses`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `unpaids`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `discounts`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `marketing_expense`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_cash`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `coh`                decimal(12,2) NOT NULL DEFAULT 0.00,
    `short_over`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_commission`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `remarks`            text DEFAULT NULL,
    `saved_by`           varchar(100) DEFAULT NULL,
    `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Cash Breakdown rows (QTY × Denomination) ───────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_cash_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
    `report_date`   date NOT NULL,
    `qty`           decimal(12,2) DEFAULT 0.00,
    `denomination`  decimal(12,2) DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Transactions (plate, service, staff, price, discount…) ─
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_transactions` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
    `report_date`   date NOT NULL,
    `plate_no`      varchar(30) DEFAULT NULL,
    `service`       varchar(100) DEFAULT NULL,
    `staff`         varchar(100) DEFAULT NULL,
    `price`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `discount`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `commission`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_sales`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `mop`           varchar(30) DEFAULT NULL,
    `remarks`       varchar(200) DEFAULT NULL,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Expenses / Unpaids detail rows ─────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_detail_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
    `report_date`   date NOT NULL,
    `section`       varchar(20) NOT NULL,
    `particular`    varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Marketing rows (particular, amount, staff, commission) ─
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_marketing_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
    `report_date`   date NOT NULL,
    `particular`    varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `staff`         varchar(100) DEFAULT NULL,
    `commission`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Services price list — now a live DB catalog instead of a static
// array, so services added via carwash_services.php show up here
// immediately (mirrors the workbook's "X" sheet VLOOKUP table). ──
require_once __DIR__ . '/carwash_services_lib.php';
ensureCarwashServicesTable($pdo);
seedCarwashServicesIfEmpty($pdo, CW_STORE);
$SERVICES = getCarwashServicesGrouped($pdo, CW_STORE);
$SERVICE_PRICE_MAP = [];
$SERVICE_LIST = [];
foreach ($SERVICES as $grp => $items) {
    foreach ($items as $name => $price) {
        $SERVICE_PRICE_MAP[$name] = $price;
        $SERVICE_LIST[] = ['name' => $name, 'price' => $price, 'category' => $grp];
    }
}

$MOP_OPTIONS = ['CASH', 'Card - Cafe', 'QR', 'GCash', 'Bank Transfer'];

// Default cash-breakdown denominations (mirrors the workbook's pre-filled rows)
$DEFAULT_DENOMS = [1000, 500, 200, 100, 50, 20, 10, 5, 0.5, 0.1, 0.05, null, null, null, null];

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
            $pdo->prepare("INSERT INTO report_locks (store_name, report_date, locked_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([CW_STORE, $reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM report_locks WHERE store_name=? AND report_date=?")
                ->execute([CW_STORE, $reportDate]);
        }
        logAudit($pdo, 'H', 'Carwash Sales Report', $lock ? 'locked' : 'unlocked', $reportDate,
            $lock ? 'Locked the carwash report for editing' : 'Unlocked the carwash report for editing');
        echo json_encode(['ok' => true, 'locked' => $lock]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save Cash Breakdown rows ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_cash'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, CW_STORE, $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM h_carwash_cash_rows WHERE store_name=? AND report_date=?")->execute([CW_STORE, $reportDate]);
        $ins = $pdo->prepare("INSERT INTO h_carwash_cash_rows (store_name,report_date,qty,denomination,sort_order) VALUES (?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([CW_STORE, $reportDate, (float)($r['qty']??0), (float)($r['denomination']??0), $i]);
        }
        logAudit($pdo, 'H', 'Carwash Sales Report', 'updated', $reportDate,
            'Updated Cash Breakdown rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Transactions rows ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_transactions'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, CW_STORE, $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM h_carwash_transactions WHERE store_name=? AND report_date=?")->execute([CW_STORE, $reportDate]);
        $ins = $pdo->prepare("INSERT INTO h_carwash_transactions
            (store_name,report_date,plate_no,service,staff,price,discount,commission,net_sales,mop,remarks,sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $price      = (float)($r['price'] ?? 0);
            $discount   = (float)($r['discount'] ?? 0);
            $commission = ($price - $discount) * 0.25;
            $netSales   = $price - $commission;
            $ins->execute([CW_STORE, $reportDate, $r['plate_no']??null, $r['service']??null, $r['staff']??null,
                $price, $discount, $commission, $netSales, $r['mop']??null, $r['remarks']??null, $i]);
        }
        logAudit($pdo, 'H', 'Carwash Sales Report', 'updated', $reportDate,
            'Updated Transactions rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Expenses / Unpaids detail rows ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_detail'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $section    = $_POST['section'];
        if (isDateLocked($pdo, CW_STORE, $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM h_carwash_detail_rows WHERE store_name=? AND report_date=? AND section=?")->execute([CW_STORE, $reportDate, $section]);
        $ins = $pdo->prepare("INSERT INTO h_carwash_detail_rows (store_name,report_date,section,particular,amount,sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([CW_STORE, $reportDate, $section, $r['particular']??null, (float)($r['amount']??0), $i]);
        }
        logAudit($pdo, 'H', 'Carwash Sales Report', 'updated', $reportDate,
            'Updated "' . $section . '" rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Marketing rows ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_marketing'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, CW_STORE, $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM h_carwash_marketing_rows WHERE store_name=? AND report_date=?")->execute([CW_STORE, $reportDate]);
        $ins = $pdo->prepare("INSERT INTO h_carwash_marketing_rows (store_name,report_date,particular,amount,staff,commission,sort_order) VALUES (?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([CW_STORE, $reportDate, $r['particular']??null, (float)($r['amount']??0), $r['staff']??null, (float)($r['commission']??0), $i]);
        }
        logAudit($pdo, 'H', 'Carwash Sales Report', 'updated', $reportDate,
            'Updated Marketing rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save main summary ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (isDateLocked($pdo, CW_STORE, $reportDate)) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask management to unlock it before editing.']);
            exit;
        }

        $openingCashier = trim((string)($_POST['opening_cashier'] ?? ''));
        $closingCashier = trim((string)($_POST['closing_cashier'] ?? ''));
        $soldGc         = (float)($_POST['sold_gc'] ?? 0);
        $qrPalawanPay   = (float)($_POST['qr_palawan_pay'] ?? 0);
        $cardPayments   = (float)($_POST['card_payments'] ?? 0);
        $remarks        = trim((string)($_POST['remarks'] ?? ''));

        // Transactions totals — PRICE / (PRICE-DISCOUNT)*25% / PRICE-COMMISSION
        $txStmt = $pdo->prepare("SELECT COALESCE(SUM(price),0) AS price, COALESCE(SUM(discount),0) AS discount
            FROM h_carwash_transactions WHERE store_name=? AND report_date=?");
        $txStmt->execute([CW_STORE, $reportDate]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC) ?: ['price'=>0,'discount'=>0];
        $totalPrice    = (float)$tx['price'];
        $totalDiscount = (float)$tx['discount'];
        $staffCf       = ($totalPrice - $totalDiscount) * 0.25; // STAFF CF (25% commission fee)

        // Expenses / Unpaids totals
        $detStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM h_carwash_detail_rows WHERE store_name=? AND report_date=? GROUP BY section");
        $detStmt->execute([CW_STORE, $reportDate]);
        $detTotals = [];
        foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $detTotals[$r['section']] = (float)$r['total'];
        $expenses = $detTotals['expenses'] ?? 0;
        $unpaids  = $detTotals['unpaids'] ?? 0;

        // Marketing totals (amount + commission)
        $mktStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS amount, COALESCE(SUM(commission),0) AS commission
            FROM h_carwash_marketing_rows WHERE store_name=? AND report_date=?");
        $mktStmt->execute([CW_STORE, $reportDate]);
        $mkt = $mktStmt->fetch(PDO::FETCH_ASSOC) ?: ['amount'=>0,'commission'=>0];
        $marketingExpense    = (float)$mkt['amount'];
        $marketingCommission = (float)$mkt['commission'];

        // Cash Breakdown total = COH (Cash on Hand)
        $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(qty*denomination),0) AS total
            FROM h_carwash_cash_rows WHERE store_name=? AND report_date=?");
        $cashStmt->execute([CW_STORE, $reportDate]);
        $coh = (float)$cashStmt->fetchColumn();

        // POS READING (mirrors Excel B33 = I34 + P55): total service price + marketing amount
        $posReading = $totalPrice + $marketingExpense;
        // GROSS SALES (mirrors Excel B30 = B33 - B31): POS Reading - Staff CF
        $grossSales = $posReading - $staffCf;
        // Net Cash (mirrors Excel B40)
        $netCash = $posReading - $expenses - $unpaids - $totalDiscount - $marketingExpense - $qrPalawanPay - $cardPayments;
        // (Short)Over (mirrors Excel B44 = B41 - B40)
        $shortOver = $coh - $netCash;
        // Total Commission (shown next to the transactions table: STAFF CF + Marketing commission)
        $totalCommission = $staffCf + $marketingCommission;

        $fields = ['store_name','report_date','opening_cashier','closing_cashier','sold_gc',
            'qr_palawan_pay','card_payments','gross_sales','staff_cf','pos_reading','expenses',
            'unpaids','discounts','marketing_expense','net_cash','coh','short_over',
            'total_commission','remarks','saved_by'];
        $data = [CW_STORE, $reportDate, $openingCashier, $closingCashier, $soldGc,
            $qrPalawanPay, $cardPayments, $grossSales, $staffCf, $posReading, $expenses,
            $unpaids, $totalDiscount, $marketingExpense, $netCash, $coh, $shortOver,
            $totalCommission, $remarks, $user['name']];
        $dupFields = array_diff($fields, ['store_name','report_date']);
        $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $dupFields));
        $sql = "INSERT INTO h_carwash_report (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ")
                VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")
                ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute($data);

        // ── Push mapped totals into the Carwash Summary Report ──────
        // Every time this day's Sales Report is saved, mirror the relevant
        // totals into h_carwash_summary_entries so the Summary page's
        // "AUTO-CALCULATED" / "SALES-INCOME" columns fill in automatically.
        // Only the columns below have a real 1:1 source in this report —
        // Deposit Swipe, Late Payment, Cancelled Transaction, and Paid have
        // no equivalent here and stay manually-entered on the Summary page.
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_summary_entries` (
                `id`                     int(11) NOT NULL AUTO_INCREMENT,
                `report_date`            date NOT NULL,
                `store_name`             varchar(100) NOT NULL DEFAULT 'HEROCARWASH',
                `gross_sales_excl_mktg`  decimal(12,2) DEFAULT 0.00,
                `store_gross_excl_mktg`  decimal(12,2) DEFAULT 0.00,
                `z_reading_gross`        decimal(12,2) DEFAULT 0.00,
                `cash_for_depo`          decimal(12,2) DEFAULT 0.00,
                `sales_of_day_swipe`     decimal(12,2) DEFAULT 0.00,
                `deposit_swipe`          decimal(12,2) DEFAULT 0.00,
                `late_payment`           decimal(12,2) DEFAULT 0.00,
                `cancelled_transaction`  decimal(12,2) DEFAULT 0.00,
                `unpaid`                 decimal(12,2) DEFAULT 0.00,
                `paid`                   decimal(12,2) DEFAULT 0.00,
                `advance_payment`        decimal(12,2) DEFAULT 0.00,
                `grab`                   decimal(12,2) DEFAULT 0.00,
                `bank_trans`             decimal(12,2) DEFAULT 0.00,
                `gc_sponsor_marketing`   decimal(12,2) DEFAULT 0.00,
                `gc_sold`                decimal(12,2) DEFAULT 0.00,
                `discount`               decimal(12,2) DEFAULT 0.00,
                `marketing_pull_out`     decimal(12,2) DEFAULT 0.00,
                `personal`               decimal(12,2) DEFAULT 0.00,
                `expenses`               decimal(12,2) DEFAULT 0.00,
                `other_expenses`         decimal(12,2) DEFAULT 0.00,
                `sc_for_depo`            decimal(12,2) DEFAULT 0.00,
                `total_deductions`       decimal(12,2) DEFAULT 0.00,
                `short_over`             decimal(12,2) DEFAULT 0.00,
                `total_swipe`            decimal(12,2) DEFAULT 0.00,
                `cash_deposit`           decimal(12,2) DEFAULT 0.00,
                `other_sales`            decimal(12,2) DEFAULT 0.00,
                `remarks`                text DEFAULT NULL,
                `remarks2`               text DEFAULT NULL,
                `saved_by`               varchar(100) DEFAULT NULL,
                `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
                KEY `idx_date` (`report_date`),
                KEY `idx_store` (`store_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            // Table may already exist from before this change — add any
            // missing columns instead of dropping/losing saved data.
            foreach ([
                'grab'                 => "decimal(12,2) DEFAULT 0.00",
                'bank_trans'           => "decimal(12,2) DEFAULT 0.00",
                'gc_sponsor_marketing' => "decimal(12,2) DEFAULT 0.00",
                'gc_sold'              => "decimal(12,2) DEFAULT 0.00",
                'discount'             => "decimal(12,2) DEFAULT 0.00",
                'marketing_pull_out'   => "decimal(12,2) DEFAULT 0.00",
                'personal'             => "decimal(12,2) DEFAULT 0.00",
                'expenses'             => "decimal(12,2) DEFAULT 0.00",
                'other_expenses'       => "decimal(12,2) DEFAULT 0.00",
                'sc_for_depo'          => "decimal(12,2) DEFAULT 0.00",
                'total_deductions'     => "decimal(12,2) DEFAULT 0.00",
                'short_over'           => "decimal(12,2) DEFAULT 0.00",
                'total_swipe'          => "decimal(12,2) DEFAULT 0.00",
                'cash_deposit'         => "decimal(12,2) DEFAULT 0.00",
                'other_sales'          => "decimal(12,2) DEFAULT 0.00",
                'remarks2'             => "text DEFAULT NULL",
            ] as $col => $def) {
                try { $pdo->exec("ALTER TABLE h_carwash_summary_entries ADD COLUMN IF NOT EXISTS `$col` $def"); } catch (Throwable $ignored) {}
            }

            $summaryMapped = [
                // Z Reading Gross (Incl SC/Mktg) — this IS the POS/Z reading.
                'z_reading_gross'       => $posReading,
                // Gross Sales Excl Mktg — this report's own Gross Sales
                // figure (POS Reading − Staff CF), mirrored as-is.
                'gross_sales_excl_mktg' => $grossSales,
                // Store Gross (Excl SC/Mktg) — same, further excluding discounts.
                'store_gross_excl_mktg' => $grossSales - $marketingExpense - $totalDiscount,
                // Cash for Depo — the computed cash that should be deposited.
                'cash_for_depo'         => $netCash,
                // Sales of the Day (Swipe) — today's card-swipe sales.
                'sales_of_day_swipe'    => $cardPayments,
                // Unpaid — today's unpaid total from the Expenses/Unpaids section.
                'unpaid'                => $unpaids,
                // Advance Payment — Sold GC is money collected in advance
                // for services not yet rendered.
                'advance_payment'       => $soldGc,
                // Discount, Marketing Pull Out, Expenses — same totals this
                // report already computes for its own Gross Sales / Net Cash math.
                'discount'              => $totalDiscount,
                'marketing_pull_out'    => $marketingExpense,
                'expenses'              => $expenses,
                // Short/Over — this report's own cash-reconciliation figure
                // (Cash on Hand − Net Cash), mirrored as-is.
                'short_over'            => $shortOver,
            ];
            $sumFields = array_merge(['store_name','report_date'], array_keys($summaryMapped));
            $sumData   = array_merge([CW_STORE, $reportDate], array_values($summaryMapped));
            $sumUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", array_keys($summaryMapped)));
            $sumSql = "INSERT INTO h_carwash_summary_entries (" . implode(',', array_map(fn($f)=>"`$f`",$sumFields)) . ")
                       VALUES (" . implode(',', array_fill(0,count($sumFields),'?')) . ")
                       ON DUPLICATE KEY UPDATE $sumUpdate";
            $pdo->prepare($sumSql)->execute($sumData);
        } catch (Throwable $ignored) {}

        logAudit($pdo, 'H', 'Carwash Sales Report', 'updated', $reportDate, 'Saved daily summary totals',
            ['gross_sales' => $grossSales, 'net_cash' => $netCash, 'short_over' => $shortOver]);
        echo json_encode(['ok'=>true,
            'gross_sales'=>$grossSales, 'staff_cf'=>$staffCf, 'pos_reading'=>$posReading,
            'net_cash'=>$netCash, 'coh'=>$coh, 'short_over'=>$shortOver,
            'total_commission'=>$totalCommission]);
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
    $stmt = $pdo->prepare("SELECT * FROM h_carwash_report WHERE store_name=? AND report_date=?");
    $stmt->execute([CW_STORE, $reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');

    $filename = 'HeroCarwash_SalesReport_'.$reportDate.'.xlsx';

    $xlsx = new SimpleXlsxWriter(salesReportAccentColor('H Carwash'));
    $xlsx->title('Hero Carwash — Daily Sales Report');
    $xlsx->subtitle(date('F j, Y', strtotime($reportDate))
        . ' · Opening Cashier: ' . (string)($r['opening_cashier'] ?? '—')
        . ' · Closing Cashier: ' . (string)($r['closing_cashier'] ?? '—'));
    $xlsx->blank();

    // — TRANSACTIONS —
    $xlsx->sectionRow('TRANSACTIONS');
    $xlsx->header(['No.','Plate No.','Service','Staff','Price','Discount','Commission Fee 25%','Net Sales','MOP','Remarks']);
    $txStmt = $pdo->prepare("SELECT * FROM h_carwash_transactions WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
    $txStmt->execute([CW_STORE, $reportDate]);
    $n = 1;
    foreach ($txStmt->fetchAll(PDO::FETCH_ASSOC) as $tx) {
        $xlsx->row([$n++, $tx['plate_no'], $tx['service'], $tx['staff'],
            number_format((float)$tx['price'],2,'.',''), number_format((float)$tx['discount'],2,'.',''),
            number_format((float)$tx['commission'],2,'.',''), number_format((float)$tx['net_sales'],2,'.',''),
            $tx['mop'], $tx['remarks']]);
    }
    $xlsx->blank();

    // — CASH BREAKDOWN —
    $xlsx->sectionRow('CASH BREAKDOWN');
    $xlsx->header(['Qty','Denomination','Total Collection']);
    $cashStmt = $pdo->prepare("SELECT * FROM h_carwash_cash_rows WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
    $cashStmt->execute([CW_STORE, $reportDate]);
    foreach ($cashStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
        $xlsx->row([(float)$cr['qty'] ?: '', (float)$cr['denomination'] ?: '',
            number_format((float)$cr['qty'] * (float)$cr['denomination'], 2, '.', '')]);
    }
    $xlsx->blank();

    // — EXPENSES — / — UNPAIDS —
    foreach (['expenses'=>'EXPENSES','unpaids'=>'UNPAIDS'] as $sec => $label) {
        $xlsx->sectionRow($label);
        $xlsx->header(['Particular','Amount']);
        $ds = $pdo->prepare("SELECT * FROM h_carwash_detail_rows WHERE store_name=? AND report_date=? AND section=? ORDER BY sort_order ASC");
        $ds->execute([CW_STORE, $reportDate, $sec]);
        foreach ($ds->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $xlsx->row([$d['particular'], number_format((float)$d['amount'],2,'.','')]);
        }
        $xlsx->blank();
    }

    // — MARKETING —
    $xlsx->sectionRow('MARKETING');
    $xlsx->header(['Particular','Amount','Staff','Commission']);
    $ms = $pdo->prepare("SELECT * FROM h_carwash_marketing_rows WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
    $ms->execute([CW_STORE, $reportDate]);
    foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $xlsx->row([$m['particular'], number_format((float)$m['amount'],2,'.',''), $m['staff'], number_format((float)$m['commission'],2,'.','')]);
    }
    $xlsx->blank();

    // — SUMMARY REPORT —
    $xlsx->sectionRow('SUMMARY REPORT');
    $xlsx->header(['Particular', 'Amount']);
    $xlsx->row(['Gross Sales', $g('gross_sales')]);
    $xlsx->row(['Staff CF', $g('staff_cf')]);
    $xlsx->row(['Sold GC', $g('sold_gc')]);
    $xlsx->row(['POS Reading', $g('pos_reading')]);
    $xlsx->row(['Expenses', $g('expenses')]);
    $xlsx->row(['Unpaids', $g('unpaids')]);
    $xlsx->row(['Discounts', $g('discounts')]);
    $xlsx->row(['Marketing Expense', $g('marketing_expense')]);
    $xlsx->row(['QR/Palawan Pay', $g('qr_palawan_pay')]);
    $xlsx->row(['Card Payments', $g('card_payments')]);
    $xlsx->totalRow(['Net Cash', $g('net_cash')]);
    $xlsx->totalRow(['COH (Cash on Hand)', $g('coh')]);
    $xlsx->totalRow(['(Short)Over', $g('short_over')]);
    $xlsx->totalRow(['Total Commission', $g('total_commission')]);
    $xlsx->row(['Remarks', (string)($r['remarks'] ?? '')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([8, 14, 18, 16, 12, 12, 16, 14, 10, 22]);
    $xlsx->output($filename);
}

// ── Fetch data for display ─────────────────────────────────
$fDate  = $_GET['date'] ?? date('Y-m-d');
$locked = isDateLocked($pdo, CW_STORE, $fDate);
$stmt   = $pdo->prepare("SELECT * FROM h_carwash_report WHERE store_name=? AND report_date=?");
$stmt->execute([CW_STORE, $fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$vs  = fn($k) => (string)($row[$k] ?? '');
$fmt = fn($n)  => number_format((float)$n, 2);

// Transactions
$txStmt = $pdo->prepare("SELECT * FROM h_carwash_transactions WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
$txStmt->execute([CW_STORE, $fDate]);
$txRows = $txStmt->fetchAll(PDO::FETCH_ASSOC);
$txTotalPrice    = array_sum(array_column($txRows, 'price'));
$txTotalDiscount = array_sum(array_column($txRows, 'discount'));
$txTotalCommission = ($txTotalPrice - $txTotalDiscount) * 0.25;
$txTotalNetSales = $txTotalPrice - $txTotalCommission;

// Cash breakdown
$cashStmt = $pdo->prepare("SELECT * FROM h_carwash_cash_rows WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
$cashStmt->execute([CW_STORE, $fDate]);
$cashRows = $cashStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($cashRows)) {
    // Seed with the workbook's default denominations (empty rows for QTY)
    $cashRows = array_map(fn($d) => ['qty'=>0, 'denomination'=>$d], $DEFAULT_DENOMS);
}
$cashTotal = 0;
foreach ($cashRows as $cr) $cashTotal += (float)$cr['qty'] * (float)$cr['denomination'];

// Expenses / Unpaids detail rows
$detStmt = $pdo->prepare("SELECT * FROM h_carwash_detail_rows WHERE store_name=? AND report_date=? ORDER BY section, sort_order ASC");
$detStmt->execute([CW_STORE, $fDate]);
$allDetails = $detStmt->fetchAll(PDO::FETCH_ASSOC);
$details = [];
foreach ($allDetails as $d) $details[$d['section']][] = $d;
$expensesTotal = array_sum(array_column($details['expenses'] ?? [], 'amount'));
$unpaidsTotal  = array_sum(array_column($details['unpaids'] ?? [], 'amount'));

// Marketing rows
$mktStmt = $pdo->prepare("SELECT * FROM h_carwash_marketing_rows WHERE store_name=? AND report_date=? ORDER BY sort_order ASC");
$mktStmt->execute([CW_STORE, $fDate]);
$mktRows = $mktStmt->fetchAll(PDO::FETCH_ASSOC);
$mktAmountTotal     = array_sum(array_column($mktRows, 'amount'));
$mktCommissionTotal = array_sum(array_column($mktRows, 'commission'));

// Summary calcs (mirrors the workbook's formula chain)
$posReadingCalc  = $txTotalPrice + $mktAmountTotal;
$grossSalesCalc  = $posReadingCalc - $txTotalCommission;
$netCashCalc     = $posReadingCalc - $expensesTotal - $unpaidsTotal - $txTotalDiscount - $mktAmountTotal - $v('qr_palawan_pay') - $v('card_payments');
$shortOverCalc   = $cashTotal - $netCashCalc;
$totalCommissionCalc = $txTotalCommission + $mktCommissionTotal;

$pageTitle  = 'Hero Carwash Sales Report';
$activePage = 'h_carwash_sales_report';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

<!-- Shared floating panel for the Service search dropdown (positioned via JS) -->
<div id="cw-service-dropdown"></div>

<!-- Header -->
<div class="cw-header-card">
  <div>
    <div class="eyebrow">Hero Carwash · Sales</div>
    <div class="title">🚗 Hero Carwash Daily Sales Report</div>
    <div class="subtitle">Cash Breakdown + Transactions · Commission &amp; Net Sales auto-calculated</div>
  </div>
  <span style="background:rgba(255,255,255,.15);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">📌 HERO CARWASH</span>
</div>

<?php if ($locked): ?>
<div class="locked-banner">
  🔒 <?= date('M j, Y', strtotime($fDate)) ?> is locked by management. All fields on this page are read-only.
</div>
<?php endif; ?>

<!-- Opening / Closing Cashier -->
<div class="sr-oc-bar">
  <div class="sr-oc-field">
    <label for="opening_cashier">Opening Cashier:</label>
    <input type="text" id="opening_cashier" style="text-transform:uppercase" value="<?= htmlspecialchars($vs('opening_cashier')) ?>" placeholder="e.g. HENCEL">
  </div>
  <div class="sr-oc-field">
    <label for="closing_cashier">Closing Cashier:</label>
    <input type="text" id="closing_cashier" style="text-transform:uppercase" value="<?= htmlspecialchars($vs('closing_cashier')) ?>" placeholder="e.g. HENCEL">
  </div>
</div>

<!-- Controls -->
<div class="sr-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
    <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
    <a href="h_carwash_sales_report.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download Excel</a>
    <a href="carwash_services.php" class="btn btn-ghost">⚙ Manage Services</a>
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
            ? 'This will make the Carwash Sales Report editable again for this date.'
            : 'This will make the Carwash Sales Report read-only for this date. No one will be able to edit or save changes until it’s unlocked.' ?>
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

<div class="cw-main-grid">
<!-- ══════════════════════════════════════════════════════════
     LEFT COLUMN: Cash Breakdown + Summary Report
════════════════════════════════════════════════════════════ -->
<div>

  <div class="sr-section">
    <div class="sr-section-title dark-red">Cash Breakdown</div>
    <div style="overflow-x:auto">
    <table class="di-table cash-table" id="cash-table">
      <thead>
        <tr><th>Qty</th><th>Denomination</th><th>Total Collection</th><th style="width:32px"></th></tr>
      </thead>
      <tbody id="cash-body">
        <?php foreach ($cashRows as $cr): $tot = (float)$cr['qty'] * (float)$cr['denomination']; ?>
        <tr>
          <td><input class="di-inp" type="number" step="0.01" value="<?= (float)$cr['qty'] ?: '' ?>" placeholder="" oninput="cashChanged()"></td>
          <td><input class="di-inp" type="number" step="0.01" value="<?= $cr['denomination']!==null ? (float)$cr['denomination'] : '' ?>" placeholder="" oninput="cashChanged()"></td>
          <td class="cash-row-total"><?= $fmt($tot) ?></td>
          <td><button class="btn-del-row" onclick="delCashRow(this)">✕</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="total-label" colspan="2">TOTAL</td>
          <td class="grand-total" id="cash-grand-total"><?= $fmt($cashTotal) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <button class="btn-add-row" onclick="addCashRow()">+ Add Row</button>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SUMMARY REPORT
  ═══════════════════════════════════════════════════════ -->
  <div class="sr-card">
    <div class="sr-title-row"><div class="sr-main-title">Summary Report</div></div>

    <div class="sr-row" style="background:#f8f9fb">
      <div class="sr-label" style="text-align:left;flex:1">For</div>
      <div class="sr-value-wrap"><span class="sr-input readout" style="font-size:.78rem"><?= date('n/j/Y', strtotime($fDate)) ?></span></div>
    </div>

    <div class="sr-row tan">
      <div class="sr-label">Gross Sales <span style="font-size:.62rem;opacity:.7">(auto: POS Reading − Staff CF)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gross_sales" value="<?= $fmt($grossSalesCalc) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row tan">
      <div class="sr-label">Staff CF <span style="font-size:.62rem;opacity:.7">(auto: Total Commission Fee 25%)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="staff_cf" value="<?= $fmt($txTotalCommission) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row tan">
      <div class="sr-label">Sold GC</div>
      <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="sold_gc" value="<?= $v('sold_gc') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    </div>
    <div class="sr-row tan">
      <div class="sr-label">POS Reading <span style="font-size:.62rem;opacity:.7">(auto: Total Price + Marketing)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="pos_reading" value="<?= $fmt($posReadingCalc) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row pink-val">
      <div class="sr-label">Expenses <span style="font-size:.62rem;opacity:.7">(auto from Expenses)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="expenses" value="<?= $fmt($expensesTotal) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row pink-val">
      <div class="sr-label">Unpaids <span style="font-size:.62rem;opacity:.7">(auto from Unpaids)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="unpaids" value="<?= $fmt($unpaidsTotal) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row pink-val">
      <div class="sr-label">Discounts <span style="font-size:.62rem;opacity:.7">(auto from Discount col)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="discounts" value="<?= $fmt($txTotalDiscount) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row pink-val">
      <div class="sr-label">Marketing Expense <span style="font-size:.62rem;opacity:.7">(auto from Marketing)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="marketing_expense" value="<?= $fmt($mktAmountTotal) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row" style="background:#eef2f7">
      <div class="sr-label">QR/Palawan Pay</div>
      <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="qr_palawan_pay" value="<?= $v('qr_palawan_pay') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    </div>
    <div class="sr-row" style="background:#eef2f7">
      <div class="sr-label">Card Payments</div>
      <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="card_payments" value="<?= $v('card_payments') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    </div>

    <div class="sr-row net">
      <div class="sr-label">Net Cash</div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="net_cash" value="<?= $fmt($netCashCalc) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row coh">
      <div class="sr-label">COH (Cash on Hand) <span style="font-size:.62rem;opacity:.7">(auto from Cash Breakdown)</span></div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="coh" value="<?= $fmt($cashTotal) ?>" readonly tabindex="-1"></div>
    </div>
    <div class="sr-row short">
      <div class="sr-label">(Short)Over</div>
      <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="short_over" value="<?= $fmt($shortOverCalc) ?>" readonly tabindex="-1"></div>
    </div>

    <div class="sr-row" style="flex-direction:column;align-items:stretch;background:#f8f9fb">
      <div class="sr-label" style="text-align:left;padding-right:0;margin-bottom:6px">Remarks</div>
      <textarea class="sr-remarks" id="remarks" placeholder="Optional notes…"><?= htmlspecialchars($vs('remarks')) ?></textarea>
    </div>
  </div>

  <div style="font-family:var(--font-h);font-size:.68rem;color:#b91c1c;font-style:italic;margin-top:10px">
    Only the gray/editable cells need to be filled out — everything else auto-calculates.
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     RIGHT COLUMN: Transactions + Expenses/Unpaids/Marketing
════════════════════════════════════════════════════════════ -->
<div>

  <div class="sr-section">
    <div class="sr-section-title dark-red">Transactions</div>
    <div style="overflow-x:auto">
    <table class="di-table" id="tx-table">
      <thead>
        <tr>
          <th style="width:36px">No.</th>
          <th>Plate No.</th>
          <th>Services</th>
          <th>Staff</th>
          <th>Price</th>
          <th>Discount</th>
          <th>Commission Fee 25%</th>
          <th>Net Sales</th>
          <th>MOP</th>
          <th>Remarks</th>
          <th style="width:32px"></th>
        </tr>
      </thead>
      <tbody id="tx-body">
        <?php foreach ($txRows as $i => $tx): ?>
        <tr>
          <td class="tx-no" style="text-align:center"><?= $i+1 ?></td>
          <td><input class="di-inp txt" type="text" value="<?= htmlspecialchars($tx['plate_no'] ?? '') ?>" placeholder="Plate No."></td>
          <td>
            <input class="di-inp tx-service" type="text" autocomplete="off"
                   value="<?= htmlspecialchars($tx['service'] ?? '') ?>" placeholder="🔍 Search service…">
          </td>
          <td><input class="di-inp txt" type="text" value="<?= htmlspecialchars($tx['staff'] ?? '') ?>" placeholder="Staff"></td>
          <td><input class="di-inp tx-price" type="number" step="0.01" value="<?= (float)$tx['price'] ?: '' ?>" readonly tabindex="-1"></td>
          <td><input class="di-inp tx-discount" type="number" step="0.01" value="<?= (float)$tx['discount'] ?: '' ?>" placeholder="" oninput="txChanged()"></td>
          <td><input class="di-inp tx-commission" type="number" step="0.01" value="<?= $fmt($tx['commission']) ?>" readonly tabindex="-1"></td>
          <td><input class="di-inp tx-net" type="number" step="0.01" value="<?= $fmt($tx['net_sales']) ?>" readonly tabindex="-1"></td>
          <td>
            <select class="di-inp">
              <?php foreach ($MOP_OPTIONS as $mop): ?>
              <option value="<?= htmlspecialchars($mop) ?>" <?= ($tx['mop'] ?? 'CASH') === $mop ? 'selected' : '' ?>><?= htmlspecialchars($mop) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input class="di-inp txt" type="text" value="<?= htmlspecialchars($tx['remarks'] ?? '') ?>" placeholder=""></td>
          <td><button class="btn-del-row" onclick="delTxRow(this)">✕</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="total-label" colspan="4">TOTAL</td>
          <td id="tx-tot-price"><?= $fmt($txTotalPrice) ?></td>
          <td id="tx-tot-discount"><?= $fmt($txTotalDiscount) ?></td>
          <td id="tx-tot-commission"><?= $fmt($txTotalCommission) ?></td>
          <td id="tx-tot-net"><?= $fmt($txTotalNetSales) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <button class="btn-add-row" onclick="addTxRow()">+ Add Row</button>
  </div>

  <div class="total-commission-box">
    <span class="label">Total Commission (Staff + Marketing)</span>
    <span class="value" id="total-commission-display">₱<?= $fmt($totalCommissionCalc) ?></span>
  </div>

  <!-- ══════════════════════════════════════════════════════
       EXPENSES | UNPAIDS
  ═══════════════════════════════════════════════════════ -->
  <div class="sr-section">
    <div class="sub-grid" id="subsec-row1">
      <?php
      $subsec1 = ['expenses' => 'EXPENSES', 'unpaids' => 'UNPAIDS'];
      foreach ($subsec1 as $sec => $label):
          $secRows = $details[$sec] ?? [['particular'=>'','amount'=>0]];
      ?>
      <div class="sub-col" data-section="<?= $sec ?>">
        <div class="sub-col-title"><?= $label ?></div>
        <div class="sub-col-hdr"><span>Particular</span><span>Amount</span><span></span></div>
        <div class="sub-rows">
          <?php foreach ($secRows as $sr): ?>
          <div class="sub-row">
            <input class="sub-inp" type="text" placeholder="Enter particular…" value="<?= htmlspecialchars($sr['particular'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
            <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$sr['amount'] ?: '' ?>" oninput="subChanged('<?= $sec ?>')">
            <button class="btn-del-row" onclick="delSubRow(this,'<?= $sec ?>')" style="margin:2px 4px">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="btn-add-row" onclick="addSubRow('<?= $sec ?>')">+ Add</button>
        <div class="sub-footer">
          <span>Total</span>
          <span id="sub-tot-<?= $sec ?>">₱<?= number_format(array_sum(array_column($secRows,'amount')),2) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       MARKETING
  ═══════════════════════════════════════════════════════ -->
  <div class="sr-section">
    <div class="sr-section-title dark-gold">Marketing</div>
    <div class="mkt-hdr"><span>Particular</span><span>Amount</span><span>Staff</span><span>Commission</span><span></span></div>
    <div id="mkt-rows">
      <?php $mktSeed = empty($mktRows) ? [['particular'=>'','amount'=>0,'staff'=>'','commission'=>0]] : $mktRows; ?>
      <?php foreach ($mktSeed as $mr): ?>
      <div class="mkt-row">
        <input class="sub-inp" type="text" placeholder="Enter particular…" value="<?= htmlspecialchars($mr['particular'] ?? '') ?>" oninput="mktChanged()">
        <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$mr['amount'] ?: '' ?>" oninput="mktChanged()">
        <input class="sub-inp" type="text" placeholder="Staff" value="<?= htmlspecialchars($mr['staff'] ?? '') ?>" oninput="mktChanged()">
        <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$mr['commission'] ?: '' ?>" oninput="mktChanged()">
        <button class="btn-del-row" onclick="delMktRow(this)" style="margin:2px 4px">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn-add-row" onclick="addMktRow()">+ Add</button>
    <div class="mkt-footer">
      <span>Total</span>
      <span id="mkt-tot-amount">₱<?= $fmt($mktAmountTotal) ?></span>
      <span></span>
      <span id="mkt-tot-commission">₱<?= $fmt($mktCommissionTotal) ?></span>
    </div>
  </div>

</div>
</div>

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
const FDATE = <?= json_encode($fDate) ?>;
const REPORT_LOCKED = <?= $locked ? 'true' : 'false' ?>;
const fmt = n => (parseFloat(n)||0).toFixed(2);
function gv(id) { const el = document.getElementById(id); return el ? (parseFloat(el.value)||0) : 0; }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

// ── Service → Price lookup map (mirrors the workbook's VLOOKUP table) ──
const SERVICE_PRICES = <?= json_encode($SERVICE_PRICE_MAP) ?>;
const SERVICE_LIST   = <?= json_encode($SERVICE_LIST) ?>;

// ── Cash Breakdown ──────────────────────────────────────────
function cashRowData(tr) {
  const inps = tr.querySelectorAll('input');
  return { qty: parseFloat(inps[0]?.value)||0, denomination: parseFloat(inps[1]?.value)||0 };
}
function cashChanged() {
  let total = 0;
  document.querySelectorAll('#cash-body tr').forEach(tr => {
    const d = cashRowData(tr);
    const rowTotal = d.qty * d.denomination;
    tr.querySelector('.cash-row-total').textContent = fmt(rowTotal);
    total += rowTotal;
  });
  document.getElementById('cash-grand-total').textContent = fmt(total);
  setVal('coh', fmt(total));
  recalc();
}
function addCashRow() {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="cashChanged()"></td>
    <td><input class="di-inp" type="number" step="0.01" placeholder="" oninput="cashChanged()"></td>
    <td class="cash-row-total">0.00</td>
    <td><button class="btn-del-row" onclick="delCashRow(this)">✕</button></td>`;
  document.getElementById('cash-body').appendChild(tr);
}
function delCashRow(btn) { btn.closest('tr').remove(); cashChanged(); }

// ── Transactions ────────────────────────────────────────────
function renumberTx() {
  document.querySelectorAll('#tx-body tr').forEach((tr, i) => {
    tr.querySelector('.tx-no').textContent = i + 1;
  });
}
// ── Custom Service Search Dropdown (replaces native <select>/<datalist>) ──
let activeServiceInput = null;

function escHtml(s) {
  return (s + '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
function highlightMatch(name, q) {
  if (!q) return escHtml(name);
  const idx = name.toLowerCase().indexOf(q);
  if (idx === -1) return escHtml(name);
  return escHtml(name.slice(0, idx)) + '<mark>' + escHtml(name.slice(idx, idx + q.length)) + '</mark>' + escHtml(name.slice(idx + q.length));
}

function positionServiceDropdown(input) {
  const dd = document.getElementById('cw-service-dropdown');
  const rect = input.getBoundingClientRect();
  const vh = window.innerHeight;
  dd.style.left  = rect.left + 'px';
  dd.style.width = Math.max(rect.width, 240) + 'px';
  const spaceBelow = vh - rect.bottom;
  if (spaceBelow < 260 && rect.top > 260) {
    dd.style.top = 'auto';
    dd.style.bottom = (vh - rect.top + 4) + 'px';
  } else {
    dd.style.bottom = 'auto';
    dd.style.top = (rect.bottom + 4) + 'px';
  }
}

function filterServiceDropdown(query) {
  const dd = document.getElementById('cw-service-dropdown');
  const q = (query || '').trim().toLowerCase();
  const matches = SERVICE_LIST.filter(s => !q || s.name.toLowerCase().includes(q)).slice(0, 40);
  if (!matches.length) {
    dd.innerHTML = '<div class="cw-sd-empty">No matching services — try Manage Services to add one</div>';
    return;
  }
  let lastCat = null, html = '';
  matches.forEach((s, i) => {
    if (s.category !== lastCat) {
      html += `<div class="cw-sd-group">${escHtml(s.category)}</div>`;
      lastCat = s.category;
    }
    html += `<div class="cw-sd-item${i === 0 ? ' active' : ''}" data-name="${escAttr(s.name)}" data-price="${s.price}">
               <span class="cw-sd-name">${highlightMatch(s.name, q)}</span>
               <span class="cw-sd-price">₱${Number(s.price).toFixed(2)}</span>
             </div>`;
  });
  dd.innerHTML = html;
}

function openServiceDropdown(input) {
  activeServiceInput = input;
  positionServiceDropdown(input);
  filterServiceDropdown(input.value);
  document.getElementById('cw-service-dropdown').style.display = 'block';
}

function closeServiceDropdown() {
  const dd = document.getElementById('cw-service-dropdown');
  dd.style.display = 'none';
  dd.innerHTML = '';
  activeServiceInput = null;
}

function selectServiceItem(item) {
  if (!activeServiceInput || !item) return;
  activeServiceInput.value = item.dataset.name;
  const tr = activeServiceInput.closest('tr');
  if (tr) tr.querySelector('.tx-price').value = parseFloat(item.dataset.price) || '';
  txChanged();
  closeServiceDropdown();
}

document.addEventListener('focusin', e => {
  if (e.target.classList && e.target.classList.contains('tx-service')) openServiceDropdown(e.target);
});
document.addEventListener('input', e => {
  if (e.target.classList && e.target.classList.contains('tx-service')) {
    activeServiceInput = e.target;
    positionServiceDropdown(e.target);
    filterServiceDropdown(e.target.value);
    document.getElementById('cw-service-dropdown').style.display = 'block';
  }
});
document.addEventListener('change', e => {
  if (e.target.classList && e.target.classList.contains('tx-service')) {
    const price = SERVICE_PRICES[e.target.value] || 0;
    const tr = e.target.closest('tr');
    if (tr) { tr.querySelector('.tx-price').value = price || ''; txChanged(); }
  }
});
document.addEventListener('keydown', e => {
  if (!e.target.classList || !e.target.classList.contains('tx-service')) return;
  const dd = document.getElementById('cw-service-dropdown');
  if (dd.style.display === 'none') return;
  const items = Array.from(dd.querySelectorAll('.cw-sd-item'));
  if (!items.length) return;
  let idx = items.findIndex(i => i.classList.contains('active'));
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    idx = Math.min(items.length - 1, idx + 1);
    items.forEach(i => i.classList.remove('active'));
    items[idx].classList.add('active'); items[idx].scrollIntoView({ block: 'nearest' });
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    idx = Math.max(0, idx - 1);
    items.forEach(i => i.classList.remove('active'));
    items[idx].classList.add('active'); items[idx].scrollIntoView({ block: 'nearest' });
  } else if (e.key === 'Enter') {
    e.preventDefault();
    selectServiceItem(items[idx] || items[0]);
  } else if (e.key === 'Escape') {
    closeServiceDropdown();
  }
});
document.addEventListener('mousedown', e => {
  const dd = document.getElementById('cw-service-dropdown');
  const item = e.target.closest('.cw-sd-item');
  if (item && dd.contains(item)) { e.preventDefault(); selectServiceItem(item); return; }
  if (!dd.contains(e.target) && !(e.target.classList && e.target.classList.contains('tx-service'))) closeServiceDropdown();
});
window.addEventListener('scroll', () => { if (activeServiceInput) positionServiceDropdown(activeServiceInput); }, true);
window.addEventListener('resize', () => { if (activeServiceInput) positionServiceDropdown(activeServiceInput); });
function txChanged() {
  let totPrice=0, totDiscount=0;
  document.querySelectorAll('#tx-body tr').forEach(tr => {
    const price    = parseFloat(tr.querySelector('.tx-price')?.value) || 0;
    const discount = parseFloat(tr.querySelector('.tx-discount')?.value) || 0;
    const commission = (price - discount) * 0.25;
    const netSales    = price - commission;
    tr.querySelector('.tx-commission').value = fmt(commission);
    tr.querySelector('.tx-net').value        = fmt(netSales);
    totPrice += price; totDiscount += discount;
  });
  const totCommission = (totPrice - totDiscount) * 0.25;
  const totNet         = totPrice - totCommission;
  document.getElementById('tx-tot-price').textContent      = fmt(totPrice);
  document.getElementById('tx-tot-discount').textContent   = fmt(totDiscount);
  document.getElementById('tx-tot-commission').textContent = fmt(totCommission);
  document.getElementById('tx-tot-net').textContent        = fmt(totNet);

  setVal('staff_cf',  fmt(totCommission));
  setVal('discounts', fmt(totDiscount));
  recalc();
}
function buildMopOptions() {
  let html = '';
  <?php foreach ($MOP_OPTIONS as $mop): ?>
  html += '<option value="<?= htmlspecialchars($mop, ENT_QUOTES) ?>"<?= $mop === 'CASH' ? ' selected' : '' ?>><?= htmlspecialchars($mop, ENT_QUOTES) ?></option>';
  <?php endforeach; ?>
  return html;
}
function addTxRow() {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="tx-no" style="text-align:center"></td>
    <td><input class="di-inp txt" type="text" placeholder="Plate No."></td>
    <td><input class="di-inp tx-service" type="text" autocomplete="off" placeholder="🔍 Search service…"></td>
    <td><input class="di-inp txt" type="text" placeholder="Staff"></td>
    <td><input class="di-inp tx-price" type="number" step="0.01" readonly tabindex="-1"></td>
    <td><input class="di-inp tx-discount" type="number" step="0.01" placeholder="" oninput="txChanged()"></td>
    <td><input class="di-inp tx-commission" type="number" step="0.01" readonly tabindex="-1"></td>
    <td><input class="di-inp tx-net" type="number" step="0.01" readonly tabindex="-1"></td>
    <td><select class="di-inp">${buildMopOptions()}</select></td>
    <td><input class="di-inp txt" type="text" placeholder=""></td>
    <td><button class="btn-del-row" onclick="delTxRow(this)">✕</button></td>`;
  document.getElementById('tx-body').appendChild(tr);
  renumberTx();
}
function delTxRow(btn) { btn.closest('tr').remove(); renumberTx(); txChanged(); }

// ── Expenses / Unpaids sub-sections ────────────────────────
function subChanged(sec) {
  const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
  let tot = 0;
  col.querySelectorAll('input[type=number]').forEach(i => tot += parseFloat(i.value)||0);
  const totEl = document.getElementById(`sub-tot-${sec}`);
  if (totEl) totEl.textContent = '₱' + fmt(tot);

  if (sec === 'expenses') { setVal('expenses', fmt(tot)); recalc(); }
  if (sec === 'unpaids')  { setVal('unpaids',  fmt(tot)); recalc(); }
}
function addSubRow(sec) {
  const col  = document.querySelector(`.sub-col[data-section="${sec}"]`);
  const rows = col.querySelector('.sub-rows');
  const div  = document.createElement('div');
  div.className = 'sub-row';
  div.innerHTML = `
    <input class="sub-inp" type="text" placeholder="Enter particular…" oninput="subChanged('${sec}')">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="subChanged('${sec}')">
    <button class="btn-del-row" onclick="delSubRow(this,'${sec}')" style="margin:2px 4px">✕</button>`;
  rows.appendChild(div);
}
function delSubRow(btn, sec) { btn.closest('.sub-row').remove(); subChanged(sec); }

// ── Marketing section ──────────────────────────────────────
function mktChanged() {
  let totAmount=0, totCommission=0;
  document.querySelectorAll('#mkt-rows .mkt-row').forEach(row => {
    const inps = row.querySelectorAll('input[type=number]');
    totAmount     += parseFloat(inps[0]?.value)||0;
    totCommission += parseFloat(inps[1]?.value)||0;
  });
  document.getElementById('mkt-tot-amount').textContent     = '₱' + fmt(totAmount);
  document.getElementById('mkt-tot-commission').textContent = '₱' + fmt(totCommission);
  setVal('marketing_expense', fmt(totAmount));
  recalc();
}
function addMktRow() {
  const wrap = document.getElementById('mkt-rows');
  const div = document.createElement('div');
  div.className = 'mkt-row';
  div.innerHTML = `
    <input class="sub-inp" type="text" placeholder="Enter particular…" oninput="mktChanged()">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="mktChanged()">
    <input class="sub-inp" type="text" placeholder="Staff" oninput="mktChanged()">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="mktChanged()">
    <button class="btn-del-row" onclick="delMktRow(this)" style="margin:2px 4px">✕</button>`;
  wrap.appendChild(div);
}
function delMktRow(btn) { btn.closest('.mkt-row').remove(); mktChanged(); }

// ── Summary recalc (mirrors the workbook's formula chain) ──
function recalc() {
  const totPriceText = document.getElementById('tx-tot-price')?.textContent || '0';
  const mktAmountText = (document.getElementById('mkt-tot-amount')?.textContent || '₱0').replace('₱','');
  const posReading = (parseFloat(totPriceText)||0) + (parseFloat(mktAmountText)||0);
  setVal('pos_reading', fmt(posReading));

  const staffCf = gv('staff_cf');
  const grossSales = posReading - staffCf;
  setVal('gross_sales', fmt(grossSales));

  const expenses  = gv('expenses');
  const unpaids   = gv('unpaids');
  const discounts = gv('discounts');
  const marketing = gv('marketing_expense');
  const qrPay     = gv('qr_palawan_pay');
  const cardPay   = gv('card_payments');

  const netCash = posReading - expenses - unpaids - discounts - marketing - qrPay - cardPay;
  setVal('net_cash', fmt(netCash));

  const coh = gv('coh');
  const shortOver = coh - netCash;
  setVal('short_over', fmt(shortOver));
  const soEl = document.getElementById('short_over');
  soEl.style.color = shortOver < 0 ? '#ffd54f' : '#b71c1c';

  const mktCommission = parseFloat(document.getElementById('mkt-tot-commission')?.textContent.replace('₱',''))||0;
  const totalCommission = staffCf + mktCommission;
  document.getElementById('total-commission-display').textContent = '₱' + fmt(totalCommission);
}

// ── Save All ───────────────────────────────────────────────
async function saveAll() {
  if (REPORT_LOCKED) {
    showToast('🔒 This date is locked and cannot be edited.', 'error');
    return;
  }
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  // 1. Save Cash Breakdown rows
  const cashRows = [];
  document.querySelectorAll('#cash-body tr').forEach(tr => cashRows.push(cashRowData(tr)));
  const fd1 = new FormData();
  fd1.append('ajax_save_cash','1');
  fd1.append('report_date', FDATE);
  fd1.append('rows', JSON.stringify(cashRows));
  await fetch('h_carwash_sales_report.php', {method:'POST', body:fd1});

  // 2. Save Transactions
  const txRows = [];
  document.querySelectorAll('#tx-body tr').forEach(tr => {
    txRows.push({
      plate_no: tr.querySelectorAll('input.di-inp.txt')[0]?.value || '',
      service:  tr.querySelector('.tx-service')?.value || '',
      staff:    tr.querySelectorAll('input.di-inp.txt')[1]?.value || '',
      price:    parseFloat(tr.querySelector('.tx-price')?.value) || 0,
      discount: parseFloat(tr.querySelector('.tx-discount')?.value) || 0,
      mop:      tr.querySelector('select')?.value || 'CASH',
      remarks:  tr.querySelectorAll('input.di-inp.txt')[2]?.value || '',
    });
  });
  const fd2 = new FormData();
  fd2.append('ajax_save_transactions','1');
  fd2.append('report_date', FDATE);
  fd2.append('rows', JSON.stringify(txRows));
  await fetch('h_carwash_sales_report.php', {method:'POST', body:fd2});

  // 3. Save Expenses / Unpaids
  for (const sec of ['expenses','unpaids']) {
    const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
    if (!col) continue;
    const rows = [];
    col.querySelectorAll('.sub-row').forEach(row => {
      const inps = row.querySelectorAll('input');
      rows.push({ particular: inps[0]?.value||'', amount: parseFloat(inps[1]?.value)||0 });
    });
    const fd3 = new FormData();
    fd3.append('ajax_save_detail','1');
    fd3.append('report_date', FDATE);
    fd3.append('section', sec);
    fd3.append('rows', JSON.stringify(rows));
    await fetch('h_carwash_sales_report.php', {method:'POST', body:fd3});
  }

  // 4. Save Marketing rows
  const mktRows = [];
  document.querySelectorAll('#mkt-rows .mkt-row').forEach(row => {
    const txtInps = row.querySelectorAll('input[type=text]');
    const numInps = row.querySelectorAll('input[type=number]');
    mktRows.push({
      particular: txtInps[0]?.value || '',
      amount:     parseFloat(numInps[0]?.value) || 0,
      staff:      txtInps[1]?.value || '',
      commission: parseFloat(numInps[1]?.value) || 0,
    });
  });
  const fd4 = new FormData();
  fd4.append('ajax_save_marketing','1');
  fd4.append('report_date', FDATE);
  fd4.append('rows', JSON.stringify(mktRows));
  await fetch('h_carwash_sales_report.php', {method:'POST', body:fd4});

  // 5. Save main summary
  const fd5 = new FormData();
  fd5.append('ajax_save','1');
  fd5.append('report_date', FDATE);
  fd5.append('opening_cashier', document.getElementById('opening_cashier')?.value || '');
  fd5.append('closing_cashier', document.getElementById('closing_cashier')?.value || '');
  fd5.append('sold_gc', gv('sold_gc'));
  fd5.append('qr_palawan_pay', gv('qr_palawan_pay'));
  fd5.append('card_payments', gv('card_payments'));
  fd5.append('remarks', document.getElementById('remarks')?.value || '');

  const res  = await fetch('h_carwash_sales_report.php', {method:'POST', body:fd5});
  const data = await res.json();
  if (data.ok) {
    status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
    showToast('✓ Carwash sales report saved', 'success');
  } else {
    showToast('❌ ' + data.msg, 'error'); status.textContent = '❌ Error';
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

// ── Lock popover ─────────────────────────────────────────
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
    const res  = await fetch('h_carwash_sales_report.php', {method:'POST', body:fd});
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

// ── Lock the whole page (all fields read-only) ──────────────
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

document.addEventListener('DOMContentLoaded', () => {
  renumberTx();
  cashChanged();
  txChanged();
  mktChanged();
  recalc();
  if (REPORT_LOCKED) lockPage();
});
</script>