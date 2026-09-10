<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Permission denied. Please log in as admin.']);
    exit;
}

// Fetch stored settings
$set_q = $conn->query("SELECT api_token, phone_number_id, waba_id FROM whatsapp_settings WHERE id = 1");
$settings = $set_q ? $set_q->fetch_assoc() : [];

// Allow query / post parameters to override stored values so user can test without saving first
$rawToken   = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$rawPhoneId = trim($_GET['phone_id'] ?? ($_POST['phone_id'] ?? ''));
$rawWabaId  = trim($_GET['waba_id'] ?? ($_POST['waba_id'] ?? ''));

$token    = !empty($rawToken) ? $rawToken : trim($settings['api_token'] ?? '');
$phone_id = !empty($rawPhoneId) ? $rawPhoneId : trim($settings['phone_number_id'] ?? '');
$waba_id  = !empty($rawWabaId) ? $rawWabaId : trim($settings['waba_id'] ?? '');

if (empty($token) || (empty($phone_id) && empty($waba_id))) {
    echo json_encode(['error' => 'Please configure your Meta API Token and Phone Number ID in Admin -> WhatsApp Notifs -> Settings first.']);
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

// Step 1: If WABA ID is missing, auto-discover it via debug_token or /me
if (empty($waba_id)) {
    // Discovery Method A: debug_token inspection (returns exact WABA IDs in granular_scopes)
    $debug = meta_curl_get("https://graph.facebook.com/debug_token?input_token=" . urlencode($token) . "&access_token=" . urlencode($token), $token);
    if ($debug['success'] && !empty($debug['data']['data']['granular_scopes'])) {
        foreach ($debug['data']['data']['granular_scopes'] as $scopeItem) {
            $scopeName = $scopeItem['scope'] ?? '';
            if (in_array($scopeName, ['whatsapp_business_management', 'whatsapp_business_messaging']) && !empty($scopeItem['target_ids'])) {
                $waba_id = $scopeItem['target_ids'][0];
                break;
            }
        }
    }

    // Discovery Method B: check businesses associated with token
    if (empty($waba_id)) {
        $checkMe = meta_curl_get("https://graph.facebook.com/v21.0/me?fields=id,name,businesses{owned_whatsapp_business_accounts,client_whatsapp_business_accounts}", $token);
        if ($checkMe['success'] && !empty($checkMe['data']['businesses']['data'])) {
            foreach ($checkMe['data']['businesses']['data'] as $biz) {
                if (!empty($biz['owned_whatsapp_business_accounts']['data'][0]['id'])) {
                    $waba_id = $biz['owned_whatsapp_business_accounts']['data'][0]['id'];
                    break;
                }
                if (!empty($biz['client_whatsapp_business_accounts']['data'][0]['id'])) {
                    $waba_id = $biz['client_whatsapp_business_accounts']['data'][0]['id'];
                    break;
                }
            }
        }
    }

    // Discovery Method C: check accounts via /me/accounts
    if (empty($waba_id)) {
        $checkAcc = meta_curl_get("https://graph.facebook.com/v21.0/me/accounts?fields=id,name", $token);
        if ($checkAcc['success'] && !empty($checkAcc['data']['data'][0]['id'])) {
            $waba_id = $checkAcc['data']['data'][0]['id'];
        }
    }

    if (!empty($waba_id)) {
        $safe_waba = $conn->real_escape_string($waba_id);
        $conn->query("UPDATE whatsapp_settings SET waba_id = '$safe_waba' WHERE id = 1");
    } else {
        echo json_encode([
            'error' => 'WhatsApp Business Account ID (WABA ID) could not be auto-detected. Please copy your "WhatsApp Business Account ID" from Meta WhatsApp Manager (under API Setup tab) and enter it in Admin -> WhatsApp Notifs -> Settings.'
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
        $body_text = '';
        $param_count = 0;
        $header_type = 'NONE';

        if (!empty($tpl['components']) && is_array($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                $compType = strtoupper($comp['type'] ?? '');
                if ($compType === 'HEADER') {
                    $header_type = strtoupper($comp['format'] ?? 'TEXT');
                }
                if ($compType === 'BODY') {
                    $body_text = $comp['text'] ?? '';
                    preg_match_all('/\{\{(\d+)\}\}/', $body_text, $pm);
                    if (!empty($pm[1])) {
                        $param_count = max(array_map('intval', $pm[1]));
                    }
                }
            }
        }

        $templates[] = [
            'name'        => $tpl['name'] ?? '',
            'language'    => $tpl['language'] ?? 'en',
            'status'      => $tpl['status'] ?? 'UNKNOWN',
            'category'    => $tpl['category'] ?? 'UTILITY',
            'body_text'   => $body_text,
            'param_count' => $param_count,
            'header_type' => $header_type,
            'components'  => $tpl['components'] ?? []
        ];
    }
}

echo json_encode([
    'success'   => true,
    'waba_id'   => $waba_id,
    'count'     => count($templates),
    'templates' => $templates
]);
