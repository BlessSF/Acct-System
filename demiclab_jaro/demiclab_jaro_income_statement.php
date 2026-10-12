<?php
// ============================================================
//  demiclab_jaro_income_statement.php — DemicLab Hero Income Statement
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Jaro') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Add any new Excel-matching columns that don't exist yet on older
// installs of this table (mirrors the pattern used on the Purchases page) ──
try {
    $existingIsCols = $pdo->query("SHOW COLUMNS FROM `demiclab_jaro_income_statement`")->fetchAll(PDO::FETCH_COLUMN);
    $newIsCols = [
        'wtax_rent'             => "decimal(14,2) DEFAULT 0.00",
        'service_fee'           => "decimal(14,2) DEFAULT 0.00",
        'bio'                   => "decimal(14,2) DEFAULT 0.00",
        'janitorial_cleaning'   => "decimal(14,2) DEFAULT 0.00",
        'fare'                  => "decimal(14,2) DEFAULT 0.00",
        'fuel_oil'              => "decimal(14,2) DEFAULT 0.00",
        'management_fee'        => "decimal(14,2) DEFAULT 0.00",
        'representation_expense'=> "decimal(14,2) DEFAULT 0.00",
        'other_equipment'       => "decimal(14,2) DEFAULT 0.00",
    ];
    foreach ($newIsCols as $col => $ddl) {
        if (!in_array($col, $existingIsCols, true)) {
            $pdo->exec("ALTER TABLE `demiclab_jaro_income_statement` ADD COLUMN `$col` $ddl");
        }
    }
} catch (Throwable $ignored) {}

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
        'net_sales', 'sales_discount', 'cost_of_sales',
        // Operating Expenses
        'rent', 'water_electricity', 'salaries',
        // General & Administrative Expenses
        'wtax_rent', 'service_fee', 'office_equipment_supplies', 'philhealth_sss',
        'sponsorship_marketing', 'bio', 'janitorial_cleaning', 'freight_storage',
        'fare', 'fuel_oil', 'repairs_maintenance', 'communication', 'management_fee',
        'representation_expense', 'professional_fees', 'taxes_licenses', 'miscellaneous_expense',
        'other_equipment',
    ];

    $data = [
        'store_name'   => 'DemicLab-Jaro',
        'stmt_date'    => $_POST['stmt_date'] ?? $stmtDate,
        'stmt_year'    => (int)date('Y', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_month'   => (int)date('n', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_day'     => (int)date('j', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_label'   => trim($_POST['stmt_label'] ?? ''),
        'saved_by'     => $user['name'],
    ];
    foreach ($fields as $f) $data[$f] = (float)($_POST[$f] ?? 0);

    // cost_of_sales and sales_discount are pre-filled on load from the
    // Purchases Ledger / Branch Summary Report (see the auto-pull block
    // below), but — like Rent and the other Purchases-mapped fields —
    // whatever value is on screen when Save is pressed (auto-suggestion
    // or manual edit) is what gets stored; nothing overrides it here.

    $cols      = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

    try {
        $sql = "INSERT INTO demiclab_jaro_income_statement ("
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
        $s = $pdo->prepare("SELECT * FROM `demiclab_jaro_income_statement` WHERE store_name='DemicLab-Jaro' AND stmt_date=? LIMIT 1");
        $s->execute([$csvDate]);
        $csvRow = $s->fetch() ?: null;
    } catch (Throwable $ignored) {}

    $cv = fn($k) => $csvRow ? number_format((float)($csvRow[$k] ?? 0), 2, '.', '') : '0.00';

    $netSales    = (float)($csvRow['net_sales']      ?? 0);
    $salesDisc   = (float)($csvRow['sales_discount'] ?? 0);
    $cogs        = (float)($csvRow['cost_of_sales']  ?? 0);
    $grossProfit = $netSales - $salesDisc - $cogs;

    $opexFields = ['rent','water_electricity','salaries'];
    $gaFields   = ['wtax_rent','service_fee','office_equipment_supplies','philhealth_sss',
                   'sponsorship_marketing','bio','janitorial_cleaning','freight_storage','fare',
                   'fuel_oil','repairs_maintenance','communication','management_fee',
                   'representation_expense','professional_fees','taxes_licenses',
                   'miscellaneous_expense','other_equipment'];

    $opexTotal = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $opexFields));
    $gaTotal   = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $gaFields));
    $totalExp  = $opexTotal + $gaTotal;
    $netIncome = $grossProfit - $totalExp;

    $pct = fn($part) => $netSales > 0 ? round($part / $netSales * 100, 1).'%' : '-';

    $opexLabels = ['Rent','Electricity','Salary'];
    $gaLabels = ['WTax Rent','Service Fee','Office Supplies','SSS/Phil/HDMF Contribution',
                 'Advertisement','Bio','Janitorial & Cleaning Supplies','Freight Charge','Fare',
                 'Fuel/Oil','Repairs & Maintenance','Communication','Management Fee',
                 'Representation Expense','Professional Fee','Taxes & Licenses','Misc','Other Equipment'];

    $xlsx = new SimpleXlsxWriter(branchAccentColor('DemicLab-Jaro'));
    $xlsx->title('DEMICLAB-JARO BRANCH — INCOME STATEMENT ' . date('Y', strtotime($csvDate)));
    $xlsx->subtitle(date('F d, Y', strtotime($csvDate)));
    $xlsx->blank();
    $xlsx->header(['Line Item', 'Amount', '% of Net Sales']);

    $xlsx->sectionRow('REVENUE');
    $xlsx->row(['Sales Revenue',          $cv('net_sales'),      $pct($netSales)]);
    $xlsx->row(['Sales Discounts',        $cv('sales_discount'), $pct($salesDisc)]);
    $xlsx->row(['COGS (Actual Purchases)',$cv('cost_of_sales'),  $pct($cogs)]);
    $xlsx->totalRow(['GROSS SALES',       number_format($grossProfit,2,'.',''), $pct($grossProfit)]);
    $xlsx->blank();

    $xlsx->sectionRow('OPERATING EXPENSES');
    foreach ($opexFields as $i => $f)
        $xlsx->row([$opexLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL OPERATING EXPENSES', number_format($opexTotal,2,'.',''), $pct($opexTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('GENERAL AND ADMINISTRATIVE EXPENSES');
    foreach ($gaFields as $i => $f)
        $xlsx->row([$gaLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL ADMINISTRATIVE EXPENSES', number_format($gaTotal,2,'.',''), $pct($gaTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('TOTALS');
    $xlsx->row(['Total Expenses', number_format($totalExp,2,'.',''), $pct($totalExp)]);
    $xlsx->totalRow(['Net Income (Loss)', number_format($netIncome,2,'.',''), $pct($netIncome)]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([38,16,14]);
    $xlsx->output('income_statement_demiclab_jaro_'.$csvDate.'.xlsx');
}

// ── Load saved data ───────────────────────────────────────
$row = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM demiclab_jaro_income_statement WHERE store_name='DemicLab-Jaro' AND stmt_date=? LIMIT 1");
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
    $sgStmt = $pdo->prepare("SELECT COALESCE(SUM(`gross_sales`),0) FROM `demiclab_jaro_report_entries` WHERE store_name='DemicLab-Jaro' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sgStmt->execute([$expYear, $expMonth]);
    $storeGrossTotal = (float)$sgStmt->fetchColumn();
} catch (Throwable $ignored) {}
$netSalesAuto = number_format($storeGrossTotal, 2, '.', '');
$netSalesVal  = $row ? $v('net_sales') : $netSalesAuto;
// ── Auto-pull Sales Discount from Branch Summary Report ──
// Sales Discount = SUM(total_discounts) for the selected month
$storeDiscountTotal = 0;
try {
    $sdStmt = $pdo->prepare("SELECT COALESCE(SUM(`total_discounts`),0) FROM `demiclab_jaro_report_entries` WHERE store_name='DemicLab-Jaro' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sdStmt->execute([$expYear, $expMonth]);
    $storeDiscountTotal = (float)$sdStmt->fetchColumn();
} catch (Throwable $ignored) {}
$salesDiscountAuto = number_format($storeDiscountTotal, 2, '.', '');
$salesDiscountVal  = $row ? $v('sales_discount') : $salesDiscountAuto;
// ── Auto-pull COGS (actual purchases) from the Purchases Ledger ──
// "Materials Purchases (Consumables/CGS)" entered on the Purchases page
// (demic_jaro_purchases) is summed for this same month and flows straight
// into Cost of Sales below — no manual re-entry, always in sync.
$purchasesAuto = 0;
try {
    $pcStmt = $pdo->prepare("SELECT COALESCE(SUM(`materials_purchases`),0) FROM `demic_jaro_purchases` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $pcStmt->execute([$expYear, $expMonth]);
    $purchasesAuto = (float)$pcStmt->fetchColumn();
} catch (Throwable $ignored) {}
$costOfSalesAuto = number_format($purchasesAuto, 2, '.', '');
$costOfSalesVal  = $row ? $v('cost_of_sales') : $costOfSalesAuto;

// ── Auto-pull Income Statement fields from their matching Purchases
// Ledger column (demic_jaro_purchases) ─────────────────────────────
// This Income Statement's field list now mirrors the DEMIC LAB JARO
// Excel sheet line-for-line, and each field maps 1:1 to the Purchases
// Ledger column with the same category (no more combined rows). Every
// mapped field is pre-filled with that column's live monthly total —
// exactly like Cost of Sales above — while staying fully editable.
// Whatever value is on screen when Save is pressed (auto-suggestion or
// manual edit) is what gets stored.
//
// Fields NOT in this map (SSS/Phil/HDMF Contribution, Taxes & Licenses)
// have no matching column anywhere in the Purchases Ledger, so they stay
// plain manual-entry fields — same as before.
$purchasesFieldMap = [
    'rent'                     => 'rent',
    'water_electricity'        => 'electricity',           // Excel "ELECTRICITY"
    'salaries'                 => 'salary',                 // Excel "SALARY"
    'wtax_rent'                => 'wtax_rent',              // Excel "WTAX RENT"
    'service_fee'              => 'service_fee',            // Excel "SERVICE FEE"
    'office_equipment_supplies'=> 'office_supplies',        // Excel "OFFICE SUPPLIES"
    'sponsorship_marketing'    => 'advertisement',          // Excel "ADVERTISEMENT"
    'bio'                      => 'bio',                    // Excel "BIO"
    'janitorial_cleaning'      => 'janitorial_cleaning',    // Excel "JANITORIAL & CLEANING SUPPLIES"
    'freight_storage'          => 'freight_charge',         // Excel "FREIGHT CHARGE"
    'fare'                     => 'fare_travel',            // Excel "FARE"
    'fuel_oil'                 => 'fuel_oil',               // Excel "FUEL/OIL"
    'repairs_maintenance'      => 'repairs_maintenance',    // Excel "REPAIRS & MAINTENANCE"
    'communication'            => 'communication',          // Excel "COMMUNICATION"
    'management_fee'           => 'management_fee',         // Excel "MANAGEMENT FEE"
    'representation_expense'   => 'representation_expense', // Excel "REPRESENTATION EXPENSE"
    'professional_fees'        => 'professional_fee',       // Excel "PROFESSIONAL FEE"
    'miscellaneous_expense'    => 'misc',                   // Excel "MISC"
    'other_equipment'          => 'other_equipment',        // Excel "OTHER EQUIPMENT"
    'philhealth_sss'           => 'sss_phic_hdmf',          // Excel "SSS/PHIL/HDMF CONTRIBUTION"
    'taxes_licenses'           => 'taxes_licenses',         // Excel "TAXES & LICENSES"
];
$purchasesAllCols = array_unique(array_values($purchasesFieldMap));
$purchasesAutoVals = array_fill_keys($purchasesAllCols, 0.0);
try {
    $selectParts = implode(',', array_map(fn($c) => "COALESCE(SUM(`$c`),0) AS `$c`", $purchasesAllCols));
    $paStmt = $pdo->prepare("SELECT $selectParts FROM `demic_jaro_purchases` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $paStmt->execute([$expYear, $expMonth]);
    $purchasesAutoVals = $paStmt->fetch(PDO::FETCH_ASSOC) ?: $purchasesAutoVals;
} catch (Throwable $ignored) {}
// True if a field is fed by the Purchases Ledger.
$isPurchasesAuto = fn($k) => array_key_exists($k, $purchasesFieldMap);
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
    $expStmt = $pdo->prepare("SELECT $selectParts FROM `demiclab_jaro_expenses` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $expStmt->execute([$expYear, $expMonth]);
    $expTotals = $expStmt->fetch() ?: [];
} catch (Throwable $ignored) {}
$ef = fn($k) => isset($expTotals[$k]) ? number_format((float)$expTotals[$k], 2, '.', ',') : '0.00';




$pageTitle  = 'Income Statement';
$activePage = 'demiclab_jaro_income_statement';
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
  <a id="csvBtn" href="demiclab_jaro_income_statement.php?export_csv=1&date=<?= htmlspecialchars($stmtDate) ?>" class="btn btn-ghost" style="gap:6px">⬇ Download Excel</a>
  <span id="saveStatus" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
</div>

<div class="is-wrap">
<div class="is-card">

  <!-- Header -->
  <div class="is-header">
    <div class="is-header-title">DemicLab-Jaro Branch</div>
    <div class="is-header-main">INCOME STATEMENT <?= date('Y', strtotime($stmtDate)) ?></div>
    <div class="is-header-date"><?= $displayDate ?></div>
  </div>

  <div class="is-body">

    <!-- ── REVENUE ── -->
    <div class="is-section">Revenue</div>
    <?php
    $rows_revenue = [];
    ?>
    <!-- Sales Revenue — auto-pulled from Branch Summary Report store_gross total, editable -->
    <div class="is-row">
      <div class="is-label">
        Sales Revenue
        <span style="font-family:var(--font-m);font-size:.6rem;color:#2563eb;margin-left:8px;
                     background:rgba(37,99,235,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(37,99,235,.15)">
          ↳ Auto from Summary Report <span style="opacity:.7">(editable)</span>
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="net_sales" data-field="net_sales"
               value="<?= $netSalesVal ?>" oninput="recalc()"
               style="background:#eff6ff;color:#2563eb;font-weight:700;border-color:rgba(37,99,235,.2)">
        <div class="is-pct" id="pct_net_sales">—</div>
      </div>
    </div>
    <!-- Sales Discounts — auto-pulled from Branch Summary Report total_discounts for this month, editable -->
    <div class="is-row">
      <div class="is-label">
        Sales Discounts
        <span style="font-family:var(--font-m);font-size:.6rem;color:#2563eb;margin-left:8px;
                     background:rgba(37,99,235,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(37,99,235,.15)">
          ↳ Auto from Summary Report <span style="opacity:.7">(editable)</span>
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="sales_discount" data-field="sales_discount"
               value="<?= $salesDiscountVal ?>" oninput="recalc()"
               style="background:#eff6ff;color:#2563eb;font-weight:700;border-color:rgba(37,99,235,.2)">
        <div class="is-pct red" id="pct_sales_discount">—</div>
      </div>
    </div>
    <!-- COGS (Actual Purchases) — auto-pulled from Purchases Ledger (Materials Purchases / Consumables-CGS) for this month, editable -->
    <div class="is-row">
      <div class="is-label">
        COGS (Actual Purchases)
        <span style="font-family:var(--font-m);font-size:.6rem;color:#2563eb;margin-left:8px;
                     background:rgba(37,99,235,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(37,99,235,.15)">
          ↳ Auto from Purchases Ledger <span style="opacity:.7">(editable)</span>
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="cost_of_sales" data-field="cost_of_sales"
               value="<?= $costOfSalesVal ?>" oninput="recalc()"
               style="background:#eff6ff;color:#2563eb;font-weight:700;border-color:rgba(37,99,235,.2)">
        <div class="is-pct red" id="pct_cost_of_sales">—</div>
      </div>
    </div>

    <!-- Gross Sales total -->
    <div class="is-total underline">
      <div class="is-total-label">Gross Sales</div>
      <div class="is-total-val" id="tot_gross_profit">0.00</div>
      <div class="is-total-pct" id="pct_gross_profit" style="color:#2563eb"></div>
    </div>

    <!-- ── OPERATING EXPENSES ── -->
    <div class="is-section">Operating Expenses</div>
    <?php
    $rows_opex = [
      ['rent',              'Rent'],
      ['water_electricity', 'Electricity'],
      ['salaries',          'Salary'],
    ];
    foreach ($rows_opex as [$field, $label]):
      $isAuto = $isPurchasesAuto($field);
      is_render_row(
        $field, $label,
        $isAuto ? $paVal($field) : $v($field),
        '',
        $isAuto ? 'Auto from Purchases Ledger' : null
      );
    endforeach; ?>

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
      ['wtax_rent',                'WTax Rent'],
      ['service_fee',              'Service Fee'],
      ['office_equipment_supplies','Office Supplies'],
      ['philhealth_sss',           'SSS/Phil/HDMF Contribution'],
      ['sponsorship_marketing',    'Advertisement'],
      ['bio',                      'Bio'],
      ['janitorial_cleaning',      'Janitorial & Cleaning Supplies'],
      ['freight_storage',          'Freight Charge'],
      ['fare',                     'Fare'],
      ['fuel_oil',                 'Fuel/Oil'],
      ['repairs_maintenance',      'Repairs & Maintenance'],
      ['communication',            'Communication'],
      ['management_fee',           'Management Fee'],
      ['representation_expense',   'Representation Expense'],
      ['professional_fees',        'Professional Fee'],
      ['taxes_licenses',           'Taxes & Licenses'],
      ['miscellaneous_expense',    'Misc'],
      ['other_equipment',          'Other Equipment'],
    ];
    foreach ($rows_ga as [$field, $label]):
      $isAuto = $isPurchasesAuto($field);
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
    <div class="is-total is-net" style="background:#eff6ff;border-top:2px solid #2563eb">
      <div class="is-total-label">Net Income (Loss)</div>
      <div class="is-total-val" id="tot_net_before_tax">0.00</div>
      <div class="is-total-pct" id="pct_net_before_tax" style="color:var(--accent3)"></div>
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

  // ── Revenue ──
  const grossProfit   = netSales - salesDisc - cogs;
  setPct('pct_net_sales',      netSales,  netSales, '');
  setPct('pct_sales_discount', salesDisc, netSales, 'red');
  setPct('pct_cost_of_sales',  cogs,      netSales, 'red');
  setTotal('tot_gross_profit', grossProfit, 'pct_gross_profit', netSales);

  // ── Operating Expenses (Rent, Electricity, Salary) ──
  const opexFields = ['rent','water_electricity','salaries'];
  let opexTotal = 0;
  opexFields.forEach(f => {
    const val = gv(f);
    opexTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_operating', opexTotal, 'pct_tot_operating', netSales);

  // ── G&A (18 lines matching the Excel sheet) ──
  const gaFields = ['wtax_rent','service_fee','office_equipment_supplies','philhealth_sss','sponsorship_marketing','bio','janitorial_cleaning','freight_storage','fare','fuel_oil','repairs_maintenance','communication','management_fee','representation_expense','professional_fees','taxes_licenses','miscellaneous_expense','other_equipment'];
  let gaTotal = 0;
  gaFields.forEach(f => {
    const val = gv(f);
    gaTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_ga', gaTotal, 'pct_tot_ga', netSales);

  // ── Totals ──
  const totalExp     = opexTotal + gaTotal;
  const netIncome    = grossProfit - totalExp;

  setTotal('tot_expenses',       totalExp,  'pct_tot_expenses',    netSales);
  setTotal('tot_net_before_tax', netIncome, 'pct_net_before_tax',  netSales);

  // Color Net Income (Loss)
  const el = document.getElementById('tot_net_before_tax');
  if (el) el.style.color = netIncome >= 0 ? 'var(--accent3)' : 'var(--accent2)';
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
    const res  = await fetch('demiclab_jaro_income_statement.php', {method:'POST', body:fd});
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