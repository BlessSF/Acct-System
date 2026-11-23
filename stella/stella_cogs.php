<?php
// ============================================================
//  stella_cogs.php — Stella Branch COGS (Cost of Goods Sold)
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_cogs` (
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `cogs_date`    date NOT NULL,
    `cogs_year`    int(4) NOT NULL,
    `cogs_month`   tinyint(2) NOT NULL,
    `store_name`   varchar(50) NOT NULL DEFAULT 'Stella',
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

// ── Self-repair: a table copied from a database export can lose its PRIMARY KEY / AUTO_INCREMENT on `id`.
//    Then every new row gets id 0, "Delete" removes nothing and edits hit every row at once.
try {
    $col = $pdo->query("SHOW COLUMNS FROM `stella_cogs` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && stripos((string)$col['Extra'], 'auto_increment') === false) {
        $bad = (int)$pdo->query("SELECT COUNT(*) FROM `stella_cogs` WHERE id = 0 OR id IN (SELECT id FROM (SELECT id FROM `stella_cogs` GROUP BY id HAVING COUNT(*) > 1) d)")->fetchColumn();
        if ($bad > 0 || $col['Key'] !== 'PRI') {
            $pdo->exec("SET @cogs_i := 0");
            $pdo->exec("UPDATE `stella_cogs` SET id = (@cogs_i := @cogs_i + 1) ORDER BY cogs_date, updated_at");
        }
        if ($col['Key'] === 'PRI') $pdo->exec("ALTER TABLE `stella_cogs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
        else                       $pdo->exec("ALTER TABLE `stella_cogs` ADD PRIMARY KEY (`id`), MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
    }
} catch (Throwable $e) {}

