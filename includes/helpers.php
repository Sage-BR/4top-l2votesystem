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

define('VOTEAPI_CDN', 'https://cdn.4teambr.com/votesystem/voteapi.php');

// Mapa arquivo → identificador aceito pelo CDN
function getTopKey($btn) {
    static $map = array(
        'l2jbrasil.php'   => 'l2jbrasil',
        '4top.php'        => '4top',
        'hopzone.php'     => 'hopzone',
        'hopzoneu.php'    => 'hopzoneu',
        'itopz.php'       => 'itopz',
        'l2toporg.php'    => 'l2toporg',
        'arenatop100.php' => 'arenatop100',
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

    /**
     * Tops que não possuem API de consulta — só postback/callback.
     * Para esses, o claim é aceito sem verificação (jogador clicou = confirmado).
     */
    private static $noCheckApi = array('arenatop100');

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
            return $this->ok((int)(isset($data['voteTime']) ? $data['voteTime'] : 0));
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
        $url = VOTEAPI_CDN . '?' . http_build_query($params);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
            ));
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err || $body === false) return null;
        } else {
            $ctx  = stream_context_create(array('http' => array('timeout' => $this->timeout)));
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) return null;
        }

        $data = @json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
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
 * Tenta getVoteUrl() da API primeiro; fallback para campo url do banco.
 */
function getTopVoteUrl($top, $login = '') {
    if (!empty($top['top_btn'])) {
        $api = loadTopApi($top);
        if ($api && method_exists($api, 'getVoteUrl')) {
            return $api->getVoteUrl($login);
        }
    }
    return isset($top['url']) ? (string)$top['url'] : '#';
}

/**
 * Lista tops disponíveis buscando do CDN; fallback estático se CDN falhar.
 */
