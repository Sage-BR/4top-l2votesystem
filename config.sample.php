<?php
// VoteSystem ICP Networks — Config de exemplo
// Copie para config.php e preencha com seus dados
// OU use o install.php para gerar automaticamente

define('DB_HOST',       'localhost');
define('DB_USER',       'root');
define('DB_PASS',       'sua_senha_aqui');
define('DB_NAME',       'l2jdb');

// Opções: 'acis' | 'l2jserver' | 'l2jmobius'
define('GAME_PROJECT',  'l2jserver');

// Lista opcional de proxies/CDNs confiáveis para resolver o IP real do cliente.
// Ex.: array('173.245.48.0/20', '103.21.244.0/22', '127.0.0.1');
define('VS_TRUSTED_PROXY_CIDRS', array());

// Anticheat / VPN / Proxy detection
define('VS_ANTICHEAT_ENABLED', true);
define('VS_ANTICHEAT_RISK_BLOCK', 70);
define('VS_ANTICHEAT_CACHE_SEC', 900);
define('VS_ANTICHEAT_IPAPI_TIMEOUT', 4);

define('INSTALLED',     true);
