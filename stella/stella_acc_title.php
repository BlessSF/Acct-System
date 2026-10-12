<?php
// ============================================================
//  stella_acc_title.php — Stella Account Title Masterlist
//  Manage (add / delete) the Account Title options used by
//  stella_disbursement.php's ACCOUNT TITLE dropdown.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once __DIR__ . '/../lib/vat_mode.php';
require_once __DIR__ . '/../lib/audit_log.php';
requireLogin();

// Only Stella branch + management can access
if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_acc_titles` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `title`       varchar(255) NOT NULL,
    `section`     enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
    `sort_order`  int(6) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
// Existing installations created before `section` existed — add it directly.
// Safe to re-run: "column already exists" errors are ignored.
try { $pdo->exec("ALTER TABLE `stella_acc_titles` ADD COLUMN `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses' AFTER `title`"); }
catch (Throwable $ignored) {}

// One-time correction for rows seeded before `section` existed (they landed
// on the 'expenses' default). Only touches rows still flagged as the
// original system seed and still on the default, so it never overwrites a
// section someone has since edited on purpose.
$fixToAssets = $pdo->prepare("UPDATE `stella_acc_titles` SET `section`='assets' WHERE `title`=? AND `saved_by`='system-seed' AND `section`='expenses'");
foreach (["Office Equipment","Other Equipment","Service Vehicle","Leasehold Improvement","Furniture and Fixtures","Investments"] as $t) {
    $fixToAssets->execute([$t]);
}
$fixToOther = $pdo->prepare("UPDATE `stella_acc_titles` SET `section`='other' WHERE `title`=? AND `saved_by`='system-seed' AND `section`='expenses'");
foreach (["Accounts Payable","EWT Payable"] as $t) {
    $fixToOther->execute([$t]);
}

// ── One-time seed from the list that used to be hardcoded in
//    stella_disbursement.php / stella_sum.php / stella_tb.php,
//    so existing entries and report groupings keep working ──
$countStmt = $pdo->query("SELECT COUNT(*) FROM `stella_acc_titles`");
if ((int)$countStmt->fetchColumn() === 0) {
    // title => section ('assets' | 'expenses' | 'other')
    $DEFAULT_TITLES = [
        "Office Equipment"             => 'assets',
        "Other Equipment"              => 'assets',
        "Service Vehicle"              => 'assets',
        "Leasehold Improvement"        => 'assets',
        "Furniture and Fixtures"       => 'assets',
        "Investments"                  => 'assets',
        "Accounts Payable"             => 'other',
        "EWT Payable"                  => 'other',
        "Purchases - Non-Vat"          => 'expenses',
        "Purchases - Vatable"          => 'expenses',
        "Kitchen Supplies"             => 'expenses',
        "Solane"                       => 'expenses',
        "Miscellaneous"                => 'expenses',
        "Rent"                         => 'expenses',
        "CUSA"                         => 'expenses',
        "Office Supplies"              => 'expenses',
        "Pest Control"                 => 'expenses',
        "Advertisement"                => 'expenses',
        "Bio Augmentation"             => 'expenses',
        "Professional Fee"             => 'expenses',
        "Bookkeeping Fee"              => 'expenses',
        "Fare & Transportation"        => 'expenses',
        "Fuel & Oil"                   => 'expenses',
        "Repairs and Maintenance"      => 'expenses',
        "Telephone, Light & Water"     => 'expenses',
        "Delivery Expense"             => 'expenses',
        "Salaries and Wages"           => 'expenses',
        "Representation Expense"       => 'expenses',
        "Meals"                        => 'expenses',
        "Taxes and Licenses"           => 'expenses',
        "SSS, PHIC, HDMF Contribution" => 'expenses',
        "Commission Expense"           => 'expenses',
        "M'Nikki"                      => 'expenses',
        "c/o Nikki"                    => 'expenses',
        "Others"                       => 'expenses',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `stella_acc_titles` (`title`,`section`,`sort_order`,`saved_by`) VALUES (?,?,?,?)");
    $i = 0;
    foreach ($DEFAULT_TITLES as $t => $sec) { $ins->execute([$t, $sec, $i, 'system-seed']); $i++; }
}

// ── VAT setting per Account Title: does an entry under it carry input tax? ──
vatModeEnsureColumn($pdo, 'stella_acc_titles');
$AUDIT_BRANCH = 'Stella';

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $title   = trim($_POST['title'] ?? '');
    $section = $_POST['section'] ?? 'expenses';
    if (!in_array($section, ['assets','expenses','other'], true)) $section = 'expenses';
    $vatMode = vatModeClean($_POST['vat_mode'] ?? 'both');
    if ($title === '') { echo json_encode(['ok' => false, 'msg' => 'Title cannot be empty']); exit; }
    try {
        $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM `stella_acc_titles`")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO `stella_acc_titles` (`title`,`section`,`vat_mode`,`sort_order`,`saved_by`) VALUES (?,?,?,?,?)");
        $stmt->execute([$title, $section, $vatMode, $maxOrd + 1, $user['name']]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'title' => $title, 'section' => $section, 'vat_mode' => $vatMode]);
    } catch (Throwable $e) {
        $msg = str_contains($e->getMessage(), 'uniq_title') ? 'That account title already exists' : $e->getMessage();
        echo json_encode(['ok' => false, 'msg' => $msg]);
    }
    exit;
}

