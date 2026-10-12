<?php
// ============================================================
//  demic_sales_report_summary.php — Demic Lab Sales Report Summary
//  Mirrors the "SUMMARY" sheet from the Demic sales report Excel:
//  DATE / SALES REVENUE(POS) / CASH / HMO / CHARGE TO COMPANY / DR-CR /
//  SHORT-OVER / PAYABLES / MANPOWER COST(FRONTLINE, PF FEE) /
//  DISCOUNTS(HMO 2% EWT, SC/PWD-REGULAR-VIP)
//  SHORT/OVER = CASH + HMO + CHARGE TO COMPANY + DR/CR − SALES REVENUE
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && !in_array(currentBranch(), ['DemicLab-Main','DemicLab-Jaro'], true)) {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `demic_sales_report_summary` (
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`        date NOT NULL,
    `store_name`        varchar(50) NOT NULL DEFAULT 'Demic Lab',
    `sales_revenue`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `cash`              decimal(12,2) NOT NULL DEFAULT 0.00,
    `hmo`               decimal(12,2) NOT NULL DEFAULT 0.00,
    `charge_to_company` decimal(12,2) NOT NULL DEFAULT 0.00,
    `dr_cr`             decimal(12,2) NOT NULL DEFAULT 0.00,
    `short_over`        decimal(12,2) NOT NULL DEFAULT 0.00,
    `payables`          decimal(12,2) NOT NULL DEFAULT 0.00,
    `frontline_staff`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `pf_fee`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `hmo_ewt`           decimal(12,2) NOT NULL DEFAULT 0.00,
    `sc_pwd`            decimal(12,2) NOT NULL DEFAULT 0.00,
    `sort_order`        int(4) NOT NULL DEFAULT 0,
    `saved_by`          varchar(100) DEFAULT NULL,
    `updated_at`        timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

try { $pdo->exec("ALTER TABLE `demic_sales_report_summary` ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`)"); } catch (Throwable $ignored) {}

// ── AJAX: Save row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_row') {
    header('Content-Type: application/json');
    try {
        $store    = isBranch() ? currentBranch() : trim($_POST['store_name'] ?? 'Demic Lab');
        $date     = $_POST['entry_date'] ?? date('Y-m-d');
        $rev      = (float)($_POST['sales_revenue']     ?? 0);
        $cash     = (float)($_POST['cash']              ?? 0);
        $hmo      = (float)($_POST['hmo']                ?? 0);
        $ctc      = (float)($_POST['charge_to_company']  ?? 0);
        $drcr     = (float)($_POST['dr_cr']              ?? 0);
        $payables = (float)($_POST['payables']           ?? 0);
        $front    = (float)($_POST['frontline_staff']    ?? 0);
        $pffee    = (float)($_POST['pf_fee']              ?? 0);
        $hewt     = (float)($_POST['hmo_ewt']             ?? 0);
        $scpwd    = (float)($_POST['sc_pwd']              ?? 0);

        // SHORT/OVER = CASH + HMO + CHARGE TO COMPANY + DR/CR − SALES REVENUE
        $shortOver = round($cash + $hmo + $ctc + $drcr - $rev, 2);

        // Each calendar day (per store) has exactly one row — upsert on entry_date+store_name
        // rather than a client-supplied id, since rows are now auto-generated for every day.
        $pdo->prepare("INSERT INTO demic_sales_report_summary
                (entry_date,store_name,sales_revenue,cash,hmo,charge_to_company,dr_cr,short_over,payables,frontline_staff,pf_fee,hmo_ewt,sc_pwd,saved_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    sales_revenue=VALUES(sales_revenue), cash=VALUES(cash), hmo=VALUES(hmo),
                    charge_to_company=VALUES(charge_to_company), dr_cr=VALUES(dr_cr),
                    short_over=VALUES(short_over), payables=VALUES(payables),
                    frontline_staff=VALUES(frontline_staff), pf_fee=VALUES(pf_fee),
                    hmo_ewt=VALUES(hmo_ewt), sc_pwd=VALUES(sc_pwd), saved_by=VALUES(saved_by)")
            ->execute([$date,$store,$rev,$cash,$hmo,$ctc,$drcr,$shortOver,$payables,$front,$pffee,$hewt,$scpwd,$user['name']]);

        $idStmt = $pdo->prepare("SELECT id FROM demic_sales_report_summary WHERE entry_date=? AND store_name=?");
        $idStmt->execute([$date,$store]);
        $id = (int)$idStmt->fetchColumn();

        echo json_encode(['ok'=>true,'id'=>$id,'short_over'=>$shortOver]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete row ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'delete_row') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM demic_sales_report_summary WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Filters ───────────────────────────────────────────────
$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
$fMonth = max(1, min(12, $fMonth));
$storeLabel = isBranch() ? currentBranch() : 'All Branches';

[$bClauseSum, $bParamsSum] = branchFilter('store_name');
$stmt = $pdo->prepare("SELECT * FROM demic_sales_report_summary WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? AND $bClauseSum ORDER BY entry_date");
$stmt->execute(array_merge([$fYear, $fMonth], $bParamsSum));
$summaryByDate = [];
foreach ($stmt->fetchAll() as $r) { $summaryByDate[$r['entry_date']] = $r; }

// ── Pull the Daily Report entries for the same month so days that haven't
//    been manually saved on this summary yet are auto-filled from them ──
[$bClauseDaily, $bParamsDaily] = branchFilter('store_name');
$dstmt = $pdo->prepare("SELECT * FROM demic_daily_reports WHERE YEAR(report_date)=? AND MONTH(report_date)=? AND $bClauseDaily");
$dstmt->execute(array_merge([$fYear, $fMonth], $bParamsDaily));
$dailyByDate = [];
foreach ($dstmt->fetchAll() as $d) { $dailyByDate[$d['report_date']] = $d; }

// ── Build one row per calendar day of the selected month ────
$emptyRow = ['sales_revenue'=>0,'cash'=>0,'hmo'=>0,'charge_to_company'=>0,'dr_cr'=>0,'short_over'=>0,
             'payables'=>0,'frontline_staff'=>0,'pf_fee'=>0,'hmo_ewt'=>0,'sc_pwd'=>0];
$daysInMonth = (int)date('t', mktime(0,0,0,$fMonth,1,$fYear));
$rows = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $fYear, $fMonth, $day);

    if (isset($summaryByDate[$date])) {
        // Manually saved/edited on this summary before — that value wins.
        $sr = $summaryByDate[$date];
        $rows[] = [
            'id' => (int)$sr['id'], 'entry_date' => $date, 'auto' => false, 'has_data' => true,
            'sales_revenue' => (float)$sr['sales_revenue'], 'cash' => (float)$sr['cash'],
            'hmo' => (float)$sr['hmo'], 'charge_to_company' => (float)$sr['charge_to_company'],
            'dr_cr' => (float)$sr['dr_cr'], 'short_over' => (float)$sr['short_over'],
            'payables' => (float)$sr['payables'], 'frontline_staff' => (float)$sr['frontline_staff'],
            'pf_fee' => (float)$sr['pf_fee'], 'hmo_ewt' => (float)$sr['hmo_ewt'], 'sc_pwd' => (float)$sr['sc_pwd'],
        ];
    } elseif (isset($dailyByDate[$date])) {
        // Not saved on the Summary yet — auto-derive from the Demic Daily Report for
        // that date. A Daily Report already exists for this day, so it counts as
        // "data entered" (green dot) even though it hasn't been saved here yet.
        $d = $dailyByDate[$date];
        $payItems = json_decode($d['payables_expenses'] ?? '[]', true) ?: [];
        $payTotal = 0.0;
        foreach ($payItems as $pi) { $payTotal += (float)($pi['amount'] ?? 0); }
        $rev = (float)$d['sales_revenue']; $cash = (float)$d['cash']; $hmo = (float)$d['hmo'];
        $ctc = (float)$d['charge_to_company']; $drcr = (float)$d['dr_cr'];
        $rows[] = [
            'id' => 0, 'entry_date' => $date, 'auto' => true, 'has_data' => true,
            'sales_revenue' => $rev, 'cash' => $cash, 'hmo' => $hmo,
            'charge_to_company' => $ctc, 'dr_cr' => $drcr,
            'short_over' => round($cash + $hmo + $ctc + $drcr - $rev, 2),
            'payables' => round($payTotal, 2),
            'frontline_staff' => (float)$d['frontline_medical_staff'], 'pf_fee' => (float)$d['pf_fee'],
            'hmo_ewt' => round($hmo * ((float)$d['hmo_withholding_pct'] / 100), 2),
            'sc_pwd' => (float)$d['sales_discount'],
        ];
    } else {
        // No daily report for this date either — blank editable row, nothing entered yet.
        $rows[] = array_merge(['id' => 0, 'entry_date' => $date, 'auto' => true, 'has_data' => false], $emptyRow);
    }
}

// ── Totals ────────────────────────────────────────────────
$T = ['sales_revenue'=>0,'cash'=>0,'hmo'=>0,'charge_to_company'=>0,'dr_cr'=>0,'short_over'=>0,
      'payables'=>0,'frontline_staff'=>0,'pf_fee'=>0,'hmo_ewt'=>0,'sc_pwd'=>0];
$entriesCount = 0;
foreach ($rows as $r) {
    foreach ($T as $k => $v) { $T[$k] += $r[$k]; }
    if ($r['sales_revenue'] != 0 || $r['cash'] != 0 || $r['hmo'] != 0 || $r['charge_to_company'] != 0
        || $r['dr_cr'] != 0 || $r['payables'] != 0) { $entriesCount++; }
}

// ── CSV export ────────────────────────────────────────────
if (($_GET['export_csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demic_sales_report_summary_' . $fYear . '-' . str_pad($fMonth,2,'0',STR_PAD_LEFT) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Sales Revenue (POS Reading)','Cash','HMO','Charge to Company','DR/CR','Short/Over',
                   'Payables','Frontline/Medical Staff','PF Fee','HMO 2% (EWT)','SC/PWD,Regular,VIP','Source']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['entry_date'],$r['sales_revenue'],$r['cash'],$r['hmo'],$r['charge_to_company'],
                        $r['dr_cr'],$r['short_over'],$r['payables'],$r['frontline_staff'],$r['pf_fee'],
                        $r['hmo_ewt'],$r['sc_pwd'],($r['auto']?'Auto (from Daily Report)':'Saved')]);
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTAL',$T['sales_revenue'],$T['cash'],$T['hmo'],$T['charge_to_company'],$T['dr_cr'],
                    $T['short_over'],$T['payables'],$T['frontline_staff'],$T['pf_fee'],$T['hmo_ewt'],$T['sc_pwd']]);
    fclose($out);
    exit;
}

