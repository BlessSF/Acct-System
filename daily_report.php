<?php
// ============================================================
//  daily_report.php — Daily Store Report (auto, read-only)
//
//  This used to be a manual CRUD page writing into a legacy
//  `daily_reports` table. Every branch that lands on this page
//  now has its own Sales Report page (Stella/Dois/H/H Carwash/
//  Pub Express/Recovery/Commissary) feeding live figures straight
//  from lib/branch_sales.php's $SALES_UNION — same source the
//  Dashboard uses — so nobody needs to re-type anything here.
//  It's saved once, on the branch's own Sales/OIC Report page, and
//  shows up here automatically. Commissary saves on its OIC Daily
//  Report page (commissary/commissary_oic_report.php).
//
//  Every store except Commissary and the two Demic Lab stores gets the
//  editable OIC Report panel on the right of each day's card (H keeps
//  its own h_oic_report + Sales Summary tab; Stella / Dois / Pub Express /
//  Recovery / H Carwash save to branch_oic_report).
//
//  The two Demic Lab stores still use their own separate, manually-
//  entered daily report page (linked below) since their column
//  shape doesn't line up with this generic view.
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();
enforceCashierAccess();
require_once 'lib/branch_colors.php';
require_once 'lib/branch_sales.php';
require_once 'lib/audit_log.php';

$pdo  = getPDO();
$user = currentUser();
$SALES_UNION = branchSalesUnionTable();
// Repair: one saved report per store + date (duplicates from older saves are merged, newest wins)
foreach (['stella_sales_report','dois_sales_report','pub_express_sales_report','h_sales_report','recovery_sales_report','commissary_oic_report','h_carwash_report'] as $__t) ensureUniqueStoreDate($pdo, $__t);
ensureBranchOicTable($pdo); ensureHOicTable($pdo);

/**
 * Posted OIC Report fields → [column => value] for the INSERT … ON DUPLICATE KEY UPDATE.
 * Money / target fields can't go negative; the Last Order time is HH:MM (or NULL when blank).
 * If the new-format columns couldn't be added to $table (host refused the ALTER), they're left
 * out so the original fields still save.
 */
function oicPostedValues(string $table): array {
    $num = ['gulay_commissary','gulay','seafoods','direct_purchases','grab_discount',
            'kitchen','frontline','overtime','undertime',
            'quota_target','cogs_threshold','mp_threshold','np_threshold'];
    $v = [];
    foreach ($num as $k) $v[$k] = max(0, (float)($_POST[$k] ?? 0));
    $t = trim((string)($_POST['last_order_time'] ?? ''));
    $v['last_order_time'] = preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t) ? $t : null;
    if (!oicNewColumnsReady($table)) {
        foreach (array_keys(oicNewColumns()) as $k) unset($v[$k]);
    }
    return $v;
}

