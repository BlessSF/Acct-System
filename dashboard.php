<?php
// ============================================================
//  dashboard.php — KPIs + Daily Profit/Loss filter
// ============================================================
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$pdo = getPDO();

// ── Live Gross Sales source ────────────────────────────────
// Stella / Dois / Pub Express / H / H Carwash / Recovery / Commissary /
// DemicLab-Main / DemicLab-Jaro all feed the Dashboard straight from
// what's Saved on each branch's own Sales/OIC Report page (see
// lib/branch_sales.php) instead of the old shared `daily_reports`
// table. H Carwash is Hero's carwash operation — it has its own
// report (h_carwash_sales_report.php) and shows up as its own
// separate branch card here, distinct from H's regular retail sales.
// Commissary's figures come from its OIC Daily Report
// (commissary_oic_report.php).
require_once 'lib/branch_sales.php';
$SALES_UNION = branchSalesUnionTable();
require_once 'lib/branch_colors.php';

// Branch isolation: branch accounts only see their own store's data
[$bClause, $bParams] = branchFilter('store_name');
$bAnd = $bClause === '1=1' ? '' : " AND $bClause";   // e.g. " AND `store_name` = ?"
$bVal = $bParams[0] ?? null;  // null means no filter (management)

// ── Management "view as branch" selector ────────────────────
// Manager / Accounting Head aren't tied to one store, so the
// single-store sections below (Today snapshot, Monthly Summary,
// Recent Reports) need to know which branch to show. Branch
// accounts always see their own store; management picks one via
// the dropdown rendered near the Today card (?view_branch=...).
// Keep this list in sync with the $BRANCHES array in index.php.
$ALL_BRANCH_LABELS = ['Stella','Dois','H','H Carwash','Pub Express','Commissary','Recovery','DemicLab-Main','DemicLab-Jaro'];
if (isBranch()) {
    $viewBranch = currentBranch();
} else {
    $selBranch  = trim($_GET['view_branch'] ?? '');
    $viewBranch = in_array($selBranch, $ALL_BRANCH_LABELS, true) ? $selBranch : $ALL_BRANCH_LABELS[0];
}

// ── Dashboard filter params ────────────────────────────────
$dashView = in_array($_GET['dview']??'',['daily','monthly','yearly']) ? $_GET['dview'] : 'monthly';
$dYear    = (int)($_GET['dyear']  ?? date('Y'));
$dMonth   = (int)($_GET['dmonth'] ?? date('n'));
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── KPI Card filter params ─────────────────────────────────
$kpiDate      = $_GET['kpi_date']  ?? date('Y-m-d');   // for "Today's Gross Sales" card
$kpiMonth     = (int)($_GET['kpi_month'] ?? date('n'));
$kpiYear      = (int)($_GET['kpi_year']  ?? date('Y'));

// ── Global KPIs (always full-year) ────────────────────────
$ky = date('Y');

// ── Daily Reports KPIs ────────────────────────────────────
// Same OIC maths as the Daily Report page (lib/branch_sales.php → oicCalcMetrics):
// percentages are a share of Gross Sales, as in the OIC Excel sheet.
function calcMetrics(array $r): array {
    return oicCalcMetrics($r);
}

// Monthly daily-report summary
$drMonth = (int)($_GET['drmonth'] ?? date('n'));
$drYear  = (int)($_GET['dryear']  ?? date('Y'));

$drRows = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=? AND store_name = COALESCE(?, store_name) ORDER BY report_date DESC");
$drRows->execute([$drYear, $drMonth, $viewBranch]);
$drReports = $drRows->fetchAll();

// Aggregate for the selected month
$drTotals = ['gross_sales'=>0,'sales_discount'=>0,'gulay_commissary'=>0,'direct_purchases'=>0,
             'other_expenses'=>0,'kitchen_manpower'=>0,'frontline_manpower'=>0,'overtime'=>0,'undertime'=>0,
             'quota_target'=>9000,'cogs_threshold'=>30,'mp_threshold'=>35,'np_threshold'=>20];
foreach ($drReports as $row) {
    foreach (['gross_sales','sales_discount','gulay_commissary','direct_purchases','other_expenses',
              'kitchen_manpower','frontline_manpower','overtime','undertime'] as $k) {
        $drTotals[$k] += (float)$row[$k];
    }
}
$drM = calcMetrics($drTotals);

// Today's report
$today = date('Y-m-d');
$todayReport = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE report_date=? AND store_name = COALESCE(?, store_name)");
$todayReport->execute([$today, $viewBranch]);
$todayReport = $todayReport->fetch();
$todayM = $todayReport ? calcMetrics($todayReport) : null;

// Recent daily reports (last 7)
$recentDR = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE store_name = COALESCE(?, store_name) ORDER BY report_date DESC LIMIT 7");
$recentDR->execute([$viewBranch]);
$recentDR = $recentDR->fetchAll();

// ── Chart data based on dashView ──────────────────────────
$chartLabels = []; $chartSales = []; $chartExp = []; $chartProfit = [];

