<?php
/**
 * VoteSystem — Bootstrap
 *
 * Inclua no topo de cada página pública (após config.php).
 * Para customizar título, favicon, brand e rodapé edite: includes/layout.php
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
require_once __DIR__ . '/layout.php';

// ── Layout helpers ────────────────────────────────────────────────────────────

function renderHead($pageTitle = '') {
    $siteName   = defined('LAYOUT_SITE_NAME')   ? LAYOUT_SITE_NAME   : 'VoteSystem';
    $siteSuffix = defined('LAYOUT_SITE_SUFFIX')  ? LAYOUT_SITE_SUFFIX  : '4Top Servers';
    $favicon    = defined('LAYOUT_FAVICON')      ? LAYOUT_FAVICON      : '';
    $extraCss   = defined('LAYOUT_EXTRA_CSS')    ? LAYOUT_EXTRA_CSS    : '';

    $title = $pageTitle
        ? htmlspecialchars($pageTitle) . ' — ' . htmlspecialchars($siteName)
        : htmlspecialchars($siteName) . ' — ' . htmlspecialchars($siteSuffix);

    echo '<!DOCTYPE html><html lang="pt-BR">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $title . '</title>';
    if ($favicon) {
        $ext  = strtolower(pathinfo($favicon, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'svg') ? 'image/svg+xml' : 'image/x-icon');
        echo '<link rel="icon" type="' . $mime . '" href="' . htmlspecialchars($favicon) . '">';
    }
    echo '<link rel="stylesheet" href="assets/css/main.css">';
    if (trim($extraCss)) echo '<style>' . $extraCss . '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="wrapper">';
}

function renderNav() {
    $login      = isset($_SESSION['vs_login']) ? $_SESSION['vs_login'] : null;
    $isAdm      = isAdmin();
    $curPage    = basename($_SERVER['PHP_SELF']);
    $brandUrl   = defined('LAYOUT_BRAND_URL')   ? LAYOUT_BRAND_URL   : 'vote.php';
    $extraLinks = isset($GLOBALS['LAYOUT_NAV_LINKS']) ? $GLOBALS['LAYOUT_NAV_LINKS'] : array();

    echo '<nav class="navbar">';
    echo '<a href="' . htmlspecialchars($brandUrl) . '" class="nav-brand">';
    echo '<img src="https://i.imgur.com/MAuPJrp.png" alt="VoteSystem" style="height:36px;width:auto;display:block">';
    echo '</a>';
    echo '<ul class="nav-links">';
    echo '<li><a href="vote.php" class="' . ($curPage === 'vote.php' ? 'active' : '') . '">⚜ Votar</a></li>';
    if ($isAdm) {
        echo '<li><a href="admin.php" class="' . ($curPage === 'admin.php' ? 'active' : '') . '">⚙ Admin</a></li>';
    }
    foreach ($extraLinks as $link) {
        $target = !empty($link['target']) ? ' target="' . htmlspecialchars($link['target']) . '"' : '';
        echo '<li><a href="' . htmlspecialchars($link['url']) . '"' . $target . '>' . htmlspecialchars($link['label']) . '</a></li>';
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
    $footer = defined('LAYOUT_FOOTER') ? LAYOUT_FOOTER : 'VoteSystem <span class="text-gold">4Top Servers</span>';
    echo '<footer class="footer">' . $footer . '</footer>';
    echo '</div>'; // .wrapper
    echo '</body></html>';
}