<?php
// ============================================================
//  h_cogs.php — H Branch COGS (Cost of Goods Sold)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'H') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cogs` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `cogs_date`    date NOT NULL,
    `cogs_year`    int(4) NOT NULL,
    `cogs_month`   tinyint(2) NOT NULL,
    `store_name`   varchar(50) NOT NULL DEFAULT 'H',
    `beg`          decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
    `purc`         decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
    `end_inv`      decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
    `cos`          decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
    `mktg_cost`    decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
    `saved_by`     varchar(100) DEFAULT NULL,
    `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── PURC. comes from Disbursement ("Purchases - Non-Vat" / "Purchases - Vatable" → H Expenses Purchases) ──
$__es = __DIR__ . '/../lib/expense_sync.php';
if (is_file($__es)) {
    require_once $__es;
    try { expSyncCogsPurchases($pdo, 'h_cogs', 'h_expenses', 'H', null, false); } catch (Throwable $e) {}
}

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));

// Clamp month
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

// ── helpers for the 15th / last-day rows ─────────────────────
function cogsSuggest(PDO $pdo, string $date): array {
    $y = (int)date('Y', strtotime($date)); $m = (int)date('n', strtotime($date));
    $ms = sprintf('%04d-%02d-01', $y, $m);
    $prev = $pdo->prepare("SELECT cogs_date, end_inv FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? AND cogs_date < ? ORDER BY cogs_date DESC, id DESC LIMIT 1");
    $prev->execute([$y, $m, $date]);
    $pr = $prev->fetch(PDO::FETCH_ASSOC);
    if ($pr) { $beg = (float)$pr['end_inv']; $lower = $pr['cogs_date']; }
    else {
        $py = $y; $pm = $m - 1; if ($pm < 1) { $pm = 12; $py--; }
        $q = $pdo->prepare("SELECT end_inv FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date DESC, id DESC LIMIT 1");
        $q->execute([$py, $pm]); $v = $q->fetchColumn();
        if ($v === false) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM h_month_end_inv WHERE store_name='H' AND inv_year=? AND inv_month=?");
            $q->execute([$py, $pm]); $v = $q->fetchColumn();
        }
        $beg = (float)$v; $lower = date('Y-m-d', strtotime($ms . ' -1 day'));
    }
    $w = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM h_expenses WHERE expense_date > ? AND expense_date <= ?");
    $w->execute([$lower, $date]); $purc = round((float)$w->fetchColumn(), 2);
    $end = 0.0;
    if ($date === date('Y-m-t', strtotime($ms))) {      // month-end row → suggest the Month End Inventory total
        $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM h_month_end_inv WHERE store_name='H' AND inv_year=? AND inv_month=?");
        $q->execute([$y, $m]); $end = (float)$q->fetchColumn();
    }
    return ['beg' => round($beg, 2), 'purc' => $purc, 'end_inv' => round($end, 2)];
}
function cogsMonthState(PDO $pdo, int $y, int $m): array {
    $q = $pdo->prepare("SELECT id, cogs_date, purc, cos FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date, id");
    $q->execute([$y, $m]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// ── AJAX: suggestion for a new row ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_suggest'])) {
    header('Content-Type: application/json');
    $d = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { echo json_encode(['ok'=>false]); exit; }
    echo json_encode(['ok'=>true] + cogsSuggest($pdo, $d)); exit;
}
// ── AJAX: live state (PURC./COS follow the Disbursement) ─────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_state'])) {
    header('Content-Type: application/json');
    try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'h_cogs', 'h_expenses', 'H', null, false); } catch (Throwable $e) {}
    echo json_encode(['ok'=>true, 'rows'=>cogsMonthState($pdo, $fYear, $fMonth)]); exit;
}

// ── AJAX: Save row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $date  = $_POST['cogs_date'] ?? null;
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid date']);
            exit;
        }
        $beg   = (float)($_POST['beg']       ?? 0);
        $purc  = (float)($_POST['purc']      ?? 0);
        $end   = (float)($_POST['end_inv']   ?? 0);
        $mktg  = (float)($_POST['mktg_cost'] ?? 0);
        $cos   = $beg + $purc - $mktg - $end;   // COS = BEG + PURC - MKTG COST - END

        $yr  = (int)date('Y', strtotime($date));
        $mo  = (int)date('n', strtotime($date));

              $rowId = (int)($_POST['row_id'] ?? 0);
        // one row per date
        $dup = $pdo->prepare("SELECT id FROM h_cogs WHERE store_name='H' AND cogs_date=? AND id<>? LIMIT 1");
        $dup->execute([$date, $rowId]);
        if ($dup->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'A COGS row for this date already exists']); exit; }
        if ($rowId > 0) {
            $pdo->prepare("UPDATE h_cogs SET cogs_date=?, cogs_year=?, cogs_month=?, beg=?, purc=?, end_inv=?, cos=?, mktg_cost=?, saved_by=? WHERE id=? AND store_name='H'")
                ->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$user['name'],$rowId]);
            $outId = $rowId;
        } else {
            $sql = "INSERT INTO h_cogs (cogs_date, cogs_year, cogs_month, store_name, beg, purc, end_inv, cos, mktg_cost, saved_by) VALUES (?,?,?,'H',?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$user['name']]);
            $outId = (int)$pdo->lastInsertId();
        }
        // re-split PURC. between the rows of the month (15th / last day) and refresh COS
        try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'h_cogs', 'h_expenses', 'H', null, false); } catch (Throwable $e) {}
        echo json_encode(['ok'=>true, 'id'=>$outId, 'rows'=>cogsMonthState($pdo, $yr, $mo)]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['row_id'] ?? 0);
        $pdo->prepare("DELETE FROM h_cogs WHERE id=? AND store_name='H'")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Excel (.xlsx) Export ─────────────────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $rows = $pdo->prepare("SELECT * FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date ASC");
    $rows->execute([$fYear,$fMonth]);
    $data = $rows->fetchAll();

    $tot = $pdo->prepare("SELECT COALESCE(SUM(beg),0) beg, COALESCE(SUM(purc),0) purc, COALESCE(SUM(end_inv),0) end_inv, COALESCE(SUM(cos),0) cos, COALESCE(SUM(mktg_cost),0) mktg_cost FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=?");
    $tot->execute([$fYear,$fMonth]);
    $t = $tot->fetch();
    if ($data) { $t['beg'] = (float)$data[0]['beg']; $t['end_inv'] = (float)end($data)['end_inv']; }

    $xlsx = new SimpleXlsxWriter(branchAccentColor('H'));
    $xlsx->title('H Branch — COGS');
    $xlsx->subtitle(date('F Y', mktime(0,0,0,$fMonth,1,$fYear)));
    $xlsx->blank();
    $xlsx->header(['DATE','BEG.','PURC.','END.','COS','MKTG COST']);
    foreach ($data as $r) {
        $xlsx->row([
            date('M d, Y', strtotime($r['cogs_date'])),
            number_format($r['beg'],2,'.',''),
            number_format($r['purc'],2,'.',''),
            number_format($r['end_inv'],2,'.',''),
            number_format($r['cos'],2,'.',''),
            number_format($r['mktg_cost'],2,'.',''),
        ]);
    }
    $xlsx->totalRow(['TOTAL', number_format($t['beg'],2,'.',''), number_format($t['purc'],2,'.',''), number_format($t['end_inv'],2,'.',''), number_format($t['cos'],2,'.',''), number_format($t['mktg_cost'],2,'.','')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([16,12,12,12,12,14]);
    $xlsx->output('h_cogs_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.xlsx');
}

// ── Fetch Month End Inv grand total for selected month ─────
// Sum all total_amount entries from h_month_end_inv for any day in selected month/year
$meiStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS grand_total FROM h_month_end_inv WHERE store_name='H' AND inv_year=? AND inv_month=?");
$meiStmt->execute([$fYear,$fMonth]);
$meiTotal = (float)$meiStmt->fetchColumn();

// ── Materials Purchases (Consumables/CGS) grand total for selected month ─
// Pulled live from the H Expenses ledger's Purchases total — auto-suggests
// this month's PURC. on a new row, same as END. is auto-suggested from MEI.
$purcAutoStmt = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM h_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
$purcAutoStmt->execute([$fYear,$fMonth]);
$purcAuto = (float)$purcAutoStmt->fetchColumn();
$purcUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(purc),0) FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=?");
$purcUsedStmt->execute([$fYear,$fMonth]);
$purcNew = max(0, round($purcAuto - (float)$purcUsedStmt->fetchColumn(), 2));

// ── Previous month's Ending Inventory — auto-suggests this month's BEG. ─
// (mirrors the spreadsheet chain: each month's BEG = prior month's END)
$prevMonth = $fMonth - 1;
$prevYear  = $fYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

// Prefer the actual saved END. from last month's COGS (the true chain source,
// since END is now editable and may differ from Month End Inventory).
$prevCogsStmt = $pdo->prepare("SELECT end_inv FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date DESC, id DESC LIMIT 1");
$prevCogsStmt->execute([$prevYear,$prevMonth]);
$prevCogsEnd = $prevCogsStmt->fetchColumn();

if ($prevCogsEnd !== false) {
    $prevEndInv = (float)$prevCogsEnd;
    $prevEndSource = 'COGS';
} else {
    // Fallback: no COGS entry saved for last month yet — use Month End Inventory
    $prevMeiStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS grand_total FROM h_month_end_inv WHERE store_name='H' AND inv_year=? AND inv_month=?");
    $prevMeiStmt->execute([$prevYear,$prevMonth]);
    $prevEndInv = (float)$prevMeiStmt->fetchColumn();
    $prevEndSource = 'MEI';
}

// ── Load rows for selected month ──────────────────────────
$stmt = $pdo->prepare("SELECT * FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date ASC");
$stmt->execute([$fYear,$fMonth]);
$rows = $stmt->fetchAll();

// ── Monthly totals ────────────────────────────────────────
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(beg),0) beg, COALESCE(SUM(purc),0) purc, COALESCE(SUM(end_inv),0) end_inv, COALESCE(SUM(cos),0) cos, COALESCE(SUM(mktg_cost),0) mktg_cost FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=?");
$totStmt->execute([$fYear,$fMonth]);
$totals = $totStmt->fetch();
// rows chain (BEG of the next row = END of the previous), so BEG = first row's BEG and END = last row's END
if ($rows) { $totals['beg'] = (float)$rows[0]['beg']; $totals['end_inv'] = (float)end($rows)['end_inv']; }

$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle  = 'COGS';
$activePage = 'h_cogs';
include '../layout.php';
?>

<style>
/* ── Header strip ── */
.cogs-header {
  background: linear-gradient(135deg, #1a2e1a 0%, #0f3d2e 100%);
  border-radius: var(--radius);
  padding: 20px 26px 16px;
  margin-bottom: 18px;
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.cogs-header-left .eyebrow {
  font-family: var(--font-m); font-size: .58rem;
  text-transform: uppercase; letter-spacing: .12em;
  color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.cogs-header-left .title {
  font-size: 1.1rem; font-weight: 800;
  color: #fff; letter-spacing: -.02em;
}
.cogs-header-left .subtitle {
  font-family: var(--font-m); font-size: .68rem;
  color: rgba(255,255,255,.5); margin-top: 3px;
}

/* ── KPI cards ── */
.cogs-kpi {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px; margin-bottom: 18px;
}
.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
}
.kpi-label {
  font-family: var(--font-m); font-size: .58rem;
  text-transform: uppercase; letter-spacing: .08em;
  color: var(--subtext); margin-bottom: 5px;
}
.kpi-val {
  font-family: var(--font-m); font-size: 1.15rem;
  font-weight: 800;
}
.kpi-val.green { color: var(--accent); }
.kpi-val.red   { color: var(--accent2); }
.kpi-val.gold  { color: var(--accent3); }
.kpi-val.blue  { color: #3b82f6; }
.kpi-val.purple{ color: #8b5cf6; }

/* ── Controls ── */
.cogs-controls {
  display: flex; gap: 10px; align-items: center;
  flex-wrap: wrap; margin-bottom: 16px;
}

/* ── Table ── */
.cogs-tbl { width: 100%; border-collapse: collapse; }
.cogs-tbl thead th {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .58rem;
  font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; padding: 10px 12px;
  border: 1px solid #2d5480; text-align: center;
  white-space: nowrap;
}
.cogs-tbl thead th.col-date  { min-width: 140px; text-align: left; }
.cogs-tbl thead th.col-num   { min-width: 130px; }
.cogs-tbl thead th.col-cos   { background: #1a4a2e; }
.cogs-tbl thead th.col-act   { min-width: 80px; background: #374151; }

.cogs-tbl tbody tr { transition: background .1s; }
.cogs-tbl tbody tr:hover { background: #f8fafb; }
.cogs-tbl tbody td {
  border: 1px solid var(--border);
  padding: 0; vertical-align: middle;
}
.cogs-tbl tbody td.td-date { padding: 6px 10px; }

.cogs-input {
  width: 100%; padding: 8px 10px;
  text-align: right;
  font-family: var(--font-m); font-size: .78rem;
  color: var(--text); background: transparent;
  border: none; outline: none;
  transition: background .15s, box-shadow .15s;
}
.cogs-input:focus {
  background: #fff;
  box-shadow: inset 0 0 0 2px var(--accent);
}
.cogs-input.cos-field {
  background: #f0fdf4; color: var(--accent);
  font-weight: 700; cursor: default;
}
.cogs-input.mktg-field { background: #fffbeb; color: var(--accent3); font-weight: 600; }

/* Date input */
.date-input {
  width: 100%; padding: 7px 10px;
  font-family: var(--font-m); font-size: .76rem;
  border: none; outline: none; background: transparent;
  color: var(--text);
}
.date-input:focus { background: #f8f9fb; }

/* Totals row */
.cogs-tbl tfoot td {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .72rem;
  font-weight: 800; padding: 10px 12px;
  border: 1px solid #2d5480; text-align: right;
  white-space: nowrap;
}
.cogs-tbl tfoot td.lbl { text-align: left; text-transform: uppercase; letter-spacing: .06em; }
.cogs-tbl tfoot td.cos-tot { background: #1a4a2e; color: #6ee7b7; font-size: .82rem; }
.cogs-tbl tfoot td.mktg-tot { background: #78350f; color: #fde68a; }

/* Save / delete btns */
.btn-save-row {
  padding: 5px 12px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 6px;
  font-family: var(--font-m); font-size: .65rem;
  font-weight: 600; cursor: pointer;
  transition: background .15s;
}
.btn-save-row:hover { background: #0a6649; }
.btn-del-row {
  padding: 5px 8px;
  background: transparent; color: var(--accent2);
  border: 1px solid rgba(220,53,69,.2);
  border-radius: 6px; font-size: .65rem;
  cursor: pointer; transition: background .15s;
}
.btn-del-row:hover { background: rgba(220,53,69,.07); }
.row-status {
  font-family: var(--font-m); font-size: .62rem;
  color: var(--accent); display: none; margin-top: 3px;
}

@media (max-width: 700px) {
  .cogs-kpi { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- Header -->
<div class="cogs-header">
  <div class="cogs-header-left">
    <div class="eyebrow">H Branch · COGS</div>
    <div class="title">Cost of Goods Sold</div>
    <div class="subtitle">BEG auto-suggests from prior month's END · END auto-suggests from Month End Inventory (editable) · COS = BEG + PURC − MKTG COST − END · rows on the 15th and the last day · auto-saves</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 12px;border-radius:20px;
                 font-family:var(--font-m);font-size:.65rem;font-weight:600">
      📌 H
    </span>
  </div>
</div>

<!-- KPI Strip -->
<div class="cogs-kpi">
  <div class="kpi-card" style="border-top:2px solid #3b82f6">
    <div class="kpi-label">Beg. Inventory</div>
    <div class="kpi-val blue" id="kpi_beg"><?= number_format($totals['beg'],2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid #8b5cf6">
    <div class="kpi-label">Purchases</div>
    <div class="kpi-val purple" id="kpi_purc"><?= number_format($rows ? $totals['purc'] : $purcAuto,2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid var(--accent2)">
    <div class="kpi-label">End. Inventory</div>
    <div class="kpi-val red" id="kpi_end"><?= number_format($totals['end_inv'],2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid var(--accent)">
    <div class="kpi-label">COS (Total)</div>
    <div class="kpi-val green" id="kpi_cos"><?= number_format($totals['cos'],2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid var(--accent3)">
    <div class="kpi-label">Marketing Cost</div>
    <div class="kpi-val gold" id="kpi_mktg"><?= number_format($totals['mktg_cost'],2) ?></div>
  </div>
</div>

<!-- Controls -->
<div class="cogs-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" id="filterForm">
    <select name="month" class="form-control" style="max-width:140px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
  </form>
  <button class="btn btn-primary" onclick="addRow('15')" title="Row for the 15th of this month">+ 15th</button>
  <button class="btn btn-primary" onclick="addRow('last')" title="Row for the last day of this month">+ Last Day</button>
  <a href="h_cogs.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download Excel</a>
</div>

<!-- Column Totals Banner -->
<div class="card" style="padding:0;margin-bottom:14px;overflow:hidden">
  <div style="background:#0f3d2e;padding:7px 16px;font-family:var(--font-m);font-size:.58rem;
              text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.5)">
    COLUMN TOTALS — <?= strtoupper($months[$fMonth-1]) ?> <?= $fYear ?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0">
    <?php
    $totCols = [
      ['BEG.',        'beg',       '#3b82f6'],
      ['PURC.',       'purc',      '#8b5cf6'],
      ['END.',        'end_inv',   '#dc3545'],
      ['COS',         'cos',       '#0f7b5c'],
      ['MKTG COST',   'mktg_cost', '#d97706'],
    ];
    foreach($totCols as [$lbl,$key,$color]):
      $val = $totals[$key] ?? 0;
    ?>
    <div style="padding:12px 16px;border-right:1px solid var(--border)">
      <div style="font-family:var(--font-m);font-size:.55rem;text-transform:uppercase;
                  letter-spacing:.08em;color:var(--subtext);margin-bottom:4px"><?= $lbl ?></div>
      <div style="font-family:var(--font-m);font-size:1.05rem;font-weight:800;
                  color:<?= $color ?>">
        <?= (float)$val == 0 ? '<span style="color:var(--muted)">—</span>' : number_format((float)$val,2) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MEI Auto-fill Notice -->
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 16px;
            margin-bottom:12px;display:flex;align-items:center;gap:10px;
            font-family:var(--font-m);font-size:.72rem;color:#92400e">
  <span style="font-size:1rem">📦</span>
  <span>
    <strong>END. Inventory</strong> is auto-suggested from the
    <strong>Month End Inventory grand total</strong> for
    <?= $months[$fMonth-1] . ' ' . $fYear ?>:
    <strong style="color:#b45309"><?= number_format($meiTotal,2) ?></strong>
    <?php if ($meiTotal == 0): ?>
      <span style="color:#dc3545"> — No inventory entries found for this month yet.</span>
    <?php endif; ?>
    — you can still edit it.
  </span>
</div>

<!-- BEG Auto-suggest Notice -->
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 16px;
            margin-bottom:12px;display:flex;align-items:center;gap:10px;
            font-family:var(--font-m);font-size:.72rem;color:#1e40af">
  <span style="font-size:1rem">🔗</span>
  <span>
    <strong>BEG. Inventory</strong> for a new row is auto-suggested from
    <strong><?= $months[$prevMonth-1] . ' ' . $prevYear ?></strong>'s Ending Inventory
    <?= $prevEndSource === 'COGS' ? '(as saved in COGS)' : '(from Month End Inventory)' ?>:
    <strong style="color:#1d4ed8"><?= number_format($prevEndInv,2) ?></strong>
    <?php if ($prevEndInv == 0): ?>
      <span style="color:#dc3545"> — No prior month inventory found.</span>
    <?php endif; ?>
    — you can still edit it.
  </span>
</div>

<!-- PURC Auto-suggest Notice -->
<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:10px 16px;
            margin-bottom:12px;display:flex;align-items:center;gap:10px;
            font-family:var(--font-m);font-size:.72rem;color:#5b21b6">
  <span style="font-size:1rem">🧾</span>
  <span>
    <strong>PURC.</strong> for a new row is auto-filled from the
    <strong>H Expenses ledger's Purchases total</strong> for
    <?= $months[$fMonth-1] . ' ' . $fYear ?>:
    <strong style="color:#6d28d9"><?= number_format($purcAuto,2) ?></strong>
    <?php if ($purcAuto == 0): ?>
      <span style="color:#dc3545"> — No Purchases entries found for this month yet.</span>
    <?php endif; ?>
    — you can still edit it.
  </span>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow-x:auto">
  <table class="cogs-tbl" id="cogsTable">
    <thead>
      <tr>
        <th class="col-date">Date</th>
        <th class="col-num">BEG.</th>
        <th class="col-num" title="Auto-suggested from the H Expenses ledger's Purchases total total — editable">PURC. <span style="font-size:.5rem;background:rgba(139,92,246,.25);padding:1px 5px;border-radius:8px;color:#c4b5fd">SUGGESTED</span></th>
        <th class="col-num" title="Auto-suggested from Month End Inventory — editable">END. <span style="font-size:.5rem;background:rgba(255,200,0,.2);padding:1px 5px;border-radius:8px;color:#fbbf24">SUGGESTED</span></th>
        <th class="col-num col-cos">COS</th>
        <th class="col-num" style="background:#78350f">MKTG COST</th>
        <th class="col-act">Action</th>
      </tr>
    </thead>
    <tbody id="cogsBody">
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
        <tr data-id="<?= $r['id'] ?>">
          <td class="td-date">
            <input type="date" class="date-input" value="<?= htmlspecialchars($r['cogs_date']) ?>"
                   onchange="changed(this)">
          </td>
          <td><input type="number" step="0.01" class="cogs-input" value="<?= $r['beg'] ?>"
                     placeholder="0.00" oninput="changed(this)"></td>
          <td title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"><input type="number" step="0.01" class="cogs-input purc-auto-field" value="<?= $r['purc'] ?>"
                     placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9;font-weight:700"></td>
          <td title="Auto-suggested from Month End Inventory grand total (<?= number_format($meiTotal,2) ?>) — editable">
            <input type="number" step="0.01" class="cogs-input end-auto-field"
                   value="<?= $r['end_inv'] ?>"
                   oninput="changed(this)"
                   style="background:#fff8e1;color:#b45309;font-weight:700">
          </td>
          <td><input type="number" step="0.01" class="cogs-input cos-field" value="<?= $r['cos'] ?>"
                     readonly tabindex="-1"></td>
          <td><input type="number" step="0.01" class="cogs-input mktg-field" value="<?= $r['mktg_cost'] ?>"
                     placeholder="0.00" oninput="changed(this)"></td>
          <td style="padding:6px 8px;text-align:center">
            <div style="display:flex;flex-direction:column;gap:3px;align-items:center">
              <button class="btn-del-row"  onclick="deleteRow(this)">✕</button>
              <div class="row-status" style="display:block">✓ Saved</div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
      <tr id="emptyRow">
        <td colspan="7" style="text-align:center;padding:48px;color:var(--muted);
                                font-family:var(--font-m);font-size:.78rem">
          No COGS entries for <?= $months[$fMonth-1] ?> <?= $fYear ?> — click <strong>+ 15th</strong> or <strong>+ Last Day</strong>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="lbl">TOTAL</td>
        <td id="foot_beg"><?= number_format($totals['beg'],2) ?></td>
        <td id="foot_purc"><?= number_format($totals['purc'],2) ?></td>
        <td id="foot_end"><?= number_format($totals['end_inv'],2) ?></td>
        <td class="cos-tot" id="foot_cos"><?= number_format($totals['cos'],2) ?></td>
        <td class="mktg-tot" id="foot_mktg"><?= number_format($totals['mktg_cost'],2) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <button class="btn btn-primary" onclick="addRow('15')">+ 15th</button>
  <button class="btn btn-primary" onclick="addRow('last')">+ Last Day</button>
  <span style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext)">Changes save automatically · PURC. follows the Disbursement (15th row = purchases up to the 15th, last-day row = the rest)</span>
</div>

  </div></div>

<script>
const CURR_MONTH = <?= $fMonth ?>;
const CURR_YEAR  = <?= $fYear ?>;
// Grand total from Month End Inventory for this month — auto-fills END. column
const MEI_TOTAL  = <?= $meiTotal ?>;
// Previous month's Ending Inventory — auto-suggests BEG. on a new row
const PREV_END_INV = <?= $prevEndInv ?>;
// This month's Purchases total (from H Expenses ledger's Purchases total) — auto-suggests PURC. on a new row
const PURC_AUTO = <?= $purcNew ?>;
const PURC_LEDGER = <?= $purcAuto ?>;

const pad2 = n => String(n).padStart(2,'0');
function dateFor(kind) {
  if (kind === '15') return `${CURR_YEAR}-${pad2(CURR_MONTH)}-15`;
  const last = new Date(CURR_YEAR, CURR_MONTH, 0).getDate();
  return `${CURR_YEAR}-${pad2(CURR_MONTH)}-${pad2(last)}`;
}

const ROW_HTML = (d) => `
    <td class="td-date">
      <input type="date" class="date-input" value="${d}" onchange="dateChanged(this)">
    </td>
    <td><input type="number" step="0.01" class="cogs-input beg-auto-field" value="" placeholder="0.00" oninput="changed(this)" style="background:#eff6ff;color:#1d4ed8;font-weight:700" title="Auto: previous row's END (or last month's END)"></td>
    <td><input type="number" step="0.01" class="cogs-input purc-auto-field" value="" placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9;font-weight:700" title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"></td>
    <td><input type="number" step="0.01" class="cogs-input end-auto-field" value="" placeholder="0.00" oninput="changed(this)" style="background:#fff8e1;color:#b45309;font-weight:700"></td>
    <td><input type="number" step="0.01" class="cogs-input cos-field" value="0.00" readonly tabindex="-1"></td>
    <td><input type="number" step="0.01" class="cogs-input mktg-field" value="" placeholder="0.00" oninput="changed(this)"></td>
    <td style="padding:6px 8px;text-align:center">
      <div style="display:flex;flex-direction:column;gap:3px;align-items:center">
        <button class="btn-del-row" onclick="deleteRow(this)">✕</button>
        <div class="row-status" style="display:block;color:var(--subtext)">not saved yet</div>
      </div>
    </td>`;

async function fillSuggestion(tr) {
  const d = tr.querySelector('.date-input').value;
  if (!d) return;
  try {
    const j = await (await fetch('h_cogs.php?ajax_suggest=1&date=' + encodeURIComponent(d))).json();
    if (!j.ok) return;
    const ins = tr.querySelectorAll('.cogs-input');
    ins[0].value = j.beg.toFixed(2); ins[1].value = j.purc.toFixed(2);
    ins[2].value = j.end_inv ? j.end_inv.toFixed(2) : '';
    calcCos(ins[0]);
    if (j.end_inv > 0) changed(ins[2]);          // month-end row with an inventory total → save straight away
  } catch (e) {}
}

function addRow(kind) {
  const empty = document.getElementById('emptyRow');
  if (empty) empty.remove();
  let d = kind ? dateFor(kind) : null;
  if (!d) {
    const has15 = [...document.querySelectorAll('#cogsBody .date-input')].some(i => i.value === dateFor('15'));
    d = dateFor(has15 ? 'last' : '15');
  }
  if ([...document.querySelectorAll('#cogsBody .date-input')].some(i => i.value === d)) {
    alert('There is already a COGS row for ' + d + '.'); return;
  }
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = ROW_HTML(d);
  document.getElementById('cogsBody').appendChild(tr);
  fillSuggestion(tr);
  tr.querySelector('.end-auto-field').focus();
}

function gv(input) { return parseFloat(input.value) || 0; }

// COS = BEG + PURC - MKTG COST - END
function calcCos(el) {
  const tr = el.closest('tr'), i = tr.querySelectorAll('.cogs-input');
  i[3].value = (gv(i[0]) + gv(i[1]) - gv(i[4]) - gv(i[2])).toFixed(2);
  refreshFooter();
}

// every edit → recalc + auto-save (debounced)
function changed(el) {
  const tr = el.closest('tr');
  calcCos(el);
  setStatus(tr, 'typing…', 'var(--subtext)');
  clearTimeout(tr._t);
  tr._t = setTimeout(() => { tr._t = null; saveRow(tr); }, 600);
}
async function dateChanged(el) {
  const tr = el.closest('tr');
  if ((parseInt(tr.dataset.id) || 0) === 0) { await fillSuggestion(tr); }
  changed(el);
}
function setStatus(tr, txt, color) {
  const s = tr.querySelector('.row-status'); if (!s) return;
  s.textContent = txt; s.style.color = color; s.style.display = 'block';
}

function getRowData(tr) {
  const ins = tr.querySelectorAll('.cogs-input');
  return {
    cogs_date: tr.querySelector('.date-input').value,
    beg: gv(ins[0]), purc: gv(ins[1]), end_inv: gv(ins[2]), cos: gv(ins[3]), mktg_cost: gv(ins[4]),
  };
}

// apply the server's PURC./COS split to every row of the month
function applyState(rows) {
  (rows || []).forEach(r => {
    const tr = document.querySelector(`#cogsBody tr[data-id="${r.id}"]`);
    if (!tr) return;
    const ins = tr.querySelectorAll('.cogs-input');
    ins[1].value = (+r.purc).toFixed(2);
    ins[3].value = (+r.cos).toFixed(2);
  });
  refreshFooter(); updateKPIs();
}

