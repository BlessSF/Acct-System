<?php
// ============================================================
//  lib/vat_mode.php — "VAT treatment" setting for Account Titles
// ============================================================
//  Every branch keeps its own Account Title list (<branch>_acc_titles).
//  Each title now has a VAT treatment that decides whether a
//  Disbursement entry carries Input Tax (12% VAT):
//
//    both  VAT or Non-VAT   Follows the supplier: a VAT supplier gets
//                           input tax, a Non-VAT (NV) supplier does not.
//                           This is how every title behaved before.
//    vat   Input tax only   Always carries input tax, whatever the
//                           supplier is flagged as.
//    none  Non-VAT only     Never carries input tax, even when the
//                           supplier is VAT-registered
//                           (e.g. "Purchases - Non-Vat").
//
//  Used by <branch>_disbursement.php (on every save, so the server is
//  always the one that decides) and <branch>_acc_title.php (the setting
//  itself, plus "update existing entries").
//
//      require_once __DIR__ . '/../lib/vat_mode.php';
// ============================================================

const VAT_MODES = ['both', 'vat', 'none'];

const VAT_MODE_LABELS = [
    'both' => 'VAT or Non-VAT',
    'vat'  => 'Input tax only',
    'none' => 'Non-VAT only',
];

/** Anything unknown falls back to the safe default ("follow the supplier"). */
function vatModeClean($mode): string {
    return in_array($mode, VAT_MODES, true) ? $mode : 'both';
}

/**
 * Makes sure <branch>_acc_titles has the vat_mode column.
 * Safe to call on every request: it only does work the first time.
 */
function vatModeEnsureColumn(PDO $pdo, string $titlesTable): void {
    static $done = [];
    if (isset($done[$titlesTable])) return;
    $done[$titlesTable] = true;

    $has = $pdo->query("SHOW COLUMNS FROM `$titlesTable` LIKE 'vat_mode'")->fetch();
    if (!$has) {
        try {
            $pdo->exec("ALTER TABLE `$titlesTable` ADD COLUMN `vat_mode` enum('both','vat','none') NOT NULL DEFAULT 'both'");
            // The column is brand new, so nobody has chosen anything yet. Keep what
            // Commissary already did (and what every branch should do): a Non-Vat
            // purchase never carries input tax.
            $pdo->exec("UPDATE `$titlesTable` SET `vat_mode`='none' WHERE `title`='Purchases - Non-Vat'");
        } catch (Throwable $ignored) { /* another request added it first */ }
    }
    // Brand-new databases: the default titles are inserted after the column exists,
    // so correct the untouched seed row too. Only rows still flagged as the original
    // system seed are touched, so a choice made by a person is never overwritten.
    try {
        $pdo->exec("UPDATE `$titlesTable` SET `vat_mode`='none' WHERE `title`='Purchases - Non-Vat' AND `saved_by`='system-seed' AND `vat_mode`='both'");
    } catch (Throwable $ignored) {}
}

/** [account title => vat_mode] for every title in the branch's list. */
function vatModeMap(PDO $pdo, string $titlesTable): array {
    $map = [];
    try {
        vatModeEnsureColumn($pdo, $titlesTable);
        foreach ($pdo->query("SELECT `title`, `vat_mode` FROM `$titlesTable`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[$r['title']] = vatModeClean($r['vat_mode']);
        }
    } catch (Throwable $e) {
        // If the setting can't be read, every title behaves as it did before (follows the supplier).
    }
    return $map;
}

/** The setting for one account title ('both' if the title isn't in the list). */
function vatModeFor(PDO $pdo, string $titlesTable, string $title): string {
    try {
        vatModeEnsureColumn($pdo, $titlesTable);
        $st = $pdo->prepare("SELECT `vat_mode` FROM `$titlesTable` WHERE `title`=?");
        $st->execute([$title]);
        $m = $st->fetchColumn();
        return $m === false ? 'both' : vatModeClean($m);
    } catch (Throwable $e) {
        return 'both';
    }
}

/**
 * Input tax and net-of-VAT for one entry. Same 12% formula the Disbursement
 * screen has always used (gross / 1.12 * 0.12).
 *
 * @return array{0: float, 1: float} [input_tax, net_of_vat]
 */
function vatModeCompute(string $mode, string $vatStatus, float $gross): array {
    $carriesTax = $mode === 'vat' || ($mode === 'both' && $vatStatus === 'VAT');
    if (!$carriesTax) return [0.0, round($gross, 2)];
    $tax = $gross / 1.12 * 0.12;
    return [round($tax, 2), round($gross - $tax, 2)];
}

/**
 * Entries already saved under this title whose Input Tax / Net of VAT would
 * change under $mode.  [id => [input_tax, net_of_vat]]
 */
function vatModeChanges(PDO $pdo, string $disbTable, string $title, string $mode): array {
    $st = $pdo->prepare("SELECT `id`, `vat_status`, `gross`, `input_tax`, `net_of_vat` FROM `$disbTable` WHERE `account_title`=?");
    $st->execute([$title]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        [$tax, $net] = vatModeCompute($mode, (string)$r['vat_status'], (float)$r['gross']);
        if (abs($tax - (float)$r['input_tax']) > 0.004 || abs($net - (float)$r['net_of_vat']) > 0.004) {
            $out[(int)$r['id']] = [$tax, $net];
        }
    }
    return $out;
}

/** Rewrites those entries (only the two computed columns). Returns how many were changed. */
function vatModeRecalc(PDO $pdo, string $disbTable, string $title, string $mode): int {
    $changes = vatModeChanges($pdo, $disbTable, $title, $mode);
    if (!$changes) return 0;
    $up = $pdo->prepare("UPDATE `$disbTable` SET `input_tax`=?, `net_of_vat`=? WHERE `id`=?");
    $pdo->beginTransaction();
    try {
        foreach ($changes as $id => [$tax, $net]) $up->execute([$tax, $net, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return count($changes);
}