<?php
// ============================================================
//  vendor_masterlist.php — Shared Vendor Masterlist (all branches)
//  One record per vendor. No branch separation.
//  Columns: No. | TIN | Name of Suppliers | V/NV | Address |
//           Particulars | Document Type | Contact | Notes
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$pdo  = getPDO();
$user = currentUser();

// ── Migrate: create unified table if not yet done ──────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `vendor_masterlist_unified` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `tin`           varchar(100) DEFAULT '',
    `company_name`  varchar(255) DEFAULT '',
    `vat_status`    varchar(10)  DEFAULT 'V',
    `address`       varchar(255) DEFAULT '',
    `particulars`   varchar(255) DEFAULT '',
    `document_type` varchar(100) DEFAULT '',
    `contact`       varchar(150) DEFAULT '',
    `notes`         text         DEFAULT NULL,
    `saved_by`      varchar(100) DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_tin` (`tin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── One-time migration: pull unique vendors from old table ──
$migrated = $pdo->query("SELECT COUNT(*) FROM vendor_masterlist_unified")->fetchColumn();
if ((int)$migrated === 0) {
    // Check if old table exists and has data
    try {
        $oldCount = $pdo->query("SELECT COUNT(*) FROM vendor_masterlist")->fetchColumn();
        if ((int)$oldCount > 0) {
            // Insert deduplicated records (group by TIN, pick latest)
            $pdo->exec("INSERT INTO vendor_masterlist_unified
                        (tin, company_name, vat_status, address, particulars, document_type, contact, notes, saved_by, created_at)
                        SELECT tin, company_name, vat_status, address, particulars, document_type, contact, notes, saved_by, MAX(created_at)
                        FROM vendor_masterlist
                        GROUP BY tin, company_name
                        ORDER BY company_name ASC");
        }
    } catch (Throwable $e) { /* old table may not exist */ }
}

$TXT_COLS = ['tin','company_name','vat_status','address','particulars','document_type','contact','notes'];

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = [];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO vendor_masterlist_unified (" . implode(',', array_map(fn($f)=>"`$f`", $fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($TXT_COLS as $f) {
        $sets[] = "`$f`=?";
        $vals[] = trim($_POST[$f] ?? '');
    }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE vendor_masterlist_unified SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok' => true]);
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
        $pdo->prepare("DELETE FROM vendor_masterlist_unified WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Clear All ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_clear_all'])) {
    header('Content-Type: application/json');
    // Only admin and management can clear all
    try {
        $pdo->exec("DELETE FROM vendor_masterlist_unified");
        $pdo->exec("ALTER TABLE vendor_masterlist_unified AUTO_INCREMENT = 1");
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Import from Excel (bulk upsert) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_import'])) {
    header('Content-Type: application/json');
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows) || !count($rows)) {
        echo json_encode(['ok' => false, 'msg' => 'No rows received']);
        exit;
    }
    $added = 0; $updated = 0; $skipped = 0;
    $sql = "INSERT INTO vendor_masterlist_unified
                (tin, company_name, vat_status, address, particulars, document_type, contact, notes, saved_by)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                company_name  = VALUES(company_name),
                vat_status    = VALUES(vat_status),
                particulars   = VALUES(particulars),
                document_type = VALUES(document_type),
                contact       = VALUES(contact),
                notes         = VALUES(notes),
                saved_by      = VALUES(saved_by)";

    // Add unique key on tin+address combo (safe migration)
    // Same company name allowed — different addresses = different branches
    try {
        $pdo->exec("ALTER TABLE vendor_masterlist_unified DROP INDEX `ux_tin`");
    } catch (Throwable $ignored) {}
    try {
        $pdo->exec("ALTER TABLE vendor_masterlist_unified ADD UNIQUE KEY `ux_tin_address` (`tin`(100), `address`(150))");
    } catch (Throwable $ignored) {}

    $stmt = $pdo->prepare($sql);
    foreach ($rows as $r) {
        $tin = trim($r['tin'] ?? '');
        if (!$tin) { $skipped++; continue; }
        try {
            $stmt->execute([
                $tin,
                trim($r['company_name']  ?? ''),
                trim($r['vat_status']    ?? 'V'),
                trim($r['address']       ?? ''),
                trim($r['particulars']   ?? ''),
                trim($r['document_type'] ?? ''),
                trim($r['contact']       ?? ''),
                trim($r['notes']         ?? ''),
                $user['name'],
            ]);
            if ($stmt->rowCount() === 1) $added++;
            else $updated++;
        } catch (Throwable $e) { $skipped++; }
    }
    echo json_encode(['ok' => true, 'added' => $added, 'updated' => $updated, 'skipped' => $skipped]);
    exit;
}


