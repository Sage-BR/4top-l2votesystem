<?php
/** Integração DarkEden: contas, personagens e entrega direta na bag. */
if (!defined('INSTALLED')) { header('Location: install.php'); exit; }
require_once __DIR__ . '/game_projects.php';

// Reprodução do hash legado MySQL 3.23, sem depender de funções removidas do banco.
function gameOldPassword($password) {
    if (PHP_INT_SIZE < 8) throw new RuntimeException('O hash legado DK exige PHP de 64 bits.');
    $nr = 1345345333; $nr2 = 0x12345671; $add = 7;
    for ($i = 0, $length = strlen($password); $i < $length; $i++) {
        $c = ord($password[$i]);
        if ($c === 32 || $c === 9) continue;
        $nr = ($nr ^ ((($nr & 63) + $add) * $c + ($nr << 8))) & 0xffffffff;
        $nr2 = ($nr2 + (($nr2 << 8) ^ $nr)) & 0xffffffff;
        $add += $c;
    }
    return $password === '' ? '' : sprintf('%08x%08x', $nr & 0x7fffffff, $nr2 & 0x7fffffff);
}
function gameVerifyPassword($plainPassword, $storedHash) {
    if (!is_string($plainPassword) || !is_string($storedHash) || $storedHash === '') return false;
    switch (gamePasswordMode()) {
        case 'plain': return hash_equals($storedHash, $plainPassword);
        case 'mysql_old': return hash_equals(strtolower($storedHash), gameOldPassword($plainPassword));
        case 'mysql_password': return hash_equals(strtoupper($storedHash), '*' . strtoupper(sha1(sha1($plainPassword, true))));
    }
    return false;
}
function gameGetAccount($login) {
    gamePasswordMode();
    $stmt = getDB()->prepare('SELECT PlayerID AS login, Password AS password, Access AS account_access FROM Player WHERE PlayerID = ? LIMIT 1');
    $stmt->execute(array($login));
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}
function gameLogin($login, $password) {
    $account = gameGetAccount($login);
    if (!$account || $account['account_access'] !== 'ALLOW' || !gameVerifyPassword($password, $account['password'])) return false;
    $admins = defined('DK_ADMIN_ACCOUNTS') && is_array(DK_ADMIN_ACCOUNTS) ? DK_ADMIN_ACCOUNTS : array();
    return array('login' => $account['login'], 'access_level' => in_array($account['login'], $admins, true) ? 1 : 0);
}
function gameCharToken($race, $name) { return base64_encode($race . ':' . $name); }
function gameParseCharToken($token) {
    if (!is_string($token) || strlen($token) > 256) return false;
    $raw = base64_decode($token, true);
    if ($raw === false || base64_encode($raw) !== $token) return false;
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2 || !in_array($parts[0], array('Slayer', 'Vampire', 'Ousters'), true) || $parts[1] === '' || strlen($parts[1]) > 40) return false;
    return array('race' => $parts[0], 'name' => $parts[1]);
}
function gameGetChars($login) {
    // Slayer é o cadastro principal; Race aponta para os dados da raça atual.
    $stmt = getDB()->prepare("SELECT Name, Race FROM Slayer WHERE PlayerID = ? AND Active = 'ACTIVE' ORDER BY Slot, Name");
    $stmt->execute(array($login)); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); $chars = array();
    $races = array('SLAYER' => 'Slayer', 'VAMPIRE' => 'Vampire', 'OUSTERS' => 'Ousters');
    foreach ($rows as $row) {
        $race = $races[$row['Race']] ?? null;
        if (!$race) continue;
        $token = gameCharToken($race, $row['Name']);
        if (gameCharBelongsTo($login, $token)) $chars[] = array('obj_Id' => $token, 'char_name' => $row['Name'] . ' (' . $race . ')');
    }
    return $chars;
}
function gameCharBelongsTo($login, $token) {
    $char = gameParseCharToken($token);
    if (!$char) return false;
    $stmt = getDB()->prepare("SELECT c.Name FROM `{$char['race']}` c JOIN Slayer s ON s.Name = c.Name AND s.PlayerID = c.PlayerID WHERE c.PlayerID = ? AND c.Name = ? AND c.Active = 'ACTIVE' AND s.Active = 'ACTIVE' AND s.Race = ? LIMIT 1");
    $stmt->execute(array($login, $char['name'], strtoupper($char['race'])));
    return (bool)$stmt->fetch();
}
require_once __DIR__ . '/inventory.php';
function _ipValid($ip) {
    $ip = trim((string)$ip);
    if (preg_match('/^::ffff:(\\d+\\.\\d+\\.\\d+\\.\\d+)$/i', $ip, $m)) {
        $ip = $m[1];
    }
    return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
}

