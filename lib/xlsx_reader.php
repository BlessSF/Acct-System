<?php
// ============================================================
//  lib/xlsx_reader.php — tiny read-only .xlsx reader (no Composer needed)
//  Uses only ZipArchive + SimpleXML, which Hostinger PHP has by default.
//  Returns each cell's value (the number Excel last calculated for
//  formulas) and whether the cell has a coloured background — that is
//  how a "classification" row (e.g. FRUITS&VEGETABLES) is told apart
//  from a plain item row.
// ============================================================

function xlsxColToNum(string $letters): int {
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n;
}

function xlsxOpen(string $path): ZipArchive {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('That file is not a valid .xlsx workbook.');
    return $zip;
}

function xlsxXml(ZipArchive $zip, string $name): ?SimpleXMLElement {
    $s = $zip->getFromName($name);
    if ($s === false) return null;
    $x = @simplexml_load_string($s);
    return $x ?: null;
}

/** Sheet list: [ ['name'=>..., 'file'=>'xl/worksheets/sheet1.xml'], ... ] in workbook order. */
function xlsxSheets(ZipArchive $zip): array {
    $wb = xlsxXml($zip, 'xl/workbook.xml');
    if (!$wb) throw new RuntimeException('Could not read the workbook.');
    $rels = [];
    $rx = xlsxXml($zip, 'xl/_rels/workbook.xml.rels');
    if ($rx) foreach ($rx->Relationship as $r) {
        $t = (string)$r['Target'];
        $rels[(string)$r['Id']] = (strpos($t, '/') === 0) ? ltrim($t, '/') : 'xl/' . $t;
    }
    $out = [];
    foreach ($wb->sheets->sheet as $sh) {
        $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        if (isset($rels[$rid])) $out[] = ['name' => (string)$sh['name'], 'file' => $rels[$rid]];
    }
    return $out;
}

function xlsxSharedStrings(ZipArchive $zip): array {
    $ss = xlsxXml($zip, 'xl/sharedStrings.xml');
    $out = [];
    if ($ss) foreach ($ss->si as $si) {
        if (isset($si->t)) { $out[] = (string)$si->t; continue; }
        $t = '';
        foreach ($si->r as $r) $t .= (string)$r->t;
        $out[] = $t;
    }
    return $out;
}

/** style index => true when that cell style has a coloured (non-white) solid fill. */
function xlsxColoredStyles(ZipArchive $zip): array {
    $st = xlsxXml($zip, 'xl/styles.xml');
    if (!$st) return [];
    $fillColored = [];
    if (isset($st->fills->fill)) foreach ($st->fills->fill as $i => $f) {
        $colored = false;
        $pf = $f->patternFill ?? null;
        if ($pf && (string)$pf['patternType'] === 'solid' && isset($pf->fgColor)) {
            $fg = $pf->fgColor;
            if (isset($fg['rgb'])) {
                $rgb = strtoupper((string)$fg['rgb']);
                $colored = !in_array($rgb, ['FFFFFFFF', '00FFFFFF', '00000000', 'FF000000'], true) || false;
                if ($rgb === 'FF000000') $colored = true; // real black fill
                if ($rgb === '00000000') $colored = false; // "no colour" placeholder
            } elseif (isset($fg['theme'])) {
                $colored = ((int)$fg['theme'] !== 0);      // theme 0 = white
            } elseif (isset($fg['indexed'])) {
                $colored = !in_array((int)$fg['indexed'], [64, 9], true);
            }
        }
        $fillColored[] = $colored;
    }
    $map = [];
    if (isset($st->cellXfs->xf)) foreach ($st->cellXfs->xf as $i => $xf) {
        $fid = (int)$xf['fillId'];
        $map[] = $fillColored[$fid] ?? false;
    }
    return $map;
}

/**
 * Read one worksheet → rows[$r][$c] = ['v'=>string|float|null, 'fill'=>bool]  (1-based row/col)
 * Reads at most $maxCol columns / $maxRow rows.
 */
function xlsxReadSheet(ZipArchive $zip, string $file, int $maxCol = 26, int $maxRow = 4000): array {
    $shared = xlsxSharedStrings($zip);
    $colored = xlsxColoredStyles($zip);
    $sx = xlsxXml($zip, $file);
    if (!$sx) throw new RuntimeException('Could not read that sheet.');
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = (int)$row['r'];
        if ($r > $maxRow) break;
        foreach ($row->c as $c) {
            if (!preg_match('/^([A-Z]+)(\d+)$/', (string)$c['r'], $m)) continue;
            $col = xlsxColToNum($m[1]);
            if ($col > $maxCol) continue;
            $t = (string)$c['t'];
            $v = null;
            if ($t === 's')              { $v = $shared[(int)$c->v] ?? ''; }
            elseif ($t === 'inlineStr')  { $v = (string)$c->is->t; }
            elseif ($t === 'str')        { $v = (string)$c->v; }
            elseif ($t === 'b')          { $v = ((string)$c->v) === '1' ? 1.0 : 0.0; }
            elseif ($t === 'e')          { $v = null; }
            elseif (isset($c->v) && (string)$c->v !== '') { $v = (float)$c->v; }
            $fill = !empty($colored[(int)$c['s']]);
            if ($v === null && !$fill) continue;
            $rows[$r][$col] = ['v' => $v, 'fill' => $fill];
        }
    }
    return $rows;
}

