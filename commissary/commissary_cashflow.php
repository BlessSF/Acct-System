<?php
// ============================================================
//  commissary_cashflow.php — Commissary Branch Cash Position Report
//  Two tabs, same layout, separate saved data:
//    1) Cash Position       (store_name = 'Commissary')
//    2) Mid Cash Position   (store_name = 'Commissary Mid')
//  Each tab: BANK BALANCES, ACCOUNTS RECEIVABLE, ACCOUNTS PAYABLE
//  (each a free-form list of named line items), a memo-only "less"
//  list (Owner's Pull-out, Loan Repayment, etc.), a Remarks box, and
//  NET = Bank Total + AR Total − AP Total.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create tables if not exist ─────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_cash_position` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `cp_date`     date NOT NULL,
    `cp_year`     int(4) NOT NULL,
    `cp_month`    tinyint(2) NOT NULL,
    `store_name`  varchar(50) NOT NULL DEFAULT 'Commissary',
    `remarks`     text,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_month` (`store_name`,`cp_year`,`cp_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_cash_position_items` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `cp_id`       int(11) NOT NULL,
    `section`     varchar(10) NOT NULL COMMENT 'bank | ar | ap | less',
    `label`       varchar(150) NOT NULL DEFAULT '',
    `amount`      decimal(14,2) NOT NULL DEFAULT 0.00,
    `sort_order`  int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_cp_id` (`cp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Personal withdrawal ledgers (Owner's Pull-out style records) — one
// continuous running list per person, not scoped to a single month.
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_personal_withdrawal` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `person`      varchar(30) NOT NULL COMMENT 'sir_manuel | maam_nikki',
    `pw_date`     date DEFAULT NULL,
    `particular`  varchar(150) NOT NULL DEFAULT '',
    `amount`      decimal(14,2) NOT NULL DEFAULT 0.00,
    `remarks`     varchar(255) NOT NULL DEFAULT '',
    `sort_order`  int(4) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_person` (`person`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

$SECTIONS = [
    'bank' => 'Bank Balances',
    'ar'   => 'Accounts Receivable',
    'ap'   => 'Accounts Payable',
    'less' => "Less (Owner's Pull-out, Loan Repayment, etc.)",
];

// ── Periods (tabs) ──────────────────────────────────────────
// 'end'/'mid' = the Cash Position style tabs (Bank/AR/AP/Less sections)
// 'sir_manuel'/'maam_nikki'/'owners_pullout' = continuous personal
//   withdrawal ledgers (Date · Particular · Amount · Remarks),
//   not scoped to one month
$PERIODS = [
    'end'            => ['label' => 'Cash Position',                        'icon' => '💰', 'store'  => 'Commissary',     'type' => 'cashpos'],
    'mid'            => ['label' => 'Mid Cash Position',                    'icon' => '📊', 'store'  => 'Commissary Mid', 'type' => 'cashpos'],
    'sir_manuel'     => ['label' => 'Personal Withdrawal 2026 (Sir Manuel)', 'icon' => '🧔', 'person' => 'sir_manuel',     'type' => 'withdrawal'],
    'maam_nikki'     => ['label' => 'Personal Withdrawal 2026 (Maam Nikki)', 'icon' => '👩', 'person' => 'maam_nikki',     'type' => 'withdrawal'],
    'owners_pullout' => ["label" => "Owner's Pull-out Record",              'icon' => '📤', 'person' => 'owners_pullout', 'type' => 'withdrawal'],
];

// ── Filters ───────────────────────────────────────────────
// A real, exact date (not just a month/year picker) — this is what
// gets saved as cp_date and shown everywhere in the UI/export.
$fDateStr = $_GET['date'] ?? '';
if ($fDateStr === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDateStr) || !strtotime($fDateStr)) {
    $fDateStr = date('Y-m-d');
}
$fYear  = (int)date('Y', strtotime($fDateStr));
$fMonth = (int)date('n', strtotime($fDateStr));

$fTab = $_GET['tab'] ?? 'end';
if (!isset($PERIODS[$fTab])) $fTab = 'end';

$lastDay     = $fDateStr;
$displayDate = date('n/j/Y', strtotime($fDateStr));

// ── Helper: fetch (or null) the parent row + its items grouped by section
function loadCashPosition(PDO $pdo, string $store, int $year, int $month): array {
    $p = $pdo->prepare("SELECT * FROM commissary_cash_position WHERE store_name=? AND cp_year=? AND cp_month=? LIMIT 1");
    $p->execute([$store, $year, $month]);
    $parent = $p->fetch() ?: null;

    $grouped = ['bank' => [], 'ar' => [], 'ap' => [], 'less' => []];
    if ($parent) {
        $i = $pdo->prepare("SELECT * FROM commissary_cash_position_items WHERE cp_id=? ORDER BY sort_order,id");
        $i->execute([$parent['id']]);
        foreach ($i->fetchAll() as $row) {
            $sec = $row['section'];
            if (isset($grouped[$sec])) $grouped[$sec][] = $row;
        }
    }
    return [$parent, $grouped];
}

// ── Helper: fetch all withdrawal rows for a person (whole running ledger) ──
function loadWithdrawalRows(PDO $pdo, string $person): array {
    $s = $pdo->prepare("SELECT * FROM commissary_personal_withdrawal WHERE person=? ORDER BY sort_order, pw_date, id");
    $s->execute([$person]);
    return $s->fetchAll();
}

// ── AJAX: Save everything (whole-pane save, one period at a time) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $period = $_POST['period'] ?? 'end';
        if (!isset($PERIODS[$period]) || $PERIODS[$period]['type'] !== 'cashpos') $period = 'end';
        $store = $PERIODS[$period]['store'];

        $remarks = trim($_POST['remarks'] ?? '');
        $items   = json_decode($_POST['items'] ?? '[]', true);
        if (!is_array($items)) $items = [];

        $existing = $pdo->prepare("SELECT id FROM commissary_cash_position WHERE store_name=? AND cp_year=? AND cp_month=?");
        $existing->execute([$store, $fYear, $fMonth]);
        $cpId = $existing->fetchColumn();

        if ($cpId) {
            $pdo->prepare("UPDATE commissary_cash_position SET cp_date=?, remarks=?, saved_by=? WHERE id=?")
                ->execute([$lastDay, $remarks, $user['name'], $cpId]);
            $pdo->prepare("DELETE FROM commissary_cash_position_items WHERE cp_id=?")->execute([$cpId]);
        } else {
            $pdo->prepare("INSERT INTO commissary_cash_position (cp_date,cp_year,cp_month,store_name,remarks,saved_by) VALUES (?,?,?,?,?,?)")
                ->execute([$lastDay, $fYear, $fMonth, $store, $remarks, $user['name']]);
            $cpId = (int)$pdo->lastInsertId();
        }

        $ins = $pdo->prepare("INSERT INTO commissary_cash_position_items (cp_id,section,label,amount,sort_order) VALUES (?,?,?,?,?)");
        $validSections = ['bank','ar','ap','less'];
        $order = ['bank'=>0,'ar'=>0,'ap'=>0,'less'=>0];
        $totals = ['bank'=>0,'ar'=>0,'ap'=>0,'less'=>0];
        foreach ($items as $it) {
            $sec = $it['section'] ?? '';
            if (!in_array($sec, $validSections, true)) continue;
            $label  = trim((string)($it['label'] ?? ''));
            $amount = round((float)($it['amount'] ?? 0), 2);
            if ($label === '' && $amount == 0) continue; // skip fully empty rows
            $ins->execute([$cpId, $sec, $label, $amount, $order[$sec]++]);
            $totals[$sec] += $amount;
        }

        $net = $totals['bank'] + $totals['ar'] - $totals['ap'];
        echo json_encode(['ok'=>true,'totals'=>$totals,'net'=>$net,'period'=>$period]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save a personal withdrawal ledger (whole-list save) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_withdrawal'])) {
    header('Content-Type: application/json');
    try {
        $period = $_POST['period'] ?? '';
        if (!isset($PERIODS[$period]) || $PERIODS[$period]['type'] !== 'withdrawal') {
            throw new Exception('Invalid withdrawal ledger tab');
        }
        $person = $PERIODS[$period]['person'];

        $rows = json_decode($_POST['rows'] ?? '[]', true);
        if (!is_array($rows)) $rows = [];

        $pdo->prepare("DELETE FROM commissary_personal_withdrawal WHERE person=?")->execute([$person]);

        $ins = $pdo->prepare("INSERT INTO commissary_personal_withdrawal (person,pw_date,particular,amount,remarks,sort_order,saved_by) VALUES (?,?,?,?,?,?,?)");
        $order = 0;
        $total = 0;
        foreach ($rows as $r) {
            $rawDate    = trim((string)($r['date'] ?? ''));
            $date       = ($rawDate !== '' && strtotime($rawDate)) ? date('Y-m-d', strtotime($rawDate)) : null;
            $particular = trim((string)($r['particular'] ?? ''));
            $amount     = round((float)($r['amount'] ?? 0), 2);
            $remarks    = trim((string)($r['remarks'] ?? ''));
            if ($date === null && $particular === '' && $amount == 0 && $remarks === '') continue; // skip empty rows
            $ins->execute([$person, $date, $particular, $amount, $remarks, $order++, $user['name']]);
            $total += $amount;
        }

        echo json_encode(['ok'=>true,'total'=>$total,'period'=>$period]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Excel (.xlsx) Export ─────────────────────────────────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $period = $_GET['period'] ?? 'end';
    if (!isset($PERIODS[$period])) $period = 'end';
    $meta  = $PERIODS[$period];
    $label = $meta['label'];

    if ($meta['type'] === 'withdrawal') {
        // ── Personal Withdrawal ledger export (Date · Particular · Amount · Remarks) ──
        $rows  = loadWithdrawalRows($pdo, $meta['person']);
        $total = array_sum(array_column($rows, 'amount'));

        $xlsx = new SimpleXlsxWriter(branchAccentColor('Commissary'));
        $xlsx->title('Commissary Branch — '.strtoupper($label));
        $xlsx->blank();
        $xlsx->header(['DATE','PARTICULAR','AMOUNT','REMARKS']);
        if (empty($rows)) {
            $xlsx->row(['(no entries)','','','']);
        }
        foreach ($rows as $r) {
            $dispDate = $r['pw_date'] ? date('n/j/Y', strtotime($r['pw_date'])) : '';
            $xlsx->row([$dispDate, $r['particular'], number_format((float)$r['amount'],2,'.',''), $r['remarks']]);
        }
        $xlsx->totalRow(['', 'TOTAL', number_format($total,2,'.',''), '']);
        $xlsx->blank();

        $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
        $xlsx->columnWidths([14,40,16,30]);
        $xlsx->output('commissary_'.$meta['person'].'_personal_withdrawal.xlsx');
        exit;
    }

    // ── Cash Position style export (Bank/AR/AP/Less sections) ──
    $store = $meta['store'];

    [$parent, $grouped] = loadCashPosition($pdo, $store, $fYear, $fMonth);
    $remarks = $parent['remarks'] ?? '';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Commissary'));
    $xlsx->title('Commissary Branch — '.$label.' Report');
    $xlsx->subtitle('AS OF: '.$displayDate);
    $xlsx->blank();

    $totals = ['bank'=>0,'ar'=>0,'ap'=>0,'less'=>0];
    foreach (['bank','ar','ap','less'] as $sec) {
        $rows = $grouped[$sec];
        $xlsx->sectionRow(strtoupper($SECTIONS[$sec]));
        $xlsx->header(['PARTICULAR','AMOUNT']);
        $secTotal = 0;
        if (empty($rows)) {
            $xlsx->row(['(no items)','']);
        }
        foreach ($rows as $r) {
            $xlsx->row([$r['label'], number_format((float)$r['amount'],2,'.','')]);
            $secTotal += (float)$r['amount'];
        }
        $xlsx->row(['TOTAL', number_format($secTotal,2,'.','')]);
        $totals[$sec] = $secTotal;
        $xlsx->blank();
    }

    $net = $totals['bank'] + $totals['ar'] - $totals['ap'];
    $xlsx->totalRow(['NET (Bank + AR − AP)', number_format($net,2,'.','')]);
    $xlsx->blank();

    if (trim((string)$remarks) !== '') {
        $xlsx->sectionRow('REMARKS');
        foreach (explode("\n", $remarks) as $line) $xlsx->row([$line]);
        $xlsx->blank();
    }

    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([40,18]);
    $fileTag = $period === 'mid' ? 'mid_cash_position' : 'cash_position';
    $xlsx->output('commissary_'.$fileTag.'_'.date('Y_m_d',strtotime($fDateStr)).'.xlsx');
}

// ── Load saved data for display (all tabs, so switching is instant) ──
$PDATA = [];
$WDATA = [];
foreach ($PERIODS as $key => $meta) {
    if ($meta['type'] === 'withdrawal') {
        $wRows = loadWithdrawalRows($pdo, $meta['person']);
        $WDATA[$key] = [
            'rows'  => $wRows,
            'total' => array_sum(array_column($wRows, 'amount')),
        ];
        continue;
    }
    [$parent, $grouped] = loadCashPosition($pdo, $meta['store'], $fYear, $fMonth);
    $totals = [];
    foreach (['bank','ar','ap','less'] as $sec) {
        $totals[$sec] = array_sum(array_column($grouped[$sec], 'amount'));
    }
    $net = $totals['bank'] + $totals['ar'] - $totals['ap'];
    $PDATA[$key] = [
        'remarks' => $parent['remarks'] ?? '',
        'grouped' => $grouped,
        'totals'  => $totals,
        'net'     => $net,
    ];
}

$fmt = fn($n) => number_format((float)$n, 2);

$pageTitle  = 'Cash Position Report';
$activePage = 'commissary_cashflow';
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

/* ── Tabs (same pattern as Purchases: Supplier / Cash) ── */
.pv-tabs { display:flex; gap:6px; margin-bottom:18px; border-bottom:2px solid var(--border); flex-wrap:wrap; }
.pv-tab {
  padding:9px 18px; font-family:var(--font-h); font-size:.82rem; font-weight:600;
  color:var(--subtext); background:none; border:none; cursor:pointer;
  border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s;
}
.pv-tab:hover { color:var(--accent); }
.pv-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.pv-pane { display:none; }
.pv-pane.active { display:block; }

.cf-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 8px; }
.cf-asof { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); margin-bottom: 16px; }
.cf-asof strong { color: var(--text); font-weight: 800; }

.cf-net-bar {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 14px 22px; margin-bottom: 18px; display: flex; align-items: center;
  justify-content: space-between; box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.cf-net-bar .nb-label { font-family: var(--font-m); font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--subtext); }
.cf-net-bar .nb-formula { font-family: var(--font-m); font-size: .62rem; color: var(--muted); margin-top: 2px; }
.cf-net-bar .nb-val { font-family: var(--font-m); font-size: 1.35rem; font-weight: 800; }
.cf-net-bar .nb-val.neg { color: #b91c1c; }
.cf-net-bar .nb-val.pos { color: var(--accent); }

/* ── Sections ── */
.cp-section { margin-bottom: 22px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.05); }
.cp-section-header { display: flex; align-items: center; justify-content: space-between; background: #1e3060; color: #fff; padding: 10px 18px; }
.cp-section.less .cp-section-header { background: #4a4a55; }
.cp-section-title { font-family: var(--font-m); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
.cp-section-total { font-family: var(--font-m); font-size: .88rem; font-weight: 800; color: #a5c8ff; }
.cp-section.less .cp-section-total { color: #d1d1db; }

.cp-table { width: 100%; border-collapse: collapse; }
.cp-table tbody tr { border-bottom: 1px solid #f0f2f5; }
.cp-table tbody tr:last-child { border-bottom: none; }
.cp-table td { padding: 6px 14px; vertical-align: middle; }
.cp-table td.amt-col { width: 170px; }
.cp-table td.act-col { width: 46px; }
.cp-inp { width: 100%; border: 1px solid transparent; background: transparent; font-family: var(--font-m); font-size: .8rem; color: var(--text); padding: 6px 8px; border-radius: 6px; outline: none; }
.cp-inp:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(15,123,92,.08); }
.cp-inp.amt { text-align: right; }
.cp-row-del { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; font-size: .72rem; }
.cp-row-del:hover { background: #fecaca; }
.cp-add-row-wrap { padding: 10px 14px; }
.cp-add-row-btn { font-family: var(--font-m); font-size: .72rem; font-weight: 700; padding: 6px 12px; border-radius: 7px; border: 1px dashed var(--border); background: transparent; color: var(--accent3); cursor: pointer; }
.cp-add-row-btn:hover { border-color: var(--accent); background: #f0fdf4; }
.cp-empty-note { padding: 16px; text-align: center; font-family: var(--font-m); font-size: .72rem; color: var(--muted); }

.cp-remarks { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,.05); }
.cp-remarks label { display: block; font-family: var(--font-m); font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--subtext); margin-bottom: 8px; }
.cp-remarks textarea { width: 100%; min-height: 90px; resize: vertical; font-family: var(--font-m); font-size: .8rem; color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; outline: none; }
.cp-remarks textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15,123,92,.08); }

.cf-save-status { font-family: var(--font-m); font-size: .72rem; color: var(--subtext); }
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }

/* ── Personal Withdrawal ledger (Date · Particular · Amount · Remarks) ── */
.pw-wrap { max-width: 980px; margin: 0 auto; }
.pw-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.05); }
.pw-card-header { display: flex; align-items: center; justify-content: space-between; background: #1e3060; color: #fff; padding: 12px 18px; }
.pw-card-title { font-family: var(--font-m); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
.pw-card-total { font-family: var(--font-m); font-size: .92rem; font-weight: 800; color: #a5c8ff; }
.pw-table { width: 100%; border-collapse: collapse; }
.pw-table thead th { background: #f5f7fa; color: var(--subtext); font-family: var(--font-m); font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; text-align: left; padding: 8px 14px; border-bottom: 1px solid var(--border); }
.pw-table thead th.amt-col { text-align: right; }
.pw-table tbody tr { border-bottom: 1px solid #f0f2f5; }
.pw-table tbody tr:last-child { border-bottom: none; }
.pw-table td { padding: 6px 14px; vertical-align: middle; }
.pw-table td.amt-col { width: 150px; }
.pw-table td.act-col { width: 46px; }
.pw-table .cp-inp.wdate { min-width: 130px; }
</style>

<!-- Page Header -->
<div class="cf-header-card">
  <div>
    <div class="eyebrow">Commissary Branch · Finance</div>
    <div class="title">Cash Position Report</div>
    <div class="subtitle">Bank Balances · Accounts Receivable · Accounts Payable</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;border-radius:20px;
               font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 Commissary
  </span>
</div>

<!-- Tabs: Cash Position | Mid Cash Position | Personal Withdrawal ledgers -->
<div class="pv-tabs">
  <?php foreach ($PERIODS as $key => $meta): ?>
  <button type="button" class="pv-tab <?= $fTab===$key ? 'active' : '' ?>" data-tab="<?= $key ?>" onclick="switchTab('<?= $key ?>')"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?></button>
  <?php endforeach; ?>
</div>

<!-- Controls -->
<div class="cf-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="date" name="date" class="form-control" style="max-width:170px" value="<?= htmlspecialchars($fDateStr) ?>" onchange="this.form.submit()">
    <input type="hidden" name="tab" id="cf-month-form-tab" value="<?= htmlspecialchars($fTab) ?>">
    <button type="button" class="btn btn-primary" onclick="saveCashPosition()">💾 Save</button>
    <a href="commissary_cashflow.php?export_csv=1&date=<?= urlencode($fDateStr) ?>&period=<?=$fTab?>" id="downloadLink" class="btn btn-ghost">⬇ Download Excel</a>
    <span id="saveStatus" class="cf-save-status"></span>
  </form>
</div>

<div class="cf-asof" id="asOfNote">As of <strong><?= htmlspecialchars($displayDate) ?></strong> · applies to Cash Position / Mid Cash Position</div>

<?php foreach ($PERIODS as $pKey => $pMeta):

  // ── Personal Withdrawal ledger tab (Date · Particular · Amount · Remarks) ──
  if ($pMeta['type'] === 'withdrawal'):
    $wRows  = $WDATA[$pKey]['rows'];
    $wTotal = $WDATA[$pKey]['total'];
?>
<div class="pv-pane <?= $fTab===$pKey ? 'active' : '' ?>" id="pane-<?= $pKey ?>">
  <div class="pw-wrap">
    <div class="pw-card">
      <div class="pw-card-header">
        <span class="pw-card-title"><?= htmlspecialchars(strtoupper($pMeta['label'])) ?></span>
        <span class="pw-card-total">TOTAL: <?= $fmt($wTotal) ?></span>
      </div>
      <table class="pw-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Particular</th>
            <th class="amt-col">Amount</th>
            <th>Remarks</th>
            <th class="act-col"></th>
          </tr>
        </thead>
        <tbody id="wrows_<?= $pKey ?>">
          <?php foreach ($wRows as $r): ?>
          <tr class="cp-row">
            <td><input class="cp-inp wdate" type="date" value="<?= htmlspecialchars($r['pw_date'] ?? '') ?>" oninput="markRowChanged(this)"></td>
            <td><input class="cp-inp wparticular" type="text" value="<?= htmlspecialchars($r['particular']) ?>" placeholder="Particular…" oninput="markRowChanged(this)"></td>
            <td class="amt-col"><input class="cp-inp amt wamount" type="number" step="0.01" value="<?= (float)$r['amount'] ?: '' ?>" placeholder="0.00" oninput="onWAmountInput(this,'<?= $pKey ?>')"></td>
            <td><input class="cp-inp wremarks" type="text" value="<?= htmlspecialchars($r['remarks']) ?>" placeholder="Remarks…" oninput="markRowChanged(this)"></td>
            <td class="act-col"><button class="cp-row-del" onclick="removeWRow(this,'<?= $pKey ?>')" title="Remove row">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (empty($wRows)): ?>
        <div class="cp-empty-note" id="wempty_<?= $pKey ?>">No withdrawals yet — click "+ Add Row" below</div>
      <?php endif; ?>
      <div class="cp-add-row-wrap">
        <button class="cp-add-row-btn" onclick="addWRow('<?= $pKey ?>')">+ Add Row</button>
      </div>
    </div>
  </div>
</div>
<?php
  // ── Cash Position style tab (Bank/AR/AP/Less sections) ──
  else:
  $d       = $PDATA[$pKey];
  $grouped = $d['grouped'];
  $totals  = $d['totals'];
  $net     = $d['net'];
  $remarks = $d['remarks'];
?>
<div class="pv-pane <?= $fTab===$pKey ? 'active' : '' ?>" id="pane-<?= $pKey ?>">

  <!-- Net bar -->
  <div class="cf-net-bar">
    <div>
      <div class="nb-label"><?= htmlspecialchars($pMeta['label']) ?> · Net</div>
      <div class="nb-formula">Bank Balances Total + Accounts Receivable Total − Accounts Payable Total</div>
    </div>
    <div class="nb-val <?= $net < 0 ? 'neg' : 'pos' ?>" id="netVal_<?= $pKey ?>"><?= $fmt($net) ?></div>
  </div>

  <div class="cf-wrap">

  <?php foreach (['bank','ar','ap','less'] as $sec):
    $rows = $grouped[$sec];
  ?>
  <div class="cp-section <?= $sec==='less' ? 'less' : '' ?>" data-section="<?= $sec ?>">
    <div class="cp-section-header">
      <span class="cp-section-title"><?= htmlspecialchars($SECTIONS[$sec]) ?></span>
      <span class="cp-section-total" id="total_<?= $pKey ?>_<?= $sec ?>"><?= $fmt($totals[$sec]) ?></span>
    </div>
    <table class="cp-table">
      <tbody id="rows_<?= $pKey ?>_<?= $sec ?>">
        <?php foreach ($rows as $r): ?>
        <tr class="cp-row">
          <td><input class="cp-inp label" type="text" value="<?= htmlspecialchars($r['label']) ?>" placeholder="Particular…" oninput="markRowChanged(this)"></td>
          <td class="amt-col"><input class="cp-inp amt" type="number" step="0.01" value="<?= (float)$r['amount'] ?: '' ?>" placeholder="0.00" oninput="onAmountInput(this,'<?= $pKey ?>','<?= $sec ?>')"></td>
          <td class="act-col"><button class="cp-row-del" onclick="removeCpRow(this,'<?= $pKey ?>','<?= $sec ?>')" title="Remove row">✕</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($rows)): ?>
      <div class="cp-empty-note" id="empty_<?= $pKey ?>_<?= $sec ?>">No items yet — click "+ Add Row" below</div>
    <?php endif; ?>
    <div class="cp-add-row-wrap">
      <button class="cp-add-row-btn" onclick="addCpRow('<?= $pKey ?>','<?= $sec ?>')">+ Add Row</button>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="cp-remarks">
    <label>Remarks</label>
    <textarea id="remarksInput_<?= $pKey ?>" placeholder="e.g. decrease due to loan repayment, owner's pull-out, equipment purchase…"><?= htmlspecialchars($remarks) ?></textarea>
  </div>

  </div><!-- /cf-wrap -->
</div><!-- /pv-pane -->
<?php endif; endforeach; ?>

<script>
let CURRENT_TAB  = '<?= $fTab ?>';
let CURRENT_DATE = '<?= $fDateStr ?>';
const PERIOD_TYPES  = <?= json_encode(array_map(fn($p) => $p['type'], $PERIODS)) ?>;
const PERIOD_LABELS = <?= json_encode(array_map(fn($p) => $p['label'], $PERIODS)) ?>;

function gv(el) { return parseFloat(el?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

// ── Tab switching (same pattern as Purchases: Supplier / Cash) ──
function switchTab(period) {
  CURRENT_TAB = period;
  document.querySelectorAll('.pv-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === period));
  document.querySelectorAll('.pv-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + period));
  const tabField = document.getElementById('cf-month-form-tab');
  if (tabField) tabField.value = period;
  const dl = document.getElementById('downloadLink');
  if (dl) dl.href = dl.href.replace(/period=[a-z_]+/, 'period=' + period);
  const status = document.getElementById('saveStatus');
  if (status) status.textContent = '';
  const asOf = document.getElementById('asOfNote');
  if (asOf) asOf.style.display = (PERIOD_TYPES[period] === 'withdrawal') ? 'none' : '';
}

function markRowChanged(el) {
  const status = document.getElementById('saveStatus');
  if (status) status.textContent = '';
}

function onAmountInput(el, period, section) {
  markRowChanged(el);
  refreshSectionTotal(period, section);
  refreshNet(period);
}

function refreshSectionTotal(period, section) {
  let tot = 0;
  document.querySelectorAll(`#rows_${period}_${section} .amt`).forEach(el => tot += gv(el));
  const el = document.getElementById(`total_${period}_${section}`);
  if (el) el.textContent = fmt(tot);
  return tot;
}

