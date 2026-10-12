<?php
// ============================================================
//  demiclab_jaro_summary_report.php — DemicLab-Jaro Branch Daily Summary
//  Columns: 4010 | Gross Sales (POS+Discount) | POS Reading |
//  Cash | HMO | Charge to Company | Debit (less 4%) |
//  Credit (less 3.5%) | Discounts: 30%/SC/PWD(20%)/15%/10%/5% |
//  Total Discounts | Total After Discounts | Late Payment | Remarks
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ─────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `demiclab_jaro_report_entries` (
        `id`                    int(11) NOT NULL AUTO_INCREMENT,
        `report_date`           date NOT NULL,
        `store_name`            varchar(100) NOT NULL DEFAULT 'DemicLab-Jaro',
        `cash`                  decimal(12,2) DEFAULT 0.00,
        `hmo`                   decimal(12,2) DEFAULT 0.00,
        `charge_to_company`     decimal(12,2) DEFAULT 0.00,
        `debit`                 decimal(12,2) DEFAULT 0.00,
        `credit`                decimal(12,2) DEFAULT 0.00,
        `disc_30`               decimal(12,2) DEFAULT 0.00,
        `disc_scpwd`            decimal(12,2) DEFAULT 0.00,
        `disc_15`               decimal(12,2) DEFAULT 0.00,
        `disc_10`               decimal(12,2) DEFAULT 0.00,
        `disc_5`                decimal(12,2) DEFAULT 0.00,
        `total_discounts`       decimal(12,2) DEFAULT 0.00,
        `pos_reading`           decimal(12,2) DEFAULT 0.00,
        `gross_sales`           decimal(12,2) DEFAULT 0.00,
        `total_after_discounts` decimal(12,2) DEFAULT 0.00,
        `late_payment`          decimal(12,2) DEFAULT 0.00,
        `remarks`               text DEFAULT NULL,
        `saved_by`              varchar(100) DEFAULT NULL,
        `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_date_store` (`report_date`, `store_name`),
        KEY `idx_date` (`report_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $ignored) {}

// ── Auto-patch table if it already existed with a different/older
//    schema (CREATE TABLE IF NOT EXISTS above won't add columns to a
//    table that already exists — this makes it self-healing) ───────
$jaroReportCols = [
    'cash'                  => "decimal(12,2) DEFAULT 0.00",
    'hmo'                   => "decimal(12,2) DEFAULT 0.00",
    'charge_to_company'     => "decimal(12,2) DEFAULT 0.00",
    'debit'                 => "decimal(12,2) DEFAULT 0.00",
    'credit'                => "decimal(12,2) DEFAULT 0.00",
    'disc_30'               => "decimal(12,2) DEFAULT 0.00",
    'disc_scpwd'            => "decimal(12,2) DEFAULT 0.00",
    'disc_15'               => "decimal(12,2) DEFAULT 0.00",
    'disc_10'               => "decimal(12,2) DEFAULT 0.00",
    'disc_5'                => "decimal(12,2) DEFAULT 0.00",
    'total_discounts'       => "decimal(12,2) DEFAULT 0.00",
    'pos_reading'           => "decimal(12,2) DEFAULT 0.00",
    'gross_sales'           => "decimal(12,2) DEFAULT 0.00",
    'total_after_discounts' => "decimal(12,2) DEFAULT 0.00",
    'late_payment'          => "decimal(12,2) DEFAULT 0.00",
    'remarks'               => "text DEFAULT NULL",
    'saved_by'              => "varchar(100) DEFAULT NULL",
];
foreach ($jaroReportCols as $colName => $colDef) {
    try {
        $pdo->exec("ALTER TABLE `demiclab_jaro_report_entries` ADD COLUMN IF NOT EXISTS `$colName` $colDef");
    } catch (Throwable $ignored) {}
}

// ── AJAX save (per-row) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $reportDate = $_POST['report_date'] ?? '';
    if (!$reportDate) { echo json_encode(['ok'=>false,'msg'=>'Missing date.']); exit; }

    $numCols = [
        'cash','hmo','charge_to_company','debit','credit',
        'disc_30','disc_scpwd','disc_15','disc_10','disc_5',
        'total_discounts','pos_reading','gross_sales',
        'total_after_discounts','late_payment'
    ];

    $data = ['store_name'=>'DemicLab-Jaro','report_date'=>$reportDate];
    foreach ($numCols as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    $data['remarks']  = trim($_POST['remarks'] ?? '');
    $data['saved_by'] = $user['name'];

    $fields    = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields));

    try {
        $sql = "INSERT INTO demiclab_jaro_report_entries ("
             . implode(',', array_map(fn($f) => "`$f`", $fields))
             . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")"
             . " ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX quota save ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_quota'])) {
    header('Content-Type: application/json');
    $month = trim($_POST['report_month'] ?? '');
    $quota = (float)($_POST['quota'] ?? 0);
    if (!$month) { echo json_encode(['ok'=>false,'msg'=>'Missing data']); exit; }
    try {
        try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ig) {}
        $pdo->prepare("INSERT INTO summary_reports (report_month,store_name,quota_target,created_by)
            VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE quota_target=VALUES(quota_target),created_by=VALUES(created_by)")
            ->execute([$month.'-01','DemicLab-Jaro',$quota,$user['name']]);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csvYear  = (int)($_GET['year']  ?? date('Y'));
    $csvMonth = (int)($_GET['month'] ?? date('n'));
    $mNames   = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $days = cal_days_in_month(CAL_GREGORIAN, $csvMonth, $csvYear);
    $stmt = $pdo->prepare("SELECT * FROM demiclab_jaro_report_entries WHERE store_name='DemicLab-Jaro' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$csvYear, $csvMonth]);
    $saved = [];
    foreach ($stmt->fetchAll() as $r) $saved[$r['report_date']] = $r;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab-Jaro_'.$mNames[$csvMonth].'_'.$csvYear.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['DemicLab-Jaro — Summary Report', $mNames[$csvMonth].' '.$csvYear]);
    fputcsv($out,[]);
    fputcsv($out,['DATE','GROSS SALES (POS+DISC)','POS READING','CASH','HMO','CHARGE TO COMPANY','DEBIT (less 4%)','CREDIT (less 3.5%)','30%','SC/PWD(20%)','15%','10%','5%','TOTAL DISCOUNTS','TOTAL AFTER DISCOUNTS','LATE PAYMENT','REMARKS']);
    $totals = array_fill(0, 16, 0);
    for ($d=1;$d<=$days;$d++) {
        $ds = sprintf('%04d-%02d-%02d',$csvYear,$csvMonth,$d);
        $r  = $saved[$ds] ?? null;
        $v  = fn($k) => $r ? (float)$r[$k] : 0;
        $line = [
            date('d-M-Y (D)',strtotime($ds)),
            $v('gross_sales'), $v('pos_reading'), $v('cash'), $v('hmo'),
            $v('charge_to_company'), $v('debit'), $v('credit'),
            $v('disc_30'), $v('disc_scpwd'), $v('disc_15'), $v('disc_10'), $v('disc_5'),
            $v('total_discounts'), $v('total_after_discounts'), $v('late_payment'),
            $r['remarks'] ?? ''
        ];
        fputcsv($out, $line);
        for ($i=1;$i<=15;$i++) $totals[$i] += is_numeric($line[$i]) ? $line[$i] : 0;
    }
    $tRow = ['TOTAL']; for($i=1;$i<=15;$i++) $tRow[]=$totals[$i]; $tRow[]='';
    fputcsv($out,$tRow);
    fclose($out); exit;
}

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$mNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
$allDays = [];
for ($d=1;$d<=$daysInMonth;$d++) $allDays[] = sprintf('%04d-%02d-%02d',$fYear,$fMonth,$d);

// ── Load saved rows ───────────────────────────────────────
$savedRows = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM demiclab_jaro_report_entries WHERE store_name='DemicLab-Jaro' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$fYear,$fMonth]);
    foreach ($stmt->fetchAll() as $r) $savedRows[$r['report_date']] = $r;
} catch (Throwable $ignored) {}

// ── Load quota ────────────────────────────────────────────
$savedQuota = 0;
try {
    try { $pdo->exec("ALTER TABLE summary_reports ADD COLUMN IF NOT EXISTS quota_target decimal(12,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ig) {}
    $qr = $pdo->prepare("SELECT quota_target FROM summary_reports WHERE store_name='DemicLab-Jaro' AND YEAR(report_month)=? AND MONTH(report_month)=? LIMIT 1");
    $qr->execute([$fYear,$fMonth]);
    $savedQuota = (float)($qr->fetchColumn() ?: 0);
} catch(Throwable $ignored) {}

$COLS = ['cash','hmo','charge_to_company','debit','credit','disc_30','disc_scpwd','disc_15','disc_10','disc_5','total_discounts','gross_sales','total_after_discounts','late_payment'];

$pageTitle  = 'DemicLab-Jaro Summary Report';
$activePage = 'demiclab_summary';
include '../layout.php';
?>

<style>
.page-content { padding: 20px 24px !important; overflow-x: hidden; }
.sr-outer {
  width:100%; overflow-x:auto; overflow-y:visible;
  border-radius:var(--radius); border:1px solid var(--border);
  background:var(--surface); scrollbar-width:thin;
  scrollbar-color:#c1c7d0 #f1f3f5;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.sr-outer::-webkit-scrollbar{height:8px}
.sr-outer::-webkit-scrollbar-track{background:#f1f3f5}
.sr-outer::-webkit-scrollbar-thumb{background:#c1c7d0;border-radius:4px}
.sr-table{border-collapse:collapse;width:max-content;font-size:.69rem;table-layout:fixed}
.sr-table thead th{
  background:#1e3060;color:#fff;
  font-family:var(--font-m);font-size:.52rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.06em;
  padding:8px 5px;border:1px solid #2d4080;
  white-space:normal;text-align:center;line-height:1.3;
  position:sticky;top:0;z-index:20;
}
.sr-table thead tr.grp-row th{
  font-size:.5rem;padding:4px 6px;background:#162050;letter-spacing:.05em;
}
.th-date{position:sticky!important;left:0;z-index:30!important;width:90px;min-width:90px;background:#162050!important}
.th-act{position:sticky!important;right:0;z-index:30!important;width:66px;min-width:66px;background:#162050!important}
.td-date{
  position:sticky;left:0;z-index:5;
  background:#f8f9fb!important;box-shadow:2px 0 5px rgba(0,0,0,.06);
  font-family:var(--font-m);font-size:.69rem;color:var(--accent);
  font-weight:600;text-align:center;padding:0 5px;
  white-space:nowrap;width:90px;min-width:90px;
}
.td-act{
  position:sticky;right:0;z-index:5;
  background:#f8f9fb!important;box-shadow:-2px 0 5px rgba(0,0,0,.06);
  text-align:center;padding:3px 4px;width:66px;min-width:66px;
}
.g-sales{background:#1a4a2e!important}
.g-cash{background:#1e3060!important}
.g-disc{background:#4a1a00!important}
.g-calc{background:#1a3a4a!important}
.g-note{background:#2d2060!important}

.c-sales{background:#f0fdf4}
.c-cash{background:#eff6ff}
.c-disc{background:#fff7ed}
.c-calc{background:#f0f9ff}
.c-note{background:#faf5ff}

.col-num{width:90px;min-width:90px}
.col-num-sm{width:78px;min-width:78px}
.col-txt{width:160px;min-width:160px}

.sr-table tbody tr{border-bottom:1px solid #e8eaed;transition:background .1s}
.sr-table tbody tr:hover td{filter:brightness(.97)}
.sr-table td{border:1px solid #e3e6ea;padding:0;vertical-align:middle}

.sri{
  width:100%;padding:6px 5px;
  background:transparent;border:none;outline:none;
  color:#1a1d23;font-family:var(--font-m);font-size:.69rem;
  text-align:right;display:block;box-sizing:border-box;
}
.sri:focus{background:rgba(15,123,92,.07);outline:1px solid rgba(15,123,92,.4)}
.sri.calc{color:#1d4ed8;background:rgba(37,99,235,.04);font-weight:700;cursor:default}
.sri.txt{text-align:left;font-family:var(--font-h);font-size:.69rem}
.sri.neg{color:#be123c!important;font-weight:600}

.bsr{
  padding:4px 8px;font-size:.6rem;
  font-family:var(--font-m);font-weight:700;
  background:#f0fdf4;color:#15803d;
  border:1px solid #bbf7d0;border-radius:5px;
  cursor:pointer;white-space:nowrap;
  transition:all .13s;letter-spacing:.02em;width:56px;
}
.bsr:hover{background:#dcfce7;border-color:#86efac}
.bsr.saving{opacity:.5;pointer-events:none}
.bsr.ok{background:#dcfce7;color:#15803d;border-color:#86efac}
.bsr.err{background:#fff1f2;color:#be123c;border-color:#fecdd3}

.sr-table tfoot td{
  padding:7px 5px;font-family:var(--font-m);font-size:.69rem;font-weight:700;
  text-align:right;border:1px solid #d1d5db;
  background:#1e3060;color:#fff;
}
.sr-table tfoot td.tfl{text-align:center;font-size:.6rem;text-transform:uppercase;letter-spacing:.06em;position:sticky;left:0;z-index:5}
.sr-table tfoot td.tfr{position:sticky;right:0;z-index:5}

.sdot{display:inline-block;width:6px;height:6px;border-radius:50%;margin-left:4px;vertical-align:middle}
.sdot.ok{background:#22c55e;box-shadow:0 0 4px #22c55e}
.sdot.new{background:#f59e0b}
.toast{position:fixed;top:68px;right:22px;z-index:9999;max-width:320px;animation:fadeSlideDown .3s ease}
.sr-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.scroll-hint{font-family:var(--font-m);font-size:.62rem;color:var(--subtext);text-align:center;padding:5px 12px;border-bottom:1px solid var(--border);background:#f8f9fb}
.sr-leg{display:flex;gap:12px;flex-wrap:wrap;font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-bottom:10px;align-items:center}
.sr-leg span{display:flex;align-items:center;gap:5px}
.ld{display:inline-block;width:9px;height:9px;border-radius:3px}
</style>

<!-- Header -->
<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">DemicLab-Jaro <span style="color:var(--accent)">Summary Report</span></div>
    <div class="section-subtitle">Daily entry · each row has its own Save button · all calculated fields update automatically</div>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="sr-controls">
  <div style="font-family:var(--font-m);font-size:.76rem;color:var(--accent);padding:7px 13px;background:var(--accent-dim);border-radius:8px;border:1px solid rgba(34,211,165,.2)">
    📍 DemicLab-Jaro
  </div>
  <select name="month" class="form-control" style="max-width:130px" onchange="this.form.submit()">
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$mNames[$m]?></option>
    <?php endfor; ?>
  </select>
  <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
    <?php for($y=2050;$y>=2023;$y--): ?>
    <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="button" class="btn btn-primary btn-sm" onclick="saveAll()">💾 Save All</button>
  <a href="#" class="btn btn-ghost btn-sm" style="color:var(--accent3);border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.06)" onclick="downloadCSV(event)">⬇ Download CSV</a>
  <a href="demiclab_jaro_summary_overview.php" class="btn btn-ghost btn-sm">📊 View Overview</a>
</form>

<!-- Legend -->
<div class="sr-leg">
  <span><span class="ld" style="background:#bbf7d0;border:1px solid #86efac"></span>Sales / Income</span>
  <span><span class="ld" style="background:#bfdbfe;border:1px solid #93c5fd"></span>Cash Breakdown</span>
  <span><span class="ld" style="background:#fed7aa;border:1px solid #fdba74"></span>Discounts</span>
  <span><span class="ld" style="background:#e0f2fe;border:1px solid #7dd3fc"></span>Auto-calculated</span>
  <span style="margin-left:8px"><span class="sdot ok"></span> Saved &nbsp;<span class="sdot new"></span> Unsaved</span>
</div>

<!-- Table -->
<div class="sr-outer">
<div class="scroll-hint">← Scroll horizontally to see all columns →</div>
<table class="sr-table" id="srt">
  <thead>
    <tr class="grp-row">
      <th class="th-date"></th>
      <th colspan="1" class="g-sales">SALES</th>
      <th colspan="6" class="g-cash">CASH BREAKDOWN</th>
      <th colspan="5" class="g-disc">DISCOUNTS</th>
      <th colspan="2" class="g-calc">AUTO-CALCULATED</th>
      <th colspan="3" class="g-note">NOTES</th>
      <th class="th-act"></th>
    </tr>
    <tr>
      <th class="th-date">DATE</th>
      <!-- Sales -->
      <th class="g-sales col-num">GROSS SALES<br>(POS+DISC)</th>
      <!-- Cash Breakdown -->
      <th class="g-cash col-num">POS READING</th>
      <th class="g-cash col-num">CASH</th>
      <th class="g-cash col-num">HMO</th>
      <th class="g-cash col-num">CHARGE TO COMPANY</th>
      <th class="g-cash col-num">DEBIT<br>(amt less 4%)</th>
      <th class="g-cash col-num">CREDIT<br>(amt less 3.5%)</th>
      <!-- Discounts -->
      <th class="g-disc col-num-sm">30%</th>
      <th class="g-disc col-num-sm">SC/PWD<br>(20%)</th>
      <th class="g-disc col-num-sm">15%</th>
      <th class="g-disc col-num-sm">10%</th>
      <th class="g-disc col-num-sm">5%</th>
      <!-- Auto-calc -->
      <th class="g-calc col-num">TOTAL DISCOUNTS</th>
      <th class="g-calc col-num">TOTAL AFTER DISCOUNTS</th>
      <!-- Notes -->
      <th class="g-note col-num">LATE PAYMENT (CHECK)</th>
      <th class="g-note col-txt">REMARKS</th>
      <th class="th-act">SAVE</th>
    </tr>
  </thead>

  <tbody>
  <?php foreach ($allDays as $ds):
    $dayN  = (int)date('j', strtotime($ds));
    $dayNm = date('D', strtotime($ds));
    $row   = $savedRows[$ds] ?? null;
    $saved = $row !== null;
    $rid   = 'r'.str_replace('-','',$ds);
    $dv    = fn($k) => ($row && isset($row[$k]) && (float)$row[$k] != 0)
                       ? number_format((float)$row[$k],2,'.','') : '';
  ?>
  <tr id="<?=$rid?>" data-date="<?=$ds?>" data-saved="<?=$saved?1:0?>">
    <td class="td-date">
      <?=$dayN?>-<?=date('M',strtotime($ds))?> <small style="color:var(--subtext);font-size:.6rem"><?=$dayNm?></small>
      <span class="sdot <?=$saved?'ok':'new'?>" id="dot_<?=$rid?>"></span>
    </td>
    <!-- Sales: Gross Sales (auto: POS Reading + Total Discounts) -->
    <td class="c-sales"><input type="number" step="0.01" class="sri calc" data-col="gross_sales" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <!-- Cash breakdown: POS Reading (auto: cash+hmo+ctc+debit+credit) | Cash | HMO | Charge | Debit | Credit -->
    <td class="c-calc"><input type="number" step="0.01" class="sri calc" data-col="pos_reading_calc" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <td class="c-cash"><input type="number" step="0.01" class="sri" data-col="cash" data-row="<?=$rid?>" value="<?=$dv('cash')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-cash"><input type="number" step="0.01" class="sri" data-col="hmo" data-row="<?=$rid?>" value="<?=$dv('hmo')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-cash"><input type="number" step="0.01" class="sri" data-col="charge_to_company" data-row="<?=$rid?>" value="<?=$dv('charge_to_company')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-cash"><input type="number" step="0.01" class="sri" data-col="debit" data-row="<?=$rid?>" value="<?=$dv('debit')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-cash"><input type="number" step="0.01" class="sri" data-col="credit" data-row="<?=$rid?>" value="<?=$dv('credit')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- Discounts -->
    <td class="c-disc"><input type="number" step="0.01" class="sri" data-col="disc_30" data-row="<?=$rid?>" value="<?=$dv('disc_30')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-disc"><input type="number" step="0.01" class="sri" data-col="disc_scpwd" data-row="<?=$rid?>" value="<?=$dv('disc_scpwd')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-disc"><input type="number" step="0.01" class="sri" data-col="disc_15" data-row="<?=$rid?>" value="<?=$dv('disc_15')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-disc"><input type="number" step="0.01" class="sri" data-col="disc_10" data-row="<?=$rid?>" value="<?=$dv('disc_10')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-disc"><input type="number" step="0.01" class="sri" data-col="disc_5" data-row="<?=$rid?>" value="<?=$dv('disc_5')?>" placeholder="0.00" oninput="changed(this)"></td>
    <td class="c-calc"><input type="number" step="0.01" class="sri calc" data-col="total_discounts" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <td class="c-calc"><input type="number" step="0.01" class="sri calc" data-col="total_after_discounts" data-row="<?=$rid?>" value="" readonly tabindex="-1"></td>
    <td class="c-note"><input type="number" step="0.01" class="sri" data-col="late_payment" data-row="<?=$rid?>" value="<?=$dv('late_payment')?>" placeholder="0.00" oninput="changed(this)"></td>
    <!-- Remarks -->
    <td class="c-note"><input type="text" class="sri txt" data-col="remarks" data-row="<?=$rid?>" value="<?=htmlspecialchars($row['remarks']??'')?>" placeholder="…" oninput="changed(this)"></td>
    <td class="td-act">
      <button class="bsr" id="btn_<?=$rid?>" onclick="saveRow('<?=$rid?>','<?=$ds?>')">
        <?=$saved?'Update':'Save'?>
      </button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr>
      <td class="tfl">TOTAL</td>
      <?php
      $footCols = ['gross_sales','pos_reading_calc','cash','hmo','charge_to_company','debit','credit','disc_30','disc_scpwd','disc_15','disc_10','disc_5','total_discounts','total_after_discounts','late_payment'];
      foreach ($footCols as $c): ?>
      <td id="tot_<?=$c?>">—</td>
      <?php endforeach; ?>
      <td></td><!-- remarks -->
      <td class="tfr"></td>
    </tr>
  </tfoot>
</table>
</div>

<!-- Save All -->
<div style="display:flex;justify-content:flex-end;margin-top:12px">
  <button class="btn btn-primary" onclick="saveAll()">💾 Save All Rows</button>
</div>

<!-- ── SUMMARY PANEL ── -->
<div class="card" style="margin-top:20px;border-top:2px solid var(--accent3);overflow:hidden">
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid var(--border)">
    <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--subtext)">DemicLab-Jaro — Monthly Performance Summary</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">

    <!-- Monthly Quota input -->
    <div style="padding:16px 20px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Monthly Quota</div>
      <input id="quotaInput" type="number" step="0.01" min="0"
        value="<?= $savedQuota>0?number_format($savedQuota,2,'.',''):'' ?>"
        placeholder="0.00"
        style="background:#fff;border:1px solid #d1d5db;border-radius:7px;color:#1a1d23;font-family:var(--font-m);font-size:.88rem;font-weight:700;padding:6px 10px;width:100%;outline:none;transition:border-color .15s"
        oninput="recalcSummary()"
        onfocus="this.style.borderColor='#0f7b5c'"
        onblur="this.style.borderColor='#d1d5db';saveQuota()">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Input monthly target</div>
    </div>

    <!-- Summary computed values -->
    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#f0fdf4">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:6px">Summary</div>
      <div style="font-family:var(--font-m);font-size:.72rem;line-height:2">
        <div>Cash Sales: <strong id="sum_cash_sales" style="color:#0f7b5c">—</strong></div>
        <div>POS Reading: <strong id="sum_pos_reading" style="color:#0f7b5c">—</strong></div>
        <div>POS + Discount: <strong id="sum_pos_discount" style="color:#0f7b5c">—</strong></div>
        <div>Total Discount: <strong id="sum_total_discount" style="color:#dc3545">—</strong></div>
        <div>Gross Sale: <strong id="sum_gross_sale" style="color:#0f7b5c">—</strong></div>
      </div>
    </div>

    <div style="padding:16px 20px;border-right:1px solid var(--border);background:#fffbeb">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Pending Quota</div>
      <div id="sum_pending" style="font-size:1.25rem;font-weight:800;color:#b45309;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Monthly Quota − Gross Sale</div>
      <div style="margin-top:10px;font-family:var(--font-m);font-size:.7rem">
        %: <strong id="sum_pct" style="color:#2563eb">—</strong>
      </div>
    </div>

    <div style="padding:16px 20px;background:#eff6ff">
      <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.09em;color:var(--subtext);margin-bottom:8px">Daily Target</div>
      <div id="sum_daily_target" style="font-size:1.25rem;font-weight:800;color:#1d4ed8;font-family:var(--font-m)">—</div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">Pending Quota ÷ 2</div>
    </div>

  </div>
</div>

  </div></div>

<script>
const SAVE_COLS = <?=json_encode($COLS)?>;

function gv(rid, col) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  return e ? (parseFloat(e.value) || 0) : 0;
}
function sv(rid, col, val) {
  const e = document.querySelector(`#${rid} [data-col="${col}"]`);
  if (e) e.value = val !== '' ? parseFloat(val).toFixed(2) : '';
}

function recalc(rid) {
  const cash   = gv(rid,'cash');
  const hmo    = gv(rid,'hmo');
  const ctc    = gv(rid,'charge_to_company');
  const debit  = gv(rid,'debit');
  const credit = gv(rid,'credit');
  const d30    = gv(rid,'disc_30');
  const dscpwd = gv(rid,'disc_scpwd');
  const d15    = gv(rid,'disc_15');
  const d10    = gv(rid,'disc_10');
  const d5     = gv(rid,'disc_5');

  const hasCash = cash||hmo||ctc||debit||credit;
  const hasDisc = d30||dscpwd||d15||d10||d5;
  const hasInput = hasCash||hasDisc;

  // POS READING (auto) = Cash + HMO + Charge to Company + Debit + Credit
  const posReadingCalc = cash + hmo + ctc + debit + credit;
  sv(rid,'pos_reading_calc', hasCash ? posReadingCalc : '');

  // TOTAL AFTER DISCOUNTS = I*(1-0.3) + J*(1-0.2) + K*(1-0.15) + L*(1-0.1) + M*(1-0.05)
  const totalAfter = (d30*0.7) + (dscpwd*0.8) + (d15*0.85) + (d10*0.9) + (d5*0.95);
  sv(rid,'total_after_discounts', hasDisc ? totalAfter : '');

  // TOTAL DISCOUNTS = I+J+K+L+M - O  (=I3+J3+K3+L3+M3-O3)
  const totalDisc = d30 + dscpwd + d15 + d10 + d5 - totalAfter;
  sv(rid,'total_discounts', hasDisc ? totalDisc : '');

  // GROSS SALES = POS Reading (auto) + Total Discounts  (=C3+N3)
  const grossSales = posReadingCalc + totalDisc;
  sv(rid,'gross_sales', hasInput ? grossSales : '');

  // LATE PAYMENT — manual input only, no auto-calc
}

function recalcTotals() {
  const colsToTotal = ['pos_reading','gross_sales','pos_reading_calc','cash','hmo','charge_to_company','debit','credit','disc_30','disc_scpwd','disc_15','disc_10','disc_5','total_discounts','total_after_discounts','late_payment'];
  colsToTotal.forEach(col => {
    const el = document.getElementById('tot_' + col) || document.getElementById('tot_' + col + '_display');
    if (!el) return;
    let s = 0;
    document.querySelectorAll(`[data-col="${col}"]`).forEach(i => s += parseFloat(i.value)||0);
    el.textContent = s===0 ? '—' : s.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  });
  recalcSummary();
}

function changed(el) {
  const rid = el.dataset.row;
  recalc(rid);
  recalcTotals();
  const btn = document.getElementById('btn_'+rid);
  btn.textContent = 'Save'; btn.className = 'bsr';
  document.getElementById(rid).dataset.saved = '0';
  document.getElementById('dot_'+rid).className = 'sdot new';
}

async function saveRow(rid, ds) {
  const btn = document.getElementById('btn_'+rid);
  btn.textContent = '…'; btn.className = 'bsr saving';
  const fd = new FormData();
  fd.append('ajax_save','1');
  fd.append('report_date', ds);
  SAVE_COLS.forEach(col => {
    const el = document.querySelector(`#${rid} [data-col="${col}"]`);
    fd.append(col, el ? (el.value||'0') : '0');
  });
  const remEl = document.querySelector(`#${rid} [data-col="remarks"]`);
  fd.append('remarks', remEl ? remEl.value : '');
  try {
    const res  = await fetch('demiclab_jaro_summary_report.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.textContent='Update'; btn.className='bsr ok';
      document.getElementById(rid).dataset.saved='1';
      document.getElementById('dot_'+rid).className='sdot ok';
      setTimeout(()=>{if(btn.className.includes('ok'))btn.className='bsr';},2200);
    } else {
      btn.textContent='Error'; btn.className='bsr err';
      showToast('❌ '+data.msg,'error');
      setTimeout(()=>{btn.textContent='Save';btn.className='bsr';},3000);
    }
  } catch(e){btn.textContent='Error';btn.className='bsr err';showToast('❌ Network error','error');}
}

async function saveAll() {
  const rows = [...document.querySelectorAll('#srt tbody tr')];
  for (const row of rows) {
    await saveRow(row.id, row.dataset.date);
    await new Promise(r=>setTimeout(r,40));
  }
  showToast('✓ All '+rows.length+' rows saved','success');
}

function downloadCSV(e) {
  e.preventDefault();
  const p = new URLSearchParams(window.location.search);
  p.set('export_csv','1');
  window.location.href = 'demiclab_jaro_summary_report.php?'+p.toString();
}

function showToast(msg,type){
  const t=document.createElement('div');
  t.className='flash flash-'+(type||'success')+' toast';
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),4000);
}

