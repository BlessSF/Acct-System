<?php
// ============================================================
//  commissary_receivables.php — Commissary: Accounts Receivable Ledger
//  Replica of ACCOUNTS_RECEIVABLE_LEDGER_COMMI_.xlsx — one tab per
//  store, same DATE / DESCRIPTION / DEBIT / CREDIT / BALANCE core
//  columns as the source workbook, plus each store's own SALES /
//  DIRECT PURCHASES(COGS) reference columns where the sheet had them.
//  Also adds a cross-store Summary tab (outstanding balance rollup).
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Commissary branch + management can access
if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Store list is DB-backed so stores (tabs) can be added/deleted
//    at runtime — seeded once with the 14 stores from the source
//    workbook. "extra1..4" are optional SALES / DIRECT PURCHASES(COGS)
//    reference columns; null = that store doesn't track that column. ──
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_receivables_stores` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_key`     varchar(30) NOT NULL,
    `label`         varchar(100) NOT NULL,
    `extra1_label`  varchar(60) DEFAULT NULL,
    `extra2_label`  varchar(60) DEFAULT NULL,
    `extra3_label`  varchar(60) DEFAULT NULL,
    `extra4_label`  varchar(60) DEFAULT NULL,
    `sort_order`    int(11) NOT NULL DEFAULT 0,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_store_key` (`store_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ((int)$pdo->query("SELECT COUNT(*) FROM commissary_receivables_stores")->fetchColumn() === 0) {
    $DEFAULT_STORES = [
        'STELLA'    => ['label' => 'Stella',                  'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Direct Purchases (COGS)', 'extra4' => 'Purchases %'],
        'DOIS'      => ['label' => 'Dois',                     'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Direct Purchases (COGS)', 'extra4' => null],
        'SM'        => ['label' => 'Pub Express-SM',           'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Direct Purchases (COGS)', 'extra4' => null],
        'ARKEN'     => ['label' => 'Shocksisig Arken',         'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => null,                       'extra4' => null],
        'SPA'       => ['label' => 'Recovery Spa',             'extra1' => null,    'extra2' => null,      'extra3' => null,                       'extra4' => null],
        'SHOCKSISIG'=> ['label' => 'Shocksisig',               'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Grab Net Earnings + Walk In', 'extra4' => 'Purchases %'],
        'SIR_PAULO' => ['label' => 'Shocksisig Sir Paulo',     'extra1' => null,    'extra2' => null,      'extra3' => null,                       'extra4' => null],
        'DND'       => ['label' => 'DND',                      'extra1' => null,    'extra2' => null,      'extra3' => null,                       'extra4' => null],
        'ILONGGA'   => ['label' => 'Ilongga Express',          'extra1' => null,    'extra2' => null,      'extra3' => null,                       'extra4' => null],
        'HERO'      => ['label' => 'Hero',                     'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Direct Purchases (COGS)', 'extra4' => 'Purchases %'],
        'MEGAWORLD' => ['label' => 'Megaworld',                'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => 'Direct Purchases (COGS)', 'extra4' => null],
        'SIR_DEKEN' => ['label' => 'Shocksisig (Worksworth)',  'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => null,                       'extra4' => null],
        'LEDESCO'   => ['label' => 'Pub Express-Ledesco',      'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => null,                       'extra4' => null],
        'LEDESCO1'  => ['label' => 'Ledesco',                  'extra1' => 'Sales', 'extra2' => 'Sales %', 'extra3' => null,                       'extra4' => null],
    ];
    $sIns = $pdo->prepare("INSERT INTO commissary_receivables_stores (store_key, label, extra1_label, extra2_label, extra3_label, extra4_label, sort_order) VALUES (?,?,?,?,?,?,?)");
    $ord = 0;
    foreach ($DEFAULT_STORES as $key => $meta) {
        $sIns->execute([$key, $meta['label'], $meta['extra1'], $meta['extra2'], $meta['extra3'], $meta['extra4'], $ord++]);
    }
}

function loadStores(PDO $pdo): array {
    $out = [];
    $stmt = $pdo->query("SELECT * FROM commissary_receivables_stores ORDER BY sort_order ASC, id ASC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['store_key']] = [
            'label' => $r['label'],
            'extra1' => $r['extra1_label'], 'extra2' => $r['extra2_label'],
            'extra3' => $r['extra3_label'], 'extra4' => $r['extra4_label'],
        ];
    }
    return $out;
}
$STORES = loadStores($pdo);

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_receivables_ledger` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `store_key`   varchar(30) NOT NULL,
    `sort_order`  int(11) NOT NULL DEFAULT 0,
    `entry_date`  varchar(40) DEFAULT '',
    `description` varchar(255) DEFAULT '',
    `debit`       decimal(14,2) NOT NULL DEFAULT 0.00,
    `credit`      decimal(14,2) NOT NULL DEFAULT 0.00,
    `balance`     decimal(14,2) NOT NULL DEFAULT 0.00,
    `extra1`      varchar(60) DEFAULT NULL,
    `extra2`      varchar(60) DEFAULT NULL,
    `extra3`      varchar(60) DEFAULT NULL,
    `extra4`      varchar(60) DEFAULT NULL,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_store_sort` (`store_key`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Migration: older installs created extra1..4 as decimal columns,
//    which silently reject word/text values (e.g. "Waived", "N/A").
//    Widen them to varchar so Sales / Sales % / Direct Purchases(COGS)
//    can hold text as well as numbers. Safe to run on every load. ──
try {
    foreach (['extra1','extra2','extra3','extra4'] as $col) {
        $colInfo = $pdo->query("SHOW COLUMNS FROM commissary_receivables_ledger LIKE '$col'")->fetch(PDO::FETCH_ASSOC);
        if ($colInfo && stripos($colInfo['Type'], 'decimal') !== false) {
            $pdo->exec("ALTER TABLE commissary_receivables_ledger MODIFY `$col` varchar(60) DEFAULT NULL");
        }
    }
} catch (Throwable $e) { /* non-fatal — table stays usable either way */ }

// ── One-time historical import from the source workbook ───
// Runs only the first time the table is empty — seeds every recorded
// entry from ACCOUNTS_RECEIVABLE_LEDGER_COMMI_.xlsx so history shows
// up right away instead of starting blank.
try {
    $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM commissary_receivables_ledger")->fetchColumn();
    if ($rowCount === 0) {
        $seedPath = __DIR__ . '/data/receivables_ledger_seed.json';
        if (is_file($seedPath)) {
            $seed = json_decode(file_get_contents($seedPath), true) ?: [];
            $ins = $pdo->prepare("INSERT INTO commissary_receivables_ledger
                (store_key, sort_order, entry_date, description, debit, credit, balance, extra1, extra2, extra3, extra4, saved_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $pdo->beginTransaction();
            foreach ($seed as $storeKey => $storeData) {
                if (!isset($STORES[$storeKey])) continue;
                $i = 0;
                foreach ($storeData['rows'] ?? [] as $r) {
                    $ins->execute([
                        $storeKey, $i++,
                        $r['date_raw'] ?? '', $r['description'] ?? '',
                        (float)($r['debit'] ?? 0), (float)($r['credit'] ?? 0), (float)($r['balance'] ?? 0),
                        (isset($r['extra1']) && $r['extra1'] !== '') ? trim((string)$r['extra1']) : null,
                        (isset($r['extra2']) && $r['extra2'] !== '') ? trim((string)$r['extra2']) : null,
                        (isset($r['extra3']) && $r['extra3'] !== '') ? trim((string)$r['extra3']) : null,
                        (isset($r['extra4']) && $r['extra4'] !== '') ? trim((string)$r['extra4']) : null,
                        'Historical Import',
                    ]);
                }
            }
            $pdo->commit();
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

// ── AJAX: add row ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $storeKey = $_POST['store_key'] ?? '';
    if (!isset($STORES[$storeKey])) { echo json_encode(['ok'=>false,'msg'=>'Unknown store.']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),-1)+1 FROM commissary_receivables_ledger WHERE store_key=?");
        $stmt->execute([$storeKey]);
        $nextOrd = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO commissary_receivables_ledger
            (store_key, sort_order, entry_date, description, debit, credit, balance, extra1, extra2, extra3, extra4, saved_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([
            $storeKey, $nextOrd,
            trim($_POST['entry_date'] ?? ''), trim($_POST['description'] ?? ''),
            (float)($_POST['debit'] ?? 0), (float)($_POST['credit'] ?? 0), (float)($_POST['balance'] ?? 0),
            trim($_POST['extra1'] ?? '') !== '' ? trim($_POST['extra1']) : null,
            trim($_POST['extra2'] ?? '') !== '' ? trim($_POST['extra2']) : null,
            trim($_POST['extra3'] ?? '') !== '' ? trim($_POST['extra3']) : null,
            trim($_POST['extra4'] ?? '') !== '' ? trim($_POST['extra4']) : null,
            $user['name'],
        ]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: update row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }
    try {
        $pdo->prepare("UPDATE commissary_receivables_ledger SET
                entry_date=?, description=?, debit=?, credit=?, balance=?,
                extra1=?, extra2=?, extra3=?, extra4=?, saved_by=?
            WHERE id=?")->execute([
            trim($_POST['entry_date'] ?? ''), trim($_POST['description'] ?? ''),
            (float)($_POST['debit'] ?? 0), (float)($_POST['credit'] ?? 0), (float)($_POST['balance'] ?? 0),
            trim($_POST['extra1'] ?? '') !== '' ? trim($_POST['extra1']) : null,
            trim($_POST['extra2'] ?? '') !== '' ? trim($_POST['extra2']) : null,
            trim($_POST['extra3'] ?? '') !== '' ? trim($_POST['extra3']) : null,
            trim($_POST['extra4'] ?? '') !== '' ? trim($_POST['extra4']) : null,
            $user['name'], $id,
        ]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: delete row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM commissary_receivables_ledger WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: add a new store section (tab) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_store'])) {
    header('Content-Type: application/json');
    $label = trim($_POST['label'] ?? '');
    if ($label === '') { echo json_encode(['ok'=>false,'msg'=>'Store name is required.']); exit; }
    try {
        // Slugify the label into a store_key, ensuring uniqueness
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
        $base = trim($base, '_');
        if ($base === '') $base = 'STORE';
        $key = $base;
        $n = 1;
        $chk = $pdo->prepare("SELECT COUNT(*) FROM commissary_receivables_stores WHERE store_key=?");
        while (true) {
            $chk->execute([$key]);
            if ((int)$chk->fetchColumn() === 0) break;
            $key = $base . '_' . (++$n);
        }
        $ordStmt = $pdo->query("SELECT COALESCE(MAX(sort_order),-1)+1 FROM commissary_receivables_stores");
        $nextOrd = (int) $ordStmt->fetchColumn();
        $pdo->prepare("INSERT INTO commissary_receivables_stores (store_key, label, extra1_label, extra2_label, extra3_label, extra4_label, sort_order) VALUES (?,?,?,?,?,?,?)")
            ->execute([$key, $label, null, null, null, null, $nextOrd]);
        echo json_encode(['ok'=>true, 'store_key'=>$key]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: delete an entire store section (and its ledger rows) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_store'])) {
    header('Content-Type: application/json');
    $storeKey = $_POST['store_key'] ?? '';
    if ($storeKey === '') { echo json_encode(['ok'=>false,'msg'=>'Missing store.']); exit; }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM commissary_receivables_ledger WHERE store_key=?")->execute([$storeKey]);
        $pdo->prepare("DELETE FROM commissary_receivables_stores WHERE store_key=?")->execute([$storeKey]);
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Fetch data for display ─────────────────────────────────
$tab       = $_GET['tab'] ?? '';
$isSummary = ($tab === 'SUMMARY');
if ($tab === '') $tab = array_key_first($STORES) ?? 'SUMMARY';
if (!$isSummary && !isset($STORES[$tab])) $tab = array_key_first($STORES) ?? 'SUMMARY';
$isSummary = ($tab === 'SUMMARY');

$storeRows = [];
if (!$isSummary) {
    $stmt = $pdo->prepare("SELECT * FROM commissary_receivables_ledger WHERE store_key=? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$tab]);
    $storeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Summary: outstanding balance per store = balance of that store's last row
$summaryRows = [];
$summaryTotal = 0;
if ($isSummary) {
    foreach ($STORES as $key => $meta) {
        $stmt = $pdo->prepare("SELECT balance, entry_date FROM commissary_receivables_ledger WHERE store_key=? ORDER BY sort_order DESC, id DESC LIMIT 1");
        $stmt->execute([$key]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM commissary_receivables_ledger WHERE store_key=?");
        $cntStmt->execute([$key]);
        $bal = $last ? (float)$last['balance'] : 0;
        $summaryRows[$key] = ['label' => $meta['label'], 'balance' => $bal, 'last_date' => $last['entry_date'] ?? '', 'entries' => (int)$cntStmt->fetchColumn()];
        $summaryTotal += $bal;
    }
}

// Extra columns (Sales / Sales % / Direct Purchases(COGS) / Purchases %)
// can now hold either a number or a plain word/note. Format numbers with
// commas as before; pass words through untouched (still HTML-escaped).
function fmtExtra($v) {
    if ($v === null || $v === '') return '';
    return is_numeric($v) ? number_format((float)$v, 2) : htmlspecialchars($v);
}

$pageTitle  = 'Accounts Receivable';
$activePage = 'commissary_receivables';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.arl-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:16px; border-bottom:2px solid var(--border); padding-bottom:0; }
.arl-tab {
  padding:9px 14px; border-radius:8px 8px 0 0; font-family:var(--font-m); font-size:.68rem; font-weight:700;
  color:var(--subtext); text-decoration:none; border:1px solid transparent; border-bottom:none;
  transition:all .15s; white-space:nowrap;
}
.arl-tab:hover { background:#f8f9fb; color:var(--text); }
.arl-tab.active { background:var(--surface); color:var(--accent); border-color:var(--border); position:relative; top:2px; }
.arl-tab.summary-tab { margin-left:auto; }
.arl-tab.arl-new-tab { color:var(--accent); border:1px dashed var(--border); }
.arl-tab.arl-new-tab:hover { background:#eef4ff; }

.arl-controls { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:14px; justify-content:space-between; }
.arl-store-name { font-family:var(--font-m); font-size:.85rem; font-weight:800; color:var(--text); }
.arl-store-balance { font-family:var(--font-m); font-size:.75rem; font-weight:700; color:var(--accent); }

.arl-outer { width:100%; overflow-x:auto; border-radius:var(--radius); border:1px solid var(--border); background:var(--surface); box-shadow:0 1px 4px rgba(0,0,0,.06); max-height:70vh; overflow-y:auto; }
.arl-table { border-collapse:collapse; width:100%; font-size:.72rem; }
.arl-table thead th {
  background:#1e3a5f; color:#fff; font-family:var(--font-m); font-size:.6rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:8px 7px; border:1px solid #2d5480; text-align:right;
  position:sticky; top:0; z-index:2;
}
.arl-table thead th:nth-child(1), .arl-table thead th:nth-child(2) { text-align:left; }
.arl-table tbody td { padding:5px 7px; border:1px solid var(--border); text-align:right; }
.arl-table tbody td:nth-child(1), .arl-table tbody td:nth-child(2) { text-align:left; }
.arl-table tbody td:nth-child(1) { font-family:var(--font-m); font-size:.65rem; color:var(--subtext); white-space:nowrap; }
.arl-table tbody tr:nth-child(even) { background:#f9fafb; }
.arl-table tbody tr:hover { background:#eef4ff; }
.arl-table td.arl-balance { font-weight:700; color:var(--text); background:#f4f6fa; }
.arl-table tfoot td { padding:8px 7px; border:1px solid var(--border); font-family:var(--font-m); font-weight:800; text-align:right; background:#eef2ff; color:#3730a3; }
.arl-table tfoot td:first-child { text-align:left; }
.arl-table input {
  width:100%; border:1px solid var(--border); background:#fff; text-align:right; font-family:var(--font-m); font-size:.72rem;
  padding:3px 4px; border-radius:4px; box-sizing:border-box;
}
.arl-table input.txt-left { text-align:left; }
.arl-table input:focus { background:#fff8e1; outline:1px solid var(--accent); }
.arl-table td.td-act { white-space:nowrap; text-align:center; }
.arl-btn { font-family:var(--font-m); font-size:.62rem; font-weight:700; padding:3px 8px; border-radius:5px; border:1px solid var(--border); background:#fff; cursor:pointer; margin:0 2px; }
.arl-btn:hover { background:#f0f4ff; }
.arl-btn.arl-save { color:#065f46; border-color:#a7f3d0; background:#ecfdf5; }
.arl-btn.arl-del { color:#991b1b; border-color:#fecaca; background:#fef2f2; }
.arl-btn.arl-cancel { color:#374151; }

.arl-summary-block { margin-bottom:24px; }
.arl-note { font-family:var(--font-m); font-size:.65rem; color:var(--subtext2); margin:-4px 0 12px; }
</style>

<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Accounts Receivable</div>
    <div class="section-subtitle">Commissary → store receivable ledger · mirrors ACCOUNTS_RECEIVABLE_LEDGER_COMMI_.xlsx, one tab per store</div>
  </div>
</div>

<div class="arl-tabs">
  <?php foreach ($STORES as $key => $meta): ?>
  <a href="?tab=<?= urlencode($key) ?>" class="arl-tab <?= (!$isSummary && $tab===$key) ? 'active' : '' ?>"><?= htmlspecialchars($meta['label']) ?></a>
  <?php endforeach; ?>
  <a href="#" class="arl-tab arl-new-tab" onclick="createStore(event)">➕ New Store</a>
  <a href="?tab=SUMMARY" class="arl-tab summary-tab <?= $isSummary ? 'active' : '' ?>">📊 Summary</a>
</div>

<script>
async function createStore(ev) {
  ev.preventDefault();
  const label = prompt('Name of the new store / section:');
  if (!label || !label.trim()) return;
  const fd = new FormData();
  fd.append('ajax_add_store', '1');
  fd.append('label', label.trim());
  try {
    const res = await fetch('commissary_receivables.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      window.location.href = 'commissary_receivables.php?tab=' + encodeURIComponent(data.store_key);
    } else {
      alert('❌ ' + (data.msg || 'Could not create store'));
    }
  } catch (e) { alert('❌ Network error'); }
}
</script>

<?php if (!$isSummary): $meta = $STORES[$tab];
  $extraCols = [];
  foreach (['extra1','extra2','extra3','extra4'] as $ek) { if ($meta[$ek]) $extraCols[$ek] = $meta[$ek]; }
  $lastBalance = 0;
  foreach ($storeRows as $r) { if ((float)$r['balance'] !== 0.0 || $r['debit'] || $r['credit']) $lastBalance = (float)$r['balance']; }
?>

<div class="arl-controls">
  <div class="arl-store-name">🏬 <?= htmlspecialchars($meta['label']) ?> <span class="arl-note" style="margin:0"><?= count($storeRows) ?> entries</span></div>
  <div>
    <span class="arl-store-balance">Outstanding Balance: ₱<?= number_format($lastBalance, 2) ?></span>
    <button type="button" class="arl-btn arl-save" onclick="addRow()">+ Add Entry</button>
    <button type="button" class="arl-btn arl-del" onclick="deleteStore()">🗑️ Delete This Store</button>
  </div>
</div>

<div class="arl-outer">
<table class="arl-table" id="arlTable">
  <thead>
    <tr>
      <th>Date</th>
      <th>Description</th>
      <th>Debit</th>
      <th>Credit</th>
      <th>Balance</th>
      <?php foreach ($extraCols as $ek => $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?>
      <th>Action</th>
    </tr>
  </thead>
  <tbody id="arlBody">
    <?php foreach ($storeRows as $r): ?>
    <tr data-id="<?= $r['id'] ?>">
      <td data-raw="<?= htmlspecialchars($r['entry_date']) ?>"><?= htmlspecialchars($r['entry_date']) ?></td>
      <td data-raw="<?= htmlspecialchars($r['description']) ?>"><?= htmlspecialchars($r['description']) ?></td>
      <td data-raw="<?= $r['debit'] ?>"><?= $r['debit'] ? number_format($r['debit'],2) : '' ?></td>
      <td data-raw="<?= $r['credit'] ?>"><?= $r['credit'] ? number_format($r['credit'],2) : '' ?></td>
      <td class="arl-balance" data-raw="<?= $r['balance'] ?>"><?= $r['balance'] ? number_format($r['balance'],2) : '' ?></td>
      <?php foreach ($extraCols as $ek => $label): $v = $r[$ek]; ?>
      <td data-raw="<?= htmlspecialchars((string)$v) ?>"><?= fmtExtra($v) ?></td>
      <?php endforeach; ?>
      <td class="td-act">
        <button class="arl-btn" onclick="editRow(this)">✏️ Edit</button>
        <button class="arl-btn arl-del" onclick="deleteRow(this)">🗑️ Del</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">Total</td>
      <td><?= number_format(array_sum(array_column($storeRows,'debit')),2) ?></td>
      <td><?= number_format(array_sum(array_column($storeRows,'credit')),2) ?></td>
      <td>₱<?= number_format($lastBalance,2) ?></td>
      <?php foreach ($extraCols as $ek => $label):
        $numericVals = array_filter(array_column($storeRows,$ek), 'is_numeric');
      ?><td><?= $numericVals ? number_format(array_sum($numericVals),2) : '—' ?></td><?php endforeach; ?>
      <td></td>
    </tr>
  </tfoot>
</table>
</div>

<script>
const STORE_KEY  = '<?= $tab ?>';
const ENDPOINT   = 'commissary_receivables.php';
const EXTRA_KEYS = <?= json_encode(array_keys($extraCols)) ?>;
const N_EXTRA    = EXTRA_KEYS.length;

const flt = v => parseFloat(String(v ?? '').replace(/,/g,'')) || 0;
const fmt = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

function editRow(btn) {
  const tr = btn.closest('tr');
  if (tr.classList.contains('editing')) return;
  tr.classList.add('editing');
  const tds = tr.querySelectorAll('td');
  // tds[0]=date 1=desc 2=debit 3=credit 4=balance ... extras ... last=action
  const mk = (i, cls, type) => {
    const raw = tds[i].dataset.raw || '';
    tds[i].innerHTML = `<input type="${type}" class="${cls}" value="${raw.replace(/"/g,'&quot;')}">`;
  };
  mk(0, 'txt-left', 'text');
  mk(1, 'txt-left', 'text');
  mk(2, '', 'number');
  mk(3, '', 'number');
  mk(4, '', 'number');
  // Extra columns (Sales / Sales % / Direct Purchases(COGS) / Purchases %)
  // accept words as well as numbers, so they're plain text inputs.
  for (let i = 0; i < N_EXTRA; i++) mk(5 + i, 'txt-left', 'text');
  const actTd = tds[tds.length - 1];
  actTd.innerHTML = `<button class="arl-btn arl-save" onclick="saveRow(this)">💾 Save</button><button class="arl-btn arl-cancel" onclick="location.reload()">✖ Cancel</button>`;
}

function addRow() {
  const tbody = document.getElementById('arlBody');
  const tr = document.createElement('tr');
  tr.dataset.id = '';
  tr.classList.add('editing');
  let h = `<td><input type="text" class="txt-left" value=""></td>`;
  h += `<td><input type="text" class="txt-left" value=""></td>`;
  h += `<td><input type="number" value=""></td>`;
  h += `<td><input type="number" value=""></td>`;
  h += `<td><input type="number" value=""></td>`;
  for (let i = 0; i < N_EXTRA; i++) h += `<td><input type="text" class="txt-left" value=""></td>`;
  h += `<td class="td-act"><button class="arl-btn arl-save" onclick="saveRow(this)">💾 Save</button><button class="arl-btn arl-cancel" onclick="this.closest('tr').remove()">✖ Cancel</button></td>`;
  tr.innerHTML = h;
  tbody.appendChild(tr);
  tr.scrollIntoView({behavior:'smooth', block:'center'});
  tr.querySelector('input')?.focus();
}

async function saveRow(btn) {
  const tr = btn.closest('tr');
  const id = tr.dataset.id;
  const inputs = tr.querySelectorAll('input');
  const fd = new FormData();
  fd.append(id ? 'ajax_update' : 'ajax_add', '1');
  if (id) fd.append('id', id);
  fd.append('store_key', STORE_KEY);
  fd.append('entry_date', inputs[0].value.trim());
  fd.append('description', inputs[1].value.trim());
  fd.append('debit', flt(inputs[2].value));
  fd.append('credit', flt(inputs[3].value));
  fd.append('balance', flt(inputs[4].value));
  // Send extra-column values as-is (they may be words, not numbers).
  for (let i = 0; i < N_EXTRA; i++) fd.append(EXTRA_KEYS[i], inputs[5+i].value.trim());

  btn.textContent = '…';
  try {
    const res  = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast('✓ Saved', 'success');
      location.reload();
    } else {
      showToast('❌ ' + (data.msg || 'Save failed'), 'error');
      btn.textContent = '💾 Save';
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
    btn.textContent = '💾 Save';
  }
}

async function deleteRow(btn) {
  if (!confirm('Delete this entry?')) return;
  const tr = btn.closest('tr');
  const id = tr.dataset.id;
  if (!id) { tr.remove(); return; }
  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('id', id);
  try {
    const res  = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast('✓ Deleted', 'success');
      location.reload();
    } else {
      showToast('❌ ' + (data.msg || 'Delete failed'), 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

async function deleteStore() {
  if (!confirm('Delete the "' + <?= json_encode($meta['label']) ?> + '" store section? This removes ALL of its ledger entries permanently.')) return;
  const fd = new FormData();
  fd.append('ajax_delete_store', '1');
  fd.append('store_key', STORE_KEY);
  try {
    const res  = await fetch(ENDPOINT, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      showToast('✓ Store deleted', 'success');
      window.location.href = 'commissary_receivables.php';
    } else {
      showToast('❌ ' + (data.msg || 'Delete failed'), 'error');
    }
  } catch (e) { showToast('❌ Network error', 'error'); }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>

<?php else: /* ── SUMMARY TAB ─────────────────────────────── */ ?>

<div class="arl-summary-block">
  <div class="section-subtitle" style="margin-bottom:10px">🧾 Outstanding balance per store — current balance is that store's most recent ledger entry</div>
  <div class="arl-outer" style="max-height:none">
  <table class="arl-table">
    <thead><tr><th>Store</th><th>Entries</th><th>Last Entry Date</th><th>Outstanding Balance</th></tr></thead>
    <tbody>
      <?php foreach ($STORES as $key => $meta): $s = $summaryRows[$key]; ?>
      <tr>
        <td><a href="?tab=<?= urlencode($key) ?>"><?= htmlspecialchars($meta['label']) ?></a></td>
        <td><?= (int)$s['entries'] ?></td>
        <td><?= htmlspecialchars($s['last_date']) ?></td>
        <td class="arl-balance">₱<?= number_format($s['balance'],2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3">Total Outstanding</td><td>₱<?= number_format($summaryTotal,2) ?></td></tr></tfoot>
  </table>
  </div>
</div>

<?php endif; ?>
