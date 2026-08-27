<?php
/**
 * ============================================================
 *  Analytics Heartbeat Endpoint (Public)
 *  Location: /api/analytics_heartbeat.php
 * ============================================================
 *  Ultra-lightweight endpoint for real-time live visitor tracking.
 *  - Updates last_activity timestamp on regular heartbeat (every 25s)
 *  - Immediately marks session inactive on leave/pagehide signal
 * ============================================================
 */

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/DbConnection.php';

// Check if user is logged-in admin (exclude admin from live customer metrics)
if (session_status() === PHP_SESSION_NONE) {
    include_once BASE_PATH . '/includes/session_setup.php';
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    http_response_code(204);
    exit;
}

// Check CORS Origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

try {
    $pdo = DbConnection::getInstance();

    // Check if tracking is globally enabled
    $stmt = $pdo->prepare("SELECT setting_value FROM analytics_settings WHERE setting_key = 'tracking_enabled' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() === '0') {
        http_response_code(204);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    $visitorUid = preg_replace('/[^a-f0-9]/', '', substr($input['visitor_uid'] ?? '', 0, 32));
    $sessionId  = preg_replace('/[^a-f0-9]/', '', substr($input['session_id'] ?? '', 0, 32));
    $action     = trim($input['action'] ?? 'heartbeat');

    if (strlen($visitorUid) >= 16 && strlen($sessionId) >= 16) {
        if ($action === 'leave') {
            // Visitor closed tab or navigated away — set last_activity back to immediately drop from live counter
            $stmt = $pdo->prepare("UPDATE analytics_visitors SET last_activity = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE visitor_uid = ? AND session_id = ?");
            $stmt->execute([$visitorUid, $sessionId]);
        } else {
            // Regular heartbeat ping — mark active right now
            $stmt = $pdo->prepare("UPDATE analytics_visitors SET last_activity = NOW() WHERE visitor_uid = ? AND session_id = ?");
            $stmt->execute([$visitorUid, $sessionId]);
        }
    }
} catch (\Throwable $e) {
    // Heartbeat failures are non-critical and silent
}

http_response_code(204);
