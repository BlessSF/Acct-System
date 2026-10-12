<?php
// ============================================================
//  dois_cashflow.php — Dois Branch Cashflow Statement
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Dois') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `dois_cashflow` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `cf_date`         date NOT NULL,
    `cf_year`         int(4) NOT NULL,
    `cf_month`        tinyint(2) NOT NULL,
    `store_name`      varchar(50) NOT NULL DEFAULT 'Dois',
    `cash_beg`        decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
    `sales`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `inv_purchases`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `expenses`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `pdc_loan`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `withdrawals`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `net_cash_flow`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash_end`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`        varchar(100) DEFAULT NULL,
    `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

$lastDay     = date('Y-m-d', mktime(0,0,0,$fMonth+1,0,$fYear));
$displayDate = date('n/j/Y', strtotime($lastDay));

// ── Helper: live-pull SUM(pdc) from dois_expenses with full diagnostics ──
function pullPdcTotal(PDO $pdo, int $year, int $month): array {
    $debug = ['step' => 'start', 'table_exists' => null, 'column_exists' => null, 'raw_value' => null, 'error' => null];
    try {
        // 1. Does the table exist at all?
        $chk = $pdo->query("SHOW TABLES LIKE 'dois_expenses'");
        $debug['table_exists'] = $chk && $chk->rowCount() > 0;

        if (!$debug['table_exists']) {
            $debug['error'] = "Table 'dois_expenses' does not exist on this database.";
            return [0.0, $debug];
        }

        // 2. Does the pdc column exist?
        $colChk = $pdo->query("SHOW COLUMNS FROM dois_expenses LIKE 'pdc'");
        $debug['column_exists'] = $colChk && $colChk->rowCount() > 0;

        if (!$debug['column_exists']) {
            $debug['error'] = "Column 'pdc' does not exist in dois_expenses. Run the PDC/CA/Withdrawal/Depreciation migration SQL.";
            return [0.0, $debug];
        }

        // 3. Pull the actual sum
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(pdc),0) AS total_pdc FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        $row = $stmt->fetch();
        $debug['raw_value'] = $row['total_pdc'] ?? null;
        $debug['step'] = 'done';

        $value = isset($row['total_pdc']) && is_numeric($row['total_pdc']) ? (float)$row['total_pdc'] : 0.0;
        return [$value, $debug];

    } catch (Throwable $e) {
        $debug['error'] = $e->getMessage();
        return [0.0, $debug];
    }
}

// ── Helper: pull the "Selected VAT" total saved from the Expenses page
//    (checkbox-selected rows, VAT column only) for a given month ──────
function pullSelectedVatTotal(PDO $pdo, string $store, int $year, int $month): float {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `dois_cf_vat_selection` (
            `id`            int(11) NOT NULL AUTO_INCREMENT,
            `store_name`    varchar(50) NOT NULL DEFAULT 'Dois',
            `sel_year`      int(4) NOT NULL,
            `sel_month`     tinyint(2) NOT NULL,
            `vat_total`     decimal(12,2) NOT NULL DEFAULT 0.00,
            `row_count`     int(11) NOT NULL DEFAULT 0,
            `saved_by`      varchar(100) DEFAULT NULL,
            `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $stmt = $pdo->prepare("SELECT vat_total FROM dois_cf_vat_selection WHERE store_name=? AND sel_year=? AND sel_month=?");
        $stmt->execute([$store, $year, $month]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (float)$v : 0.0;
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── AJAX: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $cashBeg     = (float)($_POST['cash_beg']     ?? 0);
        // Inventory Purchases — always re-pulled live: SUM(purchases) from dois_expenses
        // + whatever VAT total was saved from the checkbox-selected rows on the Expenses page
        $invPurc = 0.0;
        try {
            $purcStmt = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
            $purcStmt->execute([$fYear,$fMonth]);
            $selectedVat = pullSelectedVatTotal($pdo, 'Dois', $fYear, $fMonth);
            $invPurc = (float)$purcStmt->fetchColumn() + $selectedVat;
        } catch (Throwable $e) {
            $invPurc = pullSelectedVatTotal($pdo, 'Dois', $fYear, $fMonth);
        }
        // Withdrawals (Pull-out) — always re-pulled live: SUM(withdrawal) from dois_expenses
        $withdrawals = 0.0;
        try {
            $wdStmt = $pdo->prepare("SELECT COALESCE(SUM(withdrawal),0) FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
            $wdStmt->execute([$fYear,$fMonth]);
            $withdrawals = (float)$wdStmt->fetchColumn();
        } catch (Throwable $e) {
            $withdrawals = 0.0;
        }

        [$pdcLoan, $pdcDebug] = pullPdcTotal($pdo, $fYear, $fMonth);

        // Expenses — sum of all operating expense columns (excludes PDC, CA, withdrawal, depreciation, sales_discounts, purchases)
        $expenses = 0.0;
        try {
            $expStmt = $pdo->prepare("SELECT COALESCE(SUM(
                salaries + rent + medicine + lpg + repairs_maintenance + fuel_trans +
                communication + transportation + light + drinking_water + water +
                sss_phic_hdmf + taxes_licences + office_supplies + kitchen_supplies +
                bio_pest_control + representation + miscellaneous + sir_budoy_nikki +
                staff_meal + pest_control_bio_aug + commission_fees + exhaust_cleaning + bank_fees + admin_salary_shares + marketing
            ),0) AS total_expenses FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
            $expStmt->execute([$fYear,$fMonth]);
            $expenses = (float)$expStmt->fetchColumn();
        } catch (Throwable $e) {
            $expenses = 0.0;
        }

        // Sales — always re-pulled live, never trusted from POST
        $sgStmt = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?");
        $sgStmt->execute([$fYear,$fMonth]);
        $sales = (float)$sgStmt->fetchColumn();

        $netCashFlow = $sales - $invPurc - $expenses - $pdcLoan - $withdrawals;
        $cashEnd     = $cashBeg + $netCashFlow;

        $existing = $pdo->prepare("SELECT id FROM dois_cashflow WHERE store_name='Dois' AND cf_year=? AND cf_month=? LIMIT 1");
        $existing->execute([$fYear,$fMonth]);
        $existingId = $existing->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE dois_cashflow SET cf_date=?, cash_beg=?, sales=?, inv_purchases=?, expenses=?, pdc_loan=?, withdrawals=?, net_cash_flow=?, cash_end=?, saved_by=? WHERE id=?")
                ->execute([$lastDay,$cashBeg,$sales,$invPurc,$expenses,$pdcLoan,$withdrawals,$netCashFlow,$cashEnd,$user['name'],$existingId]);
        } else {
            $pdo->prepare("INSERT INTO dois_cashflow (cf_date,cf_year,cf_month,store_name,cash_beg,sales,inv_purchases,expenses,pdc_loan,withdrawals,net_cash_flow,cash_end,saved_by) VALUES (?,?,?,'Dois',?,?,?,?,?,?,?,?,?)")
                ->execute([$lastDay,$fYear,$fMonth,$cashBeg,$sales,$invPurc,$expenses,$pdcLoan,$withdrawals,$netCashFlow,$cashEnd,$user['name']]);
        }
        echo json_encode(['ok'=>true,'net_cash_flow'=>$netCashFlow,'cash_end'=>$cashEnd,'pdc_debug'=>$pdcDebug]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $s = $pdo->prepare("SELECT * FROM dois_cashflow WHERE store_name='Dois' AND cf_year=? AND cf_month=? LIMIT 1");
    $s->execute([$fYear,$fMonth]);
    $row = $s->fetch() ?: null;
    $cv  = fn($k) => $row ? number_format((float)($row[$k] ?? 0),2,'.','') : '0.00';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dois_cashflow_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['CASHFLOW STATEMENT']);
    fputcsv($out,['']);
    fputcsv($out,['','For the month Ending',$displayDate]);
    fputcsv($out,['','Cash at Beginning of Month',$cv('cash_beg')]);
    fputcsv($out,['']);
    fputcsv($out,['','Sales',$cv('sales')]);
    fputcsv($out,['']);
    fputcsv($out,['less']);
    fputcsv($out,['','Inventory Purchases',$cv('inv_purchases')]);
    fputcsv($out,['','Expenses',$cv('expenses')]);
    fputcsv($out,['','PDC (loan)',$cv('pdc_loan')]);
    fputcsv($out,['','Withdrawals (Pull-out)',$cv('withdrawals')]);
    fputcsv($out,['']);
    fputcsv($out,['Net Cash Flow','',$cv('net_cash_flow')]);
    fputcsv($out,['']);
    fputcsv($out,['Cash at Beginning of Month','',$cv('cash_beg')]);
    fputcsv($out,['Cash at End of Month','',$cv('cash_end')]);
    fclose($out);
    exit;
}

