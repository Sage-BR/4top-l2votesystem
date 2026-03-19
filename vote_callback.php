<?php
/**
 * VoteSystem — Callback Handler
 *
 * Receptor de postback HTTP para tops que chamam nosso servidor após o voto.
 * Atualmente suportado: ArenaTop100, GamingTop100
 *
 * A entrega de reward principal ocorre via claimReward() em vote.php.
 * Este callback apenas registra o voto no log.
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

if (!file_exists(__DIR__ . '/.installed')) { http_response_code(404); exit; }
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

// GamingTop100 manda GET ?user_id= sem parâmetro network
// Validamos pela origem (IP do servidor deles) no handler
if (empty($network) && isset($_GET['user_id'])) {
    $network = 'gamingtop100';
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
    case 'gamingtop100':
        handleGamingTop100();
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
    // token vazio no banco = top não configurado corretamente = rejeita
    $tokenOk = isset($top['token'])
        && is_string($top['token'])
        && $top['token'] !== ''
        && hash_equals((string)$top['token'], (string)$secret);

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
// GamingTop100 — postback
// Docs: https://gamingtop100.net (dashboard > My Servers > Postback URL)
//
// Configuração no painel GamingTop100:
//   Postback URL: http://SEU_SITE/vote_callback.php
//   Voting link:  http://gamingtop100.net/in-SITE_ID-LOGIN_DO_JOGADOR
//
// Parâmetros recebidos (GET):
//   user_id — login do jogador (passado no link via -LOGIN)
//
// Segurança: valida que a requisição vem do IP do servidor gamingtop100.net
//            (gethostbyname resolve o hostname para o IP atual deles)
// =============================================================================
function handleGamingTop100() {
    // ── Valida origem: só aceita requisições do servidor GamingTop100 ─────────
    // gethostbyname resolve em tempo real — se eles mudarem o IP, continua funcionando
    $requesterIp  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $gamingTopIp  = (string)gethostbyname('gamingtop100.net');

    // gethostbyname retorna o hostname se a resolução falhar — verificamos isso
    if ($gamingTopIp === 'gamingtop100.net') {
        // DNS falhou — loga e rejeita por segurança
        @file_put_contents(__DIR__ . '/vote_callback.log',
            date('Y-m-d H:i:s') . " | GamingTop100 | DNS RESOLVE FALHOU\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(503);
        echo 'DNS_ERROR';
        return;
    }

    if ($requesterIp !== $gamingTopIp) {
        @file_put_contents(__DIR__ . '/vote_callback.log',
            date('Y-m-d H:i:s') . " | GamingTop100 | IP INVÁLIDO | requester=$requesterIp | expected=$gamingTopIp\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(403);
        echo 'FORBIDDEN';
        return;
    }

    // ── Lê e valida user_id (login do jogador) ────────────────────────────────
    // GamingTop100 exige que user_id contenha apenas números — mas nosso login
    // pode ser alfanumérico. O link de votação deve usar o login do jogador
    // e o GamingTop100 devolve exatamente o que foi passado.
    $rawUserId = (string)(isset($_GET['user_id']) ? $_GET['user_id'] : '');

    // Sanitiza: remove tudo que não seja alfanumérico, underscore ou hífen
    $login = preg_replace('/[^a-zA-Z0-9_\-]/', '', $rawUserId);
    $login = substr($login, 0, 45);

    if (empty($login)) {
        @file_put_contents(__DIR__ . '/vote_callback.log',
            date('Y-m-d H:i:s') . " | GamingTop100 | USER_ID INVÁLIDO | raw=" . substr($rawUserId, 0, 50) . "\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(200);
        echo 'OK';
        return;
    }

    // ── Busca o top GamingTop100 ativo no banco ───────────────────────────────
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM 4top_tops WHERE top_btn = 'gamingtop100.php' AND enabled = 1 LIMIT 1"
    );
    $stmt->execute();
    $top = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$top) {
        http_response_code(200);
        echo 'OK';
        return;
    }

    $topId = (int)$top['id'];

    // ── Evita registro duplicado (cooldown 12h) ───────────────────────────────
    if (hasVotedRecently($login, $topId)) {
        @file_put_contents(__DIR__ . '/vote_callback.log',
            date('Y-m-d H:i:s') . " | GamingTop100 | COOLDOWN | login=$login\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(200);
        echo 'OK';
        return;
    }

    // ── Registra o voto ───────────────────────────────────────────────────────
    $result = registerVote($login, $topId, $requesterIp);

    @file_put_contents(__DIR__ . '/vote_callback.log',
        date('Y-m-d H:i:s') . " | GamingTop100 | login=$login | result=$result\n",
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