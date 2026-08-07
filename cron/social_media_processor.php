<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/config/DbConnection.php';

// Support CLI and HTTP modes
$isCli = (php_sapi_name() === 'cli');
$pdo = DbConnection::getInstance();

if (!$isCli) {
    $secret = $_GET['secret'] ?? '';
    // Mock secret validation logic
    $stmt = $pdo->prepare("SELECT setting_value FROM sm_settings WHERE setting_key = 'cron_secret_key'");
    $stmt->execute();
    $dbSecret = $stmt->fetchColumn();

    if ($secret !== $dbSecret && $dbSecret !== false) {
        http_response_code(403);
        die('Forbidden: Invalid cron secret key.');
    }
} else {
    $options = getopt('', ['dry-run']);
    $dryRun = isset($options['dry-run']);
}

function log_message($msg) {
    $logFile = BASE_PATH . '/logs/social_media_cron.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

$start = microtime(true);
log_message("Cron started.");

// Mock QueueProcessor logic
$processed = 0;
$succeeded = 0;
$failed = 0;
$skipped = 0;

log_message(sprintf("Summary: Processed: %d, Succeeded: %d, Failed: %d, Skipped: %d", $processed, $succeeded, $failed, $skipped));

$end = microtime(true);
$duration = round($end - $start, 2);
log_message("Cron finished in {$duration} seconds.");

if ($isCli) {
    echo "Cron finished in {$duration} seconds.\n";
}
