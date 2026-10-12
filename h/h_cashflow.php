<?php
// ============================================================
//  h_cashflow.php — H Branch Cashflow Statement
//  Mirrors the "CASHFLOW STATEMENT" Excel sheet:
//  Operations section (Customers Collections − Inventory
//  Purchases − General Op/Admin Expenses = PROFIT), then a
//  Financing section (Add Back Depreciation − itemized
//  Management Withdrawals), rolled up into Net Increase in
//  Cash and Cash at End of Month, cross-checked live against
//  the Bank Statement page's Closing Balance for the same
//  month-end date.
//
//  Customers Collections / Inventory Purchases / General
//  Op&Admin Expenses / Add Back Depreciation are manual,
//  editable fields (pre-filled with a live suggestion pulled
//  from Summary Report / Expenses the first time a month is
//  opened, but the saved value always wins after that).
//  Management Withdrawals is a free-form add/remove row list
//  ("Cash paid for") with its own total.
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

// Business name shown under the statement title (row 2 of the Excel).
$BUSINESS_NAME = 'HERO BREAKFAST TO BAR';

// ── Create table if not exists ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cashflow` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `cf_date`         date NOT NULL,
    `cf_year`         int(4) NOT NULL,
    `cf_month`        tinyint(2) NOT NULL,
    `store_name`      varchar(50) NOT NULL DEFAULT 'H',
    `cash_beg`        decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
    `sales`           decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Customers Collections (manual)',
    `inv_purchases`   decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Inventory Purchases (manual)',
    `expenses`        decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'General operating and administrative expenses (manual)',
    `pdc_loan`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `withdrawals`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Management Withdrawals total (sum of row list)',
    `net_cash_flow`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash_end`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`        varchar(100) DEFAULT NULL,
    `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── New columns needed for the Excel-matching layout ───────
try { $pdo->exec("ALTER TABLE `h_cashflow` ADD COLUMN `pull_out_roi` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `h_cashflow` ADD COLUMN `depreciation` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `h_cashflow` ADD COLUMN `net_cf_operations` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `h_cashflow` ADD COLUMN `net_cf_financing` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}
try { $pdo->exec("ALTER TABLE `h_cashflow` ADD COLUMN `net_increase_cash` decimal(12,2) NOT NULL DEFAULT 0.00"); } catch (Throwable $ignored) {}

// ── Management Withdrawals — itemized "Cash paid for" rows ─
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_cashflow_withdrawal_rows` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `store_name`    varchar(50) NOT NULL DEFAULT 'H',
    `cf_year`       int(4) NOT NULL,
    `cf_month`      tinyint(2) NOT NULL,
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

// ── Helper: live-suggest SUM(<column>) from h_expenses for a month
//    (used only to pre-fill Add Back Depreciation on a brand-new,
//    never-saved month — the saved value always wins after that) ──
function pullExpenseColumnTotal(PDO $pdo, int $year, int $month, string $column): array {
    $debug = ['step' => 'start', 'table_exists' => null, 'column_exists' => null, 'raw_value' => null, 'error' => null];
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'h_expenses'");
        $debug['table_exists'] = $chk && $chk->rowCount() > 0;
        if (!$debug['table_exists']) {
            $debug['error'] = "Table 'h_expenses' does not exist on this database.";
            return [0.0, $debug];
        }
        $colChk = $pdo->query("SHOW COLUMNS FROM h_expenses LIKE " . $pdo->quote($column));
        $debug['column_exists'] = $colChk && $colChk->rowCount() > 0;
        if (!$debug['column_exists']) {
            $debug['error'] = "Column '$column' does not exist in h_expenses.";
            return [0.0, $debug];
        }
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(`$column`),0) AS total FROM h_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        $row = $stmt->fetch();
        $debug['raw_value'] = $row['total'] ?? null;
        $debug['step'] = 'done';
        $value = isset($row['total']) && is_numeric($row['total']) ? (float)$row['total'] : 0.0;
        return [$value, $debug];
    } catch (Throwable $e) {
        $debug['error'] = $e->getMessage();
        return [0.0, $debug];
    }
}

