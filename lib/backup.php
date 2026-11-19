<?php
// ============================================================
//  lib/backup.php — full-database backup + reminder helpers
//  Pure PHP/PDO (no mysqldump / exec needed, works on Hostinger).
// ============================================================

function backupEnsureTables(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
        `k` varchar(60) NOT NULL, `v` text DEFAULT NULL, PRIMARY KEY (`k`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `backup_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `created_at` datetime NOT NULL,
        `actor_name` varchar(100) NOT NULL,
        `actor_role` varchar(20) NOT NULL,
        `filename` varchar(150) NOT NULL,
        `tables_count` int(11) NOT NULL DEFAULT 0,
        `rows_count` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function backupCanAccess(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['manager', 'accounting'], true);
}

function backupGetSetting(PDO $pdo, string $k, $default = null) {
    $s = $pdo->prepare("SELECT v FROM app_settings WHERE k = ?");
    $s->execute([$k]);
    $v = $s->fetchColumn();
    return $v === false ? $default : $v;
}

function backupSetSetting(PDO $pdo, string $k, string $v): void {
    $pdo->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([$k, $v]);
}

/** Reminder state: ['due'=>bool,'never'=>bool,'last'=>?string,'days'=>int,'interval'=>int] */
function backupStatus(PDO $pdo): array {
    backupEnsureTables($pdo);
    $interval = (int)backupGetSetting($pdo, 'backup_interval_days', 7);
    if ($interval < 1) $interval = 7;
    $last = $pdo->query("SELECT created_at FROM backup_log ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
    $days = $last ? (int)floor((time() - strtotime($last)) / 86400) : null;
    return [
        'interval' => $interval,
        'last'     => $last,
        'days'     => $days,
        'never'    => $last === null,
        'due'      => $last === null || $days >= $interval,
    ];
}

/** Stream a complete .sql dump of every table/view. Returns [tables, rows]. */
function backupStreamDump(PDO $pdo, callable $out): array {
    @set_time_limit(0);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $tables = []; $views = [];
    foreach ($pdo->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM) as $r) {
        if (strtoupper($r[1]) === 'VIEW') $views[] = $r[0]; else $tables[] = $r[0];
    }
    $out("-- SalesHub full backup\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n\n");
    $out("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    $totalRows = 0;
    foreach ($tables as $t) {
        $q = '`' . str_replace('`', '``', $t) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
        $out("-- ----------------------------\n-- Table: $t\n-- ----------------------------\nDROP TABLE IF EXISTS $q;\n$create;\n\n");
        $st = $pdo->query("SELECT * FROM $q");
        $cols = null; $batch = []; $n = 0;
        $flush = function () use (&$batch, &$cols, $q, $out) {
            if (!$batch) return;
            $out("INSERT INTO $q ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        };
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = implode(',', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row)));
            }
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) { $vals[] = 'NULL'; }
                elseif (is_int($v) || is_float($v)) { $vals[] = (string)$v; }
                elseif (preg_match('//u', $v)) { $vals[] = $pdo->quote($v); }
                else { $vals[] = '0x' . bin2hex($v); }
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            $n++;
            if (count($batch) >= 200) $flush();
        }
        $flush();
        $st->closeCursor();
        $totalRows += $n;
        $out("\n");
    }
    foreach ($views as $v) {
        $q = '`' . str_replace('`', '``', $v) . '`';
        $create = $pdo->query("SHOW CREATE VIEW $q")->fetch(PDO::FETCH_NUM)[1];
        $out("DROP VIEW IF EXISTS $q;\n$create;\n\n");
    }
    $out("SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n-- End of backup\n");
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    return [count($tables), $totalRows];
}