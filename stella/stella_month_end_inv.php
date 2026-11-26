<?php
// ============================================================
//  stella_month_end_inv.php — Stella Branch Month End Inventory
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Create table if not exists ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_month_end_inv` (
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `inv_date`      date NOT NULL,
    `inv_year`      int(4) NOT NULL,
    `inv_month`     tinyint(2) NOT NULL,
    `store_name`    varchar(50) NOT NULL DEFAULT 'Stella',
    `category`      varchar(50) NOT NULL,
    `sort_order`    int(4) NOT NULL DEFAULT 0,
    `item_desc`     varchar(200) NOT NULL DEFAULT '',
    `unit`          varchar(20) NOT NULL DEFAULT 'BOTTLE',
    `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
    `end_inv_num`   decimal(12,4) NOT NULL DEFAULT 0.0000,
    `total_amount`  decimal(12,2) NOT NULL DEFAULT 0.00,
    `saved_by`      varchar(100) DEFAULT NULL,
    `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Sections (custom categories) table ─────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_categories` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `store_name`  varchar(50) NOT NULL DEFAULT 'Stella',
    `name`        varchar(100) NOT NULL,
    `sort_order`  int(4) NOT NULL DEFAULT 0,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_store_name` (`store_name`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Tiny flag table so the default-section seed below only ever runs ONCE,
// ever — not "once whenever the table happens to be empty". Otherwise,
// deleting every section would make it look empty again and the defaults
// would silently come back on the next page load.
$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_categories_meta` (
    `store_name`  varchar(50) NOT NULL,
    `seeded`      tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`store_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// One-time seed: preserve the original 8 default sections the very first
// time this page ever runs, so pre-existing saved data keeps showing up
// exactly as before. This never runs again after that — even if the user
// later deletes every section on purpose, it will correctly stay empty.
$alreadySeeded = (bool)$pdo->query("SELECT seeded FROM stella_categories_meta WHERE store_name='Stella'")->fetchColumn();
if (!$alreadySeeded) {
    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM stella_categories WHERE store_name='Stella'")->fetchColumn();
    if ($catCount === 0) {
        $defaults = ['LIQUORS/BEVERAGES','DRY GOODS','BAR STOCKS','MEAT/WET PRODUCTS',
                     'VEGETABLES','PASTA','CONDIMENTS','BREAD'];
        $insDef = $pdo->prepare("INSERT IGNORE INTO stella_categories (store_name,name,sort_order) VALUES ('Stella',?,?)");
        foreach ($defaults as $i => $name) $insDef->execute([$name, $i]);
    }
    $pdo->prepare("INSERT INTO stella_categories_meta (store_name,seeded) VALUES ('Stella',1) ON DUPLICATE KEY UPDATE seeded=1")->execute();
}

// One-time self-heal: any category name already used inside
// stella_month_end_inv (e.g. from a renamed/typo'd section) that isn't
// registered yet gets auto-registered, so its items don't disappear.
$knownNames = $pdo->query("SELECT name FROM stella_categories WHERE store_name='Stella'")->fetchAll(PDO::FETCH_COLUMN);
$usedNames  = $pdo->query("SELECT DISTINCT category FROM stella_month_end_inv WHERE store_name='Stella' AND category<>''")->fetchAll(PDO::FETCH_COLUMN);
$missing    = array_diff($usedNames, $knownNames);
if (!empty($missing)) {
    $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM stella_categories WHERE store_name='Stella'")->fetchColumn();
    $insMiss = $pdo->prepare("INSERT IGNORE INTO stella_categories (store_name,name,sort_order) VALUES ('Stella',?,?)");
    foreach (array_values($missing) as $i => $name) {
        $insMiss->execute([$name, $maxOrd + 1 + $i]);
    }
}

// Load current section list (custom, user-created)
$catStmt = $pdo->query("SELECT name FROM stella_categories WHERE store_name='Stella' ORDER BY sort_order,id");
$CATEGORIES = array_column($catStmt->fetchAll(PDO::FETCH_ASSOC), 'name');

// Shared color palette for category headers + KPI cards (cycles for any count)
$CAT_COLORS = ['#1e3a5f','#3d1f0f','#1f0f3d','#3d0f1f','#0f3d1f','#1f3d0f','#3d2e0f','#0f2e3d',
               '#4a1f5f','#5f3d1f','#1f5f4a','#5f1f3d'];

$UNITS = ['BOTTLE','PCS','PACKS','ROLL','CAN','KILO','BOX','JAR','GRAMS','KG','ML','BARS','TUB','GAL','LITER',
          'BUNDLE','SACK','SET','SERVING','SLICE','PAD','REAM','PESOS','DOZEN','UNIT'];

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];

// ── Filters ───────────────────────────────────────────────
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fMonth = (int)($_GET['month'] ?? date('n'));
if ($fMonth < 1)  $fMonth = 1;
if ($fMonth > 12) $fMonth = 12;

// Max days in selected month
$daysInMonth = (int)date('t', mktime(0,0,0,$fMonth,1,$fYear));
$fDay = (int)($_GET['day'] ?? $daysInMonth);
if ($fDay < 1)            $fDay = 1;
if ($fDay > $daysInMonth) $fDay = $daysInMonth;

$selectedDate = date('Y-m-d', mktime(0,0,0,$fMonth,$fDay,$fYear));
$lastDay      = $selectedDate; // used for saving
$displayDate  = $months[$fMonth-1] . ' ' . str_pad($fDay,2,'0',STR_PAD_LEFT) . ', ' . $fYear;

// The GRAND TOTAL here is COGS END. (and next month's BEG.) → after any change, refresh COGS
// (runs after the JSON reply is sent; months done oldest first so each BEG. follows the END. before it).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_intersect(array_keys($_POST), ['ajax_save','ajax_delete','ajax_delete_category','ajax_duplicate_day','ajax_import_run'])) {
    register_shutdown_function(function () use ($pdo) {
        $f = __DIR__ . '/../lib/expense_sync.php';
        if (!is_file($f)) return;
        require_once $f;
        if (!function_exists('stellaCogsSyncEnd')) return;
        try {
            $ms = $pdo->query("SELECT DISTINCT inv_year y, inv_month m FROM stella_month_end_inv WHERE store_name='Stella' ORDER BY inv_year, inv_month")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ms as $r) stellaCogsSyncEnd($pdo, (int)$r['y'], (int)$r['m']);
        } catch (Throwable $e) {}
    });
}

// ── AJAX: Save row ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $rowId      = (int)($_POST['row_id']      ?? 0);
        $category   = trim($_POST['category']     ?? '');
        $sortOrder  = (int)($_POST['sort_order']  ?? 0);
        $itemDesc   = trim($_POST['item_desc']    ?? '');
        $unit       = trim($_POST['unit']         ?? 'BOTTLE');
        $suppCost   = (float)($_POST['supplier_cost'] ?? 0);
        $endInvNum  = (float)($_POST['end_inv_num']   ?? 0);
        $total      = round($suppCost * $endInvNum, 2);

        // The page sends the date it is showing (this request has no ?year=&month=&day= in its URL, so without it
        // the server would fall back to the current month's last day and save into the WRONG date).
        $saveDate = trim($_POST['inv_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saveDate) || !strtotime($saveDate)) $saveDate = $lastDay;
        $lastDay = $saveDate;
        $yr = (int)date('Y', strtotime($lastDay));
        $mo = (int)date('n', strtotime($lastDay));

        if ($rowId > 0) {
            // an existing item stays on its own date — only its content is updated
            $pdo->prepare("UPDATE stella_month_end_inv SET category=?, sort_order=?, item_desc=?, unit=?, supplier_cost=?, end_inv_num=?, total_amount=?, saved_by=? WHERE id=? AND store_name='Stella'")
                ->execute([$category,$sortOrder,$itemDesc,$unit,$suppCost,$endInvNum,$total,$user['name'],$rowId]);
            echo json_encode(['ok'=>true,'id'=>$rowId,'total'=>$total]);
        } else {
            $pdo->prepare("INSERT INTO stella_month_end_inv (inv_date,inv_year,inv_month,store_name,category,sort_order,item_desc,unit,supplier_cost,end_inv_num,total_amount,saved_by) VALUES (?,?,?,'Stella',?,?,?,?,?,?,?,?)")
                ->execute([$lastDay,$yr,$mo,$category,$sortOrder,$itemDesc,$unit,$suppCost,$endInvNum,$total,$user['name']]);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['ok'=>true,'id'=>$newId,'total'=>$total]);
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
        $pdo->prepare("DELETE FROM stella_month_end_inv WHERE id=? AND store_name='Stella'")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Add a new section (category) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_category'])) {
    header('Content-Type: application/json');
    try {
        $name = strtoupper(trim($_POST['name'] ?? ''));
        if ($name === '') { echo json_encode(['ok'=>false,'msg'=>'Section name cannot be empty.']); exit; }
        $exists = $pdo->prepare("SELECT id FROM stella_categories WHERE store_name='Stella' AND name=?");
        $exists->execute([$name]);
        if ($exists->fetch()) { echo json_encode(['ok'=>false,'msg'=>'A section named "'.$name.'" already exists.']); exit; }
        $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM stella_categories WHERE store_name='Stella'")->fetchColumn();
        $pdo->prepare("INSERT INTO stella_categories (store_name,name,sort_order) VALUES ('Stella',?,?)")
            ->execute([$name, $maxOrd+1]);
        echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'name'=>$name]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Remove a section (blocks unless empty, or forced) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_category'])) {
    header('Content-Type: application/json');
    try {
        $name  = trim($_POST['name'] ?? '');
        $force = !empty($_POST['force']);
        $chk   = $pdo->prepare("SELECT COUNT(*) FROM stella_month_end_inv WHERE store_name='Stella' AND category=?");
        $chk->execute([$name]);
        $itemCount = (int)$chk->fetchColumn();

        if ($itemCount > 0 && !$force) {
            echo json_encode(['ok'=>false,'needsForce'=>true,'itemCount'=>$itemCount,
                'msg'=>"This section still has $itemCount saved item(s) across one or more dates."]);
            exit;
        }
        if ($itemCount > 0 && $force) {
            $pdo->prepare("DELETE FROM stella_month_end_inv WHERE store_name='Stella' AND category=?")->execute([$name]);
        }
        $pdo->prepare("DELETE FROM stella_categories WHERE store_name='Stella' AND name=?")->execute([$name]);
        echo json_encode(['ok'=>true,'deletedItems'=>$itemCount]);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Duplicate this date's items into other day(s) ────
// Lets a user build the item list once and reuse it across the month
// instead of retyping every item description / unit / cost each day.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_duplicate_day'])) {
    header('Content-Type: application/json');
    try {
        $srcDate = trim($_POST['src_date'] ?? '');
        $targets = json_decode($_POST['target_dates'] ?? '[]', true);
        $copyQty = !empty($_POST['copy_qty']);
        $mode    = ($_POST['mode'] ?? 'skip'); // skip | append | replace

        if (!$srcDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $srcDate)) {
            echo json_encode(['ok'=>false,'msg'=>'Missing or invalid source date.']); exit;
        }
        if (!is_array($targets) || !count($targets)) {
            echo json_encode(['ok'=>false,'msg'=>'Pick at least one day to duplicate into.']); exit;
        }

        $srcStmt = $pdo->prepare("SELECT * FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=? ORDER BY sort_order,id");
        $srcStmt->execute([$srcDate]);
        $srcRows = $srcStmt->fetchAll();
        if (!$srcRows) {
            echo json_encode(['ok'=>false,'msg'=>'No items on '.$srcDate.' to duplicate yet.']); exit;
        }

        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=?");
        $delStmt = $pdo->prepare("DELETE FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=?");
        $insStmt = $pdo->prepare("INSERT INTO stella_month_end_inv (inv_date,inv_year,inv_month,store_name,category,sort_order,item_desc,unit,supplier_cost,end_inv_num,total_amount,saved_by) VALUES (?,?,?,'Stella',?,?,?,?,?,?,?,?)");

        $summary = ['copied'=>[], 'skipped'=>[]];
        foreach ($targets as $tDate) {
            $tDate = trim($tDate);
            if (!$tDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tDate)) continue;
            if ($tDate === $srcDate) continue; // duplicating a date onto itself would just double it up

            $chkStmt->execute([$tDate]);
            $existing = (int)$chkStmt->fetchColumn();

            if ($existing > 0) {
                if ($mode === 'skip')   { $summary['skipped'][] = $tDate; continue; }
                if ($mode === 'replace') $delStmt->execute([$tDate]);
                // mode === 'append' just inserts on top of what's there
            }

            $yr = (int)date('Y', strtotime($tDate));
            $mo = (int)date('n', strtotime($tDate));
            foreach ($srcRows as $r) {
                $endInv   = $copyQty ? (float)$r['end_inv_num'] : 0;
                $suppCost = (float)$r['supplier_cost'];
                $total    = $copyQty ? round($suppCost * $endInv, 2) : 0;
                $insStmt->execute([$tDate,$yr,$mo,$r['category'],$r['sort_order'],$r['item_desc'],$r['unit'],$suppCost,$endInv,$total,$user['name']]);
            }
            $summary['copied'][] = $tDate;
        }

        echo json_encode(['ok'=>true,'summary'=>$summary,'itemCount'=>count($srcRows)]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Item counts for an arbitrary month/year ───────────
// Powers the "Duplicate to Other Days" modal when the user browses to
// a month other than the one currently on screen (e.g. next month).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_month_counts'])) {
    header('Content-Type: application/json');
    try {
        $yr = (int)($_POST['year'] ?? 0);
        $mo = (int)($_POST['month'] ?? 0);
        if ($yr < 2000 || $yr > 2100 || $mo < 1 || $mo > 12) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid month/year.']); exit;
        }
        $cStmt = $pdo->prepare("SELECT DAY(inv_date) as d, COUNT(*) as c FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=? GROUP BY DAY(inv_date)");
        $cStmt->execute([$yr, $mo]);
        $counts = array_column($cStmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'd');
        $days   = (int)date('t', mktime(0,0,0,$mo,1,$yr));
        echo json_encode(['ok'=>true,'counts'=>(object)$counts,'days'=>$days]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}


// ══════════════════════════════════════════════════════════════
//  IMPORT FROM EXCEL  (reads the .xlsx directly — no library needed)
//  Reads the saved values of the sheet (what Excel shows), finds the item lists by their
//  "UNIT" header (left list A–E, right list G–K), and re-computes Total = Supplier Cost × End.Inv.Num.
// ══════════════════════════════════════════════════════════════
function impColIdx(string $ref): int { $n = 0; foreach (str_split(preg_replace('/[^A-Z]/', '', strtoupper($ref))) as $ch) $n = $n * 26 + (ord($ch) - 64); return $n; }
function impSheets(ZipArchive $z): array {
    $wb = $z->getFromName('xl/workbook.xml'); $rl = $z->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb === false || $rl === false) return [];
    $rels = [];
    foreach (simplexml_load_string($rl)->Relationship as $r) $rels[(string)$r['Id']] = (string)$r['Target'];
    $out = [];
    $x = simplexml_load_string($wb);
    foreach ($x->sheets->sheet as $sh) {
        $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $t = $rels[$rid] ?? ''; if ($t === '') continue;
        $t = ltrim($t, '/'); if (strpos($t, 'xl/') !== 0) $t = 'xl/' . $t;
        $out[] = ['name' => (string)$sh['name'], 'file' => $t];
    }
    return $out;
}
function impShared(ZipArchive $z): array {
    $raw = $z->getFromName('xl/sharedStrings.xml'); if ($raw === false) return [];
    $out = [];
    foreach (simplexml_load_string($raw)->si as $si) {
        if (isset($si->t)) { $out[] = (string)$si->t; continue; }
        $t = ''; foreach ($si->r as $r) $t .= (string)$r->t; $out[] = $t;
    }
    return $out;
}
function impGrid(ZipArchive $z, string $file, array $shared): array {
    $raw = $z->getFromName($file); if ($raw === false) return [];
    $x = simplexml_load_string($raw); $g = [];
    if (!isset($x->sheetData)) return [];
    foreach ($x->sheetData->row as $row) {
        $rn = (int)$row['r']; if ($rn > 6000) break;
        foreach ($row->c as $c) {
            $ci = impColIdx((string)$c['r']); if ($ci > 30) continue;
            $t = (string)$c['t']; $v = null;
            if ($t === 'inlineStr') { $v = (string)$c->is->t; }
            elseif (isset($c->v)) {
                $raw = (string)$c->v;
                if ($t === 's') $v = $shared[(int)$raw] ?? '';
                elseif ($t === 'str' || $t === 'e') $v = ($t === 'e') ? null : $raw;
                elseif ($t === 'b') $v = (float)$raw;
                else $v = is_numeric($raw) ? (float)$raw : $raw;
            }
            if ($v !== null && $v !== '') $g[$rn][$ci] = $v;
        }
    }
    return $g;
}
function impNum($v): ?float {
    if ($v === null || $v === '') return null;
    if (is_int($v) || is_float($v)) return (float)$v;
    $t = str_replace([',', ' '], '', (string)$v);
    return is_numeric($t) ? (float)$t : null;
}
function impUnit($u): string {
    if ($u === null) return 'PCS';
    $u = strtoupper(trim((string)$u)); $u = trim($u, " '\"");
    if ($u === '' || preg_match('/^-?\d+(\.\d+)?$/', $u)) return 'PCS';
    $map = ['BOT'=>'BOTTLE','BOTS'=>'BOTTLE','BOTTLES'=>'BOTTLE','KILOS'=>'KILO','KL'=>'KILO','PACK'=>'PACKS','PCK'=>'PACKS','PC'=>'PCS',
            'GALON'=>'GAL','GALLON'=>'GAL','LTR'=>'LITER','LITERS'=>'LITER','GRMAS'=>'GRAMS','GRMS'=>'GRAMS','GRAM'=>'GRAMS','BAR'=>'BARS',
            'ROLLS'=>'ROLL','1ROLL'=>'ROLL','SACKS'=>'SACK','SERVE'=>'SERVING','BNDL'=>'BUNDLE','BUNDLES'=>'BUNDLE','CANS'=>'CAN','BOXES'=>'BOX','1DOZEN'=>'DOZEN'];
    return substr($map[$u] ?? $u, 0, 20);
}
// Reads one sheet → ['items'=>[...], 'excelTotal'=>float, 'sections'=>[name=>count]]
function impParse(array $grid, string $defaultSection): array {
    // panels = every cell "UNIT" in the first rows
    $panels = [];
    for ($r = 1; $r <= 12; $r++) foreach (($grid[$r] ?? []) as $ci => $v)
        if (is_string($v) && strtoupper(trim($v)) === 'UNIT' && $ci >= 2) $panels[] = ['row' => $r, 'unit' => $ci];
    if (!$panels) $panels = [['row' => 2, 'unit' => 2]];
    usort($panels, fn($a, $b) => $a['unit'] <=> $b['unit']);
    $maxRow = $grid ? max(array_keys($grid)) : 0;
    $items = []; $excelTotal = 0.0; $sections = [];
    foreach ($panels as $p) {
        $cd = $p['unit'] - 1; $cu = $p['unit']; $cc = $p['unit'] + 1; $cq = $p['unit'] + 2; $ct = $p['unit'] + 3;
        $section = $defaultSection; $first = true;
        for ($r = $p['row'] + 1; $r <= $maxRow; $r++) {
            $row = $grid[$r] ?? [];
            $desc = $row[$cd] ?? null; $unit = $row[$cu] ?? null; $cost = $row[$cc] ?? null; $qty = $row[$cq] ?? null; $tot = $row[$ct] ?? null;
            $dU = is_string($desc) ? strtoupper(trim($desc)) : '';
            if ((is_string($qty) && stripos(trim($qty), 'TOTAL') === 0) || (is_string($desc) && preg_match('/^(TOTAL|OVERALL|GRAND)/', $dU))) {
                $excelTotal += (float)(impNum($tot) ?? impNum($cost) ?? 0);
                break;
            }
            if (!is_string($desc) || trim($desc) === '') continue;
            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            // first line of a list with nothing else on it = the list's section title (e.g. FRUITS&VEGETABLES)
            if ($first && $unit === null && $cost === null && $qty === null && $tot === null) {
                $section = strtoupper(substr($desc, 0, 100)); $first = false; continue;
            }
            $first = false;
            $c = impNum($cost); $q = impNum($qty); $t = impNum($tot);
            if ($q !== null) { $c = $c ?? 0.0; $t = round($c * $q, 2); }
            elseif ($t !== null && $t > 0) {
                if ($c !== null && $c > 0) $q = round($t / $c, 4); else { $c = $t; $q = 1.0; }
                $t = round($t, 2);
            } else { $c = $c ?? 0.0; $q = 0.0; $t = 0.0; }
            $items[] = ['section' => $section, 'desc' => substr($desc, 0, 200), 'unit' => impUnit($unit),
                        'cost' => round($c, 2), 'qty' => round($q, 4), 'total' => $t];
            $sections[$section] = ($sections[$section] ?? 0) + 1;
        }
    }
    return ['items' => $items, 'excelTotal' => $excelTotal, 'sections' => $sections];
}
function impGuessParts(string $name): ?array {
    $months = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
    $u = strtoupper($name); $m = 0;
    foreach ($months as $k => $n) { if (strpos($u, $k) !== false) { $m = $n; break; } }
    if (!$m) return null;
    $year = null;
    if (preg_match('/(20\d\d)/', $u, $y)) $year = (int)$y[1];
    elseif (preg_match('/2K(\d\d)/', $u, $y)) $year = 2000 + (int)$y[1];
    $rest = preg_replace('/(20\d\d)|2K\d\d/', '', $u);
    $day = (preg_match('/(\d{1,2})/', $rest, $d) && (int)$d[1] >= 1 && (int)$d[1] <= 31) ? (int)$d[1] : null;
    return ['m' => $m, 'd' => $day, 'y' => $year];
}
// The workbook lists the newest sheet first. A sheet name without a year (e.g. "OCTOBER 30") takes the year of the
// sheet before it, going back one year when the month goes up (… JAN 2025, DECEMBER 28 → 2024).
function impGuessDates(array $names, int $defYear): array {
    $out = []; $lastY = $defYear; $lastM = 12; $first = true;
    foreach ($names as $name) {
        $g = impGuessParts($name);
        if (!$g) { $out[] = ['date' => '', 'explicit' => false]; continue; }
        $explicit = $g['y'] !== null;
        if ($explicit) $y = $g['y'];
        else { $y = $lastY; if (!$first && $g['m'] > $lastM) $y = $lastY - 1; }
        $lastY = $y; $lastM = $g['m']; $first = false;
        $dim = (int)date('t', mktime(0, 0, 0, $g['m'], 1, $y));
        $d = ($g['d'] !== null && $g['d'] <= $dim) ? $g['d'] : $dim;
        $out[] = ['date' => sprintf('%04d-%02d-%02d', $y, $g['m'], $d), 'explicit' => $explicit];
    }
    return $out;
}
function impOpenUpload(?string &$err): ?ZipArchive {
    if (!class_exists('ZipArchive')) { $err = 'This server cannot read .xlsx files (the PHP "zip" extension is off). Ask Hostinger to enable it.'; return null; }
    $f = $_FILES['xlsx'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) { $err = 'The file did not upload (' . ($f['error'] ?? 'none') . '). Pick the .xlsx file again.'; return null; }
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') { $err = 'Please choose an Excel .xlsx file.'; return null; }
    $z = new ZipArchive();
    if ($z->open($f['tmp_name']) !== true) { $err = 'That file could not be opened as an Excel workbook.'; return null; }
    return $z;
}

// ── AJAX: list the sheets of the uploaded workbook ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_import_inspect'])) {
    header('Content-Type: application/json');
    try {
        @set_time_limit(120); @ini_set('memory_limit', '512M');
        $err = null; $z = impOpenUpload($err);
        if (!$z) { echo json_encode(['ok' => false, 'msg' => $err]); exit; }
        $sheets = [];
        $shs = impSheets($z); $guess = impGuessDates(array_column($shs, 'name'), $fYear);
        foreach ($shs as $i => $sh) $sheets[] = ['name' => $sh['name'], 'date' => $guess[$i]['date'], 'explicit' => $guess[$i]['explicit']];
        $z->close();
        echo json_encode(['ok' => true, 'sheets' => $sheets]);
    } catch (Throwable $e) { echo json_encode(['ok' => false, 'msg' => 'Could not read the file: ' . $e->getMessage()]); }
    exit;
}

// ── AJAX: preview (dry=1) or import one sheet into one date ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_import_run'])) {
    header('Content-Type: application/json');
    try {
        @set_time_limit(180); @ini_set('memory_limit', '512M');
        $err = null; $z = impOpenUpload($err);
        if (!$z) { echo json_encode(['ok' => false, 'msg' => $err]); exit; }
        $sheetName = (string)($_POST['sheet'] ?? '');
        $date      = trim($_POST['inv_date'] ?? '');
        $mode      = (($_POST['mode'] ?? 'replace') === 'append') ? 'append' : 'replace';
        $dry       = !empty($_POST['dry']);
        $defSec    = strtoupper(trim($_POST['section'] ?? '')); if ($defSec === '') $defSec = 'INVENTORY';
        $defSec    = substr($defSec, 0, 100);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) { echo json_encode(['ok' => false, 'msg' => 'Pick the date to import into.']); exit; }
        $file = null;
        foreach (impSheets($z) as $sh) if ($sh['name'] === $sheetName) { $file = $sh['file']; break; }
        if (!$file) { echo json_encode(['ok' => false, 'msg' => 'Sheet not found in the file.']); exit; }
        $parsed = impParse(impGrid($z, $file, impShared($z)), $defSec);
        $z->close();
        $items = $parsed['items'];
        $ourTotal = 0.0; foreach ($items as $it) $ourTotal += $it['total'];
        $exQ = $pdo->prepare("SELECT COUNT(*) FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=?"); $exQ->execute([$date]);
        $existing = (int)$exQ->fetchColumn();
        $info = ['ok' => true, 'items' => count($items), 'total' => round($ourTotal, 2), 'excelTotal' => round($parsed['excelTotal'], 2),
                 'sections' => $parsed['sections'], 'existing' => $existing, 'withQty' => count(array_filter($items, fn($i) => $i['qty'] > 0))];
        if (!$items) { echo json_encode(['ok' => false, 'msg' => 'No items found on that sheet (looking for a list with a UNIT column header).']); exit; }
        if ($dry) { echo json_encode($info); exit; }

        $yr = (int)date('Y', strtotime($date)); $mo = (int)date('n', strtotime($date));
        $pdo->beginTransaction();
        if ($mode === 'replace' && $existing > 0) $pdo->prepare("DELETE FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=?")->execute([$date]);
        $known = $pdo->query("SELECT name FROM stella_categories WHERE store_name='Stella'")->fetchAll(PDO::FETCH_COLUMN);
        $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM stella_categories WHERE store_name='Stella'")->fetchColumn();
        $insCat = $pdo->prepare("INSERT IGNORE INTO stella_categories (store_name,name,sort_order) VALUES ('Stella',?,?)");
        $created = [];
        foreach (array_keys($parsed['sections']) as $sec) if (!in_array($sec, $known, true)) { $insCat->execute([$sec, ++$maxOrd]); $created[] = $sec; }
        $ordQ = $pdo->prepare("SELECT COALESCE(MAX(sort_order),-1) FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=? AND category=?");
        $next = [];
        $ins = $pdo->prepare("INSERT INTO stella_month_end_inv (inv_date,inv_year,inv_month,store_name,category,sort_order,item_desc,unit,supplier_cost,end_inv_num,total_amount,saved_by) VALUES (?,?,?,'Stella',?,?,?,?,?,?,?,?)");
        foreach ($items as $it) {
            $sec = $it['section'];
            if (!isset($next[$sec])) { $ordQ->execute([$date, $sec]); $next[$sec] = (int)$ordQ->fetchColumn() + 1; }
            $ins->execute([$date, $yr, $mo, $sec, $next[$sec]++, $it['desc'], $it['unit'], $it['cost'], $it['qty'], $it['total'], $user['name']]);
        }
        $pdo->commit();
        $info['created'] = $created; $info['date'] = $date;
        echo json_encode($info);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Import failed: ' . $e->getMessage()]);
    }
    exit;
}

// ── Excel (.xlsx) Export ─────────────────────────────────────
// Styled export (branch-colored title/header, bordered totals) —
// replaces the old plain-text CSV export.
if (isset($_GET['export_csv'])) {
    require_once __DIR__ . '/../lib/xlsx_writer.php';
    require_once __DIR__ . '/../lib/branch_colors.php';

    $stmt = $pdo->prepare("SELECT * FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=? ORDER BY sort_order, id");
    $stmt->execute([$selectedDate]);
    $rows = $stmt->fetchAll();

    $xlsx = new SimpleXlsxWriter(branchAccentColor('Stella'));
    $xlsx->title('Stella Branch — Month End Inventory');
    $xlsx->subtitle('DATE: ' . $displayDate);
    $xlsx->blank();
    $xlsx->header(['ITEM DESCRIPTIONS','UNIT','SUPPLIER COST','END.INV.NUM','TOTAL AMOUNT']);

    $grandTotal = 0;
    $grouped = [];
    foreach ($rows as $r) $grouped[$r['category']][] = $r;

    foreach ($CATEGORIES as $cat) {
        $catRows = $grouped[$cat] ?? [];
        $xlsx->sectionRow($cat);
        $catTotal = 0;
        foreach ($catRows as $r) {
            $xlsx->row([$r['item_desc'],$r['unit'],number_format($r['supplier_cost'],2,'.',''),number_format($r['end_inv_num'],4,'.',''),number_format($r['total_amount'],2,'.','')]);
            $catTotal += (float)$r['total_amount'];
        }
        $xlsx->row(['','','','TOTAL', number_format($catTotal,2,'.','')]);
        $grandTotal += $catTotal;
    }
    $xlsx->totalRow(['','','','GRAND TOTAL', number_format($grandTotal,2,'.','')]);
    $xlsx->blank();
    $xlsx->footer('Generated by SalesHub — ' . date('Y-m-d H:i:s'));
    $xlsx->columnWidths([28,10,14,14,16]);
    $xlsx->output('stella_month_end_inv_'.date('Y_m',mktime(0,0,0,$fMonth,1,$fYear)).'.xlsx');
}

// ── Load rows ─────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM stella_month_end_inv WHERE store_name='Stella' AND inv_date=? ORDER BY sort_order, id");
$stmt->execute([$selectedDate]);
$allRows = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($allRows as $r) $grouped[$r['category']][] = $r;

// Category totals & grand total
$catTotals  = [];
$grandTotal = 0;
foreach ($CATEGORIES as $cat) {
    $t = array_sum(array_column($grouped[$cat] ?? [], 'total_amount'));
    $catTotals[$cat] = $t;
    $grandTotal += $t;
}

// Item counts per day-of-month, for the "Duplicate to Other Days" modal
// (lets us warn when a target day already has data before overwriting it).
$dayCountStmt = $pdo->prepare("SELECT DAY(inv_date) as d, COUNT(*) as c FROM stella_month_end_inv WHERE store_name='Stella' AND inv_year=? AND inv_month=? GROUP BY DAY(inv_date)");
$dayCountStmt->execute([$fYear, $fMonth]);
$dayCounts = array_column($dayCountStmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'd');

$pageTitle  = 'Month End Inv.';
$activePage = 'stella_month_end_inv';
$bodyClass  = 'sp-stella_month_end_inv';
include '../layout.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<?php
// Build unit options HTML
$unitOpts = '';
foreach ($UNITS as $u) $unitOpts .= "<option value=\"$u\">$u</option>";
?>

<!-- Page Header -->
<div class="inv-header">
  <div>
    <div class="eyebrow">Stella Branch · Inventory</div>
    <div class="title">Month End Inventory</div>
    <div class="subtitle">DATE: <?= $displayDate ?> &nbsp;·&nbsp; Total auto-calculates from Supplier Cost × End Inv. Num</div>
  </div>
  <span style="background:rgba(255,255,255,.1);color:#fff;padding:5px 14px;
               border-radius:20px;font-family:var(--font-m);font-size:.65rem;font-weight:600;align-self:flex-start">
    📌 Stella
  </span>
</div>

<!-- Controls -->
<div class="inv-controls">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <select name="month" class="form-control" style="max-width:140px" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=$fMonth==$m?'selected':''?>><?= $months[$m-1] ?></option>
      <?php endfor; ?>
    </select>
    <select name="day" class="form-control" style="max-width:80px" onchange="this.form.submit()">
      <?php for($d=1;$d<=$daysInMonth;$d++): ?>
      <option value="<?=$d?>" <?=$fDay==$d?'selected':''?>><?= str_pad($d,2,'0',STR_PAD_LEFT) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-control" style="max-width:100px" onchange="this.form.submit()">
      <?php for($y=date('Y')-5;$y<=date('Y')+10;$y++): ?>
      <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <a href="stella_month_end_inv.php?export_csv=1&month=<?=$fMonth?>&day=<?=$fDay?>&year=<?=$fYear?>" class="btn btn-ghost">⬇ Download Excel</a>
    <button type="button" class="btn btn-ghost" onclick="openDuplicateModal()"
            <?= empty($allRows) ? 'disabled title="No items on this date yet to duplicate"' : '' ?>>
      📋 Duplicate to Other Days
    </button>
    <button type="button" class="btn btn-ghost" onclick="openImportModal()">⬆ Import Excel</button>
    <button type="button" class="btn btn-primary" onclick="addSection()">+ Add Section</button>
  </form>

  <div style="position:relative;min-width:260px;flex:1;max-width:360px">
    <input type="text" id="itemSearchInput" class="form-control" style="width:100%;padding-left:30px"
           placeholder="Search item description…" oninput="filterByDesc(this.value)" autocomplete="off">
    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--subtext2);font-size:.75rem;pointer-events:none">🔍</span>
    <button type="button" id="clearSearchBtn" onclick="clearSearch()"
            style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:var(--subtext2);cursor:pointer;font-size:.85rem;padding:2px 4px">✕</button>
  </div>
</div>
<div id="searchNoResults" style="display:none;font-family:var(--font-m);font-size:.72rem;color:var(--subtext2);margin:-10px 0 16px 2px">
  No items match “<span id="searchNoResultsTerm"></span>”.
</div>

<!-- KPI strip — all categories + grand total, click to filter -->
<div class="inv-kpi" id="kpiStrip" style="grid-template-columns: repeat(<?= count($CATEGORIES)+1 ?>, 1fr)">
  <?php foreach ($CATEGORIES as $ci => $cat):
    $val = $catTotals[$cat] ?? 0;
    $c = $CAT_COLORS[$ci % count($CAT_COLORS)];
  ?>
  <div class="inv-kpi-card" onclick="filterCategory(<?= $ci ?>)"
       data-cat-idx="<?= $ci ?>"
       style="border-top:2px solid <?= $c ?>">
    <div class="inv-kpi-label"><?= htmlspecialchars($cat) ?></div>
    <div class="inv-kpi-val" id="kpi_cat_<?= $ci ?>">
      <?= $val > 0 ? number_format($val,2) : '<span style="color:var(--muted)">—</span>' ?>
    </div>
    <div class="inv-kpi-hint">Click to filter</div>
  </div>
  <?php endforeach; ?>
  <div class="inv-kpi-card grand-card" onclick="filterCategory(null)">
    <div class="inv-kpi-label">Grand Total</div>
    <div class="inv-kpi-val grand" id="kpi_grand_total"><?= $grandTotal > 0 ? number_format($grandTotal,2) : '—' ?></div>
    <div class="inv-kpi-hint">Click to show all</div>
  </div>
</div>

<!-- Sections per category -->
<?php foreach ($CATEGORIES as $catIdx => $cat):
  $catRows = $grouped[$cat] ?? [];
  $catTotal = $catTotals[$cat] ?? 0;
  $catColor = $CAT_COLORS[$catIdx % count($CAT_COLORS)];
?>
<div class="inv-section" id="section_<?= $catIdx ?>" data-cat="<?= htmlspecialchars($cat) ?>">

  <!-- Category header -->
  <div class="inv-cat-head" style="background:<?= $catColor ?>">
    <span><?= htmlspecialchars($cat) ?></span>
    <div style="display:flex;align-items:center;gap:10px">
      <span class="inv-cat-total-badge" id="cat_total_badge_<?= $catIdx ?>">
        Total: <?= $catTotal > 0 ? number_format($catTotal,2) : '—' ?>
      </span>
      <button class="btn-inv-del-section" onclick="removeSection(<?= $catIdx ?>,'<?= addslashes($cat) ?>')" title="Remove this section (only if empty)">✕</button>
    </div>
  </div>

  <!-- Column headers -->
  <div class="inv-col-head">
    <div>Item Descriptions</div>
    <div>Unit</div>
    <div>Supplier Cost</div>
    <div>End. Inv. Num</div>
    <div>Total Amount</div>
    <div>Action</div>
  </div>

  <!-- Rows -->
  <div id="tbody_<?= $catIdx ?>">
    <?php if ($catRows): ?>
      <?php foreach ($catRows as $ri => $r): ?>
      <div class="inv-row" data-id="<?= $r['id'] ?>" data-cat="<?= $catIdx ?>">
        <div class="inv-cell">
          <input type="text" class="inv-input desc-input"
                 value="<?= htmlspecialchars($r['item_desc']) ?>"
                 placeholder="Item description…"
                 oninput="markChanged(this);checkDuplicate(this)">
        </div>
        <div class="inv-cell">
          <select class="inv-unit-select" onchange="markChanged(this)">
            <?php foreach($UNITS as $u): ?>
            <option value="<?=$u?>" <?= $r['unit']===$u?'selected':'' ?>><?=$u?></option>
            <?php endforeach; ?>
            <?php if ($r['unit'] !== '' && !in_array($r['unit'], $UNITS, true)): ?><option value="<?= htmlspecialchars($r['unit']) ?>" selected><?= htmlspecialchars($r['unit']) ?></option><?php endif; ?>
          </select>
        </div>
        <div class="inv-cell">
          <input type="number" step="0.01" class="inv-input num-input"
                 value="<?= ((float)$r['supplier_cost'] == 0) ? '' : $r['supplier_cost'] ?>" placeholder="0.00"
                 oninput="calcTotal(this);markChanged(this)">
        </div>
        <div class="inv-cell">
          <input type="number" step="0.0001" class="inv-input num-input"
                 value="<?= ((float)$r['end_inv_num'] == 0) ? '' : $r['end_inv_num'] ?>" placeholder="0.0000"
                 oninput="calcTotal(this);markChanged(this)">
        </div>
        <div class="inv-cell">
          <input type="number" step="0.01" class="inv-input total-input"
                 value="<?= ((float)$r['total_amount'] == 0) ? '' : $r['total_amount'] ?>" placeholder="0.00" readonly tabindex="-1">
        </div>
        <div class="inv-cell inv-action-cell">
          <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
            <div style="display:flex;gap:3px">
              <button class="btn-inv-save" onclick="saveRow(this,'<?= htmlspecialchars($cat) ?>',<?= $catIdx ?>)">Save</button>
              <button class="btn-inv-del"  onclick="deleteRow(this,<?= $catIdx ?>)">✕</button>
            </div>
            <div class="row-status"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="inv-row empty-row-<?= $catIdx ?>" style="padding:14px 14px;font-family:var(--font-m);font-size:.72rem;color:var(--muted);text-align:center;display:block">
        No items yet — click Add Row
      </div>
    <?php endif; ?>
  </div>

  <!-- Add row -->
  <div style="padding:4px 8px">
    <button class="btn-add-row" onclick="addRow(<?= $catIdx ?>,'<?= htmlspecialchars($cat) ?>')">
      + Add Row
    </button>
  </div>

  <!-- Category total row -->
  <div class="inv-total-row">
    <div>Total — <?= htmlspecialchars($cat) ?></div>
    <div></div><div></div><div></div>
    <div class="total-val" id="cat_total_<?= $catIdx ?>"><?= $catTotal > 0 ? number_format($catTotal,2) : '—' ?></div>
    <div></div>
  </div>

</div>
<?php endforeach; ?>

<?php if (empty($CATEGORIES)): ?>
  <div class="inv-section-empty-note">
    No sections yet — click <strong>+ Add Section</strong> above to create your first category (e.g. MEAT, BEVERAGE, DRY GOODS).
  </div>
<?php endif; ?>

<!-- Grand Total -->
<div class="inv-grand-total">
  <div class="inv-grand-label">🏷 Grand Total — All Categories</div>
  <div class="inv-grand-val" id="grand_total_display"><?= $grandTotal > 0 ? number_format($grandTotal,2) : '0.00' ?></div>
</div>

  </div></div>

<!-- Add Section Modal -->
<div class="modal-overlay" id="addSectionModal">
  <div class="modal-box">
    <div class="modal-header">New Section</div>
    <div class="modal-body">
      Name this new section (e.g. MEAT, BEVERAGE, DRY GOODS):
      <input type="text" class="modal-input" id="addSectionInput" placeholder="SECTION NAME" maxlength="100">
      <div class="modal-error" id="addSectionError"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal('addSectionModal')">Cancel</button>
      <button class="modal-btn modal-btn-primary" onclick="confirmAddSection()">Create Section</button>
    </div>
  </div>
</div>

<!-- Duplicate-to-Other-Days Modal -->
<div class="modal-overlay" id="duplicateModal">
  <div class="modal-box wide">
    <div class="modal-header">📋 Duplicate <?= htmlspecialchars($displayDate) ?> to Other Days</div>
    <div class="modal-body">
      Copy all <strong><?= count($allRows) ?> item(s)</strong> from this date into any day(s) you pick below —
      including next month or any other month/year. You'll only need to adjust the counts for each day afterward.

      <div class="dup-month-nav">
        <button type="button" class="dup-nav-btn" onclick="dupGoToMonth(-1)" title="Previous month">◀</button>
        <select id="dupBrowseMonth" class="dup-select" style="margin:0" onchange="dupBrowseSelectChange()"></select>
        <select id="dupBrowseYear" class="dup-select" style="margin:0;max-width:90px" onchange="dupBrowseSelectChange()"></select>
        <button type="button" class="dup-nav-btn" onclick="dupGoToMonth(1)" title="Next month">▶</button>
        <button type="button" class="dup-nav-btn dup-nav-today" onclick="dupBrowseTo(INV_YEAR, INV_MONTH)">This Month</button>
      </div>

      <div class="dup-legend" style="margin-top:10px">
        <span><span class="dot"></span> already has items</span>
        <span style="opacity:.6">crossed out = current date</span>
      </div>
      <div class="dup-quicklinks">
        <a onclick="dupSelectAll()">Select all remaining days (this view)</a>
        <a onclick="dupClearAll()">Clear all selections</a>
      </div>
      <div class="dup-day-grid" id="dupDayGrid"></div>
      <div id="dupSelectedNote" style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext);margin-top:2px"></div>

      <div class="dup-option-row">
        <input type="checkbox" id="dupCopyQty" style="margin-top:3px">
        <label for="dupCopyQty">
          Also copy quantities &amp; totals
          <span class="hint">Leave unchecked to copy just the item list (description, unit, cost) with blank counts — best for a fresh daily tally.</span>
        </label>
      </div>

      <div class="dup-option-row" style="border-top:none;padding-top:0;flex-direction:column;align-items:stretch">
        <label for="dupMode">If a picked day already has items:</label>
        <select id="dupMode" class="dup-select">
          <option value="skip">Skip that day (safest)</option>
          <option value="append">Add on top (may create duplicates)</option>
          <option value="replace">Replace its items first</option>
        </select>
      </div>

      <div id="dupSummary"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal('duplicateModal')">Cancel</button>
      <button class="modal-btn modal-btn-primary" id="dupConfirmBtn" onclick="confirmDuplicate()">Duplicate</button>
    </div>
  </div>
</div>

<!-- Import-from-Excel Modal -->
<div class="modal-overlay" id="importModal">
  <div class="modal-box wide">
    <div class="modal-header">⬆ Import from Excel</div>
    <div class="modal-body">
      Choose your inventory workbook (.xlsx). Pick the sheet and the date it belongs to — every item (description, unit, cost, end. inv. num) is brought in and the totals are computed for you.
      <div class="imp-field"><label>Excel file</label><input type="file" id="impFile" accept=".xlsx" class="imp-file"></div>
      <div id="impStep2" class="imp-hidden">
        <div class="imp-field"><label>Sheet</label><select id="impSheet" class="dup-select" onchange="impSheetChanged()"></select></div>
        <div class="imp-field"><label>Import into date</label><input type="date" id="impDate" class="modal-input" onchange="impPreview()"></div>
        <div class="imp-field"><label>Section for the main list</label>
          <select id="impSection" class="dup-select" onchange="impSectionChanged()">
            <option value="__new__" selected>➕ New section…</option>
            <?php foreach ($CATEGORIES as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
          </select>
          <input type="text" id="impSectionNew" class="modal-input" placeholder="NEW SECTION NAME" maxlength="100" value="INVENTORY" oninput="impPreview()">
          <span class="imp-hint">A list with its own title in the sheet (e.g. FRUITS&amp;VEGETABLES) goes to a section of that name, created if missing.</span>
        </div>
        <div class="imp-field"><label>If that date already has items</label>
          <select id="impMode" class="dup-select"><option value="replace">Replace them with the Excel items</option><option value="append">Add the Excel items on top</option></select>
        </div>
        <div id="impPreview" class="imp-preview"></div>
      </div>
      <div class="modal-error" id="impError"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal('importModal')">Cancel</button>
      <button class="modal-btn modal-btn-primary" id="impGo" onclick="impRun()" disabled>Import</button>
    </div>
  </div>
</div>

<!-- Generic Confirm / Danger Modal (reused for remove-section flows) -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-header" id="confirmModalHeader">Confirm</div>
    <div class="modal-body" id="confirmModalMsg"></div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal('confirmModal')">Cancel</button>
      <button class="modal-btn" id="confirmModalBtn"></button>
    </div>
  </div>
</div>

<script>
const INV_MONTH = <?= $fMonth ?>;
const INV_YEAR  = <?= $fYear ?>;
const INV_DAY   = <?= $fDay ?>;
const INV_DATE  = <?= json_encode($selectedDate) ?>;
const DAYS_IN_MONTH = <?= $daysInMonth ?>;
const DAY_COUNTS    = <?= json_encode($dayCounts, JSON_FORCE_OBJECT) ?>; // day-of-month => item count, for INV_YEAR/INV_MONTH
const MONTH_NAMES   = <?= json_encode($months) ?>;
const UNITS     = <?= json_encode($UNITS) ?>;

// Build unit select options string
const UNIT_OPTS = UNITS.map(u => `<option value="${u}">${u}</option>`).join('');

function gv(el) { return parseFloat(el?.value) || 0; }

function calcTotal(el) {
  const row  = el.closest('.inv-row');
  const inputs = row.querySelectorAll('.num-input');
  const cost = gv(inputs[0]);
  const qty  = gv(inputs[1]);
  const tot  = Math.round(cost * qty * 100) / 100;
  row.querySelector('.total-input').value = tot.toFixed(2);
  refreshCatTotal(parseInt(row.dataset.cat));
}

function markChanged(el) {
  // triggers recalc on non-numeric fields
  refreshCatTotal(parseInt(el.closest('.inv-row').dataset.cat));
}

function refreshCatTotal(catIdx) {
  let sum = 0;
  document.querySelectorAll(`#tbody_${catIdx} .inv-row[data-id]`).forEach(row => {
    sum += gv(row.querySelector('.total-input'));
  });
  const fmt = n => n === 0 ? '—' : n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  const el = document.getElementById(`cat_total_${catIdx}`);
  if (el) el.textContent = fmt(sum);
  const badge = document.getElementById(`cat_total_badge_${catIdx}`);
  if (badge) badge.textContent = `Total: ${fmt(sum)}`;
  // update KPI card
  const kpiEl = document.getElementById(`kpi_cat_${catIdx}`);
  if (kpiEl) kpiEl.innerHTML = sum === 0 ? '<span style="color:var(--muted)">—</span>' : fmt(sum);
  refreshGrandTotal();
}

