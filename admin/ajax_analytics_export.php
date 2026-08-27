<?php
/**
 * ============================================================
 *  Analytics CSV Export Endpoint (Admin)
 *  Location: /admin/ajax_analytics_export.php
 * ============================================================
 *  Streams CSV export for analytics data.
 *  Requires admin authentication.
 * ============================================================
 */

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
AuthMiddleware::check($conn);

require_once __DIR__ . '/modules/AnalyticsService.php';

$type   = $_GET['type'] ?? '';
$filter = $_GET['filter'] ?? '7days';
$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;

$analytics = new AnalyticsService();
[$startDate, $endDate] = $analytics->getDateRange($filter, $start, $end);

$allowedTypes = ['visitors', 'page_views', 'product_views', 'searches'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo 'Invalid export type.';
    exit;
}

$filename = 'analytics_' . $type . '_' . $startDate . '_to_' . $endDate . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'visitors':
        fputcsv($output, ['First Visit', 'Last Activity', 'Landing Page', 'Referrer', 'Traffic Source', 'Device', 'Browser', 'OS', 'Country', 'Region', 'City']);
        foreach ($analytics->exportVisitors($startDate, $endDate) as $row) {
            fputcsv($output, $row);
        }
        break;

    case 'page_views':
        fputcsv($output, ['Viewed At', 'Page URL', 'Page Title', 'Referrer', 'Device', 'Browser', 'OS', 'Country', 'City']);
        foreach ($analytics->exportPageViews($startDate, $endDate) as $row) {
            fputcsv($output, $row);
        }
        break;

    case 'product_views':
        fputcsv($output, ['Viewed At', 'Product ID', 'Product Name', 'From Search', 'Device', 'Browser', 'Country', 'City']);
        foreach ($analytics->exportProductViews($startDate, $endDate) as $row) {
            fputcsv($output, $row);
        }
        break;

    case 'searches':
        fputcsv($output, ['Searched At', 'Query', 'Result Count', 'Device', 'Browser', 'Country', 'City']);
        foreach ($analytics->exportSearches($startDate, $endDate) as $row) {
            fputcsv($output, $row);
        }
        break;
}

fclose($output);
