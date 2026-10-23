<?php
// ============================================================
//  layout.php — Shared sidebar + topbar HTML
//  Usage: include with $pageTitle and $activePage set
// ============================================================
$user = currentUser();
$flash_success = flashGet('success');
$flash_error   = flashGet('error');

// ── Cashier lockdown (UI-level safety net) ──────────────────
// isCashier(), currentBranch(), and cashierDailyReportPath() now live in
// auth.php so every page can use them — not just ones that reach this
// include. The real enforcement (including blocking AJAX POSTs that
// `exit;` before this file is ever reached) happens in
// enforceCashierAccess(), which should be called right after
// requireLogin() at the top of every page. This redirect is kept here
// too as a defense-in-depth fallback for plain page navigation.
if (isCashier()) {
    $isDemicBranchNow = in_array(currentBranch(), ['DemicLab-Main', 'DemicLab-Jaro'], true);
    $dailyReportKey  = $isDemicBranchNow ? 'demic_daily' : 'daily';
    $dailyReportFile = cashierDailyReportPath();
    // Allowed activePage keys: Dashboard, their branch's Daily Report,
    // and their branch's Sales Report page(s) — kept in sync with
    // cashierSalesReportPaths() so this fallback never re-blocks a page
    // enforceCashierAccess() already lets through.
    $allowedPages = [$dailyReportKey, 'daily_carwash'];
    if (branchHasDashboard()) $allowedPages[] = 'dashboard';
    foreach (cashierSalesReportPaths() as $srPath) {
        $allowedPages[] = pathinfo($srPath, PATHINFO_FILENAME);
    }
    $onSharedPage = in_array($activePage ?? '', $allowedPages, true);
    // If this isn't one of those pages, send them to their Daily Report.
    if (!$onSharedPage) {
        header('Location: ' . BASE_URL . '/' . $dailyReportFile);
        exit;
    }
}

// ── Project name mapping (display only — DB keys unchanged) ─
function projectName(string $branch): string {
    $map = [
        'Stella'      => 'Project S',
        'Dois'        => 'Project D',
        'H'           => 'Project H',
        'Pub Express' => 'Project P',
        'Commissary'  => 'Project C',
        'Recovery'    => 'Project R',
        'DemicLab-Main' => 'Project DL',
        'DemicLab-Jaro' => 'Project DLJ',
    ];
    return $map[$branch] ?? $branch;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'SalesHub') ?> — SalesHub</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ── Reset & Root ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:        #f4f5f7;
  --surface:   #ffffff;
  --surf2:     #f8f9fb;
  --surf3:     #f0f2f5;
  --border:    #e3e6ea;
  --border2:   #c8cdd5;
  --accent:    #0f7b5c;
  --accent-dim:rgba(15,123,92,.10);
  --accent2:   #dc3545;
  --accent3:   #d97706;
  --blue:      #2563eb;
  --purple:    #7c3aed;
  --muted:     #d1d5db;
  --text:      #1a1d23;
  --subtext:   #6b7280;
  --subtext2:  #4b5563;
  --radius:    10px;
  --radius-sm: 7px;
  --font-h:    'Geist', sans-serif;
  --font-m:    'Geist Mono', monospace;
  --font-serif:'Instrument Serif', serif;
  --sidebar-w: 240px;
  --shadow:    0 1px 3px rgba(0,0,0,.07), 0 4px 12px rgba(0,0,0,.05);
  --shadow-lg: 0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.06);
}
html, body { height: 100%; }
body {
  font-family: var(--font-h);
  background: var(--bg); color: var(--text);
  display: flex; overflow-x: auto;
  -webkit-font-smoothing: antialiased;
}

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar-w); height: 100vh;
  background: #1a1d23;
  border-right: 1px solid rgba(255,255,255,.06);
  display: flex; flex-direction: column;
  padding: 0; gap: 0;
  position: fixed; top:0; left:0; bottom:0;
  z-index: 200;
  overflow: hidden;
}

.sidebar-inner {
  display: flex; flex-direction: column;
  padding: 20px 12px; gap: 2px;
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.15) transparent;
}
.sidebar-inner::-webkit-scrollbar { width: 4px; }
.sidebar-inner::-webkit-scrollbar-track { background: transparent; }
.sidebar-inner::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 2px; }
.sidebar-inner::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.25); }

