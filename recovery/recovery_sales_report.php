<?php
// ============================================================
//  recovery_sales_report.php — Recovery Branch Daily Sales Report
//  Sub-section detail rows (Marketing
//  Pullout, GRAB, Expenses, Late Payment, Advance Payment,
//  GC Sponsorship, GC Sold) — totals feed Net Sales / Short/Over
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/audit_log.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Recovery') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Lock stops Cashier edits only — OIC (Accounting) and Manager can
// still edit a locked date.
$canLock = !isCashier();

// ── Main summary table — Recovery-specific fields ──────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_sales_report` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `store_name`            varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`           date NOT NULL,
    `gross_sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `staff_cf`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `sold_gc`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `pos_reading`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `discounts`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `celeb_discounts`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `redeemed_gc`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `swiper`                decimal(12,2) NOT NULL DEFAULT 0.00,
    `gcash_sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `maya_sales`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `maya_dp`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `unpaids`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `advance_payment`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `expenses`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `marketing_expense`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `product_sold`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_cash`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `coh`                   decimal(12,2) NOT NULL DEFAULT 0.00,
    `short_over`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `opening_cashier`       varchar(100) DEFAULT NULL,
    `closing_cashier`       varchar(100) DEFAULT NULL,
    `saved_by`              varchar(100) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Migrate old columns if table existed before (safe — ignore errors)
$newCols = ['staff_cf','sold_gc','pos_reading','discounts','celeb_discounts','redeemed_gc',
            'swiper','gcash_sales','maya_sales','maya_dp','unpaids','advance_payment',
            'expenses','marketing_expense','product_sold','net_cash'];
foreach ($newCols as $col) {
    try { $pdo->exec("ALTER TABLE `recovery_sales_report` ADD COLUMN `$col` decimal(12,2) NOT NULL DEFAULT 0.00"); }
    catch (Throwable $ignored) {}
}
foreach (['opening_cashier','closing_cashier'] as $col) {
    try { $pdo->exec("ALTER TABLE `recovery_sales_report` ADD COLUMN `$col` varchar(100) DEFAULT NULL"); }
    catch (Throwable $ignored) {}
}

// ── Sub-section detail rows table ─────────────────────────
// section: marketing_pullout | grab | expenses | late_payment |
//          advance_payment | gc_sponsorship | gc_sold
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_sales_detail_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`   date NOT NULL,
    `section`       varchar(40) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

foreach (['mop','remarks'] as $col) {
    try { $pdo->exec("ALTER TABLE `recovery_sales_detail_rows` ADD COLUMN `$col` varchar(150) DEFAULT NULL"); }
    catch (Throwable $ignored) {}
}
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_sales_services` (
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `store_name`        varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`       date NOT NULL,
    `time_in`           time DEFAULT NULL,
    `time_out`          time DEFAULT NULL,
    `slip_no`           varchar(30) DEFAULT NULL,
    `client_name`       varchar(150) DEFAULT NULL,
    `service`           varchar(200) DEFAULT NULL,
    `stylist`           varchar(100) DEFAULT NULL,
    `regular_price`     decimal(12,2) DEFAULT 0.00,
    `promo_price`       decimal(12,2) DEFAULT 0.00,
    `comm_rate`         varchar(10) DEFAULT '' COMMENT 'Selected commission tier: 30, 20, 15, or disc50',
    `celeb_promo_10`    decimal(12,2) DEFAULT 0.00,
    `disc_20_pwd_snr`   decimal(12,2) DEFAULT 0.00,
    `comm_30`           decimal(12,2) DEFAULT 0.00,
    `comm_20`           decimal(12,2) DEFAULT 0.00,
    `comm_15`           decimal(12,2) DEFAULT 0.00,
    `disc_50_staff`     decimal(12,2) DEFAULT 0.00,
    `net_sales`         decimal(12,2) DEFAULT 0.00,
    `mode_of_payment`   varchar(50) DEFAULT NULL,
    `advance_payment`   decimal(12,2) DEFAULT 0.00,
    `mop`               varchar(50) DEFAULT NULL,
    `remarks`           varchar(255) DEFAULT NULL,
    `sort_order`        int(4) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_ss_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Existing installations already had this table before comm_rate was
// added — CREATE TABLE IF NOT EXISTS above is a no-op for them, so add
// the column directly. Safe to re-run: errors (already exists) are ignored.
try { $pdo->exec("ALTER TABLE `recovery_sales_services` ADD COLUMN `comm_rate` varchar(10) DEFAULT '' COMMENT 'Selected commission tier: 30, 20, 15, or disc50' AFTER `promo_price`"); }
catch (Throwable $ignored) {}