async function saveRow(tr) {
  if (tr._deleted) return;
  const data = getRowData(tr);
  if (!data.cogs_date) { setStatus(tr, 'set a date', 'var(--accent2)'); return; }
  if (tr._saving) { tr._again = true; return; }        // never insert the same new row twice
  tr._saving = true;
  setStatus(tr, 'saving…', 'var(--subtext)');
  const fd = new FormData();
  fd.append('ajax_save','1');
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  const existingId = parseInt(tr.dataset.id) || 0;
  if (existingId > 0) fd.append('row_id', existingId);
  try {
    const json = await (await fetch('h_cogs.php', {method:'POST', body:fd})).json();
    if (json.ok) {
      if (json.id) tr.dataset.id = json.id;
      if (tr._deleted && json.id) { const f = new FormData(); f.append('ajax_delete','1'); f.append('row_id', json.id); fetch('h_cogs.php', {method:'POST', body:f}); return; }
      setStatus(tr, '✓ Saved', 'var(--accent)');
      applyState(json.rows);
    } else setStatus(tr, '❌ ' + json.msg, 'var(--accent2)');
  } catch(e) { setStatus(tr, '❌ Network error', 'var(--accent2)'); }
  tr._saving = false;
  if (tr._again) { tr._again = false; saveRow(tr); }
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id) || 0;

  // two-click confirmation inside the page (browser pop-ups can be blocked, which made Delete look dead)
  if (id > 0 && btn.dataset.sure !== '1') {
    btn.dataset.sure = '1'; btn.dataset.old = btn.textContent; btn.textContent = 'Delete?';
    btn.style.background = '#dc3545'; btn.style.color = '#fff';
    setTimeout(() => { if (btn.dataset.sure === '1') { btn.dataset.sure = ''; btn.textContent = btn.dataset.old || '✕'; btn.style.background = ''; btn.style.color = ''; } }, 3500);
    return;
  }

  // stop any pending / queued auto-save of this row so it cannot bring the row back
  clearTimeout(tr._t); tr._t = null; tr._again = false; tr._deleted = true;
  btn.disabled = true;

  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete','1');
    fd.append('row_id', id);
    try {
      const j = await (await fetch('h_cogs.php', {method:'POST', body:fd})).json();
      if (!j.ok) { tr._deleted = false; btn.disabled = false; setStatus(tr, '❌ ' + (j.msg || 'not deleted'), 'var(--accent2)'); return; }
    } catch(e) { tr._deleted = false; btn.disabled = false; setStatus(tr, '❌ not deleted (network)', 'var(--accent2)'); return; }
  }

  tr.remove();
  if (!document.querySelector('#cogsBody tr')) {
    const tbody = document.getElementById('cogsBody');
    tbody.innerHTML = `<tr id="emptyRow"><td colspan="7" style="text-align:center;padding:48px;color:var(--muted);font-family:var(--font-m);font-size:.78rem">No COGS entries — click <strong>+ 15th</strong> or <strong>+ Last Day</strong></td></tr>`;
  }
  refreshFooter();
  updateKPIs();
}