let activeFilter = null; // null = show all

// ── Search by item description ───────────────────────────────
function filterByDesc(query) {
  query = query.trim().toLowerCase();

  document.getElementById('clearSearchBtn').style.display = query ? 'block' : 'none';

  // Searching overrides the category KPI filter so results from every
  // category can surface together.
  if (query) {
    activeFilter = null;
    document.querySelectorAll('.inv-kpi-card[data-cat-idx]').forEach(c => c.classList.remove('active-filter'));
  }

  let totalMatches = 0;
  document.querySelectorAll('.inv-section').forEach(section => {
    let anyVisible = false;
    section.querySelectorAll('.inv-row[data-id]').forEach(row => {
      const descInput = row.querySelector('.desc-input');
      const desc = (descInput?.value || '').toLowerCase();
      const match = !query || desc.includes(query);
      row.style.display = match ? '' : 'none';
      if (match) { anyVisible = true; totalMatches++; }
    });
    section.style.display = query ? (anyVisible ? '' : 'none') : '';
  });

  const noRes = document.getElementById('searchNoResults');
  if (noRes) {
    noRes.style.display = (query && totalMatches === 0) ? 'block' : 'none';
    document.getElementById('searchNoResultsTerm').textContent = query;
  }
}

function clearSearch() {
  const input = document.getElementById('itemSearchInput');
  input.value = '';
  filterByDesc('');
  input.focus();
}

