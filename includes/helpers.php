<?php
/**
 * VoteSystem — Helpers (lógica do sistema de votação)
 *
 * Responsabilidades:
 *   - Loader de API de top (CDN 4teambr)
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

// API local - voteapi.php no mesmo servidor
define('VOTEAPI_LOCAL', 'voteapi.php');

// Mapa arquivo → identificador aceito pelo CDN
function getTopKey($btn) {
    static $map = array(
        'l2jbrasil.php'   => 'l2jbrasil',
        '4top.php'        => '4top',
        'l2toporg.php'    => 'l2toporg',
        'l2network.php'   => 'l2network',
    );
    return isset($map[$btn]) ? $map[$btn] : null;
}

/**
 * Retorna um RemoteTopApi que delega checkVote/getVoteUrl ao CDN 4teambr.
 * Retorna null se o top_btn não for reconhecido.
 */
function loadTopApi($top) {
    $btn    = basename((string)(isset($top['top_btn']) ? $top['top_btn'] : ''));
    $topKey = getTopKey($btn);
    if (!$topKey) return null;

    $token    = (string)(isset($top['token'])  ? $top['token']  : '');
    $serverId = (string)(isset($top['top_id']) ? $top['top_id'] : '');

    return new RemoteTopApi($topKey, $token, $serverId);
}

/**
 * Proxy HTTP para o voteapi.php do CDN.
 * Interface pública: checkVote($ip, $login) e getVoteUrl($login).
 */
class RemoteTopApi {

    private $topKey;
    private $token;
    private $serverId;
    private $timeout = 15;

    public function __construct($topKey, $token, $serverId) {
        $this->topKey   = $topKey;
        $this->token    = $token;
        $this->serverId = $serverId;
    }

    private static $noCheckApi = array();

    public function checkVote($ip, $login = '') {
        // Tops sem API de check: aceita direto sem chamar o CDN
        if (in_array($this->topKey, self::$noCheckApi, true)) {
            return $this->ok(0);
        }

        $data = $this->call(array(
            'top'       => $this->topKey,
            'server_id' => $this->serverId,
            'token'     => $this->token,
            'ip'        => $ip,
            'login'     => $login,
            'action'    => 'check',
        ));

        if ($data === null) return $this->fail('CDN inacessível');

        // CDN retorna error como booleano true/false
        if (isset($data['error']) && $data['error'] === true) {
            return $this->fail(isset($data['message']) ? $data['message'] : 'Erro no CDN');
        }

        // voted é booleano true/false
        if (isset($data['voted']) && $data['voted'] === true) {
            $vt = isset($data['voteTime']) ? (int)$data['voteTime'] : 0;
            // Se voteTime parece um IP (muito maior que timestamp UNIX), usa time()
            if ($vt > 5000000000) { $vt = 0; }
            return $this->ok($vt);
        }

        return $this->notVoted(isset($data['message']) ? $data['message'] : 'Não votou');
    }

    public function getVoteUrl($login = '') {
        $data = $this->call(array(
            'top'       => $this->topKey,
            'server_id' => $this->serverId,
            'token'     => $this->token,
            'login'     => $login,
            'action'    => 'vote_url',
        ));
        return ($data && isset($data['voteUrl'])) ? $data['voteUrl'] : '#';
    }

    private function call($params) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';

        // Remove porta, extrai apenas o hostname
        $hostname = strtolower(parse_url('http://' . $host, PHP_URL_HOST) ?: '');
        // Rejeita caracteres perigosos (SSRF básico) mas permite hostname normal
        if ($hostname === '' || preg_match('/[<>"\'\\s]/', $hostname)) {
            $hostname = '127.0.0.1';
        }

        $port     = (int)($_SERVER['SERVER_PORT'] ?? 80);
        $portSuffix = ($port === 80 || $port === 443) ? '' : ':' . $port;

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/vote.php';
        $baseDir    = rtrim(dirname($scriptName), '/\\');
        if ($baseDir === '.' || $baseDir === '\\') $baseDir = '';