.brand {
  display: flex; align-items: center; gap: 11px;
  padding: 12px 8px 20px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 16px;
}
.brand-icon {
  width: 34px; height: 34px; border-radius: 9px;
  background: linear-gradient(145deg, #0f7b5c 0%, #0a5c44 100%);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; color: #fff; font-size: .85rem;
  flex-shrink: 0; letter-spacing: -.02em;
  box-shadow: 0 2px 8px rgba(15,123,92,.4);
}
.brand-name {
  font-size: .95rem; font-weight: 600; letter-spacing: -.025em;
  color: #f1f3f5;
}
.brand-sub {
  font-family: var(--font-m); font-size: .6rem; color: rgba(255,255,255,.35);
  letter-spacing: .04em; text-transform: uppercase; margin-top: 1px;
}

.nav-section {
  font-family: var(--font-m); font-size: .58rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .12em;
  color: rgba(255,255,255,.3); padding: 16px 10px 5px;
}
.nav-item {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 10px; border-radius: var(--radius-sm);
  font-size: .82rem; font-weight: 500;
  color: rgba(255,255,255,.5); cursor: pointer;
  text-decoration: none;
  transition: background .15s, color .15s;
  position: relative;
}
.nav-item .ni {
  font-size: .85rem; width: 18px; text-align: center;
  flex-shrink: 0; opacity: .7;
}
.nav-item:hover { background: rgba(255,255,255,.06); color: rgba(255,255,255,.85); }
.nav-item:hover .ni { opacity: 1; }
.nav-item.active {
  background: rgba(15,123,92,.25);
  color: #4ade80;
  font-weight: 600;
}
.nav-item.active .ni { opacity: 1; }
.nav-item.active::before {
  content: ''; position: absolute; left: 0; top: 20%; bottom: 20%;
  width: 2px; border-radius: 2px; background: #4ade80;
}

.sidebar-footer {
  padding: 12px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.user-card {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 11px; border-radius: var(--radius-sm);
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
  transition: border-color .15s;
}
.user-card:hover { border-color: rgba(255,255,255,.15); }
.user-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: linear-gradient(135deg, #0f7b5c, #2563eb);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .72rem; color: #fff; flex-shrink: 0;
}
.user-name { font-size: .78rem; font-weight: 600; color: rgba(255,255,255,.85); }
.user-role { font-family: var(--font-m); font-size: .6rem; color: rgba(255,255,255,.35); margin-top: 1px; }
.logout-btn {
  margin-left: auto; width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  font-size: .7rem; font-family: var(--font-m);
  color: rgba(255,255,255,.3); text-decoration: none;
  transition: background .15s, color .15s;
  flex-shrink: 0;
}
.logout-btn:hover { background: rgba(220,53,69,.2); color: #f87171; }

/* ── Main Wrapper ── */
.main-wrapper {
  margin-left: var(--sidebar-w);
  flex: 1; display: flex; flex-direction: column; min-height: 100vh;
  position: relative; z-index: 1;
  min-width: 0;
  overflow-x: hidden;
}

/* ── Topbar ── */
.topbar {
  position: sticky; top: 0; z-index: 100;
  background: rgba(255,255,255,.92); backdrop-filter: blur(20px) saturate(1.4);
  border-bottom: 1px solid var(--border);
  padding: 0 32px;
  height: 56px;
  display: flex; align-items: center; justify-content: space-between;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.topbar-title {
  font-size: .92rem; font-weight: 600; letter-spacing: -.02em;
  color: var(--text);
}
.topbar-breadcrumb {
  font-family: var(--font-m); font-size: .68rem; color: var(--subtext);
  margin-top: 1px;
}
.topbar-right { display: flex; align-items: center; gap: 12px; }
.live-badge {
  display: flex; align-items: center; gap: 6px;
  font-family: var(--font-m); font-size: .68rem; color: var(--subtext2);
  background: var(--surf2); border: 1px solid var(--border);
  padding: 5px 10px; border-radius: 20px;
}
.live-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #22c55e; box-shadow: 0 0 6px #22c55e;
  animation: blink 2.5s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1;box-shadow:0 0 6px #22c55e}50%{opacity:.4;box-shadow:none} }

/* ── Page Content ── */
.page-content { padding: 28px 32px; flex: 1; }

/* ── Flash Messages ── */
.flash {
  border-radius: var(--radius-sm); padding: 11px 16px; margin-bottom: 20px;
  font-family: var(--font-m); font-size: .76rem;
  display: flex; align-items: center; gap: 10px;
  animation: fadeSlideDown .3s cubic-bezier(.22,1,.36,1);
}
.flash-success {
  background: #f0fdf4; border: 1px solid #bbf7d0;
  color: #15803d;
}
.flash-error {
  background: #fff1f2; border: 1px solid #fecdd3;
  color: #be123c;
}
@keyframes fadeSlideDown { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }

/* ── Shared Components ── */
.section-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px;
}
.section-title {
  font-size: 1.25rem; font-weight: 600; letter-spacing: -.03em;
  line-height: 1.2;
}
.section-title span { color: var(--accent); }
.section-subtitle {
  font-family: var(--font-m); font-size: .7rem; color: var(--subtext);
  margin-top: 3px;
}

.btn {
  padding: 8px 16px; border-radius: var(--radius-sm); font-family: var(--font-h);
  font-size: .78rem; font-weight: 500; cursor: pointer;
  border: none; transition: all .15s; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  letter-spacing: -.01em;
}
.btn-primary {
  background: var(--accent); color: #fff;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(15,123,92,.25);
}
.btn-primary:hover {
  background: #0a6649;
  box-shadow: 0 4px 12px rgba(15,123,92,.3);
  transform: translateY(-1px);
}
.btn-primary:active { transform: translateY(0); }
.btn-danger {
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3;
}
.btn-danger:hover { background: #ffe4e6; border-color: #fda4af; }
.btn-ghost {
  background: var(--surface); color: var(--subtext2);
  border: 1px solid var(--border);
}
.btn-ghost:hover { border-color: var(--border2); color: var(--text); background: var(--surf2); }
.btn-sm { padding: 5px 11px; font-size: .72rem; }

/* ── Card ── */
.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 20px 22px;
  box-shadow: 0 1px 4px rgba(0,0,0,.06), 0 2px 8px rgba(0,0,0,.04);
}
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px;
}
.card-title { font-size: .88rem; font-weight: 600; letter-spacing: -.015em; }
.card-badge {
  font-family: var(--font-m); font-size: .64rem;
  background: #f0fdf4; color: var(--accent);
  padding: 3px 9px; border-radius: 20px; border: 1px solid #bbf7d0;
}

/* ── Divider ── */
.divider { height: 1px; background: var(--border); margin: 16px 0; }

/* ── Table ── */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th {
  font-family: var(--font-m); font-size: .62rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .1em; color: var(--subtext);
  padding: 0 0 10px; text-align: left; border-bottom: 1px solid var(--border);
}
tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8f9fb; }
td { padding: 11px 0; font-size: .82rem; vertical-align: middle; }
td+td { padding-left: 16px; }
th+th { padding-left: 16px; }

/* ── Status badges ── */
.badge {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 20px;
  font-family: var(--font-m); font-size: .63rem; font-weight: 500;
  letter-spacing: .02em;
}
.badge-paid    { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.badge-pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.badge-overdue { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }

/* ── Modal ── */
.modal-overlay {
  display: none; position: fixed; inset: 0; z-index: 500;
  background: rgba(15,20,30,.5); backdrop-filter: blur(6px);
  align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal {
  background: #fff; border: 1px solid var(--border);
  border-radius: 12px; padding: 28px 28px 24px; width: 100%; max-width: 480px;
  animation: scaleIn .22s cubic-bezier(.22,1,.36,1);
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 48px rgba(0,0,0,.14), 0 4px 12px rgba(0,0,0,.08);
}
@keyframes scaleIn { from{opacity:0;transform:scale(.96) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
.modal-title {
  font-size: 1rem; font-weight: 600; letter-spacing: -.025em;
  margin-bottom: 20px; padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
  color: var(--text);
}
.form-group { margin-bottom: 14px; }
.form-group label {
  display: block; font-family: var(--font-m); font-size: .64rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .08em; color: var(--subtext);
  margin-bottom: 6px;
}
.form-control {
  width: 100%; padding: 9px 13px;
  background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-sm); color: var(--text);
  font-family: var(--font-m); font-size: .82rem;
  outline: none; transition: border-color .15s, box-shadow .15s;
}
.form-control:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(15,123,92,.1);
}
.form-control::placeholder { color: var(--subtext); opacity: .6; }
select.form-control option { background: #fff; color: var(--text); }
textarea.form-control { resize: vertical; min-height: 70px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-actions {
  display: flex; gap: 8px; justify-content: flex-end;
  margin-top: 20px; padding-top: 16px;
  border-top: 1px solid var(--border);
}

/* ── KPI grid ── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 22px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 18px 20px;
  position: relative; overflow: hidden;
  transition: border-color .2s, transform .2s, box-shadow .2s;
  animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both;
  box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.kpi-card:hover { border-color: var(--border2); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.kpi-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--kpi-color, var(--accent)), transparent);
  opacity: .6;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.kpi-label {
  font-family: var(--font-m); font-size: .62rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .1em; color: var(--subtext);
  margin-bottom: 10px;
}
.kpi-value {
  font-size: 1.6rem; font-weight: 700; letter-spacing: -.04em; line-height: 1;
  color: var(--kpi-color, var(--accent));
  font-variant-numeric: tabular-nums;
}
.kpi-sub { font-family: var(--font-m); font-size: .68rem; color: var(--subtext); margin-top: 6px; }
.kpi-icon {
  position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
  font-size: 1.6rem; opacity: .07;
}

/* ── Chart grid ── */
.chart-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 22px; }
.chart-wrap { position: relative; height: 230px; }

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f3f5; border-radius: 3px; }
::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #9aa3af; }

/* ── Responsive ── */
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(2,1fr)}.chart-grid{grid-template-columns:1fr}}
@media(max-width:720px){.sidebar{display:none}.main-wrapper{margin-left:0}.page-content{padding:16px}.form-row{grid-template-columns:1fr}}
</style>
<style>
/* Sales Reports (all branches): hide number-input spinner arrows */
body[class*="_sales_report"] input[type=number]{-moz-appearance:textfield!important;appearance:textfield!important}
body[class*="_sales_report"] input[type=number]::-webkit-outer-spin-button,
body[class*="_sales_report"] input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none!important;margin:0!important}
</style>
</head>
<body class="page-<?= htmlspecialchars($activePage ?? '', ENT_QUOTES) ?>">

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-inner">
    <div class="brand">
      <div class="brand-icon">S</div>
      <div>
        <div class="brand-name">Multipliers Corp</div>
        <div class="brand-sub">
          <?php if (isBranch() && currentBranch()): ?>
            <?= htmlspecialchars(projectName(currentBranch())) ?>
          <?php else: ?>
            Meritoni
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (isCashier()): ?>

    <?php if (branchHasDashboard()): ?>
    <div class="nav-section">Overview</div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>">
      <span class="ni">⊞</span> Dashboard
    </a>
    <?php endif; ?>

    <div class="nav-section">Records</div>
    <?php $isDemicCashier = in_array(currentBranch(), ['DemicLab-Main', 'DemicLab-Jaro'], true); ?>
    <a href="<?= BASE_URL . '/' . cashierDailyReportPath() ?>" class="nav-item <?= ($activePage===($isDemicCashier ? 'demic_daily' : 'daily'))?'active':'' ?>">
      <span class="ni">📋</span> Daily Report
    </a>

    <?php if (currentBranch() === 'H'): ?>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=<?= urlencode('H Carwash') ?>" class="nav-item <?= ($activePage==='daily_carwash')?'active':'' ?>">
      <span class="ni">🚗</span> Carwash Daily Report
    </a>
    <?php endif; ?>

    <?php foreach (cashierSalesReportPaths() as $srPath):
      $srPage  = pathinfo($srPath, PATHINFO_FILENAME);
      $isCw    = (strpos($srPath, 'carwash') !== false);
      $srLabel = $isCw ? 'Carwash Sales Report' : ($srPage === 'commissary_oic_report' ? 'OIC Report' : 'Sales Report');
      $srIcon  = $isCw ? '🚗' : '🧾';
    ?>
    <a href="<?= BASE_URL . '/' . $srPath ?>" class="nav-item <?= ($activePage===$srPage)?'active':'' ?>">
      <span class="ni"><?= $srIcon ?></span> <?= $srLabel ?>
    </a>
    <?php endforeach; ?>

    <?php else: ?>

    <?php if (branchHasDashboard()): ?>
    <div class="nav-section">Overview</div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>">
      <span class="ni">⊞</span> Dashboard
    </a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['user_role'] ?? '', ['manager', 'accounting'], true)): ?>
    <a href="<?= BASE_URL ?>/daily_report.php" class="nav-item <?= ($activePage==='daily_all')?'active':'' ?>">
      <span class="ni">📋</span> Daily Report (All Branch)
    </a>
    <?php endif; ?>

    <div class="nav-section">Records</div>

    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_sales_report.php" class="nav-item <?= ($activePage==='stella_sales_report')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Sales Report' : 'Stella Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/stella/stella_cogs.php" class="nav-item <?= ($activePage==='stella_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'Stella COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/stella/stella_cogs_monitoring.php" class="nav-item <?= ($activePage==='stella_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'Stella COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/stella/stella_expenses.php" class="nav-item <?= ($activePage==='stella_expenses')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Expenses' : 'Stella Expenses' ?>
    </a>
    <a href="<?= BASE_URL ?>/summary_report.php" class="nav-item <?= ($activePage==='summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary Report' : 'Stella Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=Stella" class="nav-item <?= ($activePage==='daily')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Daily Report' : 'Stella Daily Report' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_sales_report.php" class="nav-item <?= ($activePage==='dois_sales_report')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Sales Report' : 'Dois Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/dois/dois_cogs.php" class="nav-item <?= ($activePage==='dois_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'Dois COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/dois/dois_cogs_monitoring.php" class="nav-item <?= ($activePage==='dois_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'Dois COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/dois/dois_expenses.php" class="nav-item <?= ($activePage==='dois_expenses')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Expenses' : 'Dois Expenses' ?>
    </a>
    <a href="<?= BASE_URL ?>/dois/dois_summary_report.php" class="nav-item <?= ($activePage==='dois_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project D Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=Dois" class="nav-item <?= ($activePage==='daily')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Daily Report' : 'Dois Daily Report' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_sales_report.php" class="nav-item <?= ($activePage==='h_sales_report')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Sales Report' : 'H Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_carwash_sales_report.php" class="nav-item <?= ($activePage==='h_carwash_sales_report')?'active':'' ?>">
      <span class="ni">🚗</span> <?= isBranch() ? 'Carwash Sales Report' : 'H Carwash Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/carwash_services.php" class="nav-item <?= ($activePage==='h_carwash_services')?'active':'' ?>">
      <span class="ni">🛠️</span> <?= isBranch() ? 'Services' : 'H Carwash Services' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_carwash_summary_report.php" class="nav-item <?= ($activePage==='h_carwash_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Carwash Summary' : 'H Carwash Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_carwash_income_statement.php" class="nav-item <?= ($activePage==='h_carwash_income_statement')?'active':'' ?>">
      <span class="ni">📑</span> <?= isBranch() ? 'Carwash Income Statement' : 'H Carwash Income Statement' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_cogs.php" class="nav-item <?= ($activePage==='h_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'H COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_cogs_monitoring.php" class="nav-item <?= ($activePage==='h_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'H COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_carwash_cogs.php" class="nav-item <?= ($activePage==='h_carwash_cogs')?'active':'' ?>">
      <span class="ni">🚗</span> <?= isBranch() ? 'Carwash COGS' : 'H Carwash COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_expenses.php" class="nav-item <?= ($activePage==='h_expenses')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Expenses' : 'H Expenses' ?>
    </a>
    <a href="<?= BASE_URL ?>/h/h_summary_report.php" class="nav-item <?= ($activePage==='h_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project H Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=H" class="nav-item <?= ($activePage==='daily')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Daily Report' : 'H Daily Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=<?= urlencode('H Carwash') ?>" class="nav-item <?= ($activePage==='daily_carwash')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Carwash Daily Report' : 'H Carwash Daily Report' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_sales_report.php" class="nav-item <?= ($activePage==='pub_express_sales_report')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Sales Report' : 'Pub Express Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_cogs.php" class="nav-item <?= ($activePage==='pub_express_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'Pub Express COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/pub_express/pub_cogs_monitoring.php" class="nav-item <?= ($activePage==='pub_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'Pub COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_expenses.php" class="nav-item <?= ($activePage==='pub_express_expenses')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Expenses' : 'Pub Express Expenses' ?>
    </a>
    <a href="<?= BASE_URL ?>/pub_express/pub_summary_report.php" class="nav-item <?= ($activePage==='pub_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project P Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=<?= urlencode('Pub Express') ?>" class="nav-item <?= ($activePage==='daily')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Daily Report' : 'Pub Express Daily Report' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_daily_store_billing.php" class="nav-item <?= ($activePage==='commissary_daily_store_billing')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Daily Store Billing' : 'Commissary Daily Store Billing' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_cogs.php" class="nav-item <?= ($activePage==='commissary_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'Commissary COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_cogs_monitoring.php" class="nav-item <?= ($activePage==='commissary_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'Commissary COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_accounts_payable.php" class="nav-item <?= ($activePage==='commissary_accounts_payable')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Accounts' : 'Commissary Accounts' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_disbursement.php" class="nav-item <?= ($activePage==='commissary_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'Commissary Disbursement' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_receivables.php" class="nav-item <?= ($activePage==='commissary_receivables')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Accounts Receivable' : 'Commissary Receivable' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_riders_salary.php" class="nav-item <?= ($activePage==='commissary_riders_salary')?'active':'' ?>">
      <span class="ni">🏍️</span> <?= isBranch() ? 'Riders Salary' : 'Commissary Riders Salary' ?>
    </a>
    <a href="<?= BASE_URL ?>/commissary/commissary_oic_report.php" class="nav-item <?= ($activePage==='commissary_oic_report')?'active':'' ?>">
      <span class="ni">📝</span> <?= isBranch() ? 'OIC Report' : 'Commissary OIC Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/summary_report.php" class="nav-item <?= ($activePage==='summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary Report' : 'Commissary Summary' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_sales_report.php" class="nav-item <?= ($activePage==='recovery_sales_report')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Sales Report' : 'Recovery Sales Report' ?>
    </a>
    <a href="<?= BASE_URL ?>/recovery/recovery_cogs.php" class="nav-item <?= ($activePage==='recovery_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'Recovery COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/recovery/recovery_cogs_monitoring.php" class="nav-item <?= ($activePage==='recovery_cogs_monitoring')?'active':'' ?>">
      <span class="ni">📈</span> <?= isBranch() ? 'COGS Monitoring' : 'Recovery COGS Monitoring' ?>
    </a>
    <a href="<?= BASE_URL ?>/recovery/recovery_expenses.php" class="nav-item <?= ($activePage==='recovery_expenses')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Expenses' : 'Recovery Expenses' ?>
    </a>
    <a href="<?= BASE_URL ?>/recovery/recovery_summary_report.php" class="nav-item <?= ($activePage==='recovery_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project R Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/daily_report.php?view_branch=Recovery" class="nav-item <?= ($activePage==='daily')?'active':'' ?>">
      <span class="ni">📋</span> <?= isBranch() ? 'Daily Report' : 'Recovery Daily Report' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'DemicLab-Main' || currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demic_daily_report.php" class="nav-item <?= ($activePage==='demic_daily')?'active':'' ?>">
      <span class="ni">📋</span> Demic Daily Report
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_cogs.php" class="nav-item <?= ($activePage==='demiclab_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'DemicLab-Main COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demic_purchases.php" class="nav-item <?= ($activePage==='demic_purchases')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Purchases Ledger' : 'DemicLab-Main Purchases Ledger' ?>
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demic_payables.php" class="nav-item <?= ($activePage==='demic_payables')?'active':'' ?>">
      <span class="ni">💳</span> <?= isBranch() ? 'Payables' : 'DemicLab-Main Payables' ?>
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demic_receivables.php" class="nav-item <?= ($activePage==='demic_receivables')?'active':'' ?>">
      <span class="ni">📥</span> <?= isBranch() ? 'Receivables' : 'DemicLab-Main Receivables' ?>
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_summary_report.php" class="nav-item <?= ($activePage==='demiclab_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project DL Summary' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_cogs.php" class="nav-item <?= ($activePage==='demiclab_jaro_cogs')?'active':'' ?>">
      <span class="ni">📦</span> <?= isBranch() ? 'COGS' : 'DemicLab-Jaro COGS' ?>
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_summary_report.php" class="nav-item <?= ($activePage==='demiclab_jaro_summary')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Summary' : 'Project DLJ Summary' ?>
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_purchases.php" class="nav-item <?= ($activePage==='demic_jaro_purchases')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Purchases Ledger' : 'DemicLab-Jaro Purchases Ledger' ?>
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_payables.php" class="nav-item <?= ($activePage==='demic_jaro_payables')?'active':'' ?>">
      <span class="ni">💳</span> <?= isBranch() ? 'Payables' : 'DemicLab-Jaro Payables' ?>
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_receivables.php" class="nav-item <?= ($activePage==='demic_jaro_receivables')?'active':'' ?>">
      <span class="ni">📥</span> <?= isBranch() ? 'Receivables' : 'DemicLab-Jaro Receivables' ?>
    </a>
    <?php endif; ?>

    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demic_discounts.php" class="nav-item <?= ($activePage==='demic_discounts')?'active':'' ?>">
      <span class="ni">🏷️</span> Demic Discounts
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demic_sales_report_summary.php" class="nav-item <?= ($activePage==='demic_sales_summary')?'active':'' ?>">
      <span class="ni">💹</span> Sales Report Summary
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_discounts.php" class="nav-item <?= ($activePage==='demic_jaro_discounts')?'active':'' ?>">
      <span class="ni">🏷️</span> Demic Discounts
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_sales_report_summary.php" class="nav-item <?= ($activePage==='demic_jaro_sales_summary')?'active':'' ?>">
      <span class="ni">💹</span> Sales Report Summary
    </a>
    <?php endif; ?>

    <?php
    $expensePages = [
        'Stella'      => ['file'=>'stella/stella_expenses.php',      'key'=>'stella_expenses'],
        'Dois'        => ['file'=>'dois/dois_expenses.php',        'key'=>'dois_expenses'],
        'H'           => ['file'=>'h/h_expenses.php',           'key'=>'h_expenses'],
        'Pub Express' => ['file'=>'pub_express/pub_express_expenses.php', 'key'=>'pub_express_expenses'],
        'Commissary'  => ['file'=>'commissary/commissary_expenses.php',  'key'=>'commissary_expenses'],
        'Recovery'    => ['file'=>'recovery/recovery_expenses.php',    'key'=>'recovery_expenses'],
        'DemicLab-Main' => ['file'=>'demicmain/demiclab_expenses.php',      'key'=>'demiclab_expenses'],
        'DemicLab-Jaro' => ['file'=>'demiclab_jaro/demiclab_jaro_expenses.php', 'key'=>'demiclab_jaro_expenses'],
    ];
    if (isBranch()) {
        $branch = currentBranch();
        if (isset($expensePages[$branch])):
            $ep = $expensePages[$branch];
    ?>
    <?php
    $isPages = [
        'Stella'      => ['file'=>'stella/stella_income_statement.php',       'key'=>'stella_income_statement'],
        'Dois'        => ['file'=>'dois/dois_income_statement.php',         'key'=>'dois_income_statement'],
        'H'           => ['file'=>'h/h_income_statement.php',            'key'=>'h_income_statement'],
        'Pub Express' => ['file'=>'pub_express/pub_income_statement.php',          'key'=>'pub_express_income_statement'],
        'Commissary'  => ['file'=>'commissary/commissary_income_statement.php',   'key'=>'commissary_income_statement'],
        'Recovery'    => ['file'=>'recovery/recovery_income_statement.php',     'key'=>'recovery_income_statement'],
        'DemicLab-Main' => ['file'=>'demicmain/demiclab_income_statement.php',      'key'=>'demiclab_income_statement'],
        'DemicLab-Jaro' => ['file'=>'demiclab_jaro/demiclab_jaro_income_statement.php', 'key'=>'demiclab_jaro_income_statement'],
    ];
    if (isset($isPages[$branch])):
        $ip = $isPages[$branch];
    ?>
    <a href="<?= BASE_URL . '/' . ($ip['file']) ?>" class="nav-item <?= ($activePage===$ip['key'])?'active':'' ?>">
      <span class="ni">📑</span> Income Statement
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/vendor_masterlist.php" class="nav-item <?= ($activePage==='vendor_masterlist')?'active':'' ?>">
      <span class="ni">🏢</span> Vendor Masterlist
    </a>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_pdc.php" class="nav-item <?= ($activePage==='stella_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_month_end_inv.php" class="nav-item <?= ($activePage==='stella_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'Stella Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_cashflow.php" class="nav-item <?= ($activePage==='stella_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cashflow' : 'Stella Cashflow' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_cashflow_balance.php" class="nav-item <?= ($activePage==='stella_cashflow_balance')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Cashflow Balance' : 'Stella Cashflow Balance' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_disbursement.php" class="nav-item <?= ($activePage==='stella_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'Stella Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_profit_loss.php" class="nav-item <?= ($activePage==='stella_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'Stella P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Stella' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/stella/stella_reconcile.php" class="nav-item <?= ($activePage==='stella_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'Stella Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_pdc.php" class="nav-item <?= ($activePage==='dois_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_month_end_inv.php" class="nav-item <?= ($activePage==='dois_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'Dois Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_cashflow.php" class="nav-item <?= ($activePage==='dois_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cashflow' : 'Dois Cashflow' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_cashflow_balance.php" class="nav-item <?= ($activePage==='dois_cashflow_balance')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Cashflow Balance' : 'Dois Cashflow Balance' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_disbursement.php" class="nav-item <?= ($activePage==='dois_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'Dois Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_profit_loss.php" class="nav-item <?= ($activePage==='dois_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'Dois P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Dois' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/dois/dois_reconcile.php" class="nav-item <?= ($activePage==='dois_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'Dois Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_pdc.php" class="nav-item <?= ($activePage==='h_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_month_end_inv.php" class="nav-item <?= ($activePage==='h_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'H Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_cashflow.php" class="nav-item <?= ($activePage==='h_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cashflow' : 'H Cashflow' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_cashflow_recon.php" class="nav-item <?= ($activePage==='h_cashflow_recon')?'active':'' ?>">
      <span class="ni">🔄</span> <?= isBranch() ? 'CashFlow Reconciliation' : 'H CashFlow Reconciliation' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_cashflow_balance.php" class="nav-item <?= ($activePage==='h_cashflow_balance')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Cashflow Balance' : 'H Cashflow Balance' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_disbursement.php" class="nav-item <?= ($activePage==='h_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'H Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_profit_loss.php" class="nav-item <?= ($activePage==='h_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'H P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_reconcile.php" class="nav-item <?= ($activePage==='h_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'H Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_bank_statement.php" class="nav-item <?= ($activePage==='h_bank_statement')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Bank Statement' : 'H Bank Statement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'H' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/h/h_check_report.php" class="nav-item <?= ($activePage==='h_check_report')?'active':'' ?>">
      <span class="ni">✅</span> <?= isBranch() ? 'Check Releasing Report' : 'H Check Releasing Report' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_pdc.php" class="nav-item <?= ($activePage==='pub_express_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_month_end_inv.php" class="nav-item <?= ($activePage==='pub_express_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'Pub Express Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_cashflow.php" class="nav-item <?= ($activePage==='pub_express_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cashflow' : 'Pub Express Cashflow' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_cashflow_balance.php" class="nav-item <?= ($activePage==='pub_express_cashflow_balance')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Cashflow Balance' : 'Pub Express Cashflow Balance' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_disbursement.php" class="nav-item <?= ($activePage==='pub_express_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'Pub Express Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_profit_loss.php" class="nav-item <?= ($activePage==='pub_express_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'Pub Express P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Pub Express' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/pub_express/pub_express_reconcile.php" class="nav-item <?= ($activePage==='pub_express_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'Pub Express Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_purchases_ledger.php" class="nav-item <?= ($activePage==='commissary_purchases_ledger')?'active':'' ?>">
      <span class="ni">🛒</span> <?= isBranch() ? 'Purchases' : 'Commissary Purchases' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_month_end_inv.php" class="nav-item <?= ($activePage==='commissary_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'Commissary Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_cashflow.php" class="nav-item <?= ($activePage==='commissary_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cash Position Report' : 'Commissary Cash Position Report' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_profit_loss.php" class="nav-item <?= ($activePage==='commissary_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'Commissary P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Commissary' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/commissary/commissary_reconcile.php" class="nav-item <?= ($activePage==='commissary_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'Commissary Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_pdc.php" class="nav-item <?= ($activePage==='recovery_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_month_end_inv.php" class="nav-item <?= ($activePage==='recovery_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'Recovery Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_cashflow.php" class="nav-item <?= ($activePage==='recovery_cashflow')?'active':'' ?>">
      <span class="ni">💵</span> <?= isBranch() ? 'Cashflow' : 'Recovery Cashflow' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_cashflow_balance.php" class="nav-item <?= ($activePage==='recovery_cashflow_balance')?'active':'' ?>">
      <span class="ni">🧮</span> <?= isBranch() ? 'Cashflow Balance' : 'Recovery Cashflow Balance' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_disbursement.php" class="nav-item <?= ($activePage==='recovery_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'Recovery Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_profit_loss.php" class="nav-item <?= ($activePage==='recovery_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'Recovery P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'Recovery' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/recovery/recovery_reconcile.php" class="nav-item <?= ($activePage==='recovery_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'Recovery Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demic_daily_cashflow.php" class="nav-item <?= ($activePage==='demic_daily_cashflow')?'active':'' ?>">
      <span class="ni">📅</span> <?= isBranch() ? 'Daily Sales and Cashflow' : 'DemicLab-Main Daily Sales and Cashflow' ?>
    </a>
    <a href="<?= BASE_URL ?>/demicmain/demic_sales_distribution.php" class="nav-item <?= ($activePage==='demic_sales_distribution')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Sales Distribution Report' : 'DemicLab-Main Sales Distribution Report' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_daily_cashflow.php" class="nav-item <?= ($activePage==='demic_jaro_daily_cashflow')?'active':'' ?>">
      <span class="ni">📅</span> <?= isBranch() ? 'Daily Sales and Cashflow' : 'DemicLab-Jaro Daily Sales and Cashflow' ?>
    </a>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demic_jaro_sales_distribution.php" class="nav-item <?= ($activePage==='demic_jaro_sales_distribution')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Sales Distribution Report' : 'DemicLab-Jaro Sales Distribution Report' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_pdc.php" class="nav-item <?= ($activePage==='demiclab_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_month_end_inv.php" class="nav-item <?= ($activePage==='demiclab_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'DemicLab-Main Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_disbursement.php" class="nav-item <?= ($activePage==='demiclab_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'DemicLab-Main Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_profit_loss.php" class="nav-item <?= ($activePage==='demiclab_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'DemicLab-Main P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_reconcile.php" class="nav-item <?= ($activePage==='demiclab_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'DemicLab-Main Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Main' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demicmain/demiclab_bank_statement.php" class="nav-item <?= ($activePage==='demiclab_bank_statement')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Bank Statement' : 'DemicLab-Main Bank Statement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_pdc.php" class="nav-item <?= ($activePage==='demiclab_jaro_pdc')?'active':'' ?>">
      <span class="ni">📝</span> PDC
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_month_end_inv.php" class="nav-item <?= ($activePage==='demiclab_jaro_month_end_inv')?'active':'' ?>">
      <span class="ni">🗂️</span> <?= isBranch() ? 'Month End Inv.' : 'DemicLab-Jaro Month End Inv.' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_cash_positioning.php" class="nav-item <?= ($activePage==='demiclab_jaro_cash_positioning')?'active':'' ?>">
      <span class="ni">💰</span> <?= isBranch() ? 'Cash Positioning' : 'DemicLab-Jaro Cash Positioning' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_disbursement.php" class="nav-item <?= ($activePage==='demiclab_jaro_disbursement')?'active':'' ?>">
      <span class="ni">📒</span> <?= isBranch() ? 'Disbursement' : 'DemicLab-Jaro Disbursement' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_profit_loss.php" class="nav-item <?= ($activePage==='demiclab_jaro_profit_loss')?'active':'' ?>">
      <span class="ni">📊</span> <?= isBranch() ? 'Profit & Loss' : 'DemicLab-Jaro P&L' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_reconcile.php" class="nav-item <?= ($activePage==='demiclab_jaro_reconcile')?'active':'' ?>">
      <span class="ni">🏦</span> <?= isBranch() ? 'Reconcile' : 'DemicLab-Jaro Reconcile' ?>
    </a>
    <?php endif; ?>
    <?php if (currentBranch() === 'DemicLab-Jaro' || isManagement()): ?>
    <a href="<?= BASE_URL ?>/demiclab_jaro/demiclab_jaro_bank_statement.php" class="nav-item <?= ($activePage==='demiclab_jaro_bank_statement')?'active':'' ?>">
      <span class="ni">🧾</span> <?= isBranch() ? 'Bank Statement' : 'DemicLab-Jaro Bank Statement' ?>
    </a>
    <?php endif; ?>
    <?php } else { // Management: show all branches ?>
    <?php
    $isPages = [
        'Stella'      => ['file'=>'stella/stella_income_statement.php',       'key'=>'stella_income_statement',      'label'=>'Stella'],
        'Dois'        => ['file'=>'dois/dois_income_statement.php',         'key'=>'dois_income_statement',        'label'=>'Dois'],
        'H'           => ['file'=>'h/h_income_statement.php',            'key'=>'h_income_statement',           'label'=>'H'],
        'Pub Express' => ['file'=>'pub_express/pub_income_statement.php',          'key'=>'pub_express_income_statement', 'label'=>'Pub Express'],
        'Commissary'  => ['file'=>'commissary/commissary_income_statement.php',   'key'=>'commissary_income_statement',  'label'=>'Commissary'],
        'Recovery'    => ['file'=>'recovery/recovery_income_statement.php',     'key'=>'recovery_income_statement',    'label'=>'Recovery'],
        'DemicLab-Main' => ['file'=>'demicmain/demiclab_income_statement.php',      'key'=>'demiclab_income_statement',      'label'=>'DemicLab-Main'],
        'DemicLab-Jaro' => ['file'=>'demiclab_jaro/demiclab_jaro_income_statement.php', 'key'=>'demiclab_jaro_income_statement', 'label'=>'DemicLab-Jaro'],
    ];
    foreach ($isPages as $ip): ?>
    <a href="<?= BASE_URL . '/' . ($ip['file']) ?>" class="nav-item <?= ($activePage===$ip['key'])?'active':'' ?>">
      <span class="ni">📑</span> <?= projectName($ip['label']) ?> Income Stmt
    </a>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/vendor_masterlist.php" class="nav-item <?= ($activePage==='vendor_masterlist')?'active':'' ?>">
      <span class="ni">🏢</span> Vendor Masterlist
    </a>
    <?php } ?>
    <?php if (isManagement() && !in_array($_SESSION['user_role'] ?? '', ['manager', 'accounting'], true)): ?>
    <a href="<?= BASE_URL ?>/sales.php" class="nav-item <?= ($activePage==='sales')?'active':'' ?>">
      <span class="ni">↗</span> Sales
    </a>
    <a href="<?= BASE_URL ?>/expenses.php" class="nav-item <?= ($activePage==='expenses')?'active':'' ?>">
      <span class="ni">↙</span> Expenses
    </a>
    <?php endif; ?>
    <?php if (isManagement()): ?>
    <div class="nav-section">Oversight</div>
    <a href="<?= BASE_URL ?>/tracking_monitoring.php" class="nav-item <?= ($activePage==='tracking_monitoring')?'active':'' ?>">
      <span class="ni">🛰️</span> Tracking &amp; Monitoring
    </a>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars(isBranch() ? projectName($user['name']) : $user['name']) ?></div>
        <div class="user-role"><?= isBranch() ? htmlspecialchars(projectName($user['name'])) : htmlspecialchars(managementRoleLabel($user['role'])) ?></div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Logout">→</a>
    </div>
  </div>
</aside>

<script>
// ── Keep the sidebar scrolled to where the person left it ──────
// Each nav click is a full page load (not an SPA), so without this
// the sidebar always reopens scrolled to the top. We save the
// scroll position (and which link was clicked) to sessionStorage
// right before navigating, then restore it as soon as the next
// page's sidebar renders.
(function () {
  var KEY = 'sh_sidebar_scroll';
  var sidebar = document.querySelector('.sidebar-inner');
  if (!sidebar) return;

  // Restore: prefer the exact saved scrollTop; fall back to
  // scrolling the active link into view (e.g. first load, or a
  // stale/mismatched saved value).
  try {
    var saved = sessionStorage.getItem(KEY);
    if (saved !== null) {
      sidebar.scrollTop = parseInt(saved, 10) || 0;
    } else {
      var active = sidebar.querySelector('.nav-item.active');
      if (active) active.scrollIntoView({ block: 'center' });
    }
  } catch (e) {}

  // Save on every click of a sidebar link, right before the
  // browser navigates away.
  sidebar.querySelectorAll('a.nav-item, a.logout-btn').forEach(function (link) {
    link.addEventListener('click', function () {
      try { sessionStorage.setItem(KEY, sidebar.scrollTop); } catch (e) {}
    });
  });

  // Also save on scroll (debounced) and on unload, in case the
  // person scrolls without clicking (e.g. browser back/forward).
  var scrollTimer = null;
  sidebar.addEventListener('scroll', function () {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(function () {
      try { sessionStorage.setItem(KEY, sidebar.scrollTop); } catch (e) {}
    }, 120);
  });
  window.addEventListener('pagehide', function () {
    try { sessionStorage.setItem(KEY, sidebar.scrollTop); } catch (e) {}
  });
})();
</script>

<!-- ── Main ── -->
<div class="main-wrapper">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar-breadcrumb"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <div class="topbar-right">
      <div class="live-badge"><div class="live-dot"></div> Live</div>
    </div>
  </div>

  <div class="page-content">
    <?php if ($flash_success): ?>
    <div class="flash flash-success">✓ <?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="flash flash-error">⚠ <?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>