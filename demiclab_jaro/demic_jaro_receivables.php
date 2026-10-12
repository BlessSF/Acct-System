<?php
// ============================================================
//  demic_jaro_receivables.php — DemicLab Receivables Module
//  Mirrors demic_jaro_payables.php's architecture, replicated from
//  RECEIVABLES.xlsx (5 tabs):
//   • SUMMARY          → Summary tab       (jaro_receivables_summary)    [was LEDGER / LEDGER 2]
//   • MONITORING       → Monitoring tab    (jaro_receivables_monitoring) [was MONITORING — stacked per HMO/Corporate, dynamic sections]
//   • PAST DUE         → Past Due tab      (jaro_receivables_pastdue)    [was HMO PAST DUE]
//   • CLIENT LEDGERS   → Client Ledgers tab(jaro_receivables_ledger)     [was the ~37 individual HMO/company sheets — stacked per client, dynamic sections]
//   • OTHERS           → Others tab        (jaro_receivables_others)     [was OTHERS — misc small receivables, flat list with a NAME column]
//   • SOA NUM TRACKER  → SOA Tracker tab   (jaro_receivables_soatracker) [was COMP.-SOA NUM TRACKER — running SN-C##### sequence with USED status]
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only DemicLab branch and management can access
if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create tables ──────────────────────────────────────
// NOTE: `jaro_receivables_summary` is now a READ-ONLY, AUTO-CALCULATED view of the
// Client Ledgers tab (see $SUMMARY computation below). The numeric columns are
// kept on this table for backward compatibility only and are no longer written
// to from the UI. The only thing still stored here is per-client REMARKS,
// upserted by `hmo_name` (see idx_hmo_name unique key + ajax_update_summary_remarks).
$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_summary` (
    `id`                     int(11) NOT NULL AUTO_INCREMENT,
    `hmo_name`               varchar(255) DEFAULT '',
    `amount`                 decimal(14,2) DEFAULT 0.00,
    `ewt_tax`                decimal(14,2) DEFAULT 0.00,
    `jaro_receivables_after_ew`   decimal(14,2) DEFAULT 0.00,
    `surcharge`              decimal(14,2) DEFAULT 0.00,
    `payment`                decimal(14,2) DEFAULT 0.00,
    `balance`                decimal(14,2) DEFAULT 0.00,
    `remarks`                varchar(255) DEFAULT '',
    `sort_order`             int(11) DEFAULT 0,
    `saved_by`               varchar(100) DEFAULT NULL,
    `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_hmo_name` (`hmo_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_monitoring` (
    `id`                 int(11) NOT NULL AUTO_INCREMENT,
    `hmo_name`           varchar(255) DEFAULT '',
    `entry_date`         date DEFAULT NULL,
    `amount`             decimal(14,2) DEFAULT 0.00,
    `remaining_balance`  decimal(14,2) DEFAULT 0.00,
    `remarks`            varchar(255) DEFAULT '',
    `saved_by`           varchar(100) DEFAULT NULL,
    `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hmo` (`hmo_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_pastdue` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `hmo_name`     varchar(255) DEFAULT '',
    `entry_date`   date DEFAULT NULL,
    `amount`       decimal(14,2) DEFAULT 0.00,
    `due_date`     date DEFAULT NULL,
    `status`       varchar(30) DEFAULT 'NOT PAID',
    `remarks`      varchar(255) DEFAULT '',
    `saved_by`     varchar(100) DEFAULT NULL,
    `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_ledger` (
    `id`                 int(11) NOT NULL AUTO_INCREMENT,
    `client_name`        varchar(255) DEFAULT '',
    `billing_date`       date DEFAULT NULL,
    `soa_number`         varchar(100) DEFAULT '',
    `amount`             decimal(14,2) DEFAULT 0.00,
    `payment_date`       date DEFAULT NULL,
    `taxes_withheld`     decimal(14,2) DEFAULT 0.00,
    `paid_amount`        decimal(14,2) DEFAULT 0.00,
    `remaining_balance`  decimal(14,2) DEFAULT 0.00,
    `aging`              varchar(60) DEFAULT '',
    `surcharge`          decimal(14,2) DEFAULT 0.00,
    `remarks`            varchar(255) DEFAULT '',
    `saved_by`           varchar(100) DEFAULT NULL,
    `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_client` (`client_name`),
    KEY `idx_billing` (`billing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Some client sheets carry extra columns the base schema above doesn't have
