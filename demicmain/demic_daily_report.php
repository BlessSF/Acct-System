<?php
// ============================================================
//  demic_daily_report.php — Demic Lab Daily Report Input (CRUD)
//  Payables stored as JSON in payables_expenses column
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();
require_once '../lib/branch_colors.php';

$pdo  = getPDO();
$user = currentUser();

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_daily_reports` (
    `id`                       int(11) NOT NULL AUTO_INCREMENT,
    `report_date`              date NOT NULL,
    `store_name`               varchar(50) NOT NULL DEFAULT 'Demic Lab',
    `sales_revenue`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `sales_discount`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline_medical_staff`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_fee`                   decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash`                     decimal(12,2) NOT NULL DEFAULT 0.00,
    `hmo`                      decimal(12,2) NOT NULL DEFAULT 0.00,
    `charge_to_company`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `dr_cr`                    decimal(12,2) NOT NULL DEFAULT 0.00,
    `hmo_withholding_pct`      decimal(5,2)  NOT NULL DEFAULT 2.00,
    `quota_target`             decimal(12,2) NOT NULL DEFAULT 50000.00,
    `payables_threshold`       decimal(5,2)  NOT NULL DEFAULT 45.00,
    `mp_threshold`             decimal(5,2)  NOT NULL DEFAULT 10.00,
    `payables_expenses`        text,
    `hmo_approved`             decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total HMO Approved (count)',
    `patients_served`          decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total Patients Serve (count)',
    `test_xray`                decimal(10,2) NOT NULL DEFAULT 0.00,
    `test_laboratory`          decimal(10,2) NOT NULL DEFAULT 0.00,
    `test_consultation`        decimal(10,2) NOT NULL DEFAULT 0.00,
    `test_ultrasound`          decimal(10,2) NOT NULL DEFAULT 0.00,
    `test_2d_echo`             decimal(10,2) NOT NULL DEFAULT 0.00,
    `created_by`               varchar(100) DEFAULT NULL,
    `updated_at`               timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

try { $pdo->exec("ALTER TABLE `demic_daily_reports` ADD UNIQUE KEY `ux_date_store` (`report_date`,`store_name`)"); } catch(Throwable $ignored){}
// Backfill new columns for tables created before this update
foreach (['hmo_approved','patients_served','test_xray','test_laboratory','test_consultation','test_ultrasound','test_2d_echo'] as $col) {
    try { $pdo->exec("ALTER TABLE `demic_daily_reports` ADD COLUMN `$col` decimal(10,2) NOT NULL DEFAULT 0.00"); } catch(Throwable $ignored){}
}

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = ($_POST['ajax'] ?? '') === '1';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $report_date = $_POST['report_date'] ?? date('Y-m-d');
        // Branch accounts always use their own branch name; management can set freely
        $store_name  = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');

        $frontline_medical_staff = (float)($_POST['frontline_medical_staff'] ?? 0);
        $pf_fee                  = (float)($_POST['pf_fee']                  ?? 0);
        $cash                    = (float)($_POST['cash']                    ?? 0);
        $hmo                     = (float)($_POST['hmo']                     ?? 0);
        $charge_to_company       = (float)($_POST['charge_to_company']       ?? 0);
        $dr_cr                   = (float)($_POST['dr_cr']                   ?? 0);
        $hmo_withholding_pct     = (float)($_POST['hmo_withholding_pct']     ?? 2);
        $quota_target            = (float)($_POST['quota_target']            ?? 50000);
        $payables_threshold      = (float)($_POST['payables_threshold']      ?? 45);
        $mp_threshold            = (float)($_POST['mp_threshold']            ?? 10);
        $hmo_approved            = (float)($_POST['hmo_approved']            ?? 0);
        $patients_served         = (float)($_POST['patients_served']         ?? 0);
        $test_xray               = (float)($_POST['test_xray']               ?? 0);
        $test_laboratory         = (float)($_POST['test_laboratory']         ?? 0);
        $test_consultation       = (float)($_POST['test_consultation']       ?? 0);
        $test_ultrasound         = (float)($_POST['test_ultrasound']         ?? 0);
        $test_2d_echo            = (float)($_POST['test_2d_echo']            ?? 0);

        // Sales Revenue = total of the Payment Method Breakdown (Cash + HMO + Charge to Company + DR/CR)
        // Sales Discount (SC/PWD/CORP) — entered directly, not derived.
        $sales_revenue  = round($cash + $hmo + $charge_to_company + $dr_cr, 2);
        $sales_discount = (float)($_POST['sales_discount'] ?? 0);

        // Build payables JSON (dynamic line items)
        $payItems   = [];
        $payLabels  = $_POST['pay_label']  ?? [];
        $payAmounts = $_POST['pay_amount'] ?? [];
        foreach ($payLabels as $i => $lbl) {
            $lbl = trim($lbl);
            $amt = (float)($payAmounts[$i] ?? 0);
            if ($lbl !== '' || $amt > 0) {
                $payItems[] = ['label' => $lbl ?: 'Payable '.($i+1), 'amount' => $amt];
            }
        }
        $payJson = json_encode($payItems);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE demic_daily_reports SET
                report_date=?,store_name=?,sales_revenue=?,sales_discount=?,
                frontline_medical_staff=?,pf_fee=?,cash=?,hmo=?,charge_to_company=?,dr_cr=?,
                hmo_withholding_pct=?,quota_target=?,payables_threshold=?,mp_threshold=?,
                payables_expenses=?,hmo_approved=?,patients_served=?,
                test_xray=?,test_laboratory=?,test_consultation=?,test_ultrasound=?,test_2d_echo=?
                WHERE id=?");
            $stmt->execute([$report_date,$store_name,$sales_revenue,$sales_discount,
                $frontline_medical_staff,$pf_fee,$cash,$hmo,$charge_to_company,$dr_cr,
                $hmo_withholding_pct,$quota_target,$payables_threshold,$mp_threshold,
                $payJson,$hmo_approved,$patients_served,
                $test_xray,$test_laboratory,$test_consultation,$test_ultrasound,$test_2d_echo,
                $id]);
            flashSet('success','Demic Lab report updated.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO demic_daily_reports
                (report_date,store_name,sales_revenue,sales_discount,
                 frontline_medical_staff,pf_fee,cash,hmo,charge_to_company,dr_cr,
                 hmo_withholding_pct,quota_target,payables_threshold,mp_threshold,
                 payables_expenses,hmo_approved,patients_served,
                 test_xray,test_laboratory,test_consultation,test_ultrasound,test_2d_echo,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                store_name=VALUES(store_name),sales_revenue=VALUES(sales_revenue),
                sales_discount=VALUES(sales_discount),
                frontline_medical_staff=VALUES(frontline_medical_staff),pf_fee=VALUES(pf_fee),
                cash=VALUES(cash),hmo=VALUES(hmo),charge_to_company=VALUES(charge_to_company),dr_cr=VALUES(dr_cr),
                hmo_withholding_pct=VALUES(hmo_withholding_pct),quota_target=VALUES(quota_target),
                payables_threshold=VALUES(payables_threshold),mp_threshold=VALUES(mp_threshold),
                payables_expenses=VALUES(payables_expenses),
                hmo_approved=VALUES(hmo_approved),patients_served=VALUES(patients_served),
                test_xray=VALUES(test_xray),test_laboratory=VALUES(test_laboratory),
                test_consultation=VALUES(test_consultation),test_ultrasound=VALUES(test_ultrasound),
                test_2d_echo=VALUES(test_2d_echo)");
            $stmt->execute([$report_date,$store_name,$sales_revenue,$sales_discount,
                $frontline_medical_staff,$pf_fee,$cash,$hmo,$charge_to_company,$dr_cr,
                $hmo_withholding_pct,$quota_target,$payables_threshold,$mp_threshold,
                $payJson,$hmo_approved,$patients_served,
                $test_xray,$test_laboratory,$test_consultation,$test_ultrasound,$test_2d_echo,
                $user['name']]);
            flashSet('success','Demic Lab report saved.');
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
        header('Location: demic_daily_report.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        [$bClause, $bParams] = branchFilter('store_name');
        $pdo->prepare("DELETE FROM demic_daily_reports WHERE id=? AND $bClause")
            ->execute(array_merge([$id], $bParams));
        flashSet('success','Report deleted.');
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
        header('Location: demic_daily_report.php'); exit;
    }
}

// ── Load for edit ──────────────────────────────────────────
$editRow = null;
if (isset($_GET['edit'])) {
    [$bClause, $bParams] = branchFilter('store_name');
    $s = $pdo->prepare("SELECT * FROM demic_daily_reports WHERE id=? AND $bClause");
    $s->execute(array_merge([(int)$_GET['edit']], $bParams));
    $editRow = $s->fetch();
}

// ── Filters ───────────────────────────────────────────────
$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// Optional exact-date filter (?date=YYYY-MM-DD narrows the list below
// to just that one day) — same pattern as the main Daily Report page
// and Commissary's OIC Daily Report.
$fDate = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDate)) $fDate = '';

