<?php
/**
 * WAPI SaaS - AI Bot Builder: Add Q&A Pair to Knowledge Base API
 * Adds a question-answer pair for bot training.
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

$botId    = sanitizeInt($input['bot_id'] ?? 0);
$question = trim($input['question'] ?? '');
$answer   = trim($input['answer'] ?? '');

// Validate inputs
$errors = [];
if ($botId <= 0) {
    $errors[] = 'Invalid bot ID.';
}
if (empty($question)) {
    $errors[] = 'Question is required.';
}
if (empty($answer)) {
    $errors[] = 'Answer is required.';
}
if (strlen($question) > 1000) {
    $errors[] = 'Question must be under 1000 characters.';
}
if (strlen($answer) > 5000) {
    $errors[] = 'Answer must be under 5000 characters.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
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

    $db = Database::getInstance();

    // Get or create knowledge base for this bot
    $kb = $db->fetch(
        "SELECT id FROM ai_knowledge_bases WHERE bot_id = ? AND user_id = ?",
        [$botId, $userId]
    );

    if (!$kb) {
        $kbId = $db->insert('ai_knowledge_bases', [
            'bot_id'  => $botId,
            'user_id' => $userId,
            'name'    => $bot['name'] . ' Knowledge Base',
            'status'  => 'active'
        ]);
    } else {
        $kbId = $kb['id'];
    }

    // Add Q&A pair via class method
    $qaData = AIKnowledgeBase::addQAPair($kbId, $userId, $question, $answer);

    if (!$qaData) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add Q&A pair.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $qaData,
        'message' => 'Q&A pair added successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot add-qa error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add Q&A pair. Please try again.']);
}