// ── Helper: pull the Bank Statement's Closing Balance for the
//    month-end (used to cross-check "Closing Balance" row).
//    Tries, in order: (1) an entry dated exactly the last day of the
//    month, (2) the latest Bank Statement entry found anywhere inside
//    the month (covers months where the statement was saved under a
//    different date, e.g. the 30th or an interim date), so July's
//    Closing Balance shows up even if no row is dated exactly 7/31. ──
function pullBankStatementClosingBalance(PDO $pdo, string $store, string $monthLastDay, ?string $monthFirstDay = null): array {
    // Returns ['value' => float|null, 'date' => 'Y-m-d'|null]
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'h_bank_statement'");
        if (!$chk || $chk->rowCount() === 0) return ['value' => null, 'date' => null];

        $stmt = $pdo->prepare("SELECT closing_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $monthLastDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return ['value' => (float)$v, 'date' => $monthLastDay];

        $firstDay = $monthFirstDay ?? date('Y-m-01', strtotime($monthLastDay));
        $stmt = $pdo->prepare("SELECT report_date, closing_balance FROM h_bank_statement WHERE store_name=? AND report_date BETWEEN ? AND ? ORDER BY report_date DESC LIMIT 1");
        $stmt->execute([$store, $firstDay, $monthLastDay]);
        $row = $stmt->fetch();
        if ($row) return ['value' => (float)$row['closing_balance'], 'date' => $row['report_date']];

        return ['value' => null, 'date' => null];
    } catch (Throwable $e) {
        return ['value' => null, 'date' => null];
    }
}