$pageTitle  = 'Demic Sales Report Summary';
$activePage = 'demic_sales_summary';
include '../layout.php';
?>

<div class="section-header">
  <div>
    <div class="section-title">Demic <span>Sales Report Summary</span></div>
    <div class="section-subtitle">Daily entry · every day auto-fills from the Daily Report · each row has its own Save button</div>
  </div>
</div>

<!-- Controls -->
<div class="card sr-card sr-controlbar">
  <div class="sr-pill">📍 <?=htmlspecialchars($storeLabel)?></div>
  <select onchange="window.location='?month='+this.value+'&year=<?=$fYear?>'" class="sr-dd">
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=$months[$m-1]?></option>
    <?php endfor; ?>
  </select>
  <select onchange="window.location='?month=<?=$fMonth?>&year='+this.value" class="sr-dd">
    <?php for($y=2050;$y>=2020;$y--): ?>
    <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="button" id="saveAllBtn" class="sr-btn-saveall" onclick="saveAllRows()">💾 Save All</button>
  <a href="?month=<?=$fMonth?>&year=<?=$fYear?>&export_csv=1" class="sr-btn-csv">⬇ Download CSV</a>
</div>

<!-- Legend -->
<div class="sr-legend-row">
  <span><i class="sr-sw sr-sw-manpower"></i> Manpower Cost</span>
  <span><i class="sr-sw sr-sw-discount"></i> Discounts</span>
  <span><i class="sr-sw sr-sw-auto"></i> Auto-calculated</span>
  <span><i class="sr-dot sr-dot-green"></i> Data Entered</span>
  <span><i class="sr-dot sr-dot-amber"></i> No Data Yet</span>
