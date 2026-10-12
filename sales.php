<?php
// ============================================================
//  sales.php — Sales management with Day / Month / Year filters
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Branch accounts have no access to Sales — redirect to dashboard
if (isBranch()) {
    header('Location: dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Handle POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $client   = trim($_POST['client'] ?? '');
        $product  = trim($_POST['product'] ?? '');
        $amount   = (float)($_POST['amount'] ?? 0);
        $status   = in_array($_POST['status'],['paid','pending','overdue']) ? $_POST['status'] : 'pending';
        $date     = $_POST['sale_date'] ?? date('Y-m-d');
        $notes    = trim($_POST['notes'] ?? '');

        if (!$client || !$product || $amount <= 0) {
            flashSet('error', 'Please fill in all required fields.');
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO sales (client,product,amount,status,sale_date,notes,created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$client,$product,$amount,$status,$date,$notes,$user['id']]);
                flashSet('success', 'Sale record added successfully.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE sales SET client=?,product=?,amount=?,status=?,sale_date=?,notes=? WHERE id=?");
                $stmt->execute([$client,$product,$amount,$status,$date,$notes,$id]);
                flashSet('success', 'Sale record updated.');
            }
        }
        header('Location: sales.php?view='.($_POST['view_mode']??'month').'&year='.($_POST['fyear']??date('Y')).'&month='.($_POST['fmonth']??date('n')).'&day='.($_POST['fday']??0));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);
        flashSet('success', 'Sale record deleted.');
        header('Location: sales.php'); exit;
    }
}

