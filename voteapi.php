<?php
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
    if (strncmp($corsOrigin, $expected, strlen($expected)) === 0) {
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
    return substr(strip_tags($v), 0, $maxlen);
}

function normalizeVoteIp($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return '';

    if (preg_match('/^::ffff:(\\d+\\.\\d+\\.\\d+\\.\\d+)$/i', $ip, $m)) {
        $ip = $m[1];
    }

    $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
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
            '4top.php'        => array('name' => '4TOP',        'site' => 'top.4teambr.com',   'token' => false),
            'l2jbrasil.php'   => array('name' => 'L2JBrasil',   'site' => 'top.l2jbrasil.com', 'token' => true),
            'hopzone.php'     => array('name' => 'Hopzone.net', 'site' => 'l2.hopzone.net',     'token' => true),
        'hopzoneu.php'    => array('name' => 'Hopzone.eu',  'site' => 'hopzone.eu',          'token' => true),
        'itopz.php'       => array('name' => 'iTopZ',       'site' => 'itopz.com',           'token' => true),
        'l2toporg.php'    => array('name' => 'L2Top.org',   'site' => 'l2top.org',           'token' => true),
        'hotservers.php'  => array('name' => 'HotServers',  'site' => 'hotservers.org',      'token' => true),
        'l2rankzone.php'  => array('name' => 'L2RankZone',  'site' => 'l2rankzone.com',      'token' => true),


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

// Anti-flood após validações básicas
if ($action === 'check' && $ip) {
    floodCheck($ip, $top);
}

// ── Ação inválida ─────────────────────────────────────────────────────────────
$validActions = ['check', 'vote_url', 'list_tops'];
if (!in_array($action, $validActions)) {
    echo json_encode(array('error' => true, 'message' => 'Ação inválida'));
    exit;
}

// ── Build handler ─────────────────────────────────────────────────────────────
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

// ── Handler map ───────────────────────────────────────────────────────────────
function buildHandler($top, $token, $serverId) {
    // Remove extensão .php se existir
    $top = preg_replace('/\.php$/i', '', $top);
    
static $map = array(
        'l2jbrasil'   => 'L2JBrasilTop',
        '4top'        => 'FourTopTop',
        'l2toporg'    => 'L2TopOrgTop',
        'l2network'   => 'L2NetworkTop',
    );
    $class = isset($map[$top]) ? $map[$top] : null;
    if ($class === null || !class_exists($class)) return null;
    return new $class($token, $serverId);
}


// =============================================================================
// TopResult — imutável após criação
// =============================================================================
final class TopResult {
    public $voted    = false;
    public $error    = false;
    public $message  = '';
    public $voteTime = 0;
    public $raw      = array();

    private function __construct() {}

    public static function ok($voteTime = 0, $raw = array()) {
        $r = new self();
        $r->voted    = true;
        $r->voteTime = (int)$voteTime;
        $r->raw      = $raw;
        $r->message  = 'Votou';
        return $r;
    }

    public static function notVoted($msg = 'Não votou', $raw = array()) {
        $r = new self();
        $r->message = $msg;
        $r->raw     = $raw;
        return $r;
    }

    public static function fail($msg = 'Erro na API') {
        $r = new self();
        $r->error   = true;
        $r->message = $msg;
        return $r;
    }
}


// =============================================================================
// TopBase — base com HTTP helpers e utilitário de timezone
// =============================================================================
abstract class TopBase {

    protected $token    = '';
    protected $serverId = '';
    protected $timeout  = 15;
    protected $name     = '';

    // Fuso horário padrão das APIs (sobrescrever nas subclasses se diferente)
    // A grande maioria dos tops internacionais opera em UTC.
    protected $apiTimezone = 'UTC';

    public function __construct($token, $serverId) {
        $this->token    = (string)$token;
        $this->serverId = (string)$serverId;
    }

    abstract public function checkVote($ip, $login = '');

    // ── Timezone helper ───────────────────────────────────────────────────────
    /**
     * Converte string de data retornada pela API para Unix timestamp UTC.
     * Usa $this->apiTimezone para interpretar a string corretamente,
     * independentemente do fuso configurado no servidor PHP.
     *
     * @param  string $dateStr  Data no formato "Y-m-d H:i:s" ou similar
     * @return int              Unix timestamp UTC (0 em caso de erro)
     */
    protected function parseDateToUtc($dateStr) {
        if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00' || $dateStr === '0') {
            return 0;
        }
        try {
            $dt = new DateTime($dateStr, new DateTimeZone($this->apiTimezone));
            return $dt->getTimestamp(); // sempre Unix UTC
        } catch (Throwable $e) {
            $this->log("parseDateToUtc falhou: '$dateStr' tz={$this->apiTimezone} err={$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Verifica se um Unix timestamp de voto ainda está dentro da janela.
     *
     * @param  int  $voteTs    Unix UTC do momento do voto
     * @param  int  $windowSec Janela em segundos (padrão 12h = 43200)
     * @return bool
     */
    protected function isVoteValid($voteTs, $windowSec = 43200) {
        if ($voteTs <= 0) return false;
        return ($voteTs + $windowSec) >= time();
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────
    protected function httpGetSimple($url) {
        return $this->_curl($url, array(
            CURLOPT_HTTPHEADER => array(),
        ));
    }

    protected function httpGet($url, $headers = array()) {
        if (empty($headers)) {
            $headers = array(
                'Accept: application/json, text/plain, */*',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
                'Connection: keep-alive',
            );
        }
        return $this->_curl($url, array(CURLOPT_HTTPHEADER => $headers));
    }

    private function _curl($url, $extra = array()) {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        if (!function_exists('curl_init')) {
            $ctx  = stream_context_create(array(
                'http' => array(
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                    'header' => "User-Agent: " . $userAgent . "\r\n"
                ),
                'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $body = @file_get_contents($url, false, $ctx);
            return $body !== false ? $body : false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => $userAgent,
        ) + $extra);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $body === false) {
            $this->log("curl error: $err | url: $url");
            return false;
        }

        if ($code !== 200 || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<html') !== false) {
            $this->log("resposta inválida HTTP $code | url: $url");
            return false;
        }

        return $body;
    }

    protected function decodeJson($body) {
        if (!$body) return null;
        $data = @json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }

    protected function decodeXml($body) {
        if (!$body) return null;
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();
        return ($xml !== false) ? $xml : null;
    }

    protected function log($msg) {
        $line = date('[Y-m-d H:i:s]') . ' [' . ($this->name ?: get_class($this)) . "] $msg\n";
        @file_put_contents(dirname(__FILE__) . '/vote_api.log', $line, FILE_APPEND | LOCK_EX);
    }
}


// =============================================================================
// 4TOP
// =============================================================================
class FourTopTop extends TopBase {
    protected $name       = '4TOP';
    protected $apiTimezone = 'UTC'; // API original trabalha em UTC
    const API_URL          = 'https://top.4teambr.com/api.php';
    const VOTE_WINDOW      = 43200; // 12h em segundos

    public function checkVote($ip, $login = '') {
        if (empty($this->serverId))                        return TopResult::fail('4TOP: Server ID não configurado');
        if ((empty($ip) || $ip === 'UNKNOWN') && empty($login)) return TopResult::fail('4TOP: IP ou login obrigatório');

        return $this->checkApi($ip, $login);
    }

    private function checkApi($ip, $login = '') {
        $url  = self::API_URL . '?name=' . urlencode($this->serverId) . '&ip=' . urlencode($ip);
        if (!empty($login)) $url .= '&login=' . urlencode($login);
        $body = $this->safeGet($url);
        if (!$body) return TopResult::fail('4TOP API inacessível');

        $data = $this->decodeJson($body);
        if (!$data) return TopResult::fail('4TOP: JSON inválido');

        $voted   = (int)($data['voted']     ?? 0);
        $dateStr = $data['vote_date']        ?? null;

        // Converte usando o fuso da API (UTC) → Unix UTC
        $voteTs  = $this->parseDateToUtc($dateStr);

        $this->log("voted=$voted | voteTs=$voteTs | vote_date=$dateStr | ip=$ip | login=$login | now=" . time());

        if ($voted === 1 && $this->isVoteValid($voteTs, self::VOTE_WINDOW)) {
            return TopResult::ok($voteTs, array(
                'votes'     => $data['votes']     ?? 0,
                'vote_date' => $dateStr,
            ));
        }

        return TopResult::notVoted('Não votou');
    }

    private function safeGet($url, $tries = 3) {
        for ($i = 0; $i < $tries; $i++) {
            $body = $this->httpGet($url);
            if ($body !== false && trim($body) !== '') return $body;
            if ($i < $tries - 1) usleep(200000);
        }
        return false;
    }

    public function getVoteUrl($login = '') {
        $url = 'https://top.4teambr.com/index.php?a=in&u=' . urlencode($this->serverId);
        if (!empty($login)) $url .= '&login=' . urlencode($login);
        return $url;
    }
}


// =============================================================================
// L2JBrasil — via CF Worker (evita bloqueio por datacenter IP)
// =============================================================================
class L2JBrasilTop extends TopBase {
    protected $name        = 'L2JBrasil';
    protected $apiTimezone = 'America/Sao_Paulo';
    const API_URL          = 'https://4topvotesystem.4teambrsg.workers.dev/';
    const VOTE_URL         = 'https://top.l2jbrasil.com/index.php';
    const VOTE_WINDOW      = 43200;

    public function checkVote($ip, $login = '') {
        if (empty($this->serverId))          return TopResult::fail('L2JBrasil: Server ID não configurado');
        if (empty($ip) || $ip === 'UNKNOWN') return TopResult::fail('L2JBrasil: IP obrigatório');

        // player_id = MD5 do login do char (preferencial) ou vazio (fallback com base no login)
        // username  = slug do servidor
        $identifier = !empty($login) ? md5($login) : '';

        $url = self::API_URL . '?' . http_build_query([
            'player_id' => $identifier,
            'username'  => $this->serverId,
            'type'      => 'json',
            'hours'     => '12',
        ]);

        $body = $this->httpGetSimple($url);
        if (!$body) return TopResult::fail('L2JBrasil inacessível');

        $data = $this->decodeJson($body);
        if ($data === null || !isset($data['vote'])) return TopResult::fail('L2JBrasil: resposta inválida');

        // Normaliza — pode vir objeto único ou array
        $votes = isset($data['vote'][0]) ? $data['vote'] : [$data['vote']];

        foreach ($votes as $vote) {
            $status = (string)($vote['status']          ?? '0');
            $hours  = (float)($vote['hours_since_vote'] ?? 99);
            $date   = $vote['date']                     ?? '0';
            $voteTs = $this->parseDateToUtc($date);
            $playerId = $vote['player_id'] ?? 'N/A';

            $this->log("status=$status | hours=$hours | player_id=$playerId | ip=$ip | login=$login");

            if ($status === '1' && $hours >= 0 && $hours < 12) {
                return TopResult::ok($voteTs ?: time(), $vote);
            }
        }

        $this->log("voted=0 no match | ip=$ip login=$login");
        return TopResult::notVoted('Não votou');
    }

    public function getVoteUrl($login = '') {
        // Com login: player_id = MD5 do login do char (32 caracteres) — l2jbrasil associa voto ao char (CGNAT safe)
        // Sem login: sem player_id (vazio)
        $playerId = !empty($login) ? md5($login) : '';
        return self::VOTE_URL . '?a=in&u=' . urlencode($this->serverId)
             . '&player_id=' . urlencode($playerId);
    }
}


// =============================================================================
// L2Top.org — verifica por login
// =============================================================================
class L2TopOrgTop extends TopBase {
    protected $name        = 'L2Top.org';
    protected $apiTimezone = 'UTC';
    const API_URL          = 'https://l2top.org/api';
    const VOTE_URL         = 'https://l2top.org/server';

    public function checkVote($ip, $login = '') {
        if (empty($login))       return TopResult::fail('L2Top.org: login obrigatório');
        if (empty($this->token)) return TopResult::fail('L2Top.org: API Key não configurada');

        $url  = self::API_URL . '/' . urlencode($this->token) . '/name/' . urlencode($login) . '/';
        $body = $this->httpGet($url);
        if (!$body) return TopResult::fail('L2Top.org API inacessível');

        $data = $this->decodeJson($body);
        if (!$data || !isset($data['result'])) return TopResult::fail('L2Top.org: resposta inválida');

        $res      = $data['result'];
        $isVoted  = (bool)($res['is_voted']  ?? false);
        $voteTime = (int)($res['vote_time']   ?? 0);

        $this->log("is_voted=$isVoted | voteTime=$voteTime | login=$login");

        // L2Top.org retorna Unix UTC diretamente
        return ($isVoted && $voteTime > 0)
            ? TopResult::ok($voteTime)
            : TopResult::notVoted('Não votou');
    }

    public function getVoteUrl($login = '') {
        return self::VOTE_URL . '/' . urlencode($this->serverId) . '/vote/' . urlencode($login) . '/';
    }
}


// =============================================================================
// L2Network.eu
// =============================================================================
class L2NetworkTop extends TopBase {
    protected $name        = 'L2Network.eu';
    protected $apiTimezone = 'UTC';
    const API_URL          = 'https://l2network.eu/api.php';
    const VOTE_URL         = 'https://l2network.eu/index.php';

    public function checkVote($ip, $login = '') {
        if (empty($this->token)) return TopResult::fail('L2Network: API Key não configurada');
        if (empty($login))      return TopResult::fail('L2Network: Login obrigatório para verificar');

        $postData = http_build_query(array(
            'apiKey' => $this->token,
            'type'   => 2,
            'player' => $login,
        ));

        $body = $this->httpPost($postData);
        if (!$body) return TopResult::fail('L2Network API inacessível');

        $data = $this->decodeJson($body);
        if (!$data) return TopResult::fail('L2Network: resposta inválida');

        // Resposta pode vir com "result" ou direto
        $result = $data['result'] ?? $data;

        // Se retornou Unix timestamp > 0 = votou
        $voteTime = 0;
        if (is_numeric($result) && (int)$result > 0) {
            $voteTime = (int)$result;
        } elseif (is_array($result) && isset($result['vote_time'])) {
            $voteTime = (int)$result['vote_time'];
        }

        $this->log("login=$login | voteTime=$voteTime");

        if ($voteTime > 0 && $this->isVoteValid($voteTime, self::VOTE_WINDOW)) {
            return TopResult::ok($voteTime, $data);
        }

        return TopResult::notVoted('Não votou');
    }

    public function getVoteUrl($login = '') {
        return self::VOTE_URL . '?a=in&u=' . urlencode($this->serverId) 
             . '&id=' . urlencode($login ?: $this->serverId);
    }

    private function httpPost($postData) {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(array(
                'http' => array(
                    'timeout' => $this->timeout,
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                                 "User-Agent: " . $userAgent . "\r\n",
                    'content' => $postData,
                ),
            ));
            return @file_get_contents(self::API_URL, false, $ctx);
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => self::API_URL,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => $userAgent,
        ));
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        return $err ? false : $body;
    }
}
