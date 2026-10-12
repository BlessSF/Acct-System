<?php
// ============================================================
//  demic_jaro_daily_cashflow.php — Daily Sales and Cashflow
//  Mirrors "2026 DAILY SALES AND CASH FLOW.xlsx": one row per line
//  item, one column per day of the month, editable CASH/HMO/CTC/
//  CREDIT-DEBIT running-total rows (with their own TOTAL row below),
//  section subtotals, and a TOTAL column on the right that sums the
//  whole month per row.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && !in_array(currentBranch(), ['DemicLab-Jaro','DemicLab-Jaro'], true)) {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_jaro_daily_cashflow` (
    `id`                     int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`             date NOT NULL,
    `store_name`             varchar(50) NOT NULL DEFAULT 'Demic Lab',
    `hmo`                    decimal(12,2) NOT NULL DEFAULT 0.00,
    `charge_to_company`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `dr_cr`                  decimal(12,2) NOT NULL DEFAULT 0.00,
    `labs`                   decimal(12,2) NOT NULL DEFAULT 0.00,
    `xray_ecg_utz_2decho`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `packages`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `sc_pwd`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `running_sales`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `running_cash`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `running_hmo`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `running_ctc`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `running_drcr`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `jaro_payables_consumables`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `consumables`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `janitorial`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `office`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline_daily_fixed`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline_holiday`      decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline_reliever`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_xray`                decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_clinic_doctor`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_ob_doctor`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_ecg`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_2d_echo`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_ultrasound`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_ob_ultrasound`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_ortho`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_cardio`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_patho_it`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_im`                  decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_pedia`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`               varchar(100) DEFAULT NULL,
    `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