if ($dashView === 'daily') {
    $dmax = cal_days_in_month(CAL_GREGORIAN, $dMonth, $dYear);
    $sRows = $pdo->prepare("SELECT DAY(report_date) d, COALESCE(SUM(gross_sales),0) t FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=?$bAnd GROUP BY d");
    $sRows->execute(array_values(array_filter([$dYear,$dMonth,$bVal], fn($v)=>$v!==null))); $sMap=[];
    foreach ($sRows->fetchAll() as $r) $sMap[$r['d']] = (float)$r['t'];
    $eRows = $pdo->prepare("SELECT DAY(report_date) d, COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) t FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=?$bAnd GROUP BY d");
    $eRows->execute(array_values(array_filter([$dYear,$dMonth,$bVal], fn($v)=>$v!==null))); $eMap=[];
    foreach ($eRows->fetchAll() as $r) $eMap[$r['d']] = (float)$r['t'];
    for ($d=1; $d<=$dmax; $d++) {
        $s=$sMap[$d]??0; $e=$eMap[$d]??0;
        $chartLabels[]=$d; $chartSales[]=$s; $chartExp[]=$e; $chartProfit[]=round($s-$e,2);
    }
} elseif ($dashView === 'monthly') {
    $sRows = $pdo->prepare("SELECT MONTH(report_date) m, COALESCE(SUM(gross_sales),0) t FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd GROUP BY m");
    $sRows->execute(array_values(array_filter([$dYear,$bVal], fn($v)=>$v!==null))); $sMap=[];
    foreach ($sRows->fetchAll() as $r) $sMap[$r['m']] = (float)$r['t'];
    $eRows = $pdo->prepare("SELECT MONTH(report_date) m, COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) t FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd GROUP BY m");
    $eRows->execute(array_values(array_filter([$dYear,$bVal], fn($v)=>$v!==null))); $eMap=[];
    foreach ($eRows->fetchAll() as $r) $eMap[$r['m']] = (float)$r['t'];
    $mShort=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    for ($m=1;$m<=12;$m++) {
        $s=$sMap[$m]??0; $e=$eMap[$m]??0;
        $chartLabels[]=$mShort[$m-1]; $chartSales[]=$s; $chartExp[]=$e; $chartProfit[]=round($s-$e,2);
    }
} else {
    for ($y=2020;$y<=2050;$y++) {
        $sq=$pdo->prepare("SELECT COALESCE(SUM(gross_sales),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
        $sq->execute(array_filter([$y,$bVal],fn($v)=>$v!==null)); $s=(float)$sq->fetchColumn();
        $eq=$pdo->prepare("SELECT COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
        $eq->execute(array_filter([$y,$bVal],fn($v)=>$v!==null)); $e=(float)$eq->fetchColumn();
        $chartLabels[]=$y; $chartSales[]=$s; $chartExp[]=$e; $chartProfit[]=round($s-$e,2);
    }
}

$periodSales  = array_sum($chartSales);
$periodExp    = array_sum($chartExp);
$periodProfit = $periodSales - $periodExp;
$periodDays   = count(array_filter($chartProfit, fn($v)=>$v>0));
$lossDays     = count(array_filter($chartProfit, fn($v)=>$v<0));
$bestIdx      = !empty($chartProfit) ? array_search(max($chartProfit), $chartProfit) : 0;
$worstIdx     = !empty($chartProfit) ? array_search(min($chartProfit), $chartProfit) : 0;

// Expense breakdown from daily reports
$expQ = fn($col) => (float)(function() use ($pdo,$ky,$bAnd,$bVal,$col,$SALES_UNION){
    $q=$pdo->prepare("SELECT COALESCE(SUM($col),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
    $q->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null)); return $q->fetchColumn();
})();
$cat_data = [
    ['category'=>'COGS',      'total'=> $expQ('gulay_commissary+direct_purchases')],
    ['category'=>'Manpower',  'total'=> $expQ('kitchen_manpower+frontline_manpower+overtime+undertime')],
    ['category'=>'Other Exp', 'total'=> $expQ('other_expenses')],
];

// ── Branch Performance breakdown (Manager / Accounting view only) ──
// Branch accounts only ever see their own store, so this section is
// meaningless (and would leak other branches') to them — skip entirely.
$isManagerView = !isBranch();
$branchPerf          = [];
$knownBranches       = [];
$missingTodayLabels  = [];
$grandToday = $grandMonth = $grandFyGs = $grandFyNp = 0.0;
if ($isManagerView) {
    // Today's gross sales, per branch
    $todayByBranch = [];
    $q = $pdo->prepare("SELECT store_name, gross_sales FROM $SALES_UNION WHERE report_date = ?");
    $q->execute([$today]);
    foreach ($q->fetchAll() as $r) $todayByBranch[$r['store_name']] = (float)$r['gross_sales'];

    // Selected month's gross sales, per branch (reuses the Monthly Gross Sales KPI filter above)
    $monthByBranch = [];
    $q = $pdo->prepare("SELECT store_name, COALESCE(SUM(gross_sales),0) t FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=? GROUP BY store_name");
    $q->execute([$kpiYear, $kpiMonth]);
    foreach ($q->fetchAll() as $r) $monthByBranch[$r['store_name']] = (float)$r['t'];

    // Full-year totals, per branch: gross sales, discounts, expenses, days recorded
    $fyByBranch = [];
    $q = $pdo->prepare("SELECT store_name,
            COALESCE(SUM(gross_sales),0) gross_sales,
            COALESCE(SUM(sales_discount),0) sales_discount,
            COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) total_exp,
            COUNT(*) days
        FROM $SALES_UNION WHERE YEAR(report_date)=? GROUP BY store_name");
    $q->execute([$ky]);
    foreach ($q->fetchAll() as $r) $fyByBranch[$r['store_name']] = $r;

    // Also pick up any store_name that appears in the data but isn't in our known label list
    // (e.g. a new branch added to daily_reports before index.php was updated)
    $knownBranches = $ALL_BRANCH_LABELS;
    foreach (array_merge(array_keys($todayByBranch), array_keys($monthByBranch), array_keys($fyByBranch)) as $sn) {
        if ($sn !== '' && !in_array($sn, $knownBranches, true)) $knownBranches[] = $sn;
    }

    // Previous month's gross sales, per branch (for the "vs Last Month" trend column)
    $prevMonth = $kpiMonth - 1; $prevYear = $kpiYear;
    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
    $prevMonthByBranch = [];
    $q = $pdo->prepare("SELECT store_name, COALESCE(SUM(gross_sales),0) t FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=? GROUP BY store_name");
    $q->execute([$prevYear, $prevMonth]);
    foreach ($q->fetchAll() as $r) $prevMonthByBranch[$r['store_name']] = (float)$r['t'];

    foreach ($knownBranches as $label) {
        $fy    = $fyByBranch[$label] ?? null;
        $gs    = $fy ? (float)$fy['gross_sales']     : 0.0;
        $disc  = $fy ? (float)$fy['sales_discount']  : 0.0;
        $exp   = $fy ? (float)$fy['total_exp']       : 0.0;
        $days  = $fy ? (int)$fy['days']              : 0;
        $gp    = $gs - $disc;
        $np    = $gp - $exp;
        $margin = $gp > 0 ? round(($np/$gp)*100,1) : 0;
        $thisM  = $monthByBranch[$label] ?? 0.0;
        $prevM  = $prevMonthByBranch[$label] ?? 0.0;
        $momChange = $prevM > 0 ? round((($thisM - $prevM) / $prevM) * 100, 1) : null;
        $branchPerf[] = [
            'label'  => $label,
            'today'  => $todayByBranch[$label] ?? null,
            'month'  => $thisM,
            'mom'    => $momChange,
            'fy_gs'  => $gs,
            'fy_np'  => $np,
            'margin' => $margin,
            'days'   => $days,
        ];
    }
    // Best-performing branch (by FY gross sales) shown first
    usort($branchPerf, fn($a,$b) => $b['fy_gs'] <=> $a['fy_gs']);

    $grandToday = array_sum(array_map(fn($b)=>$b['today']??0, $branchPerf));
    $grandMonth = array_sum(array_column($branchPerf,'month'));
    $grandFyGs  = array_sum(array_column($branchPerf,'fy_gs'));
    $grandFyNp  = array_sum(array_column($branchPerf,'fy_np'));

    // Today's reporting compliance — which branches HAVE and HAVEN'T submitted yet
    $reportedTodayLabels = array_keys($todayByBranch);
    $missingTodayLabels  = array_values(array_diff($knownBranches, $reportedTodayLabels));
}

// ── CSV Export: Branch Performance table (manager only) ──
if ($isManagerView && isset($_GET['export_branch_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="branch_performance_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Branch', "Today's Gross Sales", "This Month ({$monthNames[$kpiMonth]} {$kpiYear})",
                   'vs Last Month %', "FY {$ky} Gross Sales", "FY {$ky} Net Profit", 'Margin %', 'Days Recorded']);
    foreach ($branchPerf as $bp) {
        fputcsv($out, [$bp['label'], $bp['today'] ?? 0, $bp['month'], $bp['mom'] ?? '', $bp['fy_gs'], $bp['fy_np'], $bp['margin'], $bp['days']]);
    }
    fclose($out);
    exit;
}

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include 'layout.php';
?>

<?php if ($isManagerView): ?>
<div style="margin-bottom:16px">
  <div class="vb-row-label">Viewing Branch <span style="font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0">— switches the Today snapshot, Monthly Summary, and Recent Reports below · Branch Performance and company-wide totals are unaffected</span></div>
  <div class="vb-grid">
    <?php foreach ($ALL_BRANCH_LABELS as $b): $c = branchAccentColor($b); $active = $viewBranch === $b; ?>
    <div class="vb-card <?= $active ? 'active' : '' ?>" onclick="selectViewBranch('<?= htmlspecialchars($b, ENT_QUOTES) ?>')"
         style="border-top-color:<?= $c ?>; <?= $active ? "background:{$c}0d;" : '' ?>">
      <div class="vb-card-val" style="color:<?= $c ?>">
        <span class="vb-dot" style="background:<?= $c ?>"></span><?= htmlspecialchars($b) ?>
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
function selectViewBranch(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('view_branch', val);
  window.location.href = url.toString();
}
</script>
<?php endif; ?>

<!-- ── Today's Report or prompt ── -->
<?php if ($todayReport && $todayM): ?>
<div class="card" style="margin-bottom:20px;border-color:rgba(34,211,165,.2);background:linear-gradient(135deg,rgba(34,211,165,.04),transparent)">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-family:var(--font-m);font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--accent);margin-bottom:4px">Today — <?= date('F j, Y') ?></div>
      <div style="font-size:1rem;font-weight:600">Store: <?= htmlspecialchars($todayReport['store_name']) ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php
        $badges = [
          ['QUOTA', $todayM['quotaPassed']],
          ['COGS',  $todayM['cogsPassed']],
          ['MP',    $todayM['mpPassed']],
          ['NP',    $todayM['npPassed']],
        ];
        foreach ($badges as [$label, $pass]):
      ?>
      <span style="font-family:var(--font-m);font-size:.65rem;font-weight:600;padding:4px 11px;border-radius:20px;
            background:<?=$pass?'rgba(34,211,165,.12)':'rgba(248,113,113,.12)'?>;
            color:<?=$pass?'var(--accent)':'var(--accent2)'?>;
            border:1px solid <?=$pass?'rgba(34,211,165,.2)':'rgba(248,113,113,.2)'?>">
        <?=$label?> <?=$pass?'✓':'✗'?>
      </span>
      <?php endforeach; ?>
      <?php
        $editPage = branchSalesReportPage($todayReport['store_name']);
        $editHref = $editPage ? BASE_URL . $editPage . '?date=' . $today : 'daily_report.php?edit=' . $todayReport['id'];
      ?>
      <a href="<?= htmlspecialchars($editHref) ?>" class="btn btn-ghost btn-sm">Edit Today</a>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:16px">
    <?php
      $todayKpis = [
        ['Gross Sales',   '₱'.number_format($todayReport['gross_sales'],2),     'var(--text)'],
        ['Gross Profit',  '₱'.number_format($todayM['gp'],2),                   'var(--accent2)'],
        ['Total COGS',    '₱'.number_format($todayM['totalExp'],2).' ('.$todayM['cogsP'].'%)', $todayM['cogsPassed']?'var(--accent)':'var(--accent2)'],
        ['Total Manpower','₱'.number_format($todayM['totalMp'],2).' ('.$todayM['mpP'].'%)',   $todayM['mpPassed']?'var(--accent)':'var(--accent2)'],
        ['Net Profit',    ($todayM['profitLoss']<0?'−':'').'₱'.number_format(abs($todayM['profitLoss']),2).' ('.$todayM['npP'].'%)', $todayM['npPassed']?'var(--accent)':'var(--accent2)'],
      ];
      foreach ($todayKpis as [$lbl,$val,$clr]):
    ?>
    <div style="background:var(--surf2);border:1px solid var(--border);border-radius:9px;padding:12px 14px">
      <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:5px"><?=$lbl?></div>
      <div style="font-size:.9rem;font-weight:700;color:<?=$clr?>"><?=$val?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif (isBranch()): ?>
