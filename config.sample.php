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
define('DB_NAME',       'DARKEDEN');

// Opções: 'darkeden_master' | 'darkeden_v2'
define('GAME_PROJECT', 'darkeden_master');

// Opcional: sobrescreve o padrão do projeto. Não detecta nem altera senhas existentes.
// 'plain' | 'mysql_old' (DB_VERSION 4) | 'mysql_password' (MySQL 4.1+)
// define('DK_PASSWORD_MODE', 'mysql_old');

// Contas existentes em Player; nenhuma conta recebe admin automaticamente.
define('DK_ADMIN_ACCOUNTS', array());

// ServerCount de TODOS os gameservers precisa ser igual ao passo abaixo.
// Reserve o slot 1 exclusivamente ao painel; nenhum gameserver pode usar ServerID 1.
// Exemplo: ServerCount 2 / ServerID 0 no jogo; reinicie os gameservers após configurar.
define('DK_ITEM_ID_STEP', 2);
define('DK_ITEM_ID_SLOT', 1);

// Lista opcional de proxies/CDNs confiáveis para resolver o IP real do cliente.
// Ex.: array('173.245.48.0/20', '103.21.244.0/22', '127.0.0.1');
define('VS_TRUSTED_PROXY_CIDRS', array());

// Anticheat / VPN / Proxy detection
define('VS_ANTICHEAT_ENABLED', true);
define('VS_ANTICHEAT_RISK_BLOCK', 70);
define('VS_ANTICHEAT_CACHE_SEC', 900);
define('VS_ANTICHEAT_IPAPI_TIMEOUT', 4);

define('INSTALLED',     true);