// ── Cash Breakdown table ────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_cash_breakdown` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`   date NOT NULL,
    `denomination`  decimal(10,2) NOT NULL DEFAULT 0.00,
    `qty`           int(6) NOT NULL DEFAULT 0,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Sales Services (Influencer/Marketing) table ────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_mktg_services` (
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `store_name`        varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`       date NOT NULL,
    `time_start`        time DEFAULT NULL,
    `time_end`          time DEFAULT NULL,
    `slip_no`           varchar(30) DEFAULT NULL,
    `client_name`       varchar(150) DEFAULT NULL,
    `service`           varchar(200) DEFAULT NULL,
    `stylist`           varchar(100) DEFAULT NULL,
    `at_cost`           decimal(12,2) DEFAULT 0.00,
    `commission_fee`    decimal(12,2) DEFAULT 0.00,
    `total_mktg_exp`    decimal(12,2) DEFAULT 0.00,
    `remarks`           varchar(255) DEFAULT NULL,
    `sort_order`        int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── GC Sold table (Service GC & Paid GC) ────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_gc_sold` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`   date NOT NULL,
    `gc_type`       enum('service','paid') NOT NULL DEFAULT 'service',
    `series`        varchar(50) DEFAULT NULL,
    `name`          varchar(150) DEFAULT NULL,
    `voucher`       varchar(50) DEFAULT NULL,
    `qty`           int(6) DEFAULT 0,
    `amount`        decimal(12,2) DEFAULT 0.00,
    `remarks`       varchar(255) DEFAULT NULL,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Product Sold table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_product_sold` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Recovery',
    `report_date`   date NOT NULL,
    `particular`    varchar(150) DEFAULT NULL,
    `qty`           decimal(10,2) DEFAULT 0.00,
    `price`         decimal(12,2) DEFAULT 0.00,
    `amount`        decimal(12,2) DEFAULT 0.00,
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
                VALUES ('Recovery', ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = CURRENT_TIMESTAMP")
                ->execute([$reportDate, $user['name']]);
        } else {
            $pdo->prepare("DELETE FROM report_locks WHERE store_name='Recovery' AND report_date=?")
                ->execute([$reportDate]);
        }
        logAudit($pdo, 'Recovery', 'Sales Report', $lock ? 'locked' : 'unlocked', $reportDate,
            $lock ? 'Locked the sales report for editing' : 'Unlocked the sales report for editing');
        echo json_encode(['ok' => true, 'locked' => $lock]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save Sales Services rows ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_services'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_sales_services WHERE store_name='Recovery' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO recovery_sales_services
            (store_name,report_date,time_in,time_out,slip_no,client_name,service,stylist,
             regular_price,promo_price,comm_rate,celeb_promo_10,disc_20_pwd_snr,comm_30,comm_20,
             comm_15,disc_50_staff,net_sales,mode_of_payment,advance_payment,mop,remarks,sort_order)
            VALUES ('Recovery',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $rateMap = ['30' => 0.30, '20' => 0.20, '15' => 0.15, 'disc50' => 0.50];
        foreach ($rows as $i => $r) {
            $promoPrice = (float)($r['promo_price'] ?? 0);
            $commRate   = $r['comm_rate'] ?? '';
            // Only ONE of these four is ever populated — auto-derived from the single
            // selected tier x Promo Price, not trusted from the client directly.
            $fee = isset($rateMap[$commRate]) ? round($promoPrice * $rateMap[$commRate], 2) : 0;
            $comm30 = $commRate === '30'     ? $fee : 0;
            $comm20 = $commRate === '20'     ? $fee : 0;
            $comm15 = $commRate === '15'     ? $fee : 0;
            $disc50 = $commRate === 'disc50' ? $fee : 0;

            $ins->execute([
                $reportDate,
                $r['time_in']    ?: null,
                $r['time_out']   ?: null,
                $r['slip_no']    ?: null,
                $r['client_name']?: null,
                $r['service']    ?: null,
                $r['stylist']    ?: null,
                (float)($r['regular_price']  ?? 0),
                $promoPrice,
                $commRate,
                (float)($r['celeb_promo_10'] ?? 0),
                (float)($r['disc_20_pwd_snr']?? 0),
                $comm30,
                $comm20,
                $comm15,
                $disc50,
                (float)($r['net_sales']      ?? 0),
                $r['mode_of_payment'] ?: null,
                (float)($r['advance_payment']?? 0),
                $r['mop']        ?: null,
                $r['remarks']    ?: null,
                $i,
            ]);
        }
        // Return total net sales so summary can be updated
        $total = array_sum(array_column($rows, 'net_sales'));
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated Sales Services rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true,'total_net_sales'=>round($total,2)]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $NUM_COLS = ['gross_sales','staff_cf','sold_gc','pos_reading','discounts',
            'celeb_discounts','redeemed_gc','swiper','gcash_sales','maya_sales',
            'maya_dp','unpaids','advance_payment','expenses','marketing_expense',
            'product_sold','coh'];
        $vals = [];
        foreach ($NUM_COLS as $f) $vals[$f] = (float)($_POST[$f] ?? 0);

        // ── Authoritative recalculation from the just-saved sub-tables ──
        $ssSumStmt = $pdo->prepare("SELECT
                COALESCE(SUM(regular_price),0)    AS regular_price,
                COALESCE(SUM(celeb_promo_10),0)   AS celeb_promo_10,
                COALESCE(SUM(comm_30),0)          AS comm_30,
                COALESCE(SUM(comm_20),0)          AS comm_20,
                COALESCE(SUM(comm_15),0)          AS comm_15,
                COALESCE(SUM(advance_payment),0)  AS advance_payment
            FROM recovery_sales_services WHERE store_name='Recovery' AND report_date=?");
        $ssSumStmt->execute([$reportDate]);
        $ssSums = $ssSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $mktgSumStmt = $pdo->prepare("SELECT
                COALESCE(SUM(at_cost),0)        AS at_cost,
                COALESCE(SUM(commission_fee),0) AS commission_fee,
                COALESCE(SUM(total_mktg_exp),0) AS total_mktg_exp
            FROM recovery_mktg_services WHERE store_name='Recovery' AND report_date=?");
        $mktgSumStmt->execute([$reportDate]);
        $mktgSums = $mktgSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $gcServiceStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM recovery_gc_sold WHERE store_name='Recovery' AND report_date=? AND gc_type='service'");
        $gcServiceStmt->execute([$reportDate]);
        $gcServiceSum = (float)$gcServiceStmt->fetchColumn();

        $productStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM recovery_product_sold WHERE store_name='Recovery' AND report_date=?");
        $productStmt->execute([$reportDate]);
        $productSum = (float)$productStmt->fetchColumn();

        $secSumStmt = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) AS total
            FROM recovery_sales_detail_rows WHERE store_name='Recovery' AND report_date=? GROUP BY section");
        $secSumStmt->execute([$reportDate]);
        $secSums = [];
        foreach ($secSumStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $secSums[$r['section']] = (float)$r['total'];

        $vals['unpaids']           = $secSums['unpaids_corp'] ?? 0;
        $vals['expenses']          = $secSums['expenses'] ?? 0;
        // POS Reading = Regular Price (Sales Services) + Product Sold only.
        // Marketing/Influencer At Cost is a separate cost line (marketing_expense),
        // it does NOT ring up on the POS and must not be added here.
        $vals['pos_reading']       = (float)$ssSums['regular_price'] + $productSum;
        $vals['staff_cf']          = ((float)$ssSums['comm_30'] + (float)$ssSums['comm_20'] + (float)$ssSums['comm_15']) + (float)$mktgSums['commission_fee'];
        $vals['marketing_expense'] = (float)$mktgSums['at_cost'];
        $vals['sold_gc']           = $gcServiceSum;
        // Gross Sales = POS Reading + Sold GC (Staff CF/Marketing Expense are
        // costs, not revenue — they must NOT be added here)
        $vals['gross_sales']       = $vals['pos_reading'] + $vals['sold_gc'];
        $vals['celeb_discounts']   = (float)$ssSums['celeb_promo_10'];
        $vals['advance_payment']   = (float)$ssSums['advance_payment'];
        $vals['product_sold']      = $productSum;

        // Net Cash = POS Reading - Discounts - Celeb. Discounts + Redeemed GC - Swiper - GCash
        //           - Maya Sales - Maya DP - Unpaids - Advance Payment - Expenses - Marketing Expense - Product Sold
        $netCash = $vals['pos_reading']
                 - $vals['discounts']
                 - $vals['celeb_discounts']
                 + $vals['redeemed_gc']
                 - $vals['swiper']
                 - $vals['gcash_sales']
                 - $vals['maya_sales']
                 - $vals['maya_dp']
                 - $vals['unpaids']
                 - $vals['advance_payment']
                 - $vals['expenses']
                 - $vals['marketing_expense']
                 - $vals['product_sold'];

        $shortOver = $vals['coh'] - $netCash;

        $openingCashier = trim($_POST['opening_cashier'] ?? '') ?: null;
        $closingCashier = trim($_POST['closing_cashier'] ?? '') ?: null;

        $allCols = array_merge($NUM_COLS, ['net_cash','short_over','opening_cashier','closing_cashier','saved_by']);
        $allVals = array_merge(array_values($vals), [$netCash, $shortOver, $openingCashier, $closingCashier, $user['name']]);

        $fields  = array_merge(['store_name','report_date'], $allCols);
        $data    = array_merge(['Recovery', $reportDate], $allVals);
        $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $allCols));
        $sql = "INSERT INTO recovery_sales_report (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ")
                VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")
                ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute($data);
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate, 'Saved daily summary totals',
            ['net_cash' => $netCash, 'short_over' => $shortOver]);
        echo json_encode(['ok'=>true,'net_cash'=>$netCash,'short_over'=>$shortOver]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save sub-section detail rows ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_detail'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $section    = $_POST['section'];
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_sales_detail_rows WHERE store_name='Recovery' AND report_date=? AND section=?")->execute([$reportDate,$section]);
        $ins = $pdo->prepare("INSERT INTO recovery_sales_detail_rows (store_name,report_date,section,item_name,amount,mop,remarks,sort_order) VALUES ('Recovery',?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $section, $r['name']??null, (float)($r['amount']??0), $r['mop']??null, $r['remarks']??null, $i]);
        }
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated "' . $section . '" rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Cash Breakdown rows ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_cash'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_cash_breakdown WHERE store_name='Recovery' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO recovery_cash_breakdown (store_name,report_date,denomination,qty,sort_order) VALUES ('Recovery',?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, (float)($r['denomination']??0), (int)($r['qty']??0), $i]);
        }
        $total = array_sum(array_map(fn($r) => (float)($r['denomination']??0) * (int)($r['qty']??0), $rows));
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated Cash Breakdown rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true,'total'=>round($total,2)]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Marketing/Influencer Sales Services rows ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_mktg'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_mktg_services WHERE store_name='Recovery' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO recovery_mktg_services
            (store_name,report_date,time_start,time_end,slip_no,client_name,service,stylist,at_cost,commission_fee,total_mktg_exp,remarks,sort_order)
            VALUES ('Recovery',?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([
                $reportDate,
                $r['time_start']  ?: null,
                $r['time_end']    ?: null,
                $r['slip_no']     ?: null,
                $r['client_name'] ?: null,
                $r['service']     ?: null,
                $r['stylist']     ?: null,
                (float)($r['at_cost']        ?? 0),
                (float)($r['commission_fee'] ?? 0),
                (float)($r['total_mktg_exp'] ?? 0),
                $r['remarks']     ?: null,
                $i,
            ]);
        }
        $total = array_sum(array_column($rows, 'total_mktg_exp'));
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated Marketing/Influencer Sales Services rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true,'total'=>round($total,2)]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save GC Sold rows (Service GC / Paid GC) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_gc'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        $gcType     = $_POST['gc_type'] === 'paid' ? 'paid' : 'service';
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_gc_sold WHERE store_name='Recovery' AND report_date=? AND gc_type=?")->execute([$reportDate,$gcType]);
        $ins = $pdo->prepare("INSERT INTO recovery_gc_sold (store_name,report_date,gc_type,series,name,voucher,qty,amount,remarks,sort_order) VALUES ('Recovery',?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $gcType, $r['series']??null, $r['name']??null, $r['voucher']??null,
                (int)($r['qty']??0), (float)($r['amount']??0), $r['remarks']??null, $i]);
        }
        $total = array_sum(array_column($rows, 'amount'));
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated GC Sold (' . $gcType . ') rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true,'total'=>round($total,2)]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save Product Sold rows ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_product'])) {
    header('Content-Type: application/json');
    try {
        $reportDate = $_POST['report_date'];
        if (isDateLocked($pdo, 'Recovery', $reportDate) && isCashier()) {
            echo json_encode(['ok' => false, 'msg' => 'This date is locked. Ask accounting to unlock it before editing.']);
            exit;
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM recovery_product_sold WHERE store_name='Recovery' AND report_date=?")->execute([$reportDate]);
        $ins = $pdo->prepare("INSERT INTO recovery_product_sold (store_name,report_date,particular,qty,price,amount,sort_order) VALUES ('Recovery',?,?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$reportDate, $r['particular']??null, (float)($r['qty']??0), (float)($r['price']??0), (float)($r['amount']??0), $i]);
        }
        $total = array_sum(array_column($rows, 'amount'));
        logAudit($pdo, 'Recovery', 'Sales Report', 'updated', $reportDate,
            'Updated Product Sold rows (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ')');
        echo json_encode(['ok'=>true,'total'=>round($total,2)]);
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
    $stmt = $pdo->prepare("SELECT * FROM recovery_sales_report WHERE store_name='Recovery' AND report_date=?");
    $stmt->execute([$reportDate]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $g = fn($k) => number_format((float)($r[$k] ?? 0), 2, '.', '');

    $filename = 'Recovery_SalesReport_'.$reportDate.'.xlsx';

    $xlsx = new SimpleXlsxWriter(salesReportAccentColor('Recovery'));
    $xlsx->title('Recovery Branch — Daily Sales Report');
    $xlsx->subtitle(date('F j, Y', strtotime($reportDate))
        . ' · Opening Cashier: ' . (string)($r['opening_cashier'] ?? '—')
        . ' · Closing Cashier: ' . (string)($r['closing_cashier'] ?? '—'));
    $xlsx->blank();

    // — SALES SERVICES —
    $xlsx->sectionRow('SALES SERVICES');
    $xlsx->header(['Time In','Time Out','Slip No.','Client Name','Service','Stylist','Regular Price','Promo Price','Comm. Rate','Celeb Promo 10%','Disc 20% PWD/SNR','Comm 30%','Comm 20%','Comm 15%','Disc 50% Staff','Net Sales','Mode of Payment','Advance Payment','MOP','Remarks']);
    $ssExpStmt = $pdo->prepare("SELECT * FROM recovery_sales_services WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
    $ssExpStmt->execute([$reportDate]);
    foreach ($ssExpStmt->fetchAll(PDO::FETCH_ASSOC) as $ss) {
        $xlsx->row([
            $ss['time_in'], $ss['time_out'], $ss['slip_no'], $ss['client_name'], $ss['service'], $ss['stylist'],
            number_format((float)$ss['regular_price'],2,'.',''), number_format((float)$ss['promo_price'],2,'.',''), $ss['comm_rate'],
            number_format((float)$ss['celeb_promo_10'],2,'.',''), number_format((float)$ss['disc_20_pwd_snr'],2,'.',''),
            number_format((float)$ss['comm_30'],2,'.',''), number_format((float)$ss['comm_20'],2,'.',''), number_format((float)$ss['comm_15'],2,'.',''),
            number_format((float)$ss['disc_50_staff'],2,'.',''), number_format((float)$ss['net_sales'],2,'.',''),
            $ss['mode_of_payment'], number_format((float)$ss['advance_payment'],2,'.',''), $ss['mop'], $ss['remarks'],
        ]);
    }
    $xlsx->blank();

    // — INFLUENCER / MARKETING —
    $xlsx->sectionRow('INFLUENCER / MARKETING');
    $xlsx->header(['Time Start','Time End','Slip No.','Client Name','Service','Stylist','At Cost','Commission Fee','Total MKTG Expense','Remarks']);
    $mktgExpStmt = $pdo->prepare("SELECT * FROM recovery_mktg_services WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
    $mktgExpStmt->execute([$reportDate]);
    foreach ($mktgExpStmt->fetchAll(PDO::FETCH_ASSOC) as $mk) {
        $xlsx->row([
            $mk['time_start'], $mk['time_end'], $mk['slip_no'], $mk['client_name'], $mk['service'], $mk['stylist'],
            number_format((float)$mk['at_cost'],2,'.',''), number_format((float)$mk['commission_fee'],2,'.',''),
            number_format((float)$mk['total_mktg_exp'],2,'.',''), $mk['remarks'],
        ]);
    }
    $xlsx->blank();

    // — CASH BREAKDOWN —
    $xlsx->sectionRow('CASH BREAKDOWN');
    $xlsx->header(['Denomination','Qty','Total Collection']);
    $cbExpStmt = $pdo->prepare("SELECT * FROM recovery_cash_breakdown WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC");
    $cbExpStmt->execute([$reportDate]);
    foreach ($cbExpStmt->fetchAll(PDO::FETCH_ASSOC) as $cb) {
        if ((float)$cb['qty'] == 0) continue;
        $xlsx->row([number_format((float)$cb['denomination'],2,'.',''), (int)$cb['qty'],
            number_format((float)$cb['denomination'] * (float)$cb['qty'],2,'.','')]);
    }
    $xlsx->blank();

    // — GC SOLD (Service GC) —
    foreach (['service' => 'GC SOLD — SERVICE GC', 'paid' => 'GC SOLD — PAID GC'] as $gcType => $gcLabel) {
        $xlsx->sectionRow($gcLabel);
        $xlsx->header(['Series','Name','Voucher','Qty','Amount','Remarks']);
        $gcExpStmt = $pdo->prepare("SELECT * FROM recovery_gc_sold WHERE store_name='Recovery' AND report_date=? AND gc_type=? ORDER BY sort_order ASC, id ASC");
        $gcExpStmt->execute([$reportDate, $gcType]);
        foreach ($gcExpStmt->fetchAll(PDO::FETCH_ASSOC) as $gcRow) {
            $xlsx->row([$gcRow['series'], $gcRow['name'], $gcRow['voucher'], (int)$gcRow['qty'],
                number_format((float)$gcRow['amount'],2,'.',''), $gcRow['remarks']]);
        }
        $xlsx->blank();
    }

    // — PRODUCT SOLD —
    $xlsx->sectionRow('PRODUCT SOLD');
    $xlsx->header(['Particular','Qty','Price','Amount']);
    $prodExpStmt = $pdo->prepare("SELECT * FROM recovery_product_sold WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
    $prodExpStmt->execute([$reportDate]);
    foreach ($prodExpStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $xlsx->row([$pr['particular'], number_format((float)$pr['qty'],2,'.',''),
            number_format((float)$pr['price'],2,'.',''), number_format((float)$pr['amount'],2,'.','')]);
    }
    $xlsx->blank();

    // — Sub-sections (Unpaids Corp., Expenses) —
    $recSubSections = [
        'unpaids_corp' => 'UNPAIDS CORP.',
        'expenses'     => 'EXPENSES',
    ];
    $recDetExpStmt = $pdo->prepare("SELECT * FROM recovery_sales_detail_rows WHERE store_name='Recovery' AND report_date=? AND section=? ORDER BY sort_order ASC");
    foreach ($recSubSections as $sec => $label) {
        $xlsx->sectionRow($label);
        $xlsx->header(['Particular','Amount']);
        $recDetExpStmt->execute([$reportDate, $sec]);
        foreach ($recDetExpStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $xlsx->row([$d['item_name'], number_format((float)$d['amount'],2,'.','')]);
        }
        $xlsx->blank();
    }

    // — SUMMARY REPORT —
    $xlsx->sectionRow('SUMMARY REPORT');
    $xlsx->header(['Particular', 'Amount']);
    $xlsx->row(['Gross Sales', $g('gross_sales')]);
    $xlsx->row(['Staff CF', $g('staff_cf')]);
    $xlsx->row(['Sold GC', $g('sold_gc')]);
    $xlsx->row(['POS Reading', $g('pos_reading')]);
    $xlsx->row(['Discounts', $g('discounts')]);
    $xlsx->row(['Celeb. Discounts 10%', $g('celeb_discounts')]);
    $xlsx->row(['Redeemed GC', $g('redeemed_gc')]);
    $xlsx->row(['Swiper', $g('swiper')]);
    $xlsx->row(['Gcash (Sales)', $g('gcash_sales')]);
    $xlsx->row(['Maya (Sales)', $g('maya_sales')]);
    $xlsx->row(['Maya (DP)', $g('maya_dp')]);
    $xlsx->row(['Unpaids', $g('unpaids')]);
    $xlsx->row(['Advance Payment', $g('advance_payment')]);
    $xlsx->row(['Expenses', $g('expenses')]);
    $xlsx->row(['Marketing Expense', $g('marketing_expense')]);
    $xlsx->row(['Product Sold', $g('product_sold')]);
    $xlsx->totalRow(['Net Cash', $g('net_cash')]);
    $xlsx->totalRow(['COH (Cash on Hand)', $g('coh')]);
    $xlsx->totalRow(['(Short) Over', $g('short_over')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([10, 10, 10, 16, 16, 12, 12, 12, 10, 12, 12, 10, 10, 10, 10, 12, 14, 12, 10, 16]);
    $xlsx->output($filename);
}

// ── Fetch data for display ─────────────────────────────────
$fDate = $_GET['date'] ?? date('Y-m-d');
$locked = isDateLocked($pdo, 'Recovery', $fDate);
// Lock stops Cashier edits only — OIC (Accounting) and Manager can still edit a locked date.
$restrictEdit = $locked && isCashier();
$stmt  = $pdo->prepare("SELECT * FROM recovery_sales_report WHERE store_name='Recovery' AND report_date=?");
$stmt->execute([$fDate]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn($k) => (float)($row[$k] ?? 0);
$fmt = fn($n)  => number_format((float)$n, 2);

// Fetch Sales Services rows
$ssStmt = $pdo->prepare("SELECT * FROM recovery_sales_services WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
$ssStmt->execute([$fDate]);
$ssRows = $ssStmt->fetchAll(PDO::FETCH_ASSOC);
$ssTotalNet      = array_sum(array_column($ssRows, 'net_sales'));
$ssRegularTotal  = array_sum(array_column($ssRows, 'regular_price'));
$ssCelebTotal    = array_sum(array_column($ssRows, 'celeb_promo_10'));
$ssComm30Total   = array_sum(array_column($ssRows, 'comm_30'));
$ssComm20Total   = array_sum(array_column($ssRows, 'comm_20'));
$ssComm15Total   = array_sum(array_column($ssRows, 'comm_15'));
$ssAdvanceTotal  = array_sum(array_column($ssRows, 'advance_payment'));

// Fetch sub-section detail rows
$detailStmt = $pdo->prepare("SELECT * FROM recovery_sales_detail_rows WHERE store_name='Recovery' AND report_date=? ORDER BY section, sort_order ASC");
$detailStmt->execute([$fDate]);
$allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
$details = [];
foreach ($allDetails as $d) $details[$d['section']][] = $d;
$secSum = fn($sec) => array_sum(array_column($details[$sec] ?? [], 'amount'));

// Fetch Cash Breakdown rows (fixed denomination list, merged with saved qty)
$CASH_DENOMS = [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.5, 0.1, 0.05];
$cbStmt = $pdo->prepare("SELECT * FROM recovery_cash_breakdown WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC");
$cbStmt->execute([$fDate]);
$cbSaved = $cbStmt->fetchAll(PDO::FETCH_ASSOC);
$cbQtyByDenom = [];
$cbExtraByDenom = [];   // extras keyed by denomination too, so repeats merge instead of piling up
foreach ($cbSaved as $cr) {
    $denomVal = (float)$cr['denomination'];
    $denomKey = (string)$denomVal;
    if (in_array($denomVal, $CASH_DENOMS, true)) {
        // Always accumulate — a second saved row for the same fixed
        // denomination adds to its quantity instead of becoming a
        // separate "extra" row (that's what caused the duplicates).
        $cbQtyByDenom[$denomKey] = ($cbQtyByDenom[$denomKey] ?? 0) + (int)$cr['qty'];
    } elseif ($denomVal > 0) {
        // Genuine custom denomination — merge repeats of the same
        // custom value together too, rather than listing them twice.
        $cbExtraByDenom[$denomKey] = ($cbExtraByDenom[$denomKey] ?? 0) + (int)$cr['qty'];
    }
}
$cbExtraRows = [];
foreach ($cbExtraByDenom as $denomKey => $qty) {
    $cbExtraRows[] = ['denomination' => $denomKey, 'qty' => $qty];
}
// Always keep at least 3 blank slots available for additional denominations
while (count($cbExtraRows) < 3) $cbExtraRows[] = ['denomination' => 0, 'qty' => 0];
$cbTotal = 0;
foreach ($cbQtyByDenom as $denomKey => $qty) $cbTotal += (float)$denomKey * $qty;
foreach ($cbExtraByDenom as $denomKey => $qty) $cbTotal += (float)$denomKey * $qty;

// Fetch Sales Services (Influencer/Marketing) rows
$mktgStmt = $pdo->prepare("SELECT * FROM recovery_mktg_services WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
$mktgStmt->execute([$fDate]);
$mktgRows = $mktgStmt->fetchAll(PDO::FETCH_ASSOC);
$mktgTotal       = array_sum(array_column($mktgRows, 'total_mktg_exp'));
$mktgAtCostTotal = array_sum(array_column($mktgRows, 'at_cost'));
$mktgCommTotal   = array_sum(array_column($mktgRows, 'commission_fee'));

// Fetch GC Sold rows (Service GC / Paid GC)
$gcStmt = $pdo->prepare("SELECT * FROM recovery_gc_sold WHERE store_name='Recovery' AND report_date=? AND gc_type=? ORDER BY sort_order ASC, id ASC");
$gcStmt->execute([$fDate, 'service']);
$gcServiceRows = $gcStmt->fetchAll(PDO::FETCH_ASSOC);
$gcStmt->execute([$fDate, 'paid']);
$gcPaidRows = $gcStmt->fetchAll(PDO::FETCH_ASSOC);
$gcServiceTotal = array_sum(array_column($gcServiceRows, 'amount'));
$gcPaidTotal    = array_sum(array_column($gcPaidRows, 'amount'));

// Fetch Product Sold rows
$prodStmt = $pdo->prepare("SELECT * FROM recovery_product_sold WHERE store_name='Recovery' AND report_date=? ORDER BY sort_order ASC, id ASC");
$prodStmt->execute([$fDate]);
$productRows = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
$productTotal = array_sum(array_column($productRows, 'amount'));

// ── Auto-calculated summary fields (mirrors JS recalcSummary()) ──
$unpaidsCalc          = $secSum('unpaids_corp');
$expensesCalc         = $secSum('expenses');
// POS Reading = Regular Price (Sales Services) + Product Sold only.
// Marketing/Influencer At Cost is a separate cost line (marketing_expense),
// it does NOT ring up on the POS and must not be added here.
$posReadingCalc       = $ssRegularTotal + $productTotal;
$staffCfCalc          = ($ssComm30Total + $ssComm20Total + $ssComm15Total) + $mktgCommTotal;
$marketingExpenseCalc = $mktgAtCostTotal;
$soldGcCalc           = $gcServiceTotal;
// Gross Sales = POS Reading + Sold GC (Staff CF/Marketing Expense are costs, not revenue)
$grossSalesCalc       = $posReadingCalc + $soldGcCalc;
$celebDiscountsCalc   = $ssCelebTotal;
$advancePaymentCalc   = $ssAdvanceTotal;
$productSoldCalc      = $productTotal;

// ── Fetch services from DB (single source of truth) ────────
// The Sales Services dropdown is driven entirely by the Commission Guide's
// Services Price List (recovery_services_pricelist table). No hardcoded
// list here anymore — add/rename/remove a service in the Commission Guide
// and it shows up here automatically, nothing to edit in this file.
$dbServices = $pdo->query("SELECT name, regular, promo, is_promo FROM recovery_services_pricelist ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
usort($dbServices, fn($a,$b) => strcmp($a['name'], $b['name']));
$SERVICES = array_column($dbServices, 'name');

// ── Fetch stylists from DB (for connected dropdown) ────────
$dbStylists = $pdo->query("SELECT name, handles FROM recovery_stylist_handles ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch MKTG guide (for Sales Services — Influencer/Marketing dropdown) ──
// Source: Commission Guide's "MKTG — At Cost & Commission Fee Guide" table.
// Picking a service there auto-fills At Cost + Commission Fee below.
$mktgServices = $pdo->query("SELECT service AS name, price, fix_cf, at_cost FROM recovery_commission_fees ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Recovery Sales Report';
$activePage = 'recovery_sales_report';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

<!-- Header -->
<div class="sr-header-card">
  <div>
    <div class="eyebrow">Recovery Branch · Sales</div>
    <div class="title">Daily Sales Report</div>
    <div class="subtitle">Sales services + sub-sections · Net Sales &amp; Short/Over auto-calculated</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">📌 Recovery</span>
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
    <a href="recovery_sales_report.php?export_csv=1&date=<?= htmlspecialchars($fDate) ?>" class="btn btn-ghost">⬇ Download Excel</a>
    <span id="saveStatus" class="sr-save-status"></span>
  </form>
  <a href="commission_services_guide_therapist.php" target="_blank" class="btn-guide">📋 Commission, Services Guide, Therapist</a>

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
     SHIFT INFO: Date | Opening Cashier | Closing Cashier
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="shift-bar">
    <div class="shift-field">
      <label>DATE</label>
      <div class="shift-readout"><?= date('F j, Y', strtotime($fDate)) ?></div>
    </div>
    <div class="shift-field">
      <label>OPENING CASHIER</label>
      <input type="text" id="opening_cashier" class="shift-inp" placeholder="Cashier name" value="<?= htmlspecialchars($row['opening_cashier'] ?? '') ?>">
    </div>
    <div class="shift-field">
      <label>CLOSING CASHIER</label>
      <input type="text" id="closing_cashier" class="shift-inp" placeholder="Cashier name" value="<?= htmlspecialchars($row['closing_cashier'] ?? '') ?>">
    </div>
  </div>
</div>

<?php
// ── Stylist dropdown cell builder ──────────────────────────
function stylCell(string $cur, array $stylists, string $cls = 'ss'): string {
    $opts = '<div class="styl-opt" data-val="" onclick="pickStyl(this,\'\')"
        style="padding:6px 10px;font-size:.72rem;color:#9ca3af;cursor:pointer">— Stylist —</div>';
    foreach ($stylists as $st) {
        $n = htmlspecialchars($st['name'], ENT_QUOTES);
        $active = $cur === $st['name'] ? 'styl-active' : '';
        $h = $st['handles'] ? '<span style="font-size:.57rem;color:#9ca3af;white-space:normal;line-height:1.2">' . htmlspecialchars($st['handles']) . '</span>' : '';
        $opts .= "<div class=\"styl-opt $active\" data-val=\"$n\"
            onclick=\"pickStyl(this,'$n')\"
            style=\"padding:6px 10px;font-size:.72rem;color:#374151;cursor:pointer\">" .
            htmlspecialchars($st['name']) . "$h</div>";
    }
    $display = $cur ?: '— Stylist —';
    return "<td style=\"min-width:80px\">
      <div class=\"styl-wrap\">
        <div class=\"styl-display\" tabindex=\"0\"
             onclick=\"toggleStylDrop(this)\" onkeydown=\"stylKey(event,this)\">
          <span class=\"styl-text\">" . htmlspecialchars($display) . "</span>
          <span class=\"styl-arrow\">▼</span>
        </div>
        <div class=\"styl-dropdown\">
          <div class=\"styl-search-wrap\">
            <input class=\"styl-search\" type=\"text\" placeholder=\"Search stylist…\"
                   oninput=\"filterStyl(this)\" onclick=\"event.stopPropagation()\">
          </div>
          <div class=\"styl-list\">$opts</div>
        </div>
        <input type=\"hidden\" class=\"styl-val\" value=\"" . htmlspecialchars($cur) . "\">
      </div>
    </td>";
}
?>
<!-- ══════════════════════════════════════════════════════════
     SALES SERVICES
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sr-section-title dark-red" style="background:#8b6914">📋 Sales Services</div>
  <div class="ss-wrap">
  <table class="ss-table" id="ss-table">
    <thead>
      <tr>
        <th rowspan="2" style="width:58px">TIME IN</th>
        <th rowspan="2" style="width:58px">TIME OUT</th>
        <th rowspan="2" style="width:72px">Service Slip No.</th>
        <th rowspan="2" style="width:130px">Client Name</th>
        <th rowspan="2" style="width:170px">Services</th>
        <th rowspan="2" style="width:74px">Stylist</th>
        <th rowspan="2" style="width:70px">Regular Price</th>
        <th rowspan="2" style="width:70px">Promo Price</th>
        <th rowspan="2" style="width:68px">CELEBRATION PROMO 10%</th>
        <th rowspan="2" style="width:68px">Disc 20% (PWD/SNR)</th>
        <th rowspan="2" style="width:90px">Comm. Rate</th>
        <th rowspan="2" style="width:68px">30% Commission fee</th>
        <th rowspan="2" style="width:68px">20% Commission fee</th>
        <th rowspan="2" style="width:68px">15% Commission fee</th>
        <th rowspan="2" style="width:68px">50% DISC. FOR STAFF</th>
        <th rowspan="2" style="width:80px">Net Sales</th>
        <th rowspan="2" style="width:70px">Mode of Payment</th>
        <th rowspan="2" style="width:70px">ADVANCE PAYMENT</th>
        <th rowspan="2" style="width:56px">MOP</th>
        <th rowspan="2" style="width:110px">Remarks</th>
        <th rowspan="2" style="width:96px" class="ss-action-th">Action</th>
      </tr>
    </thead>
    <tbody id="ss-body">
      <?php foreach ($ssRows as $sr): ?>
      <tr>
        <td><input class="ss-inp time" type="time" value="<?= htmlspecialchars($sr['time_in'] ?? '') ?>" onchange="ssChanged()"></td>
        <td><input class="ss-inp time" type="time" value="<?= htmlspecialchars($sr['time_out'] ?? '') ?>" onchange="ssChanged()"></td>
        <td><input class="ss-inp slip txt" type="text" value="<?= htmlspecialchars($sr['slip_no'] ?? '') ?>" placeholder="" onchange="ssChanged()"></td>
        <td><input class="ss-inp name txt" type="text" value="<?= htmlspecialchars($sr['client_name'] ?? '') ?>" placeholder=""></td>
        <td>
          <?php
            $curSvc = $sr['service'] ?? '';
            $isOther = !empty($curSvc) && !in_array($curSvc, $SERVICES);
            $displayText = $curSvc ?: '— Select Service —';
          ?>
          <div class="svc-wrap">
            <div class="svc-display" tabindex="0" onclick="toggleSvcDrop(this)" onkeydown="svcKeyDown(event,this)">
              <span class="svc-text"><?= htmlspecialchars($displayText) ?></span>
              <span class="svc-arrow">▼</span>
            </div>
            <div class="svc-dropdown">
              <div class="svc-search-wrap">
                <input class="svc-search" type="text" placeholder="Search service…" oninput="filterSvc(this)" onclick="event.stopPropagation()">
              </div>
              <div class="svc-list">
                <div class="svc-opt <?= !$curSvc ? 'active' : '' ?>" data-val="" onclick="pickSvc(this,'')">— Select Service —</div>
                <?php foreach($SERVICES as $svc): ?>
                <div class="svc-opt <?= $curSvc===$svc ? 'active' : '' ?>" data-val="<?=htmlspecialchars($svc)?>" onclick="pickSvc(this,'<?=htmlspecialchars($svc)?>')"><?=htmlspecialchars($svc)?></div>
                <?php endforeach; ?>
                <div class="svc-opt other <?= $isOther ? 'active' : '' ?>" data-val="__other__" onclick="pickSvc(this,'__other__')">✏ Other (type below)</div>
              </div>
            </div>
            <input class="svc-sel-hidden svc-val" type="hidden" value="<?=htmlspecialchars($curSvc)?>">
          </div>
          <input class="ss-inp svc txt svc-other-inp" type="text" placeholder="Custom service…"
                 value="<?= $isOther ? htmlspecialchars($curSvc) : '' ?>"
                 style="display:<?= $isOther ? 'block' : 'none' ?>;margin-top:3px"
                 oninput="ssChanged()">
        </td>
        <?= stylCell($sr['stylist'] ?? '', $dbStylists) ?>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['regular_price'] ?: '' ?>" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['promo_price'] ?: '' ?>" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['celeb_promo_10'] ?: '' ?>" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['disc_20_pwd_snr'] ?: '' ?>" placeholder="" oninput="ssRowCalc(this)"></td>
        <?php $cr = $sr['comm_rate'] ?? ''; ?>
        <td>
          <select class="ss-inp comm-rate-sel" oninput="ssRowCalc(this)">
            <option value=""       <?= $cr===''       ? 'selected':'' ?>>—</option>
            <option value="30"     <?= $cr==='30'      ? 'selected':'' ?>>30%</option>
            <option value="20"     <?= $cr==='20'      ? 'selected':'' ?>>20%</option>
            <option value="15"     <?= $cr==='15'      ? 'selected':'' ?>>15%</option>
            <option value="disc50" <?= $cr==='disc50'  ? 'selected':'' ?>>50% Staff</option>
          </select>
        </td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['comm_30'] ?: '' ?>" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['comm_20'] ?: '' ?>" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['comm_15'] ?: '' ?>" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['disc_50_staff'] ?: '' ?>" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td class="ss-net"><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['net_sales'] ?: '' ?>" placeholder="" readonly style="color:#166534;font-weight:700;background:transparent"></td>
        <td><input class="ss-inp mop txt" type="text" value="<?= htmlspecialchars($sr['mode_of_payment'] ?? '') ?>" placeholder=""></td>
        <td><input class="ss-inp" type="number" step="0.01" value="<?= (float)$sr['advance_payment'] ?: '' ?>" placeholder="" oninput="ssChanged()"></td>
        <td><input class="ss-inp mop txt" type="text" value="<?= htmlspecialchars($sr['mop'] ?? '') ?>" placeholder=""></td>
        <td><input class="ss-inp rem txt" type="text" value="<?= htmlspecialchars($sr['remarks'] ?? '') ?>" placeholder=""></td>
        <td class="ss-action-cell"><button class="btn-ss-save" onclick="saveSsTable(this)">Save</button><button class="btn-del-row" onclick="delSsRow(this)">✕</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($ssRows)): ?>
      <tr>
        <td><input class="ss-inp time" type="time" onchange="ssChanged()"></td>
        <td><input class="ss-inp time" type="time" onchange="ssChanged()"></td>
        <td><input class="ss-inp slip txt" type="text" placeholder=""></td>
        <td><input class="ss-inp name txt" type="text" placeholder=""></td>
        <td>
          <div class="svc-wrap">
            <div class="svc-display" tabindex="0" onclick="toggleSvcDrop(this)" onkeydown="svcKeyDown(event,this)">
              <span class="svc-text">— Select Service —</span>
              <span class="svc-arrow">▼</span>
            </div>
            <div class="svc-dropdown">
              <div class="svc-search-wrap">
                <input class="svc-search" type="text" placeholder="Search service…" oninput="filterSvc(this)" onclick="event.stopPropagation()">
              </div>
              <div class="svc-list">
                <div class="svc-opt active" data-val="" onclick="pickSvc(this,'')">— Select Service —</div>
                <?php foreach($SERVICES as $svc): ?>
                <div class="svc-opt" data-val="<?=htmlspecialchars($svc)?>" onclick="pickSvc(this,'<?=htmlspecialchars($svc)?>')"><?=htmlspecialchars($svc)?></div>
                <?php endforeach; ?>
                <div class="svc-opt other" data-val="__other__" onclick="pickSvc(this,'__other__')">✏ Other (type below)</div>
              </div>
            </div>
            <input class="svc-sel-hidden svc-val" type="hidden" value="">
          </div>
          <input class="ss-inp svc txt svc-other-inp" type="text" placeholder="Custom service…" style="display:none;margin-top:3px" oninput="ssChanged()">
        </td>
        <?= stylCell('', $dbStylists) ?>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
        <td>
          <select class="ss-inp comm-rate-sel" oninput="ssRowCalc(this)">
            <option value="" selected>—</option>
            <option value="30">30%</option>
            <option value="20">20%</option>
            <option value="15">15%</option>
            <option value="disc50">50% Staff</option>
          </select>
        </td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
        <td class="ss-net"><input class="ss-inp" type="number" step="0.01" placeholder="0.00" readonly style="color:#166534;font-weight:700;background:transparent"></td>
        <td><input class="ss-inp mop txt" type="text" placeholder=""></td>
        <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssChanged()"></td>
        <td><input class="ss-inp mop txt" type="text" placeholder=""></td>
        <td><input class="ss-inp rem txt" type="text" placeholder=""></td>
        <td class="ss-action-cell"><button class="btn-ss-save" onclick="saveSsTable(this)">Save</button><button class="btn-del-row" onclick="delSsRow(this)">✕</button></td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="background:#c8a96e;color:#1a0a00;font-weight:800"></td>
        <td class="num" id="ss-tot-regular">0.00</td>
        <td class="num" id="ss-tot-promo">0.00</td>
        <td class="num" id="ss-tot-celeb">0.00</td>
        <td class="num" id="ss-tot-disc20">0.00</td>
        <td></td>
        <td class="num" id="ss-tot-comm30">0.00</td>
        <td class="num" id="ss-tot-comm20">0.00</td>
        <td class="num" id="ss-tot-comm15">0.00</td>
        <td class="num" id="ss-tot-disc50">0.00</td>
        <td class="num" id="ss-tot-net" style="color:#1b4332">0.00</td>
        <td></td>
        <td class="num" id="ss-tot-advance">0.00</td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <button class="btn-add-row" onclick="addSsRow()">+ Add Row</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     CASH BREAKDOWN
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sr-section-title" style="background:#1a1a1a">💵 Cash Breakdown</div>
  <div class="cb-layout">

    <div>
      <table class="cb-table" id="cb-table">
        <thead>
          <tr><th>QTY</th><th>DENOMINATION</th><th>TOTAL COLLECTION</th></tr>
        </thead>
        <tbody id="cb-body">
          <?php foreach ($CASH_DENOMS as $denom):
            $dKey = (string)(float)$denom;
            $qty  = (int)($cbQtyByDenom[$dKey] ?? 0);
            $tot  = $qty * $denom;
          ?>
          <tr data-denom="<?= $denom ?>">
            <td><input class="cb-inp" type="number" step="1" min="0" value="<?= $qty ?: '' ?>" placeholder="0" oninput="cbChanged()"></td>
            <td class="cb-denom"><?= number_format($denom, $denom == (int)$denom ? 0 : 2) ?></td>
            <td class="cb-tot-cell" id="cb-tot-<?= $dKey ?>"><?= number_format($tot, 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php foreach ($cbExtraRows as $er): ?>
          <tr data-denom="extra">
            <td><input class="cb-inp" type="number" step="1" min="0" value="<?= (int)$er['qty'] ?: '' ?>" placeholder="0" oninput="cbChanged()"></td>
            <td><input class="cb-inp" type="number" step="0.01" value="<?= (float)$er['denomination'] ?: '' ?>" placeholder="0.00" oninput="cbChanged()" style="width:80px"></td>
            <td class="cb-tot-cell">0.00</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="2">TOTAL</td><td id="cb-grand-total"><?= number_format($cbTotal, 2) ?></td></tr>
        </tfoot>
      </table>
      <div style="text-align:center;margin-top:10px">
        <button class="btn-add-row" onclick="addCbRow()" style="margin:0">+ Add Denomination</button>
      </div>
    </div>

    <div class="cb-side">
      <?php
      $subsec2 = [
          'down_payment' => ['label' => 'DOWN PAYMENT (ADV BOOKING)', 'color' => '#1a2f5c', 'wide' => true,  'name_label' => 'Name'],
          'unpaids_corp' => ['label' => 'UNPAIDS CORP.',              'color' => '#a67c00', 'wide' => false, 'name_label' => 'Name'],
          'expenses'     => ['label' => 'EXPENSES',                   'color' => '#6b1530', 'wide' => false, 'name_label' => 'Particular'],
      ];
      foreach ($subsec2 as $sec => $info):
          $label   = $info['label'];
          $isWide  = $info['wide'];
          $default = $isWide ? ['item_name'=>'','amount'=>0,'mop'=>'','remarks'=>''] : ['item_name'=>'','amount'=>0];
          $secRows = $details[$sec] ?? [$default];
      ?>
      <div class="sub-col" data-section="<?= $sec ?>">
        <div class="sub-col-title" style="background:<?= $info['color'] ?>"><?= $label ?></div>
        <div class="sub-col-hdr<?= $isWide ? ' wide' : '' ?>">
          <span><?= $info['name_label'] ?></span><span>Amount</span>
          <?php if ($isWide): ?><span>MOP</span><span>Remarks</span><?php endif; ?>
          <span></span>
        </div>
        <div class="sub-rows">
          <?php foreach ($secRows as $sr): ?>
          <div class="sub-row<?= $isWide ? ' wide' : '' ?>">
            <input class="sub-inp" type="text" placeholder="Enter name…" value="<?= htmlspecialchars($sr['item_name'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
            <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)($sr['amount'] ?? 0) ?: '' ?>" oninput="subChanged('<?= $sec ?>')">
            <?php if ($isWide): ?>
            <input class="sub-inp" type="text" placeholder="MOP" value="<?= htmlspecialchars($sr['mop'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
            <input class="sub-inp" type="text" placeholder="Remarks" value="<?= htmlspecialchars($sr['remarks'] ?? '') ?>" oninput="subChanged('<?= $sec ?>')">
            <?php endif; ?>
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
</div>

<!-- ══════════════════════════════════════════════════════════
     SALES SERVICES (INFLUENCER / MARKETING)
════════════════════════════════════════════════════════════ -->
<?php
// Helper: build the mktg service dropdown cell
// $mktgList items: name, price, fix_cf, at_cost — sourced from the Commission
// Guide's MKTG table so picking a service auto-fills At Cost + Commission Fee.
function mktgSvcCell(string $curSvc, array $mktgList): string {
    $isOther = $curSvc && !in_array($curSvc, array_column($mktgList,'name'));
    $display = $curSvc ?: '— Select Service —';
    $opts  = '<div class="mktg-svc-opt" data-val="" onclick="pickMktgSvc(this,\'\')"
        style="padding:6px 10px;font-size:.72rem;color:#9ca3af;cursor:pointer">— Select Service —</div>';
    foreach ($mktgList as $ps) {
        $n = htmlspecialchars($ps['name'], ENT_QUOTES);
        $price  = (float)$ps['price'];
        $fixCf  = (float)$ps['fix_cf'];
        $atCost = (float)$ps['at_cost'];
        $active = $curSvc === $ps['name'] ? 'mktg-active' : '';
        $opts .= "<div class=\"mktg-svc-opt $active\" data-val=\"$n\" data-atcost=\"$atCost\" data-fixcf=\"$fixCf\"
            onclick=\"pickMktgSvc(this,'{$n}')\"
            style=\"padding:6px 10px;font-size:.72rem;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:6px\">
            <span>" . htmlspecialchars($ps['name']) . "</span>" .
            ($price > 0 ? "<span style=\"font-size:.6rem;color:#6b7280;white-space:nowrap\">₱" . number_format($price, 0) . "</span>" : "") .
            "</div>";
    }
    $opts .= '<div class="mktg-svc-opt mktg-other" data-val="__other__" onclick="pickMktgSvc(this,\'__other__\')"
        style="padding:6px 10px;font-size:.72rem;color:#6b7280;cursor:pointer;border-top:1px solid #f0f2f5">✏ Other (type below)</div>';
    $otherDisp = $isOther ? 'block' : 'none';
    $otherVal  = $isOther ? htmlspecialchars($curSvc) : '';
    return "<td style=\"min-width:180px\">
      <div class=\"mktg-svc-wrap\" style=\"position:relative\">
        <div class=\"mktg-svc-display\" tabindex=\"0\"
             onclick=\"toggleMktgDrop(this)\" onkeydown=\"mktgSvcKey(event,this)\"
             style=\"cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:4px;padding:4px 8px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;font-size:.72rem\">
          <span class=\"mktg-svc-text\" style=\"flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap\">" . htmlspecialchars($display) . "</span>
          <span style=\"font-size:.5rem;color:#9ca3af\">▼</span>
        </div>
        <div class=\"mktg-svc-dropdown\" style=\"display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #c8d4df;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:280px;max-height:280px;overflow:hidden;flex-direction:column\">
          <div style=\"padding:6px 8px 4px;border-bottom:1px solid #f0f2f5\">
            <input type=\"text\" class=\"mktg-svc-search\" placeholder=\"Search service…\"
                   oninput=\"filterMktgSvc(this)\" onclick=\"event.stopPropagation()\"
                   style=\"width:100%;padding:4px 8px;font-size:.71rem;border:1px solid #e0e0e0;border-radius:4px;outline:none\">
          </div>
          <div class=\"mktg-svc-list\" style=\"overflow-y:auto;flex:1;padding:3px 0\">$opts</div>
        </div>
        <input type=\"hidden\" class=\"mktg-svc-val\" value=\"" . htmlspecialchars($curSvc) . "\">
      </div>
      <input class=\"mt-inp txt mktg-other-inp\" type=\"text\" placeholder=\"Custom service…\"
             value=\"$otherVal\" style=\"display:$otherDisp;margin-top:3px\">
    </td>";
}
?>
<div class="sr-section" style="border-top:3px solid #c2185b">
  <div class="sr-section-title" style="background:linear-gradient(135deg,#6b1530,#c2185b);display:flex;align-items:center;justify-content:space-between">
    <span>📣 Sales Services — Influencer / Marketing</span>
    <span style="font-size:.6rem;font-weight:400;opacity:.8;font-family:var(--font-h)">Service dropdown linked to MKTG Guide · Auto-fills At Cost &amp; Commission Fee</span>
  </div>
  <div class="mt-wrap">
  <table class="mt-table" id="mktg-table" style="--mt-hdr:#c2185b">
    <thead>
      <tr style="background:#c2185b">
        <th style="width:64px">Time Start</th>
        <th style="width:64px">Time End</th>
        <th style="width:80px">Service Slip No.</th>
        <th style="width:130px">Client Name</th>
        <th style="min-width:180px">Services <span style="font-weight:400;opacity:.8;font-size:.56rem">(linked to MKTG)</span></th>
        <th style="width:80px">Stylist</th>
        <th style="width:80px">AT COST <span style="font-weight:400;opacity:.8;font-size:.56rem">(auto)</span></th>
        <th style="width:90px">Commission Fee <span style="font-weight:400;opacity:.8;font-size:.56rem">(auto)</span></th>
        <th style="width:95px;background:#1b4332">Total Mktg Exp.</th>
        <th style="width:130px">Remarks</th>
        <th style="width:32px"></th>
      </tr>
    </thead>
    <tbody id="mktg-body">
      <?php foreach ($mktgRows as $mr): ?>
      <tr>
        <td><input class="mt-inp" type="time" value="<?= htmlspecialchars($mr['time_start'] ?? '') ?>"></td>
        <td><input class="mt-inp" type="time" value="<?= htmlspecialchars($mr['time_end'] ?? '') ?>"></td>
        <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($mr['slip_no'] ?? '') ?>"></td>
        <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($mr['client_name'] ?? '') ?>"></td>
        <?= mktgSvcCell($mr['service'] ?? '', $mktgServices) ?>
        <?= stylCell($mr['stylist'] ?? '', $dbStylists, 'mt') ?>
        <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$mr['at_cost'] ?: '' ?>" oninput="mktgRowCalc(this)"></td>
        <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$mr['commission_fee'] ?: '' ?>" oninput="mktgRowCalc(this)"></td>
        <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$mr['total_mktg_exp'] ?: '' ?>" readonly style="color:#166534;font-weight:700;background:rgba(26,77,26,.06)"></td>
        <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($mr['remarks'] ?? '') ?>"></td>
        <td><button class="btn-del-row" onclick="delMktgRow(this)">✕</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($mktgRows)): ?>
      <tr>
        <td><input class="mt-inp" type="time"></td>
        <td><input class="mt-inp" type="time"></td>
        <td><input class="mt-inp txt" type="text"></td>
        <td><input class="mt-inp txt" type="text"></td>
        <?= mktgSvcCell('', $mktgServices) ?>
        <?= stylCell('', $dbStylists, 'mt') ?>
        <td><input class="mt-inp" type="number" step="0.01" oninput="mktgRowCalc(this)"></td>
        <td><input class="mt-inp" type="number" step="0.01" oninput="mktgRowCalc(this)"></td>
        <td><input class="mt-inp" type="number" step="0.01" readonly style="color:#166534;font-weight:700;background:rgba(26,77,26,.06)"></td>
        <td><input class="mt-inp txt" type="text"></td>
        <td><button class="btn-del-row" onclick="delMktgRow(this)">✕</button></td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="text-align:right">TOTAL</td>
        <td id="mktg-atcost-tot"><?= number_format($mktgAtCostTotal, 2) ?></td>
        <td id="mktg-comm-tot"><?= number_format($mktgCommTotal, 2) ?></td>
        <td id="mktg-tot"><?= number_format($mktgTotal, 2) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <button class="btn-add-row" onclick="addMktgRow()">+ Add Row</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     GC SOLD: Service GC | Paid GC
════════════════════════════════════════════════════════════ -->
<div class="sr-section">
  <div class="sr-section-title" style="background:#1b4332">🎁 GC Sold</div>
  <div class="gc-grid three-col">

    <?php
    $gcTables = [
        'service' => ['label' => 'SERVICE GC (SOLD)', 'rows' => $gcServiceRows, 'total' => $gcServiceTotal],
        'paid'    => ['label' => 'PAID GC (SOLD)',     'rows' => $gcPaidRows,    'total' => $gcPaidTotal],
    ];
    foreach ($gcTables as $gcType => $gcInfo):
        $gcRows = $gcInfo['rows'];
    ?>
    <div>
      <div class="sub-col-title" style="background:#1b4332"><?= $gcInfo['label'] ?></div>
      <div class="mt-wrap">
      <table class="mt-table" id="gc-<?= $gcType ?>-table">
        <thead>
          <tr>
            <th style="width:60px">Series</th>
            <th style="width:110px">Name</th>
            <th style="width:70px">Voucher</th>
            <th style="width:44px">Qty</th>
            <th style="width:70px">Amount</th>
            <th style="width:100px">Remarks</th>
            <th style="width:28px"></th>
          </tr>
        </thead>
        <tbody id="gc-<?= $gcType ?>-body">
          <?php foreach ($gcRows as $gr): ?>
          <tr>
            <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($gr['series'] ?? '') ?>"></td>
            <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($gr['name'] ?? '') ?>"></td>
            <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($gr['voucher'] ?? '') ?>"></td>
            <td><input class="mt-inp" type="number" step="1" value="<?= (int)$gr['qty'] ?: '' ?>" oninput="gcChanged('<?= $gcType ?>')"></td>
            <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$gr['amount'] ?: '' ?>" oninput="gcChanged('<?= $gcType ?>')"></td>
            <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($gr['remarks'] ?? '') ?>"></td>
            <td><button class="btn-del-row" onclick="delGcRow(this,'<?= $gcType ?>')">✕</button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($gcRows)): ?>
          <tr>
            <td><input class="mt-inp txt" type="text"></td>
            <td><input class="mt-inp txt" type="text"></td>
            <td><input class="mt-inp txt" type="text"></td>
            <td><input class="mt-inp" type="number" step="1" oninput="gcChanged('<?= $gcType ?>')"></td>
            <td><input class="mt-inp" type="number" step="0.01" oninput="gcChanged('<?= $gcType ?>')"></td>
            <td><input class="mt-inp txt" type="text"></td>
            <td><button class="btn-del-row" onclick="delGcRow(this,'<?= $gcType ?>')">✕</button></td>
          </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" style="text-align:right">Total</td><td id="gc-<?= $gcType ?>-tot"><?= number_format($gcInfo['total'],2) ?></td><td colspan="2"></td></tr>
        </tfoot>
      </table>
      </div>
      <button class="btn-add-row" onclick="addGcRow('<?= $gcType ?>')">+ Add</button>
    </div>
    <?php endforeach; ?>

    <div>
      <div class="sub-col-title" style="background:#223a70">🛍 PRODUCT SOLD</div>
      <div class="mt-wrap">
      <table class="mt-table" id="product-table">
        <thead>
          <tr>
            <th style="width:110px">Particular</th>
            <th style="width:44px">Qty</th>
            <th style="width:60px">Price</th>
            <th style="width:60px">Amount</th>
            <th style="width:28px"></th>
          </tr>
        </thead>
        <tbody id="product-body">
          <?php foreach ($productRows as $pr): ?>
          <tr>
            <td><input class="mt-inp txt" type="text" value="<?= htmlspecialchars($pr['particular'] ?? '') ?>"></td>
            <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$pr['qty'] ?: '' ?>" oninput="productRowCalc(this)"></td>
            <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$pr['price'] ?: '' ?>" oninput="productRowCalc(this)"></td>
            <td><input class="mt-inp" type="number" step="0.01" value="<?= (float)$pr['amount'] ?: '' ?>" readonly style="color:#166534;font-weight:700;background:transparent"></td>
            <td><button class="btn-del-row" onclick="delProductRow(this)">✕</button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($productRows)): ?>
          <tr>
            <td><input class="mt-inp txt" type="text"></td>
            <td><input class="mt-inp" type="number" step="0.01" oninput="productRowCalc(this)"></td>
            <td><input class="mt-inp" type="number" step="0.01" oninput="productRowCalc(this)"></td>
            <td><input class="mt-inp" type="number" step="0.01" readonly style="color:#166534;font-weight:700;background:transparent"></td>
            <td><button class="btn-del-row" onclick="delProductRow(this)">✕</button></td>
          </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3" style="text-align:right">Total</td><td id="product-tot"><?= number_format($productTotal,2) ?></td><td></td></tr>
        </tfoot>
      </table>
      </div>
      <button class="btn-add-row" onclick="addProductRow()">+ Add Row</button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUMMARY REPORT — Recovery Branch
════════════════════════════════════════════════════════════ -->
<div class="sr-wrap">
<div class="sr-card">
  <div class="sr-title-row"><div class="sr-main-title">Summary Report</div></div>

  <div class="sr-row" style="background:#f8f9fb">
    <div class="sr-label" style="text-align:left;flex:1">For</div>
    <div class="sr-value-wrap"><span class="sr-input readout" style="font-size:.8rem"><?= date('n/j/Y', strtotime($fDate)) ?></span></div>
  </div>

  <div class="sr-row green">
    <div class="sr-label">GROSS SALES <span style="font-size:.65rem;opacity:.7">(auto: POS Reading + Sold GC)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="gross_sales" value="<?= $fmt($grossSalesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">STAFF CF <span style="font-size:.65rem;opacity:.7">(auto: 30%+20%+15% Commission + MKTG Commission Fee)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="staff_cf" value="<?= $fmt($staffCfCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">SOLD GC <span style="font-size:.65rem;opacity:.7">(auto: Service GC Sold)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="sold_gc" value="<?= $fmt($soldGcCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">POS READING <span style="font-size:.65rem;opacity:.7">(auto: Regular Price + Product Sold)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="pos_reading" value="<?= $fmt($posReadingCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">DISCOUNTS</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="discounts" value="<?= $v('discounts') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">CELEB. DISCOUNTS 10% <span style="font-size:.65rem;opacity:.7">(auto: Celebration Promo 10%)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="celeb_discounts" value="<?= $fmt($celebDiscountsCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row green">
    <div class="sr-label">REDEEMED GC</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="redeemed_gc" value="<?= $v('redeemed_gc') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">SWIPER</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="swiper" value="<?= $v('swiper') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">GCASH (SALES)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="gcash_sales" value="<?= $v('gcash_sales') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">MAYA (SALES)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="maya_sales" value="<?= $v('maya_sales') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">MAYA (DP)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="maya_dp" value="<?= $v('maya_dp') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">UNPAIDS <span style="font-size:.65rem;opacity:.7">(auto: Unpaids Corp.)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="unpaids" value="<?= $fmt($unpaidsCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">ADVANCE PAYMENT <span style="font-size:.65rem;opacity:.7">(auto: Advance Payment in Sales Services)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="advance_payment" value="<?= $fmt($advancePaymentCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">EXPENSES <span style="font-size:.65rem;opacity:.7">(auto: Expenses sub-section)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="expenses" value="<?= $fmt($expensesCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">MARKETING EXPENSE <span style="font-size:.65rem;opacity:.7">(auto: At Cost in Influencer)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="marketing_expense" value="<?= $fmt($marketingExpenseCalc) ?>" readonly tabindex="-1"></div>
  </div>
  <div class="sr-row pink-val">
    <div class="sr-label">PRODUCT SOLD <span style="font-size:.65rem;opacity:.7">(auto: Product Sold total)</span></div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="product_sold" value="<?= $fmt($productSoldCalc) ?>" readonly tabindex="-1"></div>
  </div>

  <div class="sr-row net">
    <div class="sr-label">Net Cash</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="net_cash" value="<?= $fmt($v('net_cash')) ?>" readonly tabindex="-1"></div>
  </div>

  <div class="sr-row coh">
    <div class="sr-label">COH (Cash on Hand)</div>
    <div class="sr-value-wrap"><input type="number" step="0.01" class="sr-input" id="coh" value="<?= $v('coh') ?: '' ?>" oninput="recalc()" placeholder="0.00"></div>
    <div class="sr-note" style="color:#cfd8ea">(cash sales only)</div>
  </div>

  <div class="sr-row short">
    <div class="sr-label">(Short) Over</div>
    <div class="sr-value-wrap"><input type="text" class="sr-input readout" id="short_over" value="<?= $fmt($v('short_over')) ?>" readonly tabindex="-1"></div>
    <div class="sr-note" style="color:#ffd8d8">(if "-" means short)</div>
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

const SERVICES = <?= json_encode(array_column($dbServices, 'name')) ?>;

// Regular/Promo price lookup, keyed by service name — sourced from the
// Commission Guide's Services Price List (recovery_services_pricelist),
// so picking a service here auto-fills its price instead of retyping it.
const SERVICE_PRICE_MAP = <?= json_encode(array_column($dbServices, null, 'name')) ?>;

function buildSvcDropdown(selectedVal = '') {
  const isOther = selectedVal && !SERVICES.includes(selectedVal);
  const displayText = selectedVal && !isOther ? selectedVal : (isOther ? selectedVal : '— Select Service —');
  let opts = `<div class="svc-opt ${!selectedVal?'active':''}" data-val="" onclick="pickSvc(this,'')">— Select Service —</div>`;
  SERVICES.forEach(s => {
    opts += `<div class="svc-opt ${selectedVal===s?'active':''}" data-val="${s.replace(/"/g,'&quot;')}" onclick="pickSvc(this,'${s.replace(/'/g,"\\'").replace(/"/g,'&quot;')}')">${s}</div>`;
  });
  opts += `<div class="svc-opt other ${isOther?'active':''}" data-val="__other__" onclick="pickSvc(this,'__other__')">✏ Other (type below)</div>`;
  return `<div class="svc-wrap">
    <div class="svc-display" tabindex="0" onclick="toggleSvcDrop(this)" onkeydown="svcKeyDown(event,this)">
      <span class="svc-text">${selectedVal||'— Select Service —'}</span>
      <span class="svc-arrow">▼</span>
    </div>
    <div class="svc-dropdown">
      <div class="svc-search-wrap">
        <input class="svc-search" type="text" placeholder="Search service…" oninput="filterSvc(this)" onclick="event.stopPropagation()">
      </div>
      <div class="svc-list">${opts}</div>
    </div>
    <input class="svc-sel-hidden svc-val" type="hidden" value="${selectedVal}">
  </div>
  <input class="ss-inp svc txt svc-other-inp" type="text" placeholder="Custom service…"
    value="${isOther?selectedVal:''}"
    style="display:${isOther?'block':'none'};margin-top:3px" oninput="ssChanged()">`;
}

// ── Custom dropdown functions ──────────────────────────────
function toggleSvcDrop(btn) {
  const wrap = btn.closest('.svc-wrap');
  const drop = wrap.querySelector('.svc-dropdown');
  const isOpen = drop.classList.contains('open');

  // close all others first
  document.querySelectorAll('.svc-dropdown.open').forEach(d => {
    d.classList.remove('open');
    d.closest('.svc-wrap')?.querySelector('.svc-display')?.classList.remove('open');
  });

  if (!isOpen) {
    // position using fixed coords so it escapes table overflow
    const rect = btn.getBoundingClientRect();
    const dropW = 280;
    let left = rect.left;
    let top  = rect.bottom + 4;
    // prevent going off right edge
    if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
    // if not enough space below, open upward
    if (top + 320 > window.innerHeight - 8) top = rect.top - 320 - 4;
    drop.style.left = left + 'px';
    drop.style.top  = top  + 'px';

    drop.classList.add('open');
    btn.classList.add('open');
    const search = drop.querySelector('.svc-search');
    search.value = '';
    filterSvc(search);
    setTimeout(() => search.focus(), 60);
    const active = drop.querySelector('.svc-opt.active');
    if (active) active.scrollIntoView({block:'nearest'});
  }
}

function pickSvc(opt, val) {
  const wrap = opt.closest('.svc-wrap');
  const display = wrap.querySelector('.svc-display .svc-text');
  const hidden  = wrap.querySelector('.svc-val');
  const drop    = wrap.querySelector('.svc-dropdown');
  const otherInp = wrap.nextElementSibling;

  wrap.querySelectorAll('.svc-opt').forEach(o => o.classList.remove('active'));
  opt.classList.add('active');
  hidden.value = val;
  drop.classList.remove('open');
  wrap.querySelector('.svc-display').classList.remove('open');

  if (val === '__other__') {
    display.textContent = '✏ Other…';
    if (otherInp?.classList.contains('svc-other-inp')) {
      otherInp.style.display = 'block';
      otherInp.focus();
    }
  } else {
    display.textContent = val || '— Select Service —';
    if (otherInp?.classList.contains('svc-other-inp')) {
      otherInp.style.display = 'none';
      otherInp.value = '';
    }
  }

  // Auto-fill Regular Price / Promo Price from the Commission Guide's
  // price list when the picked service has a catalog match.
  const priceInfo = SERVICE_PRICE_MAP[val];
  let filled = false;
  if (priceInfo) {
    const tr = wrap.closest('tr');
    const numInps = tr?.querySelectorAll('td input.ss-inp[type="number"]');
    if (numInps && numInps.length >= 2) {
      numInps[0].value = priceInfo.regular ? Number(priceInfo.regular) : '';
      numInps[1].value = priceInfo.promo   ? Number(priceInfo.promo)   : '';
      ssRowCalc(numInps[1]); // also calls ssChanged() internally
      filled = true;
    }
  }
  if (!filled) ssChanged();
}

function filterSvc(inp) {
  const q = inp.value.toLowerCase().trim();
  const list = inp.closest('.svc-dropdown').querySelector('.svc-list');
  list.querySelectorAll('.svc-opt').forEach(opt => {
    const match = !q || opt.dataset.val.toLowerCase().includes(q) || opt.textContent.toLowerCase().includes(q);
    opt.style.display = match ? '' : 'none';
  });
  const anyVisible = [...list.querySelectorAll('.svc-opt')].some(o => o.style.display !== 'none');
  let noMatch = list.querySelector('.svc-no-match');
  if (!anyVisible) {
    if (!noMatch) { noMatch = document.createElement('div'); noMatch.className='svc-no-match'; noMatch.textContent='No match'; list.appendChild(noMatch); }
    noMatch.style.display = '';
  } else if (noMatch) noMatch.style.display = 'none';
}

function svcKeyDown(e, btn) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleSvcDrop(btn); }
  if (e.key === 'Escape') { document.querySelectorAll('.svc-dropdown.open').forEach(d => { d.classList.remove('open'); d.closest('.svc-wrap')?.querySelector('.svc-display')?.classList.remove('open'); }); }
}

// close on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.svc-wrap')) {
    document.querySelectorAll('.svc-dropdown.open').forEach(d => {
      d.classList.remove('open');
      d.closest('.svc-wrap')?.querySelector('.svc-display')?.classList.remove('open');
    });
  }
});

// reposition on scroll/resize
function repositionOpenDrop() {
  const open = document.querySelector('.svc-dropdown.open');
  if (!open) return;
  const btn = open.closest('.svc-wrap')?.querySelector('.svc-display');
  if (!btn) return;
  const rect = btn.getBoundingClientRect();
  const dropW = 280;
  let left = rect.left;
  let top  = rect.bottom + 4;
  if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
  if (top + 320 > window.innerHeight - 8) top = rect.top - 320 - 4;
  open.style.left = left + 'px';
  open.style.top  = top  + 'px';
}
window.addEventListener('scroll', repositionOpenDrop, true);
window.addEventListener('resize', repositionOpenDrop);

// ── Cash Breakdown helpers ──────────────────────────────────
function cbChanged() {
  let grand = 0;
  document.querySelectorAll('#cb-body tr').forEach(tr => {
    const denomAttr = tr.dataset.denom;
    const qtyInp = tr.querySelectorAll('input')[0];
    const qty = parseFloat(qtyInp?.value) || 0;
    let denom = 0;
    if (denomAttr === 'extra') {
      denom = parseFloat(tr.querySelectorAll('input')[1]?.value) || 0;
    } else {
      denom = parseFloat(denomAttr) || 0;
    }
    const rowTotal = qty * denom;
    const totCell = tr.querySelector('td:last-child');
    if (totCell) totCell.textContent = fmt(rowTotal);
    grand += rowTotal;
  });
  document.getElementById('cb-grand-total').textContent = fmt(grand);
  // Cash Breakdown reflects the physically counted cash drawer
  setVal('coh', grand.toFixed(2));
  recalc();
}

function addCbRow() {
  const tbody = document.getElementById('cb-body');
  const tr = document.createElement('tr');
  tr.dataset.denom = 'extra';
  tr.innerHTML = `
    <td><input class="cb-inp" type="number" step="1" min="0" placeholder="0" oninput="cbChanged()"></td>
    <td><input class="cb-inp" type="number" step="0.01" placeholder="0.00" oninput="cbChanged()" style="width:80px"></td>
    <td class="cb-tot-cell">0.00</td>`;
  tbody.appendChild(tr);
}

function getCbRows() {
  const rows = [];
  document.querySelectorAll('#cb-body tr').forEach(tr => {
    const denomAttr = tr.dataset.denom;
    const inps = tr.querySelectorAll('input');
    const qty = parseFloat(inps[0]?.value) || 0;
    const denomination = denomAttr === 'extra' ? (parseFloat(inps[1]?.value) || 0) : parseFloat(denomAttr);
    if (qty > 0 || denomination > 0) rows.push({ denomination, qty });
  });
  return rows;
}

// ── Sales Services (Influencer/Marketing) helpers ───────────
function mktgRowCalc(inp) {
  const tr = inp.closest('tr');
  const nums = tr.querySelectorAll('input[type=number]');
  const atCost = parseFloat(nums[0]?.value) || 0;
  const comm   = parseFloat(nums[1]?.value) || 0;
  const total  = atCost + comm;
  if (nums[2]) nums[2].value = total > 0 ? total.toFixed(2) : '';
  mktgChanged();
}

function mktgChanged() {
  let atCostTot = 0, commTot = 0, tot = 0;
  document.querySelectorAll('#mktg-body tr').forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    atCostTot += parseFloat(nums[0]?.value) || 0;
    commTot   += parseFloat(nums[1]?.value) || 0;
    tot       += parseFloat(nums[2]?.value) || 0;
  });
  document.getElementById('mktg-atcost-tot').textContent = fmt(atCostTot);
  document.getElementById('mktg-comm-tot').textContent   = fmt(commTot);
  document.getElementById('mktg-tot').textContent        = fmt(tot);
  recalcSummary();
}

// ── Stylist Dropdown JS ────────────────────────────────────
const STYLISTS = <?= json_encode(array_map(fn($s) => [
  'name'    => $s['name'],
  'handles' => $s['handles'],
], $dbStylists)) ?>;

function buildStylCell(selectedVal = '') {
  let opts = `<div class="styl-opt" data-val="" onclick="pickStyl(this,'')"
      style="padding:6px 10px;font-size:.72rem;color:#9ca3af;cursor:pointer">— Stylist —</div>`;
  STYLISTS.forEach(st => {
    const n = st.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const handles = st.handles
      ? `<span style="font-size:.57rem;color:#9ca3af;display:block;white-space:normal;line-height:1.2">${st.handles}</span>`
      : '';
    opts += `<div class="styl-opt ${selectedVal===st.name?'styl-active':''}"
        data-val="${n}" onclick="pickStyl(this,'${n}')"
        style="padding:6px 10px;font-size:.72rem;color:#374151;cursor:pointer">
        ${st.name}${handles}</div>`;
  });
  return `<td style="min-width:80px">
    <div class="styl-wrap">
      <div class="styl-display" tabindex="0"
           onclick="toggleStylDrop(this)" onkeydown="stylKey(event,this)">
        <span class="styl-text">${selectedVal||'— Stylist —'}</span>
        <span class="styl-arrow">▼</span>
      </div>
      <div class="styl-dropdown">
        <div class="styl-search-wrap">
          <input class="styl-search" type="text" placeholder="Search stylist…"
                 oninput="filterStyl(this)" onclick="event.stopPropagation()">
        </div>
        <div class="styl-list">${opts}</div>
      </div>
      <input type="hidden" class="styl-val" value="${selectedVal}">
    </div>
  </td>`;
}

function toggleStylDrop(btn) {
  const wrap = btn.closest('.styl-wrap');
  const drop = wrap.querySelector('.styl-dropdown');
  const isOpen = drop.classList.contains('open');
  // Close all open stylist dropdowns
  document.querySelectorAll('.styl-dropdown.open').forEach(d => {
    d.classList.remove('open');
    d.closest('.styl-wrap')?.querySelector('.styl-display')?.classList.remove('open');
  });
  if (!isOpen) {
    const rect = btn.getBoundingClientRect(), dropW = 200;
    let left = rect.left, top = rect.bottom + 4;
    if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
    if (top + 240 > window.innerHeight - 8) top = rect.top - 240 - 4;
    drop.style.left = left + 'px'; drop.style.top = top + 'px';
    drop.classList.add('open'); btn.classList.add('open');
    const s = drop.querySelector('.styl-search');
    if (s) { s.value = ''; filterStyl(s); setTimeout(() => s.focus(), 60); }
    const active = drop.querySelector('.styl-active');
    if (active) active.scrollIntoView({ block: 'nearest' });
  }
}

function pickStyl(opt, val) {
  const wrap    = opt.closest('.styl-wrap');
  const display = wrap.querySelector('.styl-text');
  const hidden  = wrap.querySelector('.styl-val');
  const drop    = wrap.querySelector('.styl-dropdown');
  wrap.querySelectorAll('.styl-opt').forEach(o => o.classList.remove('styl-active'));
  opt.classList.add('styl-active');
  hidden.value = val;
  display.textContent = val || '— Stylist —';
  drop.classList.remove('open');
  wrap.querySelector('.styl-display')?.classList.remove('open');
}

function filterStyl(inp) {
  const q = inp.value.toLowerCase().trim();
  inp.closest('.styl-dropdown').querySelector('.styl-list')
    .querySelectorAll('.styl-opt').forEach(o => {
      o.style.display = (!q || o.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
}

function stylKey(e, btn) {
  if (e.key==='Enter'||e.key===' ') { e.preventDefault(); toggleStylDrop(btn); }
  if (e.key==='Escape') {
    document.querySelectorAll('.styl-dropdown.open').forEach(d => {
      d.classList.remove('open');
      d.closest('.styl-wrap')?.querySelector('.styl-display')?.classList.remove('open');
    });
  }
}

// Close styl dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.styl-wrap')) {
    document.querySelectorAll('.styl-dropdown.open').forEach(d => {
      d.classList.remove('open');
      d.closest('.styl-wrap')?.querySelector('.styl-display')?.classList.remove('open');
    });
  }
});

// Reposition styl dropdown on scroll/resize
window.addEventListener('scroll', () => {
  const open = document.querySelector('.styl-dropdown.open');
  if (!open) return;
  const btn = open.closest('.styl-wrap')?.querySelector('.styl-display');
  if (!btn) return;
  const rect = btn.getBoundingClientRect(), dropW = 200;
  let left = rect.left, top = rect.bottom + 4;
  if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
  if (top + 240 > window.innerHeight - 8) top = rect.top - 240 - 4;
  open.style.left = left + 'px'; open.style.top = top + 'px';
}, true);
const MKTG_SERVICES = <?= json_encode(array_map(fn($s) => [
  'name'    => $s['name'],
  'price'   => (float)$s['price'],
  'at_cost' => (float)$s['at_cost'],
  'fix_cf'  => (float)$s['fix_cf'],
], $mktgServices)) ?>;

function buildMktgSvcCell(selectedVal = '') {
  const isOther = selectedVal && !MKTG_SERVICES.some(s => s.name === selectedVal);
  const display = selectedVal && !isOther ? selectedVal : (isOther ? selectedVal : '— Select Service —');
  let opts = `<div class="mktg-svc-opt" data-val="" onclick="pickMktgSvc(this,'')"
      style="padding:6px 10px;font-size:.72rem;color:#9ca3af;cursor:pointer">— Select Service —</div>`;
  MKTG_SERVICES.forEach(s => {
    const priceTag = s.price > 0 ? `<span style="font-size:.6rem;color:#6b7280;white-space:nowrap">₱${s.price.toLocaleString()}</span>` : '';
    opts += `<div class="mktg-svc-opt ${selectedVal===s.name?'mktg-active':''}"
        data-val="${s.name.replace(/"/g,'&quot;')}" data-atcost="${s.at_cost}" data-fixcf="${s.fix_cf}"
        onclick="pickMktgSvc(this,'${s.name.replace(/'/g,"\\'").replace(/"/g,'&quot;')}')"
        style="padding:6px 10px;font-size:.72rem;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:6px">
        <span>${s.name}</span>${priceTag}</div>`;
  });
  opts += `<div class="mktg-svc-opt mktg-other" data-val="__other__" onclick="pickMktgSvc(this,'__other__')"
      style="padding:6px 10px;font-size:.72rem;color:#6b7280;cursor:pointer;border-top:1px solid #f0f2f5">✏ Other (type below)</div>`;
  return `<td style="min-width:180px">
    <div class="mktg-svc-wrap" style="position:relative">
      <div class="mktg-svc-display" tabindex="0"
           onclick="toggleMktgDrop(this)" onkeydown="mktgSvcKey(event,this)"
           style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:4px;padding:4px 8px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;font-size:.72rem">
        <span class="mktg-svc-text" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${display}</span>
        <span style="font-size:.5rem;color:#9ca3af">▼</span>
      </div>
      <div class="mktg-svc-dropdown" style="display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #c8d4df;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:280px;max-height:280px;overflow:hidden;flex-direction:column">
        <div style="padding:6px 8px 4px;border-bottom:1px solid #f0f2f5">
          <input type="text" class="mktg-svc-search" placeholder="Search service…"
                 oninput="filterMktgSvc(this)" onclick="event.stopPropagation()"
                 style="width:100%;padding:4px 8px;font-size:.71rem;border:1px solid #e0e0e0;border-radius:4px;outline:none">
        </div>
        <div class="mktg-svc-list" style="overflow-y:auto;flex:1;padding:3px 0">${opts}</div>
      </div>
      <input type="hidden" class="mktg-svc-val" value="${selectedVal}">
    </div>
    <input class="mt-inp txt mktg-other-inp" type="text" placeholder="Custom service…"
           value="${isOther ? selectedVal : ''}" style="display:${isOther?'block':'none'};margin-top:3px">
  </td>`;
}

function toggleMktgDrop(btn) {
  const wrap = btn.closest('.mktg-svc-wrap');
  const drop = wrap.querySelector('.mktg-svc-dropdown');
  const isOpen = drop.style.display === 'flex';
  document.querySelectorAll('.mktg-svc-dropdown').forEach(d => { d.style.display='none'; });
  if (!isOpen) {
    const rect = btn.getBoundingClientRect(), dropW = 280;
    let left = rect.left, top = rect.bottom + 4;
    if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
    if (top + 280 > window.innerHeight - 8) top = rect.top - 280 - 4;
    drop.style.left = left+'px'; drop.style.top = top+'px';
    drop.style.display = 'flex';
    const s = drop.querySelector('.mktg-svc-search');
    if (s) { s.value=''; filterMktgSvc(s); setTimeout(()=>s.focus(),60); }
    const active = drop.querySelector('.mktg-active');
    if (active) active.scrollIntoView({block:'nearest'});
  }
}

function pickMktgSvc(opt, val) {
  const wrap = opt.closest('.mktg-svc-wrap');
  const display = wrap.querySelector('.mktg-svc-text');
  const hidden  = wrap.querySelector('.mktg-svc-val');
  const drop    = wrap.querySelector('.mktg-svc-dropdown');
  const otherInp = wrap.nextElementSibling;
  wrap.querySelectorAll('.mktg-svc-opt').forEach(o => o.classList.remove('mktg-active'));
  opt.classList.add('mktg-active');
  hidden.value = val;
  drop.style.display = 'none';
  if (val === '__other__') {
    display.textContent = '✏ Other…';
    if (otherInp?.classList.contains('mktg-other-inp')) { otherInp.style.display='block'; otherInp.focus(); }
  } else {
    display.textContent = val || '— Select Service —';
    if (otherInp?.classList.contains('mktg-other-inp')) { otherInp.style.display='none'; otherInp.value=''; }
    // Auto-fill At Cost + Commission Fee from the MKTG guide (like a VLOOKUP) —
    // always overwrite on service change, since these are looked-up values.
    const atCost = parseFloat(opt.dataset.atcost) || 0;
    const fixCf  = parseFloat(opt.dataset.fixcf)  || 0;
    const tr = wrap.closest('tr');
    const nums = tr?.querySelectorAll('input[type=number]');
    if (nums?.[0]) nums[0].value = atCost > 0 ? atCost.toFixed(2) : '';
    if (nums?.[1]) nums[1].value = fixCf  > 0 ? fixCf.toFixed(2)  : '';
    if (nums?.[0]) mktgRowCalc(nums[0]);
  }
}

function filterMktgSvc(inp) {
  const q = inp.value.toLowerCase().trim();
  const list = inp.closest('.mktg-svc-dropdown').querySelector('.mktg-svc-list');
  list.querySelectorAll('.mktg-svc-opt').forEach(o => {
    o.style.display = (!q || o.dataset.val.toLowerCase().includes(q) || o.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}

function mktgSvcKey(e, btn) {
  if (e.key==='Enter'||e.key===' ') { e.preventDefault(); toggleMktgDrop(btn); }
  if (e.key==='Escape') document.querySelectorAll('.mktg-svc-dropdown').forEach(d=>d.style.display='none');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.mktg-svc-wrap'))
    document.querySelectorAll('.mktg-svc-dropdown').forEach(d => d.style.display='none');
});

function addMktgRow() {
  const tbody = document.getElementById('mktg-body');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="mt-inp" type="time"></td>
    <td><input class="mt-inp" type="time"></td>
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp txt" type="text"></td>
    ${buildMktgSvcCell()}
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp" type="number" step="0.01" oninput="mktgRowCalc(this)"></td>
    <td><input class="mt-inp" type="number" step="0.01" oninput="mktgRowCalc(this)"></td>
    <td><input class="mt-inp" type="number" step="0.01" readonly style="color:#166534;font-weight:700;background:rgba(26,77,26,.06)"></td>
    <td><input class="mt-inp txt" type="text"></td>
    <td><button class="btn-del-row" onclick="delMktgRow(this)">✕</button></td>`;
  tbody.appendChild(tr);
}

