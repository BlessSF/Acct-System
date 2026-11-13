<?php
// ============================================================
//  stella_cogs_monitoring.php — Stella Daily COGS Monitoring
//  Layout and formulas follow the "STELLA COGS 2026 MONITORING" Excel:
//    C  NON COGS billing = B − D
//    P  TOTAL COGS       = B + F + G + H + I + J      (gas not included)
//    Q  COGS %           = P / W
//    R  MARKETING AT COST= U × 45%
//    X  AUTO WARNED      = Q < 45.5% → "COGS PASSED" else "COGS OVER COST"
//    AB MP COST %        = AA / W
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
    `sm_kitchen_copy`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Stella kitchen copy — billing to Commi (COGS)',
    `rhea_copy`           decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Rhea copy — billing to Commi (COGS)',
    `rhea_copy_parcel`    decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Gulay billing',
    `gulay`               decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Gulay (store expenses)',
    `other_expenses`      decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(COGS) tube ice, drinks, etc.',
    `bottled_water_sodas` decimal(12,2) NOT NULL DEFAULT 0.00,
    `transpo_df_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
    `staff_meal_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
    `office_supplies`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
    `dining_supplies`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
    `refund_misc`         decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Miscellaneous (NON COGS)',
    `total_sales_per_day` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total sales from POS',
    `total_mp`            decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Manpower',
    `cogs_threshold_pct`  decimal(5,2)  NOT NULL DEFAULT 45.50,
    `mp_threshold_pct`    decimal(5,2)  NOT NULL DEFAULT 7.00,
    `sort_order`          int(4) NOT NULL DEFAULT 0,
    `saved_by`            varchar(100) DEFAULT NULL,
    `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// columns added for the Excel layout (guarded, run once)
try {
    $have = $pdo->query("SHOW COLUMNS FROM `stella_cogs_monitoring`")->fetchAll(PDO::FETCH_COLUMN);
    $add = [
        'gulay_store_copy' => "Gulay store copy (Commi bill)",
        'mikmik'           => "Seafood — Mik Mik",
        'direct_purchases' => "Seafood — direct purchases (asstd)",
        'daily_sales'      => "Total sales per day",
        'extra_fees'       => "Extra fees / voucher sold",
        'mkt_pullout'      => "Marketing pull-out",
        'gross_sales'      => "Gross sales (POS reading less marketing pull-out)",
        'gas_kg'           => "Gas consumption — KG",
        'gas_amount'       => "Gas consumption — amount",
    ];
    foreach ($add as $col => $cm) if (!in_array($col, $have, true))
        $pdo->exec("ALTER TABLE `stella_cogs_monitoring` ADD COLUMN `$col` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '" . addslashes($cm) . "'");
    // bottled water / sodas now belong to "Others expenses (tube ice, drinks, etc.)"
    $pdo->exec("UPDATE `stella_cogs_monitoring` SET other_expenses = other_expenses + bottled_water_sodas, bottled_water_sodas = 0 WHERE bottled_water_sodas <> 0");
} catch (Throwable $e) {}

const COGS_LIMIT = 45.5;   // % — "COGS PASSED" below this, "COGS OVER COST" otherwise (same as the Excel)

// DB column ⇄ field order used by the table (index = position in the row)
$FIELDS = ['sm_kitchen_copy','rhea_copy','rhea_copy_parcel','gulay_store_copy','gulay','mikmik','direct_purchases','other_expenses',
           'transpo_df_expenses','staff_meal_expenses','office_supplies','dining_supplies','refund_misc',
           'daily_sales','extra_fees','mkt_pullout','gross_sales','total_sales_per_day','gas_kg','gas_amount','total_mp'];

// ── AJAX: Save row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $id         = (int)($_POST['id'] ?? 0);
        $store_name = isBranch() ? currentBranch() : 'Stella';
        $entryDate  = $_POST['entry_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) { echo json_encode(['ok'=>false,'msg'=>'Pick a date first']); exit; }
        $vals = [];
        foreach ($FIELDS as $f) $vals[$f] = (float)str_replace(',', '', (string)($_POST[$f] ?? 0));
        $so = (int)($_POST['sort_order'] ?? 0);
        if ($id > 0) {
            $sets = implode(',', array_map(fn($f) => "`$f`=?", $FIELDS));
            $pdo->prepare("UPDATE stella_cogs_monitoring SET entry_date=?,store_name=?,$sets,cogs_threshold_pct=?,sort_order=?,saved_by=? WHERE id=?")
                ->execute(array_merge([$entryDate,$store_name], array_values($vals), [COGS_LIMIT,$so,$user['name'],$id]));
        } else {
            $cols = implode(',', array_map(fn($f) => "`$f`", $FIELDS));
            $qs   = implode(',', array_fill(0, count($FIELDS), '?'));
            $pdo->prepare("INSERT INTO stella_cogs_monitoring (entry_date,store_name,$cols,cogs_threshold_pct,sort_order,saved_by) VALUES (?,?,$qs,?,?,?)")
                ->execute(array_merge([$entryDate,$store_name], array_values($vals), [COGS_LIMIT,$so,$user['name']]));
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Import rows (from an Excel month sheet, parsed in the browser) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'import_rows') {
    header('Content-Type: application/json');
    try {
        $in = json_decode($_POST['rows'] ?? '[]', true);
        if (!is_array($in) || !$in) { echo json_encode(['ok'=>false,'msg'=>'Nothing to import.']); exit; }
        if (count($in) > 400) { echo json_encode(['ok'=>false,'msg'=>'Too many rows.']); exit; }
        $overwrite  = ($_POST['overwrite'] ?? '1') === '1';
        $store_name = isBranch() ? currentBranch() : 'Stella';
        $sel = $pdo->prepare("SELECT id FROM stella_cogs_monitoring WHERE store_name=? AND entry_date=? ORDER BY id LIMIT 1");
        $sets = implode(',', array_map(fn($f) => "`$f`=?", $FIELDS));
        $upd = $pdo->prepare("UPDATE stella_cogs_monitoring SET $sets,cogs_threshold_pct=?,sort_order=?,saved_by=? WHERE id=?");
        $cols = implode(',', array_map(fn($f) => "`$f`", $FIELDS));
        $qs   = implode(',', array_fill(0, count($FIELDS), '?'));
        $ins = $pdo->prepare("INSERT INTO stella_cogs_monitoring (entry_date,store_name,$cols,cogs_threshold_pct,sort_order,saved_by) VALUES (?,?,$qs,?,?,?)");
        $nIns = $nUpd = $nSkip = 0;
        $pdo->beginTransaction();
        foreach ($in as $r) {
            $d = (string)($r['entry_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) continue;
            $vals = [];
            foreach ($FIELDS as $f) $vals[] = round((float)($r[$f] ?? 0), 2);
            $so = (int)substr($d, 8, 2);
            $sel->execute([$store_name, $d]);
            $eid = (int)$sel->fetchColumn();
            if ($eid > 0) {
                if (!$overwrite) { $nSkip++; continue; }
                $upd->execute(array_merge($vals, [COGS_LIMIT, $so, $user['name'], $eid]));
                $nUpd++;
            } else {
                $ins->execute(array_merge([$d, $store_name], $vals, [COGS_LIMIT, $so, $user['name']]));
                $nIns++;
            }
        }
        $pdo->commit();
        echo json_encode(['ok'=>true,'inserted'=>$nIns,'updated'=>$nUpd,'skipped'=>$nSkip]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_row') {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM stella_cogs_monitoring WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Filters ───────────────────────────────────────────────
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));
$mStart = sprintf('%04d-%02d-01', $fYear, $fMonth);
$mEnd   = date('Y-m-t', strtotime($mStart));

[$bClause, $bParams] = branchFilter('store_name');
$stmt = $pdo->prepare("SELECT * FROM stella_cogs_monitoring WHERE entry_date BETWEEN ? AND ? AND $bClause ORDER BY entry_date,sort_order,id");
$stmt->execute(array_merge([$mStart, $mEnd], $bParams));
$rows = $stmt->fetchAll();

// the Excel formulas
function calcRow(array $r): array {
    $b = (float)$r['sm_kitchen_copy']; $d = (float)$r['rhea_copy'];
    $p = $b + (float)$r['gulay_store_copy'] + (float)$r['gulay'] + (float)$r['mikmik'] + (float)$r['direct_purchases'] + (float)$r['other_expenses'];
    $w = (float)$r['total_sales_per_day'];
    return [
        'nonCogs' => $b - $d,
        'cogs'    => $p,
        'pct'     => $w > 0 ? $p / $w * 100 : null,
        'mktCost' => (float)$r['mkt_pullout'] * 0.45,
        'status'  => $w > 0 ? (($p / $w * 100) < COGS_LIMIT ? 'COGS PASSED' : 'COGS OVER COST') : '',
        'mpPct'   => $w > 0 ? (float)$r['total_mp'] / $w * 100 : null,
    ];
}
$nf = fn($v) => number_format((float)$v, 2);

// ── Excel export ──────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';
    $x = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $x->title('STELLA COGS MONITORING');
    $x->subtitle(strtoupper($months[$fMonth-1]) . ' ' . $fYear);
    $x->blank();
    $x->header(['DATE','STELLA KITCHEN COPY (COGS)','BILLING TO COMMI (NON COGS)','RHEA COPY (COGS)','GULAY BILLING','GULAY STORE COPY (COMMI BILL)',
        'GULAY (STORE EXPENSES)','MIK MIK','DIRECT PURCHASES (ASSTD)','OTHERS EXPENSES (COGS)','TRANSPO (NON COGS)','STAFF MEAL (NON COGS)',
        'OFFICE SUPPLIES','DINING SUPPLIES','MISCELLANEOUS (NON COGS)','TOTAL COGS','%','MARKETING PULL-OUT AT COST','TOTAL SALES PER DAY',
        'EXTRA FEES / VOUCHER SOLD','MARKETING PULL-OUT','GROSS SALES','TOTAL SALES FROM POS','AUTO WARNED','GAS KG','GAS AMOUNT','MANPOWER','MP COST AVERAGE (%)']);
    $T = array_fill_keys(['b','nc','d','e','f','g','h','i','j','k','l','m','n','o','p','r','s','t','u','v','w','y','z','aa'], 0.0);
    foreach ($rows as $r) {
        $c = calcRow($r);
        $T['b']+=$r['sm_kitchen_copy']; $T['nc']+=$c['nonCogs']; $T['d']+=$r['rhea_copy']; $T['e']+=$r['rhea_copy_parcel']; $T['f']+=$r['gulay_store_copy'];
        $T['g']+=$r['gulay']; $T['h']+=$r['mikmik']; $T['i']+=$r['direct_purchases']; $T['j']+=$r['other_expenses']; $T['k']+=$r['transpo_df_expenses'];
        $T['l']+=$r['staff_meal_expenses']; $T['m']+=$r['office_supplies']; $T['n']+=$r['dining_supplies']; $T['o']+=$r['refund_misc']; $T['p']+=$c['cogs'];
        $T['r']+=$c['mktCost']; $T['s']+=$r['daily_sales']; $T['t']+=$r['extra_fees']; $T['u']+=$r['mkt_pullout']; $T['v']+=$r['gross_sales'];
        $T['w']+=$r['total_sales_per_day']; $T['y']+=$r['gas_kg']; $T['z']+=$r['gas_amount']; $T['aa']+=$r['total_mp'];
        $x->row([date('d-M-y', strtotime($r['entry_date'])), $r['sm_kitchen_copy'], round($c['nonCogs'],2), $r['rhea_copy'], $r['rhea_copy_parcel'], $r['gulay_store_copy'],
            $r['gulay'], $r['mikmik'], $r['direct_purchases'], $r['other_expenses'], $r['transpo_df_expenses'], $r['staff_meal_expenses'],
            $r['office_supplies'], $r['dining_supplies'], $r['refund_misc'], round($c['cogs'],2), $c['pct'] === null ? '' : round($c['pct'],2) . '%',
            round($c['mktCost'],2), $r['daily_sales'], $r['extra_fees'], $r['mkt_pullout'], $r['gross_sales'], $r['total_sales_per_day'], $c['status'],
            $r['gas_kg'], $r['gas_amount'], $r['total_mp'], $c['mpPct'] === null ? '' : round($c['mpPct'],2) . '%']);
    }
    $tp = $T['w'] > 0 ? $T['p'] / $T['w'] * 100 : null;
    $x->totalRow(['TOTAL', round($T['b'],2), round($T['nc'],2), round($T['d'],2), round($T['e'],2), round($T['f'],2), round($T['g'],2), round($T['h'],2), round($T['i'],2),
        round($T['j'],2), round($T['k'],2), round($T['l'],2), round($T['m'],2), round($T['n'],2), round($T['o'],2), round($T['p'],2), $tp === null ? '' : round($tp,2) . '%',
        round($T['r'],2), round($T['s'],2), round($T['t'],2), round($T['u'],2), round($T['v'],2), round($T['w'],2),
        $tp === null ? '' : ($tp < COGS_LIMIT ? 'COGS PASSED' : 'COGS OVER COST'), round($T['y'],2), round($T['z'],2), round($T['aa'],2),
        $T['w'] > 0 ? round($T['aa'] / $T['w'] * 100, 2) . '%' : '']);
    $x->blank();
    $x->footer('NOTE: COST OF GOODS SHOULD NOT EXCEED ' . COGS_LIMIT . '% — Generated by SalesHub ' . date('Y-m-d H:i:s'));
    $x->columnWidths(array_merge([11], array_fill(0, 27, 14)));
    $x->output('Stella_COGS_Monitoring_' . $fYear . '_' . sprintf('%02d', $fMonth) . '.xlsx');
    exit;
}

$pageTitle  = 'Stella COGS Monitoring';
$activePage = 'stella_cogs_monitoring';
include '../layout.php';

// one input cell
function cmi(string $f, $v, string $cls = ''): string {
    $show = ((float)$v) != 0 ? number_format((float)$v, 2) : '';
    return '<td><input class="ci num ' . $cls . '" data-f="' . $f . '" type="text" inputmode="decimal" autocomplete="off" value="' . $show . '" placeholder="0.00"></td>';
}
function cmc(string $k, string $txt, string $cls = ''): string {
    return '<td class="calc ' . $cls . '" data-c="' . $k . '">' . $txt . '</td>';
}
function rowHtml(array $r): string {
    $c = calcRow($r);
    $n = fn($v) => number_format((float)$v, 2);
    $h  = '<tr data-id="' . (int)$r['id'] . '">';
    $h .= '<td class="sticky-date"><input class="ci dt" data-f="entry_date" type="date" value="' . htmlspecialchars($r['entry_date']) . '"></td>';
    $h .= cmi('sm_kitchen_copy', $r['sm_kitchen_copy'], 'cg');
    $h .= cmc('nonCogs', $n($c['nonCogs']), 'nc');
    $h .= cmi('rhea_copy', $r['rhea_copy'], 'cg');
    $h .= cmi('rhea_copy_parcel', $r['rhea_copy_parcel']);
    $h .= cmi('gulay_store_copy', $r['gulay_store_copy'], 'cg');
    $h .= cmi('gulay', $r['gulay'], 'cg');
    $h .= cmi('mikmik', $r['mikmik'], 'cg');
    $h .= cmi('direct_purchases', $r['direct_purchases'], 'cg');
    $h .= cmi('other_expenses', $r['other_expenses'], 'cg');
    $h .= cmi('transpo_df_expenses', $r['transpo_df_expenses'], 'nn');
    $h .= cmi('staff_meal_expenses', $r['staff_meal_expenses'], 'nn');
    $h .= cmi('office_supplies', $r['office_supplies'], 'nn');
    $h .= cmi('dining_supplies', $r['dining_supplies'], 'nn');
    $h .= cmi('refund_misc', $r['refund_misc'], 'nn');
    $h .= cmc('cogs', $n($c['cogs']), 'tot');
    $h .= cmc('pct', $c['pct'] === null ? '' : number_format($c['pct'], 2) . '%', 'tot');
    $h .= cmc('mktCost', $n($c['mktCost']));
    $h .= cmi('daily_sales', $r['daily_sales']);
    $h .= cmi('extra_fees', $r['extra_fees']);
    $h .= cmi('mkt_pullout', $r['mkt_pullout']);
    $h .= cmi('gross_sales', $r['gross_sales']);
    $h .= cmi('total_sales_per_day', $r['total_sales_per_day'], 'pos');
    $h .= '<td class="calc" data-c="status">' . ($c['status'] === '' ? '' : '<span class="badge ' . ($c['status'] === 'COGS PASSED' ? 'badge-ok' : 'badge-bad') . '">' . $c['status'] . '</span>') . '</td>';
    $h .= cmi('gas_kg', $r['gas_kg']);
    $h .= cmi('gas_amount', $r['gas_amount']);
    $h .= cmi('total_mp', $r['total_mp']);
    $h .= cmc('mpPct', $c['mpPct'] === null ? '' : number_format($c['mpPct'], 2) . '%');
    $h .= '<td class="act-td"><button class="btn-dl" onclick="deleteRow(this)" title="Delete">✕</button><div class="row-st"></div></td>';
    return $h . '</tr>';
}
$blank = array_fill_keys(array_merge($FIELDS, ['entry_date','id']), 0); $blank['entry_date'] = ''; $blank['id'] = 0;
?>

<div class="section-header">
  <div>
    <div class="section-title">Stella COGS <span>Monitoring</span></div>
    <div class="section-subtitle">Daily COGS &amp; Manpower — <?=$months[$fMonth-1]?> <?=$fYear?> · same layout and formulas as the Excel</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button type="button" class="btn btn-ghost" onclick="openImport()">⬆ Import Excel</button>
    <a class="btn btn-primary" href="?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>">⬇ Download Excel</a>
  </div>
</div>

<!-- Import modal -->
<div id="impOverlay" class="imp-overlay" onclick="if(event.target===this)closeImport()">
  <div class="imp-box">
    <div class="imp-head"><b>Import COGS Monitoring from Excel</b><button type="button" class="imp-x" onclick="closeImport()">✕</button></div>
    <div class="imp-body">
      <label class="imp-lbl">1 · Excel file (.xlsx)</label>
      <input type="file" id="impFile" accept=".xlsx,.xlsm,.xls" onchange="impLoad(this.files[0])">
      <div id="impStep2" style="display:none">
        <label class="imp-lbl">2 · Which sheet (month) in the Excel?</label>
        <select id="impSheet" onchange="impPick()"></select>
        <label class="imp-lbl">3 · Import into which month?</label>
        <div style="display:flex;gap:8px">
          <select id="impMonth" onchange="impPreview()"><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>"><?=$months[$m-1]?></option><?php endfor; ?></select>
          <select id="impYear" onchange="impPreview()"><?php for($y=2050;$y>=2020;$y--): ?><option value="<?=$y?>"><?=$y?></option><?php endfor; ?></select>
        </div>
        <label class="imp-chk"><input type="checkbox" id="impOver" checked> Replace days that already have data (untick to keep them and only add new days)</label>
        <div id="impInfo" class="imp-info"></div>
        <div id="impPrev" class="imp-prev"></div>
      </div>
      <div id="impErr" class="imp-err"></div>
    </div>
    <div class="imp-foot">
      <button type="button" class="btn btn-ghost" onclick="closeImport()">Cancel</button>
      <button type="button" class="btn btn-primary" id="impGo" onclick="impRun()" disabled>Import</button>
    </div>
  </div>
</div>

<?php if (isset($_GET['imported'])): ?><div class="card" style="padding:10px 18px;margin-bottom:14px;color:var(--accent);font-family:var(--font-m);font-size:.78rem;font-weight:700">✓ Imported <?= (int)$_GET['imported'] ?> day(s) from Excel.</div><?php endif; ?>

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

<div class="card" style="padding:0">
  <div class="legend">
    <span><i class="lg cg"></i> COGS (counted in TOTAL COGS)</span>
    <span><i class="lg nn"></i> NON COGS</span>
    <span>TOTAL COGS = Stella kitchen copy + Gulay + Seafood + Others (gas not included) · COGS % = TOTAL COGS ÷ TOTAL SALES FROM POS · must stay under <?= COGS_LIMIT ?>%</span>
  </div>
  <div class="tblwrap">
  <table class="cogs-tbl" id="cogsTbl">
    <thead>
      <tr>
        <th rowspan="2" class="sticky-date">DATE</th>
        <th colspan="4" class="g-commi">COMMI BILL (POS)</th>
        <th colspan="2" class="g-cogs">GULAY EXPENSES</th>
        <th colspan="2" class="g-cogs">SEAFOOD</th>
        <th rowspan="2" class="g-cogs">OTHERS EXPENSES (COGS)<br><span class="sub">TUBE ICE, DRINKS, ETC.</span></th>
        <th rowspan="2" class="g-non">TRANSPO OF EXPENSES<br><span class="sub">FARE AND DELIVERY FEE (NON COGS)</span></th>
        <th rowspan="2" class="g-non">STAFF MEAL EXPENSES<br><span class="sub">(NON COGS)</span></th>
        <th colspan="2" class="g-non">FRONTLINE EXPENSES (NON COGS)</th>
        <th rowspan="2" class="g-non">MISCELLANEOUS<br><span class="sub">(NON COGS)</span></th>
        <th colspan="2" class="g-tot">TOTAL</th>
        <th rowspan="2">MARKETING PULL-OUT AT COST</th>
        <th rowspan="2">TOTAL SALES PER DAY</th>
        <th rowspan="2">EXTRA FEES / VOUCHER SOLD</th>
        <th rowspan="2">MARKETING PULL-OUT</th>
        <th rowspan="2">GROSS SALES<br><span class="sub">(POS READING LESS MARKETING PULL-OUT)</span></th>
        <th rowspan="2">TOTAL SALES FROM POS</th>
        <th rowspan="2" class="g-tot">AUTO WARNED<br><span class="sub">(IF COGS EXCEED)</span></th>
        <th colspan="2">GAS CONSUMPTIONS</th>
        <th rowspan="2">MANPOWER</th>
        <th rowspan="2">MP COST AVERAGE (%)</th>
        <th rowspan="2"></th>
      </tr>
      <tr>
        <th class="g-commi">STELLA KITCHEN COPY<br><span class="sub">BILLING TO COMMI (COGS)</span></th>
        <th class="g-commi">BILLING TO COMMI<br><span class="sub">(NON COGS)</span></th>
        <th class="g-commi">RHEA COPY<br><span class="sub">BILLING TO COMMI (COGS)</span></th>
        <th class="g-commi">GULAY BILLING</th>
        <th class="g-cogs">GULAY STORE COPY<br><span class="sub">(COMMI BILL)</span></th>
        <th class="g-cogs">GULAY<br><span class="sub">(STORE EXPENSES)</span></th>
        <th class="g-cogs">MIK MIK</th>
        <th class="g-cogs">DIRECT PURCHASES<br><span class="sub">(ASSTD)</span></th>
        <th class="g-non">OFFICE SUPPLIES</th>
        <th class="g-non">DINING SUPPLIES<br><span class="sub">(TISSUE, CLEANING MATERIALS)</span></th>
        <th class="g-tot">TOTAL COGS<br><span class="sub">(NOT INCLUDED GAS)</span></th>
        <th class="g-tot">%</th>
        <th>KG</th>
        <th>AMOUNT</th>
      </tr>
    </thead>
    <tbody id="cogsTbody"><?php foreach ($rows as $r) echo rowHtml($r); ?></tbody>
    <tfoot>
      <tr id="totRow">
        <td class="sticky-date">TOTAL</td>
        <?php
        $totCols = ['sm_kitchen_copy','nonCogs','rhea_copy','rhea_copy_parcel','gulay_store_copy','gulay','mikmik','direct_purchases','other_expenses',
                    'transpo_df_expenses','staff_meal_expenses','office_supplies','dining_supplies','refund_misc','cogs','pct','mktCost',
                    'daily_sales','extra_fees','mkt_pullout','gross_sales','total_sales_per_day','status','gas_kg','gas_amount','total_mp','mpPct'];
        foreach ($totCols as $tc) echo '<td data-t="' . $tc . '"></td>';
        ?>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <div class="btn-row">
    <button class="btn-add" onclick="addRow()">+ Add Row</button>
    <button class="btn-add alt" onclick="addMonthDays()">+ Add all days of <?=$months[$fMonth-1]?></button>
  </div>
  <div class="note">NOTE: COST OF GOODS SHOULD NOT EXCEED <?= COGS_LIMIT ?>% · changes save automatically</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
.legend { display:flex; gap:18px; flex-wrap:wrap; align-items:center; padding:10px 16px; font-family:var(--font-m); font-size:.66rem; color:var(--subtext); border-bottom:1px solid var(--border); }
.legend .lg { display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:-2px; margin-right:4px; }
.lg.cg { background:rgba(34,211,165,.35); } .lg.nn { background:rgba(148,163,184,.4); }
.tblwrap { overflow:auto; max-height:72vh; }
.cogs-tbl { border-collapse:separate; border-spacing:0; font-family:var(--font-m); font-size:.72rem; min-width:2900px; }
.cogs-tbl thead th {
  background:var(--surf2); color:var(--subtext); text-transform:uppercase; font-size:.58rem; letter-spacing:.03em;
  padding:7px 6px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:center; line-height:1.3;
  position:sticky; top:0; z-index:5; vertical-align:middle;
}
.cogs-tbl thead tr:nth-child(2) th { top:34px; }
.cogs-tbl thead .sub { display:block; font-size:.52rem; color:var(--subtext2); text-transform:none; font-weight:400; }
.cogs-tbl thead th.g-commi { background:rgba(99,102,241,.16); }
.cogs-tbl thead th.g-cogs  { background:rgba(34,211,165,.18); }
.cogs-tbl thead th.g-non   { background:rgba(148,163,184,.22); }
.cogs-tbl thead th.g-tot   { background:rgba(245,158,11,.22); }
.cogs-tbl tbody td, .cogs-tbl tfoot td { padding:3px 4px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:right; min-width:92px; background:var(--surface); }
.cogs-tbl .sticky-date { position:sticky; left:0; z-index:6; min-width:128px; text-align:center; font-weight:700; }
.cogs-tbl thead th.sticky-date { z-index:8; }
.cogs-tbl tbody tr:hover td { background:var(--surf2); }
.cogs-tbl .ci { width:100%; min-width:84px; padding:5px 6px; border:1px solid transparent; border-radius:5px; background:transparent; color:var(--text); font-family:var(--font-m); font-size:.72rem; text-align:right; }
.cogs-tbl .ci.dt { text-align:center; }
.cogs-tbl .ci:hover { border-color:var(--border); }
.cogs-tbl .ci:focus { outline:none; border-color:var(--accent); background:var(--surface); box-shadow:0 0 0 2px rgba(34,211,165,.18); }
.cogs-tbl .ci.cg { background:rgba(34,211,165,.06); } .cogs-tbl .ci.nn { background:rgba(148,163,184,.08); }
.cogs-tbl td.calc { font-weight:600; color:var(--subtext); }
.cogs-tbl td.calc.tot { color:var(--text); background:rgba(245,158,11,.08); }
.cogs-tbl tfoot td { position:sticky; bottom:0; z-index:4; font-weight:800; background:var(--surf2); color:var(--text); padding:9px 8px; border-top:2px solid var(--border); }
.cogs-tbl tfoot td.sticky-date { z-index:7; }
.badge { display:inline-block; font-family:var(--font-m); font-size:.6rem; font-weight:700; padding:4px 8px; border-radius:20px; white-space:nowrap; }
.badge-ok  { background:rgba(34,211,165,.14); color:var(--accent); border:1px solid rgba(34,211,165,.25); }
.badge-bad { background:rgba(248,113,113,.14); color:var(--accent2); border:1px solid rgba(248,113,113,.25); }
.act-td { white-space:nowrap; text-align:center !important; min-width:70px !important; }
.btn-dl { font-family:var(--font-m); font-size:.66rem; padding:4px 8px; border-radius:6px; border:1px solid rgba(248,113,113,.25); background:rgba(248,113,113,.08); color:var(--accent2); cursor:pointer; }
.row-st { font-size:.6rem; color:var(--accent); min-height:12px; }
.btn-row { display:flex; gap:10px; padding:12px 16px 4px; flex-wrap:wrap; }
.btn-add { padding:9px 14px; background:rgba(34,211,165,.06); border:1px dashed rgba(34,211,165,.3); color:var(--accent); border-radius:8px; cursor:pointer; font-family:var(--font-m); font-size:.74rem; }
.btn-add:hover { background:rgba(34,211,165,.12); }
.btn-add.alt { border-color:rgba(99,102,241,.35); color:#818cf8; background:rgba(99,102,241,.06); }
.imp-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9999; align-items:center; justify-content:center; padding:16px; }
.imp-overlay.open { display:flex; }
.imp-box { background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:12px; width:min(560px,100%); max-height:92vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.35); }
.imp-head { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--border); font-size:.95rem; }
.imp-x { background:none; border:none; color:var(--subtext); font-size:1rem; cursor:pointer; }
.imp-body { padding:14px 18px; overflow:auto; display:flex; flex-direction:column; gap:6px; font-family:var(--font-m); font-size:.76rem; }
.imp-body select, .imp-body input[type=file] { padding:7px 8px; border:1px solid var(--border); border-radius:7px; background:var(--surface); color:var(--text); font-family:var(--font-m); font-size:.76rem; }
#impStep2 { flex-direction:column; gap:6px; }
.imp-lbl { display:block; font-weight:700; margin-top:8px; color:var(--subtext); text-transform:uppercase; font-size:.62rem; letter-spacing:.05em; }
.imp-chk { display:flex; gap:7px; align-items:flex-start; margin-top:10px; color:var(--subtext); }
.imp-info { margin-top:10px; font-weight:700; }
.imp-prev { max-height:170px; overflow:auto; border:1px solid var(--border); border-radius:7px; display:none; }
.imp-prev table { width:100%; border-collapse:collapse; font-size:.68rem; }
.imp-prev th, .imp-prev td { padding:4px 8px; border-bottom:1px solid var(--border); text-align:right; }
.imp-prev th:first-child, .imp-prev td:first-child { text-align:left; }
.imp-err { color:var(--accent2); font-weight:700; }
.imp-foot { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid var(--border); }
.imp-foot .btn[disabled] { opacity:.45; cursor:not-allowed; }
.note { padding:6px 16px 14px; font-family:var(--font-m); font-size:.64rem; color:var(--subtext2); }
</style>

<script>
const LIMIT = <?= COGS_LIMIT ?>;
const FIELDS = <?= json_encode($FIELDS) ?>;
const YM = '<?= sprintf('%04d-%02d', $fYear, $fMonth) ?>';
const LAST_DAY = <?= (int)date('t', strtotime($mStart)) ?>;
const ROW_TPL = <?= json_encode(rowHtml($blank)) ?>;

const numOf = v => { const x = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(x) ? x : 0; };
const fmt   = n => (+n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const pctS  = n => (+n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%';
const val   = (tr, f) => numOf(tr.querySelector('[data-f="' + f + '"]')?.value);

// live thousands separators while typing (cursor stays put)
function commaLive(el) {
  const v = el.value, pos = el.selectionStart == null ? v.length : el.selectionStart;
  const before = v.slice(0, pos).replace(/[^0-9.\-]/g, '').length;
  let c = v.replace(/[^0-9.\-]/g, ''); const neg = c.charAt(0) === '-'; c = c.replace(/-/g, '');
  const parts = c.split('.'); let ip = parts[0].replace(/^0+(?=\d)/, '');
  const dec = parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, 2) : '';
  ip = ip.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const out = (neg ? '-' : '') + ip + dec;
  if (out !== v) { el.value = out; let n = 0, i = 0; for (; i < out.length && n < before; i++) if (/[0-9.\-]/.test(out[i])) n++; try { el.setSelectionRange(i, i); } catch (e) {} }
}

// the Excel formulas, per row
function calc(tr) {
  const b = val(tr,'sm_kitchen_copy'), d = val(tr,'rhea_copy');
  const p = b + val(tr,'gulay_store_copy') + val(tr,'gulay') + val(tr,'mikmik') + val(tr,'direct_purchases') + val(tr,'other_expenses');
  const w = val(tr,'total_sales_per_day');
  const set = (k, t, html) => { const c = tr.querySelector('[data-c="' + k + '"]'); if (c) { if (html) c.innerHTML = t; else c.textContent = t; } };
  set('nonCogs', fmt(b - d));
  set('cogs', fmt(p));
  set('pct', w > 0 ? pctS(p / w * 100) : '');
  set('mktCost', fmt(val(tr,'mkt_pullout') * 0.45));
  const ok = w > 0 && (p / w * 100) < LIMIT;
  set('status', w > 0 ? '<span class="badge ' + (ok ? 'badge-ok' : 'badge-bad') + '">' + (ok ? 'COGS PASSED' : 'COGS OVER COST') + '</span>' : '', true);
  set('mpPct', w > 0 ? pctS(val(tr,'total_mp') / w * 100) : '');
}

function totals() {
  const T = {}; const add = (k, v) => T[k] = (T[k] || 0) + v;
  document.querySelectorAll('#cogsTbody tr').forEach(tr => {
    FIELDS.forEach(f => add(f, val(tr, f)));
    const num = k => numOf(tr.querySelector('[data-c="' + k + '"]')?.textContent);
    add('nonCogs', num('nonCogs')); add('cogs', num('cogs')); add('mktCost', num('mktCost'));
  });
  document.querySelectorAll('#totRow [data-t]').forEach(td => {
    const k = td.dataset.t;
    if (k === 'pct')    td.textContent = T.total_sales_per_day > 0 ? pctS(T.cogs / T.total_sales_per_day * 100) : '';
    else if (k === 'mpPct') td.textContent = T.total_sales_per_day > 0 ? pctS(T.total_mp / T.total_sales_per_day * 100) : '';
    else if (k === 'status') {
      if (T.total_sales_per_day > 0) { const ok = (T.cogs / T.total_sales_per_day * 100) < LIMIT; td.innerHTML = '<span class="badge ' + (ok ? 'badge-ok' : 'badge-bad') + '">' + (ok ? 'COGS PASSED' : 'COGS OVER COST') + '</span>'; }
      else td.innerHTML = '';
    } else td.textContent = fmt(T[k] || 0);
  });
}

// auto-save (debounced)
function status(tr, t, color) { const s = tr.querySelector('.row-st'); if (s) { s.textContent = t; s.style.color = color || ''; } }
function changed(el) {
  const tr = el.closest('tr');
  if (el.classList.contains('num')) commaLive(el);
  calc(tr); totals();
  status(tr, 'typing…', 'var(--subtext)');
  clearTimeout(tr._t); tr._t = setTimeout(() => saveRow(tr), 700);
}
async function saveRow(tr) {
  if (tr._deleted) return;
  if (tr._saving) { tr._again = true; return; }
  const date = tr.querySelector('[data-f="entry_date"]').value;
  if (!date) { status(tr, 'pick a date', 'var(--accent2)'); return; }
  tr._saving = true;
  const fd = new FormData();
  fd.append('ajax_action', 'save_row'); fd.append('id', tr.dataset.id || 0); fd.append('entry_date', date);
  FIELDS.forEach(f => fd.append(f, val(tr, f)));
  fd.append('sort_order', Array.from(tr.parentElement.children).indexOf(tr));
  try {
    const d = await (await fetch('stella_cogs_monitoring.php', {method:'POST', body:fd})).json();
    if (d.ok) {
      tr.dataset.id = d.id;
      if (tr._deleted) { const f = new FormData(); f.append('ajax_action','delete_row'); f.append('id', d.id); fetch('stella_cogs_monitoring.php', {method:'POST', body:f}); }
      else status(tr, '✓ saved', 'var(--accent)');
    } else status(tr, '❌ ' + d.msg, 'var(--accent2)');
  } catch (e) { status(tr, '❌ network', 'var(--accent2)'); }
  tr._saving = false;
  if (tr._again) { tr._again = false; saveRow(tr); }
}

function wire(tr) {
  tr.querySelectorAll('[data-f]').forEach(el => {
    el.addEventListener('input', () => changed(el));
    el.addEventListener('change', () => changed(el));
    if (el.classList.contains('num')) {
      el.addEventListener('focus', () => el.select());
      el.addEventListener('blur', () => { el.value = el.value.trim() === '' ? '' : fmt(numOf(el.value)); });
    }
  });
}
function newRow(date) {
  const tr = document.createElement('tr'); tr.dataset.id = '0';
  const tmp = document.createElement('tbody'); tmp.innerHTML = ROW_TPL;
  tr.innerHTML = tmp.firstElementChild.innerHTML;
  if (date) tr.querySelector('[data-f="entry_date"]').value = date;
  wire(tr); return tr;
}
function addRow() {
  const body = document.getElementById('cogsTbody');
  // next day after the last row of the month
  const dates = Array.from(body.querySelectorAll('[data-f="entry_date"]')).map(i => i.value).filter(Boolean).sort();
  let d = YM + '-01';
  if (dates.length) { const n = parseInt(dates[dates.length - 1].slice(8), 10) + 1; d = YM + '-' + String(Math.min(n, LAST_DAY)).padStart(2, '0'); }
  const tr = newRow(d); body.appendChild(tr); calc(tr); totals();
  tr.querySelector('[data-f="sm_kitchen_copy"]').focus();
}
function addMonthDays() {
  const body = document.getElementById('cogsTbody');
  const have = new Set(Array.from(body.querySelectorAll('[data-f="entry_date"]')).map(i => i.value));
  const fresh = [];
  for (let d = 1; d <= LAST_DAY; d++) { const ds = YM + '-' + String(d).padStart(2, '0'); if (!have.has(ds)) fresh.push(newRow(ds)); }
  // keep date order
  const all = Array.from(body.children).concat(fresh);
  all.sort((a, b) => a.querySelector('[data-f="entry_date"]').value.localeCompare(b.querySelector('[data-f="entry_date"]').value));
  all.forEach(tr => body.appendChild(tr));
  all.forEach(calc); totals();
}
async function deleteRow(btn) {
  const tr = btn.closest('tr'), id = parseInt(tr.dataset.id) || 0;
  if (id > 0 && btn.dataset.sure !== '1') {   // two clicks, no browser pop-up
    btn.dataset.sure = '1'; btn.textContent = 'Delete?'; btn.style.background = '#dc3545'; btn.style.color = '#fff';
    setTimeout(() => { if (btn.dataset.sure === '1') { btn.dataset.sure = ''; btn.textContent = '✕'; btn.style.background = ''; btn.style.color = ''; } }, 3500);
    return;
  }
  clearTimeout(tr._t); tr._again = false; tr._deleted = true;
  if (id > 0) {
    const fd = new FormData(); fd.append('ajax_action','delete_row'); fd.append('id', id);
    try {
      const j = await (await fetch('stella_cogs_monitoring.php', {method:'POST', body:fd})).json();
      if (!j.ok) { tr._deleted = false; status(tr, '❌ not deleted', 'var(--accent2)'); return; }
    } catch (e) { tr._deleted = false; status(tr, '❌ not deleted', 'var(--accent2)'); return; }
  }
  tr.remove(); totals();
}

document.querySelectorAll('#cogsTbody tr').forEach(tr => { wire(tr); calc(tr); });
totals();

/* ============ Import from Excel ============ */
let IMP = { sheets: {}, order: [] };
function openImport() {
  document.getElementById('impOverlay').classList.add('open');
  document.getElementById('impMonth').value = <?= $fMonth ?>;
  document.getElementById('impYear').value = <?= $fYear ?>;
}
function closeImport() { document.getElementById('impOverlay').classList.remove('open'); }
function impErr(t) { document.getElementById('impErr').textContent = t || ''; }

// header text of a column (rows 3-5 of the sheet, merged cells only carry text in their first cell) → DB field
const IMP_RULES = [
  [/BILLING TO COMMI\s*\(NON/, null],
  [/TOTAL COGS|AUTO WARNED|MP COST AVERAGE|AT COST|ACCTG TOTAL/, null],
  [/STELLA KITCHEN COPY/, 'sm_kitchen_copy'],
  [/RHEA COPY/, 'rhea_copy'],
  [/GULAY BILLING/, 'rhea_copy_parcel'],
  [/GULAY STORE COPY/, 'gulay_store_copy'],
  [/GULAY\s*\(STORE/, 'gulay'],
  [/MIK\s*MIK/, 'mikmik'],
  [/DIRECT PURCHASES/, 'direct_purchases'],
  [/OTHERS?\s+EXPENSES/, 'other_expenses'],
  [/TRANSPO/, 'transpo_df_expenses'],
  [/STAFF MEAL/, 'staff_meal_expenses'],
  [/OFFICE SUPPLIES/, 'office_supplies'],
  [/DINING SUPPLIES/, 'dining_supplies'],
  [/MISCELL/, 'refund_misc'],
  [/TOTAL SALES FROM POS/, 'total_sales_per_day'],
  [/TOTAL SALES PER DAY/, 'daily_sales'],
  [/EXTRA FEES/, 'extra_fees'],
  [/MARKETING PULL-?\s*OUT\s*$/, 'mkt_pullout'],
  [/GROSS SALES/, 'gross_sales'],
  [/(^|\s)KG(\s|$)/, 'gas_kg'],
  [/(^|\s)AMOUNT(\s|$)/, 'gas_amount'],
  [/MANPOWER/, 'total_mp']
];
function impNum(v) {
  if (typeof v === 'number') return isFinite(v) ? v : 0;
  if (typeof v === 'string') { const x = parseFloat(v.replace(/[₱,\s]/g, '')); return isFinite(x) ? x : 0; }
  return 0;
}
function impDate(v) {
  if (typeof v === 'number' && v > 36000 && v < 80000) { const d = XLSX.SSF.parse_date_code(v); if (d) return d.y + '-' + String(d.m).padStart(2,'0') + '-' + String(d.d).padStart(2,'0'); }
  if (v instanceof Date && !isNaN(v)) return v.getFullYear() + '-' + String(v.getMonth()+1).padStart(2,'0') + '-' + String(v.getDate()).padStart(2,'0');
  if (typeof v === 'string') {
    const t = v.trim(); let m;
    if ((m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/))) return m[1] + '-' + m[2].padStart(2,'0') + '-' + m[3].padStart(2,'0');
    if ((m = t.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/))) return m[3] + '-' + m[1].padStart(2,'0') + '-' + m[2].padStart(2,'0');
  }
  return null;
}
function impParseSheet(ws) {
  const a = XLSX.utils.sheet_to_json(ws, {header: 1, raw: true, defval: null});
  let h = -1;
  for (let i = 0; i < Math.min(a.length, 25); i++) if (String(a[i][0] == null ? '' : a[i][0]).trim().toUpperCase() === 'DATE') { h = i; break; }
  if (h < 0) return null;
  const width = Math.max.apply(null, a.slice(h, h + 3).map(r => r.length));
  const map = {};   // col index -> field
  const used = {};
  for (let c = 1; c < width; c++) {
    const txt = [h, h+1, h+2].map(r => a[r] && a[r][c] != null ? String(a[r][c]) : '').join(' ').replace(/\s+/g, ' ').toUpperCase().trim();
    if (!txt) continue;
    for (const [re, f] of IMP_RULES) {
      if (re.test(txt)) { if (f && !used[f]) { map[c] = f; used[f] = 1; } break; }
    }
  }
  if (!used.sm_kitchen_copy && !used.gulay && !used.total_sales_per_day && !used.gross_sales) return null;
  const rows = [];
  for (let i = h + 1; i < a.length; i++) {
    const d = impDate(a[i][0]); if (!d) continue;
    const o = {entry_date: d}; let any = false;
    FIELDS.forEach(f => o[f] = 0);
    Object.keys(map).forEach(c => { const v = Math.round(impNum(a[i][c]) * 100) / 100; o[map[c]] = v; if (v !== 0) any = true; });
    // older sheets have no "total sales from POS" column — they used GROSS SALES as the COGS % base
    if (!used.total_sales_per_day && used.gross_sales) o.total_sales_per_day = o.gross_sales;
    if (any) rows.push(o);
  }
  return rows;
}
function impMonthOf(rows) {   // most common YYYY-MM in the sheet
  const n = {}; rows.forEach(r => { const k = r.entry_date.slice(0, 7); n[k] = (n[k] || 0) + 1; });
  let best = null; Object.keys(n).forEach(k => { if (!best || n[k] > n[best]) best = k; }); return best;
}
function impLoad(file) {
  impErr(''); document.getElementById('impStep2').style.display = 'none'; document.getElementById('impGo').disabled = true;
  if (!file) return;
  if (typeof XLSX === 'undefined') { impErr('The Excel reader could not load (check your internet connection) and try again.'); return; }
  const rd = new FileReader();
  rd.onerror = () => impErr('Could not read that file.');
  rd.onload = e => {
    try {
      const wb = XLSX.read(new Uint8Array(e.target.result), {type: 'array'});
      IMP = {sheets: {}, order: []};
      wb.SheetNames.forEach(n => { const r = impParseSheet(wb.Sheets[n]); if (r && r.length) { IMP.sheets[n] = r; IMP.order.push(n); } });
      if (!IMP.order.length) { impErr('No COGS Monitoring data found in this file (no sheet with dates + amounts).'); return; }
      const sel = document.getElementById('impSheet'); sel.innerHTML = '';
      const mn = <?= json_encode($months) ?>;
      IMP.order.forEach(n => {
        const r = IMP.sheets[n], ym = impMonthOf(r), o = document.createElement('option'); o.value = n;
        o.textContent = n.trim() + '  —  ' + r.length + ' day(s) with data' + (ym ? ' · ' + mn[parseInt(ym.slice(5), 10) - 1] + ' ' + ym.slice(0, 4) : '');
        sel.appendChild(o);
      });
      // preselect the sheet that matches the month currently open on the page
      const cur = YM; let pick = IMP.order.find(n => impMonthOf(IMP.sheets[n]) === cur) || IMP.order[0];
      sel.value = pick; document.getElementById('impStep2').style.display = 'flex'; impPick();
    } catch (err) { impErr('Could not open that file: ' + err.message); }
  };
  rd.readAsArrayBuffer(file);
}
function impPick() {   // choosing a sheet auto-sets the target month to the month of its dates
  const rows = IMP.sheets[document.getElementById('impSheet').value] || [];
  const ym = impMonthOf(rows);
  if (ym) { document.getElementById('impYear').value = ym.slice(0, 4); document.getElementById('impMonth').value = parseInt(ym.slice(5), 10); }
  impPreview();
}
function impTarget() {
  const m = document.getElementById('impMonth').value, y = document.getElementById('impYear').value;
  return y + '-' + String(m).padStart(2, '0');
}
function impRows() {
  const t = impTarget();
  return (IMP.sheets[document.getElementById('impSheet').value] || []).filter(r => r.entry_date.slice(0, 7) === t);
}
function impPreview() {
  const rows = impRows(), info = document.getElementById('impInfo'), prev = document.getElementById('impPrev'), go = document.getElementById('impGo');
  impErr('');
  const mn = <?= json_encode($months) ?>, t = impTarget();
  const label = mn[parseInt(t.slice(5), 10) - 1] + ' ' + t.slice(0, 4);
  if (!rows.length) {
    info.textContent = 'This sheet has no days with data for ' + label + '. Pick another sheet or month.';
    prev.style.display = 'none'; go.disabled = true; return;
  }
  let sales = 0, cogs = 0;
  rows.forEach(r => { sales += r.total_sales_per_day; cogs += r.sm_kitchen_copy + r.gulay_store_copy + r.gulay + r.mikmik + r.direct_purchases + r.other_expenses; });
  info.textContent = rows.length + ' day(s) will be imported into ' + label + '  ·  Total sales ₱' + fmt(sales) + '  ·  Total COGS ₱' + fmt(cogs) + (sales > 0 ? ' (' + pctS(cogs / sales * 100) + ')' : '');
  prev.innerHTML = '<table><tr><th>Date</th><th>Kitchen copy</th><th>Total sales (POS)</th><th>Manpower</th></tr>' +
    rows.map(r => '<tr><td>' + r.entry_date + '</td><td>' + fmt(r.sm_kitchen_copy) + '</td><td>' + fmt(r.total_sales_per_day) + '</td><td>' + fmt(r.total_mp) + '</td></tr>').join('') + '</table>';
  prev.style.display = 'block'; go.disabled = false;
}
async function impRun() {
  const rows = impRows(); if (!rows.length) return;
  const go = document.getElementById('impGo'); go.disabled = true; go.textContent = 'Importing…'; impErr('');
  const fd = new FormData();
  fd.append('ajax_action', 'import_rows'); fd.append('rows', JSON.stringify(rows));
  fd.append('overwrite', document.getElementById('impOver').checked ? '1' : '0');
  try {
    const d = await (await fetch('stella_cogs_monitoring.php', {method: 'POST', body: fd})).json();
    if (d.ok) {
      const t = impTarget();
      window.location = 'stella_cogs_monitoring.php?month=' + parseInt(t.slice(5), 10) + '&year=' + t.slice(0, 4) + '&imported=' + (d.inserted + d.updated);
      return;
    }
    impErr('❌ ' + d.msg);
  } catch (e) { impErr('❌ Network error — nothing was imported.'); }
  go.disabled = false; go.textContent = 'Import';
}
</script>

  </div></div>
</body>