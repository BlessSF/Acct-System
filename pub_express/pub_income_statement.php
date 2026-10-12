<?php
// ============================================================
//  pub_income_statement.php — Pub Express Hero Income Statement
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Pub Express') {
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
        'net_sales','sales_discount','cost_of_sales',
        // Other Income
        'other_income_royalty',
        // Operating – Variable
        'equipment_supplies','depreciation_expense','transportation_fuel','lpg',
        // Operating – Fixed
        'rent','water_electricity','drinking_water','pest_control_bio',
        'common_area_charges','exhaust_cleaning','salaries',
        // G&A
        'office_equipment_supplies','philhealth_sss','medical_supplies',
        'agency_fees','bank_fees','staff_meal','representation_benefits',
        'professional_fees','communication','freight_storage',
        'repairs_maintenance','sponsorship_marketing','taxes_licenses',
        'system_development','construction_progress','insurance',
        'admin_shares','miscellaneous_expense',
        // Tax
        'vat_payment',
    ];

    $data = [
        'store_name'   => 'Pub Express',
        'stmt_date'    => $_POST['stmt_date'] ?? $stmtDate,
        'stmt_year'    => (int)date('Y', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_month'   => (int)date('n', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_day'     => (int)date('j', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_label'   => trim($_POST['stmt_label'] ?? ''),
        'saved_by'     => $user['name'],
    ];
    foreach ($fields as $f) $data[$f] = (float)($_POST[$f] ?? 0);

    $cols      = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

    try {
        $sql = "INSERT INTO pub_income_statement ("
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
        $s = $pdo->prepare("SELECT * FROM `pub_income_statement` WHERE store_name='Pub Express' AND stmt_date=? LIMIT 1");
        $s->execute([$csvDate]);
        $csvRow = $s->fetch() ?: null;
    } catch (Throwable $ignored) {}

    $cv = fn($k) => $csvRow ? number_format((float)($csvRow[$k] ?? 0), 2, '.', '') : '0.00';

    $netSales    = (float)($csvRow['net_sales']           ?? 0);
    $salesDisc   = (float)($csvRow['sales_discount']      ?? 0);
    $cogs        = (float)($csvRow['cost_of_sales']       ?? 0);
    $royalty     = (float)($csvRow['other_income_royalty']?? 0);
    $grossProfit = $netSales - $salesDisc - $cogs;

    $varFields   = ['equipment_supplies','depreciation_expense','transportation_fuel','lpg'];
    $fixedFields = ['rent','water_electricity','drinking_water','pest_control_bio','common_area_charges','exhaust_cleaning','salaries'];
    $gaFields    = ['office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees',
                    'staff_meal','representation_benefits','professional_fees','communication','freight_storage',
                    'repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development',
                    'construction_progress','insurance','admin_shares','miscellaneous_expense'];

    $varTotal     = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $varFields));
    $fixedTotal   = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $fixedFields));
    $gaTotal      = array_sum(array_map(fn($f) => (float)($csvRow[$f] ?? 0), $gaFields));
    $opexTotal    = $varTotal + $fixedTotal;
    $totalExp     = $opexTotal + $gaTotal;
    $netBeforeTax = $grossProfit - $totalExp + $royalty;
    $vatPayment   = (float)($csvRow['vat_payment'] ?? 0);
    $netAfterTax  = $netBeforeTax - $vatPayment;

    $pct = fn($part) => $netSales > 0 ? round($part / $netSales * 100, 1).'%' : '-';

    $varLabels = ['Equipment and Supplies','Depreciation Expense','Transportation and Fuel','LPG'];
    $fixedLabels = ['Rent','Water and Electricity','Drinking Water','Pest Control, Bio Aug.',
                    'Common Area Charges','Exhaust Cleaning','Salaries'];
    $gaLabels = ['Office Equipment & Supplies','Philhealth, Pag-ibig, SSS Premium','Medical Supplies',
                 'Agency Fees / Commission','Bank Fees','Staff Meal','Representation / Employee Benefits',
                 'Professional / Administrative Fees','Communication','Freight Charge & Storage',
                 'Repairs and Maintenance','Sponsorship and Marketing','Taxes and Licenses-Others (Est)',
                 'System Development','Construction and Progress','Insurance','ADMIN Shares','Miscellaneous Expense'];

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Pub Express'));
    $xlsx->title('Pub Express BRANCH — INCOME STATEMENT ' . date('Y', strtotime($csvDate)));
    $xlsx->subtitle(date('F d, Y', strtotime($csvDate)));
    $xlsx->blank();
    $xlsx->header(['Line Item', 'Amount', '% of Net Sales']);

    $xlsx->sectionRow('REVENUE');
    $xlsx->row(['Net Sales',              $cv('net_sales'),            $pct($netSales)]);
    $xlsx->row(['Sales Discount',         $cv('sales_discount'),       $pct($salesDisc)]);
    $xlsx->row(['Cost of Sales',          $cv('cost_of_sales'),        $pct($cogs)]);
    $xlsx->totalRow(['GROSS PROFIT',      number_format($grossProfit,2,'.',''), $pct($grossProfit)]);
    $xlsx->row(['Other Income - Royalty', $cv('other_income_royalty'), $pct($royalty)]);
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
    foreach ($gaFields as $i => $f)
        $xlsx->row([$gaLabels[$i], $cv($f), $pct((float)($csvRow[$f] ?? 0))]);
    $xlsx->totalRow(['TOTAL ADMINISTRATIVE EXPENSES', number_format($gaTotal,2,'.',''), $pct($gaTotal)]);
    $xlsx->blank();

    $xlsx->sectionRow('TOTALS');
    $xlsx->row(['Total Expenses',               number_format($totalExp,2,'.',''),     $pct($totalExp)]);
    $xlsx->row(['Net Income (Loss) Before Tax', number_format($netBeforeTax,2,'.',''), $pct($netBeforeTax)]);
    $xlsx->row(['VAT Payment',                  $cv('vat_payment'),                    $pct($vatPayment)]);
    $xlsx->totalRow(['NET INCOME AFTER TAX',    number_format($netAfterTax,2,'.',''),  $pct($netAfterTax)]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([38,16,14]);
    $xlsx->output('income_statement_pub_express_'.$csvDate.'.xlsx');
}

