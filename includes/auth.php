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
 * Admin: access_level >= threshold (configurável via VS_ADMIN_ACCESS_LEVEL)
 * aCis: accounts.access_level = 0 para jogadores, >= 1 para GMs/admins.
 * Por padrão, qualquer GM (access_level >= 1) tem acesso ao painel.
 * Defina VS_ADMIN_ACCESS_LEVEL em config.php para restringir (ex: 100 para Head GM+).
 */
function isAdmin() {
    $threshold = defined('VS_ADMIN_ACCESS_LEVEL') ? (int)VS_ADMIN_ACCESS_LEVEL : 1;
    return isLoggedIn() && currentAccessLevel() >= $threshold;
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
