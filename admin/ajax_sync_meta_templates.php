<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Permission denied. Please log in as admin.']);
    exit;
}

// Fetch stored settings
$set_q = $conn->query("SELECT api_token, phone_number_id, waba_id FROM whatsapp_settings WHERE id = 1");
$settings = $set_q ? $set_q->fetch_assoc() : [];

// Allow query / post parameters to override stored values so user can test without saving first
$token    = trim($_GET['token'] ?? ($_POST['token'] ?? ($settings['api_token'] ?? '')));
$phone_id = trim($_GET['phone_id'] ?? ($_POST['phone_id'] ?? ($settings['phone_number_id'] ?? '')));
$waba_id  = trim($_GET['waba_id'] ?? ($_POST['waba_id'] ?? ($settings['waba_id'] ?? '')));

if (empty($token) || (empty($phone_id) && empty($waba_id))) {
    echo json_encode(['error' => 'Please enter your Meta API Token and either Phone Number ID or WABA ID.']);
    exit;
}

// Helper cURL function with SSL bypass and timeout protection
function meta_curl_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER      => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_TIMEOUT         => 25,
        CURLOPT_FOLLOWLOCATION  => true,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || !empty($error)) {
        return ['success' => false, 'error' => 'cURL Network Error: ' . $error, 'http_code' => $httpCode];
    }
    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['success' => false, 'error' => 'Invalid JSON from Meta: ' . substr($response, 0, 150), 'http_code' => $httpCode];
    }
    return ['success' => true, 'data' => $json, 'http_code' => $httpCode];
}

// Step 1: If WABA ID is missing, try to auto-discover it via Phone Number ID
if (empty($waba_id) && !empty($phone_id)) {
    // Try latest Graph API endpoints
    $api_versions = ['v21.0', 'v20.0', 'v19.0'];
    $discovered_waba = null;

    foreach ($api_versions as $ver) {
        $check = meta_curl_get("https://graph.facebook.com/{$ver}/{$phone_id}?fields=whatsapp_business_account_id,display_phone_number,name_status", $token);
        if ($check['success'] && !empty($check['data']['whatsapp_business_account_id'])) {
            $discovered_waba = $check['data']['whatsapp_business_account_id'];
            break;
        }
    }

    if ($discovered_waba) {
        $waba_id = $discovered_waba;
        // Auto-save discovered WABA ID into database
        $safe_waba = $conn->real_escape_string($waba_id);
        $conn->query("UPDATE whatsapp_settings SET waba_id = '$safe_waba' WHERE id = 1");
    } else {
        echo json_encode([
            'error' => 'Could not auto-detect WhatsApp Business Account ID (WABA ID) from Phone Number ID. Please enter your WABA ID manually in the "WABA ID" field.'
        ]);
        exit;
    }
}

// Step 2: Fetch Message Templates from WABA
$tpl_res = meta_curl_get("https://graph.facebook.com/v21.0/{$waba_id}/message_templates?limit=100", $token);

// Fallback to v20.0 if v21.0 fails
if (!$tpl_res['success'] || isset($tpl_res['data']['error'])) {
    $tpl_res = meta_curl_get("https://graph.facebook.com/v20.0/{$waba_id}/message_templates?limit=100", $token);
}

if (!$tpl_res['success']) {
    echo json_encode(['error' => $tpl_res['error']]);
    exit;
}

$data = $tpl_res['data'];

if (isset($data['error'])) {
    $errMsg = $data['error']['message'] ?? 'Meta API error';
    $errCode = $data['error']['code'] ?? '';
    echo json_encode(['error' => "Meta API Error (Code {$errCode}): {$errMsg}"]);
    exit;
}

$templates = [];
if (!empty($data['data']) && is_array($data['data'])) {
    foreach ($data['data'] as $tpl) {
        // Extract body text preview if available
        $body_text = '';
        if (!empty($tpl['components']) && is_array($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                if (($comp['type'] ?? '') === 'BODY') {
                    $body_text = $comp['text'] ?? '';
                    break;
                }
            }
        }

        $templates[] = [
            'name'       => $tpl['name'] ?? '',
            'language'   => $tpl['language'] ?? 'en',
            'status'     => $tpl['status'] ?? 'UNKNOWN',
            'category'   => $tpl['category'] ?? 'UTILITY',
            'body_text'  => $body_text,
            'components' => $tpl['components'] ?? []
        ];
    }
}

echo json_encode([
    'success'   => true,
    'waba_id'   => $waba_id,
    'count'     => count($templates),
    'templates' => $templates
]);