<div class="card" style="margin-bottom:20px;border-color:rgba(251,191,36,.15);background:rgba(251,191,36,.03);
     display:flex;align-items:center;justify-content:space-between;padding:16px 22px">
  <div>
    <div style="font-family:var(--font-m);font-size:.68rem;color:var(--accent3);margin-bottom:3px">⚠ No entry yet for today</div>
    <div style="font-size:.84rem;color:var(--subtext2)"><?= date('F j, Y') ?> — Daily report not submitted</div>
  </div>
  <a href="daily_report.php" class="btn btn-primary">+ Add Today's Report</a>
</div>
<?php endif; ?>

<!-- ── KPI Cards (from daily reports) ── -->
<?php
// KPI card: selected date gross sales
$kpiDayReport = $pdo->prepare("SELECT * FROM $SALES_UNION WHERE report_date=?$bAnd");
$kpiDayReport->execute(array_filter([$kpiDate,$bVal],fn($v)=>$v!==null));
$kpiDayReport = $kpiDayReport->fetch();
$kpiDayGS = $kpiDayReport ? (float)$kpiDayReport['gross_sales'] : null;

// KPI card: selected month gross sales
$stmtMGS = $pdo->prepare("SELECT COALESCE(SUM(gross_sales),0) FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=?$bAnd");
$stmtMGS->execute(array_filter([$kpiYear, $kpiMonth, $bVal],fn($v)=>$v!==null));
$thisMonthGS = (float)$stmtMGS->fetchColumn();

