<?php
// ============================================================
//  commissary_oic_report.php — Commissary OIC Daily Report
//  Card-based CRUD list (styled after Demic Lab Daily Report):
//  month/year tabs, one hoverable card per day, Add/Edit modals,
//  per-entry editable Quota/COGS/MP/NP targets, pass/fail badges.
//  Total COGS feeds Commissary Income Statement's Cost of Sales
//  auto-pull, the same way Expenses feeds VAT.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Table (created on first hit, matches other modules' pattern) ──
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_oic_report` (
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `store_name`        varchar(50) NOT NULL DEFAULT 'Commissary',
    `report_date`       date NOT NULL,
    `pos_reading`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `store_gulay`       decimal(12,2) NOT NULL DEFAULT 0.00,
    `samgyup_sales`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `grab_sales`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `others_walkin`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `grab_discount`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `walkin_discount`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `supplier`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `gulay`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `direct_purchases`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `other_expenses`    decimal(12,2) NOT NULL DEFAULT 0.00,
    `kitchen`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline`         decimal(12,2) NOT NULL DEFAULT 0.00,
    `overtime`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `quota_target`      decimal(12,2) NOT NULL DEFAULT 117000.00,
    `cogs_threshold`    decimal(5,2)  NOT NULL DEFAULT 80.00,
    `mp_threshold`      decimal(5,2)  NOT NULL DEFAULT 4.00,
    `np_threshold`      decimal(5,2)  NOT NULL DEFAULT 10.00,
    `saved_by`          varchar(100) DEFAULT NULL,
    `created_at`        timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`        timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Backfill target/threshold columns for tables created before this update
foreach ([
    'quota_target'   => "decimal(12,2) NOT NULL DEFAULT 117000.00",
    'cogs_threshold' => "decimal(5,2) NOT NULL DEFAULT 80.00",
    'mp_threshold'   => "decimal(5,2) NOT NULL DEFAULT 4.00",
    'np_threshold'   => "decimal(5,2) NOT NULL DEFAULT 10.00",
] as $col => $def) {
    try { $pdo->exec("ALTER TABLE `commissary_oic_report` ADD COLUMN `$col` $def"); } catch (Throwable $ignored) {}
}

// ── One-time seed from COMMI_OIC_REPORT__2026.xlsx (30 sheets) ────
// This must run at most once, ever — checking "does the table have
// rows right now" is the wrong test, because deleting all rows also
// makes that count 0, which silently re-imported the same 30 sheets
// every time someone cleared the report. A small flags table records
// that the import already happened, independent of whatever rows
// exist afterward, so deleting data stays deleted.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_seed_log` (
        `seed_key`   varchar(100) NOT NULL,
        `seeded_at`  timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`seed_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $seedKey  = 'commissary_oic_report_2026';
    $checkStmt = $pdo->prepare("SELECT 1 FROM `app_seed_log` WHERE seed_key=?");
    $checkStmt->execute([$seedKey]);
    $alreadySeeded = (bool)$checkStmt->fetchColumn();

    if (!$alreadySeeded) {
        require_once __DIR__ . '/commissary_oic_report_seed.php';
        if (!empty($SEED_COMMISSARY_OIC_2026)) {
            $cols = ['store_name','report_date','pos_reading','store_gulay','samgyup_sales','grab_sales',
                     'others_walkin','grab_discount','walkin_discount','supplier','gulay','direct_purchases',
                     'other_expenses','kitchen','frontline','overtime','saved_by'];
            $ph   = implode(',', array_fill(0, count($cols), '?'));
            $ins  = $pdo->prepare("INSERT INTO `commissary_oic_report` (" . implode(',', $cols) . ") VALUES ($ph)
                                    ON DUPLICATE KEY UPDATE report_date=report_date");
            foreach ($SEED_COMMISSARY_OIC_2026 as $r) {
                $ins->execute([
                    'Commissary', $r['report_date'], $r['pos_reading'], $r['store_gulay'], $r['samgyup_sales'],
                    $r['grab_sales'], $r['others_walkin'], $r['grab_discount'], $r['walkin_discount'],
                    $r['supplier'], $r['gulay'], $r['direct_purchases'], $r['other_expenses'],
                    $r['kitchen'], $r['frontline'], $r['overtime'], 'Seed Import',
                ]);
            }
        }
        // Mark this seed as done permanently, whether or not the table
        // still has rows in it later.
        $pdo->prepare("INSERT INTO `app_seed_log` (seed_key) VALUES (?) ON DUPLICATE KEY UPDATE seed_key=seed_key")
            ->execute([$seedKey]);
    }
} catch (Throwable $ignored) {}

$fields = ['pos_reading','store_gulay','samgyup_sales','grab_sales','others_walkin',
           'grab_discount','walkin_discount','supplier','gulay','direct_purchases',
           'other_expenses','kitchen','frontline','overtime'];

// ── Handle POST (save / delete) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        header('Content-Type: application/json');
        $id        = (int)($_POST['id'] ?? 0);
        $saveDate  = $_POST['report_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saveDate)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid date.']); exit;
        }

        $data = ['store_name' => 'Commissary', 'report_date' => $saveDate, 'saved_by' => $user['name']];
        foreach ($fields as $f) $data[$f] = (float)($_POST[$f] ?? 0);
        $data['quota_target']   = (float)($_POST['quota_target']   ?? 117000);
        $data['cogs_threshold'] = (float)($_POST['cogs_threshold'] ?? 80);
        $data['mp_threshold']   = (float)($_POST['mp_threshold']   ?? 4);
        $data['np_threshold']   = (float)($_POST['np_threshold']   ?? 10);

        $cols      = array_keys($data);
        $dupUpdate = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", array_diff($cols, ['store_name','report_date'])));

        try {
            if ($id > 0) {
                $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
                $pdo->prepare("UPDATE commissary_oic_report SET $set WHERE id=?")
                    ->execute(array_merge(array_values($data), [$id]));
            } else {
                $sql = "INSERT INTO commissary_oic_report ("
                     . implode(',', array_map(fn($c) => "`$c`", $cols))
                     . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")"
                     . " ON DUPLICATE KEY UPDATE $dupUpdate";
                $pdo->prepare($sql)->execute(array_values($data));
            }
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM commissary_oic_report WHERE id=? AND store_name='Commissary'")->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }
}

// ── Filters — month/year tabs, plus an optional exact-date filter ──
// (?date=YYYY-MM-DD narrows the list below to just that one day,
// same pattern as Demic Lab's month/year tabs).
$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

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

// ── Metrics helper — Sales Revenue, Sales Discount, Gross Sales,
//    COGS, Manpower, Profit/Loss + Quota/COGS/MP/NP pass-fail ──
function calcOicMetrics(array $r): array {
    $g = fn($k) => (float)($r[$k] ?? 0);

    $salesRevenue = $g('pos_reading') + $g('store_gulay') + $g('samgyup_sales') + $g('grab_sales') + $g('others_walkin');
    $grabCf       = max($g('grab_sales') - $g('grab_discount'), 0) * 0.2;
    $salesDisc    = $g('grab_discount') + $grabCf + $g('walkin_discount');
    $grossSales   = $salesRevenue - $salesDisc;

    $totalCogs = $g('supplier') + $g('gulay') + $g('direct_purchases');
    $expCogs   = $totalCogs + $g('other_expenses');
    $manpower  = $g('kitchen') + $g('frontline') + $g('overtime');
    $grandExp  = $expCogs + $manpower;
    $profit    = $grossSales - $grandExp;

    $quotaTarget   = (float)($r['quota_target']   ?? 117000);
    $cogsThreshold = (float)($r['cogs_threshold'] ?? 80);
    $mpThreshold   = (float)($r['mp_threshold']   ?? 4);
    $npThreshold   = (float)($r['np_threshold']   ?? 10);

    $cogsPct = $salesRevenue > 0 ? ($totalCogs / $salesRevenue) * 100 : 0;
    $mpPct   = $salesRevenue > 0 ? ($manpower  / $salesRevenue) * 100 : 0;
    $expPct  = $salesRevenue > 0 ? ($grandExp  / $salesRevenue) * 100 : 0;
    $npPct   = $salesRevenue > 0 ? ($profit    / $salesRevenue) * 100 : 0;

    $quotaPassed = $salesRevenue > $quotaTarget;
    $cogsPassed  = $cogsPct < $cogsThreshold;
    $mpPassed    = $mpPct   < $mpThreshold;
    $npPassed    = $npPct   > $npThreshold;

    return compact('salesRevenue','grabCf','salesDisc','grossSales','totalCogs','expCogs',
                    'manpower','grandExp','profit','quotaTarget','cogsThreshold','mpThreshold',
                    'npThreshold','cogsPct','mpPct','expPct','npPct',
                    'quotaPassed','cogsPassed','mpPassed','npPassed');
}

// ── Excel (.xlsx) Export — whole filtered month, or a single day
//    (?only_id=) from a card's own Download button ─────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $onlyId = isset($_GET['only_id']) ? (int)$_GET['only_id'] : 0;
    if ($onlyId > 0) {
        $s = $pdo->prepare("SELECT * FROM commissary_oic_report WHERE id=? AND store_name='Commissary'");
        $s->execute([$onlyId]);
        $row0 = $s->fetch();
        $exportRows = $row0 ? [$row0] : [];
        $filename = 'Commissary_OIC_' . ($row0 ? date('F_j_Y', strtotime($row0['report_date'])) : 'Report') . '.xlsx';
        $subtitle = $row0 ? date('F j, Y', strtotime($row0['report_date'])) : '';
    } else {
        $es = $pdo->prepare("SELECT * FROM commissary_oic_report WHERE store_name='Commissary' AND YEAR(report_date)=? AND MONTH(report_date)=? ORDER BY report_date ASC");
        $es->execute([$fYear, $fMonth]);
        $exportRows = $es->fetchAll();
        $filename = 'Commissary_OIC_' . $monthNames[$fMonth] . '_' . $fYear . '.xlsx';
        $subtitle = $monthNames[$fMonth] . ' ' . $fYear;
    }

    $n = fn($v) => number_format($v, 2, '.', '');
    $xlsx = new SimpleXlsxWriter(branchAccentColor('Commissary'));
    $xlsx->title('THE COMMISSARY — OIC DAILY REPORT');
    $xlsx->subtitle($subtitle);
    $xlsx->blank();

    foreach ($exportRows as $r) {
        $m = calcOicMetrics($r);

        $xlsx->sectionRow(date('F j, Y', strtotime($r['report_date'])));
        $xlsx->header(['Line Item', 'Amount', '%', 'Flag']);

        $xlsx->sectionRow('SALES REVENUE (POS READING)');
        $xlsx->row(['POS Reading', $n((float)$r['pos_reading']), '', '']);
        $xlsx->row(['Store Gulay', $n((float)$r['store_gulay']), '', '']);
        $xlsx->row(['Samgyup Sales', $n((float)$r['samgyup_sales']), '', '']);
        $xlsx->row(['Grab Sales', $n((float)$r['grab_sales']), '', '']);
        $xlsx->row(['Others (Walk In/Delivery)', $n((float)$r['others_walkin']), '', '']);
        $xlsx->totalRow(['SALES REVENUE', $n($m['salesRevenue']), '', $m['quotaPassed'] ? 'QUOTA PASSED' : 'QUOTA FAILED']);
        $xlsx->blank();

        $xlsx->sectionRow('SALES DISCOUNT');
        $xlsx->row(['Grab Discount', $n((float)$r['grab_discount']), '', '']);
        $xlsx->row(['Grab CF (auto 20%)', $n($m['grabCf']), '', '']);
        $xlsx->row(['Walk In Discount (senior & PWD)', $n((float)$r['walkin_discount']), '', '']);
        $xlsx->totalRow(['TOTAL SALES DISCOUNT', $n($m['salesDisc']), '', '']);
        $xlsx->blank();

        $xlsx->totalRow(['GROSS SALES', $n($m['grossSales']), '', '']);
        $xlsx->blank();

        $xlsx->sectionRow('COST OF GOODS SOLD');
        $xlsx->row(['Supplier', $n((float)$r['supplier']), '', '']);
        $xlsx->row(['Gulay', $n((float)$r['gulay']), '', '']);
        $xlsx->row(['Direct Purchases (COGS)', $n((float)$r['direct_purchases']), '', '']);
        $xlsx->totalRow(['TOTAL COGS', $n($m['totalCogs']), round($m['cogsPct'], 1) . '%', $m['cogsPassed'] ? 'COGS PASSED' : 'COGS FAILED']);
        $xlsx->row(['Other Expenses (Non-COGS)', $n((float)$r['other_expenses']), '', '']);
        $xlsx->totalRow(['TOTAL EXPENSES (COGS + OTHER)', $n($m['expCogs']), '', '']);
        $xlsx->blank();

        $xlsx->sectionRow('MANPOWER ON DUTY');
        $xlsx->row(['Kitchen', $n((float)$r['kitchen']), '', '']);
        $xlsx->row(['Frontline', $n((float)$r['frontline']), '', '']);
        $xlsx->row(['Overtime', $n((float)$r['overtime']), '', '']);
        $xlsx->totalRow(['TOTAL MANPOWER', $n($m['manpower']), round($m['mpPct'], 1) . '%', $m['mpPassed'] ? 'MP PASSED' : 'MP FAILED']);
        $xlsx->blank();

        $xlsx->sectionRow('TARGETS USED');
        $xlsx->row(['Quota Target', $n($m['quotaTarget'])]);
        $xlsx->row(['COGS Max %', $n($m['cogsThreshold']) . '%']);
        $xlsx->row(['MP Max %', $n($m['mpThreshold']) . '%']);
        $xlsx->row(['NP Min %', $n($m['npThreshold']) . '%']);
        $xlsx->blank();

        $xlsx->sectionRow('TOTALS');
        $xlsx->totalRow(['TOTAL EXPENSES', $n($m['grandExp']), round($m['expPct'], 1) . '%', '']);
        $xlsx->totalRow(['PROFIT (- LOSS)', $n($m['profit']), round($m['npPct'], 1) . '%', $m['npPassed'] ? 'NP PASSED' : 'NP FAILED']);
        $xlsx->blank();
        $xlsx->blank();
    }

    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([32, 16, 10, 16]);
    $xlsx->output($filename);
}

// ── Load this month's entries for the card list ─────────────
$reports = [];
try {
    if ($fDate !== '') {
        $ms = $pdo->prepare("SELECT * FROM commissary_oic_report WHERE store_name='Commissary' AND report_date=?");
        $ms->execute([$fDate]);
    } else {
        $ms = $pdo->prepare("SELECT * FROM commissary_oic_report WHERE store_name='Commissary' AND YEAR(report_date)=? AND MONTH(report_date)=? ORDER BY report_date DESC");
        $ms->execute([$fYear, $fMonth]);
    }
    $reports = $ms->fetchAll();
} catch (Throwable $ignored) {}

$pageTitle  = 'OIC Report';
$activePage = 'commissary_oic_report';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Commissary OIC Daily <span>Report</span></div>
    <div class="section-subtitle">THE COMMISSARY — store performance for <?= $monthNames[$fMonth] ?> <?= $fYear ?></div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <a href="?<?= http_build_query(array_merge($_GET, ['export_csv'=>1])) ?>" class="btn btn-ghost">⬇ Download All (Month)</a>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ New Entry</button>
  </div>
</div>

<!-- Month / Year tabs -->
<div class="card" style="padding:12px 18px;margin-bottom:18px">
  <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <?php for ($m = 1; $m <= 12; $m++): ?>
    <a href="?month=<?= $m ?>&year=<?= $fYear ?>"
       style="padding:5px 13px;border-radius:6px;font-size:.74rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?= ($fMonth === $m && $fDate === '') ? 'background:var(--accent);color:#031a12;' : 'color:var(--subtext2);' ?>">
      <?= substr($monthNames[$m], 0, 3) ?>
    </a>
    <?php endfor; ?>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <input type="date" id="jumpDate" class="form-control" style="padding:5px 10px;font-size:.74rem;max-width:150px"
             value="<?= htmlspecialchars($fDate) ?>"
             title="Filter the list below to a single date">
      <select onchange="window.location='?month=<?= $fMonth ?>&year='+this.value" class="form-control"
              style="padding:5px 10px;font-size:.74rem;font-weight:600;max-width:90px;
                     background:var(--surf2);border:1px solid rgba(34,211,165,.3);color:var(--accent)">
        <?php for ($y = 2050; $y >= 2020; $y--): ?>
        <option value="<?= $y ?>" <?= $fYear === $y ? 'selected' : '' ?>><?= $y ?></option>
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
  <a href="?month=<?= $fMonth ?>&year=<?= $fYear ?>" class="btn btn-ghost btn-sm">✕ Clear filter</a>
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
  <?php foreach ($reports as $r): $m = calcOicMetrics($r); ?>
  <div class="card oic-card-hover" style="padding:0;overflow:hidden" data-date="<?= $r['report_date'] ?>">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 20px;
                background:var(--surf2);border-bottom:1px solid var(--border);flex-wrap:wrap;gap:8px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:.92rem;font-weight:700"><?= date('F j, Y', strtotime($r['report_date'])) ?></div>
        <span style="font-family:var(--font-m);font-size:.64rem;color:var(--subtext);
                     background:var(--bg);padding:3px 9px;border-radius:20px;border:1px solid var(--border)">
          Commissary
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
        <?php foreach ([['QUOTA',$m['quotaPassed']],['COGS',$m['cogsPassed']],['MP',$m['mpPassed']],['NP',$m['npPassed']]] as [$lbl,$pass]): ?>
        <span style="font-family:var(--font-m);font-size:.62rem;padding:3px 9px;border-radius:20px;
              background:<?= $pass ? 'rgba(34,211,165,.12)' : 'rgba(248,113,113,.12)' ?>;
              color:<?= $pass ? 'var(--accent)' : 'var(--accent2)' ?>;
              border:1px solid <?= $pass ? 'rgba(34,211,165,.2)' : 'rgba(248,113,113,.2)' ?>">
          <?= $lbl ?> <?= $pass ? '✓ PASSED' : '✗ FAILED' ?>
        </span>
        <?php endforeach; ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.location='?<?= http_build_query(array_merge($_GET, ['export_csv'=>1,'only_id'=>$r['id']])) ?>'">⬇ Download</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="openEditModal(<?= $r['id'] ?>)">Edit</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="deleteReport(<?= $r['id'] ?>)">Del</button>
      </div>
    </div>

    <!-- Top 5 metrics -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);border-bottom:1px solid var(--border)">
      <?php
        $cells = [
          ['Sales Revenue',  '₱'.number_format($m['salesRevenue'],2), 'var(--text)',    'Target ₱'.number_format($m['quotaTarget'],0)],
          ['Sales Discount', '₱'.number_format($m['salesDisc'],2),    'var(--accent2)', null],
          ['Gross Sales',    '₱'.number_format($m['grossSales'],2),   'var(--accent2)', null],
          ['Total Expenses', '₱'.number_format($m['grandExp'],2),     'var(--accent3)', round($m['expPct'],1).'% of revenue'],
          ['Profit / Loss',  ($m['profit']<0?'−':'').'₱'.number_format(abs($m['profit']),2),
                             $m['profit']>=0?'var(--accent)':'var(--accent2)',
                             round($m['npPct'],1).'% of revenue'],
        ];
        foreach ($cells as $i=>[$lbl,$val,$clr,$sub]):
      ?>
      <div style="padding:13px 16px;<?= $i<4 ? 'border-right:1px solid var(--border);' : '' ?>
                  <?= $i===4 ? ($m['profit']>=0?'background:rgba(34,211,165,.03)':'background:rgba(248,113,113,.03)') : '' ?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--subtext);margin-bottom:4px"><?= $lbl ?></div>
        <div style="font-size:1rem;font-weight:700;color:<?= $clr ?>"><?= $val ?></div>
        <?php if ($sub): ?><div style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext);margin-top:2px"><?= $sub ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Detail breakdown -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr">
      <!-- Sales Revenue -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          Sales Revenue &nbsp;<span style="color:<?= $m['quotaPassed']?'var(--accent)':'var(--accent2)' ?>"><?= $m['quotaPassed']?'✓':'✗' ?></span>
        </div>
        <?php foreach ([['POS Reading',$r['pos_reading']],['Store Gulay',$r['store_gulay']],['Samgyup Sales',$r['samgyup_sales']],['Grab Sales',$r['grab_sales']],['Others (Walk In/Delivery)',$r['others_walkin']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?= $n ?></span><span>₱<?= number_format($a,2) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total</span><span>₱<?= number_format($m['salesRevenue'],2) ?></span>
        </div>
      </div>

      <!-- Sales Discount -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          Sales Discount
        </div>
        <?php foreach ([['Grab Discount',$r['grab_discount']],['Grab CF (auto 20%)',$m['grabCf']],['Walk In Discount (SC/PWD)',$r['walkin_discount']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?= $n ?></span><span>₱<?= number_format($a,2) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total</span><span>₱<?= number_format($m['salesDisc'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.72rem;font-weight:700;margin-top:6px;color:var(--accent)">
          <span>Gross Sales</span><span>₱<?= number_format($m['grossSales'],2) ?></span>
        </div>
      </div>

      <!-- COGS -->
      <div style="padding:12px 16px;border-right:1px solid var(--border)">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          COGS &nbsp;<span style="color:<?= $m['cogsPassed']?'var(--accent)':'var(--accent2)' ?>"><?= round($m['cogsPct'],1) ?>%</span>
        </div>
        <?php foreach ([['Supplier',$r['supplier']],['Gulay',$r['gulay']],['Direct Purchases',$r['direct_purchases']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?= $n ?></span><span>₱<?= number_format($a,2) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total COGS</span><span>₱<?= number_format($m['totalCogs'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-top:4px">
          <span style="color:var(--subtext2)">Other Expenses</span><span>₱<?= number_format($r['other_expenses'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:2px">
          <span>Total Exp (COGS+Other)</span><span>₱<?= number_format($m['expCogs'],2) ?></span>
        </div>
      </div>

      <!-- Manpower -->
      <div style="padding:12px 16px">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">
          Manpower on Duty &nbsp;<span style="color:<?= $m['mpPassed']?'var(--accent)':'var(--accent2)' ?>"><?= round($m['mpPct'],1) ?>%</span>
        </div>
        <?php foreach ([['Kitchen',$r['kitchen']],['Frontline',$r['frontline']],['Overtime',$r['overtime']]] as [$n,$a]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?= $n ?></span><span>₱<?= number_format($a,2) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
          <span>Total MP</span><span>₱<?= number_format($m['manpower'],2) ?></span>
        </div>
      </div>
    </div>

    <!-- Summary + Targets -->
    <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid var(--border)">
      <div style="padding:12px 16px;border-right:1px solid var(--border);background:<?= $m['profit']>=0?'rgba(34,211,165,.03)':'rgba(248,113,113,.03)' ?>">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">Summary</div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)">Total Expenses</span><span>₱<?= number_format($m['grandExp'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)">Exp % of Revenue</span><span><?= round($m['expPct'],1) ?>%</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.74rem;font-weight:700;margin-top:6px;padding-top:5px;border-top:1px solid var(--border)">
          <span style="color:<?= $m['profit']>=0?'var(--accent)':'var(--accent2)' ?>">
            <?= $m['profit']>=0?'PROFIT':'LOSS' ?> <?= round($m['npPct'],1) ?>%
          </span>
          <span style="color:<?= $m['profit']>=0?'var(--accent)':'var(--accent2)' ?>">
            ₱<?= number_format(abs($m['profit']),2) ?>
          </span>
        </div>
      </div>
      <div style="padding:12px 16px">
        <div style="font-family:var(--font-m);font-size:.58rem;text-transform:uppercase;letter-spacing:.07em;color:var(--subtext);margin-bottom:7px">Targets Used This Day</div>
        <?php foreach ([['Quota Target','₱'.number_format($m['quotaTarget'],0)],['COGS Max %',round($m['cogsThreshold'],1).'%'],['MP Max %',round($m['mpThreshold'],1).'%'],['NP Min %',round($m['npThreshold'],1).'%']] as [$n,$v]): ?>
        <div style="display:flex;justify-content:space-between;font-family:var(--font-m);font-size:.7rem;margin-bottom:2px">
          <span style="color:var(--subtext2)"><?= $n ?></span><span><?= $v ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:2rem;margin-bottom:12px;opacity:.3">📋</div>
  <?php if ($fDate !== ''): ?>
  <div style="font-family:var(--font-m);font-size:.8rem;color:var(--subtext)">No entry saved for <?= date('F j, Y', strtotime($fDate)) ?></div>
  <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
    <a href="?month=<?= $fMonth ?>&year=<?= $fYear ?>" class="btn btn-ghost">✕ Clear filter</a>
    <button class="btn btn-primary" onclick="openModal('addModal');document.querySelector('#addForm [name=report_date]').value='<?= $fDate ?>'">+ Add Entry for This Date</button>
  </div>
  <?php else: ?>
  <div style="font-family:var(--font-m);font-size:.8rem;color:var(--subtext)">No entries for <?= $monthNames[$fMonth] ?> <?= $fYear ?></div>
  <button class="btn btn-primary" style="margin-top:16px" onclick="openModal('addModal')">+ Add First Entry</button>
  <?php endif; ?>
</div>
<?php endif; ?>
</div><!-- /reportsListContainer -->

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-title">📋 New OIC Daily Report</div>
    <form id="addForm" onsubmit="return submitOicReport(event,'addForm')">
      <input type="hidden" name="action" value="save">
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Date *</label><input name="report_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label>Store</label><input class="form-control" value="Commissary" readonly style="opacity:.6;cursor:not-allowed"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🧾 Sales Revenue (POS Reading)</div>
      <div class="form-row">
        <div class="form-group"><label>POS Reading (₱)</label><input name="pos_reading" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Store Gulay (₱)</label><input name="store_gulay" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Samgyup Sales (₱)</label><input name="samgyup_sales" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Grab Sales (₱)</label><input name="grab_sales" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Others (Walk In/Delivery) (₱)</label><input name="others_walkin" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🏷️ Sales Discount</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Grab Discount (₱)</label><input name="grab_discount" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Walk In Discount (SC/PWD) (₱)</label><input name="walkin_discount" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div style="font-family:var(--font-m);font-size:.66rem;color:#b06060;margin:-10px 0 16px">↳ Grab CF is auto-computed as 20% of (Grab Sales − Grab Discount) when saved.</div>

      <div class="dr-section-hdr" style="--sc:#c0392b">📦 Cost of Goods Sold</div>
      <div class="form-row">
        <div class="form-group"><label>Supplier (₱)</label><input name="supplier" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Gulay (₱)</label><input name="gulay" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Direct Purchases (COGS) (₱)</label><input name="direct_purchases" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Other Expenses (Non-COGS) (₱)</label><input name="other_expenses" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">👥 Manpower on Duty</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Kitchen (₱)</label><input name="kitchen" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
        <div class="form-group"><label>Frontline (₱)</label><input name="frontline" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Overtime (₱)</label><input name="overtime" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value=""></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🎯 Targets (used for the Quota/COGS/MP/NP pass-fail badges)</div>
      <div class="form-row">
        <div class="form-group"><label>Quota Target (₱)</label><input name="quota_target" type="number" step="0.01" min="0" class="form-control" value="117000"></div>
        <div class="form-group"><label>COGS Max (%)</label><input name="cogs_threshold" type="number" step="0.1" min="0" max="100" class="form-control" value="80"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>MP Max (%)</label><input name="mp_threshold" type="number" step="0.1" min="0" max="100" class="form-control" value="4"></div>
        <div class="form-group"><label>NP Min (%)</label><input name="np_threshold" type="number" step="0.1" min="0" max="100" class="form-control" value="10"></div>
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
  <div class="modal" style="max-width:640px">
    <div class="modal-title" id="editModalTitle">✏️ Edit Report</div>
    <form id="editForm" onsubmit="return submitOicReport(event,'editForm')">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Date *</label><input name="report_date" id="edit_report_date" type="date" class="form-control" required></div>
        <div class="form-group"><label>Store</label><input class="form-control" value="Commissary" readonly style="opacity:.6;cursor:not-allowed"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🧾 Sales Revenue (POS Reading)</div>
      <div class="form-row">
        <div class="form-group"><label>POS Reading (₱)</label><input name="pos_reading" id="edit_pos_reading" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Store Gulay (₱)</label><input name="store_gulay" id="edit_store_gulay" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Samgyup Sales (₱)</label><input name="samgyup_sales" id="edit_samgyup_sales" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Grab Sales (₱)</label><input name="grab_sales" id="edit_grab_sales" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Others (Walk In/Delivery) (₱)</label><input name="others_walkin" id="edit_others_walkin" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🏷️ Sales Discount</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Grab Discount (₱)</label><input name="grab_discount" id="edit_grab_discount" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Walk In Discount (SC/PWD) (₱)</label><input name="walkin_discount" id="edit_walkin_discount" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">📦 Cost of Goods Sold</div>
      <div class="form-row">
        <div class="form-group"><label>Supplier (₱)</label><input name="supplier" id="edit_supplier" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Gulay (₱)</label><input name="gulay" id="edit_gulay" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Direct Purchases (COGS) (₱)</label><input name="direct_purchases" id="edit_direct_purchases" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Other Expenses (Non-COGS) (₱)</label><input name="other_expenses" id="edit_other_expenses" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">👥 Manpower on Duty</div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Kitchen (₱)</label><input name="kitchen" id="edit_kitchen" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>Frontline (₱)</label><input name="frontline" id="edit_frontline" type="number" step="0.01" min="0" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>Overtime (₱)</label><input name="overtime" id="edit_overtime" type="number" step="0.01" min="0" class="form-control"></div>
      </div>

      <div class="dr-section-hdr" style="--sc:#c0392b">🎯 Targets (used for the Quota/COGS/MP/NP pass-fail badges)</div>
      <div class="form-row">
        <div class="form-group"><label>Quota Target (₱)</label><input name="quota_target" id="edit_quota_target" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="form-group"><label>COGS Max (%)</label><input name="cogs_threshold" id="edit_cogs_threshold" type="number" step="0.1" min="0" max="100" class="form-control"></div>
      </div>
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group"><label>MP Max (%)</label><input name="mp_threshold" id="edit_mp_threshold" type="number" step="0.1" min="0" max="100" class="form-control"></div>
        <div class="form-group"><label>NP Min (%)</label><input name="np_threshold" id="edit_np_threshold" type="number" step="0.1" min="0" max="100" class="form-control"></div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<style>
.dr-section-hdr {
  font-family: var(--font-m); font-size: .62rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .1em;
  color: var(--sc, var(--subtext));
  margin-bottom: 10px; padding-bottom: 7px;
  border-bottom: 1px solid var(--border);
}

/* Whole-card hover lift, like the Demic Lab list */
.oic-card-hover { transition: box-shadow .15s, transform .15s; cursor: default; }
.oic-card-hover:hover { box-shadow: 0 6px 22px rgba(0,0,0,.08); transform: translateY(-1px); }

#addModal.modal-overlay, #editModal.modal-overlay {
  background: rgba(20, 20, 20, .45);
  backdrop-filter: blur(6px);
}
#addModal .modal, #editModal .modal { background: #ffffff; border: 1px solid #e3b9b9; }
#addModal .modal-title, #editModal .modal-title { border-bottom-color: #e3b9b9; color: #111111; }
#addModal .form-control, #editModal .form-control { background: #fff; border: 1px solid #e3b9b9; }
#addModal .form-control:focus, #editModal .form-control:focus { border-color: #c0392b; box-shadow: 0 0 0 3px rgba(192,57,43,.15); }
#addModal .form-control::placeholder, #editModal .form-control::placeholder { color: #b06060; opacity: .8; }
#addModal .form-control[readonly], #editModal .form-control[readonly] { background: #fbe4e4 !important; color: #b91c1c !important; }
#addModal .form-group label, #editModal .form-group label { color: #1a1a1a; }
#addModal .modal-actions, #editModal .modal-actions { border-top-color: #e3b9b9; }
</style>

<script>
function openModal(id){
  document.getElementById(id).classList.add('open');
  if (id === 'addModal') {
    const f = document.getElementById('addForm');
    if (f) f.reset();
  }
}
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => {
  if (e.target === o) o.classList.remove('open');
}));

// ── Load report data embedded in the page (no server round trip) ──
let reportsData = {};
(function loadReportsData(){
  const el = document.getElementById('reportsDataJson');
  reportsData = el ? JSON.parse(el.textContent || '{}') : {};
})();

const OIC_FIELDS = ['pos_reading','store_gulay','samgyup_sales','grab_sales','others_walkin',
  'grab_discount','walkin_discount','supplier','gulay','direct_purchases','other_expenses',
  'kitchen','frontline','overtime','quota_target','cogs_threshold','mp_threshold','np_threshold'];

function openEditModal(id) {
  const d = reportsData[id];
  if (!d) return;
  document.getElementById('edit_id').value = d.id;
  document.getElementById('editModalTitle').textContent =
    '✏️ Edit — ' + new Date(d.report_date + 'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
  document.getElementById('edit_report_date').value = d.report_date;
  OIC_FIELDS.forEach(f => {
    const el = document.getElementById('edit_' + f);
    if (el) el.value = d[f];
  });
  openModal('editModal');
}

function showToast(msg, isError) {
  let t = document.getElementById('oicToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'oicToast';
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

function submitOicReport(evt, formId) {
  evt.preventDefault();
  const form = document.getElementById(formId);
  const fd = new FormData(form);
  fd.set('ajax', '1');
  const btn = form.querySelector('button[type="submit"]');
  const origText = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  fetch('commissary_oic_report.php', { method: 'POST', body: fd })
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
  fetch('commissary_oic_report.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) { showToast('Report deleted.'); refreshReportsList(); }
      else { showToast((data && data.msg) || 'Delete failed.', true); }
    })
    .catch(() => showToast('Delete failed — check your connection.', true));
}

// Date filter — narrows the list below to just that one date's card
// (or an empty state if nothing's saved for it). Does not open any modal.
document.getElementById('jumpDate').addEventListener('change', function() {
  const val = this.value;
  if (!val) return;
  window.location = '?date=' + val;
});
</script>
</body>
</html>