if ($fDate !== '') {
    // A specific date filter always wins and drives which month/year the
    // tabs above show as active, so the filtered day is visibly in view.
    $fYear  = (int)date('Y', strtotime($fDate));
    $fMonth = (int)date('n', strtotime($fDate));
} else {
    $fYear  = (int)($_GET['year']  ?? date('Y'));
    $fMonth = (int)($_GET['month'] ?? date('n'));
    if ($fMonth < 1 || $fMonth > 12) $fMonth = (int)date('n');
}

// ── Management "view branch" filter ─────────────────────────
// Demic Lab has two stores sharing this one page/table (DemicLab-Main,
// DemicLab-Jaro), distinguished by store_name. Branch accounts only
// ever see their own store via branchFilter() below. Management sees
// both by default, but can pick one via ?store= (same click-to-filter
// pattern as the main Daily Report page).
$DEMIC_STORES = ['DemicLab-Main', 'DemicLab-Jaro'];
$isManagerView = !isBranch();
$viewStore = '';
if ($isManagerView) {
    $selStore  = trim($_GET['store'] ?? '');
    $viewStore = in_array($selStore, $DEMIC_STORES, true) ? $selStore : '';
}

[$bClause, $bParams] = branchFilter('store_name');
if ($viewStore !== '') {
    $bClause = "`store_name` = ?";
    $bParams = [$viewStore];
}
if ($fDate !== '') {
    $rows = $pdo->prepare("SELECT * FROM demic_daily_reports WHERE report_date=? AND $bClause ORDER BY report_date DESC");
    $rows->execute(array_merge([$fDate], $bParams));
} else {
    $rows = $pdo->prepare("SELECT * FROM demic_daily_reports WHERE YEAR(report_date)=? AND MONTH(report_date)=? AND $bClause ORDER BY report_date DESC");
    $rows->execute(array_merge([$fYear, $fMonth], $bParams));
}
$reports = $rows->fetchAll();

// ── Metrics helper ────────────────────────────────────────
function calcDemicMetrics(array $r): array {
    $revenue = (float)$r['sales_revenue'];
    $disc    = (float)$r['sales_discount'];
    $gross   = $revenue - $disc;

    $payables = json_decode($r['payables_expenses'] ?? '[]', true) ?: [];
    $payablesTotal = 0;
    foreach ($payables as $p) { $payablesTotal += (float)($p['amount'] ?? 0); }

    $frontline = (float)$r['frontline_medical_staff'];
    $pfFee     = (float)$r['pf_fee'];
    $mpTotal   = $frontline + $pfFee;

    $totalExp   = $payablesTotal + $mpTotal;
    $profitLoss = $gross - $totalExp;

    $cash   = (float)$r['cash'];
    $hmo    = (float)$r['hmo'];
    $charge = (float)$r['charge_to_company'];
    $drCr   = (float)$r['dr_cr'];
    $paymentTotal = $cash + $hmo + $charge + $drCr;
    $paymentMismatch = abs($paymentTotal - $revenue) > 0.01;

    $hmoWithholdPct = (float)($r['hmo_withholding_pct'] ?? 2);
    $hmoWithholding = round($hmo * ($hmoWithholdPct / 100), 2);

    $discP     = $revenue > 0 ? round(($disc        / $revenue) * 100, 1) : 0;
    $payP      = $revenue > 0 ? round(($payablesTotal/ $revenue) * 100, 1) : 0;
    $mpP       = $revenue > 0 ? round(($mpTotal      / $revenue) * 100, 1) : 0;
    $totalExpP = $revenue > 0 ? round(($totalExp     / $revenue) * 100, 1) : 0;
    $npP       = $revenue > 0 ? round(($profitLoss   / $revenue) * 100, 1) : 0;

    $quotaPassed = $revenue > (float)($r['quota_target']       ?? 0);
    $payPassed   = $payP    < (float)($r['payables_threshold'] ?? 0);
    $mpPassed    = $mpP     < (float)($r['mp_threshold']       ?? 0);

    // ── Patient / HMO stats & No. of Test breakdown (from the store sheet) ──
    $hmoApproved    = (float)($r['hmo_approved']    ?? 0);
    $patientsServed = (float)($r['patients_served'] ?? 0);
    $avgSalesPerPatient = $patientsServed > 0 ? round($revenue / $patientsServed, 2) : 0;

    $tests = [
        'Xray'          => (float)($r['test_xray']         ?? 0),
        'Laboratory'    => (float)($r['test_laboratory']   ?? 0),
        'Consultation'  => (float)($r['test_consultation'] ?? 0),
        'Ultrasound'    => (float)($r['test_ultrasound']   ?? 0),
        '2D Echo'       => (float)($r['test_2d_echo']      ?? 0),
    ];
    $testsTotal = array_sum($tests);

    return compact('gross','payablesTotal','mpTotal','totalExp','profitLoss',
                   'discP','payP','mpP','totalExpP','npP',
                   'cash','hmo','charge','drCr','paymentTotal','paymentMismatch',
                   'hmoWithholdPct','hmoWithholding',
                   'quotaPassed','payPassed','mpPassed','payables',
                   'hmoApproved','patientsServed','avgSalesPerPatient','tests','testsTotal');
}

