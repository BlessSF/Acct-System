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

// ── Filters ───────────────────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year  < 2000 || $year  > 2100) $year  = (int)date('Y');
if ($month < 1    || $month > 12)   $month = (int)date('n');
$compare = !isset($_GET['year']) ? true : (($_GET['compare'] ?? '') === '1');   // show the previous month beside it
$prevY = $month === 1 ? $year - 1 : $year;
$prevM = $month === 1 ? 12 : $month - 1;

// Expenses Ledger columns that pre-fill a line until the month is saved
$autoExpenseMap = [
    'sales_discount' => ['sales_discounts'], 'transportation_fuel' => ['fuel_trans'], 'lpg' => ['lpg'], 'rent' => ['rent'],
    'water_electricity' => ['light','water'], 'drinking_water' => ['drinking_water'], 'pest_control_bio' => ['bio_pest_control'],
    'salaries' => ['salaries'], 'office_equipment_supplies' => ['office_supplies'], 'philhealth_sss' => ['sss_phic_hdmf'],
    'medical_supplies' => ['medicine'], 'staff_meal' => ['staff_meal'], 'representation_benefits' => ['representation'],
    'communication' => ['communication'], 'repairs_maintenance' => ['repairs_maintenance'], 'sponsorship_marketing' => ['marketing'],
    'taxes_licenses' => ['taxes_licences'], 'miscellaneous_expense' => ['miscellaneous'],
];
const IS_VAR = ['equipment_supplies','depreciation_expense','transportation_fuel','lpg'];
const IS_FIX = ['rent','water_electricity','drinking_water','pest_control_bio','common_area_charges','exhaust_cleaning','salaries'];
const IS_GA  = ['office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees','staff_meal','representation_benefits','professional_fees','communication','management_fee','freight_storage','repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development','construction_progress','insurance','admin_shares','miscellaneous_expense'];

