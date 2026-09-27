<?php
/**
 * Digital Asset Tag Portal - application configuration
 *
 * Loads environment values, resolves the public base URL and provides the
 * small helper set (escaping, translation, session) used across the portal.
 *
 * Requires the LINKTEC_SECURE constant, matching the convention used by the
 * existing LinkTec site in /Users/LinkTec/hydraulic.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

define('DAT_APP_ROOT', dirname(__DIR__));
define('DAT_VERSION', '1.0.0');

/**
 * Read a configuration value from .env or the process environment.
 */
if (!function_exists('dat_env')) {
    function dat_env($key, $default = null)
    {
        static $fileVars = null;

        if ($fileVars === null) {
            $fileVars = [];
            $file = DAT_APP_ROOT . '/.env';
            if (is_file($file) && is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    $len = strlen($value);
                    if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                        $value = substr($value, 1, -1);
                    }
                    $fileVars[$name] = $value;
                }
            }
        }

        if (array_key_exists($key, $fileVars)) {
            return $fileVars[$key];
        }

        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        return $default;
    }
}

/** Portal branding. */
define('PORTAL_NAME', dat_env('PORTAL_NAME', 'Digital Asset Tag Portal'));
define('PORTAL_DEFAULT_LANG', dat_env('PORTAL_DEFAULT_LANG', 'en'));
define('PORTAL_MESSAGE_RATE_LIMIT', max(1, (int) dat_env('PORTAL_MESSAGE_RATE_LIMIT', 5)));
define('PORTAL_MESSAGE_RETENTION_DAYS', max(1, (int) dat_env('PORTAL_MESSAGE_RETENTION_DAYS', 90)));
define('PORTAL_MAX_UPLOAD_BYTES', max(1024, (int) dat_env('PORTAL_MAX_UPLOAD_BYTES', 10485760)));

/** Public URL of this portal, without a trailing slash. */
if (!function_exists('dat_request_is_https')) {
    function dat_request_is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}

/** Path this application is mounted under, e.g. "" or "/nfc". */
if (!function_exists('dat_base_path')) {
    function dat_base_path()
    {
        static $path = null;
        if ($path !== null) {
            return $path;
        }

        $path = '';
        $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = realpath(DAT_APP_ROOT);
        if ($docRoot !== false && $appRoot !== false && strpos($appRoot, $docRoot) === 0) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($appRoot, strlen($docRoot)));
            $path = rtrim($relative, '/');
        }

        return $path;
    }
}

if (!function_exists('dat_base_url')) {
    function dat_base_url()
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $configured = trim((string) dat_env('PORTAL_BASE_URL', ''));
        if ($configured !== '') {
            $base = rtrim($configured, '/');
            return $base;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = (dat_request_is_https() ? 'https://' : 'http://') . $host . dat_base_path();

        return $base;
    }
}

if (!function_exists('dat_url')) {
    function dat_url($path = '')
    {
        $path = ltrim((string) $path, '/');
        return $path === '' ? dat_base_url() . '/' : dat_base_url() . '/' . $path;
    }
}

/** Stable public tag URL, the single address written to QR and NFC. */
if (!function_exists('dat_tag_url')) {
    function dat_tag_url($publicId)
    {
        return dat_url('t/' . rawurlencode((string) $publicId));
    }
}

/** Session bootstrap with hardened cookie defaults. */
if (!function_exists('dat_session_start')) {
    function dat_session_start()
    {
        // Sessions are meaningless on the command line (tests, scripts).
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('DAT_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => dat_request_is_https(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Currently selected UI language. */
if (!function_exists('dat_lang')) {
    function dat_lang()
    {
        static $lang = null;
        if ($lang !== null) {
            return $lang;
        }

        $supported = ['en', 'de'];
        $requested = $_GET['lang'] ?? null;

        if ($requested !== null && in_array($requested, $supported, true)) {
            dat_session_start();
            $_SESSION['dat_lang'] = $requested;
        } elseif ($requested !== null && function_exists('setcookie')) {
            setcookie('dat_lang', '', time() - 3600, '/');
        }

        $candidate = $_SESSION['dat_lang']
            ?? $_COOKIE['dat_lang']
            ?? PORTAL_DEFAULT_LANG;

        $lang = in_array($candidate, $supported, true) ? $candidate : 'en';

        if (function_exists('setcookie') && !headers_sent()) {
            setcookie('dat_lang', $lang, time() + 31536000, '/', '', dat_request_is_https(), true);
        }

        return $lang;
    }
}

/**
 * Translate a key. Falls back to the English string, then to the key itself.
 */
if (!function_exists('t')) {
    function t($key, $fallback = null)
    {
        static $strings = [];
        static $loaded = [];

        $lang = dat_lang();
        if (!isset($loaded[$lang])) {
            $file = DAT_APP_ROOT . '/includes/lang/' . $lang . '.php';
            $strings[$lang] = is_file($file) ? (array) require $file : [];
            $loaded[$lang] = true;
        }

        if (isset($strings[$lang][$key])) {
            return $strings[$lang][$key];
        }

        if ($lang !== 'en') {
            if (!isset($loaded['en'])) {
                $file = DAT_APP_ROOT . '/includes/lang/en.php';
                $strings['en'] = is_file($file) ? (array) require $file : [];
                $loaded['en'] = true;
            }
            if (isset($strings['en'][$key])) {
                return $strings['en'][$key];
            }
        }

        return $fallback ?? $key;
    }
}

/** HTML escape helper, identical in spirit to the existing site's e(). */
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