// ── Handle Excel (.xlsx) download — either the full filtered
//    month/branch list, or a single day only (when ?only_date= and
//    ?only_id= are passed from a card's own Download button), each
//    including every payables/expenses line item and test breakdown
//    per day (not just the summary totals). ──
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $storeLabel = isBranch() ? currentBranch() : ($viewStore !== '' ? $viewStore : ($_GET['store'] ?? 'Demic Lab'));

    // Single-day export: only_id identifies the exact row (safest — a
    // store can have at most one report per date thanks to the
    // ux_date_store unique key, but id is unambiguous either way).
    $onlyId = isset($_GET['only_id']) ? (int)$_GET['only_id'] : 0;
    if ($onlyId > 0) {
        [$bClause, $bParams] = branchFilter('store_name');
        $oneStmt = $pdo->prepare("SELECT * FROM demic_daily_reports WHERE id=? AND $bClause");
        $oneStmt->execute(array_merge([$onlyId], $bParams));
        $oneRow = $oneStmt->fetch();
        $exportRows = $oneRow ? [$oneRow] : [];
        $exportLabel = $exportRows ? date('F_j_Y', strtotime($oneRow['report_date'])) : 'Report';
        $filename = str_replace(' ', '_', $exportRows ? $oneRow['store_name'] : $storeLabel) . '_DailyReport_' . $exportLabel . '.xlsx';
        $subtitle = $exportRows ? date('F j, Y', strtotime($oneRow['report_date'])) : '';
    } else {
        $exportRows = $reports;
        $filename = str_replace(' ', '_', $storeLabel) . '_DailyReport_' . $monthNames[$fMonth] . '_' . $fYear . '.xlsx';
        $subtitle = $monthNames[$fMonth] . ' ' . $fYear;
    }

    $xlsx = new SimpleXlsxWriter(branchAccentColor($storeLabel));
    $xlsx->title($storeLabel . ' — Daily Report');
    $xlsx->subtitle($subtitle);
    $xlsx->blank();

    foreach ($exportRows as $r) {
        $m = calcDemicMetrics($r);
        $payables = $m['payables'];

        $xlsx->sectionRow(date('F j, Y', strtotime($r['report_date'])) . ' — ' . $r['store_name']);
        $xlsx->header(['Particular', 'Amount']);
        $xlsx->row(['Sales Revenue', number_format((float)$r['sales_revenue'],2,'.','')]);
        $xlsx->row(['Sales Discount (SC/PWD/CORP)', number_format((float)$r['sales_discount'],2,'.','')]);
        $xlsx->row(['Gross Sales', number_format($m['gross'],2,'.','')]);
        $xlsx->blank();

        // — Payment Method breakdown —
        $xlsx->sectionRow('PAYMENT METHOD');
        $xlsx->header(['Particular', 'Amount']);
        $xlsx->row(['Cash', number_format((float)$r['cash'],2,'.','')]);
        $xlsx->row(['HMO', number_format((float)$r['hmo'],2,'.','')]);
        $xlsx->row(['Charge to Company', number_format((float)$r['charge_to_company'],2,'.','')]);
        $xlsx->row(['DR/CR', number_format((float)$r['dr_cr'],2,'.','')]);
        $xlsx->totalRow(['Total', number_format($m['paymentTotal'],2,'.','')]);
        $xlsx->row(['HMO Withholding Tax (' . rtrim(rtrim(number_format($m['hmoWithholdPct'],2),'0'),'.') . '%)', number_format($m['hmoWithholding'],2,'.','')]);
        $xlsx->blank();

        // — Payables / Expenses (every line item the branch entered) —
        $xlsx->sectionRow('PAYABLES / EXPENSES');
        $xlsx->header(['Particular', 'Amount']);
        if ($payables) {
            foreach ($payables as $p) {
                $xlsx->row([$p['label'] ?? '', number_format((float)($p['amount'] ?? 0),2,'.','')]);
            }
        }
        $xlsx->totalRow(['Total Payables', number_format($m['payablesTotal'],2,'.','')]);
        $xlsx->blank();

        // — Manpower on Duty —
        $xlsx->sectionRow('MANPOWER ON DUTY');
        $xlsx->header(['Particular', 'Amount']);
        $xlsx->row(['Frontline / Medical Staff', number_format((float)$r['frontline_medical_staff'],2,'.','')]);
        $xlsx->row(['PF Fee', number_format((float)$r['pf_fee'],2,'.','')]);
        $xlsx->totalRow(['Total Manpower', number_format($m['mpTotal'],2,'.','')]);
        $xlsx->blank();

        // — Patient / HMO Stats —
        $xlsx->sectionRow('PATIENT / HMO STATS');
        $xlsx->header(['Particular', 'Amount']);
        $xlsx->row(['Total HMO Approved', number_format($m['hmoApproved'],2,'.','')]);
        $xlsx->row(['Total Patients Served', number_format($m['patientsServed'],2,'.','')]);
        $xlsx->row(['Avg. Sales per Patient', number_format($m['avgSalesPerPatient'],2,'.','')]);
        $xlsx->blank();

        // — No. of Tests breakdown —
        $xlsx->sectionRow('NO. OF TESTS');
        $xlsx->header(['Test', 'Count']);
        foreach ($m['tests'] as $testName => $testCount) {
            $xlsx->row([$testName, number_format($testCount,2,'.','')]);
        }
        $xlsx->totalRow(['Total Tests', number_format($m['testsTotal'],2,'.','')]);
        $xlsx->blank();

        // — Thresholds used for this day's pass/fail badges —
        $xlsx->sectionRow('THRESHOLDS');
        $xlsx->header(['Particular', 'Value']);
        $xlsx->row(['Quota Target', number_format((float)$r['quota_target'],2,'.','')]);
        $xlsx->row(['Payables Threshold %', number_format((float)$r['payables_threshold'],2,'.','')]);
        $xlsx->row(['Manpower Threshold %', number_format((float)$r['mp_threshold'],2,'.','')]);
        $xlsx->blank();

        // — Day Summary —
        $xlsx->sectionRow('SUMMARY');
        $xlsx->header(['Particular', 'Amount']);
        $xlsx->row(['Total Expenses', number_format($m['totalExp'],2,'.','')]);
        $xlsx->row(['Exp % of Revenue', $m['totalExpP'] . '%']);
        $xlsx->totalRow([($m['profitLoss']>=0?'Profit':'Loss') . ' (' . $m['npP'] . '%)', number_format(abs($m['profitLoss']),2,'.','')]);
        $xlsx->blank();
        $xlsx->blank();
    }

    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([40, 18]);
    $xlsx->output($filename);
}

