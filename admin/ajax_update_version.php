<?php
/**
 * AJAX Endpoint: Update Website / Release Version
 */
include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

// Security: Only logged-in admin can change version
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = trim($_POST['version'] ?? '');
    if (empty($version)) {
        echo json_encode(['success' => false, 'message' => 'Version string cannot be empty.']);
        exit;
    }

    $safe_version = $conn->real_escape_string($version);
    $query = "INSERT INTO settings (setting_key, setting_value) 
              VALUES ('site_version', '$safe_version') 
              ON DUPLICATE KEY UPDATE setting_value='$safe_version'";

    if ($conn->query($query)) {
        echo json_encode([
            'success' => true,
            'version' => htmlspecialchars($version),
            'message' => 'Website version successfully updated to ' . htmlspecialchars($version) . '!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
