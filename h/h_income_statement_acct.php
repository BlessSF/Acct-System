<?php
// ============================================================
//  h_income_statement_acct.php — H Income Statement (FS layout)
//
//  Same layout as the "FS" sheet of the HERO disbursement workbook.
//  Everything is connected, nothing has to be typed twice:
//    Net Sales        ← H Summary Report (Store Gross)         (can be overtyped)
//    Sales Discount   ← H Disbursement, title "Sales Discounts"
//    Cost of Sales    ← H COGS page (Cost of Sales of the month)
//    Expense lines    ← H Disbursement, grouped by Account Title (see $FS_LINES below)
//  Typed here (saved automatically): Other Income, Depreciation, VAT Payment, ROI WW&Owner
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once '../lib/branch_sales.php';
requireLogin();

if (isBranch() && currentBranch() !== 'H') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Filters ───────────────────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year  < 2000 || $year  > 2100) $year  = (int)date('Y');
if ($month < 1    || $month > 12)   $month = (int)date('n');
$basis   = (($_GET['basis'] ?? 'gross') === 'net') ? 'net' : 'gross';      // gross = what the FS sheet uses for October
$compare = !isset($_GET['year']) ? true : (($_GET['compare'] ?? '') === '1');   // show the previous month beside it
$prevY = $month === 1 ? $year - 1 : $year;
$prevM = $month === 1 ? 12 : $month - 1;

