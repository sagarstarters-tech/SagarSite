<?php
require_once __DIR__ . '/includes/session_setup.php';
require_once __DIR__ . '/includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Invalid request.");
}

$user_id = $_SESSION['user_id'];

// Validate token and check access
$sql = "SELECT ud.*, p.download_file, p.download_url, p.name 
        FROM user_downloads ud 
        JOIN products p ON ud.product_id = p.id 
        WHERE ud.download_token = ? AND ud.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $token, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$access = $result->fetch_assoc();

if (!$access) {
    die("Access denied or invalid token.");
}

// Check Expiry
if ($access['expiry_date'] && strtotime($access['expiry_date']) < time()) {
    die("Download link has expired.");
}

// Check Download Limit
if ($access['download_limit'] !== null && $access['download_count'] >= $access['download_limit']) {
    die("Download limit reached.");
}

// Handle External URL
if (!empty($access['download_url'])) {
    // Increment count and redirect
    $conn->query("UPDATE user_downloads SET download_count = download_count + 1 WHERE id = " . $access['id']);
    header("Location: " . $access['download_url']);
    exit;
}

// Handle Local File
if (!empty($access['download_file'])) {
    $file_path = 'uploads/downloads/' . $access['download_file'];
    if (file_exists($file_path)) {
        // Increment count
        $conn->query("UPDATE user_downloads SET download_count = download_count + 1 WHERE id = " . $access['id']);
        
        // Serve file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($access['name'] . '.' . pathinfo($access['download_file'], PATHINFO_EXTENSION)) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        die("File not found on server.");
    }
}

die("Nothing to download.");
?>
