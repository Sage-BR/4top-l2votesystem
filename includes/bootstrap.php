<?php
/**
 * VoteSystem — Bootstrap
 *
 * Inclua no topo de cada página pública (após config.php).
 * Ordem de carregamento:
 *   1. db.php      — singleton PDO
 *   2. core.php    — lógica de jogo (hash, auth, reward, sessão, CSRF, IP)
 *   3. auth.php    — helpers de sessão/acesso (isLoggedIn, requireAdmin…)
 *   4. helpers.php — lógica do votesystem (tops, cooldown, claim, display)
 */

if (!defined('INSTALLED')) {
    header('Location: install.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start(array('cookie_httponly' => true, 'cookie_samesite' => 'Lax'));
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// ── Layout helpers ────────────────────────────────────────────────────────────

function renderHead($title = 'VoteSystem') {
    echo '<!DOCTYPE html><html lang="pt-BR">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . ' — VoteSystem ICP</title>';
    echo '<link rel="stylesheet" href="assets/css/main.css">';
    echo '</head>';
    echo '<body>';
    echo '<div class="wrapper">';
}

function renderNav() {
    $login   = isset($_SESSION['vs_login']) ? $_SESSION['vs_login'] : null;
    $isAdm   = isAdmin();
    $curPage = basename($_SERVER['PHP_SELF']);
    echo '<nav class="navbar">';
    echo '<a href="vote.php" class="nav-brand"><span class="brand-icon">⚔</span>VoteSystem</a>';
    echo '<ul class="nav-links">';
    echo '<li><a href="vote.php" class="' . ($curPage === 'vote.php' ? 'active' : '') . '">⚜ Votar</a></li>';
    if ($isAdm) {
        echo '<li><a href="admin.php" class="' . ($curPage === 'admin.php' ? 'active' : '') . '">⚙ Admin</a></li>';
    }
    echo '</ul>';
    echo '<div class="nav-user">';
    if ($login) {
        echo '<div class="user-info">';
        echo '<span style="color:var(--text-dim)">👤</span>';
        echo '<span>' . htmlspecialchars($login) . '</span>';
        if ($isAdm) echo '<span class="badge-admin">ADMIN</span>';
        echo '</div>';
        echo '<a href="logout.php" class="btn btn-ghost btn-sm">Sair</a>';
    } else {
        echo '<a href="index.php" class="btn btn-primary btn-sm">Login</a>';
    }
    echo '</div>';
    echo '</nav>';
}

function renderFooter() {
    echo '<footer class="footer">';
    echo 'VoteSystem <span class="text-gold">4Top Servers</span> &mdash; PHP 5.6~8.2 Compatible';
    echo '</footer>';
    echo '</div>'; // .wrapper
    echo '</body></html>';
}
