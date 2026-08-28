<?php

/**
 * Shared hardening helpers used by the public API, peer protocol, admin UI, and dapps.
 * Consensus and ledger rules are not changed here.
 */
class Security
{
    const PEER_PROTOCOL_METHODS = [
        'peer',
        'ping',
        'submitTransaction',
        'submitBlock',
        'submitBlockNew',
        'currentBlock',
        'getBlock',
        'getBlocks',
        'getPeerBlocks',
        'getPeers',
        'getAppsHash',
        'updateDapps',
        'checkDapps',
        'updateMasternode',
        'getMasternode',
        'propagateMsg7',
        'processedMessage',
        'deepCheck',
        'checkMyPeer',
        'getDbBlocks',
        'getInfo',
        'emitToScoket'
    ];

    const DEV_API_METHODS = [
        'nodeDevCommand',
        'nodeDevInfo',
        'nodeDebug',
    ];

    const SENSITIVE_CONFIG_KEYS = [
        'db_connect',
        'db_user',
        'db_pass',
        'admin_password',
        'admin_public_key',
        'miner_private_key',
        'generator_private_key',
        'masternode_private_key',
        'dapps_private_key',
        'allow_auto_update',
        'trusted_proxies',
        'cors_origin',
    ];

    const ADMIN_TASKS = [
        'Sync',
        'NodeMiner',
        'Dapps',
        'Masternode',
        'Cron',
    ];

    static function h($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    static function safeSessionId($id)
    {
        return preg_replace('/[^a-zA-Z0-9,-]/', '', (string)$id);
    }

    static function corsOrigin()
    {
        global $_config;
        $origin = '*';
        if (isset($_config['cors_origin']) && is_string($_config['cors_origin']) && $_config['cors_origin'] !== '') {
            $origin = $_config['cors_origin'];
        }
        if ($origin !== '*' && !self::isSameOriginUrl($origin) && !preg_match('#^https?://[a-z0-9.-]+(?::[0-9]+)?$#i', $origin)) {
            $origin = '*';
        }
        return $origin;
    }

    static function sendCorsHeaders()
    {
        if (headers_sent()) {
            return;
        }
        $origin = self::corsOrigin();
        header('Access-Control-Allow-Origin: '.$origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        if ($origin !== '*') {
            header('Vary: Origin');
        }
    }

    static function getRemoteAddr()
    {
        global $_config;
        $remote = isset($_SERVER['REMOTE_ADDR']) ? san_ip($_SERVER['REMOTE_ADDR']) : '';
        $trusted = [];
        if (isset($_config['trusted_proxies']) && is_array($_config['trusted_proxies'])) {
            $trusted = $_config['trusted_proxies'];
        }

        $useForwarded = $remote !== '' && in_array($remote, $trusted, true);
        $ip = $remote;
        if ($useForwarded) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = san_ip($_SERVER['HTTP_CF_CONNECTING_IP']);
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = san_ip(trim($xff[0]));
            }
        }

        $validated = Peer::validateIp($ip);
        if ($validated) {
            return $validated;
        }
        $fallback = Peer::validateIp($remote);
        if ($fallback) {
            return $fallback;
        }
        if (!$ip) {
            _log("Peer Request: invalid ip = $ip SERVER=".json_encode($_SERVER));
        }
        return $ip;
    }

