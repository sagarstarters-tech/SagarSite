<?php
/**
 * WAPI SaaS - AI Bot Builder: List Bots API
 * Returns all AI bots for the logged-in user.
 * Method: GET
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

// Auth check
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $bots = AIBot::getByUser($userId);

    echo json_encode([
        'success' => true,
        'data'    => $bots ?: [],
        'message' => 'Bots retrieved successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve bots. Please try again.']);
}
