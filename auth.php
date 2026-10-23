<?php
// ============================================================
//  auth.php — Session & authentication helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

// True for a fetch()-driven AJAX call: either it sent the standard
// XHR header, or — the pattern every ajax_* handler in this codebase
// relies on — the POST body carries an `ajax_...` flag field. Plain
// full-page <form method="POST"> submits (delete confirms, daily
// report saves, login, etc.) never use an `ajax_`-prefixed field name,
// so they're unaffected by this check.
function isAjaxRequest(): bool {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') return true;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        foreach (array_keys($_POST) as $k) {
            if (strpos($k, 'ajax_') === 0) return true;
        }
    }
    return false;
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        // A fetch() call that hits an expired/missing session used to get
        // a 302 redirect to the login page here. fetch() silently follows
        // that redirect and hands the caller the login page's HTML, so
        // `await res.json()` throws — every Save/Update/Delete button on
        // every page then shows a generic "Error" / "❌ Network error"
        // even though the real problem is just "you got logged out."
        // Respond with real JSON instead so the UI can say that plainly.
        if (isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'session_expired' => true,
                'msg' => 'Your session has expired. Please log in again.']);
            exit;
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'      => $_SESSION['user_id']      ?? 0,
        'name'    => $_SESSION['user_name']    ?? 'Guest',
        'role'    => $_SESSION['user_role']    ?? 'staff',
        'email'   => $_SESSION['user_email']   ?? '',
        'subrole' => $_SESSION['user_subrole'] ?? null,
    ];
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isBranch(): bool {
    return ($_SESSION['user_role'] ?? '') === 'branch';
}

function isManagement(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['manager', 'accounting', 'oic', 'admin']);
}

// Accounting Head — a company-wide, all-branch financial oversight
// account (session role 'accounting'). Passes isManagement(), so it
// already sees everything Manager sees across every branch — this
// helper exists for the rare spot that wants to address the role
// specifically (e.g. labeling, or a future Manager-only carve-out).
function isAccountingHead(): bool {
    return ($_SESSION['user_role'] ?? '') === 'accounting';
}

// Friendly display label for a management session role.
function managementRoleLabel(string $role): string {
    switch ($role) {
        case 'manager':    return 'Manager';
        case 'accounting': return 'Accounting Head';
        case 'oic':        return 'OIC';
        case 'admin':      return 'Admin';
        default:           return ucfirst($role);
    }
}

// A "cashier" is a branch login made through the Cashier icon on the
// login page. Their session still has user_role === 'branch' (so every
// existing isBranch()/currentBranch() check elsewhere keeps working
// untouched) but user_subrole === 'cashier' marks the restriction.
// Cashiers may reach the Dashboard, their branch's Daily Report, and —
// for the branches listed in cashierSalesReportPaths() — their branch's
// Sales Report page(s). Every other page stays off-limits.
function isCashier(): bool {
    return ($_SESSION['user_subrole'] ?? '') === 'cashier';
}

function currentBranch(): ?string {
    return $_SESSION['user_branch'] ?? null;
}

// Whether this login gets the Dashboard page at all. The H branch's
// OIC and Cashier logins do NOT — they work from their Daily Report /
// Sales Report pages only. Management and every other branch keep it.
function branchHasDashboard(): bool {
    return !(isBranch() && currentBranch() === 'H');
}

// The one Daily Report page a Cashier/OIC login is allowed to reach for
// their branch, as a path relative to the app root (for redirects/links).
// Demic branches use demicmain/demic_daily_report.php (which has its own
// fields and formulas); every other branch uses the generic daily_report.php.
function cashierDailyReportPath(): string {
    return in_array(currentBranch(), ['DemicLab-Main', 'DemicLab-Jaro'], true)
        ? 'demicmain/demic_daily_report.php'
        : 'daily_report.php';
}

// The branch Sales Report page(s) a Cashier login is allowed to reach,
// as paths relative to the app root. Only these branches get Sales
// Report access for the Cashier subrole — H has both its regular and
// Carwash Sales Report. Any branch not listed here returns [] and the
// Cashier for that branch stays limited to Dashboard + Daily Report.
function cashierSalesReportPaths(): array {
    switch (currentBranch()) {
        case 'Stella':      return ['stella/stella_sales_report.php'];
        case 'Dois':        return ['dois/dois_sales_report.php'];
        case 'H':           return ['h/h_sales_report.php', 'h/h_carwash_sales_report.php'];
        case 'Pub Express': return ['pub_express/pub_express_sales_report.php'];
        case 'Recovery':    return ['recovery/recovery_sales_report.php'];
        case 'Commissary':  return ['commissary/commissary_oic_report.php']; // OIC entry page (New Entry)
        default:            return [];
    }
}

// Pages a Cashier is allowed to land on — the Dashboard, their branch's
// Daily Report (see cashierDailyReportPath()), and their branch's Sales
// Report page(s) (see cashierSalesReportPaths()). Bare filenames only —
// compared against basename($_SERVER['SCRIPT_NAME']) in
// enforceCashierAccess(), which strips any folder path.
function cashierAllowedSharedPages(): array {
    $pages = [basename(cashierDailyReportPath())];
    if (branchHasDashboard()) array_unshift($pages, 'dashboard.php');
    foreach (cashierSalesReportPaths() as $srPath) {
        $pages[] = basename($srPath);
    }
    return $pages;
}

// ── Real enforcement, callable BEFORE any AJAX handler runs ──────────
// requireLogin() only checks whether someone is logged in at all — it
// has no idea which page it's on, so it can't stop a Cashier session
// from POSTing straight to another page's ajax_save/ajax_delete handler
// and bypassing the sidebar/redirect restriction entirely (those AJAX
// blocks `exit;` long before layout.php's redirect ever runs). Call
// this immediately after requireLogin(), before any AJAX handling, on
// every page — it redirects (GET) or hard-stops with 403 (POST/AJAX)
// if a Cashier tries to touch anything outside cashierAllowedSharedPages()
// (Dashboard, their branch's Daily Report, and their branch's Sales
// Report page(s) where applicable).
function enforceCashierAccess(): void {
    if (!isCashier()) return;

    $thisFile = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if (in_array($thisFile, cashierAllowedSharedPages(), true)) return; // Dashboard or their Daily Report — fine

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Mid-AJAX-handler request from a disallowed page — block outright,
        // don't redirect (a redirect response is useless to a fetch() call
        // and would otherwise let the original handler's code keep running).
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Access restricted to your branch\'s Daily Report.']);
        exit;
    }

    header('Location: ' . BASE_URL . '/' . cashierDailyReportPath());
    exit;
}

// Returns a SQL WHERE clause fragment and optional param to restrict data to the
// current branch. For management accounts returns an always-true fragment with no param.
// Usage: [$clause, $params] = branchFilter('store_name');
function branchFilter(string $column = 'store_name'): array {
    if (isBranch()) {
        return ["`$column` = ?", [currentBranch()]];
    }
    return ['1=1', []];
}

function flashSet(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function flashGet(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}