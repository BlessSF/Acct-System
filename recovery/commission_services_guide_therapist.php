<?php
// ============================================================
//  commission_services_guide_therapist.php
//  Editable: Services Price List, Stylist/Services Handle,
//  Commission Fee Guidelines — all saved to DB
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

$pdo  = getPDO();
$user = currentUser();

// ── Create tables ─────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_services_pricelist` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `name`       varchar(200) NOT NULL DEFAULT '',
    `regular`    decimal(10,2) NOT NULL DEFAULT 0.00,
    `promo`      decimal(10,2) NOT NULL DEFAULT 0.00,
    `is_promo`   tinyint(1) NOT NULL DEFAULT 0,
    `sort_order` int(4) NOT NULL DEFAULT 0,
    `saved_by`   varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_stylist_handles` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `price`      decimal(10,2) DEFAULT NULL,
    `name`       varchar(100) NOT NULL DEFAULT '',
    `handles`    text DEFAULT NULL,
    `sort_order` int(4) NOT NULL DEFAULT 0,
    `saved_by`   varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS `recovery_commission_fees` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `service`    varchar(200) NOT NULL DEFAULT '',
    `price`      decimal(10,2) NOT NULL DEFAULT 0.00,
    `fix_cf`     decimal(10,2) NOT NULL DEFAULT 0.00,
    `at_cost`    decimal(10,2) NOT NULL DEFAULT 0.00,
    `sort_order` int(4) NOT NULL DEFAULT 0,
    `saved_by`   varchar(100) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
// Migration: add at_cost to installs created before this column existed
try { $pdo->exec("ALTER TABLE `recovery_commission_fees` ADD COLUMN `at_cost` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `fix_cf`"); }
catch (Throwable $ignored) {}

// ── Seed default data if tables are empty ─────────────────
if (!(int)$pdo->query("SELECT COUNT(*) FROM recovery_services_pricelist")->fetchColumn()) {
    $defaults = [
        ['Nail Cleaning',         269,  209,  0],
        ['Basic Manicure',        319,  239,  0],
        ['Basic Pedicure',        389,  289,  0],
        ['Gel Manicure',          499,  399,  0],
        ['Gel Pedicure',          659,  499,  0],
        ['Gel Removal',           200,  200,  0],
        ['Nail Extension Simple', 1399, 999,  0],
        ['Nail Extension Pd',     2200, 1500, 0],
        ['Promo Nail Cleaning',   209,  209,  1],
        ['Promo Basic Manicure',  239,  239,  1],
        ['Promo Basic Pedicure',  289,  289,  1],
        ['Promo Gel Manicure',    399,  399,  1],
        ['Promo Gel Pedicure',    499,  499,  1],
    ];
    $ins = $pdo->prepare("INSERT INTO recovery_services_pricelist (name,regular,promo,is_promo,sort_order,saved_by) VALUES (?,?,?,?,?,'system')");
    foreach ($defaults as $i => $d) $ins->execute([$d[0],$d[1],$d[2],$d[3],$i]);
}

// ── Backfill: add any service from the Sales Report's dropdown that isn't  ──
// in this price list yet, as a $0 placeholder. Without a row here, the Sales
// Services dropdown on recovery_sales_report.php has nothing to auto-fill
// Regular/Promo Price from for that service. Idempotent — only inserts
// names that don't already exist, never touches existing rows.
$ALL_SALES_REPORT_SERVICES = [
    'Nail Cleaning','Basic Manicure','Basic Pedicure','Gel Manicure','Gel Pedicure',
    'Gel Removal','Nail Extension Simple','Nail Extension Pd',
    'Promo Nail Cleaning','Promo Basic Manicure','Promo Basic Pedicure',
    'Promo Gel Manicure','Promo Gel Pedicure','Promo Nail Extension Simple',
    'Promo Nail Extension Pd','Nails Extenson Removal',
    'Reg Foot Spa','Foot Spa + Foot Scrub','Foot Spa + Foot Scrub+ Foot Massage','Foot Reflex',
    'Classic Lash Extensions','Cat Eye/Wispy Lash Ext','Semi Glam','Full Glam','Lash Removal',
    'last lift with tint','PROMO Classic Lash Extensions','PROMO Cat Eye/Wispy Lash Ext',
    'PROMO Semi Glam','PROMO Full Glam','PROMO Lash Lift','Lash Lift',
    'Brow Shaping (Wax Or Tread)','Brow Tinting','Brow Lamination',
    'Brow Shaping + Tint Package','Brow Lamination + Tint Package',
    'Express Head Spa (30 Mins)','Luxury Scalp + Basic Facial','Milk + Honey Scrub',
    'Coffee Detox Scrub','Swedish Massage','Thai Massage','Combination Massage',
    'Daytime Massage Promo(10am -4pm)','Daytime Massage Promo(10am -4pm) shiatsu',
    'Hotel Or Home Service Fee',
    'Face Lifting','Scalp Scrub','Underarm Wax','Chest Wax','Full Arm Wax','Half Arm Wax',
    'Full Leg Wax','Bikini Wax','Chin Wax','Add Ventosa','Add Hotstone','Gc 1000',
    'Package 1 (1hr body massage + 30 mins ventosa)',
    'Package 2 ( 1hr body massage + 30mins hotstone)',
    'Package 3 (1hr body massage + Body Scrub)',
    'Swedish Massage 1.5hrs','Combination Massage 1.5hrs','thai Massage 1.5hrs',
    'Back Massage','Back Massage 30Mins','Deep Cleansing Facial','Hand Massage',
    '30Mins Massage','15 Min. Headspa','Hydrafacial','Basic Facial','Junior Headspa',
    'Foot Massage',
    'Package 1 (Footspa + Foot Scrub+basic mani+ basic pedi)',
    'Package 2 ( Footspa + Gel Mani +Gel Pedi)',
    'Package 3 (Footspa + Foot Massage + Gel Pedi)',
    'Package 1 (45mins Express Headspa + 1hr Body Massage)',
    'Package 2 (45mins Express Headspa + 30mins foot massage )',
    'Package 3 (45mins Express Headspa + Body Scrubi)',
    '30mins Express Headspa','SHIATSU MASSAGE',
    'ADD ON 150 FOR GEL DESIGN','FOOT MASSAGE 1HR.','Ear Candling',
    'PROMO 25mins Express Headspa',
    'ULTIMATE RECOVERY','HEAD TO TOE BLISS','SCALP & FOOT RENEWAL',
    'PAMPER & POLISH','HEAD MASSAGE','HAND AND FOOT MASSAGE',
];
try {
    $existingNames = array_column(
        $pdo->query("SELECT name FROM recovery_services_pricelist")->fetchAll(PDO::FETCH_ASSOC),
        'name'
    );
    $missing = array_diff($ALL_SALES_REPORT_SERVICES, $existingNames);
    if ($missing) {
        $nextSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM recovery_services_pricelist")->fetchColumn() + 1;
        $insMissing = $pdo->prepare("INSERT INTO recovery_services_pricelist (name,regular,promo,is_promo,sort_order,saved_by) VALUES (?,0,0,0,?,'system-backfill')");
        foreach ($missing as $name) {
            $insMissing->execute([$name, $nextSort++]);
        }
    }
} catch (Throwable $ignored) {}

if (!(int)$pdo->query("SELECT COUNT(*) FROM recovery_stylist_handles")->fetchColumn()) {
    $defaults = [
        [269,  'APRIL',    ''],
        [319,  'JAZY',     'Headspa / Massage'],
        [389,  'SHANE',    'Lash / Manicure / Pedicure / Nails Extension / Footspa / Foot Scrub / Body Scrub'],
        [499,  'ANGEL',    ''],
        [659,  'RONALENE', ''],
        [200,  'MILA',     ''],
        [1399, 'ANDREA',   ''],
        [2200, 'JANINE',   ''],
        [null, 'JOY',      ''],
        [null, 'CARMEN',   ''],
        [null, 'RUTH',     ''],
        [null, 'ANGIE',    ''],
    ];
    $ins = $pdo->prepare("INSERT INTO recovery_stylist_handles (price,name,handles,sort_order,saved_by) VALUES (?,?,?,?,'system')");
    foreach ($defaults as $i => $d) $ins->execute([$d[0],$d[1],$d[2],$i]);
}

if (!(int)$pdo->query("SELECT COUNT(*) FROM recovery_commission_fees")->fetchColumn()) {
    $defaults = [
        ['Brow Shaping (Wax or Thread)', 199, 30],
        ['Hotel or Home Service Fee',    300, 30],
        ['Lash Removal',                 250, 30],
        ['Gel Removal',                  200, 30],
        ['Softgel Removal',              300, 30],
        ['Lash Removal',                 300, 30],
    ];
    $ins = $pdo->prepare("INSERT INTO recovery_commission_fees (service,price,fix_cf,sort_order,saved_by) VALUES (?,?,?,?,'system')");
    foreach ($defaults as $i => $d) $ins->execute([$d[0],$d[1],$d[2],$i]);
}

// ── AJAX: Save row ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $table  = $_POST['table'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $so     = (int)($_POST['sort_order'] ?? 0);
        $by     = $user['name'];

        $allowed = ['recovery_services_pricelist','recovery_stylist_handles','recovery_commission_fees'];
        if (!in_array($table, $allowed)) { echo json_encode(['ok'=>false,'msg'=>'Invalid table']); exit; }

        if ($table === 'recovery_services_pricelist') {
            $name   = trim($_POST['name'] ?? '');
            $reg    = (float)($_POST['regular'] ?? 0);
            $promo  = (float)($_POST['promo'] ?? 0);
            $isP    = (int)($_POST['is_promo'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE recovery_services_pricelist SET name=?,regular=?,promo=?,is_promo=?,sort_order=?,saved_by=? WHERE id=?")
                    ->execute([$name,$reg,$promo,$isP,$so,$by,$id]);
            } else {
                $pdo->prepare("INSERT INTO recovery_services_pricelist (name,regular,promo,is_promo,sort_order,saved_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$name,$reg,$promo,$isP,$so,$by]);
                $id = (int)$pdo->lastInsertId();
            }
        } elseif ($table === 'recovery_stylist_handles') {
            $price   = strlen(trim($_POST['price'] ?? '')) ? (float)$_POST['price'] : null;
            $name    = trim($_POST['name'] ?? '');
            $handles = trim($_POST['handles'] ?? '');
            if ($id > 0) {
                $pdo->prepare("UPDATE recovery_stylist_handles SET price=?,name=?,handles=?,sort_order=?,saved_by=? WHERE id=?")
                    ->execute([$price,$name,$handles,$so,$by,$id]);
            } else {
                $pdo->prepare("INSERT INTO recovery_stylist_handles (price,name,handles,sort_order,saved_by) VALUES (?,?,?,?,?)")
                    ->execute([$price,$name,$handles,$so,$by]);
                $id = (int)$pdo->lastInsertId();
            }
        } elseif ($table === 'recovery_commission_fees') {
            $service = trim($_POST['service'] ?? '');
            $price   = (float)($_POST['price'] ?? 0);
            $fixCf   = (float)($_POST['fix_cf'] ?? 0);
            $atCost  = (float)($_POST['at_cost'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE recovery_commission_fees SET service=?,price=?,fix_cf=?,at_cost=?,sort_order=?,saved_by=? WHERE id=?")
                    ->execute([$service,$price,$fixCf,$atCost,$so,$by,$id]);
            } else {
                $pdo->prepare("INSERT INTO recovery_commission_fees (service,price,fix_cf,at_cost,sort_order,saved_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$service,$price,$fixCf,$atCost,$so,$by]);
                $id = (int)$pdo->lastInsertId();
            }
        }

        echo json_encode(['ok'=>true,'id'=>$id]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Delete row ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $table   = $_POST['table'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $allowed = ['recovery_services_pricelist','recovery_stylist_handles','recovery_commission_fees'];
    if (in_array($table, $allowed) && $id > 0) {
        $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
    }
    echo json_encode(['ok'=>true]); exit;
}

// ── Load data ──────────────────────────────────────────────
$priceList  = $pdo->query("SELECT * FROM recovery_services_pricelist ORDER BY sort_order,id")->fetchAll();
$stylistList = $pdo->query("SELECT * FROM recovery_stylist_handles ORDER BY sort_order,id")->fetchAll();
$commList   = $pdo->query("SELECT * FROM recovery_commission_fees ORDER BY sort_order,id")->fetchAll();

$pageTitle  = 'Commission Guide';
$activePage = 'commission_services_guide';
include '../layout.php';
?>

<style>
.csgt-header {
  background:linear-gradient(135deg,#1a1a1a 0%,#2d2d2d 100%);
  border-radius:var(--radius); padding:20px 26px; margin-bottom:22px;
  display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.csgt-header .eyebrow { font-family:var(--font-m); font-size:.58rem; text-transform:uppercase; letter-spacing:.14em; color:rgba(255,255,255,.45); margin-bottom:4px; }
.csgt-header .title { font-size:1.2rem; font-weight:800; color:#fff; }
.csgt-header .subtitle { font-family:var(--font-m); font-size:.67rem; color:rgba(255,255,255,.5); margin-top:4px; }

.csgt-section { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 2px 12px rgba(0,0,0,.06); overflow:hidden; margin-bottom:22px; }
.csgt-section-title { padding:10px 16px; font-family:var(--font-m); font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#fff; display:flex; align-items:center; justify-content:space-between; }

/* ── Table ── */
.csgt-table { width:100%; border-collapse:collapse; }
.csgt-table th { padding:8px 10px; font-family:var(--font-m); font-size:.63rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; text-align:center; color:#fff; border:1px solid rgba(255,255,255,.15); white-space:nowrap; }
.csgt-table th.left { text-align:left; }
.csgt-table td { padding:4px 6px; border:1px solid #e5e7eb; font-size:.78rem; text-align:center; vertical-align:middle; }
.csgt-table tbody tr:nth-child(even) td { background:#fafafa; }
.csgt-table tbody tr:hover td { background:#f0fdf4 !important; }
.csgt-table td.left { text-align:left; }
.csgt-table tr.promo td { background:#fbdada !important; color:#7b1a1a; font-weight:700; }

/* ── Inline inputs ── */
.ei { width:100%; border:1px solid transparent; background:transparent; font-family:var(--font-m); font-size:.77rem; padding:4px 6px; outline:none; border-radius:4px; color:var(--text); }
.ei:focus { border-color:#f5c542; background:#fffbeb; }
.ei.num { text-align:right; }
.ei.left { text-align:left; }
.ei.name-inp { font-weight:700; }

/* ── Buttons ── */
.btn-sv { padding:3px 10px; background:#15803d; color:#fff; border:none; border-radius:4px; font-size:.63rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.btn-sv:hover { background:#166534; }
.btn-dl { padding:3px 7px; background:transparent; color:#dc2626; border:1px solid #fca5a5; border-radius:4px; font-size:.63rem; cursor:pointer; }
.btn-dl:hover { background:#fee2e2; }
.btn-add { width:100%; padding:7px; background:transparent; border:1px dashed #d1d5db; border-radius:6px; font-family:var(--font-m); font-size:.65rem; color:#6b7280; cursor:pointer; margin:4px 0 0; }
.btn-add:hover { background:#f0fdf4; border-color:#15803d; color:#15803d; }
.row-st { font-family:var(--font-m); font-size:.55rem; color:#15803d; display:none; text-align:center; }

.promo-chk { width:16px; height:16px; cursor:pointer; accent-color:#dc2626; }
.toast { position:fixed; top:68px; right:22px; z-index:9999; max-width:320px; animation:fadeSlideDown .3s ease; }

/* ── Service search bar ── */
.csgt-search-wrap {
  position:relative; display:flex; align-items:center; gap:8px;
  background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
  padding:10px 14px; margin-bottom:18px; box-shadow:0 2px 12px rgba(0,0,0,.06);
}
.csgt-search-icon { font-size:.85rem; opacity:.6; }
.csgt-search-inp {
  flex:1; border:none; outline:none; background:transparent;
  font-family:var(--font-m); font-size:.8rem; color:var(--text); padding:4px 2px;
}
.csgt-search-clear {
  border:none; background:#f3f4f6; color:#6b7280; width:20px; height:20px; border-radius:50%;
  font-size:.62rem; cursor:pointer; line-height:1; flex-shrink:0;
}
.csgt-search-clear:hover { background:#e5e7eb; color:#111827; }
.csgt-search-count { font-family:var(--font-m); font-size:.63rem; color:#6b7280; white-space:nowrap; }
.csgt-row-hidden { display:none !important; }
.csgt-search-hl { background:#fef08a; border-radius:2px; padding:0 1px; }
</style>

<!-- Header -->
<div class="csgt-header">
  <div>
    <div class="eyebrow">Recovery Branch · Reference</div>
    <div class="title">Commission, Services Guide &amp; Therapist</div>
    <div class="subtitle">Editable — click any cell to edit, then Save per row</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span id="saveAllStatus" style="font-family:var(--font-m);font-size:.72rem;color:#166534;font-weight:700"></span>
    <button type="button" class="btn-sv" style="padding:9px 18px;font-size:.8rem" onclick="saveAllRows()">💾 Save All</button>
  </div>
</div>

<!-- Search bar — filters Services Price List & MKTG tables by service name -->
<div class="csgt-search-wrap">
  <span class="csgt-search-icon">🔍</span>
  <input type="text" id="serviceSearch" class="csgt-search-inp"
         placeholder="Search a service… (filters Services Price List &amp; MKTG guide below)"
         oninput="filterServices(this.value)">
  <button type="button" id="serviceSearchClear" class="csgt-search-clear" onclick="clearServiceSearch()" style="display:none">✕</button>
  <span id="serviceSearchCount" class="csgt-search-count"></span>
</div>

<!-- ══════════════════════════════════════════════════════════
     SERVICES PRICE LIST
════════════════════════════════════════════════════════════ -->
<div class="csgt-section">
  <div class="csgt-section-title" style="background:#1a2f5c">
    💅 Services Price List
  </div>
  <table class="csgt-table" id="pl-table">
    <thead style="background:#1a2f5c">
      <tr>
        <th class="left" style="min-width:200px">Services</th>
        <th style="width:110px">Regular Price</th>
        <th style="width:110px">Promo Price</th>
        <th style="width:60px">Is Promo?</th>
        <th style="width:90px">Action</th>
      </tr>
    </thead>
    <tbody id="pl-body">
      <?php foreach ($priceList as $i => $r):
        $needsPrice = !$r['regular'] && !$r['promo'];
      ?>
      <tr data-id="<?= $r['id'] ?>" class="<?= $r['is_promo'] ? 'promo' : '' ?>">
        <td class="left">
          <input class="ei left" type="text" value="<?= htmlspecialchars($r['name']) ?>" placeholder="Service name…">
          <?php if ($needsPrice): ?><span title="No price set yet" style="margin-left:6px;font-size:.65rem;color:#b45309;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:1px 6px;font-family:var(--font-m)">needs price</span><?php endif; ?>
        </td>
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['regular'] ?: '' ?>" placeholder="0" onfocus="this.select()"></td>
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['promo'] ?: '' ?>"   placeholder="0" onfocus="this.select()"></td>
        <td><input class="promo-chk" type="checkbox" <?= $r['is_promo'] ? 'checked' : '' ?> onchange="this.closest('tr').className=this.checked?'promo':''"></td>
        <td>
          <button class="btn-sv" onclick="saveRow(this,'recovery_services_pricelist','pl')">Save</button>
          <button class="btn-dl" onclick="deleteRow(this,'recovery_services_pricelist')">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn-add" onclick="addPlRow()">+ Add Service</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     STYLIST NAME / SERVICES HANDLE
════════════════════════════════════════════════════════════ -->
<div class="csgt-section">
  <div class="csgt-section-title" style="background:#a67c00">
    💇 Stylist Name &amp; Services Handle
  </div>
  <table class="csgt-table" id="sh-table">
    <thead style="background:#a67c00">
      <tr>
        <th style="width:120px">Regular Price</th>
        <th style="width:150px">Stylist Name</th>
        <th class="left">Services Handle</th>
        <th style="width:90px">Action</th>
      </tr>
    </thead>
    <tbody id="sh-body">
      <?php foreach ($stylistList as $r): ?>
      <tr data-id="<?= $r['id'] ?>">
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['price'] !== null ? $r['price'] : '' ?>" placeholder="—" onfocus="this.select()"></td>
        <td><input class="ei name-inp" type="text" value="<?= htmlspecialchars($r['name']) ?>" placeholder="Stylist name…"></td>
        <td class="left"><input class="ei left" type="text" value="<?= htmlspecialchars($r['handles']) ?>" placeholder="Services handled…"></td>
        <td>
          <button class="btn-sv" onclick="saveRow(this,'recovery_stylist_handles','sh')">Save</button>
          <button class="btn-dl" onclick="deleteRow(this,'recovery_stylist_handles')">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn-add" onclick="addShRow()">+ Add Stylist</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     MKTG — AT COST & COMMISSION FEE GUIDE
     (This is the lookup source used by the Sales Report's
     Sales Services — Influencer/Marketing section: pick a
     Service there and At Cost + Commission Fee auto-fill from here.)
════════════════════════════════════════════════════════════ -->
<div class="csgt-section">
  <div class="csgt-section-title" style="background:#7b1a1a">
    <span>🎯 MKTG — At Cost &amp; Commission Fee Guide (Influencer / Free Services)</span>
  </div>
  <table class="csgt-table" id="cf-table">
    <thead style="background:#7b1a1a">
      <tr>
        <th class="left" style="min-width:260px">Services</th>
        <th style="width:100px">Price</th>
        <th style="width:100px">Fix CF</th>
        <th style="width:100px">At Cost</th>
        <th style="width:110px;background:#1a4d1a">Total Mktg Exp.</th>
        <th style="width:90px">Action</th>
      </tr>
    </thead>
    <tbody id="cf-body">
      <?php foreach ($commList as $r): ?>
      <tr data-id="<?= $r['id'] ?>">
        <td class="left">
          <input class="ei left" type="text" value="<?= htmlspecialchars($r['service'] ?? '') ?>" placeholder="Service name" onfocus="this.select()">
        </td>
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['price'] ?: '' ?>" placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['fix_cf'] ?: '' ?>"  placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
        <td><input class="ei num" type="number" step="0.01" value="<?= $r['at_cost'] ?: '' ?>"  placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
        <td><input class="ei num" type="text" value="<?= number_format((float)$r['fix_cf'] + (float)$r['at_cost'], 2) ?>" readonly style="font-weight:700;background:#f0fdf4;color:#166534"></td>
        <td>
          <button class="btn-sv" onclick="saveRow(this,'recovery_commission_fees','cf')">Save</button>
          <button class="btn-dl" onclick="deleteRow(this,'recovery_commission_fees')">✕</button>
          <div class="row-st"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn-add" onclick="addCfRow()">+ Add Row</button>
</div>

<script>
// ── Save All ──────────────────────────────────────────────
async function saveAllRows() {
  const status = document.getElementById('saveAllStatus');
  if (status) status.textContent = 'Saving…';

  const jobs = [];
  document.querySelectorAll('#pl-body tr[data-id]').forEach(tr => {
    const btn = tr.querySelector('.btn-sv');
    if (btn) jobs.push([btn, 'recovery_services_pricelist', 'pl']);
  });
  document.querySelectorAll('#sh-body tr[data-id]').forEach(tr => {
    const btn = tr.querySelector('.btn-sv');
    if (btn) jobs.push([btn, 'recovery_stylist_handles', 'sh']);
  });
  document.querySelectorAll('#cf-body tr[data-id]').forEach(tr => {
    const btn = tr.querySelector('.btn-sv');
    if (btn) jobs.push([btn, 'recovery_commission_fees', 'cf']);
  });

  for (const [btn, table, type] of jobs) {
    await saveRow(btn, table, type);
  }

  if (status) {
    status.textContent = `✓ Saved ${jobs.length} row(s)`;
    setTimeout(() => status.textContent = '', 3000);
  }
}

// ── Generic save ──────────────────────────────────────────
async function saveRow(btn, table, type) {
  const tr = btn.closest('tr');
  const st = tr.querySelector('.row-st');
  const fd = new FormData();
  fd.append('ajax_save','1');
  fd.append('table', table);
  fd.append('id', tr.dataset.id || 0);
  fd.append('sort_order', Array.from(tr.parentElement.children).indexOf(tr));

  if (type === 'pl') {
    const inps = tr.querySelectorAll('input[type=text], input[type=number]');
    const chk  = tr.querySelector('input[type=checkbox]');
    fd.append('name',     inps[0]?.value.trim() || '');
    fd.append('regular',  parseFloat(inps[1]?.value) || 0);
    fd.append('promo',    parseFloat(inps[2]?.value) || 0);
    fd.append('is_promo', chk?.checked ? 1 : 0);
  } else if (type === 'sh') {
    const nums = tr.querySelectorAll('input[type=number]');
    const txts = tr.querySelectorAll('input[type=text]');
    fd.append('price',   nums[0]?.value !== '' ? parseFloat(nums[0].value) : '');
    fd.append('name',    txts[0]?.value.trim() || '');
    fd.append('handles', txts[1]?.value.trim() || '');
  } else if (type === 'cf') {
    const txts    = tr.querySelectorAll('input[type=text]');
    const numInps = tr.querySelectorAll('input[type=number]');
    fd.append('service', txts[0]?.value.trim() || '');
    fd.append('price',   parseFloat(numInps[0]?.value) || 0);
    fd.append('fix_cf',  parseFloat(numInps[1]?.value) || 0);
    fd.append('at_cost', parseFloat(numInps[2]?.value) || 0);
  }

  btn.textContent = '…'; btn.disabled = true;
  try {
    const res  = await fetch('commission_services_guide_therapist.php', {method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      tr.dataset.id = data.id;
      st.textContent = '✓'; st.style.display = 'block';
      setTimeout(() => st.style.display = 'none', 2000);
      showToast('Saved', 'success');
    } else {
      alert('Error: ' + data.msg);
    }
  } catch(e) { alert('Network error'); }
  btn.textContent = 'Save'; btn.disabled = false;
}

// ── Generic delete ────────────────────────────────────────
async function deleteRow(btn, table) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id) || 0;
  if (id > 0 && !confirm('Delete this row?')) return;
  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete','1');
    fd.append('table', table);
    fd.append('id', id);
    await fetch('commission_services_guide_therapist.php', {method:'POST',body:fd});
  }
  tr.remove();
}

// ── Add rows ──────────────────────────────────────────────
function addPlRow() {
  const tbody = document.getElementById('pl-body');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td class="left"><input class="ei left" type="text" placeholder="Service name…"></td>
    <td><input class="ei num" type="number" step="0.01" placeholder="0" onfocus="this.select()"></td>
    <td><input class="ei num" type="number" step="0.01" placeholder="0" onfocus="this.select()"></td>
    <td><input class="promo-chk" type="checkbox" onchange="this.closest('tr').className=this.checked?'promo':''"></td>
    <td>
      <button class="btn-sv" onclick="saveRow(this,'recovery_services_pricelist','pl')">Save</button>
      <button class="btn-dl" onclick="deleteRow(this,'recovery_services_pricelist')">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[type=text]').focus();
}

function addShRow() {
  const tbody = document.getElementById('sh-body');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td><input class="ei num" type="number" step="0.01" placeholder="—" onfocus="this.select()"></td>
    <td><input class="ei name-inp" type="text" placeholder="Stylist name…"></td>
    <td class="left"><input class="ei left" type="text" placeholder="Services handled…"></td>
    <td>
      <button class="btn-sv" onclick="saveRow(this,'recovery_stylist_handles','sh')">Save</button>
      <button class="btn-dl" onclick="deleteRow(this,'recovery_stylist_handles')">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[type=text]').focus();
}

function addCfRow() {
  const tbody = document.getElementById('cf-body');
  const tr = document.createElement('tr');
  tr.dataset.id = '0';
  tr.innerHTML = `
    <td class="left"><input class="ei left" type="text" placeholder="Service name" onfocus="this.select()"></td>
    <td><input class="ei num" type="number" step="0.01" placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
    <td><input class="ei num" type="number" step="0.01" placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
    <td><input class="ei num" type="number" step="0.01" placeholder="0" onfocus="this.select()" oninput="cfRowCalc(this)"></td>
    <td><input class="ei num" type="text" value="0.00" readonly style="font-weight:700;background:#f0fdf4;color:#166534"></td>
    <td>
      <button class="btn-sv" onclick="saveRow(this,'recovery_commission_fees','cf')">Save</button>
      <button class="btn-dl" onclick="deleteRow(this,'recovery_commission_fees')">✕</button>
      <div class="row-st"></div>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[type=text]').focus();
}