// (SOA Sent date, Check #, Check Amount, Less 11.18%, Tax, Rebate). Add them
// safely so existing installs migrate without losing data.
function ensureColumn(PDO $pdo, string $table, string $column, string $definition) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $chk->execute([$table, $column]);
    if ((int)$chk->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
ensureColumn($pdo, 'jaro_receivables_ledger', 'soa_sent_date', "date DEFAULT NULL AFTER soa_number");
ensureColumn($pdo, 'jaro_receivables_ledger', 'check_no',      "varchar(60) DEFAULT '' AFTER amount");
ensureColumn($pdo, 'jaro_receivables_ledger', 'check_amount',  "decimal(14,2) DEFAULT 0.00 AFTER check_no");
ensureColumn($pdo, 'jaro_receivables_ledger', 'less_1118',     "decimal(14,2) DEFAULT 0.00 AFTER taxes_withheld");
ensureColumn($pdo, 'jaro_receivables_ledger', 'tax',            "decimal(14,2) DEFAULT 0.00 AFTER less_1118");
ensureColumn($pdo, 'jaro_receivables_ledger', 'rebate',         "decimal(14,2) DEFAULT 0.00 AFTER surcharge");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_others` (
    `id`                 int(11) NOT NULL AUTO_INCREMENT,
    `name`               varchar(255) DEFAULT '',
    `billing_date`       date DEFAULT NULL,
    `amount`             decimal(14,2) DEFAULT 0.00,
    `payment_date`       date DEFAULT NULL,
    `taxes_withheld`     decimal(14,2) DEFAULT 0.00,
    `paid_amount`        decimal(14,2) DEFAULT 0.00,
    `remaining_balance`  decimal(14,2) DEFAULT 0.00,
    `aging`              varchar(60) DEFAULT '',
    `surcharge`          decimal(14,2) DEFAULT 0.00,
    `remarks`            varchar(255) DEFAULT '',
    `saved_by`           varchar(100) DEFAULT NULL,
    `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `jaro_receivables_soatracker` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `soa_number`   varchar(50) DEFAULT '',
    `used`         tinyint(1) NOT NULL DEFAULT 0,
    `saved_by`     varchar(100) DEFAULT NULL,
    `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Seed the tracker once with SN-C00001..SN-C00050, matching the original
// spreadsheet (rows 1-23 already marked USED / highlighted yellow there).
if ((int)$pdo->query("SELECT COUNT(*) FROM `jaro_receivables_soatracker`")->fetchColumn() === 0) {
    $seedStmt = $pdo->prepare("INSERT INTO `jaro_receivables_soatracker` (`soa_number`, `used`) VALUES (?, ?)");
    for ($i = 1; $i <= 50; $i++) {
        $seedStmt->execute(['SN-C' . str_pad($i, 5, '0', STR_PAD_LEFT), $i <= 23 ? 1 : 0]);
    }
}

// ── Field whitelists (used by generic AJAX handlers below) ──
$TBL = [
    'monitoring' => [
        'table' => 'jaro_receivables_monitoring',
        'txt'   => ['hmo_name', 'remarks'],
        'num'   => ['amount', 'remaining_balance'],
        'date'  => ['entry_date'],
        'sel'   => [],
    ],
    'pastdue' => [
        'table' => 'jaro_receivables_pastdue',
        'txt'   => ['hmo_name', 'remarks'],
        'num'   => ['amount'],
        'date'  => ['entry_date', 'due_date'],
        'sel'   => ['status'],
    ],
    'ledger' => [
        'table' => 'jaro_receivables_ledger',
        'txt'   => ['client_name', 'soa_number', 'check_no', 'aging', 'remarks'],
        'num'   => ['amount', 'check_amount', 'taxes_withheld', 'less_1118', 'tax', 'paid_amount', 'remaining_balance', 'surcharge', 'rebate'],
        'date'  => ['billing_date', 'soa_sent_date', 'payment_date'],
        'sel'   => [],
    ],
    'others' => [
        'table' => 'jaro_receivables_others',
        'txt'   => ['name', 'aging', 'remarks'],
        'num'   => ['amount', 'taxes_withheld', 'paid_amount', 'remaining_balance', 'surcharge'],
        'date'  => ['billing_date', 'payment_date'],
        'sel'   => [],
    ],
];

function tblFields(array $def): array {
    return array_merge($def['txt'], $def['num'], $def['date'], $def['sel']);
}

// ── AJAX: generic add ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $data = [];
    foreach ($def['txt'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($def['num'] as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    foreach ($def['date'] as $f) $data[$f] = $_POST[$f] ?: null;
    foreach ($def['sel'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO `{$def['table']}` (" . implode(',', array_map(fn($f)=>"`$f`",$fields)) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $def = $TBL[$key];
    $id  = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($def['txt'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    foreach ($def['num'] as $f) { $sets[]="`$f`=?"; $vals[]=(float)($_POST[$f] ?? 0); }
    foreach ($def['date'] as $f) { $sets[]="`$f`=?"; $vals[]=$_POST[$f] ?: null; }
    foreach ($def['sel'] as $f) { $sets[]="`$f`=?"; $vals[]=trim($_POST[$f] ?? ''); }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE `{$def['table']}` SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: generic delete ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $key = $_POST['tbl'] ?? '';
    if (!isset($TBL[$key])) { echo json_encode(['ok'=>false,'msg'=>'Bad table']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM `{$TBL[$key]['table']}` WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Summary tab remarks only (numbers on that tab are read-only  ──
// ── and auto-calculated from Client Ledgers — see $SUMMARY below) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_summary_remarks'])) {
    header('Content-Type: application/json');
    $hmo = trim($_POST['hmo_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    if ($hmo === '') { echo json_encode(['ok'=>false,'msg'=>'Missing client name']); exit; }
    try {
        $pdo->prepare("INSERT INTO `jaro_receivables_summary` (`hmo_name`, `remarks`, `saved_by`)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE `remarks`=VALUES(`remarks`), `saved_by`=VALUES(`saved_by`)")
            ->execute([$hmo, $remarks, $user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: SOA tracker — generate next SOA number (SN-C##### auto-increment) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_soa_add'])) {
    header('Content-Type: application/json');
    try {
        $max = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING(soa_number, 5) AS UNSIGNED)) FROM `jaro_receivables_soatracker` WHERE soa_number LIKE 'SN-C%'")->fetchColumn();
        $soaNumber = 'SN-C' . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO `jaro_receivables_soatracker` (`soa_number`, `used`, `saved_by`) VALUES (?, 0, ?)")
            ->execute([$soaNumber, $user['name']]);
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'soa_number'=>$soaNumber]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: SOA tracker — toggle USED status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_soa_toggle'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    try {
        $pdo->prepare("UPDATE `jaro_receivables_soatracker` SET `used` = 1 - `used`, `saved_by` = ? WHERE id = ?")
            ->execute([$user['name'], $id]);
        $used = (int)$pdo->query("SELECT used FROM `jaro_receivables_soatracker` WHERE id = " . (int)$id)->fetchColumn();
        echo json_encode(['ok'=>true, 'used'=>$used]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: SOA tracker — delete a row ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_soa_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM `jaro_receivables_soatracker` WHERE id = ?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Known clients / HMOs — pre-seed sections so they show up empty  ──
// ── even before any row has been saved for them. Anything saved     ──
// ── under a new name (via "+ New Client" / "+ New HMO") is picked   ──
// ── up automatically on reload from the DB, same as the PDC types.  ──
$KNOWN_CLIENTS = [
    'CARITAS','COCOLIFE','INTELLICARE','AVEGA','MED ASIA','VALUCARE','XTENDOPS','ICARE/IN LIFE','IMS','IQOR',
    'AMAPHIL','PHILCARE','PACIFIC CROSS','MAXICARE','NEARSOL','JLS AIRCONDITIONING','MULTIPLIERS','LIFE AND HEALTH',
    'ANDOKS','ESSENTIALE','JPA AGENCY','ASIAPRO','F. GURREA','FREMONT','FIRMC','SONYU','JF HORIZON','VERMILLION',
    'APS TRAKING CARGO','SUREFIRE','WEST TOWN HOTEL','R-II BUILDERS','ZURI HOTEL','GO LOUNGE','DECATHLON',
    '1AVIATION','STARBOARD','SGD',
];

function rcvSlug($s) { return strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($s)), '_')); }

// ── Client Ledgers: each client's original sheet has a different set of  ──
// ── columns (some skip SOA Number entirely, others add Check #, Check    ──
// ── Amount, SOA Sent date, Less 11.18%, Tax, or Rebate). $LEDGER_COL_META ──
// ── describes every possible column; $CLIENT_LEDGER_COLS picks which of  ──
// ── them — and in what order — each client actually shows.               ──
$LEDGER_COL_META = [
    'billing_date'       => ['label' => 'BILLING DATE',      'type' => 'date',   'w' => 120],
    'soa_number'         => ['label' => 'SOA NUMBER',        'type' => 'text',   'w' => 110],
    'soa_sent_date'      => ['label' => 'SOA SENT ON',       'type' => 'date',   'w' => 120],
    'amount'             => ['label' => 'AMOUNT',            'type' => 'number', 'w' => 110],
    'check_no'           => ['label' => 'CHECK #',           'type' => 'text',   'w' => 110],
    'check_amount'       => ['label' => 'CHECK AMOUNT',      'type' => 'number', 'w' => 120],
    'payment_date'       => ['label' => 'PAYMENT DATE',      'type' => 'date',   'w' => 120],
    'taxes_withheld'     => ['label' => 'TAXES WITHHELD',    'type' => 'number', 'w' => 120],
    'less_1118'          => ['label' => 'LESS 11.18%',       'type' => 'number', 'w' => 110],
    'tax'                => ['label' => 'TAX',               'type' => 'number', 'w' => 100],
    'paid_amount'        => ['label' => 'PAID',              'type' => 'number', 'w' => 110],
    'remaining_balance'  => ['label' => 'REMAINING BALANCE', 'type' => 'number', 'w' => 130],
    'aging'              => ['label' => 'AGING',             'type' => 'text',   'w' => 110],
    'surcharge'          => ['label' => 'SURCHARGE',         'type' => 'number', 'w' => 110],
    'rebate'             => ['label' => 'REBATE',            'type' => 'number', 'w' => 110, 'title' => 'Amount still short / kulang after payment — no REBATE column means fully paid'],
    'remarks'            => ['label' => 'REMARKS',           'type' => 'text',   'w' => 200],
];

$LEDGER_COLS_DEFAULT = ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','remarks'];
$LEDGER_COLS_NO_SOA  = ['billing_date','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','remarks'];

$CLIENT_LEDGER_COLS = [
    'CARITAS'             => $LEDGER_COLS_NO_SOA,
    'COCOLIFE'            => ['billing_date','soa_number','amount','check_no','payment_date','paid_amount','tax','remaining_balance','aging','surcharge','rebate','remarks'],
    'INTELLICARE'         => ['billing_date','soa_number','amount','check_no','check_amount','payment_date','less_1118','paid_amount','remaining_balance','aging','surcharge','tax','rebate','remarks'],
    'AVEGA'               => ['billing_date','soa_number','soa_sent_date','amount','check_no','check_amount','payment_date','taxes_withheld','less_1118','tax','paid_amount','remaining_balance','aging','surcharge','rebate','remarks'],
    'MED ASIA'            => ['billing_date','soa_number','amount','check_no','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','rebate','remarks'],
    'VALUCARE'            => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','rebate','remarks'],
    'XTENDOPS'            => $LEDGER_COLS_DEFAULT,
    'ICARE/IN LIFE'       => ['billing_date','soa_number','amount','taxes_withheld','payment_date','paid_amount','remaining_balance','aging','tax','remarks'],
    'IMS'                 => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','check_no','rebate','remarks'],
    'IQOR'                => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','tax','check_no','rebate','remarks'],
    'AMAPHIL'             => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','rebate','remarks'],
    'PHILCARE'            => ['billing_date','soa_number','soa_sent_date','amount','payment_date','check_no','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','rebate','remarks'],
    'PACIFIC CROSS'       => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','check_no','rebate','remarks'],
    'MAXICARE'            => ['billing_date','soa_number','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','check_no','rebate','remarks'],
    'NEARSOL'             => $LEDGER_COLS_DEFAULT,
    'JLS AIRCONDITIONING' => $LEDGER_COLS_DEFAULT,
    'MULTIPLIERS'         => $LEDGER_COLS_DEFAULT,
    'LIFE AND HEALTH'     => ['billing_date','amount','soa_number','soa_sent_date','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','rebate','remarks'],
    'ANDOKS'              => $LEDGER_COLS_DEFAULT,
    'ESSENTIALE'          => $LEDGER_COLS_DEFAULT,
    'JPA AGENCY'          => ['billing_date','amount','soa_number','payment_date','paid_amount','remaining_balance','aging','surcharge','remarks'],
    'ASIAPRO'             => ['billing_date','amount','soa_number','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','remarks'],
    'F. GURREA'           => ['billing_date','amount','soa_number','payment_date','taxes_withheld','paid_amount','remaining_balance','surcharge','remarks'],
    'FREMONT'             => ['billing_date','amount','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','check_no','remarks'],
    'FIRMC'               => ['billing_date','amount','soa_number','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','remarks'],
    'SONYU'               => ['billing_date','amount','soa_number','payment_date','taxes_withheld','check_no','paid_amount','remaining_balance','aging','surcharge','remarks'],
    'JF HORIZON'          => ['billing_date','amount','soa_number','payment_date','taxes_withheld','paid_amount','remaining_balance','aging','surcharge','remarks'],
    'VERMILLION'          => $LEDGER_COLS_DEFAULT,
    'APS TRAKING CARGO'   => $LEDGER_COLS_NO_SOA,
    'SUREFIRE'            => $LEDGER_COLS_NO_SOA,
    'WEST TOWN HOTEL'     => $LEDGER_COLS_NO_SOA,
    'R-II BUILDERS'       => $LEDGER_COLS_NO_SOA,
    'ZURI HOTEL'          => $LEDGER_COLS_NO_SOA,
    'GO LOUNGE'           => $LEDGER_COLS_NO_SOA,
    'DECATHLON'           => $LEDGER_COLS_NO_SOA,
    '1AVIATION'           => $LEDGER_COLS_NO_SOA,
    'STARBOARD'           => $LEDGER_COLS_NO_SOA,
    'SGD'                 => $LEDGER_COLS_NO_SOA,
];

function ledgerColsFor(string $name, array $map, array $default): array {
    return $map[$name] ?? $default;
}

function renderLedgerCell(string $key, array $meta, $value, string $slug): string {
    $slugJs = addslashes($slug);
    if ($meta['type'] === 'date') {
        return '<td><input type="date" class="pvi" style="text-align:center" data-col="'.$key.'" value="'.htmlspecialchars($value ?? '').'" oninput="rowChanged(this,\'led_'.$slugJs.'\')"></td>';
    }
    if ($meta['type'] === 'number') {
        return '<td><input type="number" step="0.01" class="pvi" data-col="'.$key.'" value="'.nz($value).'" oninput="rowChanged(this,\'led_'.$slugJs.'\')"></td>';
    }
    return '<td><input class="pvi txt" data-col="'.$key.'" value="'.htmlspecialchars($value ?? '').'" oninput="rowChanged(this,\'led_'.$slugJs.'\')"></td>';
}

// Load rows for a table, grouped by $groupField, seeded with $baseList so
// every known section renders even with zero rows, plus any custom names
// already saved under that field.
function loadGroupedSections(PDO $pdo, string $table, string $groupField, array $baseList): array {
    if ($baseList) {
        $placeholders = implode(',', array_fill(0, count($baseList), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT `$groupField` FROM `$table` WHERE `$groupField` NOT IN ($placeholders) AND `$groupField` <> '' ORDER BY `$groupField` ASC");
        $stmt->execute($baseList);
    } else {
        $stmt = $pdo->query("SELECT DISTINCT `$groupField` FROM `$table` WHERE `$groupField` <> '' ORDER BY `$groupField` ASC");
    }
    $custom   = array_column($stmt->fetchAll(), $groupField);
    $allNames = array_merge($baseList, $custom);

    $allRows = $pdo->query("SELECT * FROM `$table` ORDER BY id ASC")->fetchAll();
    $byName  = array_fill_keys($allNames, []);
    foreach ($allRows as $r) {
        if (!isset($byName[$r[$groupField]])) $byName[$r[$groupField]] = [];
        $byName[$r[$groupField]][] = $r;
    }
    return [$allNames, $byName];
}

[$MON_NAMES, $monByName]    = loadGroupedSections($pdo, 'jaro_receivables_monitoring', 'hmo_name', $KNOWN_CLIENTS);
[$LEDGER_NAMES, $ledByName] = loadGroupedSections($pdo, 'jaro_receivables_ledger', 'client_name', $KNOWN_CLIENTS);

// ── SUMMARY (read-only): one row per Client Ledgers client, auto-totaled ──
// AMOUNT / EWT TAX / PAYMENT / SURCHARGE = sum of that client's ledger rows.
// RECEIVABLES (AFTER EW)  = AMOUNT − EWT TAX
// BALANCE                 = sum of each ledger row's Remaining Balance + Receivables (after EW)
$SUMMARY = [];
foreach ($LEDGER_NAMES as $name) {
    $amt = $ewt = $paid = $remBal = $sur = 0.0;
    foreach ($ledByName[$name] as $r) {
        $amt    += (float)$r['amount'];
        $ewt    += (float)$r['taxes_withheld'];
        $paid   += (float)$r['paid_amount'];
        $remBal += (float)$r['remaining_balance'];
        $sur    += (float)$r['surcharge'];
    }
    $afterEW = $amt - $ewt;
    $SUMMARY[$name] = [
        'amount'               => $amt,
        'ewt_tax'              => $ewt,
        'jaro_receivables_after_ew' => $afterEW,
        'surcharge'            => $sur,
        'payment'              => $paid,
        'balance'              => $remBal + $afterEW,
    ];
}
// Manually-kept remarks per client (the only editable field on this tab)
$summaryRemarks = array_column($pdo->query("SELECT hmo_name, remarks FROM jaro_receivables_summary")->fetchAll(), 'remarks', 'hmo_name');

// Totals per HMO/Corporate for the Monitoring tab's client boxes
$MON_TOTALS = [];
foreach ($MON_NAMES as $name) {
    $MON_TOTALS[$name] = array_sum(array_column($monByName[$name], 'remaining_balance'));
}

$pastdueRows = $pdo->query("SELECT * FROM jaro_receivables_pastdue ORDER BY due_date ASC, id ASC")->fetchAll();
$othersRows  = $pdo->query("SELECT * FROM jaro_receivables_others ORDER BY billing_date ASC, id ASC")->fetchAll();
$soaRows     = $pdo->query("SELECT * FROM jaro_receivables_soatracker ORDER BY id ASC")->fetchAll();

$pageTitle  = 'Receivables';
$activePage = 'demic_jaro_receivables';
include '../layout.php';

function nz($v) { $v=(float)$v; return $v!=0 ? number_format($v,2,'.','') : ''; }
function nzs($v) { return $v !== null && $v !== '' ? $v : ''; }
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

/* Tabs */
.pv-tabs { display:flex; gap:6px; margin-bottom:18px; border-bottom:2px solid var(--border); flex-wrap:wrap; }
.pv-tab {
  padding:9px 18px; font-family:var(--font-h); font-size:.82rem; font-weight:600;
  color:var(--subtext); background:none; border:none; cursor:pointer;
  border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s;
}
.pv-tab:hover { color:var(--accent); }
.pv-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.pv-pane { display:none; }
.pv-pane.active { display:block; }

.pv-outer {
  width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border);
  background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:14px;
}
.pv-table { border-collapse:collapse; width:max-content; min-width:100%; font-size:.72rem; }
.pv-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.56rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:9px 8px; border:1px solid #2d5480;
  white-space:nowrap; text-align:center;
}
.pv-table td { border:1px solid #e3e6ea; padding:0; vertical-align:middle; }
.pv-table tbody tr:hover td { filter:brightness(.97); }
.pv-table tfoot td {
  padding:8px 8px; font-family:var(--font-m); font-size:.7rem; font-weight:800;
  text-align:right; border:1px solid #d1d5db; background:#1e3a5f; color:#fff;
}
.pv-table tfoot td.tfl { text-align:center; text-transform:uppercase; letter-spacing:.07em; font-size:.6rem; }

.pvi {
  width:100%; padding:6px 6px; background:transparent; border:none; outline:none;
  color:#1a1d23; font-family:var(--font-m); font-size:.7rem; text-align:right; display:block; box-sizing:border-box;
}
.pvi.txt  { text-align:left; font-family:var(--font-h); }
.pvi:focus { background:rgba(15,123,92,.07); outline:1px solid rgba(15,123,92,.4); }
select.pvi { text-align:left; -webkit-appearance:none; appearance:none; background:#fff; }

/* Read-only computed cells (Summary tab) */
.pv-ro {
  padding:6px 6px; font-family:var(--font-m); font-size:.7rem; text-align:right;
  color:#1a1d23; background:#fafbfc; box-sizing:border-box;
}
.pv-ro.txt { text-align:left; font-family:var(--font-h); font-weight:600; background:#fff; }

.td-act { text-align:center; padding:4px 6px; white-space:nowrap; }
.btn-add-row {
  display:flex; align-items:center; gap:7px; padding:8px 16px; background:var(--accent); color:#fff;
  border:none; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; font-family:var(--font-h);
  transition:background .15s, transform .1s;
}
.btn-add-row:hover { background:#0a6649; transform:translateY(-1px); }
.bsv {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:5px; cursor:pointer;
  white-space:nowrap; display:inline-block; margin-right:3px;
}
.bsv:hover { background:#dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok { background:#dcfce7; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.bdel {
  padding:3px 8px; font-size:.6rem; font-family:var(--font-m); font-weight:700;
  background:#fff1f2; color:#be123c; border:1px solid #fecdd3; border-radius:5px; cursor:pointer;
}
.bdel:hover { background:#ffe4e6; }

.pv-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }

.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }

/* SOA Num Tracker */
.soa-row.soa-used td { background:#fff9c4 !important; }
.soa-toggle-btn {
  padding:4px 10px; font-size:.62rem; font-family:var(--font-m); font-weight:700;
  background:#f4f5f7; color:#4b5563; border:1px solid #d1d5db; border-radius:5px; cursor:pointer;
  white-space:nowrap;
}
.soa-toggle-btn:hover { background:#e5e7eb; }
.soa-row.soa-used .soa-toggle-btn { background:#fde047; color:#713f12; border-color:#eab308; }
.soa-row.soa-used .soa-toggle-btn:hover { background:#facc15; }

.pv-pdc-section { margin-bottom:24px; }
.pv-pdc-title {
  font-family:var(--font-h); font-size:.85rem; font-weight:700; color:#1a1d23;
  margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid var(--border);
  text-transform:uppercase; letter-spacing:.04em;
}

/* Client filter boxes + search */
.pv-filter-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
.pv-search {
  flex:1; min-width:200px; max-width:340px; padding:8px 12px; border:1px solid var(--border);
  border-radius:8px; font-family:var(--font-m); font-size:.75rem; outline:none;
}
.pv-search:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(15,123,92,.12); }
.pv-chips { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; margin-bottom:18px; }
.pv-chip {
  background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:10px 12px;
  cursor:pointer; transition:all .15s; box-shadow:0 1px 3px rgba(0,0,0,.05);
}
.pv-chip:hover { border-color:var(--accent); transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.08); }
.pv-chip.active { border-color:var(--accent); background:rgba(15,123,92,.06); }
.pv-chip-name {
  font-family:var(--font-h); font-size:.7rem; font-weight:700; color:#1a1d23; text-transform:uppercase;
  letter-spacing:.03em; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.pv-chip-total { font-family:var(--font-m); font-size:.85rem; font-weight:800; color:var(--accent); }
.pv-chip-empty { grid-column:1/-1; text-align:center; padding:14px; font-family:var(--font-m); font-size:.72rem; color:var(--subtext2); }
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Receivables <span style="color:var(--accent)">Module</span></div>
    <div class="section-subtitle">Summary · Monitoring · Past Due · Client Ledgers · Others · SOA Num Tracker — mirrors RECEIVABLES.xlsx</div>
  </div>
</div>

<!-- Tabs -->
<div class="pv-tabs">
  <button class="pv-tab active" data-tab="summary" onclick="switchTab('summary')">📋 Summary</button>
  <button class="pv-tab" data-tab="monitoring" onclick="switchTab('monitoring')">📊 Monitoring</button>
  <button class="pv-tab" data-tab="pastdue" onclick="switchTab('pastdue')">⏰ Past Due</button>
  <button class="pv-tab" data-tab="ledger" onclick="switchTab('ledger')">📁 Client Ledgers</button>
  <button class="pv-tab" data-tab="others" onclick="switchTab('others')">📎 Others</button>
  <button class="pv-tab" data-tab="soatracker" onclick="switchTab('soatracker')">🔢 SOA Num Tracker</button>
</div>

<!-- ══════════════ TAB 1: SUMMARY (read-only, auto-totaled from Client Ledgers) ══════════════ -->
<div class="pv-pane active" id="pane-summary">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Read-only — auto-calculated from the Client Ledgers tab. Only Remarks can be edited here.</div>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:200px">HMO / CORPORATE</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:120px">EWT TAX</th>
          <th style="min-width:150px">RECEIVABLES (AFTER EW)</th>
          <th style="min-width:110px">SURCHARGE</th>
          <th style="min-width:120px">PAYMENT</th>
          <th style="min-width:120px">BALANCE</th>
          <th style="min-width:200px">REMARKS</th>
        </tr>
      </thead>
      <tbody id="tbody-summary">
        <?php foreach ($LEDGER_NAMES as $name): $s = $SUMMARY[$name]; ?>
        <tr>
          <td class="pv-ro txt"><?=htmlspecialchars($name)?></td>
          <td class="pv-ro"><?=nz($s['amount'])?></td>
          <td class="pv-ro"><?=nz($s['ewt_tax'])?></td>
          <td class="pv-ro"><?=nz($s['jaro_receivables_after_ew'])?></td>
          <td class="pv-ro"><?=nz($s['surcharge'])?></td>
          <td class="pv-ro"><?=nz($s['payment'])?></td>
          <td class="pv-ro"><?=nz($s['balance'])?></td>
          <td><input class="pvi txt" data-hmo="<?=htmlspecialchars($name)?>" value="<?=htmlspecialchars($summaryRemarks[$name] ?? '')?>" onchange="saveSummaryRemarks(this)"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php
          $sumAmt = array_sum(array_column($SUMMARY, 'amount'));
          $sumEwt = array_sum(array_column($SUMMARY, 'ewt_tax'));
          $sumAew = array_sum(array_column($SUMMARY, 'jaro_receivables_after_ew'));
          $sumSur = array_sum(array_column($SUMMARY, 'surcharge'));
          $sumPay = array_sum(array_column($SUMMARY, 'payment'));
          $sumBal = array_sum(array_column($SUMMARY, 'balance'));
        ?>
        <tr>
          <td class="tfl">TOTAL</td>
          <td><?=nz($sumAmt) ?: '—'?></td>
          <td><?=nz($sumEwt) ?: '—'?></td>
          <td><?=nz($sumAew) ?: '—'?></td>
          <td><?=nz($sumSur) ?: '—'?></td>
          <td><?=nz($sumPay) ?: '—'?></td>
          <td><?=nz($sumBal) ?: '—'?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 2: MONITORING (stacked per HMO/Corporate) ══════════════ -->
<div class="pv-pane" id="pane-monitoring">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Amounts still due, tracked per HMO / corporate account</div>
    <button type="button" class="btn-add-row" onclick="promptNewSection('monitoring')">+ New HMO / Corporate</button>
  </div>

  <div class="pv-filter-bar">
    <input type="text" id="mon-search" class="pv-search" placeholder="🔎 Search HMO / Corporate…" oninput="filterSections('monitoring-sections', this.value)">
    <button type="button" class="btn-add-row sm" onclick="clearSectionFilter('monitoring-sections','mon-search')">Show All</button>
  </div>
  <div class="pv-chips">
    <?php foreach ($MON_NAMES as $name): ?>
    <div class="pv-chip" data-scope="monitoring-sections" data-name="<?=htmlspecialchars($name, ENT_QUOTES)?>" onclick="chipClick('monitoring-sections','mon-search','<?=htmlspecialchars($name, ENT_QUOTES)?>')">
      <div class="pv-chip-name"><?=htmlspecialchars($name)?></div>
      <div class="pv-chip-total"><?=nz($MON_TOTALS[$name]) ?: '0.00'?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$MON_NAMES): ?><div class="pv-chip-empty">No HMOs / corporates yet.</div><?php endif; ?>
  </div>

  <div id="monitoring-sections">
    <div class="pv-chip-empty" id="monitoring-sections-empty" style="display:none">No client matches that search.</div>
    <?php foreach ($MON_NAMES as $name): $slug = rcvSlug($name); $rows = $monByName[$name]; ?>
    <div class="pv-pdc-section" id="monsec-<?=$slug?>" data-section-name="<?=htmlspecialchars($name, ENT_QUOTES)?>">
      <div class="pv-pdc-title"><?=htmlspecialchars($name)?></div>
      <div class="pv-controls">
        <button type="button" class="btn-add-row" onclick="addRow('mon_<?=$slug?>')">+ Add Row</button>
      </div>
      <div class="pv-outer">
        <table class="pv-table">
          <thead>
            <tr>
              <th style="min-width:120px">DATE</th>
              <th style="min-width:120px">AMOUNT</th>
              <th style="min-width:140px">REMAINING BALANCE</th>
              <th style="min-width:220px">REMARKS</th>
              <th style="min-width:90px">ACTION</th>
            </tr>
          </thead>
          <tbody id="tbody-mon-<?=$slug?>">
            <?php foreach ($rows as $r): ?>
            <tr data-id="<?=$r['id']?>">
              <td><input type="date" class="pvi" style="text-align:center" data-col="entry_date" value="<?=htmlspecialchars($r['entry_date'])?>" oninput="rowChanged(this,'mon_<?=$slug?>')"></td>
              <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'mon_<?=$slug?>')"></td>
              <td><input type="number" step="0.01" class="pvi" data-col="remaining_balance" value="<?=nz($r['remaining_balance'])?>" oninput="rowChanged(this,'mon_<?=$slug?>')"></td>
              <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'mon_<?=$slug?>')"></td>
              <td class="td-act"><button class="bsv" onclick="saveRow(this,'mon_<?=$slug?>')">Update</button><button class="bdel" onclick="deleteRow(this,'mon_<?=$slug?>')">Del</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td class="tfl">TOTAL</td><td id="tot-mon_<?=$slug?>-amount">—</td><td id="tot-mon_<?=$slug?>-remaining_balance">—</td><td></td><td></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════ TAB 3: PAST DUE ══════════════ -->
<div class="pv-pane" id="pane-pastdue">
  <div class="pv-controls">
    <button type="button" class="btn-add-row" onclick="addRow('pastdue')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:180px">HMO / CORPORATE</th>
          <th style="min-width:120px">DATE</th>
          <th style="min-width:120px">AMOUNT</th>
          <th style="min-width:120px">DUE DATE</th>
          <th style="min-width:110px">STATUS</th>
          <th style="min-width:220px">REMARKS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-pastdue">
        <?php foreach ($pastdueRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input class="pvi txt" data-col="hmo_name" value="<?=htmlspecialchars($r['hmo_name'])?>" oninput="rowChanged(this,'pastdue')"></td>
          <td><input type="date" class="pvi" style="text-align:center" data-col="entry_date" value="<?=htmlspecialchars($r['entry_date'])?>" oninput="rowChanged(this,'pastdue')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'pastdue')"></td>
          <td><input type="date" class="pvi" style="text-align:center" data-col="due_date" value="<?=htmlspecialchars($r['due_date'])?>" oninput="rowChanged(this,'pastdue')"></td>
          <td>
            <select class="pvi" data-col="status" onchange="rowChanged(this,'pastdue')">
              <option value="PAID"     <?=$r['status']==='PAID'?'selected':''?>>Paid</option>
              <option value="NOT PAID" <?=$r['status']==='NOT PAID'?'selected':''?>>Not Paid</option>
            </select>
          </td>
          <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'pastdue')"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'pastdue')">Update</button><button class="bdel" onclick="deleteRow(this,'pastdue')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="tfl">TOTAL</td><td></td><td id="tot-pastdue-amount">—</td><td></td><td></td><td></td><td></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 4: CLIENT LEDGERS (stacked per client) ══════════════ -->
<div class="pv-pane" id="pane-ledger">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Each client keeps its own billing history below</div>
    <button type="button" class="btn-add-row" onclick="promptNewSection('ledger')">+ New Client</button>
  </div>
  <div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext2);margin:-4px 0 14px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;line-height:1.5">
    💡 <strong>REBATE</strong> = the amount still short / <em>kulang</em> after payment. If a client's table below has no REBATE column, that client's billings are considered fully paid — a REBATE column only appears where a shortfall has historically needed tracking.
  </div>

  <div class="pv-filter-bar">
    <input type="text" id="led-search" class="pv-search" placeholder="🔎 Search client…" oninput="filterSections('ledger-sections', this.value)">
    <button type="button" class="btn-add-row sm" onclick="clearSectionFilter('ledger-sections','led-search')">Show All</button>
  </div>
  <div class="pv-chips">
    <?php foreach ($LEDGER_NAMES as $name): $s = $SUMMARY[$name]; ?>
    <div class="pv-chip" data-scope="ledger-sections" data-name="<?=htmlspecialchars($name, ENT_QUOTES)?>" onclick="chipClick('ledger-sections','led-search','<?=htmlspecialchars($name, ENT_QUOTES)?>')">
      <div class="pv-chip-name"><?=htmlspecialchars($name)?></div>
      <div class="pv-chip-total"><?=nz($s['balance']) ?: '0.00'?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$LEDGER_NAMES): ?><div class="pv-chip-empty">No clients yet.</div><?php endif; ?>
  </div>

  <div id="ledger-sections">
    <div class="pv-chip-empty" id="ledger-sections-empty" style="display:none">No client matches that search.</div>
    <?php foreach ($LEDGER_NAMES as $name): $slug = rcvSlug($name); $rows = $ledByName[$name]; $cols = ledgerColsFor($name, $CLIENT_LEDGER_COLS, $LEDGER_COLS_DEFAULT); ?>
    <div class="pv-pdc-section" id="ledsec-<?=$slug?>" data-section-name="<?=htmlspecialchars($name, ENT_QUOTES)?>">
      <div class="pv-pdc-title"><?=htmlspecialchars($name)?></div>
      <div class="pv-controls">
        <button type="button" class="btn-add-row" onclick="addRow('led_<?=$slug?>')">+ Add Row</button>
      </div>
      <div class="pv-outer">
        <table class="pv-table">
          <thead>
            <tr>
              <?php foreach ($cols as $key): $meta = $LEDGER_COL_META[$key]; ?>
              <th style="min-width:<?=$meta['w']?>px"<?= isset($meta['title']) ? ' title="'.htmlspecialchars($meta['title']).'"' : '' ?>><?=$meta['label']?></th>
              <?php endforeach; ?>
              <th style="min-width:90px">ACTION</th>
            </tr>
          </thead>
          <tbody id="tbody-led-<?=$slug?>">
            <?php foreach ($rows as $r): ?>
            <tr data-id="<?=$r['id']?>">
              <?php foreach ($cols as $key): $meta = $LEDGER_COL_META[$key]; echo renderLedgerCell($key, $meta, $r[$key] ?? null, $slug); endforeach; ?>
              <td class="td-act"><button class="bsv" onclick="saveRow(this,'led_<?=$slug?>')">Update</button><button class="bdel" onclick="deleteRow(this,'led_<?=$slug?>')">Del</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <?php foreach ($cols as $i => $key): $meta = $LEDGER_COL_META[$key]; ?>
                <?php if ($i === 0): ?>
                <td class="tfl">TOTAL</td>
                <?php elseif ($meta['type'] === 'number'): ?>
                <td id="tot-led_<?=$slug?>-<?=$key?>">—</td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
              <?php endforeach; ?>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════ TAB 5: OTHERS (misc, flat list) ══════════════ -->
<div class="pv-pane" id="pane-others">
  <div class="pv-controls">
    <button type="button" class="btn-add-row" onclick="addRow('others')">+ Add Row</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:170px">NAME</th>
          <th style="min-width:120px">BILLING DATE</th>
          <th style="min-width:110px">AMOUNT</th>
          <th style="min-width:120px">PAYMENT DATE</th>
          <th style="min-width:110px">TAXES WITHHELD</th>
          <th style="min-width:110px">PAID</th>
          <th style="min-width:130px">REMAINING BALANCE</th>
          <th style="min-width:110px">AGING</th>
          <th style="min-width:110px">SURCHARGE</th>
          <th style="min-width:200px">REMARKS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-others">
        <?php foreach ($othersRows as $r): ?>
        <tr data-id="<?=$r['id']?>">
          <td><input class="pvi txt" data-col="name" value="<?=htmlspecialchars($r['name'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="date" class="pvi" style="text-align:center" data-col="billing_date" value="<?=htmlspecialchars($r['billing_date'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="amount" value="<?=nz($r['amount'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="date" class="pvi" style="text-align:center" data-col="payment_date" value="<?=htmlspecialchars($r['payment_date'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="taxes_withheld" value="<?=nz($r['taxes_withheld'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="paid_amount" value="<?=nz($r['paid_amount'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="remaining_balance" value="<?=nz($r['remaining_balance'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input class="pvi txt" data-col="aging" value="<?=htmlspecialchars($r['aging'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input type="number" step="0.01" class="pvi" data-col="surcharge" value="<?=nz($r['surcharge'])?>" oninput="rowChanged(this,'others')"></td>
          <td><input class="pvi txt" data-col="remarks" value="<?=htmlspecialchars($r['remarks'])?>" oninput="rowChanged(this,'others')"></td>
          <td class="td-act"><button class="bsv" onclick="saveRow(this,'others')">Update</button><button class="bdel" onclick="deleteRow(this,'others')">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="tfl">TOTAL</td><td></td>
          <td id="tot-others-amount">—</td><td></td>
          <td id="tot-others-taxes_withheld">—</td>
          <td id="tot-others-paid_amount">—</td>
          <td id="tot-others-remaining_balance">—</td>
          <td></td>
          <td id="tot-others-surcharge">—</td>
          <td></td><td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════ TAB 6: SOA NUM TRACKER (running SN-C##### sequence) ══════════════ -->
<div class="pv-pane" id="pane-soatracker">
  <div class="pv-controls">
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);text-transform:uppercase;letter-spacing:.06em">Click MARK USED to flag a SOA number as used (turns yellow)</div>
    <button type="button" class="btn-add-row" onclick="addSoaNumber()">+ Generate Next SOA #</button>
  </div>
  <div class="pv-outer">
    <table class="pv-table">
      <thead>
        <tr>
          <th style="min-width:50px">#</th>
          <th style="min-width:160px">SOA NUMBER</th>
          <th style="min-width:110px">STATUS</th>
          <th style="min-width:90px">ACTION</th>
        </tr>
      </thead>
      <tbody id="tbody-soatracker">
        <?php foreach ($soaRows as $i => $r): ?>
        <tr data-id="<?=$r['id']?>" class="soa-row <?=$r['used'] ? 'soa-used' : ''?>">
          <td style="text-align:center;font-family:var(--font-m);font-size:.68rem;color:var(--subtext2)"><?=$i+1?></td>
          <td class="pv-ro txt"><?=htmlspecialchars($r['soa_number'])?></td>
          <td class="td-act"><button class="soa-toggle-btn" onclick="toggleSoa(this)"><?=$r['used'] ? 'USED' : 'MARK USED'?></button></td>
          <td class="td-act"><button class="bdel" onclick="deleteSoa(this)">Del</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// ── Column configs per section (drives generic add/save row-building) ──
const CONFIGS = {
  pastdue: {
    tbody: 'tbody-pastdue',
    tbl: 'pastdue',
    cols: [
      {col:'hmo_name', type:'text'},
      {col:'entry_date', type:'date'},
      {col:'amount', type:'number'},
      {col:'due_date', type:'date'},
      {col:'status', type:'select', options:[['PAID','Paid'],['NOT PAID','Not Paid']]},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount'],
  },
  others: {
    tbody: 'tbody-others',
    tbl: 'others',
    cols: [
      {col:'name', type:'text'},
      {col:'billing_date', type:'date'},
      {col:'amount', type:'number'},
      {col:'payment_date', type:'date'},
      {col:'taxes_withheld', type:'number'},
      {col:'paid_amount', type:'number'},
      {col:'remaining_balance', type:'number'},
      {col:'aging', type:'text'},
      {col:'surcharge', type:'number'},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount','taxes_withheld','paid_amount','remaining_balance','surcharge'],
  },
  <?php foreach ($MON_NAMES as $name): $slug = rcvSlug($name); ?>
  mon_<?=$slug?>: {
    tbody: 'tbody-mon-<?=$slug?>',
    tbl: 'monitoring',
    fixed: { hmo_name: <?= json_encode($name) ?> },
    cols: [
      {col:'entry_date', type:'date'},
      {col:'amount', type:'number'},
      {col:'remaining_balance', type:'number'},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount','remaining_balance'],
  },
  <?php endforeach; ?>
  <?php foreach ($LEDGER_NAMES as $name): $slug = rcvSlug($name); $cols = ledgerColsFor($name, $CLIENT_LEDGER_COLS, $LEDGER_COLS_DEFAULT); ?>
  led_<?=$slug?>: {
    tbody: 'tbody-led-<?=$slug?>',
    tbl: 'ledger',
    fixed: { client_name: <?= json_encode($name) ?> },
    cols: [
      <?php foreach ($cols as $key): $meta = $LEDGER_COL_META[$key]; ?>
      {col:<?= json_encode($key) ?>, type:<?= json_encode($meta['type']) ?>},
      <?php endforeach; ?>
    ],
    sums: <?= json_encode(array_values(array_filter($cols, fn($k) => $LEDGER_COL_META[$k]['type'] === 'number'))) ?>,
  },
  <?php endforeach; ?>
};

// ── Tab switching ──
function switchTab(name) {
  document.querySelectorAll('.pv-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  document.querySelectorAll('.pv-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
}
<?php if (in_array($_GET['tabkeep'] ?? '', ['summary','monitoring','pastdue','ledger','others','soatracker'])): ?>
switchTab('<?= $_GET['tabkeep'] ?>');
<?php endif; ?>

function recalcFooter(section) {
  const cfg = CONFIGS[section];
  if (!cfg) return;
  cfg.sums.forEach(col => {
    const el = document.getElementById(`tot-${section}-${col}`);
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`#${cfg.tbody} [data-col="${col}"]`).forEach(i => s += parseFloat(i.value) || 0);
    el.textContent = s === 0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  });
}

