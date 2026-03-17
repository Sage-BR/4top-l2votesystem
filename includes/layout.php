<?php
/**
 * VoteSystem — Layout Config
 * =====================================================================
 * Edite APENAS este arquivo para mudar título, favicon, nome do site
 * e rodapé em TODAS as páginas (login, vote, admin).
 * =====================================================================
 */

// ── Título do site (aparece na aba do navegador) ──────────────────────
define('LAYOUT_SITE_NAME',   'VoteSystem');        // nome principal
define('LAYOUT_SITE_SUFFIX', '4Top Servers');      // sufixo após o —

// ── Favicon ───────────────────────────────────────────────────────────
// Coloque seu favicon em assets/ e ajuste o caminho abaixo.
// Use '' para desativar.
define('LAYOUT_FAVICON', 'assets/favicon.png');

// ── Ícone e nome no navbar ────────────────────────────────────────────
define('LAYOUT_BRAND_ICON', '⚔');
define('LAYOUT_BRAND_URL',  'vote.php');

// ── Rodapé ────────────────────────────────────────────────────────────
define('LAYOUT_FOOTER', 'VoteSystem <span class="text-gold">4Top Servers</span>');

// ── Links extras no menu (opcional) ──────────────────────────────────
$GLOBALS['LAYOUT_NAV_LINKS'] = array(
    // array('label' => '🌐 Site',    'url' => 'https://meuserver.com', 'target' => '_blank'),
    // array('label' => '💬 Discord', 'url' => 'https://discord.gg/...', 'target' => '_blank'),
);

// ── CSS extra (opcional) ──────────────────────────────────────────────
define('LAYOUT_EXTRA_CSS', '
/* cole seu CSS customizado aqui */
');