// This year's gross sales
$stmtYGS = $pdo->prepare("SELECT COALESCE(SUM(gross_sales),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
$stmtYGS->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null));
$totalGS = (float)$stmtYGS->fetchColumn();

// Days recorded this year
$stmtDays = $pdo->prepare("SELECT COUNT(*) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
$stmtDays->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null));
$daysFY = (int)$stmtDays->fetchColumn();

// Total expenses & net profit this year
$stmtExp = $pdo->prepare("SELECT COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
$stmtExp->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null));
$totalExpFY = (float)$stmtExp->fetchColumn();

$stmtDisc = $pdo->prepare("SELECT COALESCE(SUM(sales_discount),0) FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd");
$stmtDisc->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null));
$totalDiscFY = (float)$stmtDisc->fetchColumn();

$gpFY = $totalGS - $totalDiscFY;
$profitFY = $gpFY - $totalExpFY;
$marginFY = $gpFY > 0 ? round(($profitFY/$gpFY)*100,1) : 0;
$npPassedFY = $marginFY >= 20;

?>

<!-- Row 1: 3 Gross Sales boxes -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">

  <!-- Daily Gross Sales (filterable by date) -->
  <div class="kpi-card" style="--kpi-color:var(--accent);animation-delay:.05s">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
      <div class="kpi-label" style="margin-bottom:0">Daily Gross Sales</div>
      <form method="GET" style="margin:0">
        <?php foreach(['dview','dyear','dmonth','drmonth','dryear','kpi_month','kpi_year'] as $k): ?>
        <input type="hidden" name="<?=$k?>" value="<?=htmlspecialchars($_GET[$k]??'')?>">
        <?php endforeach; ?>
        <input type="date" name="kpi_date" value="<?=htmlspecialchars($kpiDate)?>"
               class="form-control" style="padding:3px 7px;font-size:.7rem;max-width:130px"
               onchange="this.form.submit()">
      </form>
    </div>
    <?php if ($kpiDayGS !== null): ?>
      <div class="kpi-value">₱<?= number_format($kpiDayGS,2) ?></div>
      <div class="kpi-sub"><?= date('F j, Y', strtotime($kpiDate)) ?></div>
    <?php else: ?>
      <div class="kpi-value" style="color:var(--subtext);font-size:1.1rem">No entry</div>
      <div class="kpi-sub" style="color:var(--accent3)">⚠ Not submitted <?= $kpiDate===date('Y-m-d')?'today':'on this date' ?></div>
    <?php endif; ?>
    <div class="kpi-icon">📅</div>
  </div>

  <!-- Monthly Gross Sales (filterable by month/year) -->
  <div class="kpi-card" style="--kpi-color:#60a5fa;animation-delay:.1s">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
      <div class="kpi-label" style="margin-bottom:0">Monthly Gross Sales</div>
      <form method="GET" style="margin:0;display:flex;gap:4px;align-items:center">
        <?php foreach(['dview','dyear','dmonth','drmonth','dryear','kpi_date'] as $k): ?>
        <input type="hidden" name="<?=$k?>" value="<?=htmlspecialchars($_GET[$k]??'')?>">
        <?php endforeach; ?>
        <select name="kpi_month" class="form-control" style="padding:3px 6px;font-size:.7rem;max-width:90px" onchange="this.form.submit()">
          <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?=$m?>" <?=$kpiMonth==$m?'selected':''?>><?=$monthNames[$m]?></option>
          <?php endfor; ?>
        </select>
        <select name="kpi_year" class="form-control" style="padding:3px 6px;font-size:.7rem;max-width:72px" onchange="this.form.submit()">
          <?php for($y=2050;$y>=2020;$y--): ?>
          <option value="<?=$y?>" <?=$kpiYear==$y?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
    <div class="kpi-value" style="color:#60a5fa">₱<?= number_format($thisMonthGS,2) ?></div>
    <?php
      $stmtDM = $pdo->prepare("SELECT COUNT(*) FROM $SALES_UNION WHERE YEAR(report_date)=? AND MONTH(report_date)=?$bAnd");
      $stmtDM->execute(array_values(array_filter([$kpiYear, $kpiMonth, $bVal], fn($v)=>$v!==null)));
      $daysThisMonth = (int)$stmtDM->fetchColumn();
    ?>
    <div class="kpi-sub"><?= $daysThisMonth ?> day<?= $daysThisMonth!=1?'s':'' ?> recorded this month</div>
    <div class="kpi-icon">📆</div>
  </div>

  <!-- This Year's Gross Sales -->
  <div class="kpi-card" style="--kpi-color:var(--accent3);animation-delay:.15s">
    <div class="kpi-label">FY <?=$ky?> Gross Sales</div>
    <div class="kpi-value" style="color:var(--accent3)">₱<?= number_format($totalGS,2) ?></div>
    <div class="kpi-sub"><?= $daysFY ?> day<?= $daysFY!=1?'s':'' ?> recorded this year</div>
    <div class="kpi-icon">📊</div>
  </div>

</div>

<!-- Row 2: 3 Financial summary boxes -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px">

  <!-- Total Expenses FY -->
  <div class="kpi-card" style="--kpi-color:var(--accent2);animation-delay:.2s">
    <div class="kpi-label">Total Expenses (<?=$ky?>)</div>
    <div class="kpi-value" style="color:var(--accent2)">₱<?= number_format($totalExpFY,2) ?></div>
    <div class="kpi-sub"><?= $gpFY>0 ? round(($totalExpFY/$gpFY)*100,1).'% of Gross Profit' : '—' ?></div>
    <div class="kpi-icon">📉</div>
  </div>

  <!-- Net Profit FY -->
  <div class="kpi-card" style="--kpi-color:<?=$npPassedFY?'var(--accent3)':'var(--accent2)'?>;animation-delay:.25s">
    <div class="kpi-label">Net Profit (<?=$ky?>)</div>
    <div class="kpi-value" style="color:<?=$npPassedFY?'var(--accent3)':'var(--accent2)'?>">
      <?=$profitFY<0?'−':''?>₱<?= number_format(abs($profitFY),2) ?>
    </div>
    <div class="kpi-sub" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
      <span><?=$marginFY?>% margin</span>
      <span style="font-family:var(--font-m);font-size:.6rem;padding:2px 7px;border-radius:10px;
            background:<?=$npPassedFY?'rgba(34,211,165,.15)':'rgba(248,113,113,.15)'?>;
            color:<?=$npPassedFY?'var(--accent)':'var(--accent2)'?>">
        NP <?=$npPassedFY?'PASSED':'FAILED'?>
      </span>
    </div>
    <div class="kpi-icon"><?=$npPassedFY?'📈':'📉'?></div>
  </div>

  <!-- Days Recorded -->
  <div class="kpi-card" style="--kpi-color:var(--purple);animation-delay:.3s">
    <div class="kpi-label">Days Recorded (<?=$ky?>)</div>
    <div class="kpi-value" style="color:var(--purple)"><?= $daysFY ?></div>
    <div class="kpi-sub">Daily reports submitted</div>
    <div class="kpi-icon">📋</div>
  </div>