// ── One month: saved row, else live values from the Summary Report / Expenses Ledger ──
function stellaIsLoad(PDO $pdo, int $y, int $m, array $autoMap): array
{
    $row = null;
    try {
        $q = $pdo->prepare("SELECT * FROM stella_income_statement WHERE store_name='Stella' AND YEAR(stmt_date)=? AND MONTH(stmt_date)=? ORDER BY stmt_date DESC, id DESC LIMIT 1");
        $q->execute([$y, $m]); $row = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}
    $net = 0.0;
    try {
        $q = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='Stella' AND YEAR(report_date)=? AND MONTH(report_date)=?");
        $q->execute([$y, $m]); $net = round((float)$q->fetchColumn(), 2);
    } catch (Throwable $e) {}
    $led = [];
    if (!$row) {
        try {
            $cols = ['vat']; foreach ($autoMap as $cs) foreach ($cs as $c) $cols[] = $c;
            $sel = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", array_unique($cols)));
            $q = $pdo->prepare("SELECT $sel FROM stella_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
            $q->execute([$y, $m]); $led = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
    }
    $v = [];
    foreach (IS_FIELDS as $f) {
        if ($f === 'net_sales')       $x = $net;
        elseif ($row)                 $x = (float)($row[$f] ?? 0);
        elseif ($f === 'vat_payment') $x = (float)($led['vat'] ?? 0);
        elseif (isset($autoMap[$f]))  { $x = 0.0; foreach ($autoMap[$f] as $c) $x += (float)($led[$c] ?? 0); }
        else                          $x = 0.0;
        $v[$f] = round($x, 2);
    }
    return ['v' => $v, 'saved' => $row !== null, 'date' => $row ? $row['stmt_date'] : sprintf('%04d-%02d-01', $y, $m)];
}
function stellaIsCalc(array $v, float $extra = 0.0): array
{
    $s = function (array $ks) use ($v) { $t = 0.0; foreach ($ks as $k) $t += (float)($v[$k] ?? 0); return $t; };
    $o = [];
    $o['gross'] = $v['net_sales'] - $v['sales_discount'] - $v['cost_of_sales'];
    $o['tvar']  = $s(IS_VAR); $o['tfix'] = $s(IS_FIX); $o['topex'] = $o['tvar'] + $o['tfix'];
    $o['tga']   = $s(IS_GA);  $o['texp'] = $o['topex'] + $o['tga'];
    $o['nbt']   = $o['gross'] - $o['texp'] + $v['other_income_royalty'];
    $o['nat']   = $o['nbt'] - $v['vat_payment'];
    $o['ni']    = $o['nat'] - $extra;
    return $o;
}
$cur  = stellaIsLoad($pdo, $year, $month, $autoExpenseMap);
$prev = $compare ? stellaIsLoad($pdo, $prevY, $prevM, $autoExpenseMap) : null;

// ── Extra expense lines (editable, stored per year; one amount per month) ──
$extras = []; $prevEx = [];
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM stella_is_extra WHERE ex_year=?"); $cnt->execute([$year]);
    if ((int)$cnt->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO stella_is_extra (ex_year, sort_order, label, saved_by) VALUES (?,?,?,?)");
        foreach (['QUARTERLY VAT PAYMENT','BUSINESS PERMIT RENEWAL','FB BOOSTING','TEAM BUILDING SHARE/SUMMER LEAGUE',
                  'HONDA BEAT MOTORCYCLE','ANNUAL INCOME TAX RETURN','BIR PENALTY','ILOILO CITY LOA'] as $i => $lb)
            $ins->execute([$year, $i + 1, $lb, $user['name']]);
    }
    $q = $pdo->prepare("SELECT * FROM stella_is_extra WHERE ex_year=? ORDER BY sort_order ASC, id ASC"); $q->execute([$year]);
    $extras = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($prevY !== $year) {
        $q = $pdo->prepare("SELECT * FROM stella_is_extra WHERE ex_year=? ORDER BY sort_order ASC, id ASC"); $q->execute([$prevY]);
        $prevRows = $q->fetchAll(PDO::FETCH_ASSOC);
    } else $prevRows = $extras;
    foreach ($prevRows as $i => $x) $prevEx[$i] = (float)$x["m$prevM"];
} catch (Throwable $e) {}
$exCur = 0.0; foreach ($extras as $x) $exCur += (float)$x["m$month"];
$exPrev = array_sum($prevEx);
$C  = stellaIsCalc($cur['v'], $exCur);
$CP = $prev ? stellaIsCalc($prev['v'], $exPrev) : null;

$MONTHS = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
$monthLabel = $MONTHS[$month - 1] . ' ' . $year;
$prevLabel  = $MONTHS[$prevM - 1] . ' ' . $prevY;
$money = function ($n) { $n = (float)$n; return abs($n) < 0.005 ? '—' : number_format($n, 2, '.', ','); };
$pctOf = function ($n, $base) { return (abs($n) < 0.005 || abs($base) < 0.005) ? '' : number_format($n / $base * 100, 0) . '%'; };

// ── Row layout (same order as the Excel sheet) ──
// kind: in = editable line | calc = formula | head / sub = headings | gap
$ROWS = [
    ['in','net_sales','Net Sales',0,['ro'=>1,'pct'=>0]], ['in','sales_discount','Sales Discount',0,[]], ['in','cost_of_sales','Cost of Sales',0,[]],
    ['calc','gross','Gross Profit',0,['t'=>'total']], ['in','other_income_royalty','Other Income-Royalty',0,[]],
    ['head','Operating Expenses'],
    ['in','equipment_supplies','Equipment and Supplies',1,[]], ['in','depreciation_expense','Depreciation Expense',1,[]],
    ['in','transportation_fuel','Transportation and Fuel',1,[]], ['in','lpg','LPG',1,[]], ['calc','tvar','Total',1,['t'=>'sub']],
    ['sub','Fixed:'],
    ['in','rent','Rent',1,[]], ['in','water_electricity','Water and Electricity',1,[]], ['in','drinking_water','Drinking Water',1,[]],
    ['in','pest_control_bio','Pest Control, Bio Aug,',1,[]], ['in','common_area_charges','Common Area Charges',1,[]],
    ['in','exhaust_cleaning','Exhaust Cleaning',1,[]], ['in','salaries','Salaries',1,[]], ['calc','tfix','Total',1,['t'=>'sub']],
    ['calc','topex','Total Operating Expenses',0,['t'=>'total']],
    ['head','General and Administrative Expenses'],
    ['in','office_equipment_supplies','Office Equipment &Supplies',1,[]], ['in','philhealth_sss','Philhealth, Pag- ibig,SSS Premium',1,[]],
    ['in','medical_supplies','Medical Supplies',1,[]], ['in','agency_fees','AGENCY FEES/COMMISSION',1,[]],
    ['in','bank_fees','Bank Fees',1,[]], ['in','staff_meal','Staff Meal',1,[]],
    ['in','representation_benefits','Representation/Employee Benefits',1,[]], ['in','professional_fees','Professional/Administrative Fees',1,[]],
    ['in','communication','Communication',1,[]], ['in','management_fee','Management Fee',1,[]],
    ['in','freight_storage','Freight Charge & Storage',1,[]], ['in','repairs_maintenance','Repairs and Maintenance',1,[]],
    ['in','sponsorship_marketing','Sponsorship and Marketing',1,[]], ['in','taxes_licenses','Taxes and Licenses-Others(Est)',1,[]],
    ['in','system_development','System Development',1,[]], ['in','construction_progress','Construction and Progress',1,[]],
    ['in','insurance','Insurance',1,[]], ['in','admin_shares','ADMIN Shares',1,[]], ['in','miscellaneous_expense','Miscellaneous Expense',1,[]],
    ['calc','tga','Total Administrative Expenses',0,['t'=>'total']],
    ['calc','texp','Total Expenses',0,['t'=>'total']],
    ['calc','nbt','Net Income (Loss) Before Taxes',0,['t'=>'grand']],
    ['in','vat_payment','VAT PAYMENT',0,[]],
    ['calc','nat','Net Income after VAT payment',0,['t'=>'grand']],
];
$neg = ['gross','nbt','nat','ni'];

$pageTitle  = 'Income Statement';
$activePage = 'stella_income_statement';
$bodyClass  = 'sp-stella_income_statement';

// ── Excel download (the month on screen, with the previous month beside it) ──
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';
    $f2 = fn($n) => number_format((float)$n, 2, '.', '');
    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('STELLA INCOME STATEMENT YR ' . $year);
    $xlsx->subtitle($monthLabel . ' — generated ' . date('F d, Y'));
    $xlsx->blank();
    $hd = ['Line Item']; if ($compare) { $hd[] = $prevLabel; $hd[] = '%'; } $hd[] = $monthLabel; $hd[] = '%';
    $xlsx->header($hd);
    $line = function ($label, $pv, $cv, $tot = false) use ($xlsx, $compare, $f2, $pctOf, $prev, $cur, $CP, $C) {
        $c = [$label];
        if ($compare) { $c[] = $f2($pv); $c[] = $pctOf($pv, $prev['v']['net_sales']); }
        $c[] = $f2($cv); $c[] = $pctOf($cv, $cur['v']['net_sales']);
        $tot ? $xlsx->totalRow($c) : $xlsx->row($c);
    };
    foreach ($ROWS as $r) {
        if ($r[0] === 'head' || $r[0] === 'sub') { $xlsx->sectionRow($r[1]); continue; }
        $k = $r[1];
        $cv = $r[0] === 'in' ? $cur['v'][$k] : $C[$k];
        $pv = $prev ? ($r[0] === 'in' ? $prev['v'][$k] : $CP[$k]) : 0;
        $line(($r[3] ? '    ' : '') . $r[2], $pv, $cv, $r[0] === 'calc' && ($r[4]['t'] ?? '') !== 'sub');
    }
    $xlsx->sectionRow('EXTRA EXPENSES:');
    foreach ($extras as $i => $x) $line($x['label'], $prevEx[$i] ?? 0, (float)$x["m$month"]);
    $line('NET INCOME', $prev ? $CP['ni'] : 0, $C['ni'], true);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths($compare ? [40, 18, 7, 18, 7] : [40, 18, 7]);
    $xlsx->output('income_statement_stella_' . $year . '_' . sprintf('%02d', $month) . '.xlsx');
}