function refreshNet(period) {
  const bank = refreshSectionTotal(period, 'bank');
  const ar   = refreshSectionTotal(period, 'ar');
  const ap   = refreshSectionTotal(period, 'ap');
  refreshSectionTotal(period, 'less');
  const net = bank + ar - ap;
  const el = document.getElementById(`netVal_${period}`);
  el.textContent = fmt(net);
  el.className = 'nb-val ' + (net < 0 ? 'neg' : 'pos');
}

function addCpRow(period, section) {
  const empty = document.getElementById(`empty_${period}_${section}`);
  if (empty) empty.remove();

  const tr = document.createElement('tr');
  tr.className = 'cp-row';
  tr.innerHTML = `
    <td><input class="cp-inp label" type="text" placeholder="Particular…" oninput="markRowChanged(this)"></td>
    <td class="amt-col"><input class="cp-inp amt" type="number" step="0.01" placeholder="0.00" oninput="onAmountInput(this,'${period}','${section}')"></td>
    <td class="act-col"><button class="cp-row-del" onclick="removeCpRow(this,'${period}','${section}')" title="Remove row">✕</button></td>`;
  document.getElementById(`rows_${period}_${section}`).appendChild(tr);
  tr.querySelector('input[type=text]').focus();
}

function removeCpRow(btn, period, section) {
  btn.closest('tr').remove();
  refreshSectionTotal(period, section);
  refreshNet(period);
  const tbody = document.getElementById(`rows_${period}_${section}`);
  if (!tbody.children.length) {
    const wrap = document.createElement('div');
    wrap.className = 'cp-empty-note';
    wrap.id = `empty_${period}_${section}`;
    wrap.textContent = 'No items yet — click "+ Add Row" below';
    tbody.closest('.cp-section').querySelector('.cp-add-row-wrap').insertAdjacentElement('beforebegin', wrap);
  }
}

