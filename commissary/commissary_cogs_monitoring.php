<?php
// ============================================================
//  commissary_cogs_monitoring.php — Commissary COGS Monitoring
//  Mirrors COMMI_COGS_MONITORING_2026.xlsx (one tab per month):
//    TOTAL SALES = POS Reading + Others + Grab + Samgyup Walk-in + Samgyup GCash
//                  + Personal Unpaid + Retaso/Paras Unpaid + Paras Retaso Paid
//    TOTAL (COGS) = Credit Card + Supplier + Pricom + Direct Purchases
//    COGS %  = TOTAL / TOTAL SALES          TOTAL EXPENSES = TOTAL + PCF (Non-COGS)
//    EXPENSES % = TOTAL EXPENSES / TOTAL SALES
//  Every change saves by itself. Amount cells accept Excel-style
//  formulas, e.g.  =7050+14258+9800
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Tables ───────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_cogs_mon` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entry_date` date NOT NULL,
    `billing_pos` decimal(14,2) NOT NULL DEFAULT 0.00,
    `billing_others` decimal(14,2) NOT NULL DEFAULT 0.00,
    `others` decimal(14,2) NOT NULL DEFAULT 0.00,
    `grab` decimal(14,2) NOT NULL DEFAULT 0.00,
    `samgyup_walkin` decimal(14,2) NOT NULL DEFAULT 0.00,
    `samgyup_gcash` decimal(14,2) NOT NULL DEFAULT 0.00,
    `personal_unpaid` decimal(14,2) NOT NULL DEFAULT 0.00,
    `retaso_unpaid` decimal(14,2) NOT NULL DEFAULT 0.00,
    `paras_retaso_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
    `manpower_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
    `personal_cc` decimal(14,2) NOT NULL DEFAULT 0.00,
    `credit_card` decimal(14,2) NOT NULL DEFAULT 0.00,
    `supplier` decimal(14,2) NOT NULL DEFAULT 0.00,
    `pricom` decimal(14,2) NOT NULL DEFAULT 0.00,
    `direct_purchases` decimal(14,2) NOT NULL DEFAULT 0.00,
    `pcf` decimal(14,2) NOT NULL DEFAULT 0.00,
    `formulas` text DEFAULT NULL,
    `saved_by` varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_cogs_mon_set` (
    `ym` char(7) NOT NULL,
    `target_sales` decimal(14,2) NOT NULL DEFAULT 3000000.00,
    `others_target` decimal(14,2) NOT NULL DEFAULT 400000.00,
    `cogs_max_pct` decimal(5,2) NOT NULL DEFAULT 45.00,
    `mp_max_pct` decimal(5,2) NOT NULL DEFAULT 7.00,
    `ly_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
    `lm_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Amount columns, in sheet order
const CM_KEYS = ['billing_pos','billing_others','others','grab','samgyup_walkin','samgyup_gcash',
                 'personal_unpaid','retaso_unpaid','paras_retaso_paid','manpower_cost','personal_cc',
                 'credit_card','supplier','pricom','direct_purchases','pcf'];
const CM_SALES = ['billing_pos','others','grab','samgyup_walkin','samgyup_gcash','personal_unpaid','retaso_unpaid','paras_retaso_paid'];

function cmCalc(array $r): array {
    $g = fn($k) => (float)($r[$k] ?? 0);
    $sales = 0; foreach (CM_SALES as $k) $sales += $g($k);
    $cogs  = $g('credit_card') + $g('supplier') + $g('pricom') + $g('direct_purchases');
    $exp   = $cogs + $g('pcf');
    return [
        'sales' => round($sales, 2), 'cogs' => round($cogs, 2), 'exp' => round($exp, 2),
        'cogsPct' => $sales > 0 ? $cogs / $sales * 100 : 0,
        'mpPct'   => $sales > 0 ? $g('manpower_cost') / $sales * 100 : 0,
        'expPct'  => $sales > 0 ? $exp / $sales * 100 : 0,
    ];
}

// ── AJAX: save one day (empty row = delete) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $date = $_POST['entry_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['ok'=>false,'msg'=>'Bad date']); exit; }
        $vals = []; $any = false;
        foreach (CM_KEYS as $k) { $vals[$k] = round((float)str_replace(',', '', $_POST[$k] ?? 0), 2); if ($vals[$k] != 0) $any = true; }
        $fm = [];
        $in = json_decode($_POST['formulas'] ?? '{}', true) ?: [];
        foreach ($in as $k => $f) {
            if (in_array($k, CM_KEYS, true) && is_string($f) && preg_match('/^=[0-9+\-*\/(). ]{1,200}$/', $f)) $fm[$k] = $f;
        }
        if (!$any) {
            $pdo->prepare("DELETE FROM commissary_cogs_mon WHERE entry_date=?")->execute([$date]);
            echo json_encode(['ok'=>true,'deleted'=>true]); exit;
        }
        $cols = array_merge(['entry_date'], CM_KEYS, ['formulas','saved_by']);
        $params = array_merge([$date], array_values($vals), [$fm ? json_encode($fm) : null, $user['name']]);
        $upd = implode(',', array_map(fn($k)=>"`$k`=VALUES(`$k`)", array_merge(CM_KEYS, ['formulas','saved_by'])));
        $pdo->prepare("INSERT INTO commissary_cogs_mon (`".implode('`,`',$cols)."`) VALUES (".implode(',',array_fill(0,count($cols),'?')).") ON DUPLICATE KEY UPDATE $upd")->execute($params);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: save the month settings (target, thresholds, LY / LM) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_settings') {
    header('Content-Type: application/json');
    try {
        $ym = $_POST['ym'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { echo json_encode(['ok'=>false,'msg'=>'Bad month']); exit; }
        $n = fn($k) => round((float)str_replace(',', '', $_POST[$k] ?? 0), 2);
        $pdo->prepare("INSERT INTO commissary_cogs_mon_set (ym,target_sales,others_target,cogs_max_pct,mp_max_pct,ly_sales,lm_sales)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_sales=VALUES(target_sales),others_target=VALUES(others_target),
            cogs_max_pct=VALUES(cogs_max_pct),mp_max_pct=VALUES(mp_max_pct),ly_sales=VALUES(ly_sales),lm_sales=VALUES(lm_sales)")
            ->execute([$ym,$n('target_sales'),$n('others_target'),$n('cogs_max_pct'),$n('mp_max_pct'),$n('ly_sales'),$n('lm_sales')]);
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

$st = $pdo->prepare("SELECT * FROM commissary_cogs_mon WHERE entry_date BETWEEN ? AND ? ORDER BY entry_date");
$st->execute(["$ym-01", "$ym-".sprintf('%02d',$days)]);
$byDate = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $byDate[$r['entry_date']] = $r;

$ss = $pdo->prepare("SELECT * FROM commissary_cogs_mon_set WHERE ym=?");
$ss->execute([$ym]);
$set = $ss->fetch(PDO::FETCH_ASSOC) ?: ['target_sales'=>3000000,'others_target'=>400000,'cogs_max_pct'=>45,'mp_max_pct'=>7,'ly_sales'=>0,'lm_sales'=>0];

$tot = array_fill_keys(CM_KEYS, 0.0);
foreach ($byDate as $r) foreach (CM_KEYS as $k) $tot[$k] += (float)$r[$k];
$tc = cmCalc($tot);

// ── CSV export (same column order as the Excel) ──────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commissary_cogs_monitoring_'.$ym.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Commissary COGS Monitoring — '.$months[$fMonth-1].' '.$fYear]);
    fputcsv($out, ['DATE','BILLING STORE (POS READING)','BILLING STORE (OTHERS)','OTHERS','GRAB (A/R)','SAMGYUP WALK IN (CASH/ DEPO)',
        'SAMGYUP (GCASH & OTHERS)','PERSONAL (UNPAID)','RETASO/ PARAS UNPAID','PARAS RETASO/ OTHERS PAID','TOTAL SALES',
        'MANPOWER COST','MP %','PERSONAL CREDIT CARD MAAM NIKKI','CREDIT CARD','SUPPLIER','PRICOM','DIRECT PURCHASES','TOTAL','COGS %',
        'PCF (NON-COGS)','TOTAL EXPENSES','%']);
    for ($d = 1; $d <= $days; $d++) {
        $date = sprintf('%s-%02d', $ym, $d); $r = $byDate[$date] ?? null;
        if (!$r) { fputcsv($out, [$date]); continue; }
        $c = cmCalc($r);
        fputcsv($out, [$date,$r['billing_pos'],$r['billing_others'],$r['others'],$r['grab'],$r['samgyup_walkin'],$r['samgyup_gcash'],
            $r['personal_unpaid'],$r['retaso_unpaid'],$r['paras_retaso_paid'],$c['sales'],$r['manpower_cost'],round($c['mpPct'],1).'%',
            $r['personal_cc'],$r['credit_card'],$r['supplier'],$r['pricom'],$r['direct_purchases'],$c['cogs'],round($c['cogsPct'],1).'%',
            $r['pcf'],$c['exp'],round($c['expPct'],1).'%']);
    }
    fputcsv($out, ['TOTAL',$tot['billing_pos'],$tot['billing_others'],$tot['others'],$tot['grab'],$tot['samgyup_walkin'],$tot['samgyup_gcash'],
        $tot['personal_unpaid'],$tot['retaso_unpaid'],$tot['paras_retaso_paid'],$tc['sales'],$tot['manpower_cost'],round($tc['mpPct'],1).'%',
        $tot['personal_cc'],$tot['credit_card'],$tot['supplier'],$tot['pricom'],$tot['direct_purchases'],$tc['cogs'],round($tc['cogsPct'],1).'%',
        $tot['pcf'],$tc['exp'],round($tc['expPct'],1).'%']);
    fclose($out);
    exit;
}

$pageTitle  = 'Commissary COGS Monitoring';
$activePage = 'commissary_cogs_monitoring';
include '../layout.php';

$n2  = fn($v) => ((float)$v == 0) ? '' : number_format((float)$v, 2, '.', '');
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div class="section-header">
  <div>
    <div class="section-title">Commissary COGS <span>Monitoring</span></div>
    <div class="section-subtitle">Daily sales, COGS &amp; manpower — <?=$months[$fMonth-1]?> <?=$fYear?> · saves automatically · amount cells accept formulas like <b>=7050+14258+9800</b></div>
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
  <div class="cm-card"><div class="l">TOTAL SALES</div><div class="v" id="c_sales"><?=$fmt($tc['sales'])?></div></div>
  <div class="cm-card"><div class="l">TOTAL COGS</div><div class="v" id="c_cogs"><?=$fmt($tc['cogs'])?></div></div>
  <div class="cm-card" id="c_cogs_box"><div class="l">COGS %</div><div class="v"><span id="c_cogsPct"><?=number_format($tc['cogsPct'],1)?></span>%</div><div class="s" id="c_cogsSt"></div></div>
  <div class="cm-card"><div class="l">MANPOWER COST</div><div class="v" id="c_mp"><?=$fmt($tot['manpower_cost'])?></div></div>
  <div class="cm-card" id="c_mp_box"><div class="l">MP %</div><div class="v"><span id="c_mpPct"><?=number_format($tc['mpPct'],1)?></span>%</div><div class="s" id="c_mpSt"></div></div>
  <div class="cm-card"><div class="l">TOTAL EXPENSES</div><div class="v" id="c_exp"><?=$fmt($tc['exp'])?></div><div class="s"><span id="c_expPct"><?=number_format($tc['expPct'],1)?></span>% of sales</div></div>
  <div class="cm-card"><div class="l">SALES − EXPENSES</div><div class="v" id="c_net"><?=$fmt($tc['sales']-$tc['exp'])?></div></div>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="cm-tbl" id="cmTbl">
    <thead>
      <tr>
        <th style="min-width:104px">DATE</th>
        <th>BILLING STORE<br>(POS READING)</th><th>BILLING STORE<br>(OTHERS)</th><th>OTHERS</th><th>GRAB<br>(A/R)</th>
        <th>SAMGYUP WALK IN<br>(CASH/ DEPO)</th><th>SAMGYUP<br>(GCASH &amp; OTHERS)</th><th>PERSONAL<br>(UNPAID)</th>
        <th>RETASO/ PARAS<br>UNPAID</th><th>PARAS RETASO/<br>OTHERS PAID</th>
        <th class="calc-h">TOTAL SALES</th>
        <th>MANPOWER<br>COST</th><th class="calc-h">MP %</th>
        <th>PERSONAL CREDIT CARD<br>MAAM NIKKI</th><th>CREDIT CARD</th><th>SUPPLIER</th><th>PRICOM</th><th>DIRECT<br>PURCHASES</th>
        <th class="calc-h">TOTAL</th><th class="calc-h">COGS %</th>
        <th>PCF<br>(NON-COGS)</th><th class="calc-h">TOTAL<br>EXPENSES</th><th class="calc-h">%</th>
        <th style="min-width:34px"></th>
      </tr>
    </thead>
    <tbody id="cmBody">
    <?php for ($d = 1; $d <= $days; $d++):
        $date = sprintf('%s-%02d', $ym, $d); $r = $byDate[$date] ?? [];
        $c = cmCalc($r); $fmap = json_decode($r['formulas'] ?? '', true) ?: [];
        $dow = date('D', strtotime($date));
        $inp = function($k) use ($r,$n2,$fmap) {
            $f = $fmap[$k] ?? '';
            return '<td><input class="ci" data-k="'.$k.'" inputmode="decimal" autocomplete="off" value="'.$n2($r[$k] ?? 0).'"'
                 .($f ? ' data-f="'.htmlspecialchars($f,ENT_QUOTES).'" title="'.htmlspecialchars($f,ENT_QUOTES).'"' : '').'></td>';
        };
    ?>
      <tr data-date="<?=$date?>" class="<?= in_array($dow,['Sat','Sun'],true)?'wk':'' ?>">
        <td class="dcell"><b><?=$d?></b> <span><?=$dow?></span></td>
        <?php foreach (['billing_pos','billing_others','others','grab','samgyup_walkin','samgyup_gcash','personal_unpaid','retaso_unpaid','paras_retaso_paid'] as $k) echo $inp($k); ?>
        <td class="calc" data-c="sales"><?= $c['sales'] ? $fmt($c['sales']) : '' ?></td>
        <?= $inp('manpower_cost') ?>
        <td class="calc" data-c="mpPct"><?= $c['sales'] ? number_format($c['mpPct'],1).'%' : '' ?></td>
        <?php foreach (['personal_cc','credit_card','supplier','pricom','direct_purchases'] as $k) echo $inp($k); ?>
        <td class="calc" data-c="cogs"><?= $c['cogs'] ? $fmt($c['cogs']) : '' ?></td>
        <td class="calc" data-c="cogsPct"><?= $c['sales'] ? number_format($c['cogsPct'],1).'%' : '' ?></td>
        <?= $inp('pcf') ?>
        <td class="calc" data-c="exp"><?= $c['exp'] ? $fmt($c['exp']) : '' ?></td>
        <td class="calc" data-c="expPct"><?= $c['sales'] ? number_format($c['expPct'],1).'%' : '' ?></td>
        <td class="stc"><span class="dot" title=""></span></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot>
      <tr class="tot">
        <td>TOTAL</td>
        <?php foreach (['billing_pos','billing_others','others','grab','samgyup_walkin','samgyup_gcash','personal_unpaid','retaso_unpaid','paras_retaso_paid'] as $k): ?><td data-t="<?=$k?>"><?=$fmt($tot[$k])?></td><?php endforeach; ?>
        <td data-t="sales"><?=$fmt($tc['sales'])?></td>
        <td data-t="manpower_cost"><?=$fmt($tot['manpower_cost'])?></td>
        <td data-t="mpPct"><?=number_format($tc['mpPct'],1)?>%</td>
        <?php foreach (['personal_cc','credit_card','supplier','pricom','direct_purchases'] as $k): ?><td data-t="<?=$k?>"><?=$fmt($tot[$k])?></td><?php endforeach; ?>
        <td data-t="cogs"><?=$fmt($tc['cogs'])?></td>
        <td data-t="cogsPct"><?=number_format($tc['cogsPct'],1)?>%</td>
        <td data-t="pcf"><?=$fmt($tot['pcf'])?></td>
        <td data-t="exp"><?=$fmt($tc['exp'])?></td>
        <td data-t="expPct"><?=number_format($tc['expPct'],1)?>%</td>
        <td></td>
      </tr>
      <tr class="pct">
        <td>% OF SALES</td>
        <td data-p="billing_pos"></td><td data-p="billing_others"></td><td data-p="others" title="Others ÷ Others target"></td><td data-p="grab"></td>
        <td data-p="samgyup_walkin"></td><td data-p="samgyup_gcash"></td><td data-p="personal_unpaid"></td><td data-p="retaso_unpaid"></td><td data-p="paras_retaso_paid"></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td data-p="pcf" title="PCF ÷ Total expenses"></td><td></td><td></td><td></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<!-- Month settings / target -->
<div class="card cm-set" style="margin-top:14px">
  <div class="cm-set-t">Target &amp; thresholds — <?=$months[$fMonth-1]?> <?=$fYear?></div>
  <div class="cm-set-g">
    <label>Total target sales<input class="ci" id="s_target" inputmode="decimal" value="<?=$n2($set['target_sales'])?>"></label>
    <label>Others target<input class="ci" id="s_others" inputmode="decimal" value="<?=$n2($set['others_target'])?>"></label>
    <label>COGS max %<input class="ci" id="s_cogsmax" inputmode="decimal" value="<?=rtrim(rtrim(number_format((float)$set['cogs_max_pct'],2,'.',''),'0'),'.')?>"></label>
    <label>MP max %<input class="ci" id="s_mpmax" inputmode="decimal" value="<?=rtrim(rtrim(number_format((float)$set['mp_max_pct'],2,'.',''),'0'),'.')?>"></label>
    <label>LY (last year) sales<input class="ci" id="s_ly" inputmode="decimal" value="<?=$n2($set['ly_sales'])?>"></label>
    <label>LM (last month) sales<input class="ci" id="s_lm" inputmode="decimal" value="<?=$n2($set['lm_sales'])?>"></label>
  </div>
  <div class="cm-tg">
    <div><span>NEEDED SALES TO REACH TARGET</span><b id="t_need">0.00</b></div>
    <div><span>SALES PERCENTAGE</span><b id="t_pct">0.0%</b></div>
    <div><span>DAILY SALES QUOTA</span><b id="t_quota">0.00</b></div>
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
.cm-tbl thead th { position:sticky; top:0; background:#2f855a; color:#fff; font-size:.58rem; letter-spacing:.03em; padding:7px 5px; border:1px solid #256b49; text-align:center; line-height:1.3; white-space:nowrap; z-index:2; }
.cm-tbl thead th.calc-h { background:#1f6b3f; }
.cm-tbl tbody td { padding:2px 3px; border:1px solid var(--border); text-align:right; }
.cm-tbl tbody tr.wk td { background:rgba(47,133,90,.05); }
.cm-tbl td.dcell { text-align:left; padding:3px 8px; white-space:nowrap; position:sticky; left:0; background:var(--surface); z-index:1; }
.cm-tbl td.dcell span { color:var(--subtext2); font-size:.6rem; margin-left:4px; }
.cm-tbl td.calc { background:#f0fdf4; font-weight:700; padding:4px 8px; white-space:nowrap; color:#14532d; }
.cm-tbl td.calc.over { color:#c0182b; background:#fef2f2; }
.cm-tbl .ci, .cm-set .ci { width:100%; min-width:84px; padding:4px 6px; border:1px solid transparent; border-radius:5px; background:transparent; color:var(--text); font-family:var(--font-m); font-size:.72rem; text-align:right; }
.cm-tbl .ci:hover { border-color:var(--border); }
.cm-tbl .ci:focus, .cm-set .ci:focus { outline:none; border-color:#2f855a; background:#fff; box-shadow:0 0 0 2px rgba(47,133,90,.15); }
.cm-tbl tfoot td { background:#bbf7d0; color:#14532d; font-weight:800; padding:6px 8px; border:1px solid #86d9a5; text-align:right; white-space:nowrap; }
.cm-tbl tfoot tr.pct td { background:#dcfce7; font-weight:700; font-size:.66rem; }
.cm-tbl tfoot td:first-child { text-align:left; }
.stc { width:34px; text-align:center; }
.dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:transparent; }
.dot.saving { background:#d69e2e; } .dot.ok { background:#2f855a; } .dot.err { background:#c0182b; }
.cm-set { padding:14px 18px; }
.cm-set-t { font-family:var(--font-m); font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#2f855a; margin-bottom:10px; }
.cm-set-g { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; }
.cm-set-g label { font-family:var(--font-m); font-size:.6rem; color:var(--subtext2); text-transform:uppercase; letter-spacing:.04em; display:flex; flex-direction:column; gap:4px; }
.cm-set .ci { border:1px solid var(--border); background:var(--surface); text-align:right; padding:7px 9px; }
.cm-tg { display:flex; gap:22px; flex-wrap:wrap; margin-top:14px; padding-top:12px; border-top:1px dashed var(--border); }
.cm-tg div { display:flex; flex-direction:column; gap:2px; }
.cm-tg span { font-family:var(--font-m); font-size:.58rem; letter-spacing:.05em; color:var(--subtext2); }
.cm-tg b { font-family:var(--font-m); font-size:.95rem; color:#14532d; }
</style>

<script>
const YM = '<?=$ym?>', DAYS = <?=$days?>;
const KEYS = <?=json_encode(CM_KEYS)?>;
const SALES = <?=json_encode(CM_SALES)?>;
const money = n => Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

// Excel-style input:  "=7050+14258+9800"  or a plain number
function parseCell(el) {
  const s = (el.value || '').trim();
  if (s.startsWith('=')) {
    const ex = s.slice(1);
    if (/^[0-9+\-*\/(). ]+$/.test(ex)) { try { const v = Function('"use strict";return (' + ex + ')')(); if (isFinite(v)) return {v:Math.round(v*100)/100, f:'='+ex.trim()}; } catch(e) {} }
    return {v:0, f:null};
  }
  return {v: parseFloat(s.replace(/,/g,'')) || 0, f:null};
}
const val = el => { if (!el) return 0; const f = el.dataset.f; return f && document.activeElement === el ? parseCell(el).v : (el._v !== undefined ? el._v : parseCell(el).v); };

function rowVals(tr) {
  const o = {}; KEYS.forEach(k => { const el = tr.querySelector(`[data-k="${k}"]`); o[k] = el ? (el._v !== undefined ? el._v : parseCell(el).v) : 0; });
  return o;
}
function calc(o) {
  let sales = 0; SALES.forEach(k => sales += o[k]);
  const cogs = o.credit_card + o.supplier + o.pricom + o.direct_purchases, exp = cogs + o.pcf;
  return {sales, cogs, exp, cogsPct: sales>0?cogs/sales*100:0, mpPct: sales>0?o.manpower_cost/sales*100:0, expPct: sales>0?exp/sales*100:0};
}
function setting(id) { return parseFloat((document.getElementById(id).value||'').replace(/,/g,'')) || 0; }

function refreshRow(tr) {
  const o = rowVals(tr), c = calc(o);
  const put = (n, t, over) => { const td = tr.querySelector(`[data-c="${n}"]`); td.textContent = t; td.classList.toggle('over', !!over); };
  put('sales',  c.sales ? money(c.sales) : '');
  put('mpPct',  c.sales ? c.mpPct.toFixed(1)+'%' : '', c.sales && c.mpPct > setting('s_mpmax'));
  put('cogs',   c.cogs ? money(c.cogs) : '');
  put('cogsPct',c.sales ? c.cogsPct.toFixed(1)+'%' : '', c.sales && c.cogsPct > setting('s_cogsmax'));
  put('exp',    c.exp ? money(c.exp) : '');
  put('expPct', c.sales ? c.expPct.toFixed(1)+'%' : '');
}
function refreshTotals() {
  const T = {}; KEYS.forEach(k => T[k] = 0);
  document.querySelectorAll('#cmBody tr').forEach(tr => { const o = rowVals(tr); KEYS.forEach(k => T[k] += o[k]); });
  const c = calc(T);
  const set = (k, t) => { const td = document.querySelector(`[data-t="${k}"]`); if (td) td.textContent = t; };
  KEYS.forEach(k => set(k, money(T[k])));
  set('sales', money(c.sales)); set('cogs', money(c.cogs)); set('exp', money(c.exp));
  set('mpPct', c.mpPct.toFixed(1)+'%'); set('cogsPct', c.cogsPct.toFixed(1)+'%'); set('expPct', c.expPct.toFixed(1)+'%');
  // % of sales row
  const pc = (k, base, t) => { const td = document.querySelector(`[data-p="${k}"]`); if (td) td.textContent = base > 0 ? (T[k]/base*100).toFixed(1)+'%' : ''; };
  ['billing_pos','billing_others','grab','samgyup_walkin','samgyup_gcash','personal_unpaid','retaso_unpaid','paras_retaso_paid'].forEach(k => pc(k, c.sales));
  pc('others', setting('s_others')); pc('pcf', c.exp);
  // cards
  const txt = (id, t) => document.getElementById(id).textContent = t;
  txt('c_sales', money(c.sales)); txt('c_cogs', money(c.cogs)); txt('c_cogsPct', c.cogsPct.toFixed(1));
  txt('c_mp', money(T.manpower_cost)); txt('c_mpPct', c.mpPct.toFixed(1)); txt('c_exp', money(c.exp));
  txt('c_expPct', c.expPct.toFixed(1)); txt('c_net', money(c.sales - c.exp));
  const cOver = c.sales > 0 && c.cogsPct > setting('s_cogsmax'), mOver = c.sales > 0 && c.mpPct > setting('s_mpmax');
  document.getElementById('c_cogs_box').className = 'cm-card ' + (c.sales ? (cOver ? 'bad' : 'ok') : '');
  document.getElementById('c_mp_box').className   = 'cm-card ' + (c.sales ? (mOver ? 'bad' : 'ok') : '');
  txt('c_cogsSt', c.sales ? (cOver ? 'COGS OVER COST' : 'COGS PASSED') : '');
  txt('c_mpSt',   c.sales ? (mOver ? 'MP FAILED' : 'MP PASSED') : '');
  // target block (same formulas as the sheet: sales − target, % = sales ÷ target, quota = needed ÷ days)
  const target = setting('s_target'), need = c.sales - target;
  txt('t_need', money(need)); txt('t_pct', target > 0 ? (c.sales/target*100).toFixed(1)+'%' : '0.0%'); txt('t_quota', money(need / DAYS));
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
    const r = await fetch('commissary_cogs_monitoring.php', {method:'POST', body:fd}); const d = await r.json();
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
document.querySelectorAll('.cm-set .ci').forEach(el => {
  el.addEventListener('focus', () => el.select());
  el.addEventListener('input', () => { refreshTotals(); document.querySelectorAll('#cmBody tr').forEach(refreshRow);
    clearTimeout(setTimer); setTimer = setTimeout(saveSettings, 700); });
});
async function saveSettings() {
  const fd = new FormData(); fd.append('ajax_action','save_settings'); fd.append('ym', YM);
  fd.append('target_sales', setting('s_target')); fd.append('others_target', setting('s_others'));
  fd.append('cogs_max_pct', setting('s_cogsmax')); fd.append('mp_max_pct', setting('s_mpmax'));
  fd.append('ly_sales', setting('s_ly')); fd.append('lm_sales', setting('s_lm'));
  try { await fetch('commissary_cogs_monitoring.php', {method:'POST', body:fd}); } catch (e) {}
}
refreshTotals();
</script>

  </div></div>
</body>