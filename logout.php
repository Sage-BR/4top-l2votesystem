<?php
/**
 * VoteSystem ICP Networks — Logout
 * Requer CSRF token (via GET) para evitar logout forçado.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/core.php';

$csrf = isset($_GET['csrf']) ? (string)$_GET['csrf'] : '';
if (!verifyCsrf($csrf)) {
    header('Location: index.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
session_regenerate_id(true);
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
