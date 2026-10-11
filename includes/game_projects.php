<?php
/** Perfis dos servidores DarkEden suportados por esta edição. */
function gameProjects() {
    return array(
        'darkeden_master' => array('name' => 'DarkEden Server Master', 'icon' => '&#9876;', 'desc' => 'server-master: Player, Slayer/Vampire/Ousters e entrega na bag', 'pass' => 'Senha direta (padrão do exemplo)', 'password_mode' => 'plain'),
        'darkeden_v2' => array('name' => 'DarkEden v2', 'icon' => '&#9876;', 'desc' => 'darkeden-v2-serverfiles: Player e entrega na bag nativa', 'pass' => 'MySQL OLD_PASSWORD (DB_VERSION 4)', 'password_mode' => 'mysql_old'),
    );
}
function gamePasswordMode() {
    $projects = gameProjects();
    if (!defined('GAME_PROJECT') || !isset($projects[GAME_PROJECT])) throw new RuntimeException('Perfil DarkEden inválido.');
    $mode = defined('DK_PASSWORD_MODE') ? DK_PASSWORD_MODE : $projects[GAME_PROJECT]['password_mode'];
    if (!in_array($mode, array('plain', 'mysql_old', 'mysql_password'), true)) throw new RuntimeException('Formato de senha DarkEden inválido.');
    return $mode;
}