function delMktgRow(btn) { btn.closest('tr').remove(); mktgChanged(); }

function getMktgRows() {
  const rows = [];
  document.querySelectorAll('#mktg-body tr').forEach(tr => {
    const times = tr.querySelectorAll('input[type=time]');
    const txts  = tr.querySelectorAll('input[type=text]');
    const nums  = tr.querySelectorAll('input[type=number]');
    // Service from connected dropdown
    const svcVal = tr.querySelector('.mktg-svc-val');
    const svcOther = tr.querySelector('.mktg-other-inp');
    const service = svcVal
      ? (svcVal.value === '__other__' ? (svcOther?.value || '') : svcVal.value)
      : (txts[2]?.value || '');
    rows.push({
      time_start: times[0]?.value || '', time_end: times[1]?.value || '',
      slip_no: txts[0]?.value || '', client_name: txts[1]?.value || '',
      service,
      stylist: txts[txts.length > 4 ? 3 : 2]?.value || '',
      at_cost: parseFloat(nums[0]?.value)||0,
      commission_fee: parseFloat(nums[1]?.value)||0,
      total_mktg_exp: parseFloat(nums[2]?.value)||0,
      remarks: txts[txts.length > 4 ? 4 : 3]?.value || '',
    });
  });
  return rows;
}

