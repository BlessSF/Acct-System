<?php
// ============================================================
//  h_cogs_monitoring.php — H COGS Monitoring
//  Follows HERO_COGS_MONITORING_2026.xlsx (one tab per month):
//    TOTAL COGS = RHEA/COMMI COPY (Billing to Commi COGS) + SUM(Gulay Store Copy .. Tube Ice)
//               = F + H + I + K + L + M          (gas & manpower not included)
//    COGS %     = TOTAL COGS / GROSS SALES       status: COGS PASSED if COGS % < 30.5%
//    MP COST AVERAGE % = MANPOWER / GROSS SALES
//    Summary: TOTAL COGS = BEG COGS + ACCU COGS − END COGS ; COGS% = TOTAL COGS / ACCU SALES
//  Every change saves by itself. Amount cells accept Excel-style
//  formulas, e.g.  =4510+650+290
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'H') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Tables ───────────────────────────────────────────────
const HC_KEYS = ['kitchen_cogs','kitchen_noncogs','commi_cogs','gulay_billing','gulay_store','gulay_exp',
                 'direct','carwash','tube_ice','transpo','staff_meal','office','dining','misc',
                 'gross','gas_kg','gas_amt','manpower'];
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cogs_mon` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entry_date` date NOT NULL,
    " . implode(",\n    ", array_map(fn($k) => "`$k` decimal(14,2) NOT NULL DEFAULT 0.00", HC_KEYS)) . ",
    `formulas` text DEFAULT NULL,
    `saved_by` varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cogs_mon_set` (
    `ym` char(7) NOT NULL,
    `cogs_max_pct` decimal(5,2) NOT NULL DEFAULT 30.50,
    `beg_cogs` decimal(14,2) NOT NULL DEFAULT 0.00,
    `end_cogs` decimal(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// COGS columns of the sheet: F + H + I + K + L + M
const HC_COGS = ['commi_cogs','gulay_store','gulay_exp','direct','carwash','tube_ice'];

function hcCalc(array $r, float $max = 30.5): array {
    $g = fn($k) => (float)($r[$k] ?? 0);
    $cogs = 0; foreach (HC_COGS as $k) $cogs += $g($k);
    $gross = $g('gross');
    $pct   = $gross > 0 ? $cogs / $gross * 100 : 0;
    return [
        'cogs' => round($cogs, 2), 'gross' => $gross,
        'cogsPct' => $pct, 'mpPct' => $gross > 0 ? $g('manpower') / $gross * 100 : 0,
        'status' => $gross > 0 ? ($pct < $max ? 'COGS PASSED' : 'COGS OVER COST') : '',
    ];
}

// ── AJAX: save one day (empty row = delete) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $date = $_POST['entry_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['ok'=>false,'msg'=>'Bad date']); exit; }
        $vals = []; $any = false;
        foreach (HC_KEYS as $k) { $vals[$k] = round((float)str_replace(',', '', $_POST[$k] ?? 0), 2); if ($vals[$k] != 0) $any = true; }
        $fm = [];
        $in = json_decode($_POST['formulas'] ?? '{}', true) ?: [];
        foreach ($in as $k => $f) {
            if (in_array($k, HC_KEYS, true) && is_string($f) && preg_match('/^=[0-9+\-*\/(). ]{1,200}$/', $f)) $fm[$k] = $f;
        }
        if (!$any) {
            $pdo->prepare("DELETE FROM h_cogs_mon WHERE entry_date=?")->execute([$date]);
            echo json_encode(['ok'=>true,'deleted'=>true]); exit;
        }
        $cols = array_merge(['entry_date'], HC_KEYS, ['formulas','saved_by']);
        $params = array_merge([$date], array_values($vals), [$fm ? json_encode($fm) : null, $user['name']]);
        $upd = implode(',', array_map(fn($k)=>"`$k`=VALUES(`$k`)", array_merge(HC_KEYS, ['formulas','saved_by'])));
        $pdo->prepare("INSERT INTO h_cogs_mon (`".implode('`,`',$cols)."`) VALUES (".implode(',',array_fill(0,count($cols),'?')).") ON DUPLICATE KEY UPDATE $upd")->execute($params);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: save the month settings (COGS limit, BEG / END COGS) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_settings') {
    header('Content-Type: application/json');
    try {
        $ym = $_POST['ym'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { echo json_encode(['ok'=>false,'msg'=>'Bad month']); exit; }
        $n = fn($k) => round((float)str_replace(',', '', $_POST[$k] ?? 0), 2);
        $pdo->prepare("INSERT INTO h_cogs_mon_set (ym,cogs_max_pct,beg_cogs,end_cogs) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE cogs_max_pct=VALUES(cogs_max_pct),beg_cogs=VALUES(beg_cogs),end_cogs=VALUES(end_cogs)")
            ->execute([$ym,$n('cogs_max_pct'),$n('beg_cogs'),$n('end_cogs')]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Filters / data ───────────────────────────────────────
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$ym     = sprintf('%04d-%02d', $fYear, $fMonth);
$days   = (int)date('t', strtotime("$ym-01"));

$st = $pdo->prepare("SELECT * FROM h_cogs_mon WHERE entry_date BETWEEN ? AND ? ORDER BY entry_date");
$st->execute(["$ym-01", "$ym-".sprintf('%02d',$days)]);
$byDate = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $byDate[$r['entry_date']] = $r;

$ss = $pdo->prepare("SELECT * FROM h_cogs_mon_set WHERE ym=?");
$ss->execute([$ym]);
$set = $ss->fetch(PDO::FETCH_ASSOC) ?: ['cogs_max_pct'=>30.5,'beg_cogs'=>0,'end_cogs'=>0];
$maxPct = (float)$set['cogs_max_pct'];

$tot = array_fill_keys(HC_KEYS, 0.0);
foreach ($byDate as $r) foreach (HC_KEYS as $k) $tot[$k] += (float)$r[$k];
$tc = hcCalc($tot, $maxPct);

// ── CSV export (same column order as the Excel) ──────────
$CSV_HEAD = ['DATE','HERO KITCHEN COPY - BILLING TO COMMI (COGS)','HERO KITCHEN COPY - BILLING TO COMMI (NON COGS)',
    'RHEA/COMMI COPY - BILLING TO COMMI (COGS)','RHEA/COMMI COPY - GULAY BILLING','GULAY STORE COPY (COMMI BILL)','GULAY (STORE EXPENSES)',
    'DIRECT PURCHASES (ASSTD)','CARWASH DIRECT PURCHASES','TUBE ICE, DRINKS, ETC.','TRANSPO/DF (NON COGS)','STAFF MEAL (NON COGS)',
    'OFFICE SUPPLIES (NON COGS)','DINING SUPPLIES (NON COGS)','MISCELLANEOUS (NON COGS)','TOTAL COGS','COGS %','GROSS SALES','AUTO WARNED',
    'GAS KG','GAS AMOUNT','MANPOWER','MP COST AVERAGE %'];
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="h_cogs_monitoring_'.$ym.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['H COGS Monitoring — '.$months[$fMonth-1].' '.$fYear]);
    fputcsv($out, $CSV_HEAD);
    $line = function($label, $r, $c) {
        return [$label,$r['kitchen_cogs'],$r['kitchen_noncogs'],$r['commi_cogs'],$r['gulay_billing'],$r['gulay_store'],$r['gulay_exp'],
            $r['direct'],$r['carwash'],$r['tube_ice'],$r['transpo'],$r['staff_meal'],$r['office'],$r['dining'],$r['misc'],
            $c['cogs'], $c['gross'] > 0 ? round($c['cogsPct'],2).'%' : '', $r['gross'], $c['status'],
            $r['gas_kg'],$r['gas_amt'],$r['manpower'], $c['gross'] > 0 ? round($c['mpPct'],2).'%' : ''];
    };
    for ($d = 1; $d <= $days; $d++) {
        $date = sprintf('%s-%02d', $ym, $d); $r = $byDate[$date] ?? null;
        if (!$r) { fputcsv($out, [$date]); continue; }
        fputcsv($out, $line($date, $r, hcCalc($r, $maxPct)));
    }
    fputcsv($out, $line('TOTAL', $tot, $tc));
    fclose($out);
    exit;
}

$pageTitle  = 'H COGS Monitoring';
$activePage = 'h_cogs_monitoring';
include '../layout.php';

$n2  = fn($v) => ((float)$v == 0) ? '' : number_format((float)$v, 2, '.', '');
$fmt = fn($v) => number_format((float)$v, 2);
$pct = fn($v) => number_format((float)$v, 2) . '%';
$accuCogs = $tc['cogs'];
$totalCogsSum = (float)$set['beg_cogs'] + $accuCogs - (float)$set['end_cogs'];

// column groups in sheet order (key => label)
$G_KITCHEN = ['kitchen_cogs'=>'BILLING TO COMMI<br>(COGS)','kitchen_noncogs'=>'BILLING TO COMMI<br>(NON COGS)'];
$G_COMMI   = ['commi_cogs'=>'BILLING TO COMMI<br>(COGS)','gulay_billing'=>'GULAY<br>BILLING'];
$G_GULAY   = ['gulay_store'=>'GULAY STORE COPY<br>(COMMI BILL)','gulay_exp'=>'GULAY<br>(STORE EXPENSES)'];
$G_OTHERS  = ['direct'=>'DIRECT PURCHASES<br>(ASSTD)','carwash'=>'CARWASH DIRECT<br>PURCHASES','tube_ice'=>'TUBE ICE,<br>DRINKS, ETC.'];
$G_NONCOGS = ['transpo'=>1,'staff_meal'=>1,'office'=>1,'dining'=>1,'misc'=>1];
?>

<div class="section-header">
  <div>
    <div class="section-title">H COGS <span>Monitoring</span></div>
    <div class="section-subtitle">Daily COGS, gross sales &amp; manpower — <?=$months[$fMonth-1]?> <?=$fYear?> · saves automatically · amount cells accept formulas like <b>=4510+650+290</b></div>
  </div>
  <a class="btn btn-primary" href="?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>">⬇ Download CSV</a>
</div>

<div class="card" style="padding:12px 18px;margin-bottom:14px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?month=<?=$m?>&year=<?=$fYear?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;
              <?=$fMonth===$m?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>"><?=substr($months[$m-1],0,3)?></a>
    <?php endfor; ?>
    <div style="margin-left:auto">
      <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value" class="form-control"
              style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px">
        <?php for($y=2050;$y>=2020;$y--): ?><option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option><?php endfor; ?>
      </select>
    </div>
  </div>
</div>

<!-- Summary cards -->
<div class="cm-cards">
  <div class="cm-card"><div class="l">GROSS SALES</div><div class="v" id="c_gross"><?=$fmt($tc['gross'])?></div></div>
  <div class="cm-card"><div class="l">TOTAL COGS</div><div class="v" id="c_cogs"><?=$fmt($tc['cogs'])?></div></div>
  <div class="cm-card" id="c_cogs_box"><div class="l">COGS %</div><div class="v"><span id="c_cogsPct"><?=number_format($tc['cogsPct'],2)?></span>%</div><div class="s" id="c_cogsSt"></div></div>
  <div class="cm-card"><div class="l">MANPOWER</div><div class="v" id="c_mp"><?=$fmt($tot['manpower'])?></div></div>
  <div class="cm-card"><div class="l">MP COST AVERAGE %</div><div class="v"><span id="c_mpPct"><?=number_format($tc['mpPct'],2)?></span>%</div></div>
  <div class="cm-card"><div class="l">KITCHEN COPY − COMMI COPY</div><div class="v" id="c_diff"><?=$fmt($tot['kitchen_cogs'] - $tot['commi_cogs'])?></div><div class="s">Billing to Commi (COGS)</div></div>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="cm-tbl" id="cmTbl">
    <thead>
      <tr>
        <th rowspan="2" style="min-width:104px">DATE</th>
        <th colspan="2">HERO KITCHEN COPY</th>
        <th colspan="2">RHEA / COMMI COPY</th>
        <th colspan="2">GULAY EXPENSES</th>
        <th colspan="3">OTHERS EXPENSES (COGS)</th>
        <th>TRANSPO/DF<br>(NON COGS)</th><th>STAFF MEAL<br>(NON COGS)</th><th colspan="2">FRONTLINE EXPENSES<br>(NON COGS)</th><th>MISCELLANEOUS<br>(NON COGS)</th>
        <th colspan="2" class="calc-h">TOTAL COGS<br><small>(not incl. gas &amp; MP)</small></th>
        <th rowspan="2">GROSS<br>SALES</th>
        <th rowspan="2" class="calc-h">AUTO WARNED<br>(IF COGS EXCEED)</th>
        <th colspan="2">GAS CONSUMPTIONS</th>
        <th rowspan="2">MANPOWER</th><th rowspan="2" class="calc-h">MP COST<br>AVERAGE (%)</th>
        <th rowspan="2" style="min-width:34px"></th>
      </tr>
      <tr>
        <?php foreach ($G_KITCHEN + $G_COMMI + $G_GULAY + $G_OTHERS as $k => $l): ?><th><?=$l?></th><?php endforeach; ?>
        <th>&nbsp;</th><th>&nbsp;</th><th>OFFICE<br>SUPPLIES</th><th>DINING SUPPLIES<br>(CLEANING MATERIALS)</th><th>&nbsp;</th>
        <th class="calc-h">TOTAL COGS</th><th class="calc-h">%</th>
        <th>KG</th><th>AMOUNT</th>
      </tr>
    </thead>
    <tbody id="cmBody">
    <?php for ($d = 1; $d <= $days; $d++):
        $date = sprintf('%s-%02d', $ym, $d); $r = $byDate[$date] ?? [];
        $c = hcCalc($r, $maxPct); $fmap = json_decode($r['formulas'] ?? '', true) ?: [];
        $dow = date('D', strtotime($date));
        $inp = function($k) use ($r,$n2,$fmap) {
            $f = $fmap[$k] ?? '';
            return '<td><input class="ci" data-k="'.$k.'" inputmode="decimal" autocomplete="off" value="'.$n2($r[$k] ?? 0).'"'
                 .($f ? ' data-f="'.htmlspecialchars($f,ENT_QUOTES).'" title="'.htmlspecialchars($f,ENT_QUOTES).'"' : '').'></td>';
        };
    ?>
      <tr data-date="<?=$date?>" class="<?= in_array($dow,['Sat','Sun'],true)?'wk':'' ?>">
        <td class="dcell"><b><?=$d?></b> <span><?=$dow?></span></td>
        <?php foreach (array_merge(array_keys($G_KITCHEN), array_keys($G_COMMI), array_keys($G_GULAY), array_keys($G_OTHERS), array_keys($G_NONCOGS)) as $k) echo $inp($k); ?>
        <td class="calc" data-c="cogs"><?= $c['cogs'] ? $fmt($c['cogs']) : '' ?></td>
        <td class="calc" data-c="cogsPct"><?= $c['gross'] > 0 ? $pct($c['cogsPct']) : '' ?></td>
        <?= $inp('gross') ?>
        <td class="calc st" data-c="status"><?= $c['status'] ?></td>
        <?= $inp('gas_kg') ?><?= $inp('gas_amt') ?>
        <?= $inp('manpower') ?>
        <td class="calc" data-c="mpPct"><?= $c['gross'] > 0 ? $pct($c['mpPct']) : '' ?></td>
        <td class="stc"><span class="dot" title=""></span></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot>
      <tr class="tot">
        <td>TOTAL</td>
        <?php foreach (array_merge(array_keys($G_KITCHEN), array_keys($G_COMMI), array_keys($G_GULAY), array_keys($G_OTHERS), array_keys($G_NONCOGS)) as $k): ?><td data-t="<?=$k?>"><?=$fmt($tot[$k])?></td><?php endforeach; ?>
        <td data-t="cogs"><?=$fmt($tc['cogs'])?></td>
        <td data-t="cogsPct"><?=$pct($tc['cogsPct'])?></td>
        <td data-t="gross"><?=$fmt($tot['gross'])?></td>
        <td data-t="status"><?=$tc['status']?></td>
        <td data-t="gas_kg"><?=$fmt($tot['gas_kg'])?></td><td data-t="gas_amt"><?=$fmt($tot['gas_amt'])?></td>
        <td data-t="manpower"><?=$fmt($tot['manpower'])?></td>
        <td data-t="mpPct"><?=$pct($tc['mpPct'])?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<!-- Month settings + Total COGS % Average Summary -->
<div class="card cm-set" style="margin-top:14px">
  <div class="cm-set-t">Total COGS % Average Summary — <?=$months[$fMonth-1]?> <?=$fYear?></div>
  <div class="cm-set-g">
    <label>ACCU SALES (gross sales total)<input class="ci" id="a_sales" readonly value="<?=$fmt($tc['gross'])?>"></label>
    <label>BEG COGS<input class="ci" id="s_beg" inputmode="decimal" value="<?=$n2($set['beg_cogs'])?>"></label>
    <label>ACCU COGS (total COGS)<input class="ci" id="a_cogs" readonly value="<?=$fmt($accuCogs)?>"></label>
    <label>END COGS (month end amount)<input class="ci" id="s_end" inputmode="decimal" value="<?=$n2($set['end_cogs'])?>"></label>
    <label>COGS limit % (COGS PASSED if below)<input class="ci" id="s_max" inputmode="decimal" value="<?=rtrim(rtrim(number_format($maxPct,2,'.',''),'0'),'.')?>"></label>
  </div>
  <div class="cm-tg">
    <div><span>TOTAL COGS (BEG + ACCU − END)</span><b id="t_total"><?=$fmt($totalCogsSum)?></b></div>
    <div><span>COGS %</span><b id="t_pct"><?= $tc['gross'] > 0 ? $pct($totalCogsSum / $tc['gross'] * 100) : '0.00%' ?></b></div>
  </div>
</div>

<style>
.cm-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:14px; }
.cm-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:10px 14px; }
.cm-card .l { font-family:var(--font-m); font-size:.58rem; letter-spacing:.06em; color:var(--subtext2); }
.cm-card .v { font-family:var(--font-m); font-size:1.05rem; font-weight:800; margin-top:3px; }
.cm-card .s { font-family:var(--font-m); font-size:.62rem; font-weight:700; margin-top:2px; color:var(--subtext2); }
.cm-card.bad .v, .cm-card.bad .s { color:#c0182b; }
.cm-card.ok .s { color:#15803d; }
.cm-tbl { width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.72rem; }
.cm-tbl thead th { background:#2f855a; color:#fff; font-size:.56rem; letter-spacing:.03em; padding:6px 5px; border:1px solid #256b49; text-align:center; line-height:1.3; white-space:nowrap; }
.cm-tbl thead th small { font-weight:500; opacity:.85; font-size:.5rem; }
.cm-tbl thead th.calc-h { background:#1f6b3f; }
.cm-tbl tbody td { padding:2px 3px; border:1px solid var(--border); text-align:right; }
.cm-tbl tbody tr.wk td { background:rgba(47,133,90,.05); }
.cm-tbl td.dcell { text-align:left; padding:3px 8px; white-space:nowrap; position:sticky; left:0; background:var(--surface); z-index:1; }
.cm-tbl td.dcell span { color:var(--subtext2); font-size:.6rem; margin-left:4px; }
.cm-tbl td.calc { background:#f0fdf4; font-weight:700; padding:4px 8px; white-space:nowrap; color:#14532d; }
.cm-tbl td.calc.over { color:#c0182b; background:#fef2f2; }
.cm-tbl td.calc.st { font-size:.6rem; text-align:center; }
.cm-tbl .ci, .cm-set .ci { width:100%; min-width:84px; padding:4px 6px; border:1px solid transparent; border-radius:5px; background:transparent; color:var(--text); font-family:var(--font-m); font-size:.72rem; text-align:right; }
.cm-tbl .ci:hover { border-color:var(--border); }
.cm-tbl .ci:focus, .cm-set .ci:focus { outline:none; border-color:#2f855a; background:#fff; box-shadow:0 0 0 2px rgba(47,133,90,.15); }
.cm-tbl tfoot td { background:#bbf7d0; color:#14532d; font-weight:800; padding:6px 8px; border:1px solid #86d9a5; text-align:right; white-space:nowrap; }
.cm-tbl tfoot td:first-child { text-align:left; }
.cm-tbl tfoot td[data-t="status"] { font-size:.6rem; text-align:center; }
.stc { width:34px; text-align:center; }
.dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:transparent; }
.dot.saving { background:#d69e2e; } .dot.ok { background:#2f855a; } .dot.err { background:#c0182b; }
.cm-set { padding:14px 18px; }
.cm-set-t { font-family:var(--font-m); font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#2f855a; margin-bottom:10px; }
.cm-set-g { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; }
.cm-set-g label { font-family:var(--font-m); font-size:.6rem; color:var(--subtext2); text-transform:uppercase; letter-spacing:.04em; display:flex; flex-direction:column; gap:4px; }
.cm-set .ci { border:1px solid var(--border); background:var(--surface); text-align:right; padding:7px 9px; }
.cm-set .ci[readonly] { background:#f0fdf4; font-weight:700; color:#14532d; }
.cm-tg { display:flex; gap:22px; flex-wrap:wrap; margin-top:14px; padding-top:12px; border-top:1px dashed var(--border); }
.cm-tg div { display:flex; flex-direction:column; gap:2px; }
.cm-tg span { font-family:var(--font-m); font-size:.58rem; letter-spacing:.05em; color:var(--subtext2); }
.cm-tg b { font-family:var(--font-m); font-size:.95rem; color:#14532d; }
</style>

<script>
const YM = '<?=$ym?>';
const KEYS = <?=json_encode(HC_KEYS)?>;
const COGS = <?=json_encode(HC_COGS)?>;
const money = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

// Excel-style input:  "=4510+650+290"  or a plain number
function parseCell(el) {
  const s = (el.value || '').trim();
  if (s.startsWith('=')) {
    const ex = s.slice(1);
    if (/^[0-9+\-*\/(). ]+$/.test(ex)) { try { const v = Function('"use strict";return (' + ex + ')')(); if (isFinite(v)) return {v:Math.round(v*100)/100, f:'='+ex.trim()}; } catch(e) {} }
    return {v:0, f:null};
  }
  return {v: parseFloat(s.replace(/,/g,'')) || 0, f:null};
}
function rowVals(tr) {
  const o = {}; KEYS.forEach(k => { const el = tr.querySelector(`[data-k="${k}"]`); o[k] = el ? (el._v !== undefined ? el._v : parseCell(el).v) : 0; });
  return o;
}
function setting(id) { return parseFloat((document.getElementById(id).value||'').replace(/,/g,'')) || 0; }
function calc(o) {
  let cogs = 0; COGS.forEach(k => cogs += o[k]);
  const g = o.gross, pct = g > 0 ? cogs / g * 100 : 0;
  return {cogs, gross:g, cogsPct:pct, mpPct: g>0 ? o.manpower/g*100 : 0,
          status: g > 0 ? (pct < setting('s_max') ? 'COGS PASSED' : 'COGS OVER COST') : ''};
}

function refreshRow(tr) {
  const o = rowVals(tr), c = calc(o);
  const put = (n, t, over) => { const td = tr.querySelector(`[data-c="${n}"]`); td.textContent = t; td.classList.toggle('over', !!over); };
  put('cogs',   c.cogs ? money(c.cogs) : '');
  put('cogsPct',c.gross > 0 ? c.cogsPct.toFixed(2)+'%' : '', c.status === 'COGS OVER COST');
  put('status', c.status, c.status === 'COGS OVER COST');
  put('mpPct',  c.gross > 0 ? c.mpPct.toFixed(2)+'%' : '');
}
function refreshTotals() {
  const T = {}; KEYS.forEach(k => T[k] = 0);
  document.querySelectorAll('#cmBody tr').forEach(tr => { const o = rowVals(tr); KEYS.forEach(k => T[k] += o[k]); });
  const c = calc(T);
  const set = (k, t) => { const td = document.querySelector(`[data-t="${k}"]`); if (td) td.textContent = t; };
  KEYS.forEach(k => set(k, money(T[k])));
  set('cogs', money(c.cogs)); set('cogsPct', c.cogsPct.toFixed(2)+'%'); set('status', c.status); set('mpPct', c.mpPct.toFixed(2)+'%');
  const txt = (id, t) => document.getElementById(id).textContent = t;
  txt('c_gross', money(c.gross)); txt('c_cogs', money(c.cogs)); txt('c_cogsPct', c.cogsPct.toFixed(2));
  txt('c_mp', money(T.manpower)); txt('c_mpPct', c.mpPct.toFixed(2)); txt('c_diff', money(T.kitchen_cogs - T.commi_cogs));
  const over = c.status === 'COGS OVER COST';
  document.getElementById('c_cogs_box').className = 'cm-card ' + (c.gross ? (over ? 'bad' : 'ok') : '');
  txt('c_cogsSt', c.status);
  // Total COGS % Average Summary:  TOTAL COGS = BEG + ACCU − END ;  COGS% = TOTAL COGS / ACCU SALES
  document.getElementById('a_sales').value = money(c.gross);
  document.getElementById('a_cogs').value  = money(c.cogs);
  const total = setting('s_beg') + c.cogs - setting('s_end');
  txt('t_total', money(total)); txt('t_pct', (c.gross > 0 ? total / c.gross * 100 : 0).toFixed(2) + '%');
}

// ── autosave (per row, debounced) ──
const timers = new WeakMap();
function queueSave(tr) { clearTimeout(timers.get(tr)); setDot(tr, 'saving'); timers.set(tr, setTimeout(() => saveRow(tr), 600)); }
function setDot(tr, s) { const d = tr.querySelector('.dot'); if (d) d.className = 'dot ' + s; }
async function saveRow(tr) {
  const fd = new FormData(); fd.append('ajax_action','save_row'); fd.append('entry_date', tr.dataset.date);
  const fm = {};
  KEYS.forEach(k => { const el = tr.querySelector(`[data-k="${k}"]`); const o = el._v !== undefined ? el._v : parseCell(el).v; fd.append(k, o); if (el.dataset.f) fm[k] = el.dataset.f; });
  fd.append('formulas', JSON.stringify(fm));
  try {
    const r = await fetch('h_cogs_monitoring.php', {method:'POST', body:fd}); const d = await r.json();
    setDot(tr, d.ok ? 'ok' : 'err'); if (!d.ok) alert('Save failed: ' + d.msg);
    else setTimeout(() => { const x = tr.querySelector('.dot'); if (x && x.classList.contains('ok')) x.className = 'dot'; }, 1800);
  } catch (e) { setDot(tr, 'err'); }
}

document.querySelectorAll('#cmBody .ci').forEach(el => {
  const p = parseCell(el); el._v = p.v;
  el.addEventListener('focus', () => { if (el.dataset.f) el.value = el.dataset.f; el.select(); });
  el.addEventListener('input', () => { const p = parseCell(el); el._v = p.v; refreshRow(el.closest('tr')); refreshTotals(); queueSave(el.closest('tr')); });
  el.addEventListener('blur', () => {
    const p = parseCell(el); el._v = p.v;
    if (p.f) { el.dataset.f = p.f; el.title = p.f; } else { delete el.dataset.f; el.title = ''; }
    el.value = p.v ? p.v.toFixed(2) : '';
    refreshRow(el.closest('tr')); refreshTotals(); queueSave(el.closest('tr'));
  });
  el.addEventListener('keydown', e => {            // Enter / ↓ move down, ↑ moves up (like the sheet)
    if (e.key !== 'Enter' && e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    e.preventDefault();
    const tr = el.closest('tr'), idx = [...tr.children].indexOf(el.closest('td'));
    const nx = (e.key === 'ArrowUp') ? tr.previousElementSibling : tr.nextElementSibling;
    nx?.children[idx]?.querySelector('.ci')?.focus();
  });
});
document.querySelectorAll('#cmBody tr').forEach(refreshRow);

// ── month settings (autosave) ──
let setTimer = null;
document.querySelectorAll('.cm-set .ci:not([readonly])').forEach(el => {
  el.addEventListener('focus', () => el.select());
  el.addEventListener('input', () => { refreshTotals(); document.querySelectorAll('#cmBody tr').forEach(refreshRow);
    clearTimeout(setTimer); setTimer = setTimeout(saveSettings, 700); });
});
async function saveSettings() {
  const fd = new FormData(); fd.append('ajax_action','save_settings'); fd.append('ym', YM);
  fd.append('cogs_max_pct', setting('s_max')); fd.append('beg_cogs', setting('s_beg')); fd.append('end_cogs', setting('s_end'));
  try { await fetch('h_cogs_monitoring.php', {method:'POST', body:fd}); } catch (e) {}
}
refreshTotals();
</script>

  </div></div>
</body>