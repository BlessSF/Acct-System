<?php
/**
 * branch_colors.php — single source of truth for each branch's accent
 * color, used to color-code Excel exports (title band, header row,
 * totals row) consistently everywhere that branch appears.
 */
function branchAccentColor(string $branch): string
{
    static $map = [
        'Stella'          => '#15803D', // green
        'Dois'            => '#EA580C', // orange
        'Pub Express'     => '#C1121F', // red (paired with white header text/bands)
        'H'               => '#E4002B', // red (Hero)
        'Recovery'        => '#8B5E3C', // light brown
        'Commissary'      => '#d97706', // amber
        'DemicLab-Main'   => '#2563eb', // blue
        'DemicLab-Jaro'   => '#2563eb', // blue
        'H Carwash'       => '#C0202D', // red (Carwash)
    ];
    return $map[$branch] ?? '#0f7b5c';
}

/**
 * salesReportAccentColor() — accent color specifically for the Daily
 * Sales Report exports (per-branch request): Stella=green, Dois=orange,
 * Pub Express=red/dark red, H (Hero)=pure red/white, H Carwash=red/white,
 * Recovery=light brown. Kept separate from branchAccentColor() so the
 * existing COGS / Month-End Inventory / Summary Report exports (already
 * colored) are not affected.
 */
function salesReportAccentColor(string $branch): string
{
    static $map = [
        'Stella'       => '#15803D', // green
        'Dois'         => '#EA580C', // orange
        'Pub Express'  => '#7F1D1D', // dark red
        'H'            => '#E4002B', // pure red (Hero)
        'H Carwash'    => '#C0202D', // red (Carwash)
        'Recovery'     => '#8B5E3C', // light brown
    ];
    return $map[$branch] ?? '#0f7b5c';
}
