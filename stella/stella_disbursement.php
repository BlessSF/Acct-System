<?php
// ============================================================
//  stella_disbursement.php — Stella Disbursement Journal
//  Auto-fills vendor details from vendor_masterlist by TIN or name
// ============================================================
require_once '../config.php';
require_once '../auth.php';
require_once __DIR__ . '/../lib/vat_mode.php';
// Expenses auto-posting library. If it is missing / broken the Disbursement still works (entries just
// are not posted to Expenses) instead of the whole page failing with HTTP 500.
$expSyncLib = __DIR__ . '/../lib/expense_sync.php';
$expSyncReady = false;
if (is_file($expSyncLib)) { require_once $expSyncLib; $expSyncReady = true; }
requireLogin();

// Only Stella branch + management can access
if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Auto-create table ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_disbursement` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `entry_date`    date             DEFAULT NULL,
    `tin`           varchar(100)     DEFAULT '',
    `company_name`  varchar(255)     DEFAULT '',
    `vat_status`    varchar(10)      DEFAULT 'VAT',
    `address`       varchar(255)     DEFAULT '',
    `invoice_no`    varchar(100)     DEFAULT '',
    `account_title` varchar(255)     DEFAULT '',
    `gross`         decimal(15,2)    DEFAULT 0.00,
    `input_tax`     decimal(15,2)    DEFAULT 0.00,
    `net_of_vat`    decimal(15,2)    DEFAULT 0.00,
    `particular`    varchar(255)     DEFAULT '',
    `saved_by`      varchar(100)     DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date`         (`entry_date`),
    KEY `idx_tin`          (`tin`),
    KEY `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Input Tax / Net of VAT keep 6 decimals (Excel keeps the hidden fractions behind each cell, so the totals only match to the cent this way)
try {
    $sc = $pdo->query("SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stella_disbursement' AND COLUMN_NAME='input_tax'")->fetchColumn();
    if ($sc !== false && (int)$sc < 6) $pdo->exec("ALTER TABLE stella_disbursement MODIFY `input_tax` decimal(18,6) DEFAULT 0.000000, MODIFY `net_of_vat` decimal(18,6) DEFAULT 0.000000");
} catch (Throwable $e) {}

// sort_no = the row's position in the imported Excel sheet, so the page shows the same order as the sheet
try {
    if (!$pdo->query("SHOW COLUMNS FROM stella_disbursement LIKE 'sort_no'")->fetch()) $pdo->exec("ALTER TABLE stella_disbursement ADD COLUMN `sort_no` int(11) NULL DEFAULT NULL");
} catch (Throwable $e) {}

$TXT_COLS = ['entry_date','tin','company_name','vat_status','address','invoice_no','account_title','particular'];
$NUM_COLS = ['gross','input_tax','net_of_vat'];

// ── Account Titles (managed via stella_acc_title.php) ──────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_acc_titles` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `title`       varchar(255) NOT NULL,
    `section`     enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
    `sort_order`  int(6) NOT NULL DEFAULT 0,
    `saved_by`    varchar(100) DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── One-time seed from the list that used to be hardcoded here /
