<?php
// ============================================================
//  demic_jaro_vat_computation.php — DemicLab-Jaro VAT Computation Journal
//  Format: one column per expense category (matches "JULY 2026" sheet).
//  No VAT / VATABLE computation — plain manual entry per category,
//  with Excel-style click-and-drag cell selection to total any
//  selection of cells (like Excel's status-bar Sum/Avg/Count).
//
//  Reached via the "🧾 VAT Computation" button on
//  demic_jaro_purchases.php (which links with ?month=N&year=YYYY).
//  This file also accepts a combined ?month=YYYY-MM param so it
//  can be filtered/linked to directly.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only DemicLab-Jaro branch + management can access
if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Expense categories — copied verbatim (order + labels) from the
//    "JULY 2026" tab of DISBURSEMENT_2026_(VAT).xlsx, columns G→AC ──
// key => column label
$CATS = [
    'materials_purchases'          => 'MATERIALS PURCAHASES (CONSUMABLES/ECGS)',
    'wtax_rent'                    => 'WTAX RENT',
    'service_fee'                  => 'SERVICE FEE',
    'rent'                         => 'RENT',
    'office_supplies'              => 'OFFICE SUPPLIES',
    'employee_benefits'            => 'EMPLOYEE BENEFITS',
    'advertisement'                => 'ADVERTISEMENT',
    'bio'                          => 'BIO',
    'janitorial_cleaning_supplies' => 'JANITORIAL & CLEANING SUPPLIES',
    'freight_charge'               => 'FREIGHT CHARGE',
    'fare'                         => 'FARE',
    'fuel_oil'                     => 'FUEL/OIL',
    'repairs_maintenance'          => 'REPAIRS & MAINTENANCE',
    'communication'                => 'COMMUNICATION',
    'electricity'                  => 'ELECTRICITY',
    'management_fee'               => 'MANAGEMENT FEE',
    'representation_expense'       => 'REPRESENTATION EXPENSE',
    'salary'                       => 'SALARY',
    'professional_fee'             => 'PROFESSIONAL FEE',
    'taxes_licenses'               => 'TAXES & LICENSES',
    'sss_phil_hdmf'                => 'SSS/PHIL/HDMF CONTRIBUTION',
    'misc'                         => 'MISC',
    'other_equipment'              => 'OTHER EQUIPMENT',
];

// ── Auto-create table ──────────────────────────────────────
$catColsSql = '';
foreach ($CATS as $key => $label) {
    $catColsSql .= "`$key` decimal(15,2) DEFAULT 0.00,\n    ";
}
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_jaro_vat_computation` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`    date             DEFAULT NULL,
    `company_name`  varchar(255)     DEFAULT '',
    `description`   varchar(255)     DEFAULT '',
    `address`       varchar(255)     DEFAULT '',
    `invoice_no`    varchar(100)     DEFAULT '',
    $catColsSql
    `saved_by`      varchar(100)     DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date`         (`entry_date`),
    KEY `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Safe migration: bring an existing (older VAT-style) table up to
//    the new per-category layout, and drop the columns we no longer
//    need (vat_status, gross, input_tax, net_of_vat, account_title, tin).
foreach ($CATS as $key => $label) {
    try {
        $pdo->exec("ALTER TABLE `demic_jaro_vat_computation` ADD COLUMN `$key` decimal(15,2) DEFAULT 0.00");
    } catch (Throwable $ignored) {} // column already exists — ignore
}
try { $pdo->exec("ALTER TABLE `demic_jaro_vat_computation` ADD COLUMN `description` varchar(255) DEFAULT ''"); } catch (Throwable $ignored) {}
foreach (['tin','vat_status','gross','input_tax','net_of_vat','account_title'] as $oldCol) {
    try { $pdo->exec("ALTER TABLE `demic_jaro_vat_computation` DROP COLUMN `$oldCol`"); } catch (Throwable $ignored) {} // already gone — ignore
}

$TXT_COLS = ['entry_date','company_name','description','address','invoice_no'];
$NUM_COLS = array_keys($CATS);