</div>

<?php if ($isManagerView && $branchPerf): ?>
<!-- ── Today's Reporting Compliance (Manager / Accounting only) ── -->
<?php $missingCount = count($missingTodayLabels); $totalCount = count($knownBranches); ?>
<div class="card" style="margin-bottom:20px;padding:16px 22px;
     border-color:<?= $missingCount===0 ? 'rgba(34,211,165,.2)' : 'rgba(251,191,36,.25)' ?>;
     background:<?= $missingCount===0 ? 'linear-gradient(135deg,rgba(34,211,165,.04),transparent)' : 'rgba(251,191,36,.04)' ?>">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-family:var(--font-m);font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;
                  color:<?= $missingCount===0 ? 'var(--accent)' : 'var(--accent3)' ?>;margin-bottom:4px">
        Today's Reporting Status — <?= date('F j, Y') ?>
      </div>
      <div style="font-size:.95rem;font-weight:600">
        <?php if ($missingCount===0): ?>
          ✓ All <?= $totalCount ?> branches have submitted today's report
        <?php else: ?>
          ⚠ <?= $missingCount ?> of <?= $totalCount ?> branch<?= $totalCount!=1?'es':'' ?> haven't submitted today yet
        <?php endif; ?>
      </div>
    </div>
    <?php if ($missingCount > 0): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;max-width:60%">
      <?php foreach ($missingTodayLabels as $ml): ?>
      <span style="font-family:var(--font-m);font-size:.68rem;font-weight:600;padding:4px 11px;border-radius:20px;
            background:rgba(251,191,36,.12);color:var(--accent3);border:1px solid rgba(251,191,36,.25)">
        <?= htmlspecialchars($ml) ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($isManagerView && $branchPerf): ?>
