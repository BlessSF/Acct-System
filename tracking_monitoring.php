<?php
// ============================================================
//  tracking_monitoring.php — System-wide activity monitor
//  Manager-only page: filter by date, branch, and who made the
//  change (Manager / a branch's OIC-Accounting / a branch's
//  Cashier), and see every create / update / delete / lock
//  action recorded across the system by lib/audit_log.php.
// ============================================================
require_once 'config.php';
require_once 'auth.php';
require_once 'lib/audit_log.php';
requireLogin();
enforceCashierAccess(); // cashiers never reach this page

$pdo  = getPDO();
$user = currentUser();

// Only management (Manager / Accounting / OIC / Admin session roles)
// may view the monitor — branch OIC/Cashier logins are redirected.
if (!isManagement()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

ensureAuditLogTable($pdo);

// ── Branch list (matches the branches used across the app) ─────────
$BRANCHES = ['Stella', 'Dois', 'H', 'Pub Express', 'Commissary', 'Recovery', 'DemicLab-Main', 'DemicLab-Jaro'];

// ── Filters (GET, so the view is bookmarkable/shareable) ───────────
$fDateFrom = trim($_GET['date_from'] ?? date('Y-m-d')); // defaults to TODAY
$fDateTo   = trim($_GET['date_to']   ?? date('Y-m-d')); // defaults to TODAY
$fBranch   = trim($_GET['branch']    ?? '');             // '' = all branches
$fWho      = trim($_GET['who']       ?? '');             // '' = anyone; else 'Manager' or 'Dois|oic' / 'Dois|cashier'
$fAction   = trim($_GET['action']    ?? '');             // '' = any action
$fModule   = trim($_GET['module']    ?? '');             // free-text module search
$fPage     = max(1, (int)($_GET['p'] ?? 1));
$perPage   = 50;

$where  = [];
$params = [];

// Filter on created_at (the actual timestamp of the change) so "today"
// really means "changes made today", regardless of which business date
// (report_date) the change applies to.
if ($fDateFrom !== '') { $where[] = 'DATE(created_at) >= ?'; $params[] = $fDateFrom; }
if ($fDateTo   !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = $fDateTo; }
if ($fBranch   !== '') { $where[] = 'branch = ?'; $params[] = $fBranch; }
if ($fAction   !== '') { $where[] = 'action = ?'; $params[] = $fAction; }
if ($fModule   !== '') { $where[] = 'module LIKE ?'; $params[] = "%$fModule%"; }

if ($fWho !== '') {
    if ($fWho === 'Manager') {
        $where[] = "actor_role = 'manager'";
    } elseif ($fWho === 'AccountingHead') {
        $where[] = "actor_role = 'accounting'";
    } elseif (strpos($fWho, '|') !== false) {
        [$whoBranch, $whoSub] = explode('|', $fWho, 2);
        $where[] = "actor_role = 'branch' AND actor_name = ? AND actor_subrole = ?";
        $params[] = $whoBranch;
        $params[] = $whoSub;
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log $whereSql");
$cntStmt->execute($params);
$totalCount = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$fPage      = min($fPage, $totalPages);
$offset     = ($fPage - 1) * $perPage;

$dataStmt = $pdo->prepare("SELECT * FROM audit_log $whereSql ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// Quick counts for the summary strip (today, matching current filters minus date range)
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()");
$todayStmt->execute();
$todayCount = (int)$todayStmt->fetchColumn();

$actionLabels = [
    'created'  => ['label' => 'Created',   'color' => '#0f7b5c', 'bg' => 'rgba(15,123,92,.10)'],
    'updated'  => ['label' => 'Updated',   'color' => '#2563eb', 'bg' => 'rgba(37,99,235,.10)'],
    'deleted'  => ['label' => 'Deleted',   'color' => '#dc3545', 'bg' => 'rgba(220,53,69,.10)'],
    'locked'   => ['label' => 'Locked',    'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
    'unlocked' => ['label' => 'Unlocked',  'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,.10)'],
];

function tmQueryString(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) { if ($v === '' || $v === null) unset($params[$k]); }
    return htmlspecialchars('?' . http_build_query($params));
}

$pageTitle  = 'Tracking & Monitoring';
$activePage = 'tracking_monitoring';
include 'layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.tm-summary { display: flex; gap: 14px; margin-bottom: 18px; flex-wrap: wrap; }
.tm-summary-card {
  flex: 1 1 180px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px 16px; box-shadow: var(--shadow);
}
.tm-summary-card .n { font-family: var(--font-m); font-size: 1.5rem; font-weight: 700; color: var(--text); }
.tm-summary-card .l { font-size: .72rem; color: var(--subtext); text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }

.tm-filters {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 16px; margin-bottom: 18px; box-shadow: var(--shadow);
}
.tm-filters form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.tm-field { display: flex; flex-direction: column; gap: 4px; }
.tm-field label { font-size: .68rem; font-weight: 600; color: var(--subtext); text-transform: uppercase; letter-spacing: .04em; }
.tm-field input, .tm-field select {
  border: 1px solid var(--border2); border-radius: var(--radius-sm); padding: 7px 10px;
  font-family: var(--font-h); font-size: .82rem; background: var(--surf2); color: var(--text);
  min-width: 130px;
}
.tm-field input:focus, .tm-field select:focus { outline: none; border-color: var(--accent); background: var(--surface); }
.tm-btn {
  border: none; border-radius: var(--radius-sm); padding: 8px 16px; font-size: .82rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
}
.tm-btn-primary { background: var(--accent); color: #fff; }
.tm-btn-primary:hover { background: #0c6349; }
.tm-btn-ghost { background: transparent; color: var(--subtext); border: 1px solid var(--border2); }
.tm-btn-ghost:hover { background: var(--surf3); }

.tm-outer {
  width: 100%; overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface); box-shadow: var(--shadow);
}
.tm-table { border-collapse: collapse; width: 100%; min-width: 900px; font-size: .8rem; }
.tm-table thead th {
  background: var(--surf3); color: var(--subtext2); font-family: var(--font-m); font-size: .64rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 10px 12px;
  border-bottom: 1px solid var(--border); text-align: left; white-space: nowrap; position: sticky; top: 0;
}
.tm-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
.tm-table tbody tr:hover { background: var(--surf2); }
.tm-when { font-family: var(--font-m); font-size: .74rem; color: var(--subtext2); white-space: nowrap; }
.tm-who  { font-weight: 600; }
.tm-who-sub { font-size: .7rem; color: var(--subtext); font-weight: 400; }
.tm-badge {
  display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .68rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
}
.tm-empty { padding: 40px; text-align: center; color: var(--subtext); }
.tm-pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: .8rem; color: var(--subtext); }
.tm-pagination a { color: var(--accent); text-decoration: none; font-weight: 600; }
.tm-pagination a.disabled { color: var(--muted); pointer-events: none; }
</style>

<div class="tm-summary">
  <div class="tm-summary-card"><div class="n"><?= number_format($todayCount) ?></div><div class="l">Changes Today (all branches)</div></div>
  <div class="tm-summary-card"><div class="n"><?= number_format($totalCount) ?></div><div class="l">Matching Current Filters</div></div>
</div>

<div class="tm-filters">
  <form method="GET">
    <div class="tm-field">
      <label>Date From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($fDateFrom) ?>">
    </div>
    <div class="tm-field">
      <label>Date To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($fDateTo) ?>">
    </div>
    <div class="tm-field">
      <label>Branch</label>
      <select name="branch">
        <option value="">All Branches</option>
        <?php foreach ($BRANCHES as $b): ?>
        <option value="<?= htmlspecialchars($b) ?>" <?= $fBranch === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="tm-field">
      <label>Who</label>
      <select name="who">
        <option value="">Anyone</option>
        <option value="Manager" <?= $fWho === 'Manager' ? 'selected' : '' ?>>Manager</option>
        <option value="AccountingHead" <?= $fWho === 'AccountingHead' ? 'selected' : '' ?>>Accounting Head</option>
        <?php foreach ($BRANCHES as $b): ?>
        <option value="<?= htmlspecialchars($b) ?>|oic" <?= $fWho === "$b|oic" ? 'selected' : '' ?>><?= htmlspecialchars($b) ?> — OIC/Accounting</option>
        <option value="<?= htmlspecialchars($b) ?>|cashier" <?= $fWho === "$b|cashier" ? 'selected' : '' ?>><?= htmlspecialchars($b) ?> — Cashier</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="tm-field">
      <label>Action</label>
      <select name="action">
        <option value="">Any Action</option>
        <?php foreach ($actionLabels as $key => $meta): ?>
        <option value="<?= $key ?>" <?= $fAction === $key ? 'selected' : '' ?>><?= $meta['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="tm-field">
      <label>Module</label>
      <input type="text" name="module" placeholder="e.g. Sales Report" value="<?= htmlspecialchars($fModule) ?>">
    </div>
    <div class="tm-field">
      <button type="submit" class="tm-btn tm-btn-primary">Filter</button>
    </div>
    <div class="tm-field">
      <a href="<?= BASE_URL ?>/tracking_monitoring.php" class="tm-btn tm-btn-ghost" style="display:inline-block;text-decoration:none;text-align:center;">Reset</a>
    </div>
  </form>
</div>

<div class="tm-outer">
  <table class="tm-table">
    <thead>
      <tr>
        <th>When</th>
        <th>Branch</th>
        <th>Who</th>
        <th>Action</th>
        <th>Module</th>
        <th>Report Date</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
      <tr><td colspan="7" class="tm-empty">No activity found for the selected filters.</td></tr>
      <?php else: foreach ($rows as $r):
        $meta = $actionLabels[$r['action']] ?? ['label' => ucfirst($r['action']), 'color' => '#4b5563', 'bg' => 'rgba(75,85,99,.10)'];
        $isBranchActor = $r['actor_role'] === 'branch';
      ?>
      <tr>
        <td class="tm-when"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?><br><?= htmlspecialchars(date('g:i A', strtotime($r['created_at']))) ?></td>
        <td><?= htmlspecialchars($r['branch']) ?></td>
        <td>
          <div class="tm-who"><?= htmlspecialchars($r['actor_name']) ?></div>
          <?php if ($isBranchActor): ?>
          <div class="tm-who-sub"><?= $r['actor_subrole'] === 'cashier' ? 'Cashier' : 'OIC/Accounting' ?></div>
          <?php else: ?>
          <div class="tm-who-sub"><?= htmlspecialchars(managementRoleLabel($r['actor_role'])) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="tm-badge" style="color:<?= $meta['color'] ?>;background:<?= $meta['bg'] ?>;"><?= htmlspecialchars($meta['label']) ?></span></td>
        <td><?= htmlspecialchars($r['module']) ?></td>
        <td><?= $r['record_date'] ? htmlspecialchars(date('M j, Y', strtotime($r['record_date']))) : '—' ?></td>
        <td><?= htmlspecialchars($r['summary'] ?? '') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="tm-pagination">
  <div>Page <?= $fPage ?> of <?= $totalPages ?> (<?= number_format($totalCount) ?> total)</div>
  <div>
    <a class="<?= $fPage <= 1 ? 'disabled' : '' ?>" href="<?= tmQueryString(['p' => $fPage - 1]) ?>">&larr; Newer</a>
    &nbsp;&nbsp;
    <a class="<?= $fPage >= $totalPages ? 'disabled' : '' ?>" href="<?= tmQueryString(['p' => $fPage + 1]) ?>">Older &rarr;</a>
  </div>
</div>
