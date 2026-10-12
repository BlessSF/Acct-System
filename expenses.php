<?php
// ============================================================
//  expenses.php — Expenses management (CRUD)
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Branch accounts have no access to Expenses — redirect to dashboard
if (isBranch()) {
    header('Location: dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

$categories = [
    'Salaries & Wages','Marketing & Ads','Operations',
    'Software & Tools','Logistics & Ship','Office & Utilities','Miscellaneous'
];

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $category    = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $date        = $_POST['expense_date'] ?? date('Y-m-d');
        $notes       = trim($_POST['notes'] ?? '');

        if (!$category || !$description || $amount <= 0) {
            flashSet('error', 'Please fill in all required fields.');
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO expenses (category,description,amount,expense_date,notes,created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$category,$description,$amount,$date,$notes,$user['id']]);
                flashSet('success', 'Expense added successfully.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE expenses SET category=?,description=?,amount=?,expense_date=?,notes=? WHERE id=?");
                $stmt->execute([$category,$description,$amount,$date,$notes,$id]);
                flashSet('success', 'Expense updated.');
            }
        }
        header('Location: expenses.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);
        flashSet('success', 'Expense deleted.');
        header('Location: expenses.php'); exit;
    }
}

// ── Filters ───────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$fCat    = $_GET['cat'] ?? '';
$fYear   = (int)($_GET['year'] ?? date('Y'));

$where  = ["YEAR(expense_date) = ?"];
$params = [$fYear];
if ($search) { $where[] = "(description LIKE ? OR category LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fCat)   { $where[] = "category = ?"; $params[] = $fCat; }

$sql = "SELECT * FROM expenses WHERE ".implode(' AND ',$where)." ORDER BY expense_date DESC, id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$expenses = $stmt->fetchAll();

// ── Summary ───────────────────────────────────────────────
$r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) total, COUNT(*) cnt FROM expenses WHERE YEAR(expense_date)=?");
$r->execute([$fYear]); $summary = $r->fetch();

$byCat = $pdo->prepare("SELECT category, SUM(amount) total FROM expenses WHERE YEAR(expense_date)=? GROUP BY category ORDER BY total DESC LIMIT 4");
$byCat->execute([$fYear]); $catSummary = $byCat->fetchAll();

$pageTitle  = 'Expenses';
$activePage = 'expenses';
include 'layout.php';
?>

<div class="section-header">
  <div class="section-title">Expense <span>Records</span></div>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Expense</button>
</div>

<!-- KPI strip -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:22px">
  <div class="card" style="padding:16px 20px;border-top:2px solid var(--accent2)">
    <div style="font-family:var(--font-m);font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:4px">Total Spent</div>
    <div style="font-size:1.4rem;font-weight:800;color:var(--accent2)">$<?= number_format($summary['total'],0) ?></div>
    <div style="font-family:var(--font-m);font-size:.74rem;color:var(--muted)"><?= $summary['cnt'] ?> records</div>
  </div>
  <?php foreach ($catSummary as $c): ?>
  <div class="card" style="padding:16px 20px">
    <div style="font-family:var(--font-m);font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:var(--subtext);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($c['category']) ?></div>
    <div style="font-size:1.2rem;font-weight:800;color:var(--accent3)">$<?= number_format($c['total'],0) ?></div>
    <div style="font-family:var(--font-m);font-size:.72rem;color:var(--muted)"><?= $summary['total']>0?round($c['total']/$summary['total']*100).'%':'—' ?> of total</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card" style="padding:14px 20px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <input type="text" name="q" class="form-control" placeholder="Search description or category…"
           value="<?= htmlspecialchars($search) ?>" style="max-width:260px">
    <select name="cat" class="form-control" style="max-width:200px">
      <option value="">All categories</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= htmlspecialchars($cat) ?>" <?= $fCat===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year" class="form-control" style="max-width:110px">
      <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
    <a href="expenses.php" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Category</th><th>Description</th><th>Amount</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($expenses): ?>
          <?php foreach ($expenses as $e): ?>
          <tr>
            <td style="font-family:var(--font-m);font-size:.7rem;color:var(--muted)"><?= $e['id'] ?></td>
            <td>
              <span style="background:rgba(255,209,102,.1);color:var(--accent3);padding:3px 9px;border-radius:20px;font-family:var(--font-m);font-size:.68rem;">
                <?= htmlspecialchars($e['category']) ?>
              </span>
            </td>
            <td style="font-weight:600"><?= htmlspecialchars($e['description']) ?></td>
            <td style="font-family:var(--font-m);font-weight:500;color:var(--accent2)">$<?= number_format($e['amount'],2) ?></td>
            <td style="font-family:var(--font-m);font-size:.76rem;color:var(--subtext)"><?= date('M d, Y',strtotime($e['expense_date'])) ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($e)) ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete this expense?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Del</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);font-family:var(--font-m);font-size:.8rem">No expense records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-title">Add Expense</div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label>Category *</label>
          <select name="category" class="form-control" required>
            <option value="">Select…</option>
            <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Expense Date *</label>
          <input name="expense_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Description *</label>
        <input name="description" class="form-control" placeholder="Brief description" required>
      </div>
      <div class="form-group">
        <label>Amount ($) *</label>
        <input name="amount" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" required>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="2" style="resize:vertical" placeholder="Optional…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Expense</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-title">Edit Expense</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="form-row">
        <div class="form-group">
          <label>Category *</label>
          <select name="category" id="editCategory" class="form-control" required>
            <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Expense Date *</label>
          <input name="expense_date" id="editDate" type="date" class="form-control" required>
        </div>
      </div>
      <div class="form-group">
        <label>Description *</label>
        <input name="description" id="editDesc" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Amount ($) *</label>
        <input name="amount" id="editAmount" type="number" step="0.01" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="editNotes" class="form-control" rows="2" style="resize:vertical"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

  </div></div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{ if(e.target===o)o.classList.remove('open'); }));

function openEdit(e){
  document.getElementById('editId').value        = e.id;
  document.getElementById('editCategory').value  = e.category;
  document.getElementById('editDesc').value      = e.description;
  document.getElementById('editAmount').value    = e.amount;
  document.getElementById('editDate').value      = e.expense_date;
  document.getElementById('editNotes').value     = e.notes||'';
  openModal('editModal');
}
</script>
<script src="assets/js/keynav.js"></script>
</body>
</html>