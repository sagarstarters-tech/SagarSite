<?php
/**
 * Diagnostic tool to list approved Meta Cloud API templates
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

$set_q = $conn->query("SELECT api_token, phone_number_id, waba_id FROM whatsapp_settings WHERE id = 1");
$settings = $set_q->fetch_assoc();

if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
    echo json_encode(['error' => 'API token or Phone Number ID is missing in whatsapp_settings']);
    exit;
}

$token = trim($settings['api_token']);
$phone_id = trim($settings['phone_number_id']);
$waba_id = trim($settings['waba_id'] ?? '');

if (empty($waba_id)) {
    $ch = curl_init("https://graph.facebook.com/v19.0/{$phone_id}?fields=whatsapp_business_account_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    $waba_id = $data['whatsapp_business_account_id'] ?? '';
    curl_close($ch);
}

if (empty($waba_id)) {
    echo json_encode(['error' => 'Could not detect WABA ID. Please enter WABA ID in WhatsApp Notifs Settings.']);
    exit;
}

$ch = curl_init("https://graph.facebook.com/v19.0/{$waba_id}/message_templates?limit=100");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$templates_data = json_decode($res, true);
curl_close($ch);

if (isset($templates_data['error'])) {
    echo json_encode(['error' => $templates_data['error']['message'] ?? 'Meta API error']);
    exit;
}

$approved = [];
if (!empty($templates_data['data'])) {
    foreach ($templates_data['data'] as $tpl) {
        if (($tpl['status'] ?? '') === 'APPROVED') {
            $approved[] = [
                'name' => $tpl['name'],
                'language' => $tpl['language'],
                'category' => $tpl['category'],
                'components' => $tpl['components'] ?? []
            ];
        }
    }
}

// Fetch current abandoned cart settings
$ac_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM abandoned_cart_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ac_settings[$row['setting_key']] = $row['setting_value'];
    }
}

echo json_encode([
    'success' => true,
    'waba_id' => $waba_id,
    'abandoned_cart_settings' => [
        'is_enabled' => $ac_settings['is_enabled'] ?? '',
        'reminder_1_delay' => $ac_settings['reminder_1_delay'] ?? '',
        'meta_template_1' => $ac_settings['meta_template_1'] ?? '',
        'meta_template_2' => $ac_settings['meta_template_2'] ?? '',
        'meta_template_3' => $ac_settings['meta_template_3'] ?? '',
        'meta_template_4' => $ac_settings['meta_template_4'] ?? '',
        'meta_template_lang' => $ac_settings['meta_template_lang'] ?? '',
    ],
    'approved_templates' => $approved
], JSON_PRETTY_PRINT);