$pageTitle  = 'Demic Lab Daily Report';
$activePage = 'demic_daily';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Demic Lab Daily <span>Report</span></div>
    <div class="section-subtitle">Store performance — <?=$monthNames[$fMonth]?> <?=$fYear?></div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if (isManagement()): ?>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost">← Back to Dashboard</a>
    <?php endif; ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['export_csv'=>1])) ?>" class="btn btn-ghost">⬇ Download All (Month)</a>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ New Entry</button>
  </div>
</div>

<?php if ($isManagerView): ?>
<div style="margin-bottom:16px">
  <div class="vb-row-label">Viewing Store <span style="font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0">— filters the list below · click a store, or Both Stores to see everything</span></div>
  <div class="vb-grid">
    <div class="vb-card <?= $viewStore === '' ? 'active' : '' ?>" onclick="selectDemicStore('')"
         style="border-top-color:#6b7280; <?= $viewStore === '' ? 'background:#6b72800d;' : '' ?>">
      <div class="vb-card-val" style="color:#6b7280">
        <span class="vb-dot" style="background:#6b7280"></span>Both Stores
        <?php if ($viewStore === ''): ?><span class="vb-check" style="color:#6b7280">✓</span><?php endif; ?>
      </div>
      <div class="vb-card-hint"><?= $viewStore === '' ? 'Currently viewing' : 'Click to view' ?></div>
    </div>
    <?php foreach ($DEMIC_STORES as $st): $c = branchAccentColor($st); $active = $viewStore === $st; ?>
    <div class="vb-card <?= $active ? 'active' : '' ?>" onclick="selectDemicStore('<?= htmlspecialchars($st, ENT_QUOTES) ?>')"
         style="border-top-color:<?= $c ?>; <?= $active ? "background:{$c}0d;" : '' ?>">
      <div class="vb-card-val" style="color:<?= $c ?>">
        <span class="vb-dot" style="background:<?= $c ?>"></span><?= htmlspecialchars($st) ?>
        <?php if ($active): ?><span class="vb-check" style="color:<?= $c ?>">✓</span><?php endif; ?>
      </div>
      <div class="vb-card-hint"><?= $active ? 'Currently viewing' : 'Click to view' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<style>
