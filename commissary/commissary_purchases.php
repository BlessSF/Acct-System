<?php
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    header('Location: ../dashboard.php'); exit;
}

$pdo = getPDO();

// ── Create table if not exists ─────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `commissary_purchases` (
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

// ── AJAX: Vendor lookup (for autocomplete) ─────────────────
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

// ── AJAX: Save a row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_row'])) {
    header('Content-Type: application/json');
    $cols = [
        'date_col','tin','supplier_name','type','address','particulars','or_cr_no',
        'amount_w_vat','total_vat_exclusive','input_taxes','non_vat_amount','total_amount_vat_ex',
        'purchases','staff_meal','fare','drinking_water','other_supplies','delivery_fee',
        'kitchen_equipment','pest_control','office_supplies','bio_augmentation','misc',
        'repairs_maintenance','internet_communication','fuel_oil','electricity','bill_water',
        'representation_expense','salary','sss_hdmf_ph_cont','taxes_licenses','solane',
        'mnikki','office_equipment','insurance','commission',
    ];
    $id = (int)($_POST['id'] ?? 0);
    $date = $_POST['entry_date'] ?? date('Y-m-d');
    $data = ['store_name' => 'Commissary', 'entry_date' => $date];
    foreach ($cols as $c) {
        $v = $_POST[$c] ?? '';
        $data[$c] = in_array($c, ['tin','supplier_name','type','address','particulars','or_cr_no','date_col'])
            ? ($v ?: null)
            : (is_numeric($v) ? (float)$v : 0);
    }
    if ($id > 0) {
        $sets = implode(',', array_map(fn($k) => "`$k`=:$k", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE commissary_purchases SET $sets WHERE id=:id");
        $data['id'] = $id;
        $stmt->execute($data);
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        $keys = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $vals = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
        $stmt = $pdo->prepare("INSERT INTO commissary_purchases ($keys) VALUES ($vals)");
        $stmt->execute($data);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
    exit;
}

// ── AJAX: Delete a row ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_row'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) $pdo->prepare("DELETE FROM commissary_purchases WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Date filter ────────────────────────────────────────────
$fMonth = $_GET['month'] ?? date('Y-m');
[$fYear, $fMon] = explode('-', $fMonth);
$startDate = "$fYear-$fMon-01";
$endDate   = date('Y-m-t', strtotime($startDate));

// ── Fetch rows ─────────────────────────────────────────────
$rows = $pdo->prepare("SELECT * FROM commissary_purchases
    WHERE store_name='Commissary' AND entry_date BETWEEN ? AND ?
    ORDER BY entry_date, sort_order, id");
$rows->execute([$startDate, $endDate]);
$rows = $rows->fetchAll(PDO::FETCH_ASSOC);

// All numeric columns for totals
$numCols = [
    'amount_w_vat','total_vat_exclusive','input_taxes','non_vat_amount','total_amount_vat_ex',
    'purchases','staff_meal','fare','drinking_water','other_supplies','delivery_fee',
    'kitchen_equipment','pest_control','office_supplies','bio_augmentation','misc',
    'repairs_maintenance','internet_communication','fuel_oil','electricity','bill_water',
    'representation_expense','salary','sss_hdmf_ph_cont','taxes_licenses','solane',
    'mnikki','office_equipment','insurance','commission',
];
$totals = array_fill_keys($numCols, 0);
foreach ($rows as $r) {
    foreach ($numCols as $c) $totals[$c] += (float)$r[$c];
}

$pageTitle  = 'Commissary Purchases';
$activePage = 'commissary_purchases';
include '../layout.php';
?>

<style>
.cp-page { font-family:var(--font-h,'Inter',sans-serif); }

/* Header card */
.cp-header-card {
  background:linear-gradient(135deg,#1a4d1a,#2e7d32);
  border-radius:var(--radius,8px); padding:16px 24px;
  margin-bottom:14px; display:flex; align-items:center;
  justify-content:space-between; flex-wrap:wrap; gap:10px;
}
.cp-header-card .eyebrow { font-size:.56rem; text-transform:uppercase; letter-spacing:.14em; color:rgba(255,255,255,.45); margin-bottom:3px; }
.cp-header-card .title   { font-size:1.05rem; font-weight:800; color:#fff; }
.cp-header-card .subtitle{ font-size:.63rem; color:rgba(255,255,255,.5); margin-top:2px; }

/* Controls */
.cp-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }

/* Table wrap */
.cp-table-wrap { overflow-x:auto; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.08); margin-bottom:12px; }

/* Main table */
.cp-table { border-collapse:collapse; font-size:.68rem; background:#fff; min-width:max-content; }
.cp-table th {
  background:#6aaa20; color:#fff;
  padding:6px 7px; font-size:.58rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em;
  border:1px solid #4e8a10; text-align:center;
  white-space:nowrap; position:sticky; top:0; z-index:2;
}
.cp-table th.hdr-group {
  background:#4caf50; font-size:.57rem;
}
.cp-table th.hdr-row2 {
  background:#6aaa20; top:28px;
}
.cp-table td {
  padding:3px 5px; border:1px solid #d4e6c3;
  vertical-align:middle; white-space:nowrap;
  background:#fff;
}
.cp-table tbody tr:hover td { background:#f5fff0; }
.cp-table tfoot td {
  background:#6aaa20; color:#fff; font-weight:800;
  border:1px solid #4e8a10; padding:5px 7px;
  font-family:var(--font-m,'monospace'); font-size:.68rem;
  text-align:right;
}
.cp-table tfoot td.tot-lbl {
  background:#3d6e0c; text-align:left; font-size:.62rem;
  letter-spacing:.05em; text-transform:uppercase;
}

/* Row number column */
.cp-table td.rn { color:#aaa; font-size:.6rem; text-align:center; width:28px; background:#f9fafb; }

/* Inputs */
.cp-inp {
  border:none; background:transparent;
  font-family:var(--font-m,'monospace'); font-size:.68rem;
  color:var(--text); outline:none; width:100%;
  padding:2px 4px; border-radius:3px;
  transition:background .12s;
}
.cp-inp:focus { background:#fffbeb; box-shadow:inset 0 0 0 1px #d4a017; }
.cp-inp.num   { text-align:right; }
.cp-inp.txt   { text-align:left; }
.cp-inp.dt    { width:100px; }

/* ── Sticky Action Column ── */
.cp-table td.td-action,
.cp-table th.th-action {
  position: sticky;
  right: 0;
  z-index: 3;
  background: #f9fafb;
  border-left: 2px solid #b5d98a;
  min-width: 70px;
  width: 70px;
  text-align: center;
  white-space: nowrap;
}
.cp-table th.th-action {
  background: #3d6e0c;
  z-index: 4;
}
.cp-table tbody tr:hover td.td-action { background: #f0fde8; }
.cp-table tfoot td.td-action-foot {
  position: sticky;
  right: 0;
  z-index: 3;
  background: #3d6e0c;
  border-left: 2px solid #b5d98a;
}

/* Per-row action buttons */
.btn-row-save {
  background: #1a4d1a; color: #fff; border: none;
  border-radius: 4px; padding: 3px 7px; font-size: .6rem;
  font-weight: 700; cursor: pointer; display: block;
  width: 100%; margin-bottom: 3px; letter-spacing: .02em;
  transition: background .12s;
}
.btn-row-save:hover { background: #155231; }
.btn-row-save.saving { background: #b45309; }
.btn-row-save.saved  { background: #166534; }
.btn-del-cp {
  background: #fee2e2; border: none; color: #991b1b;
  border-radius: 4px; padding: 3px 7px; font-size: .6rem;
  font-weight: 700; cursor: pointer; display: block;
  width: 100%; transition: background .12s;
}
.btn-del-cp:hover { background: #fecaca; }

/* Add row button */
.btn-add-cp {
  padding:7px 16px; background:#1a4d1a; color:#fff;
  border:none; border-radius:6px; font-size:.75rem;
  font-weight:700; cursor:pointer; display:inline-flex;
  align-items:center; gap:6px;
}
.btn-add-cp:hover { background:#155231; }

/* KPI strip */
.cp-kpi-strip {
  display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;
}
.cp-kpi {
  background:#fff; border:1px solid #d4e6c3; border-radius:8px;
  padding:10px 16px; flex:1; min-width:140px;
  border-top:3px solid #6aaa20;
}
.cp-kpi .kpi-l { font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; color:#6b7280; margin-bottom:4px; }
.cp-kpi .kpi-v { font-family:var(--font-m); font-size:.92rem; font-weight:800; color:#1a4d1a; }

.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }
@keyframes fadeSlideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── Vendor Autocomplete ── */
.ac-wrap { position:relative; width:100%; }
.ac-list {
  display:none; position:absolute; top:calc(100% + 3px); left:0;
  z-index:9999; background:#fff; border:1px solid #c8d4df;
  border-radius:8px; box-shadow:0 8px 28px rgba(0,0,0,.15);
  min-width:280px; max-height:220px; overflow-y:auto;
}
.ac-list.open { display:block; }
.ac-item {
  padding:8px 12px; cursor:pointer; border-bottom:1px solid #f0f2f5;
  font-size:.74rem;
}
.ac-item:last-child { border-bottom:none; }
.ac-item:hover { background:#f0fdf4; }
.ac-item .ac-name { font-weight:700; color:#1a1d23; }
.ac-item .ac-tin  { font-family:var(--font-m); font-size:.64rem; color:#6b7280; margin-top:1px; }
.ac-item .ac-addr { font-family:var(--font-m); font-size:.62rem; color:#9ca3af; margin-top:1px; font-style:italic; }
</style>

<div class="cp-page">

<!-- Header -->
<div class="cp-header-card">
  <div>
    <div class="eyebrow">Commissary · Project C</div>
    <div class="title">Purchases Register</div>
    <div class="subtitle">All purchase entries with VAT breakdown and expense classification</div>
  </div>
  <span style="background:rgba(255,255,255,.15);color:#fff;padding:5px 14px;border-radius:20px;font-size:.63rem;font-weight:600">🛒 Commissary</span>
</div>

<!-- Controls -->
<div class="cp-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="month" name="month" class="form-control" style="width:160px"
           value="<?= htmlspecialchars($fMonth) ?>" onchange="this.form.submit()">
    <button type="button" class="btn btn-primary" onclick="saveAll()">💾 Save All</button>
    <a href="commissary_purchases.php?export_csv=1&month=<?= htmlspecialchars($fMonth) ?>" class="btn btn-ghost">⬇ Download Excel</a>
    <a href="commissary_supplier.php" class="btn btn-ghost">🚚 Supplier</a>
    <a href="commissary_purchases_ledger.php?month=<?= htmlspecialchars($fMonth) ?>#sec-csh" class="btn btn-ghost">💵 Cash</a>
    <span id="saveStatus" style="font-size:.72rem;color:var(--subtext)"></span>
  </form>
</div>

<!-- KPI strip -->
<?php
$fmt = fn($v) => number_format((float)$v, 2);
?>
<div class="cp-kpi-strip">
  <div class="cp-kpi"><div class="kpi-l">Amount w/ VAT</div><div class="kpi-v" id="kpi-amount_w_vat"><?= $fmt($totals['amount_w_vat']) ?></div></div>
  <div class="cp-kpi"><div class="kpi-l">Input Taxes</div><div class="kpi-v" id="kpi-input_taxes"><?= $fmt($totals['input_taxes']) ?></div></div>
  <div class="cp-kpi"><div class="kpi-l">Non-VAT Amount</div><div class="kpi-v" id="kpi-non_vat_amount"><?= $fmt($totals['non_vat_amount']) ?></div></div>
  <div class="cp-kpi"><div class="kpi-l">Total VAT Exclusive</div><div class="kpi-v" id="kpi-total_vat_exclusive"><?= $fmt($totals['total_vat_exclusive']) ?></div></div>
  <div class="cp-kpi"><div class="kpi-l">Purchases</div><div class="kpi-v" id="kpi-purchases"><?= $fmt($totals['purchases']) ?></div></div>
  <div class="cp-kpi"><div class="kpi-l">Total rows</div><div class="kpi-v"><?= count($rows) ?></div></div>
</div>

<!-- Table -->
<div class="cp-table-wrap">
<table class="cp-table" id="cp-table">
  <thead>
    <!-- Row 1: group headers -->
    <tr>
      <th rowspan="2" style="width:28px">#</th>
      <th rowspan="2" style="width:80px">DATE</th>
      <th rowspan="2" style="width:110px">TIN</th>
      <th rowspan="2" style="min-width:180px">NAME OF SUPPLIER</th>
      <th rowspan="2" style="width:55px">TYPE</th>
      <th rowspan="2" style="min-width:140px">ADDRESS</th>
      <th colspan="2" class="hdr-group">Particulars</th>
      <th rowspan="2" style="width:90px">AMOUNT w/ VAT</th>
      <th rowspan="2" style="width:90px">TOTAL VAT EXCLUSIVE</th>
      <th rowspan="2" style="width:80px">INPUT TAXES</th>
      <th rowspan="2" style="width:80px">NON-VAT AMOUNT</th>
      <th rowspan="2" style="width:90px">TOTAL AMOUNT VAT EX</th>
      <!-- Expense columns -->
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
    <!-- Row 2: sub-headers (only for the genuine 2-column Particulars group) -->
    <tr>
      <th class="hdr-row2" style="width:110px">/Description</th>
      <th class="hdr-row2" style="width:70px">O.R./C.R. NO.</th>
    </tr>
  </thead>
  <tbody id="cp-body">
    <?php foreach ($rows as $i => $r): ?>
    <tr data-id="<?= $r['id'] ?>">
      <td class="rn"><?= $i+1 ?></td>
      <td><input class="cp-inp txt dt" type="date" value="<?= htmlspecialchars($r['date_col'] ?? $r['entry_date']) ?>" oninput="markDirty(this)"></td>
      <td><div class="ac-wrap">
        <input class="cp-inp txt" type="text" data-col="tin"
               value="<?= htmlspecialchars($r['tin'] ?? '') ?>" placeholder="TIN…"
               oninput="markDirty(this);triggerAC(this)" autocomplete="off">
        <div class="ac-list"></div>
      </div></td>
      <td><div class="ac-wrap">
        <input class="cp-inp txt" type="text" data-col="supplier_name"
               value="<?= htmlspecialchars($r['supplier_name'] ?? '') ?>" placeholder="Type to search…"
               oninput="markDirty(this);triggerAC(this)" autocomplete="off" style="min-width:170px">
        <div class="ac-list"></div>
      </div></td>
      <td><select class="cp-inp txt" data-col="vat_status" onchange="markDirty(this)" style="width:54px">
        <?php foreach(['','VAT','NV'] as $t): ?>
        <option value="<?= $t ?>" <?= ($r['type']==$t)?'selected':'' ?>><?= $t ?: '–' ?></option>
        <?php endforeach; ?>
      </select></td>
      <td><input class="cp-inp txt" type="text" data-col="address"
                 value="<?= htmlspecialchars($r['address'] ?? '') ?>" placeholder=""
                 oninput="markDirty(this)" style="min-width:130px"></td>
      <td><input class="cp-inp txt" type="text" value="<?= htmlspecialchars($r['particulars'] ?? '') ?>" placeholder="" oninput="markDirty(this)" style="min-width:105px"></td>
      <td><select class="cp-inp txt" data-col="or_cr_no" onchange="markDirty(this)">
        <?php foreach(['','CHARGE SALES INVOICE','PURCHASE ORDER','CASH SALE INVOICE','DELIVERY RECEIPT','ORDER SLIP','BILLING','OFFICIAL RECEIPT','SERVICE INVOICE','SALES INVOICE'] as $doc): ?>
        <option value="<?= htmlspecialchars($doc) ?>" <?= (($r['or_cr_no'] ?? '')===$doc)?'selected':'' ?>><?= $doc !== '' ? htmlspecialchars($doc) : '–' ?></option>
        <?php endforeach; ?>
      </select></td>
      <?php foreach($numCols as $c): ?>
      <td><input class="cp-inp num" type="number" step="0.01" value="<?= (float)$r[$c] ?: '' ?>" placeholder="" oninput="markDirty(this);updateTotals()"></td>
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
      <td><input class="cp-inp txt dt" type="date" value="<?= date('Y-m-d') ?>" oninput="markDirty(this)"></td>
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
      <td><select class="cp-inp txt" data-col="vat_status" onchange="markDirty(this)" style="width:54px"><option value="">–</option><option value="VAT">VAT</option><option value="NV">NV</option></select></td>
      <td><input class="cp-inp txt" type="text" data-col="address" placeholder=""
                 oninput="markDirty(this)" style="min-width:130px"></td>
      <td><input class="cp-inp txt" type="text" placeholder="" oninput="markDirty(this)" style="min-width:105px"></td>
      <td><select class="cp-inp txt" data-col="or_cr_no" onchange="markDirty(this)">
        <?php foreach(['','CHARGE SALES INVOICE','PURCHASE ORDER','CASH SALE INVOICE','DELIVERY RECEIPT','ORDER SLIP','BILLING','OFFICIAL RECEIPT','SERVICE INVOICE','SALES INVOICE'] as $doc): ?>
        <option value="<?= htmlspecialchars($doc) ?>"><?= $doc !== '' ? htmlspecialchars($doc) : '–' ?></option>
        <?php endforeach; ?>
      </select></td>
      <?php foreach($numCols as $c): ?>
      <td><input class="cp-inp num" type="number" step="0.01" placeholder="" oninput="markDirty(this);updateTotals()"></td>
      <?php endforeach; ?>
      <td class="td-action">
        <button class="btn-row-save" onclick="saveRow(this)" title="Save this row">💾 Save</button>
        <button class="btn-del-cp"   onclick="deleteRow(this)" title="Delete this row">✕ Del</button>
      </td>
    </tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr id="cp-totals-row">
      <td class="tot-lbl" colspan="8">TOTAL</td>
      <?php foreach($numCols as $c): ?>
      <td id="tot-<?= $c ?>"><?= $fmt($totals[$c]) ?></td>
      <?php endforeach; ?>
      <td class="td-action-foot"></td>
    </tr>
  </tfoot>
</table>
</div>

<!-- Add row button -->
<button class="btn-add-cp" onclick="addRow()">+ Add Row</button>

</div><!-- /cp-page -->

<script>
const FMONTH   = '<?= $fMonth ?>';
const FDATE    = '<?= $startDate ?>';
const NUM_COLS = <?= json_encode($numCols) ?>;
const fmt = n => {
  const v = parseFloat(n)||0;
  return v === 0 ? '' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
};
const flt = v => parseFloat(String(v??'').replace(/,/g,''))||0;

function markDirty(el) {
  el.closest('tr').dataset.dirty = '1';
}

function updateTotals() {
  const tots = {};
  NUM_COLS.forEach(c => tots[c] = 0);
  document.querySelectorAll('#cp-body tr[data-id]').forEach(tr => {
    const nums = tr.querySelectorAll('input[type=number]');
    NUM_COLS.forEach((c,i) => { tots[c] += flt(nums[i]?.value); });
  });
  NUM_COLS.forEach(c => {
    const el = document.getElementById('tot-'+c);
    if (el) el.textContent = tots[c] > 0 ? tots[c].toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : '0.00';
    const kpi = document.getElementById('kpi-'+c);
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
  const d = { entry_date: date_col, date_col, tin, supplier_name: supplier,
              type, address, particulars, or_cr_no };
  NUM_COLS.forEach((c,i) => d[c] = flt(nums[i]?.value));
  return d;
}

// ── Vendor Autocomplete ────────────────────────────────────
let acTimer = null;

function triggerAC(inp) {
  clearTimeout(acTimer);
  acTimer = setTimeout(() => doAC(inp), 220);
}

async function doAC(inp) {
  const q = inp.value.trim();
  const list = inp.closest('.ac-wrap').querySelector('.ac-list');
  if (q.length < 2) { list.classList.remove('open'); return; }
  try {
    const res  = await fetch('commissary_purchases.php?vendor_lookup=1&q=' + encodeURIComponent(q));
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
  // Fill TIN, name, VAT type, address from vendor masterlist
  const tinInp  = row.querySelector('[data-col="tin"]');
  const nameInp = row.querySelector('[data-col="supplier_name"]');
  const vatSel  = row.querySelector('[data-col="vat_status"]');
  const addrInp = row.querySelector('[data-col="address"]');
  if (tinInp)  tinInp.value  = item.dataset.tin;
  if (nameInp) nameInp.value = item.dataset.name;
  if (vatSel)  vatSel.value  = (item.dataset.vat === 'NV') ? 'NV' : 'VAT';
  if (addrInp) addrInp.value = item.dataset.addr;
  // Close all dropdowns
  document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
  if (tinInp) markDirty(tinInp);
  // Move focus to particulars (next logical field)
  const allInps = [...row.querySelectorAll('input:not([type=number]):not([type=date])')];
  const partInp = allInps.find(i => !i.dataset.col);
  if (partInp) partInp.focus();
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap'))
    document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});

async function saveAll() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';
  const rows = document.querySelectorAll('#cp-body tr[data-id]');
  let saved = 0;
  for (const tr of rows) {
    const id = parseInt(tr.dataset.id)||0;
    const d  = getRowData(tr);
    // Only save rows with at least a supplier or any non-zero amount
    const hasData = d.supplier_name || NUM_COLS.some(c => d[c] > 0);
    if (!hasData && id === 0) continue;
    const fd = new FormData();
    fd.append('ajax_save_row','1');
    fd.append('id', id);
    for (const [k,v] of Object.entries(d)) fd.append(k,v);
    const res  = await fetch('commissary_purchases.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      tr.dataset.id    = data.id;
      tr.dataset.dirty = '';
      saved++;
    }
  }
  renumberRows();
  status.textContent = `✓ Saved ${saved} row(s)`;
  status.style.color = 'var(--accent)';
  showToast(`✓ ${saved} row(s) saved`,'success');
  setTimeout(()=>{status.textContent='';},4000);
}

// ── Per-row Save ──────────────────────────────────────────
async function saveRow(btn) {
  const tr  = btn.closest('tr');
  const id  = parseInt(tr.dataset.id) || 0;
  const d   = getRowData(tr);
  const hasData = d.supplier_name || NUM_COLS.some(c => d[c] > 0);
  if (!hasData) { showToast('Nothing to save — fill in supplier or an amount first','error'); return; }

  btn.textContent = '…';
  btn.classList.add('saving');
  btn.disabled = true;

  const fd = new FormData();
  fd.append('ajax_save_row','1');
  fd.append('id', id);
  for (const [k,v] of Object.entries(d)) fd.append(k,v);

  try {
    const res  = await fetch('commissary_purchases.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      tr.dataset.id    = data.id;
      tr.dataset.dirty = '';
      btn.textContent = '✓ Saved';
      btn.classList.remove('saving');
      btn.classList.add('saved');
      showToast('✓ Row saved','success');
      updateTotals();
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
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id)||0;
  if (id > 0 && !confirm('Delete this row? This cannot be undone.')) return;
  btn.textContent = '…'; btn.disabled = true;
  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete_row','1'); fd.append('id',id);
    await fetch('commissary_purchases.php',{method:'POST',body:fd});
  }
  tr.remove();
  renumberRows();
  updateTotals();
  showToast('Row deleted','success');
}

function addRow() {
  const tbody = document.getElementById('cp-body');
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
    html += `<td><input class="cp-inp num" type="number" step="0.01" placeholder="" oninput="markDirty(this);updateTotals()"></td>`;
  });
  html += `<td class="td-action">
    <button class="btn-row-save" onclick="saveRow(this)" title="Save this row">💾 Save</button>
    <button class="btn-del-cp"   onclick="deleteRow(this)" title="Delete this row">✕ Del</button>
  </td>`;
  tr.innerHTML = html;
  tbody.appendChild(tr);
  tr.querySelector('[data-col="tin"]')?.focus();
}

function renumberRows() {
  document.querySelectorAll('#cp-body tr .rn').forEach((td,i) => td.textContent = i+1);
}

function showToast(msg,type='success') {
  const t = document.createElement('div');
  t.className = `flash flash-${type} toast`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),4000);
}

// CSV Export
<?php if (isset($_GET['export_csv'])): ?>
window.addEventListener('DOMContentLoaded',()=>{
  // handled server-side below
});
<?php endif; ?>
</script>

<?php
// ── Excel (.xlsx) Export ─────────────────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Commissary'));
    $xlsx->title('Commissary — Purchases');
    $xlsx->subtitle($fMonth);
    $xlsx->blank();
    $xlsx->header(['#','Date','TIN','Name of Supplier','Type','Address','Particulars/Description','O.R./C.R. No.',
        'Amount w/ VAT','Total VAT Exclusive','Input Taxes','Non-VAT Amount','Total Amount VAT Ex',
        'Purchases','Staff Meal','Fare','Drinking Water','Other Supplies','Delivery Fee',
        'Kitchen Equipment','Pest Control','Office Supplies','Bio Augmentation','Misc',
        'Repairs & Maintenance','Internet & Communication','Fuel & Oil','Electricity',
        'Bill Water','Representation Expense','Salary','SSS HDMF PH Cont','Taxes & Licenses',
        'Solane',"M'Nikki",'Office Equipment','Insurance','Commission']);
    foreach ($rows as $i => $r) {
        $line = [$i+1, $r['date_col'] ?? $r['entry_date'], $r['tin'], $r['supplier_name'],
                 $r['type'], $r['address'], $r['particulars'], $r['or_cr_no']];
        foreach ($numCols as $c) $line[] = number_format((float)$r[$c],2);
        $xlsx->row($line);
    }
    $totLine = ['','','','','','','','TOTAL'];
    foreach ($numCols as $c) $totLine[] = number_format($totals[$c],2);
    $xlsx->totalRow($totLine);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->output('commissary_purchases_'.$fMonth.'.xlsx');
}
?>
</body>
</html>