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

// 1. Get Cart #46 details
$cart46 = null;
$res = $conn->query("SELECT ac.*, u.name as uname, u.phone as uphon FROM abandoned_carts ac LEFT JOIN users u ON ac.user_id = u.id WHERE ac.id = 46 OR u.phone LIKE '%8573934013%' ORDER BY ac.id DESC LIMIT 5");
$carts = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $carts[] = $row;
    }
}

// 2. Get DB logs for 8573934013 or cart 46
$waLogs = [];
$res = $conn->query("SELECT * FROM abandoned_cart_wa_logs WHERE customer_number LIKE '%8573934013%' OR cart_id = 46 ORDER BY id DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $waLogs[] = $row;
    }
}

// 3. Read last 4000 bytes of cart_abandonment_whatsapp.log
$logTail = '';
$logPath = __DIR__ . '/../logs/cart_abandonment_whatsapp.log';
if (file_exists($logPath)) {
    $size = filesize($logPath);
    $fp = fopen($logPath, 'r');
    if ($fp) {
        if ($size > 4000) fseek($fp, $size - 4000);
        $logTail = fread($fp, 4000);
        fclose($fp);
    }
}

// 4. Also fetch global WhatsApp sender number from whatsapp_settings
$waSender = null;
$res = $conn->query("SELECT phone_number_id, waba_id, meta_template_name FROM whatsapp_settings WHERE id = 1");
if ($res) $waSender = $res->fetch_assoc();

echo json_encode([
    'server_time' => date('Y-m-d H:i:s'),
    'whatsapp_settings' => $waSender,
    'carts' => $carts,
    'wa_logs' => $waLogs,
    'log_tail' => $logTail
], JSON_PRETTY_PRINT);
