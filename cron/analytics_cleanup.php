<?php
/**
 * ============================================================
 *  Analytics Data Retention Cleanup (Cron)
 *  Location: /cron/analytics_cleanup.php
 * ============================================================
 *  Deletes analytics data older than the configured retention
 *  period. Safe to run via cron or manually.
 *
 *  Only deletes from analytics_* tables.
 *  NEVER deletes orders, customers, or products.
 *
 *  Usage (cron):
 *    php /path/to/cron/analytics_cleanup.php
 *  Or via URL with ?run_cron=1 parameter.
 * ============================================================
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/modules/AnalyticsService.php';

$analytics = new AnalyticsService();

// Throttle: only run once per 24 hours
$lastRun = (int)$analytics->getSetting('last_cleanup_run', '0');
if ((time() - $lastRun) < 86400 && php_sapi_name() !== 'cli') {
    echo json_encode(['status' => 'skipped', 'message' => 'Already ran within last 24 hours']);
    exit;
}

$results = $analytics->cleanupOldData();

$output = [
    'status'    => 'completed',
    'retention' => $analytics->getSetting('retention_months', '12') . ' months',
    'deleted'   => $results,
    'timestamp' => date('Y-m-d H:i:s'),
];

if (php_sapi_name() === 'cli') {
    echo "Analytics Cleanup Complete\n";
    echo "Retention: " . $output['retention'] . "\n";
    foreach ($results as $table => $count) {
        echo "  {$table}: {$count} rows deleted\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode($output);
}