function _ipVersion($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return 0;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 4;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 6;
    return 0;
}

function _pickPreferredIp(array $candidates) {
    $firstValid = false;
    foreach ($candidates as $candidate) {
        $ip = _ipValid($candidate);
        if (!$ip) continue;
        if (_ipVersion($ip) === 4) return $ip;
        if ($firstValid === false) $firstValid = $ip;
    }
    return $firstValid;
}

function _pickFirstValidIp(array $candidates) {
    foreach ($candidates as $candidate) {
        $ip = _ipValid($candidate);
        if ($ip) return $ip;
    }
    return false;
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

    // Só confia em headers de proxy se REMOTE_ADDR for proxy confiável
    if ($trusted) {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // CF-Connecting-IP é o endereço real recebido; só usa alternativas se estiver inválido.
            $ip = _ipValid($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (!$ip) {
                $ip = _pickPreferredIp(array(
                    isset($_SERVER['HTTP_CF_PSEUDO_IPV4']) ? $_SERVER['HTTP_CF_PSEUDO_IPV4'] : '',
                    isset($_SERVER['HTTP_CF_CONNECTING_IPV6']) ? $_SERVER['HTTP_CF_CONNECTING_IPV6'] : '',
                ));
            }
            if ($ip) return array('ip' => $ip, 'source' => 'CF-Connecting-IP', 'remote_addr' => $remoteAddr, 'trusted_proxy' => $trusted);
        }

        if (!empty($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
            $ip = _pickPreferredIp(array($_SERVER['HTTP_TRUE_CLIENT_IP']));
            if ($ip) return array('ip' => $ip, 'source' => 'True-Client-IP', 'remote_addr' => $remoteAddr, 'trusted_proxy' => $trusted);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For é uma cadeia de cliente + proxies; manter o primeiro IP válido.
            $ip = _pickFirstValidIp(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            if ($ip) {
                return array('ip' => $ip, 'source' => 'X-Forwarded-For', 'remote_addr' => $remoteAddr, 'trusted_proxy' => $trusted);
            }
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = _pickPreferredIp(array($_SERVER['HTTP_X_REAL_IP']));
            if ($ip) {
                return array('ip' => $ip, 'source' => 'X-Real-IP', 'remote_addr' => $remoteAddr, 'trusted_proxy' => $trusted);
            }
        }
    }

    if ($trusted && !empty($_SERVER['HTTP_FORWARDED'])) {
        $candidates = array();
        if (preg_match_all('/for=(?:"?\\[?)([a-f0-9:.]+)(?:\\]?"?)/i', $_SERVER['HTTP_FORWARDED'], $m)) {
            $candidates = $m[1];
        }
        // Forwarded também é uma cadeia; não selecionar o IP de um proxy intermediário.
        $ip = _pickFirstValidIp($candidates);
        if ($ip) {
            return array('ip' => $ip, 'source' => 'Forwarded', 'remote_addr' => $remoteAddr, 'trusted_proxy' => true);
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

function clientIpSource() {
    $details = clientIpDetails();
    return isset($details['source']) ? $details['source'] : 'UNKNOWN';
}

// ── Sessão ────────────────────────────────────────────────────────────────────

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $hadCookie = isset($_COOKIE[session_name()]);
        $params = array('cookie_httponly' => true, 'cookie_samesite' => 'Lax');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $params['cookie_secure'] = true;
        }
        session_start($params);
        // Regenera se não havia cookie (evita session fixation em sessões novas)
        if (!$hadCookie) {
            session_regenerate_id(true);
        }
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
    $token = is_string($token) ? $token : '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
