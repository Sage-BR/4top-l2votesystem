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
// L2JMobius / L2JSunrise      → accessLevel   (camelCase)
//
function gameGetAccount($login) {
    $col  = (GAME_PROJECT === 'l2jmobius' || GAME_PROJECT === 'l2jsunrise') ? 'accessLevel' : 'access_level';
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
// L2JMobius / L2JSunrise      → charId,  account_name, deletetime, lastAccess
//
function _charIdCol() {
    return (GAME_PROJECT === 'l2jmobius' || GAME_PROJECT === 'l2jsunrise') ? 'charId' : 'obj_Id';
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
//   L2JMobius /
//   L2JSunrise       → idem + time
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
        case 'l2jsunrise':
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

        case 'l2mythras':
            // items do L2Mythras não tem mana_left nem time
            // tem life_time, augmentation_id, attribute_* e outros campos NOT NULL
            $ins = $db->prepare(
                "INSERT INTO items
                    (object_id, owner_id, item_id, count, enchant_level,
                     loc, loc_data, life_time, augmentation_id,
                     attribute_fire, attribute_water, attribute_wind,
                     attribute_earth, attribute_holy, attribute_unholy,
                     custom_type1, custom_type2, custom_flags,
                     agathion_energy, visual_item_id)
                 VALUES (?, ?, ?, ?, 0, 'INVENTORY', 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)"
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
        // L2Mythras tem object_id antes de owner_id no INSERT
        if (GAME_PROJECT === 'l2mythras') {
            $ins->execute(array(++$maxId, $ownerId, (int)$r['item_id'], (int)$r['quantity']));
        } else {
            $ins->execute(array($ownerId, ++$maxId, (int)$r['item_id'], (int)$r['quantity']));
        }
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

function _ipValid($ip) {
    $ip = trim((string)$ip);
    return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
}

function _ipInCidr($ip, $cidr) {
    $ip = _ipValid($ip);
    if (!$ip) return false;
    $cidr = trim((string)$cidr);
    if ($cidr === '') return false;

    if (strpos($cidr, '/') === false) {
        return $ip === _ipValid($cidr);
    }

    list($subnet, $mask) = explode('/', $cidr, 2);
    $subnet = _ipValid($subnet);
    if (!$subnet) return false;

    $mask = (int)$mask;
    $ipBin = @inet_pton($ip);
    $subBin = @inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) return false;

    $bits = strlen($ipBin) * 8;
    if ($mask < 0 || $mask > $bits) return false;

    $bytes = (int)floor($mask / 8);
    $rem   = $mask % 8;

    for ($i = 0; $i < $bytes; $i++) {
        if ($ipBin[$i] !== $subBin[$i]) return false;
    }

    if ($rem === 0) return true;
    $bitmask = chr((0xFF << (8 - $rem)) & 0xFF);
    return (($ipBin[$bytes] & $bitmask) === ($subBin[$bytes] & $bitmask));
}

function _trustedProxyCidrs() {
    return (defined('VS_TRUSTED_PROXY_CIDRS') && is_array(VS_TRUSTED_PROXY_CIDRS))
        ? VS_TRUSTED_PROXY_CIDRS
        : array();
}

function _isTrustedProxy($remoteAddr) {
    $remoteAddr = _ipValid($remoteAddr);
    if (!$remoteAddr) return false;
    foreach (_trustedProxyCidrs() as $cidr) {
        if (_ipInCidr($remoteAddr, $cidr)) return true;
    }
    return false;
}

function clientIpDetails() {
    $remoteAddr = _ipValid(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $trusted    = _isTrustedProxy($remoteAddr);

    if ($trusted && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = _ipValid($_SERVER['HTTP_CF_CONNECTING_IP']);
        if ($ip) return array('ip' => $ip, 'source' => 'CF-Connecting-IP', 'remote_addr' => $remoteAddr, 'trusted_proxy' => true);
    }

    if ($trusted && !empty($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
        $ip = _ipValid($_SERVER['HTTP_TRUE_CLIENT_IP']);
        if ($ip) return array('ip' => $ip, 'source' => 'True-Client-IP', 'remote_addr' => $remoteAddr, 'trusted_proxy' => true);
    }

    if ($trusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $ip = _ipValid($part);
            if ($ip) {
                return array('ip' => $ip, 'source' => 'X-Forwarded-For', 'remote_addr' => $remoteAddr, 'trusted_proxy' => true);
            }
        }
    }

    if ($trusted && !empty($_SERVER['HTTP_FORWARDED'])) {
        if (preg_match_all('/for=(?:"?\\[?)([a-f0-9:.]+)(?:\\]?"?)/i', $_SERVER['HTTP_FORWARDED'], $m)) {
            foreach ($m[1] as $part) {
                $ip = _ipValid($part);
                if ($ip) {
                    return array('ip' => $ip, 'source' => 'Forwarded', 'remote_addr' => $remoteAddr, 'trusted_proxy' => true);
                }
            }
        }
    }

    if ($remoteAddr) {
        return array('ip' => $remoteAddr, 'source' => 'REMOTE_ADDR', 'remote_addr' => $remoteAddr, 'trusted_proxy' => false);
    }

    return array('ip' => 'UNKNOWN', 'source' => 'UNKNOWN', 'remote_addr' => null, 'trusted_proxy' => false);
}

function clientIp() {
    $details = clientIpDetails();
    return isset($details['ip']) ? $details['ip'] : 'UNKNOWN';
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