/**
 * Month-end inventory layout parser.
 * Finds every "ITEM DESCRIPTION" header cell near the top: each one starts a block of
 * 5 columns  [item | unit | final cost | end.inv.num | total amount].
 * Inside a block, a row that only has a name and a coloured background is a CLASSIFICATION
 * header (e.g. FRUITS&VEGETABLES); the items under it belong to it. Items above any
 * classification header have none (category = null → the caller defaults it to Storage).
 *
 * Returns ['items'=>[ ['cat'=>?string,'name'=>,'unit'=>,'cost'=>,'qty'=>,'total'=>,'block'=>int,'row'=>int], ... ],
 *          'skipped'=>[ 'reason' => count ]]
 */
function parseMonthEndSheet(array $rows): array {
    $blocks = [];
    $headerRow = null;
    for ($r = 1; $r <= 8; $r++) {
        if (empty($rows[$r])) continue;
        foreach ($rows[$r] as $c => $cell) {
            if (is_string($cell['v']) && preg_match('/^\s*ITEM\s*D[A-Z]*/i', $cell['v'])) {
                $blocks[$c] = $r;
            }
        }
        if ($blocks) { $headerRow = $r; break; }
    }
    if (!$blocks) throw new RuntimeException('This sheet has no "ITEM DESCRIPTION" column heading, so it does not look like a month-end inventory sheet.');
    ksort($blocks);

    $stopNames = ['OVERALL', 'GRAND TOTAL', 'TOTAL', 'TOTAL:'];
    $num = function ($cell) {
        if (!$cell) return null;
        $v = $cell['v'];
        if (is_float($v)) return $v;
        if (is_string($v) && is_numeric(trim(str_replace(',', '', $v)))) return (float)str_replace(',', '', $v);
        return null;
    };
    $items = []; $skipped = ['no name / total rows' => 0];
    $bi = 0;
    foreach ($blocks as $c0 => $hr) {
        $bi++;
        $cat = null;
        $maxRow = max(array_keys($rows));
        for ($r = $hr + 1; $r <= $maxRow; $r++) {
            $nameCell = $rows[$r][$c0] ?? null;
            $name = ($nameCell && $nameCell['v'] !== null) ? trim((string)(is_float($nameCell['v']) ? rtrim(rtrim(number_format($nameCell['v'], 4, '.', ''), '0'), '.') : $nameCell['v'])) : '';
            if ($name === '') { $skipped['no name / total rows']++; continue; }
            if (in_array(strtoupper($name), $stopNames, true)) { if (strtoupper($name) === 'OVERALL' || strtoupper($name) === 'GRAND TOTAL') break; continue; }

            $unit  = $rows[$r][$c0 + 1]['v'] ?? null;
            $cost  = $num($rows[$r][$c0 + 2] ?? null);
            $qty   = $num($rows[$r][$c0 + 3] ?? null);
            $total = $num($rows[$r][$c0 + 4] ?? null);
            $unit  = is_string($unit) ? trim($unit) : '';

            $bare = ($unit === '' && $cost === null && $qty === null && $total === null);
            if ($bare && !empty($nameCell['fill'])) {          // coloured, nothing else → classification
                $cat = strtoupper(preg_replace('/\s+/', ' ', $name));
                continue;
            }
            $items[] = ['cat' => $cat, 'name' => $name, 'unit' => $unit, 'cost' => $cost, 'qty' => $qty,
                        'total' => $total, 'block' => $bi, 'row' => $r];
        }
    }
    return ['items' => $items, 'skipped' => $skipped];
}

/** Excel unit text → the unit names the page already uses. */
function normalizeInvUnit(string $u): string {
    $u = strtoupper(trim($u));
    if ($u === '') return 'PCS';
    static $alias = ['BOT'=>'BOTTLE','BOTS'=>'BOTTLE','BOTTLES'=>'BOTTLE','KILOS'=>'KILO','KGS'=>'KG','PC'=>'PCS','PIECE'=>'PCS','PIECES'=>'PCS',
                     'PACK'=>'PACKS','PCK'=>'PACKS','PKS'=>'PACKS','PKT'=>'PACKS','CANS'=>'CAN','BOXES'=>'BOX','LTR'=>'LITER','LITERS'=>'LITER','GRAM'=>'GRAMS','GM'=>'GRAMS'];
    return substr($alias[$u] ?? $u, 0, 20);
}

/** Best guess of the month-end date from a sheet name like "OCTOBER 31 2026" / "SEPTEMBER 2026" / "JAN31,2026". */
function guessSheetDate(string $sheetName, int $defaultYear): ?string {
    $months = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
    if (!preg_match('/^\s*(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\.?\s*(\d{1,2})?\D*?((?:19|20)\d{2})?\s*$/i', $sheetName, $m)) return null;
    $mo = $months[strtoupper($m[1])];
    $yr = !empty($m[3]) ? (int)$m[3] : $defaultYear;
    $dim = (int)date('t', mktime(0, 0, 0, $mo, 1, $yr));
    $d = (!empty($m[2]) && (int)$m[2] >= 1 && (int)$m[2] <= $dim) ? (int)$m[2] : $dim;
    return sprintf('%04d-%02d-%02d', $yr, $mo, $d);
}