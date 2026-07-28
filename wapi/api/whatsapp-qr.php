<?php
/**
 * WAPI SaaS - WhatsApp QR Session Proxy API
 * Securely proxies requests from the dashboard to the Node.js QR service.
 * All requests are authenticated via PHP session before forwarding.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Node.js QR Service config
$qrServiceUrl = 'http://127.0.0.1:3001';
$qrApiKey     = 'wapi_qr_secret_key_2026';

/**
 * Helper: Forward a request to the Node.js QR service
 */
function proxyToNode($url, $method = 'GET', $postData = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'QR Service is not running. Details: ' . $error, 'service_down' => true];
    }

    $data = json_decode($response, true);
    return $data ?: ['success' => false, 'error' => 'Invalid response from QR service'];
}

// ─── Route Actions ───────────────────────────────────────────
try {
    switch ($action) {

        case 'start':
            // CSRF check for POST actions
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                die(json_encode(['success' => false, 'error' => 'POST required']));
            }
            $result = proxyToNode("$qrServiceUrl/session/start", 'POST', [
                'userId' => $userId,
                'apiKey' => $qrApiKey
            ]);
            echo json_encode($result);
            break;

        case 'qr':
            $result = proxyToNode("$qrServiceUrl/session/qr/$userId");
            echo json_encode($result);
            break;

        case 'status':
            $result = proxyToNode("$qrServiceUrl/session/status/$userId");
            echo json_encode($result);
            break;

        case 'disconnect':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                die(json_encode(['success' => false, 'error' => 'POST required']));
            }
            $result = proxyToNode("$qrServiceUrl/session/disconnect", 'POST', [
                'userId' => $userId,
                'apiKey' => $qrApiKey
            ]);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: start, qr, status, disconnect']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
