<?php
/**
 * WAPI SaaS - AI Bot Builder: Clone Bot API
 * Creates a duplicate of an existing bot.
 * Method: POST
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

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF validation
if (!CSRF::validateToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$userId = $_SESSION['user_id'];

// Read input from JSON body or POST data
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$input    = is_array($jsonData) ? $jsonData : $_POST;

$botId = sanitizeInt($input['bot_id'] ?? 0);

if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
    exit;
}

try {
    // Check plan limit before cloning — checkPlanLimit returns bool
    if (!AIBot::checkPlanLimit($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You have reached the maximum number of bots for your plan. Please upgrade to clone more.'
        ]);
        exit;
    }

    $newBotId = AIBot::cloneBot($botId, $userId);

    if (!$newBotId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bot not found or you do not have permission to clone it.']);
        exit;
    }

    $newBot = AIBot::getById($newBotId, $userId);

    echo json_encode([
        'success' => true,
        'data'    => $newBot,
        'message' => 'Bot cloned successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot clone error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to clone bot. Please try again.']);
}
