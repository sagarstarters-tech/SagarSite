<?php
/**
 * WAPI SaaS - AI Bot Builder: Test Bot API
 * Sends a test message to a bot and returns the AI response (without sending via WhatsApp).
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

$botId   = sanitizeInt($input['bot_id'] ?? 0);
$message = trim($input['message'] ?? '');

// Validate inputs
if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (strlen($message) > 4000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message must be under 4000 characters']);
    exit;
}

try {
    // Verify bot ownership
    $bot = AIBot::getById($botId, $userId);
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bot not found or access denied']);
        exit;
    }

    // Process message with test parameters — returns AI response without sending via WhatsApp
    $result = AIOrchestrator::processMessage(
        $botId,
        'test_user_' . $userId,
        'Test User',
        $message,
        'test',
        'test'
    );

    $reply = $result['message'] ?? 'No response generated. Check your bot configuration.';
    $success = ($result['status'] === 'success' || $result['status'] === 'handover');

    echo json_encode([
        'success' => $success,
        'reply'   => $reply,
        'data'    => [
            'response'      => $reply,
            'status'        => $result['status'] ?? 'unknown',
            'model_used'    => $result['model'] ?? $bot['ai_model'] ?? 'unknown',
            'tokens_used'   => $result['tokens_used'] ?? 0,
            'processing_ms' => $result['response_time_ms'] ?? 0,
            'test_mode'     => true
        ],
        'message' => $result['status'] === 'error' ? ($result['error'] ?? 'Error') : 'Test response generated successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot test-bot error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate test response: ' . $e->getMessage()
    ]);
}
