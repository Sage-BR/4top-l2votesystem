<?php
// Testes isolados: sem chamadas externas, credenciais reais ou banco da hospedagem.
require_once __DIR__ . '/../includes/hopzoneeu.php';
function getDB() { return $GLOBALS['hopzoneDb']; }
function csrfToken() { return 'fixture-csrf'; }
class HopzoneTestDb {
    public $rows = array();
    public $released = 0;
    public function prepare($sql) { return new HopzoneTestStatement($this, $sql); }
}
class HopzoneTestStatement {
    private $db;
    private $sql;
    private $value = false;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute($params) {
        if (strpos($this->sql, 'GET_LOCK') !== false) { $this->value = 1; return; }
        if (strpos($this->sql, 'RELEASE_LOCK') !== false) { $this->db->released++; return; }
        if (strpos($this->sql, 'SELECT *') === 0) { $this->value = $this->db->rows[$params[1]] ?? false; return; }
        if (strpos($this->sql, 'SELECT login') === 0) {
            $this->value = false;
            foreach ($this->db->rows as $row) if ((string)$row['vote_id'] === (string)$params[1]) $this->value = $row['login'];
            return;
        }
        if (strpos($this->sql, 'INSERT') === 0) {
            $ignore = strpos($this->sql, 'INSERT IGNORE') === 0;
            foreach ($this->db->rows as $row) {
                if ((string)$row['vote_id'] === (string)$params[2] && $row['login'] !== $params[1]) return;
            }
            if ($ignore && isset($this->db->rows[$params[1]])) return;
            $this->db->rows[$params[1]] = array('top_id' => $params[0], 'login' => $params[1], 'vote_id' => $params[2],
                'vote_url' => $params[3], 'config_hash' => $params[4], 'created_at' => gmdate('Y-m-d H:i:s'));
            return;
        }
        throw new RuntimeException('Unexpected SQL');
    }
    public function fetch($mode = null) { return $this->value; }
    public function fetchColumn() { return $this->value; }
}
class HopzoneProbe extends HopzoneEuApi {
    public $answers = array();
    public $calls = array();
    public $logs = array();
    protected function log($message) { $this->logs[] = $message; }
    protected function request($fields) {
        $this->calls[] = $fields;
        if (!$this->answers) throw new RuntimeException('No mock answer');
        $answer = array_shift($this->answers);
        if ($answer instanceof Throwable) throw $answer;
        return $answer;
    }
}
function checkHopzone($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'OK: ' . $label . PHP_EOL;
}
$GLOBALS['hopzoneDb'] = new HopzoneTestDb();
$api = new HopzoneProbe(array('id' => 2, 'top_id' => '6', 'token' => 'fixture-key'));
$completed = array('error' => 'OK', 'server_id' => 6, 'vote_id' => 1251, 'status' => 'completed', 'vote_time' => time() - 60);
checkHopzone($api->completedTime($completed, '1251') > 0, 'recent completed vote');
foreach (array(
    array('status', 'pending'), array('server_id', 9), array('vote_id', 99),
    array('vote_time', time() - 43201), array('vote_time', time() + 60), array('vote_time', 0)
) as $change) {
    $bad = $completed;
    $bad[$change[0]] = $change[1];
    checkHopzone($api->completedTime($bad, '1251') === 0, 'reject ' . $change[0] . '=' . $change[1]);
}
$api->answers = array(array('error' => 'OK', 'vote_id' => 1251, 'url' => 'https://hopzone.eu/vote/6/1251'));
checkHopzone($api->prepareVote('player-a') === 'https://hopzone.eu/vote/6/1251', 'generate player-bound URL');
checkHopzone($api->calls[0] === array('generate' => '6'), 'generate uses server ID');
checkHopzone(isset($GLOBALS['hopzoneDb']->rows['player-a']), 'persistent login reference');
$api->answers = array(array('status' => 'pending', 'vote_id' => 1251, 'server_id' => 6));
$count = count($api->calls);
checkHopzone($api->prepareVote('player-a') === 'https://hopzone.eu/vote/6/1251' && count($api->calls) === $count + 1, 'reopening reuses pending ID');
$api->answers = array($completed);
checkHopzone($api->checkVote('192.0.2.1', 'player-a')->voted, 'login-bound check');
$api->answers = array(array('status' => 'pending'));
$count = count($api->calls);
checkHopzone(!$api->checkVote('192.0.2.1', 'player-a')->voted && count($api->calls) === $count + 1, 'pending does not fall back to IP');
$api->answers = array($completed);
checkHopzone(!$api->checkVote('192.0.2.1', 'player-b')->voted, 'IP cannot reuse another account vote');
$ipVote = $completed;
$ipVote['vote_id'] = 1252;
$api->answers = array($ipVote);
checkHopzone($api->checkVote('2001:db8::1', 'player-b')->voted, 'IPv6 fallback without reference');
checkHopzone($GLOBALS['hopzoneDb']->rows['player-b']['vote_id'] === '1252', 'IP fallback binds vote ID');
$api->answers = array(new RuntimeException('offline'));
checkHopzone($api->checkVote('192.0.2.1', 'player-a')->error, 'network failure fails closed');
$api->answers = array(array('vote_id' => 999, 'url' => 'https://other.example/vote'));
try { $api->prepareVote('player-c'); throw new LogicException('unsafe redirect accepted'); }
catch (RuntimeException $e) { checkHopzone(true, 'reject foreign redirect URL'); }
checkHopzone($GLOBALS['hopzoneDb']->released === 3, 'locks released after success and failure');
checkHopzone($api->getVoteUrl('player-a') === 'voteapi.php?action=hopzone_vote&top_id=2&csrf=fixture-csrf', 'local URL omits API key');
checkHopzone((bool)preg_grep('/VOTO CONFIRMADO/', $api->logs), 'confirmed vote logged');
checkHopzone((bool)preg_grep('/status=pending/', $api->logs), 'pending vote logged');
checkHopzone(strpos(implode(' ', $api->logs), 'fixture-key') === false, 'API key absent from logs');
