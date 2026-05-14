<?php
/**
 * VoteSystem 4Top Servers — Config de exemplo
 *
 * ATENÇÃO: NUNCA commite o config.php real no Git!
 * Use este arquivo como template e mantenha config.php no .gitignore.
 *
 * Segurança:
 *   - Use senhas fortes e únicas para o banco de dados
 *   - Mantenha o config.php fora do repositório Git
 *   - Certifique-se de que o .htaccess protege o config.php
 *   - Remova install.php após a instalação
 */

define('DB_HOST',       'localhost');
define('DB_USER',       'root');
define('DB_PASS',       'sua_senha_aqui');
define('DB_NAME',       'l2jdb');

// Opções: 'acis' | 'l2jserver' | 'l2jmobius' | 'l2jorion' | 'l2jsunrise' | 'l2mythras' | 'l2jlisvus'
define('GAME_PROJECT',  'l2jserver');

// Lista opcional de proxies/CDNs confiáveis para resolver o IP real do cliente.
// Ex.: array('173.245.48.0/20', '103.21.244.0/22', '127.0.0.1');
define('VS_TRUSTED_PROXY_CIDRS', array());

// Anticheat / VPN / Proxy detection
define('VS_ANTICHEAT_ENABLED', true);
define('VS_ANTICHEAT_RISK_BLOCK', 70);
define('VS_ANTICHEAT_CACHE_SEC', 900);
define('VS_ANTICHEAT_IPAPI_TIMEOUT', 4);

// Nível mínimo de access_level para acesso ao admin panel
// 1 = qualquer GM, 100 = Head GM+, 200 = Admin+
// define('VS_ADMIN_ACCESS_LEVEL', 100);

define('INSTALLED',     true);
