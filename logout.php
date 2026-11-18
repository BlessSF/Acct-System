<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cp = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cp['path'], $cp['domain'], $cp['secure'], $cp['httponly']);
}
session_destroy();
header('Location: index.php' . (isset($_GET['timeout']) ? '?timeout=1' : ''));
exit;