// ── Personal Withdrawal ledger rows ──
function onWAmountInput(el, period) { markRowChanged(el); }

function addWRow(period) {
  const empty = document.getElementById(`wempty_${period}`);
  if (empty) empty.remove();

  const tr = document.createElement('tr');
  tr.className = 'cp-row';
  tr.innerHTML = `
    <td><input class="cp-inp wdate" type="date" oninput="markRowChanged(this)"></td>
    <td><input class="cp-inp wparticular" type="text" placeholder="Particular…" oninput="markRowChanged(this)"></td>
    <td class="amt-col"><input class="cp-inp amt wamount" type="number" step="0.01" placeholder="0.00" oninput="onWAmountInput(this,'${period}')"></td>
    <td><input class="cp-inp wremarks" type="text" placeholder="Remarks…" oninput="markRowChanged(this)"></td>
    <td class="act-col"><button class="cp-row-del" onclick="removeWRow(this,'${period}')" title="Remove row">✕</button></td>`;
  document.getElementById(`wrows_${period}`).appendChild(tr);
  tr.querySelector('input[type=date]').focus();
}

function removeWRow(btn, period) {
  btn.closest('tr').remove();
  const tbody = document.getElementById(`wrows_${period}`);
  if (!tbody.children.length) {
    const wrap = document.createElement('div');
    wrap.className = 'cp-empty-note';
    wrap.id = `wempty_${period}`;
    wrap.textContent = 'No withdrawals yet — click "+ Add Row" below';
    tbody.closest('.pw-card').querySelector('.cp-add-row-wrap').insertAdjacentElement('beforebegin', wrap);
  }
}

