<?php
/**
 * ============================================================
 *  DB CONNECT — MySQLi Connection Bootstrap
 *  Location: /includes/db_connect.php
 * ============================================================
 *  Establishes the global $conn (MySQLi) connection used by
 *  legacy and frontend PHP pages throughout the project.
 *
 *  For new code, prefer the PDO singleton:
 *      require_once BASE_PATH . '/config/Database.php';
 *      $pdo = Database::getInstance();
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once __DIR__ . '/country_codes.php';
require_once __DIR__ . '/session_setup.php';
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config/');
}

// Load all constants (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, APP_ENV, etc.)
if (!defined('DB_HOST')) {
    require CONFIG_PATH . 'config.php';
}

// ── MySQLi Connection ────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    if (APP_ENV === 'production') {
        error_log('[DB] MySQLi connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die('A database error occurred. Please try again later.');
    }
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// ── Fetch Global Settings ────────────────────────────────────
$global_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM settings";
if ($result = $conn->query($settings_query)) {
    while ($row = $result->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
    $result->free();
}

// ── Apply Timezone ───────────────────────────────────────────
$timezone = !empty($global_settings['timezone']) ? $global_settings['timezone'] : 'Asia/Kolkata';
if (!in_array($timezone, timezone_identifiers_list())) {
    $timezone = 'Asia/Kolkata';
}
date_default_timezone_set($timezone);

// Sync MySQL session timezone with PHP timezone
try {
    $now = new DateTime('now', new DateTimeZone($timezone));
    $offset = $now->format('P'); // e.g. +05:30
    $conn->query("SET time_zone = '{$offset}'");
} catch (\Throwable $e) {}

// ── Global Currency Symbol ───────────────────────────────────
$global_currency = !empty($global_settings['currency_symbol']) ? $global_settings['currency_symbol'] : '₹';