.vb-row-label { font-family: var(--font-m); font-size: .62rem; text-transform: uppercase; letter-spacing: .07em; color: var(--subtext); margin-bottom: 8px; }
.vb-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.vb-card {
  background: var(--surface); border: 1px solid var(--border); border-top: 3px solid;
  border-radius: var(--radius); padding: 12px 14px; cursor: pointer; user-select: none;
  transition: box-shadow .15s, background .15s;
}
.vb-card:hover { box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
.vb-card.active { box-shadow: 0 0 0 2px rgba(0,0,0,.06); }
.vb-card-val { font-family: var(--font-h); font-size: .92rem; font-weight: 800; display: flex; align-items: center; gap: 7px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vb-card-hint { font-family: var(--font-m); font-size: .58rem; color: var(--subtext); margin-top: 4px; }
.vb-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(0,0,0,.04); }
.vb-check { margin-left: auto; font-size: .78rem; font-weight: 700; }
</style>
<script>
function selectDemicStore(val) {
  const url = new URL(window.location.href);
  if (val) url.searchParams.set('store', val);
  else url.searchParams.delete('store');
  window.location.href = url.toString();
}
</script>
<?php endif; ?>

<!-- Month / Year tabs -->
<div class="card" style="padding:12px 18px;margin-bottom:18px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?month=<?=$m?>&year=<?=$fYear?><?= $viewStore !== '' ? '&store='.urlencode($viewStore) : '' ?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?= ($fMonth===$m && $fDate==='') ?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>">
      <?=substr($monthNames[$m],0,3)?>
    </a>
    <?php endfor; ?>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <input type="date" id="jumpDate" class="form-control" style="padding:5px 10px;font-size:.74rem;max-width:150px"
             value="<?= htmlspecialchars($fDate) ?>"
             title="Filter the list below to a single date">
      <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value+'<?= $viewStore !== '' ? '&store='.urlencode($viewStore) : '' ?>'"
              class="form-control" style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px;
                     background:var(--surf2);border:1px solid rgba(34,211,165,.3);color:var(--accent)">
        <?php for($y=2050;$y>=2020;$y--): ?>
        <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
</div>

<?php if ($fDate !== ''): ?>
<div class="card" style="padding:10px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;
                          background:rgba(34,211,165,.05);border:1px solid rgba(34,211,165,.2)">
  <div style="font-family:var(--font-m);font-size:.74rem;color:var(--accent)">
    Filtering by <strong><?= date('F j, Y', strtotime($fDate)) ?></strong>
  </div>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?><?= $viewStore !== '' ? '&store='.urlencode($viewStore) : '' ?>" class="btn btn-ghost btn-sm">✕ Clear filter</a>
</div>
<?php endif; ?>

<!-- Records list -->
<div id="reportsListContainer">
<script type="application/json" id="reportsDataJson"><?php
  $reportsForJs = [];
  foreach ($reports as $r) { $reportsForJs[$r['id']] = $r; }
  echo json_encode($reportsForJs);
?></script>
<?php if ($reports): ?>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px">
  <?php foreach ($reports as $r):
    $m = calcDemicMetrics($r);
    $payables = $m['payables'];
  ?>
  <div class="card" style="padding:0;overflow:hidden">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 20px;
                background:var(--surf2);border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:.92rem;font-weight:700"><?=date('F j, Y',strtotime($r['report_date']))?></div>
        <span style="font-family:var(--font-m);font-size:.64rem;color:var(--subtext);
                     background:var(--bg);padding:3px 9px;border-radius:20px;border:1px solid var(--border)">
          <?=htmlspecialchars($r['store_name'])?>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
        <?php foreach([['QUOTA',$m['quotaPassed']],['COGS/PAYABLES',$m['payPassed']],['MP',$m['mpPassed']]] as [$lbl,$pass]): ?>
        <span style="font-family:var(--font-m);font-size:.62rem;padding:3px 9px;border-radius:20px;
              background:<?=$pass?'rgba(34,211,165,.12)':'rgba(248,113,113,.12)'?>;
              color:<?=$pass?'var(--accent)':'var(--accent2)'?>;
              border:1px solid <?=$pass?'rgba(34,211,165,.2)':'rgba(248,113,113,.2)'?>">
          <?=$lbl?> <?=$pass?'✓ PASSED':'✗ FAILED'?>
        </span>
        <?php endforeach; ?>
        <?php if ($m['paymentMismatch']): ?>
        <span style="font-family:var(--font-m);font-size:.62rem;padding:3px 9px;border-radius:20px;
              background:rgba(251,191,36,.12);color:var(--accent3);border:1px solid rgba(251,191,36,.2)">
          ⚠ PAYMENTS MISMATCH
        </span>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.location='?<?= http_build_query(array_merge($_GET, ['export_csv'=>1,'only_id'=>$r['id']])) ?>'">⬇ Download</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="openEditModal(<?=$r['id']?>)">Edit</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="deleteReport(<?=$r['id']?>)">Del</button>
      </div>
    </div>

    <!-- Top 5 metrics -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);border-bottom:1px solid var(--border)">
      <?php
        $cells = [
          ['Sales Revenue',  '₱'.number_format($r['sales_revenue'],2),    'var(--text)',   null],
          ['Sales Discount', '₱'.number_format($r['sales_discount'],2),   'var(--accent2)',$m['discP'].'% of revenue'],
          ['Gross Sales',    '₱'.number_format($m['gross'],2),            'var(--accent2)',null],
          ['Total Expenses', '₱'.number_format($m['totalExp'],2),         'var(--accent3)',$m['totalExpP'].'% of revenue'],
          ['Profit / Loss',  ($m['profitLoss']<0?'−':'').'₱'.number_format(abs($m['profitLoss']),2),
                             $m['profitLoss']>=0?'var(--accent)':'var(--accent2)',
                             $m['npP'].'% of revenue'],
        ];
        foreach ($cells as $i=>[$lbl,$val,$clr,$sub]):
      ?>
      <div style="padding:13px 16px;<?=$i<4?'border-right:1px solid var(--border);':''?>
                  <?=$i===4?($m['profitLoss']>=0?'background:rgba(34,211,165,.03)':'background:rgba(248,113,113,.03)'):''?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px"><?=$lbl?></div>
        <div style="font-size:1rem;font-weight:700;color:<?=$clr?>"><?=$val?></div>
        <?php if($sub): ?><div style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext);margin-top:2px"><?=$sub?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Detail breakdown -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr">
      <!-- Payables -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;
                    color:var(--subtext);margin-bottom:7px">
          PAYABLES &nbsp;<span style="color:<?=$m['payPassed']?'var(--accent)':'var(--accent2)'?>"><?=$m['payP']?>%</span>
        </div>
        <?php if ($payables): foreach($payables as $p): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=htmlspecialchars($p['label'])?></span><span>₱<?=number_format($p['amount'],2)?></span>
        </div>
        <?php endforeach; else: ?>
        <div style="font-family:var(--font-m);font-size:.7rem;color:var(--subtext)">No payables listed</div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total</span><span>₱<?=number_format($m['payablesTotal'],2)?></span>
        </div>
      </div>

      <!-- Manpower -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          MANPOWER ON DUTY &nbsp;<span style="color:<?=$m['mpPassed']?'var(--accent)':'var(--accent2)'?>"><?=$m['mpP']?>%</span>
        </div>
        <?php foreach([['Frontline / Medical Staff',$r['frontline_medical_staff']],['PF Fee',$r['pf_fee']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span>₱<?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total MP</span><span>₱<?=number_format($m['mpTotal'],2)?></span>
        </div>
      </div>

      <!-- Payment breakdown -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          Payment Method
        </div>
        <?php foreach([['Cash',$m['cash']],['HMO',$m['hmo']],['Charge to Company',$m['charge']],['DR/CR',$m['drCr']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span>₱<?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border);
                    <?=$m['paymentMismatch']?'color:var(--accent2)':''?>">
          <span>Total</span><span>₱<?=number_format($m['paymentTotal'],2)?></span>
        </div>
        <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:4px">
          HMO WH Tax (<?=rtrim(rtrim(number_format($m['hmoWithholdPct'],2),'0'),'.')?>%): ₱<?=number_format($m['hmoWithholding'],2)?>
        </div>
      </div>

      <!-- Summary -->
      <div style="padding:12px 16px;background:<?=$m['profitLoss']>=0?'rgba(34,211,165,.03)':'rgba(248,113,113,.03)'?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">Summary</div>
        <?php foreach([['Total Expenses','₱'.number_format($m['totalExp'],2)],['Exp % of Revenue',$m['totalExpP'].'%']] as [$n,$v]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.74rem;font-weight:700;margin-top:6px;padding-top:5px;border-top:1px solid var(--border)">
          <span style="color:<?=$m['profitLoss']>=0?'var(--accent)':'var(--accent2)'?>">
            <?=$m['profitLoss']>=0?'PROFIT':'LOSS'?> <?=$m['npP']?>%
          </span>
          <span style="color:<?=$m['profitLoss']>=0?'var(--accent)':'var(--accent2)'?>">
            ₱<?=number_format(abs($m['profitLoss']),2)?>
          </span>
        </div>
      </div>
    </div>

    <!-- Patient / HMO stats & No. of Test -->
    <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid var(--border)">
      <!-- Patient / HMO Stats -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          Patient / HMO Stats
        </div>
        <?php foreach([['Total HMO Approved',$m['hmoApproved']],['Total Patients Serve',$m['patientsServed']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span><?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Average Sales / Patient</span><span>₱<?=number_format($m['avgSalesPerPatient'],2)?></span>
        </div>
      </div>

      <!-- No. of Test -->
      <div style="padding:12px 16px">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          No. of Test
        </div>
        <?php foreach($m['tests'] as $n=>$a): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?=$n?></span><span><?=number_format($a,2)?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total Tests</span><span><?=number_format($m['testsTotal'],2)?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:2rem;margin-bottom:12px;opacity:.3">📋</div>
  <div style="font-family:var(--font-m);font-size:.8rem;color:var(--subtext)">
    <?php if ($fDate !== ''): ?>
      No reports for <?= date('F j, Y', strtotime($fDate)) ?>
    <?php else: ?>
      No reports for <?=$monthNames[$fMonth]?> <?=$fYear?>
    <?php endif; ?>
  </div>
  <?php if ($fDate !== ''): ?>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?><?= $viewStore !== '' ? '&store='.urlencode($viewStore) : '' ?>" class="btn btn-ghost" style="margin-top:16px">✕ Clear filter</a>
  <?php endif; ?>
  <button class="btn btn-primary" style="margin-top:16px" onclick="openModal('addModal')">+ Add First Entry</button>
</div>
<?php endif; ?>
</div><!-- /reportsListContainer -->

<script>
// Date filter — narrows the list above to just that one date's report(s)
// (or an empty state if nothing's saved for it), same as the main Daily
// Report page and Commissary's OIC Daily Report.
document.getElementById('jumpDate').addEventListener('change', function() {
  const val = this.value;
  if (!val) return;
  <?php if ($viewStore !== ''): ?>
  window.location = '?date=' + val + '&store=<?= urlencode($viewStore) ?>';
  <?php else: ?>
  window.location = '?date=' + val;
  <?php endif; ?>
});
</script>

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-title">📋 New Demic Lab Report</div>
    <form id="addForm" onsubmit="return submitDailyReport(event,'addForm')">
      <input type="hidden" name="action" value="save">
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group">
          <label>Date *</label>
          <input name="report_date" type="date" class="form-control" value="<?=date('Y-m-d')?>" required>
        </div>
        <div class="form-group">
          <label>Store Name</label>
          <?php if (isBranch()): ?>
          <input name="store_name" class="form-control" value="<?= htmlspecialchars(currentBranch()) ?>" readonly
                 style="opacity:.6;cursor:not-allowed">
          <?php else: ?>
          <input name="store_name" class="form-control" value="Demic Lab">
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group">
          <label>Sales Discount (SC/PWD/CORP) (₱)</label>
          <input name="sales_discount" type="number" step="0.01" min="0" class="form-control sr-discount" placeholder="0.00" value="" oninput="calcSalesSummary(this)">
        </div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🧾 Payables</div>
      <div id="payAdd" class="dr-dyn-container"></div>
      <button type="button" class="dr-add-btn" onclick="addPayRow('payAdd')">＋ Add Payable Row</button>

      <div class="dr-section-hdr" style="--sc:#0b7cb8;margin-top:18px">👥 Manpower on Duty</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Frontline / Medical Staff (₱)</label><input name="frontline_medical_staff" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>PF Fee (IT, Patho, Doctor, Xray, ECG, 2D Echo, Ultrasound) (₱)</label><input name="pf_fee" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">💳 Payment Method Breakdown</div>
      <div class="form-row">
        <div class="form-group"><label>Cash (₱)</label><input name="cash" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value="" oninput="calcSalesSummary(this)"></div>
        <div class="form-group"><label>HMO (₱)</label><input name="hmo" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value="" oninput="calcSalesSummary(this)"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Charge to Company (₱)</label><input name="charge_to_company" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value="" oninput="calcSalesSummary(this)"></div>
        <div class="form-group"><label>DR/CR (₱)</label><input name="dr_cr" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value="" oninput="calcSalesSummary(this)"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>HMO Withholding Tax (%)</label><input name="hmo_withholding_pct" type="number" step="0.01" min="0" max="100" class="form-control" value="2" oninput="calcSalesSummary(this)"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">💰 Sales Summary</div>
      <div class="form-row" style="margin-bottom:18px">
        <div class="form-group">
          <label>Sales Revenue / POS Reading (₱) <span style="opacity:.6;font-weight:400">(auto)</span></label>
          <input type="number" class="form-control sr-revenue" value="0.00" readonly tabindex="-1" style="background:var(--surf3);color:var(--subtext2);font-weight:600">
        </div>
        <div class="form-group">
          <label>Gross Sales (₱) <span style="opacity:.6;font-weight:400">(auto)</span></label>
          <input type="number" class="form-control sr-gross" value="0.00" readonly tabindex="-1" style="background:var(--surf3);color:var(--accent);font-weight:700">
        </div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">⚙ Thresholds</div>
      <div class="form-row">
        <div class="form-group"><label>Quota Target (₱)</label><input name="quota_target" type="number" step="0.01" min="0" class="form-control" value="50000"></div>
        <div class="form-group"><label>COGS/Payables Max (%)</label><input name="payables_threshold" type="number" step="0.1" min="0" max="100" class="form-control" value="45"></div>
      </div>
      <div class="form-row" style="margin-bottom:18px">
        <div class="form-group"><label>MP Max (%)</label><input name="mp_threshold" type="number" step="0.1" min="0" max="100" class="form-control" value="10"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🩺 Patient / HMO Stats</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Total HMO Approved</label><input name="hmo_approved" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Total Patients Serve</label><input name="patients_served" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🧪 No. of Test</div>
      <div class="form-row">
        <div class="form-group"><label>Xray</label><input name="test_xray" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Laboratory</label><input name="test_laboratory" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Consultation</label><input name="test_consultation" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Ultrasound</label><input name="test_ultrasound" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>2D Echo</label><input name="test_2d_echo" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Report</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL (static template, populated via JS — no page reload) ══ -->
<div class="modal-overlay" id="editModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-title" id="editModalTitle">✏️ Edit Report</div>
    <form id="editForm" onsubmit="return submitDailyReport(event,'editForm')">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Date *</label><input name="report_date" id="edit_report_date" type="date" class="form-control" required></div>
        <div class="form-group"><label>Store Name</label>
          <?php if (isBranch()): ?>
          <input name="store_name" class="form-control" value="<?= htmlspecialchars(currentBranch()) ?>" readonly
                 style="opacity:.6;cursor:not-allowed">
          <?php else: ?>
          <input name="store_name" id="edit_store_name" class="form-control">
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group">
          <label>Sales Discount (SC/PWD/CORP) (₱)</label>
          <input name="sales_discount" id="edit_sales_discount" type="number" step="0.01" min="0" class="form-control sr-discount" oninput="calcSalesSummary(this)">
        </div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🧾 Payables</div>
      <div id="payEdit" class="dr-dyn-container"></div>
      <button type="button" class="dr-add-btn" onclick="addPayRow('payEdit')">＋ Add Payable Row</button>

      <div class="dr-section-hdr" style="--sc:#0b7cb8;margin-top:18px">👥 Manpower on Duty</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Frontline / Medical Staff (₱)</label><input name="frontline_medical_staff" id="edit_frontline_medical_staff" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>PF Fee (IT, Patho, Doctor, Xray, ECG, 2D Echo, Ultrasound) (₱)</label><input name="pf_fee" id="edit_pf_fee" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">💳 Payment Method Breakdown</div>
      <div class="form-row">
        <div class="form-group"><label>Cash (₱)</label><input name="cash" id="edit_cash" type="number" step="0.01" min="0" class="form-control" oninput="calcSalesSummary(this)"></div>
        <div class="form-group"><label>HMO (₱)</label><input name="hmo" id="edit_hmo" type="number" step="0.01" min="0" class="form-control" oninput="calcSalesSummary(this)"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Charge to Company (₱)</label><input name="charge_to_company" id="edit_charge_to_company" type="number" step="0.01" min="0" class="form-control" oninput="calcSalesSummary(this)"></div>
        <div class="form-group"><label>DR/CR (₱)</label><input name="dr_cr" id="edit_dr_cr" type="number" step="0.01" min="0" class="form-control" oninput="calcSalesSummary(this)"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>HMO Withholding Tax (%)</label><input name="hmo_withholding_pct" id="edit_hmo_withholding_pct" type="number" step="0.01" min="0" max="100" class="form-control" oninput="calcSalesSummary(this)"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">💰 Sales Summary</div>
      <div class="form-row" style="margin-bottom:18px">
        <div class="form-group">
          <label>Sales Revenue / POS Reading (₱) <span style="opacity:.6;font-weight:400">(auto)</span></label>
          <input type="number" class="form-control sr-revenue" value="0.00" readonly tabindex="-1" style="background:var(--surf3);color:var(--subtext2);font-weight:600">
        </div>
        <div class="form-group">
          <label>Gross Sales (₱) <span style="opacity:.6;font-weight:400">(auto)</span></label>
          <input type="number" class="form-control sr-gross" value="0.00" readonly tabindex="-1" style="background:var(--surf3);color:var(--accent);font-weight:700">
        </div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">⚙ Thresholds</div>
      <div class="form-row">
        <div class="form-group"><label>Quota Target (₱)</label><input name="quota_target" id="edit_quota_target" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>COGS/Payables Max (%)</label><input name="payables_threshold" id="edit_payables_threshold" type="number" step="0.1" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:18px">
        <div class="form-group"><label>MP Max (%)</label><input name="mp_threshold" id="edit_mp_threshold" type="number" step="0.1" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🩺 Patient / HMO Stats</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Total HMO Approved</label><input name="hmo_approved" id="edit_hmo_approved" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Total Patients Serve</label><input name="patients_served" id="edit_patients_served" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#0b7cb8">🧪 No. of Test</div>
      <div class="form-row">
        <div class="form-group"><label>Xray</label><input name="test_xray" id="edit_test_xray" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Laboratory</label><input name="test_laboratory" id="edit_test_laboratory" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Consultation</label><input name="test_consultation" id="edit_test_consultation" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Ultrasound</label><input name="test_ultrasound" id="edit_test_ultrasound" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>2D Echo</label><input name="test_2d_echo" id="edit_test_2d_echo" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

  </div></div>

<style>
/* Section headers in modal */
.dr-section-hdr {
  font-family: var(--font-m); font-size: .62rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .1em;
  color: var(--sc, var(--subtext));
  margin-bottom: 10px; padding-bottom: 7px;
  border-bottom: 1px solid var(--border);
}
.dr-amt { text-align: right; }
/* Dynamic rows (label + amount only, no type selector) */
.dr-dyn-container { display: flex; flex-direction: column; gap: 5px; margin-top: 5px; }
.dr-dyn-row-2 {
  display: grid; grid-template-columns: 1fr 130px 30px;
  gap: 8px; align-items: center;
}
.dr-remove-btn {
  width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0;
  border: 1px solid rgba(248,113,113,.2); background: rgba(248,113,113,.08);
  color: var(--accent2); cursor: pointer; font-size: .85rem;
  display: flex; align-items: center; justify-content: center;
}
.dr-remove-btn:hover { background: rgba(248,113,113,.2); }
/* Add button */
.dr-add-btn {
  margin-top: 8px; width: 100%; padding: 8px;
  background: rgba(34,211,165,.05); border: 1px dashed rgba(34,211,165,.25);
  color: var(--accent); border-radius: 8px; cursor: pointer;
  font-family: var(--font-m); font-size: .74rem; letter-spacing: .02em;
  transition: background .15s; display: flex; align-items: center; justify-content: center; gap: 6px;
}
.dr-add-btn:hover { background: rgba(34,211,165,.1); }

#addModal.modal-overlay, #editModal.modal-overlay {
  background: rgba(120, 175, 220, .4);
  backdrop-filter: blur(6px);
}

/* ── Light-blue theme, scoped to this page's Add/Edit report modals ── */
#addModal .modal, #editModal .modal {
  background: #dcedfa;
  border: 1px solid #94c9ee;
}
#addModal .modal-title, #editModal .modal-title {
  border-bottom-color: #94c9ee;
  color: #0c5a8a;
}
#addModal .form-control, #editModal .form-control {
  background: #fff; border: 1px solid #94c9ee;
}
#addModal .form-control:focus, #editModal .form-control:focus {
  border-color: #0b7cb8;
  box-shadow: 0 0 0 3px rgba(11,124,184,.15);
}
#addModal .form-control::placeholder, #editModal .form-control::placeholder {
  color: #4f95c4; opacity: .8;
}
#addModal .form-control[readonly], #editModal .form-control[readonly] {
  background: #c7e3f6 !important; color: #0c5a8a !important;
}
#addModal .form-group label, #editModal .form-group label {
  color: #2e5f85;
}
#addModal .modal-actions, #editModal .modal-actions {
  border-top-color: #94c9ee;
}
#addModal .dr-add-btn, #editModal .dr-add-btn {
  background: rgba(11,124,184,.08); border: 1px dashed rgba(11,124,184,.35); color: #0b7cb8;
}
#addModal .dr-add-btn:hover, #editModal .dr-add-btn:hover {
  background: rgba(11,124,184,.15);
}
</style>

