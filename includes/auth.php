<?php
/**
 * VoteSystem — Auth helpers
 *
 * Wrappers de sessão e controle de acesso.
 * A verificação de senha e busca de conta ficam em core.php (gameLogin).
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

function isLoggedIn() {
    startSession();
    return !empty($_SESSION['vs_login']);
}

// Permissão explícita: Access do jogo não representa o administrador do painel.
function isAdmin() {
    $admins = defined('DK_ADMIN_ACCOUNTS') && is_array(DK_ADMIN_ACCOUNTS) ? DK_ADMIN_ACCOUNTS : array();
    return isLoggedIn() && in_array(currentLogin(), $admins, true);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?msg=nologin');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: vote.php?msg=noaccess');
        exit;
    }
}

/**
 * Mantido por compatibilidade com index.php.
 * Delega para gameLogin() de core.php.
 */
function attemptLogin($login, $password) {
    return gameLogin($login, $password);
}
