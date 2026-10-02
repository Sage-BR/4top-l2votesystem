<?php
/**
 * VoteSystem — Helpers (lógica do sistema de votação)
 *
 * Responsabilidades:
 *   - Loader local de APIs de tops
 *   - CRUD de tops e rewards no banco
 *   - Cooldown, log e registro de votos
 *   - Entrega de recompensa (delega para core.php)
 *   - Log admin
 *   - Utilitários de display
 *
 * Depende de: db.php, core.php
 * Compatível: PHP 5.6 ~ 8.2
 */

// ── CDN 4teambr ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/hopzoneeu.php';
require_once __DIR__ . '/postbacktops.php';
require_once __DIR__ . '/top_handlers.php';

// Mapa arquivo → identificador aceito pelo CDN
function getTopKey($btn) {
    static $map = array(
        'mmtop200.php' => 'mmtop200',
        'gamingtop100.php' => 'gamingtop100',
        'top100arena.php' => 'top100arena',
        'l2jbrasil.php'   => 'l2jbrasil',
        '4top.php'        => '4top',
        'l2toporg.php'    => 'l2toporg',
        'l2network.php'   => 'l2network',
    );
    return isset($map[$btn]) ? $map[$btn] : null;
}

/**
 * Retorna um adaptador que chama as classes de API diretamente no mesmo processo.
 * Retorna null se o top_btn não for reconhecido.
 */
function loadTopApi($top) {
    $btn    = basename((string)(isset($top['top_btn']) ? $top['top_btn'] : ''));
    if ($btn === 'hopzoneu.php') return new HopzoneEuApi($top);
    if (postbackTopSupported($btn)) return new PostbackTopApi($top);
    $topKey = getTopKey($btn);
    if (!$topKey) return null;

    $token    = (string)(isset($top['token'])  ? $top['token']  : '');
    $serverId = (string)(isset($top['top_id']) ? $top['top_id'] : '');

    return new RemoteTopApi($topKey, $token, $serverId);
}

/**
 * Adaptador local: mantém a interface antiga sem requisições HTTP ao próprio painel.
 * Interface pública: checkVote($ip, $login) e getVoteUrl($login).
 */
class RemoteTopApi {
    private $handler;
    public function __construct($topKey, $token, $serverId) {
        $this->handler = buildHandler($topKey, $token, $serverId);
    }
    public function checkVote($ip, $login = '') {
        return $this->handler ? $this->handler->checkVote($ip, $login) : TopResult::fail('Top não suportado');
    }
    public function getVoteUrl($login = '') {
        return $this->handler ? $this->handler->getVoteUrl($login) : '#';
    }
}

/**
 * Retorna a URL de voto do player para o top.
 * Monta a URL direta garantindo parâmetros de identificação (login / player_id MD5).
 */
function getTopVoteUrl($top, $login = '') {
    $btn      = !empty($top['top_btn']) ? basename($top['top_btn']) : '';
    $serverId = (string)(isset($top['top_id']) ? $top['top_id'] : '');
    $login    = trim((string)$login);
    if (postbackTopSupported($btn)) return postbackTopVoteUrl($top, $login);

    switch ($btn) {
        case 'hopzoneu.php':
            return (new HopzoneEuApi($top))->getVoteUrl($login);
        case '4top.php':
            $url = 'https://top.4teambr.com/index.php?a=in&u=' . urlencode($serverId);
            if ($login !== '') $url .= '&login=' . urlencode($login);
            return $url;

        case 'l2jbrasil.php':
            $playerId = ($login !== '') ? md5($login) : '';
            return 'https://top.l2jbrasil.com/index.php?a=in&u=' . urlencode($serverId) . '&player_id=' . urlencode($playerId);

        case 'l2toporg.php':
            return 'https://l2top.org/server/' . urlencode($serverId) . '/vote/' . ($login !== '' ? urlencode($login) . '/' : '');

        case 'l2network.php':
            return 'https://l2network.eu/index.php?a=in&u=' . urlencode($serverId) . '&id=' . urlencode($login ?: $serverId);

    }

    // Fallback genérico para outros tops
    $apiUrl = '#';
    if (!empty($top['top_btn'])) {
        $api = loadTopApi($top);
        if ($api && method_exists($api, 'getVoteUrl')) {
            $apiUrl = $api->getVoteUrl($login);
        }
    }
    $dbUrl = isset($top['url']) ? trim($top['url']) : '';
    return ($apiUrl && $apiUrl !== '#') ? $apiUrl : ($dbUrl ?: '#');
}