<script>
function openModal(id){
  document.getElementById(id).classList.add('open');
  // New Entry modal: seed one blank Payables row automatically so it's
  // never silently left empty (which would understate Total Expenses
  // and overstate Profit) — matches the always-visible Payables row
  // on the Excel sheet.
  if (id === 'addModal') {
    const f = document.getElementById('addForm');
    if (f) f.reset();
    const payAdd = document.getElementById('payAdd');
    if (payAdd) {
      payAdd.innerHTML = '';
      addPayRow('payAdd');
    }
    const rev = document.querySelector('#addModal .sr-revenue');
    const gro = document.querySelector('#addModal .sr-gross');
    if (rev) rev.value = '0.00';
    if (gro) gro.value = '0.00';
  }
}
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => {
  if (e.target === o) o.classList.remove('open');
}));

// Sales Revenue = Cash + HMO + Charge to Company + DR/CR
// Sales Discount (SC/PWD/CORP) is entered directly by the user.
// Gross Sales = Sales Revenue - Sales Discount
function calcSalesSummary(fromEl) {
  const form = fromEl.closest('form');
  const gv = name => parseFloat(form.querySelector(`[name="${name}"]`)?.value) || 0;
  const cash     = gv('cash');
  const hmo      = gv('hmo');
  const charge   = gv('charge_to_company');
  const drcr     = gv('dr_cr');
  const discount = gv('sales_discount');

  const revenue = cash + hmo + charge + drcr;
  const gross   = revenue - discount;

  form.querySelector('.sr-revenue').value = revenue.toFixed(2);
  form.querySelector('.sr-gross').value   = gross.toFixed(2);
}
// Compute once on load for the Edit modal (in case values were pre-filled server-side but need re-derivation)
document.querySelectorAll('#editModal form, #addModal form').forEach(f => {
  const cashField = f.querySelector('[name="cash"]');
  if (cashField) calcSalesSummary(cashField);
});