function recalcSummary() {
  const quota = parseFloat(document.getElementById('quotaInput')?.value)||0;

  // Cash Sales = sum of cash column
  let cashSales=0, posReading=0, grossSales=0, totalDisc=0;
  document.querySelectorAll('#srt tbody tr').forEach(row => {
    cashSales   += parseFloat(row.querySelector('[data-col="cash"]')?.value)||0;
    posReading  += parseFloat(row.querySelector('[data-col="pos_reading_calc"]')?.value)||0;
    grossSales  += parseFloat(row.querySelector('[data-col="gross_sales"]')?.value)||0;
    totalDisc   += parseFloat(row.querySelector('[data-col="total_discounts"]')?.value)||0;
  });

  const fmt = n => n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

  document.getElementById('sum_cash_sales').textContent    = fmt(cashSales);
  document.getElementById('sum_pos_reading').textContent   = fmt(posReading);
  document.getElementById('sum_pos_discount').textContent  = fmt(grossSales);
  document.getElementById('sum_total_discount').textContent= fmt(totalDisc);
  document.getElementById('sum_gross_sale').textContent    = fmt(grossSales);

  if (quota > 0) {
    const pending    = quota - grossSales;
    const pct        = (grossSales / quota * 100);
    const dailyTgt   = pending / 2;

    document.getElementById('sum_pending').textContent     = pending<=0 ? '✓ Target Met!' : fmt(pending);
    document.getElementById('sum_pending').style.color     = pending<=0 ? 'var(--accent)' : '#b45309';
    document.getElementById('sum_pct').textContent         = pct.toFixed(2)+'%';
    document.getElementById('sum_daily_target').textContent= pending<=0 ? '✓ Done!' : fmt(dailyTgt);
  } else {
    ['sum_pending','sum_pct','sum_daily_target'].forEach(id=>{document.getElementById(id).textContent='—';});
  }
}

async function saveQuota(){
  const val = document.getElementById('quotaInput')?.value;
  if (!val) return;
  const monthEl=document.querySelector('select[name="month"]');
  const yearEl =document.querySelector('select[name="year"]');
  const m = monthEl?monthEl.value.toString().padStart(2,'0'):'<?=date('m')?>';
  const y = yearEl ?yearEl.value:'<?=$fYear?>';
  const fd=new FormData();
  fd.append('ajax_quota','1');
  fd.append('report_month',y+'-'+m);
  fd.append('quota',val);
  try{ await fetch('demiclab_jaro_summary_report.php',{method:'POST',body:fd}); }catch(e){}
}

document.addEventListener('keydown',e=>{
  if(!e.target.classList.contains('sri')||e.key!=='Enter')return;
  e.preventDefault();
  const col=e.target.dataset.col,rid=e.target.dataset.row;
  const next=document.getElementById(rid)?.nextElementSibling;
  if(next){const ni=next.querySelector(`[data-col="${col}"]`);if(ni){ni.focus();ni.select();}}
});

document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('#srt tbody tr').forEach(r=>recalc(r.id));
  recalcTotals();
  recalcSummary();
});
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>