// ── Load saved data ───────────────────────────────────────
$row = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM pub_income_statement WHERE store_name='Pub Express' AND stmt_date=? LIMIT 1");
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
    $sgStmt = $pdo->prepare("SELECT COALESCE(SUM(`gross_sales`),0) FROM `pub_report_entries` WHERE store_name='Pub Express' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $sgStmt->execute([$expYear, $expMonth]);
    $storeGrossTotal = (float)$sgStmt->fetchColumn();
} catch (Throwable $ignored) {}
$netSalesAuto = number_format($storeGrossTotal, 2, '.', '');
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
    $expStmt = $pdo->prepare("SELECT $selectParts FROM `pub_express_expenses` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $expStmt->execute([$expYear, $expMonth]);
    $expTotals = $expStmt->fetch() ?: [];
} catch (Throwable $ignored) {}
$ef = fn($k) => isset($expTotals[$k]) ? number_format((float)$expTotals[$k], 2, '.', ',') : '0.00';
// ── Auto VAT Payment = total VAT from expenses ledger ─────
$vatAuto = (float)($expTotals['vat'] ?? 0);
$vatAutoFmt = $vatAuto > 0 ? number_format($vatAuto, 2, '.', '') : '0.00';


// ── Auto-map: Income Statement line items pulled from the Expenses Ledger ──
// Any field listed here mirrors the matching (or combined) column total from
// the branch's Expenses Ledger for the selected month, the same way Net
// Sales mirrors the Summary Report and VAT Payment already mirrored the
// ledger's VAT column. These fields become read-only on the form below.
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
$autoExpenseVal = [];
foreach ($autoExpenseMap as $__field => $__cols) {
    $__sum = 0.0;
    foreach ($__cols as $__c) $__sum += (float)($expTotals[$__c] ?? 0);
    $autoExpenseVal[$__field] = number_format($__sum, 2, '.', '');
}
$autoBadge = '<span style="font-family:var(--font-m);font-size:.58rem;color:var(--accent);margin-left:8px;background:rgba(15,123,92,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(15,123,92,.15)">↳ Auto from Expenses</span>';
$autoAttrs  = 'readonly tabindex="-1" style="background:#f0fdf4;color:var(--accent);font-weight:700;cursor:default;border-color:rgba(15,123,92,.2)"';





