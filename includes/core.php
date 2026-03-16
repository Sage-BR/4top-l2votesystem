<?php
/**
 * VoteSystem — Core (lógica de integração com o jogo)
 *
 * Responsabilidades:
 *   - Hash / verificação de senha por projeto
 *   - Busca de conta no banco do jogo
 *   - Busca de personagem (aCis)
 *   - Entrega de reward in-game
 *   - IP do cliente
 *   - Sessão / CSRF
 *
 * NÃO contém lógica de tops, votação ou UI — isso fica em helpers.php.
 *
 * Compatível: PHP 5.6 ~ 8.2
 */

if (!defined('INSTALLED')) {
    header('Location: install.php');
    exit;
}

// ── Hash de senha ─────────────────────────────────────────────────────────────
//
// aCis 370+  → base64_encode( sha1($pass, true) )
// L2JServer  → base64_encode( sha1($pass, true) )   (mesmo algoritmo)
// L2JMobius  → strtolower( hash('sha256', $pass) )
//
function gameHashPassword($plainPassword) {
    switch (GAME_PROJECT) {
        case 'l2jmobius':
            return strtolower(hash('sha256', $plainPassword));

        case 'acis':
        case 'l2jserver':
        default:
            return base64_encode(sha1($plainPassword, true));
    }
}

/**
 * Verifica senha em texto plano contra o hash armazenado.
 * Usa hash_equals para evitar timing attacks.
 */
function gameVerifyPassword($plainPassword, $storedHash) {
    $computed = gameHashPassword($plainPassword);
    return hash_equals((string)$storedHash, $computed);
}

// ── Conta do jogo ─────────────────────────────────────────────────────────────

/**
 * Retorna array com login, password e access_level da conta,
 * ou false se não encontrada.
 *
 * Coluna de access_level:
 *   aCis / L2JServer  → access_level  (snake_case)
 *   L2JMobius         → accessLevel   (camelCase)
 */
function gameGetAccount($login) {
    $col  = (GAME_PROJECT === 'l2jmobius') ? 'accessLevel' : 'access_level';
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT login, password, `{$col}` AS access_level FROM accounts WHERE login = ? LIMIT 1"
    );
    $stmt->execute(array($login));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: false;
}

/**
 * Tenta autenticar. Retorna array('login', 'access_level') ou false.
 */
function gameLogin($login, $password) {
    $account = gameGetAccount($login);
    if (!$account) return false;
    if (empty($account['password'])) return false;
    if (!gameVerifyPassword($password, $account['password'])) return false;

    return array(
        'login'        => $account['login'],
        'access_level' => (int)$account['access_level'],
    );
}

// ── Personagem (aCis) ─────────────────────────────────────────────────────────

/**
 * Retorna todos os personagens ativos da conta para o jogador escolher.
 * Retorna array de [['obj_Id' => ..., 'char_name' => ...], ...] ordenado por lastAccess DESC.
 */
function gameGetChars($login) {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT obj_Id, char_name FROM characters
         WHERE account_name = ?
           AND deletetime = 0
         ORDER BY lastAccess DESC"
    );
    $stmt->execute(array($login));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se o obj_Id pertence à conta. Proteção contra forja de obj_Id.
 */
function gameCharBelongsTo($login, $objId) {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT obj_Id FROM characters
         WHERE account_name = ? AND obj_Id = ? AND deletetime = 0
         LIMIT 1"
    );
    $stmt->execute(array($login, (int)$objId));
    return (bool)$stmt->fetch();
}

// ── Entrega de reward ─────────────────────────────────────────────────────────

/**
 * Entrega rewards ao jogador conforme o projeto configurado.
 *
 * aCis:
 *   Insere diretamente na tabela `items`.
 *   $objId = obj_Id escolhido pelo jogador. Se null, usa o mais recente.
 *
 * L2JServer / L2JMobius:
 *   Insere em `icpvote_pending_rewards` para entrega via cron ou mod Java.
 *
 * @param  string   $login    Login da conta
 * @param  array    $rewards  Rows de icpvote_rewards
 * @param  PDO      $db       Conexão PDO (dentro de transação)
 * @param  int|null $objId    obj_Id escolhido pelo jogador (aCis)
 * @return bool               true = entregue
 */
