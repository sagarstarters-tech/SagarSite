<?php
/**
 * WAPI SaaS - AI Bot Builder: List Knowledge Base Items API
 * Returns all KB entries (documents, URLs, Q&A pairs) for a bot.
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
$botId  = sanitizeInt($_GET['bot_id'] ?? 0);

if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
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

    // Get all knowledge bases for this bot
    $knowledgeBases = $db->fetchAll(
        "SELECT id, name, status, created_at, updated_at 
         FROM ai_knowledge_bases 
         WHERE bot_id = ? AND user_id = ? 
         ORDER BY created_at DESC",
        [$botId, $userId]
    ) ?: [];

    // For each KB, fetch its items
    $kbData = [];
    foreach ($knowledgeBases as $kb) {
        $kbId = $kb['id'];

        // Fetch documents
        $documents = $db->fetchAll(
            "SELECT id, file_name, file_path, file_type, file_size, status, created_at 
             FROM ai_kb_documents 
             WHERE kb_id = ? 
             ORDER BY created_at DESC",
            [$kbId]
        ) ?: [];

        // Fetch URLs
        $urls = $db->fetchAll(
            "SELECT id, url, title, status, last_crawled_at, created_at 
             FROM ai_kb_urls 
             WHERE kb_id = ? 
             ORDER BY created_at DESC",
            [$kbId]
        ) ?: [];

        // Fetch Q&A pairs
        $qaPairs = $db->fetchAll(
            "SELECT id, question, answer, is_active, created_at 
             FROM ai_kb_qa_pairs 
             WHERE kb_id = ? 
             ORDER BY created_at DESC",
            [$kbId]
        ) ?: [];

        $kb['documents'] = $documents;
        $kb['urls']      = $urls;
        $kb['qa_pairs']  = $qaPairs;
        $kb['stats']     = [
            'documents' => count($documents),
            'urls'      => count($urls),
            'qa_pairs'  => count($qaPairs),
            'total'     => count($documents) + count($urls) + count($qaPairs)
        ];

        $kbData[] = $kb;
    }

    echo json_encode([
        'success' => true,
        'data'    => $kbData,
        'message' => 'Knowledge base data retrieved successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot list-kb error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve knowledge base data.']);
}