    static function isSafePeerUrl($url)
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || $host === '0.0.0.0' || $host === '[::1]') {
            return DEVELOPMENT;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ok = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($ok === false) {
                return DEVELOPMENT;
            }
        } else {
            if (!preg_match('/^[a-z0-9.-]+$/i', $host) || strpos($host, '..') !== false) {
                return false;
            }
        }
        if (isset($parts['port'])) {
            $port = (int)$parts['port'];
            if ($port < 1 || $port > 65535) {
                return false;
            }
        }
        return true;
    }

    static function isSafeRedirect($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }
        if (preg_match('/[\r\n]/', $url)) {
            return false;
        }
        if (isset($url[0]) && $url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
            return true;
        }
        return self::isSameOriginUrl($url);
    }

    static function isSameOriginUrl($url)
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = strtolower($parts['host']);
        $reqHost = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if ($reqHost === '') {
            return false;
        }
        $reqHost = explode(':', $reqHost)[0];
        return hash_equals($reqHost, $host);
    }

    static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    static function csrfField()
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf" value="'.self::h($token).'">';
    }

    static function csrfQuery()
    {
        return '_csrf='.urlencode(self::csrfToken());
    }

    static function verifyCsrf()
    {
        $sent = '';
        if (isset($_POST['_csrf'])) {
            $sent = (string)$_POST['_csrf'];
        } elseif (isset($_GET['_csrf'])) {
            $sent = (string)$_GET['_csrf'];
        }
        $expected = $_SESSION['_csrf'] ?? '';
        if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
            return false;
        }
        return true;
    }

    static function requireCsrf()
    {
        if (!self::verifyCsrf()) {
            http_response_code(403);
            die('Invalid request token');
        }
    }

    static function isLoggedInAdmin()
    {
        return !empty($_SESSION['login']);
    }

    static function allowlistOrder($order)
    {
        $order = strtolower((string)$order);
        return ($order === 'asc' || $order === 'desc') ? $order : 'desc';
    }

    static function isPeerProtocolMethod($q)
    {
        return is_string($q) && in_array($q, self::PEER_PROTOCOL_METHODS, true);
    }

    static function isBlockedDevApiMethod($q)
    {
        if (!is_string($q) || $q === '') {
            return false;
        }
        $q = trim($q);
        if (in_array($q, self::DEV_API_METHODS, true)) {
            return true;
        }
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $q)));
        if ($str !== '') {
            $str[0] = strtolower($str[0]);
        }
        return in_array($str, self::DEV_API_METHODS, true);
    }

    static function extractTarSafely($archive, $destDir, $allowedPrefix, $extract = true)
    {
        if (!is_file($archive) || !is_dir($destDir)) {
            return false;
        }
        $destReal = realpath($destDir);
        if ($destReal === false) {
            return false;
        }
        $listCmd = 'tar -tzf '.escapeshellarg($archive);
        $listing = [];
        $code = 0;
        exec($listCmd.' 2>&1', $listing, $code);
        if ($code !== 0 || count($listing) > 10000) {
            _log("Security: tar list failed for $archive");
            return false;
        }
        $prefix = ltrim(str_replace('\\', '/', $allowedPrefix), '/');
        foreach ($listing as $entry) {
            $entry = str_replace('\\', '/', trim($entry));
            if ($entry === '' || $entry === './') {
                continue;
            }
            if (strpos($entry, './') === 0) {
                $entry = substr($entry, 2);
            }
            if ($entry === '') {
                continue;
            }
            if ($entry[0] === '/' || strpos($entry, ':') !== false) {
                _log("Security: tar member has absolute path: $entry");
                return false;
            }
            $parts = explode('/', $entry);
            foreach ($parts as $part) {
                if ($part === '..') {
                    _log("Security: tar member path traversal: $entry");
                    return false;
                }
            }
            if ($prefix !== '' && strpos($entry, $prefix) !== 0) {
                _log("Security: tar member outside prefix $prefix: $entry");
                return false;
            }
        }
        $details = [];
        exec('tar -tvzf '.escapeshellarg($archive).' 2>&1', $details, $detailCode);
        if ($detailCode !== 0) return false;
        foreach ($details as $detail) {
            if (in_array($detail[0] ?? '', ['l', 'h'], true)) return false;
        }
        if (!$extract) return true;
        $cmd = 'tar -xzf '.escapeshellarg($archive).' -C '.escapeshellarg($destReal)
            .' --no-same-owner --no-same-permissions';
        $output = [];
        $extractCode = 0;
        exec($cmd.' 2>&1', $output, $extractCode);
        return $extractCode === 0;
    }

    static function isAdminTaskClass($task)
    {
        return is_string($task) && in_array($task, self::ADMIN_TASKS, true);
    }

    static function validDappsId($dapps_id)
    {
        return is_string($dapps_id) && preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $dapps_id) === 1;
    }
}