include '../layout.php';
$H = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$cols = $compare ? 5 : 3;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<div class="fs-controls">
  <form method="GET" class="fs-form">
    <select name="month" class="form-control" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?>
    </select>
    <select name="year" class="form-control" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
    </select>
    <label class="fs-chk"><input type="checkbox" name="compare" value="1" <?= $compare ? 'checked' : '' ?> onchange="this.form.submit()"> Show previous month</label>
  </form>
  <label class="fs-chk"><input type="checkbox" id="hideZero"> Hide zero lines</label>
  <button class="btn btn-primary" type="button" id="btnSave">💾 Save</button>
  <a href="stella_income_statement.php?export_csv=1&amp;year=<?= $year ?>&amp;month=<?= $month ?><?= $compare ? '&amp;compare=1' : '' ?>" class="btn btn-ghost">⬇ Download Excel</a>
  <button type="button" class="btn btn-ghost" onclick="window.print()">🖨 Print</button>
  <span id="saveStatus" class="fs-status"></span>
</div>

<div class="fs-wrap">
<div class="fs-card">
  <div class="fs-title">
    <b>STELLA INCOME STATEMENT YR <?= $year ?></b>
    <span>Stella Branch · <?= $cur['saved'] ? 'saved for this month' : 'live from the Summary Report and Expenses Ledger — saved when you edit a line' ?></span>
  </div>
  <table class="fs-table" id="fsTable">
    <thead>
      <tr>
        <th></th>
        <?php if ($compare): ?><th><?= $H($prevLabel) ?></th><th class="pcw"></th><?php endif; ?>
        <th class="cur"><?= $H($monthLabel) ?></th><th class="cur pcw"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ROWS as $r):
        $kind = $r[0];
        if ($kind === 'head' || $kind === 'sub'): ?>
      <tr class="<?= $kind === 'head' ? 'sec' : 'sec2' ?>"><td colspan="<?= $cols ?>"><?= $H($r[1]) ?></td></tr>
    <?php continue; endif;
        $k = $r[1]; $label = $r[2]; $lvl = $r[3]; $o = $r[4];
        $vc = $kind === 'in' ? $cur['v'][$k]  : $C[$k];
        $vp = $prev ? ($kind === 'in' ? $prev['v'][$k] : $CP[$k]) : 0;
        $cls = $kind === 'in' ? 'line' : ($o['t'] ?? 'total');
        $zero = $kind === 'in' && abs($vc) < 0.005 && (!$prev || abs($vp) < 0.005) && $k !== 'net_sales';
        $showPct = $k !== 'net_sales';
    ?>
      <tr class="<?= $cls . ($zero ? ' zero' : '') ?>" data-key="<?= $k ?>" data-zero="<?= $zero ? 1 : 0 ?>">
        <td class="lbl<?= $lvl ? ' l1' : '' ?>"><?= $H($label) ?></td>
        <?php if ($compare): ?>
        <td class="num <?= ($prev && in_array($k, $neg, true) && $vp < 0) ? 'neg' : '' ?>"><?= $money($vp) ?></td>
        <td class="pc"><?= $showPct ? $pctOf($vp, $prev['v']['net_sales']) : '' ?></td>
        <?php endif; ?>
        <td class="num cur" id="v_<?= $k ?>">
          <?php if ($kind === 'in'): ?>
          <input class="fs-in<?= !empty($o['ro']) ? ' ro' : '' ?>" type="text" inputmode="decimal" autocomplete="off" data-f="<?= $k ?>"
                 value="<?= $vc == 0 ? '' : number_format($vc, 2, '.', ',') ?>" placeholder="0.00"
                 <?= !empty($o['ro']) ? 'readonly tabindex="-1" title="Auto from the Summary Report (Store Gross)"' : '' ?>>
          <?php else: ?><span class="<?= (in_array($k, $neg, true) && $vc < 0) ? 'neg' : '' ?>"><?= $money($vc) ?></span><?php endif; ?>
        </td>
        <td class="pc cur" id="p_<?= $k ?>"><?= $showPct ? $pctOf($vc, $cur['v']['net_sales']) : '' ?></td>
      </tr>
    <?php endforeach; ?>

      <tr class="sec x-sec"><td colspan="<?= $cols ?>">EXTRA EXPENSES:</td></tr>
    <?php foreach ($extras as $i => $x): $xv = (float)$x["m$month"]; $xp = $prevEx[$i] ?? 0; ?>
      <tr class="xline<?= ($xv == 0 && $xp == 0) ? ' zero' : '' ?>" data-zero="<?= ($xv == 0 && $xp == 0) ? 1 : 0 ?>" data-xid="<?= (int)$x['id'] ?>">
        <td class="lbl l1 xlab"><button type="button" class="fs-xdel" data-xdel="<?= (int)$x['id'] ?>" title="Remove this line">✕</button>
          <input type="text" class="fs-xl" value="<?= $H($x['label']) ?>" data-xl="<?= (int)$x['id'] ?>" autocomplete="off"></td>
        <?php if ($compare): ?><td class="num"><?= $money($xp) ?></td><td class="pc"></td><?php endif; ?>
        <td class="num cur"><input class="fs-in" type="text" inputmode="decimal" autocomplete="off" data-xid="<?= (int)$x['id'] ?>" placeholder="0.00"
             value="<?= $xv == 0 ? '' : number_format($xv, 2, '.', ',') ?>"></td>
        <td class="pc cur"></td>
      </tr>
    <?php endforeach; ?>
      <tr class="xadd"><td colspan="<?= $cols ?>"><button type="button" id="btnAddX" class="fs-addx">+ Add expense line</button></td></tr>

      <tr class="final" data-key="ni">
        <td class="lbl">NET INCOME</td>
        <?php if ($compare): ?><td class="num <?= ($prev && $CP['ni'] < 0) ? 'neg' : '' ?>"><?= $money($prev ? $CP['ni'] : 0) ?></td><td class="pc"><?= $prev ? $pctOf($CP['ni'], $prev['v']['net_sales']) : '' ?></td><?php endif; ?>
        <td class="num cur" id="v_ni"><span class="<?= $C['ni'] < 0 ? 'neg' : '' ?>"><?= $money($C['ni']) ?></span></td>
        <td class="pc cur" id="p_ni"><?= $pctOf($C['ni'], $cur['v']['net_sales']) ?></td>
      </tr>
    </tbody>
  </table>
  <div class="fs-note">Net Sales = Store Gross total of the Summary Report. Lines that exist in the Expenses Ledger are filled from it until you edit and save the month.
    You can type sums such as <b>1000+500</b>. Everything saves automatically.</div>
