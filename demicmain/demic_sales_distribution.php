<?php
// ============================================================
//  demic_sales_distribution.php — Daily Sales Distribution Report
//  Mirrors "2026 SALES DISTRIBUTION.xlsx": one row per day, with
//  TOTAL DAILY SALES + REACHED TARGET % derived automatically, a
//  % breakdown per revenue channel (Old Client / Walk In / Company
//  Cash / Charge to Company / HMO / Home Service), and a TOTAL row
//  pinned to the bottom of the table.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && !in_array(currentBranch(), ['DemicLab-Main','DemicLab-Jaro'], true)) {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// Fixed daily sales target used for REACHED TARGET % (₱2,000,000/day — matches the workbook).
const SD_DAILY_TARGET = 2000000.0;

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_sales_distribution` (
    `id`                 int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`         date NOT NULL,
    `store_name`         varchar(50) NOT NULL DEFAULT 'Demic Lab',
    `old_client`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `walk_in`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `company_cash`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `charge_to_company`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `dr_cr`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `hmo`                 decimal(12,2) NOT NULL DEFAULT 0.00,
    `home_service`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`            varchar(100) DEFAULT NULL,
    `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

try { $pdo->exec("ALTER TABLE `demic_sales_distribution` ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`)"); } catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `demic_sales_distribution` ADD COLUMN `dr_cr` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}

// ── Channels metadata table — lets an OIC/manager add a brand-new
//    revenue channel column from the UI (💾 "+ Add Channel") without a
//    developer touching code. Each row here gets its own real column
//    added to `demic_sales_distribution` via ALTER TABLE below.
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_sales_distribution_channels` (
    `id`             int(11) NOT NULL AUTO_INCREMENT,
    `channel_key`    varchar(64) NOT NULL,
    `channel_label`  varchar(100) NOT NULL,
    `sort_order`     int(11) NOT NULL DEFAULT 0,
    `created_by`     varchar(100) DEFAULT NULL,
    `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_channel_key` (`channel_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// The full set of editable (input) fields — TOTAL DAILY SALES, REACHED TARGET %, and every
// "%" column are derived, never stored.
$INPUT_KEYS = ['old_client','walk_in','company_cash','charge_to_company','dr_cr','hmo','home_service'];
$INPUT_LABELS = [
    'old_client'         => 'OLD CLIENT',
    'walk_in'            => 'WALK IN',
    'company_cash'       => 'COMPANY (CASH)',
    'charge_to_company'  => 'CHARGE TO COMPANY',
    'dr_cr'              => 'DR/CR',
    'hmo'                => 'HMO',
    'home_service'       => 'HOME SERVICE',
];

// Append any custom channels an OIC/manager has added via "+ Add Channel".
// Their columns already exist on `demic_sales_distribution` (added at
// creation time in the ajax_action=add_channel handler below), so from
// here on they behave exactly like the core channels above.
$customChannels = $pdo->query("SELECT channel_key, channel_label FROM demic_sales_distribution_channels ORDER BY sort_order ASC, id ASC")->fetchAll();
foreach ($customChannels as $cc) {
    $INPUT_KEYS[] = $cc['channel_key'];
    $INPUT_LABELS[$cc['channel_key']] = $cc['channel_label'];
}

