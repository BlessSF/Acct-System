<?php
// ============================================================
//  h_check_report.php — H Branch Check Releasing Report
//  Mirrors the "H BREAKFAST TO BAR RELEASING CHECK" worksheet:
//  a running list of checks to be released (Date / Vendor /
//  Amount), a "Released Check" sub-table that auto-pulls the
//  rows marked Released, and a summary strip (Bank Balance →
//  Available Fund → For Releasing → Extra Fund).
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

$BUSINESS_NAME = 'H BREAKFAST TO BAR';

// ── Create tables if not exists ───────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `h_check_report` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `check_date`    date DEFAULT NULL,
    `cr_year`       int(4) NOT NULL,
    `cr_month`      tinyint(2) NOT NULL,
    `store_name`    varchar(50) NOT NULL DEFAULT 'H',
    `vendor`        varchar(200) DEFAULT NULL,
    `amount`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `is_released`   tinyint(1) NOT NULL DEFAULT 0,
    `remarks`       varchar(255) DEFAULT NULL,
    `sort_order`    int(4) DEFAULT 0,
    `saved_by`      varchar(100) DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Add `remarks` column if this table already existed before this feature (safe no-op otherwise)
try {
    $pdo->exec("ALTER TABLE h_check_report ADD COLUMN remarks varchar(255) DEFAULT NULL AFTER is_released");
} catch (Throwable $e) { /* column already exists — ignore */ }

