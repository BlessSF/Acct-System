<?php
// ============================================================
//  pub_express_month_end_inv.php — Pub Express Month End Inventory
//  LEFT TABLE:  ITEMS × OP/GRAB/BB/DEL/QTY SOLD/TOTAL OUT/ENDING/VARIANCES
//  RIGHT TABLE: BREAKDOWN × PER SERVING/OP/GRAB/SALES/TOTAL SOLD/DOZEN/TOTAL OUT
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Pub Express') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create tables ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_inv_main` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `inv_date`      date NOT NULL,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Pub Express',
    `item_name`     varchar(200) NOT NULL DEFAULT '',
    `sort_order`    int(4) NOT NULL DEFAULT 0,
    `op`            decimal(12,4) NOT NULL DEFAULT 0.0000,
    `grab`          decimal(12,4) NOT NULL DEFAULT 0.0000,
    `bb`            decimal(12,4) NOT NULL DEFAULT 0.0000,
    `del`           decimal(12,4) NOT NULL DEFAULT 0.0000,
    `qty_sold_op`   decimal(12,4) NOT NULL DEFAULT 0.0000,
    `qty_sold_grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
    `total_out`     decimal(12,4) NOT NULL DEFAULT 0.0000,
    `ending`        decimal(12,4) NOT NULL DEFAULT 0.0000,
    `op_sale`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `grab_sales`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `actual_ending` decimal(12,4) NOT NULL DEFAULT 0.0000,
    `variances`     decimal(12,4) NOT NULL DEFAULT 0.0000,
    `saved_by`      varchar(100) DEFAULT NULL,
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Add unique key if missing (safe — ignored if already exists)
try { $pdo->exec("ALTER TABLE `pub_express_inv_main` ADD UNIQUE KEY `ux_date_item` (`inv_date`,`store_name`,`item_name`(100))"); } catch(Throwable $ignored){}

$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_inv_breakdown` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `inv_date`        date NOT NULL,
    `store_name`      varchar(50) NOT NULL DEFAULT 'Pub Express',
    `group_name`      varchar(200) NOT NULL DEFAULT '',
    `item_name`       varchar(200) NOT NULL DEFAULT '',
    `sort_order`      int(4) NOT NULL DEFAULT 0,
    `per_serving`     decimal(12,4) NOT NULL DEFAULT 0.0000,
    `op`              decimal(12,4) NOT NULL DEFAULT 0.0000,
    `grab`            decimal(12,4) NOT NULL DEFAULT 0.0000,
    `sales_op`        decimal(12,4) NOT NULL DEFAULT 0.0000,
    `sales_grab`      decimal(12,4) NOT NULL DEFAULT 0.0000,
    `bd_op`           decimal(12,4) NOT NULL DEFAULT 0.0000,
    `bd_grab`         decimal(12,4) NOT NULL DEFAULT 0.0000,
    `total_sold_out`  decimal(12,4) NOT NULL DEFAULT 0.0000,
    `converted_dozen` decimal(12,4) NOT NULL DEFAULT 0.0000,
    `total_out`       decimal(12,4) NOT NULL DEFAULT 0.0000,
    `saved_by`        varchar(100) DEFAULT NULL,
    `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Date filter ───────────────────────────────────────────
$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));
$daysInMonth = (int)date('t', mktime(0,0,0,$fMonth,1,$fYear));
$fDay   = (int)($_GET['day'] ?? $daysInMonth);
$fDay   = max(1, min($daysInMonth, $fDay));
$selectedDate = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $fDay);
$displayDate  = $months[$fMonth-1].' '.str_pad($fDay,2,'0',STR_PAD_LEFT).', '.$fYear;

// ── AJAX: Save main row ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_main') {
    header('Content-Type: application/json');
    try {
        $id   = (int)($_POST['id'] ?? 0);
        $item = trim($_POST['item_name'] ?? '');
        $so   = (int)($_POST['sort_order'] ?? 0);
        $f    = fn($k) => (float)($_POST[$k] ?? 0);
        $op=$f('op'); $grab=$f('grab'); $bb=$f('bb'); $del=$f('del');
        $qsop=$f('qty_sold_op'); $qsgrab=$f('qty_sold_grab');
        $totalOut = $qsop + $qsgrab;
        $ending   = ($op + $grab + $bb + $del) - $totalOut;
        $opSale=$f('op_sale'); $grabSales=$f('grab_sales');
        $actualEnding=$f('actual_ending');
        $variances = $actualEnding - $ending;

        if ($id > 0) {
            $pdo->prepare("UPDATE pub_express_inv_main SET inv_date=?,item_name=?,sort_order=?,op=?,grab=?,bb=?,del=?,qty_sold_op=?,qty_sold_grab=?,total_out=?,ending=?,op_sale=?,grab_sales=?,actual_ending=?,variances=?,saved_by=? WHERE id=? AND store_name='Pub Express'")
                ->execute([$selectedDate,$item,$so,$op,$grab,$bb,$del,$qsop,$qsgrab,$totalOut,$ending,$opSale,$grabSales,$actualEnding,$variances,$user['name'],$id]);
        } else {
            $pdo->prepare("INSERT INTO pub_express_inv_main (inv_date,store_name,item_name,sort_order,op,grab,bb,del,qty_sold_op,qty_sold_grab,total_out,ending,op_sale,grab_sales,actual_ending,variances,saved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE item_name=VALUES(item_name),op=VALUES(op),grab=VALUES(grab),bb=VALUES(bb),del=VALUES(del),qty_sold_op=VALUES(qty_sold_op),qty_sold_grab=VALUES(qty_sold_grab),total_out=VALUES(total_out),ending=VALUES(ending),op_sale=VALUES(op_sale),grab_sales=VALUES(grab_sales),actual_ending=VALUES(actual_ending),variances=VALUES(variances),saved_by=VALUES(saved_by)")
                ->execute([$selectedDate,'Pub Express',$item,$so,$op,$grab,$bb,$del,$qsop,$qsgrab,$totalOut,$ending,$opSale,$grabSales,$actualEnding,$variances,$user['name']]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'total_out'=>$totalOut,'ending'=>$ending,'variances'=>$variances]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Save breakdown row ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_bd') {
    header('Content-Type: application/json');
    try {
        $id   = (int)($_POST['id'] ?? 0);
        $grp  = trim($_POST['group_name'] ?? '');
        $item = trim($_POST['item_name']  ?? '');
        $so   = (int)($_POST['sort_order'] ?? 0);
        $f    = fn($k) => (float)($_POST[$k] ?? 0);
        $ps=$f('per_serving'); $op=$f('op'); $grab=$f('grab');
        $sop=$f('sales_op'); $sgrab=$f('sales_grab');
        $bdop=$f('bd_op'); $bdgrab=$f('bd_grab');
        $totalSold = $bdop + $bdgrab;
        $converted = $ps > 0 ? round($totalSold / $ps, 4) : 0;
        $totalOut  = $f('total_out');

        if ($id > 0) {
            $pdo->prepare("UPDATE pub_express_inv_breakdown SET inv_date=?,group_name=?,item_name=?,sort_order=?,per_serving=?,op=?,grab=?,sales_op=?,sales_grab=?,bd_op=?,bd_grab=?,total_sold_out=?,converted_dozen=?,total_out=?,saved_by=? WHERE id=? AND store_name='Pub Express'")
                ->execute([$selectedDate,$grp,$item,$so,$ps,$op,$grab,$sop,$sgrab,$bdop,$bdgrab,$totalSold,$converted,$totalOut,$user['name'],$id]);
        } else {
            $pdo->prepare("INSERT INTO pub_express_inv_breakdown (inv_date,store_name,group_name,item_name,sort_order,per_serving,op,grab,sales_op,sales_grab,bd_op,bd_grab,total_sold_out,converted_dozen,total_out,saved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$selectedDate,'Pub Express',$grp,$item,$so,$ps,$op,$grab,$sop,$sgrab,$bdop,$bdgrab,$totalSold,$converted,$totalOut,$user['name']]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'total_sold_out'=>$totalSold,'converted_dozen'=>$converted]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_main') {
    header('Content-Type: application/json');
    $pdo->prepare("DELETE FROM pub_express_inv_main WHERE id=? AND store_name='Pub Express'")->execute([(int)$_POST['id']]);
    echo json_encode(['ok'=>true]); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_bd') {
    header('Content-Type: application/json');
    $pdo->prepare("DELETE FROM pub_express_inv_breakdown WHERE id=? AND store_name='Pub Express'")->execute([(int)$_POST['id']]);
    echo json_encode(['ok'=>true]); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_group') {
    header('Content-Type: application/json');
    $grp = trim($_POST['group_name'] ?? '');
    $pdo->prepare("DELETE FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=? AND group_name=?")
        ->execute([$selectedDate, $grp]);
    echo json_encode(['ok'=>true]); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'rename_group') {
    header('Content-Type: application/json');
    $oldName = trim($_POST['old_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');
    if ($oldName === '' || $newName === '') {
        echo json_encode(['ok'=>false,'msg'=>'Group name cannot be empty']); exit;
    }
    $pdo->prepare("UPDATE pub_express_inv_breakdown SET group_name=? WHERE store_name='Pub Express' AND inv_date=? AND group_name=?")
        ->execute([$newName, $selectedDate, $oldName]);
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: Duplicate this date's Items + Breakdown into other
//    day(s). Lets a user build the item/group list once and reuse
//    it across the month — or any other month/year — instead of
//    retyping every item, group, and per-serving value each day. ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'duplicate_day') {
    header('Content-Type: application/json');
    try {
        $srcDate = trim($_POST['src_date'] ?? '');
        $targets = json_decode($_POST['target_dates'] ?? '[]', true);
        $copyQty = !empty($_POST['copy_qty']);
        $mode    = ($_POST['mode'] ?? 'skip'); // skip | append | replace

        if (!$srcDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $srcDate)) {
            echo json_encode(['ok'=>false,'msg'=>'Missing or invalid source date.']); exit;
        }
        if (!is_array($targets) || !count($targets)) {
            echo json_encode(['ok'=>false,'msg'=>'Pick at least one day to duplicate into.']); exit;
        }

        $srcMainStmt = $pdo->prepare("SELECT * FROM pub_express_inv_main WHERE store_name='Pub Express' AND inv_date=? ORDER BY sort_order,id");
        $srcMainStmt->execute([$srcDate]);
        $srcMainRows = $srcMainStmt->fetchAll();

        $srcBdStmt = $pdo->prepare("SELECT * FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=? ORDER BY group_name,sort_order,id");
        $srcBdStmt->execute([$srcDate]);
        $srcBdRows = $srcBdStmt->fetchAll();

        if (!$srcMainRows && !$srcBdRows) {
            echo json_encode(['ok'=>false,'msg'=>'No items or groups on '.$srcDate.' to duplicate yet.']); exit;
        }

        $chkMain = $pdo->prepare("SELECT COUNT(*) FROM pub_express_inv_main WHERE store_name='Pub Express' AND inv_date=?");
        $chkBd   = $pdo->prepare("SELECT COUNT(*) FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=?");
        $delMain = $pdo->prepare("DELETE FROM pub_express_inv_main WHERE store_name='Pub Express' AND inv_date=?");
        $delBd   = $pdo->prepare("DELETE FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=?");

        $insMain = $pdo->prepare("INSERT INTO pub_express_inv_main
            (inv_date,store_name,item_name,sort_order,op,grab,bb,del,qty_sold_op,qty_sold_grab,total_out,ending,op_sale,grab_sales,actual_ending,variances,saved_by)
            VALUES (?,'Pub Express',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insBd = $pdo->prepare("INSERT INTO pub_express_inv_breakdown
            (inv_date,store_name,group_name,item_name,sort_order,per_serving,op,grab,sales_op,sales_grab,bd_op,bd_grab,total_sold_out,converted_dozen,total_out,saved_by)
            VALUES (?,'Pub Express',?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insSummary = $pdo->prepare("INSERT INTO pub_express_inv_summary (inv_date,store_name,discount,saved_by) VALUES (?,'Pub Express',?,?)
                                      ON DUPLICATE KEY UPDATE discount=VALUES(discount),saved_by=VALUES(saved_by)");

        $srcDiscount = 0;
        if ($copyQty) {
            $discStmt = $pdo->prepare("SELECT discount FROM pub_express_inv_summary WHERE store_name='Pub Express' AND inv_date=?");
            $discStmt->execute([$srcDate]);
            $srcDiscount = (float)($discStmt->fetchColumn() ?: 0);
        }

        $pdo->beginTransaction();

        $summary = ['copied'=>[], 'skipped'=>[]];
        foreach ($targets as $tDate) {
            $tDate = trim($tDate);
            if (!$tDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tDate)) continue;
            if ($tDate === $srcDate) continue; // duplicating a date onto itself would just double it up

            $chkMain->execute([$tDate]);
            $chkBd->execute([$tDate]);
            $existing = (int)$chkMain->fetchColumn() + (int)$chkBd->fetchColumn();

            if ($existing > 0) {
                if ($mode === 'skip')   { $summary['skipped'][] = $tDate; continue; }
                if ($mode === 'replace') { $delMain->execute([$tDate]); $delBd->execute([$tDate]); }
                // mode === 'append' just inserts on top of what's there
            }

            foreach ($srcMainRows as $r) {
                $op=$copyQty?$r['op']:0; $grab=$copyQty?$r['grab']:0; $bb=$copyQty?$r['bb']:0; $del=$copyQty?$r['del']:0;
                $qsop=$copyQty?$r['qty_sold_op']:0; $qsgrab=$copyQty?$r['qty_sold_grab']:0;
                $totalOut=$copyQty?$r['total_out']:0; $ending=$copyQty?$r['ending']:0;
                $opSale=$copyQty?$r['op_sale']:0; $grabSales=$copyQty?$r['grab_sales']:0;
                $actualEnding=$copyQty?$r['actual_ending']:0; $variances=$copyQty?$r['variances']:0;
                $insMain->execute([$tDate,$r['item_name'],$r['sort_order'],$op,$grab,$bb,$del,$qsop,$qsgrab,$totalOut,$ending,$opSale,$grabSales,$actualEnding,$variances,$user['name']]);
            }
            foreach ($srcBdRows as $r) {
                $op=$copyQty?$r['op']:0; $grab=$copyQty?$r['grab']:0;
                $sop=$copyQty?$r['sales_op']:0; $sgrab=$copyQty?$r['sales_grab']:0;
                $bdop=$copyQty?$r['bd_op']:0; $bdgrab=$copyQty?$r['bd_grab']:0;
                $totalSold=$copyQty?$r['total_sold_out']:0; $converted=$copyQty?$r['converted_dozen']:0;
                $totalOut=$copyQty?$r['total_out']:0;
                $insBd->execute([$tDate,$r['group_name'],$r['item_name'],$r['sort_order'],$r['per_serving'],$op,$grab,$sop,$sgrab,$bdop,$bdgrab,$totalSold,$converted,$totalOut,$user['name']]);
            }
            if ($copyQty) {
                $insSummary->execute([$tDate, $srcDiscount, $user['name']]);
            }

            $summary['copied'][] = $tDate;
        }

        $pdo->commit();
        echo json_encode(['ok'=>true,'summary'=>$summary,'mainCount'=>count($srcMainRows),'bdCount'=>count($srcBdRows)]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Row counts (Items + Breakdown) for an arbitrary
//    month/year — powers the "Duplicate to Other Days" modal
//    when the user browses to a month other than the one
//    currently on screen (e.g. next month). ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'month_counts') {
    header('Content-Type: application/json');
    try {
        $yr = (int)($_POST['year'] ?? 0);
        $mo = (int)($_POST['month'] ?? 0);
        if ($yr < 2000 || $yr > 2100 || $mo < 1 || $mo > 12) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid month/year.']); exit;
        }
        $cStmt = $pdo->prepare("SELECT DAY(inv_date) AS d, COUNT(*) AS c FROM (
            SELECT inv_date FROM pub_express_inv_main WHERE store_name='Pub Express' AND YEAR(inv_date)=? AND MONTH(inv_date)=?
            UNION ALL
            SELECT inv_date FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND YEAR(inv_date)=? AND MONTH(inv_date)=?
        ) t GROUP BY DAY(inv_date)");
        $cStmt->execute([$yr, $mo, $yr, $mo]);
        $counts = array_column($cStmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'd');
        $days   = (int)date('t', mktime(0,0,0,$mo,1,$yr));
        echo json_encode(['ok'=>true,'counts'=>(object)$counts,'days'=>$days]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Sales Summary (discount) table ─────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pub_express_inv_summary` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `inv_date`   date NOT NULL,
    `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
    `discount`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`   varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_date` (`inv_date`,`store_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_summary') {
    header('Content-Type: application/json');
    try {
        $discount = (float)($_POST['discount'] ?? 0);
        $pdo->prepare("INSERT INTO pub_express_inv_summary (inv_date,store_name,discount,saved_by) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE discount=VALUES(discount),saved_by=VALUES(saved_by)")
            ->execute([$selectedDate,'Pub Express',$discount,$user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Load data ─────────────────────────────────────────────
$mainRows = $pdo->prepare("SELECT * FROM pub_express_inv_main WHERE store_name='Pub Express' AND inv_date=? ORDER BY sort_order,id");
$mainRows->execute([$selectedDate]);
$mainRows = $mainRows->fetchAll();

$bdRows = $pdo->prepare("SELECT * FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=? ORDER BY group_name,sort_order,id");
$bdRows->execute([$selectedDate]);
$bdRows = $bdRows->fetchAll();

// Row counts (Items + Breakdown) per day-of-month, for the
// "Duplicate to Other Days" modal (lets us warn when a target day
// already has data, and pre-renders the grid for the current
// month without an extra AJAX round trip).
$dupCountStmt = $pdo->prepare("SELECT DAY(inv_date) AS d, COUNT(*) AS c FROM (
    SELECT inv_date FROM pub_express_inv_main WHERE store_name='Pub Express' AND YEAR(inv_date)=? AND MONTH(inv_date)=?
    UNION ALL
    SELECT inv_date FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND YEAR(inv_date)=? AND MONTH(inv_date)=?
) t GROUP BY DAY(inv_date)");
$dupCountStmt->execute([$fYear, $fMonth, $fYear, $fMonth]);
$dupDayCounts = array_column($dupCountStmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'd');

// Display helper: show at most 2 decimals, and trim trailing
// zeros (5.0000 -> 5, 5.2500 -> 5.25). Fixes stored decimal(12,4)
// values printing as e.g. "5.0000" in the inputs.
function nf($v): string {
    $v = (float)$v;
    if ($v == 0) return '';
    $s = number_format($v, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s;
}

$bdGrouped = [];
foreach ($bdRows as $r) $bdGrouped[$r['group_name']][] = $r;

// ── Export Items as Excel (.xlsx) ────────────────────────────
if (isset($_GET['export_main_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Pub Express'));
    $xlsx->title('Pub Express — Items');
    $xlsx->subtitle($selectedDate);
    $xlsx->blank();
    $xlsx->header(['Item','OP','GRAB','BB','DEL','Qty Sold (OP)','Qty Sold (GRAB)','Total Out','Ending','OP Sale','Grab Sales','Actual Ending','Variances']);
    foreach ($mainRows as $r) {
        $xlsx->row([$r['item_name'],$r['op'],$r['grab'],$r['bb'],$r['del'],$r['qty_sold_op'],$r['qty_sold_grab'],$r['total_out'],$r['ending'],$r['op_sale'],$r['grab_sales'],$r['actual_ending'],$r['variances']]);
    }
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->output('Pub_Express_Items_' . $selectedDate . '.xlsx');
}

// ── Export Breakdown as Excel (.xlsx) ────────────────────────
if (isset($_GET['export_bd_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Pub Express'));
    $xlsx->title('Pub Express — Breakdown');
    $xlsx->subtitle($selectedDate);
    $xlsx->blank();
    $xlsx->header(['Group', 'Item', 'Per Serving', 'Stock OP', 'Stock Grab', 'Sales OP', 'Sales Grab', 'BD OP', 'BD Grab', 'Total Sold Out', 'Converted To Dozen', 'Total Out']);
    foreach ($bdGrouped as $grpName => $grpRows) {
        foreach ($grpRows as $r) {
            $xlsx->row([
                $grpName, $r['item_name'], $r['per_serving'], $r['op'], $r['grab'],
                $r['sales_op'], $r['sales_grab'], $r['bd_op'], $r['bd_grab'],
                $r['total_sold_out'], $r['converted_dozen'], $r['converted_dozen'],
            ]);
        }
    }
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->output('Pub_Express_Breakdown_' . $selectedDate . '.xlsx');
}

// ── Sales Summary totals ────────────────────────────────────
$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(op_sale),0) AS walk_in, COALESCE(SUM(grab_sales),0) AS grab FROM pub_express_inv_main WHERE store_name='Pub Express' AND inv_date=?");
$sumStmt->execute([$selectedDate]);
$sumTotals = $sumStmt->fetch();
$walkInSalesTotal = (float)$sumTotals['walk_in'];  // Items op_sale
$itemsGrabSalesTotal = (float)$sumTotals['grab'];

$bdSumStmt = $pdo->prepare("SELECT COALESCE(SUM(sales_op),0) AS sop, COALESCE(SUM(sales_grab),0) AS sgrab FROM pub_express_inv_breakdown WHERE store_name='Pub Express' AND inv_date=?");
$bdSumStmt->execute([$selectedDate]);
$bdSumTotals = $bdSumStmt->fetch();
$bdSalesOpTotal   = (float)$bdSumTotals['sop'];
$bdSalesGrabTotal = (float)$bdSumTotals['sgrab'];

// WALK IN SALES = Items OP Sale + Breakdown Sales OP
$walkInCombined = $walkInSalesTotal + $bdSalesOpTotal;
// GRAB SALES    = Items Grab Sales + Breakdown Sales Grab
$grabSalesTotal = $itemsGrabSalesTotal + $bdSalesGrabTotal;

$discountRow = $pdo->prepare("SELECT discount FROM pub_express_inv_summary WHERE store_name='Pub Express' AND inv_date=?");
$discountRow->execute([$selectedDate]);
$discountVal = (float)($discountRow->fetchColumn() ?: 0);
$grossSales  = $walkInCombined + $grabSalesTotal - $discountVal;

// ── Export ALL (Items + Breakdown + Sales Summary) as one Excel (.xlsx) ──
if (isset($_GET['export_all_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Pub Express'));
    $xlsx->title('Pub Express — Month End Inventory');
    $xlsx->subtitle($displayDate);
    $xlsx->blank();

    $xlsx->sectionRow('ITEMS');
    $xlsx->header(['Item','OP','GRAB','BB','DEL','Qty Sold (OP)','Qty Sold (GRAB)','Total Out','Ending','OP Sale','Grab Sales','Actual Ending','Variances']);
    foreach ($mainRows as $r) {
        $xlsx->row([$r['item_name'],$r['op'],$r['grab'],$r['bb'],$r['del'],$r['qty_sold_op'],$r['qty_sold_grab'],$r['total_out'],$r['ending'],$r['op_sale'],$r['grab_sales'],$r['actual_ending'],$r['variances']]);
    }
    $xlsx->blank();

    $xlsx->sectionRow('BREAKDOWN');
    $xlsx->header(['Group', 'Item', 'Per Serving', 'Stock OP', 'Stock Grab', 'Sales OP', 'Sales Grab', 'BD OP', 'BD Grab', 'Total Sold Out', 'Converted To Dozen', 'Total Out']);
    foreach ($bdGrouped as $grpName => $grpRows) {
        foreach ($grpRows as $r) {
            $xlsx->row([$grpName, $r['item_name'], $r['per_serving'], $r['op'], $r['grab'], $r['sales_op'], $r['sales_grab'], $r['bd_op'], $r['bd_grab'], $r['total_sold_out'], $r['converted_dozen'], $r['total_out']]);
        }
    }
    $xlsx->blank();

    $xlsx->sectionRow('SALES SUMMARY');
    $xlsx->row(['Walk In Sales — Items OP Sale',          number_format($walkInSalesTotal,2,'.','')]);
    $xlsx->row(['Walk In Sales — Breakdown Sales OP',     number_format($bdSalesOpTotal,2,'.','')]);
    $xlsx->row(['WALK IN SALES TOTAL',                    number_format($walkInCombined,2,'.','')]);
    $xlsx->row(['Grab Sales — Items Grab Sales',          number_format($itemsGrabSalesTotal,2,'.','')]);
    $xlsx->row(['Grab Sales — Breakdown Sales Grab',      number_format($bdSalesGrabTotal,2,'.','')]);
    $xlsx->row(['GRAB SALES TOTAL',                       number_format($grabSalesTotal,2,'.','')]);
    $xlsx->row(['Discount',                               number_format($discountVal,2,'.','')]);
    $xlsx->totalRow(['GROSS SALES',                       number_format($grossSales,2,'.','')]);

    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->output('Pub_Express_MonthEndInv_' . $selectedDate . '.xlsx');
}

$pageTitle  = 'Month End Inv.';
$activePage = 'pub_express_month_end_inv';
include '../layout.php';
?>

<style>
/* ── Header ── */
.inv-header {
  background:linear-gradient(135deg,#7b2d00 0%,#a33d00 100%);
  border-radius:var(--radius);padding:18px 24px 14px;
  margin-bottom:16px;display:flex;align-items:flex-start;
  justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.inv-header .eyebrow{font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.45);margin-bottom:3px;}
.inv-header .title{font-size:1.1rem;font-weight:800;color:#fff;}
.inv-header .subtitle{font-family:var(--font-m);font-size:.66rem;color:rgba(255,255,255,.5);margin-top:3px;}

/* ── Controls ── */
.inv-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;}

/* ── Dual table layout ──
   Each panel gets the FULL page width, stacked one on top of the
   other, instead of being squeezed into two equal side-by-side
   columns. With 11-13 columns per table, squeezing them side by
   side left too little room per cell for larger numbers. */
.inv-dual{display:grid;grid-template-columns:1fr;gap:16px;}

/* ── Table wrapper ── */
.inv-panel{background:#fff;border:1.5px solid #d1d5db;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.inv-panel-head{background:#7b2d00;color:#fff;padding:9px 14px;font-family:var(--font-m);font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;}
.inv-panel-head.right{background:#1e3a5f;}

/* ── Table ── */
.inv-tbl{width:100%;border-collapse:collapse;font-size:.8rem;}
.inv-tbl th{background:#8b3500;color:#fff;padding:8px 10px;font-family:var(--font-m);font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;border:1px solid #6b2900;text-align:center;white-space:nowrap;}
.inv-tbl th.right-th{background:#1e3a5f;border-color:#163060;}
.inv-tbl th.subhead{background:#6b7280;font-size:.58rem;}
.inv-tbl th.subhead.right{background:#2d4a7a;}
.inv-tbl td{border:1px solid #e5e7eb;padding:4px 5px;text-align:center;vertical-align:middle;}
.inv-tbl td.item-td{text-align:left;font-weight:700;background:#f9fafb;padding-left:10px;white-space:nowrap;}
.inv-tbl td.group-td{text-align:left;font-weight:800;background:#dbeafe;padding-left:10px;color:#1e3a5f;white-space:nowrap;}
.inv-tbl td.total-td{background:#fef3c7;font-weight:700;color:#92400e;}
.inv-tbl td.variance-pos{background:#dcfce7;color:#166534;font-weight:700;}
.inv-tbl td.variance-neg{background:#fee2e2;color:#991b1b;font-weight:700;}

/* ── Sticky right-side columns (Variances, Action) ──
   With this many columns the table scrolls horizontally, but
   Variances and Action are the ones people need to see/use no
   matter where they've scrolled to, so pin them to the right
   edge of the scroll area instead of letting them get lost. */
.inv-tbl .sticky-r2, .inv-tbl .sticky-r1 { position: sticky; background: #fff; z-index: 2; }
.inv-tbl .sticky-r1 { right: 0; min-width: 92px; }
.inv-tbl .sticky-r2 { right: 92px; box-shadow: -1px 0 0 #d1d5db inset; }
.inv-tbl th.sticky-r1, .inv-tbl th.sticky-r2 { z-index: 3; }
.inv-tbl th.sticky-r1 { background: #8b3500; right: 0; }
.inv-tbl th.sticky-r2 { background: #8b3500; right: 92px; }
.inv-tbl td.sticky-r2.variance-pos { background: #dcfce7; }
.inv-tbl td.sticky-r2.variance-neg { background: #fee2e2; }
.inv-tbl td.sticky-r1 { background: #fff; }

/* ── Inline inputs ── */
.ci{width:100%;border:none;background:transparent;font-family:var(--font-m);font-size:.8rem;text-align:right;padding:6px 8px;outline:none;min-width:80px;}
.ci:focus{background:#fffbeb;box-shadow:inset 0 0 0 2px #f59e0b;}
.ci.item-ci{text-align:left;font-weight:600;min-width:130px;}
.ci.calc{background:#f0fdf4;color:#166534;font-weight:700;cursor:default;}
.ci.variance-ci{font-weight:700;}

/* ── Action ── */
.act-td{white-space:nowrap;padding:2px 4px !important;}
.btn-sv{padding:3px 8px;background:#15803d;color:#fff;border:none;border-radius:4px;font-size:.6rem;font-weight:700;cursor:pointer;}
.btn-sv:hover{background:#166534;}
.btn-dl{padding:3px 6px;background:transparent;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;font-size:.6rem;cursor:pointer;}
.btn-dl:hover{background:#fee2e2;}
.btn-add{width:100%;padding:6px;background:transparent;border:1px dashed #d1d5db;border-radius:6px;font-family:var(--font-m);font-size:.65rem;color:#6b7280;cursor:pointer;margin-top:4px;}
.btn-add:hover{background:#f0fdf4;border-color:#15803d;color:#15803d;}
.row-st{font-family:var(--font-m);font-size:.55rem;color:#15803d;display:none;text-align:center;}

/* ── Add group btn ── */
.btn-add-group{padding:6px 14px;background:#1e3a5f;color:#fff;border:none;border-radius:7px;font-family:var(--font-m);font-size:.68rem;font-weight:700;cursor:pointer;margin-top:8px;}
.btn-add-group:hover{background:#163060;}

/* ── Breakdown toolbar: search + CSV ── */
.bd-toolbar{display:flex;gap:8px;align-items:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;}
.bd-search{flex:1;min-width:0;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-family:var(--font-m);font-size:.72rem;outline:none;}
.bd-search:focus{border-color:#1e3a5f;box-shadow:0 0 0 2px rgba(30,58,95,.12);}
.btn-bd-csv{padding:6px 12px;background:#fff;border:1px solid #d1d5db;border-radius:6px;font-family:var(--font-m);font-size:.68rem;font-weight:700;color:#374151;text-decoration:none;white-space:nowrap;}
.btn-bd-csv:hover{background:#f3f4f6;}

/* ── Breakdown Sales OP/Grab grand totals ── */
.bd-totals{padding:8px 14px;border-bottom:1px solid #e5e7eb;background:#fff;}
.bd-totals-row{display:flex;justify-content:space-between;padding:3px 0;font-family:var(--font-h);}
.bd-totals-row span:first-child{font-weight:800;color:#1a1a1a;font-size:.76rem;}
.bd-totals-row span:last-child{font-weight:800;color:#b91c1c;font-size:.82rem;font-family:var(--font-m);}
.grp-header-row.bd-hide,tr[data-group].bd-hide{display:none;}
.inv-tbl tbody tr.bd-hide{display:none;}

.toast{position:fixed;top:68px;right:22px;z-index:9999;max-width:320px;animation:fadeSlideDown .3s ease;}

/* ── Sales Summary ── */
.sales-summary-wrap {
  margin-bottom:20px;
  background:#fff;border:1.5px solid #d1d5db;border-radius:10px;
  overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);
  max-width:560px;
}
.sales-summary-head {
  background:#1e3a5f;color:#fff;padding:9px 16px;
  font-family:var(--font-m);font-size:.7rem;font-weight:800;
  letter-spacing:.1em;text-transform:uppercase;
}
.ss-row {
  display:flex;align-items:center;
  border-bottom:1px solid #f0f2f5;min-height:40px;
}
.ss-row:last-child{border-bottom:none;}
.ss-label {
  flex:1;padding:8px 16px;font-family:var(--font-m);
  font-size:.78rem;font-weight:700;color:#374151;
  background:#f9fafb;border-right:1px solid #e5e7eb;
}
.ss-value {
  width:160px;padding:8px 14px;text-align:right;
  font-family:var(--font-m);font-size:.84rem;font-weight:800;
  color:#1e3a5f;
}
.ss-value.green{color:#15803d;}
.ss-value.gross{color:#dc2626;font-size:.92rem;}
.ss-input {
  width:140px;padding:6px 10px;text-align:right;
  font-family:var(--font-m);font-size:.82rem;font-weight:700;
  color:#dc2626;border:1px solid #e5e7eb;border-radius:5px;
  outline:none;margin:0 10px;
}
.ss-input:focus{border-color:#f59e0b;background:#fffbeb;}
.ss-save-btn {
  padding:4px 10px;background:#15803d;color:#fff;border:none;
  border-radius:4px;font-size:.62rem;font-weight:700;cursor:pointer;
  margin-right:8px;white-space:nowrap;
}
.ss-save-btn:hover{background:#166534;}

/* ── Modals ── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(10,12,20,.55);
  backdrop-filter: blur(2px); display: none;
  align-items: center; justify-content: center; z-index: 9999; padding: 16px;
}
.modal-overlay.active { display: flex; }
.modal-box {
  background: #fff; width: 420px; max-width: 100%;
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.35);
  animation: modalPop .15s ease;
}
@keyframes modalPop { from{transform:scale(.96);opacity:0} to{transform:scale(1);opacity:1} }
.modal-header {
  background: #7b2d00; color: #fff; padding: 16px 20px;
  font-family: var(--font-m); font-size: .85rem; font-weight: 800; letter-spacing: .02em;
}
.modal-body { padding: 20px; font-family: var(--font-m); font-size: .8rem; color: #374151; line-height: 1.6; }
.modal-footer {
  padding: 14px 20px; display: flex; justify-content: flex-end; gap: 8px;
  border-top: 1px solid #e5e7eb; background: #fafbfc;
}
.modal-btn { padding: 9px 16px; border-radius: 8px; font-family: var(--font-m); font-size: .75rem; font-weight: 700; cursor: pointer; border: none; }
.modal-btn-cancel  { background: #eef0f3; color: #374151; }
.modal-btn-cancel:hover  { background: #e2e5ea; }
.modal-btn-primary { background: #7b2d00; color: #fff; }
.modal-btn-primary:hover { background: #5f2300; }
.modal-btn-primary:disabled { background:#d1d5db; cursor:not-allowed; }

/* ── Duplicate-to-other-days modal ── */
.modal-box.wide { width: 480px; }
.dup-day-grid {
  display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;
  margin: 10px 0 14px;
}
.dup-day-chip {
  position: relative; display: flex; align-items: center; justify-content: center;
  height: 34px; border: 1px solid #d1d5db; border-radius: 7px;
  font-family: var(--font-m); font-size: .72rem; font-weight: 700;
  cursor: pointer; user-select: none; background: #fff; color: #374151;
  transition: all .12s;
}
.dup-day-chip:hover { border-color: #7b2d00; }
.dup-day-chip.checked { background: #7b2d00; border-color: #7b2d00; color: #fff; }
.dup-day-chip.has-data::after {
  content: ''; position: absolute; top: 3px; right: 3px;
  width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;
}
.dup-day-chip.has-data.checked::after { background: #fff; }
.dup-day-chip.is-source { opacity: .35; cursor: not-allowed; text-decoration: line-through; }
.dup-month-nav { display: flex; align-items: center; gap: 6px; margin-top: 12px; }
.dup-nav-btn {
  flex: none; height: 30px; padding: 0 10px; border: 1px solid #d1d5db;
  border-radius: 7px; background: #fff; color: #374151; cursor: pointer;
  font-family: var(--font-m); font-size: .72rem; font-weight: 700;
}
.dup-nav-btn:hover { border-color: #7b2d00; background: #fff7ed; }
.dup-nav-today { margin-left: auto; color: #1e3a5f; }
.dup-month-nav .dup-select { flex: 1; }
.dup-legend {
  display: flex; align-items: center; gap: 14px; font-family: var(--font-m);
  font-size: .64rem; color: #6b7280; margin-bottom: 10px;
}
.dup-legend span { display: flex; align-items: center; gap: 5px; }
.dup-legend .dot { width: 6px; height: 6px; border-radius: 50%; background: #f59e0b; display: inline-block; }
.dup-quicklinks { display: flex; gap: 12px; margin-bottom: 4px; }
.dup-quicklinks a { font-family: var(--font-m); font-size: .68rem; color: #1e3a5f; cursor: pointer; text-decoration: underline; }
.dup-option-row {
  display: flex; align-items: flex-start; gap: 8px; margin-top: 12px;
  padding-top: 12px; border-top: 1px solid #e5e7eb;
}
.dup-option-row label { font-family: var(--font-m); font-size: .74rem; color: #374151; line-height: 1.4; }
.dup-option-row .hint { display: block; font-size: .65rem; color: #6b7280; margin-top: 2px; }
.dup-select {
  width: 100%; margin-top: 6px; padding: 7px 10px;
  font-family: var(--font-m); font-size: .76rem;
  border: 1px solid #d1d5db; border-radius: 7px; background: #fff; color: #374151;
}
#dupSummary {
  margin-top: 12px; padding: 10px 12px; border-radius: 8px;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  font-family: var(--font-m); font-size: .72rem; color: #166534; display: none;
}
</style>

<!-- Header -->
<div class="inv-header">
  <div>
    <div class="eyebrow">Pub Express · Month End Inventory</div>
    <div class="title">Month End Inventory</div>
    <div class="subtitle"><?= $displayDate ?> · Auto-calculates Total Out, Ending &amp; Variances</div>
  </div>
  <span style="background:rgba(255,255,255,.12);color:#fff;padding:5px 14px;border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600">📌 Pub Express</span>
</div>

<!-- Controls -->
<div class="inv-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:130px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="day" class="form-control" style="max-width:70px" onchange="this.form.submit()">
      <?php for($d=1;$d<=$daysInMonth;$d++): ?>
      <option value="<?=$d?>" <?=$fDay==$d?'selected':''?>><?= str_pad($d,2,'0',STR_PAD_LEFT) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:90px" onchange="this.form.submit()">
      <?php for($y=date('Y')-3;$y<=date('Y')+2;$y++): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
  </form>

  <button type="button" class="btn-bd-csv" id="dupOpenBtn" style="background:#7b2d00;color:#fff;border-color:#7b2d00" onclick="openDuplicateModal()"
          <?= (empty($mainRows) && empty($bdRows)) ? 'disabled title="No items or groups on this date yet to duplicate"' : '' ?>>
    📋 Duplicate to Other Days
  </button>

  <a href="pub_express_month_end_inv.php?export_all_csv=1&year=<?= $fYear ?>&month=<?= $fMonth ?>&day=<?= $fDay ?>" class="btn-bd-csv" style="background:#1e3a5f;color:#fff;border-color:#1e3a5f">⬇ Download All (Excel)</a>
</div>

<!-- ══ SALES SUMMARY ══════════════════════════════════════ -->
<div class="sales-summary-wrap">
  <div class="sales-summary-head">📊 Sales Summary</div>

  <div class="ss-row">
    <div class="ss-label">
      GRAB SALES
      <div style="font-size:.65rem;font-weight:400;color:#6b7280;margin-top:3px;line-height:1.6">
        = Items Grab Sales <span id="ss-grab-items-sub" style="font-family:var(--font-m);color:#15803d"><?= number_format($itemsGrabSalesTotal,2) ?></span>
        + Breakdown Sales Grab <span id="ss-grab-bd-sub" style="font-family:var(--font-m);color:#15803d"><?= number_format($bdSalesGrabTotal,2) ?></span>
      </div>
    </div>
    <div class="ss-value green" id="ss-grab"><?= number_format($grabSalesTotal, 2) ?></div>
  </div>

  <div class="ss-row">
    <div class="ss-label">
      WALK IN SALES
      <div style="font-size:.65rem;font-weight:400;color:#6b7280;margin-top:3px;line-height:1.6">
        = Items OP Sale <span id="ss-walkin-items-sub" style="font-family:var(--font-m);color:#1e3a5f"><?= number_format($walkInSalesTotal,2) ?></span>
        + Breakdown Sales OP <span id="ss-walkin-bd-sub" style="font-family:var(--font-m);color:#1e3a5f"><?= number_format($bdSalesOpTotal,2) ?></span>
      </div>
    </div>
    <div class="ss-value" id="ss-walkin"><?= number_format($walkInSalesTotal + $bdSalesOpTotal, 2) ?></div>
  </div>

  <div class="ss-row">
    <div class="ss-label">BREAKDOWN — SALES OP TOTAL</div>
    <div class="ss-value" id="ss-bd-sales-op"><?= number_format($bdSalesOpTotal, 2) ?></div>
  </div>

  <div class="ss-row">
    <div class="ss-label">BREAKDOWN — SALES GRAB TOTAL</div>
    <div class="ss-value" id="ss-bd-sales-grab"><?= number_format($bdSalesGrabTotal, 2) ?></div>
  </div>

  <div class="ss-row">
    <div class="ss-label">DISCOUNT</div>
    <input class="ss-input" type="number" step="0.01" id="ss-discount"
           value="<?= $discountVal ?: '' ?>" placeholder="0.00"
           oninput="recalcSummary()" onfocus="this.select()">
    <button class="ss-save-btn" onclick="saveSummary()">Save</button>
  </div>

  <div class="ss-row" style="background:#fff8f8;border-top:2px solid #dc2626">
    <div class="ss-label" style="color:#dc2626;font-size:.84rem">GROSS SALES</div>
    <div class="ss-value gross" id="ss-gross"><?= number_format($grossSales, 2) ?></div>
  </div>
</div>

<!-- Dual table layout -->
<div class="inv-dual">

  <!-- ══ LEFT: MAIN TABLE ══ -->
  <div>
    <div class="inv-panel">
      <div class="inv-panel-head">📦 Items</div>

      <div class="bd-toolbar">
        <input type="text" id="mainSearch" class="bd-search" placeholder="Search item…" oninput="filterMain()">
        <a href="pub_express_month_end_inv.php?export_main_csv=1&year=<?= $fYear ?>&month=<?= $fMonth ?>&day=<?= $fDay ?>" class="btn-bd-csv">⬇ Download Excel</a>
      </div>

      <div style="overflow-x:auto">
      <table class="inv-tbl" id="mainTbl">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:110px;text-align:left">ITEMS</th>
            <th colspan="4">STOCK IN</th>
            <th colspan="2">QTY SOLD</th>
            <th rowspan="2">TOTAL OUT</th>
            <th rowspan="2">ENDING</th>
            <th colspan="2">SALES</th>
            <th rowspan="2">ACTUAL ENDING</th>
            <th rowspan="2" class="sticky-r2">VARIANCES</th>
            <th rowspan="2" class="sticky-r1">ACTION</th>
          </tr>
          <tr>
            <th class="subhead">OP</th>
            <th class="subhead">GRAB</th>
            <th class="subhead">BB</th>
            <th class="subhead">DEL</th>
            <th class="subhead">QTY SOLD (OP)</th>
            <th class="subhead">QTY SOLD (GRAB)</th>
            <th class="subhead">OP SALE</th>
            <th class="subhead">GRAB SALES</th>
          </tr>
        </thead>
        <tbody id="mainTbody">
          <?php foreach ($mainRows as $r): ?>
          <tr data-id="<?= $r['id'] ?>">
            <td class="item-td"><input class="ci item-ci" type="text" value="<?= htmlspecialchars($r['item_name']) ?>" placeholder="Item name…" oninput="mainChanged(this)"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['op']) ?>"            placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['grab']) ?>"          placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['bb']) ?>"            placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['del']) ?>"           placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['qty_sold_op']) ?>"   placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['qty_sold_grab']) ?>" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['total_out']) ?>" readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['ending']) ?>"    readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['op_sale']) ?>"    readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['grab_sales']) ?>" readonly tabindex="-1"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['actual_ending']) ?>" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
            <td class="sticky-r2 <?= $r['variances'] < 0 ? 'variance-neg' : ($r['variances'] > 0 ? 'variance-pos' : '') ?>">
              <input class="ci variance-ci <?= $r['variances'] < 0 ? 'variance-neg' : ($r['variances'] > 0 ? 'variance-pos' : '') ?>" type="number" value="<?= nf($r['variances']) ?>" readonly tabindex="-1">
            </td>
            <td class="act-td sticky-r1">
              <button class="btn-sv" onclick="saveMain(this)">Save</button>
              <button class="btn-dl" onclick="deleteMain(this)">✕</button>
              <div class="row-st"></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <button class="btn-add" onclick="addMainRow()">+ Add Row</button>
    </div>
  </div>

  <!-- ══ RIGHT: BREAKDOWN TABLE ══ -->
  <div>
    <div class="inv-panel">
      <div class="inv-panel-head right">📊 Breakdown</div>

      <div class="bd-toolbar">
        <input type="text" id="bdSearch" class="bd-search" placeholder="Search item or group…" oninput="filterBd()">
        <a href="pub_express_month_end_inv.php?export_bd_csv=1&year=<?= $fYear ?>&month=<?= $fMonth ?>&day=<?= $fDay ?>" class="btn-bd-csv">⬇ Download Excel</a>
      </div>

      <div class="bd-totals">
        <div class="bd-totals-row"><span>SALES OP TOTAL:</span> <span id="bdTotalSalesOp">0.00</span></div>
        <div class="bd-totals-row"><span>SALES GRAB TOTAL:</span> <span id="bdTotalSalesGrab">0.00</span></div>
      </div>

      <div style="overflow-x:auto">
      <table class="inv-tbl" id="bdTbl">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:120px;text-align:left" class="right-th">ITEMS</th>
            <th rowspan="2" class="right-th">PER SERVING</th>
            <th colspan="2" class="right-th">STOCK</th>
            <th colspan="2" class="right-th">SALES</th>
            <th colspan="2" class="right-th">BD</th>
            <th rowspan="2" class="right-th">TOTAL SOLD OUT</th>
            <th rowspan="2" class="right-th">CONVERTED TO DOZEN</th>
            <th rowspan="2" class="right-th">TOTAL OUT</th>
            <th rowspan="2" class="right-th">ACTION</th>
          </tr>
          <tr>
            <th class="subhead right">OP</th>
            <th class="subhead right">GRAB</th>
            <th class="subhead right">SALES OP</th>
            <th class="subhead right">SALES GRAB</th>
            <th class="subhead right">OP</th>
            <th class="subhead right">GRAB</th>
          </tr>
        </thead>
        <tbody id="bdTbody">
          <?php foreach ($bdGrouped as $grpName => $grpRows): ?>
          <?php $grpTotalOut = array_sum(array_map(fn($gr) => (float)$gr['converted_dozen'], $grpRows)); ?>
          <tr class="grp-header-row" data-group="<?= htmlspecialchars($grpName) ?>">
            <td class="group-td" colspan="10">
              <input class="ci item-ci" style="font-weight:800;color:#1e3a5f;background:transparent" type="text" value="<?= htmlspecialchars($grpName) ?>" placeholder="Group name…">
            </td>
            <td class="grp-total-out" style="font-weight:800;color:#1e3a5f;text-align:right;padding-right:12px" data-group-total="<?= htmlspecialchars($grpName) ?>"><?= nf($grpTotalOut) ?></td>
            <td class="act-td">
              <button class="btn-sv" onclick="saveGroupName(this)">Save</button>
              <button class="btn-dl" onclick="deleteBdGroup(this)" title="Delete group">✕</button>
            </td>
          </tr>
          <?php foreach ($grpRows as $r): ?>
          <tr data-id="<?= $r['id'] ?>" data-group="<?= htmlspecialchars($grpName) ?>">
            <td class="item-td" style="padding-left:18px"><input class="ci item-ci" type="text" value="<?= htmlspecialchars($r['item_name']) ?>" placeholder="Item…" oninput="bdChanged(this)"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['per_serving']) ?>" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['op']) ?>"          placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['grab']) ?>"        placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['sales_op']) ?>"   readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['sales_grab']) ?>" readonly tabindex="-1"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['bd_op']) ?>"       placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
            <td><input class="ci" type="number" step="0.01" value="<?= nf($r['bd_grab']) ?>"     placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['total_sold_out']) ?>" readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['converted_dozen']) ?>" readonly tabindex="-1"></td>
            <td><input class="ci calc" type="number" value="<?= nf($r['converted_dozen']) ?>" readonly tabindex="-1" style="visibility:hidden"></td>
            <td class="act-td">
              <button class="btn-sv" onclick="saveBd(this)">Save</button>
              <button class="btn-dl" onclick="deleteBd(this)">✕</button>
              <div class="row-st"></div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr class="add-row-tr" data-group="<?= htmlspecialchars($grpName) ?>">
            <td colspan="12" style="padding:2px 4px">
              <button class="btn-add" onclick="addBdRow(this,'<?= htmlspecialchars(addslashes($grpName)) ?>')">+ Add Row</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <button class="btn-add-group" onclick="addBdGroup()">+ Add Group</button>
    </div>
  </div>

</div>

<!-- Duplicate-to-Other-Days Modal -->
<div class="modal-overlay" id="duplicateModal">
  <div class="modal-box wide">
    <div class="modal-header">📋 Duplicate <?= htmlspecialchars($displayDate) ?> to Other Days</div>
    <div class="modal-body">
      Copy all <strong><?= count($mainRows) ?> item(s)</strong> and <strong><?= count($bdRows) ?> breakdown row(s)</strong>
      from this date into any day(s) you pick below — including next month or any other month/year.

      <div class="dup-month-nav">
        <button type="button" class="dup-nav-btn" onclick="dupGoToMonth(-1)" title="Previous month">◀</button>
        <select id="dupBrowseMonth" class="dup-select" style="margin:0" onchange="dupBrowseSelectChange()"></select>
        <select id="dupBrowseYear" class="dup-select" style="margin:0;max-width:90px" onchange="dupBrowseSelectChange()"></select>
        <button type="button" class="dup-nav-btn" onclick="dupGoToMonth(1)" title="Next month">▶</button>
        <button type="button" class="dup-nav-btn dup-nav-today" onclick="dupBrowseTo(INV_YEAR, INV_MONTH)">This Month</button>
      </div>

      <div class="dup-legend" style="margin-top:10px">
        <span><span class="dot"></span> already has items</span>
        <span style="opacity:.6">crossed out = current date</span>
      </div>
      <div class="dup-quicklinks">
        <a onclick="dupSelectAll()">Select all remaining days (this view)</a>
        <a onclick="dupClearAll()">Clear all selections</a>
      </div>
      <div class="dup-day-grid" id="dupDayGrid"></div>
      <div id="dupSelectedNote" style="font-family:var(--font-m);font-size:.66rem;color:#6b7280;margin-top:2px"></div>

      <div class="dup-option-row">
        <input type="checkbox" id="dupCopyQty" style="margin-top:3px">
        <label for="dupCopyQty">
          Also copy quantities &amp; sales
          <span class="hint">Leave unchecked to copy just the item/group list (names, order, per-serving) with blank counts — best for a fresh daily tally.</span>
        </label>
      </div>

      <div class="dup-option-row" style="border-top:none;padding-top:0;flex-direction:column;align-items:stretch">
        <label for="dupMode">If a picked day already has data:</label>
        <select id="dupMode" class="dup-select">
          <option value="skip">Skip that day (safest)</option>
          <option value="append">Add on top (may create duplicates)</option>
          <option value="replace">Replace its items/groups first</option>
        </select>
      </div>

      <div id="dupSummary"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal('duplicateModal')">Cancel</button>
      <button class="modal-btn modal-btn-primary" id="dupConfirmBtn" onclick="confirmDuplicate()">Duplicate</button>
    </div>
  </div>
</div>

<script>
const INV_MONTH     = <?= $fMonth ?>;
const INV_YEAR      = <?= $fYear ?>;
const INV_DAY       = <?= $fDay ?>;
const INV_DATE      = <?= json_encode($selectedDate) ?>;
const DAYS_IN_MONTH  = <?= $daysInMonth ?>;
const DAY_COUNTS     = <?= json_encode($dupDayCounts, JSON_FORCE_OBJECT) ?>; // day-of-month => item+group-row count, for INV_YEAR/INV_MONTH
const MONTH_NAMES    = <?= json_encode($months) ?>;

function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// ── Duplicate to Other Days ──────────────────────────────────
// Lets a user build the item/group list once (Items + Breakdown)
// and reuse it across the month — or any other month/year —
// instead of retyping every item, group, and per-serving value
// each day.
let dupSelectedDates = new Set();
let dupBrowseYear    = INV_YEAR;
let dupBrowseMonth   = INV_MONTH;

function openDuplicateModal() {
  dupSelectedDates = new Set();
  dupBrowseYear  = INV_YEAR;
  dupBrowseMonth = INV_MONTH;
  document.getElementById('dupCopyQty').checked = false;
  document.getElementById('dupMode').value = 'skip';
  document.getElementById('dupSummary').style.display = 'none';
  populateDupMonthYearSelects();
  renderDupGrid(DAY_COUNTS, DAYS_IN_MONTH); // current month's data is already on the page — no fetch needed
  updateDupConfirmBtn();
  openModal('duplicateModal');
}

function populateDupMonthYearSelects() {
  const monthSel = document.getElementById('dupBrowseMonth');
  const yearSel  = document.getElementById('dupBrowseYear');
  if (!monthSel.options.length) {
    MONTH_NAMES.forEach((name, i) => monthSel.appendChild(new Option(name, i + 1)));
  }
  if (!yearSel.options.length) {
    for (let y = INV_YEAR - 3; y <= INV_YEAR + 2; y++) yearSel.appendChild(new Option(y, y));
  }
  monthSel.value = dupBrowseMonth;
  yearSel.value  = dupBrowseYear;
}

function dupGoToMonth(offset) {
  let m = dupBrowseMonth + offset, y = dupBrowseYear;
  if (m < 1)  { m = 12; y--; }
  if (m > 12) { m = 1;  y++; }
  dupBrowseTo(y, m);
}

function dupBrowseSelectChange() {
  const y = parseInt(document.getElementById('dupBrowseYear').value);
  const m = parseInt(document.getElementById('dupBrowseMonth').value);
  dupBrowseTo(y, m);
}

async function dupBrowseTo(year, month) {
  dupBrowseYear = year; dupBrowseMonth = month;
  document.getElementById('dupBrowseYear').value  = year;
  document.getElementById('dupBrowseMonth').value = month;

  // The currently-viewed page's month is already loaded — skip the round trip.
  if (year === INV_YEAR && month === INV_MONTH) {
    renderDupGrid(DAY_COUNTS, DAYS_IN_MONTH);
    return;
  }

  const grid = document.getElementById('dupDayGrid');
  grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:var(--subtext);font-family:var(--font-m);font-size:.72rem">Loading…</div>';

  const fd = new FormData();
  fd.append('ajax_action', 'month_counts');
  fd.append('year', year);
  fd.append('month', month);
  try {
    const res  = await fetch('pub_express_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      renderDupGrid(json.counts, json.days);
    } else {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:#b91c1c;font-family:var(--font-m);font-size:.72rem">Could not load that month.</div>';
    }
  } catch (e) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:#b91c1c;font-family:var(--font-m);font-size:.72rem">Network error.</div>';
  }
}

function renderDupGrid(counts, daysInMonth) {
  const grid = document.getElementById('dupDayGrid');
  const isSourceMonth = dupBrowseYear === INV_YEAR && dupBrowseMonth === INV_MONTH;
  let html = '';
  for (let d = 1; d <= daysInMonth; d++) {
    const iso      = `${dupBrowseYear}-${String(dupBrowseMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isSource = isSourceMonth && d === INV_DAY;
    const cnt      = counts[String(d)] || 0;
    const hasData  = cnt > 0 && !isSource;
    const checked  = dupSelectedDates.has(iso);
    const tip      = isSource ? 'This is the source date' : (hasData ? cnt + ' row(s) already here' : 'No items yet');
    html += `<div class="dup-day-chip${hasData ? ' has-data' : ''}${isSource ? ' is-source' : ''}${checked ? ' checked' : ''}"
                  data-iso="${iso}" title="${tip}"
                  onclick="${isSource ? '' : `toggleDupDate('${iso}')`}">${String(d).padStart(2,'0')}</div>`;
  }
  grid.innerHTML = html;
  updateDupConfirmBtn();
}

function toggleDupDate(iso) {
  const chip = document.querySelector(`.dup-day-chip[data-iso="${iso}"]`);
  if (dupSelectedDates.has(iso)) {
    dupSelectedDates.delete(iso);
    chip.classList.remove('checked');
  } else {
    dupSelectedDates.add(iso);
    chip.classList.add('checked');
  }
  updateDupConfirmBtn();
}

function dupSelectAll() {
  document.querySelectorAll('.dup-day-chip:not(.is-source)').forEach(chip => {
    dupSelectedDates.add(chip.dataset.iso);
    chip.classList.add('checked');
  });
  updateDupConfirmBtn();
}

function dupClearAll() {
  dupSelectedDates.clear();
  document.querySelectorAll('.dup-day-chip').forEach(chip => chip.classList.remove('checked'));
  updateDupConfirmBtn();
}

function updateDupConfirmBtn() {
  const btn  = document.getElementById('dupConfirmBtn');
  const note = document.getElementById('dupSelectedNote');
  const n    = dupSelectedDates.size;
  btn.textContent = n ? `Duplicate to ${n} Day${n > 1 ? 's' : ''}` : 'Duplicate';
  btn.disabled = n === 0;
  if (note) note.textContent = n ? `${n} date(s) selected in total (across all months browsed)` : '';
}

async function confirmDuplicate() {
  if (!dupSelectedDates.size) return;

  const targetDates = Array.from(dupSelectedDates).sort();

  const btn = document.getElementById('dupConfirmBtn');
  const origLabel = btn.textContent;
  btn.disabled = true; btn.textContent = 'Duplicating…';

  const fd = new FormData();
  fd.append('ajax_action', 'duplicate_day');
  fd.append('src_date', INV_DATE);
  fd.append('target_dates', JSON.stringify(targetDates));
  fd.append('copy_qty', document.getElementById('dupCopyQty').checked ? '1' : '');
  fd.append('mode', document.getElementById('dupMode').value);

  try {
    const res  = await fetch('pub_express_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      const s = json.summary;
      const box = document.getElementById('dupSummary');
      let msg = `✓ Copied ${json.mainCount} item(s) and ${json.bdCount} breakdown row(s) into ${s.copied.length} day(s).`;
      if (s.skipped.length) msg += ` Skipped ${s.skipped.length} day(s) that already had data.`;
      box.textContent = msg;
      box.style.display = 'block';
      btn.textContent = 'Done ✓';
      setTimeout(() => { closeModal('duplicateModal'); location.reload(); }, 1200);
    } else {
      alert('Error: ' + (json.msg || 'Could not duplicate.'));
      btn.disabled = false; btn.textContent = origLabel;
    }
  } catch (e) {
    alert('Network error while duplicating.');
    btn.disabled = false; btn.textContent = origLabel;
  }
}

const gv = el => parseFloat(el?.value) || 0;
const fmt = n => n === 0 ? '' : Number(n).toFixed(2).replace(/\.?0+$/,'');


// ── SALES SUMMARY (reads already-computed values from the DOM;
//    does not touch or duplicate calcMain/calcBd's own math) ──
function refreshSalesSummaryFromDOM() {
  // ── Items table: sum op_sale (ci[9]) and grab_sales (ci[10]) ──
  let itemsWalkin = 0, itemsGrab = 0;
  document.querySelectorAll('#mainTbody tr[data-id]').forEach(tr => {
    const ci = tr.querySelectorAll('input.ci');
    if (ci.length >= 11) {
      itemsWalkin += parseFloat(ci[9]?.value)  || 0;
      itemsGrab   += parseFloat(ci[10]?.value) || 0;
    }
  });

  // ── Breakdown table: sum sales_op (ci[4]) and sales_grab (ci[5]) ──
  let bdOp = 0, bdGrab = 0;
  document.querySelectorAll('#bdTbody tr:not(.grp-header-row):not(.add-row-tr)[data-group]').forEach(tr => {
    const ci = tr.querySelectorAll('input.ci');
    if (ci[4]) bdOp   += parseFloat(ci[4]?.value) || 0;
    if (ci[5]) bdGrab += parseFloat(ci[5]?.value) || 0;
  });

  // ── WALK IN SALES = Items OP Sale + Breakdown Sales OP ──
  const walkinTotal = itemsWalkin + bdOp;
  // ── GRAB SALES    = Items Grab Sales + Breakdown Sales Grab ──
  const grabTotal   = itemsGrab + bdGrab;

  const fmtN = n => n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

  // Update Walk In sub-labels
  const subWalkinItems = document.getElementById('ss-walkin-items-sub');
  const subWalkinBd    = document.getElementById('ss-walkin-bd-sub');
  if (subWalkinItems) subWalkinItems.textContent = fmtN(itemsWalkin);
  if (subWalkinBd)    subWalkinBd.textContent    = fmtN(bdOp);

  // Update Grab sub-labels
  const subGrabItems = document.getElementById('ss-grab-items-sub');
  const subGrabBd    = document.getElementById('ss-grab-bd-sub');
  if (subGrabItems) subGrabItems.textContent = fmtN(itemsGrab);
  if (subGrabBd)    subGrabBd.textContent    = fmtN(bdGrab);

  // Update main values
  const walkinEl = document.getElementById('ss-walkin');
  const grabEl   = document.getElementById('ss-grab');
  if (walkinEl) walkinEl.textContent = fmtN(walkinTotal);
  if (grabEl)   grabEl.textContent   = fmtN(grabTotal);

  recalcSummary();
}

function recalcSummary() {
  const grab   = parseFloat(document.getElementById('ss-grab')?.textContent.replace(/,/g,''))   || 0;
  const walkin = parseFloat(document.getElementById('ss-walkin')?.textContent.replace(/,/g,'')) || 0;
  const disc   = parseFloat(document.getElementById('ss-discount')?.value) || 0;
  const gross  = walkin + grab - disc;
  const grossEl = document.getElementById('ss-gross');
  if (grossEl) grossEl.textContent = gross.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}

async function saveSummary() {
  const disc = parseFloat(document.getElementById('ss-discount')?.value) || 0;
  const fd = new FormData();
  fd.append('ajax_action','save_summary');
  fd.append('discount', disc);
  try {
    const r = await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) showToast('✓ Summary saved','success');
    else showToast('❌ '+d.msg,'error');
  } catch(e){ showToast('❌ Network error','error'); }
}

function showToast(msg,type) {
  const t=document.createElement('div');
  t.className='flash flash-'+(type||'success')+' toast';
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),3000);
}

// Live-updates Sales Summary's Walk In / Grab whenever any Items cell
// changes, without touching calcMain/mainChanged themselves.
document.getElementById('mainTbody')?.addEventListener('input', refreshSalesSummaryFromDOM);

// Filters the Items table by item name — same pattern as filterBd().
function filterMain() {
  const q = document.getElementById('mainSearch').value.trim().toLowerCase();
  document.querySelectorAll('#mainTbody tr[data-id]').forEach(tr => {
    const itemName = (tr.querySelector('.item-ci')?.value || '').toLowerCase();
    tr.classList.toggle('bd-hide', q !== '' && !itemName.includes(q));
  });
}

// ── MAIN TABLE ────────────────────────────────────────────
function calcMain(inp) {
  const tr = inp.closest('tr');
  const ci = tr.querySelectorAll('input.ci');
  // ci[1]=op ci[2]=grab ci[3]=bb ci[4]=del ci[5]=qty_sold_op ci[6]=qty_sold_grab
  // ci[7]=total_out(calc) ci[8]=ending(calc) ci[9]=op_sale(calc) ci[10]=grab_sales(calc)
  // ci[11]=actual_ending ci[12]=variances(calc)
  const totalOut   = gv(ci[5]) + gv(ci[6]);
  const ending     = gv(ci[3]) + gv(ci[4]) - totalOut;          // ENDING = BB + DEL - TOTAL OUT
  const opSale     = gv(ci[5]) * gv(ci[1]);                       // OP SALE = QTY SOLD (OP) × OP
  const grabSales  = gv(ci[2]) * gv(ci[6]);                      // GRAB SALES = GRAB × QTY SOLD (GRAB)
  const variances  = gv(ci[11]) - ending;                        // VARIANCES = ACTUAL ENDING - ENDING

  ci[7].value  = totalOut.toFixed(2);
  ci[8].value  = ending.toFixed(2);
  ci[9].value  = opSale.toFixed(2);
  ci[10].value = grabSales.toFixed(2);
  ci[12].value = variances.toFixed(2);

  const vtd = ci[12].parentElement;
  const varianceClass = variances < 0 ? 'variance-neg' : (variances > 0 ? 'variance-pos' : '');
  vtd.className = 'sticky-r2 ' + varianceClass;
  ci[12].className = 'ci variance-ci ' + varianceClass;
}

function mainChanged(inp) { /* just for non-calc fields */ }

function addMainRow() {
  const tbody = document.getElementById('mainTbody');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td class="item-td"><input class="ci item-ci" type="text" placeholder="Item name…" oninput="mainChanged(this)"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcMain(this)" onfocus="this.select()"></td>
    <td class="sticky-r2"><input class="ci variance-ci" type="number" value="" readonly tabindex="-1"></td>
    <td class="act-td sticky-r1">
      <button class="btn-sv" onclick="saveMain(this)">Save</button>
      <button class="btn-dl" onclick="deleteMain(this)">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('.item-ci').focus();
}

async function saveMain(btn) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const ci = tr.querySelectorAll('input.ci');
  const fd = new FormData();
  fd.append('ajax_action','save_main');
  fd.append('id', tr.dataset.id||0);
  fd.append('inv_date', INV_DATE);
  fd.append('item_name',    ci[0].value.trim());
  fd.append('sort_order',   Array.from(tr.parentElement.children).indexOf(tr));
  fd.append('op',           gv(ci[1]));
  fd.append('grab',         gv(ci[2]));
  fd.append('bb',           gv(ci[3]));
  fd.append('del',          gv(ci[4]));
  fd.append('qty_sold_op',  gv(ci[5]));
  fd.append('qty_sold_grab',gv(ci[6]));
  fd.append('op_sale',      gv(ci[9]));
  fd.append('grab_sales',   gv(ci[10]));
  fd.append('actual_ending',gv(ci[11]));
  btn.textContent='…'; btn.disabled=true;
  try {
    const r = await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
      tr.dataset.id = d.id;
      st.textContent='✓'; st.style.display='block';
      setTimeout(()=>st.style.display='none',2000);
    } else { alert('Error: '+d.msg); }
  } catch(e){ alert('Network error'); }
  btn.textContent='Save'; btn.disabled=false;
}

async function deleteMain(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  if (id>0 && !confirm('Delete this row?')) return;
  if (id>0) {
    const fd=new FormData(); fd.append('ajax_action','delete_main'); fd.append('id',id);
    await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
  }
  tr.remove();
}

// ── BREAKDOWN TABLE ───────────────────────────────────────
function calcBd(inp) {
  const tr = inp.closest('tr');
  const ci = tr.querySelectorAll('input.ci');
  // ci[0]=item ci[1]=per_serving ci[2]=op ci[3]=grab
  // ci[4]=sales_op(calc = op*bd_op) ci[5]=sales_grab(calc = grab*bd_grab)
  // ci[6]=bd_op ci[7]=bd_grab ci[8]=total_sold(calc) ci[9]=converted(calc = total_sold * per_serving) ci[10]=total_out(calc, mirrors converted, hidden per row)
  const ps       = gv(ci[1]);
  const op       = gv(ci[2]);
  const grab     = gv(ci[3]);
  const bdOp     = gv(ci[6]);
  const bdGrab   = gv(ci[7]);

  const salesOp    = op * bdOp;
  const salesGrab  = grab * bdGrab;
  const totalSold  = bdOp + bdGrab;
  const converted  = totalSold * ps;

  ci[4].value  = salesOp.toFixed(2);
  ci[5].value  = salesGrab.toFixed(2);
  ci[8].value  = totalSold.toFixed(2);
  ci[9].value  = converted.toFixed(2);
  ci[10].value = converted.toFixed(2);
  updateGroupTotalOut(tr.dataset.group);
  updateBdGrandTotals();
}

// Sums every row's (hidden) Total Out value within a group and writes it
// into that group's single header cell — one Total Out per group, not per row.
function updateGroupTotalOut(grpName) {
  if (!grpName) return;
  let sum = 0;
  document.querySelectorAll(`#bdTbody tr[data-group="${CSS.escape(grpName)}"]:not(.grp-header-row):not(.add-row-tr)`).forEach(tr => {
    const ci = tr.querySelectorAll('input.ci');
    if (ci[10]) sum += gv(ci[10]);
  });
  const cell = document.querySelector(`.grp-total-out[data-group-total="${CSS.escape(grpName)}"]`);
  if (cell) cell.textContent = sum.toFixed(2);
}

// Grand total of Sales OP / Sales Grab across every group/item — shown
// above the table so you don't have to add up each group by hand.
function updateBdGrandTotals() {
  let totalOp = 0, totalGrab = 0;
  document.querySelectorAll('#bdTbody tr:not(.grp-header-row):not(.add-row-tr)').forEach(tr => {
    const ci = tr.querySelectorAll('input.ci');
    if (ci[4]) totalOp   += gv(ci[4]);
    if (ci[5]) totalGrab += gv(ci[5]);
  });
  const opEl   = document.getElementById('bdTotalSalesOp');
  const grabEl = document.getElementById('bdTotalSalesGrab');
  if (opEl)   opEl.textContent   = totalOp.toFixed(2);
  if (grabEl) grabEl.textContent = totalGrab.toFixed(2);
}

// Filters the Breakdown table by item name or group name. A group stays
// visible if its own name matches, or if any item inside it matches.
function filterBd() {
  const q = document.getElementById('bdSearch').value.trim().toLowerCase();
  const groups = {};
  document.querySelectorAll('#bdTbody tr[data-group]').forEach(tr => {
    const g = tr.dataset.group;
    if (!groups[g]) groups[g] = { header: null, addRow: null, items: [] };
    if (tr.classList.contains('grp-header-row')) groups[g].header = tr;
    else if (tr.classList.contains('add-row-tr')) groups[g].addRow = tr;
    else groups[g].items.push(tr);
  });

  Object.entries(groups).forEach(([g, info]) => {
    const groupMatches = g.toLowerCase().includes(q);
    let anyItemMatches = false;
    info.items.forEach(tr => {
      const itemName = (tr.querySelector('.item-ci')?.value || '').toLowerCase();
      const match = !q || groupMatches || itemName.includes(q);
      tr.classList.toggle('bd-hide', !match);
      if (match) anyItemMatches = true;
    });
    const showGroup = !q || groupMatches || anyItemMatches;
    if (info.header) info.header.classList.toggle('bd-hide', !showGroup);
    if (info.addRow) info.addRow.classList.toggle('bd-hide', !showGroup);
  });
}

function bdChanged(inp) {}

function addBdRow(btn, grpName) {
  const addTr = btn.closest('tr');
  const tr = document.createElement('tr');
  tr.dataset.id    = '0';
  tr.dataset.group = grpName;
  tr.innerHTML = `
    <td class="item-td" style="padding-left:18px"><input class="ci item-ci" type="text" placeholder="Item…" oninput="bdChanged(this)"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" placeholder="0" oninput="calcBd(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="number" value="" readonly tabindex="-1" style="visibility:hidden"></td>
    <td class="act-td">
      <button class="btn-sv" onclick="saveBd(this)">Save</button>
      <button class="btn-dl" onclick="deleteBd(this)">✕</button>
      <div class="row-st"></div>
    </td>`;
  addTr.parentElement.insertBefore(tr, addTr);
  tr.querySelector('.item-ci').focus();
}

let newGroupCounter = 0;

function addBdGroup() {
  const tbody = document.getElementById('bdTbody');
  const grpName = 'NEW GROUP' + (++newGroupCounter > 1 ? ' ' + newGroupCounter : '');
  const hdr = document.createElement('tr');
  hdr.className = 'grp-header-row';
  hdr.dataset.group = grpName;
  hdr.innerHTML = `<td class="group-td" colspan="10"><input class="ci item-ci" style="font-weight:800;color:#1e3a5f;background:transparent" type="text" value="${grpName}" placeholder="Group name…"></td><td class="grp-total-out" style="font-weight:800;color:#1e3a5f;text-align:right;padding-right:12px" data-group-total="${grpName}">0.00</td><td class="act-td"><button class="btn-sv" onclick="saveGroupName(this)">Save</button><button class="btn-dl" onclick="deleteBdGroup(this)" title="Delete group">✕</button></td>`;
  const addBtn = document.createElement('tr');
  addBtn.className = 'add-row-tr';
  addBtn.dataset.group = grpName;
  addBtn.innerHTML = `<td colspan="12" style="padding:2px 4px"><button class="btn-add" onclick="addBdRow(this,'${grpName}')">+ Add Row</button></td>`;
  tbody.appendChild(hdr);
  tbody.appendChild(addBtn);
  hdr.querySelector('input').focus();
}

async function saveBd(btn) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const ci = tr.querySelectorAll('input.ci');
  const fd = new FormData();
  fd.append('ajax_action','save_bd');
  fd.append('id',tr.dataset.id||0);
  fd.append('inv_date',INV_DATE);
  fd.append('group_name', tr.dataset.group||'');
  fd.append('item_name',  ci[0].value.trim());
  fd.append('sort_order', Array.from(tr.parentElement.children).indexOf(tr));
  fd.append('per_serving',gv(ci[1]));
  fd.append('op',         gv(ci[2]));
  fd.append('grab',       gv(ci[3]));
  fd.append('sales_op',   gv(ci[4]));
  fd.append('sales_grab', gv(ci[5]));
  fd.append('bd_op',      gv(ci[6]));
  fd.append('bd_grab',    gv(ci[7]));
  fd.append('total_out',  gv(ci[10]));
  btn.textContent='…'; btn.disabled=true;
  try {
    const r = await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
      tr.dataset.id=d.id;
      st.textContent='✓'; st.style.display='block';
      setTimeout(()=>st.style.display='none',2000);
    } else { alert('Error: '+d.msg); }
  } catch(e){ alert('Network error'); }
  btn.textContent='Save'; btn.disabled=false;
}

async function deleteBd(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  const grpName = tr.dataset.group;
  if (id>0 && !confirm('Delete this row?')) return;
  if (id>0) {
    const fd=new FormData(); fd.append('ajax_action','delete_bd'); fd.append('id',id);
    await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
  }
  tr.remove();
  updateGroupTotalOut(grpName);
  updateBdGrandTotals();
}

async function deleteBdGroup(btn) {
  const hdr = btn.closest('tr');
  const grpName = hdr.dataset.group;
  if (!confirm(`Delete the group "${grpName}" and all its rows?`)) return;
  const fd = new FormData();
  fd.append('ajax_action','delete_group');
  fd.append('group_name', grpName);
  try {
    const r = await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
    const d = await r.json();
    if (!d.ok) { alert('Error: '+(d.msg||'delete failed')); return; }
  } catch(e) { alert('Network error'); return; }
  document.querySelectorAll(`#bdTbody tr[data-group="${CSS.escape(grpName)}"]`).forEach(tr => tr.remove());
  updateBdGrandTotals();
}

// Renames a group. If it already has saved rows, bulk-renames them in the
// DB too; either way, every DOM element tied to the old name (item rows,
// the "+ Add Row" button, the group-total cell) gets re-tagged with the
// new name so future saves/adds/deletes keep pointing at the right group.
async function saveGroupName(btn) {
  const hdr     = btn.closest('tr');
  const oldName = hdr.dataset.group;
  const input   = hdr.querySelector('input.item-ci');
  const newName = input.value.trim();

  if (!newName) { alert('Group name cannot be empty'); input.value = oldName; return; }
  if (newName === oldName) return;

  const hasSavedRows = Array.from(document.querySelectorAll(`#bdTbody tr[data-group="${CSS.escape(oldName)}"]:not(.grp-header-row):not(.add-row-tr)`))
    .some(tr => parseInt(tr.dataset.id) > 0);

  if (hasSavedRows) {
    btn.textContent = '…'; btn.disabled = true;
    const fd = new FormData();
    fd.append('ajax_action','rename_group');
    fd.append('old_name', oldName);
    fd.append('new_name', newName);
    try {
      const r = await fetch('pub_express_month_end_inv.php',{method:'POST',body:fd});
      const d = await r.json();
      if (!d.ok) { alert('Error: '+(d.msg||'rename failed')); btn.textContent='Save'; btn.disabled=false; return; }
    } catch(e) { alert('Network error'); btn.textContent='Save'; btn.disabled=false; return; }
    btn.textContent = 'Save'; btn.disabled = false;
  }

  hdr.dataset.group = newName;
  const totalCell = document.querySelector(`.grp-total-out[data-group-total="${CSS.escape(oldName)}"]`);
  if (totalCell) totalCell.dataset.groupTotal = newName;
  document.querySelectorAll(`#bdTbody tr[data-group="${CSS.escape(oldName)}"]`).forEach(tr => {
    tr.dataset.group = newName;
    if (tr.classList.contains('add-row-tr')) {
      const addBtn = tr.querySelector('.btn-add');
      if (addBtn) addBtn.setAttribute('onclick', `addBdRow(this,'${newName.replace(/'/g,"\\'")}')`);
    }
  });
}

// Mirrors the Breakdown panel's own Sales OP/Grab totals (computed by its
// existing updateBdGrandTotals()) into the Sales Summary card above —
// also recomputes the combined GRAB SALES (Items + Breakdown).
document.getElementById('bdTbody')?.addEventListener('input', () => {
  const opVal   = document.getElementById('bdTotalSalesOp')?.textContent   || '0.00';
  const ssOpEl  = document.getElementById('ss-bd-sales-op');
  if (ssOpEl) ssOpEl.textContent = opVal;
  // Recompute the full grab total (items + breakdown) via the shared helper
  refreshSalesSummaryFromDOM();
});

// ── Recalculate every row on page load ──────────────────────
// Stored values reflect whatever formula was live the moment a
// row was last saved. If the formulas change later, old rows
// would otherwise keep showing outdated numbers until someone
// manually re-saves them. Recomputing here means the page
// always shows numbers from the CURRENT formulas, immediately —
// no stale data, no need to click Save just to refresh a value.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#mainTbody tr').forEach(tr => {
    const first = tr.querySelector('input.ci');
    if (first) calcMain(first);
  });
  document.querySelectorAll('#bdTbody tr[data-id]').forEach(tr => {
    const first = tr.querySelector('input.ci');
    if (first) calcBd(first);
  });
});

// Separate, additive listener: initializes the Sales Summary card
// (and its mirrored Breakdown totals) to reflect the values the
// blocks above just recalculated — including the combined GRAB SALES.
document.addEventListener('DOMContentLoaded', () => {
  refreshSalesSummaryFromDOM();
  const opVal  = document.getElementById('bdTotalSalesOp')?.textContent || '0.00';
  const ssOpEl = document.getElementById('ss-bd-sales-op');
  if (ssOpEl) ssOpEl.textContent = opVal;
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>