function gameDeliverRewards($login, array $rewards, $db, $objId = null) {
    if (empty($rewards)) return true;

    if (GAME_PROJECT === 'acis') {
        return _deliverRewardsAcis($login, $rewards, $db, $objId);
    }

    return _queueRewards($login, $rewards, $db);
}

/**
 * aCis — insere itens diretamente na tabela `items`.
 *
 * Schema items (aCis):
 *   owner_id      INT(11)           — obj_Id do personagem
 *   object_id     INT(11) PK        — ID único do item (gerado aqui)
 *   item_id       SMALLINT UNSIGNED — ID do item no jogo
 *   count         INT UNSIGNED      — quantidade
 *   enchant_level SMALLINT          — 0
 *   loc           VARCHAR(10)       — 'INVENTORY'
 *   loc_data      INT               — 0
 *   custom_type1  INT               — 0
 *   custom_type2  INT               — 0
 *   mana_left     SMALLINT          — -1
 *   time          BIGINT            — 0
 */
function _deliverRewardsAcis($login, array $rewards, $db, $objId = null) {
    // Usa o obj_Id escolhido pelo jogador; fallback para o mais recente
    if ($objId !== null) {
        $ownerId = (int)$objId;
    } else {
        $chars   = gameGetChars($login);
        $ownerId = !empty($chars) ? (int)$chars[0]['obj_Id'] : null;
    }

    if ($ownerId === null) {
        // Sem personagem — usa fila como fallback
        return _queueRewards($login, $rewards, $db);
    }

    // Reserva bloco de object_ids com lock para evitar duplicata em concorrência
    $maxStmt = $db->query("SELECT COALESCE(MAX(object_id), 268435456) FROM items FOR UPDATE");
    $maxId   = (int)$maxStmt->fetchColumn();

    $ins = $db->prepare(
        "INSERT INTO items
            (owner_id, object_id, item_id, count, enchant_level,
             loc, loc_data, custom_type1, custom_type2, mana_left, time)
         VALUES
            (?, ?, ?, ?, 0, 'INVENTORY', 0, 0, 0, -1, 0)"
    );

    foreach ($rewards as $r) {
        $maxId++;
        $ins->execute(array(
            $ownerId,
            $maxId,
            (int)$r['item_id'],
            (int)$r['quantity'],
        ));
    }

    return true;
}

/**
 * L2JServer / L2JMobius / fallback aCis sem personagem.
 * Insere em icpvote_pending_rewards para entrega externa.
 */
function _queueRewards($login, array $rewards, $db) {
    $stmt = $db->prepare(
        "INSERT INTO icpvote_pending_rewards (login, item_id, quantity, created_at, delivered)
         VALUES (?, ?, ?, NOW(), 0)"
    );
    foreach ($rewards as $r) {
        $stmt->execute(array($login, (int)$r['item_id'], (int)$r['quantity']));
    }
    return true;
}

// ── IP do cliente ─────────────────────────────────────────────────────────────

/**
 * Retorna o IPv4 real do visitante.
 * Ordem: Cloudflare → X-Real-IP → X-Forwarded-For → REMOTE_ADDR.
 * IPs privados/reservados na cadeia X-Forwarded-For são ignorados.
 */
function clientIp() {
    // Cloudflare — já é o IP final do usuário, confiar diretamente
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }

    // X-Real-IP (nginx)
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }

    // X-Forwarded-For — pega o primeiro IP público da cadeia
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    // Conexão direta — aceita qualquer IP válido (pode ser privado em LAN)
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = trim($_SERVER['REMOTE_ADDR']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return 'UNKNOWN';
}

// ── Sessão ────────────────────────────────────────────────────────────────────

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(array('cookie_httponly' => true, 'cookie_samesite' => 'Lax'));
    }
}

function sessionLogin(array $account) {
    startSession();
    session_regenerate_id(true);
    $_SESSION['vs_login']  = $account['login'];
    $_SESSION['vs_access'] = (int)$account['access_level'];
}

function currentLogin() {
    startSession();
    return isset($_SESSION['vs_login']) ? $_SESSION['vs_login'] : null;
}

function currentAccessLevel() {
    startSession();
    return isset($_SESSION['vs_access']) ? (int)$_SESSION['vs_access'] : 0;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrfToken() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}