// ── Typed-in lines (saved automatically) ──────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `h_is_manual` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `fy` int(4) NOT NULL, `fm` tinyint(2) NOT NULL,
        `field` varchar(40) NOT NULL,
        `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
        `saved_by` varchar(100) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_fym` (`fy`,`fm`,`field`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {}

$MANUAL = ['net_sales', 'other_income', 'depreciation', 'vat_payment', 'roi_owner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_manual'])) {
    header('Content-Type: application/json');
    $f  = $_POST['field'] ?? '';
    $fy = (int)($_POST['fy'] ?? 0); $fm = (int)($_POST['fm'] ?? 0);
    $raw = trim((string)($_POST['amount'] ?? ''));
    if (!in_array($f, $MANUAL, true) || $fy < 2000 || $fm < 1 || $fm > 12) { echo json_encode(['ok' => false, 'msg' => 'Bad request']); exit; }
    try {
        if ($raw === '') {
            $pdo->prepare("DELETE FROM h_is_manual WHERE fy=? AND fm=? AND field=?")->execute([$fy, $fm, $f]);
        } else {
            $amt = (float)preg_replace('/[^0-9.\-]/', '', $raw);
            $pdo->prepare("INSERT INTO h_is_manual (fy,fm,field,amount,saved_by) VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE amount=VALUES(amount), saved_by=VALUES(saved_by)")
                ->execute([$fy, $fm, $f, $amt, $user['name']]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── FS lines → Account Titles of the H Disbursement (edit here to change the grouping) ──
$FS_LINES = [
    'equipment_supplies' => ['Equipment and Supplies',            ['Kitchen Supplies', 'Other Equipment']],
    'transport_fuel'     => ['Transportation and Fuel',           ['Fare & Transportation', 'Fuel & Oil']],
    'lpg'                => ['LPG',                               ['Solane']],
    'rent'               => ['Rent',                              ['Rent']],
    'water_elec'         => ['Water and Electricity',             ['Water', 'Light', 'Telephone, Light & Water']],
    'drinking_water'     => ['Drinking Water',                    ['Drinking Water']],
    'pest'               => ['Pest Control, Bio Aug.',            ['Pest Control', 'Bio Augmentation']],
    'common_area'        => ['Common Area Charges',               ['CUSA']],
    'exhaust'            => ['Exhaust Cleaning',                  ['Exhaust Cleaning']],
    'salaries'           => ['Salaries',                          ['Salaries and Wages']],
    'office'             => ['Office Equipment & Supplies',       ['Office Supplies', 'Office Equipment']],
    'gov_premium'        => ['Philhealth, Pag-ibig, SSS Premium', ['SSS, PHIC, HDMF Contribution']],
    'medical'            => ['Medical Supplies',                  ['Medicine']],
    'commission'         => ['Agency & Commission Fees',          ['Commission Expense']],
    'bank_fees'          => ['Bank fees',                         ['Bank Fees']],
    'staff_meal'         => ['Staff Meal',                        ['Staff Meals', 'Meals']],
    'representation'     => ['Representation/Employee Benefits',  ['Representation Expense']],
    'professional'       => ['Professional/Administrative Fees',  ['Professional Fee', 'Bookkeeping Fee']],
    'communication'      => ['Communication',                     ['Communication']],
    'freight'            => ['Freight Charge & Storage',          ['Delivery Expense']],
    'repairs'            => ['Repairs and Maintenance',           ['Repairs and Maintenance']],
    'marketing'          => ['Sponsorship and Marketing',         ['Advertisement', 'Marketing']],
    'taxes'              => ['Taxes and Licenses-Others',         ['Taxes and Licenses']],
    'management'         => ['Management Fees',                   ['Management Fees']],
    'amortization'       => ['Amortization',                      ['Amortization']],
    'admin_shares'       => ['ADMIN Shares',                      ['Admin Salary Share']],
    'misc'               => ['Miscellaneous Expense',             ['Miscellaneous', 'Others']],
    'roi_sound'          => ['ROI (SOUND AND CCTV SYSTEM)',       ['ROI']],
];
const IS_VAR_KEYS   = ['equipment_supplies', 'depreciation', 'transport_fuel', 'lpg'];
const IS_FIXED_KEYS = ['rent', 'water_elec', 'drinking_water', 'pest', 'common_area', 'exhaust', 'salaries'];
const IS_ADMIN_KEYS = ['office', 'gov_premium', 'medical', 'commission', 'bank_fees', 'staff_meal', 'representation', 'professional',
                       'communication', 'freight', 'repairs', 'marketing', 'taxes', 'construction', 'management', 'amortization', 'admin_shares', 'misc'];
$OTHER_TITLE_GROUPS = [   // titles that are real but not an expense line of this statement
    'Sales Discounts'     => ['Sales Discounts'],
    'Purchases (in COGS)' => ['Purchases - Non-Vat', 'Purchases - Vatable', 'Carwash Purchases'],
];

$norm = function ($s) { $s = preg_replace('/\s+/', ' ', trim((string)$s)); return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };

// ── Build one month ───────────────────────────────────────
function hIsBuild(PDO $pdo, int $y, int $m, string $basis, array $FS_LINES, array $OTHER_TITLE_GROUPS, callable $norm): array
{
    // Disbursement totals per account title
    $sums = [];
    $total = 0.0;
    try {
        $st = $pdo->prepare("SELECT account_title, COALESCE(SUM(gross),0) g, COALESCE(SUM(net_of_vat),0) n, COUNT(*) c
                             FROM h_disbursement WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? GROUP BY account_title");
        $st->execute([$y, $m]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = $norm($r['account_title']);
            $v = $basis === 'net' ? (float)$r['n'] : (float)$r['g'];
            $sums[$k] = ['t' => $r['account_title'], 'v' => ($sums[$k]['v'] ?? 0) + $v, 'c' => ($sums[$k]['c'] ?? 0) + (int)$r['c']];
            $total += $v;
        }
    } catch (Throwable $e) {}

    $used = [];
    $take = function (array $titles) use (&$sums, &$used, $norm): array {
        $v = 0.0; $c = 0;
        foreach ($titles as $t) { $k = $norm($t); if (isset($sums[$k])) { $v += $sums[$k]['v']; $c += $sums[$k]['c']; $used[$k] = true; } }
        return [$v, $c];
    };

    $L = []; $cnt = [];
    foreach ($FS_LINES as $key => [$label, $titles]) { [$L[$key], $cnt[$key]] = $take($titles); }
    [$L['sales_discount'], $cnt['sales_discount']] = $take($OTHER_TITLE_GROUPS['Sales Discounts']);
    [$purch, $purchCnt] = $take($OTHER_TITLE_GROUPS['Purchases (in COGS)']);

    // Net Sales (auto) = Store Gross total of the H Summary Report
    $L['net_sales_auto'] = 0.0;
    try { $L['net_sales_auto'] = round(array_sum(hStoreGrossByDate($pdo, $y, $m)), 2); } catch (Throwable $e) {}

    // Cost of Sales from the H COGS page
    $L['cogs'] = 0.0; $cogsRows = 0;
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(cos),0), COUNT(*) FROM h_cogs WHERE store_name='H' AND cogs_year=? AND cogs_month=?");
        $st->execute([$y, $m]);
        [$L['cogs'], $cogsRows] = array_map('floatval', $st->fetch(PDO::FETCH_NUM));
    } catch (Throwable $e) {}

    // Typed-in lines
    $man = [];
    try {
        $st = $pdo->prepare("SELECT field, amount FROM h_is_manual WHERE fy=? AND fm=?");
        $st->execute([$y, $m]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $man[$r['field']] = (float)$r['amount'];
    } catch (Throwable $e) {}
    // Depreciation is the same every month → carry the latest earlier value forward when none is typed for this month
    $depCarried = null;
    if (!array_key_exists('depreciation', $man)) {
        try {
            $st = $pdo->prepare("SELECT amount, fy, fm FROM h_is_manual WHERE field='depreciation' AND (fy < ? OR (fy = ? AND fm < ?)) ORDER BY fy DESC, fm DESC LIMIT 1");
            $st->execute([$y, $y, $m]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) { $man['depreciation'] = (float)$r['amount']; $depCarried = date('M Y', mktime(0, 0, 0, (int)$r['fm'], 1, (int)$r['fy'])); }
        } catch (Throwable $e) {}
    }
    $L['other_income'] = $man['other_income'] ?? 0.0;
    $L['depreciation'] = $man['depreciation'] ?? 0.0;
    $L['vat_payment']  = $man['vat_payment']  ?? 0.0;
    $L['roi_owner']    = $man['roi_owner']    ?? 0.0;
    $L['construction'] = 0.0;
    $netSalesTyped = array_key_exists('net_sales', $man);
    $L['net_sales'] = $netSalesTyped ? $man['net_sales'] : $L['net_sales_auto'];

    // Not part of the statement
    $unmapped = [];
    foreach ($sums as $k => $s) if (!isset($used[$k]) && abs($s['v']) > 0.004) $unmapped[] = [$s['t'] === '' ? '(no account title)' : $s['t'], $s['v'], $s['c']];

    $L = hIsTotals($L);
    return ['L' => $L, 'cnt' => $cnt, 'man' => $man, 'netSalesTyped' => $netSalesTyped, 'depCarried' => $depCarried,
            'cogsRows' => (int)$cogsRows, 'purch' => $purch, 'purchCnt' => $purchCnt, 'unmapped' => $unmapped, 'total' => $total];
}
function hIsTotals(array $L): array
{
    $sum = function (array $keys) use ($L) { $s = 0.0; foreach ($keys as $k) $s += (float)($L[$k] ?? 0); return $s; };
    $L['gross_profit']  = $L['net_sales'] - $L['sales_discount'] - $L['cogs'];
    $L['var_total']     = $sum(IS_VAR_KEYS);
    $L['fixed_total']   = $sum(IS_FIXED_KEYS);
    $L['opex_total']    = $L['var_total'] + $L['fixed_total'];
    $L['admin_total']   = $sum(IS_ADMIN_KEYS);
    $L['total_exp']     = $L['opex_total'] + $L['admin_total'];
    $L['nibt']          = $L['gross_profit'] + $L['other_income'] - $L['total_exp'];
    $L['niat']          = $L['nibt'] - $L['vat_payment'];
    $L['np_add']        = $L['niat'] + $L['depreciation'];
    $L['np_final']      = $L['np_add'] - $L['roi_sound'];
    return $L;
}

$cur  = hIsBuild($pdo, $year, $month, $basis, $FS_LINES, $OTHER_TITLE_GROUPS, $norm);
$prev = $compare ? hIsBuild($pdo, $prevY, $prevM, $basis, $FS_LINES, $OTHER_TITLE_GROUPS, $norm) : null;

$monthLabel = strtoupper(date('F Y', mktime(0, 0, 0, $month, 1, $year)));
$prevLabel  = strtoupper(date('F Y', mktime(0, 0, 0, $prevM, 1, $prevY)));

$money = function ($n) { $n = (float)$n; return abs($n) < 0.005 ? '—' : number_format($n, 2, '.', ','); };
$pctOf = function ($n, $base) { return (abs($n) < 0.005 || abs($base) < 0.005) ? '' : number_format($n / $base * 100, 0) . '%'; };

// ── Row layout (mirrors the FS sheet) ─────────────────────
// [type, key, label]   types: sec (section heading) · line · total · grand · typed (editable) · note
$ROWS = [
    ['line',  'net_sales',      'Net Sales'],
    ['line',  'sales_discount', 'Sales Discount'],
    ['line',  'cogs',           'Cost of Sales'],
    ['total', 'gross_profit',   'Gross Profit'],
    ['typed', 'other_income',   'Other Income-Royalty'],
    ['sec',   '',               'Operating Expenses'],
    ['line',  'equipment_supplies', null], ['typed', 'depreciation', 'Depreciation Expense'], ['line', 'transport_fuel', null], ['line', 'lpg', null],
    ['sub',   'var_total',      'Total'],
    ['sec2',  '',               'Fixed:'],
    ['line',  'rent', null], ['line', 'water_elec', null], ['line', 'drinking_water', null], ['line', 'pest', null], ['line', 'common_area', null],
    ['line',  'exhaust', null], ['line', 'salaries', null],
    ['sub',   'fixed_total',    'Total'],
    ['total', 'opex_total',     'Total Operating Expenses'],
    ['sec',   '',               'General and Administrative Expenses'],
    ['line', 'office', null], ['line', 'gov_premium', null], ['line', 'medical', null], ['line', 'commission', null], ['line', 'bank_fees', null],
    ['line', 'staff_meal', null], ['line', 'representation', null], ['line', 'professional', null], ['line', 'communication', null],
    ['line', 'freight', null], ['line', 'repairs', null], ['line', 'marketing', null], ['line', 'taxes', null],
    ['line', 'construction', 'Construction and Progress'], ['line', 'management', null], ['line', 'amortization', null],
    ['line', 'admin_shares', null], ['line', 'misc', null],
    ['total', 'admin_total',    'Total Administrative Expenses'],
    ['total', 'total_exp',      'Total Expenses'],
    ['grand', 'nibt',           'Net Income (Loss) Before Taxes'],
    ['typed', 'vat_payment',    'VAT PAYMENT'],
    ['grand', 'niat',           'Net Income after Tax'],
    ['grand', 'np_add',         'NET PROFIT (ADD BACK DEPRECIATION)'],
    ['typed', 'roi_owner',      'ROI WW&OWNER'],
    ['line',  'roi_sound',      null],
    ['final', 'np_final',       'NET PROFIT WITH DEPRECIATION'],
];
$labelOf = function ($key, $given) use ($FS_LINES) { return $given ?? ($FS_LINES[$key][0] ?? $key); };
$titlesOf = function ($key) use ($FS_LINES) { return isset($FS_LINES[$key]) ? implode(' + ', $FS_LINES[$key][1]) : ''; };

$pageTitle  = 'Income Statement (FS)';
$activePage = 'h_income_statement_acct';
include '../layout.php';
?>

<style>
.is-wrap { max-width: <?= $compare ? '980px' : '760px' ?>; margin: 0 auto; }
.is-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 20px; }
.is-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
.fs-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.fs-table th, .fs-table td { padding: 0 10px; border-bottom: 1px solid #f1f3f6; height: 30px; }
.fs-title { background: #7a1313; color: #fff; padding: 14px 22px; }
.fs-title b { font-size: 1.02rem; letter-spacing: .01em; }
.fs-title span { display: block; font-family: var(--font-m); font-size: .66rem; color: rgba(255,255,255,.7); margin-top: 3px; }
.fs-table thead th { background: #fbeaea; color: #7a1313; font-family: var(--font-m); font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; height: 36px; text-align: right; border-bottom: 2px solid #e9c2c2; }
.fs-table thead th:first-child { text-align: left; }
.fs-table thead th.cur { background: #7a1313; color: #fff; }
.fs-table td.lbl { color: var(--text); text-align: left; }
.fs-table td.num { text-align: right; font-family: var(--font-m); font-size: .79rem; white-space: nowrap; min-width: 118px; }
.fs-table td.pc  { text-align: right; font-family: var(--font-m); font-size: .68rem; color: var(--subtext); width: 46px; border-right: 1px solid #f1f3f6; }
.fs-table td.cur { background: #fffafa; }
.fs-table tr.line td.lbl { padding-left: 26px; }
.fs-table tr.sub td.lbl  { padding-left: 90px; font-weight: 700; color: var(--subtext2); font-size: .74rem; }
.fs-table tr.sub td.num  { font-weight: 700; border-top: 1px solid var(--border2); }
.fs-table tr.sec td  { background: #f3f4f6; font-weight: 800; font-family: var(--font-m); font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; color: var(--subtext2); height: 28px; }
.fs-table tr.sec2 td { font-weight: 700; color: var(--subtext2); padding-left: 26px; height: 24px; }
.fs-table tr.total td { font-weight: 800; background: #fafbfc; border-top: 1px solid var(--border2); }
.fs-table tr.grand td { font-weight: 800; background: #f6f7f9; border-top: 2px solid var(--border2); }
.fs-table tr.final td { font-weight: 800; background: #fff6e0; border-top: 2px solid var(--accent3); color: #8a5300; }
.fs-table tr.zero.line td { color: var(--subtext); }
.fs-table .neg { color: var(--accent2) !important; }
.fs-table input.fs-in { width: 118px; text-align: right; font-family: var(--font-m); font-size: .79rem; padding: 4px 8px; border: 2px solid #0284c7; border-radius: 6px; background: #fff;
  box-shadow: 0 0 0 3px rgba(2,132,199,.35), 0 0 10px 1px rgba(2,132,199,.45); }
.fs-table input.fs-in:focus { outline: none; border-color: #0369a1; box-shadow: 0 0 0 4px rgba(2,132,199,.6), 0 0 16px 3px rgba(2,132,199,.6); }
.fs-tag { font-family: var(--font-m); font-size: .58rem; color: var(--accent); margin-left: 8px; background: rgba(15,123,92,.08); padding: 2px 7px; border-radius: 10px; border: 1px solid rgba(15,123,92,.15); white-space: nowrap; }
.fs-st { font-family: var(--font-m); font-size: .62rem; color: var(--subtext); margin-left: 6px; }
.is-note { font-family: var(--font-m); font-size: .68rem; color: var(--subtext); line-height: 1.7; padding: 12px 22px; border-top: 1px solid var(--border); background: #fcfcfd; }
.is-note b { color: var(--text); }
.is-note .bad { color: var(--accent2); }
@media print { .is-controls, .sidebar, aside, .fs-tag, .fs-st { display: none !important; } .fs-table input.fs-in { border: 0; box-shadow: none; } }
</style>

<div class="is-controls">
  <form method="GET" id="isForm" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:150px" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
      <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select name="basis" class="form-control" style="max-width:170px" onchange="this.form.submit()" title="The FS sheet uses Gross for October">
      <option value="gross" <?= $basis === 'gross' ? 'selected' : '' ?>>Amount: Gross</option>
      <option value="net"   <?= $basis === 'net'   ? 'selected' : '' ?>>Amount: Net of VAT</option>
    </select>
    <label style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext);display:flex;align-items:center;gap:6px;cursor:pointer">
      <input type="checkbox" name="compare" value="1" <?= $compare ? 'checked' : '' ?> onchange="this.form.submit()"> Show previous month
    </label>
  </form>
  <label style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext);display:flex;align-items:center;gap:6px;cursor:pointer">
    <input type="checkbox" id="hideZero" onchange="toggleZero()"> Hide zero lines
  </label>
  <a href="h_acc_title.php" class="btn btn-ghost">⚙ Account Titles</a>
  <a href="h_disbursement.php?month=<?= sprintf('%04d-%02d', $year, $month) ?>" class="btn btn-ghost">🧾 Disbursement</a>
  <button type="button" class="btn btn-ghost" onclick="window.print()">🖨 Print</button>
</div>

<div class="is-wrap">
<div class="is-card">
  <div class="fs-title">
    <b>HERO INCOME STATEMENT <?= (int)$year ?></b>
    <span>H Branch · expenses from H Disbursement by Account Title · shown as <?= $basis === 'net' ? 'Net of VAT' : 'Gross (incl. VAT)' ?></span>
  </div>

  <table class="fs-table" id="fsTable">
    <thead>
      <tr>
        <th></th>
        <?php if ($compare): ?><th><?= htmlspecialchars($prevLabel) ?></th><th style="width:46px"></th><?php endif; ?>
        <th class="cur"><?= htmlspecialchars($monthLabel) ?></th><th class="cur" style="width:46px"></th>
      </tr>
    </thead>
    <tbody>
    <?php
    foreach ($ROWS as [$type, $key, $given]):
        if ($type === 'sec' || $type === 'sec2'):
            $cols = $compare ? 5 : 3; ?>
      <tr class="<?= $type ?>"><td colspan="<?= $cols ?>"><?= htmlspecialchars($given) ?></td></tr>
    <?php   continue; endif;
        $label = $labelOf($key, $given);
        $vCur  = $cur['L'][$key] ?? 0; $vPrev = $prev ? ($prev['L'][$key] ?? 0) : 0;
        $isTyped = ($type === 'typed') || $key === 'net_sales';
        $zero = ($type === 'line' && abs($vCur) < 0.005 && (!$prev || abs($vPrev) < 0.005));
        $tip  = $titlesOf($key);
        $showPct = !in_array($key, ['net_sales'], true);
        $rowClass = $type . ($zero ? ' zero' : '');
        $negCls = function ($v) use ($key) { return ($v < 0 && in_array($key, ['gross_profit','nibt','niat','np_add','np_final'], true)) ? 'neg' : ''; };
    ?>
      <tr class="<?= $rowClass ?>" data-key="<?= htmlspecialchars($key) ?>" data-zero="<?= $zero ? '1' : '0' ?>" <?= $tip ? 'title="' . htmlspecialchars('Account Titles: ' . $tip) . '"' : '' ?>>
        <td class="lbl"><?= htmlspecialchars($label) ?>
          <?php if ($key === 'net_sales'): ?>
            <span class="fs-tag" id="tag_net_sales"><?= $cur['netSalesTyped'] ? '✎ typed (clear to use Summary Report)' : '↳ Summary Report' ?></span>
          <?php elseif ($key === 'sales_discount'): ?><span class="fs-tag">↳ Disbursement</span>
          <?php elseif ($key === 'cogs'): ?><span class="fs-tag"><?= $cur['cogsRows'] ? '↳ H COGS' : '⚠ no H COGS for this month' ?></span>
          <?php elseif ($key === 'depreciation' && $cur['depCarried']): ?><span class="fs-tag">same as <?= htmlspecialchars($cur['depCarried']) ?></span>
          <?php elseif (!empty($cur['cnt'][$key])): ?><span class="fs-tag"><?= (int)$cur['cnt'][$key] ?> entr<?= $cur['cnt'][$key] == 1 ? 'y' : 'ies' ?></span>
          <?php endif; ?>
          <?php if ($isTyped): ?><span class="fs-st" id="st_<?= htmlspecialchars($key) ?>"></span><?php endif; ?>
        </td>
        <?php if ($compare): ?>
        <td class="num <?= $negCls($vPrev) ?>"><?= $money($vPrev) ?></td>
        <td class="pc"><?= $showPct ? $pctOf($vPrev, $prev['L']['net_sales']) : '' ?></td>
        <?php endif; ?>
        <td class="num cur <?= $negCls($vCur) ?>" id="v_<?= htmlspecialchars($key) ?>">
          <?php if ($isTyped):
              $shown = ($key === 'net_sales') ? ($cur['netSalesTyped'] ? $vCur : '') : (array_key_exists($key, $cur['man']) ? $vCur : '');
              $ph    = ($key === 'net_sales') ? number_format($cur['L']['net_sales_auto'], 2, '.', '') : '0.00'; ?>
            <input class="fs-in" type="text" inputmode="decimal" data-field="<?= htmlspecialchars($key) ?>"
                   value="<?= $shown === '' ? '' : number_format((float)$shown, 2, '.', '') ?>" placeholder="<?= $ph ?>" autocomplete="off">
          <?php else: ?><?= $money($vCur) ?><?php endif; ?>
        </td>
        <td class="pc cur" id="p_<?= htmlspecialchars($key) ?>"><?= $showPct ? $pctOf($vCur, $cur['L']['net_sales']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="is-note">
    <b>Check — <?= htmlspecialchars(ucfirst(strtolower($monthLabel))) ?> disbursement</b> (<?= $basis === 'net' ? 'Net of VAT' : 'Gross' ?>): total entered <b><?= $money($cur['total']) ?></b><br>
    Purchases going to Cost of Sales (Purchases Non-Vat + Vatable + Carwash): <b><?= $money($cur['purch']) ?></b> (<?= (int)$cur['purchCnt'] ?> entries) — these are in COGS, not in the expense lines.<br>
    <?php if ($cur['unmapped']): ?>
      <span class="bad">Not in the statement (no line for these Account Titles — add them to a line in <code>$FS_LINES</code> or check the title name):</span>
      <?php foreach ($cur['unmapped'] as $u): ?><br>&nbsp;&nbsp;• <b><?= htmlspecialchars($u[0]) ?></b> <?= $money($u[1]) ?> (<?= (int)$u[2] ?>)<?php endforeach; ?>
    <?php else: ?>Every other entry of this month is included in a line above. ✓<?php endif; ?>
    <br>Net Profit With Depreciation = Net Profit (add back depreciation) − ROI (Sound and CCTV), as in the FS sheet; ROI WW&amp;Owner is shown for reference.
  </div>
</div>
</div>

<script>
const FS = {
  fy: <?= (int)$year ?>, fm: <?= (int)$month ?>,
  L: <?= json_encode($cur['L'], JSON_UNESCAPED_UNICODE) ?>,
  auto: <?= json_encode((float)$cur['L']['net_sales_auto']) ?>,
  VAR: <?= json_encode(IS_VAR_KEYS) ?>, FIXED: <?= json_encode(IS_FIXED_KEYS) ?>, ADMIN: <?= json_encode(IS_ADMIN_KEYS) ?>
};
const fmtN = n => Math.abs(n) < 0.005 ? '—' : n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const sumK = (L, ks) => ks.reduce((a, k) => a + (+L[k] || 0), 0);

function fsRecalc() {
  const L = FS.L;
  L.gross_profit = L.net_sales - L.sales_discount - L.cogs;
  L.var_total = sumK(L, FS.VAR); L.fixed_total = sumK(L, FS.FIXED); L.opex_total = L.var_total + L.fixed_total;
  L.admin_total = sumK(L, FS.ADMIN); L.total_exp = L.opex_total + L.admin_total;
  L.nibt = L.gross_profit + L.other_income - L.total_exp; L.niat = L.nibt - L.vat_payment;
  L.np_add = L.niat + L.depreciation; L.np_final = L.np_add - L.roi_sound;
  ['gross_profit','var_total','fixed_total','opex_total','admin_total','total_exp','nibt','niat','np_add','np_final'].forEach(function (k) {
    const c = document.getElementById('v_' + k), p = document.getElementById('p_' + k);
    if (c) { c.textContent = fmtN(L[k]); c.classList.toggle('neg', L[k] < 0 && ['gross_profit','nibt','niat','np_add','np_final'].includes(k)); }
    if (p) p.textContent = (Math.abs(L[k]) < 0.005 || Math.abs(L.net_sales) < 0.005) ? '' : Math.round(L[k] / L.net_sales * 100) + '%';
  });
  // % column of every plain line follows the (possibly new) Net Sales
  document.querySelectorAll('#fsTable tr[data-key]').forEach(function (tr) {
    const k = tr.dataset.key; if (k === 'net_sales' || !(k in L)) return;
    const p = document.getElementById('p_' + k);
    if (p && !['gross_profit','var_total','fixed_total','opex_total','admin_total','total_exp','nibt','niat','np_add','np_final'].includes(k))
      p.textContent = (Math.abs(L[k]) < 0.005 || Math.abs(L.net_sales) < 0.005) ? '' : Math.round(L[k] / L.net_sales * 100) + '%';
  });
}

const fsTimers = {};
document.querySelectorAll('input.fs-in').forEach(function (inp) {
  inp.addEventListener('input', function () {
    const f = inp.dataset.field, raw = inp.value.trim();
    const n = parseFloat(raw.replace(/[^0-9.\-]/g, ''));
    if (f === 'net_sales') FS.L.net_sales = raw === '' ? FS.auto : (isNaN(n) ? 0 : n); else FS.L[f] = raw === '' ? 0 : (isNaN(n) ? 0 : n);
    fsRecalc();
    const st = document.getElementById('st_' + f); if (st) st.textContent = 'typing…';
    clearTimeout(fsTimers[f]);
    fsTimers[f] = setTimeout(function () { fsSave(f, raw, st); }, 600);
  });
  inp.addEventListener('blur', function () {
    const n = parseFloat(inp.value.replace(/[^0-9.\-]/g, ''));
    if (inp.value.trim() !== '' && !isNaN(n)) inp.value = n.toFixed(2);
  });
});
async function fsSave(field, raw, st) {
  const fd = new FormData(); fd.append('ajax_manual', '1'); fd.append('field', field); fd.append('fy', FS.fy); fd.append('fm', FS.fm); fd.append('amount', raw);
  try {
    const r = await (await fetch('h_income_statement_acct.php', {method: 'POST', body: fd})).json();
    if (st) st.textContent = r.ok ? '✓ saved' : '✗ ' + (r.msg || 'not saved');
    if (field === 'net_sales') { const t = document.getElementById('tag_net_sales'); if (t) t.textContent = raw === '' ? '↳ Summary Report' : '✎ typed (clear to use Summary Report)'; }
  } catch (e) { if (st) st.textContent = '✗ network error'; }
}

function toggleZero() {
  const hide = document.getElementById('hideZero').checked;
  document.querySelectorAll('#fsTable tr[data-zero="1"]').forEach(function (r) { r.style.display = hide ? 'none' : ''; });
  try { sessionStorage.setItem('hIsAcctHideZero', hide ? '1' : '0'); } catch (e) {}
}
(function () {
  try { if (sessionStorage.getItem('hIsAcctHideZero') === '1') { document.getElementById('hideZero').checked = true; toggleZero(); } } catch (e) {}
})();
</script>

</div></div>
<script src="../assets/js/keynav.js"></script>
</body>
</html>