// ── GC Sold helpers (Service GC / Paid GC) ──────────────────
function gcChanged(type) {
  let tot = 0;
  document.querySelectorAll(`#gc-${type}-body tr`).forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    tot += parseFloat(nums[1]?.value) || 0;
  });
  document.getElementById(`gc-${type}-tot`).textContent = fmt(tot);
  recalcSummary();
}

function sumGcType(type) {
  let tot = 0;
  document.querySelectorAll(`#gc-${type}-body tr`).forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    tot += parseFloat(nums[1]?.value) || 0;
  });
  return tot;
}

function addGcRow(type) {
  const tbody = document.getElementById(`gc-${type}-body`);
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp" type="number" step="1" oninput="gcChanged('${type}')"></td>
    <td><input class="mt-inp" type="number" step="0.01" oninput="gcChanged('${type}')"></td>
    <td><input class="mt-inp txt" type="text"></td>
    <td><button class="btn-del-row" onclick="delGcRow(this,'${type}')">✕</button></td>`;
  tbody.appendChild(tr);
}

function delGcRow(btn, type) { btn.closest('tr').remove(); gcChanged(type); }

function getGcRows(type) {
  const rows = [];
  document.querySelectorAll(`#gc-${type}-body tr`).forEach(tr => {
    const txts = tr.querySelectorAll('input[type=text]');
    const nums = tr.querySelectorAll('input[type=number]');
    rows.push({
      series: txts[0]?.value || '', name: txts[1]?.value || '', voucher: txts[2]?.value || '',
      qty: parseFloat(nums[0]?.value)||0, amount: parseFloat(nums[1]?.value)||0, remarks: txts[3]?.value || '',
    });
  });
  return rows;
}

