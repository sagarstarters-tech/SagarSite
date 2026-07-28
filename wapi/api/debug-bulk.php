<?php
/**
 * Temporary debug endpoint - check bulk message failures
 * DELETE THIS FILE AFTER DEBUGGING
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get last 10 failed template messages with their error details
$failed = $db->fetchAll(
    "SELECT id, to_number, type, content, template_name, status, error_message, created_at 
     FROM messages 
     WHERE user_id = ? AND status = 'failed' AND type = 'template' 
     ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

// Get ALL templates for this user (with full details)
$templates = $db->fetchAll(
    "SELECT id, name, language, status, header_type, header_content, body, footer 
     FROM templates WHERE user_id = ? ORDER BY name",
    [$userId]
);

// Check the bulk_send.log (last 15 lines)
$bulkLog = '';
$logFile = APP_ROOT . '/logs/bulk_send.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $bulkLog = implode('', array_slice($lines, -15));
}

// Check whatsapp_api.log (last 15 lines)
$apiLog = '';
$apiLogFile = APP_ROOT . '/logs/whatsapp_api.log';
if (file_exists($apiLogFile)) {
    $lines = file($apiLogFile);
    $apiLog = implode('', array_slice($lines, -15));
}

// Check payload log (last 5 entries)
$payloadLog = '';
$payloadLogFile = APP_ROOT . '/logs/api_payload.log';
if (file_exists($payloadLogFile)) {
    $lines = file($payloadLogFile);
    $payloadLog = implode('', array_slice($lines, -10));
}

// Check which code version is deployed (look for key markers)
$batchFile = file_get_contents(APP_ROOT . '/api/bulk-send-batch.php');
$codeVersion = [
    'has_template_language_param' => str_contains($batchFile, 'template_language'),
    'has_header_type_lookup' => str_contains($batchFile, 'header_type'),
    'has_debug_logging' => str_contains($batchFile, 'bulk_send.log'),
    'still_has_hardcoded_en' => str_contains($batchFile, "'en', \$templateComponents"),
];

echo json_encode([
    'user_id' => $userId,
    'code_version_check' => $codeVersion,
    'templates_in_db' => $templates,
    'recent_failed_messages' => $failed,
    'bulk_send_log' => $bulkLog,
    'api_error_log' => $apiLog,
    'payload_log' => $payloadLog,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