// ── Duplicate item-description check (per category) ─────────
function checkDuplicate(el) {
  const row = el.closest('.inv-row');
  const tbody = row.parentElement;
  const val = el.value.trim().toLowerCase();
  let dup = false;
  if (val) {
    tbody.querySelectorAll('.inv-row[data-id] .desc-input').forEach(inp => {
      if (inp !== el && inp.value.trim().toLowerCase() === val) dup = true;
    });
  }
  el.classList.toggle('desc-duplicate', dup);
  el.title = dup ? 'This item already exists in this category' : '';
  let warn = row.querySelector('.desc-dup-warn');
  if (dup) {
    if (!warn) {
      warn = document.createElement('div');
      warn.className = 'desc-dup-warn';
      warn.textContent = '⚠ Already exists in this category';
      el.closest('.inv-cell').appendChild(warn);
    }
  } else if (warn) {
    warn.remove();
  }
}

function filterCategory(catIdx) {
  // Toggle off if clicking the already-active filter
  if (activeFilter === catIdx) catIdx = null;
  activeFilter = catIdx;

  // Update card highlight
  document.querySelectorAll('.inv-kpi-card[data-cat-idx]').forEach(card => {
    card.classList.toggle('active-filter', parseInt(card.dataset.catIdx) === catIdx);
  });

  // Show/hide sections
  document.querySelectorAll('.inv-section').forEach(section => {
    const idx = parseInt(section.id.replace('section_',''));
    section.style.display = (catIdx === null || idx === catIdx) ? '' : 'none';
  });

  // Scroll to visible section
  if (catIdx !== null) {
    const sec = document.getElementById(`section_${catIdx}`);
    if (sec) sec.scrollIntoView({behavior:'smooth', block:'start'});
  }
}

