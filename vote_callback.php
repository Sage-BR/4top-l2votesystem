<?php
/**
 * VoteSystem — Callback Handler
 *
 * Receptor de postback HTTP para tops que chamam nosso servidor após o voto.
 * Atualmente suportado: ArenaTop100
 *
 * A entrega de reward principal ocorre via claimReward() em vote.php.
 * Este callback apenas registra o voto no log.
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

if (!file_exists(__DIR__ . '/config.php')) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/helpers.php';

// ── Detecta o top pelo parâmetro network ou pela origem ──────────────────────
$network = strtolower(trim(
    isset($_GET['network'])  ? $_GET['network']  :
   (isset($_POST['network']) ? $_POST['network'] : '')
));

// ArenaTop100 não manda ?network= — detecta pelo parâmetro 'secret' ou 'voted'
if (empty($network) && (isset($_POST['secret']) || isset($_GET['secret']))) {
    $network = 'arenatop100';
}

// Log de tudo que chega para debug
$logEntry = date('Y-m-d H:i:s')
    . ' | network=' . $network
    . ' | GET='     . json_encode($_GET)
    . ' | POST='    . json_encode($_POST)
    . ' | ip='      . clientIp() . "\n";
@file_put_contents(__DIR__ . '/vote_callback.log', $logEntry, FILE_APPEND | LOCK_EX);

// ── Roteamento por network ────────────────────────────────────────────────────
switch ($network) {
    case 'arenatop100':
        handleArenaTop100();
        break;
    default:
        handleGeneric($network);
        break;
}
exit;


// =============================================================================
// ArenaTop100 — postback
// Docs: https://www.arena-top100.com (dashboard > callback settings)
//
// Parâmetros recebidos (GET e POST):
//   secret  — API secret do painel (valida que veio deles)
//   voted   — 1 = voto válido, 0 = falhou/duplicado
//   userid  — login do jogador (passado no link via &id=LOGIN)
//   userip  — IP do votante
//   reset   — timestamp de quando pode votar novamente
// =============================================================================
function handleArenaTop100() {
    $secret = isset($_POST['secret']) ? trim($_POST['secret']) : (isset($_GET['secret']) ? trim($_GET['secret']) : '');
    $voted  = isset($_POST['voted'])  ? (int)$_POST['voted']  : (isset($_GET['voted'])  ? (int)$_GET['voted']   : 0);
    $login  = isset($_POST['userid']) ? trim($_POST['userid']) : (isset($_GET['userid']) ? trim($_GET['userid']) : '');
    $userip = isset($_POST['userip']) ? trim($_POST['userip']) : (isset($_GET['userip']) ? trim($_GET['userip']) : clientIp());

    // Voto inválido ou duplicado
    if ($voted !== 1) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    // Sem login não tem como registrar
    if (empty($login)) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    $db = getDB();

    // Busca o top ArenaTop100 ativo
    $stmt = $db->prepare(
        "SELECT * FROM 4top_tops WHERE top_btn = 'arenatop100.php' AND enabled = 1 LIMIT 1"
    );
    $stmt->execute();
    $top = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$top) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    // Valida o secret contra o token cadastrado no banco
    // Em desenvolvimento o ArenaTop100 manda secret=TEST — aceita para facilitar testes
    $tokenOk = ($secret === 'TEST')
        || empty($top['token'])
        || hash_equals((string)$top['token'], $secret);

    if (!$tokenOk) {
        @file_put_contents(__DIR__ . '/vote_callback.log',
            date('Y-m-d H:i:s') . " | ArenaTop100 | SECRET INVÁLIDO | secret=$secret\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(403);
        echo 'FORBIDDEN';
        return;
    }

    $topId = (int)$top['id'];

    if (hasVotedRecently($login, $topId)) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    $result = registerVote($login, $topId, $userip ?: clientIp());

    @file_put_contents(__DIR__ . '/vote_callback.log',
        date('Y-m-d H:i:s') . " | ArenaTop100 | login=$login | userip=$userip | result=$result\n",
        FILE_APPEND | LOCK_EX
    );

    http_response_code(200);
    echo 'OK';
}


// =============================================================================
// Handler genérico — outros tops que usem callback HTTP
// =============================================================================
function handleGeneric($network) {
    $token = trim(
        isset($_GET['token'])  ? $_GET['token']  :
       (isset($_POST['token']) ? $_POST['token'] : '')
    );
    $login = trim(
        isset($_GET['id'])    ? $_GET['id']    :
       (isset($_POST['id'])   ? $_POST['id']   :
       (isset($_GET['user'])  ? $_GET['user']  :
       (isset($_POST['user']) ? $_POST['user'] : '')))
    );
    $valid = (int)(
        isset($_GET['valid'])  ? $_GET['valid']  :
       (isset($_POST['valid']) ? $_POST['valid'] : 1)
    );

    if (!$login || $valid != 1) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    $db = getDB();

    $stmt = $db->prepare(
        "SELECT * FROM 4top_tops
         WHERE top_btn LIKE ? AND (token = ? OR token IS NULL OR token = '') AND enabled = 1
         LIMIT 1"
    );
    $stmt->execute(array('%' . $network . '%', $token));
    $top = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$top) {
        $stmt = $db->prepare(
            "SELECT * FROM 4top_tops WHERE top_btn LIKE ? AND enabled = 1 LIMIT 1"
        );
        $stmt->execute(array('%' . $network . '%'));
        $top = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$top) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    $topId = (int)$top['id'];

    if (hasVotedRecently($login, $topId)) {
        http_response_code(200);
        echo 'ALREADY_VOTED';
        return;
    }

    $result = registerVote($login, $topId, clientIp());

    http_response_code(200);
    echo $result === 'ok' ? 'OK' : 'ERROR';
}