<?php
/**
 * WAPI SaaS - AI Bot Builder: Analytics API
 * Returns dashboard stats, chart data, resolution breakdown, and top questions.
 * Method: GET
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

// Auth check
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Read params from GET
$botId  = sanitizeInt($_GET['bot_id'] ?? 0);
$period = sanitize($_GET['period'] ?? '30d');

// Validate period
$allowedPeriods = ['7d', '30d', '90d'];
if (!in_array($period, $allowedPeriods, true)) {
    $period = '30d';
}

try {
    // Get dashboard stats (total conversations, messages, avg response time, resolution rate)
    $dashboardStats = AIAnalytics::getDashboardStats($userId, $botId > 0 ? $botId : null, $period);

    // Get chart data (messages over time for graphs)
    $chartData = AIAnalytics::getChartData($userId, $botId > 0 ? $botId : null, $period);

    // Get resolution breakdown (resolved, unresolved, escalated, etc.)
    $resolutionBreakdown = AIAnalytics::getResolutionBreakdown($userId, $botId > 0 ? $botId : null, $period);

    // Get most asked questions
    $mostAskedQuestions = AIAnalytics::getMostAskedQuestions($userId, $botId > 0 ? $botId : null, $period);

    echo json_encode([
        'success' => true,
        'data'    => [
            'dashboard_stats'      => $dashboardStats,
            'chart_data'           => $chartData,
            'resolution_breakdown' => $resolutionBreakdown,
            'most_asked_questions' => $mostAskedQuestions,
            'period'               => $period,
            'bot_id'               => $botId > 0 ? $botId : null
        ],
        'message' => 'Analytics data retrieved successfully'
    ]);
} catch (Exception $e) {
    error_log("AI Bot analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve analytics data.']);
}
