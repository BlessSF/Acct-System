<?php
/**
 * branch_sales.php — connects the Dashboard's Gross Sales KPIs
 * (Daily / Monthly / FY Gross Sales, Branch Performance table,
 * Today's Snapshot, Recent Reports) directly to each retail branch's
 * own Sales Report table, instead of the old shared `daily_reports`
 * table.
 *
 * Whatever a cashier/manager Saves on a branch's Sales Report page
 * (stella_sales_report, dois_sales_report, pub_express_sales_report,
 * h_sales_report, recovery_sales_report) now shows up on the
 * Dashboard immediately — no separate "daily report" entry needed.
 *
 * DemicLab-Main and DemicLab-Jaro share the demic_daily_reports table
 * (their own Daily Report page) and are wired in below too, so their
 * numbers also flow straight into the Dashboard / Accounting Head
 * totals.
 *
 * H Carwash is Hero's carwash operation — it saves to its own table
 * (h_carwash_report, via h/h_carwash_sales_report.php) and is wired
 * in as its OWN branch label ("H Carwash"), separate from "H"'s
 * regular retail sales, so it shows up as its own card/row on the
 * Dashboard and Branch Performance table instead of being merged
 * into Hero's numbers.
 *
 * Commissary is wired in from its OIC Daily Report
 * (commissary_oic_report, via commissary/commissary_oic_report.php).
 * Its Sales Revenue (POS Reading + Store Gulay + Samgyup + Grab +
 * Others) maps onto the common "gross_sales" slot and its Sales
 * Discount (Grab Discount + auto Grab CF + Walk In Discount) maps
 * onto "sales_discount", so Gross Profit here matches the OIC
 * Report's own "Gross Sales" figure. Supplier + Gulay map onto the
 * shared `gulay_commissary` slot and Direct Purchases onto
 * `direct_purchases` — the same slots this file already reserved
 * for Commissary's COGS split — and Kitchen/Frontline/Overtime map
 * onto the shared manpower slots.
 */

/** Branches wired to the Dashboard's live Gross Sales figures. */
function branchSalesConnectedBranches(): array
{
    return ['Stella', 'Dois', 'Pub Express', 'H', 'H Carwash', 'Recovery', 'Commissary', 'DemicLab-Main', 'DemicLab-Jaro'];
}

/** Sales Report table name for a given branch label, or null if not connected. */
function branchSalesReportTable(string $branch): ?string
{
    static $map = [
        'Stella'        => 'stella_sales_report',
        'Dois'          => 'dois_sales_report',
        'Pub Express'   => 'pub_express_sales_report',
        'H'             => 'h_sales_report',
        'H Carwash'     => 'h_carwash_report',
        'Recovery'      => 'recovery_sales_report',
        'Commissary'    => 'commissary_oic_report',
        'DemicLab-Main' => 'demic_daily_reports',
        'DemicLab-Jaro' => 'demic_daily_reports',
    ];
    return $map[$branch] ?? null;
}

/** Sales Report page (relative to SalesHub root, via BASE_URL) for a given branch label, or null. */
function branchSalesReportPage(string $branch): ?string
{
    static $map = [
        'Stella'        => '/stella/stella_sales_report.php',
        'Dois'          => '/dois/dois_sales_report.php',
        'Pub Express'   => '/pub_express/pub_express_sales_report.php',
        'H'             => '/h/h_sales_report.php',
        'H Carwash'     => '/h/h_carwash_sales_report.php',
        'Recovery'      => '/recovery/recovery_sales_report.php',
        'Commissary'    => '/commissary/commissary_oic_report.php',
        'DemicLab-Main' => '/demicmain/demic_daily_report.php',
        'DemicLab-Jaro' => '/demicmain/demic_daily_report.php',
    ];
    return $map[$branch] ?? null;
}

/** Grab's commission-fee ("Grab CF") rate on Grab Sales net of Grab Discount — same 20% the Commissary OIC Report uses. */
const OIC_GRAB_CF_RATE = 0.20;

