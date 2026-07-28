<?php
/**
 * WAPI SaaS - List All Chatbot Flows API
 * Returns all saved flows for the logged-in user.
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
    $flows = $db->fetchAll(
        "SELECT id, name, is_active, updated_at, created_at FROM chatbot_flows WHERE user_id = ? ORDER BY updated_at DESC, id DESC",
        [$userId]
    );

    echo json_encode([
        'success' => true,
        'flows'   => $flows ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
