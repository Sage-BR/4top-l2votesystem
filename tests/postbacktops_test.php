<?php
ob_start();
// Sem rede ou banco real: testa os contratos de resposta e autenticação de callback.
$source = file_get_contents(__DIR__ . '/../includes/top_handlers.php');
$start = strpos($source, 'final class TopResult');
eval(substr($source, $start, strpos($source, 'class FourTopTop') - $start));
eval(substr($source, strpos($source, 'abstract class IpCheckerTop')));
require_once __DIR__ . '/../includes/postbacktops.php';
define('VOTE_POSTBACK_ALLOWED_IPS', array(
    'mmtop200.php' => array('192.0.2.10'),
    'gamingtop100.php' => array('192.0.2.11'),
    'top100arena.php' => array('2001:db8::12'),
));
function clientIp() { return $_SERVER['REMOTE_ADDR']; }
function testVote($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'OK: ' . $label . PHP_EOL;
}
trait CheckerFixture {
    public $body = '';
    public $url = '';
    protected function httpGet($url, $headers = array()) { $this->url = $url; return $this->body; }
    protected function log($msg) {}
}
class MMProbe extends MMTop200Top { use CheckerFixture; }
class GamingProbe extends GamingTop100Top { use CheckerFixture; }
class ArenaProbe extends Top100ArenaTop { use CheckerFixture; }
foreach (array(new MMProbe('fixture-token', '42'), new GamingProbe('', '42')) as $checker) {
    foreach (array('true', '1') as $body) {
        $checker->body = $body;
        testVote($checker->checkVote('192.0.2.1', 'player')->voted, get_class($checker) . ' positive ' . $body);
    }
    foreach (array('false', '0') as $body) {
        $checker->body = $body;
        $result = $checker->checkVote('192.0.2.1', 'player');
        testVote(!$result->voted && !$result->error, get_class($checker) . ' negative ' . $body);
    }
    foreach (array('<html>blocked</html>', '', 'garbage', false) as $body) {
        $checker->body = $body;
        $result = $checker->checkVote('192.0.2.1', 'player');
        testVote(!$result->voted && $result->error, get_class($checker) . ' malformed/unavailable');
    }
}
$arena = new ArenaProbe('', '42');
foreach (array('{"voted":true}' => true, '{"voted":false}' => false, '{"voted":1}' => true) as $body => $expected) {
    $arena->body = $body;
    $result = $arena->checkVote('2001:db8::1', 'player');
    testVote($result->voted === $expected && !$result->error, 'Arena JSON ' . $body);
}
foreach (array('{"voted":"false"}', '{}', '<html>challenge</html>') as $body) {
    $arena->body = $body;
    testVote($arena->checkVote('192.0.2.1', 'player')->error, 'Arena rejects malformed JSON contract');
}
testVote(postbackTopOriginAllowed('mmtop200.php', '192.0.2.10'), 'MM validator accepted');
testVote(!postbackTopOriginAllowed('mmtop200.php', '192.0.2.99'), 'MM foreign source rejected');
testVote(postbackTopOriginAllowed('top100arena.php', '2001:0db8:0:0:0:0:0:12'), 'equivalent IPv6 accepted');
testVote(!postbackTopSupported('xtremetop100.php'), 'Xtreme remains excluded');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.99';
$_GET = array(); $_POST = array('user_id' => '123');
ob_start(); handleVotePostback(); $response = json_decode(ob_get_clean(), true);
testVote(http_response_code() === 403 && $response['ok'] === false, 'forged callback rejected before database');
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$_POST = array('uid' => 'player', 'vote_counted' => 'false');
ob_start(); handleVotePostback(); $response = json_decode(ob_get_clean(), true);
testVote($response['ignored'] === 'not_counted', 'uncounted MM vote ignored');
class RefFixtureDb {
    public $bindings = array();
    public function prepare($sql) { return new RefFixtureStatement($this, $sql); }
}
class RefFixtureStatement {
    private $db;
    private $sql;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute($params) { if (strpos($this->sql, 'INSERT') === 0) $this->db->bindings[] = $params; }
    public function fetchColumn() { return 17; }
    public function fetch($mode = null) { return isset($GLOBALS['cachedVote']) ? $GLOBALS['cachedVote'] : false; }
}
function getDB() { return $GLOBALS['refFixtureDb']; }
$GLOBALS['refFixtureDb'] = new RefFixtureDb();
$top = array('id' => 3, 'top_id' => '42', 'top_btn' => 'gamingtop100.php');
testVote(postbackTopVoteUrl($top, 'player-one') === 'https://www.gamingtop100.net/in-42-17', 'Gaming numeric account reference');
$top['top_btn'] = 'top100arena.php';
testVote(postbackTopVoteUrl($top, 'player-one') === 'https://www.top100arena.com/listing/42/vote?incentive=17', 'Arena numeric incentive');
$top['top_btn'] = 'mmtop200.php';
testVote(postbackTopVoteUrl($top, 'player-one') === 'https://mmtop200.com/vote/42/player-one', 'MM login in voting URL');
testVote($GLOBALS['refFixtureDb']->bindings[0][1] === 'player-one', 'numeric reference bound to actual login');
function getLastVote($login, $topId) { return $GLOBALS['cachedVote']; }
function registerVote($login, $topId, $ip) {
    $GLOBALS['savedVoteCount']++;
    $GLOBALS['cachedVote'] = array('voted_at' => gmdate('Y-m-d H:i:s', time() - 60), 'seconds_ago' => 60);
    return 'ok';
}
function getTopKey($button) { return $button; }
class RemoteTopApi {
    public function __construct($key, $token, $id) {}
    public function checkVote($ip, $login) {
        $GLOBALS['remoteCheckCount']++;
        return (object)array('error' => false, 'voted' => true, 'voteTime' => time());
    }
}
$GLOBALS['cachedVote'] = false;
$GLOBALS['savedVoteCount'] = 0;
$GLOBALS['remoteCheckCount'] = 0;
$top['token'] = '';
$adapter = new PostbackTopApi($top);
$first = $adapter->checkVote('192.0.2.20', 'player-one');
testVote(!$first->voted && !$first->error, 'without account postback vote remains pending');
$GLOBALS['cachedVote'] = array('voted_at' => gmdate('Y-m-d H:i:s', time() - 60), 'seconds_ago' => 60);
$second = $adapter->checkVote('192.0.2.20', 'player-one');
$third = $adapter->checkVote('192.0.2.21', 'player-one');
testVote($second->voted && $second->voteTime === $third->voteTime && $second->voteTime < time(), 'account postback preserves timestamp despite IP change');
testVote($GLOBALS['savedVoteCount'] === 0, 'checks do not register votes from IP');
testVote($GLOBALS['remoteCheckCount'] === 0, 'IP checker cannot confirm account');
$GLOBALS['cachedVote']['seconds_ago'] = 43200;
testVote(!$adapter->checkVote('192.0.2.20', 'player-one')->voted, 'expired account postback cannot confirm vote');
ob_end_flush();
