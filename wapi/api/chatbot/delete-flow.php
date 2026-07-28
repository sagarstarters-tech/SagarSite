<?php
/**
 * WAPI SaaS - Delete Chatbot Flow API
 * Safely deletes a flow by ID only if it belongs to the current user.
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

$input = file_get_contents('php://input');
$data  = json_decode($input, true);
$flowId = isset($data['flow_id']) ? (int)$data['flow_id'] : 0;

if ($flowId <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid flow ID']));
}

try {
    // Always verify ownership before deleting
    $existing = $db->fetch(
        "SELECT id FROM chatbot_flows WHERE id = ? AND user_id = ?",
        [$flowId, $userId]
    );

    if (!$existing) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Flow not found or access denied']));
    }

    $db->query("DELETE FROM chatbot_flows WHERE id = ? AND user_id = ?", [$flowId, $userId]);

    echo json_encode(['success' => true, 'message' => 'Flow deleted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