// ── One-time seed: JULY 2026 entries copied verbatim from
//    DISBURSEMENT_2026_(VAT).xlsx → "JULY 2026" tab (77 rows).
//    Only runs once — skipped automatically once any July 2026 row
//    already exists, so re-loading this page never duplicates data.
$julyCount = (int)$pdo->query("SELECT COUNT(*) FROM demic_jaro_vat_computation WHERE DATE_FORMAT(entry_date,'%Y-%m')='2026-07' OR (entry_date >= '2026-06-27' AND entry_date <= '2026-07-09')")->fetchColumn();
if ($julyCount === 0) {
    require __DIR__ . '/demic_jaro_vat_computation_seed_july2026.php'; // defines $SEED_JULY_2026
    $seedCols  = array_merge($TXT_COLS, $NUM_COLS);
    $seedSql   = "INSERT INTO demic_jaro_vat_computation (" . implode(',', array_map(fn($f)=>"`$f`", $seedCols)) . ") VALUES (" . implode(',', array_fill(0, count($seedCols), '?')) . ")";
    $seedStmt  = $pdo->prepare($seedSql);
    foreach ($SEED_JULY_2026 as $seedRow) {
        $vals = [];
        foreach ($seedCols as $f) {
            $vals[] = $f === 'entry_date' ? ($seedRow['entry_date'] ?? null)
                    : (in_array($f, $TXT_COLS, true) ? ($seedRow[$f] ?? '') : ($seedRow[$f] ?? 0.00));
        }
        try { $seedStmt->execute($vals); } catch (Throwable $ignored) {}
    }
}

