<?php
/**
 * ============================================================
 *  DATABASE CREDENTIALS CONFIGURATION
 *  Location: /config/database.php
 * ============================================================
 *  Reads credentials from .env file if available.
 *  Falls back to hardcoded defaults for local development.
 *
 *  ✅ To deploy to Hostinger: edit ONLY the .env file.
 *  ⛔ Direct web access to this file is blocked by .htaccess.
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    exit('No direct script access allowed');
}

// ── .env Loader ─────────────────────────────────────────────
// Load the .env file from the project root (one level up from /config)
$_env_file = BASE_PATH . '/.env';
if (file_exists($_env_file)) {
    $lines = file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if ($key !== '') {
                // Always set/override these to ensure .env has priority over system defaults
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}
unset($_env_file, $lines, $line, $key, $val);

// ── Helper: read env value or fall back to default ──────────
if (!function_exists('_env')) {
    function _env(string $key, $default = '') {
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : ($_ENV[$key] ?? $default);
    }
}

// ── Application Environment ──────────────────────────────────
if (!defined('APP_ENV')) {
    define('APP_ENV', _env('APP_ENV', 'development'));
}

// ── Database Constants ───────────────────────────────────────
if (!defined('DB_HOST')) define('DB_HOST', _env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', _env('DB_NAME', 'SagarSite_db'));
if (!defined('DB_USER')) define('DB_USER', _env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', _env('DB_PASS', ''));
if (!defined('DB_PORT')) define('DB_PORT', (int) _env('DB_PORT', '3306'));
