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
ensureVoteSchema();
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
    // i18n: localForage (IndexedDB/WebSQL/localStorage) + sistema de idiomas
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
    echo '<script src="assets/js/i18n.js"></script>';
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
    echo '<img src="https://i.imgur.com/eF2disk.png" alt="VoteSystem" style="height:50px;width:auto;display:block">';
    echo '</a>';
    echo '<ul class="nav-links">';
    echo '<li><a href="vote.php" class="' . ($curPage === 'vote.php' ? 'active' : '') . '" data-i18n="nav_vote">⚜ Votar</a></li>';
    if ($isAdm) {
        echo '<li><a href="admin.php" class="' . ($curPage === 'admin.php' ? 'active' : '') . '" data-i18n="nav_admin">⚙ Admin</a></li>';
    }
    foreach ($extraLinks as $link) {
        $target = !empty($link['target']) ? ' target="' . htmlspecialchars($link['target']) . '"' : '';
        echo '<li><a href="' . htmlspecialchars($link['url']) . '"' . $target . '>' . htmlspecialchars($link['label']) . '</a></li>';
    }
    echo '</ul>';

    // ── Bloco direito: user info ──────────────────────────────────────────
    echo '<div style="display:flex;align-items:center;gap:1rem;margin-left:auto">';

    // User info
    echo '<div class="nav-user">';
    if ($login) {
        echo '<div class="user-info">';
        echo '<span style="color:var(--text-dim)">👤</span>';
        echo '<span>' . htmlspecialchars($login) . '</span>';
        if ($isAdm) echo '<span class="badge-admin">ADMIN</span>';
        echo '</div>';
        echo '<a href="logout.php" class="btn btn-ghost btn-sm" data-i18n="nav_logout">Sair</a>';
    } else {
        echo '<a href="index.php" class="btn btn-primary btn-sm" data-i18n="nav_login">Login</a>';
    }
    echo '</div>';
    echo '</div>'; // fim bloco direito
    echo '</nav>';

    // ── Seletor de Idioma — fixo, logo abaixo do navbar, canto direito ───
    echo '<div id="langSwitcher"'
        . ' title="Language / Idioma / Язык"'
        . ' style="position:fixed;top:70px;right:18px;z-index:150;'
        .         'display:flex;align-items:center;gap:3px;'
        .         'background:rgba(4,5,8,0.82);'
        .         'border:1px solid rgba(201,168,76,0.28);'
        .         'border-radius:8px;'
        .         'padding:5px 7px;'
        .         'backdrop-filter:blur(14px);'
        .         'box-shadow:0 4px 20px rgba(0,0,0,0.5),0 0 0 1px rgba(201,168,76,0.06);">';
    echo '<button class="lang-btn" data-lang="pt" title="Português (Brasil)" aria-label="Português (Brasil)"><img src="https://flagcdn.com/br.svg" width="24" height="18" alt="BR" loading="lazy"></button>';
    echo '<button class="lang-btn" data-lang="es" title="Español"            aria-label="Español"><img src="https://flagcdn.com/es.svg" width="24" height="18" alt="ES" loading="lazy"></button>';
    echo '<button class="lang-btn" data-lang="en" title="English (US)"       aria-label="English"><img src="https://flagcdn.com/us.svg" width="24" height="18" alt="EN" loading="lazy"></button>';
    echo '<button class="lang-btn" data-lang="ru" title="Русский"            aria-label="Русский"><img src="https://flagcdn.com/ru.svg" width="24" height="18" alt="RU" loading="lazy"></button>';
    echo '</div>';
}

function renderFooter() {
    $footer = defined('LAYOUT_FOOTER') ? LAYOUT_FOOTER : 'VoteSystem <span class="text-gold">4Top Servers</span>';
    $ipInfo = function_exists('clientIp') ? clientIp() : 'UNKNOWN';

    $footer = preg_replace(
        '/<a\s[^>]*>(\s*4Top\s*Servers\s*)<\/a>/i',
        '$1',
        $footer
    );
    $footer = preg_replace(
        '/4Top\s*Servers/i',
        '<a href="https://top.4teambr.com/" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none">4Top Servers</a>',
        $footer
    );
    $footer .= ' <span class="footer-ip">Seu ip: ' . htmlspecialchars($ipInfo, ENT_QUOTES, 'UTF-8') . '</span>';
    // ──────────────────────────────────────────────────────────────────────

    echo '<footer class="footer">' . $footer . '</footer>';
    echo '</div>'; // .wrapper
    echo '</body></html>';
}