//    stella_profit_loss.php / stella_sum.php / stella_tb.php, so
//    existing entries and report groupings keep working ──
if ((int)$pdo->query("SELECT COUNT(*) FROM `stella_acc_titles`")->fetchColumn() === 0) {
    // title => section ('assets' | 'expenses' | 'other')
    $DEFAULT_TITLES = [
        "Office Equipment"             => 'assets',
        "Other Equipment"              => 'assets',
        "Service Vehicle"              => 'assets',
        "Leasehold Improvement"        => 'assets',
        "Furniture and Fixtures"       => 'assets',
        "Investments"                  => 'assets',
        "Accounts Payable"             => 'other',
        "EWT Payable"                  => 'other',
        "Purchases - Non-Vat"          => 'expenses',
        "Purchases - Vatable"          => 'expenses',
        "Kitchen Supplies"             => 'expenses',
        "Solane"                       => 'expenses',
        "Miscellaneous"                => 'expenses',
        "Rent"                         => 'expenses',
        "CUSA"                         => 'expenses',
        "Office Supplies"              => 'expenses',
        "Pest Control"                 => 'expenses',
        "Advertisement"                => 'expenses',
        "Bio Augmentation"             => 'expenses',
        "Professional Fee"             => 'expenses',
        "Bookkeeping Fee"              => 'expenses',
        "Fare & Transportation"        => 'expenses',
        "Fuel & Oil"                   => 'expenses',
        "Repairs and Maintenance"      => 'expenses',
        "Telephone, Light & Water"     => 'expenses',
        "Delivery Expense"             => 'expenses',
        "Salaries and Wages"           => 'expenses',
        "Representation Expense"       => 'expenses',
        "Meals"                        => 'expenses',
        "Taxes and Licenses"           => 'expenses',
        "SSS, PHIC, HDMF Contribution" => 'expenses',
        "Commission Expense"           => 'expenses',
        "M'Nikki"                      => 'expenses',
        "c/o Nikki"                    => 'expenses',
        "Others"                       => 'expenses',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `stella_acc_titles` (`title`,`section`,`sort_order`,`saved_by`) VALUES (?,?,?,?)");
    $i = 0;
    foreach ($DEFAULT_TITLES as $t => $sec) { $ins->execute([$t, $sec, $i, 'system-seed']); $i++; }
}

// ── VAT treatment per Account Title (set on the Account Titles page) ──
vatModeEnsureColumn($pdo, 'stella_acc_titles');

// ── Expenses auto-posting (Stella branch): every entry is posted to stella_expenses
//    through the "Expenses column" set on its Account Title (see lib/expense_sync.php) ──
if ($expSyncReady) {
    try {
        expSyncCreateExpenses($pdo, 'stella_expenses');
        expSyncEnsure($pdo, 'stella_acc_titles', 'stella_expenses', EXP_SYNC_DEFAULTS);
    } catch (Throwable $e) { $expSyncReady = false; }
}
// Same as hPostToExpenses but without the per-row COGS sync (used by bulk import, which syncs once at the end)
function hPostToExpensesOnly(PDO $pdo, int $id): string {
    try { return expSyncOne($pdo, 'stella_disbursement', 'stella_expenses', 'stella_acc_titles', $id); }
    catch (Throwable $e) { return 'error'; }
}
function hPostToExpenses(PDO $pdo, int $id): string {
    try {
        $r = expSyncOne($pdo, 'stella_disbursement', 'stella_expenses', 'stella_acc_titles', $id);
        hSyncCogsPurchases($pdo, $id);
        return $r;
    }
    catch (Throwable $e) { return 'error'; }
}
// (Stella COGS reads Purchases straight from Stella Expenses, so nothing to sync here.)
function hSyncCogsPurchases(PDO $pdo, ?int $id = null): void {}

// ── Self-repair: rows saved with id = 0 (table without AUTO_INCREMENT) can't be told apart,
//    which made Delete fail ("Missing ID") or hit several rows. Give every row its own id. ──
try {
    $__idCol = $pdo->query("SHOW COLUMNS FROM stella_disbursement LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    $__bad   = (int)$pdo->query("SELECT COUNT(*) FROM stella_disbursement WHERE id=0")->fetchColumn();
    $__noAI  = !$__idCol || stripos((string)($__idCol['Extra'] ?? ''), 'auto_increment') === false;
    if ($__bad > 0 || $__noAI) {
        if ($__bad > 0) {
            $pdo->exec("SET @n := (SELECT COALESCE(MAX(id),0) FROM stella_disbursement)");
            $pdo->exec("UPDATE stella_disbursement SET id = (@n := @n + 1) WHERE id = 0");
            try { $pdo->exec("DELETE FROM stella_expenses WHERE disbursement_id=0"); } catch (Throwable $e) {}
        }
        if ($__noAI) {
            $hasPk = (bool)$pdo->query("SHOW KEYS FROM stella_disbursement WHERE Key_name='PRIMARY'")->fetch();
            if (!$hasPk) { try { $pdo->exec("ALTER TABLE stella_disbursement ADD PRIMARY KEY (id)"); } catch (Throwable $e) {} }
            try { $pdo->exec("ALTER TABLE stella_disbursement MODIFY id int(11) NOT NULL AUTO_INCREMENT"); } catch (Throwable $e) {}
        }
        if ($__bad > 0 && $expSyncReady) {
            foreach ($pdo->query("SELECT id FROM stella_disbursement")->fetchAll(PDO::FETCH_COLUMN) as $__i) hPostToExpenses($pdo, (int)$__i);
        }
    }
} catch (Throwable $e) {}

// ── AJAX: Vendor lookup ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendor_lookup'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (!$q) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT tin, company_name, vat_status, address
                            FROM vendor_masterlist_unified
                            WHERE company_name LIKE ? OR tin LIKE ?
                            ORDER BY company_name ASC LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Input Tax / Net of VAT taken straight from the pasted/imported Excel row (so the system matches the sheet).
// Returns [tax, net] only when the values are present and add up to Gross; otherwise null (→ computed from the title's VAT setting).
function hFileTaxNet($tax, $net, float $gross): ?array {
    $clean = function ($v) { $v = trim((string)$v); if ($v === '' || stripos($v, '#') === 0) return null;
        if ($v === '-') return 0.0; return (float)preg_replace('/[^0-9.\-]/', '', $v); };
    $t = $clean($tax); $n = $clean($net);
    if ($t === null && $n === null) return null;
    if ($t === null) $t = round($gross - $n, 6);
    if ($n === null) $n = round($gross - $t, 6);
    if (abs($gross - $t - $n) > 0.02 || $t < 0 || $n == 0.0) return null;
    return [round($t, 6), round($n, 6)];
}

// ── AJAX: Add ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $data = [];
    foreach ($TXT_COLS as $f) $data[$f] = trim($_POST[$f] ?? '');
    foreach ($NUM_COLS as $f) $data[$f] = (float)str_replace(',', '', $_POST[$f] ?? 0);
    // Input Tax / Net of VAT come from the account title's VAT setting + the supplier's VAT status
    [$data['input_tax'], $data['net_of_vat']] = (isset($_POST['ov_tax']) ? hFileTaxNet($_POST['ov_tax'], $_POST['ov_net'] ?? '', $data['gross']) : null)
        ?? vatModeCompute(vatModeFor($pdo, 'stella_acc_titles', $data['account_title']), $data['vat_status'], $data['gross']);
    $data['saved_by'] = $user['name'];
    $fields = array_keys($data);
    try {
        $sql = "INSERT INTO stella_disbursement (" . implode(',', array_map(fn($f)=>"`$f`", $fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        $newId = (int)$pdo->lastInsertId();
        $posted = hPostToExpenses($pdo, $newId);
        echo json_encode(['ok' => true, 'id' => $newId, 'posted' => $posted,
            'input_tax' => number_format($data['input_tax'], 2, '.', ''), 'net_of_vat' => number_format($data['net_of_vat'], 2, '.', ''),
            'input_tax_raw' => $data['input_tax'], 'net_of_vat_raw' => $data['net_of_vat']]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Import (Excel paste of many rows) ───────────────
// Columns pasted from Excel/Sheets: DATE, TIN, NAME OF SUPPLIER, TYPE, ADDRESS,
// INVOICE #, ACCOUNT TITLE, GROSS, INPUT TAX, NET OF VAT, PARTICULAR.
// Input Tax / Net of VAT are always recomputed from the Account Title's VAT setting.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_import'])) {
    header('Content-Type: application/json');
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows) || !$rows) { echo json_encode(['ok' => false, 'msg' => 'Nothing to import']); exit; }
    if (count($rows) > 1000) { echo json_encode(['ok' => false, 'msg' => 'Too many rows at once (max 1000)']); exit; }

    $normT = function ($s) { $s = preg_replace('/\s+/', ' ', trim((string)$s)); return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };
    $titleMap = [];
    foreach ($pdo->query("SELECT title FROM stella_acc_titles")->fetchAll(PDO::FETCH_COLUMN) as $t) $titleMap[$normT($t)] = $t;

    $parseDate = function ($v) {
        $v = trim((string)$v);
        if ($v === '') return null;
        if (preg_match('/^\d{5}(\.\d+)?$/', $v)) return date('Y-m-d', (int)(((float)$v - 25569) * 86400));   // Excel serial
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $v, $m)) {
            [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            if ($y < 100) $y += 2000;
            $mo = $a; $d = $b;
            if ($a > 12 && $b <= 12) { $mo = $b; $d = $a; }
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }
        foreach (['Y-m-d', 'd-M-y', 'd-M-Y', 'j-M-y', 'j-M-Y', 'd M Y', 'M j, Y', 'F j, Y'] as $f) {
            $dt = DateTime::createFromFormat('!' . $f, $v);
            if ($dt && $dt->format($f) !== false) return $dt->format('Y-m-d');
        }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : null;
    };
    $parseNum = function ($v) {
        $v = trim((string)$v);
        if ($v === '' || $v === '-' || stripos($v, '#') === 0) return 0.0;
        $neg = (strpos($v, '(') !== false && strpos($v, ')') !== false);
        $n = (float)preg_replace('/[^0-9.\-]/', '', $v);
        return $neg ? -abs($n) : $n;
    };

    // Duplicate check: identical rows already in the system are skipped, but an identical row that
    // legitimately appears several times in the file is kept (only the surplus over what exists is added).
    $cntSt = $pdo->prepare("SELECT COUNT(*) FROM stella_disbursement WHERE entry_date=? AND tin=? AND company_name=? AND invoice_no=? AND account_title=? AND particular=? AND ABS(gross-?)<0.005");
    $ins = $pdo->prepare("INSERT INTO stella_disbursement (`entry_date`,`tin`,`company_name`,`vat_status`,`address`,`invoice_no`,`account_title`,`gross`,`input_tax`,`net_of_vat`,`particular`,`saved_by`,`sort_no`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $added = 0; $dups = 0; $skipped = 0; $undated = 0; $unposted = 0; $grossSum = 0.0; $unknownTitles = [];
    $existing = []; $seenInFile = [];

    // Rows with no date (e.g. month-end items typed without a date) get the last day of the month
    // that most of the dated rows belong to — otherwise they would never show up under that month.
    $parsed = [];
    $monthVotes = [];
    foreach ($rows as $i => $r) {
        $r = array_pad(array_values((array)$r), 11, '');
        $parsed[$i] = $r;
        $d = $parseDate($r[0]);
        if ($d && trim((string)$r[6]) !== '' && $parseNum($r[7]) != 0.0) { $ym = substr($d, 0, 7); $monthVotes[$ym] = ($monthVotes[$ym] ?? 0) + 1; }
    }
    $fallbackDate = null;
    if ($monthVotes) { arsort($monthVotes); $ym = array_key_first($monthVotes); $fallbackDate = date('Y-m-t', strtotime($ym . '-01')); }
    elseif (!empty($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month'])) { $fallbackDate = date('Y-m-t', strtotime($_POST['month'] . '-01')); }

    foreach ($parsed as $pi => $r) {
        $seq = (int)$pi + 1;                      // row position in the Excel sheet
        $date  = $parseDate($r[0]);
        $tin   = trim((string)$r[1]);
        $name  = trim((string)$r[2]);
        $type  = strtoupper(trim((string)$r[3]));
        $addr  = trim((string)$r[4]);
        $inv   = trim((string)$r[5]);
        $title = trim((string)$r[6]);
        $gross = $parseNum($r[7]);
        $part  = trim((string)$r[10]);
        if ($title === '' || $gross == 0.0) { $skipped++; continue; }      // blank / unfinished template rows
        if (!$date) {
            if (!$fallbackDate) { $skipped++; continue; }
            $date = $fallbackDate; $undated++;
        }
        $k = $normT($title);
        if (isset($titleMap[$k])) $title = $titleMap[$k]; else $unknownTitles[$title] = true;
        // TYPE column often shows #N/A for non-VAT suppliers: treat anything but VAT/NV by the TIN
        if ($type !== 'VAT' && $type !== 'NV') $type = ($tin !== '') ? 'VAT' : 'NV';
        $key = implode('|', [$date, $tin, $name, $inv, $title, $part, number_format($gross, 2, '.', '')]);
        if (!isset($existing[$key])) { $cntSt->execute([$date, $tin, $name, $inv, $title, $part, $gross]); $existing[$key] = (int)$cntSt->fetchColumn(); }
        $seenInFile[$key] = ($seenInFile[$key] ?? 0) + 1;
        $fromFile = hFileTaxNet($r[8], $r[9], $gross);
        if ($seenInFile[$key] <= $existing[$key]) {
            $dups++;
            // already in the system: keep it, but give it the Excel's position and exact (unrounded) tax / net
            try {
                $sel = $pdo->prepare("SELECT id, input_tax FROM stella_disbursement WHERE entry_date=? AND tin=? AND company_name=? AND invoice_no=? AND account_title=? AND particular=? AND ABS(gross-?)<0.005 ORDER BY id ASC LIMIT 1 OFFSET " . ($seenInFile[$key] - 1));
                $sel->execute([$date, $tin, $name, $inv, $title, $part, $gross]);
                if ($ex = $sel->fetch(PDO::FETCH_ASSOC)) {
                    if ($fromFile && abs((float)$ex['input_tax'] - $fromFile[0]) < 0.011)
                        $pdo->prepare("UPDATE stella_disbursement SET sort_no=?, input_tax=?, net_of_vat=? WHERE id=?")->execute([$seq, $fromFile[0], $fromFile[1], $ex['id']]);
                    else
                        $pdo->prepare("UPDATE stella_disbursement SET sort_no=? WHERE id=?")->execute([$seq, $ex['id']]);
                }
            } catch (Throwable $e) {}
            continue;
        }
        [$tax, $net] = $fromFile ?? vatModeCompute(vatModeFor($pdo, 'stella_acc_titles', $title), $type, $gross);
        try {
            $ins->execute([$date, $tin, $name, $type, $addr, $inv, $title, $gross, $tax, $net, $part, $user['name'], $seq]);
            $nid = (int)$pdo->lastInsertId();
            if (hPostToExpensesOnly($pdo, $nid) === 'unmapped') $unposted++;
            $added++; $grossSum += $gross;
        } catch (Throwable $e) { $skipped++; }
    }
    hSyncCogsPurchases($pdo, null);
    echo json_encode(['ok' => true, 'added' => $added, 'duplicates' => $dups, 'skipped' => $skipped, 'undated' => $undated, 'fallback_date' => $fallbackDate,
        'unposted' => $unposted, 'unknown_titles' => array_keys($unknownTitles), 'gross' => round($grossSum, 2)]);
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    $sets = []; $vals = [];
    foreach ($TXT_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = trim($_POST[$f] ?? ''); }
    foreach ($NUM_COLS as $f) { $sets[] = "`$f`=?"; $vals[] = (float)str_replace(',', '', $_POST[$f] ?? 0); }
    // Input Tax / Net of VAT come from the account title's VAT setting + the supplier's VAT status
    $grossU = (float)str_replace(',', '', $_POST['gross'] ?? 0);
    [$taxVal, $netVal] = (isset($_POST['ov_tax']) ? hFileTaxNet($_POST['ov_tax'], $_POST['ov_net'] ?? '', $grossU) : null)
        ?? vatModeCompute(vatModeFor($pdo, 'stella_acc_titles', trim($_POST['account_title'] ?? '')), trim($_POST['vat_status'] ?? ''), $grossU);
    foreach ($sets as $k => $c) {
        if ($c === '`input_tax`=?')  $vals[$k] = $taxVal;
        if ($c === '`net_of_vat`=?') $vals[$k] = $netVal;
    }
    $sets[] = '`saved_by`=?'; $vals[] = $user['name'];
    $vals[] = $id;
    try {
        $pdo->prepare("UPDATE stella_disbursement SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        $posted = hPostToExpenses($pdo, $id);
        echo json_encode(['ok' => true, 'posted' => $posted, 'input_tax' => number_format($taxVal, 2, '.', ''), 'net_of_vat' => number_format($netVal, 2, '.', ''),
            'input_tax_raw' => $taxVal, 'net_of_vat_raw' => $netVal]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Missing ID']); exit; }
    try {
        $pdo->prepare("DELETE FROM stella_disbursement WHERE id=? LIMIT 1")->execute([$id]);
        try { expSyncDelete($pdo, 'stella_expenses', $id); hSyncCogsPurchases($pdo, null); } catch (Throwable $ignored) {}
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Clear all entries of one month (also removes what they posted to Expenses) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_clear_month'])) {
    header('Content-Type: application/json');
    $m = $_POST['month'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $m) || strtoupper(trim($_POST['confirm'] ?? '')) !== 'CLEAR') { echo json_encode(['ok' => false, 'msg' => 'Not confirmed']); exit; }
    try {
        $a = $m . '-01'; $b = date('Y-m-d', strtotime($a . ' +1 month'));
        $q = $pdo->prepare("SELECT id FROM stella_disbursement WHERE entry_date >= ? AND entry_date < ?");
        $q->execute([$a, $b]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $i) { try { expSyncDelete($pdo, 'stella_expenses', (int)$i); } catch (Throwable $ignored) {} }
        $pdo->prepare("DELETE FROM stella_disbursement WHERE entry_date >= ? AND entry_date < ?")->execute([$a, $b]);
        try { hSyncCogsPurchases($pdo, null); } catch (Throwable $ignored) {}
        echo json_encode(['ok' => true, 'deleted' => count($ids)]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── XLSX Export ─────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $fMonth = $_GET['month'] ?? '';
    // Compare real dates (not formatted text) so MariaDB 11 never hits "Illegal mix of collations".
    $mStart = preg_match('/^\d{4}-\d{2}$/', $fMonth) ? $fMonth . '-01' : date('Y-m-01');
    $mEnd   = date('Y-m-d', strtotime($mStart . ' +1 month'));
    $where = $fMonth ? "WHERE entry_date >= ? AND entry_date < ?" : "";
    $params = $fMonth ? [$mStart, $mEnd] : [];
    $stmt = $pdo->prepare("SELECT * FROM stella_disbursement $where ORDER BY (sort_no IS NULL) ASC, sort_no ASC, entry_date ASC, id ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $stmt2 = $pdo->prepare("SELECT SUM(gross) g, SUM(input_tax) t, SUM(net_of_vat) n FROM stella_disbursement $where");
    $stmt2->execute($params);
    $tot = $stmt2->fetch();

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('MERITONI CORP');
    $xlsx->subtitle('Stella Disbursement Journal');
    $xlsx->subtitle(($fMonth ? date('F Y', strtotime($fMonth.'-01')) . ' · ' : '') . 'Generated ' . date('F d, Y H:i:s'));
    $xlsx->blank();
    $xlsx->header(['DATE','TIN','NAME OF SUPPLIER','TYPE','ADDRESS','INVOICE #','ACCOUNT TITLE','GROSS','INPUT TAX','NET OF VAT','PARTICULAR']);
    foreach ($rows as $r) {
        $xlsx->row([
            $r['entry_date'], $r['tin'], $r['company_name'], $r['vat_status'],
            $r['address'], $r['invoice_no'], $r['account_title'],
            $r['gross'], $r['input_tax'], $r['net_of_vat'], $r['particular']
        ]);
    }
    $xlsx->totalRow(['','','','','','','TOTAL', number_format((float)$tot['g'],2,'.',''), number_format((float)$tot['t'],2,'.',''), number_format((float)$tot['n'],2,'.',''), '']);
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([12,16,26,8,22,14,20,14,12,14,20]);
    $xlsx->output('H_Disbursement_' . date('Y-m-d') . '.xlsx');
    exit;
}


// ── Load rows ─────────────────────────────────────────────
$fMonth  = $_GET['month'] ?? date('Y-m');
$fSearch = trim($_GET['q'] ?? '');

// Compare real dates (not formatted text) so MariaDB 11 never hits "Illegal mix of collations".
$mStart = preg_match('/^\d{4}-\d{2}$/', $fMonth) ? $fMonth . '-01' : date('Y-m-01');
$mEnd   = date('Y-m-d', strtotime($mStart . ' +1 month'));
$where  = ["entry_date >= ? AND entry_date < ?"]; $params = [$mStart, $mEnd];
if ($fSearch) {
    $where[]  = "(company_name LIKE ? OR tin LIKE ? OR invoice_no LIKE ? OR particular LIKE ?)";
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
}
$whereSQL = "WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM stella_disbursement $whereSQL ORDER BY (sort_no IS NULL) ASC, sort_no ASC, entry_date ASC, id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Totals
$stmtT = $pdo->prepare("SELECT SUM(gross) g, SUM(input_tax) t, SUM(net_of_vat) n FROM stella_disbursement $whereSQL");
$stmtT->execute($params);
$totals = $stmtT->fetch();

$acctTitles = $pdo->query("SELECT title FROM `stella_acc_titles` ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_COLUMN);
$acctVatModes = vatModeMap($pdo, 'stella_acc_titles');

$pageTitle  = 'Stella Disbursement';
$activePage = 'stella_disbursement';
include '../layout.php';
?>

<style>
/* ── Outer wrapper ── */
.dsb-outer {
  width: 100%; overflow: auto; max-height: calc(100vh - 150px);   /* scrolls inside, so header + TOTAL stay visible */
  border: 2px solid #217346;
  border-radius: var(--radius);
  background: var(--surface);
  scrollbar-width: thin; scrollbar-color: #c1c7d0 #f1f3f5;
  box-shadow: 0 2px 8px rgba(0,0,0,.10);
}
.dsb-outer::-webkit-scrollbar { height: 8px; }
.dsb-outer::-webkit-scrollbar-track { background: #f1f3f5; }
.dsb-outer::-webkit-scrollbar-thumb { background: #c1c7d0; border-radius: 4px; }

/* ── Table ── */
.dsb-table { border-collapse: collapse; width: 100%; min-width: 1300px; font-size: .74rem; }

/* Header row — green Excel style */
.dsb-table thead tr.head-company td {
  background: #ffffff; color: #1a1a1a;
  font-family: var(--font-m); font-size: .68rem; font-weight: 700;
  padding: 5px 10px; border: 1px solid #c6d9b0; text-align: left;
}
.dsb-table thead tr.head-month td {
  background: #ffffff; color: #1a1a1a;
  font-family: var(--font-m); font-size: .68rem; font-weight: 700;
  padding: 4px 10px; border: 1px solid #c6d9b0;
}
.dsb-table thead tr.head-cols th {
  background: #4CAF50; color: #fff;
  font-family: var(--font-m); font-size: .58rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em;
  padding: 9px 8px; border: 1px solid #388E3C;
  white-space: nowrap; text-align: center;
  position: sticky; top: 0; z-index: 20;
}

/* Column widths */
.th-date { width: 90px;  min-width: 90px; }
.th-tin  { width: 140px; min-width: 140px; }
.th-name { width: 200px; min-width: 200px; }
.th-type { width: 60px;  min-width: 60px; }
.th-addr { width: 180px; min-width: 180px; }
.th-inv  { width: 110px; min-width: 110px; }
.th-acct { width: 160px; min-width: 160px; }
.th-gross{ width: 110px; min-width: 110px; }
.th-itax { width: 100px; min-width: 100px; }
.th-nvat { width: 110px; min-width: 110px; }
.th-part { width: 160px; min-width: 160px; }
.th-act  { width: 80px;  min-width: 80px; position: sticky; right: 0; z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,.08); }
.td-act-sticky { position: sticky; right: 0; z-index: 4; background: #fff; box-shadow: -2px 0 4px rgba(0,0,0,.08); }

/* Row styling */
.dsb-table tbody tr { border-bottom: 1px solid #d0d8e0; transition: background .1s; }
.dsb-table tbody tr td { background: #fff; }
.dsb-table tbody tr:nth-child(even) td { background: #EBF5E0; }
.dsb-table tbody tr:nth-child(even) td.td-act-sticky { background: #EBF5E0; }
.dsb-table tbody tr:hover td { background: #d6efc0 !important; }
.dsb-table td { border: 1px solid #c8d4df; padding: 0; vertical-align: middle; }

/* Inline inputs */
.di {
  width: 100%; padding: 6px 7px;
  background: transparent; border: none; outline: none;
  color: #1a1d23; font-family: var(--font-h); font-size: .74rem;
  display: block; box-sizing: border-box;
}
.di:focus { background: rgba(15,123,92,.06); outline: 1px solid #4CAF50; }
.di.mono  { font-family: var(--font-m); font-size: .70rem; }
.di.num   { font-family: var(--font-m); font-size: .70rem; text-align: right; }
.di.center{ text-align: center; }

.td-no {
  text-align: center; font-family: var(--font-m); font-size: .64rem;
  color: #555; padding: 0 5px; background: #f5f8f2 !important; font-weight: 600;
}

/* VAT select */
.di-sel {
  width: 100%; padding: 5px 4px;
  background: transparent; border: none; outline: none;
  font-family: var(--font-m); font-size: .70rem; font-weight: 700;
  text-align: center; cursor: pointer;
  appearance: none; -webkit-appearance: none;
}
.di-sel:focus { background: rgba(15,123,92,.06); }

/* Account title dropdown */
.acct-sel {
  width: 100%; padding: 6px 4px;
  font-size: .72rem; font-weight: 500; color: #1a1d23;
  text-align: left;
}

/* ghost zero */
.di::placeholder { color: #a8b0ba; opacity: 1; font-weight: 400; }

/* Totals row */
.dsb-table tfoot td {
  background: #C6EFCE; color: #1a1a1a;
  font-family: var(--font-m); font-size: .65rem; font-weight: 800;
  padding: 8px 8px; border: 1px solid #388E3C;
  position: sticky; bottom: 0; z-index: 21; box-shadow: 0 -2px 4px rgba(0,0,0,.12);
}
.dsb-table tfoot td.num { text-align: right; }

/* Action buttons */
.bsv {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; border-radius: 4px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%; margin-bottom: 3px;
}
.bsv:hover { background: #dcfce7; }
.bsv.saving { opacity:.5; pointer-events:none; }
.bsv.ok  { background:#dcfce7; color:#15803d; }
.bsv.err { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.rs { display:block; font-family:var(--font-m); font-size:.56rem; font-weight:700; margin-bottom:3px; white-space:nowrap; }
.rs-ok    { color:#15803d; }
.rs-dirty { color:#b45309; }
.rs-new   { color:#6b7280; }
.rs-err   { color:#be123c; }
.bdel {
  padding: 3px 7px; font-size: .58rem;
  font-family: var(--font-m); font-weight: 700;
  background: #fff1f2; color: #be123c;
  border: 1px solid #fecdd3; border-radius: 4px;
  cursor: pointer; white-space: nowrap;
  transition: all .13s; display: block; width: 100%;
}
.bdel:hover { background: #ffe4e6; }

/* Controls */
.dsb-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.btn-add-row {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 14px; background: #4CAF50; color: #fff;
  border: none; border-radius: 8px; font-size: .78rem; font-weight: 600;
  cursor: pointer; font-family: var(--font-h);
  transition: background .15s, transform .1s;
}
.btn-add-row:hover { background: #388E3C; transform: translateY(-1px); }

/* Autocomplete dropdown */
.ac-wrap { position: relative; width: 100%; }
.ac-list {
  display: none; position: fixed; z-index: 99999;
  background: #fff; border: 1px solid #c8d4df; border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  max-height: 220px; overflow-y: auto; min-width: 280px;
}
.ac-list.open { display: block; }
.ac-item {
  padding: 8px 12px; cursor: pointer; font-size: .74rem;
  font-family: var(--font-h); border-bottom: 1px solid #f0f2f5;
  transition: background .1s;
}
.ac-item:hover { background: #f0fdf4; }
.ac-item .ac-name { font-weight: 600; color: #1a1d23; }
.ac-item .ac-tin  { font-family: var(--font-m); font-size: .64rem; color: #6b7280; margin-top: 1px; }
.ac-item .ac-addr { font-family: var(--font-m); font-size: .62rem; color: #9ca3af; margin-top: 1px; font-style: italic; }

.scroll-hint {
  font-family: var(--font-m); font-size: .60rem; color: #374151;
  text-align: center; padding: 4px 12px; border-bottom: 1px solid #c6d9b0;
  background: #f0fff0;
}
.toast { position: fixed; top: 68px; right: 22px; z-index: 9999; max-width: 320px; animation: fadeSlideDown .3s ease; }
.empty-state {
  text-align: center; padding: 50px 20px;
  font-family: var(--font-m); font-size: .78rem; color: var(--subtext);
}
</style>

<!-- Header -->
<div class="section-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div class="section-title">Stella <span>Disbursement</span></div>
    <div class="section-subtitle">MERITONI CORP · Auto-fills vendor details from Vendor Masterlist</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
  <a href="stella_acc_title.php"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#6D4C41;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(109,76,65,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#4E342E';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#6D4C41';this.style.transform=''">
    🏷 Account Titles
  </a>
  <a href="stella_profit_loss.php"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#1565C0;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(21,101,192,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#0D47A1';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#1565C0';this.style.transform=''">
    📊 Profit &amp; Loss
  </a>
  <a href="stella_sum.php?year=<?= date('Y') ?>"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#F9A825;color:#3E2723;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(249,168,37,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#F57F17';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#F9A825';this.style.transform=''">
    📋 Sum
  </a>
  <a href="stella_tb.php?year=<?= date('Y') ?>"
     style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
            background:#37474F;color:#fff;border-radius:9px;text-decoration:none;
            font-family:var(--font-h);font-size:.82rem;font-weight:700;
            box-shadow:0 2px 8px rgba(55,71,79,.3);transition:background .15s,transform .15s;
            white-space:nowrap"
     onmouseover="this.style.background='#263238';this.style.transform='translateY(-1px)'"
     onmouseout="this.style.background='#37474F';this.style.transform=''">
    🗂 TB
  </a>
  </div>
</div>

<!-- Controls -->
<form method="GET" class="dsb-controls" id="filterForm">
  <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($fMonth) ?>"
         style="max-width:160px" onchange="this.form.submit()">
  <input type="text" name="q" class="form-control" placeholder="Search supplier, TIN, invoice…"
         value="<?= htmlspecialchars($fSearch) ?>" style="max-width:240px">
  <button type="submit" class="btn btn-ghost btn-sm">Search</button>
  <?php if ($fSearch): ?>
  <a href="stella_disbursement.php?month=<?= urlencode($fMonth) ?>" class="btn btn-ghost btn-sm">Reset</a>
  <?php endif; ?>
  <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('impFile').click()"
     style="color:#6A1B9A;border-color:rgba(106,27,154,.3);background:rgba(106,27,154,.06);font-weight:600" title="Import an Excel file in the same layout (DATE, TIN, NAME OF SUPPLIER, TYPE, ADDRESS, INVOICE #, ACCOUNT TITLE, GROSS, INPUT TAX, NET OF VAT, PARTICULAR)">
    ⬆ Import Excel
  </button>
  <input type="file" id="impFile" accept=".xlsx,.xls,.csv" style="display:none" onchange="importExcelFile(this)">
  <button type="button" class="btn btn-ghost btn-sm" onclick="clearMonth()"
     style="color:#c62828;border-color:rgba(198,40,40,.35);background:rgba(198,40,40,.06);font-weight:600" title="Delete every disbursement entry of the month shown">🗑 Clear All</button>
  <a href="stella_disbursement.php?export_csv=1&month=<?= urlencode($fMonth) ?>"
     class="btn btn-ghost btn-sm" style="color:#388E3C;border-color:rgba(76,175,80,.3);background:rgba(76,175,80,.06)">
    ⬇ Export XLSX
  </a>
  <a href="stella_profit_loss.php" class="btn btn-ghost btn-sm" style="color:#1565C0;border-color:rgba(21,101,192,.3);background:rgba(21,101,192,.06);font-weight:600">
    📊 Profit &amp; Loss
  </a>
  <a href="stella_sum.php?year=<?= date('Y') ?>" class="btn btn-ghost btn-sm" style="color:#5D4037;border-color:rgba(249,168,37,.4);background:rgba(249,168,37,.08);font-weight:600">
    📋 Sum
  </a>
  <a href="stella_tb.php?year=<?= date('Y') ?>" class="btn btn-ghost btn-sm" style="color:#37474F;border-color:rgba(55,71,79,.3);background:rgba(55,71,79,.06);font-weight:600">
    🗂 TB
  </a>
  <a href="stella_acc_title.php" class="btn btn-ghost btn-sm" style="color:#6D4C41;border-color:rgba(109,76,65,.3);background:rgba(109,76,65,.06);font-weight:600">
    🏷 Account Titles
  </a>
  <span style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)">
    <strong><?= count($rows) ?></strong> entr<?= count($rows) !== 1 ? 'ies' : 'y' ?>
  </span>
</form>

<?php
// Titles used on this page that are not posted to Expenses (no Expenses column set)
$__expMap = []; try { $__expMap = expSyncTitleMap($pdo, 'stella_acc_titles'); } catch (Throwable $e) {}
$__unposted = [];
foreach ($rows as $__r) { $t = $__r['account_title'] ?? ''; if ($t !== '' && ($__expMap[$t] ?? '') === '') $__unposted[$t] = ($__unposted[$t] ?? 0) + 1; }
?>
<?php if (!$expSyncReady): ?>
<div style="margin:0 0 10px;padding:8px 12px;border-radius:8px;background:#FEF2F2;border:1px solid #FECACA;font-family:var(--font-m);font-size:.68rem;color:#991B1B">
  ⚠ Auto-posting to Expenses is OFF: <strong>lib/expense_sync.php</strong> is missing or failed to load on this server. Entries still save here, but are not posted to Expenses.
</div>
<?php endif; ?>
<div style="margin:0 0 10px;padding:8px 12px;border-radius:8px;background:#EFF6FF;border:1px solid #BFDBFE;font-family:var(--font-m);font-size:.68rem;color:#1E3A8A;line-height:1.6">
  🔗 Entries are posted <strong>automatically to Stella Expenses</strong> (gross amount, into the column set for each Account Title).
  Expenses is view-only — edit here.
  <?php if ($__unposted): ?>
  <br><span style="color:#92400E">⚠ Not posted (no Expenses column): <strong><?= htmlspecialchars(implode(', ', array_map(fn($t,$n)=>"$t ($n)", array_keys($__unposted), $__unposted))) ?></strong>
  — <a href="stella_acc_title.php" style="color:#92400E;font-weight:700">set one in Account Titles</a>.</span>
  <?php endif; ?>
</div>

<!-- Table -->
<div class="dsb-outer">
  <?php if (count($rows) > 5): ?>
  <div class="scroll-hint">← Scroll horizontally to see all columns →</div>
  <?php endif; ?>
  <table class="dsb-table" id="dsbt">
    <thead>
      <tr class="head-company">
        <td colspan="11" style="font-size:.76rem;font-weight:800;letter-spacing:.04em">MERITONI CORP</td>
      </tr>
      <tr class="head-month">
        <td colspan="11"><?= strtoupper(date('F', strtotime($fMonth . '-01'))) ?></td>
      </tr>
      <tr class="head-cols">
        <th class="th-date">DATE</th>
        <th class="th-tin">TIN</th>

        <th class="th-name">NAME OF SUPPLIER</th>
        <th class="th-type">TYPE</th>
        <th class="th-addr">ADDRESS</th>
        <th class="th-inv">INVOICE #</th>
        <th class="th-acct">ACCOUNT TITLE</th>
        <th class="th-gross">GROSS</th>
        <th class="th-itax">INPUT TAX</th>
        <th class="th-nvat">NET OF VAT</th>
        <th class="th-part">PARTICULAR</th>
        <th class="th-act">ACTION</th>
      </tr>
    </thead>
    <tbody id="dsb-tbody">
    <?php if ($rows): ?>
      <?php foreach ($rows as $r): ?>
      <tr id="row<?= $r['id'] ?>" data-id="<?= $r['id'] ?>">
        <?= renderRow($r, $acctTitles) ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr id="empty-row">
        <td colspan="12" class="empty-state">No entries for <?= htmlspecialchars(date('F Y', strtotime($fMonth . '-01'))) ?> — type in the row below and press <strong>Enter</strong></td>
      </tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" style="text-align:right;padding:8px 12px;font-weight:800;font-family:var(--font-m);font-size:.65rem;">
          TOTAL (<span id="dsb-count"><?= count($rows) ?></span> entries)
        </td>
        <td class="num" id="tfoot-gross"><?= number_format((float)$totals['g'], 2) ?></td>
        <td class="num" id="tfoot-itax"><?= number_format((float)$totals['t'], 2) ?></td>
        <td class="num" id="tfoot-nvat"><?= number_format((float)$totals['n'], 2) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:10px;font-family:var(--font-m);font-size:.68rem;color:var(--subtext2)">
  ⏎ Fill in the last (blank) row and press <kbd style="border:1px solid #cbd5e1;border-bottom-width:2px;border-radius:4px;padding:0 5px;background:#f8fafc">Enter</kbd> — the entry is saved and a new blank row opens. To change a saved entry, edit it and press <kbd style="border:1px solid #cbd5e1;border-bottom-width:2px;border-radius:4px;padding:0 5px;background:#f8fafc">Enter</kbd>.
</div>

<?php
function renderRow(array $r, array $acctOpts): string {
    // Account titles now come from the stella_acc_titles table
    // (managed via stella_acc_title.php) instead of being hardcoded here.
    $acctSel = '<select class="di-sel acct-sel" data-col="account_title" onchange="markDirty(this)">'
             . '<option value=""' . ($r['account_title'] === '' ? ' selected' : '') . '>— Select —</option>';
    foreach ($acctOpts as $opt) {
        $sel = ($r['account_title'] === $opt) ? ' selected' : '';
        $acctSel .= '<option value="' . htmlspecialchars($opt) . '"' . $sel . '>' . htmlspecialchars($opt) . '</option>';
    }
    // If a row has an account_title that's no longer in the masterlist
    // (e.g. it was deleted), keep it selectable so data isn't silently lost.
    if ($r['account_title'] !== '' && !in_array($r['account_title'], $acctOpts, true)) {
        $acctSel .= '<option value="' . htmlspecialchars($r['account_title']) . '" selected>' . htmlspecialchars($r['account_title']) . ' (removed)</option>';
    }
    $acctSel .= '</select>';
    $d   = fn($k) => htmlspecialchars($r[$k] ?? '');
    $fmt = fn($v) => number_format((float)$v, 2);
    $vat = $r['vat_status'] ?? 'VAT';

    $h  = '<td><input type="date" class="di mono" data-col="entry_date" value="' . $d('entry_date') . '" oninput="markDirty(this)"></td>';
    $h .= '<td><div class="ac-wrap">
             <input type="text" class="di mono" data-col="tin" value="' . $d('tin') . '" placeholder="—"
                    oninput="markDirty(this);triggerAC(this)" autocomplete="off">
             <div class="ac-list"></div></div></td>';
    $h .= '<td><div class="ac-wrap">
             <input type="text" class="di" data-col="company_name" value="' . $d('company_name') . '" placeholder="—"
                    oninput="markDirty(this);triggerAC(this)" autocomplete="off">
             <div class="ac-list"></div></div></td>';
    $h .= '<td style="text-align:center">
             <select class="di-sel" data-col="vat_status" onchange="markDirty(this);calcRow(this)">
               <option value="VAT"' . ($vat==='VAT' ? ' selected' : '') . '>VAT</option>
               <option value="NV"'  . ($vat==='NV'  ? ' selected' : '') . '>NV</option>
             </select></td>';
    $h .= '<td><input type="text" class="di" data-col="address"       value="' . $d('address')       . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td><input type="text" class="di mono" data-col="invoice_no"    value="' . $d('invoice_no')    . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td>' . $acctSel . '</td>';
    $h .= '<td><input type="text" class="di num" data-col="gross"      value="' . ((float)$r['gross'] == 0 ? '' : $fmt($r['gross'])) . '" placeholder="0.00" oninput="markDirty(this);calcRow(this)"></td>';
    $h .= '<td><input type="text" class="di num" data-col="input_tax" data-raw="' . (float)$r['input_tax'] . '" value="' . $fmt($r['input_tax']) . '" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>';
    $h .= '<td><input type="text" class="di num" data-col="net_of_vat" data-raw="' . (float)$r['net_of_vat'] . '" value="' . $fmt($r['net_of_vat']). '" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>';
    $h .= '<td><input type="text" class="di" data-col="particular"     value="' . $d('particular')      . '" placeholder="—" oninput="markDirty(this)"></td>';
    $h .= '<td class="td-act-sticky" style="padding:4px 5px;text-align:center">
             <span class="rs rs-ok">✓ saved</span>
             <button class="bdel" onclick="deleteRow(this)">Del</button></td>';
    return $h;
}
?>

<script>
// Account titles now come from stella_acc_titles (manage via stella_acc_title.php)
const ACCT_OPTIONS = <?= json_encode($acctTitles, JSON_UNESCAPED_UNICODE) ?>;
// VAT setting of each account title (managed on the Account Titles page):
//   'both' = follows the supplier (VAT supplier -> input tax, NV supplier -> none)
//   'vat'  = input tax only (always 12%)    'none' = Non-VAT only (never input tax)
const ACCT_VAT_MODES = <?= json_encode($acctVatModes, JSON_UNESCAPED_UNICODE) ?>;

let newRowCounter = 0;
let acTimer = null;

// ── Dirty flag ─────────────────────────────────────────────
function markDirty(el) {
    const row = el.closest('tr');
    if (!row) return;
    row.dataset.dirty = '1';
    setRowState(row, row.dataset.id ? 'dirty' : 'new');
}

// Small status text in the Action cell (there is no Save button — Enter saves)
function setRowState(row, state) {
    const rs = row.querySelector('.rs');
    if (!rs) return;
    const map = { ok:['✓ saved','rs-ok'], dirty:['edited · Enter','rs-dirty'], 'new':['Enter to save','rs-new'], saving:['saving…','rs-new'], err:['not saved','rs-err'] };
    const m = map[state] || map['new'];
    rs.textContent = m[0]; rs.className = 'rs ' + m[1];
}

// ── Auto-calculate: gross → input_tax (12%) → net_of_vat ──
function calcRow(el) {
    const row   = el.closest('tr');
    if (el.dataset && el.dataset.col && row) { delete row.dataset.ovTax; delete row.dataset.ovNet; }   // editing again → back to the automatic rule
    const gross = parseFloat(row.querySelector('[data-col="gross"]').value.replace(/,/g,'')) || 0;
    const vat   = row.querySelector('[data-col="vat_status"]').value;
    const taxEl = row.querySelector('[data-col="input_tax"]');
    const netEl = row.querySelector('[data-col="net_of_vat"]');
    const acct  = row.querySelector('[data-col="account_title"]')?.value || '';
    // The account title's VAT setting decides: 'none' never carries input tax,
    // 'vat' always does, 'both' follows the supplier's VAT / NV flag.
    const mode  = ACCT_VAT_MODES[acct] || 'both';
    const carriesTax = mode === 'vat' || (mode === 'both' && vat === 'VAT');
    if (carriesTax) {
        const tax = gross / 1.12 * 0.12;
        const net = gross - tax;
        taxEl.value = tax.toFixed(2); taxEl.dataset.raw = tax;
        netEl.value = net.toFixed(2); netEl.dataset.raw = net;
    } else {
        taxEl.value = '0.00'; taxEl.dataset.raw = 0;
        netEl.value = gross.toFixed(2); netEl.dataset.raw = gross;
    }
}

// ── Autocomplete ───────────────────────────────────────────
function triggerAC(inp) {
    clearTimeout(acTimer);
    acTimer = setTimeout(() => doAC(inp), 220);
}

async function doAC(inp) {
    const q = inp.value.trim();
    const list = inp.closest('.ac-wrap').querySelector('.ac-list');
    if (q.length < 2) { list.classList.remove('open'); return; }
    try {
        const res  = await fetch('stella_disbursement.php?vendor_lookup=1&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.length) {
            list.innerHTML = '<div class="ac-item" style="color:#6b7280;cursor:default"><div class="ac-name">No vendor found</div><div class="ac-tin">Add to Vendor Masterlist first</div></div>';
            positionAC(inp, list);
            list.classList.add('open'); return;
        }
        list.innerHTML = data.map(v => `
          <div class="ac-item" data-tin="${escHtml(v.tin)}" data-name="${escHtml(v.company_name)}"
               data-vat="${escHtml(v.vat_status)}" data-addr="${escHtml(v.address)}"
               onclick="fillVendor(this)">
            <div class="ac-name">${escHtml(v.company_name)}</div>
            <div class="ac-tin">TIN: ${escHtml(v.tin)} · ${escHtml(v.vat_status)}</div>
            ${v.address ? `<div class="ac-addr">${escHtml(v.address)}</div>` : ''}
          </div>`).join('');
        positionAC(inp, list);
        list.classList.add('open');
    } catch(e) {}
}

// Anchors the fixed-position dropdown under the input's actual screen
// position, and flips it above the input if there's not enough room
// below — needed since .ac-list is position:fixed (so it can escape the
// table's overflow-x:auto wrapper, which was clipping it).
function positionAC(inp, list) {
    const rect = inp.getBoundingClientRect();
    const listW = Math.max(rect.width, 280);
    let left = rect.left;
    if (left + listW > window.innerWidth - 8) left = window.innerWidth - listW - 8;
    list.style.width = listW + 'px';
    list.style.left  = left + 'px';
    const maxH = 220;
    const spaceBelow = window.innerHeight - rect.bottom;
    if (spaceBelow < maxH + 8 && rect.top > maxH + 8) {
        list.style.top = 'auto';
        list.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
    } else {
        list.style.bottom = 'auto';
        list.style.top = (rect.bottom + 4) + 'px';
    }
}

function fillVendor(item) {
    const row = item.closest('tr');
    // Fill TIN, name, VAT type, and address
    row.querySelector('[data-col="tin"]').value          = item.dataset.tin;
    row.querySelector('[data-col="company_name"]').value = item.dataset.name;
    // DB stores 'VAT' or 'NV' \u2014 match exactly
    const vatSel = row.querySelector('[data-col="vat_status"]');
    vatSel.value = (item.dataset.vat === 'NV') ? 'NV' : 'VAT';
    row.querySelector('[data-col="address"]').value      = item.dataset.addr;
    // Close all dropdowns
    document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
    markDirty(row.querySelector('[data-col="tin"]'));
    // Move focus to invoice_no as the next logical field
    row.querySelector('[data-col="invoice_no"]')?.focus();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close dropdowns when clicking outside
document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap')) document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
});
// Close on scroll too — it's fixed-position now, so it would otherwise
// stay floating in place instead of following the input. But skip this
// when the scroll is happening *inside* the dropdown's own list (e.g.
// scrolling through suggestions) — that used to close it instantly.
document.addEventListener('scroll', () => {
    document.querySelectorAll('.ac-list.open').forEach(l => {
        const inp = l.closest('.ac-wrap')?.querySelector('input');
        if (inp) positionAC(inp, l); else l.classList.remove('open');
    });
}, true);
window.addEventListener('resize', () => {
    document.querySelectorAll('.ac-list.open').forEach(l => {
        const inp = l.closest('.ac-wrap')?.querySelector('input');
        if (inp) positionAC(inp, l); else l.classList.remove('open');
    });
});

// ── Account title <option> builder (used when adding a new row) ──
function acctOptionsHtml() {
    let html = '<option value="">— Select —</option>';
    ACCT_OPTIONS.forEach(t => { html += `<option value="${escHtml(t)}">${escHtml(t)}</option>`; });
    return html;
}

// ── Add row ────────────────────────────────────────────────
function addRow(focus = true, dateVal = '') {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('dsb-tbody');
    const today = dateVal || new Date().toISOString().split('T')[0];
    const tr = document.createElement('tr');
    tr.id = 'new_' + (++newRowCounter); tr.dataset.id = '';
    tr.innerHTML = `
      <td><input type="date" class="di mono" data-col="entry_date" value="${today}" oninput="markDirty(this)"></td>
      <td><div class="ac-wrap"><input type="text" class="di mono" data-col="tin" value="" placeholder="—" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td><div class="ac-wrap"><input type="text" class="di" data-col="company_name" value="" placeholder="Type to search…" oninput="markDirty(this);triggerAC(this)" autocomplete="off"><div class="ac-list"></div></div></td>
      <td style="text-align:center"><select class="di-sel" data-col="vat_status" onchange="markDirty(this);calcRow(this)"><option value="VAT">VAT</option><option value="NV">NV</option></select></td>
      <td><input type="text" class="di" data-col="address"       value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><input type="text" class="di mono" data-col="invoice_no"    value="" placeholder="—" oninput="markDirty(this)"></td>
      <td><select class="di-sel acct-sel" data-col="account_title" onchange="markDirty(this)">${acctOptionsHtml()}</select></td>
      <td><input type="text" class="di num" data-col="gross"      value="" placeholder="" oninput="markDirty(this);calcRow(this)"></td>
      <td><input type="text" class="di num" data-col="input_tax"  value="" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>
      <td><input type="text" class="di num" data-col="net_of_vat" value="" readonly style="background:rgba(76,175,80,.07);cursor:default" tabindex="-1"></td>
      <td><input type="text" class="di" data-col="particular"     value="" placeholder="—" oninput="markDirty(this)"></td>
      <td class="td-act-sticky" style="padding:4px 5px;text-align:center">
        <span class="rs rs-new">Enter to save</span>
      </td>`;
    tbody.appendChild(tr);
    if (focus) tr.querySelector('[data-col="tin"]')?.focus();
    updateTotals();
}

// ── Save row (triggered by Enter — there is no Save button) ──
// Returns true when the row was saved.
async function saveRow(row) {
    if (row._saving) return false;
    const id = row.dataset.id;
    const g  = parseFloat((row.querySelector('[data-col="gross"]').value || '0').replace(/,/g, '')) || 0;
    const acct = row.querySelector('[data-col="account_title"]').value;
    if (!id) {                       // brand-new row: needs the basics before it is saved
        if (!acct) { showToast('Choose an Account Title first', 'error'); row.querySelector('[data-col="account_title"]').focus(); return false; }
        if (g <= 0) { showToast('Enter the Gross amount first', 'error'); row.querySelector('[data-col="gross"]').focus(); return false; }
    }
    row._saving = true; setRowState(row, 'saving');
    const fd = new FormData();
    fd.append(id ? 'ajax_update' : 'ajax_add', '1');
    if (id) fd.append('id', id);
    row.querySelectorAll('[data-col]').forEach(el => {
        fd.append(el.dataset.col, el.value || '');
    });
    if (row.dataset.ovTax !== undefined) { fd.append('ov_tax', row.dataset.ovTax); fd.append('ov_net', row.dataset.ovNet); }
    try {
        const res  = await fetch('stella_disbursement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            // Show Input Tax / Net of VAT exactly as the server saved them
            if (data.input_tax !== undefined) {
                const tEl = row.querySelector('[data-col="input_tax"]');
                const nEl = row.querySelector('[data-col="net_of_vat"]');
                if (tEl) { tEl.value = data.input_tax; if (data.input_tax_raw !== undefined) tEl.dataset.raw = data.input_tax_raw; }
                if (nEl) { nEl.value = data.net_of_vat; if (data.net_of_vat_raw !== undefined) nEl.dataset.raw = data.net_of_vat_raw; }
            }
            if (!id && data.id) {
                row.dataset.id = data.id; row.id = 'row' + data.id;
                const del = document.createElement('button');
                del.className = 'bdel'; del.textContent = 'Del';
                del.onclick = function(){ deleteRow(this); };
                row.querySelector('.rs').after(del);
            }
            row.dataset.dirty = '';
            setRowState(row, 'ok');
            if (data.posted === 'unmapped') showToast('✓ Entry saved — but its Account Title has no Expenses column, so it was NOT posted to Expenses (set one in Account Titles)', 'error');
            else if (data.posted === 'error') showToast('✓ Entry saved — but posting to Expenses failed', 'error');
            else showToast('✓ Entry saved and posted to Expenses', 'success');
            updateTotals();
            row._saving = false;
            return true;
        }
        setRowState(row, 'err');
        showToast('❌ ' + data.msg, 'error');
    } catch(e) {
        setRowState(row, 'err');
        showToast('❌ Network error', 'error');
    }
    row._saving = false;
    return false;
}

// ── Enter = save. On the blank (new) row it also opens the next blank row. ──
document.addEventListener('keydown', async function (e) {
    if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
    const el = e.target;
    const row = el.closest ? el.closest('#dsb-tbody tr') : null;
    if (!row || el.tagName === 'SELECT' || el.tagName === 'BUTTON') return;
    if (el.classList.contains('ac-pick')) return;
    if (!row.dataset.id) {                 // new row → save, then a fresh blank row
        e.preventDefault(); e.stopPropagation();
        document.querySelectorAll('.ac-list').forEach(l => l.classList.remove('open'));
        const dateVal = row.querySelector('[data-col="entry_date"]').value;
        if (await saveRow(row)) addRow(true, dateVal);
    } else if (row.dataset.dirty === '1') { // saved row that was edited → save, then normal Enter-moves-down
        saveRow(row);
    }
}, true);

// ── Delete row ─────────────────────────────────────────────
async function deleteRow(btn) {
    if (!confirm('Delete this entry?')) return;
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    if (!id) { row.remove(); updateTotals(); return; }
    const fd = new FormData(); fd.append('ajax_delete','1'); fd.append('id', id);
    try {
        const res  = await fetch('stella_disbursement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            row.style.opacity = '0'; row.style.transition = 'opacity .3s';
            setTimeout(() => { row.remove(); updateTotals(); }, 300);
            showToast('✓ Entry deleted', 'success');
        } else { showToast('❌ ' + data.msg, 'error'); }
    } catch(e) { showToast('❌ Network error', 'error'); }
}

// ── Clear all entries of the month shown (double confirmation) ──
async function clearMonth() {
    const m = <?= json_encode($fMonth) ?>, label = <?= json_encode(date('F Y', strtotime($fMonth . '-01'))) ?>;
    const n = document.querySelectorAll('#dsb-tbody tr[data-id]:not([data-id=""])').length;
    if (!n) { showToast('Nothing to clear for ' + label, 'error'); return; }
    if (!confirm('Delete ALL ' + n + ' disbursement entries of ' + label + '?\n\nThe amounts they posted to Stella Expenses are removed too. This cannot be undone.')) return;
    const t = prompt('Type CLEAR to permanently delete the ' + n + ' entries of ' + label + ':');
    if (t === null) return;
    if (t.trim().toUpperCase() !== 'CLEAR') { showToast('Not cleared — you did not type CLEAR', 'error'); return; }
    const fd = new FormData(); fd.append('ajax_clear_month', '1'); fd.append('month', m); fd.append('confirm', 'CLEAR');
    try {
        const data = await (await fetch('stella_disbursement.php', { method: 'POST', body: fd })).json();
        if (data.ok) { showToast('✓ Cleared ' + data.deleted + ' entries', 'success'); setTimeout(() => location.reload(), 700); }
        else showToast('❌ ' + data.msg, 'error');
    } catch (e) { showToast('❌ Network error', 'error'); }
}

// ── Live totals ────────────────────────────────────────────
function updateTotals() {
    let g = 0, t = 0, n = 0;
    document.querySelectorAll('#dsb-tbody tr[data-id]:not([data-id=""])').forEach(row => {
        g += parseFloat(row.querySelector('[data-col="gross"]')?.value?.replace(/,/g,'') || 0);
        const te = row.querySelector('[data-col="input_tax"]'), ne = row.querySelector('[data-col="net_of_vat"]');
        t += parseFloat(te?.dataset.raw ?? te?.value?.replace(/,/g,'') ?? 0) || 0;
        n += parseFloat(ne?.dataset.raw ?? ne?.value?.replace(/,/g,'') ?? 0) || 0;
    });
    document.getElementById('tfoot-gross').textContent = g.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('tfoot-itax').textContent  = t.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    document.getElementById('tfoot-nvat').textContent  = n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    const count = document.querySelectorAll('#dsb-tbody tr[data-id]:not([data-id=""])').length;
    document.getElementById('dsb-count').textContent = count;
}

// Always keep one blank row at the bottom, ready to type into (no "+ Add Entry" button).
document.addEventListener('DOMContentLoaded', function () {
    addRow(false);
    updateTotals();
});

// ── Excel paste: copy rows from Excel / Google Sheets (DATE … PARTICULAR) and paste ──
//   • one line  → fills the row you are in (press Enter to save it)
//   • many lines → imported as new entries (asks you to confirm first)
const PASTE_COLS = ['entry_date','tin','company_name','vat_status','address','invoice_no','account_title','gross','input_tax','net_of_vat','particular'];
document.addEventListener('paste', async function (ev) {
    const el = ev.target;
    if (!el || !el.matches || !el.matches('#dsb-tbody input, #dsb-tbody select')) return;
    const text = (ev.clipboardData || window.clipboardData).getData('text');
    if (!text || (text.indexOf('\t') < 0 && !/\r?\n./.test(text))) return;          // single value → normal paste
    ev.preventDefault();
    let lines = text.replace(/\r/g, '').split('\n');
    while (lines.length && lines[lines.length - 1].trim() === '') lines.pop();
    let data = lines.map(l => l.split('\t').map(c => c.replace(/^"|"$/g, '').trim()));
    // drop a pasted header row (DATE … GROSS …)
    if (data.length && /^date$/i.test(data[0][0] || '') && data[0].some(c => /^gross$/i.test(c))) data.shift();
    if (!data.length) return;
    // Your Excel has an empty column B between DATE and TIN. If the copied cells include it (12 columns,
    // column B empty in every row), drop it so TIN, NAME, TYPE… line up with the right boxes.
    if (data.every(r => r.length >= 12 && String(r[1] || '').trim() === '')) data = data.map(r => [r[0]].concat(r.slice(2)));
    // Copied rows that begin with a date are whole entries (DATE … PARTICULAR): always start at the DATE box,
    // whichever box you clicked before pasting.
    const fullEntry = data.every(r => r.length >= 10 && pasteDateIso(r[0]) !== '');
    const start = fullEntry ? 0 : Math.max(0, PASTE_COLS.indexOf(el.dataset.col));
    const full = data.map(r => { const o = new Array(11).fill(''); r.forEach((v, j) => { if (start + j < 11) o[start + j] = v; }); return o; });

    if (full.length === 1) {                                                         // fill the current row
        const row = el.closest('tr');
        full[0].forEach((v, i) => {
            if (v === '' || ['input_tax','net_of_vat'].includes(PASTE_COLS[i])) return;
            const f = row.querySelector('[data-col="' + PASTE_COLS[i] + '"]');
            if (!f) return;
            if (PASTE_COLS[i] === 'entry_date') { const d = pasteDateIso(v); if (d) f.value = d; }
            else if (f.tagName === 'SELECT') {
                const opt = [...f.options].find(o => o.value.toLowerCase() === v.toLowerCase()); if (opt) f.value = opt.value;
            } else f.value = v;
            markDirty(f);
        });
        const g = row.querySelector('[data-col="gross"]'); if (g) calcRow(g);
        // keep the Input Tax / Net of VAT exactly as copied from Excel
        const pt = full[0][8], pn = full[0][9];
        if (String(pt).trim() !== '' || String(pn).trim() !== '') {
            const num = v => { v = String(v).trim(); return (v === '' || v === '-') ? 0 : (parseFloat(v.replace(/[^0-9.\-]/g, '')) || 0); };
            const gr = num(full[0][7]), t = num(pt), n = String(pn).trim() === '' ? gr - t : num(pn);
            if (Math.abs(gr - t - n) <= 0.02 && n !== 0) {
                row.dataset.ovTax = t.toFixed(2); row.dataset.ovNet = n.toFixed(2);
                row.querySelector('[data-col="input_tax"]').value = t.toFixed(2);
                row.querySelector('[data-col="net_of_vat"]').value = n.toFixed(2);
            }
        }
        showToast('✓ Pasted into this row — press Enter to save', 'success');
        return;
    }

    await confirmAndImport(full);
});

// Shared by Paste and Import Excel: validate, confirm, send, reload.
async function confirmAndImport(full) {
    const known = new Set(ACCT_OPTIONS.map(t => t.trim().toLowerCase()));
    const rows = full.filter(r => r[6] !== '' && (parseFloat(String(r[7]).replace(/[^0-9.\-]/g, '')) || 0) !== 0);
    if (!rows.length) { showToast('❌ No rows with an Account Title and a Gross amount found', 'error'); return; }
    const gross = rows.reduce((a, r) => a + (parseFloat(String(r[7]).replace(/[^0-9.\-]/g, '')) || 0), 0);
    const unknown = [...new Set(rows.map(r => r[6]).filter(t => !known.has(t.trim().toLowerCase())))];
    let msg = 'Import ' + rows.length + ' entries?\nTotal Gross: ' + gross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (unknown.length) msg += '\n\nThese Account Titles are not in your list (they will not post to Expenses until added):\n• ' + unknown.slice(0, 8).join('\n• ');
    const noDate = rows.filter(r => String(r[0]).trim() === '').length;
    if (noDate) msg += '\n\n' + noDate + ' rows have no date — they will be dated the last day of the month of the other rows.';
    msg += '\n\nInput Tax and Net of VAT are recalculated automatically. Entries already in the system are skipped.';
    if (!confirm(msg)) return;

    showToast('Importing ' + rows.length + ' entries…', 'success');
    const fd = new FormData(); fd.append('ajax_import', '1'); fd.append('rows', JSON.stringify(rows)); fd.append('month', document.querySelector('input[name="month"]')?.value || '');
    try {
        const res = await fetch('stella_disbursement.php', { method: 'POST', body: fd });
        const out = await res.json();
        if (!out.ok) { showToast('❌ ' + out.msg, 'error'); return; }
        let m = '✓ Imported ' + out.added + ' entries';
        if (out.duplicates) m += ' · ' + out.duplicates + ' already existed (skipped)';
        if (out.undated)    m += ' · ' + out.undated + ' had no date, so they were dated ' + out.fallback_date;
        if (out.unposted)   m += ' · ' + out.unposted + ' not posted to Expenses (no Expenses column on their title)';
        showToast(m, out.unposted ? 'error' : 'success');
        setTimeout(() => location.reload(), 1600);
    } catch (e) { showToast('❌ Network error', 'error'); }
}
function pasteDateIso(v) {
    v = String(v).trim();
    let m = v.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
    if (m) { let a = +m[1], b = +m[2], y = +m[3]; if (y < 100) y += 2000; let mo = a, d = b; if (a > 12 && b <= 12) { mo = b; d = a; }
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0'); }
    m = v.match(/^(\d{1,2})-([A-Za-z]{3})[a-z]*-(\d{2,4})$/);
    if (m) { const mo = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'].indexOf(m[2].toLowerCase()); let y = +m[3]; if (y < 100) y += 2000;
        if (mo >= 0) return y + '-' + String(mo + 1).padStart(2, '0') + '-' + String(+m[1]).padStart(2, '0'); }
    return /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '';
}

// ── Import Excel: choose the file (same layout as your disbursement sheet) ──
function loadSheetJS() {
    return new Promise((resolve, reject) => {
        if (window.XLSX) return resolve();
        const sc = document.createElement('script');
        sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        sc.onload = () => resolve(); sc.onerror = () => reject(new Error('Could not load the Excel reader (check your internet connection)'));
        document.head.appendChild(sc);
    });
}
function sheetHeader(ws) {          // finds the header row (DATE … ACCOUNT TITLE … GROSS) → {row, cols}
    const grid = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '', blankrows: true });
    const rawGrid = XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: '', blankrows: true });
    for (let r = 0; r < Math.min(grid.length, 15); r++) {
        const names = grid[r].map(c => String(c).trim().toUpperCase());
        if (names.includes('DATE') && names.includes('ACCOUNT TITLE') && names.includes('GROSS')) {
            const at = n => names.indexOf(n);
            return { grid, rawGrid, row: r, cols: [at('DATE'), at('TIN'), at('NAME OF SUPPLIER'), at('TYPE'), at('ADDRESS'), at('INVOICE #'), at('ACCOUNT TITLE'), at('GROSS'), at('INPUT TAX'), at('NET OF VAT'), at('PARTICULAR')] };
        }
    }
    return null;
}
function pickSheet(names, preferred) {
    return new Promise(resolve => {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center';
        wrap.innerHTML = '<div style="background:#fff;border-radius:12px;padding:20px 22px;min-width:300px;box-shadow:0 10px 40px rgba(0,0,0,.3);font-family:inherit">' +
            '<div style="font-weight:700;margin-bottom:6px">Which sheet should be imported?</div>' +
            '<div style="font-size:.78rem;color:#667;margin-bottom:12px">This file has several disbursement sheets.</div>' +
            '<select id="shSel" style="width:100%;padding:8px;font-size:.9rem;margin-bottom:14px">' + names.map(n => '<option' + (n === preferred ? ' selected' : '') + '>' + escHtml(n) + '</option>').join('') + '</select>' +
            '<div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn btn-ghost btn-sm" id="shNo">Cancel</button><button class="btn btn-primary btn-sm" id="shOk">Import this sheet</button></div></div>';
        document.body.appendChild(wrap);
        wrap.querySelector('#shOk').onclick = () => { const v = wrap.querySelector('#shSel').value; wrap.remove(); resolve(v); };
        wrap.querySelector('#shNo').onclick = () => { wrap.remove(); resolve(null); };
    });
}
async function importExcelFile(inp) {
    const file = inp.files && inp.files[0];
    inp.value = '';
    if (!file) return;
    try {
        showToast('Reading ' + file.name + '…', 'success');
        await loadSheetJS();
        const wb = XLSX.read(await file.arrayBuffer(), { type: 'array' });
        const found = wb.SheetNames.map(n => ({ n, h: sheetHeader(wb.Sheets[n]) })).filter(x => x.h);
        if (!found.length) { showToast('❌ No sheet with the columns DATE, ACCOUNT TITLE and GROSS was found', 'error'); return; }
        let chosen = found[0];
        if (found.length > 1) {
            const mm = (document.querySelector('input[name="month"]')?.value || '').slice(5, 7);      // page month → sheet "10"
            const pref = (found.find(x => x.n.trim() === String(parseInt(mm || '0', 10)) || x.n.trim() === mm) || found[0]).n;
            const pick = await pickSheet(found.map(x => x.n), pref);
            if (!pick) return;
            chosen = found.find(x => x.n === pick);
        }
        const { grid, rawGrid, row, cols } = chosen.h;
        const rows = [];
        for (let r = row + 1; r < grid.length; r++) {
            // Input Tax / Net of VAT: use the cell's exact value (Excel hides the extra decimals) so totals match to the cent
            rows.push(cols.map((ci, k) => {
                if (ci < 0) return '';
                const rv = rawGrid[r] ? rawGrid[r][ci] : undefined;
                if ((k === 8 || k === 9) && typeof rv === 'number') return String(rv);
                return String(grid[r][ci] ?? '').trim();
            }));
        }
        await confirmAndImport(rows);
    } catch (e) { showToast('❌ ' + e.message, 'error'); }
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'flash flash-' + (type || 'success') + ' toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// Keep the table box inside the screen so its header and the TOTAL row are always visible.
(function () {
  function fit() {
    var o = document.querySelector('.dsb-outer'); if (!o) return;
    var top = o.getBoundingClientRect().top + window.pageYOffset;
    o.style.maxHeight = Math.max(320, window.innerHeight - top - 16) + 'px';
  }
  fit(); window.addEventListener('resize', fit); window.addEventListener('load', fit);
})();
</script>
</body>
</html>