</div>
</div>

<script>
const FS = {
  month: <?= (int)$month ?>, year: <?= (int)$year ?>, date: <?= json_encode($cur['date']) ?>,
  fields: <?= json_encode(IS_FIELDS) ?>, V: <?= json_encode($cur['v']) ?>,
  VAR: <?= json_encode(IS_VAR) ?>, FIX: <?= json_encode(IS_FIX) ?>, GA: <?= json_encode(IS_GA) ?>, X: {}
};
<?php foreach ($extras as $x): ?>FS.X[<?= (int)$x['id'] ?>] = {label: <?= json_encode($x['label']) ?>, m: <?= json_encode(array_map(fn($i) => (float)$x["m$i"], range(1, 12))) ?>};
<?php endforeach; ?>
const $ = (s, r) => (r || document).querySelector(s);
const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
const money = n => Math.abs(n) < 0.005 ? '—' : n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const inp2  = n => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const sumK = ks => ks.reduce((a, k) => a + (+FS.V[k] || 0), 0);
const NEG = ['gross','nbt','nat','ni'];

function parseCell(txt) {
  const s = String(txt).replace(/^=/, '').replace(/,/g, '').trim();
  if (s === '') return 0;
  if (!/^[0-9+\-*/().\s]+$/.test(s)) return NaN;
  try { const r = Function('"use strict";return (' + s + ')')(); return isFinite(r) ? r : NaN; } catch (e) { return NaN; }
}
function recalc() {
  const v = FS.V, o = {};
  o.gross = v.net_sales - v.sales_discount - v.cost_of_sales;
  o.tvar = sumK(FS.VAR); o.tfix = sumK(FS.FIX); o.topex = o.tvar + o.tfix;
  o.tga = sumK(FS.GA); o.texp = o.topex + o.tga;
  o.nbt = o.gross - o.texp + v.other_income_royalty; o.nat = o.nbt - v.vat_payment;
  let ex = 0; for (const id in FS.X) ex += FS.X[id].m[FS.month - 1] || 0;
  o.ni = o.nat - ex;
  for (const k in o) {
    const c = $('#v_' + k); if (c) { c.innerHTML = '<span class="' + (NEG.includes(k) && o[k] < 0 ? 'neg' : '') + '">' + money(o[k]) + '</span>'; }
    const p = $('#p_' + k); if (p) p.textContent = (Math.abs(o[k]) < 0.005 || Math.abs(v.net_sales) < 0.005) ? '' : Math.round(o[k] / v.net_sales * 100) + '%';
  }
  FS.fields.forEach(f => {
    if (f === 'net_sales') return;
    const p = $('#p_' + f); if (p) p.textContent = (Math.abs(v[f]) < 0.005 || Math.abs(v.net_sales) < 0.005) ? '' : Math.round(v[f] / v.net_sales * 100) + '%';
  });
}

