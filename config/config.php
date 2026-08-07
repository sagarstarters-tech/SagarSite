<?php
/**
 * ============================================================
 *  MAIN CONFIGURATION BOOTSTRAP
 *  Location: /config/config.php
 * ============================================================
 *  This is the single entry point for all config loading.
 *  Include this file ONCE — it is safe to call multiple times
 *  (all constants are guarded with defined() checks).
 *
 *  Load order:
 *    1. BASE_PATH  / CONFIG_PATH constants
 *    2. database.php  → loads .env, defines DB_* + APP_ENV
 *    3. app.php       → defines URL + SMTP constants
 *    4. Error reporting (controlled by APP_ENV)
 * ============================================================
 */

// ── Set Application Timezone (Indian Standard Time) ─────────
date_default_timezone_set('Asia/Kolkata');

// ── Path Constants ───────────────────────────────────────────
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config/');
}

// ── Load DB Config (also loads .env + defines APP_ENV + DB_*) ──
// Guard on APP_ENV: database.php defines both APP_ENV and DB_HOST.
// If either is missing, we need to load database.php.
if (!defined('APP_ENV') || !defined('DB_HOST')) {
    require CONFIG_PATH . 'database.php';
}

// ── Error Reporting (environment-aware) ─────────────────────
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php_errors.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php_errors.log');
    error_reporting(E_ALL);
}

// ── Global HTTP Security Headers ────────────────────────────
if (!headers_sent()) {
    header("X-XSS-Protection: 1; mode=block");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    // Ensure strict transport security on production
    if (defined('APP_ENV') && APP_ENV === 'production') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

// ── Load App Config (URLs, SMTP) ────────────────────────────
// _env() is available now (defined in database.php)
if (!defined('STORE_BASE_URL')) {
    $app_config = require CONFIG_PATH . 'app.php';

    // ── URL Constants (change SITE_URL in .env for Hostinger) ──
    if (!defined('SITE_URL')) define('SITE_URL', $app_config['site_url']);
    if (!defined('ASSETS_URL')) define('ASSETS_URL', $app_config['assets_url']);
    if (!defined('STORE_BASE_URL')) define('STORE_BASE_URL', $app_config['store_base_url']);
    if (!defined('ADMIN_BASE_URL')) define('ADMIN_BASE_URL', $app_config['admin_base_url']);

    // ── SMTP Constants ──────────────────────────────────────────
    if (!defined('SMTP_HOST')) define('SMTP_HOST', $app_config['smtp_host']);
    if (!defined('SMTP_USER')) define('SMTP_USER', $app_config['smtp_user']);
    if (!defined('SMTP_PASS')) define('SMTP_PASS', $app_config['smtp_pass']);
    if (!defined('SMTP_PORT')) define('SMTP_PORT', $app_config['smtp_port']);
    if (!defined('SMTP_SECURE')) define('SMTP_SECURE', $app_config['smtp_secure']);
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $app_config['mail_from_name']);

    unset($app_config);
}
