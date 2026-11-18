<?php
// keepalive.php — the page tells the server "the person is still here" (called while they type / click / scroll).
require_once 'config.php';
require_once 'auth.php';
requireLogin();          // refreshes last_activity (ajax_keepalive) or answers 401 if already timed out
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'idle_limit' => IDLE_TIMEOUT]);