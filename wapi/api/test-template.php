<?php
/**
 * Test single template message - DELETE AFTER TESTING
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);

if (!$waAccount) {
    die(json_encode(['error' => 'No WA account']));
}

// Get the "offer" template details
$tpl = $db->fetch("SELECT * FROM templates WHERE user_id = ? AND name = 'offer' LIMIT 1", [$userId]);

// Test 1: Send template WITHOUT header components (just name + language)
$payload_no_header = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => '917052864041',  // Vikky - known working number
    'type' => 'template',
    'template' => [
        'name' => 'offer',
        'language' => ['code' => $tpl['language'] ?? 'en']
    ]
];

// Test 2: Send template WITH header components
$payload_with_header = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => '917052864041',
    'type' => 'template',
    'template' => [
        'name' => 'offer',
        'language' => ['code' => $tpl['language'] ?? 'en'],
        'components' => [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => ['link' => 'https://www.sagarstarters.com/assets/images/logo.png']
                    ]
                ]
            ]
        ]
    ]
];

$url = "https://graph.facebook.com/v18.0/{$waAccount['phone_number_id']}/messages";
$accessToken = $waAccount['access_token'];

// Only run Test 1 (without header) to check if business eligibility is the real issue
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken", "Content-Type: application/json"],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload_no_header),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);
$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError1 = curl_error($ch);
curl_close($ch);

echo json_encode([
    'template_details' => $tpl,
    'wa_account' => [
        'phone_number_id' => $waAccount['phone_number_id'],
        'waba_id' => $waAccount['waba_id'],
        'api_version' => 'v18.0'
    ],
    'test_without_header' => [
        'payload_sent' => $payload_no_header,
        'http_code' => $httpCode1,
        'response' => json_decode($response1, true),
        'curl_error' => $curlError1
    ],
    'test_with_header_payload' => $payload_with_header,
    'note' => 'Only Test 1 (without header) was executed. Test 2 payload is shown for reference.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
