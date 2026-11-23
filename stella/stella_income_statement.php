<?php
// ============================================================
//  stella_income_statement.php — Stella Income Statement (yearly, month by month)
//
//  Layout copied from STELLA_INCOME_STATEMENT_2026.xlsx:
//    one column per month (+ a blue % of Net Sales column), the same line items,
//    Total rows, Net Income before taxes, VAT payment, EXTRA EXPENSES block, NET INCOME.
//  Same data / same formulas as before:
//    - Net Sales   = Store Gross total from the Summary Report (auto, read-only)
//    - the lines that exist in the Expenses Ledger are pre-filled from it until the month is saved
//    - VAT Payment = VAT total of the Expenses Ledger until the month is saved
//    - a month is stored in stella_income_statement (one row per month)
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

// ── Keep the table ready (adds the Management Fee column + the Extra Expenses table) ──
try {
    $has = false;
    foreach ($pdo->query("SHOW COLUMNS FROM `stella_income_statement`")->fetchAll(PDO::FETCH_COLUMN) as $c) {
        if ($c === 'management_fee') { $has = true; break; }
    }
    if (!$has) $pdo->exec("ALTER TABLE `stella_income_statement` ADD COLUMN `management_fee` decimal(14,2) DEFAULT 0.00");
} catch (Throwable $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stella_is_extra` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ex_year` smallint(4) NOT NULL,
        `sort_order` int(6) NOT NULL DEFAULT 0,
        `label` varchar(255) NOT NULL DEFAULT '',
        `m1` decimal(14,2) NOT NULL DEFAULT 0, `m2` decimal(14,2) NOT NULL DEFAULT 0, `m3` decimal(14,2) NOT NULL DEFAULT 0,
        `m4` decimal(14,2) NOT NULL DEFAULT 0, `m5` decimal(14,2) NOT NULL DEFAULT 0, `m6` decimal(14,2) NOT NULL DEFAULT 0,
        `m7` decimal(14,2) NOT NULL DEFAULT 0, `m8` decimal(14,2) NOT NULL DEFAULT 0, `m9` decimal(14,2) NOT NULL DEFAULT 0,
        `m10` decimal(14,2) NOT NULL DEFAULT 0, `m11` decimal(14,2) NOT NULL DEFAULT 0, `m12` decimal(14,2) NOT NULL DEFAULT 0,
        `saved_by` varchar(100) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`), KEY `idx_year` (`ex_year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {}

// ── Every editable line, in the order of the Excel sheet ──
const IS_FIELDS = [
    'net_sales','sales_discount','cost_of_sales','other_income_royalty',
    'equipment_supplies','depreciation_expense','transportation_fuel','lpg',
    'rent','water_electricity','drinking_water','pest_control_bio','common_area_charges','exhaust_cleaning','salaries',
    'office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees','staff_meal',
    'representation_benefits','professional_fees','communication','management_fee','freight_storage',
    'repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development','construction_progress',
    'insurance','admin_shares','miscellaneous_expense','vat_payment',
];

// ── AJAX ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $date = $_POST['stmt_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['ok' => false, 'msg' => 'Bad date']); exit; }
    $data = [
        'store_name' => 'Stella',
        'stmt_date'  => $date,
        'stmt_year'  => (int)date('Y', strtotime($date)),
        'stmt_month' => (int)date('n', strtotime($date)),
        'stmt_day'   => (int)date('j', strtotime($date)),
        'saved_by'   => $user['name'],
    ];
    foreach (IS_FIELDS as $f) $data[$f] = (float)($_POST[$f] ?? 0);
    try {
        $q = $pdo->prepare("SELECT id FROM stella_income_statement WHERE store_name='Stella' AND stmt_date=? ORDER BY id DESC LIMIT 1");
        $q->execute([$date]);
        $id = $q->fetchColumn();
        if ($id) {
            $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
            $pdo->prepare("UPDATE stella_income_statement SET $set WHERE id=?")->execute([...array_values($data), $id]);
        } else {
            $cols = array_keys($data);
            $pdo->prepare("INSERT INTO stella_income_statement (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")
                ->execute(array_values($data));
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_extra'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_extra'];
    $yr  = (int)($_POST['year'] ?? date('Y'));
    try {
        if ($act === 'add') {
            $mx = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM stella_is_extra WHERE ex_year=" . $yr)->fetchColumn();
            $pdo->prepare("INSERT INTO stella_is_extra (ex_year, sort_order, label, saved_by) VALUES (?,?,?,?)")
                ->execute([$yr, $mx + 1, trim($_POST['label'] ?? 'NEW LINE'), $user['name']]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } elseif ($act === 'del') {
            $pdo->prepare("DELETE FROM stella_is_extra WHERE id=? AND ex_year=?")->execute([(int)$_POST['id'], $yr]);
            echo json_encode(['ok' => true]);
        } elseif ($act === 'save') {
            $m = json_decode($_POST['amounts'] ?? '[]', true) ?: [];
            $set = ['label=?', 'saved_by=?']; $vals = [trim($_POST['label'] ?? ''), $user['name']];
            for ($i = 1; $i <= 12; $i++) { $set[] = "m$i=?"; $vals[] = (float)($m[$i - 1] ?? 0); }
            $vals[] = (int)$_POST['id']; $vals[] = $yr;
            $pdo->prepare("UPDATE stella_is_extra SET " . implode(',', $set) . " WHERE id=? AND ex_year=?")->execute($vals);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Year ──────────────────────────────────────────────────
$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
$MONTHS = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];

// ── Saved month rows (latest row of each month wins) ──────
$saved = [];   // month => row
try {
    $st = $pdo->prepare("SELECT * FROM stella_income_statement WHERE store_name='Stella' AND YEAR(stmt_date)=? ORDER BY stmt_date ASC, id ASC");
    $st->execute([$year]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $saved[(int)date('n', strtotime($r['stmt_date']))] = $r;
} catch (Throwable $e) {}

// ── Net Sales per month = Store Gross total from the Summary Report ──
$netAuto = array_fill(1, 12, 0.0);
try {
    $q = $pdo->prepare("SELECT MONTH(report_date) m, COALESCE(SUM(store_gross),0) s FROM summary_report_entries WHERE store_name='Stella' AND YEAR(report_date)=? GROUP BY MONTH(report_date)");
    $q->execute([$year]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $netAuto[(int)$r['m']] = round((float)$r['s'], 2);
} catch (Throwable $e) {}

// ── Expenses Ledger totals per month (pre-fills the lines that exist there) ──
$autoExpenseMap = [
    'sales_discount'            => ['sales_discounts'],
    'transportation_fuel'       => ['fuel_trans'],
    'lpg'                       => ['lpg'],
    'rent'                      => ['rent'],
    'water_electricity'         => ['light','water'],
    'drinking_water'            => ['drinking_water'],
    'pest_control_bio'          => ['bio_pest_control'],
    'salaries'                  => ['salaries'],
    'office_equipment_supplies' => ['office_supplies'],
    'philhealth_sss'            => ['sss_phic_hdmf'],
    'medical_supplies'          => ['medicine'],
    'staff_meal'                => ['staff_meal'],
    'representation_benefits'   => ['representation'],
    'communication'             => ['communication'],
    'repairs_maintenance'       => ['repairs_maintenance'],
    'sponsorship_marketing'     => ['marketing'],
    'taxes_licenses'            => ['taxes_licences'],
    'miscellaneous_expense'     => ['miscellaneous'],
];
$ledgerCols = ['vat'];
foreach ($autoExpenseMap as $cols) foreach ($cols as $c) $ledgerCols[] = $c;
$ledger = array_fill(1, 12, []);
try {
    $sel = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", array_unique($ledgerCols)));
    $q = $pdo->prepare("SELECT MONTH(expense_date) m, $sel FROM stella_expenses WHERE YEAR(expense_date)=? GROUP BY MONTH(expense_date)");
    $q->execute([$year]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $ledger[(int)$r['m']] = $r;
} catch (Throwable $e) {}

// ── Build the value of every line for each month ──
$vals = []; $monthDate = []; $isSaved = [];
for ($m = 1; $m <= 12; $m++) {
    $row = $saved[$m] ?? null;
    $isSaved[$m]   = $row !== null;
    $monthDate[$m] = $row ? $row['stmt_date'] : sprintf('%04d-%02d-01', $year, $m);
    foreach (IS_FIELDS as $f) {
        if ($f === 'net_sales')       { $v = $netAuto[$m]; }
        elseif ($row)                 { $v = (float)($row[$f] ?? 0); }
        elseif ($f === 'vat_payment') { $v = (float)($ledger[$m]['vat'] ?? 0); }
        elseif (isset($autoExpenseMap[$f])) {
            $v = 0.0;
            foreach ($autoExpenseMap[$f] as $c) $v += (float)($ledger[$m][$c] ?? 0);
        } else                        { $v = 0.0; }
        $vals[$m][$f] = round($v, 2);
    }
}
// legacy "Bank Fees" is not on the Excel sheet: only show the line if the year actually has something in it
$showBank = false;
for ($m = 1; $m <= 12; $m++) if (abs($vals[$m]['bank_fees']) > 0.004) $showBank = true;

// ── Extra expenses (green block) ──
$extras = [];
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM stella_is_extra WHERE ex_year=?");
    $cnt->execute([$year]);
    if ((int)$cnt->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO stella_is_extra (ex_year, sort_order, label, saved_by) VALUES (?,?,?,?)");
        foreach (['QUARTERLY VAT PAYMENT','BUSINESS PERMIT RENEWAL','FB BOOSTING','TEAM BUILDING SHARE/SUMMER LEAGUE',
                  'HONDA BEAT MOTORCYCLE','ANNUAL INCOME TAX RETURN','BIR PENALTY','ILOILO CITY LOA'] as $i => $lb)
            $ins->execute([$year, $i + 1, $lb, $user['name']]);
    }
    $q = $pdo->prepare("SELECT * FROM stella_is_extra WHERE ex_year=? ORDER BY sort_order ASC, id ASC");
    $q->execute([$year]);
    $extras = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── The rows of the sheet (same order as the Excel) ──
//  kind: in = editable line | calc = formula line | head = heading | sub = sub heading | gap = empty row
//  lvl: 0 = column A, 1 = column B (indented), 2 = column C ("Total" right-aligned)
$ROWS = [
    ['in',  'net_sales',      'Net Sales',        0, ['ro' => 1, 'tip' => 'Auto from the Summary Report (Store Gross)']],
    ['in',  'sales_discount', 'Sales Discount',   0, ['pct' => 1]],
    ['in',  'cost_of_sales',  'Cost of Sales',    0, ['pct' => 1]],
    ['calc','gross',          'Gross Profit',     0, ['u' => 1]],
    ['in',  'other_income_royalty', 'Other Income-Royalty', 0, []],
    ['gap'],
    ['head','Operating Expenses'],
    ['in',  'equipment_supplies',   'Equipment and Supplies',  1, []],
    ['in',  'depreciation_expense', 'Depreciation Expense',    1, []],
    ['in',  'transportation_fuel',  'Transportation and Fuel', 1, []],
    ['in',  'lpg',                  'LPG',                     1, []],
    ['calc','tvar', 'Total', 2, ['u' => 1]],
    ['sub', 'Fixed:'],
    ['in',  'rent',                'Rent',                  1, []],
    ['in',  'water_electricity',   'Water and Electricity', 1, []],
    ['in',  'drinking_water',      'Drinking Water',        1, []],
    ['in',  'pest_control_bio',    'Pest Control, Bio Aug,', 1, []],
    ['in',  'common_area_charges', 'Common Area Charges',   1, []],
    ['in',  'exhaust_cleaning',    'Exhaust Cleaning',      1, []],
    ['in',  'salaries',            'Salaries',              1, ['pct' => 1]],
    ['calc','tfix', 'Total', 2, ['u' => 1]],
    ['gap'],
    ['calc','topex', 'Total Operating Expenses', 0, ['u' => 1, 'pct' => 1]],
    ['head','General and Administrative Expenses'],
    ['in', 'office_equipment_supplies', 'Office Equipment &Supplies',          1, []],
    ['in', 'philhealth_sss',            'Philhealth, Pag- ibig,SSS Premium',   1, []],
    ['in', 'medical_supplies',          'Medical Supplies',                    1, []],
    ['in', 'agency_fees',               'AGENCY FEES/COMMISSION',              1, []],
    ['in', 'staff_meal',                'Staff Meal',                          1, []],
    ['in', 'representation_benefits',   'Representation/Employee Benefits',    1, []],
    ['in', 'professional_fees',         'Professional/Administrative Fees',    1, []],
    ['in', 'communication',             'Communication',                       1, []],
    ['in', 'management_fee',            'Management Fee',                      1, []],
    ['in', 'freight_storage',           'Freight Charge & Storage',            1, []],
    ['in', 'repairs_maintenance',       'Repairs and Maintenance',             1, []],
    ['in', 'sponsorship_marketing',     'Sponsorship and Marketing',           1, []],
    ['in', 'taxes_licenses',            'Taxes and Licenses-Others(Est)',      1, []],
    ['in', 'system_development',        'System Development',                  1, []],
    ['in', 'construction_progress',     'Construction and Progress',           1, []],
    ['in', 'insurance',                 'Insurance',                           1, []],
    ['in', 'admin_shares',              'ADMIN Shares',                        1, []],
    ['in', 'miscellaneous_expense',     'Miscellaneous Expense',               1, []],
];
if ($showBank) {   // legacy line, only when the year has an amount in it
    foreach ($ROWS as $i => $r) if (($r[1] ?? '') === 'miscellaneous_expense') { array_splice($ROWS, $i + 1, 0, [['in', 'bank_fees', 'Bank Fees', 1, []]]); break; }
}
$ROWS = array_merge($ROWS, [
    ['calc','tga',  'Total Administrative Expenses', 0, ['u' => 1, 'pct' => 1]],
    ['gap'],
    ['calc','texp', 'Total Expenses', 0, ['u' => 1, 'pct' => 1]],
    ['gap'],
    ['calc','nbt',  'Net Income (Loss) Before Taxes', 0, []],
    ['in',  'vat_payment', 'VAT PAYMENT', 0, ['tip' => 'Auto from the Expenses Ledger VAT total until the month is saved']],
    ['calc','nat',  '', 0, ['pct' => 1, 'cls' => 'is-r-nat']],
]);

$pageTitle  = 'Income Statement';
$activePage = 'stella_income_statement';
$bodyClass  = 'sp-stella_income_statement';

// ── Excel download (same layout as the screen) ──
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';
    $calc = function (array $v) {
        $o = [];
        $o['gross'] = $v['net_sales'] - $v['sales_discount'] - $v['cost_of_sales'];
        $o['tvar']  = $v['equipment_supplies'] + $v['depreciation_expense'] + $v['transportation_fuel'] + $v['lpg'];
        $o['tfix']  = $v['rent'] + $v['water_electricity'] + $v['drinking_water'] + $v['pest_control_bio'] + $v['common_area_charges'] + $v['exhaust_cleaning'] + $v['salaries'];
        $o['topex'] = $o['tvar'] + $o['tfix'];
        $o['tga']   = 0;
        foreach (['office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees','staff_meal','representation_benefits','professional_fees','communication','management_fee','freight_storage','repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development','construction_progress','insurance','admin_shares','miscellaneous_expense'] as $f) $o['tga'] += $v[$f];
        $o['texp']  = $o['topex'] + $o['tga'];
        $o['nbt']   = $o['gross'] - $o['texp'] + $v['other_income_royalty'];
        $o['nat']   = $o['nbt'] - $v['vat_payment'];
        return $o;
    };
    $C = []; $exTot = array_fill(1, 12, 0.0);
    foreach ($extras as $x) for ($m = 1; $m <= 12; $m++) $exTot[$m] += (float)$x["m$m"];
    for ($m = 1; $m <= 12; $m++) { $C[$m] = $calc($vals[$m]); $C[$m]['ni'] = $C[$m]['nat'] - $exTot[$m]; }
    $f2  = fn($n) => number_format((float)$n, 2, '.', '');
    $pc  = fn($n, $m) => $vals[$m]['net_sales'] > 0 ? round($n / $vals[$m]['net_sales'] * 100) . '%' : '';
    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('STELLA INCOME STATEMENT YR ' . $year);
    $xlsx->subtitle('Generated ' . date('F d, Y'));
    $xlsx->blank();
    $hd = ['Line Item'];
    for ($m = 1; $m <= 12; $m++) { $hd[] = $MONTHS[$m - 1] . ' ' . $year; $hd[] = '%'; }
    $xlsx->header($hd);
    foreach ($ROWS as $r) {
        if ($r[0] === 'gap') { $xlsx->blank(); continue; }
        if ($r[0] === 'head' || $r[0] === 'sub') { $xlsx->sectionRow($r[1]); continue; }
        $label = $r[2] !== '' ? $r[2] : 'Net Income after VAT payment';
        $cells = [($r[3] === 1 ? '    ' : '') . $label]; $key = $r[1];
        $tot = $r[0] === 'calc' && !in_array($key, ['tvar','tfix','gross','nbt']);
        for ($m = 1; $m <= 12; $m++) {
            $n = $r[0] === 'in' ? $vals[$m][$key] : $C[$m][$key];
            $cells[] = $f2($n);
            $cells[] = !empty($r[4]['pct']) ? $pc($n, $m) : '';
        }
        $tot ? $xlsx->totalRow($cells) : $xlsx->row($cells);
    }
    $xlsx->blank();
    $xlsx->sectionRow('EXTRA EXPENSES:');
    foreach ($extras as $x) {
        $cells = [$x['label']];
        for ($m = 1; $m <= 12; $m++) { $cells[] = (float)$x["m$m"] != 0 ? $f2($x["m$m"]) : ''; $cells[] = ''; }
        $xlsx->row($cells);
    }
    $cells = ['NET INCOME'];
    for ($m = 1; $m <= 12; $m++) { $cells[] = $f2($C[$m]['ni']); $cells[] = $pc($C[$m]['ni'], $m); }
    $xlsx->totalRow($cells);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $w = [38]; for ($m = 1; $m <= 12; $m++) { $w[] = 16; $w[] = 6; }
    $xlsx->columnWidths($w);
    $xlsx->output('income_statement_stella_' . $year . '.xlsx');
}

include '../layout.php';
$H = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<div class="is-controls">
  <form method="GET" class="is-yearform">
    <select name="year" class="form-control is-year" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
      <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
  <button class="btn btn-primary" type="button" id="btnSave">💾 Save</button>
  <a id="csvBtn" href="stella_income_statement.php?export_csv=1&amp;year=<?= $year ?>" class="btn btn-ghost">⬇ Download Excel</a>
  <span id="saveStatus" class="is-status"></span>
  <span class="is-legend"><i class="dot live"></i> live from ledger &nbsp; <i class="dot saved"></i> saved</span>
</div>

<div class="is-sheet" id="isSheet">
<table class="is-grid" id="isGrid">
  <thead>
    <tr>
      <th class="is-title">STELLA INCOME STATEMENT YR <?= $year ?></th>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <th class="is-mh" data-m="<?= $m ?>"><?= $MONTHS[$m - 1] ?> <?= $year ?>
        <span class="is-chip <?= $isSaved[$m] ? 'saved' : 'live' ?>" id="chip<?= $m ?>"><?= $isSaved[$m] ? 'saved' : 'live' ?></span></th>
      <th class="is-ph"></th>
      <?php endfor; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($ROWS as $r):
      $kind = $r[0];
      if ($kind === 'gap'): ?>
    <tr class="is-gap"><td class="is-lab"></td><?php for ($m = 1; $m <= 12; $m++): ?><td class="is-c"></td><td class="is-p"></td><?php endfor; ?></tr>
  <?php elseif ($kind === 'head' || $kind === 'sub'): ?>
    <tr class="is-<?= $kind ?>"><td class="is-lab l<?= $kind === 'sub' ? 1 : 0 ?>"><?= $H($r[1]) ?></td><?php for ($m = 1; $m <= 12; $m++): ?><td class="is-c"></td><td class="is-p"></td><?php endfor; ?></tr>
  <?php else:
      $key = $r[1]; $label = $r[2]; $lvl = $r[3]; $o = $r[4];
      $cls = 'is-r' . (!empty($o['u']) ? ' u' : '') . (isset($o['cls']) ? ' ' . $o['cls'] : '');
  ?>
    <tr class="<?= $cls ?>" data-key="<?= $key ?>">
      <td class="is-lab l<?= $lvl ?>"><?= $H($label) ?></td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <td class="is-c">
        <?php if ($kind === 'in'): ?>
        <input type="text" inputmode="decimal" autocomplete="off" class="is-in<?= !empty($o['ro']) ? ' ro' : '' ?>"
               data-m="<?= $m ?>" data-f="<?= $key ?>" value="<?= $vals[$m][$key] == 0 ? '' : number_format($vals[$m][$key], 2, '.', ',') ?>"
               <?= !empty($o['ro']) ? 'readonly tabindex="-1"' : '' ?> <?= !empty($o['tip']) ? 'title="' . $H($o['tip']) . '"' : '' ?>>
        <?php else: ?>
        <span class="is-calc" data-m="<?= $m ?>" data-c="<?= $key ?>">0.00</span>
        <?php endif; ?>
      </td>
      <td class="is-p"><?php if (!empty($o['pct'])): ?><span data-m="<?= $m ?>" data-pc="<?= $key ?>"></span><?php endif; ?></td>
      <?php endfor; ?>
    </tr>
  <?php endif; endforeach; ?>

    <!-- EXTRA EXPENSES -->
    <tr class="is-x-head"><td class="is-lab">EXTRA EXPENSES:</td><?php for ($m = 1; $m <= 12; $m++): ?><td class="is-c"></td><td class="is-p"></td><?php endfor; ?></tr>
    <?php foreach ($extras as $x): ?>
    <tr class="is-x" data-xid="<?= (int)$x['id'] ?>">
      <td class="is-lab is-xlab">
        <button type="button" class="is-xdel" data-xdel="<?= (int)$x['id'] ?>" title="Remove this line">✕</button>
        <input type="text" class="is-xl" value="<?= $H($x['label']) ?>" data-xl="<?= (int)$x['id'] ?>" autocomplete="off">
      </td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <td class="is-c"><input type="text" inputmode="decimal" autocomplete="off" class="is-in is-xin" data-xm="<?= $m ?>" data-xid="<?= (int)$x['id'] ?>"
           value="<?= (float)$x["m$m"] == 0 ? '' : number_format((float)$x["m$m"], 2, '.', ',') ?>"></td>
      <td class="is-p"></td>
      <?php endfor; ?>
    </tr>
    <?php endforeach; ?>
    <tr class="is-x-add"><td class="is-lab"><button type="button" id="btnAddX" class="is-addx">+ Add expense line</button></td><?php for ($m = 1; $m <= 12; $m++): ?><td class="is-c"></td><td class="is-p"></td><?php endfor; ?></tr>

    <tr class="is-gap"><td class="is-lab"></td><?php for ($m = 1; $m <= 12; $m++): ?><td class="is-c"></td><td class="is-p"></td><?php endfor; ?></tr>
    <tr class="is-r is-r-ni" data-key="ni">
      <td class="is-lab">NET INCOME</td>
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <td class="is-c"><span class="is-calc" data-m="<?= $m ?>" data-c="ni">0.00</span></td>
      <td class="is-p"><span data-m="<?= $m ?>" data-pc="ni"></span></td>
      <?php endfor; ?>
    </tr>
  </tbody>
</table>
</div>

<script>
const YEAR  = <?= (int)$year ?>;
const MDATE = <?= json_encode(array_values($monthDate)) ?>;          // index 0 = January
const FIELDS = <?= json_encode(IS_FIELDS) ?>;
const V = <?= json_encode($vals) ?>;                                  // V[month][field]
const X = {};                                                         // extra expense lines
<?php foreach ($extras as $x): ?>X[<?= (int)$x['id'] ?>] = {label: <?= json_encode($x['label']) ?>, m: <?= json_encode(array_map(fn($i) => (float)$x["m$i"], range(1, 12))) ?>};
<?php endforeach; ?>
const SAVED = <?= json_encode(array_map('boolval', array_values($isSaved))) ?>;
const OPEX_VAR = ['equipment_supplies','depreciation_expense','transportation_fuel','lpg'];
const OPEX_FIX = ['rent','water_electricity','drinking_water','pest_control_bio','common_area_charges','exhaust_cleaning','salaries'];
const GA = ['office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees','staff_meal','representation_benefits','professional_fees','communication','management_fee','freight_storage','repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development','construction_progress','insurance','admin_shares','miscellaneous_expense'];

const $ = (s, r) => (r || document).querySelector(s);
const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
const fmt = n => (Math.round(n * 100) / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const sum = (m, list) => list.reduce((s, f) => s + (V[m][f] || 0), 0);

// "1200+350.5" or "=1200+350.5" typed into a cell is calculated, like in Excel
function parseCell(txt) {
  let s = String(txt).replace(/^=/, '').replace(/,/g, '').trim();
  if (s === '') return 0;
  if (!/^[0-9+\-*/().\s]+$/.test(s)) return NaN;
  try { const r = Function('"use strict";return (' + s + ')')(); return isFinite(r) ? r : NaN; } catch (e) { return NaN; }
}

function compute(m) {
  const v = V[m], o = {};
  o.gross = v.net_sales - v.sales_discount - v.cost_of_sales;
  o.tvar  = sum(m, OPEX_VAR);
  o.tfix  = sum(m, OPEX_FIX);
  o.topex = o.tvar + o.tfix;
  o.tga   = sum(m, GA);
  o.texp  = o.topex + o.tga;
  o.nbt   = o.gross - o.texp + v.other_income_royalty;
  o.nat   = o.nbt - v.vat_payment;
  let ex = 0; for (const id in X) ex += X[id].m[m - 1] || 0;
  o.ni    = o.nat - ex;
  return o;
}

function render() {
  for (let m = 1; m <= 12; m++) {
    const o = compute(m), base = V[m].net_sales;
    $$('[data-c][data-m="' + m + '"]').forEach(el => {
      const n = o[el.dataset.c]; el.textContent = fmt(n); el.classList.toggle('neg', n < 0);
    });
    $$('[data-pc][data-m="' + m + '"]').forEach(el => {
      const k = el.dataset.pc;
      const n = (k in o) ? o[k] : V[m][k];
      el.textContent = base > 0 && n !== undefined ? Math.round(n / base * 100) + '%' : '';
    });
  }
}

// ── saving ────────────────────────────────────────────────
const dirty = {}, timers = {};
function setStatus(t, cls) { const s = $('#saveStatus'); s.textContent = t; s.className = 'is-status ' + (cls || ''); }
async function saveMonth(m) {
  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('stmt_date', MDATE[m - 1]);
  FIELDS.forEach(f => fd.append(f, V[m][f] || 0));
  try {
    const r = await (await fetch('stella_income_statement.php', {method: 'POST', body: fd})).json();
    if (!r.ok) throw new Error(r.msg || 'error');
    delete dirty[m]; SAVED[m - 1] = true;
    const c = $('#chip' + m); c.textContent = 'saved'; c.className = 'is-chip saved';
    return true;
  } catch (e) { setStatus('❌ ' + e.message, 'err'); return false; }
}
async function saveAll() {
  const ms = Object.keys(dirty).map(Number);
  if (!ms.length) { setStatus('✓ All saved', 'ok'); return; }
  setStatus('Saving…');
  let ok = true;
  for (const m of ms) ok = (await saveMonth(m)) && ok;
  if (ok) { setStatus('✓ Saved', 'ok'); setTimeout(() => { if ($('#saveStatus').textContent === '✓ Saved') setStatus(''); }, 3000); }
}
function touchMonth(m) {
  dirty[m] = true; setStatus('Unsaved changes…');
  clearTimeout(timers[m]); timers[m] = setTimeout(saveAll, 1200);
}
async function post(data) {
  const fd = new FormData(); for (const k in data) fd.append(k, data[k]);
  fd.append('year', YEAR);
  return (await fetch('stella_income_statement.php', {method: 'POST', body: fd})).json();
}

// ── cell editing ──────────────────────────────────────────
document.addEventListener('focusin', e => {
  const t = e.target;
  if (!t.classList || !t.classList.contains('is-in') || t.readOnly) return;
  const raw = t.dataset.xid ? (X[t.dataset.xid].m[t.dataset.xm - 1] || 0) : (V[t.dataset.m][t.dataset.f] || 0);
  t.value = raw === 0 ? '' : String(raw);
  t.select();
});
document.addEventListener('focusout', e => {
  const t = e.target;
  if (!t.classList || !t.classList.contains('is-in') || t.readOnly) return;
  const n = parseCell(t.value);
  const val = isNaN(n) ? 0 : Math.round(n * 100) / 100;
  t.value = val === 0 ? '' : fmt(val);
  if (t.dataset.xid) {
    const id = t.dataset.xid, i = t.dataset.xm - 1;
    if (X[id].m[i] === val) return;
    X[id].m[i] = val; render(); saveExtra(id);
  } else {
    const m = +t.dataset.m, f = t.dataset.f;
    if (V[m][f] === val) return;
    V[m][f] = val; render(); touchMonth(m);
  }
});

const xTimers = {};
function saveExtra(id) {
  setStatus('Unsaved changes…');
  clearTimeout(xTimers[id]);
  xTimers[id] = setTimeout(async () => {
    const r = await post({ajax_extra: 'save', id: id, label: X[id].label, amounts: JSON.stringify(X[id].m)});
    setStatus(r.ok ? '✓ Saved' : '❌ ' + (r.msg || 'error'), r.ok ? 'ok' : 'err');
    if (r.ok) setTimeout(() => { if ($('#saveStatus').textContent === '✓ Saved') setStatus(''); }, 3000);
  }, 900);
}
document.addEventListener('input', e => {
  const t = e.target;
  if (t.classList && t.classList.contains('is-xl')) { X[t.dataset.xl].label = t.value; saveExtra(t.dataset.xl); }
});
document.addEventListener('click', async e => {
  const del = e.target.closest('[data-xdel]');
  if (del) {
    const id = del.dataset.xdel;
    if (!confirm('Remove "' + (X[id].label || 'this line') + '" and its amounts?')) return;
    const r = await post({ajax_extra: 'del', id: id});
    if (r.ok) { delete X[id]; del.closest('tr').remove(); render(); }
    return;
  }
  if (e.target.id === 'btnAddX') {
    await saveAll();
    const r = await post({ajax_extra: 'add', label: 'NEW LINE'});
    if (r.ok) location.reload();
  }
});
$('#btnSave').addEventListener('click', saveAll);
window.addEventListener('beforeunload', e => { if (Object.keys(dirty).length) { e.preventDefault(); e.returnValue = ''; } });

render();
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>