<?php
/**
 * ============================================================
 *  Analytics AJAX Endpoint (Admin)
 *  Location: /admin/ajax_analytics.php
 * ============================================================
 *  Returns JSON data for dashboard widgets (live count, live stream, etc.)
 *  Requires admin authentication.
 * ============================================================
 */

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';

// Auth check (AJAX - no redirect, return 403)
if (!AuthMiddleware::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/modules/AnalyticsService.php';

$action = $_GET['action'] ?? '';
$analytics = new AnalyticsService();

switch ($action) {
    case 'live_count':
        $live = $analytics->getLiveVisitors();
        echo json_encode([
            'success' => true,
            'count'   => $live['count']
        ]);
        break;

    case 'live_stream':
        $live = $analytics->getLiveVisitors();
        echo json_encode([
            'success'     => true,
            'count'       => $live['count'],
            'threshold'   => $live['threshold'] ?? 120,
            'visitors'    => $live['visitors'],
            'server_time' => date('H:i:s')
        ]);
        break;

    case 'summary':
        $filter = $_GET['filter'] ?? '7days';
        $start  = $_GET['start'] ?? null;
        $end    = $_GET['end'] ?? null;
        [$startDate, $endDate] = $analytics->getDateRange($filter, $start, $end);
        $stats = $analytics->getSummaryStats($startDate, $endDate);
        echo json_encode($stats);
        break;

    default:
        echo json_encode(['error' => 'unknown_action']);
}
