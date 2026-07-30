<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$set_q = $conn->query("SELECT api_token, phone_number_id, waba_id FROM whatsapp_settings WHERE id = 1");
$settings = $set_q->fetch_assoc();

if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
    echo json_encode(['error' => 'Please save your API Token and Phone Number ID first.']);
    exit;
}

$token = trim($settings['api_token']);
$phone_id = trim($settings['phone_number_id']);
$waba_id = trim($_GET['waba_id'] ?? '');
if (empty($waba_id)) {
    $waba_id = trim($settings['waba_id'] ?? '');
}

// 1. If WABA ID is not provided, try to fetch it automatically from the Phone Number ID
if (empty($waba_id)) {
    // Sometimes v19.0+ requires specific permissions for this field. 
    // We try to fetch the WABA ID which is essential for template listing.
    $ch = curl_init("https://graph.facebook.com/v19.0/{$phone_id}?fields=whatsapp_business_account_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $data = json_decode($res, true);

    if (!empty($data['whatsapp_business_account_id'])) {
        $waba_id = $data['whatsapp_business_account_id'];
    } else {
        $err = $data['error']['message'] ?? 'Could not find linked Business Account ID.';
        echo json_encode(['error' => $err . ' Please enter your WhatsApp Business Account ID (WABA ID) manually in the settings.']);
        exit;
    }
}

// 2. Fetch Templates
$ch = curl_init("https://graph.facebook.com/v19.0/{$waba_id}/message_templates?limit=100");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$templates_data = json_decode($res, true);

if (isset($templates_data['error'])) {
    echo json_encode(['error' => $templates_data['error']['message']]);
    exit;
}

$templates = [];
if (!empty($templates_data['data'])) {
    foreach ($templates_data['data'] as $tpl) {
        if ($tpl['status'] === 'APPROVED') {
            $templates[] = [
                'name' => $tpl['name'],
                'language' => $tpl['language'],
                'category' => $tpl['category'],
                'components' => $tpl['components']
            ];
        }
    }
}

echo json_encode(['success' => true, 'templates' => $templates]);
?>
