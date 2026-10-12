<?php
// ============================================================
//  commissary_purchases_ledger.php — Commissary Purchases Ledger
//  Replica of COMMI_JULY_DISBURSEMENT_2026.xlsx, folded into ONE page:
//   • SUPPLIER  → charge / credit purchases, billed by a supplier
//   • CASH      → over-the-counter purchases paid immediately in cash
//  Both registers share the same 30 expense-classification columns
//  from the source workbook and the same vendor-lookup autocomplete.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

// Only Commissary branch + management can access
if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo = getPDO();

// ── Two identical tables, one per register ──────────────────
const PL_TABLES = ['sup' => 'commissary_supplier', 'csh' => 'commissary_cash'];

function ensurePurchasesLedgerTable(PDO $pdo, string $table): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` (
        `id`                    int(11) NOT NULL AUTO_INCREMENT,
        `store_name`            varchar(100) DEFAULT 'Commissary',
        `entry_date`            date NOT NULL,
        `date_col`              date DEFAULT NULL,
        `tin`                   varchar(50) DEFAULT NULL,
        `supplier_name`         varchar(255) DEFAULT NULL,
        `type`                  varchar(20) DEFAULT NULL,
        `address`               varchar(255) DEFAULT NULL,
        `particulars`           varchar(255) DEFAULT NULL,
        `or_cr_no`              varchar(100) DEFAULT NULL,
        `amount_w_vat`          decimal(15,2) DEFAULT 0.00,
        `total_vat_exclusive`   decimal(15,2) DEFAULT 0.00,
        `input_taxes`           decimal(15,2) DEFAULT 0.00,
        `non_vat_amount`        decimal(15,2) DEFAULT 0.00,
        `total_amount_vat_ex`   decimal(15,2) DEFAULT 0.00,
        `purchases`             decimal(15,2) DEFAULT 0.00,
        `staff_meal`            decimal(15,2) DEFAULT 0.00,
        `fare`                  decimal(15,2) DEFAULT 0.00,
        `drinking_water`        decimal(15,2) DEFAULT 0.00,
        `other_supplies`        decimal(15,2) DEFAULT 0.00,
        `delivery_fee`          decimal(15,2) DEFAULT 0.00,
        `kitchen_equipment`     decimal(15,2) DEFAULT 0.00,
        `pest_control`          decimal(15,2) DEFAULT 0.00,
        `office_supplies`       decimal(15,2) DEFAULT 0.00,
        `bio_augmentation`      decimal(15,2) DEFAULT 0.00,
        `misc`                  decimal(15,2) DEFAULT 0.00,
        `repairs_maintenance`   decimal(15,2) DEFAULT 0.00,
        `internet_communication`decimal(15,2) DEFAULT 0.00,
        `fuel_oil`              decimal(15,2) DEFAULT 0.00,
        `electricity`           decimal(15,2) DEFAULT 0.00,
        `bill_water`            decimal(15,2) DEFAULT 0.00,
        `representation_expense`decimal(15,2) DEFAULT 0.00,
        `salary`                decimal(15,2) DEFAULT 0.00,
        `sss_hdmf_ph_cont`      decimal(15,2) DEFAULT 0.00,
        `taxes_licenses`        decimal(15,2) DEFAULT 0.00,
        `solane`                decimal(15,2) DEFAULT 0.00,
        `mnikki`                decimal(15,2) DEFAULT 0.00,
        `office_equipment`      decimal(15,2) DEFAULT 0.00,
        `insurance`             decimal(15,2) DEFAULT 0.00,
        `commission`            decimal(15,2) DEFAULT 0.00,
        `sort_order`            int(11) DEFAULT 0,
        `created_at`            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_date` (`entry_date`),
        KEY `idx_store` (`store_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
foreach (PL_TABLES as $t) ensurePurchasesLedgerTable($pdo, $t);

// All 30 expense-classification / VAT-breakdown columns, shared by both registers
$numCols = [
    'amount_w_vat','total_vat_exclusive','input_taxes','non_vat_amount','total_amount_vat_ex',
    'purchases','staff_meal','fare','drinking_water','other_supplies','delivery_fee',
    'kitchen_equipment','pest_control','office_supplies','bio_augmentation','misc',
    'repairs_maintenance','internet_communication','fuel_oil','electricity','bill_water',
    'representation_expense','salary','sss_hdmf_ph_cont','taxes_licenses','solane',
    'mnikki','office_equipment','insurance','commission',
];
$saveCols = array_merge(['date_col','tin','supplier_name','type','address','particulars','or_cr_no'], $numCols);

// ── AJAX: Vendor lookup (for autocomplete — shared by both registers) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendor_lookup'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $pdo->prepare("SELECT tin, company_name, vat_status, address
                            FROM vendor_masterlist_unified
                            WHERE company_name LIKE ? OR tin LIKE ?
                            ORDER BY company_name ASC LIMIT 10");
    $stmt->execute([$q, $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Save a row (src = 'sup' or 'csh' picks which table) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_row'])) {
    header('Content-Type: application/json');
    $src = $_POST['src'] ?? '';
    if (!isset(PL_TABLES[$src])) { echo json_encode(['ok'=>false,'msg'=>'Bad register']); exit; }
    $table = PL_TABLES[$src];

    $id   = (int)($_POST['id'] ?? 0);
    $date = $_POST['entry_date'] ?? date('Y-m-d');
    $data = ['store_name' => 'Commissary', 'entry_date' => $date];
    foreach ($saveCols as $c) {
        $v = $_POST[$c] ?? '';
        $data[$c] = in_array($c, ['tin','supplier_name','type','address','particulars','or_cr_no','date_col'])
            ? ($v ?: null)
            : (is_numeric($v) ? (float)$v : 0);
    }
    try {
        if ($id > 0) {
            $sets = implode(',', array_map(fn($k) => "`$k`=:$k", array_keys($data)));
            $stmt = $pdo->prepare("UPDATE `$table` SET $sets WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
            echo json_encode(['ok'=>true,'id'=>$id]);
        } else {
            $keys = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
            $vals = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
            $stmt = $pdo->prepare("INSERT INTO `$table` ($keys) VALUES ($vals)");
            $stmt->execute($data);
            echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete a row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_row'])) {
    header('Content-Type: application/json');
    $src = $_POST['src'] ?? '';
    if (!isset(PL_TABLES[$src])) { echo json_encode(['ok'=>false,'msg'=>'Bad register']); exit; }
    $table = PL_TABLES[$src];
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Shared month filter for both registers ───────────────────
$fMonth = $_GET['month'] ?? date('Y-m');
[$fYear, $fMon] = explode('-', $fMonth);
$startDate = "$fYear-$fMon-01";
$endDate   = date('Y-m-t', strtotime($startDate));

function plLoadRows(PDO $pdo, string $table, string $start, string $end): array {
    $stmt = $pdo->prepare("SELECT * FROM `$table`
        WHERE store_name='Commissary' AND entry_date BETWEEN ? AND ?
        ORDER BY entry_date, sort_order, id");
    $stmt->execute([$start, $end]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function plTotals(array $rows, array $numCols): array {
    $totals = array_fill_keys($numCols, 0);
    foreach ($rows as $r) foreach ($numCols as $c) $totals[$c] += (float)$r[$c];
    return $totals;
}

$supplierRows = plLoadRows($pdo, PL_TABLES['sup'], $startDate, $endDate);
$cashRows     = plLoadRows($pdo, PL_TABLES['csh'], $startDate, $endDate);
$supplierTot  = plTotals($supplierRows, $numCols);
$cashTot      = plTotals($cashRows, $numCols);

// ── CSV export (checked BEFORE any HTML is emitted) ───────────
if (isset($_GET['export_csv']) && isset(PL_TABLES[$_GET['export_csv']])) {
    $src    = $_GET['export_csv'];
    $rows   = $src === 'sup' ? $supplierRows : $cashRows;
    $totals = $src === 'sup' ? $supplierTot  : $cashTot;
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="commissary_'.$src.'_'.$fMonth.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','Date','TIN','Name of Supplier','Type','Address','Particulars/Description','O.R./C.R. No.',
        'Amount w/ VAT','Total VAT Exclusive','Input Taxes','Non-VAT Amount','Total Amount VAT Ex',
        'Purchases','Staff Meal','Fare','Drinking Water','Other Supplies','Delivery Fee',
        'Kitchen Equipment','Pest Control','Office Supplies','Bio Augmentation','Misc',
        'Repairs & Maintenance','Internet & Communication','Fuel & Oil','Electricity',
        'Bill Water','Representation Expense','Salary','SSS HDMF PH Cont','Taxes & Licenses',
        'Solane','M\'Nikki','Office Equipment','Insurance','Commission']);
    foreach ($rows as $i => $r) {
        $line = [$i+1, $r['date_col'] ?? $r['entry_date'], $r['tin'], $r['supplier_name'],
                 $r['type'], $r['address'], $r['particulars'], $r['or_cr_no']];
        foreach ($numCols as $c) $line[] = number_format((float)$r[$c], 2);
        fputcsv($out, $line);
    }
    $totLine = ['','','','','','','','TOTAL'];
    foreach ($numCols as $c) $totLine[] = number_format($totals[$c], 2);
    fputcsv($out, $totLine);
    fclose($out);
    exit;
}

$pageTitle  = 'Commissary Purchases';
$activePage = 'commissary_purchases_ledger';
include '../layout.php';

$fmt = fn($v) => number_format((float)$v, 2);

// ── Renders one full register (KPI strip + editable table) ───
function plRenderRegister(string $prefix, string $icon, string $title, string $subtitle, string $src,
                           array $rows, array $totals, array $numCols, string $fMonth, string $startDate, callable $fmt, bool $active = false): void {
?>
<div class="reg-section pv-pane<?=$active?' active':''?>" id="sec-<?=$prefix?>" data-src="<?=$src?>">

  <div class="cp-header-card">
    <div>
      <div class="eyebrow">Commissary · Project C</div>
      <div class="title"><?=htmlspecialchars($title)?></div>
      <div class="subtitle"><?=htmlspecialchars($subtitle)?></div>
    </div>
    <span style="background:rgba(255,255,255,.15);color:#fff;padding:5px 14px;border-radius:20px;font-size:.63rem;font-weight:600"><?=$icon?> Commissary</span>
  </div>

  <div class="cp-controls">
    <button type="button" class="btn btn-primary" onclick="saveAll('<?=$prefix?>')">💾 Save All</button>
    <a href="commissary_purchases_ledger.php?export_csv=<?=$src?>&month=<?=htmlspecialchars($fMonth)?>" class="btn btn-ghost">⬇ Download CSV</a>
    <span id="<?=$prefix?>-saveStatus" style="font-size:.72rem;color:var(--subtext)"></span>
  </div>

  <div class="cp-kpi-strip">
    <div class="cp-kpi"><div class="kpi-l">Amount w/ VAT</div><div class="kpi-v" id="<?=$prefix?>-kpi-amount_w_vat"><?=$fmt($totals['amount_w_vat'])?></div></div>
    <div class="cp-kpi"><div class="kpi-l">Input Taxes</div><div class="kpi-v" id="<?=$prefix?>-kpi-input_taxes"><?=$fmt($totals['input_taxes'])?></div></div>
    <div class="cp-kpi"><div class="kpi-l">Non-VAT Amount</div><div class="kpi-v" id="<?=$prefix?>-kpi-non_vat_amount"><?=$fmt($totals['non_vat_amount'])?></div></div>
    <div class="cp-kpi"><div class="kpi-l">Total VAT Exclusive</div><div class="kpi-v" id="<?=$prefix?>-kpi-total_vat_exclusive"><?=$fmt($totals['total_vat_exclusive'])?></div></div>
    <div class="cp-kpi"><div class="kpi-l">Purchases</div><div class="kpi-v" id="<?=$prefix?>-kpi-purchases"><?=$fmt($totals['purchases'])?></div></div>
    <div class="cp-kpi"><div class="kpi-l">Total rows</div><div class="kpi-v"><?=count($rows)?></div></div>
  </div>

  <div class="cp-table-wrap">
  <table class="cp-table" id="<?=$prefix?>-table">
    <thead>
      <tr>
        <th rowspan="2" style="width:28px">#</th>
        <th rowspan="2" style="width:80px">DATE</th>
        <th rowspan="2" style="width:110px">TIN</th>
        <th rowspan="2" style="min-width:180px">NAME OF <?=$src==='sup'?'SUPPLIER':'PAYEE'?></th>
        <th rowspan="2" style="width:55px">TYPE</th>
        <th rowspan="2" style="min-width:140px">ADDRESS</th>
        <th colspan="2" class="hdr-group">Particulars</th>
        <th rowspan="2" style="width:90px">AMOUNT w/ VAT</th>
        <th rowspan="2" style="width:90px">TOTAL VAT EXCLUSIVE</th>
        <th rowspan="2" style="width:80px">INPUT TAXES</th>
        <th rowspan="2" style="width:80px">NON-VAT AMOUNT</th>
        <th rowspan="2" style="width:90px">TOTAL AMOUNT VAT EX</th>
        <th rowspan="2" style="width:75px">PURCHASES</th>
        <th rowspan="2" style="width:75px">STAFF MEAL</th>
        <th rowspan="2" style="width:60px">FARE</th>
        <th rowspan="2" style="width:80px">DRINKING WATER</th>
        <th rowspan="2" style="width:75px">OTHER SUPPLIES</th>
        <th rowspan="2" style="width:75px">DELIVERY FEE</th>
        <th rowspan="2" style="width:80px">KITCHEN EQUIPMENT</th>
        <th rowspan="2" style="width:80px">PEST CONTROL</th>
        <th rowspan="2" style="width:80px">OFFICE SUPPLIES</th>
        <th rowspan="2" style="width:85px">BIO AUGMENTATION</th>
        <th rowspan="2" style="width:60px">MISC</th>
        <th rowspan="2" style="width:90px">REPAIRS &amp; MAINTENANCE</th>
        <th rowspan="2" style="width:95px">INTERNET &amp; COMMUNICATION</th>
        <th rowspan="2" style="width:75px">FUEL &amp; OIL</th>
        <th rowspan="2" style="width:75px">ELECTRICITY</th>
        <th rowspan="2" style="width:75px">BILL WATER</th>
        <th rowspan="2" style="width:90px">REPRESENTATION EXPENSE</th>
        <th rowspan="2" style="width:75px">SALARY</th>
        <th rowspan="2" style="width:85px">SSS, HDMF, PH CONT.</th>
        <th rowspan="2" style="width:80px">TAXES &amp; LICENSES</th>
        <th rowspan="2" style="width:75px">SOLANE</th>
        <th rowspan="2" style="width:75px">M'NIKKI</th>
        <th rowspan="2" style="width:80px">OFFICE EQUIPMENT</th>
        <th rowspan="2" style="width:75px">INSURANCE</th>
        <th rowspan="2" style="width:75px">COMMISSION</th>
        <th rowspan="2" class="th-action">ACTION</th>
      </tr>
      <tr>
        <th class="hdr-row2" style="width:110px">/Description</th>
        <th class="hdr-row2" style="width:70px">O.R./C.R. NO.</th>
      </tr>
    </thead>
    <tbody id="<?=$prefix?>-body">
      <?php foreach ($rows as $i => $r): ?>
      <tr data-id="<?=$r['id']?>">
        <td class="rn"><?=$i+1?></td>
        <td><input class="cp-inp txt dt" type="date" value="<?=htmlspecialchars($r['date_col'] ?? $r['entry_date'])?>" oninput="markDirty(this)"></td>
        <td><div class="ac-wrap">
          <input class="cp-inp txt" type="text" data-col="tin" value="<?=htmlspecialchars($r['tin'] ?? '')?>" placeholder="TIN…" oninput="markDirty(this);triggerAC(this)" autocomplete="off">
          <div class="ac-list"></div>
        </div></td>
        <td><div class="ac-wrap">
          <input class="cp-inp txt" type="text" data-col="supplier_name" value="<?=htmlspecialchars($r['supplier_name'] ?? '')?>" placeholder="Type to search…" oninput="markDirty(this);triggerAC(this)" autocomplete="off" style="min-width:170px">
          <div class="ac-list"></div>
        </div></td>
        <td><select class="cp-inp txt" data-col="vat_status" onchange="markDirty(this)" style="width:54px">
          <?php foreach(['','VAT','NV'] as $t): ?>
          <option value="<?=$t?>" <?=($r['type']==$t)?'selected':''?>><?=$t ?: '–'?></option>
          <?php endforeach; ?>
        </select></td>
        <td><input class="cp-inp txt" type="text" data-col="address" value="<?=htmlspecialchars($r['address'] ?? '')?>" oninput="markDirty(this)" style="min-width:130px"></td>
        <td><input class="cp-inp txt" type="text" value="<?=htmlspecialchars($r['particulars'] ?? '')?>" oninput="markDirty(this)" style="min-width:105px"></td>
        <td><select class="cp-inp txt" data-col="or_cr_no" onchange="markDirty(this)">
          <?php foreach(['','CHARGE SALES INVOICE','PURCHASE ORDER','CASH SALE INVOICE','DELIVERY RECEIPT','ORDER SLIP','BILLING','OFFICIAL RECEIPT','SERVICE INVOICE','SALES INVOICE'] as $doc): ?>
          <option value="<?=htmlspecialchars($doc)?>" <?=(($r['or_cr_no'] ?? '')===$doc)?'selected':''?>><?=$doc !== '' ? htmlspecialchars($doc) : '–'?></option>
          <?php endforeach; ?>
        </select></td>
        <?php foreach ($numCols as $c): ?>
        <td><input class="cp-inp num" type="number" step="0.01" value="<?=(float)$r[$c] ?: ''?>" oninput="markDirty(this);updateTotals('<?=$prefix?>')"></td>
        <?php endforeach; ?>
        <td class="td-action">
          <button class="btn-row-save" onclick="saveRow(this)" title="Save this row">💾 Save</button>
          <button class="btn-del-cp"   onclick="deleteRow(this)" title="Delete this row">✕ Del</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr data-id="0">
        <td class="rn">1</td>
        <td><input class="cp-inp txt dt" type="date" value="<?=date('Y-m-d')?>" oninput="markDirty(this)"></td>
        <td><div class="ac-wrap">
          <input class="cp-inp txt" type="text" data-col="tin" placeholder="TIN…" oninput="markDirty(this);triggerAC(this)" autocomplete="off">
          <div class="ac-list"></div>
        </div></td>
        <td><div class="ac-wrap">
          <input class="cp-inp txt" type="text" data-col="supplier_name" placeholder="Type to search…" oninput="markDirty(this);triggerAC(this)" autocomplete="off" style="min-width:170px">
          <div class="ac-list"></div>
        </div></td>
        <td><select class="cp-inp txt" data-col="vat_status" onchange="markDirty(this)" style="width:54px"><option value="">–</option><option value="VAT">VAT</option><option value="NV">NV</option></select></td>
        <td><input class="cp-inp txt" type="text" data-col="address" oninput="markDirty(this)" style="min-width:130px"></td>
        <td><input class="cp-inp txt" type="text" oninput="markDirty(this)" style="min-width:105px"></td>
        <td><select class="cp-inp txt" data-col="or_cr_no" onchange="markDirty(this)">
          <?php foreach(['','CHARGE SALES INVOICE','PURCHASE ORDER','CASH SALE INVOICE','DELIVERY RECEIPT','ORDER SLIP','BILLING','OFFICIAL RECEIPT','SERVICE INVOICE','SALES INVOICE'] as $doc): ?>
          <option value="<?=htmlspecialchars($doc)?>"><?=$doc !== '' ? htmlspecialchars($doc) : '–'?></option>
          <?php endforeach; ?>
        </select></td>
        <?php foreach ($numCols as $c): ?>
        <td><input class="cp-inp num" type="number" step="0.01" oninput="markDirty(this);updateTotals('<?=$prefix?>')"></td>
        <?php endforeach; ?>
        <td class="td-action">
          <button class="btn-row-save" onclick="saveRow(this)" title="Save this row">💾 Save</button>
          <button class="btn-del-cp"   onclick="deleteRow(this)" title="Delete this row">✕ Del</button>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="tot-lbl" colspan="8">TOTAL</td>
        <?php foreach ($numCols as $c): ?>
        <td id="<?=$prefix?>-tot-<?=$c?>"><?=$fmt($totals[$c])?></td>
        <?php endforeach; ?>
        <td class="td-action-foot"></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <button class="btn-add-cp" onclick="addRow('<?=$prefix?>')">+ Add Row</button>
</div>
<?php
}
?>

<style>
.cp-page { font-family:var(--font-h,'Inter',sans-serif); }

.cp-header-card {
  background:linear-gradient(135deg,#1a4d1a,#2e7d32);
  border-radius:var(--radius,8px); padding:16px 24px;
  margin-bottom:14px; display:flex; align-items:center;
  justify-content:space-between; flex-wrap:wrap; gap:10px;
}
.cp-header-card .eyebrow { font-size:.56rem; text-transform:uppercase; letter-spacing:.14em; color:rgba(255,255,255,.45); margin-bottom:3px; }
.cp-header-card .title   { font-size:1.05rem; font-weight:800; color:#fff; }
.cp-header-card .subtitle{ font-size:.63rem; color:rgba(255,255,255,.5); margin-top:2px; }

.cp-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }

.cp-table-wrap { overflow-x:auto; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.08); margin-bottom:12px; }

.cp-table { border-collapse:collapse; font-size:.68rem; background:#fff; min-width:max-content; }
.cp-table th {
  background:#6aaa20; color:#fff;
  padding:6px 7px; font-size:.58rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em;
  border:1px solid #4e8a10; text-align:center;
  white-space:nowrap; position:sticky; top:0; z-index:2;
}
.cp-table th.hdr-group { background:#4caf50; font-size:.57rem; }
.cp-table th.hdr-row2  { background:#6aaa20; top:28px; }
.cp-table td {
  padding:3px 5px; border:1px solid #d4e6c3;
  vertical-align:middle; white-space:nowrap; background:#fff;
}
.cp-table tbody tr:hover td { background:#f5fff0; }
.cp-table tfoot td {
  background:#6aaa20; color:#fff; font-weight:800;
  border:1px solid #4e8a10; padding:5px 7px;
  font-family:var(--font-m,'monospace'); font-size:.68rem;
  text-align:right;
}
.cp-table tfoot td.tot-lbl { background:#3d6e0c; text-align:left; font-size:.62rem; letter-spacing:.05em; text-transform:uppercase; }

.cp-table td.rn { color:#aaa; font-size:.6rem; text-align:center; width:28px; background:#f9fafb; }

.cp-inp {
  border:none; background:transparent;
  font-family:var(--font-m,'monospace'); font-size:.68rem;
  color:var(--text); outline:none; width:100%;
  padding:2px 4px; border-radius:3px; transition:background .12s;
}
.cp-inp:focus { background:#fffbeb; box-shadow:inset 0 0 0 1px #d4a017; }
.cp-inp.num { text-align:right; }
.cp-inp.txt { text-align:left; }
.cp-inp.dt  { width:100px; }

.cp-table td.td-action, .cp-table th.th-action {
  position:sticky; right:0; z-index:3; background:#f9fafb;
  border-left:2px solid #b5d98a; min-width:70px; width:70px;
  text-align:center; white-space:nowrap;
}
.cp-table th.th-action { background:#3d6e0c; z-index:4; }
.cp-table tbody tr:hover td.td-action { background:#f0fde8; }
.cp-table tfoot td.td-action-foot { position:sticky; right:0; z-index:3; background:#3d6e0c; border-left:2px solid #b5d98a; }

.btn-row-save {
  background:#1a4d1a; color:#fff; border:none;
  border-radius:4px; padding:3px 7px; font-size:.6rem;
  font-weight:700; cursor:pointer; display:block;
  width:100%; margin-bottom:3px; letter-spacing:.02em; transition:background .12s;
}
.btn-row-save:hover { background:#155231; }
.btn-row-save.saving { background:#b45309; }
.btn-row-save.saved  { background:#166534; }
.btn-del-cp {
  background:#fee2e2; border:none; color:#991b1b;
  border-radius:4px; padding:3px 7px; font-size:.6rem;
  font-weight:700; cursor:pointer; display:block; width:100%; transition:background .12s;
}
.btn-del-cp:hover { background:#fecaca; }

.btn-add-cp {
  padding:7px 16px; background:#1a4d1a; color:#fff;
  border:none; border-radius:6px; font-size:.75rem;
  font-weight:700; cursor:pointer; display:inline-flex;
  align-items:center; gap:6px; margin-bottom:34px;
}
.btn-add-cp:hover { background:#155231; }

.cp-kpi-strip { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.cp-kpi {
  background:#fff; border:1px solid #d4e6c3; border-radius:8px;
  padding:10px 16px; flex:1; min-width:140px; border-top:3px solid #6aaa20;
}
.cp-kpi .kpi-l { font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; color:#6b7280; margin-bottom:4px; }
.cp-kpi .kpi-v { font-family:var(--font-m); font-size:.92rem; font-weight:800; color:#1a4d1a; }

.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }
@keyframes fadeSlideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

.ac-wrap { position:relative; width:100%; }
.ac-list {
  display:none; position:absolute; top:calc(100% + 3px); left:0;
  z-index:9999; background:#fff; border:1px solid #c8d4df;
  border-radius:8px; box-shadow:0 8px 28px rgba(0,0,0,.15);
  min-width:280px; max-height:220px; overflow-y:auto;
}
.ac-list.open { display:block; }
.ac-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f0f2f5; font-size:.74rem; }
.ac-item:last-child { border-bottom:none; }
.ac-item:hover { background:#f0fdf4; }
.ac-item .ac-name { font-weight:700; color:#1a1d23; }
.ac-item .ac-tin  { font-family:var(--font-m); font-size:.64rem; color:#6b7280; margin-top:1px; }
.ac-item .ac-addr { font-family:var(--font-m); font-size:.62rem; color:#9ca3af; margin-top:1px; font-style:italic; }

.pl-month-bar {
  display:flex; gap:10px; align-items:center; flex-wrap:wrap;
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
  padding:12px 16px; margin-bottom:18px;
}
.reg-section { margin-bottom:8px; }

/* Tabs */
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
</style>

<div class="cp-page">

<div class="section-header">
  <div>
    <div class="section-title" style="color:#1a1d23">Purchases</div>
    <div class="section-subtitle">Supplier · Cash — mirrors COMMI_JULY_DISBURSEMENT_2026.xlsx</div>
  </div>
</div>

<div class="pv-tabs">
  <button type="button" class="pv-tab active" data-tab="sup" onclick="switchTab('sup')">🚚 Supplier</button>
  <button type="button" class="pv-tab" data-tab="csh" onclick="switchTab('csh')">💵 Cash</button>
</div>

<form method="GET" class="pl-month-bar" id="pl-month-form">
  <label style="font-family:var(--font-m);font-size:.6rem;text-transform:uppercase;letter-spacing:.06em;color:var(--subtext2)">Month</label>
  <input type="month" name="month" class="form-control" style="width:160px" value="<?=htmlspecialchars($fMonth)?>" onchange="this.form.submit()">
  <input type="hidden" name="tab" id="pl-month-form-tab" value="<?=htmlspecialchars($_GET['tab'] ?? 'sup')?>">
  <span style="font-family:var(--font-m);font-size:.68rem;color:var(--subtext2)">Applies to both tabs</span>
</form>

<?php
plRenderRegister('sup', '🚚', 'Supplier Register', 'Charge / credit purchases — billed by a supplier, paid later', 'sup', $supplierRows, $supplierTot, $numCols, $fMonth, $startDate, $fmt, true);
plRenderRegister('csh', '💵', 'Cash Register', 'Over-the-counter purchases paid immediately in cash', 'csh', $cashRows, $cashTot, $numCols, $fMonth, $startDate, $fmt, false);
?>

</div><!-- /cp-page -->

<script>
const FMONTH   = '<?=$fMonth?>';
const FDATE    = '<?=$startDate?>';
const NUM_COLS = <?=json_encode($numCols)?>;
const ENDPOINT = 'commissary_purchases_ledger.php';

const fmt = n => {
  const v = parseFloat(n)||0;
  return v === 0 ? '' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
};
const flt = v => parseFloat(String(v??'').replace(/,/g,''))||0;

function markDirty(el) { el.closest('tr').dataset.dirty = '1'; }

// ── Tab switching ──
function switchTab(name) {
  document.querySelectorAll('.pv-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  document.querySelectorAll('.pv-pane').forEach(p => p.classList.toggle('active', p.id === 'sec-' + name));
  const tabField = document.getElementById('pl-month-form-tab');
  if (tabField) tabField.value = name;
}
<?php if (in_array($_GET['tab'] ?? '', ['sup','csh'])): ?>
switchTab('<?= $_GET['tab'] ?>');
<?php endif; ?>

function updateTotals(prefix) {
  const section = document.getElementById('sec-'+prefix);
  const tots = {};
  NUM_COLS.forEach(c => tots[c] = 0);
  section.querySelectorAll(`#${prefix}-body tr[data-id]`).forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    NUM_COLS.forEach((c,i) => { tots[c] += flt(nums[i]?.value); });
  });
  NUM_COLS.forEach(c => {
    const el = document.getElementById(`${prefix}-tot-${c}`);
    if (el) el.textContent = tots[c] > 0 ? tots[c].toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : '0.00';
    const kpi = document.getElementById(`${prefix}-kpi-${c}`);
    if (kpi) kpi.textContent = tots[c] > 0 ? tots[c].toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : '0.00';
  });
}

