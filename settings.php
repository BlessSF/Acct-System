<?php
// ============================================================
//  settings.php — Manager / Accounting Head settings
//  Full system backup (database download) + backup reminder.
// ============================================================
require_once 'config.php';
require_once 'auth.php';
require_once 'lib/backup.php';
requireLogin();
enforceCashierAccess();

if (!backupCanAccess()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();
backupEnsureTables($pdo);

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['settings_csrf'];

// ── Actions ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flashSet('error', 'Session expired. Please try again.');
        header('Location: settings.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_reminder') {
        $d = (int)($_POST['interval'] ?? 7);
        if (!in_array($d, [1, 3, 7, 14, 30], true)) $d = 7;
        backupSetSetting($pdo, 'backup_interval_days', (string)$d);
        unset($_SESSION['backup_snooze']);
        flashSet('success', "Reminder saved — you'll be reminded every $d day" . ($d > 1 ? 's' : '') . '.');
        header('Location: settings.php'); exit;
    }

    if ($action === 'backup') {
        $gz   = function_exists('deflate_init');
        $name = 'saleshub_backup_' . date('Y-m-d_His') . ($gz ? '.sql.gz' : '.sql');
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . ($gz ? 'application/gzip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        session_write_close(); // don't block other tabs during a long dump
        $ctx = $gz ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]) : null;
        $out = function (string $s) use ($ctx) {
            echo $ctx ? deflate_add($ctx, $s, ZLIB_NO_FLUSH) : $s;
        };
        [$nt, $nr] = backupStreamDump($pdo, $out);
        if ($ctx) echo deflate_add($ctx, '', ZLIB_FINISH);
        $pdo->prepare("INSERT INTO backup_log (created_at, actor_name, actor_role, filename, tables_count, rows_count) VALUES (NOW(),?,?,?,?,?)")
            ->execute([$user['name'], $_SESSION['user_role'] ?? '', $name, $nt, $nr]);
        exit;
    }
}

$status  = backupStatus($pdo);
$history = $pdo->query("SELECT * FROM backup_log ORDER BY id DESC LIMIT 10")->fetchAll();
$tblCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

$pageTitle  = 'Settings';
$activePage = 'settings';
include 'layout.php';
?>
<style>
.st-wrap{max-width:860px;display:grid;gap:18px}
.st-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px 24px}
.st-card h2{font-size:15px;font-weight:700;margin:0 0 4px}
.st-card .st-sub{font-size:13px;color:#6b7280;margin:0 0 16px}
.st-status{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:12px;margin-bottom:16px;border:1px solid}
.st-status.ok{background:rgba(15,123,92,.07);border-color:rgba(15,123,92,.25)}
.st-status.due{background:rgba(217,119,6,.09);border-color:rgba(217,119,6,.35)}
.st-ico{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:18px;flex:none}
.ok .st-ico{background:rgba(15,123,92,.15)}.due .st-ico{background:rgba(217,119,6,.18)}
.st-status b{display:block;font-size:14px}.st-status span{font-size:12.5px;color:#6b7280}
.st-btn{background:var(--accent);color:#fff;border:0;border-radius:10px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer}
.st-btn:hover{filter:brightness(.93)}
.st-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.st-row select{padding:9px 12px;border:1px solid var(--border2);border-radius:9px;font-size:14px;background:#fff}
.st-btn2{background:#fff;color:var(--text);border:1px solid var(--border2);border-radius:10px;padding:9px 16px;font-size:13.5px;font-weight:600;cursor:pointer}
.st-note{font-size:12.5px;color:#6b7280;margin-top:12px;line-height:1.55}
.st-table{width:100%;border-collapse:collapse;font-size:13px}
.st-table th{text-align:left;color:#6b7280;font-weight:600;padding:8px 10px;border-bottom:1px solid var(--border)}
.st-table td{padding:9px 10px;border-bottom:1px solid var(--border)}
</style>

<div class="st-wrap">

  <div class="st-card">
    <h2>Full system backup</h2>
    <p class="st-sub">Downloads a complete copy of all system data — every branch, report, ledger, user and setting (<?= $tblCount ?> tables).</p>

    <?php if ($status['never']): ?>
      <div class="st-status due"><div class="st-ico">⚠️</div><div><b>No backup has been made yet</b><span>Create the first one now and keep the file somewhere safe.</span></div></div>
    <?php elseif ($status['due']): ?>
      <div class="st-status due"><div class="st-ico">⏰</div><div><b>Backup is due</b><span>Last backup: <?= date('M j, Y g:i A', strtotime($status['last'])) ?> (<?= $status['days'] ?> day<?= $status['days']==1?'':'s' ?> ago)</span></div></div>
    <?php else: ?>
      <div class="st-status ok"><div class="st-ico">✅</div><div><b>Backup is up to date</b><span>Last backup: <?= date('M j, Y g:i A', strtotime($status['last'])) ?> · next due in <?= max(0, $status['interval'] - $status['days']) ?> day(s)</span></div></div>
    <?php endif; ?>

    <form method="POST" onsubmit="var b=this.querySelector('button');b.textContent='Preparing backup…';setTimeout(function(){b.textContent='⬇ Create full backup';},6000);">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="backup">
      <button class="st-btn" type="submit">⬇ Create full backup</button>
    </form>
    <p class="st-note">The file downloads to your computer (<?= function_exists('deflate_init') ? '.sql.gz' : '.sql' ?>). Store it on a USB drive or cloud drive — not only on this computer. To restore, import the file in phpMyAdmin. Reload this page after the download finishes to see it in the history.</p>
  </div>

  <div class="st-card">
    <h2>Backup reminder</h2>
    <p class="st-sub">Managers and the Accounting Head see a reminder bar on every page when a backup is overdue.</p>
    <form method="POST" class="st-row">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="save_reminder">
      <label for="iv" style="font-size:14px">Remind me to back up every</label>
      <select id="iv" name="interval">
        <?php foreach ([1=>'day',3=>'3 days',7=>'week',14=>'2 weeks',30=>'month'] as $d=>$l): ?>
          <option value="<?= $d ?>" <?= $status['interval']===$d?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <button class="st-btn2" type="submit">Save</button>
    </form>
  </div>

  <div class="st-card">
    <h2>Backup history</h2>
    <p class="st-sub">The last 10 backups created from this page.</p>
    <?php if (!$history): ?>
      <div class="st-note" style="margin:0">Nothing yet.</div>
    <?php else: ?>
    <table class="st-table">
      <tr><th>When</th><th>By</th><th>File</th><th>Tables</th><th>Rows</th></tr>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= date('M j, Y g:i A', strtotime($h['created_at'])) ?></td>
        <td><?= htmlspecialchars($h['actor_name']) ?> <span style="color:#6b7280">· <?= htmlspecialchars(managementRoleLabel($h['actor_role'])) ?></span></td>
        <td><?= htmlspecialchars($h['filename']) ?></td>
        <td><?= (int)$h['tables_count'] ?></td>
        <td><?= number_format((int)$h['rows_count']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

</div>