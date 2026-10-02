<?php
require_once __DIR__ . '/includes/top_handlers.php';
// =============================================================================
// VoteSystem 4Top Servers — voteapi.php
// =============================================================================
// ESTRATÉGIA DE TEMPO:
//   Todo o sistema usa time() PHP (Unix UTC) como referência única.
//   Strings "vote_date" retornadas pelas APIs são convertidas para Unix UTC
//   usando o fuso horário CONHECIDO de cada API (constante API_TZ por classe).
//   O cliente (navegador) recebe apenas o Unix timestamp já em UTC,
//   e converte para o fuso local apenas na exibição — nunca para lógica.
// =============================================================================

// Callback compartilhado: os provedores enviam uid, user_id ou postback.
if (!isset($_GET['action']) && !isset($_GET['top'])
    && (isset($_POST['uid']) || isset($_GET['uid']) || isset($_POST['user_id']) || isset($_GET['user_id']) || isset($_POST['postback']) || isset($_GET['postback']))) {
    if (!file_exists(__DIR__ . '/.installed')) { http_response_code(404); exit; }
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/core.php';
    require_once __DIR__ . '/includes/helpers.php';
    handleVotePostback();
    exit;
}

// Ação de navegador: usa a conta autenticada, nunca um login informado na URL.
if (isset($_GET['action']) && is_string($_GET['action']) && $_GET['action'] === 'hopzone_vote') {
    if (!file_exists(__DIR__ . '/.installed')) { http_response_code(404); exit; }
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/bootstrap.php';
    requireLogin();
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    $hopzoneCsrf = isset($_GET['csrf']) && is_string($_GET['csrf']) ? $_GET['csrf'] : '';
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !verifyCsrf($hopzoneCsrf)) { http_response_code(403); exit; }
    $hopzoneId = filter_var($_GET['top_id'] ?? '', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
    if ($hopzoneId === false) { http_response_code(400); exit; }
    $hopzoneStmt = getDB()->prepare("SELECT * FROM 4top_tops WHERE id = ? AND enabled = 1 AND top_btn = 'hopzoneu.php' LIMIT 1");
    $hopzoneStmt->execute(array($hopzoneId));
    $hopzoneTop = $hopzoneStmt->fetch(PDO::FETCH_ASSOC);
    if (!$hopzoneTop) { http_response_code(404); exit; }
    $hopzoneLogin = currentLogin();
    session_write_close();
    try {
        $hopzoneApi = new HopzoneEuApi($hopzoneTop);
        $hopzoneUrl = $hopzoneApi->prepareVote($hopzoneLogin);
        header('Location: ' . $hopzoneUrl, true, 302);
    } catch (Throwable $e) {
        error_log('[Hopzone.eu] Falha ao preparar link de votação');
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Não foi possível abrir a votação. Volte ao VoteSystem e tente novamente.';
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// CORS: reflete a própria origem (self-hosted — cada dono usa seu domínio)
$corsOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($corsOrigin !== '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $_SERVER['HTTP_HOST'];
    $port     = (int)($_SERVER['SERVER_PORT'] ?? 80);
    if (!in_array($port, array(80, 443), true)) {
        $expected .= ':' . $port;
    }
    if ($corsOrigin === $expected) {
        header('Access-Control-Allow-Origin: ' . $corsOrigin);
        header('Vary: Origin');
    }
}

// ── Anti-Flood ────────────────────────────────────────────────────────────────
define('FLOOD_MAX',    15);
define('FLOOD_WINDOW', 60);

function floodCheck($ip, $top) {
    if (empty($ip) || $ip === 'UNKNOWN') return;
    $key  = sys_get_temp_dir() . '/vsflood_' . md5($ip . $top);
    $now  = time();
    $data = array('count' => 0, 'window_start' => $now);

    $fp = @fopen($key, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $raw = @json_decode(stream_get_contents($fp), true);
            if ($raw && ($now - $raw['window_start']) < FLOOD_WINDOW) {
                $data = $raw;
            }
            $data['count']++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    } else {
        $data['count']++;
        @file_put_contents($key, json_encode($data), LOCK_EX);
    }

    if ($data['count'] > FLOOD_MAX) {
        http_response_code(429);
        echo json_encode(array('error' => true, 'message' => 'Too many requests'));
        exit;
    }
}

// ── Input sanitization ────────────────────────────────────────────────────────
function safeGet($key, $maxlen = 200) {
    $v = isset($_GET[$key]) ? trim($_GET[$key]) : '';
    $v = preg_replace('/[\r\n]+/', ' ', $v);
    return substr(strip_tags($v), 0, $maxlen);
}

function normalizeVoteIp($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return '';

    if (preg_match('/^::ffff:(\\d+\\.\\d+\\.\\d+\\.\\d+)$/i', $ip, $m)) {
        $ip = $m[1];
    }

    $valid = filter_var($ip, FILTER_VALIDATE_IP);
    return $valid ? $ip : '';
}

$top      = safeGet('top',       30);
$serverId = safeGet('server_id', 200);
$token    = safeGet('token',     200);
$ipRaw    = safeGet('ip',        45);
$ip       = normalizeVoteIp($ipRaw);
$login    = safeGet('login',     45);
$action   = safeGet('action',    20) ?: 'check';

// Valida IP se fornecido
if ($ipRaw !== '' && $ip === '') {
    echo json_encode(array('error' => true, 'message' => 'IP inválido'));
    exit;
}

// ── List tops ─────────────────────────────────────────────────────────────────
if ($action === 'list_tops') {
    echo json_encode(array(
        'error' => false,
        'tops'  => array(
            'mmtop200.php' => array('name' => 'MMTop200', 'site' => 'mmtop200.com', 'token' => true),
            'gamingtop100.php' => array('name' => 'GamingTop100', 'site' => 'www.gamingtop100.net', 'token' => false),
            'top100arena.php' => array('name' => 'Top100Arena', 'site' => 'www.top100arena.com', 'token' => false),
            'hopzoneu.php' => array('name' => 'Hopzone.eu', 'site' => 'hopzone.eu', 'token' => true),
            '4top.php'      => array('name' => '4TOP',      'site' => 'top.4teambr.com',   'token' => true),
            'l2jbrasil.php' => array('name' => 'L2JBrasil', 'site' => 'top.l2jbrasil.com', 'token' => true),
            'l2toporg.php'  => array('name' => 'L2Top.org', 'site' => 'l2top.org',         'token' => true),
            'l2network.php' => array('name' => 'L2Network', 'site' => 'l2network.eu',      'token' => true),
        ),
    ));
    exit;
}

// ── Validações básicas ────────────────────────────────────────────────────────
if (empty($top))      { echo json_encode(array('error' => true, 'message' => 'Parâmetro top obrigatório'));       exit; }
if (empty($serverId)) { echo json_encode(array('error' => true, 'message' => 'Parâmetro server_id obrigatório')); exit; }
if ($action === 'check' && empty($ip) && empty($login)) {
    echo json_encode(array('error' => true, 'message' => 'Parâmetro ip ou login obrigatório'));
    exit;
}

// Anti-flood para qualquer ação que use IP
if ($ip) {
    floodCheck($ip, $top . '_' . $action);
}

// ── Ação inválida ─────────────────────────────────────────────────────────────
$validActions = ['check', 'vote_url', 'list_tops'];
if (!in_array($action, $validActions)) {
    echo json_encode(array('error' => true, 'message' => 'Ação inválida'));
    exit;
}

// ── Build handler ─────────────────────────────────────────────────────────────
// O Hopzone usa estado persistente por conta, compartilhado com o painel local.
if (preg_replace('/\.php$/i', '', $top) === 'hopzoneu') {
    if (!file_exists(__DIR__ . '/.installed') || !file_exists(__DIR__ . '/config.php')) {
        echo json_encode(array('error' => true, 'message' => 'Hopzone.eu requer instalação local'));
        exit;
    }
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/core.php';
    require_once __DIR__ . '/includes/helpers.php';
    $stmt = getDB()->prepare("SELECT * FROM 4top_tops WHERE top_btn = 'hopzoneu.php' AND top_id = ? AND enabled = 1 LIMIT 1");
    $stmt->execute(array($serverId));
    $hopzoneTop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hopzoneTop || $token === '' || !hash_equals((string)$hopzoneTop['token'], (string)$token)) {
        echo json_encode(array('error' => true, 'message' => 'Hopzone.eu: cadastro ou chave inválidos'));
        exit;
    }
    $hopzoneApi = new HopzoneEuApi($hopzoneTop);
    if ($action === 'vote_url') {
        try {
            echo json_encode(array('error' => false, 'voteUrl' => $hopzoneApi->prepareVote($login)));
        } catch (Throwable $e) {
            echo json_encode(array('error' => true, 'message' => 'Hopzone.eu: não foi possível gerar o link'));
        }
    } else {
        $hopzoneResult = $hopzoneApi->checkVote($ip, $login);
        $hopzoneResult->serverTime = time();
        echo json_encode($hopzoneResult);
    }
    exit;
}

$handler = buildHandler($top, $token, $serverId);
if ($handler === null) {
    echo json_encode(array('error' => true, 'message' => "Top '$top' não suportado"));
    exit;
}

if ($action === 'vote_url') {
    $url = method_exists($handler, 'getVoteUrl') ? $handler->getVoteUrl($login) : '#';
    echo json_encode(array('error' => false, 'voteUrl' => $url));
    exit;
}

// Retorna também serverTime (UTC Unix) para o frontend calcular countdown
// sem depender do relógio do cliente.
$result = $handler->checkVote($ip, $login);
echo json_encode(array(
    'voted'      => (bool)$result->voted,
    'error'      => (bool)$result->error,
    'message'    => (string)$result->message,
    'voteTime'   => (int)$result->voteTime,   // Unix UTC do momento do voto
    'serverTime' => time(),                    // Unix UTC agora — use no frontend
));
exit;