// ── AJAX: Vendor lookup (name + address only — no TIN/VAT needed) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendor_lookup'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (!$q) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT company_name, address
                            FROM vendor_masterlist_unified
                            WHERE company_name LIKE ? ORDER BY company_name ASC LIMIT 10");
    $stmt->execute(["%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = [];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($NUM_COLS as $f) $data[$f] = (float)str_replace(',', '', $_POST[$f] ?? 0);
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO demic_jaro_vat_computation (" . implode(',', array_map(fn($f)=>"`$f`", $fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($TXT_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = trim($_POST[$f] ?? ''); }
    foreach ($NUM_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = (float)str_replace(',', '', $_POST[$f] ?? 0); }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE demic_jaro_vat_computation SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM demic_jaro_vat_computation WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Month filter: accepts either ?month=YYYY-MM directly, or the
//    ?month=N&year=YYYY pair sent by demic_jaro_purchases.php ─────
function resolveMonthParam(): string {
    $m = $_GET['month'] ?? '';
    if ($m !== '' && strpos($m, '-') !== false) {
        return $m; // already YYYY-MM
    }
    if ($m !== '' && isset($_GET['year'])) {
        return sprintf('%04d-%02d', (int)$_GET['year'], (int)$m);
    }
    return date('Y-m');
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $fMonth = resolveMonthParam();
    $where = $fMonth ? "WHERE DATE_FORMAT(entry_date,'%Y-%m') = ?" : "";
    $params = $fMonth ? [$fMonth] : [];
    $stmt = $pdo->prepare("SELECT * FROM demic_jaro_vat_computation $where ORDER BY entry_date ASC, id ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DemicLab-Jaro_VAT_Computation_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['MERITONI CORP']);
    fputcsv($out, ['DemicLab-Jaro VAT Computation Journal']);
    fputcsv($out, ['Generated: ' . date('F d, Y H:i:s')]);
    fputcsv($out, []);
    $header = ['DATE','NAME OF SUPPLIER','DESCRIPTION','ADDRESS','OR/C.R. NO.','TOTAL AMOUNT'];
    foreach ($CATS as $label) $header[] = $label;
    fputcsv($out, $header);
    $colTotals = array_fill_keys($NUM_COLS, 0.0);
    foreach ($rows as $r) {
        $rowTotal = 0.0;
        $line = [$r['entry_date'], $r['company_name'], $r['description'], $r['address'], $r['invoice_no']];
        $catVals = [];
        foreach ($NUM_COLS as $c) { $v = (float)$r[$c]; $rowTotal += $v; $colTotals[$c] += $v; $catVals[] = number_format($v, 2); }
        array_splice($line, 5, 0, number_format($rowTotal, 2));
        $line = array_merge($line, $catVals);
        fputcsv($out, $line);
    }
    $totalLine = ['','','','','TOTAL', number_format(array_sum($colTotals), 2)];
    foreach ($NUM_COLS as $c) $totalLine[] = number_format($colTotals[$c], 2);
    fputcsv($out, $totalLine);
    fclose($out);
    exit;
}

// ── Load rows ─────────────────────────────────────────────
$fMonth  = resolveMonthParam();
$fSearch = trim($_GET['q'] ?? '');

$where  = ["DATE_FORMAT(entry_date,'%Y-%m') = ?"]; $params = [$fMonth];
if ($fSearch) {
    $where[]  = "(company_name LIKE ? OR description LIKE ? OR invoice_no LIKE ?)";
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
}
$whereSQL = "WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM demic_jaro_vat_computation $whereSQL ORDER BY entry_date ASC, id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Column totals (each category) + grand total — plain SUM, no VAT math
$colTotals = array_fill_keys($NUM_COLS, 0.0);
$grandTotal = 0.0;
foreach ($rows as $r) {
    foreach ($NUM_COLS as $c) { $v = (float)$r[$c]; $colTotals[$c] += $v; $grandTotal += $v; }
}

$pageTitle  = 'DemicLab-Jaro VAT Computation';
$activePage = 'demic_jaro_vat_computation';
include '../layout.php';
$colCount = 6 + count($CATS); // date, name, desc, address, invoice, total + categories
?>

<style>
/* ============================================================
   Design copied from demic_jaro_purchases.php (navy/slate ledger
   theme) — reapplied here in place of the old green Excel look.
   Class names kept as dsb-* / di / bsv / bdel / ac-* so the
   existing markup and JS (drag-select, autocomplete, etc.) don't
   need to change, only their appearance.
   ============================================================ */
.page-content { padding: 20px 24px !important; overflow-x: hidden; }

.dsb-outer {
  width: 100%; overflow-x: auto;
  border-radius: var(--radius); border: 1px solid var(--border);
  background: var(--surface);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  user-select: none; /* avoid text-selection fighting with drag-select */
}
.dsb-outer::-webkit-scrollbar { height: 8px; }
.dsb-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.dsb-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

/* ── Table ── */
.dsb-table { border-collapse: collapse; width: 100%; min-width: 2600px; font-size: .68rem; table-layout: fixed; }

.dsb-table thead tr.head-company td {
  background: #162d4a; color: rgba(255,255,255,.85);
  font-family: var(--font-m); font-size: .62rem; font-weight: 700;
  padding: 6px 12px; border: 1px solid #2d5480; text-align: left;
  letter-spacing: .04em;
}
.dsb-table thead tr.head-month td {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .6rem; font-weight: 700;
  padding: 5px 12px; border: 1px solid #2d5480; letter-spacing: .06em;
}
.dsb-table thead tr.head-totals td {
  background: #f0fdf4; color: var(--accent);
  font-family: var(--font-m); font-size: .66rem; font-weight: 800;
  padding: 6px 8px; border: 1px solid #e3e6ea; text-align: right;
  position: sticky; top: 45px; z-index: 19; white-space: nowrap;
}
.dsb-table thead tr.head-totals td.lbl {
  text-align: center; text-transform: uppercase; letter-spacing: .08em;
  font-size: .58rem; color: var(--subtext2); background: #f8f9fb;
}
.dsb-table thead tr.head-cols th {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .52rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  padding: 8px 8px; border: 1px solid #2d5480;
  white-space: normal; text-align: center; line-height: 1.3;
  position: sticky; top: 0; z-index: 20;
  cursor: pointer; /* click header = select whole column */
}
.dsb-table thead tr.head-cols th.cat-th { background: #3d1a00; }
.dsb-table thead tr.head-cols th.cat-th:hover { background: #5a2700; }
.dsb-table thead tr.head-cols th.th-total { background: #1a4a2e; }

/* Column widths */
.th-date { width: 90px;  min-width: 90px; position: sticky !important; left: 0; z-index: 30 !important; background: #162d4a !important; }
.th-name { width: 170px; min-width: 170px; }
.th-desc { width: 190px; min-width: 190px; }
.th-addr { width: 170px; min-width: 170px; }
.th-inv  { width: 100px; min-width: 100px; }
.th-total{ width: 105px; min-width: 105px; }
.th-cat  { width: 100px; min-width: 100px; }
.th-act  { width: 80px;  min-width: 80px; position: sticky; right: 0; z-index: 30; background: #162d4a !important; box-shadow: -2px 0 4px rgba(0,0,0,.15); }
.td-act-sticky { position: sticky; right: 0; z-index: 4; background: #f8f9fb; box-shadow: -2px 0 4px rgba(0,0,0,.08); border-left: 2px solid #d1d5db; }
.td-date-sticky {
  position: sticky; left: 0; z-index: 5;
  background: #f8f9fb !important; box-shadow: 2px 0 5px rgba(0,0,0,.06);
}

/* Row styling */
.dsb-table tbody tr { border-bottom: 1px solid #e8eaed; transition: background .1s; }
.dsb-table tbody tr td { background: #fff; }
.dsb-table tbody tr:nth-child(even) td { background: #f8f9fb; }
.dsb-table tbody tr:nth-child(even) td.td-act-sticky,
.dsb-table tbody tr:nth-child(even) td.td-date-sticky { background: #f0f2f5; }
.dsb-table tbody tr:hover td { filter: brightness(.97); }
.dsb-table td { border: 1px solid #e3e6ea; padding: 0; vertical-align: middle; }

/* Inline inputs */
.di {
  width: 100%; padding: 5px 6px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-h); font-size: .7rem;
  display: block; box-sizing: border-box;
}
.di:focus { background: rgba(15,123,92,.07); outline: 1px solid rgba(15,123,92,.4); }
.di.mono  { font-family: var(--font-m); font-size: .66rem; }
.di.num   { font-family: var(--font-m); font-size: .66rem; text-align: right; }

.di.total-ro {
  color: #0f7b5c; background: rgba(15,123,92,.07) !important; font-weight: 700; cursor: default;
}

/* Cell selection (Excel-style click & drag) */
td.cat-td, td.total-td { cursor: cell; }
td.cell-selected {
  background: rgba(30,58,95,.14) !important;
  outline: 2px solid #1e3a5f; outline-offset: -2px;
  position: relative; z-index: 2;
}

/* Totals row */
.dsb-table tfoot td {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .62rem; font-weight: 700;
  padding: 7px 8px; border: 1px solid #2d5480;
}
.dsb-table tfoot td.num { text-align: right; }

/* Action buttons */
.bsv {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%; margin-bottom: 3px;
}
.bsv:hover { background: #dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok  { background:#dcfce7; color:#15803d; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.bdel {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 5px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%;
}
.bdel:hover { background: #ffe4e6; }

/* Controls */
.dsb-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.btn-add-row {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 16px; background: var(--accent); color: #fff;
  border: none; border-radius: 8px; font-size: .8rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #0a6649; transform: translateY(-1px); }

/* Autocomplete dropdown */
.ac-wrap { position: relative; width: 100%; }
.ac-list {
  display: none; position: fixed; z-index: 99999;
  background: #fff; border: 1px solid #c8d4df; border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  max-height: 220px; overflow-y: auto; min-width: 280px;
}
.ac-list.open { display: block; }
.ac-item {
  padding: 8px 12px; cursor: pointer; font-size: .74rem;
  font-family: var(--font-h); border-bottom: 1px solid #f0f2f5;
  transition: background .1s;
}
.ac-item:hover { background: #f0fdf4; }
.ac-item .ac-name { font-weight: 600; color: #1a1d23; }
.ac-item .ac-addr { font-family: var(--font-m); font-size: .62rem; color: #9ca3af; margin-top: 1px; font-style: italic; }

.scroll-hint {
  font-family: var(--font-m); font-size: .62rem; color: var(--subtext);
  text-align: center; padding: 5px 12px; border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
.empty-state {
  text-align: center; padding: 50px 20px;
  font-family: var(--font-m); font-size: .78rem; color: var(--subtext);
}

/* Excel-style selection status bar */
.sel-statusbar {
  position: sticky; bottom: 0; left: 0; z-index: 30;
  display: flex; gap: 22px; align-items: center;
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .72rem; font-weight: 700;
  padding: 7px 16px; border-top: 2px solid #2d5480;
}
.sel-statusbar span b { font-weight: 800; margin-left: 5px; }
.sel-statusbar .hint { opacity: .8; font-weight: 500; font-size: .66rem; }
</style>

<!-- Header -->
<div class="section-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div class="section-title">DemicLab-Jaro <span>VAT Computation</span></div>
    <div class="section-subtitle">MERITONI CORP · Click a category header to select the whole column, or click-and-drag across cells to total any selection</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
  <a href="demic_jaro_purchases.php"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#9a3412;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(154,52,18,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#7c2d12';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#9a3412';this.style.transform=''">
    ← Purchases
  </a>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="dsb-controls" id="filterForm">
  <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($fMonth) ?>"
         style="max-width:160px" onchange="this.form.submit()">
  <input type="text" name="q" class="form-control" placeholder="Search supplier, description, OR/CR…"
         value="<?= htmlspecialchars($fSearch) ?>" style="max-width:240px">
  <button type="submit" class="btn btn-ghost btn-sm">Search</button>
  <?php if ($fSearch): ?>
  <a href="demic_jaro_vat_computation.php?month=<?= urlencode($fMonth) ?>" class="btn btn-ghost btn-sm">Reset</a>
  <?php endif; ?>
  <button type="button" class="btn-add-row" onclick="addRow()">+ Add Entry</button>
  <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllCells()" style="color:#217346;border-color:rgba(33,115,70,.3);background:rgba(33,115,70,.06)">
    ⛶ Select All Amounts
  </button>
  <a href="demic_jaro_vat_computation.php?export_csv=1&month=<?= urlencode($fMonth) ?>"
     class="btn btn-ghost btn-sm" style="color:#388E3C;border-color:rgba(76,175,80,.3);background:rgba(76,175,80,.06)">
    ⬇ Download CSV
  </a>
  <span style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
    <strong><?= count($rows) ?></strong> entr<?= count($rows) !== 1 ? 'ies' : 'y' ?>
  </span>
</form>

<!-- Table -->
<div class="dsb-outer">
  <?php if (count($rows) > 5): ?>
  <div class="scroll-hint">← Scroll horizontally to see all columns →</div>
  <?php endif; ?>
  <table class="dsb-table" id="dsbt">
    <thead>
      <tr class="head-company">
        <td colspan="<?= $colCount ?>" style="font-size:.76rem;font-weight:800;letter-spacing:.04em">MERITONI CORP</td>
      </tr>
      <tr class="head-month">
        <td colspan="<?= $colCount ?>"><?= strtoupper(date('F', strtotime($fMonth . '-01'))) ?></td>
      </tr>
      <tr class="head-totals">
        <td class="lbl" colspan="5">TOTALS</td>
        <td id="tot-grand"><?= number_format($grandTotal, 2) ?></td>
        <?php foreach ($NUM_COLS as $c): ?>
        <td id="tot-<?= $c ?>"><?= number_format($colTotals[$c], 2) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr class="head-cols">
        <th class="th-date">DATE</th>
        <th class="th-name">NAME OF SUPPLIER</th>
        <th class="th-desc">DESCRIPTION</th>
        <th class="th-addr">ADDRESS</th>
        <th class="th-inv">OR/C.R. NO.</th>
        <th class="th-total">TOTAL AMOUNT</th>
        <?php foreach ($CATS as $key => $label): ?>
        <th class="th-cat cat-th" data-colkey="<?= $key ?>" onclick="selectColumn('<?= $key ?>')" title="Click to select this whole column"><?= htmlspecialchars($label) ?></th>
        <?php endforeach; ?>
        <th class="th-act">ACTION</th>
      </tr>
    </thead>
    <tbody id="dsb-tbody">
    <?php if ($rows): ?>
      <?php foreach ($rows as $i => $r): ?>
      <tr id="row<?= $r['id'] ?>" data-id="<?= $r['id'] ?>" data-r="<?= $i ?>">
        <?= renderRow($r, $i, $CATS) ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr id="empty-row">
        <td colspan="<?= $colCount ?>" class="empty-state">No entries for <?= htmlspecialchars(date('F Y', strtotime($fMonth . '-01'))) ?> — click <strong>+ Add Entry</strong></td>
      </tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="text-align:right;padding:8px 12px;font-weight:800;font-family:var(--font-m);font-size:.65rem;">
          TOTAL (<span id="dsb-count"><?= count($rows) ?></span> entries)
        </td>
        <td class="num" id="tfoot-grand"><?= number_format($grandTotal, 2) ?></td>
        <?php foreach ($NUM_COLS as $c): ?>
        <td class="num" id="tfoot-<?= $c ?>"><?= number_format($colTotals[$c], 2) ?></td>
        <?php endforeach; ?>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <div class="sel-statusbar" id="selBar">
    <span class="hint">Click a header to select a column, or click-and-drag across cells to total a selection</span>
    <span>COUNT<b id="sel-count">0</b></span>
    <span>SUM<b id="sel-sum">0.00</b></span>
    <span>AVERAGE<b id="sel-avg">0.00</b></span>
  </div>
</div>

<div style="margin-top:12px">
  <button class="btn-add-row" onclick="addRow()">+ Add Entry</button>
</div>

<?php
function renderRow(array $r, int $rIdx, array $cats): string {
    $d   = fn($k) => htmlspecialchars($r[$k] ?? '');
    $fmt = fn($v) => number_format((float)$v, 2);
    $rowTotal = 0.0;
    foreach ($cats as $key => $label) $rowTotal += (float)($r[$key] ?? 0);

    $h  = '<td class="td-date-sticky"><input type="date" class="di mono" data-col="entry_date" value="' . $d('entry_date') . '" oninput="markDirty(this)"></td>';
    $h .= '<td><div class="ac-wrap">
             <input type="text" class="di" data-col="company_name" value="' . $d('company_name') . '" placeholder="Type to search…"
                    oninput="markDirty(this);triggerAC(this)" autocomplete="off">
             <div class="ac-list"></div></div></td>';
    $h .= '<td><input type="text" class="di" data-col="description"  value="' . $d('description')  . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td><input type="text" class="di" data-col="address"      value="' . $d('address')       . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td><input type="text" class="di mono" data-col="invoice_no" value="' . $d('invoice_no')  . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td class="total-td" data-r="' . $rIdx . '" data-c="__total"><input type="text" class="di num total-ro" data-col="__total" value="' . $fmt($rowTotal) . '" readonly tabindex="-1"></td>';
    foreach ($cats as $key => $label) {
        $val = $fmt($r[$key] ?? 0);
        $h .= '<td class="cat-td" data-r="' . $rIdx . '" data-c="' . $key . '"><input type="text" class="di num cell-input" data-col="' . $key . '" value="' . $val . '" oninput="markDirty(this);calcRowTotal(this)"></td>';
    }
    $h .= '<td class="td-act-sticky" style="padding:4px 5px;text-align:center">
             <button class="bsv" onclick="saveRow(this)">Update</button>
             <button class="bdel" onclick="deleteRow(this)">Del</button></td>';
    return $h;
}
?>

<script>
const CAT_KEYS = <?= json_encode($NUM_COLS) ?>;

let newRowCounter = 0;
let acTimer = null;

// ── Dirty flag ─────────────────────────────────────────────
function markDirty(el) {
    const row = el.closest('tr');
    const btn = row?.querySelector('.bsv');
    if (btn) { btn.textContent = row.dataset.id ? 'Update*' : 'Save'; btn.className = 'bsv'; }
}

// ── Row total = plain sum of that row's category cells (no VAT math) ──
function calcRowTotal(el) {
    const row = el.closest('tr');
    let sum = 0;
    row.querySelectorAll('.cell-input').forEach(inp => {
        sum += parseFloat((inp.value || '0').replace(/,/g,'')) || 0;
    });
    const totEl = row.querySelector('[data-col="__total"]');
    if (totEl) totEl.value = sum.toFixed(2);
    updateTotals();
}

// ── Autocomplete (name + address only) ──────────────────────
function triggerAC(inp) {
    clearTimeout(acTimer);
    acTimer = setTimeout(() => doAC(inp), 220);
}

async function doAC(inp) {
    const q = inp.value.trim();
    const list = inp.closest('.ac-wrap').querySelector('.ac-list');
    if (q.length < 2) { list.classList.remove('open'); return; }
    try {
        const res  = await fetch('demic_jaro_vat_computation.php?vendor_lookup=1&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.length) { list.classList.remove('open'); return; }
        list.innerHTML = data.map(v => `
          <div class="ac-item" data-name="${escHtml(v.company_name)}" data-addr="${escHtml(v.address)}" onclick="fillVendor(this)">
            <div class="ac-name">${escHtml(v.company_name)}</div>
            ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
          </div>`).join('');
        positionAC(inp, list);
        list.classList.add('open');
    } catch(e) {}
}

function positionAC(inp, list) {
    const rect = inp.getBoundingClientRect();
    const listW = Math.max(rect.width, 280);
    let left = rect.left;
    if (left + listW > window.innerWidth - 8) left = window.innerWidth - listW - 8;
    list.style.width = listW + 'px';
    list.style.left  = left + 'px';
    const maxH = 220;
    const spaceBelow = window.innerHeight - rect.bottom;
    if (spaceBelow < maxH + 8 && rect.top > maxH + 8) {
        list.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
        list.style.top = 'auto';
    } else {
        list.style.top = (rect.bottom + 4) + 'px';
        list.style.bottom = 'auto';
    }
}

function fillVendor(item) {
    const row = item.closest('tr');
    row.querySelector('[data-col="company_name"]').value = item.dataset.name;
    row.querySelector('[data-col="address"]').value      = item.dataset.addr;
    document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
    markDirty(row.querySelector('[data-col="company_name"]'));
    row.querySelector('[data-col="invoice_no"]')?.focus();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});
document.addEventListener('scroll', e => {
    if (e.target.closest && e.target.closest('.ac-list')) return;
    document.querySelectorAll('.ac-list.open').forEach(l => l.classList.remove('open'));
}, true);

// ── Add row ────────────────────────────────────────────────
function addRow() {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('dsb-tbody');
    const today = new Date().toISOString().split('T')[0];
    const rIdx  = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.id = 'new_' + (++newRowCounter); tr.dataset.id = ''; tr.dataset.r = rIdx;

    let cats = '';
    CAT_KEYS.forEach(key => {
        cats += `<td class="cat-td" data-r="${rIdx}" data-c="${key}"><input type="text" class="di num cell-input" data-col="${key}" value="0.00" oninput="markDirty(this);calcRowTotal(this)"></td>`;
    });

    tr.innerHTML = `
      <td class="td-date-sticky"><input type="date" class="di mono" data-col="entry_date" value="${today}" oninput="markDirty(this)"></td>
      <td><div class="ac-wrap"><input type="text" class="di" data-col="company_name" value="" placeholder="Type to search…" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td><input type="text" class="di" data-col="description" value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="di" data-col="address"     value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="di mono" data-col="invoice_no" value="" placeholder="—" oninput="markDirty(this)"></td>
      <td class="total-td" data-r="${rIdx}" data-c="__total"><input type="text" class="di num total-ro" data-col="__total" value="0.00" readonly tabindex="-1"></td>
      ${cats}
      <td class="td-act-sticky" style="padding:4px 5px;text-align:center">
        <button class="bsv" onclick="saveRow(this)">Save</button>
      </td>`;
    tbody.appendChild(tr);
    tr.querySelector('[data-col="company_name"]')?.focus();
    attachSelectionHandlers(tr);
    updateTotals();
}

// ── Save row ───────────────────────────────────────────────
async function saveRow(btn) {
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    btn.textContent = '…'; btn.className = 'bsv saving';
    const fd = new FormData();
    fd.append(id ? 'ajax_update' : 'ajax_add', '1');
    if (id) fd.append('id', id);
    row.querySelectorAll('[data-col]').forEach(el => {
        if (el.dataset.col === '__total') return; // computed client-side, not stored
        fd.append(el.dataset.col, el.value || '');
    });
    try {
        const res  = await fetch('demic_jaro_vat_computation.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            btn.textContent = 'Update'; btn.className = 'bsv ok';
            if (!id && data.id) {
                row.dataset.id = data.id; row.id = 'row' + data.id;
                const del = document.createElement('button');
                del.className = 'bdel'; del.textContent = 'Del';
                del.onclick = function(){ deleteRow(this); };
                btn.parentElement.appendChild(del);
            }
            setTimeout(() => { if (btn.className.includes('ok')) btn.className = 'bsv'; }, 2000);
            showToast('✓ Entry saved', 'success');
            updateTotals();
        } else {
            btn.textContent = 'Error'; btn.className = 'bsv err';
            showToast('❌ ' + data.msg, 'error');
            setTimeout(() => { btn.textContent = id ? 'Update' : 'Save'; btn.className = 'bsv'; }, 3000);
        }
    } catch(e) {
        btn.textContent = 'Error'; btn.className = 'bsv err';
        showToast('❌ Network error', 'error');
    }
}

// ── Delete row ─────────────────────────────────────────────
async function deleteRow(btn) {
    if (!confirm('Delete this entry?')) return;
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    if (!id) { row.remove(); updateTotals(); return; }
    const fd = new FormData(); fd.append('ajax_delete','1'); fd.append('id', id);
    try {
        const res  = await fetch('demic_jaro_vat_computation.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            row.style.opacity = '0'; row.style.transition = 'opacity .3s';
            setTimeout(() => { row.remove(); updateTotals(); }, 300);
            showToast('✓ Entry deleted', 'success');
        } else { showToast('❌ ' + data.msg, 'error'); }
    } catch(e) { showToast('❌ Network error', 'error'); }
}

// ── Live column / grand totals (plain sums — no VAT) ────────
function updateTotals() {
    const colSums = {}; CAT_KEYS.forEach(k => colSums[k] = 0);
    let grand = 0;
    document.querySelectorAll('#dsb-tbody tr[data-id], #dsb-tbody tr[id^="new_"]').forEach(row => {
        CAT_KEYS.forEach(k => {
            const v = parseFloat((row.querySelector(`[data-col="${k}"]`)?.value || '0').replace(/,/g,'')) || 0;
            colSums[k] += v; grand += v;
        });
    });
    CAT_KEYS.forEach(k => {
        const txt = colSums[k].toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
        const top = document.getElementById('tot-' + k); if (top) top.textContent = txt;
        const bot = document.getElementById('tfoot-' + k); if (bot) bot.textContent = txt;
    });
    const grandTxt = grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('tot-grand').textContent = grandTxt;
    document.getElementById('tfoot-grand').textContent = grandTxt;
    const count = document.querySelectorAll('#dsb-tbody tr[data-id], #dsb-tbody tr[id^="new_"]').length;
    document.getElementById('dsb-count').textContent = count;
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'flash flash-' + (type || 'success') + ' toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ================================================================
// Excel-style cell selection: click-and-drag to select a rectangle
// of amount cells (any category, any rows, including the TOTAL
// column), then show COUNT / SUM / AVERAGE — same idea as Excel's
// bottom status bar. Click a category header to select that whole
// column. "Select All Amounts" selects every amount cell.
// ================================================================
let selAnchor = null;   // {r, ci}
let selEnd    = null;   // {r, ci}
let isDragging = false;
const COLS = ['__total', ...CAT_KEYS]; // column order including TOTAL

function colIndex(key) { return COLS.indexOf(key); }

function clearSelection() {
    document.querySelectorAll('td.cell-selected').forEach(td => td.classList.remove('cell-selected'));
}

function applySelection() {
    clearSelection();
    if (!selAnchor || !selEnd) return;
    const r1 = Math.min(selAnchor.r, selEnd.r), r2 = Math.max(selAnchor.r, selEnd.r);
    const c1 = Math.min(selAnchor.ci, selEnd.ci), c2 = Math.max(selAnchor.ci, selEnd.ci);
    let count = 0, sum = 0;
    for (let r = r1; r <= r2; r++) {
        for (let ci = c1; ci <= c2; ci++) {
            const key = COLS[ci];
            const td = document.querySelector(`td[data-r="${r}"][data-c="${key}"]`);
            if (!td) continue;
            td.classList.add('cell-selected');
            const inp = td.querySelector('input');
            const v = parseFloat((inp?.value || '0').replace(/,/g,'')) || 0;
            sum += v; count++;
        }
    }
    document.getElementById('sel-count').textContent = count;
    document.getElementById('sel-sum').textContent = sum.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('sel-avg').textContent = (count ? sum / count : 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}

function cellCoord(td) {
    const r  = parseInt(td.dataset.r, 10);
    const ci = colIndex(td.dataset.c);
    if (isNaN(r) || ci < 0) return null;
    return { r, ci };
}

function attachSelectionHandlers(scope) {
    scope.querySelectorAll('td.cat-td, td.total-td').forEach(td => {
        if (td.dataset.selBound) return;
        td.dataset.selBound = '1';
        td.addEventListener('mousedown', e => {
            const coord = cellCoord(td);
            if (!coord) return;
            isDragging = true;
            selAnchor = coord; selEnd = coord;
            applySelection();
            // allow the click to still focus/edit the input normally
        });
        td.addEventListener('mouseenter', e => {
            if (!isDragging || e.buttons !== 1) return;
            const coord = cellCoord(td);
            if (!coord) return;
            selEnd = coord;
            applySelection();
        });
    });
}

document.addEventListener('mouseup', () => { isDragging = false; });

function selectColumn(key) {
    const rows = document.querySelectorAll('#dsb-tbody tr[data-r]');
    if (!rows.length) return;
    let maxR = 0;
    rows.forEach(row => { const r = parseInt(row.dataset.r, 10); if (!isNaN(r) && r > maxR) maxR = r; });
    const ci = colIndex(key);
    selAnchor = { r: 0, ci }; selEnd = { r: maxR, ci };
    applySelection();
}

function selectAllCells() {
    const rows = document.querySelectorAll('#dsb-tbody tr[data-r]');
    if (!rows.length) return;
    let maxR = 0;
    rows.forEach(row => { const r = parseInt(row.dataset.r, 10); if (!isNaN(r) && r > maxR) maxR = r; });
    selAnchor = { r: 0, ci: 0 }; selEnd = { r: maxR, ci: COLS.length - 1 };
    applySelection();
}

// Clicking anywhere outside the table body clears the highlighted range
document.addEventListener('click', e => {
    if (!e.target.closest('#dsb-tbody') && !e.target.closest('.cat-th')) {
        clearSelection();
    }
});

attachSelectionHandlers(document);
</script>
</body>
</html>
