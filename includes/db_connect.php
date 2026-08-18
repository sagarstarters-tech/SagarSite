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

// ── Global Helper: Get Store WhatsApp Number ────────────────
if (!function_exists('get_store_whatsapp_number')) {
    function get_store_whatsapp_number($raw = false) {
        global $global_settings, $conn;
        $number = '';
        if (!empty($global_settings['whatsapp_number'])) {
            $number = $global_settings['whatsapp_number'];
        } elseif (!empty($global_settings['contact_phone'])) {
            $number = $global_settings['contact_phone'];
        } else {
            if (!empty($conn) && $conn instanceof mysqli) {
                try {
                    $wq = $conn->query("SELECT chat_widget_number, sender_number FROM whatsapp_settings WHERE id = 1 LIMIT 1");
                    if ($wq && $wrow = $wq->fetch_assoc()) {
                        $number = !empty($wrow['chat_widget_number']) ? $wrow['chat_widget_number'] : ($wrow['sender_number'] ?? '');
                    }
                } catch (\Throwable $e) {}
            }
        }
        if (empty($number)) {
            $number = '918573934013';
        }
        if ($raw) {
            return $number;
        }
        $clean = preg_replace('/[^0-9]/', '', $number);
        if (strpos($clean, '0') === 0) {
            $clean = ltrim($clean, '0');
        }
        if (strlen($clean) === 10) {
            $clean = '91' . $clean;
        }
        return !empty($clean) ? $clean : '918573934013';
    }
}

if (!defined('DISABLE_AUTO_REMINDER_TRIGGER')) {
    if (php_sapi_name() === 'cli' || !empty($_GET['run_cron'])) {
        try {
            require_once __DIR__ . '/AbandonedCartService.php';
            (new AbandonedCartService($conn))->processAutoReminders();
        } catch (\Throwable $e) {}
    } else {
        // Run safely in background shutdown after web response is delivered to user
        register_shutdown_function(function() use ($conn) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request(); // Delivers web page to user instantly with 0 delay
            }
            try {
                $lastRunTs = 0;
                $ac_res = $conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'last_auto_run' LIMIT 1");
                if ($ac_res && $ac_row = $ac_res->fetch_assoc()) {
                    $lastRunTs = intval($ac_row['setting_value']);
                }
                if ((time() - $lastRunTs) >= 300) { // 5-minute throttle for web requests
                    $conn->query("INSERT INTO abandoned_cart_settings (setting_key, setting_value) VALUES ('last_auto_run', '" . time() . "') ON DUPLICATE KEY UPDATE setting_value = '" . time() . "'");
                    require_once __DIR__ . '/AbandonedCartService.php';
                    (new AbandonedCartService($conn))->processAutoReminders();
                }
            } catch (\Throwable $e) {}
        });
    }
}


