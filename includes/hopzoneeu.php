<?php
/** Hopzone.eu: vote_id persistente vinculado ao login; IP somente sem vínculo. */
class HopzoneEuApi {
    private $top;
    public function __construct($top) { $this->top = $top; }

    protected function log($message) {
        $message = str_replace(array("\r", "\n"), ' ', substr((string)$message, 0, 1000));
        $line = date('[Y-m-d H:i:s]') . ' [Hopzone.eu] ' . $message . "\n";
        $file = dirname(__DIR__) . '/vote_api.log';
        $flags = file_exists($file) && filesize($file) > 5242880 ? 0 : FILE_APPEND;
        @file_put_contents($file, $line, $flags | LOCK_EX);
    }

    public function getVoteUrl($login = '') {
        return 'hopzone_vote.php?top_id=' . (int)$this->top['id'] . '&csrf=' . rawurlencode(csrfToken());
    }

    protected function request($fields) {
        $fields['api_key'] = (string)$this->top['token'];
        $fields['type'] = 'json';
        $body = false;
        $url = 'https://api.hopzone.eu/v1/';
        $post = http_build_query($fields, '', '&');
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
            ));
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create(array(
                'http' => array('method' => 'POST', 'timeout' => 15, 'follow_location' => 0,
                    'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $post, 'ignore_errors' => true),
                'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $body = @file_get_contents($url, false, $context);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('/^HTTP\/\S+ ([0-9]{3})/', $http_response_header[0], $match)) $code = (int)$match[1];
        }
        if ($body === false || $code !== 200) {
            $this->log('ERRO HTTP | status=' . $code . ' | resposta indisponível');
            throw new RuntimeException('Hopzone.eu: consulta indisponível');
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['error']) || $data['error'] !== 'OK') {
            $error = isset($data['error']) && is_string($data['error']) && preg_match('/^[A-Z][0-9]{3}$/D', $data['error']) ? $data['error'] : 'resposta inválida';
            $this->log('ERRO API | código=' . $error);
            throw new RuntimeException('Hopzone.eu: API não forneceu resultado utilizável');
        }
        return $data;
    }

    private function reference($login) {
        $stmt = getDB()->prepare('SELECT * FROM 4top_hopzone_votes WHERE top_id = ? AND login = ?');
        $stmt->execute(array((int)$this->top['id'], $login));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && hash_equals($row['config_hash'], $this->configHash()) ? $row : false;
    }

    private function configHash() {
        return hash('sha256', $this->top['top_id'] . ':' . $this->top['token']);
    }

    public function completedTime($data, $expectedId = null) {
        if (!isset($data['vote_id']) || !ctype_digit((string)$data['vote_id']) || (int)$data['vote_id'] <= 0) return 0;
        if (!isset($data['server_id']) || (string)$data['server_id'] !== (string)$this->top['top_id']) return 0;
        if ($expectedId !== null && (!isset($data['vote_id']) || (string)$data['vote_id'] !== (string)$expectedId)) return 0;
        if (!isset($data['status']) || $data['status'] !== 'completed') return 0;
        $timestamp = filter_var($data['vote_time'] ?? '', FILTER_VALIDATE_INT);
        if ($timestamp === false || $timestamp <= 0 || $timestamp > time() || time() - $timestamp >= 43200) return 0;
        return $timestamp;
    }

    public function checkVote($ip, $login = '') {
        $result = new stdClass();
        $result->error = false;
        $result->voted = false;
        $result->voteTime = 0;
        $result->message = 'Conclua a votação no Hopzone.eu';
        $mode = 'sem identificação';
        try {
            $login = trim((string)$login);
            if ($login === '') throw new RuntimeException('Hopzone.eu: login obrigatório');
            if (trim((string)$this->top['token']) === '') throw new RuntimeException('Hopzone.eu: API Key não configurada');
            $reference = $login !== '' ? $this->reference($login) : false;
            if ($reference) {
                $mode = 'vote_id vinculado ao login';
                $data = $this->request(array('vote_id' => $reference['vote_id']));
                $result->voteTime = $this->completedTime($data, $reference['vote_id']);
            } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
                $mode = 'IP alternativo';
                $data = $this->request(array('ip' => $ip));
                $result->voteTime = $this->completedTime($data);
                if ($result->voteTime > 0) {
                    // Não atribui um vote_id já associado a outra conta ao mesmo IP.
                    $stmt = getDB()->prepare('SELECT login FROM 4top_hopzone_votes WHERE top_id = ? AND vote_id = ?');
                    $stmt->execute(array((int)$this->top['id'], $data['vote_id'] ?? ''));
                    $owner = $stmt->fetchColumn();
                    if ($owner !== false && $owner !== $login) $result->voteTime = 0;
                    if ($result->voteTime > 0) {
                        // A chave única impede que o mesmo voto por IP confirme duas contas.
                        $stmt = getDB()->prepare('INSERT IGNORE INTO 4top_hopzone_votes (top_id, login, vote_id, vote_url, config_hash, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                        $stmt->execute(array((int)$this->top['id'], $login, (string)$data['vote_id'],
                            'https://hopzone.eu/vote/' . $this->top['top_id'] . '/' . $data['vote_id'], $this->configHash()));
                        $saved = $this->reference($login);
                        if (!$saved || (string)$saved['vote_id'] !== (string)$data['vote_id']) $result->voteTime = 0;
                    }
                }
            }
            $result->voted = $result->voteTime > 0;
            if ($result->voted) $result->message = 'Voto confirmado no Hopzone.eu';
            $status = isset($data['status']) && in_array($data['status'], array('completed', 'pending'), true) ? $data['status'] : 'indisponível';
            $this->log(($result->voted ? 'VOTO CONFIRMADO' : 'VOTO NÃO CONFIRMADO')
                . ' | modo=' . $mode . ' | status=' . $status . ' | voteTime=' . $result->voteTime);
        } catch (Throwable $e) {
            $result->error = true;
            $result->message = 'Hopzone.eu: não foi possível verificar o voto';
            $this->log('ERRO: falha ao verificar voto | modo=' . $mode . '; nenhuma recompensa confirmada');
        }
        return $result;
    }

    /** Chamado apenas quando a conta autenticada abre o link de votação. */
    public function prepareVote($login) {
        $login = trim((string)$login);
        if ($login === '' || trim((string)$this->top['token']) === '' || !ctype_digit((string)$this->top['top_id'])) {
            throw new RuntimeException('Hopzone.eu: cadastro incompleto');
        }
        $db = getDB();
        $lock = 'hopzone_' . substr(hash('sha256', $this->top['id'] . ':' . $login), 0, 48);
        $stmt = $db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute(array($lock));
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Hopzone.eu: geração em andamento');
        try {
            $reference = $this->reference($login);
            if ($reference) {
                $data = $this->request(array('vote_id' => $reference['vote_id']));
                $age = time() - (new DateTime($reference['created_at'], new DateTimeZone('UTC')))->getTimestamp();
                $sameVote = (string)($data['vote_id'] ?? '') === (string)$reference['vote_id']
                    && (string)($data['server_id'] ?? '') === (string)$this->top['top_id'];
                if ($this->completedTime($data, $reference['vote_id']) > 0
                    || ($sameVote && ($data['status'] ?? '') === 'pending' && $age >= 0 && $age < 43200)) {
                    $this->log('LINK REUTILIZADO | vínculo por login preservado');
                    return $reference['vote_url'];
                }
            }
            $data = $this->request(array('generate' => $this->top['top_id']));
            $voteId = (string)($data['vote_id'] ?? '');
            $url = (string)($data['url'] ?? '');
            // A URL da API nunca pode redirecionar para outro domínio ou incluir a chave.
            $expected = 'https://hopzone.eu/vote/' . $this->top['top_id'] . '/' . $voteId;
            if (!ctype_digit($voteId) || (int)$voteId <= 0 || $url !== $expected) {
                throw new RuntimeException('Hopzone.eu: link inválido');
            }
            $stmt = $db->prepare('INSERT INTO 4top_hopzone_votes (top_id, login, vote_id, vote_url, config_hash, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE vote_id = VALUES(vote_id), vote_url = VALUES(vote_url), config_hash = VALUES(config_hash), created_at = VALUES(created_at)');
            $stmt->execute(array((int)$this->top['id'], $login, $voteId, $url, $this->configHash()));
            $this->log('LINK GERADO | vote_id vinculado ao login');
            return $url;
        } finally {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute(array($lock));
        }
    }
}