$pageTitle  = 'Income Statement';
$activePage = 'pub_express_income_statement';
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
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(15,123,92,.08);
}
.is-input.is-input-auto {
  background: #eff6ff;
  border-color: #93c5fd;
}
.is-input.is-input-auto:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}
.is-pct {
  font-family: var(--font-m);
  font-size: .7rem;
  color: var(--subtext);
  width: 36px;
  text-align: right;
}
.is-pct.red  { color: var(--accent2); font-weight: 600; }
.is-pct.green{ color: var(--accent);  font-weight: 600; }
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
  <a id="csvBtn" href="pub_income_statement.php?export_csv=1&date=<?= htmlspecialchars($stmtDate) ?>" class="btn btn-ghost" style="gap:6px">⬇ Download Excel</a>
  <span id="saveStatus" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
</div>

<div class="is-wrap" style="max-width:100%;margin-bottom:20px">

  <!-- ── Pub Express Expenses Summary ── -->
  <div class="is-card" style="overflow-x:auto">
    <div style="background:#1e3a5f;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div>
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.5);margin-bottom:2px">Pub Express Branch</div>
        <div style="font-size:.92rem;font-weight:700;color:#fff;letter-spacing:-.02em">
          EXPENSES LEDGER SUMMARY — <?= date('F Y', strtotime($stmtDate)) ?>
        </div>
      </div>
      <a href="pub_express_expenses.php?month=<?= $expMonth ?>&year=<?= $expYear ?>"
         class="btn btn-ghost btn-sm" style="color:#93c5fd;border-color:rgba(147,197,253,.25);background:rgba(147,197,253,.07);font-size:.65rem">
        View Full Ledger →
      </a>
    </div>

    <?php
    $expGroups = [
      'VAT BREAKDOWN' => [
        'amount_w_vat'   => 'Amount w/ VAT',
        'vat'            => 'VAT',
        'amount_wo_vat'  => 'Amount w/out VAT',
        'non_vat'        => 'Non VAT',
        'total_amount'   => 'TOTAL AMOUNT',
      ],
      'EXPENSE CATEGORIES' => [
        'purchases'          => 'Purchases',
        'salaries'           => 'Salaries',
        'rent'               => 'Rent',
        'medicine'           => 'Medicine',
        'lpg'                => 'LPG',
        'repairs_maintenance'=> 'Repairs & Maintenance',
        'fuel_trans'         => 'Fuel & Trans',
        'communication'      => 'Communication',
        'transportation'     => 'Transportation / Delivery fee',
        'light'              => 'Light',
        'drinking_water'     => 'Drinking Water',
        'water'              => 'Water',
        'sss_phic_hdmf'      => 'SSS / PHIC / HDMF',
        'taxes_licences'     => 'Taxes & Licences',
        'office_supplies'    => 'Office Supplies',
        'kitchen_supplies'   => 'Kitchen Supplies',
        'bio_pest_control'   => 'Bio Aug. / Pest Control',
        'representation'     => 'Representation',
        'miscellaneous'      => 'Miscellaneous',
        'sir_budoy_nikki'    => "Sir Budoy / M'Nikki",
        'staff_meal'         => 'Staff Meal',
        'marketing'          => 'Marketing',
        'sales_discounts'    => 'Sales Discounts',
        'row_total'          => 'ROW TOTAL',
      ],
    ];
    foreach ($expGroups as $groupName => $cols): ?>
    <div style="overflow-x:auto">
      <table style="border-collapse:collapse;width:max-content;min-width:100%">
        <thead>
          <tr>
            <td colspan="<?= count($cols) ?>"
                style="background:#162d4a;color:rgba(255,255,255,.6);font-family:var(--font-m);font-size:.55rem;
                       text-transform:uppercase;letter-spacing:.1em;padding:5px 12px;border-bottom:1px solid #2d5480">
              <?= $groupName ?>
            </td>
          </tr>
          <tr>
            <?php foreach ($cols as $col => $label):
              $isTotal = in_array($col, ['total_amount','row_total']);
            ?>
            <th style="background:<?= $isTotal ? '#1a4a2e' : '#1e3a5f' ?>;color:#fff;
                       font-family:var(--font-m);font-size:.52rem;font-weight:700;
                       text-transform:uppercase;letter-spacing:.05em;
                       padding:8px 10px;border:1px solid #2d5480;
                       white-space:normal;text-align:center;min-width:100px;max-width:120px;line-height:1.3">
              <?= htmlspecialchars($label) ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <?php foreach ($cols as $col => $label):
              $val = $expTotals[$col] ?? 0;
              $isTotal   = in_array($col, ['total_amount','row_total']);
              $isZero    = (float)$val == 0;
              $bgColor   = $isTotal ? '#f0fdf4' : ($isZero ? '#f8f9fb' : '#fff');
              $txtColor  = $isTotal ? 'var(--accent)' : ($isZero ? 'var(--muted)' : 'var(--text)');
              $fWeight   = $isTotal ? '800' : ($isZero ? '400' : '600');
            ?>
            <td style="background:<?= $bgColor ?>;border:1px solid #e3e6ea;
                       padding:9px 10px;text-align:right;
                       font-family:var(--font-m);font-size:.72rem;
                       color:<?= $txtColor ?>;font-weight:<?= $fWeight ?>;
                       white-space:nowrap;min-width:100px">
              <?= $isZero ? '<span style="color:var(--muted)">—</span>' : $ef($col) ?>
            </td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>

  </div><!-- /expenses summary card -->
