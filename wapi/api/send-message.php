<?php
/**
 * WAPI SaaS - Public API Endpoint for Sending Messages
 * Authenticated via API Key header
 */
require_once __DIR__ . '/../config/config.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Authenticate via API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'API key required. Pass via X-API-Key header.']);
    exit;
}

$db = Database::getInstance();
$keyRecord = $db->fetch("SELECT ak.*, u.status as user_status FROM api_keys ak JOIN users u ON ak.user_id = u.id WHERE ak.api_key = ? AND ak.is_active = 1", [$apiKey]);

if (!$keyRecord) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive API key.']);
    exit;
}

if ($keyRecord['user_status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account suspended. Contact support.']);
    exit;
}

// Update last used
$db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$keyRecord['id']]);

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$userId = $keyRecord['user_id'];
$to = $input['to'] ?? '';
$type = $input['type'] ?? 'text';
$message = $input['message'] ?? $input['content'] ?? '';
$mediaUrl = $input['media_url'] ?? '';
$templateName = $input['template'] ?? '';

if (empty($to)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Field "to" (phone number) is required.']);
    exit;
}

// Get user's WhatsApp account
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);
if (!$waAccount) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No active WhatsApp account configured.']);
    exit;
}

$wa = new WhatsApp();
$result = null;

switch ($type) {
    case 'text':
        $result = $wa->sendText($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $message);
        break;
    case 'image':
        $result = $wa->sendImage($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, $message);
        break;
    case 'video':
        $result = $wa->sendVideo($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, $message);
        break;
    case 'document':
        $result = $wa->sendDocument($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $mediaUrl, $input['filename'] ?? '', $message);
        break;
    case 'template':
        $result = $wa->sendTemplate($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, $templateName, $input['language'] ?? 'en', $input['components'] ?? []);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid message type. Use: text, image, video, document, template']);
        exit;
}

http_response_code($result['success'] ? 200 : 500);
echo json_encode($result);