if (isset($_GET['export_csv'])) {
    $where  = ''; $params = [];
    $fSearch = trim($_GET['q'] ?? '');
    if ($fSearch) {
        $where = "WHERE company_name LIKE ? OR tin LIKE ? OR address LIKE ? OR particulars LIKE ?";
        $params = array_fill(0, 4, "%$fSearch%");
    }
    $stmt = $pdo->prepare("SELECT * FROM vendor_masterlist_unified $where ORDER BY company_name ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Vendor_Masterlist_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vendor Masterlist — Multipliers Corp (All Branches)']);
    fputcsv($out, ['Generated: ' . date('F d, Y H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['No.', 'TIN', 'Name of Suppliers', 'V/NV', 'Address', 'Particulars', 'Document Type', 'Contact', 'Notes', 'Last Updated By']);
    foreach ($rows as $i => $r) {
        fputcsv($out, [
            $i + 1, $r['tin'], $r['company_name'], $r['vat_status'],
            $r['address'], $r['particulars'], $r['document_type'],
            $r['contact'], $r['notes'], $r['saved_by']
        ]);
    }
    fclose($out);
    exit;
}

// ── AJAX: Fetch page (JSON) ───────────────────────────────
if (isset($_GET['ajax_page'])) {
    header('Content-Type: application/json');
    $q      = trim($_GET['q'] ?? '');
    $page   = max(1, (int)($_GET['p'] ?? 1));
    $limit  = min(200, max(10, (int)($_GET['per'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $where = ''; $params = [];
    if ($q) {
        $where  = "WHERE company_name LIKE ? OR tin LIKE ? OR address LIKE ? OR particulars LIKE ?";
        $params = array_fill(0, 4, "%$q%");
    }

    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM vendor_masterlist_unified $where")
                      ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM vendor_masterlist_unified $where")->execute($params) : 0;
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM vendor_masterlist_unified $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $dataStmt = $pdo->prepare("SELECT * FROM vendor_masterlist_unified $where ORDER BY company_name ASC, id ASC LIMIT $limit OFFSET $offset");
    $dataStmt->execute($params);
    $rows = $dataStmt->fetchAll();

    echo json_encode([
        'ok'         => true,
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $limit,
        'total_pages'=> (int)ceil($total / $limit),
        'offset'     => $offset,
    ]);
    exit;
}

// ── Initial page load: just get total count + first page ──
$fSearch  = trim($_GET['q'] ?? '');
$fPage    = max(1, (int)($_GET['p'] ?? 1));
$fPerPage = min(200, max(10, (int)($_GET['per'] ?? 50)));

$where = ''; $params = [];
if ($fSearch) {
    $where  = "WHERE company_name LIKE ? OR tin LIKE ? OR address LIKE ? OR particulars LIKE ?";
    $params = array_fill(0, 4, "%$fSearch%");
}
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM vendor_masterlist_unified $where");
$cntStmt->execute($params);
$totalCount = (int)$cntStmt->fetchColumn();

// Load only first page server-side for initial render
$offset = ($fPage - 1) * $fPerPage;
$dataStmt = $pdo->prepare("SELECT * FROM vendor_masterlist_unified $where ORDER BY company_name ASC, id ASC LIMIT $fPerPage OFFSET $offset");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$pageTitle  = 'Vendor Masterlist';
$activePage = 'vendor_masterlist';
include 'layout.php';
?>

<style>
.vm-outer {
  width: 100%; overflow-x: auto; overflow-y: visible;
  border-radius: var(--radius); border: 2px solid #c8a800;
  background: var(--surface);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 2px 8px rgba(0,0,0,.10);
}
.vm-outer::-webkit-scrollbar { height: 8px; }
.vm-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.vm-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

.vm-table { border-collapse: collapse; width: 100%; min-width: 960px; font-size: .75rem; }

/* Gold Excel-style header */
.vm-table thead th {
  background: #FFD700;
  color: #1a1a1a;
  font-family: var(--font-m); font-size: .60rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em;
  padding: 10px 10px; border: 1px solid #c8a800;
  white-space: nowrap; text-align: center;
  position: sticky; top: 0; z-index: 20;
}

.th-no   { width: 42px;  min-width: 42px; }
.th-tin  { width: 150px; min-width: 150px; }
.th-co   { width: 220px; min-width: 220px; }
.th-vnv  { width: 64px;  min-width: 64px; }
.th-addr { width: 220px; min-width: 220px; }
.th-part { width: 170px; min-width: 170px; }
.th-doc  { width: 130px; min-width: 130px; }
.th-ct   { width: 140px; min-width: 140px; }
.th-nt   { width: 160px; min-width: 160px; }
.th-act  { width: 90px;  min-width: 90px; position: sticky; right: 0; z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,.08); }
.td-act-sticky { position: sticky; right: 0; z-index: 4; box-shadow: -2px 0 4px rgba(0,0,0,.08); }

/* Excel alternating rows */
.vm-table tbody tr { border-bottom: 1px solid #d0d8e0; transition: background .1s; }
.vm-table tbody tr td { background: #ffffff; }
.vm-table tbody tr td.td-act-sticky { background: #ffffff; }
.vm-table tbody tr:nth-child(even) td { background: #dce9f7; }
.vm-table tbody tr:nth-child(even) td.td-act-sticky { background: #dce9f7; }
.vm-table tbody tr:hover td { background: #c5dcf5 !important; }
.vm-table thead th.th-act { background: #FFD700; }
.vm-table td { border: 1px solid #c8d4df; padding: 0; vertical-align: middle; }

.vmi {
  width: 100%; padding: 7px 8px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-h); font-size: .75rem;
  display: block; box-sizing: border-box;
}
.vmi:focus { background: rgba(15,123,92,.06); outline: 1px solid rgba(15,123,92,.35); }
.vmi.mono { font-family: var(--font-m); font-size: .72rem; }

.td-no {
  text-align: center; font-family: var(--font-m); font-size: .68rem;
  color: #555; padding: 0 6px; background: #f0f4f8 !important; font-weight: 600;
}

.vmi-sel {
  width: 100%; padding: 6px 4px;
  background: transparent; border: none; outline: none;
  font-family: var(--font-m); font-size: .72rem; font-weight: 700;
  text-align: center; cursor: pointer;
  appearance: none; -webkit-appearance: none;
}
.vmi-sel:focus { background: rgba(15,123,92,.06); }

.bsv {
  padding: 4px 8px; font-size: .6rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%; margin-bottom: 3px;
}
.bsv:hover { background: #dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok  { background:#dcfce7; color:#15803d; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }

.bdel {
  padding: 4px 8px; font-size: .6rem;
  font-family: var(--font-m); font-weight: 700;
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%;
}
.bdel:hover { background: #ffe4e6; }

.vm-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.btn-add-row {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 16px; background: var(--accent); color: #fff;
  border: none; border-radius: 8px; font-size: .8rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #0a6649; transform: translateY(-1px); }

.tfoot-row td {
  background: #FFD700; color: #1a1a1a;
  font-family: var(--font-m); font-size: .65rem; font-weight: 800;
  padding: 8px 10px; border: 1px solid #c8a800; text-align: left;
}

.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: #92400e;
  text-align: center; padding: 5px 12px; border-bottom: 1px solid #c8a800;
  background: #fffbea;
}
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
.empty-state {
  text-align: center; padding: 60px 20px;
  font-family: var(--font-m); font-size: .78rem; color: var(--subtext);
}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title">Vendor <span>Masterlist</span></div>
    <div class="section-subtitle">One shared registry for all branches · <span id="vm-total-badge"><?= $totalCount ?></span> vendor<?= $totalCount !== 1 ? 's' : '' ?> total</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="vm-controls" id="filterForm">
  <input type="text" name="q" class="form-control" placeholder="Search vendor, TIN, address…"
         value="<?= htmlspecialchars($fSearch) ?>" style="max-width:280px">
  <button type="submit" class="btn btn-ghost btn-sm">Search</button>
  <?php if ($fSearch): ?>
  <a href="vendor_masterlist.php" class="btn btn-ghost btn-sm">Reset</a>
  <?php endif; ?>
  <button type="button" class="btn-add-row" onclick="addRow()">+ Add Vendor</button>
  <a href="vendor_masterlist.php?export_csv=1<?= $fSearch ? '&q='.urlencode($fSearch) : '' ?>"
     class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)">
    ⬇ Download CSV
  </a>
</form>
<button type="button" onclick="confirmClearAll()"
        style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
               background:#dc3545;color:#fff;border:none;border-radius:8px;
               font-family:var(--font-h);font-size:.78rem;font-weight:700;cursor:pointer;
               transition:background .15s;box-shadow:0 2px 6px rgba(220,53,69,.3)"
        onmouseover="this.style.background='#b02a37'" onmouseout="this.style.background='#dc3545'">
  🗑 Clear All
</button>
<button type="button" onclick="openImport()"
        style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
               background:#1D6F42;color:#fff;border:none;border-radius:8px;
               font-family:var(--font-h);font-size:.78rem;font-weight:700;cursor:pointer;
               transition:background .15s;box-shadow:0 2px 6px rgba(29,111,66,.3)"
        onmouseover="this.style.background='#155231'" onmouseout="this.style.background='#1D6F42'">
  📥 Import Excel
</button>
<span style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
  <strong><?= count($rows) ?></strong> vendor<?= count($rows) !== 1 ? 's' : '' ?>
</span>
<span style="display:inline-flex;align-items:center;gap:6px;font-family:var(--font-m);font-size:.72rem;color:var(--subtext);margin-left:10px">
  Show
  <select id="vm-page-size" class="form-control" style="padding:4px 8px;width:auto" onchange="vmChangePageSize(this.value)">
    <option value="25">25</option>
    <option value="50">50</option>
    <option value="100">100</option>
    <option value="all">All</option>
  </select>
</span>

<!-- Table -->
<div class="vm-outer">
  <?php if (count($rows) > 8): ?>
  <div class="scroll-hint">← Scroll horizontally to see all columns →</div>
  <?php endif; ?>
  <table class="vm-table" id="vmt">
    <thead>
      <tr>
        <th class="th-no">NO.</th>
        <th class="th-tin">TIN</th>
        <th class="th-co">NAME OF SUPPLIERS</th>
        <th class="th-vnv">V / NV</th>
        <th class="th-addr">ADDRESS</th>
        <th class="th-part">PARTICULARS / DESC.</th>
        <th class="th-doc">DOCUMENT TYPE</th>
        <th class="th-ct">CONTACT</th>
        <th class="th-nt">NOTES</th>
        <th class="th-act">ACTION</th>
      </tr>
    </thead>
    <tbody id="vm-tbody">
    <?php if ($rows): ?>
      <?php foreach ($rows as $i => $r): ?>
      <tr id="row<?= $r['id'] ?>" data-id="<?= $r['id'] ?>">
        <?= renderVMRow($r, $i + 1) ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr id="empty-row">
        <td colspan="10" class="empty-state">
          No vendors yet — click <strong>+ Add Vendor</strong> to get started
        </td>
      </tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="tfoot-row">
        <td colspan="10">
          TOTAL: <span id="vm-count"><?= $totalCount ?></span> vendor<?= $totalCount !== 1 ? 's' : '' ?> — Shared across all branches
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="vm-pagination" style="display:flex;align-items:center;gap:12px;justify-content:space-between;flex-wrap:wrap;margin-top:10px;padding:8px 2px">
  <span id="vm-page-info" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
  <span style="display:inline-flex;align-items:center;gap:8px">
    <label style="font-family:var(--font-m);font-size:.7rem;color:var(--subtext)">Per page:</label>
    <select id="vm-page-size" class="form-control" style="padding:4px 8px;width:auto" onchange="vmChangePageSize(this.value)">
      <option value="25">25</option>
      <option value="50" selected>50</option>
      <option value="100">100</option>
      <option value="200">200</option>
    </select>
    <button type="button" id="vm-prev-btn" class="btn btn-ghost btn-sm" onclick="vmPrevPage()">‹ Prev</button>
    <span id="vm-page-label" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
    <button type="button" id="vm-next-btn" class="btn btn-ghost btn-sm" onclick="vmNextPage()">Next ›</button>
  </span>
</div>

<div style="margin-top:12px">
  <button class="btn-add-row" onclick="addRow()">+ Add Vendor</button>
</div>

<?php
function renderVMRow(array $r, int $no): string {
    $d = fn($k) => htmlspecialchars($r[$k] ?? '');
    $vatVal = $r['vat_status'] ?? 'V';

    $html  = '<td class="td-no">' . $no . '</td>';
    $html .= '<td><input type="text" class="vmi mono" data-col="tin"           value="' . $d('tin')           . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="company_name"  value="' . $d('company_name')  . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td style="text-align:center">';
    $html .= '<select class="vmi-sel" data-col="vat_status" onchange="markDirty(this)">';
    $html .= '<option value="V"'  . ($vatVal === 'V'  ? ' selected' : '') . ' style="color:#15803d">V</option>';
    $html .= '<option value="NV"' . ($vatVal === 'NV' ? ' selected' : '') . ' style="color:#b45309">NV</option>';
    $html .= '</select></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="address"       value="' . $d('address')       . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="particulars"   value="' . $d('particulars')   . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="document_type" value="' . $d('document_type') . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="contact"       value="' . $d('contact')       . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td><input type="text" class="vmi"      data-col="notes"         value="' . $d('notes')         . '" placeholder="—" oninput="markDirty(this)"></td>';
    $html .= '<td class="td-act-sticky" style="padding:4px 5px;text-align:center">';
    $html .= '<button class="bsv" onclick="saveRow(this)">Update</button>';
    $html .= '<button class="bdel" onclick="deleteRow(this)">Del</button>';
    $html .= '</td>';
    return $html;
}
?>

<script>
let newRowCounter = 0;
// ── Server-side pagination state ──────────────────────────
let vmCurrentPage = <?= $fPage ?>;
let vmPerPage     = <?= $fPerPage ?>;
let vmTotal       = <?= $totalCount ?>;
let vmTotalPages  = Math.max(1, Math.ceil(vmTotal / vmPerPage));
let vmQuery       = <?= json_encode($fSearch) ?>;
let vmLoading     = false;

function markDirty(el) {
    const row = el.closest('tr');
    const btn = row?.querySelector('.bsv');
    if (btn) { btn.textContent = row.dataset.id ? 'Update*' : 'Save'; btn.className = 'bsv'; }
}

// ── Server-side pagination ────────────────────────────────
async function vmLoadPage(page, perPage, q) {
    if (vmLoading) return;
    vmLoading = true;
    page    = Math.max(1, page);
    perPage = perPage || vmPerPage;
    q       = (q !== undefined) ? q : vmQuery;

    const tbody = document.getElementById('vm-tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Loading…</td></tr>';

    try {
        const url = `vendor_masterlist.php?ajax_page=1&p=${page}&per=${perPage}&q=${encodeURIComponent(q)}`;
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error');

        vmCurrentPage = data.page;
        vmPerPage     = data.per_page;
        vmTotal       = data.total;
        vmTotalPages  = data.total_pages;
        vmQuery       = q;

        // Re-render rows
        tbody.innerHTML = '';
        if (!data.rows.length) {
            tbody.innerHTML = '<tr id="empty-row"><td colspan="10" class="empty-state">No vendors found</td></tr>';
        } else {
            data.rows.forEach((r, i) => {
                const tr = document.createElement('tr');
                tr.id         = 'row' + r.id;
                tr.dataset.id = r.id;
                tr.innerHTML  = buildRowHTML(r, data.offset + i + 1);
                tbody.appendChild(tr);
            });
        }
        vmUpdateUI();
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="10" class="empty-state" style="color:#dc2626">Error: ${e.message}</td></tr>`;
    }
    vmLoading = false;
}

function buildRowHTML(r, no) {
    const d  = k => (r[k] || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const vV = (r.vat_status || 'V') === 'V';
    return `
      <td class="td-no">${no}</td>
      <td><input type="text" class="vmi mono" data-col="tin"           value="${d('tin')}"           placeholder="—" oninput="markDirty(this)"></td>
      <td><div class="ac-wrap"><input type="text" class="vmi" data-col="company_name" value="${d('company_name')}" placeholder="—" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td style="text-align:center"><select class="vmi-sel" data-col="vat_status" onchange="markDirty(this)">
        <option value="V"  style="color:#15803d" ${vV  ? 'selected' : ''}>V</option>
        <option value="NV" style="color:#b45309" ${!vV ? 'selected' : ''}>NV</option>
      </select></td>
      <td><input type="text" class="vmi" data-col="address"       value="${d('address')}"       placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi" data-col="particulars"   value="${d('particulars')}"   placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi" data-col="document_type" value="${d('document_type')}" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi" data-col="contact"       value="${d('contact')}"       placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi" data-col="notes"         value="${d('notes')}"         placeholder="—" oninput="markDirty(this)"></td>
      <td class="td-act-sticky" style="padding:4px 5px;text-align:center">
        <button class="bsv" onclick="saveRow(this)">Update</button>
        <button class="bdel" onclick="deleteRow(this)">Del</button>
      </td>`;
}

function vmUpdateUI() {
    const start = (vmCurrentPage - 1) * vmPerPage + 1;
    const end   = Math.min(vmCurrentPage * vmPerPage, vmTotal);
    document.getElementById('vm-page-info').textContent =
        vmTotal === 0 ? 'No vendors' : `Showing ${start}–${end} of ${vmTotal}`;
    document.getElementById('vm-page-label').textContent =
        vmTotal === 0 ? '' : `Page ${vmCurrentPage} of ${vmTotalPages}`;
    document.getElementById('vm-prev-btn').disabled = vmCurrentPage <= 1;
    document.getElementById('vm-next-btn').disabled = vmCurrentPage >= vmTotalPages;
    document.getElementById('vm-count').textContent = vmTotal;
    const badge = document.getElementById('vm-total-badge');
    if (badge) badge.textContent = vmTotal;
}

function vmChangePageSize(val) {
    vmPerPage = parseInt(val);
    vmLoadPage(1, vmPerPage, vmQuery);
}

function vmPrevPage() { if (vmCurrentPage > 1) vmLoadPage(vmCurrentPage - 1); }
function vmNextPage() { if (vmCurrentPage < vmTotalPages) vmLoadPage(vmCurrentPage + 1); }

// Search override: intercept form submit → AJAX instead of page reload
document.addEventListener('DOMContentLoaded', () => {
    vmUpdateUI();
    const form = document.getElementById('filterForm');
    if (form) {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const q = form.querySelector('[name="q"]')?.value.trim() || '';
            vmLoadPage(1, vmPerPage, q);
        });
        // Reset button
        const resetA = form.querySelector('a[href="vendor_masterlist.php"]');
        if (resetA) {
            resetA.addEventListener('click', e => {
                e.preventDefault();
                form.querySelector('[name="q"]').value = '';
                vmLoadPage(1, vmPerPage, '');
            });
        }
    }
});

function addRow() {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('vm-tbody');
    const tr = document.createElement('tr');
    tr.id = 'new_' + (++newRowCounter);
    tr.dataset.id = '';
    tr.innerHTML = `
      <td class="td-no">*</td>
      <td><input type="text" class="vmi mono" data-col="tin"           value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi"      data-col="company_name"  value="" placeholder="—" oninput="markDirty(this)"></td>
      <td style="text-align:center"><select class="vmi-sel" data-col="vat_status" onchange="markDirty(this)">
        <option value="V" style="color:#15803d">V</option>
        <option value="NV" style="color:#b45309">NV</option>
      </select></td>
      <td><input type="text" class="vmi"      data-col="address"       value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi"      data-col="particulars"   value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi"      data-col="document_type" value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi"      data-col="contact"       value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="vmi"      data-col="notes"         value="" placeholder="—" oninput="markDirty(this)"></td>
      <td class="td-act-sticky" style="padding:4px 5px;text-align:center">
        <button class="bsv" onclick="saveRow(this)">Save</button>
      </td>`;
    tbody.insertBefore(tr, tbody.firstChild); // add to top
    tr.querySelector('[data-col="tin"]')?.focus();
}

async function saveRow(btn) {
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    btn.textContent = '…'; btn.className = 'bsv saving';
    const fd = new FormData();
    fd.append(id ? 'ajax_update' : 'ajax_add', '1');
    if (id) fd.append('id', id);
    row.querySelectorAll('[data-col]').forEach(el => fd.append(el.dataset.col, el.value || ''));
    try {
        const res  = await fetch('vendor_masterlist.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            btn.textContent = 'Update'; btn.className = 'bsv ok';
            if (!id && data.id) {
                row.dataset.id = data.id;
                row.id = 'row' + data.id;
                const del = document.createElement('button');
                del.className = 'bdel'; del.textContent = 'Del';
                del.onclick = function() { deleteRow(this); };
                btn.parentElement.appendChild(del);
            }
            setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
            showToast('✓ Vendor saved', 'success');
            updateCount();
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

async function deleteRow(btn) {
    if (!confirm('Delete this vendor from the shared masterlist?')) return;
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    if (!id) { row.remove(); vmTotal = Math.max(0, vmTotal - 1); vmUpdateUI(); return; }
    const fd = new FormData();
    fd.append('ajax_delete', '1'); fd.append('id', id);
    try {
        const res  = await fetch('vendor_masterlist.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            row.style.opacity = '0'; row.style.transition = 'opacity .3s';
            setTimeout(() => {
                row.remove();
                vmTotal = Math.max(0, vmTotal - 1);
                // if last row on page, go to prev page
                const remaining = document.querySelectorAll('#vm-tbody tr[data-id]').length;
                if (remaining === 0 && vmCurrentPage > 1) vmLoadPage(vmCurrentPage - 1);
                else vmUpdateUI();
            }, 300);
            showToast('✓ Vendor deleted', 'success');
        } else { showToast('❌ ' + data.msg, 'error'); }
    } catch (e) { showToast('❌ Network error', 'error'); }
}

function updateCount() {
    // kept for compatibility — now uses server total
    vmUpdateUI();
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'flash flash-' + (type || 'success') + ' toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

async function confirmClearAll() {
    // First confirmation
    if (!confirm('⚠️ CLEAR ALL VENDORS?\n\nThis will permanently delete every vendor from the shared masterlist across all branches.\n\nThis action cannot be undone.')) return;
    // Second confirmation to prevent accidents
    const typed = prompt('Type DELETE to confirm clearing all vendor records:');
    if (typed !== 'DELETE') {
        if (typed !== null) showToast('❌ Cancelled — you must type DELETE exactly', 'error');
        return;
    }
    try {
        const fd = new FormData();
        fd.append('ajax_clear_all', '1');
        const res  = await fetch('vendor_masterlist.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            showToast('✓ All vendor records cleared', 'success');
            vmTotal = 0; vmCurrentPage = 1;
            vmLoadPage(1, vmPerPage, '');
        } else {
            showToast('❌ ' + (data.msg || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Network error', 'error');
    }
}

// ── Initial render ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => vmUpdateUI());
</script>


<!-- ══ IMPORT MODAL ══════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
#import-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9998;
  background: rgba(0,0,0,.5);
  align-items: center;
  justify-content: center;
}
#import-modal.is-open { display: flex !important; }
#import-inner {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 8px 40px rgba(0,0,0,.25);
  width: 90%;
  max-width: 920px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
#drop-zone {
  margin: 18px 22px;
  border: 2px dashed #1D6F42;
  border-radius: 10px;
  padding: 32px 20px;
  text-align: center;
  cursor: pointer;
  transition: background .15s;
}
#drop-zone:hover, #drop-zone.dragover { background: #f0fdf4; }
#sheet-selector { display: none; padding: 0 22px 10px; align-items: center; gap: 10px; }
#sheet-selector.show { display: flex; }
#preview-wrap { display: none; flex: 1; overflow: auto; padding: 0 22px 10px; }
#preview-wrap.show { display: block; }
#import-btn:disabled { opacity: .4; cursor: not-allowed; }
</style>

<div id="import-modal">
  <div id="import-inner">

    <!-- Header -->
    <div style="padding:18px 22px 14px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
      <div>
        <div style="font-family:var(--font-h);font-size:1rem;font-weight:800;color:#1a1d23">📥 Import Vendors from Excel</div>
        <div style="font-family:var(--font-m);font-size:.72rem;color:#6b7280;margin-top:3px">
          Supported columns: TIN · NAME OF SUPPLIERS · V/NV · ADDRESS · PARTICULARS · DOCUMENT TYPE · CONTACT · NOTES
        </div>
      </div>
      <button id="modal-close-x" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#6b7280;line-height:1;padding:4px 8px">✕</button>
    </div>

    <!-- Drop zone -->
    <div id="drop-zone" style="flex-shrink:0">
      <div style="font-size:2rem;margin-bottom:8px">📊</div>
      <div style="font-family:var(--font-h);font-size:.88rem;font-weight:700;color:#1D6F42">Click to choose file or drag &amp; drop</div>
      <div style="font-family:var(--font-m);font-size:.70rem;color:#6b7280;margin-top:4px">.xlsx or .xls files supported</div>
      <input type="file" id="xl-file" accept=".xlsx,.xls" style="display:none">
    </div>

    <!-- Sheet selector -->
    <div id="sheet-selector" style="flex-shrink:0">
      <label style="font-family:var(--font-m);font-size:.75rem;font-weight:700;color:#374151">Sheet:</label>
      <select id="sheet-select" style="font-family:var(--font-m);font-size:.75rem;padding:5px 10px;border:1px solid #d1d5db;border-radius:6px"></select>
      <span id="row-count-badge" style="font-family:var(--font-m);font-size:.70rem;color:#6b7280"></span>
    </div>

    <!-- Preview table -->
    <div id="preview-wrap">
      <div style="font-family:var(--font-m);font-size:.68rem;color:#6b7280;margin-bottom:6px">Preview — first 10 rows shown</div>
      <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px">
        <table style="border-collapse:collapse;width:100%;min-width:700px">
          <thead id="preview-head" style="background:#1D6F42;color:#fff;position:sticky;top:0"></thead>
          <tbody id="preview-body"></tbody>
        </table>
      </div>
    </div>

    <!-- Footer -->
    <div style="padding:14px 22px;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-shrink:0">
      <div id="import-status" style="font-family:var(--font-m);font-size:.75rem;color:#6b7280"></div>
      <div style="display:flex;gap:8px">
        <button id="modal-cancel" style="padding:8px 18px;border:1px solid #d1d5db;background:#fff;border-radius:8px;font-family:var(--font-h);font-size:.78rem;font-weight:600;cursor:pointer">Cancel</button>
        <button id="import-btn" disabled
                style="padding:8px 22px;background:#1D6F42;color:#fff;border:none;border-radius:8px;font-family:var(--font-h);font-size:.78rem;font-weight:700;cursor:pointer;transition:opacity .15s">
          ✓ Import All
        </button>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
  // ── Column map ──────────────────────────────────────────────
  const COL_MAP = {
    'tin':'tin',
    'name of suppliers':'company_name','name of supplier':'company_name',
    'supplier name':'company_name','company name':'company_name','name':'company_name',
    'v / nv':'vat_status','v/nv':'vat_status','vat':'vat_status','vat status':'vat_status',
    'address':'address',
    'particulars / desc.':'particulars','particulars/desc.':'particulars',
    'particulars':'particulars','description':'particulars',
    'document type':'document_type','document':'document_type','doc type':'document_type',
    'contact':'contact','contact no':'contact','contact number':'contact',
    'notes':'notes','remarks':'notes',
  };

  const FIELDS = ['tin','company_name','vat_status','address','particulars','document_type','contact','notes'];
  const LABELS = ['TIN','Name of Supplier','V/NV','Address','Particulars','Doc Type','Contact','Notes'];

  let wb = null, parsedRows = [];

  // ── Element references (safe — DOM is ready) ────────────────
  const modal      = document.getElementById('import-modal');
  const dropZone   = document.getElementById('drop-zone');
  const xlFile     = document.getElementById('xl-file');
  const sheetSel   = document.getElementById('sheet-selector');
  const sheetSel2  = document.getElementById('sheet-select');
  const badge      = document.getElementById('row-count-badge');
  const prevWrap   = document.getElementById('preview-wrap');
  const prevHead   = document.getElementById('preview-head');
  const prevBody   = document.getElementById('preview-body');
  const importBtn  = document.getElementById('import-btn');
  const statusEl   = document.getElementById('import-status');

  // ── Open / Close ────────────────────────────────────────────
  function openImport() {
    modal.classList.add('is-open');
    reset();
  }
  function closeImport() {
    modal.classList.remove('is-open');
    reset();
  }
  function reset() {
    wb = null; parsedRows = [];
    xlFile.value = '';
    dropZone.classList.remove('dragover');
    sheetSel.classList.remove('show');
    prevWrap.classList.remove('show');
    importBtn.disabled = true;
    statusEl.textContent = '';
    badge.textContent = '';
    prevHead.innerHTML = '';
    prevBody.innerHTML = '';
  }

  // ── Wire up buttons ─────────────────────────────────────────
  window.openImport = openImport; // expose globally for the Import Excel button
  document.getElementById('modal-close-x').addEventListener('click', closeImport);
  document.getElementById('modal-cancel').addEventListener('click', closeImport);
  modal.addEventListener('click', function(e) { if (e.target === modal) closeImport(); });

  // ── File input ──────────────────────────────────────────────
  dropZone.addEventListener('click', () => xlFile.click());
  xlFile.addEventListener('change', () => { if (xlFile.files[0]) handleFile(xlFile.files[0]); });

  // ── Drag & drop ─────────────────────────────────────────────
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('dragover');
    if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
  });

  // ── Read file ───────────────────────────────────────────────
  function handleFile(file) {
    if (typeof XLSX === 'undefined') {
      statusEl.textContent = '❌ Excel library not loaded — check your internet connection.';
      return;
    }
    statusEl.textContent = 'Reading file…';
    const reader = new FileReader();
    reader.onload = ev => {
      try {
        wb = XLSX.read(ev.target.result, { type: 'array' });
        sheetSel2.innerHTML = wb.SheetNames.map((n,i) => `<option value="${i}">${n}</option>`).join('');
        sheetSel.classList.add('show');
        statusEl.textContent = '';
        loadSheet();
      } catch(err) {
        statusEl.textContent = '❌ Could not read file: ' + err.message;
      }
    };
    reader.onerror = () => { statusEl.textContent = '❌ Failed to read file.'; };
    reader.readAsArrayBuffer(file);
  }

  // ── Parse sheet ─────────────────────────────────────────────
  function loadSheet() {
    if (!wb) return;
    const idx   = parseInt(sheetSel2.value) || 0;
    const sheet = wb.Sheets[wb.SheetNames[idx]];
    const raw   = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

    // Find header row
    let hIdx = 0;
    for (let i = 0; i < Math.min(10, raw.length); i++) {
      const s = raw[i].join(' ').toLowerCase();
      if (s.includes('tin') || s.includes('supplier')) { hIdx = i; break; }
    }

    const headers = raw[hIdx].map(h => String(h).trim().toLowerCase());
    const colMap  = headers.map(h => COL_MAP[h] || null);

    parsedRows = [];
    for (let i = hIdx + 1; i < raw.length; i++) {
      const row = raw[i];
      if (row.every(c => !String(c).trim())) continue;
      const obj = {};
      colMap.forEach((field, ci) => { if (field) obj[field] = String(row[ci] ?? '').trim(); });
      if (!obj.tin) continue;
      parsedRows.push(obj);
    }

    badge.textContent = `${parsedRows.length} vendor${parsedRows.length !== 1 ? 's' : ''} found`;

    // Preview
    prevHead.innerHTML = '<tr>' + LABELS.map(l =>
      `<th style="padding:8px 10px;text-align:left;font-size:.65rem;font-weight:700;white-space:nowrap;border:1px solid #155231">${l}</th>`
    ).join('') + '</tr>';

    prevBody.innerHTML = parsedRows.slice(0,10).map((r, i) =>
      `<tr style="background:${i%2===0?'#fff':'#f0fdf4'}">` +
      FIELDS.map(f => `<td style="padding:6px 10px;border:1px solid #e5e7eb;font-size:.69rem;color:#374151;white-space:nowrap;max-width:160px;overflow:hidden;text-overflow:ellipsis">${esc(r[f]||'')}</td>`).join('') +
      '</tr>'
    ).join('');

    prevWrap.classList.add('show');
    importBtn.disabled = parsedRows.length === 0;

    if (parsedRows.length === 0) {
      statusEl.textContent = '⚠️ No rows found — make sure your Excel has a TIN column.';
    } else if (parsedRows.length > 10) {
      statusEl.textContent = `Showing first 10 of ${parsedRows.length} rows`;
    } else {
      statusEl.textContent = '';
    }
  }

  sheetSel2.addEventListener('change', loadSheet);

  // ── Import ──────────────────────────────────────────────────
  importBtn.addEventListener('click', async function() {
    if (!parsedRows.length) return;
    importBtn.textContent = 'Importing…';
    importBtn.disabled = true;
    statusEl.textContent = `Saving ${parsedRows.length} vendors…`;

    const fd = new FormData();
    fd.append('ajax_import', '1');
    fd.append('rows', JSON.stringify(parsedRows));

    try {
      const res  = await fetch('vendor_masterlist.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const msg = `✓ Done — ${data.added} added, ${data.updated} updated${data.skipped ? ', ' + data.skipped + ' skipped' : ''}`;
        statusEl.textContent = msg;
        importBtn.textContent = '✓ Import All';
        showToast(msg, 'success');
        setTimeout(() => { closeImport(); vmLoadPage(1, vmPerPage, ''); }, 1800);
      } else {
        statusEl.textContent = '❌ ' + data.msg;
        importBtn.textContent = '✓ Import All';
        importBtn.disabled = false;
      }
    } catch(e) {
      statusEl.textContent = '❌ Network error — check your connection.';
      importBtn.textContent = '✓ Import All';
      importBtn.disabled = false;
    }
  });

  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

})(); // end IIFE
</script>
</body>
</html>