$pdo->exec("CREATE TABLE IF NOT EXISTS `h_check_report_summary` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `cr_year`       int(4) NOT NULL,
    `cr_month`      tinyint(2) NOT NULL,
    `store_name`    varchar(50) NOT NULL DEFAULT 'H',
    `bank_balance`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`      varchar(100) DEFAULT NULL,
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_month` (`store_name`,`cr_year`,`cr_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

// ── AJAX: Save row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $date     = $_POST['check_date'] ?? null;
        if ($date === '') $date = null;
        $vendor   = trim($_POST['vendor'] ?? '');
        $amount   = (float)($_POST['amount'] ?? 0);
        $released = isset($_POST['is_released']) && $_POST['is_released'] == '1' ? 1 : 0;
        $remarks  = trim($_POST['remarks'] ?? '');
        $rowId    = (int)($_POST['row_id'] ?? 0);

        if ($rowId > 0) {
            $pdo->prepare("UPDATE h_check_report SET check_date=?, vendor=?, amount=?, is_released=?, remarks=?, saved_by=? WHERE id=? AND store_name='H'")
                ->execute([$date,$vendor,$amount,$released,$remarks,$user['name'],$rowId]);
            echo json_encode(['ok'=>true,'id'=>$rowId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO h_check_report (check_date,cr_year,cr_month,store_name,vendor,amount,is_released,remarks,sort_order,saved_by) VALUES (?,?,?,'H',?,?,?,?,?,?)");
            $stmt->execute([$date,$fYear,$fMonth,$vendor,$amount,$released,$remarks,9999,$user['name']]);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['row_id'] ?? 0);
        $pdo->prepare("DELETE FROM h_check_report WHERE id=? AND store_name='H'")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Save Bank Balance (summary) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_summary'])) {
    header('Content-Type: application/json');
    try {
        $bankBal = (float)($_POST['bank_balance'] ?? 0);
        $pdo->prepare("INSERT INTO h_check_report_summary (cr_year,cr_month,store_name,bank_balance,saved_by)
                        VALUES (?,?,'H',?,?)
                        ON DUPLICATE KEY UPDATE bank_balance=VALUES(bank_balance), saved_by=VALUES(saved_by)")
            ->execute([$fYear,$fMonth,$bankBal,$user['name']]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $rows = $pdo->prepare("SELECT * FROM h_check_report WHERE store_name='H' AND cr_year=? AND cr_month=? ORDER BY sort_order ASC, id ASC");
    $rows->execute([$fYear,$fMonth]);
    $data = $rows->fetchAll();

    $sumStmt = $pdo->prepare("SELECT * FROM h_check_report_summary WHERE store_name='H' AND cr_year=? AND cr_month=?");
    $sumStmt->execute([$fYear,$fMonth]);
    $sumRow = $sumStmt->fetch();
    $bankBal = (float)($sumRow['bank_balance'] ?? 0);

    $total = 0; $totalReleased = 0;
    foreach ($data as $r) { $total += (float)$r['amount']; if ($r['is_released']) $totalReleased += (float)$r['amount']; }
    $availableFund = $bankBal - $totalReleased;
    $forReleasing  = $total;
    $extraFund     = $availableFund - $forReleasing;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="h_check_report_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,[$BUSINESS_NAME.' — RELEASING CHECK']);
    fputcsv($out,[date('F Y', mktime(0,0,0,$fMonth,1,$fYear))]);
    fputcsv($out,['']);
    fputcsv($out,['DATE','VENDOR','AMOUNT','RELEASED','REMARKS']);
    foreach ($data as $r) {
        fputcsv($out,[
            $r['check_date'] ? date('m/d/Y', strtotime($r['check_date'])) : '',
            $r['vendor'],
            number_format($r['amount'],2,'.',''),
            $r['is_released'] ? 'Yes' : 'No',
            $r['remarks'] ?? '',
        ]);
    }
    fputcsv($out,['','TOTAL', number_format($total,2,'.','')]);
    fputcsv($out,['']);
    fputcsv($out,['RELEASED CHECK']);
    fputcsv($out,['','VENDOR','AMOUNT','REMARKS']);
    foreach ($data as $r) {
        if (!$r['is_released']) continue;
        fputcsv($out,['', $r['vendor'], number_format($r['amount'],2,'.',''), $r['remarks'] ?? '']);
    }
    fputcsv($out,['','TOTAL RELEASED', number_format($totalReleased,2,'.','')]);
    fputcsv($out,['']);
    fputcsv($out,['BANK BALANCE', number_format($bankBal,2,'.','')]);
    fputcsv($out,['AVAILABLE FUND', number_format($availableFund,2,'.','')]);
    fputcsv($out,['FOR RELEASING', number_format($forReleasing,2,'.','')]);
    fputcsv($out,['EXTRA FUND', number_format($extraFund,2,'.','')]);
    fputcsv($out,['']);
    fputcsv($out,['Generated by SalesHub', date('Y-m-d H:i:s')]);
    fclose($out);
    exit;
}

// ── Load rows for selected month ──────────────────────────
$stmt = $pdo->prepare("SELECT * FROM h_check_report WHERE store_name='H' AND cr_year=? AND cr_month=? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$fYear,$fMonth]);
$rows = $stmt->fetchAll();

$total = 0; $totalReleased = 0;
foreach ($rows as $r) { $total += (float)$r['amount']; if ($r['is_released']) $totalReleased += (float)$r['amount']; }

// ── Load Bank Balance (summary) ─────────────────────────────
$sumStmt = $pdo->prepare("SELECT * FROM h_check_report_summary WHERE store_name='H' AND cr_year=? AND cr_month=?");
$sumStmt->execute([$fYear,$fMonth]);
$sumRow  = $sumStmt->fetch();
$bankBal = (float)($sumRow['bank_balance'] ?? 0);

$availableFund = $bankBal - $totalReleased;
$forReleasing  = $total;
$extraFund     = $availableFund - $forReleasing;

$pageTitle  = 'Check Releasing Report';
$activePage = 'h_check_report';
include '../layout.php';
?>

<style>
.crpt-header {
  background: linear-gradient(135deg, #1a2e1a 0%, #0f3d2e 100%);
  border-radius: var(--radius);
  padding: 20px 26px 16px;
  margin-bottom: 18px;
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.crpt-header-left .eyebrow {
  font-family: var(--font-m); font-size: .58rem;
  text-transform: uppercase; letter-spacing: .12em;
  color: rgba(255,255,255,.45); margin-bottom: 4px;
}
.crpt-header-left .title {
  font-size: 1.1rem; font-weight: 800;
  color: #fff; letter-spacing: -.02em;
}
.crpt-header-left .subtitle {
  font-family: var(--font-m); font-size: .68rem;
  color: rgba(255,255,255,.5); margin-top: 3px;
}

.crpt-kpi {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px; margin-bottom: 18px;
}
.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
}
.kpi-label {
  font-family: var(--font-m); font-size: .58rem;
  text-transform: uppercase; letter-spacing: .08em;
  color: var(--subtext); margin-bottom: 5px;
}
.kpi-val {
  font-family: var(--font-m); font-size: 1.15rem;
  font-weight: 800;
}
.kpi-val.green  { color: var(--accent); }
.kpi-val.blue   { color: #3b82f6; }
.kpi-val.gold   { color: var(--accent3); }
.kpi-val.purple { color: #8b5cf6; }

.crpt-controls {
  display: flex; gap: 10px; align-items: center;
  flex-wrap: wrap; margin-bottom: 16px;
}

.crpt-tbl { width: 100%; border-collapse: collapse; }
.crpt-tbl thead th {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .58rem;
  font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; padding: 10px 12px;
  border: 1px solid #2d5480; text-align: center;
  white-space: nowrap;
}
.crpt-tbl thead th.col-date   { min-width: 130px; text-align: left; }
.crpt-tbl thead th.col-vendor { min-width: 220px; text-align: left; }
.crpt-tbl thead th.col-num    { min-width: 130px; }
.crpt-tbl thead th.col-rel    { min-width: 80px; background: #1a4a2e; }
.crpt-tbl thead th.col-remarks { min-width: 180px; text-align: left; }
.crpt-tbl thead th.col-act    { min-width: 80px; background: #374151; }

.crpt-tbl tbody tr { transition: background .1s; }
.crpt-tbl tbody tr:hover { background: #f8fafb; }
.crpt-tbl tbody tr.is-released { background: #f0fdf4; }
.crpt-tbl tbody td {
  border: 1px solid var(--border);
  padding: 0; vertical-align: middle;
}
.crpt-tbl tbody td.td-date { padding: 6px 10px; }
.crpt-tbl tbody td.td-rel { text-align: center; padding: 6px; }

.crpt-input {
  width: 100%; padding: 8px 10px;
  font-family: var(--font-m); font-size: .78rem;
  color: var(--text); background: transparent;
  border: none; outline: none;
  transition: background .15s, box-shadow .15s;
}
.crpt-input.num { text-align: right; }
.crpt-input:focus {
  background: #fff;
  box-shadow: inset 0 0 0 2px var(--accent);
}

.date-input {
  width: 100%; padding: 7px 10px;
  font-family: var(--font-m); font-size: .76rem;
  border: none; outline: none; background: transparent;
  color: var(--text);
}
.date-input:focus { background: #f8f9fb; }

.crpt-tbl tfoot td {
  background: #1e3a5f; color: #fff;
  font-family: var(--font-m); font-size: .72rem;
  font-weight: 800; padding: 10px 12px;
  border: 1px solid #2d5480; text-align: right;
  white-space: nowrap;
}
.crpt-tbl tfoot td.lbl { text-align: left; text-transform: uppercase; letter-spacing: .06em; }
.crpt-tbl tfoot td.total-tot { background: #1a4a2e; color: #6ee7b7; font-size: .82rem; }

.btn-save-row {
  padding: 5px 12px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 6px;
  font-family: var(--font-m); font-size: .65rem;
  font-weight: 600; cursor: pointer;
  transition: background .15s;
}
.btn-save-row:hover { background: #0a6649; }
.btn-del-row {
  padding: 5px 8px;
  background: transparent; color: var(--accent2);
  border: 1px solid rgba(220,53,69,.2);
  border-radius: 6px; font-size: .65rem;
  cursor: pointer; transition: background .15s;
}
.btn-del-row:hover { background: rgba(220,53,69,.07); }
.row-status {
  font-family: var(--font-m); font-size: .62rem;
  color: var(--accent); display: none; margin-top: 3px;
}

.crpt-released-card {
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: var(--radius); padding: 0; overflow: hidden;
  margin: 22px 0 18px;
}
.crpt-released-head {
  background: #0f3d2e; padding: 9px 16px;
  font-family: var(--font-m); font-size: .6rem;
  text-transform: uppercase; letter-spacing: .1em; color: #fff;
  display:flex; align-items:center; gap:8px;
}
.crpt-released-tbl { width: 100%; border-collapse: collapse; }
.crpt-released-tbl th {
  background: #dcfce7; color: #14532d;
  font-family: var(--font-m); font-size: .58rem;
  text-transform: uppercase; letter-spacing: .06em;
  padding: 8px 14px; text-align: left; border-bottom: 1px solid #bbf7d0;
}
.crpt-released-tbl th.num { text-align: right; }
.crpt-released-tbl td {
  padding: 8px 14px; font-family: var(--font-m); font-size: .78rem;
  border-bottom: 1px solid #dcfce7; color: var(--text);
}
.crpt-released-tbl td.num { text-align: right; }
.crpt-released-tbl tfoot td {
  background: #bbf7d0; color: #14532d; font-weight: 800;
  font-family: var(--font-m); font-size: .8rem;
}
.crpt-released-empty {
  padding: 24px; text-align: center; color: var(--muted);
  font-family: var(--font-m); font-size: .74rem;
}

.crpt-summary {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
}
.crpt-summary-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; border-bottom: 1px solid var(--border);
}
.crpt-summary-row:last-child { border-bottom: none; }
.crpt-summary-label {
  font-family: var(--font-h); font-weight: 600; font-size: .85rem; color: var(--text);
}
.crpt-summary-val {
  font-family: var(--font-m); font-size: .95rem; font-weight: 800;
}
.crpt-summary-row.available { background: #eff6ff; }
.crpt-summary-row.available .crpt-summary-val { color: #1d4ed8; }
.crpt-summary-row.releasing { background: #fdf1c7; }
.crpt-summary-row.releasing .crpt-summary-val { color: #92400e; }
.crpt-summary-row.extra { background: #fdecec; }
.crpt-summary-row.extra .crpt-summary-val { color: #b91c1c; font-size: 1.15rem; }
.crpt-bank-input {
  width: 180px; padding: 8px 10px; text-align: right;
  font-family: var(--font-m); font-size: .85rem; font-weight: 700;
  border: 1px solid #fde68a; background: #fffbeb; color: #92400e;
  border-radius: 6px; outline: none;
}
.crpt-bank-input:focus { box-shadow: 0 0 0 2px var(--accent); }

@media (max-width: 700px) {
  .crpt-kpi { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- Header -->
<div class="crpt-header">
  <div class="crpt-header-left">
    <div class="eyebrow">H Branch · Check Releasing</div>
    <div class="title"><?= htmlspecialchars($BUSINESS_NAME) ?> — Releasing Check</div>
    <div class="subtitle">Check the "Released" box to move a row into Released Check · Extra Fund = Available Fund − For Releasing</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 12px;border-radius:20px;
                 font-family:var(--font-m);font-size:.65rem;font-weight:600">
      📌 H
    </span>
  </div>
</div>

<!-- KPI Strip -->
<div class="crpt-kpi">
  <div class="kpi-card" style="border-top:2px solid var(--accent)">
    <div class="kpi-label">Total (For Releasing)</div>
    <div class="kpi-val green" id="kpi_total"><?= number_format($total,2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid var(--accent2)">
    <div class="kpi-label">Total Released</div>
    <div class="kpi-val" style="color:var(--accent2)" id="kpi_released"><?= number_format($totalReleased,2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid #3b82f6">
    <div class="kpi-label">Available Fund</div>
    <div class="kpi-val blue" id="kpi_available"><?= number_format($availableFund,2) ?></div>
  </div>
  <div class="kpi-card" style="border-top:2px solid var(--accent3)">
    <div class="kpi-label">Extra Fund</div>
    <div class="kpi-val gold" id="kpi_extra"><?= number_format($extraFund,2) ?></div>
  </div>
</div>

<!-- Controls -->
<div class="crpt-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" id="filterForm">
    <select name="month" class="form-control" style="max-width:140px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
  </form>
  <button class="btn btn-primary" onclick="addRow()">+ Add Row</button>
  <button class="btn btn-primary" style="background:#1d4ed8" onclick="saveAllRows()" id="saveAllBtn">💾 Save All</button>
  <a href="h_check_report.php?export_csv=1&month=<?=$fMonth?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download CSV</a>
</div>

<!-- Main Table -->
<div class="card" style="padding:0;overflow-x:auto">
  <table class="crpt-tbl" id="crptTable">
    <thead>
      <tr>
        <th class="col-date">Date</th>
        <th class="col-vendor">Vendor</th>
        <th class="col-num">Amount</th>
        <th class="col-rel">Released</th>
        <th class="col-remarks">Remarks</th>
        <th class="col-act">Action</th>
      </tr>
    </thead>
    <tbody id="crptBody">
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
        <tr data-id="<?= $r['id'] ?>" class="<?= $r['is_released'] ? 'is-released' : '' ?>">
          <td class="td-date">
            <input type="date" class="date-input" value="<?= htmlspecialchars($r['check_date'] ?? '') ?>"
                   onchange="markDirty(this)">
          </td>
          <td><input type="text" class="crpt-input vendor-field" value="<?= htmlspecialchars($r['vendor'] ?? '') ?>"
                     placeholder="e.g. Supplier Payable" oninput="markDirty(this)"></td>
          <td><input type="number" step="0.01" class="crpt-input num" value="<?= $r['amount'] ?>"
                     placeholder="0.00" oninput="markDirty(this)"></td>
          <td class="td-rel"><input type="checkbox" class="rel-check" <?= $r['is_released'] ? 'checked' : '' ?> onchange="markDirty(this)"></td>
          <td><input type="text" class="crpt-input remarks-field" value="<?= htmlspecialchars($r['remarks'] ?? '') ?>"
                     placeholder="e.g. Paid via GCash" oninput="markDirty(this)"></td>
          <td style="padding:6px 8px;text-align:center">
            <div style="display:flex;flex-direction:column;gap:3px;align-items:center">
              <div style="display:flex;gap:4px">
                <button class="btn-save-row" onclick="saveRow(this)">Save</button>
                <button class="btn-del-row"  onclick="deleteRow(this)">✕</button>
              </div>
              <div class="row-status"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
      <tr id="emptyRow">
        <td colspan="6" style="text-align:center;padding:48px;color:var(--muted);
                                font-family:var(--font-m);font-size:.78rem">
          No check entries for <?= $months[$fMonth-1] ?> <?= $fYear ?> — click <strong>+ Add Row</strong>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="lbl" colspan="2">TOTAL</td>
        <td class="total-tot" id="foot_total"><?= number_format($total,2) ?></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:14px;display:flex;align-items:center;gap:10px">
  <button class="btn btn-primary" onclick="addRow()">+ Add Row</button>
  <button class="btn btn-primary" style="background:#1d4ed8" onclick="saveAllRows()">💾 Save All</button>
  <span id="saveAllStatus" style="font-family:var(--font-m);font-size:.7rem;color:var(--accent)"></span>
</div>

<!-- Released Check (auto, read-only) -->
<div class="crpt-released-card">
  <div class="crpt-released-head">✓ Released Check <span style="opacity:.6">— auto-filled from rows checked "Released" above</span></div>
  <table class="crpt-released-tbl">
    <thead>
      <tr><th>Vendor</th><th class="num">Amount</th><th>Remarks</th></tr>
    </thead>
    <tbody id="releasedBody">
      <?php $releasedRows = array_filter($rows, fn($r) => (int)$r['is_released'] === 1); ?>
      <?php if ($releasedRows): ?>
        <?php foreach ($releasedRows as $r): ?>
        <tr><td><?= htmlspecialchars($r['vendor'] ?: '—') ?></td><td class="num"><?= number_format($r['amount'],2) ?></td><td><?= htmlspecialchars($r['remarks'] ?? '') ?></td></tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr id="releasedEmptyRow"><td colspan="3" class="crpt-released-empty">No rows marked Released yet</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td>TOTAL RELEASED</td><td class="num" id="foot_released"><?= number_format($totalReleased,2) ?></td><td></td></tr>
    </tfoot>
  </table>
</div>

<!-- Summary -->
<div class="crpt-summary">
  <div class="crpt-summary-row">
    <div class="crpt-summary-label">Bank Balance</div>
    <div class="crpt-summary-val">
      <input type="number" step="0.01" class="crpt-bank-input" id="bank_balance"
             value="<?= number_format($bankBal,2,'.','') ?>" oninput="recalcSummary()" placeholder="0.00">
    </div>
  </div>
  <div class="crpt-summary-row available">
    <div class="crpt-summary-label">Available Fund <span style="font-weight:400;color:var(--subtext);font-size:.7rem">(Bank Balance − Total Released)</span></div>
    <div class="crpt-summary-val" id="sum_available"><?= number_format($availableFund,2) ?></div>
  </div>
  <div class="crpt-summary-row releasing">
    <div class="crpt-summary-label">For Releasing <span style="font-weight:400;color:var(--subtext);font-size:.7rem">(Total above)</span></div>
    <div class="crpt-summary-val" id="sum_releasing"><?= number_format($forReleasing,2) ?></div>
  </div>
  <div class="crpt-summary-row extra">
    <div class="crpt-summary-label">Extra Fund</div>
    <div class="crpt-summary-val" id="sum_extra"><?= number_format($extraFund,2) ?></div>
  </div>
</div>
<div style="margin-top:10px;display:flex;justify-content:flex-end">
  <span id="bankSaveStatus" style="font-family:var(--font-m);font-size:.68rem;color:var(--accent)"></span>
</div>

  </div></div>

<script>
function gv(input) { return parseFloat(input?.value) || 0; }
function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function defaultDate() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth()+1).padStart(2,'0');
  const d = String(now.getDate()).padStart(2,'0');
  return `${y}-${m}-${d}`;
}

function addRow() {
  const empty = document.getElementById('emptyRow');
  if (empty) empty.remove();

  const tbody = document.getElementById('crptBody');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td class="td-date">
      <input type="date" class="date-input" value="${defaultDate()}" onchange="markDirty(this)">
    </td>
    <td><input type="text" class="crpt-input vendor-field" value="" placeholder="e.g. Supplier Payable" oninput="markDirty(this)"></td>
    <td><input type="number" step="0.01" class="crpt-input num" value="" placeholder="0.00" oninput="markDirty(this)"></td>
    <td class="td-rel"><input type="checkbox" class="rel-check" onchange="markDirty(this)"></td>
    <td><input type="text" class="crpt-input remarks-field" value="" placeholder="e.g. Paid via GCash" oninput="markDirty(this)"></td>
    <td style="padding:6px 8px;text-align:center">
      <div style="display:flex;flex-direction:column;gap:3px;align-items:center">
        <div style="display:flex;gap:4px">
          <button class="btn-save-row" onclick="saveRow(this)">Save</button>
          <button class="btn-del-row"  onclick="deleteRow(this)">✕</button>
        </div>
        <div class="row-status"></div>
      </div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('.vendor-field').focus();
  refreshFooter();
}

function markDirty(el) { refreshFooter(); }

function getRowData(tr) {
  const date   = tr.querySelector('.date-input').value;
  const vendor = tr.querySelector('.vendor-field').value;
  const amount = gv(tr.querySelector('.crpt-input.num'));
  const released = tr.querySelector('.rel-check').checked ? 1 : 0;
  const remarks = tr.querySelector('.remarks-field').value;
  return { check_date: date, vendor, amount, is_released: released, remarks };
}

async function saveRowTr(tr) {
  const status = tr.querySelector('.row-status');
  const data   = getRowData(tr);

  const fd = new FormData();
  fd.append('ajax_save','1');
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  const existingId = parseInt(tr.dataset.id) || 0;
  if (existingId > 0) fd.append('row_id', existingId);

  try {
    const res  = await fetch('h_check_report.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      if (json.id) tr.dataset.id = json.id;
      tr.classList.toggle('is-released', data.is_released === 1);
      if (status) {
        status.textContent = '✓ Saved';
        status.style.color = 'var(--accent)';
        status.style.display = 'block';
        setTimeout(() => { status.style.display='none'; }, 3000);
      }
      return { ok: true };
    } else {
      if (status) {
        status.textContent = '❌ ' + json.msg;
        status.style.color = 'var(--accent2)';
        status.style.display = 'block';
      }
      return { ok: false, msg: json.msg };
    }
  } catch(e) {
    if (status) {
      status.textContent = '❌ Network error';
      status.style.color = 'var(--accent2)';
      status.style.display = 'block';
    }
    return { ok: false, msg: 'Network error' };
  }
}

async function saveRow(btn) {
  const tr = btn.closest('tr');
  btn.textContent = '…'; btn.disabled = true;
  await saveRowTr(tr);
  refreshFooter();
  updateKPIs();
  rebuildReleasedTable();
  btn.textContent = 'Save'; btn.disabled = false;
}

async function saveAllRows() {
  const rows = Array.from(document.querySelectorAll('#crptBody tr[data-id]'));
  const statusEl = document.getElementById('saveAllStatus');
  const btns = document.querySelectorAll('button[onclick="saveAllRows()"]');

  if (!rows.length) {
    if (statusEl) { statusEl.textContent = 'Nothing to save'; statusEl.style.color = 'var(--muted)'; }
    return;
  }

  btns.forEach(b => { b.disabled = true; b.dataset.origText = b.textContent; b.textContent = 'Saving…'; });
  if (statusEl) { statusEl.textContent = `Saving ${rows.length} row(s)…`; statusEl.style.color = 'var(--subtext)'; }

  let okCount = 0, failCount = 0;
  for (const tr of rows) {
    const result = await saveRowTr(tr);
    if (result.ok) okCount++; else failCount++;
  }

  refreshFooter();
  updateKPIs();
  rebuildReleasedTable();

  btns.forEach(b => { b.disabled = false; b.textContent = b.dataset.origText || '💾 Save All'; });
  if (statusEl) {
    statusEl.textContent = failCount ? `✓ ${okCount} saved, ❌ ${failCount} failed` : `✓ All ${okCount} row(s) saved`;
    statusEl.style.color = failCount ? 'var(--accent2)' : 'var(--accent)';
    setTimeout(() => { statusEl.textContent = ''; }, 4000);
  }
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id) || 0;

  if (id > 0 && !confirm('Delete this check entry?')) return;

  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete','1');
    fd.append('row_id', id);
    try { await fetch('h_check_report.php', {method:'POST', body:fd}); } catch(e) {}
  }

  tr.remove();
  if (!document.querySelector('#crptBody tr')) {
    const tbody = document.getElementById('crptBody');
    tbody.innerHTML = `<tr id="emptyRow"><td colspan="6" style="text-align:center;padding:48px;color:var(--muted);font-family:var(--font-m);font-size:.78rem">No check entries — click <strong>+ Add Row</strong></td></tr>`;
  }
  refreshFooter();
  updateKPIs();
  rebuildReleasedTable();
}

function refreshFooter() {
  let total = 0;
  document.querySelectorAll('#crptBody tr[data-id]').forEach(tr => {
    total += gv(tr.querySelector('.crpt-input.num'));
  });
  document.getElementById('foot_total').textContent = fmt(total);
}

function updateKPIs() {
  let total = 0, released = 0;
  document.querySelectorAll('#crptBody tr[data-id]').forEach(tr => {
    const amt = gv(tr.querySelector('.crpt-input.num'));
    total += amt;
    if (tr.querySelector('.rel-check').checked) released += amt;
  });
  document.getElementById('kpi_total').textContent = fmt(total);
  document.getElementById('kpi_released').textContent = fmt(released);
  recalcSummary();
}

function rebuildReleasedTable() {
  const body = document.getElementById('releasedBody');
  const rowsHtml = [];
  let releasedTotal = 0;
  document.querySelectorAll('#crptBody tr[data-id]').forEach(tr => {
    if (!tr.querySelector('.rel-check').checked) return;
    const vendor = tr.querySelector('.vendor-field').value || '—';
    const remarks = tr.querySelector('.remarks-field').value || '';
    const amt = gv(tr.querySelector('.crpt-input.num'));
    releasedTotal += amt;
    rowsHtml.push(`<tr><td>${vendor.replace(/</g,'&lt;')}</td><td class="num">${fmt(amt)}</td><td>${remarks.replace(/</g,'&lt;')}</td></tr>`);
  });
  body.innerHTML = rowsHtml.length ? rowsHtml.join('') : `<tr id="releasedEmptyRow"><td colspan="3" class="crpt-released-empty">No rows marked Released yet</td></tr>`;
  document.getElementById('foot_released').textContent = fmt(releasedTotal);
}

function recalcSummary() {
  let total = 0, released = 0;
  document.querySelectorAll('#crptBody tr[data-id]').forEach(tr => {
    const amt = gv(tr.querySelector('.crpt-input.num'));
    total += amt;
    if (tr.querySelector('.rel-check').checked) released += amt;
  });
  const bankBal = gv(document.getElementById('bank_balance'));
  const availableFund = bankBal - released;
  const forReleasing  = total;
  const extraFund      = availableFund - forReleasing;

  document.getElementById('kpi_available').textContent = fmt(availableFund);
  document.getElementById('kpi_extra').textContent = fmt(extraFund);
  document.getElementById('sum_available').textContent = fmt(availableFund);
  document.getElementById('sum_releasing').textContent = fmt(forReleasing);
  document.getElementById('sum_extra').textContent = fmt(extraFund);
}

let bankSaveTimer = null;
document.getElementById('bank_balance').addEventListener('input', () => {
  clearTimeout(bankSaveTimer);
  bankSaveTimer = setTimeout(saveBankBalance, 700);
});

async function saveBankBalance() {
  const status = document.getElementById('bankSaveStatus');
  const fd = new FormData();
  fd.append('ajax_save_summary','1');
  fd.append('bank_balance', gv(document.getElementById('bank_balance')));
  try {
    const res = await fetch('h_check_report.php?month=<?=$fMonth?>&year=<?=$fYear?>', {method:'POST', body:fd});
    const json = await res.json();
    status.textContent = json.ok ? '✓ Bank balance saved' : '❌ ' + json.msg;
    status.style.color = json.ok ? 'var(--accent)' : 'var(--accent2)';
  } catch(e) {
    status.textContent = '❌ Network error';
    status.style.color = 'var(--accent2)';
  }
  setTimeout(() => { status.textContent = ''; }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
  refreshFooter();
  updateKPIs();
});
</script>
</body>
</html>