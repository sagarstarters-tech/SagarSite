<?php
/**
 * ============================================================
 *  Analytics Heartbeat Endpoint (Public)
 *  Location: /api/analytics_heartbeat.php
 * ============================================================
 *  Ultra-lightweight endpoint for live visitor tracking.
 *  Simply updates last_activity timestamp.
 * ============================================================
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/DbConnection.php';

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    $visitorUid = preg_replace('/[^a-f0-9]/', '', substr($input['visitor_uid'] ?? '', 0, 32));
    $sessionId  = preg_replace('/[^a-f0-9]/', '', substr($input['session_id'] ?? '', 0, 32));

    if (strlen($visitorUid) >= 16 && strlen($sessionId) >= 16) {
        $pdo = DbConnection::getInstance();
        $stmt = $pdo->prepare("UPDATE analytics_visitors SET last_activity = NOW() WHERE visitor_uid = ? AND session_id = ? LIMIT 1");
        $stmt->execute([$visitorUid, $sessionId]);
    }
} catch (\Throwable $e) {
    // Heartbeat failures are non-critical
}

http_response_code(204);