function totalsNow() {
  let purc=0, cos=0, mktg=0, first=null, last=null;
  document.querySelectorAll('#cogsBody tr[data-id]').forEach(tr => {
    const ins = tr.querySelectorAll('.cogs-input');
    const d = tr.querySelector('.date-input').value || '';
    purc += parseFloat(ins[1]?.value)||0; cos += parseFloat(ins[3]?.value)||0; mktg += parseFloat(ins[4]?.value)||0;
    if (!first || d < first.d) first = {d, v: parseFloat(ins[0]?.value)||0};
    if (!last  || d >= last.d) last  = {d, v: parseFloat(ins[2]?.value)||0};
  });
  // rows chain, so BEG = first row's BEG and END = last row's END
  return {beg: first ? first.v : 0, purc, end: last ? last.v : 0, cos, mktg};
}
const fmtC = n => n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
function refreshFooter() {
  const t = totalsNow();
  document.getElementById('foot_beg').textContent  = fmtC(t.beg);
  document.getElementById('foot_purc').textContent = fmtC(t.purc);
  document.getElementById('foot_end').textContent  = fmtC(t.end);
  document.getElementById('foot_cos').textContent  = fmtC(t.cos);
  document.getElementById('foot_mktg').textContent = fmtC(t.mktg);
}