function refreshGrandTotal() {
  let grand = 0;
  document.querySelectorAll('.total-input').forEach(el => { grand += gv(el); });
  const fmt = n => n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  const el = document.getElementById('grand_total_display');
  if (el) el.textContent = fmt(grand);
  const kpi = document.getElementById('kpi_grand_total');
  if (kpi) kpi.textContent = grand > 0 ? fmt(grand) : '—';
}

function addRow(catIdx, catName) {
  // Remove empty placeholder
  const empty = document.querySelector(`.empty-row-${catIdx}`);
  if (empty) empty.remove();

  const tbody = document.getElementById(`tbody_${catIdx}`);
  const div   = document.createElement('div');
  div.className = 'inv-row';
  div.dataset.id  = '0';
  div.dataset.cat = catIdx;

  const sortOrder = tbody.querySelectorAll('.inv-row').length;

  div.innerHTML = `
    <div class="inv-cell">
      <input type="text" class="inv-input desc-input" placeholder="Item description…" oninput="markChanged(this);checkDuplicate(this)">
    </div>
    <div class="inv-cell">
      <select class="inv-unit-select" onchange="markChanged(this)">${UNIT_OPTS}</select>
    </div>
    <div class="inv-cell">
      <input type="number" step="0.01" class="inv-input num-input" value="" placeholder="0.00" oninput="calcTotal(this);markChanged(this)">
    </div>
    <div class="inv-cell">
      <input type="number" step="0.0001" class="inv-input num-input" value="" placeholder="0.0000" oninput="calcTotal(this);markChanged(this)">
    </div>
    <div class="inv-cell">
      <input type="number" step="0.01" class="inv-input total-input" value="" placeholder="0.00" readonly tabindex="-1">
    </div>
    <div class="inv-cell inv-action-cell">
      <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
        <div style="display:flex;gap:3px">
          <button class="btn-inv-save" onclick="saveRow(this,'${catName.replace(/'/g,"\\'")}',${catIdx})">Save</button>
          <button class="btn-inv-del" onclick="deleteRow(this,${catIdx})">✕</button>
        </div>
        <div class="row-status"></div>
      </div>
    </div>`;
  div._sortOrder = sortOrder;
  tbody.appendChild(div);
  div.querySelector('.desc-input').focus();
}