</div><!-- /is-wrap expenses -->

<div class="is-wrap">
<div class="is-card">

  <!-- Header -->
  <div class="is-header">
    <div class="is-header-title">Pub Express Branch</div>
    <div class="is-header-main">INCOME STATEMENT <?= date('Y', strtotime($stmtDate)) ?></div>
    <div class="is-header-date"><?= $displayDate ?></div>
  </div>

  <div class="is-body">

    <!-- ── REVENUE ── -->
    <div class="is-section">Revenue</div>
    <?php
    $rows_revenue = [
      ['sales_discount',  'Sales Discount',   true,  'red'],
      ['cost_of_sales',   'Cost of Sales',    true,  'red'],
    ];
    ?>
    <!-- Net Sales — auto-pulled from Branch Summary Report store_gross total -->
    <div class="is-row">
      <div class="is-label">
        Net Sales
        <span style="font-family:var(--font-m);font-size:.6rem;color:var(--accent);margin-left:8px;
                     background:rgba(15,123,92,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(15,123,92,.15)">
          ↳ Auto from Summary Report
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="net_sales" data-field="net_sales"
               value="<?= $netSalesAuto ?>"
               readonly tabindex="-1"
               style="background:#f0fdf4;color:var(--accent);font-weight:700;cursor:default;border-color:rgba(15,123,92,.2)">
        <div class="is-pct" id="pct_net_sales">—</div>
      </div>
    </div>
    <?php foreach ($rows_revenue as [$field, $label, $show_pct, $pct_class]):
      $isAuto = isset($autoExpenseMap[$field]);
    ?>
    <div class="is-row">
      <div class="is-label"><?= $label ?><?= $isAuto ? $autoBadge : '' ?></div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input<?= $isAuto ? ' is-input-auto' : '' ?>" id="<?=$field?>" data-field="<?=$field?>"
               value="<?= $isAuto ? ($row ? $v($field) : $autoExpenseVal[$field]) : $v($field) ?>"
               oninput="recalc()">
        <?php if ($show_pct): ?>
        <div class="is-pct <?=$pct_class?>" id="pct_<?=$field?>">—</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Gross Profit total -->
    <div class="is-total underline">
      <div class="is-total-label">Gross Profit</div>
      <div class="is-total-val" id="tot_gross_profit">0.00</div>
      <div class="is-total-pct" id="pct_gross_profit" style="color:var(--accent)"></div>
    </div>

    <!-- Other Income -->
    <div class="is-row" style="padding-left:28px">
      <div class="is-label" style="color:var(--subtext2);font-size:.75rem">Other Income – Royalty</div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input" id="other_income_royalty" data-field="other_income_royalty"
               value="<?=$v('other_income_royalty')?>" oninput="recalc()">
        <div class="is-pct" id="pct_other_income_royalty">—</div>
      </div>
    </div>

    <!-- ── OPERATING EXPENSES ── -->
    <div class="is-section">Operating Expenses</div>

    <div class="is-subsection">Variable</div>
    <?php
    $rows_var = [
      ['equipment_supplies',    'Equipment and Supplies'],
      ['depreciation_expense',  'Depreciation Expense'],
      ['transportation_fuel',   'Transportation and Fuel'],
      ['lpg',                   'LPG'],
    ];
    foreach ($rows_var as [$field, $label]):
      $isAuto = isset($autoExpenseMap[$field]);
    ?>
    <div class="is-row">
      <div class="is-label"><?= $label ?><?= $isAuto ? $autoBadge : '' ?></div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input<?= $isAuto ? ' is-input-auto' : '' ?>" id="<?=$field?>" data-field="<?=$field?>"
               value="<?= $isAuto ? ($row ? $v($field) : $autoExpenseVal[$field]) : $v($field) ?>"
               oninput="recalc()">
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
      ['drinking_water',     'Drinking Water'],
      ['pest_control_bio',   'Pest Control, Bio Aug.'],
      ['common_area_charges','Common Area Charges'],
      ['exhaust_cleaning',   'Exhaust Cleaning'],
      ['salaries',           'Salaries'],
    ];
    foreach ($rows_fixed as [$field, $label]):
      $isAuto = isset($autoExpenseMap[$field]);
    ?>
    <div class="is-row">
      <div class="is-label"><?= $label ?><?= $isAuto ? $autoBadge : '' ?></div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input<?= $isAuto ? ' is-input-auto' : '' ?>" id="<?=$field?>" data-field="<?=$field?>"
               value="<?= $isAuto ? ($row ? $v($field) : $autoExpenseVal[$field]) : $v($field) ?>"
               oninput="recalc()">
        <div class="is-pct" id="pct_<?=$field?>">—</div>
      </div>
    </div>
    <?php endforeach; ?>
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
      ['office_equipment_supplies', 'Office Equipment & Supplies'],
      ['philhealth_sss',            'Philhealth, Pag-ibig, SSS Premium'],
      ['medical_supplies',          'Medical Supplies'],
      ['agency_fees',               'Agency Fees / Commission'],
      ['bank_fees',                 'Bank Fees'],
      ['staff_meal',                'Staff Meal'],
      ['representation_benefits',   'Representation / Employee Benefits'],
      ['professional_fees',         'Professional / Administrative Fees'],
      ['communication',             'Communication'],
      ['freight_storage',           'Freight Charge & Storage'],
      ['repairs_maintenance',       'Repairs and Maintenance'],
      ['sponsorship_marketing',     'Sponsorship and Marketing'],
      ['taxes_licenses',            'Taxes and Licenses-Others (Est)'],
      ['system_development',        'System Development'],
      ['construction_progress',     'Construction and Progress'],
      ['insurance',                 'Insurance'],
      ['admin_shares',              'ADMIN Shares'],
      ['miscellaneous_expense',     'Miscellaneous Expense'],
    ];
    foreach ($rows_ga as [$field, $label]):
      $isAuto = isset($autoExpenseMap[$field]);
    ?>
    <div class="is-row">
      <div class="is-label"><?= $label ?><?= $isAuto ? $autoBadge : '' ?></div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input<?= $isAuto ? ' is-input-auto' : '' ?>" id="<?=$field?>" data-field="<?=$field?>"
               value="<?= $isAuto ? ($row ? $v($field) : $autoExpenseVal[$field]) : $v($field) ?>"
               oninput="recalc()">
        <div class="is-pct" id="pct_<?=$field?>">—</div>
      </div>
    </div>
    <?php endforeach; ?>
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

    <!-- Net Income Before Tax -->
    <div class="is-total" style="background:#f0fdf4;border-top:2px solid var(--accent)">
      <div class="is-total-label" style="color:var(--accent)">Net Income (Loss) Before Taxes</div>
      <div class="is-total-val" id="tot_net_before_tax" style="color:var(--accent)">0.00</div>
      <div class="is-total-pct" id="pct_net_before_tax" style="color:var(--accent)"></div>
    </div>

    <!-- VAT Payment — auto-pulled from Expenses Ledger VAT total -->
    <div class="is-row" style="padding-left:28px">
      <div class="is-label" style="color:var(--subtext2);font-size:.75rem">
        VAT Payment
        <span style="font-family:var(--font-m);font-size:.58rem;color:var(--accent);margin-left:8px;
                     background:rgba(15,123,92,.08);padding:2px 7px;border-radius:10px;border:1px solid rgba(15,123,92,.15)">
          ↳ Auto from Expenses
        </span>
      </div>
      <div class="is-input-wrap">
        <input type="number" step="0.01" class="is-input is-input-auto" id="vat_payment" data-field="vat_payment"
               value="<?= $row ? $v('vat_payment') : $vatAutoFmt ?>"
               oninput="recalc()"
               style="background:#f0fdf4;color:var(--accent);font-weight:700;border-color:rgba(15,123,92,.2)">
        <div class="is-pct" id="pct_vat_payment">—</div>
      </div>
    </div>

    <!-- Net Income After Tax -->
    <div class="is-total is-net">
      <div class="is-total-label">Net Income After Tax</div>
      <div class="is-total-val" id="tot_net_after_tax">0.00</div>
      <div class="is-total-pct" id="pct_net_after_tax" style="color:var(--accent3)"></div>
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
  const royalty       = gv('other_income_royalty');

  // ── Revenue ──
  const grossProfit   = netSales - salesDisc - cogs;
  setPct('pct_net_sales',      netSales,  netSales, '');
  setPct('pct_sales_discount', salesDisc, netSales, 'red');
  setPct('pct_cost_of_sales',  cogs,      netSales, 'red');
  setTotal('tot_gross_profit', grossProfit, 'pct_gross_profit', netSales);

  // ── Variable Opex ──
  const varFields = ['equipment_supplies','depreciation_expense','transportation_fuel','lpg'];
  let varTotal = 0;
  varFields.forEach(f => {
    const val = gv(f);
    varTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_variable', varTotal, 'pct_tot_variable', netSales);

  // ── Fixed Opex ──
  const fixedFields = ['rent','water_electricity','drinking_water','pest_control_bio','common_area_charges','exhaust_cleaning','salaries'];
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
  const gaFields = ['office_equipment_supplies','philhealth_sss','medical_supplies','agency_fees','bank_fees','staff_meal','representation_benefits','professional_fees','communication','freight_storage','repairs_maintenance','sponsorship_marketing','taxes_licenses','system_development','construction_progress','insurance','admin_shares','miscellaneous_expense'];
  let gaTotal = 0;
  gaFields.forEach(f => {
    const val = gv(f);
    gaTotal += val;
    setPct('pct_'+f, val, netSales, 'red');
  });
  setTotal('tot_ga', gaTotal, 'pct_tot_ga', netSales);

  // ── Totals ──
  const totalExp     = opexTotal + gaTotal;
  const netBeforeTax = grossProfit - totalExp + royalty;
  const vatPayment   = gv('vat_payment');
  const netAfterTax  = netBeforeTax - vatPayment;

  setTotal('tot_expenses',      totalExp,     'pct_tot_expenses',     netSales);
  setTotal('tot_net_before_tax',netBeforeTax, 'pct_net_before_tax',   netSales);
  setPct('pct_vat_payment', vatPayment, netSales, 'red');
  setTotal('tot_net_after_tax', netAfterTax,  'pct_net_after_tax',    netSales);

  // Color net after tax
  const el = document.getElementById('tot_net_after_tax');
  if (el) el.style.color = netAfterTax >= 0 ? 'var(--accent3)' : 'var(--accent2)';
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
    const res  = await fetch('pub_income_statement.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
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