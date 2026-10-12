<?php
// ============================================================
//  config.php — Database connection (PDO)
//  Edit these credentials to match your MySQL setup
// ============================================================

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'acct');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'SalesHub');
define('APP_VERSION', '1.0.0');

// ── BASE_URL — absolute URL path to the app's root folder ──────────
// Needed because layout.php (sidebar/nav) and a few auth.php redirects
// are shared across pages that now live at different folder depths
// (root pages vs. branch/*.php pages). A plain relative link like
// href="stella/stella_sales_report.php" only resolves correctly when
// the CURRENT page is at the app root — from inside e.g. dois/, the
// browser would resolve it to dois/stella/stella_sales_report.php and
// 404. BASE_URL is computed from this file's own location (config.php
// always lives at the app root) relative to the web server's document
// root, so it works no matter what your htdocs folder is named.
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $appDir  = str_replace('\\', '/', rtrim(__DIR__, '/'));
    $basePath = '';
    if ($docRoot !== '' && strpos($appDir, $docRoot) === 0) {
        $basePath = substr($appDir, strlen($docRoot));
    }
    define('BASE_URL', $basePath); // '' if hosted at web root, else e.g. '/SalesHub'
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}