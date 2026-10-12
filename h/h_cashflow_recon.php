<?php
// ============================================================
//  h_cashflow_recon.php — H Branch CashFlow Reconciliation
//  Mirrors the "MAY 2026" Excel worksheet: an Income Statement
//  panel (Sales → Gross Profit → Net Income → Add Back
//  Depreciation, plus a Purchases-vs-COGS variance) reconciled
//  against a Cashflow panel (Receivables + Bank Balances →
//  Outstanding Payables → Net Cashflow → Cash Increase),
//  ending in a single Variance figure that flags whether
//  accrual net income and actual cash movement agree for the
//  month.
//
//  Every field on this page is manual and editable, including
//  Bank Ending Balance and Beginning Balance — both are simply
//  pre-filled with a live suggestion pulled from the Bank
//  Statement the first time a month is opened (Closing Balance /
//  Opening Balance respectively), but the saved value always
//  wins after that, same as every other field below.
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

$BUSINESS_NAME = 'HERO BREAKFAST TO BAR';

// ── Create tables if not exists ───────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cashflow_recon` (
    `id`                        int(11) NOT NULL AUTO_INCREMENT,
    `recon_date`                date NOT NULL,
    `recon_year`                int(4) NOT NULL,
    `recon_month`               tinyint(2) NOT NULL,
    `store_name`                varchar(50) NOT NULL DEFAULT 'H',
    `sales`                     decimal(12,2) NOT NULL DEFAULT 0.00,
    `cost_of_sales`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `operating_expenses`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `administrative_expenses`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `extra_expenses`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `sales_discount`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `add_back_depreciation`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `total_purchases`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `cogs_income_statement`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `bank_ending_balance`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `deposit_in_transit`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `petty_cash`                decimal(12,2) NOT NULL DEFAULT 0.00,
    `beginning_balance`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`                  varchar(100) DEFAULT NULL,
    `created_at`                timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`                timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_month` (`store_name`,`recon_year`,`recon_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cashflow_recon_receivable_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'H',
    `recon_year`    int(4) NOT NULL,
    `recon_month`   tinyint(2) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cashflow_recon_payable_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'H',
    `recon_year`    int(4) NOT NULL,
    `recon_month`   tinyint(2) NOT NULL,
    `item_name`     varchar(150) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`    int(4) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

$firstDay    = date('Y-m-01', mktime(0,0,0,$fMonth,1,$fYear));
$lastDay     = date('Y-m-d', mktime(0,0,0,$fMonth+1,0,$fYear));
$displayDate = date('n/j/Y', strtotime($lastDay));

// ── Helper: Bank Statement Closing Balance for the month ───
//    (exact month-end match, else latest entry within the month) ──
function reconPullClosingBalance(PDO $pdo, string $store, string $monthLastDay, string $monthFirstDay): array {
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'h_bank_statement'");
        if (!$chk || $chk->rowCount() === 0) return ['value' => null, 'date' => null];

        $stmt = $pdo->prepare("SELECT closing_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $monthLastDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return ['value' => (float)$v, 'date' => $monthLastDay];

        $stmt = $pdo->prepare("SELECT report_date, closing_balance FROM h_bank_statement WHERE store_name=? AND report_date BETWEEN ? AND ? ORDER BY report_date DESC LIMIT 1");
        $stmt->execute([$store, $monthFirstDay, $monthLastDay]);
        $row = $stmt->fetch();
        if ($row) return ['value' => (float)$row['closing_balance'], 'date' => $row['report_date']];

        return ['value' => null, 'date' => null];
    } catch (Throwable $e) {
        return ['value' => null, 'date' => null];
    }
}

// ── Helper: Bank Statement Opening Balance for the month ───
function reconPullOpeningBalance(PDO $pdo, string $store, string $monthFirstDay): array {
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'h_bank_statement'");
        if (!$chk || $chk->rowCount() === 0) return ['value' => null, 'date' => null];

        $stmt = $pdo->prepare("SELECT opening_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $monthFirstDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return ['value' => (float)$v, 'date' => $monthFirstDay];

        $prevDay = date('Y-m-d', strtotime($monthFirstDay . ' -1 day'));
        $stmt = $pdo->prepare("SELECT closing_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $prevDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return ['value' => (float)$v, 'date' => $prevDay];

        $monthLastDay = date('Y-m-t', strtotime($monthFirstDay));
        $stmt = $pdo->prepare("SELECT report_date, opening_balance FROM h_bank_statement WHERE store_name=? AND report_date BETWEEN ? AND ? ORDER BY report_date ASC LIMIT 1");
        $stmt->execute([$store, $monthFirstDay, $monthLastDay]);
        $row = $stmt->fetch();
        if ($row) return ['value' => (float)$row['opening_balance'], 'date' => $row['report_date']];

        return ['value' => null, 'date' => null];
    } catch (Throwable $e) {
        return ['value' => null, 'date' => null];
    }
}

// ── Row helpers (Receivables / Outstanding Payables) ────────
function reconGetRows(PDO $pdo, string $table, int $year, int $month): array {
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE store_name='H' AND recon_year=? AND recon_month=? ORDER BY sort_order ASC");
    $stmt->execute([$year, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function reconSaveRows(PDO $pdo, string $table, int $year, int $month, array $rows): void {
    $pdo->prepare("DELETE FROM `$table` WHERE store_name='H' AND recon_year=? AND recon_month=?")->execute([$year, $month]);
    $ins = $pdo->prepare("INSERT INTO `$table` (store_name,recon_year,recon_month,item_name,amount,sort_order) VALUES ('H',?,?,?,?,?)");
    foreach ($rows as $i => $r) {
        $ins->execute([$year, $month, $r['name'] ?? null, (float)($r['amount'] ?? 0), $i]);
    }
}

// ── AJAX: Save Receivables rows ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_receivable_rows'])) {
    header('Content-Type: application/json');
    try {
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        reconSaveRows($pdo, 'h_cashflow_recon_receivable_rows', $fYear, $fMonth, $rows);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Save Outstanding Payables rows ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_payable_rows'])) {
    header('Content-Type: application/json');
    try {
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        reconSaveRows($pdo, 'h_cashflow_recon_payable_rows', $fYear, $fMonth, $rows);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Save main fields ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $sales       = (float)($_POST['sales'] ?? 0);
        $cogSales    = (float)($_POST['cost_of_sales'] ?? 0);
        $opEx        = (float)($_POST['operating_expenses'] ?? 0);
        $adminEx     = (float)($_POST['administrative_expenses'] ?? 0);
        $extraEx     = (float)($_POST['extra_expenses'] ?? 0);
        $discount    = (float)($_POST['sales_discount'] ?? 0);
        $addBackDep  = (float)($_POST['add_back_depreciation'] ?? 0);
        $totPurch    = (float)($_POST['total_purchases'] ?? 0);
        $cogsIS      = (float)($_POST['cogs_income_statement'] ?? 0);
        $depositTr   = (float)($_POST['deposit_in_transit'] ?? 0);
        $pettyCash   = (float)($_POST['petty_cash'] ?? 0);
        $bankEnd     = (float)($_POST['bank_ending_balance'] ?? 0);
        $beginBal    = (float)($_POST['beginning_balance'] ?? 0);

        $existing = $pdo->prepare("SELECT id FROM h_cashflow_recon WHERE store_name='H' AND recon_year=? AND recon_month=? LIMIT 1");
        $existing->execute([$fYear, $fMonth]);
        $existingId = $existing->fetchColumn();

        $params = [
            $lastDay, $sales, $cogSales, $opEx, $adminEx, $extraEx, $discount,
            $addBackDep, $totPurch, $cogsIS, $bankEnd, $depositTr, $pettyCash,
            $beginBal, $user['name'],
        ];

        if ($existingId) {
            $pdo->prepare("UPDATE h_cashflow_recon SET recon_date=?, sales=?, cost_of_sales=?, operating_expenses=?,
                administrative_expenses=?, extra_expenses=?, sales_discount=?, add_back_depreciation=?, total_purchases=?,
                cogs_income_statement=?, bank_ending_balance=?, deposit_in_transit=?, petty_cash=?, beginning_balance=?, saved_by=?
                WHERE id=?")
                ->execute(array_merge($params, [$existingId]));
        } else {
            $pdo->prepare("INSERT INTO h_cashflow_recon (recon_date,sales,cost_of_sales,operating_expenses,
                administrative_expenses,extra_expenses,sales_discount,add_back_depreciation,total_purchases,
                cogs_income_statement,bank_ending_balance,deposit_in_transit,petty_cash,beginning_balance,saved_by,
                recon_year,recon_month,store_name)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'H')")
                ->execute(array_merge($params, [$fYear, $fMonth]));
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $s = $pdo->prepare("SELECT * FROM h_cashflow_recon WHERE store_name='H' AND recon_year=? AND recon_month=? LIMIT 1");
    $s->execute([$fYear, $fMonth]);
    $row = $s->fetch() ?: null;
    $cv = fn($k) => $row ? number_format((float)($row[$k] ?? 0), 2, '.', '') : '0.00';

    $recvRows = reconGetRows($pdo, 'h_cashflow_recon_receivable_rows', $fYear, $fMonth);
    $payRows  = reconGetRows($pdo, 'h_cashflow_recon_payable_rows', $fYear, $fMonth);

    $sales      = (float)($row['sales'] ?? 0);
    $cogSales   = (float)($row['cost_of_sales'] ?? 0);
    $grossProfit = $sales - $cogSales;
    $totalExp   = (float)($row['operating_expenses'] ?? 0) + (float)($row['administrative_expenses'] ?? 0)
                + (float)($row['extra_expenses'] ?? 0) + (float)($row['sales_discount'] ?? 0);
    $netIncomeIS = $grossProfit - $totalExp;
    $netIncome   = $netIncomeIS + (float)($row['add_back_depreciation'] ?? 0);
    $purchVar    = (float)($row['cogs_income_statement'] ?? 0) - (float)($row['total_purchases'] ?? 0);
    $netIncomeCF = $netIncome + $purchVar;

    $recvTotal = array_sum(array_column($recvRows, 'amount'));
    $bankTotal = (float)($row['bank_ending_balance'] ?? 0) + (float)($row['deposit_in_transit'] ?? 0) + (float)($row['petty_cash'] ?? 0);
    $grandTotal = $recvTotal + $bankTotal;
    $payTotal   = array_sum(array_column($payRows, 'amount'));
    $netCashflow = $grandTotal - $payTotal;
    $cashIncrease = $netCashflow - (float)($row['beginning_balance'] ?? 0);
    $finalVariance = $cashIncrease - $netIncomeCF;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="h_cashflow_recon_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [strtoupper($months[$fMonth-1]).' '.$fYear]);
    fputcsv($out, ['']);
    fputcsv($out, ['INCOME STATEMENT', '', 'CASHFLOW']);
    fputcsv($out, ['SALES', $cv('sales'), 'RECEIVABLES']);
    foreach ($recvRows as $r) fputcsv($out, ['', '', $r['item_name'], number_format((float)$r['amount'],2,'.','')]);
    fputcsv($out, ['COST OF SALES', $cv('cost_of_sales'), 'TOTAL RECEIVABLES', number_format($recvTotal,2,'.','')]);
    fputcsv($out, ['GROSS PROFIT', number_format($grossProfit,2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['OPERATING EXPENSES', $cv('operating_expenses'), 'BANK BALANCES']);
    fputcsv($out, ['ADMINISTRATIVE EXPENSES', $cv('administrative_expenses'), 'ENDING BALANCE', $cv('bank_ending_balance')]);
    fputcsv($out, ['EXTRA EXPENSES', $cv('extra_expenses'), 'DEPOSIT IN TRANSIT', $cv('deposit_in_transit')]);
    fputcsv($out, ['SALES DISCOUNT', $cv('sales_discount'), 'PETTY CASH', $cv('petty_cash')]);
    fputcsv($out, ['TOTAL EXPENSES', number_format($totalExp,2,'.',''), 'TOTAL BANK BALANCES', number_format($bankTotal,2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['NET INCOME (INCOME STATEMENT)', number_format($netIncomeIS,2,'.',''), 'TOTAL', number_format($grandTotal,2,'.','')]);
    fputcsv($out, ['ADD BACK DEPRECIATION', $cv('add_back_depreciation')]);
    fputcsv($out, ['NET INCOME', number_format($netIncome,2,'.',''), 'OUTSTANDING PAYABLES']);
    foreach ($payRows as $r) fputcsv($out, ['', '', $r['item_name'], number_format((float)$r['amount'],2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['TOTAL PURCHASES', $cv('total_purchases'), 'TOTAL OUTFLOWS', number_format($payTotal,2,'.','')]);
    fputcsv($out, ['COGS (INCOME STATEMENT)', $cv('cogs_income_statement')]);
    fputcsv($out, ['VARIANCE', number_format($purchVar,2,'.',''), 'NET CASHFLOW', number_format($netCashflow,2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['', '', 'BEGINNING BALANCE', $cv('beginning_balance')]);
    fputcsv($out, ['', '', 'CASH INCREASE', number_format($cashIncrease,2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['NET INCOME (CASHFLOW)', number_format($netIncomeCF,2,'.','')]);
    fputcsv($out, ['']);
    fputcsv($out, ['VARIANCE', number_format($finalVariance,2,'.','')]);
    fclose($out);
    exit;
}

// ── Load saved data ───────────────────────────────────────
$s = $pdo->prepare("SELECT * FROM h_cashflow_recon WHERE store_name='H' AND recon_year=? AND recon_month=? LIMIT 1");
$s->execute([$fYear, $fMonth]);
$saved = $s->fetch() ?: null;

$sales      = (float)($saved['sales'] ?? 0);
$cogSales   = (float)($saved['cost_of_sales'] ?? 0);
$opEx       = (float)($saved['operating_expenses'] ?? 0);
$adminEx    = (float)($saved['administrative_expenses'] ?? 0);
$extraEx    = (float)($saved['extra_expenses'] ?? 0);
$discount   = (float)($saved['sales_discount'] ?? 0);
$addBackDep = (float)($saved['add_back_depreciation'] ?? 0);
$totPurch   = (float)($saved['total_purchases'] ?? 0);
$cogsIS     = (float)($saved['cogs_income_statement'] ?? 0);
$depositTr  = (float)($saved['deposit_in_transit'] ?? 0);
$pettyCash  = (float)($saved['petty_cash'] ?? 0);

// Bank Ending Balance / Beginning Balance — pre-filled with a live
// suggestion pulled from the Bank Statement the first time a month is
// opened, but fully editable, and the saved value always wins after
// that (same pattern as Sales / Cost of Sales / etc. above).
$endingLookup = reconPullClosingBalance($pdo, 'H', $lastDay, $firstDay);
$beginLookup  = reconPullOpeningBalance($pdo, 'H', $firstDay);
$bankEnd      = $saved ? (float)($saved['bank_ending_balance'] ?? 0) : ($endingLookup['value'] ?? 0);
$beginBal     = $saved ? (float)($saved['beginning_balance']  ?? 0) : ($beginLookup['value']  ?? 0);
$bankEndDate  = $endingLookup['date'];
$beginBalDate = $beginLookup['date'];

$recvRows = reconGetRows($pdo, 'h_cashflow_recon_receivable_rows', $fYear, $fMonth);
$payRows  = reconGetRows($pdo, 'h_cashflow_recon_payable_rows', $fYear, $fMonth);
if (empty($recvRows)) $recvRows = [['item_name' => '', 'amount' => 0]];
if (empty($payRows))  $payRows  = [['item_name' => '', 'amount' => 0]];
$recvTotal = array_sum(array_column($recvRows, 'amount'));
$payTotal  = array_sum(array_column($payRows, 'amount'));

// ── Derived values (initial server-side render) ────────────
$grossProfit   = $sales - $cogSales;
$totalExp      = $opEx + $adminEx + $extraEx + $discount;
$netIncomeIS   = $grossProfit - $totalExp;
$netIncome     = $netIncomeIS + $addBackDep;
$purchVar      = $cogsIS - $totPurch;
$netIncomeCF   = $netIncome + $purchVar;

$bankTotal     = $bankEnd + $depositTr + $pettyCash;
$grandTotal    = $recvTotal + $bankTotal;
$netCashflow   = $grandTotal - $payTotal;
$cashIncrease  = $netCashflow - $beginBal;
$finalVariance = $cashIncrease - $netIncomeCF;

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'CashFlow Reconciliation';
$activePage = 'h_cashflow_recon';
include '../layout.php';
?>

<style>
.cfr-wrap { max-width: 1040px; margin: 0 auto; }

.cfr-header-card {
  background: linear-gradient(135deg, #1e3060 0%, #0f2045 100%);
  border-radius: var(--radius); padding: 20px 26px 16px;
  margin-bottom: 18px; display: flex; align-items: flex-start;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.cfr-header-card .eyebrow {
  font-family: var(--font-m); font-size: .58rem; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.cfr-header-card .title { font-size: 1.2rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.cfr-header-card .subtitle { font-family: var(--font-m); font-size: .67rem; color: rgba(255,255,255,.5); margin-top: 4px; }

.cfr-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }

.cfr-month-band {
  background: #1e3060; color: #fff; text-align: center; padding: 12px;
  font-family: var(--font-m); font-weight: 800; letter-spacing: .06em;
  border-radius: var(--radius) var(--radius) 0 0; font-size: 1rem;
}

.cfr-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 0;
  background: var(--surface); border: 1px solid var(--border); border-top: none;
  border-radius: 0 0 var(--radius) var(--radius); overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
@media (max-width: 860px) { .cfr-grid { grid-template-columns: 1fr; } }

.cfr-col { display: flex; flex-direction: column; }
.cfr-col.left { border-right: 1px solid var(--border); }
@media (max-width: 860px) { .cfr-col.left { border-right: none; border-bottom: 1px solid var(--border); } }

.cfr-panel-title {
  background: #fbdede; color: #7a1f1f; text-align: center; padding: 8px;
  font-family: var(--font-m); font-weight: 800; font-size: .78rem;
  letter-spacing: .06em; text-transform: uppercase;
}

.cfr-row {
  display: flex; align-items: center; padding: 8px 20px;
  border-bottom: 1px solid #f0f2f5; min-height: 42px; gap: 10px;
}
.cfr-row:hover { background: #fafbfc; }
.cfr-row.indent { padding-left: 36px; }
.cfr-label { flex: 1; font-size: .78rem; color: var(--text); font-weight: 500; }
.cfr-label.bold { font-weight: 700; }

.cfr-input {
  width: 130px; padding: 6px 10px; text-align: right;
  font-family: var(--font-m); font-size: .78rem; color: var(--text);
  background: #fff; border: 1px solid var(--border); border-radius: 6px;
  outline: none; transition: border-color .15s, box-shadow .15s;
}
.cfr-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }
.cfr-input.manual { background: #fffbeb; border-color: rgba(217,119,6,.25); }
.cfr-input.ref { background: #ecfeff; color: #0e7490; font-weight: 700; cursor: default; border-color: rgba(14,116,144,.2); }
.cfr-input.calc { background: #f0f2f5; color: var(--text); font-weight: 800; cursor: default; border-color: transparent; }

.cfr-badge {
  font-family: var(--font-m); font-size: .55rem; color: #0e7490;
  background: rgba(14,116,144,.08); padding: 2px 6px; border-radius: 8px;
  border: 1px solid rgba(14,116,144,.15); white-space: nowrap;
}

.cfr-total {
  display: flex; align-items: center; padding: 9px 20px;
  border-top: 2px solid var(--border2); border-bottom: 1px solid var(--border);
  background: #f0f2f5;
}
.cfr-total-label { flex: 1; font-size: .78rem; font-weight: 700; font-style: italic; color: var(--text); }
.cfr-total-val { font-family: var(--font-m); font-size: .88rem; font-weight: 800; min-width: 120px; text-align: right; }

.cfr-section-header {
  background: #eef3fb; color: #1e3060; padding: 7px 20px;
  font-family: var(--font-m); font-size: .68rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em; border-bottom: 1px solid #dce5f2;
}

.cfr-wd-wrap { padding: 6px 20px 10px 36px; }
.cfr-wd-table { width: 100%; border-collapse: collapse; }
.cfr-wd-table th {
  background: #5a5a5a; color: #fff; padding: 5px 7px;
  font-family: var(--font-m); font-size: .56rem; text-transform: uppercase;
  letter-spacing: .05em; text-align: left; border: 1px solid #444;
}
.cfr-wd-table th:last-child, .cfr-wd-table td:last-child { text-align: center; width: 30px; }
.cfr-wd-table td { padding: 3px 5px; border: 1px solid #e5e7eb; font-size: .74rem; vertical-align: middle; }
.cfr-wd-inp { width: 100%; border: 1px solid #e0e0e0; background: #fafafa; border-radius: 4px; font-family: var(--font-m); font-size: .72rem; outline: none; padding: 4px 6px; }
.cfr-wd-inp.num { text-align: right; }
.cfr-wd-inp:focus { background: #fffbeb; border-color: #f5c542; box-shadow: 0 0 0 2px rgba(245,197,66,.15); }
.cfr-wd-table tfoot td { background: #e8a4a4; color: #7a1f1f; font-family: var(--font-m); font-weight: 800; font-size: .74rem; padding: 6px 7px; border: 1px solid #d38a8a; }
.cfr-wd-table tfoot td.total-label { text-align: right; }
.btn-add-row-sm { margin: 6px 0 0; padding: 3px 10px; background: #1a4d1a; color: #fff; border: none; border-radius: 5px; font-size: .66rem; font-weight: 700; cursor: pointer; }
.btn-add-row-sm:hover { background: #155231; }
.btn-del-row-sm { background: #fee2e2; border: none; color: #991b1b; border-radius: 4px; padding: 2px 5px; font-size: .62rem; cursor: pointer; }

.cfr-summary-block { background: #eef3fb; }
.cfr-summary-row { display: flex; align-items: center; padding: 8px 20px; border-bottom: 1px solid #dce5f2; }
.cfr-summary-row:last-child { border-bottom: none; }
.cfr-summary-row.highlight { background: #cfe8f7; }
.cfr-summary-label { flex: 1; font-size: .78rem; font-weight: 700; color: var(--text); }
.cfr-summary-val { font-family: var(--font-m); font-size: .86rem; font-weight: 800; min-width: 120px; text-align: right; color: var(--text); }

.cfr-variance-band {
  grid-column: 1 / -1; padding: 14px 24px; display: flex; align-items: center;
  justify-content: space-between; gap: 14px; flex-wrap: wrap;
}
.cfr-variance-band .vlabel { font-family: var(--font-m); font-weight: 800; font-size: .8rem; letter-spacing: .05em; text-transform: uppercase; }
.cfr-variance-band .vval { font-family: var(--font-m); font-weight: 800; font-size: 1.1rem; }
.cfr-variance-band.ok { background: #f0fdf4; color: #166534; }
.cfr-variance-band.warn { background: #fef2f2; color: #991b1b; }

.cfr-save-status { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
</style>

<!-- Page Header -->
<div class="cfr-header-card">
  <div>
    <div class="eyebrow">H Branch · Finance</div>
    <div class="title">CashFlow Reconciliation</div>
    <div class="subtitle">Reconciles accrual Net Income against actual cash movement for the month</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 H
  </span>
</div>

<!-- Controls -->
<div class="cfr-controls">
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
    <button type="button" class="btn btn-primary" onclick="saveRecon()">💾 Save</button>
    <a href="h_cashflow_recon.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="cfr-save-status"></span>
  </form>
</div>

<div class="cfr-wrap">
<div class="cfr-month-band"><?= strtoupper($months[$fMonth-1]) ?> <?= $fYear ?></div>
<div class="cfr-grid">

  <!-- ═══════════════ LEFT: INCOME STATEMENT ═══════════════ -->
  <div class="cfr-col left">
    <div class="cfr-panel-title">Income Statement</div>

    <div class="cfr-row">
      <div class="cfr-label bold">Sales</div>
      <input type="number" step="0.01" class="cfr-input manual" id="sales" value="<?= number_format($sales,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Cost of Sales</div>
      <input type="number" step="0.01" class="cfr-input manual" id="cost_of_sales" value="<?= number_format($cogSales,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-total">
      <div class="cfr-total-label">Gross Profit</div>
      <div class="cfr-total-val" id="tot_gross_profit"><?= $fmt($grossProfit) ?></div>
    </div>

    <div class="cfr-row" style="margin-top:6px">
      <div class="cfr-label">Operating Expenses</div>
      <input type="number" step="0.01" class="cfr-input manual" id="operating_expenses" value="<?= number_format($opEx,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Administrative Expenses</div>
      <input type="number" step="0.01" class="cfr-input manual" id="administrative_expenses" value="<?= number_format($adminEx,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Extra Expenses</div>
      <input type="number" step="0.01" class="cfr-input manual" id="extra_expenses" value="<?= number_format($extraEx,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Sales Discount</div>
      <input type="number" step="0.01" class="cfr-input manual" id="sales_discount" value="<?= number_format($discount,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-total">
      <div class="cfr-total-label">Total Expenses</div>
      <div class="cfr-total-val" id="tot_total_expenses"><?= $fmt($totalExp) ?></div>
    </div>

    <div class="cfr-total" style="background:#fff">
      <div class="cfr-total-label">Net Income (Income Statement)</div>
      <div class="cfr-total-val" id="tot_net_income_is"><?= $fmt($netIncomeIS) ?></div>
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Add Back Depreciation</div>
      <input type="number" step="0.01" class="cfr-input manual" id="add_back_depreciation" value="<?= number_format($addBackDep,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-total" style="background:#fdf1c7">
      <div class="cfr-total-label">Net Income</div>
      <div class="cfr-total-val" id="tot_net_income"><?= $fmt($netIncome) ?></div>
    </div>

    <div class="cfr-row" style="margin-top:6px">
      <div class="cfr-label">Total Purchases</div>
      <input type="number" step="0.01" class="cfr-input manual" id="total_purchases" value="<?= number_format($totPurch,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">COGS (Income Statement)</div>
      <input type="number" step="0.01" class="cfr-input manual" id="cogs_income_statement" value="<?= number_format($cogsIS,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-total">
      <div class="cfr-total-label">Variance (Purchases vs COGS)</div>
      <div class="cfr-total-val" id="tot_purch_variance"><?= $fmt($purchVar) ?></div>
    </div>

    <div class="cfr-total" style="background:#fbdede;margin-top:auto">
      <div class="cfr-total-label">Net Income (CashFlow)</div>
      <div class="cfr-total-val" id="tot_net_income_cf"><?= $fmt($netIncomeCF) ?></div>
    </div>
  </div>

  <!-- ═══════════════ RIGHT: CASHFLOW ═══════════════ -->
  <div class="cfr-col right">
    <div class="cfr-panel-title">CashFlow</div>

    <div class="cfr-section-header">Receivables</div>
    <div class="cfr-wd-wrap">
      <table class="cfr-wd-table" id="recv-table">
        <thead><tr><th>Item</th><th style="text-align:right">Amount</th><th></th></tr></thead>
        <tbody id="recv-body">
          <?php foreach ($recvRows as $r): ?>
          <tr class="wd-row-tr">
            <td><input class="cfr-wd-inp txt" type="text" placeholder="e.g. Corporate/Management Payables" value="<?= htmlspecialchars($r['item_name'] ?? '') ?>" oninput="recalc()"></td>
            <td><input class="cfr-wd-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$r['amount'] ?: '' ?>" oninput="recalc()"></td>
            <td><button class="btn-del-row-sm" onclick="delRow(this,'recv')">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td class="total-label">TOTAL</td><td style="text-align:right" id="recv-total"><?= number_format($recvTotal,2) ?></td><td></td></tr></tfoot>
      </table>
      <button class="btn-add-row-sm" onclick="addRow('recv')">+ Add Row</button>
    </div>

    <div class="cfr-section-header">Bank Balances</div>
    <div class="cfr-row">
      <div class="cfr-label">
        Ending Balance
        <?php if (!$saved && $bankEndDate !== null): ?><span class="cfr-badge">↳ Suggested from Bank Statement (<?= date('M j, Y', strtotime($bankEndDate)) ?>)</span><?php endif; ?>
      </div>
      <input type="number" step="0.01" class="cfr-input manual" id="bank_ending_balance" value="<?= number_format($bankEnd,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Deposit in Transit</div>
      <input type="number" step="0.01" class="cfr-input manual" id="deposit_in_transit" value="<?= number_format($depositTr,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-row">
      <div class="cfr-label">Petty Cash</div>
      <input type="number" step="0.01" class="cfr-input manual" id="petty_cash" value="<?= number_format($pettyCash,2,'.','') ?>" oninput="recalc()" placeholder="0.00">
    </div>
    <div class="cfr-total">
      <div class="cfr-total-label">Total Bank Balances</div>
      <div class="cfr-total-val" id="tot_bank_total"><?= $fmt($bankTotal) ?></div>
    </div>

    <div class="cfr-total" style="background:#fbdede">
      <div class="cfr-total-label">Total</div>
      <div class="cfr-total-val" id="tot_grand_total"><?= $fmt($grandTotal) ?></div>
    </div>

    <div class="cfr-section-header">Outstanding Payables</div>
    <div class="cfr-wd-wrap">
      <table class="cfr-wd-table" id="pay-table">
        <thead><tr><th>Item</th><th style="text-align:right">Amount</th><th></th></tr></thead>
        <tbody id="pay-body">
          <?php foreach ($payRows as $r): ?>
          <tr class="wd-row-tr">
            <td><input class="cfr-wd-inp txt" type="text" placeholder="e.g. Supplier Payable" value="<?= htmlspecialchars($r['item_name'] ?? '') ?>" oninput="recalc()"></td>
            <td><input class="cfr-wd-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$r['amount'] ?: '' ?>" oninput="recalc()"></td>
            <td><button class="btn-del-row-sm" onclick="delRow(this,'pay')">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td class="total-label">TOTAL OUTFLOWS</td><td style="text-align:right" id="pay-total"><?= number_format($payTotal,2) ?></td><td></td></tr></tfoot>
      </table>
      <button class="btn-add-row-sm" onclick="addRow('pay')">+ Add Row</button>
    </div>

    <div class="cfr-total" style="background:#fff">
      <div class="cfr-total-label">Net CashFlow</div>
      <div class="cfr-total-val" id="tot_net_cashflow"><?= $fmt($netCashflow) ?></div>
    </div>

    <div class="cfr-summary-block">
      <div class="cfr-summary-row">
        <div class="cfr-summary-label">
          Beginning Balance
          <?php if (!$saved && $beginBalDate !== null): ?><span class="cfr-badge">↳ Suggested from Bank Statement (<?= date('M j, Y', strtotime($beginBalDate)) ?>)</span><?php endif; ?>
        </div>
        <div class="cfr-summary-val"><input type="number" step="0.01" class="cfr-input manual" id="beginning_balance" value="<?= number_format($beginBal,2,'.','') ?>" oninput="recalc()" placeholder="0.00"></div>
      </div>
      <div class="cfr-summary-row highlight">
        <div class="cfr-summary-label">Cash Increase</div>
        <div class="cfr-summary-val" id="sum_cash_increase"><?= $fmt($cashIncrease) ?></div>
      </div>
    </div>

    <div class="cfr-total" style="background:#fdf1c7;margin-top:auto">
      <div class="cfr-total-label">Net Income (CashFlow)</div>
      <div class="cfr-total-val" id="tot_net_income_cf_right"><?= $fmt($netIncomeCF) ?></div>
    </div>
  </div>

  <!-- ═══════════════ VARIANCE BAND ═══════════════ -->
  <div class="cfr-variance-band <?= abs($finalVariance) < 1 ? 'ok' : 'warn' ?>" id="variance-band">
    <div class="vlabel"><?= abs($finalVariance) < 1 ? '✓ Reconciled' : '⚠ Variance — Needs Review' ?></div>
    <div class="vval" id="sum_final_variance"><?= $fmt($finalVariance) ?></div>
  </div>

</div>
</div>

  </div></div>

<script>
function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = fmt(val); }

// ── Row add/remove for Receivables & Outstanding Payables ──
function addRow(kind) {
  const body = document.getElementById(kind+'-body');
  const tr = document.createElement('tr');
  tr.className = 'wd-row-tr';
  tr.innerHTML = `
    <td><input class="cfr-wd-inp txt" type="text" placeholder="e.g. Item" oninput="recalc()"></td>
    <td><input class="cfr-wd-inp num" type="number" step="0.01" placeholder="0.00" oninput="recalc()"></td>
    <td><button class="btn-del-row-sm" onclick="delRow(this,'${kind}')">✕</button></td>`;
  body.appendChild(tr);
}
function delRow(btn, kind) { btn.closest('tr').remove(); recalc(); }
function rowTotal(kind) {
  let total = 0;
  document.querySelectorAll('#'+kind+'-body tr.wd-row-tr').forEach(tr => {
    total += parseFloat(tr.querySelector('.cfr-wd-inp.num').value) || 0;
  });
  return total;
}

function recalc() {
  // Income Statement side
  const sales     = gv('sales');
  const cogSales  = gv('cost_of_sales');
  const opEx      = gv('operating_expenses');
  const adminEx   = gv('administrative_expenses');
  const extraEx   = gv('extra_expenses');
  const discount  = gv('sales_discount');
  const addBackDep = gv('add_back_depreciation');
  const totPurch  = gv('total_purchases');
  const cogsIS    = gv('cogs_income_statement');

  const grossProfit = sales - cogSales;
  const totalExp     = opEx + adminEx + extraEx + discount;
  const netIncomeIS  = grossProfit - totalExp;
  const netIncome    = netIncomeIS + addBackDep;
  const purchVar     = cogsIS - totPurch;
  const netIncomeCF  = netIncome + purchVar;

  setEl('tot_gross_profit', grossProfit);
  setEl('tot_total_expenses', totalExp);
  setEl('tot_net_income_is', netIncomeIS);
  setEl('tot_net_income', netIncome);
  setEl('tot_purch_variance', purchVar);
  setEl('tot_net_income_cf', netIncomeCF);
  setEl('tot_net_income_cf_right', netIncomeCF);

  // CashFlow side
  const recvTotal = rowTotal('recv');
  const bankEnd   = gv('bank_ending_balance');
  const depositTr = gv('deposit_in_transit');
  const pettyCash = gv('petty_cash');
  const bankTotal = bankEnd + depositTr + pettyCash;
  const grandTotal = recvTotal + bankTotal;
  const payTotal  = rowTotal('pay');
  const netCashflow = grandTotal - payTotal;
  const beginBal  = gv('beginning_balance');
  const cashIncrease = netCashflow - beginBal;
  const finalVariance = cashIncrease - netIncomeCF;

  document.getElementById('recv-total').textContent = fmt(recvTotal);
  document.getElementById('pay-total').textContent = fmt(payTotal);
  setEl('tot_bank_total', bankTotal);
  setEl('tot_grand_total', grandTotal);
  setEl('tot_net_cashflow', netCashflow);
  setEl('sum_cash_increase', cashIncrease);
  setEl('sum_final_variance', finalVariance);

  const band = document.getElementById('variance-band');
  const isOk = Math.abs(finalVariance) < 1;
  band.classList.toggle('ok', isOk);
  band.classList.toggle('warn', !isOk);
  band.querySelector('.vlabel').textContent = isOk ? '✓ Reconciled' : '⚠ Variance — Needs Review';
}

async function saveRecon() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const collectRows = (kind) => {
    const rows = [];
    document.querySelectorAll('#'+kind+'-body tr.wd-row-tr').forEach(tr => {
      const inps = tr.querySelectorAll('input');
      rows.push({ name: inps[0]?.value || '', amount: parseFloat(inps[1]?.value) || 0 });
    });
    return rows;
  };

  const fdRecv = new FormData();
  fdRecv.append('ajax_save_receivable_rows', '1');
  fdRecv.append('rows', JSON.stringify(collectRows('recv')));
  await fetch('h_cashflow_recon.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fdRecv});

  const fdPay = new FormData();
  fdPay.append('ajax_save_payable_rows', '1');
  fdPay.append('rows', JSON.stringify(collectRows('pay')));
  await fetch('h_cashflow_recon.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fdPay});

  const fd = new FormData();
  fd.append('ajax_save', '1');
  ['sales','cost_of_sales','operating_expenses','administrative_expenses','extra_expenses','sales_discount',
   'add_back_depreciation','total_purchases','cogs_income_statement','bank_ending_balance','deposit_in_transit','petty_cash','beginning_balance'].forEach(id => {
    fd.append(id, gv(id));
  });

  try {
    const res  = await fetch('h_cashflow_recon.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      showToast('✓ Reconciliation saved', 'success');
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