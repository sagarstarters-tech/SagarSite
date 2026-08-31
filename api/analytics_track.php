<?php
/**
 * ============================================================
 *  Analytics Tracking Endpoint (Public)
 *  Location: /api/analytics_track.php
 * ============================================================
 *  Receives tracking events from the frontend JS tracker via
 *  sendBeacon / fetch POST. Lightweight, fire-and-forget.
 *
 *  Events: page_view, product_view, search
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

// Fast-fail on non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── Bootstrap (minimal) ──────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/modules/AnalyticsService.php';

// Exclude logged-in admin from customer telemetry
if (session_status() === PHP_SESSION_NONE) {
    include_once BASE_PATH . '/includes/session_setup.php';
}
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
session_write_close(); // Release session file lock immediately

if ($isAdmin) {
    http_response_code(204);
    exit;
}

// ── Check if tracking is enabled ─────────────────────────────
$pdo = DbConnection::getInstance();
$stmt = $pdo->prepare("SELECT setting_value FROM analytics_settings WHERE setting_key = 'tracking_enabled' LIMIT 1");
$stmt->execute();
$enabled = $stmt->fetchColumn();
if ($enabled === '0') {
    http_response_code(204);
    exit;
}

// ── CORS / Origin Check ─────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$siteUrl = defined('SITE_URL') ? SITE_URL : '';
if (!empty($origin) && !empty($siteUrl)) {
    $siteDomain = parse_url($siteUrl, PHP_URL_HOST);
    $originDomain = parse_url($origin, PHP_URL_HOST);
    if ($siteDomain && $originDomain) {
        if ($siteDomain === $originDomain || $originDomain === 'www.' . $siteDomain || $siteDomain === 'www.' . $originDomain) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
    }
}
header('Content-Type: application/json');

// ── Rate Limiting (simple, session-less) ─────────────────────
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}
$ipHash = hash('sha256', $clientIp);

// Simple rate-limit via DB: max 120 events/minute per IP hash
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM analytics_page_views pv
    JOIN analytics_visitors v ON v.session_id = pv.session_id
    WHERE v.ip_hash = ?
    AND pv.viewed_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
try {
    $stmt->execute([$ipHash]);
    $recentCount = (int)$stmt->fetchColumn();
    if ($recentCount > 120) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited']);
        exit;
    }
} catch (\Throwable $e) {
    // Rate limit check failure should not block tracking
}

// ── Parse Input ──────────────────────────────────────────────
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || empty($input['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit;
}

$event      = substr(trim($input['event'] ?? ''), 0, 30);
$visitorUid = preg_replace('/[^a-f0-9]/', '', substr($input['visitor_uid'] ?? '', 0, 32));
$sessionId  = preg_replace('/[^a-f0-9]/', '', substr($input['session_id'] ?? '', 0, 32));
$pageUrl    = substr(trim($input['page_url'] ?? ''), 0, 500);
$pageTitle  = substr(trim($input['page_title'] ?? ''), 0, 300);
$referrer   = substr(trim($input['referrer'] ?? ''), 0, 1000);
$productId  = isset($input['product_id']) ? (int)$input['product_id'] : null;
$productName = isset($input['product_name']) ? substr(trim($input['product_name']), 0, 255) : null;
$searchQuery = isset($input['search_query']) ? substr(trim($input['search_query']), 0, 255) : null;
$resultCount = isset($input['result_count']) ? (int)$input['result_count'] : 0;
$fromSearch  = isset($input['from_search']) ? substr(trim($input['from_search']), 0, 255) : null;

// Validate required fields
if (strlen($visitorUid) < 16 || strlen($sessionId) < 16) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_ids']);
    exit;
}

$allowedEvents = ['page_view', 'product_view', 'search', 'whatsapp_click'];
if (!in_array($event, $allowedEvents)) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown_event']);
    exit;
}

// ── User Agent Parsing ───────────────────────────────────────
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Filter bots
if (AnalyticsService::isBot($userAgent)) {
    http_response_code(204);
    exit;
}

$uaParsed = AnalyticsService::parseUserAgent($userAgent);
$trafficSource = AnalyticsService::classifyTrafficSource($referrer);

// ── Create/Update Visitor Record ─────────────────────────────
try {
    $analyticsService = new AnalyticsService($pdo);
    $now = date('Y-m-d H:i:s');

    // Check if visitor session already exists
    $stmt = $pdo->prepare("SELECT id FROM analytics_visitors WHERE visitor_uid = ? AND session_id = ? LIMIT 1");
    $stmt->execute([$visitorUid, $sessionId]);
    $visitorId = $stmt->fetchColumn();

    if ($visitorId) {
        // Update last activity
        $stmt = $pdo->prepare("UPDATE analytics_visitors SET last_activity = ? WHERE id = ?");
        $stmt->execute([$now, $visitorId]);
    } else {
        // Geolocation (server-side)
        $geo = $analyticsService->geolocate($clientIp);

        $stmt = $pdo->prepare("
            INSERT INTO analytics_visitors
            (visitor_uid, session_id, first_visit, last_activity, landing_page, referrer,
             traffic_source, device_type, browser, os, country, region, city, ip_hash, is_bot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $visitorUid, $sessionId, $now, $now, $pageUrl, $referrer,
            $trafficSource, $uaParsed['device_type'], $uaParsed['browser'], $uaParsed['os'],
            $geo['country'], $geo['region'], $geo['city'], $ipHash
        ]);
        $visitorId = $pdo->lastInsertId();
    }

    // ── Record Event ─────────────────────────────────────────
    switch ($event) {
        case 'page_view':
            // Duplicate protection: same visitor, same page, within 5 seconds
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM analytics_page_views
                WHERE visitor_id = ? AND page_url = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ");
            $stmt->execute([$visitorId, $pageUrl]);
            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO analytics_page_views (visitor_id, page_url, page_title, referrer, viewed_at, session_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$visitorId, $pageUrl, $pageTitle, $referrer, $now, $sessionId]);
            }
            break;

        case 'product_view':
            if ($productId && $productId > 0) {
                // Duplicate protection: same visitor, same product, within 10 seconds
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM analytics_product_views
                    WHERE visitor_id = ? AND product_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
                ");
                $stmt->execute([$visitorId, $productId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO analytics_product_views
                        (visitor_id, product_id, product_name, referrer, viewed_at, session_id, from_search)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$visitorId, $productId, $productName, $referrer, $now, $sessionId, $fromSearch]);
                }
            }
            break;

        case 'search':
            if ($searchQuery && strlen($searchQuery) > 0) {
                // Duplicate protection: same visitor, same query, within 5 seconds
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM analytics_searches
                    WHERE visitor_id = ? AND search_query = ? AND searched_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                ");
                $stmt->execute([$visitorId, $searchQuery]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO analytics_searches (visitor_id, search_query, result_count, searched_at, session_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$visitorId, $searchQuery, $resultCount, $now, $sessionId]);
                }
            }
            break;

        case 'whatsapp_click':
            // Track WhatsApp button clicks reusing the product_views table.
            // product_id  = 0 means cart; from_search stores button_type:qty metadata.
            if ($productId !== null) {
                $wa_btn_meta = $fromSearch ?: 'wa_btn:unknown';
                // Duplicate protection: same visitor, same product, same button, within 15 seconds
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM analytics_product_views
                    WHERE visitor_id = ? AND product_id = ? AND from_search = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 15 SECOND)
                ");
                $stmt->execute([$visitorId, $productId, $wa_btn_meta]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO analytics_product_views
                        (visitor_id, product_id, product_name, referrer, viewed_at, session_id, from_search)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$visitorId, $productId, $productName, $referrer, $now, $sessionId, $wa_btn_meta]);
                }
            }
            break;
    }

    http_response_code(204);

} catch (\Throwable $e) {
    // Analytics errors must never break anything
    if (defined('APP_ENV') && APP_ENV !== 'production') {
        error_log('[Analytics Track] ' . $e->getMessage());
    }
    http_response_code(204); // Still return success to client
}
