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
            '4top.php'      => array('name' => '4TOP',      'site' => 'top.4teambr.com',   'token' => true),
            'l2jbrasil.php' => array('name' => 'L2JBrasil', 'site' => 'top.l2jbrasil.com', 'token' => true),
            'l2toporg.php'  => array('name' => 'L2Top.org', 'site' => 'l2top.org',         'token' => true),
            'l2network.php' => array('name' => 'L2Network', 'site' => 'l2network.eu',      'token' => true),
            'ragezone.php'  => array('name' => 'RaGEZONE',  'site' => 'forum.ragezone.com', 'token' => true),
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
        'ragezone'    => 'RaGezoneTop',
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

    // UA fixo e identificavel: permite que cada top allowliste este cliente.
    const USER_AGENT = '4top-l2votesystem/1.0 (+https://github.com/Sage-BR/4top-l2votesystem)';

    protected $token    = '';
    protected $serverId = '';
    protected $timeout  = 15;
    protected $name     = '';

    protected $userAgent = self::USER_AGENT;

    private $lastHeaders = array();

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
            'headers' => array(),
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
        return $this->_curl($url, array('headers' => $headers));
    }

    private function _curl($url, $extra = array()) {
        $userAgent = $this->userAgent;
        $this->lastHeaders = array();
        if (!function_exists('curl_init')) {
            $headers = isset($extra['headers']) ? $extra['headers'] : array();
            $headers[] = 'User-Agent: ' . $userAgent;
            $ctx  = stream_context_create(array(
                'http' => array(
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers) . "\r\n"
                ),
                'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $body = @file_get_contents($url, false, $ctx);
            return $body !== false ? $body : false;
        }

        $extra[CURLOPT_HTTPHEADER] = isset($extra['headers']) ? $extra['headers'] : array();
        unset($extra['headers']);
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
            CURLOPT_HEADERFUNCTION => array($this, 'captureHeader'),
        ) + $extra);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $body === false) {
            $this->log("curl error: $err | HTTP $code | url: $url");
            return false;
        }

        if ($code !== 200 || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<html') !== false) {
            $snippet = substr(trim(strip_tags((string)$body)), 0, 150);
            $edge    = $this->describeEdgeBlock($code, (string)$body);
            if ($edge !== '') {
                $this->log("BLOQUEADO NO EDGE (nao e falha da API) HTTP $code | $edge | url: $url");
            } else {
                $this->log("resposta inválida HTTP $code | url: $url | body: " . ($snippet ?: 'HTML/vazio'));
            }
            return false;
        }

        return $body;
    }

    /** Callback do cURL: guarda os headers da resposta para diagnostico. */
    protected function captureHeader($ch, $header) {
        $len = strlen($header);
        $pos = strpos($header, ':');
        if ($pos !== false) {
            $this->lastHeaders[strtolower(trim(substr($header, 0, $pos)))] = trim(substr($header, $pos + 1));
        }
        return $len;
    }

    protected function responseHeader($name) {
        $name = strtolower($name);
        return isset($this->lastHeaders[$name]) ? $this->lastHeaders[$name] : '';
    }

    /** Descreve um bloqueio de CDN/WAF (com CF-Ray), ou '' se nao for do edge. */
    protected function describeEdgeBlock($code, $body) {
        $server    = strtolower($this->responseHeader('server'));
        $mitigated = $this->responseHeader('cf-mitigated');
        $ray       = $this->responseHeader('cf-ray');
        $isCf      = ($server === 'cloudflare' || $mitigated !== '' || $ray !== '');

        $reason = '';
        if (stripos($body, 'Just a moment') !== false || stripos($body, 'challenge-platform') !== false) {
            $reason = 'Cloudflare Managed Challenge — este servidor foi classificado como bot';
        } elseif (preg_match('/error code: (10\d\d)/i', $body, $m)) {
            $map = array(
                '1010' => 'Browser Integrity Check — User-Agent recusado',
                '1020' => 'Firewall/WAF rule — acesso negado para este IP ou pais',
            );
            $reason = 'Cloudflare error ' . $m[1]
                    . (isset($map[$m[1]]) ? ' (' . $map[$m[1]] . ')' : '');
        } elseif ($mitigated !== '') {
            $reason = 'Cloudflare cf-mitigated: ' . $mitigated;
        } elseif ($isCf && ($code === 403 || $code === 503) && stripos($body, '<html') !== false) {
            $reason = 'Cloudflare retornou HTTP ' . $code . ' com pagina HTML (bloqueio ou desafio no edge)';
        }

        if ($reason === '') return '';
        if ($ray !== '') {
            $reason .= ' | CF-Ray: ' . $ray . ' (informe este ID ao admin do topsite para localizar a regra)';
        }
        return $reason;
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
        $msg = str_replace(array("\r", "\n"), ' ', substr((string)$msg, 0, 1000));
        $line = date('[Y-m-d H:i:s]') . ' [' . ($this->name ?: get_class($this)) . "] $msg\n";
        // rotação simples: se >5MB, trunca
        $logFile = dirname(__FILE__) . '/vote_api.log';
        if (file_exists($logFile) && filesize($logFile) > 5242880) {
            @file_put_contents($logFile, $line, LOCK_EX);
        } else {
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
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
        if (trim($this->token) === '') return TopResult::fail('4TOP: API Key não configurada');
        if (preg_match('/[\r\n]/', $this->token)) return TopResult::fail('4TOP: API Key inválida');
        if ((empty($ip) || $ip === 'UNKNOWN') && empty($login)) return TopResult::fail('4TOP: IP ou login obrigatório');

        return $this->checkApi($ip, $login);
    }

    private function checkApi($ip, $login = '') {
        $url  = self::API_URL . '?name=' . urlencode($this->serverId) . '&ip=' . urlencode($ip);
        if (!empty($login)) $url .= '&login=' . urlencode($login);
        $body = $this->safeGet($url);
        if (!$body) {
            $this->log("ERRO: 4TOP API inacessível | url: $url");
            return TopResult::fail('4TOP API inacessível');
        }

        $data = $this->decodeJson($body);
        if (!$data) {
            $this->log("ERRO: 4TOP JSON inválido | body: $body");
            return TopResult::fail('4TOP: JSON inválido');
        }

        $voted   = (int)($data['voted']     ?? 0);
        $dateStr = $data['vote_date']        ?? null;
        $voteTs  = $this->parseDateToUtc($dateStr);
        $now     = time();
        $rawJson = json_encode($data);

        if ($voted === 1 && $this->isVoteValid($voteTs, self::VOTE_WINDOW)) {
            $this->log("VOTO CONFIRMADO | login=$login ip=$ip | vote_date=$dateStr (ts=$voteTs) | raw: $rawJson");
            return TopResult::ok($voteTs, array(
                'votes'     => $data['votes']     ?? 0,
                'vote_date' => $dateStr,
            ));
        }

        if ($voted === 1) {
            $this->log("VOTO RECUSADO (EXPIRADO) | login=$login ip=$ip | vote_date=$dateStr (ts=$voteTs) passou de 12h | raw: $rawJson");
            return TopResult::notVoted('Voto expirado (mais de 12h)');
        }

        $this->log("VOTO NÃO ENCONTRADO | login=$login ip=$ip | 4TOP retornou voted=0 (o voto não foi registrado ou captcha pendente) | raw: $rawJson");
        return TopResult::notVoted('Não votou');
    }

    private function safeGet($url, $tries = 3) {
        for ($i = 0; $i < $tries; $i++) {
            $body = $this->httpGet($url, array(
                'Accept: application/json',
                'Authorization: Bearer ' . trim($this->token),
            ));
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
// L2JBrasil — consulta direta pela hospedagem do VoteSystem
// =============================================================================
class L2JBrasilTop extends TopBase {
    protected $name        = 'L2JBrasil';
    protected $apiTimezone = 'America/Sao_Paulo';
    const API_URL          = 'https://top.l2jbrasil.com/votesystem/';
    const VOTE_URL         = 'https://top.l2jbrasil.com/index.php';
    const VOTE_WINDOW      = 43200;

    public function checkVote($ip, $login = '') {
        if (empty($this->serverId)) return TopResult::fail('L2JBrasil: Server ID não configurado');
        if ((empty($ip) || $ip === 'UNKNOWN') && empty($login)) return TopResult::fail('L2JBrasil: IP ou login obrigatório');

        // Prioridade: login (player_id=md5) — CGNAT-safe. IP como secundário (log/auditoria)
        $identifier = !empty($login) ? md5($login) : '';

        $query = [
            'username' => $this->serverId,
            'type'     => 'json',
            'hours'    => '12',
        ];
        if ($identifier !== '') {
            $query['player_id'] = $identifier;
        }
        $url = self::API_URL . '?' . http_build_query($query);

        $body = $this->httpGet($url, array('Accept: application/json'));
        if (!$body) {
            $this->log("ERRO: sem resposta utilizável do L2JBrasil (ver linha anterior para a causa) | url: $url");
            return TopResult::fail('L2JBrasil inacessível');
        }

        $data = $this->decodeJson($body);
        if ($data === null || !isset($data['vote'])) {
            $this->log("ERRO: L2JBrasil resposta inválida | body: $body");
            return TopResult::fail('L2JBrasil: resposta inválida');
        }

        $rawJson = json_encode($data);
        // Normaliza — pode vir objeto único ou array
        $votes = isset($data['vote'][0]) ? $data['vote'] : [$data['vote']];

        foreach ($votes as $vote) {
            $status   = (string)($vote['status']          ?? '0');
            $hours    = (float)($vote['hours_since_vote'] ?? 99);
            $date     = $vote['date']                     ?? '0';
            $voteTs   = $this->parseDateToUtc($date);
            $playerId = (string)($vote['player_id']       ?? 'none');

            if ($status === '1' && $hours >= 0 && $hours < 12) {
                $this->log("VOTO CONFIRMADO (POR LOGIN/PLAYER_ID) | login=$login ip=$ip player_id=$playerId | horas_desde_voto=$hours | raw: $rawJson");
                return TopResult::ok($voteTs ?: time(), $vote);
            }

            if ($status === '1' && $hours >= 12) {
                $this->log("VOTO RECUSADO (EXPIRADO) | login=$login ip=$ip player_id=$playerId | voto feito há {$hours}h (limite 12h) | raw: $rawJson");
                return TopResult::notVoted('Voto expirado (mais de 12h)');
            }
        }

        // Se não confirmou por player_id e temos IP válido, tenta checagem secundária por IP
        if (!empty($ip) && $ip !== 'UNKNOWN') {
            $urlIp = self::API_URL . '?' . http_build_query([
                'ip'        => $ip,
                'username'  => $this->serverId,
                'type'      => 'json',
                'hours'     => '12',
            ]);
            $bodyIp = $this->httpGet($urlIp, array('Accept: application/json'));
            if ($bodyIp) {
                $dataIp = $this->decodeJson($bodyIp);
                if ($dataIp && isset($dataIp['vote'])) {
                    $rawJsonIp = json_encode($dataIp);
                    $votesIp = isset($dataIp['vote'][0]) ? $dataIp['vote'] : [$dataIp['vote']];
                    foreach ($votesIp as $voteIp) {
                        $statusIp = (string)($voteIp['status']          ?? '0');
                        $hoursIp  = (float)($voteIp['hours_since_vote'] ?? 99);
                        $dateIp   = $voteIp['date']                     ?? '0';
                        $voteTsIp = $this->parseDateToUtc($dateIp);

                        if ($statusIp === '1' && $hoursIp >= 0 && $hoursIp < 12) {
                            $this->log("VOTO CONFIRMADO (POR IP) | login=$login ip=$ip | horas_desde_voto=$hoursIp | raw: $rawJsonIp");
                            return TopResult::ok($voteTsIp ?: time(), $voteIp);
                        }
                    }
                }
            }
        }

        $this->log("VOTO NÃO ENCONTRADO | login=$login ip=$ip player_id_req=$identifier | L2JBrasil retornou status=0/hours=-1 (voto não concluído na página do top ou captcha não resolvido) | raw: $rawJson");
        return TopResult::notVoted('Não votou');
    }

    public function getVoteUrl($login = '') {
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
        if (!$body) {
            $this->log("ERRO: L2Top.org API inacessível | url: $url");
            return TopResult::fail('L2Top.org API inacessível');
        }

        $data = $this->decodeJson($body);
        if (!$data || !isset($data['result'])) {
            $this->log("ERRO: L2Top.org resposta inválida | body: $body");
            return TopResult::fail('L2Top.org: resposta inválida');
        }

        $res      = $data['result'];
        $isVoted  = (bool)($res['is_voted']  ?? false);
        $voteTime = (int)($res['vote_time']   ?? 0);
        $rawJson  = json_encode($data);

        if ($isVoted && $voteTime > 0) {
            $this->log("VOTO CONFIRMADO | login=$login ip=$ip | voteTime=$voteTime | raw: $rawJson");
            return TopResult::ok($voteTime);
        }

        $this->log("VOTO NÃO ENCONTRADO | login=$login ip=$ip | L2Top.org retornou is_voted=false | raw: $rawJson");
        return TopResult::notVoted('Não votou');
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
            'ip'     => $ip ?: 'UNKNOWN',
        ));

        $body = $this->httpPost($postData);
        if (!$body) {
            $this->log("ERRO: L2Network API inacessível");
            return TopResult::fail('L2Network API inacessível');
        }

        $data = $this->decodeJson($body);
        if (!$data) {
            $this->log("ERRO: L2Network resposta inválida | body: $body");
            return TopResult::fail('L2Network: resposta inválida');
        }

        // Resposta pode vir com "result" ou direto
        $result = $data['result'] ?? $data;
        $rawJson = json_encode($data);

        // Se retornou Unix timestamp > 0 = votou
        $voteTime = 0;
        if (is_numeric($result) && (int)$result > 0) {
            $voteTime = (int)$result;
        } elseif (is_array($result) && isset($result['vote_time'])) {
            $voteTime = (int)$result['vote_time'];
        }

        if ($voteTime > 0 && $this->isVoteValid($voteTime, self::VOTE_WINDOW)) {
            $this->log("VOTO CONFIRMADO | login=$login ip=$ip | voteTime=$voteTime | raw: $rawJson");
            return TopResult::ok($voteTime, $data);
        }

        if ($voteTime > 0) {
            $this->log("VOTO RECUSADO (EXPIRADO) | login=$login ip=$ip | voteTime=$voteTime passou de 12h | raw: $rawJson");
            return TopResult::notVoted('Voto expirado (mais de 12h)');
        }

        $this->log("VOTO NÃO ENCONTRADO | login=$login ip=$ip | L2Network não confirmou o voto | raw: $rawJson");
        return TopResult::notVoted('Não votou');
    }

    public function getVoteUrl($login = '') {
        return self::VOTE_URL . '?a=in&u=' . urlencode($this->serverId)
             . '&id=' . urlencode($login ?: $this->serverId);
    }

    private function httpPost($postData) {
        $userAgent = $this->userAgent;
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


// =============================================================================
// RaGEZONE Top Sites — voto mensal identificado por login (ref)
// =============================================================================
class RaGezoneTop extends TopBase {
    protected $name        = 'RaGEZONE';
    protected $apiTimezone = 'UTC';
    const API_BASE         = 'https://forum.ragezone.com/topsites';
    const VOTE_WINDOW      = 86400; // 24h (mesmo que ranking seja mensal, CD de voto é 24h)

    public function checkVote($ip, $login = '') {
        if (empty($this->token)) return TopResult::fail('RaGEZONE: Listing Key não configurada');
        if (empty($this->serverId)) return TopResult::fail('RaGEZONE: Listing ID não configurado');
        if (empty($login)) return TopResult::fail('RaGEZONE: Login obrigatório (ref)');

        $url = self::API_BASE . '/' . urlencode($this->serverId) . '/vote-check?ref=' . urlencode($login);
        $body = $this->httpGet($url, array(
            'RZ-Listing-Key: ' . $this->token,
            'Accept: application/json',
        ));

        if (!$body) {
            $this->log("ERRO: RaGEZONE API inacessível | url: $url");
            return TopResult::fail('RaGEZONE API inacessível');
        }

        $data = $this->decodeJson($body);
        if (!$data) {
            $this->log("ERRO: RaGEZONE JSON inválido | body: $body");
            return TopResult::fail('RaGEZONE: JSON inválido');
        }

        $voted  = (bool)($data['voted'] ?? false);
        // RaGEZONE pode retornar voted_at / time (Unix) ou period (Y-m)
        $voteTime = 0;
        if (isset($data['time']) && is_numeric($data['time'])) {
            $voteTime = (int)$data['time'];
        } elseif (isset($data['voted_at']) && is_numeric($data['voted_at'])) {
            $voteTime = (int)$data['voted_at'];
        } elseif (isset($data['data']['time']) && is_numeric($data['data']['time'])) {
            $voteTime = (int)$data['data']['time'];
        }
        // Fallback: se só tem period mensal, usa início do período como referência
        $period = (string)($data['period'] ?? ($data['data']['period'] ?? ''));
        if ($voteTime === 0 && $period !== '') {
            $voteTime = strtotime($period . '-01 00:00:00 UTC') ?: time();
        }
        if ($voteTime === 0) $voteTime = time();

        $rawJson = json_encode($data);

        if ($voted && $this->isVoteValid($voteTime, self::VOTE_WINDOW)) {
            $this->log("VOTO CONFIRMADO | login=$login ip=$ip | voteTime=$voteTime | period=$period | raw: $rawJson");
            return TopResult::ok($voteTime, array(
                'period' => $period,
                'votes'  => $data['votes'] ?? ($data['data']['votes'] ?? 0),
            ));
        }

        if ($voted) {
            $this->log("VOTO RECUSADO (EXPIRADO) | login=$login ip=$ip | voteTime=$voteTime | period=$period passou de 24h | raw: $rawJson");
            return TopResult::notVoted('Voto expirado (mais de 24h)');
        }

        $this->log("VOTO NÃO ENCONTRADO | login=$login ip=$ip | RaGEZONE retornou voted=false | raw: $rawJson");
        return TopResult::notVoted('Não votou neste período (24h)');
    }

    public function getVoteUrl($login = '') {
        // vote?ref={login} — o ref é o identificador do jogador
        $url = self::API_BASE . '/' . urlencode($this->serverId) . '/vote';
        if (!empty($login)) {
            $url .= '?ref=' . urlencode($login);
        }
        return $url;
    }
}
