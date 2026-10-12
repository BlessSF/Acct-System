<?php
// ============================================================
//  lib/expense_sync.php — Disbursement → Expenses auto-posting
// ============================================================
//  Each Account Title (<branch>_acc_titles) has an "Expenses column"
//  setting. Every Disbursement entry under that title is posted to the
//  Expenses ledger (<branch>_expenses) as ONE linked row:
//
//    - the GROSS amount goes into the mapped Expenses column
//      (never the net-of-VAT amount);
//    - the VAT breakdown columns carry what the Disbursement entry has
//      (Amount w/ VAT = gross, VAT = input tax, Amount w/out VAT = net);
//    - TOTAL AMOUNT = gross, so the CHECK column is 0.00.
//
//  The Expenses page is view-only. Add / edit / delete happens in the
//  Disbursement and flows through here, so the two can never drift apart.
//
//  Titles with no Expenses column are simply not posted (the Disbursement
//  page flags them).  Currently enabled for the H branch only.
// ============================================================

/** Expenses columns a title may be mapped to: column => label shown in the setting. */
const EXP_SYNC_COLUMNS = [
    'purchases'            => 'Purchases',
    'salaries'             => 'Salaries',
    'rent'                 => 'Rent',
    'medicine'             => 'Medicine',
    'lpg'                  => 'LPG',
    'repairs_maintenance'  => 'Repairs & Maintenance',
    'fuel_trans'           => 'Fuel & Trans',
    'communication'        => 'Communication',
    'transportation'       => 'Transportation / Delivery fee',
    'light'                => 'Light',
    'drinking_water'       => 'Drinking Water',
    'water'                => 'Water',
    'sss_phic_hdmf'        => 'SSS / PHIC / HDMF',
    'taxes_licences'       => 'Taxes & Licences',
    'office_supplies'      => 'Office Supplies',
    'kitchen_supplies'     => 'Kitchen Supplies',
    'bio_pest_control'     => 'Bio Aug. / Pest Control',
    'representation'       => 'Representation',
    'miscellaneous'        => 'Miscellaneous',
    'sir_budoy_nikki'      => "Sir Budoy / M'Nikki",
    'staff_meal'           => 'Staff Meal',
    'pest_control_bio_aug' => 'Pest Control, Bio Aug',
    'commission_fees'      => 'Commission Fees',
    'exhaust_cleaning'     => 'Exhaust Cleaning',
    'bank_fees'            => 'Bank Fees',
    'admin_salary_shares'  => 'Admin Salary Shares',
    'marketing'            => 'Marketing',
    'sales_discounts'      => 'Sales Discounts',
    'pdc'                  => 'PDC',
    'ca'                   => 'CA',
    'withdrawal'           => 'Withdrawal',
    'depreciation_expense' => 'Depreciation Expense',
];

/** Starting suggestion for the H branch titles (can be changed on the Account Titles page). */
const EXP_SYNC_H_DEFAULTS = [
    'Purchases - Non-Vat'        => 'purchases',
    'Purchases - Vatable'        => 'purchases',
    'Kitchen Supplies'           => 'kitchen_supplies',
    'Solane'                     => 'lpg',
    'Miscellaneous'              => 'miscellaneous',
    'Rent'                       => 'rent',
    'Office Supplies'            => 'office_supplies',
    'Pest Control'               => 'bio_pest_control',
    'Advertisement'              => 'marketing',
    'Bio Augmentation'           => 'pest_control_bio_aug',
    'Fare & Transportation'      => 'transportation',
    'Fuel & Oil'                 => 'fuel_trans',
    'Repairs and Maintenance'    => 'repairs_maintenance',
    'Salaries and Wages'         => 'salaries',
    'Representation Expense'     => 'representation',
    'Meals'                      => 'staff_meal',
    'Taxes and Licenses'         => 'taxes_licences',
    'SSS, PHIC, HDMF Contribution' => 'sss_phic_hdmf',
    'Telephone, Light & Water'   => 'light',
    'Delivery Expense'           => 'transportation',
    'Commission Expense'         => 'commission_fees',
    "M'Nikki"                    => 'sir_budoy_nikki',
    'c/o Nikki'                  => 'sir_budoy_nikki',
    'Others'                     => 'miscellaneous',
];

function expSyncColClean($c): string {
    $c = (string)$c;
    return isset(EXP_SYNC_COLUMNS[$c]) ? $c : '';
}

