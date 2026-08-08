<?php
/**
 * Diagnostic tool to list approved Meta Cloud API templates and test live send
 * URL: /cron/check_templates_debug.php?key=sagar_cart_recovery_cron_secret&phone=918573934013
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

$testPhoneInput = $_GET['phone'] ?? $_GET['test_phone'] ?? '';
$testResult = null;

if (!empty($testPhoneInput)) {
    $phone = preg_replace('/[^0-9]/', '', $testPhoneInput);
    if (strpos($phone, '0') === 0) $phone = ltrim($phone, '0');
    if (strlen($phone) == 10) $phone = '91' . $phone;

    $set_q = $conn->query("SELECT api_token, phone_number_id FROM whatsapp_settings WHERE id = 1");
    $waSettings = $set_q ? $set_q->fetch_assoc() : null;

    if ($waSettings && !empty($waSettings['api_token']) && !empty($waSettings['phone_number_id'])) {
        $token = trim($waSettings['api_token']);
        $phoneId = trim($waSettings['phone_number_id']);
        $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $phone,
            "type"              => "template",
            "template"          => [
                "name"     => "reminder_1__gentle_nudge",
                "language" => ["code" => "en"],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                            ["type" => "text", "text" => "Test Customer"],
                            ["type" => "text", "text" => "Sample Motor Starter x1"],
                            ["type" => "text", "text" => "1,500.00"],
                            ["type" => "text", "text" => "https://www.sagarstarters.com/recover_cart.php?token=test"]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $resStr = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $testResult = [
            'sent_to' => $phone,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'payload' => $payload,
            'response' => json_decode($resStr, true)
        ];
    } else {
        $testResult = ['error' => 'Missing Meta API token or Phone Number ID'];
    }
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
    'test_result' => $testResult,
    'active_carts' => $activeCarts,
    'recent_wa_logs' => $recentLogs,
    'cart_abandonment_whatsapp_log_tail' => $logContent
], JSON_PRETTY_PRINT);