// ── Client filter boxes + search (Monitoring / Client Ledgers tabs) ──
// Typing in the search box, or clicking a client box, shows only the
// section(s) whose name matches — everything else is hidden. Clearing the
// box (or clicking "Show All") brings every section back.
function filterSections(scopeId, query) {
  const scope = document.getElementById(scopeId);
  if (!scope) return;
  const q = (query || '').trim().toLowerCase();
  let anyShown = false;
  scope.querySelectorAll('[data-section-name]').forEach(sec => {
    const show = !q || sec.dataset.sectionName.toLowerCase().includes(q);
    sec.style.display = show ? '' : 'none';
    if (show) anyShown = true;
  });
  const empty = document.getElementById(scopeId + '-empty');
  if (empty) empty.style.display = anyShown ? 'none' : 'block';
  document.querySelectorAll(`.pv-chip[data-scope="${scopeId}"]`).forEach(ch => {
    ch.classList.toggle('active', !!q && ch.dataset.name.toLowerCase() === q);
  });
}

function chipClick(scopeId, inputId, name) {
  const input = document.getElementById(inputId);
  if (input) input.value = name;
  filterSections(scopeId, name);
  const scope = document.getElementById(scopeId);
  const target = scope && scope.querySelector(`[data-section-name="${CSS.escape(name)}"]`);
  if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearSectionFilter(scopeId, inputId) {
  const input = document.getElementById(inputId);
  if (input) input.value = '';
  filterSections(scopeId, '');
}

function rowChanged(el, section) {
  const row = el.closest('tr');
  recalcFooter(section);
  const btn = row.querySelector('.bsv');
  if (btn) { btn.textContent = row.dataset.id ? 'Update' : 'Save'; btn.className = 'bsv'; }
}

let newRowCounter = 0;
function buildCell(col) {
  if (col.type === 'text') {
    return `<td><input class="pvi txt" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'number') {
    return `<td><input type="number" step="0.01" class="pvi" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'date') {
    return `<td><input type="date" class="pvi" style="text-align:center" data-col="${col.col}" value="" oninput="rowChanged(this,'{{SECTION}}')"></td>`;
  }
  if (col.type === 'select') {
    const opts = col.options.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
    return `<td><select class="pvi" data-col="${col.col}" onchange="rowChanged(this,'{{SECTION}}')">${opts}</select></td>`;
  }
  return '<td></td>';
}

function addRow(section) {
  const cfg = CONFIGS[section];
  const tbody = document.getElementById(cfg.tbody);
  const tr = document.createElement('tr');
  const tempId = 'new_' + (++newRowCounter);
  tr.id = tempId;
  tr.dataset.id = '';
  let h = '';
  cfg.cols.forEach(c => { h += buildCell(c).replaceAll('{{SECTION}}', section); });
  h += `<td class="td-act"><button class="bsv" onclick="saveRow(this,'${section}')">Save</button></td>`;
  tr.innerHTML = h;
  tbody.appendChild(tr);
  tr.querySelector('input,select')?.focus();
  recalcFooter(section);
}

async function saveRow(btn, section) {
  const cfg = CONFIGS[section] || {};
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.textContent = '…'; btn.className = 'bsv saving';

  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  fd.append('tbl', cfg.tbl || section);
  if (id) fd.append('id', id);
  row.querySelectorAll('[data-col]').forEach(el => fd.append(el.dataset.col, el.value || ''));
  if (cfg.fixed) Object.entries(cfg.fixed).forEach(([k, v]) => fd.append(k, v));

  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent = 'Update'; btn.className = 'bsv ok';
      if (!id && data.id) {
        row.dataset.id = data.id;
        const delBtn = document.createElement('button');
        delBtn.className = 'bdel'; delBtn.textContent = 'Del';
        delBtn.onclick = function(){ deleteRow(this, section); };
        btn.parentElement.appendChild(delBtn);
      }
      setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
      showToast('✓ Saved', 'success');
    } else {
      btn.textContent = 'Error'; btn.className = 'bsv err';
      showToast('❌ ' + data.msg, 'error');
      setTimeout(() => { btn.textContent = id ? 'Update' : 'Save'; btn.className = 'bsv'; }, 3000);
    }
  } catch (e) {
    btn.textContent = 'Error'; btn.className = 'bsv err';
    showToast('❌ Network error', 'error');
  }
}