<!-- ── Branch Performance (Manager / Accounting only) ── -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <div class="card-title">Branch Performance — All Branches</div>
      <div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext);margin-top:2px">
        Today · <?= $monthNames[$kpiMonth] ?> <?= $kpiYear ?> · FY <?= $ky ?> — sorted by FY Gross Sales
      </div>
    </div>
    <a href="?<?= http_build_query(array_merge($_GET, ['export_branch_csv'=>1])) ?>" class="card-badge" style="text-decoration:none">⬇ Export CSV</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Branch</th>
          <th>Today's Gross Sales</th>
          <th>This Month (<?= $monthNames[$kpiMonth] ?>)</th>
          <th>vs Last Month</th>
          <th>FY <?= $ky ?> Gross Sales</th>
          <th>FY <?= $ky ?> Net Profit</th>
          <th>Margin %</th>
          <th>Days Recorded</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($branchPerf as $bp): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($bp['label']) ?></td>
          <td style="font-family:var(--font-m)">
            <?php if ($bp['today'] === null): ?>
              <span style="color:var(--subtext);font-size:.78rem">— no entry today</span>
            <?php else: ?>
              ₱<?= number_format($bp['today'],2) ?>
            <?php endif; ?>
          </td>
          <td style="font-family:var(--font-m)">₱<?= number_format($bp['month'],2) ?></td>
          <td style="font-family:var(--font-m);font-size:.8rem">
            <?php if ($bp['mom'] === null): ?>
              <span style="color:var(--subtext)">—</span>
            <?php else: ?>
              <span style="color:<?= $bp['mom']>=0?'var(--accent)':'var(--accent2)' ?>">
                <?= $bp['mom']>=0?'▲':'▼' ?> <?= abs($bp['mom']) ?>%
              </span>
            <?php endif; ?>
          </td>
          <td style="font-family:var(--font-m);font-weight:600">₱<?= number_format($bp['fy_gs'],2) ?></td>
          <td style="font-family:var(--font-m);font-weight:600;color:<?= $bp['fy_np']>=0?'var(--accent)':'var(--accent2)' ?>">
            <?= $bp['fy_np']<0?'−':'' ?>₱<?= number_format(abs($bp['fy_np']),2) ?>
          </td>
          <td>
            <span class="badge <?= $bp['margin']>=20?'badge-paid':'badge-overdue' ?>">
              <?= $bp['margin'] ?>%
            </span>
          </td>
          <td style="font-family:var(--font-m);color:var(--subtext)"><?= $bp['days'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;border-top:2px solid var(--border)">
          <td>Grand Total</td>
          <td style="font-family:var(--font-m)">₱<?= number_format($grandToday,2) ?></td>
          <td style="font-family:var(--font-m)">₱<?= number_format($grandMonth,2) ?></td>
          <td></td>
          <td style="font-family:var(--font-m)">₱<?= number_format($grandFyGs,2) ?></td>
          <td style="font-family:var(--font-m);color:<?= $grandFyNp>=0?'var(--accent)':'var(--accent2)' ?>">
            <?= $grandFyNp<0?'−':'' ?>₱<?= number_format(abs($grandFyNp),2) ?>
          </td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div style="position:relative;height:220px;margin-top:20px">
    <canvas id="branchChart"></canvas>
  </div>
</div>
<?php endif; ?>

<!-- ── Monthly Summary (current month) ── -->
<?php if ($drReports): ?>
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:.9rem;font-weight:600;letter-spacing:-.02em">
        Monthly Summary — <?= $monthNames[$drMonth] ?> <?= $drYear ?>
      </div>
      <div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext);margin-top:2px">
        <?= count($drReports) ?> day<?= count($drReports)!=1?'s':'' ?> recorded
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <!-- Month/Year filter -->
      <form method="GET" style="display:flex;gap:4px;align-items:center">
        <?php foreach(['dview','dyear','dmonth','kpi_date','kpi_month','kpi_year'] as $k): ?>
        <input type="hidden" name="<?=$k?>" value="<?=htmlspecialchars($_GET[$k]??'')?>">
        <?php endforeach; ?>
        <select name="drmonth" class="form-control" style="padding:4px 8px;font-size:.74rem;max-width:110px" onchange="this.form.submit()">
          <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?=$m?>" <?=$drMonth==$m?'selected':''?>><?=$monthNames[$m]?></option>
          <?php endfor; ?>
        </select>
        <select name="dryear" class="form-control" style="padding:4px 8px;font-size:.74rem;max-width:80px" onchange="this.form.submit()">
          <?php for($y=2050;$y>=2020;$y--): ?>
          <option value="<?=$y?>" <?=$drYear==$y?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
      </form>
      <!-- Month pass/fail summary -->
      <?php
        $mBadges = [
          ['COGS', $drM['cogsPassed']], ['MP', $drM['mpPassed']], ['NP', $drM['npPassed']]
        ];
        foreach ($mBadges as [$lbl,$pass]):
      ?>
      <span style="font-family:var(--font-m);font-size:.65rem;padding:4px 11px;border-radius:20px;
            background:<?=$pass?'rgba(34,211,165,.1)':'rgba(248,113,113,.1)'?>;
            color:<?=$pass?'var(--accent)':'var(--accent2)'?>;
            border:1px solid <?=$pass?'rgba(34,211,165,.18)':'rgba(248,113,113,.18)'?>">
        <?=$lbl?> <?=$pass?'✓ PASSED':'✗ FAILED'?>
      </span>
      <?php endforeach; ?>
      <a href="daily_report.php?month=<?=$drMonth?>&year=<?=$drYear?><?= $isManagerView ? '&view_branch='.urlencode($viewBranch) : '' ?>" class="btn btn-ghost btn-sm">View All →</a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px">
    <?php
      $mSummaryKpis = [
        ['Gross Sales',    '₱'.number_format($drTotals['gross_sales'],2),  'var(--text)', null],
        ['Gross Profit',   '₱'.number_format($drM['gp'],2),               'var(--accent2)', null],
        ['Total Expenses', '₱'.number_format($drM['totalAllExp'],2),       'var(--accent3)', $drM['totalExpP'].'% of sales'],
        ['Net Profit',     ($drM['profitLoss']<0?'−':'').'₱'.number_format(abs($drM['profitLoss']),2), $drM['npPassed']?'var(--accent)':'var(--accent2)', $drM['npP'].'% · NP '.($drM['npPassed']?'PASSED':'FAILED')],
      ];
      foreach ($mSummaryKpis as [$lbl,$val,$clr,$sub]):
    ?>
    <div style="background:var(--surf2);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
      <div style="font-family:var(--font-m);font-size:.62rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:6px"><?=$lbl?></div>
      <div style="font-size:1.1rem;font-weight:700;color:<?=$clr?>"><?=$val?></div>
      <?php if($sub): ?><div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext);margin-top:3px"><?=$sub?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Progress bars for key ratios -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
    <?php
      $bars = [
        ['COGS %', $drM['cogsP'], (float)($drReports[0]['cogs_threshold']??30), $drM['cogsPassed'], false],
        ['Manpower %', $drM['mpP'], (float)($drReports[0]['mp_threshold']??35), $drM['mpPassed'], false],
        ['Net Profit %', $drM['npP'], (float)($drReports[0]['np_threshold']??20), $drM['npPassed'], true],
      ];
      foreach ($bars as [$label, $val, $thresh, $passed, $invert]):
        $pct = min(abs($val), 100);
        $color = $passed ? '#22d3a5' : '#f87171';
    ?>
    <div style="background:var(--surf2);border:1px solid var(--border);border-radius:9px;padding:13px 15px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <span style="font-family:var(--font-m);font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext)"><?=$label?></span>
        <span style="font-family:var(--font-m);font-size:.78rem;font-weight:600;color:<?=$color?>"><?=$val?>%</span>
      </div>
      <div style="background:var(--bg);border-radius:4px;height:6px;overflow:hidden">
        <div style="height:100%;width:<?=$pct?>%;background:<?=$color?>;border-radius:4px;transition:width .6s ease"></div>
      </div>
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);margin-top:5px">
        <?=$invert?'Min':'Max'?> target: <?=$thresh?>% ·
        <span style="color:<?=$color?>"><?=$passed?'✓ PASSED':'✗ FAILED'?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Profit/Loss Chart Section ── -->
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
    <div>
      <div style="font-size:.9rem;font-weight:600;letter-spacing:-.02em">Profit / Loss Analysis</div>
      <div style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext);margin-top:2px">
        <?php
          if($dashView==='daily')   echo $monthNames[$dMonth]." ".$dYear." — day by day";
          elseif($dashView==='monthly') echo "Year ".$dYear." — month by month";
          else echo "Last 5 years overview";
        ?>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <div style="display:flex;gap:3px;background:var(--surf2);border:1px solid var(--border);border-radius:9px;padding:3px">
        <?php foreach(['daily'=>'Daily','monthly'=>'Monthly','yearly'=>'Yearly'] as $v=>$lbl):
          $isActive = $dashView === $v;
          $href = '?dview='.$v.'&dyear='.$dYear.'&dmonth='.$dMonth;
        ?>
        <a href="<?=$href?>"
           style="padding:5px 15px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s;
                  <?=$isActive?'background:var(--accent);color:#031a12;':'color:var(--subtext2);'?>">
          <?=$lbl?>
        </a>
        <?php endforeach; ?>
      </div>

      <?php if ($dashView !== 'yearly'): ?>
      <form method="GET" style="display:inline">
        <input type="hidden" name="dview" value="<?=$dashView?>">
        <input type="hidden" name="dmonth" value="<?=$dMonth?>">
        <select name="dyear" class="form-control" style="max-width:100px" onchange="this.form.submit()">
          <?php for($y=2050;$y>=2020;$y--): ?><option value="<?=$y?>" <?=$dYear==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
        </select>
      </form>
      <?php endif; ?>
      <?php if ($dashView==='daily'): ?>
      <form method="GET" style="display:inline">
        <input type="hidden" name="dview" value="<?=$dashView?>">
        <input type="hidden" name="dyear" value="<?=$dYear?>">
        <select name="dmonth" class="form-control" style="max-width:145px" onchange="this.form.submit()">
          <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$dMonth==$m?'selected':''?>><?=$monthNames[$m]?></option><?php endfor; ?>
        </select>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Period mini KPIs -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px">
    <?php
      $pKpis = [
        ['Period Revenue',  '₱'.number_format($periodSales,0),  'var(--accent)'],
        ['Period Expenses', '₱'.number_format($periodExp,0),    'var(--accent2)'],
        ['Net Profit/Loss', ($periodProfit<0?'−':'').'₱'.number_format(abs($periodProfit),0), $periodProfit>=0?'var(--accent3)':'var(--accent2)'],
        ['Profitable Periods', $periodDays.' / '.count($chartLabels), 'var(--blue)'],
      ];
      foreach ($pKpis as [$lbl,$val,$clr]):
    ?>
    <div style="background:var(--surf2);border:1px solid var(--border);border-radius:9px;padding:12px 14px">
      <div style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:5px"><?=$lbl?></div>
      <div style="font-size:1rem;font-weight:700;color:<?=$clr?>"><?=$val?></div>
      <?php if($lbl==='Profitable Periods' && $lossDays>0): ?>
      <div style="font-family:var(--font-m);font-size:.65rem;color:var(--accent2);margin-top:2px">⚠ <?=$lossDays?> loss period<?=$lossDays>1?'s':''?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="position:relative;height:260px"><canvas id="profitChart"></canvas></div>

  <?php if (!empty($chartLabels)): ?>
  <div style="display:flex;gap:10px;margin-top:14px">
    <div style="flex:1;background:rgba(34,211,165,.06);border:1px solid rgba(34,211,165,.15);border-radius:9px;padding:12px 16px">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);text-transform:uppercase;letter-spacing:.07em">Best Period</div>
      <div style="font-size:.95rem;font-weight:700;margin-top:3px"><?=$chartLabels[$bestIdx]??'—'?></div>
      <div style="font-family:var(--font-m);font-size:.78rem;color:var(--accent)">₱<?=number_format($chartProfit[$bestIdx]??0,0)?></div>
    </div>
    <div style="flex:1;background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.15);border-radius:9px;padding:12px 16px">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);text-transform:uppercase;letter-spacing:.07em">Worst Period</div>
      <div style="font-size:.95rem;font-weight:700;margin-top:3px"><?=$chartLabels[$worstIdx]??'—'?></div>
      <div style="font-family:var(--font-m);font-size:.78rem;color:var(--accent2)"><?=($chartProfit[$worstIdx]??0)<0?'−':''?>₱<?=number_format(abs($chartProfit[$worstIdx]??0),0)?></div>
    </div>
    <?php if($periodSales>0): ?>
    <div style="flex:1;background:rgba(96,165,250,.06);border:1px solid rgba(96,165,250,.15);border-radius:9px;padding:12px 16px">
      <div style="font-family:var(--font-m);font-size:.62rem;color:var(--subtext);text-transform:uppercase;letter-spacing:.07em">Avg Margin</div>
      <div style="font-size:.95rem;font-weight:700;margin-top:3px"><?=round(($periodProfit/$periodSales)*100,1)?>%</div>
      <div style="font-family:var(--font-m);font-size:.78rem;color:var(--blue)">profit/revenue</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Charts Row ── -->