// ── Helper: pull the Bank Statement's Opening Balance for the start
//    of a given month — used to auto-fill "Cash at Beginning of
//    Month". Tries, in order: (1) a Bank Statement entry dated the
//    1st of the month, (2) the prior day's Closing Balance (i.e. the
//    last day of the previous month), (3) the earliest Bank
//    Statement entry found anywhere inside the month. ──────────────
function pullBankStatementOpeningBalance(PDO $pdo, string $store, string $monthFirstDay): ?float {
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'h_bank_statement'");
        if (!$chk || $chk->rowCount() === 0) return null;

        $stmt = $pdo->prepare("SELECT opening_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $monthFirstDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return (float)$v;

        $prevDay = date('Y-m-d', strtotime($monthFirstDay . ' -1 day'));
        $stmt = $pdo->prepare("SELECT closing_balance FROM h_bank_statement WHERE store_name=? AND report_date=? LIMIT 1");
        $stmt->execute([$store, $prevDay]);
        $v = $stmt->fetchColumn();
        if ($v !== false) return (float)$v;

        $monthLastDay = date('Y-m-t', strtotime($monthFirstDay));
        $stmt = $pdo->prepare("SELECT opening_balance FROM h_bank_statement WHERE store_name=? AND report_date BETWEEN ? AND ? ORDER BY report_date ASC LIMIT 1");
        $stmt->execute([$store, $monthFirstDay, $monthLastDay]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (float)$v : null;
    } catch (Throwable $e) {
        return null;
    }
}

// ── Helper: live-suggest Customers Collections from Summary Report ──
function suggestCollections(PDO $pdo, int $year, int $month): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='H' AND YEAR(report_date)=? AND MONTH(report_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── Helper: live-suggest Inventory Purchases (SUM(purchases) + selected VAT) ──
function suggestInventoryPurchases(PDO $pdo, int $year, int $month): float {
    $vat = 0.0;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `h_cf_vat_selection` (
            `id`            int(11) NOT NULL AUTO_INCREMENT,
            `store_name`    varchar(50) NOT NULL DEFAULT 'H',
            `sel_year`      int(4) NOT NULL,
            `sel_month`     tinyint(2) NOT NULL,
            `vat_total`     decimal(12,2) NOT NULL DEFAULT 0.00,
            `row_count`     int(11) NOT NULL DEFAULT 0,
            `saved_by`      varchar(100) DEFAULT NULL,
            `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $stmt = $pdo->prepare("SELECT vat_total FROM h_cf_vat_selection WHERE store_name='H' AND sel_year=? AND sel_month=?");
        $stmt->execute([$year, $month]);
        $v = $stmt->fetchColumn();
        $vat = $v !== false ? (float)$v : 0.0;
    } catch (Throwable $e) { $vat = 0.0; }
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(purchases),0) FROM h_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn() + $vat;
    } catch (Throwable $e) {
        return $vat;
    }
}

// ── Helper: live-suggest General Op/Admin Expenses ──────────
function suggestAdminExpenses(PDO $pdo, int $year, int $month): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(
            salaries + rent + medicine + lpg + repairs_maintenance + fuel_trans +
            communication + transportation + light + drinking_water + water +
            sss_phic_hdmf + taxes_licences + office_supplies + kitchen_supplies +
            bio_pest_control + representation + miscellaneous + sir_budoy_nikki +
            staff_meal + pest_control_bio_aug + commission_fees + exhaust_cleaning + bank_fees + admin_salary_shares + marketing
        ),0) FROM h_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── Withdrawal rows (Management Withdrawals / "Cash paid for") ─
function getWithdrawalRows(PDO $pdo, int $year, int $month): array {
    $stmt = $pdo->prepare("SELECT * FROM h_cashflow_withdrawal_rows WHERE store_name='H' AND cf_year=? AND cf_month=? ORDER BY sort_order ASC");
    $stmt->execute([$year, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── AJAX: Save Management Withdrawal rows ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_withdrawal_rows'])) {
    header('Content-Type: application/json');
    try {
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        $pdo->prepare("DELETE FROM h_cashflow_withdrawal_rows WHERE store_name='H' AND cf_year=? AND cf_month=?")->execute([$fYear, $fMonth]);
        $ins = $pdo->prepare("INSERT INTO h_cashflow_withdrawal_rows (store_name,cf_year,cf_month,item_name,amount,sort_order) VALUES ('H',?,?,?,?,?)");
        foreach ($rows as $i => $r) {
            $ins->execute([$fYear, $fMonth, $r['name'] ?? null, (float)($r['amount'] ?? 0), $i]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
    exit;
}

// ── AJAX: Save main fields ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $pullOutRoi   = (float)($_POST['pull_out_roi'] ?? 0);
        $sales        = (float)($_POST['sales']        ?? 0);
        $invPurc      = abs((float)($_POST['inv_purchases'] ?? 0));
        $expenses     = abs((float)($_POST['expenses']     ?? 0));
        $depreciation = (float)($_POST['depreciation'] ?? 0);

        // Cash at Beginning of Month — always re-pulled live from the
        // Bank Statement's Opening Balance for this month; falls back
        // to whatever was last saved if no Bank Statement entry exists.
        $cashBegRef = pullBankStatementOpeningBalance($pdo, 'H', $firstDay);
        $cashBeg    = $cashBegRef !== null ? $cashBegRef : (float)($_POST['cash_beg'] ?? 0);

        $withdrawalRows  = getWithdrawalRows($pdo, $fYear, $fMonth);
        $withdrawalTotal = array_sum(array_column($withdrawalRows, 'amount'));

        $netCfOperations = $sales - $invPurc - $expenses;
        $netCfFinancing  = $depreciation + $withdrawalTotal;
        $netIncreaseCash = $netCfOperations + $netCfFinancing;
        $cashEnd         = $cashBeg + $netIncreaseCash;

        $existing = $pdo->prepare("SELECT id FROM h_cashflow WHERE store_name='H' AND cf_year=? AND cf_month=? LIMIT 1");
        $existing->execute([$fYear,$fMonth]);
        $existingId = $existing->fetchColumn();

        $params = [
            $lastDay, $cashBeg, $pullOutRoi, $sales, $invPurc, $expenses,
            $depreciation, $withdrawalTotal, $netCfOperations, $netCfFinancing,
            $netIncreaseCash, $cashEnd, $user['name'],
        ];

        if ($existingId) {
            $pdo->prepare("UPDATE h_cashflow SET cf_date=?, cash_beg=?, pull_out_roi=?, sales=?, inv_purchases=?, expenses=?,
                depreciation=?, withdrawals=?, net_cf_operations=?, net_cf_financing=?, net_increase_cash=?, cash_end=?, saved_by=?
                WHERE id=?")
                ->execute(array_merge($params, [$existingId]));
        } else {
            $pdo->prepare("INSERT INTO h_cashflow (cf_date,cash_beg,pull_out_roi,sales,inv_purchases,expenses,
                depreciation,withdrawals,net_cf_operations,net_cf_financing,net_increase_cash,cash_end,saved_by,cf_year,cf_month,store_name)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'H')")
                ->execute(array_merge($params, [$fYear, $fMonth]));
        }
        echo json_encode(['ok'=>true,'net_increase_cash'=>$netIncreaseCash,'cash_end'=>$cashEnd]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $s = $pdo->prepare("SELECT * FROM h_cashflow WHERE store_name='H' AND cf_year=? AND cf_month=? LIMIT 1");
    $s->execute([$fYear,$fMonth]);
    $row = $s->fetch() ?: null;
    $cv  = fn($k) => $row ? number_format((float)($row[$k] ?? 0),2,'.','') : '0.00';
    $cvAbs = fn($k) => $row ? number_format(abs((float)($row[$k] ?? 0)),2,'.','') : '0.00';
    $closingRef = pullBankStatementClosingBalance($pdo, 'H', $lastDay, $firstDay)['value'];
    $withdrawalRows = getWithdrawalRows($pdo, $fYear, $fMonth);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="h_cashflow_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['CASHFLOW STATEMENT']);
    fputcsv($out,[$BUSINESS_NAME]);
    fputcsv($out,['']);
    fputcsv($out,['','For the month Ending',$displayDate]);
    fputcsv($out,['','Cash at Beginning of Month',$cv('cash_beg')]);
    fputcsv($out,['PULL OUT ROI','',$cv('pull_out_roi')]);
    fputcsv($out,['']);
    fputcsv($out,['Operations']);
    fputcsv($out,['','Customers Collections',$cv('sales')]);
    fputcsv($out,['Cash paid for']);
    fputcsv($out,['','Inventory Purchases (Actual Expenses)','('.$cvAbs('inv_purchases').')']);
    fputcsv($out,['','General operating and administrative expenses','('.$cvAbs('expenses').')']);
    fputcsv($out,['Net Cash Flow from Operations(PROFIT)','',$cv('net_cf_operations')]);
    fputcsv($out,['']);
    fputcsv($out,['','Add Back Depreciation',$cv('depreciation')]);
    fputcsv($out,['Cash paid for (Management Withdrawals)']);
    foreach ($withdrawalRows as $wr) {
        fputcsv($out, ['', $wr['item_name'], '('.number_format((float)$wr['amount'],2,'.','').')']);
    }
    fputcsv($out,['','TOTAL Management Withdrawals','('.$cv('withdrawals').')']);
    fputcsv($out,['Net Cash Flow from Financing Activities','',$cv('net_cf_financing')]);
    fputcsv($out,['']);
    fputcsv($out,['Net Increase in Cash','',$cv('net_increase_cash')]);
    fputcsv($out,['']);
    fputcsv($out,['Cash at End of Month','',$cv('cash_end')]);
    fputcsv($out,['Closing Balance (Bank Statement)','', $closingRef !== null ? number_format($closingRef,2,'.','') : 'n/a']);
    fclose($out);
    exit;
}

// ── Load saved data ───────────────────────────────────────
$s = $pdo->prepare("SELECT * FROM h_cashflow WHERE store_name='H' AND cf_year=? AND cf_month=? LIMIT 1");
$s->execute([$fYear,$fMonth]);
$saved = $s->fetch() ?: null;

// Cash at Beginning of Month — always re-pulled live from the Bank
// Statement's Opening Balance for this month (falls back to the last
// saved value if no Bank Statement entry exists for this period yet).
$cashBegRef = pullBankStatementOpeningBalance($pdo, 'H', $firstDay);
$cashBeg    = $cashBegRef !== null ? $cashBegRef : (float)($saved['cash_beg'] ?? 0);
$pullOutRoi = (float)($saved['pull_out_roi'] ?? 0);

// Manual fields: use the saved value if this month was saved before;
// otherwise pre-fill with a live suggestion so the field isn't empty.
$sales        = $saved ? (float)$saved['sales']        : suggestCollections($pdo, $fYear, $fMonth);
$invPurc      = abs($saved ? (float)$saved['inv_purchases'] : suggestInventoryPurchases($pdo, $fYear, $fMonth));
$expenses     = abs($saved ? (float)$saved['expenses']      : suggestAdminExpenses($pdo, $fYear, $fMonth));
[$depSuggest, $depDebug] = pullExpenseColumnTotal($pdo, $fYear, $fMonth, 'depreciation');
$depreciation = $saved ? (float)$saved['depreciation']  : $depSuggest;

$withdrawalRows  = getWithdrawalRows($pdo, $fYear, $fMonth);
$withdrawalTotal = array_sum(array_column($withdrawalRows, 'amount'));
if (empty($withdrawalRows)) $withdrawalRows = [['item_name' => '', 'amount' => 0]];

$netCfOperations = $sales - $invPurc - $expenses;
$netCfFinancing  = $depreciation + $withdrawalTotal;
$netIncreaseCash = $netCfOperations + $netCfFinancing;
$cashEnd         = $cashBeg + $netIncreaseCash;

// Closing Balance cross-check — pulled live from the Bank Statement
// page for this month, purely for reconciliation display. Falls back
// to the latest Bank Statement entry saved anywhere within the month
// if there's no row dated exactly on the last day.
$closingBalanceLookup = pullBankStatementClosingBalance($pdo, 'H', $lastDay, $firstDay);
$closingBalanceRef     = $closingBalanceLookup['value'];
$closingBalanceRefDate = $closingBalanceLookup['date'];

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'Cashflow';
$activePage = 'h_cashflow';
include '../layout.php';
?>

<style>
.cf-wrap { max-width: 680px; margin: 0 auto; }

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
.cf-title-row .cf-business-name {
  font-size: .74rem; font-weight: 600; color: rgba(255,255,255,.7);
  margin-top: 3px; letter-spacing: .03em;
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
.cf-input.manual-field.deduction { background: #fdf0ef; border-color: rgba(192,57,43,.25); color: #c0392b; }
.cf-input.ref-field {
  background: #ecfeff; color: #0e7490; font-weight: 700;
  cursor: default; border-color: rgba(14,116,144,.2);
}
.auto-badge {
  font-family: var(--font-m); font-size: .58rem;
  color: var(--accent); margin-left: 8px;
  background: rgba(15,123,92,.08); padding: 2px 7px;
  border-radius: 10px; border: 1px solid rgba(15,123,92,.15);
  white-space: nowrap;
}
.auto-badge.ref { color: #0e7490; background: rgba(14,116,144,.08); border-color: rgba(14,116,144,.15); }
.auto-badge.suggest { color: #92660a; background: rgba(217,119,6,.08); border-color: rgba(217,119,6,.2); }

.cf-section-header {
  background: #1e40cf; color: #fff; padding: 9px 28px;
  font-family: var(--font-m); font-size: .74rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .08em;
}
.cf-section-label {
  padding: 10px 28px 4px 52px;
  font-style: italic; font-size: .8rem; color: var(--text);
}

.cf-total {
  display: flex; align-items: center; padding: 12px 28px;
  border-top: 2px solid var(--border2); border-bottom: 1px solid var(--border);
  background: #f0f2f5;
}
.cf-total-label { flex: 1; font-size: .85rem; font-weight: 700; font-style: italic; color: var(--text); }
.cf-total-val { font-family: var(--font-m); font-size: 1rem; font-weight: 800; min-width: 150px; text-align: right; }
.cf-total-val.red { color: #c0392b; }

.cf-summary-block { background: #eef3fb; }
.cf-summary-row { display: flex; align-items: center; padding: 10px 28px; border-bottom: 1px solid #dce5f2; }
.cf-summary-row:last-child { border-bottom: none; }
.cf-summary-row.highlight { background: #cfe8f7; }
.cf-summary-label { flex: 1; font-size: .82rem; font-weight: 700; color: var(--text); }
.cf-summary-val { font-family: var(--font-m); font-size: .9rem; font-weight: 800; min-width: 150px; text-align: right; color: var(--text); }

.cf-endblock { background: #fff; }
.cf-endblock .cf-summary-row { border-bottom: 1px solid #f0f2f5; }

.cf-save-status { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

.cf-debug-box {
  max-width: 680px; margin: 0 auto 16px; padding: 10px 16px;
  border-radius: 8px; font-family: var(--font-m); font-size: .68rem;
  line-height: 1.6;
}
.cf-debug-box.ok    { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.cf-debug-box.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

/* ── Management Withdrawals — add/remove row list ── */
.cf-wd-wrap { padding: 8px 28px 14px 52px; }
.cf-wd-table { width: 100%; border-collapse: collapse; }
.cf-wd-table th {
  background: #5a5a5a; color: #fff; padding: 6px 8px;
  font-family: var(--font-m); font-size: .6rem; text-transform: uppercase;
  letter-spacing: .06em; text-align: left; border: 1px solid #444;
}
.cf-wd-table th:last-child, .cf-wd-table td:last-child { text-align: center; width: 36px; }
.cf-wd-table td { padding: 4px 6px; border: 1px solid #e5e7eb; font-size: .78rem; vertical-align: middle; }
.cf-wd-inp { width: 100%; border: 1px solid #e0e0e0; background: #fafafa; border-radius: 4px; font-family: var(--font-m); font-size: .76rem; outline: none; padding: 5px 7px; }
.cf-wd-inp.num { text-align: right; }
.cf-wd-inp:focus { background: #fffbeb; border-color: #f5c542; box-shadow: 0 0 0 2px rgba(245,197,66,.15); }
.cf-wd-table tfoot td { background: #e8a4a4; color: #7a1f1f; font-family: var(--font-m); font-weight: 800; font-size: .8rem; padding: 7px 8px; border: 1px solid #d38a8a; }
.cf-wd-table tfoot td.total-label { text-align: right; }
.btn-add-row { margin: 8px 0 0; padding: 4px 12px; background: #1a4d1a; color: #fff; border: none; border-radius: 5px; font-size: .7rem; font-weight: 700; cursor: pointer; }
.btn-add-row:hover { background: #155231; }
.btn-del-row { background: #fee2e2; border: none; color: #991b1b; border-radius: 4px; padding: 2px 6px; font-size: .65rem; cursor: pointer; }
</style>

<!-- Page Header -->
<div class="cf-header-card">
  <div>
    <div class="eyebrow">H Branch · Finance</div>
    <div class="title">Cashflow Statement</div>
    <div class="subtitle">Format follows the standard branch cashflow worksheet</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 H
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
    <a href="h_cashflow.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="cf-save-status"></span>
  </form>
</div>

<?php if (!$saved): ?>
<div class="cf-debug-box ok">
  ✓ New month — Customers Collections, Inventory Purchases, General Admin Expenses and Add Back Depreciation were pre-filled with live suggestions from Summary Report / Expenses. Edit any of them and hit Save.
</div>
<?php endif; ?>

<!-- Statement -->
<div class="cf-wrap">
<div class="cf-card">

  <div class="cf-title-row">
    <div class="cf-main-title">Cashflow Statement</div>
    <div class="cf-business-name"><?= htmlspecialchars($BUSINESS_NAME) ?></div>
  </div>

  <div class="cf-row">
    <div class="cf-label">For the month Ending</div>
    <div class="cf-input-wrap">
      <span class="cf-input" style="background:#f8f9fb;color:var(--subtext2);font-weight:700;text-align:right;border-color:transparent"><?= $displayDate ?></span>
    </div>
  </div>

  <div class="cf-row">
    <div class="cf-label">
      Cash at Beginning of Month
      <span class="auto-badge ref">↳ Auto from Bank Statement (Opening Balance, <?= date('M j, Y', strtotime($firstDay)) ?>)</span>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input ref-field" id="cash_beg" value="<?= number_format((float)$cashBeg, 2, '.', '') ?>" readonly tabindex="-1">
    </div>
  </div>

  <div class="cf-row">
    <div class="cf-label">Pull Out ROI</div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field" id="pull_out_roi" value="<?= number_format((float)$pullOutRoi, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-section-header">Operations</div>

  <div class="cf-row" style="border-top:0">
    <div class="cf-label bold">
      Customers Collections
      <?php if (!$saved): ?><span class="auto-badge suggest">↳ Suggested from Summary Report — edit as needed</span><?php endif; ?>
    </div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field" id="sales" value="<?= number_format((float)$sales, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-section-label">Cash paid for</div>

  <div class="cf-row indent">
    <div class="cf-label">Inventory Purchases (Actual Expenses)</div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field deduction" id="inv_purchases" value="<?= number_format((float)$invPurc, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-row indent">
    <div class="cf-label">General operating and administrative expenses</div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field deduction" id="expenses" value="<?= number_format((float)$expenses, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-total">
    <div class="cf-total-label">Net Cash Flow from Operations(PROFIT)</div>
    <div class="cf-total-val" id="tot_net_cf_operations"><?= $fmt($netCfOperations) ?></div>
  </div>

  <div class="cf-row" style="border-top:8px solid #f8f9fb">
    <div class="cf-label">Add Back Depreciation</div>
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field" id="depreciation" value="<?= number_format((float)$depreciation, 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
    </div>
  </div>

  <div class="cf-section-label">Cash paid for</div>

  <div class="cf-wd-wrap">
    <table class="cf-wd-table" id="wd-table">
      <thead>
        <tr><th>What is Paid For</th><th style="text-align:right">Amount</th><th></th></tr>
      </thead>
      <tbody id="wd-body">
        <?php foreach ($withdrawalRows as $wr): ?>
        <tr class="wd-row-tr">
          <td><input class="cf-wd-inp txt" type="text" placeholder="e.g. Management Withdrawal" value="<?= htmlspecialchars($wr['item_name'] ?? '') ?>" oninput="recalc()"></td>
          <td><input class="cf-wd-inp num" type="number" step="0.01" placeholder="0.00" value="<?= (float)$wr['amount'] ?: '' ?>" oninput="recalc()"></td>
          <td><button class="btn-del-row" onclick="delWdRow(this)">✕</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="total-label">TOTAL</td>
          <td style="text-align:right" id="wd-total"><?= number_format($withdrawalTotal, 2) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <button class="btn-add-row" onclick="addWdRow()">+ Add Row</button>
  </div>

  <div class="cf-total">
    <div class="cf-total-label">Net Cash Flow from Financing Activities</div>
    <div class="cf-total-val red" id="tot_net_cf_financing"><?= $fmt($netCfFinancing) ?></div>
  </div>

  <div class="cf-summary-block">
    <div class="cf-summary-row highlight">
      <div class="cf-summary-label">Net Increase in Cash</div>
      <div class="cf-summary-val" id="sum_net_increase"><?= $fmt($netIncreaseCash) ?></div>
    </div>
  </div>

  <div class="cf-summary-block cf-endblock">
    <div class="cf-summary-row">
      <div class="cf-summary-label">Cash at End of Month</div>
      <div class="cf-summary-val" id="sum_cash_end"><?= $fmt($cashEnd) ?></div>
    </div>
    <div class="cf-summary-row">
      <div class="cf-summary-label">
        Closing Balance
        <span class="auto-badge ref">
          <?= $closingBalanceRefDate !== null
                ? '↳ Auto from Bank Statement (' . date('M j, Y', strtotime($closingBalanceRefDate)) . ')'
                : '↳ No Bank Statement entry saved for ' . date('F Y', strtotime($lastDay)) . ' yet' ?>
        </span>
      </div>
      <div class="cf-summary-val" id="sum_closing_ref"><?= $closingBalanceRef !== null ? $fmt($closingBalanceRef) : '—' ?></div>
    </div>
  </div>

</div>
</div>

  </div></div>

<script>
function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = fmt(val); }

// ── Management Withdrawals row add/remove ──────────────────
function addWdRow() {
  const body = document.getElementById('wd-body');
  const tr = document.createElement('tr');
  tr.className = 'wd-row-tr';
  tr.innerHTML = `
    <td><input class="cf-wd-inp txt" type="text" placeholder="e.g. Management Withdrawal" oninput="recalc()"></td>
    <td><input class="cf-wd-inp num" type="number" step="0.01" placeholder="0.00" oninput="recalc()"></td>
    <td><button class="btn-del-row" onclick="delWdRow(this)">✕</button></td>`;
  body.appendChild(tr);
}
function delWdRow(btn) {
  btn.closest('tr').remove();
  recalc();
}
function wdTotal() {
  let total = 0;
  document.querySelectorAll('#wd-body tr.wd-row-tr').forEach(tr => {
    total += parseFloat(tr.querySelector('.cf-wd-inp.num').value) || 0;
  });
  return total;
}

function recalc() {
  const cashBeg   = gv('cash_beg');
  const sales     = gv('sales');
  const invPurc   = Math.abs(gv('inv_purchases'));
  const expenses  = Math.abs(gv('expenses'));
  const dep       = gv('depreciation');
  const withdraw  = wdTotal();

  const netCfOperations = sales - invPurc - expenses;
  const netCfFinancing  = dep + withdraw;
  const netIncreaseCash = netCfOperations + netCfFinancing;
  const cashEnd         = cashBeg + netIncreaseCash;

  document.getElementById('wd-total').textContent = fmt(withdraw);
  setEl('tot_net_cf_operations', netCfOperations);
  setEl('tot_net_cf_financing', netCfFinancing);
  setEl('sum_net_increase', netIncreaseCash);
  setEl('sum_cash_end', cashEnd);
}

async function saveCashflow() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  // 1. Save Management Withdrawal rows
  const wdRows = [];
  document.querySelectorAll('#wd-body tr.wd-row-tr').forEach(tr => {
    const inps = tr.querySelectorAll('input');
    wdRows.push({ name: inps[0]?.value || '', amount: parseFloat(inps[1]?.value) || 0 });
  });
  const fd1 = new FormData();
  fd1.append('ajax_save_withdrawal_rows', '1');
  fd1.append('rows', JSON.stringify(wdRows));
  await fetch('h_cashflow.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fd1});

  // 2. Save main fields
  const fd2 = new FormData();
  fd2.append('ajax_save', '1');
  fd2.append('cash_beg', gv('cash_beg'));
  fd2.append('pull_out_roi', gv('pull_out_roi'));
  fd2.append('sales', gv('sales'));
  fd2.append('inv_purchases', gv('inv_purchases'));
  fd2.append('expenses', gv('expenses'));
  fd2.append('depreciation', gv('depreciation'));

  try {
    const res  = await fetch('h_cashflow.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fd2});
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