</div>
<div class="sr-scrollhint">← Scroll horizontally to see all columns →</div>

<!-- Summary -->
<div class="card sr-card sr-stats">
  <div>
    <div class="sr-kicker">Entries</div>
    <div class="sr-statval"><?=$entriesCount?> / <?=$daysInMonth?></div>
  </div>
  <div>
    <div class="sr-kicker">Total Sales Revenue</div>
    <div class="sr-statval">₱<?=number_format($T['sales_revenue'],2)?></div>
  </div>
  <div>
    <div class="sr-kicker">Total Payables</div>
    <div class="sr-statval">₱<?=number_format($T['payables'],2)?></div>
  </div>
  <div>
    <div class="sr-kicker">Total Short/Over</div>
    <div class="sr-statval" style="color:<?=$T['short_over']<0?'var(--accent2)':'var(--sr-accent-deep)'?>">₱<?=number_format($T['short_over'],2)?></div>
  </div>
</div>

<!-- Table -->
<div class="card sr-card" style="padding:0">
  <div style="overflow-x:auto">
  <table class="sr-tbl" id="srTbl">
    <thead>
      <tr>
        <th colspan="2"></th>
        <th colspan="4" class="sr-grp sr-grp-gray">CASH / HMO / CHARGE / DR-CR</th>
        <th colspan="1" class="sr-grp sr-grp-blue">AUTO-CALC</th>
        <th colspan="1"></th>
        <th colspan="2" class="sr-grp sr-grp-green">MANPOWER COST</th>
        <th colspan="2" class="sr-grp sr-grp-pink">DISCOUNTS</th>
        <th></th>
      </tr>
      <tr>
        <th style="min-width:120px">DATE</th>
        <th style="min-width:140px">SALES REVENUE<br>(POS READING)</th>
        <th style="min-width:110px">CASH</th>
        <th style="min-width:110px">HMO</th>
        <th style="min-width:130px">CHARGE TO<br>COMPANY</th>
        <th style="min-width:100px">DR/CR</th>
        <th style="min-width:110px" class="grp-auto">SHORT/OVER</th>
        <th style="min-width:110px">PAYABLES</th>
        <th style="min-width:130px" class="grp-manpower">FRONTLINE/<br>MEDICAL STAFF</th>
        <th style="min-width:100px" class="grp-manpower">PF FEE</th>
        <th style="min-width:110px" class="grp-discount">HMO 2%<br>(EWT)</th>
        <th style="min-width:130px" class="grp-discount">SC/PWD,<br>REGULAR,VIP</th>
        <th style="min-width:110px">ACTION</th>
      </tr>
    </thead>
    <tbody id="srTbody">
      <?php foreach ($rows as $r): $dow = date('D', strtotime($r['entry_date'])); $dm = date('j-M', strtotime($r['entry_date'])); ?>
      <tr data-id="<?=$r['id']?>" data-date="<?=$r['entry_date']?>" class="<?=$r['auto']?'sr-row-auto':''?>">
        <td>
          <div class="sr-datecell">
            <span class="sr-dot <?=$r['has_data']?'sr-dot-green':'sr-dot-amber'?>" data-role="statusdot" title="<?=$r['has_data']?'Data entered':'No data yet'?>"></span>
            <span class="sr-dm"><?=$dm?></span>
            <span class="sr-dow"><?=$dow?></span>
            <input class="sci sr-dateinput" type="hidden" value="<?=$r['entry_date']?>">
          </div>
        </td>
        <td><input class="sci" type="number" step="0.01" min="0" value="<?=$r['sales_revenue']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="sci" type="number" step="0.01" min="0" value="<?=$r['cash']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="sci" type="number" step="0.01" min="0" value="<?=$r['hmo']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="sci" type="number" step="0.01" min="0" value="<?=$r['charge_to_company']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td><input class="sci" type="number" step="0.01" value="<?=$r['dr_cr']?>" placeholder="0.00" oninput="calcRow(this)" onfocus="this.select()"></td>
        <td class="grp-auto"><input class="sci calc" type="number" value="<?=$r['short_over']?>" readonly tabindex="-1"></td>
        <td><input class="sci" type="number" step="0.01" min="0" value="<?=$r['payables']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td class="grp-manpower"><input class="sci" type="number" step="0.01" min="0" value="<?=$r['frontline_staff']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td class="grp-manpower"><input class="sci" type="number" step="0.01" min="0" value="<?=$r['pf_fee']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td class="grp-discount"><input class="sci" type="number" step="0.01" min="0" value="<?=$r['hmo_ewt']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td class="grp-discount"><input class="sci" type="number" step="0.01" min="0" value="<?=$r['sc_pwd']?>" placeholder="0.00" onfocus="this.select()"></td>
        <td class="act-td">
          <button class="sr-btn-sv" onclick="saveRow(this)">Save</button>
          <?php if (!$r['auto']): ?><button class="sr-btn-dl" onclick="deleteRow(this)">✕</button><?php endif; ?>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="sr-total-row">
        <td>TOTAL</td>
        <td>₱<?=number_format($T['sales_revenue'],2)?></td>
        <td>₱<?=number_format($T['cash'],2)?></td>
        <td>₱<?=number_format($T['hmo'],2)?></td>
        <td>₱<?=number_format($T['charge_to_company'],2)?></td>
        <td>₱<?=number_format($T['dr_cr'],2)?></td>
        <td class="grp-auto">₱<?=number_format($T['short_over'],2)?></td>
        <td>₱<?=number_format($T['payables'],2)?></td>
        <td class="grp-manpower">₱<?=number_format($T['frontline_staff'],2)?></td>
        <td class="grp-manpower">₱<?=number_format($T['pf_fee'],2)?></td>
        <td class="grp-discount">₱<?=number_format($T['hmo_ewt'],2)?></td>
        <td class="grp-discount">₱<?=number_format($T['sc_pwd'],2)?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<style>