/**
 * Lista tops disponíveis buscando do CDN; fallback estático se CDN falhar.
 */
function ensureVoteSchema() {
    $schemaLocked = false;
    try {
        $db = getDB();
        if ((string)getSetting('schema_version', '0') === '7') return true;
        $schemaLock = $db->query("SELECT GET_LOCK('vs_schema_migration', 5)");
        $schemaLocked = (int)$schemaLock->fetchColumn() === 1;
        if (!$schemaLocked) return false;
        if ((string)getSetting('schema_version', '0') === '7') return true;
        $tables = array(
            '4top_postback_refs' => "CREATE TABLE IF NOT EXISTS `4top_postback_refs` (
                `id` INT NOT NULL AUTO_INCREMENT, `top_id` INT NOT NULL,
                `login` VARCHAR(45) NOT NULL, `voter_ip` VARCHAR(45) NOT NULL,
                `postback_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`), UNIQUE KEY `idx_top_login` (`top_id`, `login`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_hopzone_votes' => "CREATE TABLE IF NOT EXISTS `4top_hopzone_votes` (
                `top_id` INT NOT NULL, `login` VARCHAR(45) NOT NULL,
                `vote_id` VARCHAR(19) NOT NULL, `vote_url` VARCHAR(500) NOT NULL,
                `config_hash` CHAR(64) NOT NULL, `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`top_id`, `login`), UNIQUE KEY `idx_top_vote` (`top_id`, `vote_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_tops' => "CREATE TABLE IF NOT EXISTS `4top_tops` (
                    `id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL,
                    `top_id` VARCHAR(200) NOT NULL, `token` VARCHAR(500) DEFAULT NULL,
                    `url` VARCHAR(500) DEFAULT NULL, `top_btn` VARCHAR(50) DEFAULT NULL,
                    `api_url` VARCHAR(500) DEFAULT NULL, `enabled` TINYINT(1) DEFAULT 1,
                    `sort_order` INT DEFAULT 0, PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_rewards' => "CREATE TABLE IF NOT EXISTS `4top_rewards` (
                    `id` INT NOT NULL AUTO_INCREMENT, `item_id` INT NOT NULL,
                    `quantity` INT NOT NULL DEFAULT 1, `description` VARCHAR(200) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_log' => "CREATE TABLE IF NOT EXISTS `4top_log` (
                    `id` INT NOT NULL AUTO_INCREMENT, `login` VARCHAR(45) NOT NULL,
                    `ip` VARCHAR(45) NOT NULL, `top_id` INT NOT NULL,
                    `voted_at` DATETIME NOT NULL, `rewarded` TINYINT(1) DEFAULT 0,
                    `rewarded_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`), INDEX `idx_login_top` (`login`,`top_id`), INDEX `idx_ip` (`ip`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'4top_reward_claims' => "CREATE TABLE IF NOT EXISTS `4top_reward_claims` (
        `id` INT NOT NULL AUTO_INCREMENT, `login` VARCHAR(45) NOT NULL,
        `claimed_at` DATETIME NOT NULL, `hwid` VARCHAR(128) DEFAULT NULL,
        PRIMARY KEY (`id`), INDEX `idx_login` (`login`), INDEX `idx_hwid` (`hwid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_anticheat_log' => "CREATE TABLE IF NOT EXISTS `4top_anticheat_log` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `login` VARCHAR(45) DEFAULT NULL,
                    `ip` VARCHAR(45) NOT NULL,
                    `risk` TINYINT NOT NULL DEFAULT 0,
                    `reason` VARCHAR(255) DEFAULT NULL,
                    `source` VARCHAR(80) DEFAULT NULL,
                    `blocked` TINYINT(1) NOT NULL DEFAULT 0,
                    `signals` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_login` (`login`),
                    INDEX `idx_ip` (`ip`),
                    INDEX `idx_blocked_created` (`blocked`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            '4top_settings' => "CREATE TABLE IF NOT EXISTS `4top_settings` (
                    `setting_key` VARCHAR(80) NOT NULL,
                    `setting_value` TEXT DEFAULT NULL,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );

        foreach ($tables as $table => $sql) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute(array($table));
            $exists = (int)$stmt->fetchColumn() > 0;
            if (!$exists) {
                $db->exec($sql);
            }
        }

        $postbackColumn = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '4top_postback_refs' AND COLUMN_NAME = 'postback_at'");
        if ((int)$postbackColumn->fetchColumn() === 0) {
            $db->exec('ALTER TABLE 4top_postback_refs ADD COLUMN postback_at DATETIME DEFAULT NULL');
        }

        // Migration: adiciona coluna hwid em 4top_reward_claims se não existir
        $chk = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '4top_reward_claims' AND COLUMN_NAME = 'hwid'"
        );
        $chk->execute();
        if ((int)$chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE `4top_reward_claims` ADD COLUMN `hwid` VARCHAR(128) DEFAULT NULL, ADD INDEX `idx_hwid` (`hwid`)");
        }

        // Migration: corrige parâmetro das URLs (&s= → &u=) para tops que usam &u=
        $db->exec("UPDATE 4top_tops SET url = REPLACE(url, 'a=in&s=', 'a=in&u=') WHERE top_btn IN ('4top.php','l2jbrasil.php') AND url LIKE '%a=in&s=%'");

        $stmt = $db->prepare("SELECT setting_value FROM 4top_settings WHERE setting_key = ? LIMIT 1");
        // Remove o cadastro da integração descontinuada; mantém o histórico de votos.
        $removedTop = $db->prepare('DELETE FROM 4top_tops WHERE top_btn = ?');
        $removedTop->execute(array('ragezone.php'));

        $stmt->execute(array('anticheat_enabled'));
        if ($stmt->fetchColumn() === false) {
            setSetting('anticheat_enabled', '1');
        }
        $indexes = array(
            '4top_log' => array('idx_login_top_date' => 'login, top_id, voted_at', 'idx_ip_top_date' => 'ip, top_id, voted_at', 'idx_vote_date' => 'voted_at'),
            '4top_reward_claims' => array('idx_login_claim_date' => 'login, claimed_at', 'idx_hwid_claim_date' => 'hwid, claimed_at'),
            '4top_anticheat_log' => array('idx_created_id' => 'created_at, id'),
        );
        foreach ($indexes as $table => $definitions) {
            $existing = $db->prepare('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $existing->execute(array($table));
            $names = $existing->fetchAll(PDO::FETCH_COLUMN);
            $additions = array();
            foreach ($definitions as $name => $columns) {
                if (!in_array($name, $names, true)) $additions[] = "ADD INDEX `$name` ($columns)";
            }
            if ($additions) $db->exec("ALTER TABLE `$table` " . implode(', ', $additions));
        }
        if (!setSetting('schema_version', '7')) return false;
        return true;
    } catch (Throwable $e) {
        error_log('[VoteSystem] ensureVoteSchema error: ' . $e->getMessage());
        return false;
    } finally {
        if ($schemaLocked) $db->query("SELECT RELEASE_LOCK('vs_schema_migration')");
    }
}

function getSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM 4top_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(array((string)$key));
        $value = $stmt->fetchColumn();
        if ($value === false) return $default;
        return $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO 4top_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute(array((string)$key, is_bool($value) ? ($value ? '1' : '0') : (string)$value));
        return true;
    } catch (Throwable $e) {
        error_log('[VoteSystem] setSetting error: ' . $e->getMessage());
        return false;
    }
}

function logAnticheatDetection(array $data) {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO 4top_anticheat_log (login, ip, risk, reason, source, blocked, signals, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $signals = null;
        if (!empty($data['signals'])) {
            $signals = is_array($data['signals']) ? json_encode($data['signals'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string)$data['signals'];
        }

        $stmt->execute(array(
            isset($data['login']) ? trim((string)$data['login']) : null,
            isset($data['ip']) ? trim((string)$data['ip']) : '',
            isset($data['risk']) ? (int)$data['risk'] : 0,
            isset($data['reason']) ? (string)$data['reason'] : null,
            isset($data['source']) ? (string)$data['source'] : null,
            !empty($data['blocked']) ? 1 : 0,
            $signals,
        ));
        return true;
    } catch (Throwable $e) {
        error_log('[VoteSystem] logAnticheatDetection error: ' . $e->getMessage());
        return false;
    }
}

function getAvailableTops() {
    return array(
        'mmtop200.php' => array('name' => 'MMTop200', 'site' => 'mmtop200.com', 'token' => true, 'featured' => false, 'register_url' => 'https://mmtop200.com/'),
        'gamingtop100.php' => array('name' => 'GamingTop100', 'site' => 'www.gamingtop100.net', 'token' => false, 'featured' => false, 'register_url' => 'https://www.gamingtop100.net/'),
        'top100arena.php' => array('name' => 'Top100Arena', 'site' => 'www.top100arena.com', 'token' => false, 'featured' => false, 'register_url' => 'https://www.top100arena.com/'),
        'hopzoneu.php' => array('name' => 'Hopzone.eu', 'site' => 'hopzone.eu', 'token' => true, 'featured' => false, 'register_url' => 'https://hopzone.eu/'),
        '4top.php'        => array('name' => '4TOP ★',      'site' => 'top.4teambr.com',   'token' => true,  'featured' => true,  'register_url' => 'https://top.4teambr.com/addserver.php'),
        'l2jbrasil.php'   => array('name' => 'L2JBrasil', 'site' => 'top.l2jbrasil.com', 'token' => true,  'featured' => false,  'register_url' => 'https://top.l2jbrasil.com/index.php?a=add'),
        'l2toporg.php'    => array('name' => 'L2Top.org', 'site' => 'l2top.org',         'token' => true,  'featured' => false,  'register_url' => 'https://l2top.org/add-server/'),
        'l2network.php'   => array('name' => 'L2Network',   'site' => 'l2network.eu',      'token' => true,  'featured' => false, 'register_url' => 'https://l2network.eu/add-server'),
    );
}

// ── Tops — banco de dados ─────────────────────────────────────────────────────

/** Retorna true se o 4TOP está cadastrado e ativo (obrigatório). */
function has4Top() {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id FROM 4top_tops WHERE top_btn = '4top.php' AND enabled = 1 LIMIT 1"
    );
    $stmt->execute();
    return (bool)$stmt->fetch();
}

/** Tops ativos ordenados. */
function normalizeTopNames($tops) {
    foreach ($tops as &$top) {
        if ($top['top_btn'] !== '4top.php') $top['name'] = trim(str_replace('★', '', $top['name']));
    }
    unset($top);
    return $tops;
}
function getTops() {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT * FROM 4top_tops WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"
    );
    return normalizeTopNames($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** Todos os tops (inclusive desativados) — usado pelo admin. */
function getAllTops() {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT * FROM 4top_tops ORDER BY sort_order ASC, id ASC"
    );
    return normalizeTopNames($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── Rewards — banco de dados ──────────────────────────────────────────────────

function getRewards() {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM 4top_rewards ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Cooldown e log de votos ───────────────────────────────────────────────────

/**
 * Verifica se o jogador (ou seu IP) votou neste top nas últimas 12 horas.
 */
function hasVotedRecently($login, $top_id, $ip = null) {
    $db    = getDB();
    $login = trim((string)$login);
    $ip    = trim((string)$ip);

    if (!empty($ip) && $ip !== 'UNKNOWN') {
        $stmt = $db->prepare(
            "SELECT id FROM 4top_log
             WHERE (login = ? OR ip = ?) AND top_id = ?
               AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
             LIMIT 1"
        );
        $stmt->execute(array($login, $ip, $top_id));
    } else {
        $stmt = $db->prepare(
            "SELECT id FROM 4top_log
             WHERE login = ? AND top_id = ?
               AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
             LIMIT 1"
        );
        $stmt->execute(array($login, $top_id));
    }
    return (bool)$stmt->fetch();
}

/**
 * Retorna o último voto do jogador (ou de seu IP) neste top, com seconds_ago calculado.
 */
function getLastVote($login, $top_id, $ip = null) {
    $db    = getDB();
    $login = trim((string)$login);
    $ip    = trim((string)$ip);

    if (!empty($ip) && $ip !== 'UNKNOWN') {
        $stmt = $db->prepare(
            "SELECT *, TIMESTAMPDIFF(SECOND, voted_at, NOW()) AS seconds_ago
             FROM 4top_log
             WHERE (login = ? OR ip = ?) AND top_id = ?
             ORDER BY voted_at DESC
             LIMIT 1"
        );
        $stmt->execute(array($login, $ip, $top_id));
    } else {
        $stmt = $db->prepare(
            "SELECT *, TIMESTAMPDIFF(SECOND, voted_at, NOW()) AS seconds_ago
             FROM 4top_log
             WHERE login = ? AND top_id = ?
             ORDER BY voted_at DESC
             LIMIT 1"
        );
        $stmt->execute(array($login, $top_id));
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Total de votos do jogador (todos os tops, sem limite de data).
 */
function countVotes($login) {
    $db   = getDB();
    $login = trim((string)$login);
    $stmt = $db->prepare("SELECT COUNT(*) FROM 4top_log WHERE login = ?");
    $stmt->execute(array($login));
    return (int)$stmt->fetchColumn();
}

// ── Registro de voto ──────────────────────────────────────────────────────────

/**
 * Registra um voto no log. Não entrega reward — reward só via claimReward().
 * Retorna: 'ok' | 'cooldown' | 'error'
 */
function registerVote($login, $top_id, $ip) {
    $login = trim((string)$login);
    $db = getDB();
    try {
        $db->beginTransaction();

        // SELECT FOR UPDATE — trava a linha contra race condition
        $stmt = $db->prepare(
            "SELECT id FROM 4top_log
             WHERE login = ? AND top_id = ?
               AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(array($login, $top_id));
        if ($stmt->fetch()) {
            $db->rollBack();
            return 'cooldown';
        }

        $stmt = $db->prepare(
            "INSERT INTO 4top_log (login, ip, top_id, voted_at, rewarded)
             VALUES (?, ?, ?, NOW(), 0)"
        );
        $stmt->execute(array($login, $ip, $top_id));
        $db->commit();
        return 'ok';
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VoteSystem] registerVote error: ' . $e->getMessage());
        return 'error';
    }
}

// ── Verificação de votos (etapa 1) ───────────────────────────────────────────

/**
 * Consulta registros locais e APIs dos tops, sem HTTP para o próprio painel.
 * Não entrega reward — só checa e armazena os confirmados na sessão.
 *
 * Retorna array(
 *   'status'    => 'ok'|'cooldown'|'not_voted'|'no_chars'|'error',
 *   'msg'       => '...',
 *   'chars'     => [['obj_Id' => ..., 'char_name' => ...], ...]  (só quando status=ok)
 *   'confirmed' => [top_id => voteTime, ...]                       (só quando status=ok)
 * )
 */
function checkVotes($login, $ip, $hwid = '') {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = getDB();
    $login = trim((string)$login);
    $hwid = trim((string)$hwid);

    // Cooldown de claim por login ou hwid
    $chk = $db->prepare(
        "SELECT claimed_at FROM 4top_reward_claims
         WHERE (login = ? OR (hwid IS NOT NULL AND hwid = ? AND hwid != ''))
         AND claimed_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
         ORDER BY claimed_at DESC LIMIT 1"
    );
    $chk->execute(array($login, $hwid ?: null));
    if ($chk->fetch()) {
        return array('status' => 'cooldown', 'msg' => '⏳ Você já coletou sua recompensa nas últimas 12 horas.');
    }

    // Checa cada top por seu adaptador local.
    $tops      = getTops();
    $missing   = array();
    $confirmed = array();

    foreach ($tops as $t) {
        $voted = false;
        $voteTime = 0;

        // 1. Tenta o Check Local por login — evita depender de IP para confirmar voto
        $localVote = postbackTopSupported($t['top_btn']) ? false : getLastVote($login, $t['id']);
        if (!postbackTopSupported($t['top_btn']) && $localVote && $localVote['seconds_ago'] < 43200) {
            $voted = true;
            $voteTime = (new DateTime($localVote['voted_at'], new DateTimeZone('UTC')))->getTimestamp();
        }

        // 2. Se não achou localmente, tenta a API do top
        if (!$voted) {
            $api = loadTopApi($t);
            if ($api) {
                $result = $api->checkVote($ip, $login);
                if (!$result->error && $result->voted) {
                    $voted = true;
                    $voteTime = $result->voteTime;
                }
            }
        }

        if ($voted) {
            $confirmed[$t['id']] = $voteTime > 0 ? $voteTime : time();
        } else {
            $missing[] = htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8');
        }
    }

    if (!empty($missing)) {
        return array(
            'status' => 'not_voted',
            'msg'    => '⚠ Vote em todos os tops antes de coletar. Faltam: ' . implode(', ', $missing),
        );
    }

    // Busca personagens da conta para o jogador escolher
    $chars = gameGetChars($login);
    if (empty($chars)) {
        return array('status' => 'no_chars', 'msg' => '⚠ Nenhum personagem encontrado. Crie um personagem no jogo primeiro.');
    }

    // Armazena os confirmados e hwid na sessão para o claim usar
    startSession();
    if (!isset($_SESSION['vs_login']) || $_SESSION['vs_login'] !== $login) {
        session_write_close();
        return array('status' => 'error', 'msg' => 'Sessão alterada. Faça login novamente.');
    }
    $_SESSION['vs_confirmed_votes'] = $confirmed;
    if ($hwid) {
        $_SESSION['vs_confirmed_hwid'] = $hwid;
    }
    session_write_close();

    return array(
        'status'    => 'ok',
        'msg'       => '✅ Todos os votos confirmados! Escolha o personagem para receber a recompensa.',
        'chars'     => $chars,
        'confirmed' => $confirmed,
    );
}

// ── Entrega de recompensa (etapa 2) ──────────────────────────────────────────

/**
 * Entrega a recompensa ao personagem escolhido pelo jogador.
 * Usa os votos confirmados armazenados na sessão por checkVotes().
 *
 * @param  string $login   Login da conta
 * @param  int    $objId   obj_Id do personagem escolhido
 * @return array  ('status', 'msg')
 */
function claimReward($login, $objId, $hwid = null) {
    startSession();
    $login = trim((string)$login);
    
    // HWID pode vir do POST ou da sessão (salvo no checkVotes)
    if (!empty($hwid)) {
        $hwid = trim((string)$hwid);
    } elseif (!empty($_SESSION['vs_confirmed_hwid'])) {
        $hwid = $_SESSION['vs_confirmed_hwid'];
    } else {
        $hwid = '';
    }

    // Valida que checkVotes() foi chamado antes
    if (empty($_SESSION['vs_confirmed_votes'])) {
        return array('status' => 'error', 'code' => 'verification_required', 'msg' => '❌ Verificação de votos expirada. Clique em Verificar Votos novamente.');
    }

    $confirmed = $_SESSION['vs_confirmed_votes'];
    if (!isset($_SESSION['vs_login']) || $_SESSION['vs_login'] !== $login) {
        return array('status' => 'error', 'msg' => 'Sessão inválida. Faça login novamente.');
    }
    // Preserva a autorização em caso de falha; as travas e o cooldown impedem entrega duplicada.
    session_write_close();

    // Valida que o personagem pertence à conta
    if (!gameCharBelongsTo($login, $objId)) {
        return array('status' => 'error', 'msg' => '❌ Personagem inválido.');
    }

    $db = getDB();

    $claimLocks = array(substr('vs_claim_' . hash('sha256', $login), 0, 64));
    if ($hwid !== '') $claimLocks[] = substr('vs_hwid_' . hash('sha256', $hwid), 0, 64);
    sort($claimLocks, SORT_STRING);
    $heldLocks = array();
    try {
        foreach ($claimLocks as $claimLock) {
            $lockStmt = $db->prepare('SELECT GET_LOCK(?, 5)');
            $lockStmt->execute(array($claimLock));
            if ((int)$lockStmt->fetchColumn() !== 1) return array('status' => 'error', 'msg' => 'Entrega em andamento. Tente novamente.');
            $heldLocks[] = $claimLock;
        }
        $db->beginTransaction();

        // Cooldown check dentro da transação com FOR UPDATE
        $chk = $db->prepare(
            "SELECT claimed_at FROM 4top_reward_claims
             WHERE (login = ? OR (hwid IS NOT NULL AND hwid != '' AND hwid = ?))
             AND claimed_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
             ORDER BY claimed_at DESC LIMIT 1 FOR UPDATE"
        );
        $chk->execute(array($login, $hwid ?: null));
        if ($chk->fetch()) {
            $db->rollBack();
            return array('status' => 'cooldown', 'msg' => '⏳ Você já coletou sua recompensa nas últimas 12 horas.');
        }

        // Registra votos confirmados com o timestamp real do voto
        $stmtLog = $db->prepare(
            "INSERT INTO 4top_log (login, ip, top_id, voted_at, rewarded)
             VALUES (?, ?, ?, FROM_UNIXTIME(?), 0)"
        );
        $ip = clientIp();
        $chkLog = $db->prepare(
            "SELECT id FROM 4top_log WHERE login = ? AND top_id = ? AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR) LIMIT 1 FOR UPDATE"
        );
        foreach ($confirmed as $top_id => $voteTime) {
            $chkLog->execute(array($login, (int)$top_id));
            if (!$chkLog->fetch()) {
                $voteTs = (int)$voteTime > 0 ? (int)$voteTime : time();
                $stmtLog->execute(array($login, $ip ?: 'N/A', (int)$top_id, $voteTs));
            }
        }

        // Registra o claim com HWID
        $db->prepare(
            "INSERT INTO 4top_reward_claims (login, claimed_at, hwid) VALUES (?, NOW(), ?)"
        )->execute(array($login, $hwid ?: null));

        // Entrega rewards no personagem escolhido
        $rewards = getRewards();
        if (!empty($rewards)) {
            gameDeliverRewards($login, $rewards, $db, (int)$objId);
        }

        // Marca logs como recompensados
        $confirmedTopIds = array_values(array_unique(array_filter(array_map('intval', array_keys($confirmed)), function ($id) {
            return $id > 0;
        })));
        if (!empty($confirmedTopIds)) {
            $placeholders = implode(',', array_fill(0, count($confirmedTopIds), '?'));
            $stmtRewarded = $db->prepare(
                "UPDATE 4top_log SET rewarded = 1, rewarded_at = NOW()
                 WHERE login = ? AND rewarded = 0 AND top_id IN ({$placeholders})
                   AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)"
            );
            $stmtRewarded->execute(array_merge(array($login), $confirmedTopIds));
        }

        $db->commit();

        startSession();
        if (isset($_SESSION['vs_login'], $_SESSION['vs_confirmed_votes'])
            && $_SESSION['vs_login'] === $login
            && $_SESSION['vs_confirmed_votes'] === $confirmed) {
            unset($_SESSION['vs_confirmed_votes'], $_SESSION['vs_confirmed_hwid']);
        }
        session_write_close();

        return array('status' => 'ok', 'msg' => '🎁 Recompensa entregue com sucesso!');

    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VoteSystem] claimReward error: ' . $e->getMessage());
        return array('status' => 'error', 'msg' => '❌ Erro ao entregar recompensa. Tente novamente.');
    } finally {
        foreach (array_reverse($heldLocks) as $claimLock) {
            $lockStmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $lockStmt->execute(array($claimLock));
        }
    }
}

// ── Log admin ─────────────────────────────────────────────────────────────────

function getVoteLog($limit = 50, $offset = 0) {
    $db   = getDB();
    // Entregues: uma linha por entrega. Pendentes: agrupadas por login, IP e dia.
    $stmt = $db->prepare(
        "SELECT
            login,
            COALESCE(
                MIN(CASE WHEN l.ip NOT LIKE '%:%' AND l.ip <> 'UNKNOWN' THEN l.ip END),
                MIN(l.ip)
            ) AS ip,
            CASE WHEN MAX(l.rewarded) = 1 THEN MAX(l.rewarded_at) ELSE MIN(l.voted_at) END AS voted_at,
            MAX(l.rewarded) AS rewarded,
            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN t.id IS NULL THEN CONCAT('Top removido (#', l.top_id, ')')
                    WHEN t.top_btn = '4top.php' THEN t.name
                    ELSE TRIM(REPLACE(t.name, '★', ''))
                END SEPARATOR ', '
            ) AS tops_voted,
            COUNT(DISTINCT l.top_id) AS total_tops
         FROM 4top_log l
         LEFT JOIN 4top_tops t ON t.id = l.top_id
         GROUP BY l.login,
             CASE
                 WHEN l.rewarded = 1 AND l.rewarded_at IS NOT NULL
                     THEN CONCAT('claim:', DATE_FORMAT(l.rewarded_at, '%Y-%m-%d %H:%i:%s'))
                 ELSE CONCAT('pending:', l.ip, ':', DATE(l.voted_at))
             END
         ORDER BY MAX(l.voted_at) DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array((int)$limit, (int)$offset));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAnticheatLog($limit = 50, $offset = 0) {
    try {
        $db = getDB();
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $stmt = $db->prepare(
            "SELECT id, login, ip, risk, reason, source, blocked, signals, created_at
             FROM 4top_anticheat_log
             ORDER BY created_at DESC, id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[VoteSystem] getAnticheatLog error: ' . $e->getMessage());
        return array();
    }
}



// ── Utilitários ───────────────────────────────────────────────────────────────

/** Formata segundos em HH:MM:SS */
function formatCooldown($seconds) {
    if ($seconds <= 0) return '00:00:00';
    $h = (int)floor($seconds / 3600);
    $m = (int)floor(($seconds % 3600) / 60);
    $s = (int)($seconds % 60);
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/** Escape XSS */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Redirect com parâmetro GET */
function redirectWith($url, $msg_key, $msg_val) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    header('Location: ' . $url . $sep . urlencode($msg_key) . '=' . urlencode($msg_val));
    exit;
}