// ── Product Sold helpers ─────────────────────────────────────
function productRowCalc(inp) {
  const tr = inp.closest('tr');
  const nums = tr.querySelectorAll('input[type=number]');
  const qty = parseFloat(nums[0]?.value) || 0;
  const price = parseFloat(nums[1]?.value) || 0;
  const amount = qty * price;
  if (nums[2]) nums[2].value = amount > 0 ? amount.toFixed(2) : '';
  productChanged();
}

function productChanged() {
  let tot = 0;
  document.querySelectorAll('#product-body tr').forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    tot += parseFloat(nums[2]?.value) || 0;
  });
  document.getElementById('product-tot').textContent = fmt(tot);
  recalcSummary();
}

function addProductRow() {
  const tbody = document.getElementById('product-body');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="mt-inp txt" type="text"></td>
    <td><input class="mt-inp" type="number" step="0.01" oninput="productRowCalc(this)"></td>
    <td><input class="mt-inp" type="number" step="0.01" oninput="productRowCalc(this)"></td>
    <td><input class="mt-inp" type="number" step="0.01" readonly style="color:#166534;font-weight:700;background:transparent"></td>
    <td><button class="btn-del-row" onclick="delProductRow(this)">✕</button></td>`;
  tbody.appendChild(tr);
}

function delProductRow(btn) { btn.closest('tr').remove(); productChanged(); }

function getProductRows() {
  const rows = [];
  document.querySelectorAll('#product-body tr').forEach(tr => {
    const txt = tr.querySelector('input[type=text]');
    const nums = tr.querySelectorAll('input[type=number]');
    rows.push({
      particular: txt?.value || '', qty: parseFloat(nums[0]?.value)||0,
      price: parseFloat(nums[1]?.value)||0, amount: parseFloat(nums[2]?.value)||0,
    });
  });
  return rows;
}

// ── Sub-section helpers ────────────────────────────────────
function subChanged(sec) {
  const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
  let tot = 0;
  col.querySelectorAll('input[type=number]').forEach(i => tot += parseFloat(i.value)||0);
  const totEl = document.getElementById(`sub-tot-${sec}`);
  if (totEl) totEl.textContent = 'P' + fmt(tot);
  recalcSummary();
}

function addSubRow(sec) {
  const col  = document.querySelector(`.sub-col[data-section="${sec}"]`);
  const rows = col.querySelector('.sub-rows');
  const isWide = !!col.querySelector('.sub-col-hdr.wide');
  const div  = document.createElement('div');
  div.className = 'sub-row' + (isWide ? ' wide' : '');
  div.innerHTML = isWide ? `
    <input class="sub-inp" type="text" placeholder="Enter name…" oninput="subChanged('${sec}')">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="subChanged('${sec}')">
    <input class="sub-inp" type="text" placeholder="MOP" oninput="subChanged('${sec}')">
    <input class="sub-inp" type="text" placeholder="Remarks" oninput="subChanged('${sec}')">
    <button class="btn-del-row" onclick="delSubRow(this,'${sec}')" style="margin:2px 4px">✕</button>` : `
    <input class="sub-inp" type="text" placeholder="Enter name…" oninput="subChanged('${sec}')">
    <input class="sub-inp num" type="number" step="0.01" placeholder="0.00" oninput="subChanged('${sec}')">
    <button class="btn-del-row" onclick="delSubRow(this,'${sec}')" style="margin:2px 4px">✕</button>`;
  rows.appendChild(div);
}

function delSubRow(btn, sec) {
  btn.closest('.sub-row').remove();
  subChanged(sec);
}

// ── Sales Services helpers ─────────────────────────────────
function ssRowCalc(inp) {
  const tr   = inp.closest('tr');
  const inps = tr.querySelectorAll('input[type=number]');
  const rateSel = tr.querySelector('.comm-rate-sel');
  // Col order: regular, promo, celeb10, disc20, comm30, comm20, comm15, disc50, net, advance
  const regular = parseFloat(inps[0]?.value) || 0;
  const promo   = parseFloat(inps[1]?.value) || regular || 0;
  const celeb   = parseFloat(inps[2]?.value) || 0;
  const disc20  = parseFloat(inps[3]?.value) || 0;

  // Auto-compute the ONE applicable commission/discount tier from Promo Price
  const rateMap = {'30':0.30, '20':0.20, '15':0.15, 'disc50':0.50};
  const rate = rateSel ? rateSel.value : '';
  const fee  = (rate && rateMap[rate]) ? +(promo * rateMap[rate]).toFixed(2) : 0;
  if (inps[4]) inps[4].value = rate==='30'     ? fee.toFixed(2) : '';
  if (inps[5]) inps[5].value = rate==='20'     ? fee.toFixed(2) : '';
  if (inps[6]) inps[6].value = rate==='15'     ? fee.toFixed(2) : '';
  if (inps[7]) inps[7].value = rate==='disc50' ? fee.toFixed(2) : '';

  const comm30  = parseFloat(inps[4]?.value) || 0;
  const comm20  = parseFloat(inps[5]?.value) || 0;
  const comm15  = parseFloat(inps[6]?.value) || 0;
  // disc50 (50% Staff Disc) is recorded for reference only — NOT deducted from Net Sales
  // Net Sales = Regular Price − Celeb 10% − Disc 20% (PWD/SNR) − Comm 30% − Comm 20% − Comm 15%
  const net = regular - celeb - disc20 - comm30 - comm20 - comm15;
  const netInp = inps[8];
  if (netInp) netInp.value = net > 0 ? net.toFixed(2) : '';
  ssChanged();
}

function ssChanged() {
  let totReg=0, totPro=0, totCeleb=0, totDisc20=0, totComm30=0;
  let totComm20=0, totComm15=0, totDisc50=0, totNet=0, totAdv=0;

  document.querySelectorAll('#ss-body tr').forEach(tr => {
    const inps = tr.querySelectorAll('input[type=number]');
    totReg    += parseFloat(inps[0]?.value)||0;
    totPro    += parseFloat(inps[1]?.value)||0;
    totCeleb  += parseFloat(inps[2]?.value)||0;
    totDisc20 += parseFloat(inps[3]?.value)||0;
    totComm30 += parseFloat(inps[4]?.value)||0;
    totComm20 += parseFloat(inps[5]?.value)||0;
    totComm15 += parseFloat(inps[6]?.value)||0;
    totDisc50 += parseFloat(inps[7]?.value)||0;
    totNet    += parseFloat(inps[8]?.value)||0;
    totAdv    += parseFloat(inps[9]?.value)||0;
  });

  document.getElementById('ss-tot-regular').textContent = fmt(totReg);
  document.getElementById('ss-tot-promo').textContent   = fmt(totPro);
  document.getElementById('ss-tot-celeb').textContent   = fmt(totCeleb);
  document.getElementById('ss-tot-disc20').textContent  = fmt(totDisc20);
  document.getElementById('ss-tot-comm30').textContent  = fmt(totComm30);
  document.getElementById('ss-tot-comm20').textContent  = fmt(totComm20);
  document.getElementById('ss-tot-comm15').textContent  = fmt(totComm15);
  document.getElementById('ss-tot-disc50').textContent  = fmt(totDisc50);
  document.getElementById('ss-tot-net').textContent     = fmt(totNet);
  document.getElementById('ss-tot-advance').textContent = fmt(totAdv);

  recalcSummary();
}

function addSsRow() {
  const tbody = document.getElementById('ss-body');
  const tr    = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="ss-inp time" type="time" onchange="ssChanged()"></td>
    <td><input class="ss-inp time" type="time" onchange="ssChanged()"></td>
    <td><input class="ss-inp slip txt" type="text" placeholder=""></td>
    <td><input class="ss-inp name txt" type="text" placeholder=""></td>
    <td>${buildSvcDropdown()}</td>
    ${buildStylCell()}
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssRowCalc(this)"></td>
    <td>
      <select class="ss-inp comm-rate-sel" oninput="ssRowCalc(this)">
        <option value="" selected>—</option>
        <option value="30">30%</option>
        <option value="20">20%</option>
        <option value="15">15%</option>
        <option value="disc50">50% Staff</option>
      </select>
    </td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" readonly style="background:#f8fafc;color:#475569"></td>
    <td class="ss-net"><input class="ss-inp" type="number" step="0.01" placeholder="0.00" readonly style="color:#166534;font-weight:700;background:transparent"></td>
    <td><input class="ss-inp mop txt" type="text" placeholder=""></td>
    <td><input class="ss-inp" type="number" step="0.01" placeholder="" oninput="ssChanged()"></td>
    <td><input class="ss-inp mop txt" type="text" placeholder=""></td>
    <td><input class="ss-inp rem txt" type="text" placeholder=""></td>
    <td class="ss-action-cell"><button class="btn-ss-save" onclick="saveSsTable(this)">Save</button><button class="btn-del-row" onclick="delSsRow(this)">✕</button></td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[type=time]').focus();
}

function delSsRow(btn) {
  btn.closest('tr').remove();
  ssChanged();
}

function getSsRows() {
  const rows = [];
  document.querySelectorAll('#ss-body tr').forEach(tr => {
    const allInps = tr.querySelectorAll('input');
    const numInps = tr.querySelectorAll('input[type=number]');
    const svcVal    = tr.querySelector('.svc-val');
    const svcOtherInp = tr.querySelector('.svc-other-inp');
    let service = '';
    if (svcVal) {
      service = svcVal.value === '__other__'
        ? (svcOtherInp?.value || '')
        : svcVal.value;
    }
    rows.push({
      time_in:          allInps[0]?.value || '',
      time_out:         allInps[1]?.value || '',
      slip_no:          allInps[2]?.value || '',
      client_name:      allInps[3]?.value || '',
      service:          service,
      stylist:          tr.querySelector('.styl-val')?.value || '',
      regular_price:    parseFloat(numInps[0]?.value)||0,
      promo_price:      parseFloat(numInps[1]?.value)||0,
      comm_rate:        tr.querySelector('.comm-rate-sel')?.value || '',
      celeb_promo_10:   parseFloat(numInps[2]?.value)||0,
      disc_20_pwd_snr:  parseFloat(numInps[3]?.value)||0,
      comm_30:          parseFloat(numInps[4]?.value)||0,
      comm_20:          parseFloat(numInps[5]?.value)||0,
      comm_15:          parseFloat(numInps[6]?.value)||0,
      disc_50_staff:    parseFloat(numInps[7]?.value)||0,
      net_sales:        parseFloat(numInps[8]?.value)||0,
      mode_of_payment:  tr.querySelectorAll('input[type=text]')[5]?.value || '',
      advance_payment:  parseFloat(numInps[9]?.value)||0,
      mop:              tr.querySelectorAll('input[type=text]')[6]?.value || '',
      remarks:          tr.querySelectorAll('input[type=text]')[7]?.value || '',
    });
  });
  return rows;
}
function subSectionSum(sec) {
  const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
  if (!col) return 0;
  let tot = 0;
  col.querySelectorAll('input[type=number]').forEach(i => tot += parseFloat(i.value)||0);
  return tot;
}

// Recomputes all auto-fed summary fields from their source tables, then recalc()'s Net Cash
function recalcSummary() {
  // Sales Services sums (col order: regular, promo, celeb10, disc20, comm30, comm20, comm15, disc50, net, advance)
  let ssRegular=0, ssCeleb=0, ssComm30=0, ssComm20=0, ssComm15=0, ssAdvance=0;
  document.querySelectorAll('#ss-body tr').forEach(tr => {
    const n = tr.querySelectorAll('input[type=number]');
    ssRegular += parseFloat(n[0]?.value)||0;
    ssCeleb   += parseFloat(n[2]?.value)||0;
    ssComm30  += parseFloat(n[4]?.value)||0;
    ssComm20  += parseFloat(n[5]?.value)||0;
    ssComm15  += parseFloat(n[6]?.value)||0;
    ssAdvance += parseFloat(n[9]?.value)||0;
  });

  // Influencer / Marketing sums (col order: at_cost, commission_fee, total_mktg_exp)
  let mktgAtCost=0, mktgCommFee=0;
  document.querySelectorAll('#mktg-body tr').forEach(tr => {
    const n = tr.querySelectorAll('input[type=number]');
    mktgAtCost  += parseFloat(n[0]?.value)||0;
    mktgCommFee += parseFloat(n[1]?.value)||0;   // FIX: was reading index 2 (Total Mktg Exp, which double-counts At Cost) instead of the Commission Fee column
  });

  // Service GC Sold sum only (Paid GC excluded)
  let gcServiceTot=0;
  document.querySelectorAll('#gc-service-body tr').forEach(tr => {
    const n = tr.querySelectorAll('input[type=number]');
    gcServiceTot += parseFloat(n[1]?.value)||0;
  });

  // Product Sold sum
  let productTot=0;
  document.querySelectorAll('#product-body tr').forEach(tr => {
    const n = tr.querySelectorAll('input[type=number]');
    productTot += parseFloat(n[2]?.value)||0;
  });

  const unpaidsTot  = subSectionSum('unpaids_corp');
  const expensesTot = subSectionSum('expenses');

  // POS Reading = Regular Price (Sales Services) + Product Sold only.
  // Marketing/Influencer At Cost is a separate cost line (marketing_expense),
  // it does NOT ring up on the POS and must not be added here.
  const posReading       = ssRegular + productTot;
  const staffCf          = (ssComm30 + ssComm20 + ssComm15) + mktgCommFee;
  const marketingExpense = mktgAtCost;
  // Gross Sales = POS Reading + Sold GC (Staff CF/Marketing Expense are costs, not revenue)
  const grossSales       = posReading + gcServiceTot;

  setVal('pos_reading',       posReading.toFixed(2));
  setVal('staff_cf',          staffCf.toFixed(2));
  setVal('marketing_expense', marketingExpense.toFixed(2));
  setVal('gross_sales',       grossSales.toFixed(2));
  setVal('sold_gc',           gcServiceTot.toFixed(2));
  setVal('celeb_discounts',   ssCeleb.toFixed(2));
  setVal('advance_payment',   ssAdvance.toFixed(2));
  setVal('unpaids',           unpaidsTot.toFixed(2));
  setVal('expenses',          expensesTot.toFixed(2));
  setVal('product_sold',      productTot.toFixed(2));

  recalc();
}

function recalc() {
  // Net Cash = POS Reading - Discounts - Celeb. Discounts + Redeemed GC - Swiper - GCash - Maya Sales
  //           - Maya DP - Unpaids - Advance Payment - Expenses - Marketing Expense - Product Sold
  const netCash = gv('pos_reading')
                - gv('discounts')
                - gv('celeb_discounts')
                + gv('redeemed_gc')
                - gv('swiper')
                - gv('gcash_sales')
                - gv('maya_sales')
                - gv('maya_dp')
                - gv('unpaids')
                - gv('advance_payment')
                - gv('expenses')
                - gv('marketing_expense')
                - gv('product_sold');

  const shortOver = gv('coh') - netCash;

  document.getElementById('net_cash').value  = fmt(netCash);
  const soEl = document.getElementById('short_over');
  soEl.value = fmt(shortOver);
  soEl.style.color = shortOver < 0 ? '#ffd54f' : '#b71c1c';
}

// ── Save All ───────────────────────────────────────────────
// Saves the whole Sales Services table (stored as delete-all + re-insert-all
// per date, so there's no single-row save on the backend — every row's
// Save button just triggers this same full-table save).
async function saveSsTable(btn) {
  if (btn) { btn.textContent = '…'; btn.disabled = true; }
  const fd = new FormData();
  fd.append('ajax_save_services','1');
  fd.append('report_date', FDATE);
  fd.append('rows', JSON.stringify(getSsRows()));
  let ok = false;
  try {
    const r = await fetch('recovery_sales_report.php', {method:'POST',body:fd});
    const d = await r.json();
    ok = !!d.ok;
    if (ok) showToast('✓ Sales Services saved', 'success');
    else showToast('❌ Sales Services: ' + (d.msg||'save failed'), 'error');
  } catch (e) {
    showToast('❌ Sales Services: network error', 'error');
  }
  if (btn) { btn.textContent = 'Save'; btn.disabled = false; }
  return ok;
}

async function saveAll() {
  if (REPORT_LOCKED) {
    showToast('🔒 This date is locked and cannot be edited.', 'error');
    return;
  }
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  // 0. Save Sales Services rows
  await saveSsTable();

  // 2. Save each sub-section
  const allSecs = ['down_payment','unpaids_corp','expenses'];
  for (const sec of allSecs) {
    const col = document.querySelector(`.sub-col[data-section="${sec}"]`);
    if (!col) continue;
    const isWide = !!col.querySelector('.sub-col-hdr.wide');
    const rows = [];
    col.querySelectorAll('.sub-row').forEach(row => {
      const inps = row.querySelectorAll('input');
      if (isWide) {
        rows.push({ name: inps[0]?.value||'', amount: parseFloat(inps[1]?.value)||0, mop: inps[2]?.value||'', remarks: inps[3]?.value||'' });
      } else {
        rows.push({ name: inps[0]?.value||'', amount: parseFloat(inps[1]?.value)||0 });
      }
    });
    const fd2 = new FormData();
    fd2.append('ajax_save_detail','1');
    fd2.append('report_date', FDATE);
    fd2.append('section', sec);
    fd2.append('rows', JSON.stringify(rows));
    await fetch('recovery_sales_report.php', {method:'POST',body:fd2});
  }

  // 2b. Save Cash Breakdown
  const fdCb = new FormData();
  fdCb.append('ajax_save_cash','1');
  fdCb.append('report_date', FDATE);
  fdCb.append('rows', JSON.stringify(getCbRows()));
  await fetch('recovery_sales_report.php', {method:'POST',body:fdCb});

  // 2c. Save Marketing/Influencer Sales Services
  const fdMktg = new FormData();
  fdMktg.append('ajax_save_mktg','1');
  fdMktg.append('report_date', FDATE);
  fdMktg.append('rows', JSON.stringify(getMktgRows()));
  await fetch('recovery_sales_report.php', {method:'POST',body:fdMktg});

  // 2d. Save GC Sold (Service + Paid)
  for (const gcType of ['service','paid']) {
    const fdGc = new FormData();
    fdGc.append('ajax_save_gc','1');
    fdGc.append('report_date', FDATE);
    fdGc.append('gc_type', gcType);
    fdGc.append('rows', JSON.stringify(getGcRows(gcType)));
    await fetch('recovery_sales_report.php', {method:'POST',body:fdGc});
  }

  // 2e. Save Product Sold
  const fdProd = new FormData();
  fdProd.append('ajax_save_product','1');
  fdProd.append('report_date', FDATE);
  fdProd.append('rows', JSON.stringify(getProductRows()));
  await fetch('recovery_sales_report.php', {method:'POST',body:fdProd});

  // 3. Save main summary
  const fd3 = new FormData();
  fd3.append('ajax_save','1');
  fd3.append('report_date', FDATE);
  fd3.append('opening_cashier', document.getElementById('opening_cashier')?.value || '');
  fd3.append('closing_cashier', document.getElementById('closing_cashier')?.value || '');
  ['gross_sales','staff_cf','sold_gc','pos_reading','discounts','celeb_discounts',
   'redeemed_gc','swiper','gcash_sales','maya_sales','maya_dp','unpaids',
   'advance_payment','expenses','marketing_expense','product_sold','coh'].forEach(id => fd3.append(id, gv(id)));

  const res  = await fetch('recovery_sales_report.php', {method:'POST',body:fd3});
  const data = await res.json();
  if (data.ok) {
    status.textContent = '✓ Saved'; status.style.color = 'var(--accent)';
    showToast('✓ Sales report saved', 'success');
  } else {
    showToast('❌ ' + data.msg, 'error'); status.textContent = '❌ Error';
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
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
  const willLock = !REPORT_LOCKED_RAW;
  hideLockPopover();

  const fd = new FormData();
  fd.append('ajax_toggle_lock', '1');
  fd.append('report_date', FDATE);
  fd.append('lock', willLock ? '1' : '0');

  try {
    const res  = await fetch('recovery_sales_report.php', {method:'POST', body:fd});
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

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', () => {
  ssChanged();
  cbChanged(); mktgChanged(); gcChanged('service'); gcChanged('paid'); productChanged();
  recalcSummary();
  if (REPORT_LOCKED) lockPage();
});
</script>
</body>
</html>