        $query  = http_build_query($params);

        // Tenta com HTTP_HOST original (funciona na maioria dos servidores)
        // verifyHost=true: certificado TLS precisa bater com o hostname
        $url  = $scheme . '://' . $hostname . $baseDir . '/voteapi.php?' . $query;
        $body = $this->httpGet($url, $hostname !== '127.0.0.1');

        // Fallback: 127.0.0.1 (virtual hosts que rejeitam hostname externo)
        if ($body === null) {
            $url  = $scheme . '://127.0.0.1' . $portSuffix . $baseDir . '/voteapi.php?' . $query;
            $body = $this->httpGet($url, false);
        }

        if ($body === null || trim($body) === '') return null;
        $data = @json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }

    private function httpGet($url, $verifyHost = true) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => $this->timeout, 'ignore_errors' => true),
            'ssl'  => array('verify_peer' => true, 'verify_peer_name' => $verifyHost),
        ));
        return @file_get_contents($url, false, $ctx) ?: null;
    }

    private function ok($voteTime = 0) {
        $r = new stdClass(); $r->voted = true;  $r->error = false;
        $r->message = 'Votou';      $r->voteTime = $voteTime; return $r;
    }
    private function notVoted($msg = 'Não votou') {
        $r = new stdClass(); $r->voted = false; $r->error = false;
        $r->message = $msg;         $r->voteTime = 0; return $r;
    }
    private function fail($msg = 'Erro') {
        $r = new stdClass(); $r->voted = false; $r->error = true;
        $r->message = $msg;         $r->voteTime = 0; return $r;
    }
}

/**
 * Retorna a URL de voto do player para o top.
 * Tenta getVoteUrl() da API primeiro; fallback para URL do banco.
 */
function getTopVoteUrl($top, $login = '') {
    $apiUrl = '#';
    if (!empty($top['top_btn'])) {
        $api = loadTopApi($top);
        if ($api && method_exists($api, 'getVoteUrl')) {
            $apiUrl = $api->getVoteUrl($login);
        }
    }
    // Fallback para URL do banco se API falhar ou retornar '#'
    $dbUrl = isset($top['url']) ? trim($top['url']) : '';
    $url = ($apiUrl && $apiUrl !== '#') ? $apiUrl : ($dbUrl ?: '#');

    // Corrige parâmetro dos tops que usam &u= em vez de &s=
    if (!empty($top['top_btn']) && in_array($top['top_btn'], array('4top.php', 'l2jbrasil.php'), true)) {
        $url = preg_replace('/\ba=in&s=/i', 'a=in&u=', $url);
    }

    return $url;
}

/**
 * Lista tops disponíveis buscando do CDN; fallback estático se CDN falhar.
 */