<div class="chart-grid" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Gross Sales vs Expenses</div>
      <div class="card-badge">Monthly <?=$ky?></div>
    </div>
    <div class="chart-wrap"><canvas id="barChart"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Expense Breakdown</div></div>
    <div class="chart-wrap"><canvas id="donutChart"></canvas></div>
  </div>
</div>

<!-- ── Recent Daily Reports Table ── -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <div class="card-title">Recent Daily Reports</div>
    <?php
      $recentHref = 'daily_report.php';
      if (isBranch()) {
          $ownPage = branchSalesReportPage(currentBranch());
          if ($ownPage) $recentHref = BASE_URL . $ownPage;
      }
    ?>
    <a href="<?= htmlspecialchars($recentHref) ?>" class="card-badge" style="text-decoration:none">View all →</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th><th>Gross Sales</th><th>Gross Profit</th>
          <th>COGS %</th><th>MP %</th><th>Net Profit</th><th>NP %</th>
          <th>Quota</th><th>NP</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentDR): foreach ($recentDR as $r):
          $rm = calcMetrics($r);
        ?>
        <tr>
          <td style="font-family:var(--font-m);font-size:.76rem;font-weight:500"><?= date('M j, Y', strtotime($r['report_date'])) ?></td>
          <td style="font-family:var(--font-m);font-weight:600">₱<?= number_format($r['gross_sales'],2) ?></td>
          <td style="font-family:var(--font-m);color:var(--accent2)">₱<?= number_format($rm['gp'],2) ?></td>
          <td>
            <span style="font-family:var(--font-m);font-size:.74rem;color:<?=$rm['cogsPassed']?'var(--accent)':'var(--accent2)'?>">
              <?=$rm['cogsP']?>%
            </span>
          </td>
          <td>
            <span style="font-family:var(--font-m);font-size:.74rem;color:<?=$rm['mpPassed']?'var(--accent)':'var(--accent2)'?>">
              <?=$rm['mpP']?>%
            </span>
          </td>
          <td style="font-family:var(--font-m);font-weight:600;color:<?=$rm['profitLoss']>=0?'var(--accent)':'var(--accent2)'?>">
            <?=$rm['profitLoss']<0?'−':''?>₱<?= number_format(abs($rm['profitLoss']),2) ?>
          </td>
          <td style="font-family:var(--font-m);font-size:.74rem;color:<?=$rm['npPassed']?'var(--accent)':'var(--accent2)'?>">
            <?=$rm['npP']?>%
          </td>
          <td>
            <span class="badge <?=$rm['quotaPassed']?'badge-paid':'badge-overdue'?>">
              <?=$rm['quotaPassed']?'✓':'✗'?>
            </span>
          </td>
          <td>
            <span class="badge <?=$rm['npPassed']?'badge-paid':'badge-overdue'?>">
              <?=$rm['npPassed']?'PASSED':'FAILED'?>
            </span>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--subtext);font-family:var(--font-m);font-size:.78rem">
          No daily reports yet. <a href="<?= htmlspecialchars($recentHref) ?>" style="color:var(--accent)">Add the first one →</a>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

  </div></div>

<script>
const plLabels = <?=json_encode($chartLabels)?>;
const plSales  = <?=json_encode($chartSales)?>;
const plExp    = <?=json_encode($chartExp)?>;
const plProfit = <?=json_encode($chartProfit)?>;