function getRowData(tr) {
  const date_col    = tr.querySelector('input[type=date]')?.value || FDATE;
  const tin         = tr.querySelector('[data-col="tin"]')?.value || '';
  const supplier    = tr.querySelector('[data-col="supplier_name"]')?.value || '';
  const type        = tr.querySelector('[data-col="vat_status"]')?.value || '';
  const address     = tr.querySelector('[data-col="address"]')?.value || '';
  const textInps    = [...tr.querySelectorAll('input.cp-inp.txt')].filter(i => i.type !== 'date' && !i.dataset.col);
  const particulars = textInps[0]?.value || '';
  const or_cr_no    = tr.querySelector('[data-col="or_cr_no"]')?.value || '';
  const nums = tr.querySelectorAll('input[type=number]');
  const d = { entry_date: date_col, date_col, tin, supplier_name: supplier, type, address, particulars, or_cr_no };
  NUM_COLS.forEach((c,i) => d[c] = flt(nums[i]?.value));
  return d;
}

// ── Vendor Autocomplete (shared by both registers) ──
let acTimer = null;
function triggerAC(inp) { clearTimeout(acTimer); acTimer = setTimeout(() => doAC(inp), 220); }

async function doAC(inp) {
  const q = inp.value.trim();
  const list = inp.closest('.ac-wrap').querySelector('.ac-list');
  if (q.length < 2) { list.classList.remove('open'); return; }
  try {
    const res  = await fetch(ENDPOINT + '?vendor_lookup=1&q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.length) {
      list.innerHTML = '<div class="ac-item" style="color:#6b7280;cursor:default">' +
        '<div class="ac-name">No vendor found</div>' +
        '<div class="ac-tin">Add to Vendor Masterlist first</div></div>';
      list.classList.add('open'); return;
    }
    list.innerHTML = data.map(v => `
      <div class="ac-item"
           data-tin="${escHtml(v.tin)}" data-name="${escHtml(v.company_name)}"
           data-vat="${escHtml(v.vat_status)}" data-addr="${escHtml(v.address)}"
           onclick="fillVendor(this)">
        <div class="ac-name">${escHtml(v.company_name)}</div>
        <div class="ac-tin">TIN: ${escHtml(v.tin)} · ${escHtml(v.vat_status)}</div>
        ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
      </div>`).join('');
    list.classList.add('open');
  } catch(e) {}
}