function addPayRow(containerId) {
  const container = document.getElementById(containerId);
  const row = document.createElement('div');
  row.className = 'dr-dyn-row-2';
  row.innerHTML = `
    <input name="pay_label[]" type="text" class="form-control" placeholder="Payable name…">
    <input name="pay_amount[]" type="number" step="0.01" min="0" class="form-control dr-amt" placeholder="0.00" value="">
    <button type="button" class="dr-remove-btn" onclick="this.closest('.dr-dyn-row-2').remove()">✕</button>
  `;
  container.appendChild(row);
  row.querySelector('input[name="pay_label[]"]').focus();
}

function addPayRowFilled(containerId, label, amount) {
  const container = document.getElementById(containerId);
  const row = document.createElement('div');
  row.className = 'dr-dyn-row-2';
  row.innerHTML = `
    <input name="pay_label[]" type="text" class="form-control" placeholder="Payable name…" value="${(label||'').replace(/"/g,'&quot;')}">
    <input name="pay_amount[]" type="number" step="0.01" min="0" class="form-control dr-amt" placeholder="0.00" value="${(+amount||0).toFixed(2)}">
    <button type="button" class="dr-remove-btn" onclick="this.closest('.dr-dyn-row-2').remove()">✕</button>
  `;
  container.appendChild(row);
}

