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

// ── Self-repair: a table copied from a database export can lose its PRIMARY KEY / AUTO_INCREMENT on `id`.
//    Then every new row gets id 0, "Delete" removes nothing and edits hit every row at once.
try {
    $col = $pdo->query("SHOW COLUMNS FROM `h_cogs` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && stripos((string)$col['Extra'], 'auto_increment') === false) {
        $bad = (int)$pdo->query("SELECT COUNT(*) FROM `h_cogs` WHERE id = 0 OR id IN (SELECT id FROM (SELECT id FROM `h_cogs` GROUP BY id HAVING COUNT(*) > 1) d)")->fetchColumn();
        if ($bad > 0 || $col['Key'] !== 'PRI') {
            $pdo->exec("SET @cogs_i := 0");
            $pdo->exec("UPDATE `h_cogs` SET id = (@cogs_i := @cogs_i + 1) ORDER BY cogs_date, updated_at");
        }
        if ($col['Key'] === 'PRI') $pdo->exec("ALTER TABLE `h_cogs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
        else                       $pdo->exec("ALTER TABLE `h_cogs` ADD PRIMARY KEY (`id`), MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
    }
} catch (Throwable $e) {}
// Mid-month inventory (typed by hand, for reference — it does not change COS)
try { if (!$pdo->query("SHOW COLUMNS FROM `h_cogs` LIKE 'mid_inv'")->fetch()) $pdo->exec("ALTER TABLE `h_cogs` ADD COLUMN `mid_inv` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $e) {}

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
// Month End Inventory total for a month = the LAST inventory day saved in that month (month-end count).
// Earlier days (e.g. the 15th / mid-month count) are separate snapshots and must not be added on top.
function cogsMeiTotal(PDO $pdo, int $y, int $m): float {
    $a = sprintf('%04d-%02d-01', $y, $m); $b = date('Y-m-t', strtotime($a));
    $q = $pdo->prepare("SELECT MAX(inv_date) FROM h_month_end_inv WHERE store_name='H' AND inv_date BETWEEN ? AND ?");
    $q->execute([$a, $b]); $d = $q->fetchColumn();
    if ($d) {
        $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM h_month_end_inv WHERE store_name='H' AND inv_date=?");
        $q->execute([$d]); return (float)$q->fetchColumn();
    }
    $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM h_month_end_inv WHERE store_name='H' AND inv_year=? AND inv_month=?");
    $q->execute([$y, $m]); return (float)$q->fetchColumn();
}
// Mid-month Month End Inventory = the last inventory day saved from the 1st to the 15th of that month.
function cogsMidTotal(PDO $pdo, int $y, int $m): float {
    $a = sprintf('%04d-%02d-01', $y, $m); $b = sprintf('%04d-%02d-15', $y, $m);
    $q = $pdo->prepare("SELECT MAX(inv_date) FROM h_month_end_inv WHERE store_name='H' AND inv_date BETWEEN ? AND ?");
    $q->execute([$a, $b]); $d = $q->fetchColumn();
    if (!$d) return 0.0;
    $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM h_month_end_inv WHERE store_name='H' AND inv_date=?");
    $q->execute([$d]); return (float)$q->fetchColumn();
}
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
            $v = cogsMeiTotal($pdo, $py, $pm);
        }
        $beg = (float)$v; $lower = date('Y-m-d', strtotime($ms . ' -1 day'));
    }
    $w = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM h_expenses WHERE expense_date > ? AND expense_date <= ?");
    $w->execute([$lower, $date]); $purc = round((float)$w->fetchColumn(), 2);
    $end = 0.0;
    if ($date === date('Y-m-t', strtotime($ms))) {      // month-end row → suggest the Month End Inventory total
        $end = cogsMeiTotal($pdo, $y, $m);
    }
    $mid = ($date === date('Y-m-t', strtotime($ms))) ? cogsMidTotal($pdo, $y, $m) : 0.0;
    return ['beg' => round($beg, 2), 'purc' => $purc, 'end_inv' => round($end, 2), 'mid_inv' => round($mid, 2)];
}
// One BEG. and one END. per month: older rows (15th / last day / accidental duplicates) are folded into one month row.
function cogsMergeMonth(PDO $pdo, int $y, int $m): void {
    $q = $pdo->prepare("SELECT * FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date, id");
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
        $pdo->prepare("UPDATE h_cogs SET cogs_date=?, beg=?, end_inv=?, mktg_cost=? WHERE id=?")
            ->execute([$monthEnd, (float)$first['beg'], (float)$last['end_inv'], round($mktg, 2), $keep]);
        $pdo->prepare("DELETE FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? AND id<>?")->execute([$y, $m, $keep]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
}
function cogsMonthState(PDO $pdo, int $y, int $m): array {
    $q = $pdo->prepare("SELECT id, cogs_date, purc, cos FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY cogs_date, id");
    $q->execute([$y, $m]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

try { cogsMergeMonth($pdo, $fYear, $fMonth); } catch (Throwable $e) {}
try { if (function_exists('expSyncCogsPurchases')) expSyncCogsPurchases($pdo, 'h_cogs', 'h_expenses', 'H', null, false); } catch (Throwable $e) {}

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
        $mid   = (float)($_POST['mid_inv']   ?? 0);
        $cos   = $beg + $purc - $mktg - $end;   // COS = BEG + PURC - MKTG COST - END

        $date = date('Y-m-t', strtotime($date));            // one row per month, dated the last day
        $yr  = (int)date('Y', strtotime($date));
        $mo  = (int)date('n', strtotime($date));

        $rowId = (int)($_POST['row_id'] ?? 0);
        // only ONE row per month: a "new" row for a month that already has one updates that row instead
        $ex = $pdo->prepare("SELECT id FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=? ORDER BY id DESC LIMIT 1");
        $ex->execute([$yr, $mo]);
        $existing = (int)$ex->fetchColumn();
        if ($existing > 0) $rowId = $existing;
        if ($rowId > 0) {
            $pdo->prepare("UPDATE h_cogs SET cogs_date=?, cogs_year=?, cogs_month=?, beg=?, purc=?, end_inv=?, cos=?, mktg_cost=?, mid_inv=?, saved_by=? WHERE id=? AND store_name='H'")
                ->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$mid,$user['name'],$rowId]);
            $outId = $rowId;
        } else {
            $sql = "INSERT INTO h_cogs (cogs_date, cogs_year, cogs_month, store_name, beg, purc, end_inv, cos, mktg_cost, mid_inv, saved_by) VALUES (?,?,?,'H',?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date,$yr,$mo,$beg,$purc,$end,$cos,$mktg,$mid,$user['name']]);
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
    $xlsx->header(['DATE','BEG.','PURC.','END.','COS','MKTG COST','MID INV.']);
    foreach ($data as $r) {
        $xlsx->row([
            date('M d, Y', strtotime($r['cogs_date'])),
            number_format($r['beg'],2,'.',''),
            number_format($r['purc'],2,'.',''),
            number_format($r['end_inv'],2,'.',''),
            number_format($r['cos'],2,'.',''),
            number_format($r['mktg_cost'],2,'.',''),
            number_format((float)($r['mid_inv'] ?? 0),2,'.',''),
        ]);
    }
    $xlsx->totalRow(['TOTAL', number_format($t['beg'],2,'.',''), number_format($t['purc'],2,'.',''), number_format($t['end_inv'],2,'.',''), number_format($t['cos'],2,'.',''), number_format($t['mktg_cost'],2,'.',''), number_format($data ? (float)end($data)['mid_inv'] : 0,2,'.','')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([16,12,12,12,12,14,14]);
    $xlsx->output('h_cogs_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.xlsx');
}

// ── Fetch Month End Inv grand total for selected month ─────
// Month End Inventory total = the last inventory day saved in the selected month
$meiTotal = cogsMeiTotal($pdo, (int)$fYear, (int)$fMonth);
$midMei   = cogsMidTotal($pdo, (int)$fYear, (int)$fMonth);

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
    $prevEndInv = cogsMeiTotal($pdo, (int)$prevYear, (int)$prevMonth);
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
$totals['mid_inv'] = (float)($rows ? end($rows)['mid_inv'] ?? 0 : 0);
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
    <div class="subtitle">BEG auto-suggests from prior month's END · END auto-suggests from Month End Inventory (editable) · COS = BEG + PURC − MKTG COST − END · one BEG. and one END. per month · auto-saves</div>
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
  <button class="btn btn-primary addMonthBtn" onclick="addRow()" style="<?= $rows ? 'display:none' : '' ?>">+ Add <?= $months[$fMonth-1] ?> COGS</button>
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

<!-- MID Auto-suggest Notice -->
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:10px 16px;
            margin-bottom:12px;display:flex;align-items:center;gap:10px;
            font-family:var(--font-m);font-size:.72rem;color:#0f766e">
  <span style="font-size:1rem">🗓️</span>
  <span>
    <strong>MID. Inventory</strong> is auto-suggested from the
    <strong>Month End Inventory</strong> counted on or before the 15th of
    <?= $months[$fMonth-1] . ' ' . $fYear ?>:
    <strong><?= number_format($midMei,2) ?></strong>
    <?php if ($midMei == 0): ?>
      <span style="color:#dc3545"> — No mid-month inventory found (save a Month End Inventory dated the 15th or earlier).</span>
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
        <th class="col-num" style="background:#0f766e" title="Type the mid-month (15th) inventory here — for reference, it does not change COS">MID INV. <span style="font-size:.5rem;background:rgba(255,255,255,.2);padding:1px 5px;border-radius:8px;margin-left:3px">TYPE</span></th>
        <th class="col-act">Action</th>
      </tr>
    </thead>
    <tbody id="cogsBody">
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
        <tr data-id="<?= $r['id'] ?>">
          <td class="td-date">
            <input type="date" class="date-input" value="<?= htmlspecialchars($r['cogs_date']) ?>"
                   readonly tabindex="-1" title="Month-end date (one row per month)" style="pointer-events:none;opacity:.85">
          </td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input" value="<?= number_format((float)$r['beg'],2) ?>"
                     placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
          <td title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input purc-auto-field" value="<?= number_format((float)$r['purc'],2) ?>"
                     placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9;font-weight:700"></td>
          <td title="Auto-suggested from Month End Inventory grand total (<?= number_format($meiTotal,2) ?>) — editable">
            <input type="text" inputmode="decimal" autocomplete="off" class="cogs-input end-auto-field"
                   value="<?= number_format((float)$r['end_inv'],2) ?>"
                   oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"
                   style="background:#fff8e1;color:#b45309;font-weight:700">
          </td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input cos-field" value="<?= number_format((float)$r['cos'],2) ?>"
                     readonly tabindex="-1"></td>
          <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mktg-field" value="<?= number_format((float)$r['mktg_cost'],2) ?>"
                     placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
          <td title="Auto-suggested from Month End Inventory (on/before the 15th) — editable. Does not change COS"><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mid-field" value="<?= number_format((float)($r['mid_inv'] ?? 0),2) ?>"
                     placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#f0fdfa;color:#0f766e;font-weight:700"></td>
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
        <td colspan="8" style="text-align:center;padding:48px;color:var(--muted);
                                font-family:var(--font-m);font-size:.78rem">
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
        <td id="foot_mid"><?= number_format((float)($totals['mid_inv'] ?? 0),2) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <button class="btn btn-primary addMonthBtn" onclick="addRow()" style="<?= $rows ? 'display:none' : '' ?>">+ Add <?= $months[$fMonth-1] ?> COGS</button>
  <span style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext)">One BEG. and one END. per month · changes save automatically · PURC. = this month's purchases from the Disbursement</span>
</div>

  </div></div>

<script>
const CURR_MONTH = <?= $fMonth ?>;
const CURR_YEAR  = <?= $fYear ?>;
// Grand total from Month End Inventory for this month — auto-fills END. column
const MID_MEI    = <?= $midMei ?>;
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
      <input type="date" class="date-input" value="${d}" readonly tabindex="-1" title="Month-end date (one row per month)" style="pointer-events:none;opacity:.85">
    </td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input beg-auto-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#eff6ff;color:#1d4ed8;font-weight:700" title="Auto: previous row's END (or last month's END)"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input purc-auto-field" value="" placeholder="0.00" readonly tabindex="-1" style="background:#f5f3ff;color:#6d28d9;font-weight:700" title="Auto from Disbursement (Purchases - Non-Vat / Vatable)"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input end-auto-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#fff8e1;color:#b45309;font-weight:700"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input cos-field" value="0.00" readonly tabindex="-1"></td>
    <td><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mktg-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)"></td>
    <td title="Auto-suggested from Month End Inventory (on/before the 15th) — editable. Does not change COS"><input type="text" inputmode="decimal" autocomplete="off" class="cogs-input mid-field" value="" placeholder="0.00" oninput="changed(this)" onblur="fmtBlur(this)" onfocus="this.select()" onkeydown="autoKey(this,event)" style="background:#f0fdfa;color:#0f766e;font-weight:700"></td>
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
    ins[0].value = j.beg ? fmtN(j.beg) : ''; ins[1].value = fmtN(j.purc);
    ins[0].dataset.auto = ins[0].value ? '1' : '';
    ins[2].value = j.end_inv ? fmtN(j.end_inv) : '';
    ins[2].dataset.auto = ins[2].value ? '1' : '';
    if (ins[5]) { ins[5].value = j.mid_inv ? fmtN(j.mid_inv) : ''; ins[5].dataset.auto = ins[5].value ? '1' : ''; }
    calcCos(ins[0]);
    if (j.mid_inv > 0 && ins[5]) changed(ins[5]);
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
    beg: gv(ins[0]), purc: gv(ins[1]), end_inv: gv(ins[2]), cos: gv(ins[3]), mktg_cost: gv(ins[4]), mid_inv: ins[5] ? gv(ins[5]) : 0,
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
    tbody.innerHTML = `<tr id="emptyRow"><td colspan="8" style="text-align:center;padding:48px;color:var(--muted);font-family:var(--font-m);font-size:.78rem">No COGS entry — click <strong>+ Add COGS</strong></td></tr>`;
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
  const fm = document.getElementById('foot_mid'), mi = document.querySelector('#cogsBody .mid-field');
  if (fm) fm.textContent = fmtC(mi ? numOf(mi.value) : 0);
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
  // a saved month row whose MID. is still empty gets the Month End Inventory mid-month count
  if (MID_MEI > 0) document.querySelectorAll('#cogsBody tr[data-id] .mid-field').forEach(m => {
    if (numOf(m.value) === 0) { m.value = fmtN(MID_MEI); m.dataset.auto = '1'; changed(m); }
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