// ── Load saved data ───────────────────────────────────────
$s = $pdo->prepare("SELECT * FROM dois_cashflow WHERE store_name='Dois' AND cf_year=? AND cf_month=? LIMIT 1");
$s->execute([$fYear,$fMonth]);
$saved = $s->fetch() ?: null;

$cashBeg     = (float)($saved['cash_beg']      ?? 0);

// Sales — always live-pulled from Summary Report (same as Net Sales in Income Statement)
$sgStmt = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='Dois' AND YEAR(report_date)=? AND MONTH(report_date)=?");
$sgStmt->execute([$fYear,$fMonth]);
$sales = (float)$sgStmt->fetchColumn();

// Inventory Purchases — always live-pulled: SUM(purchases) from dois_expenses
// + whatever VAT total was saved from the checkbox-selected rows on the Expenses page
$invPurc = 0.0;
$selectedVatTotal = pullSelectedVatTotal($pdo, 'Dois', $fYear, $fMonth);
try {
    $purcStmt = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $purcStmt->execute([$fYear,$fMonth]);
    $invPurc = (float)$purcStmt->fetchColumn() + $selectedVatTotal;
} catch (Throwable $e) {
    $invPurc = $selectedVatTotal;
}

// Expenses — always live-pulled: sum of all operating expense columns from dois_expenses
$expenses = 0.0;
try {
    $expStmt = $pdo->prepare("SELECT COALESCE(SUM(
        salaries + rent + medicine + lpg + repairs_maintenance + fuel_trans +
        communication + transportation + light + drinking_water + water +
        sss_phic_hdmf + taxes_licences + office_supplies + kitchen_supplies +
        bio_pest_control + representation + miscellaneous + sir_budoy_nikki +
        staff_meal + pest_control_bio_aug + commission_fees + exhaust_cleaning + bank_fees + admin_salary_shares + marketing
    ),0) AS total_expenses FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $expStmt->execute([$fYear,$fMonth]);
    $expenses = (float)$expStmt->fetchColumn();
} catch (Throwable $e) {
    $expenses = 0.0;
}

