<?php
/**
 * VoteSystem — Callback Handler
 *
 * Chamado por sites de top após um jogador votar.
 * Exemplo: https://seusite.com/vote_callback.php?network=hopzone&id=LOGIN&token=TOKEN&valid=1
 *
 * ATENÇÃO: Este sistema usa polling via CDN 4teambr como método primário.
 * Este callback é um receptor alternativo para tops que suportam postback HTTP.
 * A entrega de reward principal ocorre via claimReward() em vote.php.
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

if (!file_exists(__DIR__ . '/config.php')) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/helpers.php';

$network = strtolower(trim(isset($_GET['network']) ? $_GET['network'] : (isset($_POST['network']) ? $_POST['network'] : '')));
$token   = trim(isset($_GET['token'])   ? $_GET['token']   : (isset($_POST['token'])   ? $_POST['token']   : ''));
$login   = trim(isset($_GET['id'])      ? $_GET['id']      : (isset($_POST['id'])       ? $_POST['id']       :
           (isset($_GET['user'])        ? $_GET['user']    : (isset($_POST['user'])     ? $_POST['user']     : ''))));
$valid   = (int)(isset($_GET['valid'])  ? $_GET['valid']   : (isset($_POST['valid'])    ? $_POST['valid']    : 1));

// Log para debug
$logEntry = date('Y-m-d H:i:s') . ' | ' . $network . ' | login=' . $login . ' | valid=' . $valid . ' | ip=' . clientIp() . "\n";
@file_put_contents(__DIR__ . '/vote_callback.log', $logEntry, FILE_APPEND | LOCK_EX);

// Ignora callbacks inválidos ou sem login
if (!$login || $valid != 1) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$db = getDB();

// Busca o top pelo network e token
$stmt = $db->prepare(
    "SELECT * FROM icpvote_tops
     WHERE top_btn LIKE ? AND (token = ? OR token IS NULL OR token = '') AND enabled = 1
     LIMIT 1"
);
$stmt->execute(array('%' . $network . '%', $token));
$top = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback: qualquer top ativo com esse network
if (!$top) {
    $stmt = $db->prepare(
        "SELECT * FROM icpvote_tops WHERE top_btn LIKE ? AND enabled = 1 LIMIT 1"
    );
    $stmt->execute(array('%' . $network . '%'));
    $top = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$top) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$topId = (int)$top['id'];

// Checa cooldown
if (hasVotedRecently($login, $topId)) {
    http_response_code(200);
    echo 'ALREADY_VOTED';
    exit;
}

// Registra o voto no log (sem entrega de reward — reward é via claimReward)
$result = registerVote($login, $topId, clientIp());

http_response_code(200);
echo $result === 'ok' ? 'OK' : 'ERROR';
