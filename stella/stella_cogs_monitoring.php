<?php
// ============================================================
//  stella_cogs_monitoring.php — Stella Daily COGS Monitoring
//  TOTAL COGS = SM Kitchen Copy + Gulay + Other Expenses + Bottled Water/Sodas
//  (RHEA Copy / RHEA Copy Parcel are reference-only reconciliation columns)
//  COGS % = Total COGS / Total Sales Per Day
//  MP %   = Total MP / Total Sales Per Day
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_cogs_monitoring` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`          date NOT NULL,
    `store_name`          varchar(50) NOT NULL DEFAULT 'Stella',
    `sm_kitchen_copy`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Billing to Commi (COGS)',
    `rhea_copy`           decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Billing to Commi (COGS) — reference/reconciliation only',
    `rhea_copy_parcel`    decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Reference/reconciliation only',
    `gulay`               decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Store Expenses',
    `other_expenses`      decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(COGS)',
    `bottled_water_sodas` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(COGS) — Water ₱9 / Soda ₱36 per unit',
    `transpo_df_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
    `staff_meal_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
    `office_supplies`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
    `dining_supplies`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
    `refund_misc`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_sales_per_day` decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_mp`            decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Manpower Cost',
    `cogs_threshold_pct`  decimal(5,2)  NOT NULL DEFAULT 45.00,
    `mp_threshold_pct`    decimal(5,2)  NOT NULL DEFAULT 7.00,
    `sort_order`          int(4) NOT NULL DEFAULT 0,
    `saved_by`            varchar(100) DEFAULT NULL,
    `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── AJAX: Save row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $id         = (int)($_POST['id'] ?? 0);
        $store_name = isBranch() ? currentBranch() : 'Stella';
        $entryDate  = $_POST['entry_date'] ?? date('Y-m-d');

        $num = fn($k, $def = 0) => (float)($_POST[$k] ?? $def);
        $smKitchen  = $num('sm_kitchen_copy');
        $rhea       = $num('rhea_copy');
        $rheaParcel = $num('rhea_copy_parcel');
        $gulay      = $num('gulay');
        $other      = $num('other_expenses');
        $bottled    = $num('bottled_water_sodas');
        $transpo    = $num('transpo_df_expenses');
        $staffMeal  = $num('staff_meal_expenses');
        $office     = $num('office_supplies');
        $dining     = $num('dining_supplies');
        $refund     = $num('refund_misc');
        $sales      = $num('total_sales_per_day');
        $totalMp    = $num('total_mp');
        $cogsTh     = $num('cogs_threshold_pct', 45);
        $mpTh       = $num('mp_threshold_pct', 7);
        $so         = (int)($_POST['sort_order'] ?? 0);

        $totalCogs = round($smKitchen + $gulay + $other + $bottled, 2);
        $cogsPct   = $sales > 0 ? round($totalCogs / $sales * 100, 1) : 0;
        $mpPct     = $sales > 0 ? round($totalMp   / $sales * 100, 1) : 0;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE stella_cogs_monitoring SET
                entry_date=?,store_name=?,sm_kitchen_copy=?,rhea_copy=?,rhea_copy_parcel=?,
                gulay=?,other_expenses=?,bottled_water_sodas=?,transpo_df_expenses=?,staff_meal_expenses=?,
                office_supplies=?,dining_supplies=?,refund_misc=?,total_sales_per_day=?,total_mp=?,
                cogs_threshold_pct=?,mp_threshold_pct=?,sort_order=?,saved_by=? WHERE id=?");
            $stmt->execute([$entryDate,$store_name,$smKitchen,$rhea,$rheaParcel,
                $gulay,$other,$bottled,$transpo,$staffMeal,
                $office,$dining,$refund,$sales,$totalMp,
                $cogsTh,$mpTh,$so,$user['name'],$id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO stella_cogs_monitoring
                (entry_date,store_name,sm_kitchen_copy,rhea_copy,rhea_copy_parcel,
                 gulay,other_expenses,bottled_water_sodas,transpo_df_expenses,staff_meal_expenses,
                 office_supplies,dining_supplies,refund_misc,total_sales_per_day,total_mp,
                 cogs_threshold_pct,mp_threshold_pct,sort_order,saved_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$entryDate,$store_name,$smKitchen,$rhea,$rheaParcel,
                $gulay,$other,$bottled,$transpo,$staffMeal,
                $office,$dining,$refund,$sales,$totalMp,
                $cogsTh,$mpTh,$so,$user['name']]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'totalCogs'=>$totalCogs,'cogsPct'=>$cogsPct,
            'cogsStatus'=>$cogsPct > $cogsTh ? 'COGS OVER COST' : 'COGS PASSED',
            'mpPct'=>$mpPct,'mpStatus'=>$mpPct < $mpTh ? 'MP PASSED' : 'MP FAILED']);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_row') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM stella_cogs_monitoring WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));

[$bClause, $bParams] = branchFilter('store_name');
$stmt = $pdo->prepare("SELECT * FROM stella_cogs_monitoring WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? AND $bClause ORDER BY entry_date,sort_order,id");
$stmt->execute(array_merge([$fYear, $fMonth], $bParams));
$rows = $stmt->fetchAll();

function calcCogsRow(array $r): array {
    $totalCogs = round((float)$r['sm_kitchen_copy'] + (float)$r['gulay'] + (float)$r['other_expenses'] + (float)$r['bottled_water_sodas'], 2);
    $sales     = (float)$r['total_sales_per_day'];
    $cogsPct   = $sales > 0 ? round($totalCogs / $sales * 100, 1) : 0;
    $mpPct     = $sales > 0 ? round((float)$r['total_mp'] / $sales * 100, 1) : 0;
    $cogsTh    = (float)$r['cogs_threshold_pct'];
    $mpTh      = (float)$r['mp_threshold_pct'];
    return [
        'totalCogs'  => $totalCogs,
        'cogsPct'    => $cogsPct,
        'cogsStatus' => $cogsPct > $cogsTh ? 'COGS OVER COST' : 'COGS PASSED',
        'mpPct'      => $mpPct,
        'mpStatus'   => $mpPct < $mpTh ? 'MP PASSED' : 'MP FAILED',
    ];
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stella_cogs_monitoring_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['Stella COGS Monitoring — '.$months[$fMonth-1].' '.$fYear]);
    fputcsv($out,['DATE','SM KITCHEN COPY','RHEA COPY','RHEA COPY (PARCEL)','GULAY','OTHER EXPENSES',
        'BOTTLED WATER/SODAS','TRANSPO/DF','STAFF MEAL','OFFICE SUPPLIES','DINING SUPPLIES','REFUND/MISC',
        'TOTAL SALES PER DAY','TOTAL COGS','COGS %','AUTO WARNED','TOTAL MP','MP %','MP STATUS']);
    foreach ($rows as $r) {
        $c = calcCogsRow($r);
        fputcsv($out,[
            $r['entry_date'], $r['sm_kitchen_copy'], $r['rhea_copy'], $r['rhea_copy_parcel'],
            $r['gulay'], $r['other_expenses'], $r['bottled_water_sodas'], $r['transpo_df_expenses'],
            $r['staff_meal_expenses'], $r['office_supplies'], $r['dining_supplies'], $r['refund_misc'],
            $r['total_sales_per_day'], $c['totalCogs'], $c['cogsPct'].'%', $c['cogsStatus'],
            $r['total_mp'], $c['mpPct'].'%', $c['mpStatus'],
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle  = 'Stella COGS Monitoring';
$activePage = 'stella_cogs_monitoring';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Stella COGS <span>Monitoring</span></div>
    <div class="section-subtitle">Daily COGS & Manpower — <?=$months[$fMonth-1]?> <?=$fYear?></div>
  </div>
  <a class="btn btn-primary" href="?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>">⬇ Download CSV</a>
</div>

<!-- Month / Year tabs -->
<div class="card" style="padding:12px 18px;margin-bottom:18px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?month=<?=$m?>&year=<?=$fYear?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?=$fMonth===$m?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>">
      <?=substr($months[$m-1],0,3)?>
    </a>
    <?php endfor; ?>
    <div style="margin-left:auto">
      <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value"
              class="form-control" style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px;
                     background:var(--surf2);border:1px solid rgba(34,211,165,.3);color:var(--accent)">
        <?php for($y=2050;$y>=2020;$y--): ?>
        <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="cogs-tbl" id="cogsTbl">
    <thead>
      <tr>
        <th rowspan="2" style="min-width:110px">DATE</th>
        <th colspan="3">COMMI BILL</th>
        <th rowspan="2" style="min-width:110px">GULAY<br><span class="sub">(Store Exp.)</span></th>
        <th rowspan="2" style="min-width:110px">OTHER EXP.<br><span class="sub">(COGS)</span></th>
        <th rowspan="2" style="min-width:120px">BOTTLED WATER<br>&amp; SODAS <span class="sub">(COGS)</span></th>
        <th rowspan="2" style="min-width:100px">TRANSPO/DF<br><span class="sub">(NON COGS)</span></th>
        <th rowspan="2" style="min-width:100px">STAFF MEAL<br><span class="sub">(NON COGS)</span></th>
        <th colspan="2">FRONTLINE EXP. <span class="sub">(NON COGS)</span></th>
        <th rowspan="2" style="min-width:100px">REFUND/<br>MISC</th>
        <th rowspan="2" style="min-width:120px">TOTAL SALES<br>PER DAY</th>
        <th colspan="2">COGS</th>
        <th rowspan="2" style="min-width:130px">AUTO WARNED</th>
        <th colspan="2">MANPOWER COST</th>
        <th rowspan="2" style="min-width:110px">MP STATUS</th>
        <th rowspan="2" style="min-width:150px">THRESHOLDS<br><span class="sub">COGS% / MP%</span></th>
        <th rowspan="2" style="min-width:100px">ACTION</th>
      </tr>
      <tr>
        <th style="min-width:110px">SM KITCHEN<br><span class="sub">(COGS)</span></th>
        <th style="min-width:110px">RHEA COPY<br><span class="sub">(COGS, ref.)</span></th>
        <th style="min-width:100px">RHEA<br>(PARCEL)</th>
        <th style="min-width:100px">OFFICE<br>SUPPLIES</th>
        <th style="min-width:100px">DINING<br>SUPPLIES</th>
        <th style="min-width:110px">TOTAL COGS</th>
        <th style="min-width:70px">%</th>
        <th style="min-width:100px">TOTAL MP</th>
        <th style="min-width:70px">%</th>
      </tr>
    </thead>
    <tbody id="cogsTbody">
      <?php foreach ($rows as $r): $c = calcCogsRow($r); ?>
      <tr data-id="<?=$r['id']?>">
        <td><input class="ci" type="date" value="<?=$r['entry_date']?>"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['sm_kitchen_copy']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['rhea_copy']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['rhea_copy_parcel']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['gulay']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['other_expenses']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['bottled_water_sodas']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['transpo_df_expenses']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['staff_meal_expenses']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['office_supplies']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['dining_supplies']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['refund_misc']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['total_sales_per_day']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci calc" type="number" value="<?=$c['totalCogs']?>" readonly tabindex="-1"></td>
        <td><input class="ci calc" type="text" value="<?=$c['cogsPct']?>%" readonly tabindex="-1"></td>
        <td><span class="badge <?=$c['cogsStatus']==='COGS PASSED'?'badge-ok':'badge-bad'?>" data-role="cogsStatus"><?=$c['cogsStatus']?></span></td>
        <td><input class="ci" type="number" step="0.01" min="0" value="<?=$r['total_mp']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="ci calc" type="text" value="<?=$c['mpPct']?>%" readonly tabindex="-1"></td>
        <td><span class="badge <?=$c['mpStatus']==='MP PASSED'?'badge-ok':'badge-bad'?>" data-role="mpStatus"><?=$c['mpStatus']?></span></td>
        <td class="th-cell">
          <input class="ci th-inp" type="number" step="0.1" min="0" value="<?=$r['cogs_threshold_pct']?>" title="COGS Max %" oninput="calcRow(this)">
          <input class="ci th-inp" type="number" step="0.1" min="0" value="<?=$r['mp_threshold_pct']?>" title="MP Max %" oninput="calcRow(this)">
        </td>
        <td class="act-td">
          <button class="btn-sv" onclick="saveRow(this)">Save</button>
          <button class="btn-dl" onclick="deleteRow(this)">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <button class="btn-add" onclick="addRow()">+ Add Row</button>
</div>

<style>
.cogs-tbl { width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.74rem; }
.cogs-tbl thead th {
  background:var(--surf2); color:var(--subtext); text-transform:uppercase;
  font-size:.6rem; letter-spacing:.04em; padding:7px 6px; border:1px solid var(--border);
  text-align:center; line-height:1.3;
}
.cogs-tbl thead .sub { font-size:.56rem; color:var(--subtext2); text-transform:none; font-weight:400; }
.cogs-tbl tbody td { padding:4px 5px; border:1px solid var(--border); text-align:center; }
.cogs-tbl .ci {
  width:100%; min-width:80px; padding:5px 6px; border:1px solid var(--border); border-radius:6px;
  background:var(--surface); color:var(--text); font-family:var(--font-m); font-size:.72rem;
  text-align:center;
}
.cogs-tbl .ci.calc { background:var(--surf3); color:var(--subtext2); font-weight:600; }
.cogs-tbl .th-cell { display:flex; flex-direction:column; gap:3px; padding:5px; }
.cogs-tbl .th-inp { min-width:60px; }
.badge {
  display:inline-block; font-family:var(--font-m); font-size:.6rem; font-weight:700;
  padding:4px 8px; border-radius:20px; white-space:nowrap;
}
.badge-ok  { background:rgba(34,211,165,.12); color:var(--accent); border:1px solid rgba(34,211,165,.2); }
.badge-bad { background:rgba(248,113,113,.12); color:var(--accent2); border:1px solid rgba(248,113,113,.2); }
.act-td { white-space:nowrap; }
.btn-sv, .btn-dl {
  font-family:var(--font-m); font-size:.66rem; padding:5px 8px; border-radius:6px;
  border:1px solid var(--border); cursor:pointer; margin-right:3px;
}
.btn-sv { background:var(--accent); color:#031a12; border-color:var(--accent); font-weight:600; }
.btn-dl { background:rgba(248,113,113,.08); color:var(--accent2); border-color:rgba(248,113,113,.2); }
.row-st { display:none; font-size:.64rem; color:var(--accent); margin-top:3px; }
.btn-add {
  margin:12px 16px 16px; padding:9px 14px; width:calc(100% - 32px);
  background:rgba(34,211,165,.05); border:1px dashed rgba(34,211,165,.25);
  color:var(--accent); border-radius:8px; cursor:pointer;
  font-family:var(--font-m); font-size:.74rem; letter-spacing:.02em;
}
.btn-add:hover { background:rgba(34,211,165,.1); }
</style>

<script>
const gv = el => parseFloat(el?.value) || 0;

function calcRow(inp) {
  const tr = inp.closest('tr');
  const ci = tr.querySelectorAll('.ci');
  // 0 date,1 sm_kitchen,2 rhea,3 rhea_parcel,4 gulay,5 other,6 bottled,7 transpo,8 staff_meal,
  // 9 office,10 dining,11 refund,12 sales,13 total_cogs(calc),14 cogs_pct(calc),
  // 15 total_mp,16 mp_pct(calc),17 cogs_threshold,18 mp_threshold
  const smKitchen = gv(ci[1]);
  const gulay     = gv(ci[4]);
  const other     = gv(ci[5]);
  const bottled   = gv(ci[6]);
  const sales     = gv(ci[12]);
  const totalMp   = gv(ci[15]);
  const cogsTh    = gv(ci[17]);
  const mpTh      = gv(ci[18]);

  const totalCogs = smKitchen + gulay + other + bottled;
  const cogsPct   = sales > 0 ? (totalCogs / sales * 100) : 0;
  const mpPct     = sales > 0 ? (totalMp   / sales * 100) : 0;

  ci[13].value = totalCogs.toFixed(2);
  ci[14].value = cogsPct.toFixed(1) + '%';
  ci[16].value = mpPct.toFixed(1) + '%';

  const cogsBadge = tr.querySelector('[data-role="cogsStatus"]');
  const cogsOver  = cogsPct > cogsTh;
  cogsBadge.textContent = cogsOver ? 'COGS OVER COST' : 'COGS PASSED';
  cogsBadge.className = 'badge ' + (cogsOver ? 'badge-bad' : 'badge-ok');

  const mpBadge = tr.querySelector('[data-role="mpStatus"]');
  const mpPassed = mpPct < mpTh;
  mpBadge.textContent = mpPassed ? 'MP PASSED' : 'MP FAILED';
  mpBadge.className = 'badge ' + (mpPassed ? 'badge-ok' : 'badge-bad');
}

function addRow() {
  const tbody = document.getElementById('cogsTbody');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td><input class="ci" type="date" value="<?=date('Y-m-d')?>"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" onfocus="this.select()"></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="number" value="0.00" readonly tabindex="-1"></td>
    <td><input class="ci calc" type="text" value="0.0%" readonly tabindex="-1"></td>
    <td><span class="badge badge-ok" data-role="cogsStatus">COGS PASSED</span></td>
    <td><input class="ci" type="number" step="0.01" min="0" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
    <td><input class="ci calc" type="text" value="0.0%" readonly tabindex="-1"></td>
    <td><span class="badge badge-ok" data-role="mpStatus">MP PASSED</span></td>
    <td class="th-cell">
      <input class="ci th-inp" type="number" step="0.1" min="0" value="45" title="COGS Max %" oninput="calcRow(this)">
      <input class="ci th-inp" type="number" step="0.1" min="0" value="7" title="MP Max %" oninput="calcRow(this)">
    </td>
    <td class="act-td">
      <button class="btn-sv" onclick="saveRow(this)">Save</button>
      <button class="btn-dl" onclick="deleteRow(this)">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[type="date"]').focus();
}

async function saveRow(btn) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const ci = tr.querySelectorAll('.ci');
  const fd = new FormData();
  fd.append('ajax_action','save_row');
  fd.append('id', tr.dataset.id||0);
  fd.append('entry_date', ci[0].value);
  fd.append('sm_kitchen_copy', gv(ci[1]));
  fd.append('rhea_copy', gv(ci[2]));
  fd.append('rhea_copy_parcel', gv(ci[3]));
  fd.append('gulay', gv(ci[4]));
  fd.append('other_expenses', gv(ci[5]));
  fd.append('bottled_water_sodas', gv(ci[6]));
  fd.append('transpo_df_expenses', gv(ci[7]));
  fd.append('staff_meal_expenses', gv(ci[8]));
  fd.append('office_supplies', gv(ci[9]));
  fd.append('dining_supplies', gv(ci[10]));
  fd.append('refund_misc', gv(ci[11]));
  fd.append('total_sales_per_day', gv(ci[12]));
  fd.append('total_mp', gv(ci[15]));
  fd.append('cogs_threshold_pct', gv(ci[17]));
  fd.append('mp_threshold_pct', gv(ci[18]));
  fd.append('sort_order', Array.from(tr.parentElement.children).indexOf(tr));
  btn.textContent='…'; btn.disabled=true;
  try {
    const r = await fetch('stella_cogs_monitoring.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
      tr.dataset.id = d.id;
      ci[13].value = Number(d.totalCogs).toFixed(2);
      ci[14].value = Number(d.cogsPct).toFixed(1) + '%';
      ci[16].value = Number(d.mpPct).toFixed(1) + '%';
      const cogsBadge = tr.querySelector('[data-role="cogsStatus"]');
      cogsBadge.textContent = d.cogsStatus;
      cogsBadge.className = 'badge ' + (d.cogsStatus==='COGS PASSED' ? 'badge-ok' : 'badge-bad');
      const mpBadge = tr.querySelector('[data-role="mpStatus"]');
      mpBadge.textContent = d.mpStatus;
      mpBadge.className = 'badge ' + (d.mpStatus==='MP PASSED' ? 'badge-ok' : 'badge-bad');
      st.textContent='✓'; st.style.display='block';
      setTimeout(()=>st.style.display='none',2000);
    } else { alert('Error: '+d.msg); }
  } catch(e){ alert('Network error'); }
  btn.textContent='Save'; btn.disabled=false;
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  if (id>0 && !confirm('Delete this entry?')) return;
  if (id>0) {
    const fd=new FormData(); fd.append('ajax_action','delete_row'); fd.append('id',id);
    await fetch('stella_cogs_monitoring.php',{method:'POST',body:fd});
  }
  tr.remove();
}
</script>

  </div></div>
</body>
</html>