<?php
// ============================================================
//  commissary_riders_salary.php — Commissary: Riders Salary Monitoring
//  One row per store, one column per month. Each store row now
//  lists its named riders (with a monthly rate), each month cell
//  carries a PAID / UNPAID / PARTIAL status *and* an editable ₱
//  amount, row/column totals are shown, and a payroll summary
//  section breaks salary down per rider and per month.
//  Stores, months, and riders can all be added / edited / removed.
//  Commissary branch + management only (same access rule as before).
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Snapshot whether the stores table already existed *before* this request —
// used below to tell "brand-new install" apart from "existing install whose
// tables are just currently empty" so a delete-all doesn't get treated as
// a fresh install and re-seeded.
$storesTableExistedBefore = (bool)$pdo->query("SHOW TABLES LIKE 'commissary_riders_salary_stores'")->fetch();

// ── Tables ───────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_stores` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `store_key`   varchar(30) NOT NULL,
    `label`       varchar(100) NOT NULL,
    `color`       varchar(20) NOT NULL DEFAULT 'green',
    `sort_order`  int(11) NOT NULL DEFAULT 0,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_store_key` (`store_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_months` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `month_key`   varchar(10) NOT NULL,
    `label`       varchar(30) NOT NULL,
    `sort_order`  int(11) NOT NULL DEFAULT 0,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_month_key` (`month_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_status` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `store_key`   varchar(30) NOT NULL,
    `month_key`   varchar(10) NOT NULL,
    `status`      varchar(20) NOT NULL DEFAULT '',
    `amount`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `updated_by`  varchar(100) DEFAULT NULL,
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_store_month` (`store_key`,`month_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Riders table — named riders with a monthly rate. `store_key` here is only
// the rider's original/creation branch (kept for backward compatibility);
// which branches a rider actually works at now lives in the join table below,
// so one rider can be linked to 2+ branches at once.
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_riders` (
    `id`             int(11) NOT NULL AUTO_INCREMENT,
    `store_key`      varchar(30) NOT NULL,
    `rider_name`     varchar(100) NOT NULL,
    `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`     int(11) NOT NULL DEFAULT 0,
    `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_store_key` (`store_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Rider ↔ Store join table — lets a single rider be assigned to 2 or more
// branches instead of just the one branch they were originally added under.
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_rider_stores` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `rider_id`    int(11) NOT NULL,
    `store_key`   varchar(30) NOT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_rider_store` (`rider_id`,`store_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Upgrade path: add `amount` to an older status table that predates this column.
$hasAmountCol = $pdo->query("SHOW COLUMNS FROM `commissary_riders_salary_status` LIKE 'amount'")->fetch();
if (!$hasAmountCol) {
    $pdo->exec("ALTER TABLE `commissary_riders_salary_status` ADD COLUMN `amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `status`");
}

// Tiny key/value table used to remember "we already ran the placeholder
// seed" — so clearing out the demo stores/riders/etc. later doesn't bring
// them back on the next page load (an empty table is no longer read as
// "never seeded", only the flag below is).
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_riders_salary_meta` (
    `meta_key`    varchar(60) NOT NULL,
    `meta_value`  varchar(60) NOT NULL,
    PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
function rsMetaGet(PDO $pdo, string $key): ?string {
    $stmt = $pdo->prepare("SELECT meta_value FROM commissary_riders_salary_meta WHERE meta_key=?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : $v;
}
function rsMetaSet(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO commissary_riders_salary_meta (meta_key, meta_value) VALUES (?,?)
        ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute([$key, $value]);
}

// ── Seed once — matches the source sheet: Stella, Dois, Pub Express,
//    Hero for April–July 2026, all PAID except Dois & Hero for July.
//    Gated by the 'seed_completed' flag (not by "table is empty"), so once
//    this has run it never runs again — even if every row gets deleted
//    later. If the stores table already existed before this request (i.e.
//    this app was already running), we treat it as already-seeded and
//    never backfill placeholder data, no matter how empty things are now. ──
if (rsMetaGet($pdo, 'seed_completed') !== '1') {
    if (!$storesTableExistedBefore) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_stores")->fetchColumn() === 0) {
            $DEFAULT_STORES = [
                ['STELLA',      'Stella',      'green',  0],
                ['DOIS',        'Dois',        'peach',  1],
                ['PUB_EXPRESS', 'Pub Express', 'red',    2],
                ['HERO',        'Hero',        'orange', 3],
            ];
            $sIns = $pdo->prepare("INSERT INTO commissary_riders_salary_stores (store_key, label, color, sort_order) VALUES (?,?,?,?)");
            foreach ($DEFAULT_STORES as $s) { $sIns->execute($s); }
        }
        if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_months")->fetchColumn() === 0) {
            $DEFAULT_MONTHS = [
                ['2026-04', 'April 2026', 0],
                ['2026-05', 'May 2026',   1],
                ['2026-06', 'June 2026',  2],
                ['2026-07', 'July 2026',  3],
            ];
            $mIns = $pdo->prepare("INSERT INTO commissary_riders_salary_months (month_key, label, sort_order) VALUES (?,?,?)");
            foreach ($DEFAULT_MONTHS as $m) { $mIns->execute($m); }
        }
        if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_status")->fetchColumn() === 0) {
            // amount left at 0 here — filled in below once placeholder riders exist,
            // so PAID months default to that store's rider payroll total.
            $DEFAULT_STATUS = [
                ['STELLA','2026-04','PAID'], ['STELLA','2026-05','PAID'], ['STELLA','2026-06','PAID'], ['STELLA','2026-07','PAID'],
                ['DOIS','2026-04','PAID'],   ['DOIS','2026-05','PAID'],   ['DOIS','2026-06','PAID'],   ['DOIS','2026-07',''],
                ['PUB_EXPRESS','2026-04','PAID'], ['PUB_EXPRESS','2026-05','PAID'], ['PUB_EXPRESS','2026-06','PAID'], ['PUB_EXPRESS','2026-07','PAID'],
                ['HERO','2026-04','PAID'],   ['HERO','2026-05','PAID'],   ['HERO','2026-06','PAID'],   ['HERO','2026-07',''],
            ];
            $stIns = $pdo->prepare("INSERT INTO commissary_riders_salary_status (store_key, month_key, status, updated_by) VALUES (?,?,?,?)");
            foreach ($DEFAULT_STATUS as $s) { $stIns->execute([$s[0], $s[1], $s[2], 'Historical Import']); }
        }

        // Placeholder riders — replace names/rates with the real roster per store.
        if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_riders")->fetchColumn() === 0) {
            $DEFAULT_RIDERS = [
                ['STELLA',      'Rider 1', 8000.00, 0], ['STELLA',      'Rider 2', 8000.00, 1],
                ['DOIS',        'Rider 1', 7500.00, 0],
                ['PUB_EXPRESS', 'Rider 1', 8000.00, 0], ['PUB_EXPRESS', 'Rider 2', 8000.00, 1],
                ['HERO',        'Rider 1', 7500.00, 0],
            ];
            $rIns = $pdo->prepare("INSERT INTO commissary_riders_salary_riders (store_key, rider_name, monthly_salary, sort_order) VALUES (?,?,?,?)");
            foreach ($DEFAULT_RIDERS as $r) { $rIns->execute($r); }

            // Backfill amount=0 PAID cells with that store's rider payroll total, so the
            // grid isn't full of zeros on first load.
            $payroll = [];
            foreach ($DEFAULT_RIDERS as $r) { $payroll[$r[0]] = ($payroll[$r[0]] ?? 0) + $r[2]; }
            $bf = $pdo->prepare("UPDATE commissary_riders_salary_status SET amount=? WHERE store_key=? AND status='PAID' AND amount=0");
            foreach ($payroll as $sk => $tot) { $bf->execute([$tot, $sk]); }
        }
    }

    rsMetaSet($pdo, 'seed_completed', '1');
}

// One-time migration: link every existing rider to their original store in
// the new join table (covers both a fresh install's seed riders above and
// any older install upgrading from the single-branch-per-rider version).
if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_rider_stores")->fetchColumn() === 0
    && (int)$pdo->query("SELECT COUNT(*) FROM commissary_riders_salary_riders")->fetchColumn() > 0) {
    $rsIns = $pdo->prepare("INSERT IGNORE INTO commissary_riders_salary_rider_stores (rider_id, store_key) VALUES (?,?)");
    foreach ($pdo->query("SELECT id, store_key FROM commissary_riders_salary_riders")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rsIns->execute([$r['id'], $r['store_key']]);
    }
}

function loadRiderStores(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT * FROM commissary_riders_salary_stores ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['store_key']] = $r;
    }
    return $out;
}
function loadRiderMonths(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT * FROM commissary_riders_salary_months ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['month_key']] = $r;
    }
    return $out;
}
// All riders, keyed by rider id (a rider is one identity, no longer tied to
// a single store).
function loadAllRidersMap(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT * FROM commissary_riders_salary_riders ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['id']] = $r;
    }
    return $out;
}
// Rider ↔ store links, both directions: riderId => [store_key,...] and
// store_key => [riderId,...]. This is what lets a rider hold 2+ branches.
function loadRiderStoreLinks(PDO $pdo): array {
    $byRider = []; $byStore = [];
    foreach ($pdo->query("SELECT rider_id, store_key FROM commissary_riders_salary_rider_stores ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rid = (int)$r['rider_id'];
        $byRider[$rid][] = $r['store_key'];
        $byStore[$r['store_key']][] = $rid;
    }
    return [$byRider, $byStore];
}
function money($v): string { return '₱' . number_format((float)$v, 2); }
function statusUnit(string $status): float {
    if ($status === 'PAID') return 1.0;
    if ($status === 'PARTIAL') return 0.5;
    return 0.0;
}

// ── AJAX: set / update a single cell's status ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_set_status'])) {
    header('Content-Type: application/json');
    $storeKey = $_POST['store_key'] ?? '';
    $monthKey = $_POST['month_key'] ?? '';
    $status   = trim($_POST['status'] ?? '');
    if ($storeKey === '' || $monthKey === '') { echo json_encode(['ok'=>false,'msg'=>'Missing store/month.']); exit; }
    try {
        $pdo->prepare("INSERT INTO commissary_riders_salary_status (store_key, month_key, status, updated_by)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP")
            ->execute([$storeKey, $monthKey, $status, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: set / update a single cell's ₱ amount ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_set_amount'])) {
    header('Content-Type: application/json');
    $storeKey = $_POST['store_key'] ?? '';
    $monthKey = $_POST['month_key'] ?? '';
    $amount   = (float)($_POST['amount'] ?? 0);
    if ($storeKey === '' || $monthKey === '') { echo json_encode(['ok'=>false,'msg'=>'Missing store/month.']); exit; }
    try {
        $pdo->prepare("INSERT INTO commissary_riders_salary_status (store_key, month_key, amount, updated_by)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE amount=VALUES(amount), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP")
            ->execute([$storeKey, $monthKey, $amount, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: add a new store row ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_store'])) {
    header('Content-Type: application/json');
    $label = trim($_POST['label'] ?? '');
    $color = trim($_POST['color'] ?? 'green');
    if ($label === '') { echo json_encode(['ok'=>false,'msg'=>'Store name is required.']); exit; }
    try {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
        $base = trim($base, '_'); if ($base === '') $base = 'STORE';
        $key = $base; $n = 1;
        $chk = $pdo->prepare("SELECT COUNT(*) FROM commissary_riders_salary_stores WHERE store_key=?");
        while (true) { $chk->execute([$key]); if ((int)$chk->fetchColumn() === 0) break; $key = $base.'_'.(++$n); }
        $nextOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1)+1 FROM commissary_riders_salary_stores")->fetchColumn();
        $pdo->prepare("INSERT INTO commissary_riders_salary_stores (store_key, label, color, sort_order) VALUES (?,?,?,?)")
            ->execute([$key, $label, $color, $nextOrd]);
        echo json_encode(['ok'=>true, 'store_key'=>$key]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: delete a store row (and its statuses + rider links; riders left
//    with no remaining branch are removed, others just lose that one link) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_store'])) {
    header('Content-Type: application/json');
    $storeKey = $_POST['store_key'] ?? '';
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM commissary_riders_salary_status WHERE store_key=?")->execute([$storeKey]);
        $pdo->prepare("DELETE FROM commissary_riders_salary_rider_stores WHERE store_key=?")->execute([$storeKey]);
        $pdo->exec("DELETE FROM commissary_riders_salary_riders WHERE id NOT IN (SELECT DISTINCT rider_id FROM commissary_riders_salary_rider_stores)");
        $pdo->prepare("DELETE FROM commissary_riders_salary_stores WHERE store_key=?")->execute([$storeKey]);
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: add a new month column ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_month'])) {
    header('Content-Type: application/json');
    $label = trim($_POST['label'] ?? '');
    $monthKey = trim($_POST['month_key'] ?? '');
    if ($label === '' || $monthKey === '') { echo json_encode(['ok'=>false,'msg'=>'Month is required.']); exit; }
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM commissary_riders_salary_months WHERE month_key=?");
        $chk->execute([$monthKey]);
        if ((int)$chk->fetchColumn() > 0) { echo json_encode(['ok'=>false,'msg'=>'That month already exists.']); exit; }
        $nextOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1)+1 FROM commissary_riders_salary_months")->fetchColumn();
        $pdo->prepare("INSERT INTO commissary_riders_salary_months (month_key, label, sort_order) VALUES (?,?,?)")
            ->execute([$monthKey, $label, $nextOrd]);
        echo json_encode(['ok'=>true, 'month_key'=>$monthKey]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: delete a month column ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_month'])) {
    header('Content-Type: application/json');
    $monthKey = $_POST['month_key'] ?? '';
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM commissary_riders_salary_status WHERE month_key=?")->execute([$monthKey]);
        $pdo->prepare("DELETE FROM commissary_riders_salary_months WHERE month_key=?")->execute([$monthKey]);
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: add a rider, linked to one or more branches at once ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_rider'])) {
    header('Content-Type: application/json');
    $name      = trim($_POST['rider_name'] ?? '');
    $salary    = (float)($_POST['monthly_salary'] ?? 0);
    $storeKeys = isset($_POST['store_keys']) && is_array($_POST['store_keys'])
        ? array_values(array_unique(array_filter($_POST['store_keys'], fn($v) => $v !== ''))) : [];
    if ($name === '') { echo json_encode(['ok'=>false,'msg'=>'Rider name is required.']); exit; }
    if (empty($storeKeys)) { echo json_encode(['ok'=>false,'msg'=>'Select at least one branch for this rider.']); exit; }
    try {
        $pdo->beginTransaction();
        $ordStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),-1)+1 FROM commissary_riders_salary_riders WHERE store_key=?");
        $ordStmt->execute([$storeKeys[0]]);
        $nextOrd = (int)$ordStmt->fetchColumn();
        $pdo->prepare("INSERT INTO commissary_riders_salary_riders (store_key, rider_name, monthly_salary, sort_order) VALUES (?,?,?,?)")
            ->execute([$storeKeys[0], $name, $salary, $nextOrd]);
        $riderId = (int)$pdo->lastInsertId();
        $linkStmt = $pdo->prepare("INSERT IGNORE INTO commissary_riders_salary_rider_stores (rider_id, store_key) VALUES (?,?)");
        foreach ($storeKeys as $sk) { $linkStmt->execute([$riderId, $sk]); }
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: edit a rider's name / rate / full set of assigned branches ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_rider'])) {
    header('Content-Type: application/json');
    $id        = (int)($_POST['rider_id'] ?? 0);
    $name      = trim($_POST['rider_name'] ?? '');
    $salary    = (float)($_POST['monthly_salary'] ?? 0);
    $storeKeys = isset($_POST['store_keys']) && is_array($_POST['store_keys'])
        ? array_values(array_unique(array_filter($_POST['store_keys'], fn($v) => $v !== ''))) : [];
    if (!$id || $name === '') { echo json_encode(['ok'=>false,'msg'=>'Rider name is required.']); exit; }
    if (empty($storeKeys)) { echo json_encode(['ok'=>false,'msg'=>'Select at least one branch for this rider.']); exit; }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE commissary_riders_salary_riders SET rider_name=?, monthly_salary=? WHERE id=?")
            ->execute([$name, $salary, $id]);
        $cur = $pdo->prepare("SELECT store_key FROM commissary_riders_salary_rider_stores WHERE rider_id=?");
        $cur->execute([$id]);
        $curKeys  = $cur->fetchAll(PDO::FETCH_COLUMN);
        $toAdd    = array_diff($storeKeys, $curKeys);
        $toRemove = array_diff($curKeys, $storeKeys);
        if ($toAdd) {
            $ins = $pdo->prepare("INSERT IGNORE INTO commissary_riders_salary_rider_stores (rider_id, store_key) VALUES (?,?)");
            foreach ($toAdd as $sk) { $ins->execute([$id, $sk]); }
        }
        if ($toRemove) {
            $del = $pdo->prepare("DELETE FROM commissary_riders_salary_rider_stores WHERE rider_id=? AND store_key=?");
            foreach ($toRemove as $sk) { $del->execute([$id, $sk]); }
        }
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: remove a rider from just one branch (auto-deletes the rider
//    entirely only if that was their last remaining branch) ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_unlink_rider'])) {
    header('Content-Type: application/json');
    $id       = (int)($_POST['rider_id'] ?? 0);
    $storeKey = $_POST['store_key'] ?? '';
    if (!$id || $storeKey === '') { echo json_encode(['ok'=>false,'msg'=>'Missing rider/branch.']); exit; }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM commissary_riders_salary_rider_stores WHERE rider_id=? AND store_key=?")->execute([$id, $storeKey]);
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM commissary_riders_salary_rider_stores WHERE rider_id=?");
        $remaining->execute([$id]);
        if ((int)$remaining->fetchColumn() === 0) {
            $pdo->prepare("DELETE FROM commissary_riders_salary_riders WHERE id=?")->execute([$id]);
        }
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: delete a rider entirely, from every branch ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_rider'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['rider_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM commissary_riders_salary_rider_stores WHERE rider_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM commissary_riders_salary_riders WHERE id=?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Fetch data for display ──────────────────────────────────────
$STORES = loadRiderStores($pdo);
$MONTHS = loadRiderMonths($pdo);
$ALL_RIDERS = loadAllRidersMap($pdo);
[$riderStoreLinks, $storeRiderLinks] = loadRiderStoreLinks($pdo);

// $RIDERS[store_key] = list of rider rows shown on that store's row. A rider
// linked to 2+ branches simply appears in each of those branches' lists.
$RIDERS = [];
foreach ($storeRiderLinks as $sk => $riderIds) {
    foreach ($riderIds as $rid) {
        if (isset($ALL_RIDERS[$rid])) { $RIDERS[$sk][] = $ALL_RIDERS[$rid]; }
    }
}

$statusMap = []; // [store_key][month_key] = status
$amountMap = []; // [store_key][month_key] = amount
foreach ($pdo->query("SELECT store_key, month_key, status, amount FROM commissary_riders_salary_status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $statusMap[$r['store_key']][$r['month_key']] = $r['status'];
    $amountMap[$r['store_key']][$r['month_key']] = (float)$r['amount'];
}

$COLOR_MAP = [
    'green'  => ['bg' => '#d9ead3', 'text' => '#1a3d1f'],
    'peach'  => ['bg' => '#fce5cd', 'text' => '#7a4a12'],
    'red'    => ['bg' => '#f4cccc', 'text' => '#7a1414'],
    'orange' => ['bg' => '#fbd9b0', 'text' => '#7a3f0a'],
    'blue'   => ['bg' => '#cfe2f3', 'text' => '#0b3d63'],
    'purple' => ['bg' => '#e0d6f5', 'text' => '#3d2166'],
];

// ── Summary calculations ─────────────────────────────────────────
$totalRiders  = count($ALL_RIDERS); // distinct people, not double-counted per branch
$totalAssignments = 0;
foreach ($riderStoreLinks as $sksArr) { $totalAssignments += count($sksArr); }
// Sum of every rider's monthly rate, counted once per branch they're assigned
// to (a rider on 2 branches is paid out of 2 branches' cells, so both count).
$totalPayroll = 0.0;
foreach ($ALL_RIDERS as $rid => $r) {
    $branchCount = max(count($riderStoreLinks[$rid] ?? []), 1);
    $totalPayroll += (float)$r['monthly_salary'] * $branchCount;
}

$monthTotals = []; // [month_key] => amount paid across all stores
$storeTotals = []; // [store_key] => amount paid across all months
$grandPaid   = 0.0;
foreach ($MONTHS as $mk => $m) { $monthTotals[$mk] = 0.0; }
foreach ($STORES as $sk => $s) {
    $storeTotals[$sk] = 0.0;
    foreach ($MONTHS as $mk => $m) {
        $amt = $amountMap[$sk][$mk] ?? 0.0;
        $monthTotals[$mk]   += $amt;
        $storeTotals[$sk]   += $amt;
        $grandPaid          += $amt;
    }
}

// Months-paid "units" per store (PAID=1, PARTIAL=0.5, else 0) — used to
// estimate how much of a rider's rate has actually been earned so far.
$storeUnits = [];
foreach ($STORES as $sk => $s) {
    $u = 0.0;
    foreach ($MONTHS as $mk => $m) { $u += statusUnit($statusMap[$sk][$mk] ?? ''); }
    $storeUnits[$sk] = $u;
}

$riderRows = []; // flat list for the "Salary per Rider" table — one row per
                 // (rider, branch) assignment, so a multi-branch rider shows
                 // up once per branch with that branch's own paid status.
foreach ($STORES as $sk => $s) {
    foreach (($RIDERS[$sk] ?? []) as $r) {
        $earned = (float)$r['monthly_salary'] * $storeUnits[$sk];
        $riderRows[] = [
            'name'    => $r['rider_name'],
            'store'   => $s['label'],
            'multi'   => count($riderStoreLinks[(int)$r['id']] ?? []) > 1,
            'rate'    => (float)$r['monthly_salary'],
            'units'   => $storeUnits[$sk],
            'earned'  => $earned,
        ];
    }
}
$riderTotalRate   = array_sum(array_column($riderRows, 'rate'));
$riderTotalEarned = array_sum(array_column($riderRows, 'earned'));

// ── Excel export: grid + per-rider salary + monthly totals ───────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Commissary'));
    $xlsx->title('Commissary Branch — Riders Salary Monitoring');
    $xlsx->subtitle('Generated ' . date('F j, Y'));
    $xlsx->blank();

    $header = ['STORE'];
    foreach ($MONTHS as $m) { $header[] = strtoupper($m['label']); }
    $header[] = 'TOTAL';
    $xlsx->header($header);
    foreach ($STORES as $sk => $s) {
        $row = [$s['label']];
        foreach ($MONTHS as $mk => $m) {
            $st  = $statusMap[$sk][$mk] ?? '—';
            $amt = $amountMap[$sk][$mk] ?? 0;
            $row[] = ($st === '' ? '—' : $st) . ' (' . number_format($amt, 2, '.', '') . ')';
        }
        $row[] = number_format($storeTotals[$sk], 2, '.', '');
        $xlsx->row($row);
    }
    $totalRow = ['TOTAL'];
    foreach ($MONTHS as $mk => $m) { $totalRow[] = number_format($monthTotals[$mk], 2, '.', ''); }
    $totalRow[] = number_format($grandPaid, 2, '.', '');
    $xlsx->totalRow($totalRow);

    $xlsx->blank(); $xlsx->blank();
    $xlsx->subtitle('Salary per Rider');
    $xlsx->header(['RIDER', 'STORE', 'MONTHLY RATE', 'MONTHS PAID', 'TOTAL EARNED', 'BRANCHES']);
    foreach ($riderRows as $rr) {
        $xlsx->row([$rr['name'], $rr['store'], number_format($rr['rate'],2,'.',''), number_format($rr['units'],1,'.',''), number_format($rr['earned'],2,'.',''), $rr['multi'] ? 'Multiple' : 'Single']);
    }
    $xlsx->totalRow(['TOTAL', '', number_format($riderTotalRate,2,'.',''), '', number_format($riderTotalEarned,2,'.',''), '']);

    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([20,16,16,16,16,16,14]);
    $xlsx->output('commissary_riders_salary_' . date('Y_m_d') . '.xlsx');
    exit;
}

$pageTitle  = 'Riders Salary Monitoring';
$activePage = 'commissary_riders_salary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.rsm-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
.rsm-actions { display:flex; gap:8px; flex-wrap:wrap; }
.rsm-btn {
  font-family:var(--font-m); font-size:.68rem; font-weight:700; padding:7px 12px; border-radius:6px;
  border:1px solid var(--border); background:#fff; cursor:pointer; transition:.15s;
}
.rsm-btn:hover { background:#f0f4ff; border-color:var(--border2); }
.rsm-btn.rsm-add { color:var(--accent); border-color:var(--accent); }
.rsm-btn.rsm-export { color:var(--accent3); border-color:var(--accent3); }

/* ── KPI strip ── */
.rsm-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.rsm-kpi { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 18px; position:relative; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.05); }
.rsm-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--rk-color, var(--accent)); opacity:.7; }
.rsm-kpi-label { font-family:var(--font-m); font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext); margin-bottom:8px; }
.rsm-kpi-value { font-size:1.4rem; font-weight:800; letter-spacing:-.03em; color:var(--rk-color, var(--accent)); font-variant-numeric:tabular-nums; }
.rsm-kpi-sub { font-family:var(--font-m); font-size:.64rem; color:var(--subtext); margin-top:4px; }

/* ── Main grid ── */
.rsm-outer { width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border); background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:24px; }
.rsm-table { border-collapse:collapse; width:100%; font-size:.78rem; min-width:920px; }
.rsm-table th {
  background:#c76b96; color:#1a1a1a; font-family:var(--font-m); font-size:.72rem; font-weight:800;
  padding:10px 12px; border:1px solid #b25784; text-align:center; letter-spacing:.03em;
}
.rsm-table th:first-child, .rsm-table th:nth-child(2) { text-align:left; }
.rsm-table td { padding:8px 10px; border:1px solid var(--border); text-align:center; vertical-align:top; }
.rsm-table td.rsm-store-cell { text-align:left; font-family:var(--font-m); font-weight:800; font-size:.8rem; vertical-align:middle; }
.rsm-table td.rsm-riders-cell { text-align:left; min-width:170px; }
.rsm-table td.rsm-total-cell { font-family:var(--font-m); font-weight:800; font-size:.78rem; background:#faf7f0 !important; color:#1a1a1a; vertical-align:middle; }
.rsm-table td.rsm-del-cell { text-align:center; width:30px; vertical-align:middle; }
.rsm-del-x { cursor:pointer; color:#991b1b; font-weight:800; opacity:.55; }
.rsm-del-x:hover { opacity:1; }
.rsm-table tfoot td { background:#eef0f4; font-family:var(--font-m); font-weight:800; font-size:.78rem; border-top:2px solid var(--border2); }

.rsm-cell-stack { display:flex; flex-direction:column; gap:5px; align-items:center; }
.rsm-status-select {
  width:100%; max-width:150px; font-family:var(--font-m); font-size:.7rem; font-weight:700;
  padding:6px 8px; border-radius:5px; border:1px solid rgba(0,0,0,.15); background:#fce9b8; color:#5c4a12;
  cursor:pointer; text-align:center; text-align-last:center;
}
.rsm-status-select[data-status=""] { background:#fff; color:var(--subtext); }
.rsm-status-select[data-status="PAID"] { background:#fce9b8; color:#5c4a12; }
.rsm-status-select[data-status="UNPAID"] { background:#f8d7d7; color:#7a1414; }
.rsm-status-select[data-status="PARTIAL"] { background:#d7e8fb; color:#0b3d63; }

.rsm-amount-wrap { position:relative; width:100%; max-width:150px; }
.rsm-amount-wrap::before { content:'₱'; position:absolute; left:7px; top:50%; transform:translateY(-50%); font-family:var(--font-m); font-size:.68rem; color:var(--subtext); pointer-events:none; }
.rsm-amount-input {
  width:100%; font-family:var(--font-m); font-size:.7rem; font-weight:600; text-align:right;
  padding:5px 7px 5px 18px; border-radius:5px; border:1px solid var(--border); background:#fff; color:var(--text);
}
.rsm-amount-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 2px rgba(15,123,92,.1); }

.rsm-month-th { position:relative; }
.rsm-month-del { position:absolute; top:2px; right:4px; font-size:.6rem; cursor:pointer; opacity:.6; }
.rsm-month-del:hover { opacity:1; }

/* ── Rider chips ── */
.rsm-rider-chips { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:6px; }
.rsm-chip {
  display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,.65); border:1px solid rgba(0,0,0,.12);
  border-radius:20px; padding:3px 4px 3px 9px; font-family:var(--font-m); font-size:.65rem; font-weight:700; cursor:pointer;
  transition:.15s;
}
.rsm-chip:hover { background:#fff; border-color:var(--border2); }
.rsm-chip-rate { opacity:.65; font-weight:500; }
.rsm-chip-x { display:inline-flex; align-items:center; justify-content:center; width:15px; height:15px; border-radius:50%; background:rgba(0,0,0,.08); font-size:.6rem; color:#7a1414; }
.rsm-chip-x:hover { background:rgba(153,27,27,.18); }
.rsm-chip-add {
  display:inline-flex; align-items:center; gap:3px; background:transparent; border:1px dashed var(--border2);
  border-radius:20px; padding:3px 9px; font-family:var(--font-m); font-size:.65rem; font-weight:700; cursor:pointer; color:var(--accent);
}
.rsm-chip-add:hover { background:var(--accent-dim); }
.rsm-chip-multi { border-color:var(--accent); box-shadow:0 0 0 1px var(--accent) inset; }
.rsm-chip-multi-badge { display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:10px; padding:0 5px; font-size:.58rem; font-weight:800; line-height:1.4; }

/* ── Rider add/edit modal ── */
.rsm-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:1000; }
.rsm-modal { background:#fff; border-radius:10px; padding:20px 22px; width:320px; max-width:90vw; box-shadow:0 10px 40px rgba(0,0,0,.25); }
.rsm-modal-title { font-weight:800; font-size:.95rem; margin-bottom:14px; }
.rsm-modal-field { display:block; margin-bottom:12px; font-family:var(--font-m); font-size:.64rem; font-weight:700; color:var(--subtext); text-transform:uppercase; letter-spacing:.05em; }
.rsm-modal-field input[type=text], .rsm-modal-field input[type=number] {
  display:block; width:100%; margin-top:5px; padding:8px 10px; border-radius:6px; border:1px solid var(--border);
  font-family:inherit; font-size:.85rem; text-transform:none; letter-spacing:normal; font-weight:500; color:var(--text); box-sizing:border-box;
}
.rsm-modal-field-label { margin-bottom:6px; }
.rsm-modal-checks { display:flex; flex-direction:column; gap:6px; max-height:160px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:8px 10px; }
.rsm-modal-check { display:flex; align-items:center; gap:8px; font-family:inherit; font-size:.8rem; font-weight:500; text-transform:none; letter-spacing:normal; color:var(--text); cursor:pointer; }
.rsm-modal-actions { display:flex; align-items:center; justify-content:space-between; margin-top:16px; gap:8px; }
.rsm-modal-btn { font-family:var(--font-m); font-size:.7rem; font-weight:700; padding:7px 14px; border-radius:6px; border:1px solid var(--border); background:#fff; cursor:pointer; }
.rsm-modal-btn:hover { background:#f0f4ff; }
.rsm-modal-btn.rsm-modal-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.rsm-modal-btn.rsm-modal-danger { color:#991b1b; border-color:#f4cccc; }

/* ── Summary section ── */
.rsm-summary-grid { display:grid; grid-template-columns:1.6fr 1fr; gap:16px; align-items:start; }
.rsm-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.rsm-card-title { font-size:.86rem; font-weight:700; letter-spacing:-.01em; margin-bottom:4px; }
.rsm-card-sub { font-family:var(--font-m); font-size:.66rem; color:var(--subtext); margin-bottom:14px; }
.rsm-mini-table { width:100%; border-collapse:collapse; font-size:.76rem; }
.rsm-mini-table th { text-align:left; font-family:var(--font-m); font-size:.6rem; text-transform:uppercase; letter-spacing:.07em; color:var(--subtext); padding:0 8px 8px 0; border-bottom:1px solid var(--border); }
.rsm-mini-table td { padding:7px 8px 7px 0; border-bottom:1px solid var(--border); vertical-align:middle; }
.rsm-mini-table tr:last-child td { border-bottom:none; }
.rsm-mini-table td.num, .rsm-mini-table th.num { text-align:right; font-family:var(--font-m); font-variant-numeric:tabular-nums; }
.rsm-mini-table tfoot td { font-weight:800; font-family:var(--font-m); border-top:2px solid var(--border2); border-bottom:none; padding-top:10px; }
.rsm-unit-badge { display:inline-block; padding:2px 8px; border-radius:20px; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-family:var(--font-m); font-size:.62rem; font-weight:700; }
.rsm-unit-badge.zero { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.rsm-unit-badge.partial { background:#fffbeb; color:#b45309; border-color:#fde68a; }

@media(max-width:1100px){ .rsm-kpi-grid{grid-template-columns:repeat(2,1fr)} .rsm-summary-grid{grid-template-columns:1fr} }
</style>

<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Riders Salary Monitoring</div>
    <div class="section-subtitle">Commissary → riders, monthly PAID / UNPAID status &amp; salary amount per store</div>
  </div>
</div>

<!-- KPI strip -->
<div class="rsm-kpi-grid">
  <div class="rsm-kpi" style="--rk-color:#0f7b5c">
    <div class="rsm-kpi-label">Total Riders</div>
    <div class="rsm-kpi-value"><?= (int)$totalRiders ?></div>
    <div class="rsm-kpi-sub"><?= $totalAssignments ?> branch assignment<?= $totalAssignments===1?'':'s' ?> across <?= count($STORES) ?> store<?= count($STORES)===1?'':'s' ?></div>
  </div>
  <div class="rsm-kpi" style="--rk-color:#0b5fff">
    <div class="rsm-kpi-label">Monthly Payroll</div>
    <div class="rsm-kpi-value"><?= money($totalPayroll) ?></div>
    <div class="rsm-kpi-sub">sum of all riders' monthly rate</div>
  </div>
  <div class="rsm-kpi" style="--rk-color:#d97706">
    <div class="rsm-kpi-label">Total Paid (All Months)</div>
    <div class="rsm-kpi-value"><?= money($grandPaid) ?></div>
    <div class="rsm-kpi-sub">sum of all logged amounts</div>
  </div>
  <div class="rsm-kpi" style="--rk-color:#be123c">
    <div class="rsm-kpi-label">Months Tracked</div>
    <div class="rsm-kpi-value"><?= count($MONTHS) ?></div>
    <div class="rsm-kpi-sub"><?= count($MONTHS) ? htmlspecialchars(reset($MONTHS)['label']) . ' – ' . htmlspecialchars(end($MONTHS)['label']) : '—' ?></div>
  </div>
</div>

<div class="rsm-header-row">
  <div></div>
  <div class="rsm-actions">
    <a class="rsm-btn rsm-export" href="commissary_riders_salary.php?export_csv=1">⬇ Export XLSX</a>
    <button type="button" class="rsm-btn rsm-add" onclick="addMonth()">➕ Add Month</button>
    <button type="button" class="rsm-btn rsm-add" onclick="addStore()">➕ Add Store</button>
  </div>
</div>

<div class="rsm-outer">
<table class="rsm-table" id="rsmTable">
  <thead>
    <tr>
      <th>STORE</th>
      <th>RIDERS</th>
      <?php foreach ($MONTHS as $mk => $m): ?>
      <th class="rsm-month-th">
        <?= htmlspecialchars($m['label']) ?>
        <span class="rsm-month-del" title="Delete this month" onclick="deleteMonth('<?= htmlspecialchars($mk) ?>','<?= htmlspecialchars($m['label']) ?>')">✕</span>
      </th>
      <?php endforeach; ?>
      <th>TOTAL</th>
      <th style="width:30px"></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($STORES as $sk => $s): $c = $COLOR_MAP[$s['color']] ?? $COLOR_MAP['green']; ?>
    <tr>
      <td class="rsm-store-cell" style="background:<?= $c['bg'] ?>;color:<?= $c['text'] ?>"><?= htmlspecialchars($s['label']) ?></td>
      <td class="rsm-riders-cell" style="background:<?= $c['bg'] ?>">
        <div class="rsm-rider-chips">
          <?php foreach (($RIDERS[$sk] ?? []) as $r): $branchCount = count($riderStoreLinks[(int)$r['id']] ?? []); ?>
          <span class="rsm-chip<?= $branchCount > 1 ? ' rsm-chip-multi' : '' ?>"
                title="<?= htmlspecialchars($r['rider_name']) ?> — <?= money($r['monthly_salary']) ?>/mo<?= $branchCount > 1 ? ' · works ' . $branchCount . ' branches' : '' ?> · click to edit"
                onclick="editRider(<?= (int)$r['id'] ?>)">
            <?= htmlspecialchars($r['rider_name']) ?>
            <?php if ($branchCount > 1): ?><span class="rsm-chip-multi-badge">×<?= $branchCount ?></span><?php endif; ?>
            <span class="rsm-chip-rate">₱<?= number_format($r['monthly_salary'],0) ?></span>
            <span class="rsm-chip-x" title="Remove from this branch only" onclick="event.stopPropagation(); unlinkRiderFromStore(<?= (int)$r['id'] ?>,'<?= htmlspecialchars($sk) ?>','<?= htmlspecialchars(addslashes($r['rider_name'])) ?>','<?= htmlspecialchars(addslashes($s['label'])) ?>')">✕</span>
          </span>
          <?php endforeach; ?>
        </div>
        <span class="rsm-chip-add" onclick="addRider('<?= htmlspecialchars($sk) ?>','<?= htmlspecialchars(addslashes($s['label'])) ?>')">➕ Rider</span>
      </td>
      <?php foreach ($MONTHS as $mk => $m): $status = $statusMap[$sk][$mk] ?? ''; $amt = $amountMap[$sk][$mk] ?? 0; ?>
      <td style="background:<?= $c['bg'] ?>">
        <div class="rsm-cell-stack">
          <select class="rsm-status-select" data-status="<?= htmlspecialchars($status) ?>"
                  onchange="setStatus(this,'<?= htmlspecialchars($sk) ?>','<?= htmlspecialchars($mk) ?>')">
            <option value=""        <?= $status===''        ? 'selected' : '' ?>>—</option>
            <option value="PAID"    <?= $status==='PAID'    ? 'selected' : '' ?>>PAID</option>
            <option value="UNPAID"  <?= $status==='UNPAID'  ? 'selected' : '' ?>>UNPAID</option>
            <option value="PARTIAL" <?= $status==='PARTIAL' ? 'selected' : '' ?>>PARTIAL</option>
          </select>
          <div class="rsm-amount-wrap">
            <input type="number" class="rsm-amount-input" step="0.01" min="0" value="<?= $amt > 0 ? number_format($amt,2,'.','') : '' ?>"
                   placeholder="0.00"
                   onchange="setAmount(this,'<?= htmlspecialchars($sk) ?>','<?= htmlspecialchars($mk) ?>')">
          </div>
        </div>
      </td>
      <?php endforeach; ?>
      <td class="rsm-total-cell" style="background:<?= $c['bg'] ?>"><?= money($storeTotals[$sk]) ?></td>
      <td class="rsm-del-cell"><span class="rsm-del-x" title="Delete this store" onclick="deleteStore('<?= htmlspecialchars($sk) ?>','<?= htmlspecialchars($s['label']) ?>')">✕</span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL</td>
      <?php foreach ($MONTHS as $mk => $m): ?>
      <td><?= money($monthTotals[$mk]) ?></td>
      <?php endforeach; ?>
      <td><?= money($grandPaid) ?></td>
      <td></td>
    </tr>
  </tfoot>
</table>
</div>

<!-- Summary section -->
<div class="rsm-summary-grid">
  <div class="rsm-card">
    <div class="rsm-card-title">Salary per Rider</div>
    <div class="rsm-card-sub">Monthly rate × months paid so far (PARTIAL counts as half a month)</div>
    <table class="rsm-mini-table">
      <thead>
        <tr><th>Rider</th><th>Store</th><th class="num">Monthly Rate</th><th>Months Paid</th><th class="num">Total Earned</th></tr>
      </thead>
      <tbody>
        <?php if (empty($riderRows)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--subtext);padding:14px 0;">No riders added yet — use “➕ Rider” on a store row.</td></tr>
        <?php else: foreach ($riderRows as $rr): 
          $badgeClass = $rr['units'] <= 0 ? 'zero' : ($rr['units'] < count($MONTHS) ? 'partial' : '');
        ?>
        <tr>
          <td style="font-weight:700"><?= htmlspecialchars($rr['name']) ?><?php if ($rr['multi']): ?> <span class="rsm-unit-badge" style="background:#eef2ff;color:#4338ca;border-color:#c7d2fe;">multi-branch</span><?php endif; ?></td>
          <td style="color:var(--subtext)"><?= htmlspecialchars($rr['store']) ?></td>
          <td class="num"><?= money($rr['rate']) ?></td>
          <td><span class="rsm-unit-badge <?= $badgeClass ?>"><?= rtrim(rtrim(number_format($rr['units'],1),'0'),'.') ?: '0' ?> / <?= count($MONTHS) ?></span></td>
          <td class="num" style="font-weight:700"><?= money($rr['earned']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($riderRows)): ?>
      <tfoot>
        <tr><td colspan="2">TOTAL</td><td class="num"><?= money($riderTotalRate) ?></td><td></td><td class="num"><?= money($riderTotalEarned) ?></td></tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <div class="rsm-card">
    <div class="rsm-card-title">Monthly Payroll Summary</div>
    <div class="rsm-card-sub">Amount paid across all stores, per month</div>
    <table class="rsm-mini-table">
      <thead><tr><th>Month</th><th>Stores Paid</th><th class="num">Total Paid</th></tr></thead>
      <tbody>
        <?php foreach ($MONTHS as $mk => $m): 
          $paidCount = 0;
          foreach ($STORES as $sk => $s) { if (($statusMap[$sk][$mk] ?? '') === 'PAID') $paidCount++; }
        ?>
        <tr>
          <td style="font-weight:700"><?= htmlspecialchars($m['label']) ?></td>
          <td style="color:var(--subtext)"><?= $paidCount ?> / <?= count($STORES) ?></td>
          <td class="num"><?= money($monthTotals[$mk]) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="2">GRAND TOTAL</td><td class="num"><?= money($grandPaid) ?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
const ENDPOINT = 'commissary_riders_salary.php';
const ALL_STORES = <?= json_encode(array_map(fn($s) => ['key' => $s['store_key'], 'label' => $s['label']], array_values($STORES))) ?>;
const ALL_RIDERS_DATA = <?= json_encode(array_map(function($r) use ($riderStoreLinks) {
    return ['id' => (int)$r['id'], 'name' => $r['rider_name'], 'rate' => (float)$r['monthly_salary'], 'stores' => array_values($riderStoreLinks[(int)$r['id']] ?? [])];
}, array_values($ALL_RIDERS))) ?>;

async function setStatus(sel, storeKey, monthKey) {
  sel.dataset.status = sel.value;
  const fd = new FormData();
  fd.append('ajax_set_status', '1');
  fd.append('store_key', storeKey);
  fd.append('month_key', monthKey);
  fd.append('status', sel.value);
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { showToast('✓ Saved'); setTimeout(()=>location.reload(), 350); }
    else { showToast('❌ ' + (data.msg || 'Save failed'), 'error'); }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

async function setAmount(input, storeKey, monthKey) {
  const amount = parseFloat(input.value || '0') || 0;
  const fd = new FormData();
  fd.append('ajax_set_amount', '1');
  fd.append('store_key', storeKey);
  fd.append('month_key', monthKey);
  fd.append('amount', amount);
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { showToast('✓ Saved'); setTimeout(()=>location.reload(), 350); }
    else { showToast('❌ ' + (data.msg || 'Save failed'), 'error'); }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

async function addStore() {
  const label = prompt('Name of the new store row:');
  if (!label || !label.trim()) return;
  const color = prompt('Row color — type one of: green, peach, red, orange, blue, purple', 'green') || 'green';
  const fd = new FormData();
  fd.append('ajax_add_store', '1');
  fd.append('label', label.trim());
  fd.append('color', color.trim());
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { location.reload(); }
    else { alert('❌ ' + (data.msg || 'Could not add store')); }
  } catch (e) { alert('❌ Network error'); }
}

async function deleteStore(storeKey, label) {
  if (!confirm('Delete the "' + label + '" row? This removes its riders and saved statuses too.')) return;
  const fd = new FormData();
  fd.append('ajax_delete_store', '1');
  fd.append('store_key', storeKey);
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { location.reload(); }
    else { alert('❌ ' + (data.msg || 'Delete failed')); }
  } catch (e) { alert('❌ Network error'); }
}

async function addMonth() {
  const label = prompt('New month column label (e.g. August 2026):');
  if (!label || !label.trim()) return;
  const monthKey = prompt('Short key for this month (e.g. 2026-08):', '');
  if (!monthKey || !monthKey.trim()) return;
  const fd = new FormData();
  fd.append('ajax_add_month', '1');
  fd.append('label', label.trim());
  fd.append('month_key', monthKey.trim());
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { location.reload(); }
    else { alert('❌ ' + (data.msg || 'Could not add month')); }
  } catch (e) { alert('❌ Network error'); }
}

async function deleteMonth(monthKey, label) {
  if (!confirm('Delete the "' + label + '" column? This removes its saved statuses too.')) return;
  const fd = new FormData();
  fd.append('ajax_delete_month', '1');
  fd.append('month_key', monthKey);
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { location.reload(); }
    else { alert('❌ ' + (data.msg || 'Delete failed')); }
  } catch (e) { alert('❌ Network error'); }
}

function escapeAttr(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function openRiderModal(mode, opts) {
  let rider = null;
  if (mode === 'edit') {
    rider = ALL_RIDERS_DATA.find(r => r.id === opts.riderId);
    if (!rider) return;
  }
  const overlay = document.createElement('div');
  overlay.className = 'rsm-modal-overlay';
  const checksHtml = ALL_STORES.map(s => {
    const checked = mode === 'edit' ? rider.stores.includes(s.key) : (s.key === opts.storeKey);
    return '<label class="rsm-modal-check"><input type="checkbox" value="' + escapeAttr(s.key) + '"' + (checked ? ' checked' : '') + '> ' + escapeAttr(s.label) + '</label>';
  }).join('');
  overlay.innerHTML =
    '<div class="rsm-modal">' +
      '<div class="rsm-modal-title">' + (mode === 'edit' ? 'Edit Rider' : 'Add Rider') + '</div>' +
      '<label class="rsm-modal-field">Name<input type="text" id="rsmModalName" value="' + (mode === 'edit' ? escapeAttr(rider.name) : '') + '"></label>' +
      '<label class="rsm-modal-field">Monthly salary (₱)<input type="number" id="rsmModalRate" step="0.01" min="0" value="' + (mode === 'edit' ? rider.rate : '8000') + '"></label>' +
      '<div class="rsm-modal-field">' +
        '<div class="rsm-modal-field-label">Branches this rider works in — tick 2 or more to assign them to multiple stores</div>' +
        '<div class="rsm-modal-checks">' + checksHtml + '</div>' +
      '</div>' +
      '<div class="rsm-modal-actions">' +
        (mode === 'edit' ? '<button type="button" class="rsm-modal-btn rsm-modal-danger" id="rsmModalDelete">Delete rider</button>' : '<span></span>') +
        '<div><button type="button" class="rsm-modal-btn" id="rsmModalCancel">Cancel</button> ' +
        '<button type="button" class="rsm-modal-btn rsm-modal-primary" id="rsmModalSave">Save</button></div>' +
      '</div>' +
    '</div>';
  document.body.appendChild(overlay);

  overlay.querySelector('#rsmModalCancel').onclick = () => overlay.remove();
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

  if (mode === 'edit') {
    overlay.querySelector('#rsmModalDelete').onclick = async () => {
      if (!confirm('Delete rider "' + rider.name + '" completely, from every branch?')) return;
      const fd = new FormData();
      fd.append('ajax_delete_rider', '1');
      fd.append('rider_id', rider.id);
      try {
        const res = await fetch(ENDPOINT, {method:'POST', body:fd});
        const data = await res.json();
        if (data.ok) { location.reload(); } else { alert('❌ ' + (data.msg || 'Delete failed')); }
      } catch (e) { alert('❌ Network error'); }
    };
  }

  overlay.querySelector('#rsmModalSave').onclick = async () => {
    const name = overlay.querySelector('#rsmModalName').value.trim();
    if (!name) { alert('Rider name is required.'); return; }
    const rate = parseFloat(overlay.querySelector('#rsmModalRate').value) || 0;
    const checked = Array.from(overlay.querySelectorAll('.rsm-modal-checks input:checked')).map(c => c.value);
    if (!checked.length) { alert('Select at least one branch.'); return; }
    const fd = new FormData();
    if (mode === 'edit') {
      fd.append('ajax_edit_rider', '1');
      fd.append('rider_id', rider.id);
    } else {
      fd.append('ajax_add_rider', '1');
    }
    fd.append('rider_name', name);
    fd.append('monthly_salary', rate);
    checked.forEach(sk => fd.append('store_keys[]', sk));
    try {
      const res = await fetch(ENDPOINT, {method:'POST', body:fd});
      const data = await res.json();
      if (data.ok) { location.reload(); } else { alert('❌ ' + (data.msg || 'Save failed')); }
    } catch (e) { alert('❌ Network error'); }
  };
}

function addRider(storeKey, storeLabel) {
  openRiderModal('add', {storeKey, storeLabel});
}

function editRider(riderId) {
  openRiderModal('edit', {riderId});
}

async function unlinkRiderFromStore(riderId, storeKey, riderName, storeLabel) {
  if (!confirm('Remove "' + riderName + '" from ' + storeLabel + '? (Use the ✕ on the store row to delete the store itself; this only unassigns the rider from this one branch.)')) return;
  const fd = new FormData();
  fd.append('ajax_unlink_rider', '1');
  fd.append('rider_id', riderId);
  fd.append('store_key', storeKey);
  try {
    const res = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { location.reload(); }
    else { alert('❌ ' + (data.msg || 'Remove failed')); }
  } catch (e) { alert('❌ Network error'); }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2500);
}
</script>