/** The saved OIC Report inputs (COGS split, manpower, targets, last order) for one store + date, or []. */
function loadOicRow(PDO $pdo, string $store, string $date): array {
    $table = ($store === 'H') ? 'h_oic_report' : 'branch_oic_report';
    $st = $pdo->prepare("SELECT * FROM `$table` WHERE store_name=? AND report_date=?");
    $st->execute([$store, $date]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Some branch logins cover more than one storefront under the same
// account (H covers both the regular store and H Carwash). Map each
// branch login to the extra store_name(s) it's allowed to switch to
// via view_branch (and to save an OIC Report for).
$BRANCH_SIBLINGS = [
    'H' => ['H', 'H Carwash'],
];

// ── H OIC Report — inline save (editable right on this page) ──
// COGS split / manpower / per-day targets for H. Sales Summary edits
// go straight to h/h_sales_report.php's own ajax_save, so the figures
// always match what the H Sales Report page computes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_h_oic') {
    header('Content-Type: application/json');
    try {
        if (isBranch() && currentBranch() !== 'H') { echo json_encode(['ok'=>false,'msg'=>'Not allowed.']); exit; }
        $d = $_POST['report_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { echo json_encode(['ok'=>false,'msg'=>'Invalid date.']); exit; }
        // Cashiers can edit the OIC Report, but a date locked by management stays read-only for them.
        if (isCashier()) {
            $lk = $pdo->prepare("SELECT 1 FROM report_locks WHERE store_name='H' AND report_date=? LIMIT 1");
            $lk->execute([$d]);
            if ($lk->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'This date is locked. Ask accounting to unlock it before editing.']); exit; }
        }
        ensureHOicTable($pdo);
        $vals = oicPostedValues('h_oic_report');
        $f    = array_keys($vals);
        $cols = array_merge(['store_name','report_date'], $f, ['saved_by']);
        $data = array_merge(['H', $d], array_values($vals), [$user['name']]);
        $upd  = implode(',', array_map(fn($c)=>"`$c`=VALUES(`$c`)", array_merge($f, ['saved_by'])));
        $pdo->prepare("INSERT INTO h_oic_report (" . implode(',', array_map(fn($c)=>"`$c`", $cols)) . ")
                       VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")
                       ON DUPLICATE KEY UPDATE $upd")->execute($data);
        logAudit($pdo, 'H', 'OIC Report', 'updated', $d, 'Saved OIC report (COGS / manpower / targets)', $vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── OIC Report for every other branch — inline save (same panel as H) ──
// COGS split / manpower / per-day targets for Stella, Dois, Pub Express,
// Recovery and H Carwash, kept in branch_oic_report (one row per store + date).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_oic') {
    header('Content-Type: application/json');
    try {
        $store = trim($_POST['store'] ?? '');
        if (!in_array($store, oicPanelStores(), true)) { echo json_encode(['ok'=>false,'msg'=>'Unknown branch.']); exit; }
        // A branch login can only save for its own storefront(s); management can save for any.
        if (isBranch()) {
            $mine = $BRANCH_SIBLINGS[currentBranch()] ?? [currentBranch()];
            if (!in_array($store, $mine, true)) { echo json_encode(['ok'=>false,'msg'=>'Not allowed.']); exit; }
        }
        $d = $_POST['report_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { echo json_encode(['ok'=>false,'msg'=>'Invalid date.']); exit; }
        // Same rule as H: a date locked by management stays read-only for Cashier.
        if (isCashier()) {
            $lk = $pdo->prepare("SELECT 1 FROM report_locks WHERE store_name=? AND report_date=? LIMIT 1");
            $lk->execute([oicLockStore($store), $d]);
            if ($lk->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'This date is locked. Ask accounting to unlock it before editing.']); exit; }
        }
        ensureBranchOicTable($pdo);
        $vals = oicPostedValues('branch_oic_report');
        $f    = array_keys($vals);
        $cols = array_merge(['store_name','report_date'], $f, ['saved_by']);
        $data = array_merge([$store, $d], array_values($vals), [$user['name']]);
        $upd  = implode(',', array_map(fn($c)=>"`$c`=VALUES(`$c`)", array_merge($f, ['saved_by'])));
        $pdo->prepare("INSERT INTO branch_oic_report (" . implode(',', array_map(fn($c)=>"`$c`", $cols)) . ")
                       VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")
                       ON DUPLICATE KEY UPDATE $upd")->execute($data);
        logAudit($pdo, $store, 'OIC Report', 'updated', $d, 'Saved OIC report (COGS / manpower / targets)', $vals);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

/** Everything the H side panel needs for one date (OIC row + Sales Summary base figures). */
function hSideData(PDO $pdo, string $date): array {
    $one = function (string $sql, array $p) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: []; };
    $s = $one("SELECT * FROM h_sales_report WHERE store_name='H' AND report_date=?", [$date]);
    $o = $one("SELECT * FROM h_oic_report   WHERE store_name='H' AND report_date=?", [$date]);

    $di = $one("SELECT COALESCE(SUM(cash),0) cash, COALESCE(SUM(palawan_pay),0) gcash,
                       COALESCE(SUM(card_swipe_qr),0) card, COALESCE(SUM(unpaid_credit_amount),0) unpaid,
                       COALESCE(SUM(discount),0) discount, COALESCE(SUM(bank_transfer_cheque),0) bank
                FROM h_dinein_rows WHERE store_name='H' AND report_date=?", [$date]);
    $st = $pdo->prepare("SELECT section, COALESCE(SUM(amount),0) t FROM h_sales_detail_rows
                         WHERE store_name='H' AND report_date=? GROUP BY section");
    $st->execute([$date]);
    $sec = []; foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $sec[$r['section']] = (float)$r['t'];
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount_card),0)+COALESCE(SUM(amount_cash),0)+COALESCE(SUM(amount_qr),0) t
                         FROM h_extra_sales_rows WHERE store_name='H' AND report_date=? AND section='bar_sales'");
    $st->execute([$date]);
    $bar = (float)$st->fetchColumn();

    $locked = false;
    try { $st = $pdo->prepare("SELECT 1 FROM report_locks WHERE store_name='H' AND report_date=? LIMIT 1");
          $st->execute([$date]); $locked = (bool)$st->fetchColumn(); } catch (Throwable $e) {}

    return [
        's' => $s, 'o' => $o, 'locked' => $locked,
        'diTotal'   => (float)$di['cash'] + (float)$di['gcash'] + (float)$di['card'] + (float)$di['unpaid'],
        'unpaid'    => (float)$di['unpaid'],   'gcash' => (float)$di['gcash'],
        'discount'  => (float)$di['discount'], 'bank'  => (float)$di['bank'],
        'marketing' => $sec['marketing_pullout'] ?? 0, 'grab' => $sec['grab'] ?? 0,
        'pcf'       => $sec['expenses'] ?? 0,          'gift' => $sec['gc_sponsorship'] ?? 0,
        'bar'       => $bar,
    ];
}

// ── Management "view branch" filter ─────────────────────────
// Branch accounts only ever see their own store (branchFilter()
// below already enforces that). Management/Accounting Head see
// every branch by default, but can click a branch card — same
// widget as the Dashboard's "Viewing Branch" row — to narrow the
// list down to one store at a time. Keep in sync with dashboard.php.
$ALL_BRANCH_LABELS = ['Stella','Dois','H','H Carwash','Pub Express','Commissary','Recovery','DemicLab-Main','DemicLab-Jaro'];
$isManagerView = !isBranch();

// ($BRANCH_SIBLINGS — which extra storefronts a branch login may switch to,
// so the "Carwash Daily Report" nav link works — is defined at the top.)

$selBranch = trim($_GET['view_branch'] ?? '');
$viewBranch = '';
if ($isManagerView) {
    $viewBranch = in_array($selBranch, $ALL_BRANCH_LABELS, true) ? $selBranch : '';
} else {
    $ownBranch = currentBranch();
    $allowed   = $BRANCH_SIBLINGS[$ownBranch] ?? [$ownBranch];
    $viewBranch = in_array($selBranch, $allowed, true) ? $selBranch : $ownBranch;
}

// ── Filters ───────────────────────────────────────────────
$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// Optional exact-date filter (?date=YYYY-MM-DD narrows the list below
// to just that one day) — same pattern as Commissary's OIC Daily Report.
$fDate = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDate)) $fDate = '';

if ($fDate !== '') {
    // A specific date filter always wins and drives which month/year the
    // tabs above show as active, so the filtered day is visibly in view.
    $fYear  = (int)date('Y', strtotime($fDate));
    $fMonth = (int)date('n', strtotime($fDate));
} else {
    $fYear  = (int)($_GET['year']  ?? date('Y'));
    $fMonth = (int)($_GET['month'] ?? date('n'));
    if ($fMonth < 1 || $fMonth > 12) $fMonth = (int)date('n');
}

// $viewBranch is always resolved above (to a real store for branch
// accounts, or '' for "All Branches" on the manager view), so it's
// the single source of truth for the store filter here.
if ($viewBranch !== '') {
    $bClause = "`store_name` = ?";
    $bParams = [$viewBranch];
} else {
    [$bClause, $bParams] = branchFilter('store_name');
}
if ($fDate !== '') {
    $rows = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE report_date=? AND $bClause ORDER BY report_date DESC");
    $rows->execute(array_merge([$fDate], $bParams));
} else {
    $rows = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=? AND $bClause ORDER BY report_date DESC");
    $rows->execute(array_merge([$fYear, $fMonth], $bParams));
}
$reports = $rows->fetchAll();
// One card per store + day (safety net; the data is also repaired below).
$__seen = []; $__uniq = [];
foreach ($reports as $__r) { $__k = $__r['store_name'] . '|' . $__r['report_date']; if (isset($__seen[$__k])) continue; $__seen[$__k] = 1; $__uniq[] = $__r; }
$reports = $__uniq;

// ── Excel (.xlsx) download of the OIC Report — same line items, order and flags as the OIC Excel sheet ──
// ?export_oic=1 → the month on screen (or the single ?date=), for the branch being viewed. A card's own
// "⬇ Excel" button passes date + view_branch, so it downloads just that day. Commissary and Demic Lab
// keep their own report pages/exports, so they're skipped here.
if (isset($_GET['export_oic'])) {
    require_once __DIR__ . '/lib/xlsx_writer.php';

    $exportRows = array_values(array_filter($reports, fn($r) => $r['store_name'] === 'H' || in_array($r['store_name'], oicPanelStores(), true)));
    usort($exportRows, fn($a, $b) => strcmp($a['report_date'], $b['report_date']) ?: strcmp($a['store_name'], $b['store_name']));

    $n    = fn($v) => number_format((float)$v, 2, '.', '');
    $pc   = fn($v) => $v . '%';
    $flag = fn($lbl, $ok) => $lbl . ($ok ? ' PASSED' : ' FAILED');
    $cfPct = (int)round(OIC_GRAB_CF_RATE * 100);

    $scope    = $viewBranch !== '' ? $viewBranch : 'All Branches';
    $subtitle = ($fDate !== '' ? date('F j, Y', strtotime($fDate)) : $monthNames[$fMonth] . ' ' . $fYear) . ' · ' . $scope;
    $filename = 'OIC_' . preg_replace('/[^A-Za-z0-9]+/', '_', $scope) . '_'
              . ($fDate !== '' ? date('Y-m-d', strtotime($fDate)) : $monthNames[$fMonth] . '_' . $fYear) . '.xlsx';

    $xlsx = new SimpleXlsxWriter(branchAccentColor($viewBranch !== '' ? $viewBranch : 'H'));
    $xlsx->title('OIC DAILY REPORT');
    $xlsx->subtitle($subtitle);
    $xlsx->blank();

    foreach ($exportRows as $r) {
        $o  = loadOicRow($pdo, $r['store_name'], $r['report_date']);
        $m  = calcMetrics($r);
        $g  = fn($k) => (float)($o[$k] ?? 0);
        $grab = (float)($r['grab_sales_amt'] ?? 0);
        $gd   = $g('grab_discount');
        $cf   = max($grab - $gd, 0) * OIC_GRAB_CF_RATE;
        $lot  = !empty($o['last_order_time']) ? substr((string)$o['last_order_time'], 0, 5) : '—';

        $xlsx->sectionRow($r['store_name'] . ' — ' . date('F j, Y', strtotime($r['report_date'])));
        $xlsx->header(['Line Item', 'Amount', '%', 'Flag']);

        $xlsx->totalRow(['GROSS SALES', $n($r['gross_sales']), '', $flag('QUOTA', $m['quotaPassed'])]);
        $xlsx->row(['   GRAB SALES', $n($grab), '', '']);
        $xlsx->totalRow(['SALES DISCOUNT', $n($r['sales_discount']), $pc($m['discP']), '']);
        $xlsx->row(['   GRAB DISCOUNT', $n($gd), '', '']);
        $xlsx->row(['   GRAB CF (auto ' . $cfPct . '%)', $n($cf), '', '']);
        $xlsx->row(['   WALK-IN DISCOUNT (senior & PWD)', $n($r['walkin_discount'] ?? 0), '', '']);
        $xlsx->totalRow(['GROSS PROFIT', $n($m['gp']), '', '']);
        $xlsx->blank();

        $xlsx->sectionRow('COST OF GOODS SOLD');
        $xlsx->row(['BILLING TO COMMISSARY', $n($g('gulay_commissary')), '', '']);
        $xlsx->row(['GULAY', $n($g('gulay')), '', '']);
        $xlsx->row(['SEAFOODS', $n($g('seafoods')), '', '']);
        $xlsx->row(['DIRECT PURCHASES (COGS)', $n($r['direct_purchases']), '', '']);
        $xlsx->totalRow(['TOTAL COGS', $n($m['totalCogs']), $pc($m['cogsP']), $flag('COGS', $m['cogsPassed'])]);
        $xlsx->row(['OTHER EXPENSES (NON COGS)', $n($r['other_expenses']), '', '']);
        $xlsx->totalRow(['TOTAL EXPENSES (COGS + OTHER)', $n($m['totalExp']), '', '']);
        $xlsx->blank();

        $xlsx->sectionRow('MANPOWER ON DUTY');
        $xlsx->row(['   KITCHEN', $n($r['kitchen_manpower']), '', '']);
        $xlsx->row(['   FRONTLINE', $n($r['frontline_manpower']), '', '']);
        $xlsx->row(['   OVERTIME', $n($r['overtime']), '', '']);
        $xlsx->row(['   UNDERTIME', $n($r['undertime']), '', '']);
        $xlsx->totalRow(['TOTAL MANPOWER', $n($m['totalMp']), $pc($m['mpP']), $flag('MP', $m['mpPassed'])]);
        $xlsx->row(['LAST ORDER (TIME)', $lot, '', '']);
        $xlsx->blank();

        $xlsx->totalRow(['TOTAL EXPENSES', $n($m['totalAllExp']), $pc($m['totalExpP']), '']);
        $xlsx->totalRow(['PROFIT (- LOSS)', $n($m['profitLoss']), $pc($m['npP']), $flag('NP', $m['npPassed'])]);
        $xlsx->blank();

        $xlsx->sectionRow('TARGETS USED');
        $xlsx->row(['Quota Target', $n($r['quota_target'])]);
        $xlsx->row(['COGS Max % of sales', $n($r['cogs_threshold']) . '%']);
        $xlsx->row(['MP Max % of sales', $n($r['mp_threshold']) . '%']);
        $xlsx->row(['NP Min % of sales', $n($r['np_threshold']) . '%']);
        $xlsx->blank();
        $xlsx->blank();
    }
    if (!$exportRows) $xlsx->row(['No OIC reports for this selection.']);

    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([40, 16, 10, 18]);
    $xlsx->output($filename);
}

// ── Metrics helper ────────────────────────────────────────
// One shared definition (lib/branch_sales.php) so the Dashboard and this page always agree.
function calcMetrics(array $r): array {
    return oicCalcMetrics($r);
}

/**
 * The OIC Report pane — laid out like the OIC Excel sheet, top to bottom:
 *   GROSS SALES (Grab Sales) · SALES DISCOUNT (Grab Discount, Grab CF, Walk-in Discount) · GROSS PROFIT ·
 *   COGS (Billing to Commissary, Gulay, Seafoods, Direct Purchases) · OTHER EXPENSES (NON COGS) · TOTAL EXPENSES ·
 *   MANPOWER ON DUTY (Kitchen, Frontline, Overtime, Undertime) · LAST ORDER TIME · TOTAL EXPENSES · PROFIT (− LOSS)
 * with the % column (share of Gross Sales) and the QUOTA / COGS / MP / NP flags on the right.
 * Shared by H and every other branch's panel. White cells = typed in here; grey = pulled from the Sales Report.
 * Computed cells are filled by drOic() (same maths as oicCalcMetrics() in lib/branch_sales.php).
 */
function renderOicPane(array $r, array $o, bool $canOic, string $store = '', string $date = ''): void {
    $ov  = fn($k, $def = 0) => isset($o[$k]) ? (float)$o[$k] : $def;
    $inp = fn($v) => ((float)$v) != 0 ? rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') : '';
    $fmt = fn($n) => number_format((float)$n, 2);
    $dis = $canOic ? '' : 'disabled';
    $in  = fn($k, $def = 0) => '<input type="number" step="0.01" class="dr-in" data-f="'.$k.'" value="'.$inp($ov($k, $def)).'" placeholder="0.00" oninput="drOic(this)" '.$dis.'>';
    $lot = isset($o['last_order_time']) && $o['last_order_time'] ? substr((string)$o['last_order_time'], 0, 5) : '';
    $cfPct = (int)round(OIC_GRAB_CF_RATE * 100);
    $dl = ($store !== '' && $date !== '')
        ? '?export_oic=1&date=' . urlencode($date) . '&view_branch=' . urlencode($store) : '';
    ?>
      <!-- ── OIC Report ── -->
      <div class="dr-pane" data-pane="oic">
        <div class="oic-g oic-head"><span>Line item</span><span>Amount</span><span>%</span><span></span></div>

        <div class="oic-g oic-tot"><span>GROSS SALES <small>(auto)</small></span><div class="dr-ro auto" data-o="gross"><?= $fmt($r['gross_sales']) ?></div><em></em><em data-o="fQuota"></em></div>
        <div class="oic-g oic-sub"><span>Grab Sales <small>(auto)</small></span><div class="dr-ro auto"><?= $fmt($r['grab_sales_amt'] ?? 0) ?></div><em></em><em></em></div>

        <div class="oic-g oic-tot"><span>SALES DISCOUNT</span><div class="dr-ro" data-o="disc">—</div><em data-o="discP"></em><em></em></div>
        <div class="oic-g oic-sub"><span>Grab Discount</span><?= $in('grab_discount') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Grab CF <small>(auto <?= $cfPct ?>%)</small></span><div class="dr-ro" data-o="cf">—</div><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Walk-in Discount <small>(senior &amp; PWD · auto)</small></span><div class="dr-ro auto"><?= $fmt($r['walkin_discount'] ?? 0) ?></div><em></em><em></em></div>

        <div class="oic-g oic-tot oic-gp"><span>GROSS PROFIT</span><div class="dr-ro" data-o="gp">—</div><em></em><em></em></div>

        <div class="oic-sp"></div>
        <div class="oic-g oic-sub"><span>Billing to Commissary</span><?= $in('gulay_commissary') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Gulay</span><?= $in('gulay') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Seafoods</span><?= $in('seafoods') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Direct Purchases <small>(COGS)</small></span><?= $in('direct_purchases') ?><em></em><em></em></div>
        <div class="oic-g oic-tot"><span>TOTAL COGS</span><div class="dr-ro" data-o="cogs">—</div><em data-o="cogsP"></em><em data-o="fCogs"></em></div>
        <div class="oic-g oic-sub"><span>Other Expenses <small>(non-COGS · auto)</small></span><div class="dr-ro auto"><?= $fmt($r['other_expenses']) ?></div><em></em><em></em></div>
        <div class="oic-g oic-tot"><span>TOTAL EXPENSES <small>(COGS + other)</small></span><div class="dr-ro" data-o="totExp">—</div><em></em><em></em></div>

        <div class="oic-sec">Manpower on duty</div>
        <div class="oic-g oic-sub"><span>Kitchen</span><?= $in('kitchen') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Frontline</span><?= $in('frontline') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Overtime</span><?= $in('overtime') ?><em></em><em></em></div>
        <div class="oic-g oic-sub"><span>Undertime</span><?= $in('undertime') ?><em></em><em></em></div>
        <div class="oic-g oic-tot"><span>TOTAL MANPOWER</span><div class="dr-ro" data-o="mp">—</div><em data-o="mpP"></em><em data-o="fMp"></em></div>

        <div class="oic-g oic-sub"><span>Last Order <small>(time)</small></span><input type="time" class="dr-in" data-f="last_order_time" value="<?= htmlspecialchars($lot) ?>" <?= $dis ?>><em></em><em></em></div>

        <div class="oic-g oic-tot"><span>TOTAL EXPENSES</span><div class="dr-ro" data-o="all">—</div><em data-o="allP"></em><em></em></div>
        <div class="oic-g oic-tot oic-pl" data-o="plRow"><span>PROFIT (− LOSS)</span><div class="dr-ro" data-o="pl">—</div><em data-o="npP"></em><em data-o="fNp"></em></div>

        <div class="dr-sec"><span>Targets for this day</span></div>
        <div class="dr-row"><span>Quota (₱)</span><input type="number" step="0.01" class="dr-in" data-f="quota_target" value="<?= $inp($ov('quota_target', 9000)) ?>" oninput="drOic(this)" <?= $dis ?>></div>
        <div class="dr-row"><span>COGS max (% of sales)</span><input type="number" step="0.01" class="dr-in" data-f="cogs_threshold" value="<?= $inp($ov('cogs_threshold', 30)) ?>" oninput="drOic(this)" <?= $dis ?>></div>
        <div class="dr-row"><span>MP max (% of sales)</span><input type="number" step="0.01" class="dr-in" data-f="mp_threshold" value="<?= $inp($ov('mp_threshold', 35)) ?>" oninput="drOic(this)" <?= $dis ?>></div>
        <div class="dr-row"><span>NP min (% of sales)</span><input type="number" step="0.01" class="dr-in" data-f="np_threshold" value="<?= $inp($ov('np_threshold', 20)) ?>" oninput="drOic(this)" <?= $dis ?>></div>

        <div class="dr-foot">
          <span class="dr-status" data-o="status"><?= $canOic ? '' : '🔒 This date is locked for Cashier.' ?></span>
          <?php if ($dl !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($dl, ENT_QUOTES) ?>">⬇ Excel</a><?php endif; ?>
          <?php if ($canOic): ?><button type="button" class="btn btn-primary btn-sm" onclick="drSaveOic(this)">Save OIC Report</button><?php endif; ?>
        </div>
      </div>
    <?php
}

/** What drOic() needs to redo the maths in the browser — the figures that come from the Sales Report, not typed here. */
function oicBaseData(array $r): array {
    return [
        'gross'  => (float)$r['gross_sales'],
        'grab'   => (float)($r['grab_sales_amt'] ?? 0),
        'walkin' => (float)($r['walkin_discount'] ?? 0),
        'other'  => (float)$r['other_expenses'],
        'cfRate' => OIC_GRAB_CF_RATE,
    ];
}

/** Right-hand panel for the other branches (Stella / Dois / Pub Express / Recovery / H Carwash): the editable OIC Report. */
function renderOicSidePanel(PDO $pdo, array $r, array $o): void {
    $date  = $r['report_date'];
    $store = $r['store_name'];

    $locked = false;
    try { $lk = $pdo->prepare("SELECT 1 FROM report_locks WHERE store_name=? AND report_date=? LIMIT 1");
          $lk->execute([oicLockStore($store), $date]); $locked = (bool)$lk->fetchColumn(); } catch (Throwable $e) {}
    $canOic = !(isCashier() && $locked);   // Cashier, OIC and management can edit; a locked date is read-only for Cashier
    ?>
    <div class="dr-side" data-date="<?= htmlspecialchars($date) ?>" data-store="<?= htmlspecialchars($store, ENT_QUOTES) ?>"
         data-action="save_oic" data-oic="<?= htmlspecialchars(json_encode(oicBaseData($r)), ENT_QUOTES) ?>">
      <div class="dr-tabs">
        <button type="button" class="dr-tab on" onclick="drTab(this,'oic')">OIC Report</button>
      </div>
      <?php renderOicPane($r, $o, $canOic, $store, $date); ?>
    </div>
    <?php
}

/** Right-hand panel for an H card: editable OIC Report + editable Sales Summary. */
function renderHSidePanel(PDO $pdo, array $r, array $m): void {
    $date = $r['report_date'];
    $D    = hSideData($pdo, $date);
    $s = $D['s']; $o = $D['o'];
    $sv = fn($k) => (float)($s[$k] ?? 0);
    $ov = fn($k, $def = 0) => isset($o[$k]) ? (float)$o[$k] : $def;
    $inp = fn($v) => ((float)$v) != 0 ? rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') : '';
    $fmt = fn($n) => number_format((float)$n, 2);

    $canOic = !(isCashier() && $D['locked']);     // Cashier, OIC and management can edit; a locked date is read-only for Cashier
    $roSum  = isCashier() && $D['locked'];        // locked dates are read-only for Cashier only
    $dis    = fn($flag) => $flag ? 'disabled' : '';

    // Sales Summary — same chain as h_sales_report.php
    $carwash = $sv('carwash_sales'); $svc = $sv('service_charge');
    $z     = $D['diTotal'] + $D['marketing'] + $D['grab'] + $D['gift'] + $D['bar'] + $carwash;
    $gross = $z - $svc + $D['discount'] - $D['marketing'];
    $net   = $gross - $sv('maya_swipe') - $D['unpaid'] - $D['grab'] - $D['gcash'] - $D['gift']
                    - $D['bank'] - $D['pcf'] - $sv('other_expenses') - $D['discount'];
    $short = $sv('coh') - $net;

    $oicData = oicBaseData($r);
    $sumData = [
        'di' => $D['diTotal'], 'marketing' => $D['marketing'], 'grab' => $D['grab'], 'gift' => $D['gift'], 'bar' => $D['bar'],
        'discount' => $D['discount'], 'unpaid' => $D['unpaid'], 'gcash' => $D['gcash'], 'bank' => $D['bank'], 'pcf' => $D['pcf'],
    ];
    $auto = [ // label, value, hint
        ['Gross Sales',          $gross,          'auto', 'gross'],
        ['Z Reading Gross',      $z,              'auto', 'z'],
        ['Unpaid Med (Credit)',  $D['unpaid'],    'auto', ''],
        ['Grab Sales',           $D['grab'],      'auto', ''],
        ['Bar Sales',            $D['bar'],       'auto', ''],
        ['G-Cash',               $D['gcash'],     'auto', ''],
        ['Gift Card',            $D['gift'],      'auto', ''],
        ['Marketing Pull Out',   $D['marketing'], 'auto', ''],
        ['Discount',             $D['discount'],  'auto', ''],
        ['Bank Transfer/Cheque', $D['bank'],      'auto', ''],
        ['PCF/Expenses',         $D['pcf'],       'auto', ''],
    ];
    ?>
    <div class="dr-side" data-date="<?= htmlspecialchars($date) ?>"
         data-oic="<?= htmlspecialchars(json_encode($oicData), ENT_QUOTES) ?>"
         data-sum="<?= htmlspecialchars(json_encode($sumData), ENT_QUOTES) ?>"
         data-opening="<?= htmlspecialchars((string)($s['opening_cashier'] ?? ''), ENT_QUOTES) ?>"
         data-closing="<?= htmlspecialchars((string)($s['closing_cashier'] ?? ''), ENT_QUOTES) ?>">
      <div class="dr-tabs">
        <button type="button" class="dr-tab on" onclick="drTab(this,'oic')">OIC Report</button>
        <button type="button" class="dr-tab"    onclick="drTab(this,'sum')">Sales Summary</button>
      </div>

      <?php renderOicPane($r, $o, $canOic, 'H', $date); ?>

      <!-- ── Sales Summary ── -->
      <div class="dr-pane" data-pane="sum" style="display:none">
        <?php if ($roSum): ?><div class="dr-note">🔒 This date is locked for Cashier.</div><?php endif; ?>
        <div class="dr-sec"><span>Summary Report · <?= date('n/j/Y', strtotime($date)) ?></span><span style="text-transform:none;letter-spacing:0">grey = auto from Sales Report</span></div>

        <?php foreach ($auto as [$lbl,$val,$h,$key]): ?>
        <div class="dr-row"><span><?= $lbl ?> <small>(auto)</small></span><div class="dr-ro auto" <?= $key !== '' ? 'data-s="'.$key.'"' : '' ?>><?= $fmt($val) ?></div></div>
        <?php endforeach; ?>

        <div class="dr-row"><span>Service Charge <small>(separate deposit slip)</small></span><input type="number" step="0.01" class="dr-in" data-f="service_charge" value="<?= $inp($sv('service_charge')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Carwash Sales</span><input type="number" step="0.01" class="dr-in" data-f="carwash_sales" value="<?= $inp($sv('carwash_sales')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Total Swipe <small>(auto)</small></span><div class="dr-ro" data-s="swipe"><?= $fmt($sv('deposit_swipe_card')+$sv('late_payment_card')+$sv('maya_swipe')) ?></div></div>
        <div class="dr-row"><span>Deposit Swipe (Card)</span><input type="number" step="0.01" class="dr-in" data-f="deposit_swipe_card" value="<?= $inp($sv('deposit_swipe_card')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Late Payment (Card)</span><input type="number" step="0.01" class="dr-in" data-f="late_payment_card" value="<?= $inp($sv('late_payment_card')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Sales of the day Swipe (MAYA)</span><input type="number" step="0.01" class="dr-in" data-f="maya_swipe" value="<?= $inp($sv('maya_swipe')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Other Expenses</span><input type="number" step="0.01" class="dr-in" data-f="other_expenses" value="<?= $inp($sv('other_expenses')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>

        <div class="dr-tot">
          <div class="dr-row" style="color:#b7791f"><span style="color:#b7791f">Net Sales</span><div class="dr-ro" data-s="net"><?= $fmt($net) ?></div></div>
        </div>
        <div class="dr-row" style="margin-top:4px"><span>COH (Cash on Hand) <small>(actual cash counted)</small></span><input type="number" step="0.01" class="dr-in" data-f="coh" value="<?= $inp($sv('coh')) ?>" placeholder="0.00" oninput="drSum(this)" <?= $dis($roSum) ?>></div>
        <div class="dr-row"><span>Short / Over <small>("−" = short)</small></span><div class="dr-ro" data-s="short"><?= $fmt($short) ?></div></div>
        <div class="dr-row" style="align-items:flex-start"><span style="padding-top:6px">Remarks</span>
          <textarea class="dr-remarks" data-f="remarks" rows="2" maxlength="2000" style="flex:1.6" <?= $dis($roSum) ?>><?= htmlspecialchars((string)($s['remarks'] ?? '')) ?></textarea></div>
        <div class="dr-foot">
          <span class="dr-status" data-s="status"></span>
          <?php if (!$roSum): ?><button type="button" class="btn btn-primary btn-sm" onclick="drSaveSum(this)">Save Sales Summary</button><?php endif; ?>
        </div>
      </div>
    </div>
    <?php
}

// H covers two nav links that both point at this same file (Daily Report
// and Carwash Daily Report) — give the carwash one its own activePage so
// the sidebar highlights only the link actually being viewed, not both.
// Likewise, Manager/Accounting Head's "Daily Report (All Branch)" link
// (no view_branch — every store) gets its own activePage too.
if ($isManagerView && $viewBranch === '') {
    $pageTitle  = 'Daily Report (All Branch)';
    $activePage = 'daily_all';
} elseif ($viewBranch === 'H Carwash') {
    $pageTitle  = 'Carwash Daily Report';
    $activePage = 'daily_carwash';
} else {
    $pageTitle  = 'Daily Report';
    $activePage = 'daily';
}
include 'layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Daily <span>Report</span></div>
    <div class="section-subtitle">Store performance — <?=$monthNames[$fMonth]?> <?=$fYear?> · auto-generated from each branch's Sales Report</div>
  </div>
  <?php if (isManagement()): ?>
  <div style="display:flex;gap:8px;align-items:center">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost">← Back to Dashboard</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($isManagerView): ?>
<div style="margin-bottom:16px">
  <div class="vb-row-label">Viewing Branch <span style="font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0">— filters the list below · click a branch, or All Branches to see every store. Demic Lab keeps its own daily report page, linked below.</span></div>
  <div class="vb-grid">
    <div class="vb-card <?= $viewBranch === '' ? 'active' : '' ?>" onclick="selectDrBranch('')"
         style="border-top-color:#6b7280; <?= $viewBranch === '' ? 'background:#6b72800d;' : '' ?>">
      <div class="vb-card-val" style="color:#6b7280">
        <span class="vb-dot" style="background:#6b7280"></span>All Branches
        <?php if ($viewBranch === ''): ?><span class="vb-check" style="color:#6b7280">✓</span><?php endif; ?>
      </div>
      <div class="vb-card-hint"><?= $viewBranch === '' ? 'Currently viewing' : 'Click to view' ?></div>
    </div>
    <?php foreach ($ALL_BRANCH_LABELS as $b):
      $c = branchAccentColor($b);
      $active = $viewBranch === $b;
      // Demic Lab keeps its own separate reporting table/page (see
      // dashboard.php note) — it doesn't write into `daily_reports`,
      // so route its card straight to the real page instead of
      // filtering here and showing an always-empty list. Commissary is
      // now wired into $SALES_UNION (via commissary_oic_report), so it
      // filters the list here just like every other branch.
      $externalUrl = null;
      if ($b === 'DemicLab-Main' || $b === 'DemicLab-Jaro') {
          $externalUrl = 'demicmain/demic_daily_report.php?store=' . urlencode($b)
                        . '&month=' . $fMonth . '&year=' . $fYear;
      }
      $onclick = $externalUrl
        ? "window.location='" . htmlspecialchars($externalUrl, ENT_QUOTES) . "'"
        : "selectDrBranch('" . htmlspecialchars($b, ENT_QUOTES) . "')";
    ?>
    <div class="vb-card <?= $active ? 'active' : '' ?>" onclick="<?= $onclick ?>"
         style="border-top-color:<?= $c ?>; <?= $active ? "background:{$c}0d;" : '' ?>">
      <div class="vb-card-val" style="color:<?= $c ?>">
        <span class="vb-dot" style="background:<?= $c ?>"></span><?= htmlspecialchars($b) ?>
        <?php if ($active): ?><span class="vb-check" style="color:<?= $c ?>">✓</span><?php endif; ?>
      </div>
      <div class="vb-card-hint"><?= $externalUrl ? 'Own report page →' : ($active ? 'Currently viewing' : 'Click to view') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<style>
.vb-row-label { font-family: var(--font-m); font-size: .62rem; text-transform: uppercase; letter-spacing: .07em; color: var(--subtext); margin-bottom: 8px; }
.vb-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.vb-card {
  background: var(--surface); border: 1px solid var(--border); border-top: 3px solid;
  border-radius: var(--radius); padding: 12px 14px; cursor: pointer; user-select: none;
  transition: box-shadow .15s, background .15s;
}
.vb-card:hover { box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
.vb-card.active { box-shadow: 0 0 0 2px rgba(0,0,0,.06); }
.vb-card-val { font-family: var(--font-h); font-size: .92rem; font-weight: 800; display: flex; align-items: center; gap: 7px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vb-card-hint { font-family: var(--font-m); font-size: .58rem; color: var(--subtext); margin-top: 4px; }
.vb-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(0,0,0,.04); }
.vb-check { margin-left: auto; font-size: .78rem; font-weight: 700; }
</style>
<script>
function selectDrBranch(val) {
  const url = new URL(window.location.href);
  if (val) url.searchParams.set('view_branch', val);
  else url.searchParams.delete('view_branch');
  window.location.href = url.toString();
}
</script>
<?php endif; ?>

<style>
/* H: card on the left, OIC Report / Sales Summary on the right */
.dr-split { display:grid; grid-template-columns:minmax(0,1fr) 480px; gap:12px; align-items:start; }
@media (max-width:1380px){ .dr-split { grid-template-columns:1fr; } }
.dr-side { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.dr-tabs { display:flex; background:var(--surf2); border-bottom:1px solid var(--border); }
.dr-tab { flex:1; padding:11px 8px; font-family:var(--font-m); font-size:.66rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
          border:0; background:transparent; color:var(--subtext); cursor:pointer; border-bottom:2px solid transparent; }
.dr-tab.on { color:var(--accent); border-bottom-color:var(--accent); background:var(--surface); }
.dr-pane { padding:4px 0 0; }
.dr-sec { font-family:var(--font-m); font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext);
          padding:10px 14px 4px; display:flex; justify-content:space-between; }
.dr-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 14px; font-family:var(--font-m); font-size:.7rem; }
.dr-row > span { color:var(--subtext2); flex:1; min-width:0; }
.dr-row small { opacity:.65; font-size:.58rem; }
.dr-in { width:120px; text-align:right; font-family:var(--font-m); font-size:.72rem; padding:4px 7px; border:1px solid var(--border);
         border-radius:6px; background:#fff; color:var(--text); }
.dr-in:disabled { background:var(--surf2); color:var(--subtext); }
.dr-ro { width:120px; text-align:right; font-family:var(--font-m); font-size:.72rem; font-weight:700; padding:4px 7px; border-radius:6px; background:var(--surf2); color:var(--text); }
.dr-ro.auto { background:rgba(236,72,153,.08); }
.dr-tot { margin:6px 14px 0; padding:7px 0 0; border-top:1px solid var(--border); }
.dr-tot .dr-row { padding:2px 0; font-weight:700; }
.dr-badges { display:flex; gap:5px; flex-wrap:wrap; padding:8px 14px 0; }
.dr-badge { font-family:var(--font-m); font-size:.58rem; padding:2px 8px; border-radius:20px; border:1px solid; }
.dr-badge.ok  { background:rgba(34,211,165,.12); color:var(--accent);  border-color:rgba(34,211,165,.25); }
.dr-badge.bad { background:rgba(248,113,113,.12); color:var(--accent2); border-color:rgba(248,113,113,.25); }
.dr-foot { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:12px 14px; }
.dr-status { font-family:var(--font-m); font-size:.64rem; color:var(--subtext); }
.dr-note { padding:8px 14px 0; font-family:var(--font-m); font-size:.62rem; color:var(--subtext); }
.dr-remarks { width:100%; box-sizing:border-box; font-family:var(--font-m); font-size:.7rem; padding:6px 8px; border:1px solid var(--border); border-radius:6px; resize:vertical; }

/* OIC Report pane — same rows as the OIC Excel sheet: label · amount · % of sales · pass/fail flag */
.oic-g { display:grid; grid-template-columns:minmax(0,1fr) 112px 46px 84px; align-items:center; gap:6px; padding:3px 14px;
         font-family:var(--font-m); font-size:.7rem; }
.oic-g > span { color:var(--subtext2); min-width:0; }
.oic-g small { opacity:.65; font-size:.56rem; }
.oic-g .dr-in, .oic-g .dr-ro { width:100%; box-sizing:border-box; }
.oic-g em { font-style:normal; font-size:.62rem; font-weight:700; text-align:center; }
.oic-g em[data-o$="P"] { color:#fff; background:#3b78d8; border-radius:4px; padding:3px 0; }
.oic-g em[data-o$="P"]:empty { background:transparent; }
.oic-head { padding-top:10px; font-size:.56rem; text-transform:uppercase; letter-spacing:.08em; color:var(--subtext); }
.oic-head span { color:var(--subtext) !important; }
.oic-head span:nth-child(n+2) { text-align:center; }
.oic-sub > span:first-child { padding-left:14px; }
.oic-tot { margin-top:3px; padding-top:5px; padding-bottom:5px; border-top:1px solid var(--border); background:var(--surf2); }
.oic-tot > span:first-child { font-weight:700; color:var(--text); }
.oic-gp { background:rgba(245,196,36,.13); }
.oic-pl { background:rgba(245,196,36,.22); border-top:2px solid rgba(245,196,36,.65); }
.oic-pl > span:first-child { font-size:.76rem; }
.oic-sp { height:6px; }
.oic-sec { font-family:var(--font-m); font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; font-style:italic; color:var(--subtext); padding:12px 14px 3px; }
.oic-g .dr-badge { display:block; padding:2px 0; text-align:center; white-space:nowrap; }
</style>

<!-- Month / Year tabs -->
<div class="card" style="padding:12px 18px;margin-bottom:18px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?month=<?=$m?>&year=<?=$fYear?><?= $viewBranch !== '' ? '&view_branch='.urlencode($viewBranch) : '' ?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?= ($fMonth===$m && $fDate==='') ?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>">
      <?=substr($monthNames[$m],0,3)?>
    </a>
    <?php endfor; ?>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <a class="btn btn-ghost btn-sm" style="white-space:nowrap" title="Download the OIC Report (Excel layout) for what's listed below"
         href="?export_oic=1&month=<?=$fMonth?>&year=<?=$fYear?><?= $fDate !== '' ? '&date='.urlencode($fDate) : '' ?><?= $viewBranch !== '' ? '&view_branch='.urlencode($viewBranch) : '' ?>">⬇ OIC Excel</a>
      <input type="date" id="jumpDate" class="form-control" style="padding:5px 10px;font-size:.74rem;max-width:150px"
             value="<?= htmlspecialchars($fDate) ?>"
             title="Filter the list below to a single date">
      <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value+'<?= $viewBranch !== '' ? '&view_branch='.urlencode($viewBranch) : '' ?>'"
              class="form-control" style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px;
                     background:var(--surf2);border:1px solid rgba(34,211,165,.3);color:var(--accent)">
        <?php for($y=2050;$y>=2020;$y--): ?>
        <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
</div>

<?php if ($fDate !== ''): ?>
<div class="card" style="padding:10px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;
                          background:rgba(34,211,165,.05);border:1px solid rgba(34,211,165,.2)">
  <div style="font-family:var(--font-m);font-size:.74rem;color:var(--accent)">
    Filtering by <strong><?= date('F j, Y', strtotime($fDate)) ?></strong>
  </div>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?><?= $viewBranch !== '' ? '&view_branch='.urlencode($viewBranch) : '' ?>" class="btn btn-ghost btn-sm">✕ Clear filter</a>
</div>
<?php endif; ?>

<!-- Records list -->
<div id="reportsListContainer">
<?php if ($reports): ?>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px">
  <?php foreach ($reports as $r):
    $m = calcMetrics($r);
    $extras = $m['extras'];
    $isH = ($r['store_name'] === 'H');
    $isOic = in_array($r['store_name'], oicPanelStores(), true);   // Stella / Dois / Pub Express / Recovery / H Carwash
    $hasSide = $isH || $isOic;
    $o = $hasSide ? loadOicRow($pdo, $r['store_name'], $r['report_date']) : [];   // saved OIC inputs (COGS split etc.)
  ?>
  <?php if ($hasSide): ?><div class="dr-split"><div style="min-width:0"><?php endif; ?>
  <div class="card" style="padding:0;overflow:hidden">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 20px;
                background:var(--surf2);border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:.92rem;font-weight:700"><?=date('F j, Y',strtotime($r['report_date']))?></div>
        <span style="font-family:var(--font-m);font-size:.64rem;color:var(--subtext);
                     background:var(--bg);padding:3px 9px;border-radius:20px;border:1px solid var(--border)">
          <?=htmlspecialchars($r['store_name'])?>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
        <?php foreach([['QUOTA',$m['quotaPassed']],['COGS',$m['cogsPassed']],['MP',$m['mpPassed']],['NP',$m['npPassed']]] as [$lbl,$pass]): ?>
        <span style="font-family:var(--font-m);font-size:.62rem;padding:3px 9px;border-radius:20px;
              background:<?=$pass?'rgba(34,211,165,.12)':'rgba(248,113,113,.12)'?>;
              color:<?=$pass?'var(--accent)':'var(--accent2)'?>;
              border:1px solid <?=$pass?'rgba(34,211,165,.2)':'rgba(248,113,113,.2)'?>">
          <?=$lbl?> <?=$pass?'✓ PASSED':'✗ FAILED'?>
        </span>
        <?php endforeach; ?>
        <?php $editPage = branchSalesReportPage($r['store_name']); ?>
        <?php if ($editPage): ?>
        <a href="<?= htmlspecialchars($editPage . '?date=' . $r['report_date'], ENT_QUOTES) ?>" class="btn btn-ghost btn-sm">Edit on Sales Report →</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top 5 metrics -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);border-bottom:1px solid var(--border)">
      <?php
        $cells = [
          ['Gross Sales',    '₱'.number_format($r['gross_sales'],2),     'var(--text)',   null],
          ['Sales Discount', '₱'.number_format($r['sales_discount'],2),  'var(--accent2)',$r['gross_sales']>0?$m['discP'].'% of sales':null],
          ['Gross Profit',   '₱'.number_format($m['gp'],2),              'var(--accent2)',$r['gross_sales']>0?round(($m['gp']/$r['gross_sales'])*100,1).'% of sales':null],
          ['Total Expenses', '₱'.number_format($m['totalAllExp'],2),     'var(--accent3)',$m['totalExpP'].'% of sales'],
          ['Profit / Loss',  ($m['profitLoss']<0?'−':'').'₱'.number_format(abs($m['profitLoss']),2),
                             $m['profitLoss']>=0?'var(--accent)':'var(--accent2)',
                             $m['npP'].'% · NP '.($m['npPassed']?'PASSED':'FAILED')],
        ];
        foreach ($cells as $i=>[$lbl,$val,$clr,$sub]):
      ?>
      <div style="padding:13px 16px;<?=$i<4?'border-right:1px solid var(--border);':''?>
                  <?=$i===4?($m['profitLoss']>=0?'background:rgba(34,211,165,.03)':'background:rgba(248,113,113,.03)'):''?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px"><?=$lbl?></div>
        <div style="font-size:1rem;font-weight:700;color:<?=$clr?>"><?=$val?></div>
        <?php if($sub): ?><div style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext);margin-top:2px"><?=$sub?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Detail breakdown -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr">
      <!-- COGS -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;
                    color:var(--subtext);margin-bottom:7px">
          COGS &nbsp;<span style="color:<?=$m['cogsPassed']?'var(--accent)':'var(--accent2)'?>"><?=$m['cogsP']?>%</span>
        </div>
        <?php
          $cogsLines = $hasSide
            ? [['Billing to Commissary',(float)($o['gulay_commissary']??0)],['Gulay',(float)($o['gulay']??0)],['Seafoods',(float)($o['seafoods']??0)],['Direct Purchases',$r['direct_purchases']]]
            : [['Gulay/Commissary',$r['gulay_commissary']],['Direct COGS',$r['direct_purchases']]];
          foreach($cogsLines as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span>₱<?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <?php foreach($extras as $ex): if(($ex['type']??'')!=='cogs') continue; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--accent3)"><?=htmlspecialchars($ex['label'])?></span><span>₱<?=number_format($ex['amount'],2)?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Non-COGS -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">Other Expenses</div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)">Other Non-COGS</span><span>₱<?=number_format($r['other_expenses'],2)?></span>
        </div>
        <?php foreach($extras as $ex): if(($ex['type']??'')!=='non_cogs') continue; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--blue)"><?=htmlspecialchars($ex['label'])?></span><span>₱<?=number_format($ex['amount'],2)?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Manpower -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;
                    color:var(--subtext);margin-bottom:7px">
          Manpower &nbsp;<span style="color:<?=$m['mpPassed']?'var(--accent)':'var(--accent2)'?>"><?=$m['mpP']?>%</span>
        </div>
        <?php foreach([['Kitchen',$r['kitchen_manpower']],['Frontline',$r['frontline_manpower']],['Overtime',$r['overtime']],['Undertime',$r['undertime']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span>₱<?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total MP</span><span>₱<?=number_format($m['totalMp'],2)?></span>
        </div>
      </div>

      <!-- Summary -->
      <div style="padding:12px 16px;background:<?=$m['profitLoss']>=0?'rgba(34,211,165,.03)':'rgba(248,113,113,.03)'?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">Summary</div>
        <?php foreach([['Total Expenses','₱'.number_format($m['totalAllExp'],2)],['Exp % of Sales',$m['totalExpP'].'%']] as [$n,$v]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.74rem;font-weight:700;margin-top:6px;padding-top:5px;border-top:1px solid var(--border)">
          <span style="color:<?=$m['profitLoss']>=0?'var(--accent)':'var(--accent2)'?>">
            <?=$m['profitLoss']>=0?'PROFIT':'LOSS'?> <?=$m['npP']?>%
          </span>
          <span style="color:<?=$m['profitLoss']>=0?'var(--accent)':'var(--accent2)'?>">
            ₱<?=number_format(abs($m['profitLoss']),2)?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <?php if ($hasSide): ?></div><?php if ($isH) renderHSidePanel($pdo, $r, $m); else renderOicSidePanel($pdo, $r, $o); ?></div><?php endif; ?>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:2rem;margin-bottom:12px;opacity:.3">📋</div>
  <div style="font-family:var(--font-m);font-size:.8rem;color:var(--subtext)">
    <?php if ($fDate !== ''): ?>
      No reports for <?= date('F j, Y', strtotime($fDate)) ?>
    <?php else: ?>
      No reports for <?=$monthNames[$fMonth]?> <?=$fYear?>
    <?php endif; ?>
  </div>
  <?php if ($fDate !== ''): ?>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?><?= $viewBranch !== '' ? '&view_branch='.urlencode($viewBranch) : '' ?>" class="btn btn-ghost" style="margin-top:16px">✕ Clear filter</a>
  <?php endif; ?>
  <?php
    $emptyStateBranch = isBranch() ? currentBranch() : $viewBranch;
    $emptyStatePage   = $emptyStateBranch ? branchSalesReportPage($emptyStateBranch) : null;
  ?>
  <?php if ($emptyStatePage): ?>
  <a href="<?= htmlspecialchars($emptyStatePage, ENT_QUOTES) ?>" class="btn btn-primary" style="margin-top:16px">Go to Sales Report →</a>
  <?php else: ?>
  <div style="font-family:var(--font-m);font-size:.7rem;color:var(--subtext);margin-top:8px">Save a Sales Report for a branch to see it appear here.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// ── Side panel (OIC Report; H also has Sales Summary) ──────────
const DR_H_SALES_URL = '<?= BASE_URL ?>/h/h_sales_report.php';
const drN   = (root, f) => parseFloat(root.querySelector('[data-f="'+f+'"]')?.value) || 0;
const drFmt = n => n.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
const drPeso = n => (n < 0 ? '−' : '') + '₱' + drFmt(Math.abs(n));
const drSide = el => el.closest('.dr-side');

function drTab(btn, which) {
  const side = drSide(btn);
  side.querySelectorAll('.dr-tab').forEach(b => b.classList.toggle('on', b === btn));
  side.querySelectorAll('.dr-pane').forEach(p => p.style.display = (p.dataset.pane === which ? '' : 'none'));
}

// OIC Report — live totals, % of Gross Sales and QUOTA / COGS / MP / NP flags.
// Same maths as oicCalcMetrics() in lib/branch_sales.php (the OIC Excel sheet):
//   Sales Discount = Grab Discount + Grab CF + Walk-in Discount · Gross Profit = Gross − Discount
//   Total COGS = Billing + Gulay + Seafoods + Direct · Total Expenses = COGS + Other + Manpower
//   Profit = Gross Profit − Total Expenses · every % = amount ÷ Gross Sales
function drOic(el) {
  const side = drSide(el), base = JSON.parse(side.dataset.oic);
  const pane = side.querySelector('[data-pane="oic"]');
  const n    = f => drN(pane, f);
  const gd   = n('grab_discount');
  const cf   = Math.max(base.grab - gd, 0) * base.cfRate;
  const disc = gd + cf + base.walkin;
  const gp   = base.gross - disc;
  const cogs = n('gulay_commissary') + n('gulay') + n('seafoods') + n('direct_purchases');
  const totExp = cogs + base.other;
  const mp   = n('kitchen') + n('frontline') + n('overtime') + n('undertime');
  const all  = totExp + mp;
  const pl   = gp - all;
  const pct  = v => base.gross > 0 ? Math.sign(v) * Math.round(Math.abs(v / base.gross) * 1000) / 10 : 0;
  const discP = pct(disc), cogsP = pct(cogs), mpP = pct(mp), allP = pct(all), npP = pct(pl);

  const set = (k, v) => { const e = pane.querySelector('[data-o="'+k+'"]'); if (e) e.textContent = v; };
  set('disc', drFmt(disc));  set('cf', drFmt(cf));   set('gp', drFmt(gp));
  set('cogs', drFmt(cogs));  set('totExp', drFmt(totExp));
  set('mp', drFmt(mp));      set('all', drFmt(all));
  set('pl', (pl < 0 ? '−' : '') + drFmt(Math.abs(pl)));
  set('discP', discP + '%'); set('cogsP', cogsP + '%'); set('mpP', mpP + '%');
  set('allP', allP + '%');   set('npP', npP + '%');
  const plRow = pane.querySelector('[data-o="plRow"]');
  if (plRow) plRow.style.color = pl >= 0 ? 'var(--accent)' : 'var(--accent2)';

  const flag = (k, label, ok) => { const e = pane.querySelector('[data-o="'+k+'"]');
    if (e) e.innerHTML = '<span class="dr-badge '+(ok?'ok':'bad')+'">'+label+' '+(ok?'PASSED':'FAILED')+'</span>'; };
  flag('fQuota', 'QUOTA', base.gross >= n('quota_target'));
  flag('fCogs',  'COGS',  cogsP <= n('cogs_threshold'));
  flag('fMp',    'MP',    mpP   <= n('mp_threshold'));
  flag('fNp',    'NP',    npP   >= n('np_threshold'));
}

async function drSaveOic(btn) {
  const side = drSide(btn), pane = side.querySelector('[data-pane="oic"]');
  const st = pane.querySelector('[data-o="status"]'); st.textContent = 'Saving…';
  const fd = new FormData();
  fd.append('action', side.dataset.action || 'save_h_oic');   // H panel: save_h_oic · other branches: save_oic
  if (side.dataset.store) fd.append('store', side.dataset.store);
  fd.append('report_date', side.dataset.date);
  ['gulay_commissary','gulay','seafoods','direct_purchases','grab_discount',
   'kitchen','frontline','overtime','undertime',
   'quota_target','cogs_threshold','mp_threshold','np_threshold'].forEach(f => fd.append(f, drN(pane, f)));
  fd.append('last_order_time', pane.querySelector('[data-f="last_order_time"]').value);
  try {
    const j = await (await fetch(window.location.pathname + window.location.search, {method:'POST', body:fd})).json();
    if (j.ok) { st.textContent = 'Saved ✓'; setTimeout(() => window.location.reload(), 350); }
    else st.textContent = '⚠ ' + (j.msg || 'Could not save');
  } catch (e) { st.textContent = '⚠ Network error'; }
}

// Sales Summary — live Net Sales / Short-Over (mirrors h_sales_report.php recalc)
function drSum(el) {
  const side = drSide(el), b = JSON.parse(side.dataset.sum);
  const pane = side.querySelector('[data-pane="sum"]');
  const z     = b.di + b.marketing + b.grab + b.gift + b.bar + drN(pane,'carwash_sales');
  const gross = z - drN(pane,'service_charge') + b.discount - b.marketing;
  const net   = gross - drN(pane,'maya_swipe') - b.unpaid - b.grab - b.gcash - b.gift - b.bank - b.pcf
                      - drN(pane,'other_expenses') - b.discount;
  const short = drN(pane,'coh') - net;
  const swipe = drN(pane,'deposit_swipe_card') + drN(pane,'late_payment_card') + drN(pane,'maya_swipe');
  const put = (k, v) => { const e = pane.querySelector('[data-s="'+k+'"]'); if (e) e.textContent = drFmt(v); };
  put('gross', gross); put('z', z); put('swipe', swipe); put('net', net); put('short', short);
  const se = pane.querySelector('[data-s="short"]'); if (se) se.style.color = short < 0 ? '#b71c1c' : '';
}

async function drSaveSum(btn) {
  const side = drSide(btn), pane = side.querySelector('[data-pane="sum"]');
  const st = pane.querySelector('[data-s="status"]'); st.textContent = 'Saving…';
  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('report_date', side.dataset.date);
  // Editable fields; every auto field is recomputed server-side from the Sales Report's detail rows.
  ['service_charge','carwash_sales','deposit_swipe_card','late_payment_card','maya_swipe','other_expenses','coh']
    .forEach(f => fd.append(f, drN(pane, f)));
  fd.append('remarks', pane.querySelector('[data-f="remarks"]').value);
  fd.append('opening_cashier', side.dataset.opening);   // keep existing cashier names untouched
  fd.append('closing_cashier', side.dataset.closing);
  try {
    const j = await (await fetch(DR_H_SALES_URL, {method:'POST', body:fd})).json();
    if (j.ok) { st.textContent = 'Saved ✓'; setTimeout(() => window.location.reload(), 350); }
    else st.textContent = '⚠ ' + (j.msg || 'Could not save');
  } catch (e) { st.textContent = '⚠ Network error'; }
}

// Paint the initial OIC badges / totals on load
document.querySelectorAll('.dr-side').forEach(side => {
  const first = side.querySelector('[data-pane="oic"] [data-f]'); if (first) drOic(first);
});
</script>

<script>
// Date filter — narrows the list above to just that one date's report(s)
// (or an empty state if nothing's saved for it), same as the Commissary
// OIC Daily Report page.
document.getElementById('jumpDate').addEventListener('change', function() {
  const val = this.value;
  if (!val) return;
  <?php if ($viewBranch !== ''): ?>
  window.location = '?date=' + val + '&view_branch=<?= urlencode($viewBranch) ?>';
  <?php else: ?>
  window.location = '?date=' + val;
  <?php endif; ?>
});
</script>
</div><!-- /reportsListContainer -->

  </div></div>

</body>
</html>