async function saveRow(btn, catName, catIdx) {
  const row    = btn.closest('.inv-row');
  const status = row.querySelector('.row-status');
  const rowId  = parseInt(row.dataset.id) || 0;

  const inputs  = row.querySelectorAll('.num-input');
  const desc    = row.querySelector('.desc-input').value.trim();
  const unit    = row.querySelector('.inv-unit-select').value;
  const suppCost= gv(inputs[0]);
  const endInv  = gv(inputs[1]);
  const total   = gv(row.querySelector('.total-input'));
  const sortOrd = row._sortOrder ?? Array.from(row.parentElement.children).indexOf(row);

  // Warn if another row in this category already has the same description
  const dupExists = Array.from(row.parentElement.querySelectorAll('.inv-row[data-id] .desc-input'))
    .some(inp => inp !== row.querySelector('.desc-input') && inp.value.trim().toLowerCase() === desc.toLowerCase() && desc !== '');
  if (dupExists && !confirm(`"${desc}" already exists in this category. Save it again anyway?`)) {
    return;
  }

  btn.textContent = '…'; btn.disabled = true;

  const fd = new FormData();
  fd.append('ajax_save',    '1');
  fd.append('inv_date',     INV_DATE);
  fd.append('row_id',       rowId);
  fd.append('category',     catName);
  fd.append('sort_order',   sortOrd);
  fd.append('item_desc',    desc);
  fd.append('unit',         unit);
  fd.append('supplier_cost',suppCost);
  fd.append('end_inv_num',  endInv);

  try {
    const res  = await fetch('stella_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      row.dataset.id = json.id;
      // Update total from server
      row.querySelector('.total-input').value = parseFloat(json.total).toFixed(2);
      refreshCatTotal(catIdx);
      status.textContent = '✓';
      status.style.color = 'var(--accent)';
      status.style.display = 'block';
      setTimeout(() => { status.style.display = 'none'; }, 2500);
    } else {
      status.textContent = '❌';
      status.style.color = 'var(--accent2)';
      status.style.display = 'block';
      alert('Error: ' + json.msg);
    }
  } catch(e) {
    alert('Network error');
  }
  btn.textContent = 'Save'; btn.disabled = false;
}

