<?php
/**
 * Diagnostic tool to list approved Meta Cloud API templates and recent logs
 * URL: /cron/check_templates_debug.php?key=sagar_cart_recovery_cron_secret
 */

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

// Fetch recent abandoned cart logs
$recentLogs = [];
$res = $conn->query("SELECT * FROM abandoned_cart_wa_logs ORDER BY id DESC LIMIT 20");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentLogs[] = $row;
    }
}

// Fetch active carts
$activeCarts = [];
$res = $conn->query("SELECT ac.*, u.name as uname, u.phone as uphon FROM abandoned_carts ac LEFT JOIN users u ON ac.user_id = u.id ORDER BY ac.id DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $activeCarts[] = $row;
    }
}

// Read last 3000 bytes of cart_abandonment_whatsapp.log
$logContent = '';
$logPath = __DIR__ . '/../logs/cart_abandonment_whatsapp.log';
if (file_exists($logPath)) {
    $size = filesize($logPath);
    $fp = fopen($logPath, 'r');
    if ($fp) {
        if ($size > 3000) fseek($fp, $size - 3000);
        $logContent = fread($fp, 3000);
        fclose($fp);
    }
}

echo json_encode([
    'success' => true,
    'active_carts' => $activeCarts,
    'recent_wa_logs' => $recentLogs,
    'cart_abandonment_whatsapp_log_tail' => $logContent
], JSON_PRETTY_PRINT);
