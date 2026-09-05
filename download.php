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
$sql = "SELECT ud.*, p.download_file, p.download_url, p.license_key, p.name, u.name as customer_name, u.email as customer_email 
        FROM user_downloads ud 
        JOIN products p ON ud.product_id = p.id 
        LEFT JOIN users u ON ud.user_id = u.id
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

// Check if user requested the License Key
$download_type = $_GET['type'] ?? 'file';
if ($download_type === 'license') {
    if (empty(trim($access['license_key'] ?? ''))) {
        die("No license key is configured for this product.");
    }

    $clean_name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($access['name']));
    $filename = (!empty($clean_name) ? $clean_name : 'PRODUCT') . '-LICENSE-KEY.txt';
    $cust_name = htmlspecialchars_decode($access['customer_name'] ?? 'Valued Customer');
    $cust_email = $access['customer_email'] ?? 'N/A';
    $order_num = $access['order_id'] ?? 'N/A';
    $issue_date = date('F d, Y h:i A');
    $host_domain = $_SERVER['HTTP_HOST'] ?? 'Store';

    $cert = "========================================================================\n";
    $cert .= "             OFFICIAL PRODUCT LICENSE KEY & REGISTRATION\n";
    $cert .= "========================================================================\n\n";
    $cert .= "Product Name      : " . $access['name'] . "\n";
    $cert .= "License Issued To : " . $cust_name . " (" . $cust_email . ")\n";
    $cert .= "Order Reference   : #" . $order_num . "\n";
    $cert .= "Customer ID       : #" . $access['user_id'] . "\n";
    $cert .= "Issued Date       : " . $issue_date . "\n";
    $cert .= "Store Domain      : " . $host_domain . "\n\n";
    $cert .= "------------------------------------------------------------------------\n";
    $cert .= "LICENSE KEY / SERIAL CODE:\n";
    $cert .= "------------------------------------------------------------------------\n\n";
    $cert .= trim($access['license_key']) . "\n\n";
    $cert .= "------------------------------------------------------------------------\n";
    $cert .= "IMPORTANT INSTRUCTIONS:\n";
    $cert .= "- Keep this license key confidential. Do not share it publicly.\n";
    $cert .= "- For software activation, copy and paste the key exactly as shown above.\n";
    $cert .= "- If you require technical support, please quote your Order #" . $order_num . ".\n";
    $cert .= "========================================================================\n";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($cert));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $cert;
    exit;
}

// Check Download Limit (for actual software file)
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
