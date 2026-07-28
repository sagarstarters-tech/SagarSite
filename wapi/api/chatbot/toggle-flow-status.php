<?php
/**
 * WAPI SaaS - Toggle Chatbot Flow Status API
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
$data    = json_decode(file_get_contents('php://input'), true);
$flowId  = sanitizeInt($data['flow_id'] ?? 0);
$status  = sanitizeInt($data['is_active'] ?? 1);

if (!$flowId) {
    echo json_encode(['success' => false, 'message' => 'Missing flow ID']);
    exit;
}

try {
    // Ensure the flow belongs to the user
    $flow = $db->fetch("SELECT id FROM chatbot_flows WHERE id = ? AND user_id = ?", [$flowId, $userId]);
    if (!$flow) {
        echo json_encode(['success' => false, 'message' => 'Flow not found or access denied']);
        exit;
    }

    $db->update('chatbot_flows', ['is_active' => $status], "id = ?", [$flowId]);

    echo json_encode([
        'success' => true,
        'message' => 'Flow status updated successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