function fillVendor(item) {
  const row = item.closest('tr');
  const tinInp  = row.querySelector('[data-col="tin"]');
  const nameInp = row.querySelector('[data-col="supplier_name"]');
  const vatSel  = row.querySelector('[data-col="vat_status"]');
  const addrInp = row.querySelector('[data-col="address"]');
  if (tinInp)  tinInp.value  = item.dataset.tin;
  if (nameInp) nameInp.value = item.dataset.name;
  if (vatSel)  vatSel.value  = (item.dataset.vat === 'NV') ? 'NV' : 'VAT';
  if (addrInp) addrInp.value = item.dataset.addr;
  document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
  if (tinInp) markDirty(tinInp);
  const allInps = [...row.querySelectorAll('input:not([type=number]):not([type=date])')];
  const partInp = allInps.find(i => !i.dataset.col);
  if (partInp) partInp.focus();
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap'))
    document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});

async function saveAll(prefix) {
  const section = document.getElementById('sec-'+prefix);
  const src     = section.dataset.src;
  const status  = document.getElementById(prefix+'-saveStatus');
  status.textContent = 'Saving…';
  const rows = section.querySelectorAll(`#${prefix}-body tr[data-id]`);
  let saved = 0;
  for (const tr of rows) {
    const id = parseInt(tr.dataset.id)||0;
    const d  = getRowData(tr);
    const hasData = d.supplier_name || NUM_COLS.some(c => d[c] > 0);
    if (!hasData && id === 0) continue;
    const fd = new FormData();
    fd.append('ajax_save_row','1');
    fd.append('src', src);
    fd.append('id', id);
    for (const [k,v] of Object.entries(d)) fd.append(k,v);
    const res  = await fetch(ENDPOINT,{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      tr.dataset.id    = data.id;
      tr.dataset.dirty = '';
      saved++;
    }
  }
  renumberRows(prefix);
  status.textContent = `✓ Saved ${saved} row(s)`;
  status.style.color = 'var(--accent)';
  showToast(`✓ ${saved} row(s) saved`,'success');
  setTimeout(()=>{status.textContent='';},4000);
}

async function saveRow(btn) {
  const tr      = btn.closest('tr');
  const section = btn.closest('.reg-section');
  const src     = section.dataset.src;
  const prefix  = section.id.replace('sec-','');
  const id      = parseInt(tr.dataset.id) || 0;
  const d       = getRowData(tr);
  const hasData = d.supplier_name || NUM_COLS.some(c => d[c] > 0);
  if (!hasData) { showToast('Nothing to save — fill in supplier or an amount first','error'); return; }

  btn.textContent = '…';
  btn.classList.add('saving');
  btn.disabled = true;

  const fd = new FormData();
  fd.append('ajax_save_row','1');
  fd.append('src', src);
  fd.append('id', id);
  for (const [k,v] of Object.entries(d)) fd.append(k,v);

  try {
    const res  = await fetch(ENDPOINT,{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      tr.dataset.id    = data.id;
      tr.dataset.dirty = '';
      btn.textContent = '✓ Saved';
      btn.classList.remove('saving');
      btn.classList.add('saved');
      showToast('✓ Row saved','success');
      updateTotals(prefix);
      setTimeout(() => {
        btn.textContent = '💾 Save';
        btn.classList.remove('saved');
        btn.disabled = false;
      }, 1800);
    } else {
      throw new Error(data.msg || 'Error');
    }
  } catch(e) {
    btn.textContent = '💾 Save';
    btn.classList.remove('saving');
    btn.disabled = false;
    showToast('❌ Save failed: ' + e.message,'error');
  }
}

async function deleteRow(btn) {
  const tr      = btn.closest('tr');
  const section = btn.closest('.reg-section');
  const src     = section.dataset.src;
  const prefix  = section.id.replace('sec-','');
  const id      = parseInt(tr.dataset.id)||0;
  if (id > 0 && !confirm('Delete this row? This cannot be undone.')) return;
  btn.textContent = '…'; btn.disabled = true;
  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete_row','1'); fd.append('src', src); fd.append('id',id);
    await fetch(ENDPOINT,{method:'POST',body:fd});
  }
  tr.remove();
  renumberRows(prefix);
  updateTotals(prefix);
  showToast('Row deleted','success');
}

function addRow(prefix) {
  const tbody = document.getElementById(prefix+'-body');
  const n     = tbody.querySelectorAll('tr').length + 1;
  const tr    = document.createElement('tr');
  tr.dataset.id = '0';
  let html = `
    <td class="rn">${n}</td>
    <td><input class="cp-inp txt dt" type="date" value="${FDATE}" oninput="markDirty(this)"></td>
    <td><div class="ac-wrap">
      <input class="cp-inp txt" type="text" data-col="tin" placeholder="TIN…"
             oninput="markDirty(this);triggerAC(this)" autocomplete="off">
      <div class="ac-list"></div>
    </div></td>
    <td><div class="ac-wrap">
      <input class="cp-inp txt" type="text" data-col="supplier_name" placeholder="Type to search…"
             oninput="markDirty(this);triggerAC(this)" autocomplete="off" style="min-width:170px">
      <div class="ac-list"></div>
    </div></td>
    <td><select class="cp-inp txt" data-col="vat_status" onchange="markDirty(this)" style="width:54px">
      <option value="">–</option><option value="VAT">VAT</option><option value="NV">NV</option>
    </select></td>
    <td><input class="cp-inp txt" type="text" data-col="address" placeholder=""
               oninput="markDirty(this)" style="min-width:130px"></td>
    <td><input class="cp-inp txt" type="text" placeholder="" oninput="markDirty(this)" style="min-width:105px"></td>
    <td><select class="cp-inp txt" data-col="or_cr_no" onchange="markDirty(this)">
      <option value="">–</option>
      <option value="CHARGE SALES INVOICE">CHARGE SALES INVOICE</option>
      <option value="PURCHASE ORDER">PURCHASE ORDER</option>
      <option value="CASH SALE INVOICE">CASH SALE INVOICE</option>
      <option value="DELIVERY RECEIPT">DELIVERY RECEIPT</option>
      <option value="ORDER SLIP">ORDER SLIP</option>
      <option value="BILLING">BILLING</option>
      <option value="OFFICIAL RECEIPT">OFFICIAL RECEIPT</option>
      <option value="SERVICE INVOICE">SERVICE INVOICE</option>
      <option value="SALES INVOICE">SALES INVOICE</option>
    </select></td>`;
  NUM_COLS.forEach(() => {
    html += `<td><input class="cp-inp num" type="number" step="0.01" placeholder="" oninput="markDirty(this);updateTotals('${prefix}')"></td>`;
  });
  html += `<td class="td-action">
    <button class="btn-row-save" onclick="saveRow(this)" title="Save this row">💾 Save</button>
    <button class="btn-del-cp"   onclick="deleteRow(this)" title="Delete this row">✕ Del</button>
  </td>`;
  tr.innerHTML = html;
  tbody.appendChild(tr);
  tr.querySelector('[data-col="tin"]')?.focus();
}

function renumberRows(prefix) {
  document.querySelectorAll(`#${prefix}-body tr .rn`).forEach((td,i) => td.textContent = i+1);
}

function showToast(msg,type='success') {
  const t = document.createElement('div');
  t.className = `flash flash-${type} toast`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),4000);
}
</script>
</body>
</html>
