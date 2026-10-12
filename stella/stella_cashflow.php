<?php
// ============================================================
//  stella_cashflow.php — Stella Branch Cashflow Statement
//  Rebuilt to follow the exact "CASHFLOW STATEMENT" worksheet
//  format (Operations / Investing / Financing, each with
//  "Cash receipts from" and "Cash paid for"). Every "Cash paid
//  for" row — including the default worksheet rows (auto-pulled
//  or manual) — can be removed, and custom rows can be added
//  freely. Removed default rows can be brought back with the
//  "Restore removed" link.
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

// ── Create tables if not exists ───────────────────────────
// Header row: one per month, holds Cash at Beginning of Month
// plus the computed roll-up totals.
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_cashflow` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `cf_date`         date NOT NULL,
    `cf_year`         int(4) NOT NULL,
    `cf_month`        tinyint(2) NOT NULL,
    `store_name`      varchar(50) NOT NULL DEFAULT 'Stella',
    `cash_beg`        decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
    `net_operations`  decimal(14,2) NOT NULL DEFAULT 0.00,
    `net_investing`   decimal(14,2) NOT NULL DEFAULT 0.00,
    `net_financing`   decimal(14,2) NOT NULL DEFAULT 0.00,
    `net_cash_flow`   decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Net Increase in Cash',
    `cash_end`        decimal(14,2) NOT NULL DEFAULT 0.00,
    `saved_by`        varchar(100) DEFAULT NULL,
    `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Migration helper: add a column to an already-existing table
// without erroring out if it's already there. Needed because the
// `stella_cashflow` table already existed on live installs before
// this rebuild introduced net_operations / net_investing / net_financing.
function ensureColumn(PDO $pdo, string $table, string $column, string $definitionSql): void {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        if ($chk && $chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definitionSql}");
        }
    } catch (Throwable $e) {
        // non-fatal — worst case the column already exists under a
        // slightly different definition, or the DB user lacks ALTER
    }
}
ensureColumn($pdo, 'stella_cashflow', 'net_operations', "decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `cash_beg`");
ensureColumn($pdo, 'stella_cashflow', 'net_investing',  "decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `net_operations`");
ensureColumn($pdo, 'stella_cashflow', 'net_financing',  "decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `net_investing`");