// ── PURC. comes from Disbursement ("Purchases - Non-Vat" / "Purchases - Vatable" → Stella Expenses Purchases) ──
$__es = __DIR__ . '/../lib/expense_sync.php';
if (is_file($__es)) {
    require_once $__es;
    try { expSyncCogsPurchases($pdo, 'stella_cogs', 'stella_expenses', 'Stella', null, false); } catch (Throwable $e) {}
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
    $prev = $pdo->prepare("SELECT cogs_date, end_inv FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? AND cogs_date < ? ORDER BY cogs_date DESC, id DESC LIMIT 1");
    $prev->execute([$y, $m, $date]);
    $pr = $prev->fetch(PDO::FETCH_ASSOC);
    if ($pr) { $beg = (float)$pr['end_inv']; $lower = $pr['cogs_date']; }
    else {
        $py = $y; $pm = $m - 1; if ($pm < 1) { $pm = 12; $py--; }
        $q = $pdo->prepare("SELECT end_inv FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date DESC, id DESC LIMIT 1");
        $q->execute([$py, $pm]); $v = $q->fetchColumn();
        if ($v === false) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=?");
            $q->execute([$py, $pm]); $v = $q->fetchColumn();
        }
        $beg = (float)$v; $lower = date('Y-m-d', strtotime($ms . ' -1 day'));
    }
    $w = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM stella_expenses WHERE expense_date > ? AND expense_date <= ?");
    $w->execute([$lower, $date]); $purc = round((float)$w->fetchColumn(), 2);
    $end = 0.0;
    if ($date === date('Y-m-t', strtotime($ms))) {      // month-end row → suggest the Month End Inventory total
        $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=?");
        $q->execute([$y, $m]); $end = (float)$q->fetchColumn();
    }
    return ['beg' => round($beg, 2), 'purc' => $purc, 'end_inv' => round($end, 2)];
}
// One BEG. and one END. per month: older rows (15th / last day / accidental duplicates) are folded into one month row.
function cogsMergeMonth(PDO $pdo, int $y, int $m): void {
    $q = $pdo->prepare("SELECT * FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date, id");
    $q->execute([$y, $m]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 2) return;
    $byDate = [];                                   // last saved row of each date wins
    foreach ($rows as $r) $byDate[$r['cogs_date']] = $r;
    $list = array_values($byDate);
    $first = $list[0]; $last = $list[count($list) - 1];
    $mktg = 0.0; foreach ($list as $r) $mktg += (float)$r['mktg_cost'];
    $monthEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
    $pdo->beginTransaction();
    try {
        $keep = (int)$last['id'];
        $pdo->prepare("UPDATE stella_cogs SET cogs_date=?, beg=?, end_inv=?, mktg_cost=? WHERE id=?")
            ->execute([$monthEnd, (float)$first['beg'], (float)$last['end_inv'], round($mktg, 2), $keep]);
        $pdo->prepare("DELETE FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? AND id<>?")->execute([$y, $m, $keep]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
}
function cogsMonthState(PDO $pdo, int $y, int $m): array {
    $q = $pdo->prepare("SELECT id, cogs_date, purc, cos FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date, id");
    $q->execute([$y, $m]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

try { cogsMergeMonth($pdo, $fYear, $fMonth); } catch (Throwable $e) {}
try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'stella_cogs', 'stella_expenses', 'Stella', null, false); } catch (Throwable $e) {}

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
    try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'stella_cogs', 'stella_expenses', 'Stella', null, false); } catch (Throwable $e) {}
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

        $date = date('Y-m-t', strtotime($date));            // one row per month, dated the last day
        $yr  = (int)date('Y', strtotime($date));
        $mo  = (int)date('n', strtotime($date));

        $rowId = (int)($_POST['row_id'] ?? 0);
        // only ONE row per month: a "new" row for a month that already has one updates that row instead
        $ex = $pdo->prepare("SELECT id FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY id DESC LIMIT 1");
        $ex->execute([$yr, $mo]);
        $existing = (int)$ex->fetchColumn();
        if ($existing > 0) $rowId = $existing;
        if ($rowId > 0) {
            $pdo->prepare("UPDATE stella_cogs SET cogs_date=?, cogs_year=?, cogs_month=?, beg=?, purc=?, end_inv=?, cos=?, mktg_cost=?, saved_by=? WHERE id=? AND store_name='Stella'")
                ->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$user['name'],$rowId]);
            $outId = $rowId;
        } else {
            $sql = "INSERT INTO stella_cogs (cogs_date, cogs_year, cogs_month, store_name, beg, purc, end_inv, cos, mktg_cost, saved_by) VALUES (?,?,?,'Stella',?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$user['name']]);
            $outId = (int)$pdo->lastInsertId();
        }
        // re-split PURC. between the rows of the month (15th / last day) and refresh COS
        try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'stella_cogs', 'stella_expenses', 'Stella', null, false); } catch (Throwable $e) {}
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
        $pdo->prepare("DELETE FROM stella_cogs WHERE id=? AND store_name='Stella'")->execute([$id]);
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

    $rows = $pdo->prepare("SELECT * FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date ASC");
    $rows->execute([$fYear,$fMonth]);
    $data = $rows->fetchAll();

    $tot = $pdo->prepare("SELECT COALESCE(SUM(beg),0) beg, COALESCE(SUM(purc),0) purc, COALESCE(SUM(end_inv),0) end_inv, COALESCE(SUM(cos),0) cos, COALESCE(SUM(mktg_cost),0) mktg_cost FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=?");
    $tot->execute([$fYear,$fMonth]);
    $t = $tot->fetch();
    if ($data) { $t['beg'] = (float)$data[0]['beg']; $t['end_inv'] = (float)end($data)['end_inv']; }

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('Stella Branch — COGS');
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
    $xlsx->output('stella_cogs_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.xlsx');
}

// ── Fetch Month End Inv grand total for selected month ─────
// Sum all total_amount entries from stella_month_end_inv for any day in selected month/year
$meiStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS grand_total FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=?");
$meiStmt->execute([$fYear,$fMonth]);
$meiTotal = (float)$meiStmt->fetchColumn();

