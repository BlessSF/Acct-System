<?php
// ============================================================
//  lib/audit_log.php — System-wide Tracking & Monitoring log
// ============================================================
//  One shared table (`audit_log`) records every save / update /
//  delete / lock action performed anywhere in SalesHub, so the
//  Manager account can see, on the Tracking & Monitoring page:
//    - WHEN it happened (date + time)
//    - WHICH branch it happened in
//    - WHO did it (Manager / branch OIC-Accounting / branch Cashier)
//    - WHAT they did (created / updated / deleted / locked / unlocked)
//    - WHERE in the system (module, e.g. "Dois — Sales Report")
//    - a short human-readable summary of the change
//
//  Usage from any branch/module file:
//      require_once __DIR__ . '/../lib/audit_log.php';
//      logAudit($pdo, 'Dois', 'Sales Report', 'updated', $reportDate, 'Saved daily summary totals');
//
//  Call this AFTER the change has actually succeeded (i.e. inside the
//  try{} block, right before the success `echo json_encode([...])`).
// ============================================================

function ensureAuditLogTable(PDO $pdo): void {
    static $done = false;
    if ($done) return; // one CREATE TABLE check per request is enough
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
        `id`             int(11) NOT NULL AUTO_INCREMENT,
        `branch`         varchar(50)  NOT NULL,
        `module`         varchar(100) NOT NULL,
        `action`         varchar(20)  NOT NULL,
        `record_date`    date DEFAULT NULL,
        `summary`        varchar(255) DEFAULT NULL,
        `details`        text DEFAULT NULL,
        `actor_name`     varchar(100) NOT NULL,
        `actor_role`     varchar(20)  NOT NULL,
        `actor_subrole`  varchar(20)  DEFAULT NULL,
        `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_branch_date`  (`branch`, `record_date`),
        KEY `idx_created_at`   (`created_at`),
        KEY `idx_module`       (`module`),
        KEY `idx_action`       (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $done = true;
}

// Human-readable label for "who did it", e.g. "Dois — OIC/Accounting",
// "Dois — Cashier", or "Manager". Used both when writing a log row and
// for building the "Who" filter dropdown on the monitoring page.
function auditActorLabel(array $user): string {
    if (($user['role'] ?? '') === 'branch') {
        $sub = $user['subrole'] ?? null;
        $subLabel = $sub === 'cashier' ? 'Cashier' : ($sub === 'oic' ? 'OIC/Accounting' : 'Branch');
        return ($user['name'] ?? 'Branch') . ' — ' . $subLabel;
    }
    return $user['name'] ?? ucfirst($user['role'] ?? 'User');
}

/**
 * Record one entry in the audit log.
 *
 * @param PDO         $pdo
 * @param string      $branch      Branch/store label, e.g. 'Dois', 'Stella', or 'All' for non-branch modules.
 * @param string      $module      Module/page label, e.g. 'Sales Report', 'Expenses'.
 * @param string      $action      One of: created | updated | deleted | locked | unlocked
 * @param string|null $recordDate  The business/report date the change applies to (Y-m-d), if any.
 * @param string      $summary     Short human-readable description, e.g. 'Saved daily summary totals'.
 * @param mixed       $details     Optional extra context (array/string) — stored as JSON/text.
 */
function logAudit(PDO $pdo, string $branch, string $module, string $action, ?string $recordDate, string $summary, $details = null): void {
    try {
        ensureAuditLogTable($pdo);
        $user = function_exists('currentUser') ? currentUser() : ['name' => 'Unknown', 'role' => 'unknown', 'subrole' => null];
        $detailsText = null;
        if ($details !== null) {
            $detailsText = is_string($details) ? $details : json_encode($details);
        }
        $stmt = $pdo->prepare("INSERT INTO audit_log
            (branch, module, action, record_date, summary, details, actor_name, actor_role, actor_subrole)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $branch,
            $module,
            $action,
            $recordDate ?: null,
            $summary,
            $detailsText,
            $user['name']    ?? 'Unknown',
            $user['role']    ?? 'unknown',
            $user['subrole'] ?? null,
        ]);
    } catch (Throwable $ignored) {
        // Auditing must never break the actual save/update/delete it is
        // observing — fail silently if the log write itself has a problem.
    }
}