async function deleteRow(btn, catIdx) {
  const row  = btn.closest('.inv-row');
  const id   = parseInt(row.dataset.id) || 0;

  if (id > 0 && !confirm('Delete this item?')) return;

  if (id > 0) {
    const fd = new FormData();
    fd.append('ajax_delete', '1');
    fd.append('row_id', id);
    try { await fetch('stella_month_end_inv.php', {method:'POST', body:fd}); } catch(e) {}
  }

  const tbody = row.parentElement;
  row.remove();
  refreshCatTotal(catIdx);

  // Show empty placeholder if no rows left
  if (!tbody.querySelector('.inv-row[data-id]')) {
    const ph = document.createElement('div');
    ph.className = `inv-row empty-row-${catIdx}`;
    ph.style.cssText = 'padding:14px;font-family:var(--font-m);font-size:.72rem;color:var(--muted);text-align:center;display:block';
    ph.textContent = 'No items yet — click Add Row';
    tbody.appendChild(ph);
  }
}

// ── Modal helpers ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// Close on backdrop click or Escape
document.querySelectorAll('.modal-overlay').forEach(ov => {
  ov.addEventListener('click', e => { if (e.target === ov) closeModal(ov.id); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(ov => closeModal(ov.id));
});

function showConfirm({title, message, btnLabel, btnClass, danger, onConfirm}) {
  const header = document.getElementById('confirmModalHeader');
  header.textContent = title;
  header.className = 'modal-header' + (danger ? ' danger' : '');
  document.getElementById('confirmModalMsg').innerHTML = message;
  const btn = document.getElementById('confirmModalBtn');
  btn.textContent = btnLabel;
  btn.className = 'modal-btn ' + (btnClass || 'modal-btn-primary');
  btn.onclick = async () => { closeModal('confirmModal'); await onConfirm(); };
  openModal('confirmModal');
}

function addSection() {
  const input = document.getElementById('addSectionInput');
  const err   = document.getElementById('addSectionError');
  input.value = '';
  err.style.display = 'none';
  openModal('addSectionModal');
  setTimeout(() => input.focus(), 50);
}

document.getElementById('addSectionInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') confirmAddSection();
});

