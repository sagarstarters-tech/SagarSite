<?php
/**
 * AJAX Get Media
 * Returns a JSON array of image media from the media_library table.
 */

header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 500;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT * FROM media_library WHERE file_type = 'image' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];

while ($row = $result->fetch_assoc()) {
    $baseName = strtolower(basename($row['file_name']));
    $baseUrl  = strtolower(basename($row['file_url']));
    if (in_array($baseName, $dummies) || in_array($baseUrl, $dummies)) {
        continue;
    }

    $resolvedUrl = resolve_image_url($row['file_url']);
    if (empty($resolvedUrl) || strpos($resolvedUrl, 'placeholder') !== false) {
        $altUrl = resolve_image_url($row['file_name']);
        if (!empty($altUrl) && strpos($altUrl, 'placeholder') === false) {
            $resolvedUrl = $altUrl;
        } else {
            // Missing physical file from server; skip to avoid broken/placeholder tiles in media modal
            continue;
        }
    }

    $items[] = [
        'id' => $row['id'],
        'file_name' => mb_convert_encoding($row['file_name'], 'UTF-8', 'auto'),
        'file_url' => $resolvedUrl,
        'original_name' => mb_convert_encoding($row['original_name'], 'UTF-8', 'auto'),
    ];
}

$stmt->close();

$totalStmt = $conn->query("SELECT COUNT(*) as c FROM media_library WHERE file_type = 'image'");
$total = $totalStmt->fetch_assoc()['c'];

echo json_encode([
    'success' => true,
    'data' => $items,
    'total' => $total,
    'pages' => ceil($total / $limit)
], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