/**
 * Make sure the schema is ready (idempotent, cheap):
 *   <titles>.expense_col   NULL = never set (gets the default), '' = deliberately not posted
 *   <expenses>.disbursement_id   link to the Disbursement entry
 */
function expSyncEnsure(PDO $pdo, string $titlesTable, string $expTable, array $defaults = []): void {
    $has = fn($t, $c) => (bool)$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();
    if (!$has($titlesTable, 'expense_col')) {
        try { $pdo->exec("ALTER TABLE `$titlesTable` ADD COLUMN `expense_col` varchar(40) NULL DEFAULT NULL"); } catch (Throwable $e) {}
    }
    if (!$has($expTable, 'disbursement_id')) {
        try { $pdo->exec("ALTER TABLE `$expTable` ADD COLUMN `disbursement_id` int(11) NULL DEFAULT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE `$expTable` ADD UNIQUE KEY `uniq_disbursement` (`disbursement_id`)"); } catch (Throwable $e) {}
    }
    if ($defaults) {
        $st = $pdo->prepare("UPDATE `$titlesTable` SET `expense_col`=? WHERE `title`=? AND `expense_col` IS NULL");
        foreach ($defaults as $title => $col) $st->execute([$col, $title]);
    }
}

/** title => expense column ('' when not posted). */
function expSyncTitleMap(PDO $pdo, string $titlesTable): array {
    $map = [];
    foreach ($pdo->query("SELECT `title`, `expense_col` FROM `$titlesTable`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['title']] = expSyncColClean($r['expense_col']);
    }
    return $map;
}

/** Post (or re-post / remove) the Expenses row for one Disbursement entry. Returns 'synced' | 'removed' | 'unmapped'. */
function expSyncOne(PDO $pdo, string $disbTable, string $expTable, string $titlesTable, int $disbId, ?array $map = null): string {
    $st = $pdo->prepare("SELECT * FROM `$disbTable` WHERE id=?");
    $st->execute([$disbId]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
    if (!$d) { // entry is gone
        $pdo->prepare("DELETE FROM `$expTable` WHERE `disbursement_id`=?")->execute([$disbId]);
        return 'removed';
    }
    $map = $map ?? expSyncTitleMap($pdo, $titlesTable);
    $col = $map[$d['account_title']] ?? '';
    if ($col === '' || empty($d['entry_date'])) {
        $pdo->prepare("DELETE FROM `$expTable` WHERE `disbursement_id`=?")->execute([$disbId]);
        return 'unmapped';
    }

    $gross = round((float)$d['gross'], 2);
    $row = [
        'expense_date'    => $d['entry_date'],
        'voucher_no'      => '',
        'tin'             => $d['tin'],
        'company_name'    => $d['company_name'],
        'address'         => $d['address'],
        'particulars'     => $d['particular'],
        'document_type'   => $d['account_title'],
        'document_no'     => $d['invoice_no'],
        'amount_w_vat'    => $gross,
        'vat'             => round((float)$d['input_tax'], 2),
        'amount_wo_vat'   => round((float)$d['net_of_vat'], 2),
        'non_vat'         => 0,
        'total_amount'    => $gross,
        'row_total'       => $gross,
        'saved_by'        => $d['saved_by'],
    ];
    foreach (array_keys(EXP_SYNC_COLUMNS) as $c) $row[$c] = ($c === $col) ? $gross : 0;

    $ex = $pdo->prepare("SELECT id FROM `$expTable` WHERE `disbursement_id`=?");
    $ex->execute([$disbId]);
    $existing = $ex->fetchColumn();
    if ($existing) {
        $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($row)));
        $pdo->prepare("UPDATE `$expTable` SET $sets WHERE id=?")->execute([...array_values($row), $existing]);
    } else {
        $row['disbursement_id'] = $disbId;
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($row)));
        $pdo->prepare("INSERT INTO `$expTable` ($cols) VALUES (" . implode(',', array_fill(0, count($row), '?')) . ")")
            ->execute(array_values($row));
    }
    return 'synced';
}

/**
 * Re-post many entries at once. $onlyTitle limits it to one account title,
 * $from/$to (Y-m-d, $to exclusive) to a date range. Returns counts.
 */