// ── saving ──
let timer = null, dirty = false;
function setStatus(t, c) { const s = $('#saveStatus'); s.textContent = t; s.className = 'fs-status ' + (c || ''); }
async function saveMonth() {
  const fd = new FormData(); fd.append('ajax_save', '1'); fd.append('stmt_date', FS.date);
  FS.fields.forEach(f => fd.append(f, FS.V[f] || 0));
  try {
    const r = await (await fetch('stella_income_statement.php', {method: 'POST', body: fd})).json();
    if (!r.ok) throw new Error(r.msg || 'error');
    dirty = false; setStatus('✓ Saved', 'ok'); setTimeout(() => { if ($('#saveStatus').textContent === '✓ Saved') setStatus(''); }, 3000);
    return true;
  } catch (e) { setStatus('✗ ' + e.message, 'err'); return false; }
}
function touch() { dirty = true; setStatus('Unsaved changes…'); clearTimeout(timer); timer = setTimeout(saveMonth, 1000); }
async function post(data) {
  const fd = new FormData(); for (const k in data) fd.append(k, data[k]); fd.append('year', FS.year);
  return (await fetch('stella_income_statement.php', {method: 'POST', body: fd})).json();
}
const xTimers = {};
function saveExtra(id) {
  setStatus('Unsaved changes…'); clearTimeout(xTimers[id]);
  xTimers[id] = setTimeout(async () => {
    const r = await post({ajax_extra: 'save', id: id, label: FS.X[id].label, amounts: JSON.stringify(FS.X[id].m)});
    setStatus(r.ok ? '✓ Saved' : '✗ ' + (r.msg || 'error'), r.ok ? 'ok' : 'err');
  }, 900);
}

