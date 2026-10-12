<?php
// ============================================================
//  demiclab_income_statement.php — DemicLab Hero Income Statement
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Filters ───────────────────────────────────────────────
$stmtDate = $_GET['date'] ?? date('Y-m-d');
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stmtDate)) $stmtDate = date('Y-m-d');
$displayDate = date('F d, Y', strtotime($stmtDate));

// ── AJAX: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');

    $fields = [
        // Revenue
        'net_sales','sales_discount','cost_of_sales','cogs_capital',
        // Operating – Variable
        'equipment_supplies','depreciation_expense','transportation_fuel',
        // Operating – Fixed
        'rent','water_electricity','salaries',
        // G&A
        'cash_advances','agency_fees','representation_benefits','management_fee',
        'sponsorship_marketing','repairs_maintenance','bio_aug',
        'office_equipment_supplies','freight_storage','miscellaneous_expense',
        'communication','philhealth_sss','taxes_licenses',
        'roi_incorporator','amortization',
        // Receivables / Credited to Accounts
        'receivables_hmo','credited_hmo','credited_depreciation',
    ];

    $data = [
        'store_name'   => 'DemicLab-Main',
        'stmt_date'    => $_POST['stmt_date'] ?? $stmtDate,
        'stmt_year'    => (int)date('Y', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_month'   => (int)date('n', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_day'     => (int)date('j', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_label'   => trim($_POST['stmt_label'] ?? ''),
        'saved_by'     => $user['name'],
    ];
    foreach ($fields as $f) $data[$f] = (float)($_POST[$f] ?? 0);

    // net_sales, sales_discount, cost_of_sales and cogs_capital are all
    // auto-suggested on page load (from the Summary Report / Purchases
    // Ledger / COGS page respectively) but — like the BEG/PURC/END fields
    // on the COGS page — they remain fully editable. Whatever value is on
    // the screen when Save is pressed (the auto-suggestion, or the user's
    // own edit) is what gets stored, so the figure always reflects what's
    // actually shown without silently overwriting a manual correction.

    $cols      = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

    try {
        $sql = "INSERT INTO demiclab_income_statement ("
             . implode(',', array_map(fn($c) => "`$c`", $cols))
             . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")"
             . " ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $csvDate = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $csvDate)) $csvDate = date('Y-m-d');
    $csvRow = null;
    try {
        $s = $pdo->prepare("SELECT * FROM `demiclab_income_statement` WHERE store_name='DemicLab-Main' AND stmt_date=? LIMIT 1");
        $s->execute([$csvDate]);
        $csvRow = $s->fetch() ?: null;
    } catch (Throwable $ignored) {}

    $cv = fn($k) => $csvRow ? number_format((float)($csvRow[$k] ?? 0), 2, '.', '') : '0.00';

    $netSales    = (float)($csvRow['net_sales']     ?? 0);
    $salesDisc   = (float)($csvRow['sales_discount'] ?? 0);
    $cogs        = (float)($csvRow['cost_of_sales']  ?? 0);
    $cogsCapital = (float)($csvRow['cogs_capital']   ?? 0);
    $grossProfit = $netSales - $salesDisc - $cogs;

    $varFields   = ['equipment_supplies','depreciation_expense','transportation_fuel'];
    $fixedFields = ['rent','water_electricity','salaries'];
    $gaFields    = ['agency_fees','representation_benefits','management_fee','sponsorship_marketing',
                    'repairs_maintenance','bio_aug','office_equipment_supplies','freight_storage',
                    'miscellaneous_expense','communication','philhealth_sss','taxes_licenses',
                    'roi_incorporator','amortization'];

    $varTotal   = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $varFields));
    $fixedTotal = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $fixedFields));
    $opexTotal  = $varTotal + $fixedTotal;
    $gaTotal    = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $gaFields));
    $totalExp   = $opexTotal + $gaTotal;
    $netIncome  = $grossProfit - $totalExp;

    $receivablesHmo = (float)($csvRow['receivables_hmo']       ?? 0);
    $creditedHmo    = (float)($csvRow['credited_hmo']          ?? 0);
    $creditedDep    = (float)($csvRow['credited_depreciation'] ?? 0);
    $cashAdvances   = (float)($csvRow['cash_advances']         ?? 0);
    $cashInBank     = $netIncome - $receivablesHmo + $creditedHmo + $creditedDep;

    $pct = fn($part) => $netSales > 0 ? round($part / $netSales * 100, 1).'%' : '-';

    $varLabels = ['Equipment and Supplies','Depreciation Expense','Transportation and Fuel'];
    $fixedLabels = ['Rent','Water and Electricity','Salaries'];
    $gaLabels = ['Professional/Agency fees','Representation/Employee Benefits','Management fee',
                 'Marketing Expenses','Repairs & Maintenance','Bio Aug','Office Equipment & Supplies',
                 'Freight Charge','Miscellaneous','Communication','SSS/PHIC/HDMF Contributions',
                 'Taxes & License','ROI incorporator','Amortization'];

    $xlsx = new SimpleXlsxWriter(branchAccentColor('DemicLab-Main'));
    $xlsx->title('DEMICLAB-MAIN BRANCH — INCOME STATEMENT ' . date('Y', strtotime($csvDate)));
    $xlsx->subtitle(date('F d, Y', strtotime($csvDate)));
    $xlsx->blank();
    $xlsx->header(['Line Item', 'Amount', '% of Net Sales']);

    $xlsx->sectionRow('REVENUE');
    $xlsx->row(['Sales Revenue',          $cv('net_sales'),      $pct($netSales)]);
    $xlsx->row(['Sales Discount',         $cv('sales_discount'), $pct($salesDisc)]);
    $xlsx->row(['COGS(actual purchases)', $cv('cost_of_sales'),  $pct($cogs)]);
    $xlsx->row(['Cost of Sales (CAPITAL)',$cv('cogs_capital'),   $pct($cogsCapital)]);
    $xlsx->totalRow(['GROSS PROFIT',      number_format($grossProfit,2,'.',''), $pct($grossProfit)]);
    $xlsx->blank();

    $xlsx->sectionRow('OPERATING EXPENSES - VARIABLE');
    foreach ($varFields as $i => $f)
        $xlsx->row([$varLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL VARIABLE', number_format($varTotal,2,'.',''), $pct($varTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('OPERATING EXPENSES - FIXED');
    foreach ($fixedFields as $i => $f)
        $xlsx->row([$fixedLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL FIXED', number_format($fixedTotal,2,'.',''), $pct($fixedTotal)]);
    $xlsx->totalRow(['TOTAL OPERATING EXPENSES', number_format($opexTotal,2,'.',''), $pct($opexTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('GENERAL AND ADMINISTRATIVE EXPENSES');
    $xlsx->row(['Cash Advances (memo, excluded from total)', $cv('cash_advances'), $pct($cashAdvances)]);
    foreach ($gaFields as $i => $f)
        $xlsx->row([$gaLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL ADMINISTRATIVE EXPENSES', number_format($gaTotal,2,'.',''), $pct($gaTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('TOTALS');
    $xlsx->row(['Total Expenses',    number_format($totalExp,2,'.',''),  $pct($totalExp)]);
    $xlsx->totalRow(['Net Income (Loss)', number_format($netIncome,2,'.',''), $pct($netIncome)]);
    $xlsx->blank();

    $xlsx->sectionRow('RECEIVABLES / CREDITED TO ACCOUNTS');
    $xlsx->row(['Receivables - HMO/Corp Accts',    $cv('receivables_hmo'),       $pct($receivablesHmo)]);
    $xlsx->row(['Credited - HMO/Corp. Accts',      $cv('credited_hmo'),          $pct($creditedHmo)]);
    $xlsx->row(['Credited - Depreciation Expense', $cv('credited_depreciation'), $pct($creditedDep)]);
    $xlsx->totalRow(['CASH IN BANK (LOSS)PROFIT',  number_format($cashInBank,2,'.',''), $pct($cashInBank)]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([40,16,14]);
    $xlsx->output('income_statement_demiclab_main_'.$csvDate.'.xlsx');
}

// ── Load saved data ───────────────────────────────────────
$row = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM demiclab_income_statement WHERE store_name='DemicLab-Main' AND stmt_date=? LIMIT 1");
    $stmt->execute([$stmtDate]);
    $row = $stmt->fetch() ?: null;
} catch (Throwable $ignored) {}

$v = fn($k) => $row ? number_format((float)($row[$k] ?? 0), 2, '.', '') : '0.00';
// ── Auto-pull gross total from Branch Summary Report ──────
// Net Sales = SUM(gross_sales) for the selected month
$expYear  = (int)date('Y', strtotime($stmtDate));
$expMonth = (int)date('n', strtotime($stmtDate));
$storeGrossTotal = 0;
try {
    $sgStmt = $pdo->prepare("SELECT COALESCE(SUM(`gross_sales`),0) FROM `demiclab_report_entries` WHERE store_name='DemicLab-Main' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sgStmt->execute([$expYear, $expMonth]);
    $storeGrossTotal = (float)$sgStmt->fetchColumn();
} catch (Throwable $ignored) {}
$netSalesAuto = number_format($storeGrossTotal, 2, '.', '');
// ── Auto-pull Sales Discount from Branch Summary Report ──
// Sales Discount = SUM(total_discounts) for the selected month
$storeDiscountTotal = 0;
try {
    $sdStmt = $pdo->prepare("SELECT COALESCE(SUM(`total_discounts`),0) FROM `demiclab_report_entries` WHERE store_name='DemicLab-Main' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sdStmt->execute([$expYear, $expMonth]);
    $storeDiscountTotal = (float)$sdStmt->fetchColumn();
} catch (Throwable $ignored) {}
$salesDiscountAuto = number_format($storeDiscountTotal, 2, '.', '');
// ── Auto-pull COGS (actual purchases) from the Purchases Ledger ──
// "Materials Purchases (Consumables/CGS)" entered on the Purchases page
// (demic_purchases) is summed for this same month and flows straight into
// COGS(actual purchases) below — no manual re-entry, always in sync with
// whatever month is being viewed.
$purchasesAuto = 0;
try {
    $pcStmt = $pdo->prepare("SELECT COALESCE(SUM(`materials_purchases`),0) FROM `demic_purchases` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $pcStmt->execute([$expYear, $expMonth]);
    $purchasesAuto = (float)$pcStmt->fetchColumn();
} catch (Throwable $ignored) {}
$costOfSalesAuto = number_format($purchasesAuto, 2, '.', '');
// ── Auto-pull Cost of Sales (CAPITAL) from the COGS page ──
// The COGS page's "COS (Total)" KPI (BEG + PURC − END − MKTG COST, summed
// across that page's rows for the month) flows straight into Cost of Sales
// (CAPITAL) below — no manual re-entry, always in sync with the COGS page.
$cogsCapitalAutoRaw = 0;
try {
    $ccStmt = $pdo->prepare("SELECT COALESCE(SUM(`cos`),0) FROM `demiclab_cogs` WHERE store_name='DemicLab-Main' AND cogs_year=? AND cogs_month=?");
    $ccStmt->execute([$expYear, $expMonth]);
    $cogsCapitalAutoRaw = (float)$ccStmt->fetchColumn();
} catch (Throwable $ignored) {}
$cogsCapitalAuto = number_format($cogsCapitalAutoRaw, 2, '.', '');

// ── Auto-pull Income Statement fields from their matching Purchases
// Ledger column (demic_purchases) ──────────────────────────────────
// Each entry below maps an Income Statement field to the Purchases
// Ledger column that feeds it (same name in most cases; renamed where
// the ledger's category label differs from the statement's line item).
// Every mapped field is pre-filled with that column's live monthly
// total — exactly like COGS(actual purchases) above — while staying
// fully editable. Whatever value is on screen when Save is pressed
// (auto-suggestion or manual edit) is what gets stored.
$purchasesFieldMap = [
    'rent'                     => 'rent',
    'communication'            => 'communication',
    'repairs_maintenance'      => 'repairs_maintenance',
    'cash_advances'            => 'cash_advances',
    'management_fee'           => 'management_fee',
    'bio_aug'                  => 'bio',                  // Purchases "Bio" → Bio Aug
    'office_equipment_supplies' => 'office_supplies',      // Purchases "Office Supplies"
    'freight_storage'          => 'freight_charge',        // Purchases "Freight Charge"
    'miscellaneous_expense'    => 'misc',                  // Purchases "Misc"
    'agency_fees'              => 'professional_fee',      // Purchases "Professional Fee"
    'representation_benefits'  => 'employees_benefit',     // Purchases "Employees Benefit"
    'philhealth_sss'           => 'sss_phic_hdmf',         // Purchases "SSS/Phil/HDMF Contribution"
    'taxes_licenses'           => 'taxes_licenses',        // Purchases "Taxes & Licenses"
];
$purchasesAutoVals = array_fill_keys(array_unique(array_values($purchasesFieldMap)), 0.0);
try {
    $purchasesCols = array_keys($purchasesAutoVals);
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $purchasesCols));
    $paStmt = $pdo->prepare("SELECT $selectParts FROM `demic_purchases` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $paStmt->execute([$expYear, $expMonth]);
    $purchasesAutoVals = $paStmt->fetch(PDO::FETCH_ASSOC) ?: $purchasesAutoVals;
} catch (Throwable $ignored) {}
// Saved value wins if a statement already exists for this date; otherwise
// pre-fill with the live Purchases Ledger total for that field's mapped column.
$paVal = fn($k) => $row ? $v($k) : number_format((float)($purchasesAutoVals[$purchasesFieldMap[$k] ?? ''] ?? 0), 2, '.', '');


// Small helper to render one Income Statement input row, optionally with
// the green "↳ Auto from Purchases Ledger (editable)" badge/styling used
// throughout this page for auto-suggested-but-editable fields.
function is_render_row($field, $label, $val, $pctClass = '', $badge = null) {
    ?>
    <div class="is-row">
      <div class="is-label">
        <?= $label ?>
        <?php if ($badge): ?>
        <span style="font-family:var(--font-m);font-size:.6rem;color:#2563eb;margin-left:8px;
                     background:rgba(37,99,235,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(37,99,235,.15)">
          ↳ <?= $badge ?> <span style="opacity:.7">(editable)</span>
        </span>
        <?php endif; ?>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="<?=$field?>" data-field="<?=$field?>"
               value="<?=$val?>" oninput="recalc()"
               <?php if ($badge): ?>style="background:#eff6ff;color:#2563eb;font-weight:700;border-color:rgba(37,99,235,.2)"<?php endif; ?>>
        <div class="is-pct <?=$pctClass?>" id="pct_<?=$field?>">—</div>
      </div>
    </div>
    <?php
}

// ── Effective display values ──────────────────────────────
// If a statement was already saved for this date, its saved value wins
// (so a manual edit is never silently clobbered by the auto figure).
// Otherwise the field starts out pre-filled with the live auto-suggestion,
// exactly like the "SUGGESTED" fields on the COGS page.
$netSalesVal      = $row ? $v('net_sales')      : $netSalesAuto;
$salesDiscountVal = $row ? $v('sales_discount') : $salesDiscountAuto;
$costOfSalesVal   = $row ? $v('cost_of_sales')  : $costOfSalesAuto;
$cogsCapitalVal   = $row ? $v('cogs_capital')   : $cogsCapitalAuto;
// ── Expenses column totals (for the selected month) ──────
$expCols = [
    'amount_w_vat','vat','amount_wo_vat','non_vat','total_amount',
    'purchases','salaries','rent','medicine','lpg','repairs_maintenance',
    'fuel_trans','communication','transportation','light','drinking_water',
    'water','sss_phic_hdmf','taxes_licences','office_supplies','kitchen_supplies',
    'bio_pest_control','representation','miscellaneous','sir_budoy_nikki',
    'staff_meal','marketing','sales_discounts','row_total'
];
$expTotals = [];
try {
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $expCols));
    $expStmt = $pdo->prepare("SELECT $selectParts FROM `demiclab_expenses` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $expStmt->execute([$expYear, $expMonth]);
    $expTotals = $expStmt->fetch() ?: [];
} catch (Throwable $ignored) {}
$ef = fn($k) => isset($expTotals[$k]) ? number_format((float)$expTotals[$k], 2, '.', ',') : '0.00';
// ── Auto VAT Payment = total VAT from expenses ledger ─────
$vatAuto = (float)($expTotals['vat'] ?? 0);
$vatAutoFmt = $vatAuto > 0 ? number_format($vatAuto, 2, '.', '') : '0.00';





$pageTitle  = 'Income Statement';
$activePage = 'demiclab_income_statement';
include '../layout.php';
?>

<style>
.is-wrap { max-width: 780px; margin: 0 auto; }
.is-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 20px;
}
.is-header {
  background: #1e3060;
  color: #fff;
  padding: 18px 28px 14px;
}
.is-header-title {
  font-family: var(--font-m);
  font-size: .62rem;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: rgba(255,255,255,.5);
  margin-bottom: 4px;
}
.is-header-main {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -.02em;
  color: #fff;
}
.is-header-date {
  font-family: var(--font-m);
  font-size: .72rem;
  color: rgba(255,255,255,.6);
  margin-top: 3px;
}
.is-body { padding: 0; }

/* Section heading row */
.is-section {
  background: #f0f2f5;
  padding: 8px 28px;
  font-family: var(--font-m);
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--subtext2);
  border-top: 1px solid var(--border);
}
.is-subsection {
  padding: 6px 28px 2px 40px;
  font-family: var(--font-m);
  font-size: .6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--subtext);
  border-bottom: 1px solid #f0f2f5;
}

/* Row */
.is-row {
  display: flex;
  align-items: center;
  padding: 0 28px 0 44px;
  border-bottom: 1px solid #f4f5f7;
  min-height: 38px;
  transition: background .1s;
}
.is-row:hover { background: #fafbfc; }
.is-row.indent2 { padding-left: 60px; }
.is-label {
  flex: 1;
  font-size: .78rem;
  color: var(--text);
  padding: 6px 0;
}
.is-input-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 160px;
  justify-content: flex-end;
}
.is-input {
  width: 130px;
  padding: 5px 10px;
  text-align: right;
  font-family: var(--font-m);
  font-size: .78rem;
  color: var(--text);
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 6px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.is-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}
.is-pct {
  font-family: var(--font-m);
  font-size: .7rem;
  color: var(--subtext);
  width: 36px;
  text-align: right;
}
.is-pct.red  { color: var(--accent2); font-weight: 600; }
.is-pct.green{ color: #2563eb;  font-weight: 600; }
.is-pct.gold { color: var(--accent3); font-weight: 600; }

/* Total rows */
.is-total {
  display: flex;
  align-items: center;
  padding: 8px 28px 8px 44px;
  background: #f8f9fb;
  border-top: 2px solid var(--border2);
  border-bottom: 1px solid var(--border);
}
.is-total.underline { border-bottom: 2px solid var(--border2); }
.is-total-label {
  flex: 1;
  font-family: var(--font-m);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--subtext2);
}
.is-total-val {
  font-family: var(--font-m);
  font-size: .88rem;
  font-weight: 800;
  color: var(--text);
  min-width: 130px;
  text-align: right;
}
.is-total-pct {
  font-family: var(--font-m);
  font-size: .7rem;
  font-weight: 700;
  width: 36px;
  text-align: right;
  margin-left: 6px;
}

/* Net Income highlight */
.is-net {
  background: #fffbeb;
  border-top: 2px solid var(--accent3) !important;
}
.is-net .is-total-label { color: var(--accent3); font-size: .82rem; }
.is-net .is-total-val   { color: var(--accent3); font-size: 1rem; }

/* Controls */
.is-controls {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 18px;
}

.flash.toast {
  position: fixed; top: 68px; right: 22px;
  z-index: 9999; max-width: 320px;
  animation: fadeSlideDown .3s ease;
}
</style>

<!-- Controls -->
<div class="is-controls">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="date" name="date" class="form-control" style="max-width:180px"
           value="<?= htmlspecialchars($stmtDate) ?>" onchange="this.form.submit()">
  </form>
  <button class="btn btn-primary" onclick="saveAll()">💾 Save</button>
  <a id="csvBtn" href="demiclab_income_statement.php?export_csv=1&date=<?= htmlspecialchars($stmtDate) ?>" class="btn btn-ghost" style="gap:6px">⬇ Download Excel</a>
  <span id="saveStatus" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
  <a href="demic_bs.php?date=<?= htmlspecialchars($stmtDate) ?>"
     class="btn btn-ghost btn-sm" style="margin-left:auto">📊 Balance Sheet →</a>
</div>

<div class="is-wrap">
<div class="is-card">

  <!-- Header -->
  <div class="is-header">
    <div class="is-header-title">DemicLab-Main Branch</div>
    <div class="is-header-main">INCOME STATEMENT <?= date('Y', strtotime($stmtDate)) ?></div>
    <div class="is-header-date"><?= $displayDate ?></div>
  </div>

  <div class="is-body">

    <!-- ── REVENUE ── -->
    <div class="is-section">Revenue</div>
    <?php
    // Each entry: [field, label, source label, pct class]
    // All four rows below are auto-suggested from their source page/ledger
    // for the selected month, but — like the COGS page's BEG/PURC/END
    // fields — stay fully editable. The value on screen (suggestion or
    // manual edit) is what gets saved.
    $rows_auto_revenue = [
      ['net_sales',      'Sales Revenue',              $netSalesVal,      'Auto from Summary Report',  ''],
      ['sales_discount', 'Sales Discount',              $salesDiscountVal, 'Auto from Summary Report',  'red'],
      ['cost_of_sales',  'COGS(actual purchases)',      $costOfSalesVal,   'Auto from Purchases Ledger','red'],
      ['cogs_capital',   'Cost of Sales (CAPITAL)',     $cogsCapitalVal,   'Auto from COGS page',       'red'],
    ];
    foreach ($rows_auto_revenue as [$field, $label, $val, $srcLabel, $pctClass]): ?>
    <div class="is-row">
      <div class="is-label">
        <?= $label ?>
        <span style="font-family:var(--font-m);font-size:.6rem;color:#2563eb;margin-left:8px;
                     background:rgba(37,99,235,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(37,99,235,.15)">
          ↳ <?= $srcLabel ?> <span style="opacity:.7">(editable)</span>
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="<?=$field?>" data-field="<?=$field?>"
               value="<?=$val?>" oninput="recalc()"
               style="background:#eff6ff;color:#2563eb;font-weight:700;border-color:rgba(37,99,235,.2)">
        <div class="is-pct <?=$pctClass?>" id="pct_<?=$field?>">—</div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Gross Profit total -->
    <div class="is-total underline">
      <div class="is-total-label">Gross Profit</div>
      <div class="is-total-val" id="tot_gross_profit">0.00</div>
      <div class="is-total-pct" id="pct_gross_profit" style="color:#2563eb"></div>
    </div>

    <!-- ── OPERATING EXPENSES ── -->
    <div class="is-section">Operating Expenses</div>

    <div class="is-subsection">Variable</div>
    <?php
    $rows_var = [
      ['equipment_supplies',    'Equipment and Supplies'],
      ['depreciation_expense',  'Depreciation Expense'],
      ['transportation_fuel',   'Transportation and Fuel'],
    ];
    foreach ($rows_var as [$field, $label]): ?>
    <div class="is-row">
      <div class="is-label"><?= $label ?></div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="<?=$field?>" data-field="<?=$field?>"
               value="<?=$v($field)?>" oninput="recalc()">
        <div class="is-pct" id="pct_<?=$field?>">—</div>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="is-total">
      <div class="is-total-label" style="padding-left:16px">Total Variable</div>
      <div class="is-total-val" id="tot_variable">0.00</div>
      <div class="is-total-pct" id="pct_tot_variable" style="color:var(--accent2)"></div>
    </div>

    <div class="is-subsection">Fixed</div>
    <?php
    $rows_fixed = [
      ['rent',               'Rent'],
      ['water_electricity',  'Water and Electricity'],
      ['salaries',           'Salaries'],
    ];
    foreach ($rows_fixed as [$field, $label]):
      $isAuto = array_key_exists($field, $purchasesFieldMap);
      is_render_row(
        $field, $label,
        $isAuto ? $paVal($field) : $v($field),
        '',
        $isAuto ? 'Auto from Purchases Ledger' : null
      );
    endforeach; ?>
    <div class="is-total">
      <div class="is-total-label" style="padding-left:16px">Total Fixed</div>
      <div class="is-total-val" id="tot_fixed">0.00</div>
      <div class="is-total-pct" id="pct_tot_fixed" style="color:var(--accent2)"></div>
    </div>

    <!-- Total Operating Expenses -->
    <div class="is-total underline" style="background:#fff1f0">
      <div class="is-total-label" style="color:var(--accent2)">Total Operating Expenses</div>
      <div class="is-total-val" id="tot_operating" style="color:var(--accent2)">0.00</div>
      <div class="is-total-pct" id="pct_tot_operating" style="color:var(--accent2)"></div>
    </div>

    <!-- ── G&A EXPENSES ── -->
    <div class="is-section">General and Administrative Expenses</div>
    <?php
    $rows_ga = [
      ['cash_advances',             'Cash Advances'],
      ['agency_fees',               'Professional/Agency fees'],
      ['representation_benefits',   'Representation/Employee Benefits'],
      ['management_fee',            'Management fee'],
      ['repairs_maintenance',       'Repairs & Maintenance'],
      ['bio_aug',                   'Bio Aug'],
      ['office_equipment_supplies', 'Office Equipment & Supplies'],
      ['freight_storage',           'Freight Charge'],
      ['miscellaneous_expense',     'Miscellaneous'],
      ['sponsorship_marketing',     'Marketing Expenses'],
      ['communication',             'Communication'],
      ['philhealth_sss',            'SSS/PHIC/HDMF Contributions'],
      ['taxes_licenses',            'Taxes & License'],
      ['roi_incorporator',          'ROI incorporator'],
      ['amortization',              'Amortization'],
    ];
    foreach ($rows_ga as [$field, $label]):
      $isAuto = array_key_exists($field, $purchasesFieldMap);
      is_render_row(
        $field, $label,
        $isAuto ? $paVal($field) : $v($field),
        '',
        $isAuto ? 'Auto from Purchases Ledger' : null
      );
    endforeach; ?>
    <div class="is-total underline" style="background:#fff1f0">
      <div class="is-total-label" style="color:var(--accent2)">Total Administrative Expenses</div>
      <div class="is-total-val" id="tot_ga" style="color:var(--accent2)">0.00</div>
      <div class="is-total-pct" id="pct_tot_ga" style="color:var(--accent2)"></div>
    </div>

    <!-- Total Expenses -->
    <div class="is-total" style="background:#fff1f0;border-top:2px solid #dc354533">
      <div class="is-total-label" style="color:var(--accent2);font-size:.8rem">Total Expenses</div>
      <div class="is-total-val" id="tot_expenses" style="color:var(--accent2);font-size:.95rem">0.00</div>
      <div class="is-total-pct" id="pct_tot_expenses" style="color:var(--accent2)"></div>
    </div>

    <!-- Net Income (Loss) -->
    <div class="is-total is-net">
      <div class="is-total-label">Net Income (Loss)</div>
      <div class="is-total-val" id="tot_net_after_tax">0.00</div>
      <div class="is-total-pct" id="pct_net_after_tax" style="color:var(--accent3)"></div>
    </div>

    <!-- ── RECEIVABLES ── -->
    <div class="is-section">Receivables</div>
    <div class="is-row">
      <div class="is-label">HMO/Corp Accts</div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="receivables_hmo" data-field="receivables_hmo"
               value="<?=$v('receivables_hmo')?>" oninput="recalc()">
        <div class="is-pct" id="pct_receivables_hmo">—</div>
      </div>
    </div>

    <!-- ── CREDITED TO ACCOUNTS ── -->
    <div class="is-section">Credited to Accounts</div>
    <div class="is-row">
      <div class="is-label">HMO/Corp. Accts</div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="credited_hmo" data-field="credited_hmo"
               value="<?=$v('credited_hmo')?>" oninput="recalc()">
        <div class="is-pct" id="pct_credited_hmo">—</div>
      </div>
    </div>
    <div class="is-row">
      <div class="is-label">Depreciation Expense</div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="credited_depreciation" data-field="credited_depreciation"
               value="<?=$v('credited_depreciation')?>" oninput="recalc()">
        <div class="is-pct" id="pct_credited_depreciation">—</div>
      </div>
    </div>

    <!-- Cash in Bank (Loss)Profit -->
    <div class="is-total is-net">
      <div class="is-total-label">Cash in Bank (Loss)Profit</div>
      <div class="is-total-val" id="tot_cash_in_bank">0.00</div>
      <div class="is-total-pct" id="pct_cash_in_bank" style="color:var(--accent3)"></div>
    </div>

  </div><!-- /is-body -->
</div><!-- /is-card -->
</div><!-- /is-wrap -->

  </div></div>

<script>
const STMT_DATE  = <?= json_encode($stmtDate) ?>;

function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n)  { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function pct(part, base) {
  if (!base) return '—';
  return Math.round(part / base * 100) + '%';
}
function setPct(id, part, base, cls) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = pct(part, base);
  el.className   = 'is-pct' + (cls ? ' ' + cls : '');
}
function setTotal(id, val, pctId, base, cls) {
  const el = document.getElementById(id);
  if (el) { el.textContent = fmt(val); if(cls) el.style.color=''; }
  if (pctId) {
    const pe = document.getElementById(pctId);
    if (pe) pe.textContent = base > 0 ? Math.round(val/base*100)+'%' : '—';
  }
}

function recalc() {
  const netSales      = gv('net_sales');
  const salesDisc     = gv('sales_discount');
  const cogs          = gv('cost_of_sales');
  const cogsCapital   = gv('cogs_capital');

  // ── Revenue ──
  // Note: Cost of Sales (CAPITAL) is a memo line only — it is NOT subtracted
  // from Gross Profit, matching the source Excel.
  const grossProfit   = netSales - salesDisc - cogs;
  setPct('pct_net_sales',      netSales,  netSales, '');
  setPct('pct_sales_discount', salesDisc, netSales, 'red');
  setPct('pct_cost_of_sales',  cogs,      netSales, 'red');
  setPct('pct_cogs_capital',   cogsCapital, netSales, 'red');
  setTotal('tot_gross_profit', grossProfit, 'pct_gross_profit', netSales);

  // ── Variable Opex ──
  const varFields = ['equipment_supplies','depreciation_expense','transportation_fuel'];
  let varTotal = 0;
  varFields.forEach(f => {
    const val = gv(f);
    varTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_variable', varTotal, 'pct_tot_variable', netSales);

  // ── Fixed Opex ──
  const fixedFields = ['rent','water_electricity','salaries'];
  let fixedTotal = 0;
  fixedFields.forEach(f => {
    const val = gv(f);
    fixedTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_fixed', fixedTotal, 'pct_tot_fixed', netSales);

  const opexTotal = varTotal + fixedTotal;
  setTotal('tot_operating', opexTotal, 'pct_tot_operating', netSales);

  // ── G&A ──
  // Note: Cash Advances is displayed as a memo line but is NOT included in
  // the Total Administrative Expenses / Net Income calculation, matching
  // the source Excel (it's an advance/loan, not a realized expense).
  const gaFields = ['agency_fees','representation_benefits','management_fee','sponsorship_marketing','repairs_maintenance','bio_aug','office_equipment_supplies','freight_storage','miscellaneous_expense','communication','philhealth_sss','taxes_licenses','roi_incorporator','amortization'];
  let gaTotal = 0;
  gaFields.forEach(f => {
    const val = gv(f);
    gaTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  // Cash Advances still gets its own % display, just excluded from the total
  setPct('pct_cash_advances', gv('cash_advances'), netSales, 'red');
  setTotal('tot_ga', gaTotal, 'pct_tot_ga', netSales);

  // ── Totals ──
  const totalExp  = opexTotal + gaTotal;
  const netIncome = grossProfit - totalExp;

  setTotal('tot_expenses',      totalExp,  'pct_tot_expenses',     netSales);
  setTotal('tot_net_after_tax', netIncome, 'pct_net_after_tax',    netSales);

  // Color net income
  const el = document.getElementById('tot_net_after_tax');
  if (el) el.style.color = netIncome >= 0 ? 'var(--accent3)' : 'var(--accent2)';

  // ── Receivables / Credited to Accounts / Cash in Bank ──
  const receivablesHmo = gv('receivables_hmo');
  const creditedHmo    = gv('credited_hmo');
  const creditedDep    = gv('credited_depreciation');
  setPct('pct_receivables_hmo',   receivablesHmo, netSales, 'red');
  setPct('pct_credited_hmo',      creditedHmo,    netSales, '');
  setPct('pct_credited_depreciation', creditedDep, netSales, '');

  const cashInBank = netIncome - receivablesHmo + creditedHmo + creditedDep;
  setTotal('tot_cash_in_bank', cashInBank, 'pct_cash_in_bank', netSales);
  const cbEl = document.getElementById('tot_cash_in_bank');
  if (cbEl) cbEl.style.color = cashInBank >= 0 ? 'var(--accent3)' : 'var(--accent2)';
}

async function saveAll() {
  const btn = document.querySelector('button[onclick="saveAll()"]');
  const status = document.getElementById('saveStatus');
  btn.textContent = '…'; btn.disabled = true;

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('stmt_date', STMT_DATE);

  document.querySelectorAll('.is-input').forEach(el => {
    fd.append(el.dataset.field, el.value || '0');
  });

  try {
    const res  = await fetch('demiclab_income_statement.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = '#2563eb';
      showToast('✓ Income statement saved', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      status.textContent = '❌ Error';
    }
  } catch(e) {
    showToast('❌ Network error', 'error');
  }
  btn.textContent = '💾 Save'; btn.disabled = false;
  setTimeout(() => { status.textContent = ''; }, 4000);
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', recalc);
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>