<?php
/**
 * WAPI SaaS - Bulk Send Batch Processor (AJAX)
 * Processes one batch of contacts at a time to avoid server timeouts
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

set_time_limit(120);
ignore_user_abort(false);
header('Content-Type: application/json');

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::validateToken()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db       = Database::getInstance();
$userId   = $_SESSION['user_id'];

$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);
if (!$waAccount) {
    echo json_encode(['success' => false, 'message' => 'WhatsApp account not configured.']);
    exit;
}

$type             = sanitize($_POST['type'] ?? 'text');
$content          = $_POST['content'] ?? '';
$mediaUrl         = sanitize($_POST['media_url'] ?? '');
$phones           = json_decode($_POST['phones'] ?? '[]', true);
$templateLanguage = sanitize($_POST['template_language'] ?? '');

// Rebuild template components if any
$templateComponents = [];
$rawComponents = $_POST['template_components'] ?? '';
if ($rawComponents) {
    $decoded = json_decode($rawComponents, true);
    if (is_array($decoded)) {
        $templateComponents = $decoded;
    }
}
// If template, look up full template details from DB
$templateHeaderType = 'none';
if ($type === 'template' && !empty($content)) {
    $tplRow = $db->fetch("SELECT language, header_type FROM templates WHERE user_id = ? AND name = ? LIMIT 1", [$userId, $content]);
    if ($tplRow) {
        // Use DB language if not passed from frontend
        if (empty($templateLanguage)) {
            $templateLanguage = $tplRow['language'];
        }
        $templateHeaderType = $tplRow['header_type'] ?? 'none';
        
        // If template has image/video/document header but no header component was passed, 
        // try to build it from media_url
        if (in_array($templateHeaderType, ['image', 'video', 'document']) && !empty($mediaUrl)) {
            $hasHeaderComponent = false;
            foreach ($templateComponents as $comp) {
                if (($comp['type'] ?? '') === 'header') {
                    $hasHeaderComponent = true;
                    break;
                }
            }
            if (!$hasHeaderComponent) {
                $headerParam = ['type' => $templateHeaderType, $templateHeaderType => ['link' => $mediaUrl]];
                array_unshift($templateComponents, ['type' => 'header', 'parameters' => [$headerParam]]);
            }
        }
    }
}
// Final fallback for language
if (empty($templateLanguage)) {
    $templateLanguage = 'en';
}

if (empty($phones) || !is_array($phones)) {
    echo json_encode(['success' => false, 'message' => 'No phone numbers in batch.']);
    exit;
}

// Log bulk-send details for debugging
$logDir = APP_ROOT . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/bulk_send.log',
    '[' . date('Y-m-d H:i:s') . '] TYPE: ' . $type . ' | TEMPLATE: ' . $content . ' | LANG: ' . $templateLanguage . ' | PHONES: ' . count($phones) . ' | COMPONENTS: ' . json_encode($templateComponents) . "\n",
    FILE_APPEND
);

$wa = new WhatsApp();
$sent   = 0;
$failed = 0;
$errors = [];

foreach ($phones as $phone) {
    $phone = trim($phone);
    if (empty($phone)) continue;

    switch ($type) {
        case 'text':
            $result = $wa->sendText($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $phone, $content);
            break;
        case 'image':
            $result = $wa->sendImage($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $phone, $mediaUrl, $content);
            break;
        case 'template':
            $result = $wa->sendTemplate($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $phone, $content, $templateLanguage, $templateComponents);
            break;
        default:
            $result = $wa->sendText($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $phone, $content);
    }

    if ($result['success']) {
        $sent++;
    } else {
        $failed++;
        $errors[] = "$phone: " . $result['message'];
    }

    // 15ms delay – Meta rate limit
    usleep(15000);
}

echo json_encode([
    'success' => true,
    'sent'    => $sent,
    'failed'  => $failed,
    'errors'  => $errors
]);
