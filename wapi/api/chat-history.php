<?php
/**
 * WAPI SaaS - Chat History API
 * Fetches message history with a specific contact
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$phone = sanitize($_GET['phone'] ?? '');

if (empty($phone)) {
    echo json_encode([]);
    exit;
}

$messages = $db->fetchAll("
    SELECT * FROM messages 
    WHERE user_id = ? AND to_number = ? 
    ORDER BY created_at ASC 
    LIMIT 50
", [$userId, $phone]);

$response = [];
foreach ($messages as $m) {
    $response[] = [
        'id' => $m['id'],
        'content' => e($m['content']),
        'type' => $m['type'] ?? 'text',
        'media_url' => $m['media_url'] ?? '',
        'direction' => $m['direction'],
        'status' => $m['status'],
        'time' => date('H:i', strtotime($m['created_at']))
    ];
}

echo json_encode($response);
exit;