:root {
  --sr-blue:        #1f8a5c;
  --sr-blue-deep:   #0f5c3c;
  --sr-blue-soft:   rgba(31,138,92,.08);
  --sr-blue-softer: rgba(31,138,92,.16);
  --sr-pink:        #d94f6b;
  --sr-pink-soft:   rgba(217,79,107,.08);
  --sr-pink-softer: rgba(217,79,107,.16);
  --sr-amber:       #c8850f;
  --sr-amber-soft:  rgba(200,133,15,.1);
  --sr-amber-softer:rgba(200,133,15,.2);
  --sr-gray-soft:   rgba(100,116,139,.06);

  /* Primary blue accent — used for chrome (control bar, buttons, stat cards,
     base column headers) so the sheet doesn't read as all-white. */
  --sr-accent:        #2f6fed;
  --sr-accent-deep:   #1e40af;
  --sr-accent-soft:   rgba(47,111,237,.10);
  --sr-accent-softer: rgba(47,111,237,.20);
  --sr-accent-pale:   rgba(47,111,237,.05);
}

.sr-card { border-color:var(--border); }

/* ── Control bar ── */
.sr-controlbar {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  padding:12px 16px; margin-bottom:14px;
  background:linear-gradient(135deg, var(--sr-accent-pale), transparent 60%);
  border-color:var(--sr-accent-softer);
}
.sr-pill {
  font-family:var(--font-m); font-size:.76rem; font-weight:700;
  padding:7px 14px; border-radius:20px; white-space:nowrap;
  background:var(--sr-accent-soft); color:var(--sr-accent-deep); border:1px solid var(--sr-accent-softer);
}
.sr-dd {
  padding:7px 12px; font-size:.76rem; font-weight:600;
  background:var(--surface); border:1px solid var(--sr-accent-softer); color:var(--text);
  border-radius:8px; font-family:var(--font-m);
}
.sr-dd:focus { outline:none; border-color:var(--sr-accent); box-shadow:0 0 0 3px var(--sr-accent-soft); }
.sr-btn-saveall {
  font-family:var(--font-m); font-size:.78rem; font-weight:700; padding:8px 16px;
  border-radius:9px; cursor:pointer; border:1px solid var(--sr-accent-deep);
  background:var(--sr-accent); color:#fff; margin-left:auto;
}
.sr-btn-saveall:hover { background:var(--sr-accent-deep); }
.sr-btn-saveall:disabled { opacity:.6; cursor:default; }
.sr-btn-csv {
  font-family:var(--font-m); font-size:.76rem; font-weight:600; padding:7px 14px;
  border-radius:9px; cursor:pointer; text-decoration:none; white-space:nowrap; display:inline-block;
  background:var(--sr-amber-soft); color:var(--sr-amber); border:1px solid var(--sr-amber-softer);
}
.sr-btn-csv:hover { background:var(--sr-amber-softer); }