async function saveCashPosition() {
  const period = CURRENT_TAB;
  if (PERIOD_TYPES[period] === 'withdrawal') {
    return saveWithdrawal(period);
  }

  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const items = [];
  ['bank','ar','ap','less'].forEach(section => {
    document.querySelectorAll(`#rows_${period}_${section} .cp-row`).forEach(row => {
      const label  = row.querySelector('.label').value.trim();
      const amount = gv(row.querySelector('.amt'));
      if (label === '' && amount === 0) return;
      items.push({ section, label, amount });
    });
  });

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('period', period);
  fd.append('remarks', document.getElementById('remarksInput_' + period).value);
  fd.append('items', JSON.stringify(items));

  try {
    const url = 'commissary_cashflow.php?date=' + encodeURIComponent(CURRENT_DATE) + '&tab=' + encodeURIComponent(period);
    const res  = await fetch(url, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      const label = PERIOD_LABELS[period] || 'Cash Position Report';
      showToast('✓ ' + label + ' saved', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      status.textContent = '❌ Error';
    }
  } catch(e) {
    showToast('❌ Network error', 'error');
  }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

async function saveWithdrawal(period) {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';

  const rows = [];
  document.querySelectorAll(`#wrows_${period} .cp-row`).forEach(row => {
    const date       = row.querySelector('.wdate').value;
    const particular = row.querySelector('.wparticular').value.trim();
    const amount     = gv(row.querySelector('.wamount'));
    const remarks    = row.querySelector('.wremarks').value.trim();
    if (!date && particular === '' && amount === 0 && remarks === '') return;
    rows.push({ date, particular, amount, remarks });
  });

  const fd = new FormData();
  fd.append('ajax_save_withdrawal', '1');
  fd.append('period', period);
  fd.append('rows', JSON.stringify(rows));

  try {
    const url = 'commissary_cashflow.php?date=' + encodeURIComponent(CURRENT_DATE) + '&tab=' + encodeURIComponent(period);
    const res  = await fetch(url, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      status.textContent = '✓ Saved';
      status.style.color = 'var(--accent)';
      const totalEl = document.querySelector(`#pane-${period} .pw-card-total`);
      if (totalEl) totalEl.textContent = 'TOTAL: ' + fmt(data.total);
      const label = PERIOD_LABELS[period] || 'Personal Withdrawal';
      showToast('✓ ' + label + ' saved', 'success');
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
</script>
</body>
</html>