// ── Materials Purchases (Consumables/CGS) grand total for selected month ─
// Pulled live from the Stella Expenses ledger's Purchases total — auto-suggests
// this month's PURC. on a new row, same as END. is auto-suggested from MEI.
$purcAutoStmt = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM stella_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
$purcAutoStmt->execute([$fYear,$fMonth]);
$purcAuto = (float)$purcAutoStmt->fetchColumn();
$purcUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(purc),0) FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=?");
$purcUsedStmt->execute([$fYear,$fMonth]);
$purcNew = max(0, round($purcAuto - (float)$purcUsedStmt->fetchColumn(), 2));

// ── Previous month's Ending Inventory — auto-suggests this month's BEG. ─
// (mirrors the spreadsheet chain: each month's BEG = prior month's END)
$prevMonth = $fMonth - 1;
$prevYear  = $fYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

// Prefer the actual saved END. from last month's COGS (the true chain source,
// since END is now editable and may differ from Month End Inventory).
$prevCogsStmt = $pdo->prepare("SELECT end_inv FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date DESC, id DESC LIMIT 1");
$prevCogsStmt->execute([$prevYear,$prevMonth]);
$prevCogsEnd = $prevCogsStmt->fetchColumn();

if ($prevCogsEnd !== false) {
    $prevEndInv = (float)$prevCogsEnd;
    $prevEndSource = 'COGS';
} else {
    // Fallback: no COGS entry saved for last month yet — use Month End Inventory
    $prevMeiStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS grand_total FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=?");
    $prevMeiStmt->execute([$prevYear,$prevMonth]);
    $prevEndInv = (float)$prevMeiStmt->fetchColumn();
    $prevEndSource = 'MEI';
}

// ── Load rows for selected month ──────────────────────────
$stmt = $pdo->prepare("SELECT * FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date ASC");
$stmt->execute([$fYear,$fMonth]);
$rows = $stmt->fetchAll();

// ── Monthly totals ────────────────────────────────────────
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(beg),0) beg, COALESCE(SUM(purc),0) purc, COALESCE(SUM(end_inv),0) end_inv, COALESCE(SUM(cos),0) cos, COALESCE(SUM(mktg_cost),0) mktg_cost FROM stella_cogs WHERE store_name='Stella' AND cogs_year=? AND cogs_month=?");
$totStmt->execute([$fYear,$fMonth]);
$totals = $totStmt->fetch();
// rows chain (BEG of the next row = END of the previous), so BEG = first row's BEG and END = last row's END
if ($rows) { $totals['beg'] = (float)$rows[0]['beg']; $totals['end_inv'] = (float)end($rows)['end_inv']; }

$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$pageTitle  = 'COGS';
$activePage = 'stella_cogs';
$bodyClass = 'sp-stella_cogs';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<!-- Header -->
<div class="cogs-header">
  <div class="cogs-header-left">
    <div class="eyebrow">Stella Branch · COGS</div>
    <div class="title">Cost of Goods Sold</div>
    <div class="subtitle">BEG auto-suggests from prior month's END · END auto-suggests from Month End Inventory (editable) · COS = BEG + PURC − MKTG COST − END · one BEG. and one END. per month · auto-saves</div>
  </div>
  <div class="sx-21" style="display:flex">
    <span class="sx-27" style="background:rgba(255,255,255,.1);color:#fff">
      📌 Stella
    </span>
  </div>
</div>

<!-- KPI Strip -->
<div class="cogs-kpi">
  <div class="sx-26 kpi-card" >
    <div class="kpi-label">Beg. Inventory</div>
    <div class="kpi-val blue" id="kpi_beg"><?= number_format($totals['beg'],2) ?></div>
  </div>
  <div class="sx-25 kpi-card" >
    <div class="kpi-label">Purchases</div>
    <div class="kpi-val purple" id="kpi_purc"><?= number_format($rows ? $totals['purc'] : $purcAuto,2) ?></div>
  </div>
  <div class="sx-24 kpi-card" >
    <div class="kpi-label">End. Inventory</div>
    <div class="kpi-val red" id="kpi_end"><?= number_format($totals['end_inv'],2) ?></div>
  </div>
  <div class="sx-23 kpi-card" >
    <div class="kpi-label">COS (Total)</div>
    <div class="kpi-val green" id="kpi_cos"><?= number_format($totals['cos'],2) ?></div>
  </div>
  <div class="sx-22 kpi-card" >
    <div class="kpi-label">Marketing Cost</div>
    <div class="kpi-val gold" id="kpi_mktg"><?= number_format($totals['mktg_cost'],2) ?></div>
  </div>