function ensureVoteSchema() {
    try {
        $db = getDB();
        $tables = array(
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
        $stmt->execute(array('anticheat_enabled'));
        if ($stmt->fetchColumn() === false) {
            setSetting('anticheat_enabled', '1');
        }
    } catch (Throwable $e) {
        error_log('[VoteSystem] ensureVoteSchema error: ' . $e->getMessage());
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
        '4top.php'        => array('name' => '4TOP ★',      'site' => 'top.4teambr.com',   'token' => false, 'featured' => true,  'register_url' => 'https://top.4teambr.com/addserver.php'),
        'l2jbrasil.php'   => array('name' => 'L2JBrasil ★', 'site' => 'top.l2jbrasil.com', 'token' => true,  'featured' => true,  'register_url' => 'https://top.l2jbrasil.com/index.php?a=add'),
        'l2toporg.php'    => array('name' => 'L2Top.org ★', 'site' => 'l2top.org',         'token' => true,  'featured' => true,  'register_url' => 'https://l2top.org/add-server/'),
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
function getTops() {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT * FROM 4top_tops WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Todos os tops (inclusive desativados) — usado pelo admin. */
function getAllTops() {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT * FROM 4top_tops ORDER BY sort_order ASC, id ASC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Rewards — banco de dados ──────────────────────────────────────────────────

function getRewards() {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM 4top_rewards ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Cooldown e log de votos ───────────────────────────────────────────────────

/**
 * Verifica se o jogador votou neste top nas últimas 12 horas.
 */
function hasVotedRecently($login, $top_id) {
    $db   = getDB();
    $login = trim((string)$login);
    $stmt = $db->prepare(
        "SELECT id FROM 4top_log
         WHERE login = ? AND top_id = ?
           AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
         LIMIT 1"
    );
    $stmt->execute(array($login, $top_id));
    return (bool)$stmt->fetch();
}

/**
 * Retorna o último voto do jogador neste top, com seconds_ago calculado.
 */
function getLastVote($login, $top_id) {
    $db   = getDB();
    $login = trim((string)$login);
    $stmt = $db->prepare(
        "SELECT *, TIMESTAMPDIFF(SECOND, voted_at, NOW()) AS seconds_ago
         FROM 4top_log
         WHERE login = ? AND top_id = ?
         ORDER BY voted_at DESC
         LIMIT 1"
    );
    $stmt->execute(array($login, $top_id));
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
 * Consulta o CDN e verifica se o jogador votou em todos os tops.
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

    // Checa cada top via CDN
    $tops      = getTops();
    $missing   = array();
    $confirmed = array();

    foreach ($tops as $t) {
        $voted = false;
        $voteTime = 0;

        // 1. Tenta o Check Local por login — evita depender de IP para confirmar voto
        $localVote = getLastVote($login, $t['id']);
        if ($localVote && $localVote['seconds_ago'] < 43200) {
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
    $_SESSION['vs_confirmed_votes'] = $confirmed;
    if ($hwid) {
        $_SESSION['vs_confirmed_hwid'] = $hwid;
    }

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
        return array('status' => 'error', 'msg' => '❌ Verificação de votos expirada. Clique em Verificar Votos novamente.');
    }

    $confirmed = $_SESSION['vs_confirmed_votes'];

    // Valida que o personagem pertence à conta
    if (!gameCharBelongsTo($login, $objId)) {
        return array('status' => 'error', 'msg' => '❌ Personagem inválido.');
    }

    $db = getDB();

    try {
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
        foreach ($confirmed as $top_id => $voteTime) {
            if (!hasVotedRecently($login, (int)$top_id)) {
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
        $db->prepare(
            "UPDATE 4top_log SET rewarded = 1, rewarded_at = NOW()
             WHERE login = ? AND rewarded = 0
               AND voted_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)"
        )->execute(array($login));

        $db->commit();

        // Limpa sessão
        unset($_SESSION['vs_confirmed_votes']);
        unset($_SESSION['vs_confirmed_hwid']);

        return array('status' => 'ok', 'msg' => '🎁 Recompensa entregue com sucesso!');

    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[VoteSystem] claimReward error: ' . $e->getMessage());
        return array('status' => 'error', 'msg' => '❌ Erro ao entregar recompensa. Tente novamente.');
    }
}

// ── Log admin ─────────────────────────────────────────────────────────────────

function getVoteLog($limit = 50, $offset = 0) {
    $db   = getDB();
    // Agrupa por login + ip + dia — representa uma sessão de votação real
    // Jogador que vota em vários tops no mesmo dia aparece em uma linha só
    $stmt = $db->prepare(
        "SELECT
            login,
            ip,
            MIN(voted_at)  AS voted_at,
            MAX(rewarded)  AS rewarded,
            GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tops_voted,
            COUNT(*)       AS total_tops
         FROM 4top_log l
         LEFT JOIN 4top_tops t ON t.id = l.top_id
         GROUP BY login, ip, DATE(voted_at)
         ORDER BY voted_at DESC
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