async function confirmAddSection() {
  const input = document.getElementById('addSectionInput');
  const err   = document.getElementById('addSectionError');
  const trimmed = input.value.trim();
  if (!trimmed) { err.textContent = 'Section name cannot be empty.'; err.style.display = 'block'; return; }

  const fd = new FormData();
  fd.append('ajax_add_category', '1');
  fd.append('name', trimmed);
  try {
    const res  = await fetch('stella_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      location.reload();
    } else {
      err.textContent = json.msg || 'Could not add section.';
      err.style.display = 'block';
    }
  } catch(e) {
    err.textContent = 'Network error';
    err.style.display = 'block';
  }
}

async function removeSection(catIdx, catName) {
  showConfirm({
    title: 'Remove Section',
    message: `Remove the "<strong>${catName}</strong>" section?`,
    btnLabel: 'Remove',
    btnClass: 'modal-btn-primary',
    onConfirm: async () => {
      const tryDelete = async (force) => {
        const fd = new FormData();
        fd.append('ajax_delete_category', '1');
        fd.append('name', catName);
        if (force) fd.append('force', '1');
        const res = await fetch('stella_month_end_inv.php', {method:'POST', body:fd});
        return await res.json();
      };

      let json = await tryDelete(false);
      if (!json.ok && json.needsForce) {
        showConfirm({
          title: 'Section Not Empty',
          danger: true,
          message: `"<strong>${catName}</strong>" still has <strong>${json.itemCount}</strong> saved item(s) across one or more dates.`
                  + `<br><br>Delete the section <strong>and permanently delete all ${json.itemCount} of those items too</strong>?`
                  + `<br><br>This cannot be undone.`,
          btnLabel: 'Delete Section & Items',
          btnClass: 'modal-btn-danger',
          onConfirm: async () => {
            const json2 = await tryDelete(true);
            if (json2.ok) location.reload();
            else alert(json2.msg || 'Could not remove section.');
          }
        });
        return;
      }
      if (json.ok) location.reload();
      else alert(json.msg || 'Could not remove section.');
    }
  });
}  

// ── Duplicate to Other Days ────────────────────────────────
// dupSelectedDates holds full 'YYYY-MM-DD' strings, so selections can
// span any month/year, not just the one currently on screen.
let dupSelectedDates = new Set();
let dupBrowseYear    = INV_YEAR;
let dupBrowseMonth   = INV_MONTH;

function openDuplicateModal() {
  dupSelectedDates = new Set();
  dupBrowseYear  = INV_YEAR;
  dupBrowseMonth = INV_MONTH;
  document.getElementById('dupCopyQty').checked = false;
  document.getElementById('dupMode').value = 'skip';
  document.getElementById('dupSummary').style.display = 'none';
  populateDupMonthYearSelects();
  renderDupGrid(DAY_COUNTS, DAYS_IN_MONTH); // current month's data is already on the page — no fetch needed
  updateDupConfirmBtn();
  openModal('duplicateModal');
}

function populateDupMonthYearSelects() {
  const monthSel = document.getElementById('dupBrowseMonth');
  const yearSel  = document.getElementById('dupBrowseYear');
  if (!monthSel.options.length) {
    MONTH_NAMES.forEach((name, i) => monthSel.appendChild(new Option(name, i + 1)));
  }
  if (!yearSel.options.length) {
    for (let y = INV_YEAR - 5; y <= INV_YEAR + 10; y++) yearSel.appendChild(new Option(y, y));
  }
  monthSel.value = dupBrowseMonth;
  yearSel.value  = dupBrowseYear;
}

function dupGoToMonth(offset) {
  let m = dupBrowseMonth + offset, y = dupBrowseYear;
  if (m < 1)  { m = 12; y--; }
  if (m > 12) { m = 1;  y++; }
  dupBrowseTo(y, m);
}

function dupBrowseSelectChange() {
  const y = parseInt(document.getElementById('dupBrowseYear').value);
  const m = parseInt(document.getElementById('dupBrowseMonth').value);
  dupBrowseTo(y, m);
}

async function dupBrowseTo(year, month) {
  dupBrowseYear = year; dupBrowseMonth = month;
  document.getElementById('dupBrowseYear').value  = year;
  document.getElementById('dupBrowseMonth').value = month;

  // The currently-viewed page's month is already loaded — skip the round trip.
  if (year === INV_YEAR && month === INV_MONTH) {
    renderDupGrid(DAY_COUNTS, DAYS_IN_MONTH);
    return;
  }

  const grid = document.getElementById('dupDayGrid');
  grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:var(--subtext);font-family:var(--font-m);font-size:.72rem">Loading…</div>';

  const fd = new FormData();
  fd.append('ajax_month_counts', '1');
  fd.append('year', year);
  fd.append('month', month);
  try {
    const res  = await fetch('stella_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      renderDupGrid(json.counts, json.days);
    } else {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:#b91c1c;font-family:var(--font-m);font-size:.72rem">Could not load that month.</div>';
    }
  } catch (e) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:14px;color:#b91c1c;font-family:var(--font-m);font-size:.72rem">Network error.</div>';
  }
}

