<?php
/**
 * WAPI SaaS - AI Bot Builder: Upload Document to Knowledge Base API
 * Handles document file uploads for bot training.
 * Method: POST (multipart/form-data)
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

$userId = $_SESSION['user_id'];
$botId  = sanitizeInt($_POST['bot_id'] ?? 0);

if ($botId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bot ID']);
    exit;
}

// Verify bot ownership
$bot = AIBot::getById($botId, $userId);
if (!$bot) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Bot not found or access denied']);
    exit;
}

// Validate file upload
if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No document file uploaded']);
    exit;
}

$file = $_FILES['document'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// Validate file type
$allowedTypes = ['pdf', 'docx', 'txt', 'csv'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)
    ]);
    exit;
}

// Check file size (max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds the 10MB limit.']);
    exit;
}

try {
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

    // Upload document via class method
    $document = AIKnowledgeBase::uploadDocument($kbId, $userId, $file);

    if (!$document) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to process document upload.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $document,
        'message' => 'Document uploaded successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot upload-document error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload document. Please try again.']);
}
