<?php
/**
 * WAPI SaaS - Media Proxy
 * Proxies WhatsApp media downloads through the server since
 * Meta's media URLs require Authorization headers that browsers can't send via <img> tags.
 * 
 * Usage: media-proxy.php?url=ENCODED_URL
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$mediaUrl = $_GET['url'] ?? '';

if (empty($mediaUrl)) {
    http_response_code(400);
    echo 'Missing url parameter';
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get the user's WhatsApp access token
$waAccount = $db->fetch(
    "SELECT access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1",
    [$userId]
);

if (!$waAccount || empty($waAccount['access_token'])) {
    http_response_code(403);
    echo 'No active WhatsApp account';
    exit;
}

$accessToken = $waAccount['access_token'];

// Fetch the media from WhatsApp
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $mediaUrl,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || $error) {
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    file_put_contents(
        $logDir . '/media_proxy.log',
        '[' . date('Y-m-d H:i:s') . "] Failed to proxy media | HTTP: {$httpCode} | URL: {$mediaUrl} | Error: {$error}\n",
        FILE_APPEND
    );
    http_response_code(502);
    echo 'Failed to fetch media';
    exit;
}

// Set appropriate content type and cache headers
header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
header('Cache-Control: private, max-age=86400'); // Cache for 24 hours
header('Content-Length: ' . strlen($response));

echo $response;
exit;