new Chart(document.getElementById('profitChart'), {
  type: 'bar',
  data: {
    labels: plLabels,
    datasets: [
      { label:'Gross Sales', data:plSales, backgroundColor:'rgba(34,211,165,.35)', borderRadius:4, borderSkipped:false, yAxisID:'y', order:2 },
      { label:'Expenses',    data:plExp,   backgroundColor:'rgba(248,113,113,.35)', borderRadius:4, borderSkipped:false, yAxisID:'y', order:2 },
      { label:'Profit/Loss', data:plProfit, type:'line',
        borderColor: plProfit.map(v=>v>=0?'#22d3a5':'#f87171'),
        backgroundColor:'transparent',
        pointBackgroundColor: plProfit.map(v=>v>=0?'#22d3a5':'#f87171'),
        pointRadius:4, pointHoverRadius:6, borderWidth:2, tension:0.35,
        yAxisID:'y', order:1,
        segment:{ borderColor: ctx=>{ const v=plProfit[ctx.p1DataIndex]; return v>=0?'#22d3a5':'#f87171'; } }
      }
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    interaction:{ mode:'index', intersect:false },
    plugins:{
      legend:{ labels:{ color:'#4d6b8a', font:{family:'Geist Mono',size:10}, boxWidth:10 } },
      tooltip:{
        backgroundColor:'#090e1a', borderColor:'rgba(255,255,255,.06)', borderWidth:1,
        titleColor:'#e8f0f8', bodyColor:'#4d6b8a',
        callbacks:{ label: c=>{ const s=c.dataset.label==='Profit/Loss'&&c.parsed.y<0?'−':''; return ' '+c.dataset.label+': '+s+'₱'+Math.abs(c.parsed.y).toLocaleString(); } }
      }
    },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9}} },
      y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9},
          callback:v=>(v<0?'−₱':'₱')+Math.abs(v>=1000||v<=-1000?Math.round(v/1000)+'k':v)},
          afterDataLimits: axis=>{ if(axis.min>0) axis.min=0; }
      }
    }
  }
});

<?php
$ms2q=$pdo->prepare("SELECT MONTH(report_date) m,COALESCE(SUM(gross_sales),0) t FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd GROUP BY m");
$ms2q->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null)); $ms2=$ms2q->fetchAll();
$me2q=$pdo->prepare("SELECT MONTH(report_date) m,COALESCE(SUM(gulay_commissary+direct_purchases+other_expenses+kitchen_manpower+frontline_manpower+overtime+undertime),0) t FROM $SALES_UNION WHERE YEAR(report_date)=?$bAnd GROUP BY m");
$me2q->execute(array_filter([$ky,$bVal],fn($v)=>$v!==null)); $me2=$me2q->fetchAll();
$sd2=array_fill(0,12,0); foreach($ms2 as $r) $sd2[$r['m']-1]=(float)$r['t'];
$ed2=array_fill(0,12,0); foreach($me2 as $r) $ed2[$r['m']-1]=(float)$r['t'];
?>
new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],datasets:[
    {label:'Gross Sales',data:<?=json_encode(array_values($sd2))?>,backgroundColor:'rgba(34,211,165,.7)',borderRadius:5,borderSkipped:false},
    {label:'Expenses',   data:<?=json_encode(array_values($ed2))?>,backgroundColor:'rgba(248,113,113,.6)',borderRadius:5,borderSkipped:false}
  ]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{labels:{color:'#4d6b8a',font:{family:'Geist Mono',size:10},boxWidth:10}},
      tooltip:{backgroundColor:'#090e1a',borderColor:'rgba(255,255,255,.06)',borderWidth:1,titleColor:'#e8f0f8',bodyColor:'#4d6b8a',callbacks:{label:c=>' ₱'+c.parsed.y.toLocaleString()}}
    },
    scales:{x:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9}}},
      y:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9},callback:v=>'₱'+(v>=1000?(v/1000)+'k':v)}}
    }
  }
})

const cats=<?=json_encode(array_column($cat_data,'category'))?>;
const catAmts=<?=json_encode(array_map(fn($r)=>(float)$r['total'],$cat_data))?>;
new Chart(document.getElementById('donutChart'),{
  type:'doughnut',
  data:{labels:cats.length?cats:['No data'],datasets:[{data:catAmts.length?catAmts:[1],
    backgroundColor:['#22d3a5','#f87171','#fbbf24','#60a5fa','#a78bfa'],
    borderWidth:2,borderColor:'#090e1a',hoverOffset:5}]},
  options:{responsive:true,maintainAspectRatio:false,cutout:'65%',
    plugins:{legend:{position:'bottom',labels:{color:'#4d6b8a',font:{family:'Geist Mono',size:9},boxWidth:9,padding:8}},
      tooltip:{backgroundColor:'#090e1a',borderColor:'rgba(255,255,255,.06)',borderWidth:1,titleColor:'#e8f0f8',bodyColor:'#4d6b8a',callbacks:{label:c=>' ₱'+c.parsed.toLocaleString()}}
    }
  }
});

<?php if ($isManagerView && $branchPerf): ?>
// Branch Performance comparison chart (Gross Sales vs Net Profit, FY <?=$ky?>)
const bpLabels = <?= json_encode(array_column($branchPerf,'label')) ?>;
const bpGross  = <?= json_encode(array_column($branchPerf,'fy_gs')) ?>;
const bpNet    = <?= json_encode(array_column($branchPerf,'fy_np')) ?>;

if (document.getElementById('branchChart')) {
  new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: {
      labels: bpLabels,
      datasets: [
        { label:'FY Gross Sales', data:bpGross, backgroundColor:'rgba(96,165,250,.65)', borderRadius:5, borderSkipped:false },
        { label:'FY Net Profit',  data:bpNet,   backgroundColor: bpNet.map(v=>v>=0?'rgba(34,211,165,.65)':'rgba(248,113,113,.65)'), borderRadius:5, borderSkipped:false }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{ labels:{ color:'#4d6b8a', font:{family:'Geist Mono',size:10}, boxWidth:10 } },
        tooltip:{
          backgroundColor:'#090e1a', borderColor:'rgba(255,255,255,.06)', borderWidth:1,
          titleColor:'#e8f0f8', bodyColor:'#4d6b8a',
          callbacks:{ label: c=>{ const s=c.parsed.y<0?'−':''; return ' '+c.dataset.label+': '+s+'₱'+Math.abs(c.parsed.y).toLocaleString(); } }
        }
      },
      scales:{
        x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9}} },
        y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#4d6b8a',font:{family:'Geist Mono',size:9},
            callback:v=>(v<0?'−₱':'₱')+Math.abs(v>=1000||v<=-1000?Math.round(v/1000)+'k':v)},
            afterDataLimits: axis=>{ if(axis.min>0) axis.min=0; }
        }
      }
    }
  });
}
<?php endif; ?>
</script>
</body>
</html>