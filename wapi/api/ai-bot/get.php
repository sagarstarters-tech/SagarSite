<?php
/**
 * WAPI SaaS - AI Bot Builder: Get Bot Details API
 * Returns a single bot with knowledge base stats.
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
$botId  = sanitizeInt($_GET['id'] ?? 0);

if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
    exit;
}

try {
    $bot = AIBot::getById($botId, $userId);

    if (!$bot) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        exit;
    }

    // Fetch knowledge base stats
    $db = Database::getInstance();
    $kbStats = [
        'documents' => 0,
        'urls'      => 0,
        'qa_pairs'  => 0,
        'total'     => 0
    ];

    $kb = $db->fetch(
        "SELECT id FROM ai_knowledge_bases WHERE bot_id = ? AND user_id = ?",
        [$botId, $userId]
    );

    if ($kb) {
        $kbId = $kb['id'];

        $kbStats['documents'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ai_kb_documents WHERE kb_id = ?",
            [$kbId]
        );
        $kbStats['urls'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ai_kb_urls WHERE kb_id = ?",
            [$kbId]
        );
        $kbStats['qa_pairs'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ai_kb_qa_pairs WHERE kb_id = ?",
            [$kbId]
        );
        $kbStats['total'] = $kbStats['documents'] + $kbStats['urls'] + $kbStats['qa_pairs'];
    }

    $bot['knowledge_base_stats'] = $kbStats;

    echo json_encode([
        'success' => true,
        'data'    => $bot,
        'message' => 'Bot retrieved successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot get error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve bot details.']);
}
