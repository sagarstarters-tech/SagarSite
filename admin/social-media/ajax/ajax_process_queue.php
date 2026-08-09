<?php
declare(strict_types=1);
header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/social-media/services/QueueProcessor.php';
require_once BASE_PATH . '/admin/social-media/services/ScheduleRunner.php';

AuthMiddleware::check($conn);

try {
    // 1. Process active posting schedules to generate due items into sm_queue
    $scheduleRunner = new \Admin\SocialMedia\Services\ScheduleRunner();
    $schedSummary = $scheduleRunner->processActiveSchedules();

    // 2. Process batch of due queue items
    $processor = new \Admin\SocialMedia\Services\QueueProcessor();
    $results = $processor->processBatch(10);

    $processed = count($results);
    $succeeded = 0;
    $failed = 0;

    foreach ($results as $res) {
        if (!empty($res['success'])) {
            $succeeded++;
        } else {
            $failed++;
        }
    }

    echo json_encode([
        'success' => true,
        'schedules_run' => $schedSummary['schedules_run'] ?? 0,
        'posts_queued' => $schedSummary['posts_queued'] ?? 0,
        'processed' => $processed,
        'succeeded' => $succeeded,
        'failed' => $failed
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
