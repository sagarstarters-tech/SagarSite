<?php
/**
 * Cart Abandonment Recovery Cron Job
 * Processes automated WhatsApp reminders for abandoned carts.
 * 
 * Setup: Run via cron every 10 minutes
 * CLI:  crontab -e -> add: 0,10,20,30,40,50 * * * * php /path/to/cron/cart_abandonment.php
 * HTTP: crontab -e -> add: 0,10,20,30,40,50 * * * * curl -s https://yourdomain.com/cron/cart_abandonment.php?key=YOUR_SECRET_KEY
 */

// Security: Only allow CLI or valid secret key
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    $secretKey = 'CHANGE_THIS_SECRET_KEY_' . md5(__DIR__);
    if (($_GET['key'] ?? '') !== $secretKey) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

// Dry run mode
$dryRun = $isCli && in_array('--dry-run', $argv ?? []);

// Bootstrap
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartService.php';

$logFile = __DIR__ . '/../logs/cart_abandonment.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function cronLog($message, $logFile) {
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
    if (php_sapi_name() === 'cli') echo $entry;
}

try {
    cronLog('=== Cart Abandonment Cron Started ===', $logFile);
    
    if ($dryRun) {
        cronLog('[DRY RUN] No messages will be sent', $logFile);
        // Just check due reminders count
        $service = new AbandonedCartService($conn);
        $settings = $service->getSettings();
        cronLog('Feature enabled: ' . ($settings['is_enabled'] ?? 'unknown'), $logFile);
        cronLog('Cron completed (dry run)', $logFile);
        exit(0);
    }
    
    $service = new AbandonedCartService($conn);
    $result = $service->processAutoReminders();
    
    cronLog($result['message'], $logFile);
    cronLog('=== Cron Completed ===' . PHP_EOL, $logFile);
    
    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
} catch (Throwable $e) {
    cronLog('FATAL ERROR: ' . $e->getMessage(), $logFile);
    if (!$isCli) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit(1);
}