/**
 * Columns added to h_oic_report / branch_oic_report for the new OIC Report format
 * (the Excel layout: Grab Discount, Billing-to-Commissary / Gulay / Seafoods / Direct
 * Purchases COGS split, and the Last Order time).
 *
 * `gulay_commissary` keeps its name but now means "Billing to Commissary" in the UI, so
 * every figure already saved there stays put; `gulay` and `seafoods` are new lines.
 */
function oicNewColumns(): array
{
    return [
        'grab_discount'   => "decimal(12,2) NOT NULL DEFAULT 0.00",
        'gulay'           => "decimal(12,2) NOT NULL DEFAULT 0.00",
        'seafoods'        => "decimal(12,2) NOT NULL DEFAULT 0.00",
        'last_order_time' => "time DEFAULT NULL",
    ];
}

/**
 * Adds any of oicNewColumns() the table doesn't have yet (safe to run on every request:
 * one SHOW COLUMNS, ALTERs only when something is missing). Returns true when every new
 * column is present, so the UNION below can fall back to 0 instead of erroring if an
 * ALTER was ever refused by the host.
 */
function oicEnsureNewColumns(PDO $pdo, string $table): bool
{
    $have = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) $have[$c['Field']] = true;
    $ok = true;
    foreach (oicNewColumns() as $col => $def) {
        if (isset($have[$col])) continue;
        try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def"); }
        catch (Throwable $e) { $ok = false; }
    }
    return $ok;
}

/** true once oicEnsureNewColumns() succeeded for this table in the current request. */
function oicNewColumnsReady(string $table, ?bool $set = null): bool
{
    static $ready = [];
    if ($set !== null) $ready[$table] = $set;
    return $ready[$table] ?? false;
}

/**
 * The OIC Report maths — one definition, used by the Daily Report page and the Dashboard
 * so a day can never pass on one and fail on the other.
 *
 * Follows the OIC Excel sheet (and the Commissary OIC page):
 *   GROSS SALES      = top line from the Sales Report (the figure the Quota is checked against)
 *   SALES DISCOUNT   = Grab Discount + Grab CF + Walk-in Discount (already summed in `sales_discount`)
 *   GROSS PROFIT     = Gross Sales − Sales Discount
 *   TOTAL COGS       = Billing to Commissary + Gulay + Seafoods + Direct Purchases
 *   TOTAL EXPENSES   = COGS + Other (non-COGS) expenses + Manpower
 *   PROFIT / LOSS    = Gross Profit − Total Expenses
 * and every % is a share of GROSS SALES (e.g. COGS 34,726.06 ÷ 41,753.00 = 83%).
 */
function oicCalcMetrics(array $r): array
{
    $f = fn($k, $d = 0) => (float)($r[$k] ?? $d);

    $gross = $f('gross_sales');
    $disc  = $f('sales_discount');
    $gp    = $gross - $disc;

    $totalCogsBase = $f('gulay_commissary') + $f('direct_purchases');
    $other         = $f('other_expenses');

    $extras = json_decode($r['extra_expenses'] ?? '[]', true) ?: [];
    $extraCogs = $extraNonCogs = 0.0;
    foreach ($extras as $e) {
        if (($e['type'] ?? '') === 'cogs') $extraCogs    += (float)($e['amount'] ?? 0);
        else                               $extraNonCogs += (float)($e['amount'] ?? 0);
    }
    $totalCogs = $totalCogsBase + $extraCogs;
    $totalExp  = $totalCogs + $other + $extraNonCogs;           // COGS + non-COGS (the sheet's first TOTAL EXPENSES line)

    $totalMp     = $f('kitchen_manpower') + $f('frontline_manpower') + $f('overtime') + $f('undertime');
    $totalAllExp = $totalExp + $totalMp;
    $profitLoss  = $gp - $totalAllExp;

    $pct = fn($v) => $gross > 0 ? round(($v / $gross) * 100, 1) : 0;
    $discP = $pct($disc);  $cogsP = $pct($totalCogs);  $mpP = $pct($totalMp);
    $totalExpP = $pct($totalAllExp);  $npP = $pct($profitLoss);

    $quotaPassed = $gross >= $f('quota_target', 9000);
    $cogsPassed  = $cogsP <= $f('cogs_threshold', 30);
    $mpPassed    = $mpP   <= $f('mp_threshold', 35);
    $npPassed    = $npP   >= $f('np_threshold', 20);

    return compact('gp','totalCogs','other','totalExp','totalMp','totalAllExp','profitLoss',
                   'discP','cogsP','mpP','totalExpP','npP',
                   'quotaPassed','cogsPassed','mpPassed','npPassed','extras')
         + ['otherExp' => $other];   // the Dashboard's older key name for the same figure
}

