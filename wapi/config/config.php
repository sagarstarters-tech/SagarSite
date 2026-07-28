<?php
/**
 * WAPI SaaS Platform - Main Configuration
 * All database, API, and application settings
 */

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $env = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        if (!array_key_exists(trim($name), $_SERVER) && !array_key_exists(trim($name), $_ENV)) {
            putenv(trim($line));
            $_ENV[trim($name)] = trim($value);
            $_SERVER[trim($name)] = trim($value);
        }
    }
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Application Constants
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$fallbackUrl = $protocol . "://" . $host;
define('APP_URL', $_ENV['APP_URL'] ?? $fallbackUrl);
define('APP_NAME', $_ENV['APP_NAME'] ?? 'WAPI');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? '1.0.0');
define('APP_ROOT', dirname(__DIR__));
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'wapi_saas');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Session Configuration
define('SESSION_NAME', $_ENV['SESSION_NAME'] ?? 'WAPI_SESSION');
define('SESSION_LIFETIME', $_ENV['SESSION_LIFETIME'] ?? 7200);
define('SESSION_SECURE', ($_ENV['SESSION_SECURE'] ?? 'false') === 'true');
define('SESSION_HTTPONLY', ($_ENV['SESSION_HTTPONLY'] ?? 'true') === 'true');

// Security
define('CSRF_TOKEN_NAME', '_csrf_token');
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'default-secret');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'default-encryption-key');
define('WEBHOOK_VERIFY_TOKEN', $_ENV['WEBHOOK_VERIFY_TOKEN'] ?? 'default-verify-token');

// File Upload
define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('MAX_UPLOAD_SIZE', (int)($_ENV['MAX_UPLOAD_SIZE'] ?? (100 * 1024 * 1024)));
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'mp4', 'mp3']);

// Pagination
define('ITEMS_PER_PAGE', $_ENV['ITEMS_PER_PAGE'] ?? 20);

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Asia/Kolkata');

// Mail Configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 465);
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'ssl');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'WAPI');

// Include autoloader
require_once __DIR__ . '/autoload.php';
