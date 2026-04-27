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

/**
 * Admin: access_level >= 1
 * aCis: accounts.access_level = 0 para jogadores, >= 1 para GMs/admins.
 */
function isAdmin() {
    return isLoggedIn() && currentAccessLevel() >= 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /vote/index.php?msg=nologin');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /vote/vote.php?msg=noaccess');
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
