<?php
/**
 * VoteSystem - Anticheat / VPN / Proxy detection
 *
 * Objetivo:
 *   - Detectar sinais de proxy/VPN/hosting sem quebrar o fluxo principal
 *   - Bloquear a interface de voto e claim quando houver risco
 *   - Manter o impacto baixo caso APIs externas falhem
 *
 * Regras:
 *   - Se a checagem externa falhar, retorna "safe" para não quebrar o site
 *   - Se houver sinal forte de proxy/VPN/hosting, marca como bloqueado
 *   - Cacheia resultado na sessão para reduzir chamadas externas
 */

if (!function_exists('anticheatAnalyze')) {
    function anticheatAnalyze($ip = '', $login = '') {
        if (!anticheatIsEnabled()) {
            return array(
                'blocked'    => false,
                'risk'       => 0,
                'reason'     => 'disabled',
                'source'     => 'config',
                'checked_at' => time(),
            );
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start(array('cookie_httponly' => true, 'cookie_samesite' => 'Lax'));
        }

        $ip = trim((string)$ip);
        $login = trim((string)$login);
        $cacheKey = 'vs_anticheat_' . md5($ip . '|' . $login);

        $cacheSec = defined('VS_ANTICHEAT_CACHE_SEC') ? (int)VS_ANTICHEAT_CACHE_SEC : 900;
        if (!empty($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
            $cached = $_SESSION[$cacheKey];
            if (!empty($cached['checked_at']) && (time() - (int)$cached['checked_at']) < $cacheSec) {
                return $cached;
            }
        }

        $result = array(
            'blocked'    => false,
            'risk'       => 0,
            'reason'     => '',
            'source'     => 'none',
            'checked_at' => time(),
        );

        if ($ip === '' || $ip === 'UNKNOWN' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $_SESSION[$cacheKey] = $result;
            return $result;
        }

        $signals = array();
        $trustedProxy = anticheatIsTrustedProxy();
        $headers = array(
            'HTTP_X_FORWARDED_FOR'   => 'x_forwarded_for',
            'HTTP_X_REAL_IP'         => 'x_real_ip',
            'HTTP_TRUE_CLIENT_IP'    => 'true_client_ip',
            'HTTP_FORWARDED'         => 'forwarded',
            'HTTP_VIA'               => 'via',
            'HTTP_PROXY_CONNECTION'  => 'proxy_connection',
        );

        foreach ($headers as $key => $name) {
            if (!empty($_SERVER[$key])) {
                $signals[] = $name . '=' . substr((string)$_SERVER[$key], 0, 120);
            }
        }

        $risk = 0;
        $reasons = array();

        if (!$trustedProxy && (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_X_REAL_IP']) || !empty($_SERVER['HTTP_TRUE_CLIENT_IP']) || !empty($_SERVER['HTTP_FORWARDED']))) {
            $risk += 20;
            $reasons[] = 'headers_proxy';
        }

        if (!$trustedProxy && (!empty($_SERVER['HTTP_VIA']) || !empty($_SERVER['HTTP_PROXY_CONNECTION']))) {
            $risk += 15;
            $reasons[] = 'proxy_header';
        }

        $external = anticheatQueryIpApi($ip);
        if (is_array($external)) {
            if (!empty($external['proxy'])) {
                $risk += 70;
                $reasons[] = 'ipapi_proxy';
            }
            if (!empty($external['hosting'])) {
                $risk += 10;
                $reasons[] = 'hosting';
            }
            if (!empty($external['message'])) {
                $result['source'] = 'ip-api.com';
            }
        }

        $blockThreshold = defined('VS_ANTICHEAT_RISK_BLOCK') ? (int)VS_ANTICHEAT_RISK_BLOCK : 70;
        if ($risk >= $blockThreshold) {
            $result['blocked'] = true;
        }

        $result['risk'] = min(100, $risk);
        $result['reason'] = implode(',', $reasons);
        if (!empty($signals)) {
            $result['signals'] = $signals;
        }

        $_SESSION[$cacheKey] = $result;
        if ($result['blocked'] && function_exists('logAnticheatDetection')) {
            $logData = $result;
            $logData['ip'] = $ip;
            $logData['login'] = $login;
            logAnticheatDetection($logData);
        }
        return $result;
    }

    function anticheatIsEnabled() {
        if (function_exists('getSetting')) {
            $value = getSetting('anticheat_enabled', '1');
            return (string)$value !== '0';
        }

        if (defined('VS_ANTICHEAT_ENABLED')) {
            return (bool)VS_ANTICHEAT_ENABLED;
        }

        return true;
    }

    function anticheatIsTrustedProxy() {
        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        if ($remoteAddr === '') return false;

        $cidrs = array();
        if (defined('VS_TRUSTED_PROXY_CIDRS') && is_array(VS_TRUSTED_PROXY_CIDRS)) {
            $cidrs = VS_TRUSTED_PROXY_CIDRS;
        }

        foreach ($cidrs as $cidr) {
            if (anticheatIpInCidr($remoteAddr, $cidr)) return true;
        }

        return false;
    }

    function anticheatIpInCidr($ip, $cidr) {
        $ip = trim((string)$ip);
        $cidr = trim((string)$cidr);
        if ($ip === '' || $cidr === '') return false;

        if (strpos($cidr, '/') === false) {
            return strcasecmp($ip, $cidr) === 0;
        }

        list($subnet, $mask) = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subBin = @inet_pton(trim($subnet));
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }

        $bits = strlen($ipBin) * 8;
        $mask = (int)$mask;
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

    function anticheatQueryIpApi($ip) {
        $timeout = defined('VS_ANTICHEAT_IPAPI_TIMEOUT') ? (int)VS_ANTICHEAT_IPAPI_TIMEOUT : 4;
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,message,proxy,hosting,mobile,query';

        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'ignore_errors' => true),
        ));
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || trim($body) === '') {
            return null;
        }

        $data = @json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['status']) && $data['status'] !== 'success') {
            return null;
        }

        return $data;
    }
}
