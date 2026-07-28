<?php
/**
 * WAPI SaaS - AI Bot Builder: Add URL to Knowledge Base API
 * Adds a URL source to a bot's knowledge base.
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
$url   = trim($input['url'] ?? '');

// Validate inputs
if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
    exit;
}

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL is required']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid URL (e.g., https://example.com)']);
    exit;
}

// Only allow http/https schemes
$scheme = parse_url($url, PHP_URL_SCHEME);
if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only HTTP and HTTPS URLs are allowed']);
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

    // Add URL via class method
    $urlData = AIKnowledgeBase::addUrl($kbId, $userId, $url);

    if (!$urlData) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add URL to knowledge base.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $urlData,
        'message' => 'URL added successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot add-url error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add URL. Please try again.']);
}
