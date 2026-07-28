<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.']);
}

if (!CSRF::validateToken()) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token.']);
}

if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'Please select a valid file to upload.']);
}

$file = $_FILES['media'];
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'pdf', 'doc', 'docx'];

$result = uploadFile($file, 'campaigns', $allowedTypes);

if ($result['success']) {
    $fullUrl = baseUrl($result['path']);
    jsonResponse(['success' => true, 'url' => $fullUrl, 'path' => $result['path']]);
} else {
    jsonResponse(['success' => false, 'message' => $result['message']]);
}