// Line-item rows: this is what makes "Cash paid for" (and
// "Cash receipts from") extendable instead of fixed. Every row
// on the statement — auto-pulled or manual, default or custom
// — lives here.
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_cashflow_items` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `cf_year`     int(4) NOT NULL,
    `cf_month`    tinyint(2) NOT NULL,
    `store_name`  varchar(50) NOT NULL DEFAULT 'Stella',
    `section`     enum('operations','investing','financing') NOT NULL,
    `flow_type`   enum('receipt','paid') NOT NULL,
    `label`       varchar(150) NOT NULL,
    `amount`      decimal(14,2) NOT NULL DEFAULT 0.00,
    `is_auto`     tinyint(1) NOT NULL DEFAULT 0,
    `auto_key`    varchar(50) DEFAULT NULL,
    `is_locked`   tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = default row from the worksheet',
    `is_removed`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = a default row the user removed from Cash paid for this period',
    `sort_order`  int(11) NOT NULL DEFAULT 0,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_period` (`store_name`,`cf_year`,`cf_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
ensureColumn($pdo, 'stella_cashflow_items', 'is_removed', "tinyint(1) NOT NULL DEFAULT 0 AFTER `is_locked`");

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

$lastDay     = date('Y-m-d', mktime(0,0,0,$fMonth+1,0,$fYear));
$displayDate = date('n/j/Y', strtotime($lastDay));

// ── Default rows that reproduce the worksheet's layout ─────
// Section => [ [flow_type, label, auto_key|null], ... ]  in
// worksheet order. auto_key !== null means the amount is always
// re-computed live and cannot be hand-edited.
$DEFAULT_ROWS = [
    'operations' => [
        ['receipt', 'Sales', 'sales'],
        ['receipt', 'Other Operation Sales', null],
        ['paid',    'Inventory Purchases', 'inv_purchases'],
        ['paid',    'General operating and administrative expenses', 'expenses'],
    ],
    'investing' => [
        ['receipt', 'Sales of investing property & equipment', null],
        ['receipt', 'Fees Income', null],
        ['paid',    'Purchase of property & equipment', null],
    ],
    'financing' => [
        ['receipt', 'Depreciation', null],
        ['paid',    'Repayment for loan & investment', null],
        ['paid',    'Profit Pull out', null],
        ['paid',    'Repayment for loan (company car)', null],
        ['paid',    'DND taxes', null],
        ['paid',    'PDC (loan)', 'pdc'],
        ['paid',    'Owners pull-out', 'withdrawals'],
    ],
];

// ── Helper: live-pull SUM(pdc) from stella_expenses with full diagnostics ──
function pullPdcTotal(PDO $pdo, int $year, int $month): array {
    $debug = ['step' => 'start', 'table_exists' => null, 'column_exists' => null, 'raw_value' => null, 'error' => null];
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'stella_expenses'");
        $debug['table_exists'] = $chk && $chk->rowCount() > 0;
        if (!$debug['table_exists']) {
            $debug['error'] = "Table 'stella_expenses' does not exist on this database.";
            return [0.0, $debug];
        }
        $colChk = $pdo->query("SHOW COLUMNS FROM stella_expenses LIKE 'pdc'");
        $debug['column_exists'] = $colChk && $colChk->rowCount() > 0;
        if (!$debug['column_exists']) {
            $debug['error'] = "Column 'pdc' does not exist in stella_expenses. Run the PDC/CA/Withdrawal/Depreciation migration SQL.";
            return [0.0, $debug];
        }
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(pdc),0) AS total_pdc FROM stella_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
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

// ── Helper: live-pull SUM(purchases + vat) directly from expenses ──
function pullInvPurchases(PDO $pdo, string $table, int $year, int $month): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(purchases) + SUM(vat), 0) FROM `{$table}` WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── Helper: live-pull SUM(withdrawal) → "Owners pull-out" ──
function pullWithdrawals(PDO $pdo, int $year, int $month): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(withdrawal),0) FROM stella_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── Helper: live-pull total operating expenses (General operating and administrative expenses) ──
function pullExpenses(PDO $pdo, int $year, int $month): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(
            salaries + rent + medicine + lpg + repairs_maintenance + fuel_trans +
            communication + transportation + light + drinking_water + water +
            sss_phic_hdmf + taxes_licences + office_supplies + kitchen_supplies +
            bio_pest_control + representation + miscellaneous + sir_budoy_nikki +
            staff_meal + pest_control_bio_aug + commission_fees + exhaust_cleaning + bank_fees + admin_salary_shares + marketing
        ),0) FROM stella_expenses WHERE YEAR(expense_date)=? AND MONTH(expense_date)=?");
        $stmt->execute([$year, $month]);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

// ── Helper: live-pull Sales (Net Sales, same source as Income Statement) ──
function pullSales(PDO $pdo, int $year, int $month): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(store_gross),0) FROM summary_report_entries WHERE store_name='Stella' AND YEAR(report_date)=? AND MONTH(report_date)=?");
    $stmt->execute([$year, $month]);
    return (float)$stmt->fetchColumn();
}

// ── Helper: compute every auto value in one go ──────────────
function computeAutoValues(PDO $pdo, int $year, int $month): array {
    [$pdcLoan, $pdcDebug] = pullPdcTotal($pdo, $year, $month);
    return [
        'values' => [
            'sales'         => pullSales($pdo, $year, $month),
            'inv_purchases' => pullInvPurchases($pdo, 'stella_expenses', $year, $month),
            'expenses'      => pullExpenses($pdo, $year, $month),
            'pdc'           => $pdcLoan,
            'withdrawals'   => pullWithdrawals($pdo, $year, $month),
        ],
        'pdc_debug' => $pdcDebug,
    ];
}

// ── Helper: fetch the full statement (defaults + auto values + saved rows) ──
function loadStatement(PDO $pdo, array $DEFAULT_ROWS, int $year, int $month): array {
    $auto = computeAutoValues($pdo, $year, $month);
    $autoValues = $auto['values'];

    $stmt = $pdo->prepare("SELECT * FROM stella_cashflow_items WHERE store_name='Stella' AND cf_year=? AND cf_month=? ORDER BY section, flow_type, sort_order, id");
    $stmt->execute([$year, $month]);
    $savedRows = $stmt->fetchAll();

    // Index saved rows: locked default rows by (section, flow_type, label);
    // custom rows kept in a flat list per (section, flow_type).
    $savedDefault = [];
    $savedCustom  = [];
    foreach ($savedRows as $r) {
        $key = $r['section'].'|'.$r['flow_type'].'|'.$r['label'];
        if ((int)$r['is_locked'] === 1) {
            $savedDefault[$key] = $r;
        } else {
            $savedCustom[$r['section'].'|'.$r['flow_type']][] = $r;
        }
    }

    $statement = [];
    foreach (['operations','investing','financing'] as $section) {
        $statement[$section] = ['receipt' => [], 'paid' => []];

        foreach ($DEFAULT_ROWS[$section] as [$flowType, $label, $autoKey]) {
            $key = $section.'|'.$flowType.'|'.$label;
            $existing = $savedDefault[$key] ?? null;

            // A default row can be removed from "Cash paid for" only —
            // once the user removes it, it stays hidden for this period
            // until they hit "Restore removed items".
            if ($flowType === 'paid' && $existing && (int)($existing['is_removed'] ?? 0) === 1) {
                continue;
            }

            $amount = $autoKey !== null
                ? ($autoValues[$autoKey] ?? 0.0)
                : (float)($existing['amount'] ?? 0.0);

            $statement[$section][$flowType][] = [
                'id'        => $existing['id'] ?? 0,
                'label'     => $label,
                'amount'    => $amount,
                'is_auto'   => $autoKey !== null,
                'auto_key'  => $autoKey,
                'locked'    => true,
                'removable' => $flowType === 'paid',
            ];
        }

        foreach (['receipt','paid'] as $flowType) {
            $custom = $savedCustom[$section.'|'.$flowType] ?? [];
            foreach ($custom as $r) {
                $statement[$section][$flowType][] = [
                    'id'        => (int)$r['id'],
                    'label'     => $r['label'],
                    'amount'    => (float)$r['amount'],
                    'is_auto'   => false,
                    'auto_key'  => null,
                    'locked'    => false,
                    'removable' => true,
                ];
            }
        }
    }

    return ['statement' => $statement, 'pdc_debug' => $auto['pdc_debug']];
}

function sumRows(array $rows): float {
    $t = 0.0;
    foreach ($rows as $r) $t += (float)$r['amount'];
    return $t;
}

// ── AJAX: Restore removed default "Cash paid for" rows ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_restore'])) {
    header('Content-Type: application/json');
    try {
        $section = $_POST['section'] ?? 'all';
        $validSections = ['operations','investing','financing'];
        if ($section === 'all') {
            $q = $pdo->prepare("UPDATE stella_cashflow_items SET is_removed=0 WHERE store_name='Stella' AND cf_year=? AND cf_month=? AND is_locked=1 AND flow_type='paid'");
            $q->execute([$fYear, $fMonth]);
        } elseif (in_array($section, $validSections, true)) {
            $q = $pdo->prepare("UPDATE stella_cashflow_items SET is_removed=0 WHERE store_name='Stella' AND cf_year=? AND cf_month=? AND is_locked=1 AND flow_type='paid' AND section=?");
            $q->execute([$fYear, $fMonth, $section]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $cashBeg = (float)($_POST['cash_beg'] ?? 0);

        $rawItems = json_decode($_POST['items'] ?? '[]', true);
        if (!is_array($rawItems)) $rawItems = [];

        $rawRemoved = json_decode($_POST['removed_defaults'] ?? '[]', true);
        if (!is_array($rawRemoved)) $rawRemoved = [];

        $validSections = ['operations','investing','financing'];
        $validFlow     = ['receipt','paid'];

        $pdo->beginTransaction();

        // ── Custom (user-added) rows: fully replaced each save. This
        // is what lets "Cash paid for" grow with new line items.
        $delCustom = $pdo->prepare("DELETE FROM stella_cashflow_items WHERE store_name='Stella' AND cf_year=? AND cf_month=? AND is_locked=0");
        $delCustom->execute([$fYear, $fMonth]);

        $insCustom = $pdo->prepare("INSERT INTO stella_cashflow_items
            (cf_year,cf_month,store_name,section,flow_type,label,amount,is_auto,auto_key,is_locked,is_removed,sort_order)
            VALUES (?,?,'Stella',?,?,?,?,0,NULL,0,0,?)");

        $sort = 0;
        foreach ($rawItems as $it) {
            if (!empty($it['locked'])) continue; // default rows are handled separately below
            $section  = $it['section']   ?? '';
            $flowType = $it['flow_type'] ?? '';
            $label    = trim((string)($it['label'] ?? ''));
            $amount   = (float)($it['amount'] ?? 0);

            if (!in_array($section, $validSections, true)) continue;
            if (!in_array($flowType, $validFlow, true)) continue;
            if ($label === '') continue;

            $insCustom->execute([$fYear, $fMonth, $section, $flowType, $label, $amount, $sort]);
            $sort++;
        }

        // ── Default rows (worksheet rows, auto or manual): upserted
        // individually so a removed one stays removed even though it's
        // absent from the client's payload (it's simply not in the DOM).
        $existQ = $pdo->prepare("SELECT * FROM stella_cashflow_items WHERE store_name='Stella' AND cf_year=? AND cf_month=? AND is_locked=1");
        $existQ->execute([$fYear, $fMonth]);
        $existingDefaults = [];
        foreach ($existQ->fetchAll() as $r) {
            $existingDefaults[$r['section'].'|'.$r['flow_type'].'|'.$r['label']] = $r;
        }

        $removedKeys = [];
        foreach ($rawRemoved as $rd) {
            $s = $rd['section'] ?? ''; $f = $rd['flow_type'] ?? ''; $l = trim((string)($rd['label'] ?? ''));
            if ($s !== '' && $f !== '' && $l !== '') $removedKeys[$s.'|'.$f.'|'.$l] = true;
        }

        $manualDefaultAmounts = [];
        foreach ($rawItems as $it) {
            if (empty($it['locked'])) continue;
            $s = $it['section'] ?? ''; $f = $it['flow_type'] ?? ''; $l = trim((string)($it['label'] ?? ''));
            if ($s !== '' && $f !== '' && $l !== '') $manualDefaultAmounts[$s.'|'.$f.'|'.$l] = (float)($it['amount'] ?? 0);
        }

        $autoValues = computeAutoValues($pdo, $fYear, $fMonth)['values'];

        $updDefault = $pdo->prepare("UPDATE stella_cashflow_items SET amount=?, is_removed=? WHERE id=?");
        $insDefault = $pdo->prepare("INSERT INTO stella_cashflow_items (cf_year,cf_month,store_name,section,flow_type,label,amount,is_auto,auto_key,is_locked,is_removed,sort_order) VALUES (?,?,'Stella',?,?,?,?,?,?,1,?,-1)");

        foreach ($DEFAULT_ROWS as $section => $rows) {
            foreach ($rows as [$flowType, $label, $autoKey]) {
                $key         = $section.'|'.$flowType.'|'.$label;
                $existingRow = $existingDefaults[$key] ?? null;
                $isAuto      = $autoKey !== null;

                if ($flowType === 'paid') {
                    if (isset($removedKeys[$key])) {
                        // explicitly removed this save
                        $isRemoved = 1;
                    } elseif ($isAuto || array_key_exists($key, $manualDefaultAmounts)) {
                        // still visible in the client's DOM this save
                        $isRemoved = 0;
                    } else {
                        // absent, but not explicitly removed either — it was
                        // already hidden from a previous save; keep it hidden
                        $isRemoved = $existingRow ? (int)$existingRow['is_removed'] : 0;
                    }
                } else {
                    $isRemoved = 0; // "Cash receipts from" rows are never removable
                }

                $amount = $isAuto
                    ? ($autoValues[$autoKey] ?? 0.0)
                    : ($manualDefaultAmounts[$key] ?? (float)($existingRow['amount'] ?? 0.0));

                if ($existingRow) {
                    $updDefault->execute([$amount, $isRemoved, $existingRow['id']]);
                } else {
                    $insDefault->execute([$fYear, $fMonth, $section, $flowType, $label, $amount, $isAuto ? 1 : 0, $autoKey, $isRemoved]);
                }
            }
        }

        // Recompute the roll-up totals from the freshly saved statement.
        ['statement' => $statement] = loadStatement($pdo, $DEFAULT_ROWS, $fYear, $fMonth);
        $netOps = sumRows($statement['operations']['receipt']) - sumRows($statement['operations']['paid']);
        $netInv = sumRows($statement['investing']['receipt'])  - sumRows($statement['investing']['paid']);
        $netFin = sumRows($statement['financing']['receipt'])  - sumRows($statement['financing']['paid']);
        $netIncrease = $netOps + $netInv + $netFin;
        $cashEnd = $cashBeg + $netIncrease;

        $existing = $pdo->prepare("SELECT id FROM stella_cashflow WHERE store_name='Stella' AND cf_year=? AND cf_month=? LIMIT 1");
        $existing->execute([$fYear, $fMonth]);
        $existingId = $existing->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE stella_cashflow SET cf_date=?, cash_beg=?, net_operations=?, net_investing=?, net_financing=?, net_cash_flow=?, cash_end=?, saved_by=? WHERE id=?")
                ->execute([$lastDay, $cashBeg, $netOps, $netInv, $netFin, $netIncrease, $cashEnd, $user['name'], $existingId]);
        } else {
            $pdo->prepare("INSERT INTO stella_cashflow (cf_date,cf_year,cf_month,store_name,cash_beg,net_operations,net_investing,net_financing,net_cash_flow,cash_end,saved_by) VALUES (?,?,?,'Stella',?,?,?,?,?,?,?)")
                ->execute([$lastDay, $fYear, $fMonth, $cashBeg, $netOps, $netInv, $netFin, $netIncrease, $cashEnd, $user['name']]);
        }

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'net_operations' => $netOps,
            'net_investing'  => $netInv,
            'net_financing'  => $netFin,
            'net_cash_flow'  => $netIncrease,
            'cash_end'       => $cashEnd,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $h = $pdo->prepare("SELECT * FROM stella_cashflow WHERE store_name='Stella' AND cf_year=? AND cf_month=? LIMIT 1");
    $h->execute([$fYear, $fMonth]);
    $header = $h->fetch() ?: null;
    $cashBegCsv = $header ? (float)$header['cash_beg'] : 0.0;

    ['statement' => $statement] = loadStatement($pdo, $DEFAULT_ROWS, $fYear, $fMonth);
    $netOps = sumRows($statement['operations']['receipt']) - sumRows($statement['operations']['paid']);
    $netInv = sumRows($statement['investing']['receipt'])  - sumRows($statement['investing']['paid']);
    $netFin = sumRows($statement['financing']['receipt'])  - sumRows($statement['financing']['paid']);
    $netIncrease = $netOps + $netInv + $netFin;
    $cashEndCsv  = $cashBegCsv + $netIncrease;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stella_cashflow_'.date('Y_m', mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output', 'w');
    $n2 = fn($v) => number_format((float)$v, 2, '.', '');

    fputcsv($out, ['CASHFLOW STATEMENT']);
    fputcsv($out, ['STELLA']);
    fputcsv($out, ['']);
    fputcsv($out, ['', 'For the month Ending', $displayDate]);
    fputcsv($out, ['', 'Cash at Beginning of Month', $n2($cashBegCsv)]);
    fputcsv($out, ['']);

    $sectionLabels = ['operations' => 'Operations', 'investing' => 'Investing Activities', 'financing' => 'Financing Activities'];
    $nets = ['operations' => $netOps, 'investing' => $netInv, 'financing' => $netFin];
    foreach (['operations','investing','financing'] as $section) {
        fputcsv($out, [$sectionLabels[$section]]);
        fputcsv($out, ['Cash receipts from']);
        foreach ($statement[$section]['receipt'] as $row) {
            fputcsv($out, ['', $row['label'], $n2($row['amount'])]);
        }
        fputcsv($out, ['Cash paid for']);
        foreach ($statement[$section]['paid'] as $row) {
            fputcsv($out, ['', $row['label'], $n2(-$row['amount'])]);
        }
        fputcsv($out, ['Net Cash Flow from '.$sectionLabels[$section], '', $n2($nets[$section])]);
        fputcsv($out, ['']);
    }

    fputcsv($out, ['Net Increase in Cash', '', $n2($netIncrease)]);
    fputcsv($out, ['']);
    fputcsv($out, ['Cash at Beginning of Month', '', $n2($cashBegCsv)]);
    fputcsv($out, ['Cash at End of Month', '', $n2($cashEndCsv)]);
    fclose($out);
    exit;
}

// ── Load saved header + full statement ──────────────────────
$h = $pdo->prepare("SELECT * FROM stella_cashflow WHERE store_name='Stella' AND cf_year=? AND cf_month=? LIMIT 1");
$h->execute([$fYear, $fMonth]);
$savedHeader = $h->fetch() ?: null;
$cashBeg = (float)($savedHeader['cash_beg'] ?? 0);

['statement' => $statement, 'pdc_debug' => $pdcDebug] = loadStatement($pdo, $DEFAULT_ROWS, $fYear, $fMonth);

$netOps = sumRows($statement['operations']['receipt']) - sumRows($statement['operations']['paid']);
$netInv = sumRows($statement['investing']['receipt'])  - sumRows($statement['investing']['paid']);
$netFin = sumRows($statement['financing']['receipt'])  - sumRows($statement['financing']['paid']);
$netIncrease = $netOps + $netInv + $netFin;
$cashEnd = $cashBeg + $netIncrease;

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'Cashflow';
$activePage = 'stella_cashflow';
include '../layout.php';
?>

<style>
.cf-wrap { max-width: 760px; margin: 0 auto; }

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

.cf-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; max-width: 760px; margin-left: auto; margin-right: auto; }

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
.cf-title-row .cf-sub-title {
  font-size: .68rem; color: rgba(255,255,255,.6); font-family: var(--font-m);
  letter-spacing: .08em; margin-top: 2px;
}

.cf-row {
  display: flex; align-items: center; padding: 0 28px;
  border-bottom: 1px solid #f0f2f5; min-height: 44px; transition: background .1s;
  gap: 10px;
}
.cf-row:hover { background: #fafbfc; }
.cf-row.indent { padding-left: 52px; }

.cf-label { flex: 1; font-size: .82rem; color: var(--text); font-weight: 500; }
.cf-label.bold { font-weight: 700; }

.cf-input-wrap { display: flex; align-items: center; gap: 6px; }
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
.cf-label-input {
  flex: 1; padding: 6px 10px; font-size: .82rem; color: var(--text);
  background: #fff; border: 1px solid var(--border); border-radius: 7px; outline: none;
}
.cf-label-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }

.auto-badge {
  font-family: var(--font-m); font-size: .58rem;
  color: var(--accent); margin-left: 8px;
  background: rgba(15,123,92,.08); padding: 2px 7px;
  border-radius: 10px; border: 1px solid rgba(15,123,92,.15);
  white-space: nowrap;
}

.cf-remove-btn {
  border: none; background: transparent; color: #b91c1c; cursor: pointer;
  font-size: .95rem; line-height: 1; padding: 4px 6px; border-radius: 6px;
}
.cf-remove-btn:hover { background: #fef2f2; }

.cf-section-band {
  background: #eef2f9; padding: 8px 28px; font-weight: 800; font-size: .78rem;
  color: #1e3060; text-transform: uppercase; letter-spacing: .04em; font-family: var(--font-m);
  border-top: 2px solid #dbe3f0; border-bottom: 1px solid #dbe3f0;
}
.cf-subsection-label {
  padding: 10px 28px 4px;
  font-style: italic; font-size: .8rem; color: var(--text);
  display: flex; align-items: center; justify-content: space-between;
}
.cf-add-btn {
  font-family: var(--font-m); font-size: .65rem; font-weight: 700;
  color: var(--accent); background: rgba(15,123,92,.08);
  border: 1px solid rgba(15,123,92,.2); border-radius: 8px;
  padding: 3px 10px; cursor: pointer; white-space: nowrap;
}
.cf-add-btn:hover { background: rgba(15,123,92,.15); }

.cf-restore-link {
  font-family: var(--font-m); font-size: .62rem; font-weight: 600;
  color: var(--subtext); text-decoration: none; white-space: nowrap;
}
.cf-restore-link:hover { color: var(--accent); text-decoration: underline; }

.cf-subtotal {
  display: flex; align-items: center; padding: 10px 28px;
  border-top: 1px solid var(--border2); border-bottom: 1px solid #f0f2f5;
  background: #fbfcfe;
}
.cf-subtotal-label { flex: 1; font-size: .8rem; font-weight: 700; font-style: italic; color: var(--text); }
.cf-subtotal-val { font-family: var(--font-m); font-size: .88rem; font-weight: 800; min-width: 150px; text-align: right; }

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
  max-width: 760px; margin: 0 auto 16px; padding: 10px 16px;
  border-radius: 8px; font-family: var(--font-m); font-size: .68rem;
  line-height: 1.6;
}
.cf-debug-box.ok    { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.cf-debug-box.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

@media (max-width: 640px) {
  .cf-row, .cf-section-band, .cf-subsection-label, .cf-subtotal, .cf-total, .cf-summary-row { padding-left: 16px; padding-right: 16px; }
  .cf-row.indent { padding-left: 28px; }
  .cf-input { width: 110px; }
}
</style>

<!-- Page Header -->
<div class="cf-header-card">
  <div>
    <div class="eyebrow">Stella Branch · Finance</div>
    <div class="title">Cashflow Statement</div>
    <div class="subtitle">Format follows the standard branch cashflow worksheet — Operations / Investing / Financing</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 Stella
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
    <a href="stella_cashflow.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="saveStatus" class="cf-save-status"></span>
  </form>
</div>

<?php if ($pdcDebug['error']): ?>
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
    <div class="cf-sub-title">STELLA</div>
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
      <input type="number" step="0.01" class="cf-input manual-field" id="cash_beg" value="<?= number_format($cashBeg, 2, '.', '') ?>" oninput="recalcAll()" placeholder="0.00">
    </div>
  </div>

  <?php
  $sectionLabels = ['operations' => 'Operations', 'investing' => 'Investing Activities', 'financing' => 'Financing Activities'];
  $sectionNets   = ['operations' => $netOps, 'investing' => $netInv, 'financing' => $netFin];

  foreach (['operations','investing','financing'] as $section):
  ?>
  <div class="cf-section-band"><?= $sectionLabels[$section] ?></div>

  <div class="cf-subsection-label"><span><em>Cash receipts from</em></span></div>
  <div class="cf-rows" data-section="<?= $section ?>" data-flow="receipt">
    <?php foreach ($statement[$section]['receipt'] as $row): ?>
      <?php if ($row['is_auto']): ?>
      <div class="cf-row indent">
        <div class="cf-label"><?= htmlspecialchars($row['label']) ?>
          <span class="auto-badge">↳ Auto</span>
        </div>
        <div class="cf-input-wrap">
          <input type="number" step="0.01" class="cf-input auto-field cf-amt" data-auto-key="<?= $row['auto_key'] ?>" value="<?= number_format($row['amount'],2,'.','') ?>" readonly tabindex="-1">
        </div>
      </div>
      <?php else: ?>
      <div class="cf-row indent">
        <div class="cf-label"><?= htmlspecialchars($row['label']) ?></div>
        <div class="cf-input-wrap">
          <input type="number" step="0.01" class="cf-input manual-field cf-amt" value="<?= number_format($row['amount'],2,'.','') ?>" oninput="recalcAll()" placeholder="0.00">
        </div>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="cf-subsection-label">
    <span><em>Cash paid for</em></span>
    <span style="display:flex;gap:8px;align-items:center">
      <a href="javascript:void(0)" class="cf-restore-link" onclick="restoreDefaults('<?= $section ?>')" title="Bring back any removed default rows in this section">↺ Restore removed</a>
      <button type="button" class="cf-add-btn" onclick="addCustomRow('<?= $section ?>','paid')">+ Add line item</button>
    </span>
  </div>
  <div class="cf-rows" data-section="<?= $section ?>" data-flow="paid">
    <?php foreach ($statement[$section]['paid'] as $row): ?>
      <?php if ($row['is_auto']): ?>
      <div class="cf-row indent cf-default-paid-row" data-section="<?= $section ?>" data-flow="paid" data-label="<?= htmlspecialchars($row['label']) ?>">
        <div class="cf-label"><?= htmlspecialchars($row['label']) ?>
          <span class="auto-badge">↳ Auto</span>
        </div>
        <div class="cf-input-wrap">
          <input type="number" step="0.01" class="cf-input auto-field cf-amt" data-auto-key="<?= $row['auto_key'] ?>" value="<?= number_format($row['amount'],2,'.','') ?>" readonly tabindex="-1">
          <button type="button" class="cf-remove-btn" title="Remove this row" onclick="removeDefaultRow(this)">✕</button>
        </div>
      </div>
      <?php elseif ($row['locked']): ?>
      <div class="cf-row indent cf-default-paid-row" data-section="<?= $section ?>" data-flow="paid" data-label="<?= htmlspecialchars($row['label']) ?>">
        <div class="cf-label"><?= htmlspecialchars($row['label']) ?></div>
        <div class="cf-input-wrap">
          <input type="number" step="0.01" class="cf-input manual-field cf-amt" value="<?= number_format($row['amount'],2,'.','') ?>" oninput="recalcAll()" placeholder="0.00">
          <button type="button" class="cf-remove-btn" title="Remove this row" onclick="removeDefaultRow(this)">✕</button>
        </div>
      </div>
      <?php else: ?>
      <div class="cf-row indent cf-custom-row">
        <input type="text" class="cf-label-input cf-lbl" value="<?= htmlspecialchars($row['label']) ?>" placeholder="Line item name">
        <div class="cf-input-wrap">
          <input type="number" step="0.01" class="cf-input manual-field cf-amt" value="<?= number_format($row['amount'],2,'.','') ?>" oninput="recalcAll()" placeholder="0.00">
          <button type="button" class="cf-remove-btn" title="Remove line item" onclick="this.closest('.cf-custom-row').remove(); recalcAll();">✕</button>
        </div>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="cf-subtotal">
    <div class="cf-subtotal-label">Net Cash Flow from <?= $sectionLabels[$section] ?></div>
    <div class="cf-subtotal-val" id="net_<?= $section ?>"><?= $fmt($sectionNets[$section]) ?></div>
  </div>
  <?php endforeach; ?>

  <div class="cf-total">
    <div class="cf-total-label">Net Increase in Cash</div>
    <div class="cf-total-val" id="tot_net_increase"><?= $fmt($netIncrease) ?></div>
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

<template id="custom-row-template">
  <div class="cf-row indent cf-custom-row">
    <input type="text" class="cf-label-input cf-lbl" value="" placeholder="Line item name">
    <div class="cf-input-wrap">
      <input type="number" step="0.01" class="cf-input manual-field cf-amt" value="0.00" oninput="recalcAll()" placeholder="0.00">
      <button type="button" class="cf-remove-btn" title="Remove line item" onclick="this.closest('.cf-custom-row').remove(); recalcAll();">✕</button>
    </div>
  </div>
</template>

<script>
// Default (worksheet) "Cash paid for" rows the user removed this
// session but hasn't necessarily saved yet — sent along on Save so
// the server can mark them hidden even though they're just absent
// from the DOM.
window.removedDefaults = [];

function gv(el) { return parseFloat(el?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = fmt(val); }

function sumFlow(section, flow) {
  const wrap = document.querySelector(`.cf-rows[data-section="${section}"][data-flow="${flow}"]`);
  if (!wrap) return 0;
  let total = 0;
  wrap.querySelectorAll('.cf-amt').forEach(inp => total += gv(inp));
  return total;
}

function recalcAll() {
  const cashBeg = gv(document.getElementById('cash_beg'));

  let netIncrease = 0;
  ['operations','investing','financing'].forEach(section => {
    const receipts = sumFlow(section, 'receipt');
    const paid     = sumFlow(section, 'paid');
    const net = receipts - paid;
    netIncrease += net;
    setEl('net_' + section, net);
  });

  const cashEnd = cashBeg + netIncrease;
  setEl('tot_net_increase', netIncrease);
  setEl('sum_cash_beg', cashBeg);
  setEl('sum_cash_end', cashEnd);
}

function addCustomRow(section, flow) {
  const wrap = document.querySelector(`.cf-rows[data-section="${section}"][data-flow="${flow}"]`);
  const tpl  = document.getElementById('custom-row-template');
  const node = tpl.content.cloneNode(true);
  wrap.appendChild(node);
  const newRow = wrap.lastElementChild;
  newRow.querySelector('.cf-lbl').focus();
  recalcAll();
}

// Removes a default (worksheet) "Cash paid for" row — auto-pulled or
// manual. Unlike custom rows, this needs to be remembered so it stays
// gone after Save (and after reload) rather than just resetting to 0.
function removeDefaultRow(btn) {
  const row = btn.closest('.cf-default-paid-row');
  if (!row) return;
  window.removedDefaults.push({
    section:   row.dataset.section,
    flow_type: row.dataset.flow,
    label:     row.dataset.label
  });
  row.remove();
  recalcAll();
}

// Brings back any default "Cash paid for" rows removed for this
// section (or all sections) and reloads to show the restored state.
async function restoreDefaults(section) {
  const fd = new FormData();
  fd.append('ajax_restore', '1');
  fd.append('section', section);
  try {
    const res  = await fetch('stella_cashflow.php' + window.location.search, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      window.location.reload();
    } else {
      showToast('❌ ' + (data.msg || 'Could not restore items'), 'error');
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
}

function collectItems() {
  const items = [];
  document.querySelectorAll('.cf-rows').forEach(wrap => {
    const section = wrap.dataset.section;
    const flow    = wrap.dataset.flow;
    wrap.querySelectorAll('.cf-row').forEach(row => {
      const autoInput = row.querySelector('.cf-amt.auto-field');
      if (autoInput) return; // auto rows are recomputed server-side, skip
      const lblInput = row.querySelector('.cf-lbl');
      const amtInput = row.querySelector('.cf-amt');
      if (!amtInput) return;
      const isCustom = row.classList.contains('cf-custom-row');
      const label = isCustom ? (lblInput?.value || '').trim() : row.querySelector('.cf-label')?.childNodes[0]?.textContent.trim();
      if (!label) return;
      items.push({
        section,
        flow_type: flow,
        label,
        amount: gv(amtInput),
        locked: !isCustom
      });
    });
  });
  return items;
}

async function saveCashflow() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('cash_beg', gv(document.getElementById('cash_beg')));
  fd.append('items', JSON.stringify(collectItems()));
  fd.append('removed_defaults', JSON.stringify(window.removedDefaults));

  try {
    const res  = await fetch('stella_cashflow.php' + window.location.search, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      showToast('✓ Cashflow statement saved', 'success');
      setTimeout(() => { window.location.reload(); }, 600);
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

document.addEventListener('DOMContentLoaded', recalcAll);
</script>
</body>
</html>