async function deleteRow(btn, section) {
  if (!confirm('Delete this row?')) return;
  const cfg = CONFIGS[section] || {};
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  if (!id) { row.remove(); recalcFooter(section); return; }

  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('tbl', cfg.tbl || section);
  fd.append('id', id);
  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0'; row.style.transition = 'opacity .3s';
      setTimeout(() => { row.remove(); recalcFooter(section); }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

// ── Add a brand-new section on the fly: 'monitoring' (grouped by hmo_name)
// ── or 'ledger' (grouped by client_name) ──
function slugify(s) {
  return s.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

const SECTION_KIND = {
  monitoring: {
    prefix: 'mon', tbl: 'monitoring', field: 'hmo_name', promptLabel: 'Name of the new HMO / Corporate account:',
    container: 'monitoring-sections', wrapPrefix: 'monsec',
    cols: [
      {col:'entry_date', type:'date'},
      {col:'amount', type:'number'},
      {col:'remaining_balance', type:'number'},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount','remaining_balance'],
    headers: ['DATE','AMOUNT','REMAINING BALANCE','REMARKS'],
    totalCols: ['amount','remaining_balance'],
  },
  ledger: {
    prefix: 'led', tbl: 'ledger', field: 'client_name', promptLabel: 'Name of the new client:',
    container: 'ledger-sections', wrapPrefix: 'ledsec',
    cols: [
      {col:'billing_date', type:'date'},
      {col:'soa_number', type:'text'},
      {col:'amount', type:'number'},
      {col:'payment_date', type:'date'},
      {col:'taxes_withheld', type:'number'},
      {col:'paid_amount', type:'number'},
      {col:'remaining_balance', type:'number'},
      {col:'aging', type:'text'},
      {col:'surcharge', type:'number'},
      {col:'remarks', type:'text'},
    ],
    sums: ['amount','taxes_withheld','paid_amount','remaining_balance','surcharge'],
    headers: ['BILLING DATE','SOA NUMBER','AMOUNT','PAYMENT DATE','TAXES WITHHELD','PAID','REMAINING BALANCE','AGING','SURCHARGE','REMARKS'],
    totalCols: ['','','amount','','taxes_withheld','paid_amount','remaining_balance','','surcharge',''],
  },
};

function promptNewSection(kind) {
  const spec = SECTION_KIND[kind];
  const name = prompt(spec.promptLabel);
  if (!name || !name.trim()) return;
  const label = name.trim().toUpperCase();
  const slug  = slugify(label);
  const key   = spec.prefix + '_' + slug;

  if (!slug) { alert('Please enter a valid name.'); return; }
  if (CONFIGS[key]) {
    switchTab(kind);
    document.getElementById(spec.wrapPrefix + '-' + slug)?.scrollIntoView({behavior:'smooth', block:'start'});
    return;
  }

  CONFIGS[key] = {
    tbody: 'tbody-' + spec.prefix + '-' + slug,
    tbl: spec.tbl,
    fixed: { [spec.field]: label },
    cols: spec.cols,
    sums: spec.sums,
  };

  const headerHtml = spec.headers.map(h => `<th style="min-width:110px">${h}</th>`).join('') + '<th style="min-width:90px">ACTION</th>';
  const totalHtml  = spec.totalCols.map(c => c ? `<td id="tot-${key}-${c}">—</td>` : '<td></td>').join('') + '<td></td>';

  const wrap = document.createElement('div');
  wrap.className = 'pv-pdc-section';
  wrap.id = spec.wrapPrefix + '-' + slug;
  wrap.innerHTML = `
    <div class="pv-pdc-title">${label}</div>
    <div class="pv-controls">
      <button type="button" class="btn-add-row" onclick="addRow('${key}')">+ Add Row</button>
    </div>
    <div class="pv-outer">
      <table class="pv-table">
        <thead><tr>${headerHtml}</tr></thead>
        <tbody id="tbody-${spec.prefix}-${slug}"></tbody>
        <tfoot><tr><td class="tfl">TOTAL</td>${totalHtml}</tr></tfoot>
      </table>
    </div>`;

  document.getElementById(spec.container).appendChild(wrap);
  switchTab(kind);
  addRow(key);
  wrap.scrollIntoView({behavior:'smooth', block:'start'});
  showToast('✓ New section added — fill in a row and Save to keep it', 'success');
}

// ── SOA Num Tracker ──
async function addSoaNumber() {
  const fd = new FormData();
  fd.append('ajax_soa_add', '1');
  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      const tbody = document.getElementById('tbody-soatracker');
      const tr = document.createElement('tr');
      tr.dataset.id = data.id;
      tr.className = 'soa-row';
      tr.innerHTML = `
        <td style="text-align:center;font-family:var(--font-m);font-size:.68rem;color:var(--subtext2)">${tbody.children.length + 1}</td>
        <td class="pv-ro txt">${data.soa_number}</td>
        <td class="td-act"><button class="soa-toggle-btn" onclick="toggleSoa(this)">MARK USED</button></td>
        <td class="td-act"><button class="bdel" onclick="deleteSoa(this)">Del</button></td>`;
      tbody.appendChild(tr);
      tr.scrollIntoView({behavior:'smooth', block:'nearest'});
      showToast('✓ ' + data.soa_number + ' added', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

async function toggleSoa(btn) {
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  btn.disabled = true;
  const fd = new FormData();
  fd.append('ajax_soa_toggle', '1');
  fd.append('id', id);
  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.classList.toggle('soa-used', data.used == 1);
      btn.textContent = data.used == 1 ? 'USED' : 'MARK USED';
      showToast('✓ Updated', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
  btn.disabled = false;
}

async function deleteSoa(btn) {
  if (!confirm('Delete this SOA number?')) return;
  const row = btn.closest('tr');
  const id  = row.dataset.id;
  const fd = new FormData();
  fd.append('ajax_soa_delete', '1');
  fd.append('id', id);
  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      row.style.opacity = '0'; row.style.transition = 'opacity .3s';
      setTimeout(() => {
        row.remove();
        document.querySelectorAll('#tbody-soatracker tr').forEach((r, idx) => { r.children[0].textContent = idx + 1; });
      }, 300);
      showToast('✓ Deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

async function saveSummaryRemarks(el) {
  const hmo = el.dataset.hmo;
  const fd = new FormData();
  fd.append('ajax_update_summary_remarks', '1');
  fd.append('hmo_name', hmo);
  fd.append('remarks', el.value || '');
  try {
    const res  = await fetch('demic_jaro_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { showToast('✓ Remarks saved', 'success'); }
    else { showToast('❌ ' + data.msg, 'error'); }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  Object.keys(CONFIGS).forEach(recalcFooter);
});
</script>