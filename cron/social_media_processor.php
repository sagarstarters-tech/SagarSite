<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/social-media/services/QueueProcessor.php';

// Support CLI and HTTP modes
$isCli = (php_sapi_name() === 'cli');
$pdo = DbConnection::getInstance();

if (!$isCli) {
    $secret = $_GET['secret'] ?? '';
    $stmt = $pdo->prepare("SELECT setting_value FROM sm_settings WHERE setting_key = 'cron_secret_key'");
    $stmt->execute();
    $dbSecret = $stmt->fetchColumn();

    if (!empty($dbSecret) && $secret !== $dbSecret) {
        http_response_code(403);
        die('Forbidden: Invalid cron secret key.');
    }
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

$processor = new \Admin\SocialMedia\Services\QueueProcessor();
$results = $processor->processBatch(20);

$processed = count($results);
$succeeded = 0;
$failed = 0;

foreach ($results as $res) {
    if ($res['success']) {
        $succeeded++;
    } else {
        $failed++;
    }
}

log_message(sprintf("Summary: Processed: %d, Succeeded: %d, Failed: %d", $processed, $succeeded, $failed));

$end = microtime(true);
$duration = round($end - $start, 2);
log_message("Cron finished in {$duration} seconds.");

if ($isCli) {
    echo "Cron finished in {$duration} seconds. Processed: $processed, Succeeded: $succeeded, Failed: $failed.\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'duration' => $duration,
        'processed' => $processed,
        'succeeded' => $succeeded,
        'failed' => $failed
    ]);
}