function getAvailableTops() {
    $url  = VOTEAPI_CDN . '?action=list_tops';
    $body = false;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ));
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx  = stream_context_create(array('http' => array('timeout' => 8)));
        $body = @file_get_contents($url, false, $ctx);
    }

    if ($body) {
        $data = @json_decode($body, true);
        if (!empty($data['tops']) && is_array($data['tops'])) {
            $tops = $data['tops'];
            uksort($tops, function($a, $b) use ($tops) {
                if ($a === '4top.php') return -1;
                if ($b === '4top.php') return  1;
                return strcmp($tops[$a]['name'], $tops[$b]['name']);
            });
            return $tops;
        }
    }

    // Fallback estático
    return array(
        '4top.php'        => array('name' => '4TOP',         'site' => 'top.4teambr.com',  'token' => false),
        'hopzone.php'     => array('name' => 'Hopzone.net',  'site' => 'l2.hopzone.net',    'token' => true),
        'hopzoneu.php'    => array('name' => 'Hopzone.eu',   'site' => 'hopzone.eu',         'token' => true),
        'itopz.php'       => array('name' => 'iTopZ',        'site' => 'itopz.com',          'token' => true),
        'l2jbrasil.php'   => array('name' => 'L2JBrasil',   'site' => 'top.l2jbrasil.com', 'token' => true),
        'l2toporg.php'    => array('name' => 'L2Top.org',   'site' => 'l2top.org',          'token' => true),
        'arenatop100.php' => array('name' => 'ArenaTop100', 'site' => 'arena-top100.com',   'token' => true),
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
    $stmt = $db->prepare(
        "SELECT *, TIMESTAMPDIFF(SECOND, voted_at, NOW()) AS seconds_ago
         FROM 4top_log
         WHERE login = ? AND top_id = ?
         ORDER BY voted_at DESC LIMIT 1"
    );
    $stmt->execute(array($login, $top_id));
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Total de votos do jogador (todos os tops, sem limite de data).
 */
function countVotes($login) {
    $db   = getDB();
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
    if (hasVotedRecently($login, $top_id)) return 'cooldown';

    $db = getDB();
    try {
        $stmt = $db->prepare(
            "INSERT INTO 4top_log (login, ip, top_id, voted_at, rewarded)
             VALUES (?, ?, ?, NOW(), 0)"
        );
        $stmt->execute(array($login, $ip, $top_id));
        return 'ok';
    } catch (Exception $e) {
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
function checkVotes($login, $ip) {
    $db = getDB();

    // Cooldown de claim
    $chk = $db->prepare(
        "SELECT claimed_at FROM 4top_reward_claims
         WHERE login = ? AND claimed_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
         ORDER BY claimed_at DESC LIMIT 1"
    );
    $chk->execute(array($login));
    if ($chk->fetch()) {
        return array('status' => 'cooldown', 'msg' => '⏳ Você já coletou sua recompensa nas últimas 12 horas.');
    }

    // Checa cada top via CDN
    $tops      = getTops();
    $missing   = array();
    $confirmed = array();

    foreach ($tops as $t) {
        $api = loadTopApi($t);
        if ($api) {
            $result = $api->checkVote($ip, $login);
            if (!$result->error && $result->voted) {
                $confirmed[$t['id']] = $result->voteTime > 0 ? $result->voteTime : time();
            } else {
                $missing[] = htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8');
            }
        } else {
            $confirmed[$t['id']] = time();
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

    // Armazena os confirmados na sessão para o claim usar
    startSession();
    $_SESSION['vs_confirmed_votes'] = $confirmed;
    $_SESSION['vs_confirmed_ip']    = $ip;

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
function claimReward($login, $objId) {
    startSession();

    // Valida que checkVotes() foi chamado antes
    if (empty($_SESSION['vs_confirmed_votes']) || empty($_SESSION['vs_confirmed_ip'])) {
        return array('status' => 'error', 'msg' => '❌ Verificação de votos expirada. Clique em Verificar Votos novamente.');
    }

    $confirmed = $_SESSION['vs_confirmed_votes'];
    $ip        = $_SESSION['vs_confirmed_ip'];

    // Valida que o personagem pertence à conta
    if (!gameCharBelongsTo($login, $objId)) {
        return array('status' => 'error', 'msg' => '❌ Personagem inválido.');
    }

    $db = getDB();

    // Double-check cooldown
    $chk = $db->prepare(
        "SELECT claimed_at FROM 4top_reward_claims
         WHERE login = ? AND claimed_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
         ORDER BY claimed_at DESC LIMIT 1"
    );
    $chk->execute(array($login));
    if ($chk->fetch()) {
        return array('status' => 'cooldown', 'msg' => '⏳ Você já coletou sua recompensa nas últimas 12 horas.');
    }

    try {
        $db->beginTransaction();

        // Registra votos confirmados
        $stmtLog = $db->prepare(
            "INSERT INTO 4top_log (login, ip, top_id, voted_at, rewarded)
             VALUES (?, ?, ?, NOW(), 0)"
        );
        foreach ($confirmed as $top_id => $voteTime) {
            if (!hasVotedRecently($login, (int)$top_id)) {
                $stmtLog->execute(array($login, $ip, (int)$top_id));
            }
        }

        // Registra o claim
        $db->prepare(
            "INSERT INTO 4top_reward_claims (login, claimed_at) VALUES (?, NOW())"
        )->execute(array($login));

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
        unset($_SESSION['vs_confirmed_votes'], $_SESSION['vs_confirmed_ip']);

        return array('status' => 'ok', 'msg' => '🎁 Recompensa entregue com sucesso!');

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[VoteSystem] claimReward error: ' . $e->getMessage());
        return array('status' => 'error', 'msg' => '❌ Erro ao entregar recompensa. Tente novamente.');
    }
}

// ── Log admin ─────────────────────────────────────────────────────────────────

function getVoteLog($limit = 50, $offset = 0) {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT l.*, t.name AS top_name
         FROM 4top_log l
         LEFT JOIN 4top_tops t ON t.id = l.top_id
         ORDER BY l.voted_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array((int)$limit, (int)$offset));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    header('Location: ' . $url . '?' . urlencode($msg_key) . '=' . urlencode($msg_val));
    exit;
}