/**
 * H's OIC Report (editable from the Daily Report page) — COGS split,
 * manpower and per-day targets for H. Self-creating, like every other
 * module table, and guarded so the Dashboard never breaks if it is
 * queried before anyone has opened the Daily Report.
 */
/**
 * One row per store + date: merge up duplicates (the newest row wins) and add the
 * UNIQUE key if the table was created without it, so saving a day again updates it.
 */
function ensureUniqueStoreDate(PDO $pdo, string $table): void
{
    static $done = [];
    if (isset($done[$table])) return;
    $done[$table] = true;
    try {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        if (!$pdo->query("SHOW TABLES LIKE '$table'")->fetch()) return;
        $has = false;
        foreach ($pdo->query("SHOW KEYS FROM `$table` WHERE Non_unique=0")->fetchAll(PDO::FETCH_ASSOC) as $k) {
            if ($k['Key_name'] !== 'PRIMARY' && in_array($k['Column_name'], ['store_name', 'report_date'], true)) $has = true;
        }
        if ($has) return;
        $pdo->exec("DELETE a FROM `$table` a JOIN `$table` b ON a.store_name=b.store_name AND a.report_date=b.report_date AND a.id < b.id");
        $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)");
    } catch (Throwable $e) {}
}

function ensureHOicTable(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) return;
    try {
        $pdo = $pdo ?: getPDO();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `h_oic_report` (
            `id`               int(11) NOT NULL AUTO_INCREMENT,
            `store_name`       varchar(50) NOT NULL DEFAULT 'H',
            `report_date`      date NOT NULL,
            `gulay_commissary` decimal(12,2) NOT NULL DEFAULT 0.00,
            `direct_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
            `kitchen`          decimal(12,2) NOT NULL DEFAULT 0.00,
            `frontline`        decimal(12,2) NOT NULL DEFAULT 0.00,
            `overtime`         decimal(12,2) NOT NULL DEFAULT 0.00,
            `undertime`        decimal(12,2) NOT NULL DEFAULT 0.00,
            `quota_target`     decimal(12,2) NOT NULL DEFAULT 9000.00,
            `cogs_threshold`   decimal(5,2)  NOT NULL DEFAULT 30.00,
            `mp_threshold`     decimal(5,2)  NOT NULL DEFAULT 35.00,
            `np_threshold`     decimal(5,2)  NOT NULL DEFAULT 20.00,
            `saved_by`         varchar(100) DEFAULT NULL,
            `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at`       timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        oicNewColumnsReady('h_oic_report', oicEnsureNewColumns($pdo, 'h_oic_report'));
        ensureUniqueStoreDate($pdo, 'h_oic_report');
        $done = true;
    } catch (Throwable $ignored) {}
}

/**
 * Stores whose OIC Report (COGS split, manpower, per-day targets) is entered on the
 * Daily Report page and kept in the shared `branch_oic_report` table — the same panel
 * H has. H keeps its own `h_oic_report`; Commissary has its own OIC Daily Report page
 * and the two Demic Lab stores have their own daily report page, so none of those are here.
 */
function oicPanelStores(): array
{
    return ['Stella', 'Dois', 'Pub Express', 'Recovery', 'H Carwash'];
}

/** The `report_locks.store_name` a store's dates are locked under (H Carwash locks as its internal code). */
function oicLockStore(string $store): string
{
    return $store === 'H Carwash' ? 'HEROCARWASH' : $store;
}

/** Shared OIC Report table — same columns as h_oic_report, one row per store + date. */
function ensureBranchOicTable(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) return;
    try {
        $pdo = $pdo ?: getPDO();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `branch_oic_report` (
            `id`               int(11) NOT NULL AUTO_INCREMENT,
            `store_name`       varchar(50) NOT NULL,
            `report_date`      date NOT NULL,
            `gulay_commissary` decimal(12,2) NOT NULL DEFAULT 0.00,
            `direct_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
            `kitchen`          decimal(12,2) NOT NULL DEFAULT 0.00,
            `frontline`        decimal(12,2) NOT NULL DEFAULT 0.00,
            `overtime`         decimal(12,2) NOT NULL DEFAULT 0.00,
            `undertime`        decimal(12,2) NOT NULL DEFAULT 0.00,
            `quota_target`     decimal(12,2) NOT NULL DEFAULT 9000.00,
            `cogs_threshold`   decimal(5,2)  NOT NULL DEFAULT 30.00,
            `mp_threshold`     decimal(5,2)  NOT NULL DEFAULT 35.00,
            `np_threshold`     decimal(5,2)  NOT NULL DEFAULT 20.00,
            `saved_by`         varchar(100) DEFAULT NULL,
            `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at`       timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        oicNewColumnsReady('branch_oic_report', oicEnsureNewColumns($pdo, 'branch_oic_report'));
        ensureUniqueStoreDate($pdo, 'branch_oic_report');
        $done = true;
    } catch (Throwable $ignored) {}
}