function expSyncMany(PDO $pdo, string $disbTable, string $expTable, string $titlesTable,
                     ?string $onlyTitle = null, ?string $from = null, ?string $to = null): array {
    $where = []; $args = [];
    if ($onlyTitle !== null) { $where[] = 'account_title=?'; $args[] = $onlyTitle; }
    if ($from)               { $where[] = 'entry_date>=?';   $args[] = $from; }
    if ($to)                 { $where[] = 'entry_date<?';    $args[] = $to; }
    $st = $pdo->prepare("SELECT id FROM `$disbTable`" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
    $st->execute($args);
    $map = expSyncTitleMap($pdo, $titlesTable);
    $n = ['synced' => 0, 'unmapped' => 0, 'removed' => 0];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $n[expSyncOne($pdo, $disbTable, $expTable, $titlesTable, (int)$id, $map)]++;
    }
    return $n;
}

/** Remove the Expenses row that belongs to a deleted Disbursement entry. */
function expSyncDelete(PDO $pdo, string $expTable, int $disbId): void {
    $pdo->prepare("DELETE FROM `$expTable` WHERE `disbursement_id`=?")->execute([$disbId]);
}

/** The H Expenses table (same definition as h/h_expenses.php) so Disbursement can post to it before the Expenses page was ever opened. */
function expSyncCreateHExpenses(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `h_expenses` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `expense_date`          date NOT NULL,
    `voucher_no`            varchar(100) DEFAULT '',
    `tin`                   varchar(100) DEFAULT '',
    `company_name`          varchar(255) DEFAULT '',
    `address`               varchar(255) DEFAULT '',
    `particulars`           varchar(255) DEFAULT '',
    `document_type`         varchar(100) DEFAULT '',
    `document_no`           varchar(100) DEFAULT '',
    `amount_w_vat`          decimal(12,2) DEFAULT 0.00,
    `vat`                   decimal(12,2) DEFAULT 0.00,
    `amount_wo_vat`         decimal(12,2) DEFAULT 0.00,
    `non_vat`               decimal(12,2) DEFAULT 0.00,
    `total_amount`          decimal(12,2) DEFAULT 0.00,
    `purchases`             decimal(12,2) DEFAULT 0.00,
    `salaries`              decimal(12,2) DEFAULT 0.00,
    `rent`                  decimal(12,2) DEFAULT 0.00,
    `medicine`              decimal(12,2) DEFAULT 0.00,
    `lpg`                   decimal(12,2) DEFAULT 0.00,
    `repairs_maintenance`   decimal(12,2) DEFAULT 0.00,
    `fuel_trans`            decimal(12,2) DEFAULT 0.00,
    `communication`         decimal(12,2) DEFAULT 0.00,
    `transportation`        decimal(12,2) DEFAULT 0.00,
    `light`                 decimal(12,2) DEFAULT 0.00,
    `drinking_water`        decimal(12,2) DEFAULT 0.00,
    `water`                 decimal(12,2) DEFAULT 0.00,
    `sss_phic_hdmf`         decimal(12,2) DEFAULT 0.00,
    `taxes_licences`        decimal(12,2) DEFAULT 0.00,
    `office_supplies`       decimal(12,2) DEFAULT 0.00,
    `kitchen_supplies`      decimal(12,2) DEFAULT 0.00,
    `bio_pest_control`      decimal(12,2) DEFAULT 0.00,
    `representation`        decimal(12,2) DEFAULT 0.00,
    `miscellaneous`         decimal(12,2) DEFAULT 0.00,
    `sir_budoy_nikki`       decimal(12,2) DEFAULT 0.00,
    `staff_meal`            decimal(12,2) DEFAULT 0.00,
    `pest_control_bio_aug`  decimal(12,2) DEFAULT 0.00,
    `commission_fees`       decimal(12,2) DEFAULT 0.00,
    `exhaust_cleaning`      decimal(12,2) DEFAULT 0.00,
    `bank_fees`             decimal(12,2) DEFAULT 0.00,
    `admin_salary_shares`   decimal(12,2) DEFAULT 0.00,
    `marketing`             decimal(12,2) DEFAULT 0.00,
    `sales_discounts`       decimal(12,2) DEFAULT 0.00,
    `pdc`                   decimal(12,2) DEFAULT 0.00,
    `ca`                    decimal(12,2) DEFAULT 0.00,
    `withdrawal`            decimal(12,2) DEFAULT 0.00,
    `depreciation_expense`  decimal(12,2) DEFAULT 0.00,
    `row_total`             decimal(12,2) DEFAULT 0.00,
    `saved_by`              varchar(100) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}