// Live-recompute Total Mktg Exp. (Fix CF + At Cost) as the row is edited
function cfRowCalc(inp) {
  const tr = inp.closest('tr');
  const nums = tr.querySelectorAll('input[type=number]');
  const fixCf  = parseFloat(nums[1]?.value) || 0;
  const atCost = parseFloat(nums[2]?.value) || 0;
  const totalCell = tr.querySelector('input.ei.num[type=text]');
  if (totalCell) totalCell.value = (fixCf + atCost).toFixed(2);
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ── Service search — filters Services Price List & MKTG tables live ──
function filterServices(q) {
  q = (q || '').trim().toLowerCase();
  const clearBtn = document.getElementById('serviceSearchClear');
  const countEl  = document.getElementById('serviceSearchCount');
  clearBtn.style.display = q ? 'inline-flex' : 'none';

  let matches = 0;
  ['pl-body', 'cf-body'].forEach(bodyId => {
    const tbody = document.getElementById(bodyId);
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(tr => {
      const nameInp = tr.querySelector('input.ei.left');
      const name = (nameInp?.value || '').toLowerCase();
      const match = !q || name.includes(q);
      tr.classList.toggle('csgt-row-hidden', !match);
      if (match && q) matches++;
    });
  });
  countEl.textContent = q ? `${matches} match${matches === 1 ? '' : 'es'}` : '';
}

function clearServiceSearch() {
  const inp = document.getElementById('serviceSearch');
  inp.value = '';
  filterServices('');
  inp.focus();
}
</script>
</body>
</html>