// ── AJAX: Save one day's row ──────────────────────────────
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

        $pdo->prepare("INSERT INTO demic_sales_distribution (entry_date,store_name,$cols,saved_by)
                VALUES (?,?,$marks,?)
                ON DUPLICATE KEY UPDATE $updSql, saved_by=VALUES(saved_by)")
            ->execute(array_merge([$date, $store], array_values($vals), [$user['name']]));

        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Clear one day's row ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'clear_day') {
    header('Content-Type: application/json');
    $store = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');
    $date  = $_POST['entry_date'] ?? '';
    $pdo->prepare("DELETE FROM demic_sales_distribution WHERE entry_date=? AND store_name=?")->execute([$date, $store]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── AJAX: Add a new channel column ────────────────────────
// Lets an OIC/manager add a new revenue channel from the UI. Builds a
// safe column key from the label, adds the real column to the data
// table, and records it in demic_sales_distribution_channels so it's
// picked up by $INPUT_KEYS on every future page load.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'add_channel') {
    header('Content-Type: application/json');
    try {
        $label = trim($_POST['channel_label'] ?? '');
        if ($label === '') { echo json_encode(['ok'=>false,'msg'=>'Channel name is required.']); exit; }
        if (mb_strlen($label) > 60) { echo json_encode(['ok'=>false,'msg'=>'Channel name is too long.']); exit; }

        $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
        if ($key === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            echo json_encode(['ok'=>false,'msg'=>'Please use a channel name that starts with a letter.']);
            exit;
        }

        $reserved = ['id','entry_date','store_name','saved_by','updated_at',
            'old_client','walk_in','company_cash','charge_to_company','dr_cr','hmo','home_service',
            'total_daily_sales','reached_target_pct'];
        if (in_array($key, $reserved, true)) { $key .= '_channel'; }

        $dupe = $pdo->prepare("SELECT COUNT(*) FROM demic_sales_distribution_channels WHERE channel_key = ?");
        $dupe->execute([$key]);
        if ((int)$dupe->fetchColumn() > 0) {
            echo json_encode(['ok'=>false,'msg'=>'A channel with that name already exists.']);
            exit;
        }

        $pdo->exec("ALTER TABLE `demic_sales_distribution` ADD COLUMN `$key` decimal(12,2) NOT NULL DEFAULT 0.00");
        $nextOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM demic_sales_distribution_channels")->fetchColumn();
        $pdo->prepare("INSERT INTO demic_sales_distribution_channels (channel_key,channel_label,sort_order,created_by) VALUES (?,?,?,?)")
            ->execute([$key, $label, $nextOrder, $user['name']]);

        echo json_encode(['ok'=>true,'key'=>$key]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
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
$stmt = $pdo->prepare("SELECT * FROM demic_sales_distribution WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? AND $bClause");
$stmt->execute(array_merge([$fYear, $fMonth], $bParams));
$rawByDate = [];
foreach ($stmt->fetchAll() as $r) { $rawByDate[$r['entry_date']] = $r; }

// ── Build the day list + derived matrix ──────────────────
$daysInMonth = (int)date('t', mktime(0,0,0,$fMonth,1,$fYear));
$dates = [];
for ($d = 1; $d <= $daysInMonth; $d++) { $dates[] = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $d); }

function sd_day_calc(array $v, array $inputKeys): array {
    $total = 0.0;
    foreach ($inputKeys as $k) { $total += $v[$k] ?? 0; }
    $v['total_daily_sales'] = round($total, 2);
    $v['reached_target_pct'] = SD_DAILY_TARGET > 0 ? $total / SD_DAILY_TARGET : 0.0;
    foreach ($inputKeys as $k) {
        $v["{$k}_pct"] = $total > 0 ? ($v[$k] ?? 0) / $total : 0.0;
    }
    return $v;
}

$matrix = [];
$hasAnyData = [];
foreach ($dates as $date) {
    $raw = [];
    foreach ($INPUT_KEYS as $k) { $raw[$k] = isset($rawByDate[$date]) ? (float)$rawByDate[$date][$k] : 0.0; }
    $matrix[$date] = sd_day_calc($raw, $INPUT_KEYS);
    $hasAnyData[$date] = isset($rawByDate[$date]);
}

// ── Month totals (bottom TOTAL row) ───────────────────────
$colTotal = array_fill_keys($INPUT_KEYS, 0.0);
$colTotal['total_daily_sales'] = 0.0;
$reachedPctSum = 0.0;
foreach ($dates as $date) {
    foreach ($colTotal as $k => $v) { $colTotal[$k] += $matrix[$date][$k]; }
    $reachedPctSum += $matrix[$date]['reached_target_pct'];
}
$grandTotalSales = $colTotal['total_daily_sales'];
$colTotalPct = [];
foreach ($INPUT_KEYS as $k) { $colTotalPct[$k] = $grandTotalSales > 0 ? $colTotal[$k] / $grandTotalSales : 0.0; }

$daysWithData = count(array_filter($hasAnyData));

// ── CSV export ────────────────────────────────────────────
if (($_GET['export_csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demic_sales_distribution_' . $fYear . '-' . str_pad($fMonth,2,'0',STR_PAD_LEFT) . '.csv"');
    $out = fopen('php://output', 'w');
    $header = ['DATE','TOTAL DAILY SALES','REACHED TARGET %'];
    foreach ($INPUT_KEYS as $k) { $header[] = $INPUT_LABELS[$k]; $header[] = '%'; }
    fputcsv($out, $header);
    foreach ($dates as $date) {
        $m = $matrix[$date];
        $line = [date('j-M', strtotime($date)), $m['total_daily_sales'], round($m['reached_target_pct']*100,2).'%'];
        foreach ($INPUT_KEYS as $k) { $line[] = $m[$k]; $line[] = round($m["{$k}_pct"]*100,2).'%'; }
        fputcsv($out, $line);
    }
    $totalLine = ['TOTAL', $colTotal['total_daily_sales'], round($reachedPctSum*100,2).'%'];
    foreach ($INPUT_KEYS as $k) { $totalLine[] = $colTotal[$k]; $totalLine[] = round($colTotalPct[$k]*100,2).'%'; }
    fputcsv($out, $totalLine);
    fclose($out);
    exit;
}

$pageTitle  = 'Daily Sales Distribution Report';
$activePage = 'demic_sales_distribution';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Daily Sales <span>Distribution Report</span></div>
    <div class="section-subtitle">One row per day · TOTAL DAILY SALES &amp; REACHED TARGET % auto-calculate from the channel amounts you enter · target ₱<?=number_format(SD_DAILY_TARGET,0)?>/day</div>
  </div>
</div>

<!-- Controls -->
<div class="card sd-card sd-controlbar">
  <div class="sd-pill">📍 <?=htmlspecialchars($storeLabel)?></div>
  <select onchange="window.location='?month='+this.value+'&year=<?=$fYear?>'" class="sd-dd">
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$months[$m-1]?></option>
    <?php endfor; ?>
  </select>
  <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value" class="sd-dd">
    <?php for($y=2050;$y>=2020;$y--): ?>
    <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="button" class="sd-btn-saveall sd-save-trigger" onclick="saveAllDays()"><span class="sd-savebtn-label">💾 Save All</span></button>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?>&export_csv=1" class="sd-btn-csv">⬇ Download CSV</a>
  <div class="sd-addchannel">
    <input type="text" id="sdNewChannelInput" class="sd-dd sd-addchannel-input" placeholder="New channel name…" maxlength="60">
    <button type="button" id="sdAddChannelBtn" class="sd-btn-csv" onclick="addChannel()">+ Add Channel</button>
  </div>
</div>

<!-- Legend -->
<div class="sd-legend-row">
  <span><i class="sd-sw sd-sw-input"></i> Editable</span>
  <span><i class="sd-sw sd-sw-target"></i> Reached Target %</span>
  <span><i class="sd-sw sd-sw-pct"></i> % of Daily Sales</span>
  <span><i class="sd-sw sd-sw-totalrow"></i> Month Total</span>
  <span><i class="sd-dot sd-dot-green"></i> Data Entered</span>
  <span><i class="sd-dot sd-dot-amber"></i> No Data Yet</span>
</div>

<!-- Month overview -->
<div class="card sd-card sd-stats">
  <div>
    <div class="sd-kicker">Days Entered</div>
    <div class="sd-statval"><?=$daysWithData?> / <?=$daysInMonth?></div>
  </div>
  <div>
    <div class="sd-kicker">Total Sales for the Month</div>
    <div class="sd-statval">₱<?=number_format($grandTotalSales,2)?></div>
  </div>
  <div>
    <div class="sd-kicker">Reached Target % (Month)</div>
    <div class="sd-statval"><?=number_format($reachedPctSum*100,0)?>%</div>
  </div>
  <div>
    <div class="sd-kicker">Top Channel</div>
    <?php
      $topKey = null; $topVal = -1;
      foreach ($INPUT_KEYS as $k) { if ($colTotal[$k] > $topVal) { $topVal = $colTotal[$k]; $topKey = $k; } }
    ?>
    <div class="sd-statval"><?=$topKey ? htmlspecialchars($INPUT_LABELS[$topKey]) : '—'?></div>
  </div>
</div>

<!-- Table -->
<div class="card sd-card" style="padding:0">
  <div class="sd-tablewrap">
  <table class="sd-tbl" id="sdTbl">
    <thead>
      <tr>
        <th class="sd-h-main sd-h-date">DATE</th>
        <th class="sd-h-main">TOTAL DAILY SALES</th>
        <th class="sd-h-target">REACHED<br>TARGET %</th>
        <?php foreach ($INPUT_KEYS as $k): ?>
        <th class="sd-h-main"><?=htmlspecialchars($INPUT_LABELS[$k])?></th>
        <th class="sd-h-pct">%</th>
        <?php endforeach; ?>
        <th class="sd-h-main sd-h-save">SAVE</th>
      </tr>
    </thead>
    <tbody id="sdTbody">
      <?php foreach ($dates as $date): $m = $matrix[$date]; $dow = date('D', strtotime($date)); $dm = date('j-M', strtotime($date)); ?>
      <tr>
        <td class="sd-datecell">
          <span class="sd-dot <?=$hasAnyData[$date]?'sd-dot-green':'sd-dot-amber'?>" data-role="daydot-<?=$date?>"></span>
          <span class="sd-dm"><?=$dm?></span>
          <span class="sd-dow"><?=$dow?></span>
        </td>
        <td class="sd-calc-total" data-date="<?=$date?>" data-field="total_daily_sales"><?=number_format($m['total_daily_sales'],2)?></td>
        <td class="sd-calc-target" data-date="<?=$date?>" data-field="reached_target_pct"><?=number_format($m['reached_target_pct']*100,0)?>%</td>
        <?php foreach ($INPUT_KEYS as $k): ?>
        <td class="sd-datacell">
          <input class="sdi" type="number" step="0.01" min="0" data-date="<?=$date?>" data-field="<?=$k?>"
                 value="<?=$m[$k]?>" placeholder="0.00" oninput="recalcRow('<?=$date?>')" onfocus="this.select()">
        </td>
        <td class="sd-calc-pct" data-date="<?=$date?>" data-field="<?=$k?>_pct"><?=number_format($m["{$k}_pct"]*100,0)?>%</td>
        <?php endforeach; ?>
        <td class="sd-savecell">
          <button type="button" class="sd-btn-rowsave" onclick="saveDay('<?=$date?>')" title="Save this day">💾</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="sd-total-row">
        <td class="sd-datecell sd-total-label">TOTAL</td>
        <td class="sd-calc-total" id="sdGrandTotal"><?=number_format($grandTotalSales,2)?></td>
        <td class="sd-calc-target sd-total-pct" id="sdGrandReached"><?=number_format($reachedPctSum*100,0)?>%</td>
        <?php foreach ($INPUT_KEYS as $k): ?>
        <td class="sd-calc-total" id="sdColTotal_<?=$k?>"><?=number_format($colTotal[$k],2)?></td>
        <td class="sd-calc-pct sd-total-pct" id="sdColTotalPct_<?=$k?>"><?=number_format($colTotalPct[$k]*100,0)?>%</td>
        <?php endforeach; ?>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<style>
:root {
  --sd-blue-head:  #9bc2e6;
  --sd-blue-head-d:#5f8fc7;
  --sd-yellow:     #ffff00;
  --sd-cream:      #fff2cc;
  --sd-cream-d:    #f5e2a8;
  --sd-green:      #35d16b;
  --sd-green-soft: rgba(53,209,107,.14);
  --sd-amber:      #c8850f;
}

.sd-card { border-color:var(--sd-blue-head-d); }

/* ── Control bar ── */
.sd-controlbar {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  padding:12px 16px; margin-bottom:14px;
  background:linear-gradient(135deg, rgba(155,194,230,.15), transparent 60%);
}
.sd-pill {
  font-family:var(--font-m); font-size:.76rem; font-weight:700;
  padding:7px 14px; border-radius:20px; white-space:nowrap;
  background:rgba(155,194,230,.25); color:#1e4d7b; border:1px solid var(--sd-blue-head-d);
}
.sd-dd {
  padding:7px 12px; font-size:.76rem; font-weight:600;
  background:var(--surface); border:1px solid var(--sd-blue-head-d); color:var(--text);
  border-radius:8px; font-family:var(--font-m);
}
.sd-dd:focus { outline:none; border-color:var(--sd-blue-head-d); box-shadow:0 0 0 3px rgba(155,194,230,.3); }
.sd-btn-saveall {
  font-family:var(--font-m); font-size:.78rem; font-weight:700; padding:8px 16px;
  border-radius:9px; cursor:pointer; border:1px solid var(--sd-blue-head-d);
  background:var(--sd-blue-head-d); color:#fff; margin-left:auto;
}
.sd-btn-saveall:hover { background:#4a7ab0; }
.sd-btn-saveall:disabled { opacity:.6; cursor:default; }
.sd-btn-csv {
  font-family:var(--font-m); font-size:.76rem; font-weight:600; padding:7px 14px;
  border-radius:9px; cursor:pointer; text-decoration:none; white-space:nowrap; display:inline-block;
  background:rgba(155,194,230,.25); color:#1e4d7b; border:1px solid var(--sd-blue-head-d);
}
.sd-btn-csv:hover { background:rgba(155,194,230,.4); }
.sd-addchannel { display:flex; align-items:center; gap:6px; }
.sd-addchannel-input { width:160px; }

/* ── Legend ── */
.sd-legend-row {
  display:flex; align-items:center; gap:16px; flex-wrap:wrap;
  padding:2px 4px 12px; font-family:var(--font-m); font-size:.68rem; color:var(--subtext2);
}
.sd-legend-row span { display:inline-flex; align-items:center; gap:5px; }
.sd-sw { width:10px; height:10px; border-radius:3px; display:inline-block; flex-shrink:0; }
.sd-sw-input  { background:var(--surface); border:1px solid var(--sd-blue-head-d); }
.sd-sw-target { background:var(--sd-yellow); border:1px solid #d4c700; }
.sd-sw-pct    { background:var(--sd-cream); border:1px solid var(--sd-cream-d); }
.sd-sw-totalrow { background:var(--sd-green-soft); border:1px solid var(--sd-green); }
.sd-dot { width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
.sd-dot-green { background:var(--sd-green); }
.sd-dot-amber { background:var(--sd-amber); }

/* ── Month overview cards ── */
.sd-stats {
  display:grid; grid-template-columns:repeat(4,1fr); overflow:hidden;
  padding:0; margin-bottom:14px;
  background:rgba(155,194,230,.08);
}
.sd-stats > div { padding:13px 14px; border-right:1px solid var(--sd-blue-head-d); border-left:3px solid var(--sd-blue-head-d); }
.sd-stats > div:first-child { border-left:none; }
.sd-stats > div:last-child { border-right:none; }
.sd-kicker {
  font-family:var(--font-m); font-size:.56rem; text-transform:uppercase;
  letter-spacing:.06em; color:#1e4d7b; margin-bottom:4px; font-weight:700;
}
.sd-statval { font-size:.98rem; font-weight:700; color:#1e4d7b; }

/* ── Table ── */
.sd-tablewrap { overflow:auto; max-height:72vh; }
.sd-tbl {
  width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.72rem;
  border:1px solid var(--sd-blue-head-d);
}
.sd-tbl th, .sd-tbl td { border-right:1px solid var(--sd-blue-head-d); border-bottom:1px solid var(--sd-blue-head-d); }
.sd-tbl th:last-child, .sd-tbl td:last-child { border-right:none; }

.sd-tbl thead th {
  position:sticky; top:0; z-index:3;
  font-weight:700; padding:8px 6px; text-align:center; white-space:nowrap; color:#173556;
}
.sd-h-main   { background:var(--sd-blue-head); }
.sd-h-date   { min-width:100px; text-align:left !important; padding-left:10px !important; }
.sd-h-target { background:var(--sd-yellow); min-width:78px; line-height:1.25; }
.sd-h-pct    { background:var(--sd-cream); min-width:56px; }
.sd-h-save   { min-width:56px; }

.sd-datecell {
  padding:6px 10px; white-space:nowrap; display:flex; align-items:center; gap:6px;
  font-weight:600;
}
.sd-dm { font-weight:700; }
.sd-dow { font-size:.6rem; color:var(--subtext); letter-spacing:.03em; }

.sd-datacell { padding:3px 4px; text-align:center; }
.sd-calc-total  { padding:6px 8px; text-align:right; font-weight:700; color:#173556; }
.sd-calc-target { padding:6px 8px; text-align:center; font-weight:700; background:rgba(255,255,0,.14); color:#6b6100; }
.sd-calc-pct    { padding:6px 8px; text-align:center; background:var(--sd-cream); color:#7a5d00; }
.sd-savecell { padding:3px 4px; text-align:center; }

.sd-btn-rowsave {
  border:none; background:rgba(95,143,199,.14); color:#1e4d7b; cursor:pointer;
  font-size:.72rem; border-radius:5px; padding:3px 7px; line-height:1.5;
}
.sd-btn-rowsave:hover { background:rgba(95,143,199,.28); }

/* Flat input cells */
.sd-tbl .sdi {
  width:88px; padding:4px 3px; border:1px solid transparent; border-radius:5px;
  background:transparent; color:var(--text); font-family:var(--font-m); font-size:.72rem;
  text-align:right; -moz-appearance:textfield; appearance:textfield;
}
.sd-tbl .sdi::-webkit-outer-spin-button,
.sd-tbl .sdi::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.sd-tbl .sdi:focus { outline:none; background:var(--surface); border-color:var(--sd-blue-head-d); box-shadow:0 0 0 2px rgba(155,194,230,.35); }

/* TOTAL row — pinned to the bottom, green highlight on % cells like the workbook */
.sd-tbl tfoot { position:sticky; bottom:0; z-index:3; }
.sd-total-row td { background:#eef3f9; font-weight:700; border-top:2px solid var(--sd-blue-head-d); }
.sd-total-label { color:#173556; }
.sd-total-row .sd-total-pct { background:var(--sd-green-soft); color:#166534; border:1px solid var(--sd-green); }
</style>

<script>
const DATES = <?=json_encode($dates)?>;
const TARGET = <?=json_encode(SD_DAILY_TARGET)?>;
const FIELDS = <?=json_encode($INPUT_KEYS)?>;

const gv = (date, field) => {
  const el = document.querySelector(`.sdi[data-date="${date}"][data-field="${field}"]`);
  return parseFloat(el?.value) || 0;
};
const fmt2 = n => n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtPct = n => (n*100).toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0}) + '%';

function recalcRow(date) {
  let total = 0;
  FIELDS.forEach(f => total += gv(date, f));

  const totalCell = document.querySelector(`.sd-calc-total[data-date="${date}"][data-field="total_daily_sales"]`);
  if (totalCell) totalCell.textContent = fmt2(total);

  const targetCell = document.querySelector(`.sd-calc-target[data-date="${date}"][data-field="reached_target_pct"]`);
  if (targetCell) targetCell.textContent = fmtPct(TARGET > 0 ? total / TARGET : 0);

  FIELDS.forEach(f => {
    const pctCell = document.querySelector(`.sd-calc-pct[data-date="${date}"][data-field="${f}_pct"]`);
    if (pctCell) pctCell.textContent = fmtPct(total > 0 ? gv(date, f) / total : 0);
  });

  recalcFooter();
}

function recalcFooter() {
  let grandTotal = 0, reachedSum = 0;
  const colTotals = {}; FIELDS.forEach(f => colTotals[f] = 0);

  DATES.forEach(date => {
    let dayTotal = 0;
    FIELDS.forEach(f => { const v = gv(date, f); colTotals[f] += v; dayTotal += v; });
    grandTotal += dayTotal;
    reachedSum += TARGET > 0 ? dayTotal / TARGET : 0;
  });

  const gt = document.getElementById('sdGrandTotal'); if (gt) gt.textContent = fmt2(grandTotal);
  const gr = document.getElementById('sdGrandReached'); if (gr) gr.textContent = fmtPct(reachedSum);
  FIELDS.forEach(f => {
    const ct = document.getElementById('sdColTotal_' + f); if (ct) ct.textContent = fmt2(colTotals[f]);
    const cp = document.getElementById('sdColTotalPct_' + f); if (cp) cp.textContent = fmtPct(grandTotal > 0 ? colTotals[f] / grandTotal : 0);
  });
}

async function addChannel() {
  const input = document.getElementById('sdNewChannelInput');
  const label = (input?.value || '').trim();
  if (!label) { alert('Enter a channel name first.'); return; }

  const btn = document.getElementById('sdAddChannelBtn');
  btn.disabled = true;
  btn.textContent = 'Adding…';

  try {
    const fd = new FormData();
    fd.append('ajax_action', 'add_channel');
    fd.append('channel_label', label);
    const r = await fetch('demic_sales_distribution.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      location.reload();
    } else {
      alert('Error adding channel: ' + d.msg);
      btn.disabled = false;
      btn.textContent = '+ Add Channel';
    }
  } catch (e) {
    alert('Network error adding channel.');
    btn.disabled = false;
    btn.textContent = '+ Add Channel';
  }
}

async function saveDay(date) {
  const fd = new FormData();
  fd.append('ajax_action', 'save_day');
  fd.append('entry_date', date);
  FIELDS.forEach(f => {
    fd.append(f, gv(date, f));
  });
  try {
    const r = await fetch('demic_sales_distribution.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      const dot = document.querySelector(`[data-role="daydot-${date}"]`);
      if (dot) { dot.classList.remove('sd-dot-amber'); dot.classList.add('sd-dot-green'); }
    } else {
      alert('Error saving ' + date + ': ' + d.msg);
    }
    return d;
  } catch (e) { alert('Network error saving ' + date); return {ok:false}; }
}

async function saveAllDays() {
  const btns = document.querySelectorAll('.sd-save-trigger');
  const labels = document.querySelectorAll('.sd-savebtn-label');
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