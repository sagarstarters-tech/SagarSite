<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartService.php';

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

$service = new AbandonedCartService($conn);

// Get current state of Cart #46
$cart46_before = null;
$res = $conn->query("SELECT * FROM abandoned_carts WHERE id = 46 LIMIT 1");
if ($res) $cart46_before = $res->fetch_assoc();

// Run processAutoReminders now
$autoResult = $service->processAutoReminders();

// Get updated state of Cart #46
$cart46_after = null;
$res = $conn->query("SELECT * FROM abandoned_carts WHERE id = 46 LIMIT 1");
if ($res) $cart46_after = $res->fetch_assoc();

// Get WA logs for Cart 46
$waLogs = [];
$res = $conn->query("SELECT * FROM abandoned_cart_wa_logs WHERE cart_id = 46 ORDER BY id DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $waLogs[] = $row;
    }
}

echo json_encode([
    'server_time' => date('Y-m-d H:i:s'),
    'cart46_before' => $cart46_before,
    'auto_result' => $autoResult,
    'cart46_after' => $cart46_after,
    'wa_logs' => $waLogs
], JSON_PRETTY_PRINT);