function renderDupGrid(counts, daysInMonth) {
  const grid = document.getElementById('dupDayGrid');
  const isSourceMonth = dupBrowseYear === INV_YEAR && dupBrowseMonth === INV_MONTH;
  let html = '';
  for (let d = 1; d <= daysInMonth; d++) {
    const iso      = `${dupBrowseYear}-${String(dupBrowseMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isSource = isSourceMonth && d === INV_DAY;
    const cnt      = counts[String(d)] || 0;
    const hasData  = cnt > 0 && !isSource;
    const checked  = dupSelectedDates.has(iso);
    const tip      = isSource ? 'This is the source date' : (hasData ? cnt + ' item(s) already here' : 'No items yet');
    html += `<div class="dup-day-chip${hasData ? ' has-data' : ''}${isSource ? ' is-source' : ''}${checked ? ' checked' : ''}"
                  data-iso="${iso}" title="${tip}"
                  onclick="${isSource ? '' : `toggleDupDate('${iso}')`}">${String(d).padStart(2,'0')}</div>`;
  }
  grid.innerHTML = html;
  updateDupConfirmBtn();
}

function toggleDupDate(iso) {
  const chip = document.querySelector(`.dup-day-chip[data-iso="${iso}"]`);
  if (dupSelectedDates.has(iso)) {
    dupSelectedDates.delete(iso);
    chip.classList.remove('checked');
  } else {
    dupSelectedDates.add(iso);
    chip.classList.add('checked');
  }
  updateDupConfirmBtn();
}

function dupSelectAll() {
  document.querySelectorAll('.dup-day-chip:not(.is-source)').forEach(chip => {
    dupSelectedDates.add(chip.dataset.iso);
    chip.classList.add('checked');
  });
  updateDupConfirmBtn();
}

function dupClearAll() {
  dupSelectedDates.clear();
  document.querySelectorAll('.dup-day-chip').forEach(chip => chip.classList.remove('checked'));
  updateDupConfirmBtn();
}

function updateDupConfirmBtn() {
  const btn  = document.getElementById('dupConfirmBtn');
  const note = document.getElementById('dupSelectedNote');
  const n    = dupSelectedDates.size;
  btn.textContent = n ? `Duplicate to ${n} Day${n > 1 ? 's' : ''}` : 'Duplicate';
  btn.disabled = n === 0;
  if (note) note.textContent = n ? `${n} date(s) selected in total (across all months browsed)` : '';
}

async function confirmDuplicate() {
  if (!dupSelectedDates.size) return;

  const targetDates = Array.from(dupSelectedDates).sort();

  const btn = document.getElementById('dupConfirmBtn');
  const origLabel = btn.textContent;
  btn.disabled = true; btn.textContent = 'Duplicating…';

  const fd = new FormData();
  fd.append('ajax_duplicate_day', '1');
  fd.append('src_date', INV_DATE);
  fd.append('target_dates', JSON.stringify(targetDates));
  fd.append('copy_qty', document.getElementById('dupCopyQty').checked ? '1' : '');
  fd.append('mode', document.getElementById('dupMode').value);

  try {
    const res  = await fetch('stella_month_end_inv.php', {method:'POST', body:fd});
    const json = await res.json();
    if (json.ok) {
      const s = json.summary;
      const box = document.getElementById('dupSummary');
      let msg = `✓ Copied ${json.itemCount} item(s) into ${s.copied.length} day(s).`;
      if (s.skipped.length) msg += ` Skipped ${s.skipped.length} day(s) that already had items.`;
      box.textContent = msg;
      box.style.display = 'block';
      btn.textContent = 'Done ✓';
      setTimeout(() => { closeModal('duplicateModal'); location.reload(); }, 1200);
    } else {
      alert('Error: ' + (json.msg || 'Could not duplicate.'));
      btn.disabled = false; btn.textContent = origLabel;
    }
  } catch (e) {
    alert('Network error while duplicating.');
    btn.disabled = false; btn.textContent = origLabel;
  }
}


// ── Import from Excel ─────────────────────────────────────
let impSheets = [];
function impMsg(t) { const e = document.getElementById('impError'); e.textContent = t || ''; e.style.display = t ? 'block' : 'none'; }
function openImportModal() {
  document.getElementById('impFile').value = '';
  document.getElementById('impStep2').classList.add('imp-hidden');
  document.getElementById('impGo').disabled = true;
  impMsg('');
  openModal('importModal');
}
function impForm(extra) {
  const f = document.getElementById('impFile').files[0];
  const fd = new FormData();
  fd.append('xlsx', f);
  for (const k in extra) fd.append(k, extra[k]);
  return fd;
}
document.getElementById('impFile').addEventListener('change', async function () {
  impMsg(''); document.getElementById('impGo').disabled = true;
  document.getElementById('impStep2').classList.add('imp-hidden');
  if (!this.files[0]) return;
  impMsg('Reading the workbook…');
  try {
    const res = await fetch('stella_month_end_inv.php', {method: 'POST', body: impForm({ajax_import_inspect: '1'})});
    const json = await res.json();
    if (!json.ok) { impMsg(json.msg || 'Could not read the file.'); return; }
    impSheets = json.sheets;
    const sel = document.getElementById('impSheet'); sel.innerHTML = '';
    let pick = 0, best = -1;
    impSheets.forEach((s, i) => {
      sel.appendChild(new Option(s.name + (s.date ? '  →  ' + s.date : ''), s.name));
      // prefer a sheet whose name carries the year (e.g. "OCTOBER 31 2026"); the first sheet of the workbook is the newest
      let score = 0;
      if (s.explicit && s.date === INV_DATE) score = 3; else if (s.explicit && s.date.slice(0, 7) === INV_DATE.slice(0, 7)) score = 2;
      if (score > best) { best = score; pick = i; }
    });
    sel.selectedIndex = pick;
    impMsg('');
    document.getElementById('impStep2').classList.remove('imp-hidden');
    impSheetChanged();
  } catch (e) { impMsg('Could not upload the file. Check your connection and the file size.'); }
});
function impSheetChanged() {
  const name = document.getElementById('impSheet').value;
  const s = impSheets.find(x => x.name === name);
  document.getElementById('impDate').value = (s && s.date) ? s.date : INV_DATE;
  impPreview();
}
function impSectionChanged() {
  document.getElementById('impSectionNew').classList.toggle('imp-hidden', document.getElementById('impSection').value !== '__new__');
  impPreview();
}
function impSectionName() {
  const v = document.getElementById('impSection').value;
  return v === '__new__' ? document.getElementById('impSectionNew').value.trim() : v;
}
const impMoney = n => Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
let impSeq = 0;
async function impPreview() {
  const box = document.getElementById('impPreview'), go = document.getElementById('impGo');
  const date = document.getElementById('impDate').value;
  go.disabled = true; impMsg('');
  if (!date) { box.textContent = 'Pick a date.'; return; }
  const my = ++impSeq;
  box.textContent = 'Checking the sheet…';
  try {
    const res = await fetch('stella_month_end_inv.php', {method: 'POST', body: impForm({
      ajax_import_run: '1', dry: '1', sheet: document.getElementById('impSheet').value, inv_date: date,
      section: impSectionName(), mode: document.getElementById('impMode').value})});
    const j = await res.json();
    if (my !== impSeq) return;
    if (!j.ok) { box.textContent = ''; impMsg(j.msg || 'Could not read that sheet.'); return; }
    const secs = Object.keys(j.sections).map(k => k + ' (' + j.sections[k] + ')').join(', ');
    box.innerHTML = '<b>' + j.items + ' items</b> found (' + j.withQty + ' with a count) · computed total <b>' + impMoney(j.total) + '</b>'
      + (j.excelTotal ? ' · Excel TOTAL ' + impMoney(j.excelTotal) : '') + '<br>Sections: ' + secs
      + (j.existing ? '<br><span class="imp-warn">That date already has ' + j.existing + ' item(s).</span>' : '');
    go.disabled = false;
  } catch (e) { if (my === impSeq) { box.textContent = ''; impMsg('Could not read that sheet.'); } }
}
document.getElementById('impMode').addEventListener('change', impPreview);
async function impRun() {
  const go = document.getElementById('impGo'), date = document.getElementById('impDate').value;
  const mode = document.getElementById('impMode').value;
  const existing = /already has (\d+)/.exec(document.getElementById('impPreview').textContent);
  if (existing && mode === 'replace' && !confirm('Replace the ' + existing[1] + ' item(s) already saved on ' + date + '?')) return;
  go.disabled = true; go.textContent = 'Importing…'; impMsg('');
  try {
    const res = await fetch('stella_month_end_inv.php', {method: 'POST', body: impForm({
      ajax_import_run: '1', sheet: document.getElementById('impSheet').value, inv_date: date,
      section: impSectionName(), mode: mode})});
    const j = await res.json();
    if (!j.ok) { impMsg(j.msg || 'Import failed.'); go.disabled = false; go.textContent = 'Import'; return; }
    go.textContent = 'Imported ✓ ' + j.items;
    const d = j.date.split('-');
    setTimeout(() => { location.href = 'stella_month_end_inv.php?year=' + d[0] + '&month=' + parseInt(d[1]) + '&day=' + parseInt(d[2]); }, 700);
  } catch (e) { impMsg('Network error while importing.'); go.disabled = false; go.textContent = 'Import'; }
}

// Initial grand total refresh
document.addEventListener('DOMContentLoaded', refreshGrandTotal);
</script>
<script src="../assets/js/keynav.js"></script>
</body>
</html>