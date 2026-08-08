<?php
/**
 * Test script to send a WhatsApp template directly to a test phone number
 * URL: /cron/test_send_whatsapp.php?key=sagar_cart_recovery_cron_secret&phone=918573934013
 */

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

$phone = trim($_GET['phone'] ?? '');
if (empty($phone)) {
    echo json_encode(['error' => 'Please provide phone parameter (e.g. &phone=918573934013)']);
    exit;
}

$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($cleanPhone, '0') === 0) $cleanPhone = ltrim($cleanPhone, '0');
if (strlen($cleanPhone) == 10) $cleanPhone = '91' . $cleanPhone;

// Fetch WhatsApp settings
$set_q = $conn->query("SELECT api_token, phone_number_id, waba_id FROM whatsapp_settings WHERE id = 1");
$waSettings = $set_q->fetch_assoc();

if (empty($waSettings['api_token']) || empty($waSettings['phone_number_id'])) {
    echo json_encode(['error' => 'Missing Meta API token or Phone Number ID']);
    exit;
}

$token = trim($waSettings['api_token']);
$phoneId = trim($waSettings['phone_number_id']);
$url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

// Test payload using reminder_1__gentle_nudge
$payload = [
    "messaging_product" => "whatsapp",
    "recipient_type"    => "individual",
    "to"                => $cleanPhone,
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

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo json_encode([
    'success' => ($httpCode == 200),
    'to_phone' => $cleanPhone,
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'payload' => $payload,
    'response' => json_decode($result, true)
], JSON_PRETTY_PRINT);
