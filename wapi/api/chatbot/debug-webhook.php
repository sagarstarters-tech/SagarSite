<?php
/**
 * WAPI - Chatbot Webhook Diagnostic
 * Hit this endpoint to verify the chatbot engine status on production.
 * URL: /api/chatbot/debug-webhook
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

$db = Database::getInstance();
$result = [];

// 1. Check code version (our fix marker)
$webhookFile = file_get_contents(__DIR__ . '/../webhook.php');
$result['fix_applied'] = strpos($webhookFile, '$rawInput') !== false && strpos($webhookFile, "case 'text':") !== false;
$result['input_fix'] = strpos($webhookFile, '$input   = $rawInput') !== false;

// 2. Check functions.php fix
$functionsFile = file_get_contents(__DIR__ . '/../../chatbot-engine/functions.php');
$result['text_case_fix'] = strpos($functionsFile, "case 'text':") !== false;

// 3. Check WhatsApp accounts
try {
    $accounts = $db->fetchAll("SELECT id, user_id, phone_number_id, phone_number, status FROM whatsapp_accounts LIMIT 10");
    $result['whatsapp_accounts'] = $accounts;
    $result['whatsapp_accounts_count'] = count($accounts);
} catch (Exception $e) {
    $result['whatsapp_accounts_error'] = $e->getMessage();
}

// 4. Check chatbot flows
try {
    $flows = $db->fetchAll("SELECT id, user_id, name, is_active, LENGTH(flow_json) as json_length, updated_at FROM chatbot_flows ORDER BY updated_at DESC LIMIT 10");
    $result['chatbot_flows'] = $flows;
    
    // Check if any flow has actual nodes
    foreach ($flows as &$flow) {
        $fullFlow = $db->fetch("SELECT flow_json FROM chatbot_flows WHERE id = ?", [$flow['id']]);
        if ($fullFlow) {
            $data = json_decode($fullFlow['flow_json'], true);
            $nodes = $data['drawflow']['Home']['data'] ?? $data['drawflow']['home']['data'] ?? [];
            $flow['node_count'] = count($nodes);
            $flow['node_types'] = [];
            foreach ($nodes as $nId => $nData) {
                $flow['node_types'][] = $nId . ':' . ($nData['name'] ?? 'unknown');
            }
        }
    }
    $result['chatbot_flows'] = $flows;
} catch (Exception $e) {
    $result['chatbot_flows_error'] = $e->getMessage();
}

// 5. Check chatbot sessions
try {
    $sessions = $db->fetchAll("SELECT * FROM chatbot_sessions ORDER BY updated_at DESC LIMIT 5");
    $result['recent_sessions'] = $sessions;
} catch (Exception $e) {
    $result['sessions_error'] = $e->getMessage();
}

// 6. Check recent webhook logs
try {
    $webhookLogs = $db->fetchAll("SELECT id, user_id, event_type, status, created_at FROM webhook_logs ORDER BY id DESC LIMIT 5");
    $result['recent_webhook_logs'] = $webhookLogs;
} catch (Exception $e) {
    $result['webhook_logs_error'] = $e->getMessage();
}

// 7. Check recent inbound messages 
try {
    $inbound = $db->fetchAll("SELECT id, user_id, to_number, type, LEFT(content, 50) as content_preview, status, direction, created_at FROM messages WHERE direction = 'inbound' ORDER BY id DESC LIMIT 5");
    $result['recent_inbound'] = $inbound;
} catch (Exception $e) {
    $result['inbound_error'] = $e->getMessage();
}

// 8. Check log files
$logFiles = [
    'webhook_raw' => __DIR__ . '/../webhook_raw.log',
    'webhook_debug' => __DIR__ . '/../webhook_debug.txt',
    'webhook_root' => __DIR__ . '/../../logs/webhook_root.log',
    'engine_debug' => __DIR__ . '/../../chatbot-engine/webhook_debug.log',
    'engine_errors' => __DIR__ . '/../../chatbot-engine/webhook_errors.log',
];

foreach ($logFiles as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $tail = $size > 500 ? file_get_contents($path, false, null, $size - 500) : file_get_contents($path);
        $result['logs'][$name] = [
            'exists' => true,
            'size' => $size,
            'last_modified' => date('Y-m-d H:i:s', filemtime($path)),
            'tail' => trim($tail)
        ];
    } else {
        $result['logs'][$name] = ['exists' => false];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