// ── Load report data embedded in the page (no server round trip needed) ──
let reportsData = {};
(function loadReportsData(){
  const el = document.getElementById('reportsDataJson');
  reportsData = el ? JSON.parse(el.textContent || '{}') : {};
})();

function openEditModal(id) {
  const d = reportsData[id];
  if (!d) return;
  document.getElementById('edit_id').value = d.id;
  document.getElementById('editModalTitle').textContent =
    '✏️ Edit — ' + new Date(d.report_date + 'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
  document.getElementById('edit_report_date').value = d.report_date;
  const storeEl = document.getElementById('edit_store_name');
  if (storeEl) storeEl.value = d.store_name;
  document.getElementById('edit_sales_discount').value        = d.sales_discount;
  document.getElementById('edit_frontline_medical_staff').value = d.frontline_medical_staff;
  document.getElementById('edit_pf_fee').value                = d.pf_fee;
  document.getElementById('edit_cash').value                  = d.cash;
  document.getElementById('edit_hmo').value                   = d.hmo;
  document.getElementById('edit_charge_to_company').value     = d.charge_to_company;
  document.getElementById('edit_dr_cr').value                 = d.dr_cr;
  document.getElementById('edit_hmo_withholding_pct').value   = d.hmo_withholding_pct;
  document.getElementById('edit_quota_target').value          = d.quota_target;
  document.getElementById('edit_payables_threshold').value    = d.payables_threshold;
  document.getElementById('edit_mp_threshold').value          = d.mp_threshold;
  document.getElementById('edit_hmo_approved').value          = d.hmo_approved;
  document.getElementById('edit_patients_served').value       = d.patients_served;
  document.getElementById('edit_test_xray').value             = d.test_xray;
  document.getElementById('edit_test_laboratory').value       = d.test_laboratory;
  document.getElementById('edit_test_consultation').value     = d.test_consultation;
  document.getElementById('edit_test_ultrasound').value       = d.test_ultrasound;
  document.getElementById('edit_test_2d_echo').value          = d.test_2d_echo;

  const payEdit = document.getElementById('payEdit');
  payEdit.innerHTML = '';
  let payables = [];
  try { payables = JSON.parse(d.payables_expenses || '[]') || []; } catch(e) { payables = []; }
  payables.forEach(p => addPayRowFilled('payEdit', p.label, p.amount));

  openModal('editModal');
  const cashField = document.getElementById('edit_cash');
  if (cashField) calcSalesSummary(cashField);
}

function showToast(msg, isError) {
  let t = document.getElementById('drToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'drToast';
    t.style.cssText = 'position:fixed;top:18px;right:18px;z-index:9999;padding:11px 18px;border-radius:8px;'
      + 'font-family:var(--font-m);font-size:.78rem;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.25);'
      + 'transition:opacity .25s,transform .25s;opacity:0;transform:translateY(-8px)';
    document.body.appendChild(t);
  }
  t.style.background = isError ? 'rgba(248,113,113,.95)' : 'rgba(34,211,165,.95)';
  t.style.color = isError ? '#3a0a0a' : '#031a12';
  t.textContent = msg;
  requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateY(0)'; });
  clearTimeout(t._hideTimer);
  t._hideTimer = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateY(-8px)'; }, 2600);
}

// ── Refresh just the records list + reportsData, no page navigation ──
function refreshReportsList() {
  return fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.text())
    .then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const fresh = doc.getElementById('reportsListContainer');
      const current = document.getElementById('reportsListContainer');
      if (fresh && current) current.innerHTML = fresh.innerHTML;
      const el = document.getElementById('reportsDataJson');
      reportsData = el ? JSON.parse(el.textContent || '{}') : {};
    })
    .catch(() => {});
}

function submitDailyReport(evt, formId) {
  evt.preventDefault();
  const form = document.getElementById(formId);
  const fd = new FormData(form);
  fd.set('ajax', '1');
  const btn = form.querySelector('button[type="submit"]');
  const origText = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  fetch('demic_daily_report.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) {
        closeModal(formId === 'addForm' ? 'addModal' : 'editModal');
        showToast(formId === 'addForm' ? 'Report saved.' : 'Report updated.');
        refreshReportsList();
      } else {
        showToast((data && data.msg) || 'Save failed.', true);
      }
    })
    .catch(() => showToast('Save failed — check your connection.', true))
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = origText; } });

  return false;
}

function deleteReport(id) {
  if (!confirm('Delete this report?')) return;
  const fd = new FormData();
  fd.set('action', 'delete');
  fd.set('id', id);
  fd.set('ajax', '1');
  fetch('demic_daily_report.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) { showToast('Report deleted.'); refreshReportsList(); }
      else { showToast((data && data.msg) || 'Delete failed.', true); }
    })
    .catch(() => showToast('Delete failed — check your connection.', true));
}
</script>
</body>
</html>