// Recalculate COS for all existing rows using the correct formula
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#cogsBody tr[data-id]').forEach(tr => {
    const inputs = tr.querySelectorAll('.cogs-input');
    const beg  = parseFloat(inputs[0]?.value) || 0;
    const purc = parseFloat(inputs[1]?.value) || 0;
    const end  = parseFloat(inputs[2]?.value) || 0; // saved/edited END value
    const mktg = parseFloat(inputs[4]?.value) || 0;
    if (inputs[3]) inputs[3].value = (beg + purc - end - mktg).toFixed(2);
  });
  refreshFooter();
  updateKPIs();
});

function updateKPIs() {
  const t = totalsNow(), has = !!document.querySelector('#cogsBody tr[data-id]');
  document.getElementById('kpi_beg').textContent  = fmtC(t.beg);
  document.getElementById('kpi_purc').textContent = fmtC(has ? t.purc : PURC_LEDGER);
  document.getElementById('kpi_end').textContent  = fmtC(t.end);
  document.getElementById('kpi_cos').textContent  = fmtC(t.cos);
  document.getElementById('kpi_mktg').textContent = fmtC(t.mktg);
}

// PURC. / COS follow the Disbursement live
setInterval(async function () {
  if (document.hidden || [...document.querySelectorAll('#cogsBody tr')].some(tr => tr._saving || tr._t)) return;
  try {
    const j = await (await fetch(`h_cogs.php?ajax_state=1&year=${CURR_YEAR}&month=${CURR_MONTH}`)).json();
    if (j.ok) applyState(j.rows);
  } catch (e) {}
}, 5000);
</script>
</body>
</html>