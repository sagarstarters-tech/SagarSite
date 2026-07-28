<?php
/**
 * WAPI SaaS - Get Chatbot Flow API
 * Fetches the saved JSON flow from the database.
 * Supports: ?load_latest=1  |  ?id=<flowId>  |  ?name=<flowName>
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

try {
    if (!empty($_GET['load_latest'])) {
        // Load most recently saved flow
        $flow = $db->fetch(
            "SELECT id, name, flow_json FROM chatbot_flows WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1",
            [$userId]
        );
    } elseif (!empty($_GET['id'])) {
        // Load by specific flow ID (ownership check mandatory)
        $flowId = (int)$_GET['id'];
        $flow = $db->fetch(
            "SELECT id, name, flow_json FROM chatbot_flows WHERE id = ? AND user_id = ?",
            [$flowId, $userId]
        );
    } else {
        // Load by name (fallback)
        $flowName = $_GET['name'] ?? 'Master Flow';
        $flow = $db->fetch(
            "SELECT id, name, flow_json FROM chatbot_flows WHERE user_id = ? AND name = ?",
            [$userId, $flowName]
        );
    }

    if ($flow && $flow['flow_json']) {
        $flowData = json_decode($flow['flow_json'], true);
        echo json_encode([
            'success'   => true,
            'flow'      => $flowData,
            'flow_name' => $flow['name'],
            'flow_id'   => $flow['id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No flow found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
