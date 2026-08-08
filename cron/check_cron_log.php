<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

$secretKey = 'sagar_cart_recovery_cron_secret';
try {
    $res = $conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['setting_value'])) $secretKey = trim($row['setting_value']);
    }
} catch (\Throwable $e) {}

$key = $_GET['key'] ?? '';
if ($key !== $secretKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$logFile = __DIR__ . '/../logs/cart_abandonment.log';
$content = file_exists($logFile) ? file_get_contents($logFile) : 'Log file does not exist';

// Also get last_auto_run from settings
$lastRun = '';
$res = $conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'last_auto_run' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $lastRun = date('Y-m-d H:i:s', intval($row['setting_value']));
}

echo json_encode([
    'server_time' => date('Y-m-d H:i:s'),
    'last_auto_run' => $lastRun,
    'log_tail' => implode("\n", array_slice(explode("\n", $content), -30))
], JSON_PRETTY_PRINT);