/**
 * A UNION ALL derived table across every connected branch's Sales
 * Report, normalized to the same column shape as the legacy
 * `daily_reports` table (report_date, store_name, gross_sales,
 * sales_discount, other_expenses, ...) so existing Dashboard SQL and
 * calcMetrics() keep working unchanged — just swap
 * "FROM daily_reports" for "FROM $SALES_UNION".
 *
 * Each branch's own discount / expense columns are mapped onto the
 * common slots; fields a branch has no equivalent for (COGS split,
 * manpower, per-report thresholds) default to 0 / the standard
 * store-wide target so quota/threshold badges still make sense.
 */
function branchSalesUnionSQL(): string
{
    ensureHOicTable();
    ensureBranchOicTable();

    // New-format columns (Grab Discount / Gulay / Seafoods). If a host ever refused the
    // ALTER, fall back to 0 so the Dashboard and Daily Report keep working.
    $cf = rtrim(rtrim(number_format(OIC_GRAB_CF_RATE, 4, '.', ''), '0'), '.');
    $oicCols = function (string $table): array {
        $ok = oicNewColumnsReady($table);
        return [
            'grabDisc' => $ok ? 'COALESCE(o.grab_discount, 0)' : '0',
            'gulay'    => $ok ? 'COALESCE(o.gulay, 0)'         : '0',
            'seafoods' => $ok ? 'COALESCE(o.seafoods, 0)'      : '0',
        ];
    };

    // Sales Report feeds gross / discount / other expenses; the OIC Report panel on the
    // Daily Report page (branch_oic_report) feeds the COGS split, manpower and per-day
    // targets. LEFT JOIN so a day with no OIC entry yet still shows, with the store-wide
    // defaults (0 / 9000 / 30 / 35 / 20).
    //
    // OIC format (same as the Excel / Commissary OIC):
    //   sales_discount   = Grab Discount + Grab CF (20% of Grab Sales net of Grab Discount) + Walk-in Discount
    //                      ($discountExpr — the Sales Report's own discount — is the Walk-in Discount)
    //   gulay_commissary = Billing to Commissary + Gulay + Seafoods   (Direct Purchases stays its own slot)
    // grab_sales_amt / walkin_discount are carried through untouched so the Daily Report can show the breakdown.
    $withOic = function (string $table, string $label, string $storeExpr, string $discountExpr, string $otherExpr, string $grabExpr = '0') use ($oicCols, $cf): string {
        $c = $oicCols('branch_oic_report');
        return "SELECT s.id, s.report_date, $storeExpr AS store_name, s.gross_sales,
               ($discountExpr + {$c['grabDisc']} + GREATEST($grabExpr - {$c['grabDisc']}, 0) * $cf) AS sales_discount,
               (COALESCE(o.gulay_commissary, 0) + {$c['gulay']} + {$c['seafoods']}) AS gulay_commissary,
               COALESCE(o.direct_purchases, 0) AS direct_purchases,
               $otherExpr AS other_expenses,
               COALESCE(o.kitchen, 0)   AS kitchen_manpower,
               COALESCE(o.frontline, 0) AS frontline_manpower,
               COALESCE(o.overtime, 0)  AS overtime,
               COALESCE(o.undertime, 0) AS undertime,
               '[]' AS extra_expenses,
               COALESCE(o.quota_target, 9000)  AS quota_target,
               COALESCE(o.cogs_threshold, 30)  AS cogs_threshold,
               COALESCE(o.mp_threshold, 35)    AS mp_threshold,
               COALESCE(o.np_threshold, 20)    AS np_threshold,
               s.saved_by AS created_by, s.created_at, s.updated_at,
               $grabExpr AS grab_sales_amt, $discountExpr AS walkin_discount
         FROM (SELECT t.* FROM `$table` t WHERE t.id IN (SELECT MAX(id) FROM `$table` GROUP BY store_name, report_date)) s
         LEFT JOIN (SELECT b.* FROM branch_oic_report b WHERE b.id IN (SELECT MAX(id) FROM branch_oic_report GROUP BY store_name, report_date)) o ON o.store_name = '$label' AND o.report_date = s.report_date";
    };

    // H — same idea, but its OIC rows live in h_oic_report (edited on the Daily Report page too).
    $hc = $oicCols('h_oic_report');
    $hSelect = "SELECT s.id, s.report_date, s.store_name, s.gross_sales,
               (s.discount + {$hc['grabDisc']} + GREATEST(s.grab_sales - {$hc['grabDisc']}, 0) * $cf) AS sales_discount,
               (COALESCE(o.gulay_commissary, 0) + {$hc['gulay']} + {$hc['seafoods']}) AS gulay_commissary,
               COALESCE(o.direct_purchases, 0) AS direct_purchases,
               s.other_expenses AS other_expenses,
               COALESCE(o.kitchen, 0)   AS kitchen_manpower,
               COALESCE(o.frontline, 0) AS frontline_manpower,
               COALESCE(o.overtime, 0)  AS overtime,
               COALESCE(o.undertime, 0) AS undertime,
               '[]' AS extra_expenses,
               COALESCE(o.quota_target, 9000)  AS quota_target,
               COALESCE(o.cogs_threshold, 30)  AS cogs_threshold,
               COALESCE(o.mp_threshold, 35)    AS mp_threshold,
               COALESCE(o.np_threshold, 20)    AS np_threshold,
               s.saved_by AS created_by, s.created_at, s.updated_at,
               s.grab_sales AS grab_sales_amt, s.discount AS walkin_discount
         FROM (SELECT t.* FROM h_sales_report t WHERE t.id IN (SELECT MAX(id) FROM h_sales_report GROUP BY store_name, report_date)) s
         LEFT JOIN (SELECT b.* FROM h_oic_report b WHERE b.id IN (SELECT MAX(id) FROM h_oic_report GROUP BY store_name, report_date)) o ON o.store_name = s.store_name AND o.report_date = s.report_date";

    $selects = [
        $withOic('stella_sales_report',      'Stella',      's.store_name', 's.discount', 's.other_expenses', 's.grab_sales'),
        $withOic('dois_sales_report',        'Dois',        's.store_name', 's.discount', 's.other_expenses', 's.grab_sales'),
        $withOic('pub_express_sales_report', 'Pub Express', 's.store_name', 's.discount', 's.other_expenses', 's.grab_sales'),
        $hSelect,
        // Hero Carwash — own table, but its `store_name` column is stored
        // as the internal code 'HEROCARWASH', so it's relabeled to the
        // display branch name 'H Carwash' here to keep it a distinct,
        // separate card from H's regular retail sales everywhere else in
        // the app (Branch Performance table, view-branch selector, etc.).
        // No Grab at the carwash or at Recovery, so Grab Sales stays 0 for them.
        $withOic('h_carwash_report',     'H Carwash', "'H Carwash'",   's.discounts',                      '(s.expenses + s.marketing_expense)'),
        $withOic('recovery_sales_report', 'Recovery',  's.store_name', '(s.discounts + s.celeb_discounts)', '(s.expenses + s.marketing_expense)'),
        // Commissary's OIC Daily Report — Sales Revenue (POS Reading +
        // Store Gulay + Samgyup + Grab + Others) is the "gross_sales"
        // slot, and Sales Discount (Grab Discount + auto Grab CF +
        // Walk In Discount) is "sales_discount", so gp = gross_sales -
        // sales_discount lines up with the OIC Report's own "Gross
        // Sales" total. Supplier + Gulay fill `gulay_commissary`,
        // Direct Purchases fills `direct_purchases` — matching the OIC
        // page's own Total COGS = Supplier + Gulay + Direct Purchases.
        // Thresholds mirror the OIC page's own pass/fail rules
        // (₱117,000 quota, 80% COGS, 4% manpower, 10% net profit).
        "SELECT id, report_date, store_name,
               (pos_reading + store_gulay + samgyup_sales + grab_sales + others_walkin) AS gross_sales,
               (grab_discount + GREATEST(grab_sales - grab_discount, 0) * $cf + walkin_discount) AS sales_discount,
               (supplier + gulay) AS gulay_commissary, direct_purchases AS direct_purchases,
               other_expenses AS other_expenses,
               kitchen AS kitchen_manpower, frontline AS frontline_manpower,
               overtime AS overtime, 0 AS undertime,
               '[]' AS extra_expenses,
               117000 AS quota_target, 80 AS cogs_threshold,
               4 AS mp_threshold, 10 AS np_threshold,
               saved_by AS created_by, created_at, updated_at,
               grab_sales AS grab_sales_amt, walkin_discount AS walkin_discount
         FROM commissary_oic_report",
        // Demic Lab (Main + Jaro) share one table, distinguished by store_name.
        // Its columns don't line up with the Sales Report template above (no saved_by/
        // created_at, sales stored as sales_revenue), so this one is written out by hand.
        "SELECT id, report_date, store_name, sales_revenue AS gross_sales, sales_discount AS sales_discount,
               0 AS gulay_commissary, 0 AS direct_purchases,
               (frontline_medical_staff + pf_fee) AS other_expenses,
               0 AS kitchen_manpower, 0 AS frontline_manpower,
               0 AS overtime, 0 AS undertime,
               '[]' AS extra_expenses,
               quota_target AS quota_target, 30 AS cogs_threshold,
               mp_threshold AS mp_threshold, 20 AS np_threshold,
               created_by AS created_by, updated_at AS created_at, updated_at,
               0 AS grab_sales_amt, sales_discount AS walkin_discount
         FROM demic_daily_reports",
    ];

    return implode(' UNION ALL ', $selects);
}

/** Ready-to-use "(...) daily_reports" derived-table string for direct use in FROM clauses. */
function branchSalesUnionTable(): string
{
    return '(' . branchSalesUnionSQL() . ') daily_reports';
}