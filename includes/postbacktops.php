<?php
/** Referências locais para tops que devolvem a conta por postback. */
function postbackTopSupported($button) {
    return in_array($button, array('mmtop200.php', 'gamingtop100.php', 'top100arena.php'), true);
}
function postbackTopLog($provider, $message) {
    $line = date('[Y-m-d H:i:s]') . ' [' . $provider . '] ' . $message . "\n";
    @file_put_contents(dirname(__DIR__) . '/vote_api.log', $line, FILE_APPEND | LOCK_EX);
}
function postbackTopVoteUrl($top, $login) {
    $login = trim((string)$login);
    if ($login === '' || empty($top['id']) || !ctype_digit((string)$top['top_id'])) return '#';
    try {
        $stmt = getDB()->prepare('INSERT INTO 4top_postback_refs (top_id, login, voter_ip) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE voter_ip = VALUES(voter_ip)');
        $stmt->execute(array((int)$top['id'], $login, clientIp()));
        $stmt = getDB()->prepare('SELECT id FROM 4top_postback_refs WHERE top_id = ? AND login = ?');
        $stmt->execute(array((int)$top['id'], $login));
        $ref = (string)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[VoteSystem] Falha ao gerar referência de postback; confira o schema e as permissões do banco. SQLSTATE=' . $e->getCode());
        return '#';
    }
    if ($ref === '' || !ctype_digit($ref) || (int)$ref <= 0) return '#';
    $site = rawurlencode($top['top_id']);
    switch ($top['top_btn']) {
        case 'mmtop200.php': return 'https://mmtop200.com/vote/' . $site . '/' . rawurlencode($login);
        case 'gamingtop100.php': return 'https://www.gamingtop100.net/in-' . $site . '-' . $ref;
        case 'top100arena.php': return 'https://www.top100arena.com/listing/' . $site . '/vote?incentive=' . $ref;
    }
    return '#';
}
function postbackTopOriginAllowed($provider, $remote) {
    $packed = @inet_pton($remote);
    if ($packed === false) return false;
    $override = defined('VOTE_POSTBACK_ALLOWED_IPS') ? VOTE_POSTBACK_ALLOWED_IPS : array();
    if (is_array($override) && isset($override[$provider]) && is_array($override[$provider])) {
        $allowed = $override[$provider];
    } elseif ($provider === 'top100arena.php') {
        // Origem usada no painel de referência; pode ser atualizada no config.php.
        $allowed = array('3.86.48.116');
    } else {
        $host = $provider === 'mmtop200.php' ? 'validator.mmtop200.com' : 'gamingtop100.net';
        $allowed = @gethostbynamel($host) ?: array();
        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            foreach (@dns_get_record($host, DNS_AAAA) ?: array() as $record) {
                if (!empty($record['ipv6'])) $allowed[] = $record['ipv6'];
            }
        }
    }
    foreach ($allowed as $address) if (is_string($address) && @inet_pton($address) === $packed) return true;
    return false;
}
function handleVotePostback() {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'), true)) { http_response_code(405); return; }
    $fields = array_merge($_GET, $_POST);
    $key = isset($fields['uid']) ? 'uid' : (isset($fields['user_id']) ? 'user_id' : 'postback');
    $provider = $key === 'uid' ? 'mmtop200.php' : ($key === 'user_id' ? 'gamingtop100.php' : 'top100arena.php');
    $ref = isset($fields[$key]) && is_string($fields[$key]) ? trim($fields[$key]) : '';
    if ($ref === '' || strlen($ref) > 45 || ($key !== 'uid' && (!ctype_digit($ref) || (int)$ref <= 0))) {
        http_response_code(400); echo json_encode(array('ok' => false)); return;
    }
    // clientIp só usa headers quando o remetente é um proxy confiável do projeto.
    if (!postbackTopOriginAllowed($provider, clientIp())) {
        http_response_code(403); echo json_encode(array('ok' => false)); return;
    }
    if ($key === 'uid' && (!isset($fields['vote_counted']) || !is_string($fields['vote_counted'])
        || !in_array(strtolower($fields['vote_counted']), array('1', 'true', 'yes', 'on', 'y'), true))) {
        echo json_encode(array('ok' => true, 'ignored' => 'not_counted')); return;
    }
    $db = getDB();
    $lock = '';
    $locked = false;
    try {
        $column = $key === 'uid' ? 'r.login' : 'r.id';
        $stmt = $db->prepare("SELECT r.*, t.name FROM 4top_postback_refs r JOIN 4top_tops t ON t.id = r.top_id WHERE $column = ? AND t.top_btn = ? AND t.enabled = 1 LIMIT 1");
        $stmt->execute(array($ref, $provider));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(array('ok' => false)); return; }
        $lock = 'vote_pb_' . $row['top_id'] . '_' . $row['id'];
        $stmt = $db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute(array($lock));
        $locked = (int)$stmt->fetchColumn() === 1;
        if (!$locked) { http_response_code(503); echo json_encode(array('ok' => false)); return; }
        $voterIp = isset($fields['ip_addr']) && is_string($fields['ip_addr']) && filter_var($fields['ip_addr'], FILTER_VALIDATE_IP)
            ? $fields['ip_addr'] : $row['voter_ip'];
        $result = registerVote($row['login'], (int)$row['top_id'], $voterIp);
        if ($result === 'error') throw new RuntimeException('postback registration failed');
        $stmt = $db->prepare('UPDATE 4top_postback_refs SET postback_at = NOW() WHERE id = ? AND (postback_at IS NULL OR postback_at <= DATE_SUB(NOW(), INTERVAL 12 HOUR))');
        $stmt->execute(array($row['id']));
        postbackTopLog(basename($provider, '.php'), $result === 'ok' ? 'VOTO CONFIRMADO POR POSTBACK' : 'POSTBACK DUPLICADO: horário original preservado');
        echo json_encode(array('ok' => true));
    } catch (Throwable $e) {
        postbackTopLog(basename($provider, '.php'), 'ERRO: falha ao registrar postback');
        http_response_code(500); echo json_encode(array('ok' => false));
    } finally {
        if ($locked) { $stmt = $db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute(array($lock)); }
    }
}
class PostbackTopApi {
    private $top;
    public function __construct($top) { $this->top = $top; }
    public function getVoteUrl($login = '') { return postbackTopVoteUrl($this->top, $login); }
    public function checkVote($ip, $login = '') {
        $stmt = getDB()->prepare('SELECT postback_at AS voted_at, TIMESTAMPDIFF(SECOND, postback_at, NOW()) AS seconds_ago FROM 4top_postback_refs WHERE top_id = ? AND login = ? AND postback_at IS NOT NULL');
        $stmt->execute(array($this->top['id'], trim((string)$login)));
        $vote = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vote && (int)$vote['seconds_ago'] >= 0 && (int)$vote['seconds_ago'] < 43200) {
            $result = new stdClass();
            $result->error = false; $result->voted = true;
            $result->voteTime = (new DateTime($vote['voted_at'], new DateTimeZone('UTC')))->getTimestamp();
            $result->message = 'Voto confirmado por login/postback';
            postbackTopLog(basename($this->top['top_btn'], '.php'), 'VOTO CONFIRMADO POR LOGIN/POSTBACK (HORÁRIO PRESERVADO)');
            return $result;
        }
        $result = new stdClass();
        $result->error = false; $result->voted = false; $result->voteTime = 0;
        $result->message = 'Aguardando postback que confirme o voto desta conta';
        postbackTopLog(basename($this->top['top_btn'], '.php'), 'VOTO NÃO CONFIRMADO: aguardando postback da conta; IP não comprova login');
        return $result;
    }
}