try { $pdo->exec("ALTER TABLE `demic_jaro_daily_cashflow` ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`)"); } catch (Throwable $ignored) {}
foreach (['running_sales','running_cash','running_hmo','running_ctc','running_drcr'] as $rc) {
    try { $pdo->exec("ALTER TABLE `demic_jaro_daily_cashflow` ADD COLUMN `$rc` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}
}

// The full set of editable (input) fields — everything else on the sheet is derived.
$INPUT_KEYS = ['hmo','charge_to_company','dr_cr','labs','xray_ecg_utz_2decho','packages','sc_pwd',
    'running_sales','running_cash','running_hmo','running_ctc','running_drcr',
    'jaro_payables_consumables','consumables','janitorial','office',
    'frontline_daily_fixed','frontline_holiday','frontline_reliever',
    'pf_xray','pf_clinic_doctor','pf_ob_doctor','pf_ecg','pf_2d_echo','pf_ultrasound',
    'pf_ob_ultrasound','pf_ortho','pf_cardio','pf_patho_it','pf_im','pf_pedia'];

// ── AJAX: Save one day's column ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_day') {
    header('Content-Type: application/json');
    try {
        $store = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');
        $date  = $_POST['entry_date'] ?? date('Y-m-d');

        $vals = [];
        foreach ($INPUT_KEYS as $k) { $vals[$k] = (float)($_POST[$k] ?? 0); }

        $cols   = implode(',', $INPUT_KEYS);
        $marks  = implode(',', array_fill(0, count($INPUT_KEYS), '?'));
        $updSql = implode(',', array_map(fn($k) => "$k=VALUES($k)", $INPUT_KEYS));

        $pdo->prepare("INSERT INTO demic_jaro_daily_cashflow (entry_date,store_name,$cols,saved_by)
                VALUES (?,?,$marks,?)
                ON DUPLICATE KEY UPDATE $updSql, saved_by=VALUES(saved_by)")
            ->execute(array_merge([$date, $store], array_values($vals), [$user['name']]));

        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Clear one day's column ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'clear_day') {
    header('Content-Type: application/json');
    $store = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');
    $date  = $_POST['entry_date'] ?? '';
    $pdo->prepare("DELETE FROM demic_jaro_daily_cashflow WHERE entry_date=? AND store_name=?")->execute([$date, $store]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));
$storeLabel = isBranch() ? currentBranch() : 'All Branches';

[$bClause, $bParams] = branchFilter('store_name');
$stmt = $pdo->prepare("SELECT * FROM demic_jaro_daily_cashflow WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? AND $bClause");
$stmt->execute(array_merge([$fYear, $fMonth], $bParams));
$rawByDate = [];
foreach ($stmt->fetchAll() as $r) { $rawByDate[$r['entry_date']] = $r; }

// ── Build the day list + derived matrix ──────────────────
$daysInMonth = (int)date('t', mktime(0,0,0,$fMonth,1,$fYear));
$dates = [];
for ($d = 1; $d <= $daysInMonth; $d++) { $dates[] = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $d); }

function cf_day_calc(array $v): array {
    $v['cash']            = round($v['labs'] + $v['xray_ecg_utz_2decho'] + $v['packages'] + $v['sc_pwd'], 2);
    $v['running_total']   = round($v['running_sales'] + $v['running_cash'] + $v['running_hmo'] + $v['running_ctc'] + $v['running_drcr'], 2);
    $v['jaro_payables_total']  = round($v['jaro_payables_consumables'] + $v['consumables'] + $v['janitorial'] + $v['office'], 2);
    $v['frontline_total'] = round($v['frontline_daily_fixed'] + $v['frontline_holiday'] + $v['frontline_reliever'], 2);
    $v['pf_total']        = round($v['pf_xray'] + $v['pf_clinic_doctor'] + $v['pf_ob_doctor'] + $v['pf_ecg'] +
        $v['pf_2d_echo'] + $v['pf_ultrasound'] + $v['pf_ob_ultrasound'] + $v['pf_ortho'] +
        $v['pf_cardio'] + $v['pf_patho_it'] + $v['pf_im'] + $v['pf_pedia'], 2);
    return $v;
}

$matrix = [];
$hasAnyData = [];
foreach ($dates as $date) {
    $raw = [];
    foreach ($INPUT_KEYS as $k) { $raw[$k] = isset($rawByDate[$date]) ? (float)$rawByDate[$date][$k] : 0.0; }
    $matrix[$date] = cf_day_calc($raw);
    $hasAnyData[$date] = isset($rawByDate[$date]);
}

// Row layout — the single source of truth for both rendering and totals.
// type: section | spacer | input | calc | running | total
$SECTIONS = [
    ['type'=>'input',   'key'=>'',            'field'=>'cash',              'label'=>'CASH', 'is_calc'=>true],
    ['type'=>'input',   'key'=>'hmo',                          'label'=>'HMO'],
    ['type'=>'input',   'key'=>'charge_to_company',            'label'=>'CHARGE TO COMPANY'],
    ['type'=>'input',   'key'=>'dr_cr',                        'label'=>'CREDIT / DEBIT'],
    ['type'=>'spacer'],
    ['type'=>'section', 'label'=>'CASH BREAKDOWN:'],
    ['type'=>'input',   'key'=>'labs',                         'label'=>'LABS'],
    ['type'=>'input',   'key'=>'xray_ecg_utz_2decho',          'label'=>'XRAY, ECG, UTZ, 2D ECHO'],
    ['type'=>'input',   'key'=>'packages',                     'label'=>'PACKAGES'],
    ['type'=>'input',   'key'=>'sc_pwd',                       'label'=>'SC/PWD'],
    ['type'=>'spacer'],
    ['type'=>'running', 'key'=>'running_sales',                'label'=>'RUNNING SALES'],
    ['type'=>'running', 'key'=>'running_cash',                 'label'=>'RUNNING CASH'],
    ['type'=>'running', 'key'=>'running_hmo',                  'label'=>'RUNNING HMO'],
    ['type'=>'running', 'key'=>'running_ctc',                  'label'=>'RUNNING CTC'],
    ['type'=>'running', 'key'=>'running_drcr',                 'label'=>'RUNNING CREDIT/DEBIT'],
    ['type'=>'total',   'key'=>'running_total',                'label'=>'TOTAL:',
        'sum_of'=>['running_sales','running_cash','running_hmo','running_ctc','running_drcr']],
    ['type'=>'spacer'],
    ['type'=>'section', 'label'=>'ITEMIZED PAYABLES:'],
    ['type'=>'input',   'key'=>'jaro_payables_consumables',         'label'=>'PAYABLES CONSUMABLES'],
    ['type'=>'input',   'key'=>'consumables',                  'label'=>'CONSUMABLES'],
    ['type'=>'input',   'key'=>'janitorial',                   'label'=>'JANITORIAL'],
    ['type'=>'input',   'key'=>'office',                       'label'=>'OFFICE'],
    ['type'=>'total',   'key'=>'jaro_payables_total',                'label'=>'TOTAL:',
        'sum_of'=>['jaro_payables_consumables','consumables','janitorial','office']],
    ['type'=>'spacer'],
    ['type'=>'section', 'label'=>'FRONTLINE/MEDICAL STAFF:'],
    ['type'=>'input',   'key'=>'frontline_daily_fixed',        'label'=>'DAILY FIXED AMOUNT'],
    ['type'=>'input',   'key'=>'frontline_holiday',            'label'=>'HOLIDAY'],
    ['type'=>'input',   'key'=>'frontline_reliever',           'label'=>'RELIEVER'],
    ['type'=>'total',   'key'=>'frontline_total',              'label'=>'TOTAL:',
        'sum_of'=>['frontline_daily_fixed','frontline_holiday','frontline_reliever']],
    ['type'=>'spacer'],
    ['type'=>'section', 'label'=>'PROFESSIONAL FEE:'],
    ['type'=>'input',   'key'=>'pf_xray',                      'label'=>'X-RAY'],
    ['type'=>'input',   'key'=>'pf_clinic_doctor',             'label'=>'CLINIC DOCTOR'],
    ['type'=>'input',   'key'=>'pf_ob_doctor',                 'label'=>'OB DOCTOR'],
    ['type'=>'input',   'key'=>'pf_ecg',                       'label'=>'ECG'],
    ['type'=>'input',   'key'=>'pf_2d_echo',                   'label'=>'2D ECHO'],
    ['type'=>'input',   'key'=>'pf_ultrasound',                'label'=>'ULTRASOUND'],
    ['type'=>'input',   'key'=>'pf_ob_ultrasound',             'label'=>'OB ULTRASOUND'],
    ['type'=>'input',   'key'=>'pf_ortho',                     'label'=>'ORTHO'],
    ['type'=>'input',   'key'=>'pf_cardio',                    'label'=>'CARDIO'],
    ['type'=>'input',   'key'=>'pf_patho_it',                  'label'=>'PATHO/IT'],
    ['type'=>'input',   'key'=>'pf_im',                        'label'=>'IM'],
    ['type'=>'input',   'key'=>'pf_pedia',                     'label'=>'PEDIA'],
    ['type'=>'total',   'key'=>'pf_total',                     'label'=>'TOTAL:',
        'sum_of'=>['pf_xray','pf_clinic_doctor','pf_ob_doctor','pf_ecg','pf_2d_echo','pf_ultrasound',
                   'pf_ob_ultrasound','pf_ortho','pf_cardio','pf_patho_it','pf_im','pf_pedia']],
];

// Row TOTAL column (sum across the whole month) — now includes the running rows since they're editable inputs too.
$rowTotal = [];
foreach ($SECTIONS as $row) {
    if (empty($row['key']) && $row['type'] !== 'input') continue;
    $k = $row['key'] ?? ($row['field'] ?? '');
    if ($k === '') { $k = 'cash'; }
    $sum = 0.0;
    foreach ($dates as $date) { $sum += $matrix[$date][$k] ?? 0; }
    $rowTotal[$k] = round($sum, 2);
}

// Month overview cards
$totalCash  = $rowTotal['cash']  ?? 0;
$totalHmo   = $rowTotal['hmo']   ?? 0;
$totalCtc   = $rowTotal['charge_to_company'] ?? 0;
$totalDrCr  = $rowTotal['dr_cr'] ?? 0;
$totalSales = round($totalCash + $totalHmo + $totalCtc + $totalDrCr, 2);
$totalPayables  = $rowTotal['jaro_payables_total']  ?? 0;
$totalFrontline = $rowTotal['frontline_total'] ?? 0;
$totalPF        = $rowTotal['pf_total']        ?? 0;
$daysWithData   = count(array_filter($hasAnyData));

// ── CSV export ────────────────────────────────────────────
if (($_GET['export_csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demic_jaro_daily_cashflow_' . $fYear . '-' . str_pad($fMonth,2,'0',STR_PAD_LEFT) . '.csv"');
    $out = fopen('php://output', 'w');
    $header = [''];
    foreach ($dates as $date) { $header[] = date('j-M', strtotime($date)); }
    $header[] = 'TOTAL';
    fputcsv($out, $header);
    foreach ($SECTIONS as $row) {
        if ($row['type'] === 'spacer') { fputcsv($out, []); continue; }
        if ($row['type'] === 'section') { fputcsv($out, [$row['label']]); continue; }
        $k = $row['key'] !== '' ? $row['key'] : 'cash';
        $line = [$row['label']];
        foreach ($dates as $date) { $line[] = $matrix[$date][$k] ?? 0; }
        $line[] = $rowTotal[$k] === null ? '-' : $rowTotal[$k];
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$pageTitle  = 'Daily Sales and Cashflow';
$activePage = 'demic_jaro_daily_cashflow';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Daily <span>Sales and Cashflow</span></div>
    <div class="section-subtitle">One column per day · running totals down CASH/HMO/CTC/CREDIT-DEBIT · Save button stays pinned in the TOTAL column</div>
  </div>
</div>

<!-- Controls -->
<div class="card cf-card cf-controlbar">
  <div class="cf-pill">📍 <?=htmlspecialchars($storeLabel)?></div>
  <select onchange="window.location='?month='+this.value+'&year=<?=$fYear?>'" class="cf-dd">
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$months[$m-1]?></option>
    <?php endfor; ?>
  </select>
  <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value" class="cf-dd">
    <?php for($y=2050;$y>=2020;$y--): ?>
    <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="button" id="saveAllBtn" class="cf-btn-saveall cf-save-trigger" onclick="saveAllDays()"><span class="cf-savebtn-label">💾 Save All</span></button>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?>&export_csv=1" class="cf-btn-csv">⬇ Download CSV</a>
</div>

<!-- Legend -->
<div class="cf-legend-row">
  <span><i class="cf-sw cf-sw-editable"></i> Editable</span>
  <span><i class="cf-sw cf-sw-running"></i> Running Total</span>
  <span><i class="cf-sw cf-sw-total"></i> Section Total</span>
  <span><i class="cf-dot cf-dot-green"></i> Data Entered</span>
  <span><i class="cf-dot cf-dot-amber"></i> No Data Yet</span>
</div>
<div class="cf-scrollhint">← Scroll horizontally to see every day of the month →</div>

<!-- Month overview -->
<div class="card cf-card cf-stats">
  <div>
    <div class="cf-kicker">Days Entered</div>
    <div class="cf-statval"><?=$daysWithData?> / <?=$daysInMonth?></div>
  </div>
  <div>
    <div class="cf-kicker">Total Sales (Cash+HMO+CTC+CR/DR)</div>
    <div class="cf-statval">₱<?=number_format($totalSales,2)?></div>
  </div>
  <div>
    <div class="cf-kicker">Total Payables</div>
    <div class="cf-statval">₱<?=number_format($totalPayables,2)?></div>
  </div>
  <div>
    <div class="cf-kicker">Total Frontline/Medical</div>
    <div class="cf-statval">₱<?=number_format($totalFrontline,2)?></div>
  </div>
  <div>
    <div class="cf-kicker">Total Professional Fee</div>
    <div class="cf-statval">₱<?=number_format($totalPF,2)?></div>
  </div>
</div>

<!-- Table -->
<div class="card cf-card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="cf-tbl" id="cfTbl">
    <thead>
      <tr>
        <th class="cf-labelcol">FIELD</th>
        <?php foreach ($dates as $date): $dow = date('D', strtotime($date)); $dm = date('j', strtotime($date)); ?>
        <th class="cf-datecol">
          <div class="cf-datehead">
            <span class="cf-dot <?=$hasAnyData[$date]?'cf-dot-green':'cf-dot-amber'?>" data-role="daydot-<?=$date?>"></span>
            <span class="cf-dm"><?=$dm?></span>
            <span class="cf-dow"><?=$dow?></span>
          </div>
        </th>
        <?php endforeach; ?>
        <th class="cf-totalcol">
          <div class="cf-totalhead">
            <span>TOTAL</span>
            <button type="button" class="cf-btn-total-save cf-save-trigger" onclick="saveAllDays()" title="Save all days">
              <span class="cf-savebtn-label">💾 Save</span>
            </button>
          </div>
        </th>
      </tr>
    </thead>
    <tbody id="cfTbody">
      <?php foreach ($SECTIONS as $row): ?>
        <?php if ($row['type'] === 'spacer'): ?>
        <tr class="cf-spacer"><td colspan="<?=count($dates)+2?>"></td></tr>

        <?php elseif ($row['type'] === 'section'): ?>
        <tr class="cf-section-row"><td colspan="<?=count($dates)+2?>"><?=htmlspecialchars($row['label'])?></td></tr>

        <?php else:
          $k = $row['key'] !== '' ? $row['key'] : 'cash';
          $rowClass = $row['type'] === 'total' ? 'cf-total-row' : ($row['type'] === 'running' ? 'cf-running-row' : '');
        ?>
        <tr class="<?=$rowClass?>">
          <td class="cf-labelcol"><?=htmlspecialchars($row['label'])?></td>
          <?php foreach ($dates as $date): $v = $matrix[$date][$k] ?? 0; ?>
            <td class="cf-datacell">
            <?php if (in_array($row['type'], ['input','running'], true) && empty($row['is_calc'])): ?>
              <input class="cfi" type="number" step="0.01" min="0" data-date="<?=$date?>" data-field="<?=$k?>"
                     value="<?=$v?>" placeholder="0.00" oninput="recalcAll()" onfocus="this.select()">
            <?php else: ?>
              <span class="cf-calc" data-date="<?=$date?>" data-field="<?=$k?>"><?=number_format($v,2)?></span>
            <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="cf-totalcol" data-field="<?=$k?>"><?= $rowTotal[$k] === null ? '—' : number_format($rowTotal[$k],2) ?></td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<style>
:root {
  --cf-blue:        #2563eb;
  --cf-blue-deep:   #1e3a8a;
  --cf-blue-mid:    #1e40af;
  --cf-blue-soft:   rgba(37,99,235,.08);
  --cf-blue-softer: rgba(37,99,235,.16);
  --cf-blue-pale:   rgba(37,99,235,.04);
  --cf-amber:       #c8850f;
}

.cf-card { border-color:var(--cf-blue-softer); }

/* ── Control bar ── */
.cf-controlbar {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  padding:12px 16px; margin-bottom:14px;
  background:linear-gradient(135deg, var(--cf-blue-pale), transparent 60%);
}
.cf-pill {
  font-family:var(--font-m); font-size:.76rem; font-weight:700;
  padding:7px 14px; border-radius:20px; white-space:nowrap;
  background:var(--cf-blue-soft); color:var(--cf-blue-deep); border:1px solid var(--cf-blue-softer);
}
.cf-dd {
  padding:7px 12px; font-size:.76rem; font-weight:600;
  background:var(--surface); border:1px solid var(--cf-blue-softer); color:var(--text);
  border-radius:8px; font-family:var(--font-m);
}
.cf-dd:focus { outline:none; border-color:var(--cf-blue); box-shadow:0 0 0 3px var(--cf-blue-soft); }
.cf-btn-saveall {
  font-family:var(--font-m); font-size:.78rem; font-weight:700; padding:8px 16px;
  border-radius:9px; cursor:pointer; border:1px solid var(--cf-blue-deep);
  background:var(--cf-blue); color:#fff; margin-left:auto;
}
.cf-btn-saveall:hover { background:var(--cf-blue-deep); }
.cf-btn-saveall:disabled { opacity:.6; cursor:default; }
.cf-btn-csv {
  font-family:var(--font-m); font-size:.76rem; font-weight:600; padding:7px 14px;
  border-radius:9px; cursor:pointer; text-decoration:none; white-space:nowrap; display:inline-block;
  background:var(--cf-blue-soft); color:var(--cf-blue-deep); border:1px solid var(--cf-blue-softer);
}
.cf-btn-csv:hover { background:var(--cf-blue-softer); }

/* ── Legend + scroll hint ── */
.cf-legend-row {
  display:flex; align-items:center; gap:16px; flex-wrap:wrap;
  padding:2px 4px 12px; font-family:var(--font-m); font-size:.68rem; color:var(--subtext2);
}
.cf-legend-row span { display:inline-flex; align-items:center; gap:5px; }
.cf-sw { width:10px; height:10px; border-radius:3px; display:inline-block; flex-shrink:0; }
.cf-sw-editable { background:var(--surface); border:1px solid var(--cf-blue-softer); }
.cf-sw-running  { background:var(--cf-blue-soft); border:1px solid var(--cf-blue-softer); }
.cf-sw-total    { background:var(--cf-blue-mid); }
.cf-dot { width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
.cf-dot-green { background:var(--cf-blue); }
.cf-dot-amber { background:var(--cf-amber); }
.cf-scrollhint {
  text-align:center; font-family:var(--font-m); font-size:.68rem; color:var(--subtext);
  padding-bottom:10px;
}

/* ── Month overview cards ── */
.cf-stats {
  display:grid; grid-template-columns:repeat(5,1fr); overflow:hidden;
  padding:0; margin-bottom:14px;
  background:var(--cf-blue-pale);
}
.cf-stats > div { padding:13px 14px; border-right:1px solid var(--cf-blue-softer); border-left:3px solid var(--cf-blue); }
.cf-stats > div:first-child { border-left:none; }
.cf-stats > div:last-child { border-right:none; }
.cf-kicker {
  font-family:var(--font-m); font-size:.56rem; text-transform:uppercase;
  letter-spacing:.06em; color:var(--cf-blue-deep); margin-bottom:4px; font-weight:700;
}
.cf-statval { font-size:.98rem; font-weight:700; color:var(--cf-blue-deep); }

/* ── Table ── */
.cf-tbl {
  width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.72rem;
  border:1px solid var(--cf-blue-softer);
}
.cf-tbl th, .cf-tbl td { border-right:1px solid var(--cf-blue-softer); border-bottom:1px solid var(--cf-blue-softer); }
.cf-tbl th:last-child, .cf-tbl td:last-child { border-right:none; }

.cf-tbl thead th {
  background:var(--cf-blue-deep); color:#fff; font-weight:700;
  padding:7px 6px; text-align:center; white-space:nowrap;
}
.cf-labelcol {
  min-width:190px; text-align:left !important; position:sticky; left:0; z-index:2;
  background:var(--surface);
}
.cf-tbl thead .cf-labelcol { background:var(--cf-blue-deep); z-index:3; }
.cf-datecol { min-width:64px; }
.cf-totalcol {
  min-width:96px; background:var(--cf-blue-mid); color:#fff; font-weight:700; text-align:center;
  position:sticky; right:0; z-index:2; box-shadow:-3px 0 8px rgba(0,0,0,.18);
}
.cf-tbl thead .cf-totalcol { z-index:3; }
.cf-tbl tbody .cf-totalcol { background:var(--cf-blue-soft); color:var(--cf-blue-deep); }

.cf-datehead { display:flex; flex-direction:column; align-items:center; gap:2px; }
.cf-dm { font-weight:700; font-size:.82rem; }
.cf-dow { font-size:.58rem; letter-spacing:.05em; opacity:.85; }

.cf-totalhead { display:flex; flex-direction:column; align-items:center; gap:4px; }
.cf-btn-total-save {
  border:none; background:rgba(255,255,255,.18); color:#fff; cursor:pointer;
  font-family:var(--font-m); font-size:.66rem; font-weight:700;
  border-radius:6px; padding:3px 9px; line-height:1.5; white-space:nowrap;
}
.cf-btn-total-save:hover { background:rgba(255,255,255,.32); }
.cf-btn-total-save:disabled { opacity:.6; cursor:default; }

.cf-datacell { padding:3px 4px; text-align:center; }
.cf-labelcol { padding:6px 8px; font-weight:600; }

/* Section banner rows */
.cf-section-row td {
  background:var(--cf-blue-mid); color:#fff; font-weight:700; letter-spacing:.03em;
  padding:6px 10px; text-align:left; position:sticky; left:0;
}

/* Spacer rows */
.cf-spacer td { height:6px; padding:0; border:none; background:transparent; }

/* Running / total rows */
.cf-running-row td { background:var(--cf-blue-soft); }
.cf-running-row .cf-labelcol { color:var(--cf-blue-deep); font-style:italic; }
.cf-total-row td { background:var(--cf-blue-softer); font-weight:700; }
.cf-total-row .cf-labelcol { color:var(--cf-blue-deep); }

.cf-calc { font-weight:700; color:var(--cf-blue-deep); }

/* Flat, borderless input cells — matches the plain-number spreadsheet look */
.cf-tbl .cfi {
  width:64px; padding:4px 3px; border:1px solid transparent; border-radius:5px;
  background:transparent; color:var(--text); font-family:var(--font-m); font-size:.72rem;
  text-align:center; -moz-appearance:textfield; appearance:textfield;
}
.cf-tbl .cfi::-webkit-outer-spin-button,
.cf-tbl .cfi::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.cf-tbl .cfi:focus { outline:none; background:var(--surface); border-color:var(--cf-blue); box-shadow:0 0 0 2px var(--cf-blue-soft); }
</style>

<script>
const DATES = <?=json_encode($dates)?>;

const gv = (date, field) => {
  const el = document.querySelector(`.cfi[data-date="${date}"][data-field="${field}"]`);
  return parseFloat(el?.value) || 0;
};
const setCalc = (date, field, val) => {
  const el = document.querySelector(`.cf-calc[data-date="${date}"][data-field="${field}"]`);
  if (el) el.textContent = val.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
};
const fmt = n => n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

const PAYABLES_FIELDS   = ['jaro_payables_consumables','consumables','janitorial','office'];
const FRONTLINE_FIELDS  = ['frontline_daily_fixed','frontline_holiday','frontline_reliever'];
const PF_FIELDS         = ['pf_xray','pf_clinic_doctor','pf_ob_doctor','pf_ecg','pf_2d_echo','pf_ultrasound',
                            'pf_ob_ultrasound','pf_ortho','pf_cardio','pf_patho_it','pf_im','pf_pedia'];
const RUNNING_FIELDS    = ['running_sales','running_cash','running_hmo','running_ctc','running_drcr'];
const ALL_TOTAL_FIELDS = ['cash','hmo','charge_to_company','dr_cr','labs','xray_ecg_utz_2decho','packages','sc_pwd',
  ...RUNNING_FIELDS,'running_total',
  ...PAYABLES_FIELDS,'jaro_payables_total', ...FRONTLINE_FIELDS,'frontline_total', ...PF_FIELDS,'pf_total'];

function recalcAll() {
  let run = {cash:0, hmo:0, ctc:0, drcr:0};
  const rowTotals = {};
  ALL_TOTAL_FIELDS.forEach(f => rowTotals[f] = 0);

  DATES.forEach(date => {
    const labs = gv(date,'labs'), xray = gv(date,'xray_ecg_utz_2decho'), pkg = gv(date,'packages'), scpwd = gv(date,'sc_pwd');
    const cash = labs + xray + pkg + scpwd;
    setCalc(date, 'cash', cash);

    const hmo = gv(date,'hmo'), ctc = gv(date,'charge_to_company'), drcr = gv(date,'dr_cr');

    const rs = gv(date,'running_sales'), rc = gv(date,'running_cash'), rh = gv(date,'running_hmo'),
          rctc = gv(date,'running_ctc'), rdrcr = gv(date,'running_drcr');
    const runningTotal = rs + rc + rh + rctc + rdrcr;
    setCalc(date, 'running_total', runningTotal);

    const pc = gv(date,'jaro_payables_consumables'), cons = gv(date,'consumables'), jan = gv(date,'janitorial'), off = gv(date,'office');
    const payTotal = pc + cons + jan + off;
    setCalc(date, 'jaro_payables_total', payTotal);

    const dfa = gv(date,'frontline_daily_fixed'), hol = gv(date,'frontline_holiday'), rel = gv(date,'frontline_reliever');
    const frontTotal = dfa + hol + rel;
    setCalc(date, 'frontline_total', frontTotal);

    const pfTotal = PF_FIELDS.reduce((s,f) => s + gv(date,f), 0);
    setCalc(date, 'pf_total', pfTotal);

    rowTotals['cash'] += cash; rowTotals['hmo'] += hmo; rowTotals['charge_to_company'] += ctc; rowTotals['dr_cr'] += drcr;
    rowTotals['labs'] += labs; rowTotals['xray_ecg_utz_2decho'] += xray; rowTotals['packages'] += pkg; rowTotals['sc_pwd'] += scpwd;
    RUNNING_FIELDS.forEach(f => rowTotals[f] += gv(date,f));
    rowTotals['running_total'] += runningTotal;
    PAYABLES_FIELDS.forEach(f => rowTotals[f] += gv(date,f));
    rowTotals['jaro_payables_total'] += payTotal;
    FRONTLINE_FIELDS.forEach(f => rowTotals[f] += gv(date,f));
    rowTotals['frontline_total'] += frontTotal;
    PF_FIELDS.forEach(f => rowTotals[f] += gv(date,f));
    rowTotals['pf_total'] += pfTotal;
  });

  Object.keys(rowTotals).forEach(f => {
    const cell = document.querySelector(`td.cf-totalcol[data-field="${f}"]`);
    if (cell) cell.textContent = fmt(rowTotals[f]);
  });
}

async function saveDay(date) {
  const fd = new FormData();
  fd.append('ajax_action', 'save_day');
  fd.append('entry_date', date);
  document.querySelectorAll(`.cfi[data-date="${date}"]`).forEach(el => {
    fd.append(el.dataset.field, parseFloat(el.value) || 0);
  });
  try {
    const r = await fetch('demic_jaro_daily_cashflow.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      const dot = document.querySelector(`[data-role="daydot-${date}"]`);
      if (dot) { dot.classList.remove('cf-dot-amber'); dot.classList.add('cf-dot-green'); }
    } else {
      alert('Error saving ' + date + ': ' + d.msg);
    }
    return d;
  } catch (e) { alert('Network error saving ' + date); return {ok:false}; }
}

async function saveAllDays() {
  const btns = document.querySelectorAll('.cf-save-trigger');
  const labels = document.querySelectorAll('.cf-savebtn-label');
  btns.forEach(b => b.disabled = true);
  labels.forEach(l => { l.dataset.orig = l.textContent; l.textContent = 'Saving…'; });

  let failed = 0;
  for (const date of DATES) {
    const res = await saveDay(date);
    if (!res.ok) failed++;
  }

  btns.forEach(b => b.disabled = false);
  labels.forEach(l => { l.textContent = l.dataset.orig; });
  if (failed > 0) alert(failed + ' day(s) failed to save. Please check them and try again.');
}
</script>