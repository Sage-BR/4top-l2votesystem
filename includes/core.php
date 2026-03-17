<?php
/**
 * VoteSystem — Core
 * Compatível: aCis | L2JOrion | L2JMobius | L2JLisvus
 */

if (!defined('INSTALLED')) {
    header('Location: install.php');
    exit;
}

// ── Hash de senha ─────────────────────────────────────────────────────────────
//
// aCis ≤ 408 / L2JOrion / L2JMobius → base64_encode( sha1($pass, true) )
// aCis 409+                          → BCrypt (password_verify nativo do PHP)
//
// O sistema detecta automaticamente pelo comprimento do hash armazenado:
//   BCrypt  → sempre começa com '$2' e tem 60 chars
//   SHA1B64 → 28 chars
//
function gameVerifyPassword($plainPassword, $storedHash) {
    // BCrypt — aCis 409+
    if (strlen($storedHash) === 60 && strncmp($storedHash, '$2', 2) === 0) {
        return password_verify($plainPassword, $storedHash);
    }
    // SHA1 Base64 — aCis ≤408, L2JOrion, L2JMobius
    return hash_equals((string)$storedHash, base64_encode(sha1($plainPassword, true)));
}

// ── Conta do jogo ─────────────────────────────────────────────────────────────
//
// aCis / L2JOrion / L2JLisvus → access_level  (snake_case)
// L2JMobius                   → accessLevel   (camelCase)
//
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

// ── Personagem ────────────────────────────────────────────────────────────────
//
// aCis / L2JOrion / L2JLisvus → obj_Id,  account_name, deletetime, lastAccess
// L2JMobius                   → charId,  account_name, deletetime, lastAccess
//
function _charIdCol() {
    return (GAME_PROJECT === 'l2jmobius') ? 'charId' : 'obj_Id';
}

function gameGetChars($login) {
    $col  = _charIdCol();
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT `{$col}` AS obj_Id, char_name FROM characters
         WHERE account_name = ?
           AND deletetime = 0
         ORDER BY lastAccess DESC"
    );
    $stmt->execute(array($login));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gameCharBelongsTo($login, $objId) {
    $col  = _charIdCol();
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT `{$col}` FROM characters
         WHERE account_name = ? AND `{$col}` = ? AND deletetime = 0
         LIMIT 1"
    );
    $stmt->execute(array($login, (int)$objId));
    return (bool)$stmt->fetch();
}

// ── Entrega de reward ─────────────────────────────────────────────────────────
//
// Todos os projetos entregam direto na tabela items.
//
// Schema por projeto:
//   aCis / L2JOrion  → owner_id, object_id, item_id, count, enchant_level,
//                       loc, loc_data, custom_type1, custom_type2, mana_left
//   L2JMobius        → idem + time
//   L2JLisvus        → owner_id, object_id, item_id, count, enchant_level,
//                       loc, loc_data, custom_type1, custom_type2
//                       (sem mana_left, sem time)
//
function gameDeliverRewards($login, array $rewards, $db, $objId = null) {
    if (empty($rewards)) return true;
    return _deliverRewardsGame($login, $rewards, $db, $objId);
}

function _deliverRewardsGame($login, array $rewards, $db, $objId = null) {
    $ownerId = _resolveOwnerId($login, $objId, $db);
    if ($ownerId === null) return false;

    $maxId = _nextObjectId($db);

    switch (GAME_PROJECT) {
        case 'l2jmobius':
            $ins = $db->prepare(
                "INSERT INTO items
                    (owner_id, object_id, item_id, count, enchant_level,
                     loc, loc_data, custom_type1, custom_type2, mana_left, time)
                 VALUES (?, ?, ?, ?, 0, 'INVENTORY', 0, 0, 0, -1, 0)"
            );
            break;

        case 'l2jlisvus':
            $ins = $db->prepare(
                "INSERT INTO items
                    (owner_id, object_id, item_id, count, enchant_level,
                     loc, loc_data, custom_type1, custom_type2)
                 VALUES (?, ?, ?, ?, 0, 'INVENTORY', 0, 0, 0)"
            );
            break;

        default: // acis, l2jorion
            $ins = $db->prepare(
                "INSERT INTO items
                    (owner_id, object_id, item_id, count, enchant_level,
                     loc, loc_data, custom_type1, custom_type2, mana_left)
                 VALUES (?, ?, ?, ?, 0, 'INVENTORY', 0, 0, 0, -1)"
            );
            break;
    }

    foreach ($rewards as $r) {
        $ins->execute(array($ownerId, ++$maxId, (int)$r['item_id'], (int)$r['quantity']));
    }
    return true;
}

// ── Helpers internos ──────────────────────────────────────────────────────────

function _resolveOwnerId($login, $objId, $db) {
    if ($objId !== null) return (int)$objId;
    $chars = gameGetChars($login);
    // gameGetChars já retorna charId ou obj_Id mapeado como obj_Id
    return !empty($chars) ? (int)$chars[0]['obj_Id'] : null;
}

function _nextObjectId($db) {
    $stmt = $db->query("SELECT COALESCE(MAX(object_id), 268435456) FROM items FOR UPDATE");
    return (int)$stmt->fetchColumn();
}

// ── IP do cliente ─────────────────────────────────────────────────────────────

function clientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = trim($_SERVER['REMOTE_ADDR']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
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