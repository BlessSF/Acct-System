<?php
// ============================================================
//  carwash_services.php — Hero Carwash Services Catalog (CRUD)
//  Lets management / the H branch maintain the list of carwash
//  services (name, category, price) that populates the searchable
//  "Services" field on the Carwash Sales Report. Add a service
//  here and it shows up there immediately — no code changes needed.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
// Only the H branch (and management) maintain the Hero Carwash service list.
if (isBranch() && currentBranch() !== 'H') { header('Location: ../dashboard.php'); exit; }
require_once __DIR__ . '/carwash_services_lib.php';

$pdo  = getPDO();
$user = currentUser();

const CW_STORE = 'HEROCARWASH';

ensureCarwashServicesTable($pdo);
seedCarwashServicesIfEmpty($pdo, CW_STORE);

// ── AJAX: Add a new service ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    try {
        $category = trim((string)($_POST['category'] ?? '')) ?: 'ADD-ONS / OTHERS';
        $name     = trim((string)($_POST['name'] ?? ''));
        $price    = (float)($_POST['price'] ?? 0);
        if ($name === '') {
            echo json_encode(['ok' => false, 'msg' => 'Service name is required.']);
            exit;
        }
        $ordStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM h_carwash_services WHERE store_name=?");
        $ordStmt->execute([CW_STORE]);
        $nextOrder = (int)$ordStmt->fetchColumn() + 1;

        $ins = $pdo->prepare("INSERT INTO h_carwash_services
            (store_name,category,name,price,sort_order,is_active) VALUES (?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), is_active=1");
        $ins->execute([CW_STORE, $category, $name, $price, $nextOrder]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Update an existing service ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    try {
        $id       = (int)($_POST['id'] ?? 0);
        $category = trim((string)($_POST['category'] ?? '')) ?: 'ADD-ONS / OTHERS';
        $name     = trim((string)($_POST['name'] ?? ''));
        $price    = (float)($_POST['price'] ?? 0);
        if (!$id || $name === '') {
            echo json_encode(['ok' => false, 'msg' => 'Missing service name.']);
            exit;
        }
        $pdo->prepare("UPDATE h_carwash_services SET category=?, name=?, price=? WHERE id=? AND store_name=?")
            ->execute([$category, $name, $price, $id, CW_STORE]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Toggle active / inactive (hide from dropdown without deleting) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_toggle'])) {
    header('Content-Type: application/json');
    try {
        $id     = (int)($_POST['id'] ?? 0);
        $active = ($_POST['active'] ?? '') === '1' ? 1 : 0;
        $pdo->prepare("UPDATE h_carwash_services SET is_active=? WHERE id=? AND store_name=?")
            ->execute([$active, $id, CW_STORE]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Delete a service permanently ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM h_carwash_services WHERE id=? AND store_name=?")->execute([$id, CW_STORE]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── Display data ─────────────────────────────────────────────
$services   = getCarwashServicesAll($pdo, CW_STORE);
$categories = [];
foreach ($services as $s) {
    if (!in_array($s['category'], $categories, true)) $categories[] = $s['category'];
}
if (empty($categories)) $categories = ['4 WHEELS', 'MOTORCYCLE', 'ADD-ONS / OTHERS'];

$pageTitle  = 'Hero Carwash — Manage Services';
$activePage = 'h_carwash_services';
include '../layout.php';
?>

<style>
.cw-header-card {
  background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.cw-header-card .eyebrow { font-family:var(--font-m); font-size:.58rem; text-transform:uppercase; letter-spacing:.14em; color:rgba(255,255,255,.55); margin-bottom:4px; }
.cw-header-card .title   { font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:-.02em; }
.cw-header-card .subtitle{ font-family:var(--font-m); font-size:.67rem; color:rgba(255,255,255,.6); margin-top:4px; }

.cws-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
.cws-search { flex:1; min-width:220px; max-width:340px; }
.cws-search input {
  width:100%; padding:9px 13px 9px 34px; border:1px solid var(--border); border-radius:var(--radius-sm);
  font-family:var(--font-m); font-size:.8rem; outline:none; background:#fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="%236b7280" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>') no-repeat 11px center;
  background-size:14px;
  transition: border-color .15s;
}
.cws-search input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }

.cws-add-card {
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
  box-shadow:0 2px 12px rgba(0,0,0,.06); padding:16px 18px; margin-bottom:22px;
}
.cws-add-title { font-family:var(--font-m); font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext2); margin-bottom:12px; }
.cws-add-grid { display:grid; grid-template-columns: 1.2fr 1.6fr 0.9fr auto; gap:10px; align-items:end; }
.cws-add-grid .form-group { margin-bottom:0; }
@media(max-width:820px){ .cws-add-grid{ grid-template-columns:1fr 1fr; } }

.sr-section { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 2px 12px rgba(0,0,0,.06); overflow:hidden; margin-bottom:22px; }
.sr-section-title { padding:10px 16px; font-family:var(--font-m); font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#fff; background:#7b1a1a; display:flex; align-items:center; justify-content:space-between; }
.sr-section-title .count { font-weight:500; opacity:.75; font-size:.68rem; text-transform:none; letter-spacing:0; }

.cws-table td, .cws-table th { padding: 9px 12px; }
.cws-table thead th { background:#f8f9fb; }
.cws-table input.cws-inp {
  width:100%; padding:6px 9px; border:1px solid var(--border); border-radius:6px;
  font-family:var(--font-m); font-size:.78rem; background:#fff; outline:none;
}
.cws-table input.cws-inp:focus { border-color:var(--accent); box-shadow:0 0 0 2px rgba(15,123,92,.08); }
.cws-table input.cws-price { width:100px; text-align:right; }
.cws-row.inactive { opacity:.5; }
.cws-row.inactive td { text-decoration: line-through; }
.cws-row.inactive input.cws-inp { text-decoration:none; }
.cws-actions { display:flex; gap:6px; white-space:nowrap; }
.cws-empty { padding:28px; text-align:center; color:var(--subtext); font-family:var(--font-m); font-size:.78rem; }
</style>

<div class="cw-header-card">
  <div>
    <div class="eyebrow">Hero Carwash</div>
    <div class="title">🧴 Manage Services</div>
    <div class="subtitle">Add, edit, or retire services — changes apply instantly to the Sales Report's Services search.</div>
  </div>
  <a href="h_carwash_sales_report.php" class="btn btn-ghost" style="background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.3)">← Back to Sales Report</a>
</div>

<!-- Add new service -->
<div class="cws-add-card">
  <div class="cws-add-title">+ Add New Service</div>
  <div class="cws-add-grid">
    <div class="form-group">
      <label>Category</label>
      <input type="text" id="add-category" class="form-control" list="cws-category-list" placeholder="e.g. 4 WHEELS">
      <datalist id="cws-category-list">
        <?php foreach ($categories as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="form-group">
      <label>Service Name</label>
      <input type="text" id="add-name" class="form-control" placeholder="e.g. CERAMIC COATING">
    </div>
    <div class="form-group">
      <label>Price</label>
      <input type="number" step="0.01" id="add-price" class="form-control" placeholder="0.00">
    </div>
    <button type="button" class="btn btn-primary" onclick="addService()">+ Add Service</button>
  </div>
</div>

<!-- Toolbar / search -->
<div class="cws-toolbar">
  <div class="cws-search">
    <input type="text" id="cws-search-input" placeholder="Search services…" oninput="filterServices()">
  </div>
  <span style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)" id="cws-count"></span>
</div>

<div class="sr-section">
  <div class="sr-section-title dark-red">
    <span>Services Catalog</span>
    <span class="count"><?= count($services) ?> total</span>
  </div>
  <div style="overflow-x:auto">
  <table class="di-table cws-table" id="cws-table">
    <thead>
      <tr>
        <th style="width:36px">#</th>
        <th>Category</th>
        <th>Service Name</th>
        <th style="width:110px">Price</th>
        <th style="width:80px">Status</th>
        <th style="width:190px"></th>
      </tr>
    </thead>
    <tbody id="cws-body">
      <?php foreach ($services as $i => $s): ?>
      <tr class="cws-row <?= $s['is_active'] ? '' : 'inactive' ?>" data-id="<?= (int)$s['id'] ?>"
          data-search="<?= htmlspecialchars(strtolower($s['category'].' '.$s['name'])) ?>">
        <td><?= $i+1 ?></td>
        <td><input class="cws-inp cws-category" type="text" value="<?= htmlspecialchars($s['category']) ?>" list="cws-category-list"></td>
        <td><input class="cws-inp cws-name" type="text" value="<?= htmlspecialchars($s['name']) ?>"></td>
        <td><input class="cws-inp cws-price" type="number" step="0.01" value="<?= (float)$s['price'] ?>"></td>
        <td>
          <span class="badge <?= $s['is_active'] ? 'badge-paid' : 'badge-overdue' ?>"><?= $s['is_active'] ? 'Active' : 'Hidden' ?></span>
        </td>
        <td class="cws-actions">
          <button class="btn btn-sm btn-ghost" onclick="saveService(this)">💾 Save</button>
          <button class="btn btn-sm btn-ghost" onclick="toggleService(this)"><?= $s['is_active'] ? '🚫 Hide' : '✅ Unhide' ?></button>
          <button class="btn btn-sm btn-danger" onclick="deleteService(this)">✕</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (empty($services)): ?>
  <div class="cws-empty">No services yet — add your first one above.</div>
  <?php endif; ?>
</div>

<script>
function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-' + (type || 'success') + ' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

function filterServices() {
  const q = document.getElementById('cws-search-input').value.trim().toLowerCase();
  const rows = document.querySelectorAll('#cws-body .cws-row');
  let visible = 0;
  rows.forEach(row => {
    const match = !q || (row.dataset.search || '').includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('cws-count').textContent = visible + ' of ' + rows.length + ' shown';
}
document.addEventListener('DOMContentLoaded', filterServices);

async function addService() {
  const category = document.getElementById('add-category').value.trim();
  const name     = document.getElementById('add-name').value.trim();
  const price    = parseFloat(document.getElementById('add-price').value) || 0;
  if (!name) { showToast('❌ Enter a service name first.', 'error'); return; }

  const fd = new FormData();
  fd.append('ajax_add', '1');
  fd.append('category', category || 'ADD-ONS / OTHERS');
  fd.append('name', name);
  fd.append('price', price);
  const res  = await fetch('carwash_services.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    showToast('✓ Service added', 'success');
    location.reload();
  } else {
    showToast('❌ ' + (data.msg || 'Could not add service.'), 'error');
  }
}

async function saveService(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('ajax_update', '1');
  fd.append('id', tr.dataset.id);
  fd.append('category', tr.querySelector('.cws-category').value.trim());
  fd.append('name', tr.querySelector('.cws-name').value.trim());
  fd.append('price', parseFloat(tr.querySelector('.cws-price').value) || 0);
  const res  = await fetch('carwash_services.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    showToast('✓ Saved', 'success');
    tr.dataset.search = (tr.querySelector('.cws-category').value + ' ' + tr.querySelector('.cws-name').value).toLowerCase();
  } else {
    showToast('❌ ' + (data.msg || 'Could not save.'), 'error');
  }
}

async function toggleService(btn) {
  const tr = btn.closest('tr');
  const willActivate = tr.classList.contains('inactive');
  const fd = new FormData();
  fd.append('ajax_toggle', '1');
  fd.append('id', tr.dataset.id);
  fd.append('active', willActivate ? '1' : '0');
  const res  = await fetch('carwash_services.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  } else {
    showToast('❌ ' + (data.msg || 'Could not update.'), 'error');
  }
}

async function deleteService(btn) {
  const tr = btn.closest('tr');
  if (!confirm('Delete "' + tr.querySelector('.cws-name').value + '" permanently? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('ajax_delete', '1');
  fd.append('id', tr.dataset.id);
  const res  = await fetch('carwash_services.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    tr.remove();
    filterServices();
    showToast('✓ Deleted', 'success');
  } else {
    showToast('❌ ' + (data.msg || 'Could not delete.'), 'error');
  }
}
</script>