</div>

<!-- Controls -->
<div class="cogs-controls">
  <form method="GET" class="sx-21" style="display:flex" id="filterForm">
    <select name="month" class="sx-20 form-control" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="sx-19 form-control" onchange="this.form.submit()">
      <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
  </form>
  <button class="btn btn-primary addMonthBtn" onclick="addRow()" style="<?= $rows ? 'display:none' : '' ?>">+ Add <?= $months[$fMonth-1] ?> COGS</button>
  <a href="stella_cogs.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download Excel</a>
</div>

<!-- Column Totals Banner -->
<div class="sx-18 card" >
  <div class="sx-17" style="background:#0f3d2e;color:rgba(255,255,255,.5)">
    COLUMN TOTALS — <?= strtoupper($months[$fMonth-1]) ?> <?= $fYear ?>
  </div>
  <div class="sx-16" style="display:grid">
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
    <div class="sx-15">
      <div class="sx-14" style="color:var(--subtext)"><?= $lbl ?></div>
      <div style="font-family:var(--font-m);font-size:1.05rem;font-weight:800;
                  color:<?= $color ?>">
        <?= (float)$val == 0 ? '<span style="color:var(--muted)">—</span>' : number_format((float)$val,2) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MEI Auto-fill Notice -->
<div class="sx-13" style="background:#fffbeb;display:flex;color:#92400e">
  <span class="sx-10">📦</span>
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
<div class="sx-12" style="background:#eff6ff;display:flex;color:#1e40af">
  <span class="sx-10">🔗</span>
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
<div class="sx-11" style="background:#f5f3ff;display:flex;color:#5b21b6">
  <span class="sx-10">🧾</span>
  <span>
    <strong>PURC.</strong> for a new row is auto-filled from the
    <strong>Stella Expenses ledger's Purchases total</strong> for
    <?= $months[$fMonth-1] . ' ' . $fYear ?>:
    <strong style="color:#6d28d9"><?= number_format($purcAuto,2) ?></strong>
    <?php if ($purcAuto == 0): ?>
      <span style="color:#dc3545"> — No Purchases entries found for this month yet.</span>
    <?php endif; ?>
    — you can still edit it.
  </span>
</div>

<!-- Table -->
<div class="sx-9 card" >
  <table class="cogs-tbl" id="cogsTable">
    <thead>
      <tr>
        <th class="col-date">Date</th>
        <th class="col-num">BEG.</th>
        <th class="col-num" title="Auto-suggested from the Stella Expenses ledger's Purchases total total — editable">PURC. <span class="sx-8" style="background:rgba(139,92,246,.25);color:#c4b5fd">SUGGESTED</span></th>
        <th class="col-num" title="Auto-suggested from Month End Inventory — editable">END. <span class="sx-8" style="background:rgba(255,200,0,.2);color:#fbbf24">SUGGESTED</span></th>
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
            <input type="date" class="sx-5 date-input" value="<?= htmlspecialchars($r['cogs_date']) ?>"
                   readonly tabindex="-1" title="Month-end date (one row per month)" >
          </td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input" value="<?= number_format((float)$r['beg'],2) ?>"
                     placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
          <td title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"><input type="text" inputmode="decimal" autocomplete="off" class="sx-4 cogs-input purc-auto-field" value="<?= number_format((float)$r['purc'],2) ?>"
                     placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9"></td>
          <td title="Auto-suggested from Month End Inventory grand total (<?= number_format($meiTotal,2) ?>) — editable">
            <input type="text" inputmode="decimal" autocomplete="off" class="sx-4 cogs-input end-auto-field"
                   value="<?= number_format((float)$r['end_inv'],2) ?>"
                   oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"
                   style="background:#fff8e1;color:#b45309">
          </td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input cos-field" value="<?= number_format((float)$r['cos'],2) ?>"
                     readonly tabindex="-1"></td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mktg-field" value="<?= number_format((float)$r['mktg_cost'],2) ?>"
                     placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
          <td class="sx-3">
            <div class="sx-2" style="display:flex">
              <button class="btn-del-row"  onclick="deleteRow(this)">✕</button>
              <div class="row-status" style="display:block">✓ Saved</div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
      <tr id="emptyRow">
        <td colspan="7" class="sx-1" style="color:var(--muted)">
          No COGS entries for <?= $months[$fMonth-1] ?> <?= $fYear ?> — click <strong>+ Add <?= $months[$fMonth-1] ?> COGS</strong>
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