// ── Filter params ─────────────────────────────────────────
$viewMode = in_array($_GET['view']??'',['day','month','year']) ? $_GET['view'] : 'month';
$search   = trim($_GET['q'] ?? '');
$fStatus  = $_GET['status'] ?? '';
$fYear    = (int)($_GET['year']  ?? date('Y'));
$fMonth   = (int)($_GET['month'] ?? date('n'));
$fDay     = (int)($_GET['day']   ?? (int)date('j'));
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── Build WHERE ───────────────────────────────────────────
$where = []; $params = [];
if ($viewMode === 'day') {
    $where[] = "YEAR(sale_date)=?";  $params[] = $fYear;
    $where[] = "MONTH(sale_date)=?"; $params[] = $fMonth;
    $where[] = "DAY(sale_date)=?";   $params[] = $fDay;
} elseif ($viewMode === 'month') {
    $where[] = "YEAR(sale_date)=?";  $params[] = $fYear;
    $where[] = "MONTH(sale_date)=?"; $params[] = $fMonth;
} else {
    $where[] = "YEAR(sale_date)=?";  $params[] = $fYear;
}
if ($search)  { $where[] = "(client LIKE ? OR product LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fStatus && in_array($fStatus,['paid','pending','overdue'])) { $where[] = "status=?"; $params[] = $fStatus; }
$whereSQL = "WHERE ".implode(' AND ',$where);

// ── Fetch records ─────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM sales $whereSQL ORDER BY sale_date DESC, id DESC");
$stmt->execute($params); $sales = $stmt->fetchAll();

// ── Period totals ─────────────────────────────────────────
$tStmt = $pdo->prepare("SELECT status, COUNT(*) cnt, COALESCE(SUM(amount),0) total FROM sales $whereSQL GROUP BY status");
$tStmt->execute($params);
$summary = ['paid'=>['cnt'=>0,'total'=>0],'pending'=>['cnt'=>0,'total'=>0],'overdue'=>['cnt'=>0,'total'=>0]];
foreach ($tStmt->fetchAll() as $r) $summary[$r['status']] = ['cnt'=>$r['cnt'],'total'=>(float)$r['total']];
$periodTotal = array_sum(array_column($summary,'total'));

// ── Daily breakdown chart (month view) ────────────────────
$dailyChart = [];
if ($viewMode === 'month') {
    $dStmt = $pdo->prepare("SELECT DAY(sale_date) d, COALESCE(SUM(amount),0) total FROM sales WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? GROUP BY DAY(sale_date) ORDER BY d");
    $dStmt->execute([$fYear,$fMonth]);
    $dmax = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
    $dailyChart = array_fill(1,$dmax,0);
    foreach ($dStmt->fetchAll() as $r) $dailyChart[$r['d']] = (float)$r['total'];
}

// ── Monthly breakdown chart (year view) ───────────────────
$monthlyChart = [];
if ($viewMode === 'year') {
    $mStmt = $pdo->prepare("SELECT MONTH(sale_date) m, COALESCE(SUM(amount),0) total FROM sales WHERE YEAR(sale_date)=? GROUP BY MONTH(sale_date) ORDER BY m");
    $mStmt->execute([$fYear]);
    $monthlyChart = array_fill(1,12,0);
    foreach ($mStmt->fetchAll() as $r) $monthlyChart[$r['m']] = (float)$r['total'];
}

// ── Group by day for month table ──────────────────────────
$groupedByDay = [];
if ($viewMode === 'month') {
    foreach ($sales as $s) { $groupedByDay[date('Y-m-d',strtotime($s['sale_date']))][] = $s; }
    krsort($groupedByDay);
}

$pageTitle  = 'Sales Management';
$activePage = 'sales';
include 'layout.php';
?>

<div class="section-header">
  <div class="section-title">Sales <span>Records</span></div>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Sale</button>
</div>

<!-- View Mode Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:5px;width:fit-content">
  <?php foreach (['day'=>'📅 Day','month'=>'📆 Month','year'=>'📊 Year'] as $v=>$label): ?>
  <a href="?view=<?=$v?>&year=<?=$fYear?>&month=<?=$fMonth?>&day=<?=$fDay?>"
     style="padding:7px 18px;border-radius:7px;font-size:.8rem;font-weight:700;text-decoration:none;transition:all .15s;
            <?=$viewMode===$v?'background:var(--accent);color:#080d18;':'color:var(--subtext);'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="card" style="padding:14px 20px;margin-bottom:18px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="view" value="<?=$viewMode?>">

    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.63rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">Year</label>
      <select name="year" class="form-control" style="max-width:110px">
        <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?><option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
      </select>
    </div>

    <?php if (in_array($viewMode,['month','day'])): ?>
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.63rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">Month</label>
      <select name="month" class="form-control" style="max-width:145px">
        <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?=$monthNames[$m]?></option><?php endfor; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($viewMode==='day'): ?>
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.63rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">Day</label>
      <select name="day" class="form-control" style="max-width:80px">
        <?php $dmax=cal_days_in_month(CAL_GREGORIAN,$fMonth,$fYear); for($d=1;$d<=$dmax;$d++): ?><option value="<?=$d?>" <?=$fDay==$d?'selected':''?>><?=$d?></option><?php endfor; ?>
      </select>
    </div>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.63rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">Status</label>
      <select name="status" class="form-control" style="max-width:135px">
        <option value="">All</option>
        <option value="paid"    <?=$fStatus==='paid'?'selected':''?>>Paid</option>
        <option value="pending" <?=$fStatus==='pending'?'selected':''?>>Pending</option>
        <option value="overdue" <?=$fStatus==='overdue'?'selected':''?>>Overdue</option>
      </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-family:var(--font-m);font-size:.63rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">Search</label>
      <input type="text" name="q" class="form-control" placeholder="Client or product…" value="<?=htmlspecialchars($search)?>" style="max-width:200px">
    </div>

    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    <a href="sales.php?view=<?=$viewMode?>" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<!-- Period label -->
<div style="font-family:var(--font-m);font-size:.75rem;color:var(--subtext);margin-bottom:14px">
  <span style="color:var(--accent)">●</span>
  <?php
    if($viewMode==='day')   echo " <strong style='color:var(--text)'>{$monthNames[$fMonth]} {$fDay}, {$fYear}</strong>";
    elseif($viewMode==='month') echo " <strong style='color:var(--text)'>{$monthNames[$fMonth]} {$fYear}</strong>";
    else echo " <strong style='color:var(--text)'>Full Year {$fYear}</strong>";
  ?>
  &nbsp;·&nbsp; <strong style="color:var(--text)"><?=count($sales)?></strong> records
  &nbsp;·&nbsp; Total: <strong style="color:var(--accent)">$<?=number_format($periodTotal,2)?></strong>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">
  <?php foreach(['paid'=>['var(--accent)','✓'],'pending'=>['#ffd166','◷'],'overdue'=>['#ff6b6b','⚠']] as $st=>[$clr,$ico]): ?>
  <div class="card" style="padding:16px 20px;border-top:2px solid <?=$clr?>">
    <div style="font-family:var(--font-m);font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:4px"><?=$ico?> <?=ucfirst($st)?></div>
    <div style="font-size:1.25rem;font-weight:800;color:<?=$clr?>"><?=$summary[$st]['cnt']?> <span style="font-size:.72rem;font-weight:400;color:var(--muted)">txns</span></div>
    <div style="font-family:var(--font-m);font-size:.8rem;color:var(--muted)">$<?=number_format($summary[$st]['total'],2)?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Daily chart (month view) -->
<?php if ($viewMode==='month'): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div class="card-title">Daily Sales — <?=$monthNames[$fMonth]?> <?=$fYear?></div>
    <div class="card-badge">Click a bar to jump to that day</div>
  </div>
  <div style="position:relative;height:190px"><canvas id="dailyBarChart"></canvas></div>
</div>
<?php endif; ?>

<!-- Monthly chart (year view) -->
<?php if ($viewMode==='year'): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div class="card-title">Monthly Sales — <?=$fYear?></div>
    <div class="card-badge">by month</div>
  </div>
  <div style="position:relative;height:190px"><canvas id="monthlyBarChart"></canvas></div>
</div>
<?php endif; ?>

<!-- Table: month view grouped by day -->
<?php if ($viewMode==='month' && $groupedByDay): ?>
  <?php foreach ($groupedByDay as $day => $dayRows): ?>
  <?php $dayTotal = array_sum(array_column($dayRows,'amount')); ?>
  <div style="margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 16px;
                background:var(--surf2);border:1px solid var(--border);border-radius:9px 9px 0 0;border-bottom:none">
      <span style="font-family:var(--font-m);font-size:.75rem;color:var(--accent)"><?=date('l, F j Y',strtotime($day))?></span>
      <span style="font-family:var(--font-m);font-size:.74rem;color:var(--subtext)"><?=count($dayRows)?> records &nbsp;·&nbsp; <strong style="color:var(--accent)">$<?=number_format($dayTotal,2)?></strong></span>
    </div>
    <div class="card" style="border-radius:0 0 9px 9px;padding:0 0 4px">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th style="padding-left:18px">#</th><th>Client</th><th>Product</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($dayRows as $s): ?>
            <tr>
              <td style="font-family:var(--font-m);font-size:.7rem;color:var(--muted);padding-left:18px"><?=$s['id']?></td>
              <td style="font-weight:600"><?=htmlspecialchars($s['client'])?></td>
              <td style="color:var(--subtext);font-size:.79rem"><?=htmlspecialchars($s['product'])?></td>
              <td style="font-family:var(--font-m);font-weight:500">$<?=number_format($s['amount'],2)?></td>
              <td><span class="badge badge-<?=$s['status']?>"><?=ucfirst($s['status'])?></span></td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="btn btn-ghost btn-sm" onclick="openEdit(<?=htmlspecialchars(json_encode($s))?>)">Edit</button>
                  <form method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn btn-danger btn-sm">Del</button></form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php elseif ($viewMode==='month' && !$groupedByDay): ?>
<div class="card"><p style="text-align:center;padding:40px;color:var(--muted);font-family:var(--font-m);font-size:.8rem">No sales for <?=$monthNames[$fMonth]?> <?=$fYear?></p></div>
<?php else: ?>
<!-- Flat table for day / year -->
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Product</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($sales): foreach ($sales as $s): ?>
        <tr>
          <td style="font-family:var(--font-m);font-size:.7rem;color:var(--muted)"><?=$s['id']?></td>
          <td style="font-weight:600"><?=htmlspecialchars($s['client'])?></td>
          <td style="color:var(--subtext);font-size:.79rem"><?=htmlspecialchars($s['product'])?></td>
          <td style="font-family:var(--font-m);font-weight:500">$<?=number_format($s['amount'],2)?></td>
          <td style="font-family:var(--font-m);font-size:.76rem;color:var(--subtext)"><?=date('M d, Y',strtotime($s['sale_date']))?></td>
          <td><span class="badge badge-<?=$s['status']?>"><?=ucfirst($s['status'])?></span></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost btn-sm" onclick="openEdit(<?=htmlspecialchars(json_encode($s))?>)">Edit</button>
              <form method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn btn-danger btn-sm">Del</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);font-family:var(--font-m);font-size:.8rem">No records found for this period</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-title">Add Sale Record</div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="view_mode" value="<?=$viewMode?>">
      <input type="hidden" name="fyear"  value="<?=$fYear?>">
      <input type="hidden" name="fmonth" value="<?=$fMonth?>">
      <input type="hidden" name="fday"   value="<?=$fDay?>">
      <div class="form-row">
        <div class="form-group"><label>Client *</label><input name="client" class="form-control" placeholder="Company name" required></div>
        <div class="form-group"><label>Product / Service *</label><input name="product" class="form-control" placeholder="e.g. Enterprise Plan" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Amount ($) *</label><input name="amount" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" required></div>
        <div class="form-group"><label>Sale Date *</label>
          <input name="sale_date" type="date" class="form-control"
            value="<?=$viewMode==='day'?"$fYear-".str_pad($fMonth,2,'0',STR_PAD_LEFT)."-".str_pad($fDay,2,'0',STR_PAD_LEFT):($viewMode==='month'?"$fYear-".str_pad($fMonth,2,'0',STR_PAD_LEFT)."-01":date('Y-m-d'))?>" required>
        </div>
      </div>
      <div class="form-group"><label>Status</label>
        <select name="status" class="form-control"><option value="pending">Pending</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2" style="resize:vertical"></textarea></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Record</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-title">Edit Sale Record</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="form-row">
        <div class="form-group"><label>Client *</label><input name="client" id="editClient" class="form-control" required></div>
        <div class="form-group"><label>Product / Service *</label><input name="product" id="editProduct" class="form-control" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Amount ($) *</label><input name="amount" id="editAmount" type="number" step="0.01" class="form-control" required></div>
        <div class="form-group"><label>Sale Date *</label><input name="sale_date" id="editDate" type="date" class="form-control" required></div>
      </div>
      <div class="form-group"><label>Status</label>
        <select name="status" id="editStatus" class="form-control"><option value="pending">Pending</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" id="editNotes" class="form-control" rows="2" style="resize:vertical"></textarea></div>
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
function openEdit(s){
  document.getElementById('editId').value      = s.id;
  document.getElementById('editClient').value  = s.client;
  document.getElementById('editProduct').value = s.product;
  document.getElementById('editAmount').value  = s.amount;
  document.getElementById('editDate').value    = s.sale_date;
  document.getElementById('editStatus').value  = s.status;
  document.getElementById('editNotes').value   = s.notes||'';
  openModal('editModal');
}

<?php if ($viewMode==='month'): ?>
const dailyLabels = <?=json_encode(array_keys($dailyChart))?>;
const dailyData   = <?=json_encode(array_values($dailyChart))?>;
const dailyChart_ = new Chart(document.getElementById('dailyBarChart'), {
  type:'bar',
  data:{ labels:dailyLabels, datasets:[{
    label:'Sales $', data:dailyData,
    backgroundColor:dailyData.map(v=>v>0?'rgba(0,229,160,.75)':'rgba(27,45,71,.35)'),
    borderRadius:4, borderSkipped:false
  }]},
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{
      legend:{display:false},
      tooltip:{backgroundColor:'#0f1825',borderColor:'#1b2d47',borderWidth:1,titleColor:'#dce8f5',bodyColor:'#5a7899',
        callbacks:{title:c=>'Day '+c[0].label,label:c=>' $'+c.parsed.y.toLocaleString()}}
    },
    scales:{
      x:{grid:{color:'rgba(27,45,71,.4)'},ticks:{color:'#3a5270',font:{family:'DM Mono',size:9}}},
      y:{grid:{color:'rgba(27,45,71,.4)'},ticks:{color:'#3a5270',font:{family:'DM Mono',size:9},callback:v=>'$'+(v>=1000?(v/1000).toFixed(0)+'k':v)}}
    },
    onClick:(e,els)=>{ if(els.length){ const d=dailyLabels[els[0].index]; window.location='sales.php?view=day&year=<?=$fYear?>&month=<?=$fMonth?>&day='+d; } }
  }
});
<?php endif; ?>

