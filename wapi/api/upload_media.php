<?php
/**
 * WAPI SaaS Platform - Generic Media Upload API Endpoint
 * Handles file uploads from Chatbot Builder
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Check if user is logged in (session validation)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error occurred.']);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpPath = $file['tmp_name'];

// Validate size
if ($fileSize > MAX_UPLOAD_SIZE) {
    $maxMb = floor(MAX_UPLOAD_SIZE / (1024 * 1024));
    echo json_encode(['status' => 'error', 'message' => "File size exceeds maximum allowed size ({$maxMb}MB)."]);
    exit;
}

// Validate extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExtensions = ALLOWED_EXTENSIONS; // From config.php (['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'mp4', 'mp3'])

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions)]);
    exit;
}

// Create chatbot uploads directory if it doesn't exist
$uploadPath = UPLOAD_DIR . 'chatbot/';
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0755, true);
}

// Generate unique file name to avoid overwriting
$newFileName = uniqid('media_') . '_' . time() . '.' . $fileExtension;
$destPath = $uploadPath . $newFileName;

if (move_uploaded_file($fileTmpPath, $destPath)) {
    // Generate public URL using APP_URL from config
    // APP_URL is defined in .env (e.g. https://wapi.sagarstarters.com)
    $baseUrl = rtrim(defined('APP_URL') ? APP_URL : '', '/');
    
    // Strip any trailing /wapi from APP_URL if it exists (subdomain setup)
    // Then append the path to uploads
    $publicUrl = $baseUrl . '/uploads/chatbot/' . $newFileName;

    // Log the URL for debugging
    file_put_contents(
        dirname(__DIR__) . '/chatbot-engine/webhook_debug.log',
        "[" . date('Y-m-d H:i:s') . "] Image uploaded: $publicUrl\n",
        FILE_APPEND | LOCK_EX
    );

    echo json_encode([
        'status' => 'success',
        'url' => $publicUrl,
        'message' => 'File uploaded successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check folder permissions.']);
}
