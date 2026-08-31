<?php
/**
 * Google Profile Completion Reminder Cron Job
 * Processes automated email reminders for Google users with incomplete profiles.
 *
 * Setup in Hostinger - cPanel / Task Scheduler:
 *
 * Option 1 (HTTP URL Cron):
 * Command: curl -s -L 'https://www.sagarstarters.com/cron/profile_completion_reminder.php?key=sagar_cart_recovery_cron_secret'
 * Schedule: Every 5 or 15 minutes
 *
 * Option 2 (PHP CLI):
 * Command: /usr/bin/php /home/u902894566/public_html/cron/profile_completion_reminder.php
 */

if (ob_get_level()) ob_end_clean();

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/GoogleProfileReminderService.php';

// Secret key verification for HTTP mode
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

echo "[" . date('Y-m-d H:i:s') . "] Starting Google Profile Completion Reminder process...\n";

try {
    $service = new GoogleProfileReminderService($conn);
    $sentCount = $service->processAutoReminders();
    echo "[" . date('Y-m-d H:i:s') . "] Completed successfully. Reminders sent: {$sentCount}\n";
    if (!$isCli) {
        echo json_encode(['success' => true, 'sent_count' => $sentCount]);
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    if (!$isCli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