<?php if ($viewMode==='year'): ?>
const monthLabels=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const monthData=<?=json_encode(array_values($monthlyChart))?>;
new Chart(document.getElementById('monthlyBarChart'),{
  type:'bar',
  data:{labels:monthLabels,datasets:[{label:'Sales $',data:monthData,backgroundColor:'rgba(0,229,160,.72)',borderRadius:5,borderSkipped:false}]},
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},
      tooltip:{backgroundColor:'#0f1825',borderColor:'#1b2d47',borderWidth:1,titleColor:'#dce8f5',bodyColor:'#5a7899',callbacks:{label:c=>' $'+c.parsed.y.toLocaleString()}}
    },
    scales:{
      x:{grid:{color:'rgba(27,45,71,.4)'},ticks:{color:'#3a5270',font:{family:'DM Mono',size:9}}},
      y:{grid:{color:'rgba(27,45,71,.4)'},ticks:{color:'#3a5270',font:{family:'DM Mono',size:9},callback:v=>'$'+(v>=1000?(v/1000).toFixed(0)+'k':v)}}
    },
    onClick:(e,els)=>{ if(els.length){ const m=els[0].index+1; window.location='sales.php?view=month&year=<?=$fYear?>&month='+m; } }
  }
});
<?php endif; ?>
</script>
</body>
</html>