// PDC (loan) — always live-pulled, with full diagnostics available
[$pdcLoan, $pdcDebug] = pullPdcTotal($pdo, $fYear, $fMonth);

// Withdrawals (Pull-out) — always live-pulled: SUM(withdrawal) from dois_expenses
$withdrawals = 0.0;
try {
    $wdStmt = $pdo->prepare("SELECT COALESCE(SUM(withdrawal),0) FROM dois_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
    $wdStmt->execute([$fYear,$fMonth]);
    $withdrawals = (float)$wdStmt->fetchColumn();
} catch (Throwable $e) {
    $withdrawals = 0.0;
}
$netCashFlow = $sales - $invPurc - $expenses - $pdcLoan - $withdrawals;
$cashEnd     = $cashBeg + $netCashFlow;

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'Cashflow';
$activePage = 'dois_cashflow';
include '../layout.php';
?>

<style>
.cf-wrap { max-width: 640px; margin: 0 auto; }

.cf-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.cf-header-card .eyebrow {
  font-family: var(--font-m); font-size: .58rem; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.cf-header-card .title { font-size: 1.2rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.cf-header-card .subtitle { font-family: var(--font-m); font-size: .67rem; color: rgba(255,255,255,.5); margin-top: 4px; }

.cf-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }

.cf-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: 0 2px 12px rgba(0,0,0,.06);
  overflow: hidden;
}

.cf-title-row { background: #1e3060; padding: 14px 28px; text-align: center; }
.cf-title-row .cf-main-title {
  font-size: 1rem; font-weight: 800; color: #fff;
  letter-spacing: .04em; text-transform: uppercase; font-family: var(--font-m);
}

.cf-row {
  display: flex; align-items: center; padding: 0 28px;
  border-bottom: 1px solid #f0f2f5; min-height: 46px; transition: background .1s;
}
.cf-row:hover { background: #fafbfc; }
.cf-row.indent { padding-left: 52px; }

.cf-label { flex: 1; font-size: .82rem; color: var(--text); font-weight: 500; }
.cf-label.bold { font-weight: 700; }

.cf-input-wrap { display: flex; align-items: center; }
.cf-input {
  width: 150px; padding: 7px 12px; text-align: right;
  font-family: var(--font-m); font-size: .82rem; color: var(--text);
  background: #fff; border: 1px solid var(--border); border-radius: 7px;
  outline: none; transition: border-color .15s, box-shadow .15s;
}
.cf-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }
.cf-input.manual-field { background: #fffbeb; border-color: rgba(217,119,6,.25); }
.cf-input.auto-field {
  background: #f0f4ff; color: #3b5bdb; font-weight: 700;
  cursor: default; border-color: rgba(59,91,219,.2);
}
.auto-badge {
  font-family: var(--font-m); font-size: .58rem;
  color: var(--accent); margin-left: 8px;
  background: rgba(15,123,92,.08); padding: 2px 7px;
  border-radius: 10px; border: 1px solid rgba(15,123,92,.15);
  white-space: nowrap;
}

.cf-section-label {
  padding: 10px 28px 4px;
  font-style: italic; font-size: .8rem; color: var(--text);
}

.cf-total {
  display: flex; align-items: center; padding: 12px 28px;
  border-top: 2px solid var(--border2); border-bottom: 1px solid var(--border);
  background: #f8f9fb;
}
.cf-total-label { flex: 1; font-size: .85rem; font-weight: 700; font-style: italic; color: var(--text); }
.cf-total-val { font-family: var(--font-m); font-size: 1rem; font-weight: 800; min-width: 150px; text-align: right; }

.cf-summary-block { background: #eef3fb; }
.cf-summary-row { display: flex; align-items: center; padding: 10px 28px; border-bottom: 1px solid #dce5f2; }
.cf-summary-row:last-child { border-bottom: none; }
.cf-summary-label { flex: 1; font-size: .82rem; font-weight: 700; color: var(--text); }
.cf-summary-val { font-family: var(--font-m); font-size: .9rem; font-weight: 800; min-width: 150px; text-align: right; color: var(--text); }

.cf-save-status { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

.cf-debug-box {
  max-width: 640px; margin: 0 auto 16px; padding: 10px 16px;
  border-radius: 8px; font-family: var(--font-m); font-size: .68rem;
  line-height: 1.6;
}
.cf-debug-box.ok    { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.cf-debug-box.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
</style>

<!-- Page Header -->
<div class="cf-header-card">
  <div>
    <div class="eyebrow">Dois Branch · Finance</div>
    <div class="title">Cashflow Statement</div>
    <div class="subtitle">Format follows the standard branch cashflow worksheet</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 Dois
  </span>
</div>

<!-- Controls -->
<div class="cf-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:140px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for($y=date('Y')-5;$y<=date('Y')+10;$y++): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button type="button" class="btn btn-primary" onclick="saveCashflow()">💾 Save</button>
    <a href="dois_cashflow.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="cf-save-status"></span>
  </form>
</div>

<?php if ($pdcDebug['error']): ?>
<!-- PDC Diagnostic — shows the real reason PDC isn't pulling, remove once fixed -->
<div class="cf-debug-box error">
  ⚠ PDC AUTO-PULL DIAGNOSTIC: <?= htmlspecialchars($pdcDebug['error']) ?><br>
  table_exists: <?= $pdcDebug['table_exists'] === null ? 'n/a' : ($pdcDebug['table_exists'] ? 'yes' : 'NO') ?>
  &nbsp;|&nbsp;
  column_exists: <?= $pdcDebug['column_exists'] === null ? 'n/a' : ($pdcDebug['column_exists'] ? 'yes' : 'NO') ?>
</div>
<?php else: ?>
<div class="cf-debug-box ok">
  ✓ PDC AUTO-PULL OK — raw SUM(pdc) for <?= $months[$fMonth-1] ?> <?= $fYear ?>: <?= htmlspecialchars((string)$pdcDebug['raw_value']) ?>
</div>
<?php endif; ?>

<!-- Statement -->
<div class="cf-wrap">
<div class="cf-card">

  <div class="cf-title-row">
    <div class="cf-main-title">Cashflow Statement</div>
  </div>

  <div class="cf-row">
    <div class="cf-label">For the month Ending</div>
    <div class="cf-input-wrap">
      <span class="cf-input" style="background:#f8f9fb;color:var(--subtext2);font-weight:700;text-align:right;border-color:transparent"><?= $displayDate ?></span>
    </div>
  </div>

  <div class="cf-row">
    <div class="cf-label">Cash at Beginning of Month</div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field" id="cash_beg" value="<?= number_format((float)$cashBeg, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-row" style="border-top:8px solid #f8f9fb">
    <div class="cf-label bold">
      Sales
      <span class="auto-badge">↳ Auto from Income Statement (Net Sales)</span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input auto-field" id="sales" value="<?= number_format((float)$sales, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-section-label">less</div>

  <div class="cf-row indent">
    <div class="cf-label">
      Inventory Purchases
      <span class="auto-badge" title="SUM(purchases) <?= number_format($invPurc - $selectedVatTotal,2) ?> + Selected VAT <?= number_format($selectedVatTotal,2) ?> from Expenses checkboxes">
        ↳ Auto from Expenses<?= $selectedVatTotal > 0 ? ' + VAT ('.number_format($selectedVatTotal,2).')' : ' (check rows in Expenses to add VAT)' ?>
      </span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input auto-field" id="inv_purchases" value="<?= number_format((float)$invPurc, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-row indent">
    <div class="cf-label">
      Expenses
      <span class="auto-badge">↳ Auto from Expenses</span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input auto-field" id="expenses" value="<?= number_format((float)$expenses, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-row indent">
    <div class="cf-label">
      PDC (loan)
      <span class="auto-badge">↳ Auto from Expenses</span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input auto-field" id="pdc_loan" value="<?= number_format((float)$pdcLoan, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-row indent">
    <div class="cf-label">
      Withdrawals (Pull-out)
      <span class="auto-badge">↳ Auto from Expenses</span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input auto-field" id="withdrawals" value="<?= number_format((float)$withdrawals, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-total">
    <div class="cf-total-label">Net Cash Flow</div>
    <div class="cf-total-val" id="tot_net_cash_flow"><?= $fmt($netCashFlow) ?></div>
  </div>

  <div class="cf-summary-block">
    <div class="cf-summary-row">
      <div class="cf-summary-label">Cash at Beginning of Month</div>
      <div class="cf-summary-val" id="sum_cash_beg"><?= $fmt($cashBeg) ?></div>
    </div>
    <div class="cf-summary-row">
      <div class="cf-summary-label">Cash at End of Month</div>
      <div class="cf-summary-val" id="sum_cash_end"><?= $fmt($cashEnd) ?></div>
    </div>
  </div>

</div>
</div>

  </div></div>

<script>
function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = fmt(val); }

function recalc() {
  const cashBeg     = gv('cash_beg');
  const sales       = gv('sales');
  const invPurc     = gv('inv_purchases');
  const expenses    = gv('expenses');
  const pdcLoan     = gv('pdc_loan');
  const withdrawals = gv('withdrawals');

  const netCashFlow = sales - invPurc - expenses - pdcLoan - withdrawals;
  const cashEnd     = cashBeg + netCashFlow;

  setEl('tot_net_cash_flow', netCashFlow);
  setEl('sum_cash_beg', cashBeg);
  setEl('sum_cash_end', cashEnd);
}

async function saveCashflow() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const fd = new FormData();
  fd.append('ajax_save', '1');
  ['cash_beg','sales','inv_purchases','expenses','pdc_loan','withdrawals'].forEach(id => {
    fd.append(id, gv(id));
  });

  try {
    const res  = await fetch('dois_cashflow.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      showToast('✓ Cashflow statement saved', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      status.textContent = '❌ Error';
    }
  } catch(e) {
    showToast('❌ Network error', 'error');
  }
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
</body>
</html>