/* ── Legend + scroll hint ── */
.sr-legend-row {
  display:flex; align-items:center; gap:16px; flex-wrap:wrap;
  padding:2px 4px 12px; font-family:var(--font-m); font-size:.68rem; color:var(--subtext2);
}
.sr-legend-row span { display:inline-flex; align-items:center; gap:5px; }
.sr-dot {
  width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0;
}
.sr-dot-green  { background:var(--sr-blue); }
.sr-dot-amber  { background:var(--sr-amber); }
.sr-dot-pink   { background:var(--sr-pink); }
.sr-dot-blue   { background:#3b82f6; }
.sr-sw {
  width:10px; height:10px; border-radius:3px; display:inline-block; flex-shrink:0;
}
.sr-sw-manpower { background:var(--sr-blue); }
.sr-sw-discount { background:var(--sr-pink); }
.sr-sw-auto     { background:var(--sr-accent); }
.sr-scrollhint {
  text-align:center; font-family:var(--font-m); font-size:.68rem; color:var(--subtext);
  padding-bottom:10px;
}

/* ── Stat cards ── */
.sr-stats {
  display:grid; grid-template-columns:1fr 1fr 1fr 1fr; overflow:hidden;
  padding:0; margin-bottom:14px;
  background:var(--sr-accent-pale); border-color:var(--sr-accent-softer);
}
.sr-stats > div {
  padding:13px 16px; border-right:1px solid var(--sr-accent-softer);
  border-left:3px solid var(--sr-accent);
}
.sr-stats > div:first-child { border-left:none; }
.sr-stats > div:last-child { border-right:none; }
.sr-kicker {
  font-family:var(--font-m); font-size:.58rem; text-transform:uppercase;
  letter-spacing:.08em; color:var(--sr-accent-deep); margin-bottom:4px; font-weight:700;
}
.sr-statval { font-size:1rem; font-weight:700; color:var(--sr-accent-deep); }

/* ── Table ── */
.sr-tbl {
  width:100%; border-collapse:collapse; font-family:var(--font-m); font-size:.76rem;
  border:1px solid var(--border);
}
.sr-tbl th, .sr-tbl td { border-right:1px solid var(--border); }
.sr-tbl th:last-child, .sr-tbl td:last-child { border-right:none; }
.sr-tbl thead tr:first-child th {
  background:var(--sr-gray-soft); color:var(--subtext2); font-size:.6rem; letter-spacing:.07em;
  padding:6px 8px; border-bottom:1px solid var(--border); font-weight:700;
}
.sr-tbl thead tr:first-child th.sr-grp-gray  { background:var(--sr-accent-soft); color:var(--sr-accent-deep); }
.sr-tbl thead tr:first-child th.sr-grp-blue  { background:rgba(59,130,246,.1); color:#1d4ed8; }
.sr-tbl thead tr:first-child th.sr-grp-green { background:var(--sr-blue-soft); color:var(--sr-blue-deep); }
.sr-tbl thead tr:first-child th.sr-grp-pink  { background:var(--sr-pink-soft); color:var(--sr-pink); }
.sr-tbl thead tr:last-child th {
  background:var(--sr-accent-pale); color:var(--sr-accent-deep); text-transform:uppercase;
  font-size:.6rem; letter-spacing:.05em; padding:8px 6px; border-bottom:1px solid var(--sr-accent-softer);
  text-align:center; white-space:nowrap; line-height:1.35;
}
.sr-tbl .sr-grp { text-align:center; font-weight:700; }
.sr-tbl tbody td { padding:5px 6px; border-bottom:1px solid var(--border); text-align:center; }
.sr-tbl .act-td { white-space:nowrap; }

/* Flat, borderless spreadsheet-style cells — border only appears on focus,
   matching the plain-number look of the Dois summary sheet. */
.sr-tbl .sci {
  width:100%; padding:5px 6px; border:1px solid transparent; border-radius:6px;
  background:transparent; color:var(--text); font-family:var(--font-m); font-size:.74rem;
  text-align:center;
  -moz-appearance:textfield;
  appearance:textfield;
}
.sr-tbl .sci::-webkit-outer-spin-button,
.sr-tbl .sci::-webkit-inner-spin-button {
  -webkit-appearance:none; margin:0;
}
.sr-tbl .sci:focus { outline:none; background:var(--surface); border-color:var(--sr-accent); box-shadow:0 0 0 3px var(--sr-accent-soft); }
.sr-tbl .sci.calc { color:var(--sr-accent-deep); font-weight:700; }

/* Column-group tinting — applied to every row in that column, not just
   auto/unsaved rows, so the sheet reads like the Dois summary's shaded columns. */
.sr-tbl thead tr:last-child th.grp-auto,
.sr-tbl tbody td.grp-auto,
.sr-tbl tfoot td.grp-auto { background:var(--sr-accent-soft); }
.sr-tbl thead tr:last-child th.grp-manpower,
.sr-tbl tbody td.grp-manpower,
.sr-tbl tfoot td.grp-manpower { background:var(--sr-blue-soft); }
.sr-tbl thead tr:last-child th.grp-discount,
.sr-tbl tbody td.grp-discount,
.sr-tbl tfoot td.grp-discount { background:var(--sr-pink-soft); }

.sr-total-row td {
  background:var(--sr-accent-softer); color:var(--sr-accent-deep); font-weight:700;
  padding:9px 6px; border-top:2px solid var(--sr-accent); border-bottom:none;
  border-right-color:var(--sr-accent-softer);
}

.sr-btn-sv, .sr-btn-dl {
  font-family:var(--font-m); font-size:.66rem; font-weight:700; padding:5px 10px; border-radius:20px;
  border:1px solid var(--sr-blue-softer); cursor:pointer; margin-right:4px;
}
.sr-btn-sv { background:var(--sr-blue-soft); color:var(--sr-blue-deep); }
.sr-btn-sv:hover { background:var(--sr-blue-softer); }
.sr-btn-dl {
  background:var(--sr-pink-soft); color:var(--sr-pink); border-color:var(--sr-pink-softer);
  border-radius:6px; padding:5px 8px;
}
.row-st { display:none; font-size:.66rem; color:var(--sr-blue); margin-top:3px; font-weight:700; }

/* Calendar-day date cell */
.sr-datecell { position:relative; display:flex; flex-direction:column; align-items:center; gap:2px; }
.sr-datecell .sr-dot { position:absolute; top:1px; right:1px; }
.sr-dm { font-weight:700; color:var(--sr-blue-deep); font-size:.76rem; }
.sr-dow {
  font-family:var(--font-m); font-size:.58rem; font-weight:700; letter-spacing:.06em;
  text-transform:uppercase; color:var(--subtext2);
}

/* Auto (unsaved, pulled from Daily Report) rows — a thin left accent bar
   plus the amber status dot is enough of a cue, so column tints stay legible. */
.sr-row-auto td:first-child { box-shadow: inset 3px 0 0 var(--sr-amber); }
</style>

<script>

const gvS = el => parseFloat(el?.value) || 0;

function calcRow(inp) {
  const tr = inp.closest('tr');
  const ci = tr.querySelectorAll('.sci');
  // ci[0]=date ci[1]=revenue ci[2]=cash ci[3]=hmo ci[4]=charge_to_company ci[5]=dr_cr ci[6]=short_over(calc)
  const rev  = gvS(ci[1]);
  const cash = gvS(ci[2]);
  const hmo  = gvS(ci[3]);
  const ctc  = gvS(ci[4]);
  const drcr = gvS(ci[5]);
  ci[6].value = (cash + hmo + ctc + drcr - rev).toFixed(2);
}

async function saveRow(btn) {
  const tr = btn.closest('tr');
  btn.textContent='…'; btn.disabled=true;
  const res = await doSaveRow(tr);
  if (!res.ok) alert('Error: '+res.msg);
  btn.textContent='Update'; btn.disabled=false;
}

async function doSaveRow(tr) {
  const st = tr.querySelector('.row-st');
  const ci = tr.querySelectorAll('.sci');
  // 0 date, 1 revenue, 2 cash, 3 hmo, 4 charge_to_company, 5 dr_cr, 6 short_over(calc),
  // 7 payables, 8 frontline_staff, 9 pf_fee, 10 hmo_ewt, 11 sc_pwd
  const fd = new FormData();
  fd.append('ajax_action','save_row');
  fd.append('id', tr.dataset.id||0);
  fd.append('entry_date',        ci[0].value);
  fd.append('sales_revenue',     gvS(ci[1]));
  fd.append('cash',              gvS(ci[2]));
  fd.append('hmo',                gvS(ci[3]));
  fd.append('charge_to_company', gvS(ci[4]));
  fd.append('dr_cr',              gvS(ci[5]));
  fd.append('payables',          gvS(ci[7]));
  fd.append('frontline_staff',   gvS(ci[8]));
  fd.append('pf_fee',             gvS(ci[9]));
  fd.append('hmo_ewt',           gvS(ci[10]));
  fd.append('sc_pwd',            gvS(ci[11]));
  try {
    const r = await fetch('demic_sales_report_summary.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
      tr.dataset.id = d.id;
      ci[6].value = Number(d.short_over).toFixed(2);
      // Now saved — no longer an auto/unsaved row, so drop the badge & highlight,
      // flip the status dot green, and reveal the delete button.
      if (tr.classList.contains('sr-row-auto')) {
        tr.classList.remove('sr-row-auto');
        tr.querySelector('.sr-auto-badge')?.remove();
        const actTd = tr.querySelector('.act-td');
        if (!actTd.querySelector('.sr-btn-dl')) {
          const del = document.createElement('button');
          del.className = 'sr-btn-dl';
          del.textContent = '✕';
          del.onclick = () => deleteRow(del);
          actTd.insertBefore(del, actTd.querySelector('.row-st'));
        }
      }
      const dot = tr.querySelector('[data-role="statusdot"]');
      if (dot) { dot.classList.remove('sr-dot-amber'); dot.classList.add('sr-dot-green'); }
      st.textContent='✓'; st.style.display='block';
      setTimeout(()=>st.style.display='none',2000);
      return { ok:true };
    }
    return { ok:false, msg:d.msg };
  } catch(e){ return { ok:false, msg:'Network error' }; }
}

async function saveAllRows() {
  const btn = document.getElementById('saveAllBtn');
  const trs = Array.from(document.querySelectorAll('#srTbody tr'));
  btn.textContent = 'Saving…'; btn.disabled = true;
  let failed = 0;
  for (const tr of trs) {
    const res = await doSaveRow(tr);
    if (!res.ok) failed++;
  }
  btn.textContent = '💾 Save All'; btn.disabled = false;
  if (failed > 0) alert(failed + ' row(s) failed to save. Please check them and try again.');
}

async function deleteRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  if (id<=0) return;
  if (!confirm('Delete this saved entry? The day will revert to its auto-filled value from the Daily Report (if any).')) return;
  const fd=new FormData(); fd.append('ajax_action','delete_row'); fd.append('id',id);
  await fetch('demic_sales_report_summary.php',{method:'POST',body:fd});
  location.reload();
}
</script>

  </div></div>
</body>
</html>