document.addEventListener('focusin', e => {
  const t = e.target; if (!t.classList || !t.classList.contains('fs-in') || t.readOnly) return;
  const raw = t.dataset.xid ? (FS.X[t.dataset.xid].m[FS.month - 1] || 0) : (FS.V[t.dataset.f] || 0);
  t.value = raw === 0 ? '' : String(raw); t.select();
});
document.addEventListener('focusout', e => {
  const t = e.target; if (!t.classList || !t.classList.contains('fs-in') || t.readOnly) return;
  const n = parseCell(t.value), val = isNaN(n) ? 0 : Math.round(n * 100) / 100;
  t.value = val === 0 ? '' : inp2(val);
  if (t.dataset.xid) {
    const x = FS.X[t.dataset.xid]; if (x.m[FS.month - 1] === val) return;
    x.m[FS.month - 1] = val; recalc(); saveExtra(t.dataset.xid);
  } else {
    if (FS.V[t.dataset.f] === val) return;
    FS.V[t.dataset.f] = val; recalc(); touch();
  }
});
document.addEventListener('input', e => {
  const t = e.target; if (t.classList && t.classList.contains('fs-xl')) { FS.X[t.dataset.xl].label = t.value; saveExtra(t.dataset.xl); }
});
document.addEventListener('click', async e => {
  const del = e.target.closest('[data-xdel]');
  if (del) {
    const id = del.dataset.xdel;
    if (!confirm('Remove "' + (FS.X[id].label || 'this line') + '" and its amounts?')) return;
    const r = await post({ajax_extra: 'del', id: id});
    if (r.ok) { delete FS.X[id]; del.closest('tr').remove(); recalc(); }
    return;
  }
  if (e.target.id === 'btnAddX') {
    if (dirty) await saveMonth();
    const r = await post({ajax_extra: 'add', label: 'NEW LINE'}); if (r.ok) location.reload();
  }
});
$('#btnSave').addEventListener('click', saveMonth);
$('#hideZero').addEventListener('change', function () {
  $$('#fsTable tr[data-zero="1"]').forEach(r => r.classList.toggle('hide', this.checked));
  try { sessionStorage.setItem('stellaIsHideZero', this.checked ? '1' : '0'); } catch (e) {}
});
try { if (sessionStorage.getItem('stellaIsHideZero') === '1') { $('#hideZero').checked = true; $('#hideZero').dispatchEvent(new Event('change')); } } catch (e) {}
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
recalc();
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>