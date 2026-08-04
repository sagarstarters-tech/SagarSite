<?php
/**
 * Cart Abandonment Recovery Cron Job
 * Processes automated WhatsApp reminders for abandoned carts.
 *
 * Setup in Hostinger - cPanel:
 *
 * Option 1 (HTTP URL Cron - Recommended for Hostinger):
 * Command: curl -s -L 'https://www.sagarstarters.com/cron/cart_abandonment.php?key=sagar_cart_recovery_cron_secret'
 * Schedule: Every 5 minutes
 *
 * Option 2 (PHP CLI):
 * Command: /usr/bin/php /home/u902894566/public_html/cron/cart_abandonment.php
 * Schedule: Every 5 minutes
 *
 * NOTE: Always use https://www.sagarstarters.com (with www) to avoid 301 redirect.
 * The -L flag tells curl to follow redirects automatically.
 */


// Output buffering OFF — so Hostinger cron panel shows live output
if (ob_get_level()) ob_end_clean();

$isCli = (php_sapi_name() === 'cli');

// For HTTP mode: start output immediately so cron panel shows content
if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    // Prevent caching
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Bootstrap
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartService.php';

// ── Secret Key Verification ──────────────────────────────────
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
        echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret key.']);
        exit;
    }
}

// ── Logging Helper ───────────────────────────────────────────
$logFile = __DIR__ . '/../logs/cart_abandonment.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function cronLog($message, $logFile, $isCli) {
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    // Always echo so Hostinger cron panel captures output
    echo $entry;
    if (function_exists('flush')) flush();
}

// Dry run mode (CLI only: php cron/cart_abandonment.php --dry-run)
$dryRun = $isCli && in_array('--dry-run', $argv ?? []);

try {
    cronLog('=== Cart Abandonment Cron Started ===', $logFile, $isCli);

    if (!isset($conn) || !$conn) {
        cronLog('FATAL: Database connection failed.', $logFile, $isCli);
        exit(1);
    }

    $service = new AbandonedCartService($conn);
    $settings = $service->getSettings();

    cronLog('Feature enabled: ' . ($settings['is_enabled'] ?? '0'), $logFile, $isCli);

    if ($dryRun) {
        cronLog('[DRY RUN] No messages will be sent.', $logFile, $isCli);
        cronLog('Reminder delays: R1=' . ($settings['reminder_1_delay'] ?? '?') . 'min, R2=' . ($settings['reminder_2_delay'] ?? '?') . 'min, R3=' . ($settings['reminder_3_delay'] ?? '?') . 'min, R4=' . ($settings['reminder_4_delay'] ?? '?') . 'min', $logFile, $isCli);
        cronLog('Cron completed (dry run).', $logFile, $isCli);
        if (!$isCli) echo json_encode(['success' => true, 'dry_run' => true]);
        exit(0);
    }

    if (!($settings['is_enabled'] ?? '0')) {
        cronLog('Cart abandonment recovery is DISABLED in settings. Skipping.', $logFile, $isCli);
        cronLog('=== Cron Completed (disabled) ===' . PHP_EOL, $logFile, $isCli);
        if (!$isCli) echo json_encode(['success' => true, 'message' => 'Feature is disabled']);
        exit(0);
    }

    $result = $service->processAutoReminders();

    cronLog('Result: ' . $result['message'], $logFile, $isCli);
    if (!empty($result['error_details'])) {
        foreach ($result['error_details'] as $detail) {
            cronLog('  Error detail: ' . $detail, $logFile, $isCli);
        }
    }
    cronLog('=== Cron Completed ===' . PHP_EOL, $logFile, $isCli);

    if (!$isCli) {
        echo json_encode(array_merge(['success' => true], $result));
    }

} catch (Throwable $e) {
    cronLog('FATAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), $logFile, $isCli);
    if (!$isCli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit(1);
}
