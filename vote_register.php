<?php
/**
 * VoteSystem — Vote Register
 *
 * Chamado via JS quando o jogador clica no botão de voto.
 * Registra o clique no log para rastreamento; a verificação real
 * do voto e entrega de reward ocorrem via claimReward() em vote.php.
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

if (!file_exists(__DIR__ . '/.installed')) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();
header('Content-Type: application/json');

$login = currentLogin();
if (!$login) {
    echo json_encode(array('ok' => false, 'msg' => 'not_logged'));
    exit;
}

$csrf = isset($_GET['csrf']) ? $_GET['csrf'] : '';
if (!verifyCsrf($csrf)) {
    echo json_encode(array('ok' => false, 'msg' => 'csrf_invalid'));
    exit;
}

$topId = (int)(isset($_GET['top_id']) ? $_GET['top_id'] : 0);
if ($topId <= 0) {
    echo json_encode(array('ok' => false, 'msg' => 'invalid_top'));
    exit;
}

// Verifica se o top existe e está ativo
$db   = getDB();
$stmt = $db->prepare("SELECT id FROM 4top_tops WHERE id = ? AND enabled = 1 LIMIT 1");
$stmt->execute(array($topId));
if (!$stmt->fetch()) {
    echo json_encode(array('ok' => false, 'msg' => 'top_not_found'));
    exit;
}

// Checa cooldown
if (hasVotedRecently($login, $topId)) {
    $last = getLastVote($login, $topId);
    $remaining = $last ? max(0, 43200 - (int)$last['seconds_ago']) : 0;
    echo json_encode(array(
        'ok'        => false,
        'msg'       => 'cooldown',
        'remaining' => $remaining,
        'formatted' => formatCooldown($remaining),
    ));
    exit;
}

// Registra o clique (não é o voto final — só rastreamento)
$result = registerVote($login, $topId, clientIp());

echo json_encode(array('ok' => $result === 'ok'));