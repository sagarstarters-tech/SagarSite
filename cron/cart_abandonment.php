<?php
/**
 * Cart Abandonment Recovery Cron Job
 * Processes automated WhatsApp reminders for abandoned carts.
 * 
 * Setup in Hostinger / cPanel:
 * 
 * Option 1 (PHP CLI - Recommended):
 * Command: /usr/bin/php /home/u902894566/public_html/cron/cart_abandonment.php
 * Schedule: Every 10 minutes (* /10 * * * *)
 * 
 * Option 2 (HTTP URL Cron):
 * URL: https://sagarstarters.com/cron/cart_abandonment.php?key=sagar_cart_recovery_cron_secret
 * Schedule: Every 10 minutes (* /10 * * * *)
 */

// Bootstrap
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartService.php';

$isCli = (php_sapi_name() === 'cli');

// Fetch secret key from settings or default
$secretKey = 'sagar_cart_recovery_cron_secret';
try {
    if (isset($conn) && $conn) {
        $res = $conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            if (!empty($row['setting_value'])) {
                $secretKey = trim($row['setting_value']);
            }
        }
    }
} catch (\Throwable $e) {}

if (!$isCli) {
    $requestKey = $_GET['key'] ?? '';
    if ($requestKey !== $secretKey) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret key.']);
        exit;
    }
}

// Dry run mode
$dryRun = $isCli && in_array('--dry-run', $argv ?? []);

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
    
    $service = new AbandonedCartService($conn);

    if ($dryRun) {
        cronLog('[DRY RUN] No messages will be sent', $logFile);
        $settings = $service->getSettings();
        cronLog('Feature enabled: ' . ($settings['is_enabled'] ?? 'unknown'), $logFile);
        cronLog('Cron completed (dry run)', $logFile);
        if (!$isCli) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'dry_run' => true]);
        }
        exit(0);
    }
    
    $result = $service->processAutoReminders();
    
    cronLog($result['message'], $logFile);
    cronLog('=== Cron Completed ===' . PHP_EOL, $logFile);
    
    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true], $result));
    }
    
} catch (Throwable $e) {
    cronLog('FATAL ERROR: ' . $e->getMessage(), $logFile);
    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit(1);
}