<div class="sx-7" style="display:flex">
  <button class="btn btn-primary addMonthBtn" onclick="addRow()" style="<?= $rows ? 'display:none' : '' ?>">+ Add <?= $months[$fMonth-1] ?> COGS</button>
  <span class="sx-6" style="color:var(--subtext)">One BEG. and one END. per month · changes save automatically · PURC. = this month's purchases from the Disbursement</span>
</div>

  </div></div>

<script>
const CURR_MONTH = <?= $fMonth ?>;
const CURR_YEAR  = <?= $fYear ?>;
// Grand total from Month End Inventory for this month — auto-fills END. column
const MEI_TOTAL  = <?= $meiTotal ?>;
// Previous month's Ending Inventory — auto-suggests BEG. on a new row
const PREV_END_INV = <?= $prevEndInv ?>;
// This month's Purchases total (from Stella Expenses ledger's Purchases total) — auto-suggests PURC. on a new row
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
      <input type="date" class="sx-5 date-input" value="${d}" readonly tabindex="-1" title="Month-end date (one row per month)" >
    </td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="sx-4 cogs-input beg-auto-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#eff6ff;color:#1d4ed8" title="Auto: previous row's END (or last month's END)"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="sx-4 cogs-input purc-auto-field" value="" placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9" title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="sx-4 cogs-input end-auto-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#fff8e1;color:#b45309"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input cos-field" value="0.00" readonly tabindex="-1"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mktg-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
    <td class="sx-3">
      <div class="sx-2" style="display:flex">
        <button class="btn-del-row" onclick="deleteRow(this)">✕</button>
        <div class="row-status" style="display:block;color:var(--subtext)">not saved yet</div>
      </div>
    </td>`;

async function fillSuggestion(tr) {
  const d = tr.querySelector('.date-input').value;
  if (!d) return;
  try {
    const j = await (await fetch('stella_cogs.php?ajax_suggest=1&date=' + encodeURIComponent(d))).json();
    if (!j.ok) return;
    const ins = tr.querySelectorAll('.cogs-input');
    ins[0].value = j.beg ? fmtN(j.beg) : ''; ins[1].value = fmtN(j.purc);
    ins[0].dataset.auto = ins[0].value ? '1' : '';
    ins[2].value = j.end_inv ? fmtN(j.end_inv) : '';
    ins[2].dataset.auto = ins[2].value ? '1' : '';
    calcCos(ins[0]);
    if (j.end_inv > 0) changed(ins[2]);          // month-end row with an inventory total → save straight away
  } catch (e) {}
}

// a value the page filled in by itself (BEG. from last month's END, END. from Month End Inventory) is replaced,
// not appended to, when you start typing
function autoKey(el, ev) {
  if (el.dataset.auto === '1' && (ev.key.length === 1 || ev.key === 'Backspace' || ev.key === 'Delete') && !ev.ctrlKey && !ev.metaKey) {
    el.select(); el.dataset.auto = '';
  }
}
function syncAddBtn() {
  const has = !!document.querySelector('#cogsBody tr[data-id]');
  document.querySelectorAll('.addMonthBtn').forEach(b => b.style.display = has ? 'none' : '');
}
// one BEG. and one END. per month → a single row dated the last day of the month
function addRow() {
  if (document.querySelector('#cogsBody tr[data-id]')) { syncAddBtn(); return; }
  const empty = document.getElementById('emptyRow');
  if (empty) empty.remove();
  const d = dateFor('last');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = ROW_HTML(d);
  document.getElementById('cogsBody').appendChild(tr);
  fillSuggestion(tr);
  syncAddBtn();
  tr.querySelector('.beg-auto-field').focus();
}

// numbers are shown with thousands separators (1,000.00) — parse them back before any maths
const numOf = v => { const x = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(x) ? x : 0; };
const fmtN  = n => (+n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function gv(input) { return numOf(input.value); }
// live commas while typing (keeps the cursor where it was)
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
function fmtBlur(el) { el.value = el.value.trim() === '' ? '' : fmtN(numOf(el.value)); }

// COS = BEG + PURC - MKTG COST - END
function calcCos(el) {
  const tr = el.closest('tr'), i = tr.querySelectorAll('.cogs-input');
  i[3].value = fmtN(gv(i[0]) + gv(i[1]) - gv(i[4]) - gv(i[2]));
  refreshFooter();
}

// every edit → recalc + auto-save (debounced)
function changed(el) {
  const tr = el.closest('tr');
  commaLive(el);
  calcCos(el);
  setStatus(tr, 'typing…', 'var(--subtext)');
  clearTimeout(tr._t);
  tr._t = setTimeout(() => { tr._t = null; saveRow(tr); }, 600);
}
function dateChanged(el) { /* the month row's date is fixed */ }
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
    ins[1].value = fmtN(r.purc);
    ins[3].value = fmtN(r.cos);
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
    const json = await (await fetch('stella_cogs.php', {method:'POST', body:fd})).json();
    if (json.ok) {
      if (json.id) tr.dataset.id = json.id;
      if (tr._deleted && json.id) { const f = new FormData(); f.append('ajax_delete','1'); f.append('row_id', json.id); fetch('stella_cogs.php', {method:'POST', body:f}); return; }
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
      const j = await (await fetch('stella_cogs.php', {method:'POST', body:fd})).json();
      if (!j.ok) { tr._deleted = false; btn.disabled = false; setStatus(tr, '❌ ' + (j.msg || 'not deleted'), 'var(--accent2)'); return; }
    } catch(e) { tr._deleted = false; btn.disabled = false; setStatus(tr, '❌ not deleted (network)', 'var(--accent2)'); return; }
  }

  tr.remove();
  if (!document.querySelector('#cogsBody tr')) {
    const tbody = document.getElementById('cogsBody');
    tbody.innerHTML = `<tr id="emptyRow"><td colspan="7" class="sx-1" style="color:var(--muted)">No COGS entry — click <strong>+ Add COGS</strong></td></tr>`;
  }
  refreshFooter();
  updateKPIs();
  syncAddBtn();
}

function totalsNow() {
  let purc=0, cos=0, mktg=0, first=null, last=null;
  document.querySelectorAll('#cogsBody tr[data-id]').forEach(tr => {
    const ins = tr.querySelectorAll('.cogs-input');
    const d = tr.querySelector('.date-input').value || '';
    purc += numOf(ins[1]?.value); cos += numOf(ins[3]?.value); mktg += numOf(ins[4]?.value);
    if (!first || d < first.d) first = {d, v: numOf(ins[0]?.value)};
    if (!last  || d >= last.d) last  = {d, v: numOf(ins[2]?.value)};
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
    const beg  = numOf(inputs[0]?.value);
    const purc = numOf(inputs[1]?.value);
    const end  = numOf(inputs[2]?.value); // saved/edited END value
    const mktg = numOf(inputs[4]?.value);
    if (inputs[3]) inputs[3].value = fmtN(beg + purc - end - mktg);
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
    const j = await (await fetch(`stella_cogs.php?ajax_state=1&year=${CURR_YEAR}&month=${CURR_MONTH}`)).json();
    if (j.ok) applyState(j.rows);
  } catch (e) {}
}, 5000);
</script>
</body>
</html>