// ── AJAX: Edit (rename and/or change section) ───────────────
// Renaming also updates any stella_disbursement rows that already
// used the old title, so existing entries stay linked to it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit'])) {
    header('Content-Type: application/json');
    $id       = (int)($_POST['id'] ?? 0);
    $newTitle = trim($_POST['title'] ?? '');
    $section  = $_POST['section'] ?? null;
    if ($section !== null && !in_array($section, ['assets','expenses','other'], true)) $section = null;
    if (!$id)              { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    if ($newTitle === '')  { echo json_encode(['ok' => false, 'msg' => 'Title cannot be empty']); exit; }
    try {
        $cur = $pdo->prepare("SELECT title, section FROM `stella_acc_titles` WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Account title not found']); exit; }
        $oldTitle   = $row['title'];
        $useSection = $section ?? $row['section'];

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE `stella_acc_titles` SET `title`=?, `section`=?, `saved_by`=? WHERE id=?")
            ->execute([$newTitle, $useSection, $user['name'], $id]);
        if ($oldTitle !== $newTitle) {
            $pdo->prepare("UPDATE `stella_disbursement` SET `account_title`=? WHERE `account_title`=?")
                ->execute([$newTitle, $oldTitle]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'title' => $newTitle, 'section' => $useSection]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = str_contains($e->getMessage(), 'uniq_title') ? 'That account title already exists' : $e->getMessage();
        echo json_encode(['ok' => false, 'msg' => $msg]);
    }
    exit;
}

// ── AJAX: Set section only (quick dropdown, no rename) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_set_section'])) {
    header('Content-Type: application/json');
    $id      = (int)($_POST['id'] ?? 0);
    $section = $_POST['section'] ?? '';
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    if (!in_array($section, ['assets','expenses','other'], true)) { echo json_encode(['ok' => false, 'msg' => 'Invalid section']); exit; }
    try {
        $pdo->prepare("UPDATE `stella_acc_titles` SET `section`=?, `saved_by`=? WHERE id=?")->execute([$section, $user['name'], $id]);
        echo json_encode(['ok' => true, 'section' => $section]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Set VAT setting (also reports how many saved entries it would change) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_set_vat'])) {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $mode = $_POST['vat_mode'] ?? '';
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    if (!in_array($mode, VAT_MODES, true)) { echo json_encode(['ok' => false, 'msg' => 'Invalid VAT setting']); exit; }
    try {
        $cur = $pdo->prepare("SELECT `title`, `vat_mode` FROM `stella_acc_titles` WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Account title not found']); exit; }
        $pdo->prepare("UPDATE `stella_acc_titles` SET `vat_mode`=?, `saved_by`=? WHERE id=?")->execute([$mode, $user['name'], $id]);
        try { $affected = count(vatModeChanges($pdo, 'stella_disbursement', $row['title'], $mode)); } catch (Throwable $e) { $affected = 0; }
        if ($row['vat_mode'] !== $mode) {
            logAudit($pdo, $AUDIT_BRANCH, 'Account Titles', 'updated', null,
                'VAT setting of "' . $row['title'] . '": ' . VAT_MODE_LABELS[vatModeClean($row['vat_mode'])] . ' → ' . VAT_MODE_LABELS[$mode]);
        }
        echo json_encode(['ok' => true, 'vat_mode' => $mode, 'title' => $row['title'], 'affected' => $affected, 'label' => VAT_MODE_LABELS[$mode]]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Fix every saved entry whose Input Tax doesn't match its title's VAT setting ──
// Only titles set to "Input tax only" / "Non-VAT only" are touched; "VAT or Non-VAT"
// titles keep exactly what was saved. Gross never changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_recalc_vat_all'])) {
    header('Content-Type: application/json');
    try {
        $total = 0; $per = [];
        foreach ($pdo->query("SELECT `title`, `vat_mode` FROM `stella_acc_titles`")->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $mode = vatModeClean($t['vat_mode']);
            if ($mode === 'both') continue;
            $n = vatModeRecalc($pdo, 'stella_disbursement', $t['title'], $mode);
            if ($n > 0) {
                $total += $n; $per[$t['title']] = $n;
                logAudit($pdo, $AUDIT_BRANCH, 'Account Titles', 'updated', null,
                    'Recalculated Input Tax on ' . $n . ($n === 1 ? ' entry' : ' entries') . ' under "' . $t['title'] . '" (' . VAT_MODE_LABELS[$mode] . ')');
            }
        }
        echo json_encode(['ok' => true, 'updated' => $total, 'per_title' => $per]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Apply the VAT setting to entries already saved under this title ──
// Only Input Tax and Net of VAT are rewritten (Gross never changes).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_recalc_vat'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    try {
        $cur = $pdo->prepare("SELECT `title`, `vat_mode` FROM `stella_acc_titles` WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Account title not found']); exit; }
        $mode = vatModeClean($row['vat_mode']);
        $n = vatModeRecalc($pdo, 'stella_disbursement', $row['title'], $mode);
        if ($n > 0) {
            logAudit($pdo, $AUDIT_BRANCH, 'Account Titles', 'updated', null,
                'Recalculated Input Tax on ' . $n . ($n === 1 ? ' entry' : ' entries') . ' under "' . $row['title'] . '" (' . VAT_MODE_LABELS[$mode] . ')');
        }
        echo json_encode(['ok' => true, 'updated' => $n]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM `stella_acc_titles` WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Load rows ─────────────────────────────────────────────
$titles = $pdo->query("SELECT * FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_ASSOC);
$SECTION_LABELS = ['assets' => 'Assets', 'expenses' => 'Expenses', 'other' => 'Other'];
$VAT_LABELS = VAT_MODE_LABELS;

// Saved entries whose Input Tax doesn't match their title's VAT setting (only titles that force a result are checked).
$pendingFix = [];
foreach ($titles as $t) {
    $m = vatModeClean($t['vat_mode'] ?? 'both');
    if ($m === 'both') continue;
    try { $n = count(vatModeChanges($pdo, 'stella_disbursement', $t['title'], $m)); } catch (Throwable $e) { $n = 0; }
    if ($n > 0) $pendingFix[$t['title']] = $n;
}

$pageTitle  = 'Stella Account Titles';
$activePage = 'stella_disbursement';
include '../layout.php';
?>

<style>
.at-outer {
  width: 100%; max-width: 860px;
  border: 2px solid #6D4C41; border-radius: var(--radius);
  background: var(--surface); box-shadow: 0 2px 8px rgba(0,0,0,.10);
  overflow: hidden;
}
.at-header {
  background: #6D4C41; color: #fff; padding: 10px 16px;
  font-family: var(--font-m); font-size: .68rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em;
}
.at-add-row {
  display: flex; gap: 8px; padding: 14px 16px;
  border-bottom: 1px solid #e5e0dc; background: #FBF7F4;
}
.at-add-row input {
  flex: 1; padding: 9px 12px; border: 1px solid #d7ccc8; border-radius: 8px;
  font-family: var(--font-h); font-size: .82rem; outline: none;
}
.at-add-row input:focus { border-color: #6D4C41; box-shadow: 0 0 0 3px rgba(109,76,65,.12); }
.at-sec-sel {
  padding: 9px 10px; border: 1px solid #d7ccc8; border-radius: 8px;
  font-family: var(--font-m); font-size: .74rem; font-weight: 700;
  background: #fff; cursor: pointer; outline: none;
}
.at-sec-sel:focus { border-color: #6D4C41; }
.at-add-btn {
  padding: 9px 16px; background: #6D4C41; color: #fff; border: none; border-radius: 8px;
  font-family: var(--font-h); font-size: .82rem; font-weight: 700; cursor: pointer;
  white-space: nowrap; transition: background .15s;
}
.at-add-btn:hover { background: #4E342E; }
.at-add-btn:disabled { opacity: .5; cursor: default; }

.at-list { list-style: none; margin: 0; padding: 0; max-height: 520px; overflow-y: auto; }
.at-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 16px; border-bottom: 1px solid #f0ece8;
  font-family: var(--font-h); font-size: .82rem; color: #1a1d23;
  transition: background .1s;
}
.at-item:hover { background: #FBF7F4; }
.at-item:last-child { border-bottom: none; }
.at-del {
  padding: 4px 10px; font-size: .64rem; font-weight: 700;
  font-family: var(--font-m); background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 6px; cursor: pointer;
  transition: all .13s;
}
.at-del:hover { background: #ffe4e6; }
.at-edit {
  padding: 4px 10px; font-size: .64rem; font-weight: 700;
  font-family: var(--font-m); background: #eef2ff; color: #3730a3;
  border: 1px solid #c7d2fe; border-radius: 6px; cursor: pointer;
  transition: all .13s; margin-right: 6px;
}
.at-edit:hover { background: #e0e7ff; }
.at-item-actions { display: flex; align-items: center; gap: 0; }
.at-sec-badge-sel {
  padding: 3px 8px; font-size: .62rem; font-weight: 700;
  font-family: var(--font-m); border-radius: 999px; cursor: pointer;
  outline: none; margin-right: 10px; border: 1px solid transparent;
  appearance: none; -webkit-appearance: none; text-align: center;
}
.at-sec-badge-sel.sec-assets   { background: #E3F2FD; color: #1565C0; border-color: #90CAF9; }
.at-sec-badge-sel.sec-expenses { background: #FFF9C4; color: #E65100; border-color: #F9A825; }
.at-sec-badge-sel.sec-other    { background: #ECEFF1; color: #455A64; border-color: #B0BEC5; }
.at-fixnote { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
  max-width:860px; margin:0 0 14px; padding:10px 14px; background:#FFF8E1; border:1px solid #FFE082; border-radius:10px;
  font-family:var(--font-h); font-size:.8rem; color:#6D4C00; }
.at-fixnote button { padding:6px 14px; background:#6D4C41; color:#fff; border:none; border-radius:8px;
  font-family:var(--font-h); font-size:.78rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.at-fixnote button:hover { background:#4E342E; }
/* VAT setting: does an entry under this title carry input tax? */
.at-vat-sel {
  padding: 3px 8px; font-size: .62rem; font-weight: 700;
  font-family: var(--font-m); border-radius: 999px; cursor: pointer;
  outline: none; margin-right: 8px; border: 1px solid transparent;
  appearance: none; -webkit-appearance: none; text-align: center;
}
.at-vat-sel.vat-both { background: #EDE7F6; color: #4527A0; border-color: #B39DDB; }
.at-vat-sel.vat-vat  { background: #E8F5E9; color: #2E7D32; border-color: #A5D6A7; }
.at-vat-sel.vat-none { background: #FFEBEE; color: #C62828; border-color: #EF9A9A; }
.at-vat-legend b { font-weight: 700; }
.at-edit-input {
  flex: 1; padding: 6px 10px; border: 1px solid #6D4C41; border-radius: 6px;
  font-family: var(--font-h); font-size: .82rem; outline: none;
  box-shadow: 0 0 0 3px rgba(109,76,65,.12);
}
.at-save {
  padding: 4px 10px; font-size: .64rem; font-weight: 700;
  font-family: var(--font-m); background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer;
  transition: all .13s; margin-right: 6px;
}
.at-save:hover { background: #dcfce7; }
.at-cancel {
  padding: 4px 10px; font-size: .64rem; font-weight: 700;
  font-family: var(--font-m); background: #f3f4f6; color: #374151;
  border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;
  transition: all .13s; margin-right: 6px;
}
.at-cancel:hover { background: #e5e7eb; }
.at-empty { padding: 30px 16px; text-align: center; color: var(--subtext); font-family: var(--font-m); font-size: .78rem; }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
</style>

<!-- Header -->
<div class="section-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div class="section-title">Account <span>Titles</span></div>
    <div class="section-subtitle">MERITONI CORP · Add, rename, or delete the Account Title options used in Stella Disbursement</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <a href="stella_disbursement.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
              background:#4CAF50;color:#fff;border-radius:9px;text-decoration:none;
              font-family:var(--font-h);font-size:.82rem;font-weight:700;
              box-shadow:0 2px 8px rgba(76,175,80,.3);transition:background .15s,transform .15s;
              white-space:nowrap"
       onmouseover="this.style.background='#388E3C';this.style.transform='translateY(-1px)'"
       onmouseout="this.style.background='#4CAF50';this.style.transform=''">
      ← Back to Disbursement
    </a>
  </div>
</div>

<?php if ($pendingFix): $pfTotal = array_sum($pendingFix); ?>
<div class="at-fixnote" id="at-fixnote">
  <span>⚠ <strong><?= $pfTotal ?> saved <?= $pfTotal === 1 ? 'entry' : 'entries' ?></strong> still carry an Input Tax that does not match the VAT setting of their account title
  (<?= htmlspecialchars(implode(', ', array_keys($pendingFix))) ?>).</span>
  <button type="button" onclick="fixAllVat()">Review &amp; update</button>
</div>
<?php endif; ?>
<div class="at-outer">
  <div class="at-header">Stella — Account Title List (<span id="at-count"><?= count($titles) ?></span>)</div>
  <div style="padding:8px 16px;background:#FBF7F4;border-bottom:1px solid #e5e0dc;font-family:var(--font-m);font-size:.66rem;color:#6b7280">
    <strong>Section</strong> controls where a title shows up: <span style="color:#1565C0;font-weight:700">Assets</span> and
    <span style="color:#E65100;font-weight:700">Expenses</span> appear in Sum &amp; Trial Balance;
    <span style="color:#455A64;font-weight:700">Other</span> (e.g. Accounts Payable) stays in Disbursement only.
  </div>
  <div class="at-vat-legend" style="padding:8px 16px;background:#FBF7F4;border-bottom:1px solid #e5e0dc;font-family:var(--font-m);font-size:.66rem;color:#6b7280;line-height:1.6">
    <strong>VAT setting</strong> decides whether a Disbursement entry under that title carries input tax (12%):
    <span style="color:#4527A0;font-weight:700">VAT or Non-VAT</span> follows the supplier (VAT supplier = input tax, NV supplier = none);
    <span style="color:#2E7D32;font-weight:700">Input tax only</span> always carries input tax;
    <span style="color:#C62828;font-weight:700">Non-VAT only</span> never carries input tax, even for a VAT-registered supplier (e.g. Purchases - Non-Vat).
  </div>
  <div class="at-add-row">
    <input type="text" id="at-new-title" placeholder="New account title…" maxlength="255"
           onkeydown="if(event.key==='Enter'){event.preventDefault();addTitle();}">
    <select id="at-new-section" class="at-sec-sel">
      <option value="expenses" selected>Expenses</option>
      <option value="assets">Assets</option>
      <option value="other">Other (not in TB/Sum)</option>
    </select>
    <select id="at-new-vat" class="at-sec-sel" title="Does this account title carry input tax?">
      <option value="both" selected>VAT or Non-VAT</option>
      <option value="vat">Input tax only</option>
      <option value="none">Non-VAT only</option>
    </select>
    <button class="at-add-btn" id="at-add-btn" onclick="addTitle()">+ Add</button>
  </div>
  <ul class="at-list" id="at-list">
    <?php if ($titles): ?>
      <?php foreach ($titles as $t): ?>
      <li class="at-item" id="at-row-<?= $t['id'] ?>" data-title="<?= htmlspecialchars($t['title'], ENT_QUOTES) ?>">
        <span class="at-title-text" id="at-title-<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></span>
        <span class="at-item-actions">
          <?php $vm = vatModeClean($t['vat_mode'] ?? 'both'); ?>
          <select class="at-vat-sel vat-<?= $vm ?>" id="at-vat-<?= $t['id'] ?>" data-mode="<?= $vm ?>"
                  title="VAT setting: does this title carry input tax?" onchange="setVat(<?= $t['id'] ?>, this)">
            <?php foreach ($VAT_LABELS as $val => $lbl): ?>
            <option value="<?= $val ?>"<?= $vm === $val ? ' selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <select class="at-sec-badge-sel sec-<?= htmlspecialchars($t['section']) ?>" id="at-sec-<?= $t['id'] ?>"
                  onchange="setSection(<?= $t['id'] ?>, this)">
            <?php foreach ($SECTION_LABELS as $val => $lbl): ?>
            <option value="<?= $val ?>"<?= $t['section'] === $val ? ' selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <button class="at-edit" onclick="startEdit(<?= $t['id'] ?>)">Edit</button>
          <button class="at-del" onclick="deleteTitle(<?= $t['id'] ?>, this)">Delete</button>
        </span>
      </li>
      <?php endforeach; ?>
    <?php else: ?>
      <li class="at-empty" id="at-empty">No account titles yet — add one above.</li>
    <?php endif; ?>
  </ul>
</div>

<script>
async function addTitle() {
  const inp = document.getElementById('at-new-title');
  const secSel = document.getElementById('at-new-section');
  const btn = document.getElementById('at-add-btn');
  const title = inp.value.trim();
  const section = secSel.value;
  if (!title) { inp.focus(); return; }
  btn.disabled = true; btn.textContent = '…';
  try {
    const fd = new FormData();
    fd.append('ajax_add', '1');
    fd.append('title', title);
    fd.append('section', section);
    fd.append('vat_mode', document.getElementById('at-new-vat').value);
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      const empty = document.getElementById('at-empty');
      if (empty) empty.remove();
      const list = document.getElementById('at-list');
      const li = document.createElement('li');
      li.className = 'at-item';
      li.id = 'at-row-' + data.id;
      li.dataset.title = data.title;
      li.innerHTML = `<span class="at-title-text" id="at-title-${data.id}">${escHtml(data.title)}</span>
        <span class="at-item-actions">
          <select class="at-vat-sel vat-${data.vat_mode}" id="at-vat-${data.id}" data-mode="${data.vat_mode}" title="VAT setting: does this title carry input tax?" onchange="setVat(${data.id}, this)">
            <option value="both"${data.vat_mode==='both'?' selected':''}>VAT or Non-VAT</option>
            <option value="vat"${data.vat_mode==='vat'?' selected':''}>Input tax only</option>
            <option value="none"${data.vat_mode==='none'?' selected':''}>Non-VAT only</option>
          </select>
          <select class="at-sec-badge-sel sec-${data.section}" id="at-sec-${data.id}" onchange="setSection(${data.id}, this)">
            <option value="expenses"${data.section==='expenses'?' selected':''}>Expenses</option>
            <option value="assets"${data.section==='assets'?' selected':''}>Assets</option>
            <option value="other"${data.section==='other'?' selected':''}>Other</option>
          </select>
          <button class="at-edit" onclick="startEdit(${data.id})">Edit</button>
          <button class="at-del" onclick="deleteTitle(${data.id}, this)">Delete</button>
        </span>`;
      list.appendChild(li);
      inp.value = '';
      updateCount();
      showToast('✓ Account title added', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
  btn.disabled = false; btn.textContent = '+ Add';
  inp.focus();
}

async function setSection(id, sel) {
  const newSection = sel.value;
  const prevClass = sel.className;
  sel.disabled = true;
  try {
    const fd = new FormData();
    fd.append('ajax_set_section', '1');
    fd.append('id', id);
    fd.append('section', newSection);
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      sel.className = 'at-sec-badge-sel sec-' + data.section;
      showToast('✓ Section updated', 'success');
    } else {
      sel.className = prevClass;
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    sel.className = prevClass;
    showToast('❌ Network error', 'error');
  }
  sel.disabled = false;
}

async function setVat(id, sel) {
  const prev = sel.dataset.mode;
  const mode = sel.value;
  sel.disabled = true;
  try {
    const fd = new FormData();
    fd.append('ajax_set_vat', '1');
    fd.append('id', id);
    fd.append('vat_mode', mode);
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
      sel.value = prev;
      showToast('❌ ' + data.msg, 'error');
      sel.disabled = false;
      return;
    }
    sel.dataset.mode = data.vat_mode;
    sel.className = 'at-vat-sel vat-' + data.vat_mode;
    showToast('✓ VAT setting updated', 'success');
    // Entries saved earlier keep their old Input Tax until they are recalculated — ask first.
    if (data.affected > 0) {
      const n = data.affected;
      const ok = confirm(
        n + (n === 1 ? ' entry' : ' entries') + ' already saved under "' + data.title + '" ' + (n === 1 ? 'has' : 'have') +
        ' a different Input Tax than this setting (' + data.label + ').\n\n' +
        'Update ' + (n === 1 ? 'it' : 'them') + ' now? Gross stays the same; only Input Tax and Net of VAT are recalculated.\n\n' +
        'OK = update them     Cancel = leave them as they are');
      if (ok) {
        const fd2 = new FormData();
        fd2.append('ajax_recalc_vat', '1');
        fd2.append('id', id);
        const res2  = await fetch('stella_acc_title.php', { method: 'POST', body: fd2 });
        const data2 = await res2.json();
        if (data2.ok) showToast('✓ ' + data2.updated + (data2.updated === 1 ? ' entry' : ' entries') + ' updated', 'success');
        else showToast('❌ ' + data2.msg, 'error');
      }
    }
  } catch (e) {
    sel.value = prev;
    showToast('❌ Network error', 'error');
  }
  sel.disabled = false;
}

const VAT_PENDING = <?= json_encode($pendingFix, JSON_UNESCAPED_UNICODE) ?>;

async function fixAllVat() {
  const lines = Object.entries(VAT_PENDING).map(([t, n]) => '  • ' + t + ': ' + n + (n === 1 ? ' entry' : ' entries'));
  const ok = confirm(
    'These saved entries have an Input Tax that does not match the VAT setting of their account title:\n\n' + lines.join('\n') +
    '\n\nUpdate them now? Gross stays the same; only Input Tax and Net of VAT are recalculated.\n\n' +
    'OK = update them     Cancel = leave them as they are');
  if (!ok) return;
  try {
    const fd = new FormData();
    fd.append('ajax_recalc_vat_all', '1');
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      const note = document.getElementById('at-fixnote');
      if (note) note.remove();
      showToast('✓ ' + data.updated + (data.updated === 1 ? ' entry' : ' entries') + ' updated', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

function startEdit(id) {
  const row = document.getElementById('at-row-' + id);
  if (row.querySelector('.at-edit-input')) return; // already editing
  const currentTitle = row.dataset.title;
  const textSpan   = row.querySelector('.at-title-text');
  const actionsSpan = row.querySelector('.at-item-actions');
  textSpan.style.display = 'none';
  actionsSpan.style.display = 'none';

  const input = document.createElement('input');
  input.type = 'text';
  input.className = 'at-edit-input';
  input.value = currentTitle;
  input.maxLength = 255;
  input.onkeydown = (e) => {
    if (e.key === 'Enter') { e.preventDefault(); saveEdit(id); }
    if (e.key === 'Escape') { e.preventDefault(); cancelEdit(id); }
  };

  const saveBtn = document.createElement('button');
  saveBtn.className = 'at-save'; saveBtn.textContent = 'Save';
  saveBtn.onclick = () => saveEdit(id);

  const cancelBtn = document.createElement('button');
  cancelBtn.className = 'at-cancel'; cancelBtn.textContent = 'Cancel';
  cancelBtn.onclick = () => cancelEdit(id);

  const wrap = document.createElement('span');
  wrap.className = 'at-edit-wrap';
  wrap.style.cssText = 'display:flex;gap:8px;align-items:center;flex:1';
  wrap.appendChild(input);
  const btnGroup = document.createElement('span');
  btnGroup.style.cssText = 'display:flex;flex-shrink:0';
  btnGroup.appendChild(saveBtn);
  btnGroup.appendChild(cancelBtn);
  wrap.appendChild(btnGroup);

  row.style.cssText = 'display:flex;align-items:center;gap:8px';
  row.appendChild(wrap);
  input.focus();
  input.select();
}

function cancelEdit(id) {
  const row = document.getElementById('at-row-' + id);
  const wrap = row.querySelector('.at-edit-wrap');
  if (wrap) wrap.remove();
  row.style.cssText = '';
  row.querySelector('.at-title-text').style.display = '';
  row.querySelector('.at-item-actions').style.display = '';
}

async function saveEdit(id) {
  const row   = document.getElementById('at-row-' + id);
  const input = row.querySelector('.at-edit-input');
  const saveBtn = row.querySelector('.at-save');
  const newTitle = input.value.trim();
  if (!newTitle) { input.focus(); return; }
  if (newTitle === row.dataset.title) { cancelEdit(id); return; }

  saveBtn.disabled = true; saveBtn.textContent = '…';
  try {
    const fd = new FormData();
    fd.append('ajax_edit', '1');
    fd.append('id', id);
    fd.append('title', newTitle);
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      row.dataset.title = data.title;
      row.querySelector('.at-title-text').textContent = data.title;
      cancelEdit(id);
      showToast('✓ Account title updated', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      saveBtn.disabled = false; saveBtn.textContent = 'Save';
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
    saveBtn.disabled = false; saveBtn.textContent = 'Save';
  }
}

async function deleteTitle(id, btn) {
  if (!confirm('Delete this account title?')) return;
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('ajax_delete', '1');
    fd.append('id', id);
    const res  = await fetch('stella_acc_title.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      const row = document.getElementById('at-row-' + id);
      row.style.opacity = '0'; row.style.transition = 'opacity .25s';
      setTimeout(() => {
        row.remove();
        updateCount();
        const list = document.getElementById('at-list');
        if (!list.querySelector('.at-item')) {
          list.innerHTML = '<li class="at-empty" id="at-empty">No account titles yet — add one above.</li>';
        }
      }, 250);
      showToast('✓ Account title deleted', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      btn.disabled = false;
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
    btn.disabled = false;
  }
}

function updateCount() {
  document.getElementById('at-count').textContent = document.querySelectorAll('#at-list .at-item').length;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}
</script>