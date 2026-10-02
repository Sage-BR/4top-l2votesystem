<?php
// Testes isolados, sem rede nem banco real.
ob_start();
require_once __DIR__ . '/../includes/helpers.php';
if (in_array('--endpoint', $argv, true)) {
    $_GET = array('action' => 'list_tops');
    require __DIR__ . '/../voteapi.php';
    exit;
}
function startSession() { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
function clientIp() { return '192.0.2.1'; }
function gameGetChars($login) { return array(array('obj_Id' => 1, 'char_name' => 'Fixture')); }
function gameCharBelongsTo($login, $id) { return true; }
function gameDeliverRewards($login, $rewards, $db, $id) {
    if (session_status() === PHP_SESSION_ACTIVE) throw new RuntimeException('Session locked during delivery');
    if ($db->failDelivery) throw new RuntimeException('Fixture delivery failure');
    $db->delivered++;
}
function getDB() { return $GLOBALS['fixtureDb']; }
function perfAssert($ok, $label) { if (!$ok) throw new RuntimeException($label); echo 'OK: ' . $label . PHP_EOL; }
class PerfDb {
    public $transaction = false;
    public $delivered = 0;
    public $released = 0;
    public $acquired = 0;
    public $cooldown = false;
    public $failDelivery = false;
    public function prepare($sql) { return new PerfStatement($this, $sql); }
    public function query($sql) { return new PerfStatement($this, $sql); }
    public function beginTransaction() { $this->transaction = true; }
    public function inTransaction() { return $this->transaction; }
    public function commit() { $this->transaction = false; $this->cooldown = true; }
    public function rollBack() { $this->transaction = false; }
}
class PerfStatement {
    private $db;
    private $sql;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute($params = array()) {
        if (strpos($this->sql, 'GET_LOCK') !== false) $this->db->acquired++;
        if (strpos($this->sql, 'RELEASE_LOCK') !== false) $this->db->released++;
    }
    public function fetchColumn() { return 1; }
    public function fetch($mode = null) { return strpos($this->sql, 'claimed_at') !== false && $this->db->cooldown ? array('claimed_at' => gmdate('Y-m-d H:i:s')) : false; }
    public function fetchAll($mode = null) { return strpos($this->sql, '4top_rewards') !== false ? array(array('item_id' => 57, 'quantity' => 1)) : array(); }
}
class DirectFixture {
    public function checkVote($ip, $login) { return TopResult::ok(123); }
    public function getVoteUrl($login) { return 'https://example.invalid/vote'; }
}
$adapter = new RemoteTopApi('unsupported', '', '');
perfAssert($adapter->checkVote('', 'fixture')->error, 'unsupported provider fails closed');
$property = new ReflectionProperty('RemoteTopApi', 'handler');
$property->setAccessible(true);
$property->setValue($adapter, new DirectFixture());
perfAssert($adapter->checkVote('', 'fixture')->voteTime === 123, 'local handler called directly without HTTP');
perfAssert($adapter->getVoteUrl('fixture') === 'https://example.invalid/vote', 'local vote URL delegation');
$GLOBALS['fixtureDb'] = new PerfDb();
startSession();
$_SESSION['vs_login'] = 'fixture';
$check = checkVotes('fixture', '192.0.2.1', 'fixture-hwid');
perfAssert($check['status'] === 'ok' && session_status() !== PHP_SESSION_ACTIVE, 'check releases session after saving results');
startSession();
perfAssert($_SESSION['vs_confirmed_hwid'] === 'fixture-hwid', 'HWID persists across session close');
$_SESSION['vs_confirmed_votes'] = array(1 => time());
$claim = claimReward('fixture', 1);
perfAssert($claim['status'] === 'ok' && $GLOBALS['fixtureDb']->delivered === 1, 'reward delivered with session unlocked');
perfAssert($GLOBALS['fixtureDb']->acquired === 2 && $GLOBALS['fixtureDb']->released === 2, 'account and HWID locks released after delivery');
$repeat = claimReward('fixture', 1);
perfAssert($repeat['status'] === 'error' && $GLOBALS['fixtureDb']->delivered === 1, 'consumed session authorization cannot be reused');
$_SESSION['vs_confirmed_votes'] = array(1 => time());
$repeat = claimReward('fixture', 1);
perfAssert($repeat['status'] === 'cooldown', 'database cooldown blocks another delivery');
$GLOBALS['fixtureDb']->cooldown = false;
$GLOBALS['fixtureDb']->failDelivery = true;
startSession();
$_SESSION['vs_confirmed_votes'] = array(1 => time());
$failure = claimReward('fixture', 1);
perfAssert($failure['status'] === 'error' && !$GLOBALS['fixtureDb']->transaction, 'failed delivery rolls back');
perfAssert($GLOBALS['fixtureDb']->released === $GLOBALS['fixtureDb']->acquired, 'locks released on failure and cooldown');
startSession();
$_SESSION['vs_login'] = 'different-account';
$mismatch = checkVotes('fixture', '192.0.2.1');
perfAssert($mismatch['status'] === 'error', 'account change prevents storing confirmation');
session_write_close();
ob_end_flush();
