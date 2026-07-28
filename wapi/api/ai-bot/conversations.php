<?php
/**
 * WAPI SaaS - AI Bot Builder: Conversations API
 * Returns paginated conversation history with filtering.
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

// Read filter params from GET
$botId    = sanitizeInt($_GET['bot_id'] ?? 0);
$page     = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage  = max(1, min(100, sanitizeInt($_GET['per_page'] ?? ITEMS_PER_PAGE)));
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo   = sanitize($_GET['date_to'] ?? '');

try {
    $db = Database::getInstance();

    // If conversation_id is requested, return conversation messages instead
    $conversationId = sanitizeInt($_GET['conversation_id'] ?? 0);
    if ($conversationId > 0) {
        $conv = $db->fetch(
            "SELECT c.* FROM ai_conversations c 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE c.id = ? AND b.user_id = ?",
            [$conversationId, $userId]
        );
        
        if (!$conv) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Conversation not found']);
            exit;
        }

        $messages = $db->fetchAll(
            "SELECT id, direction, sender_type, content, created_at 
             FROM ai_messages 
             WHERE conversation_id = ? 
             ORDER BY created_at ASC",
            [$conversationId]
        );

        echo json_encode([
            'success' => true,
            'data'    => [
                'conversation' => $conv,
                'messages'     => $messages
            ],
            'message' => 'Messages retrieved successfully'
        ]);
        exit;
    }

    // Build query conditions
    $conditions = ['c.user_id = ?'];
    $params     = [$userId];

    if ($botId > 0) {
        $conditions[] = 'c.bot_id = ?';
        $params[]     = $botId;
    }

    if (!empty($search)) {
        $conditions[] = '(c.customer_name LIKE ? OR c.customer_phone LIKE ? OR (SELECT content FROM ai_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) LIKE ?)';
        $searchTerm   = '%' . $search . '%';
        $params[]     = $searchTerm;
        $params[]     = $searchTerm;
        $params[]     = $searchTerm;
    }

    if (!empty($status)) {
        $conditions[] = 'c.status = ?';
        $params[]     = $status;
    }

    if (!empty($dateFrom)) {
        $conditions[] = 'DATE(c.created_at) >= ?';
        $params[]     = $dateFrom;
    }

    if (!empty($dateTo)) {
        $conditions[] = 'DATE(c.created_at) <= ?';
        $params[]     = $dateTo;
    }

    $whereClause = implode(' AND ', $conditions);

    // Count total matching records
    $totalCount = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ai_conversations c WHERE {$whereClause}",
        $params
    );

    // Calculate pagination
    $pagination = paginate($totalCount, $page, $perPage);

    // Fetch paginated results with bot name
    $conversations = $db->fetchAll(
        "SELECT c.id, c.bot_id, c.customer_name AS contact_name, c.customer_phone AS contact_phone, c.status, 
                c.messages_count AS message_count, 
                (SELECT content FROM ai_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message, 
                c.last_message_at,
                c.status AS resolution_status, c.created_at, c.updated_at,
                b.name AS bot_name
         FROM ai_conversations c
         LEFT JOIN ai_bots b ON c.bot_id = b.id
         WHERE {$whereClause}
         ORDER BY c.updated_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $pagination['offset']])
    ) ?: [];

    echo json_encode([
        'success'    => true,
        'data'       => $conversations,
        'pagination' => [
            'total'        => $totalCount,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $pagination['total_pages'],
            'has_prev'     => $pagination['has_prev'],
            'has_next'     => $pagination['has_next']
        ],
        'message'    => 'Conversations retrieved successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot conversations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve conversations.']);
}
