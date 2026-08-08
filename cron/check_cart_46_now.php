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

$ac_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM abandoned_cart_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ac_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$cart46 = null;
$res = $conn->query("SELECT * FROM abandoned_carts WHERE id = 46 LIMIT 1");
if ($res) $cart46 = $res->fetch_assoc();

echo json_encode([
    'server_time' => date('Y-m-d H:i:s'),
    'cart46' => $cart46,
    'abandoned_cart_settings' => $ac_settings
], JSON_PRETTY_PRINT);
