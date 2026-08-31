<?php
declare(strict_types=1);

/**
 * ============================================================
 *  COURIER QUEUE BACKGROUND PROCESSOR CRON
 *  Location: /cron/courier_queue_processor.php
 * ============================================================
 *  Processes pending courier order dispatches, retries failed
 *  attempts with exponential backoff, and syncs logistics status.
 *
 *  Usage:
 *    CLI:  php /path/to/cron/courier_queue_processor.php
 *    HTTP: https://www.sagarstarters.com/cron/courier_queue_processor.php?secret=YOUR_CRON_KEY
 * ============================================================
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/courier_module/Services/CourierQueueService.php';

$isCli = (php_sapi_name() === 'cli');
$pdo = DbConnection::getInstance();

// Security check for HTTP execution
if (!$isCli) {
    $secret = $_GET['secret'] ?? $_GET['key'] ?? '';
    $cronSecret = defined('CRON_SECRET_KEY') ? CRON_SECRET_KEY : '';
    
    if (empty($cronSecret)) {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
        $cronSecret = (string)($stmt->fetchColumn() ?: '');
    }

    if (!empty($cronSecret) && $secret !== $cronSecret) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid cron secret key.']));
    }
}

function courier_cron_log(string $msg): void {
    $logFile = BASE_PATH . '/logs/courier_cron.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

$startTime = microtime(true);
courier_cron_log("Courier Cron started.");

$queueService = new \CourierModule\Services\CourierQueueService($pdo);
$batchResult = $queueService->processBatch(15);

$duration = round(microtime(true) - $startTime, 2);
courier_cron_log(sprintf(
    "Courier Cron finished in %ss. Picked: %d, Succeeded: %d, Failed: %d, Permanent Failures: %d",
    $duration,
    $batchResult['total_picked'],
    $batchResult['succeeded'],
    $batchResult['failed'],
    $batchResult['permanent']
));

if ($isCli) {
    echo "Courier Cron finished in {$duration}s. Picked: {$batchResult['total_picked']}, Succeeded: {$batchResult['succeeded']}, Failed: {$batchResult['failed']}.\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status'   => 'success',
        'duration' => $duration,
        'summary'  => $batchResult
    ]);
}
