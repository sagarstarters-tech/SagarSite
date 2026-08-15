<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/social-media/services/ScheduleRunner.php';
require_once BASE_PATH . '/admin/social-media/services/QueueProcessor.php';

header('Content-Type: application/json');

$pdo = DbConnection::getInstance();
$now = date('Y-m-d H:i:s');

// 1. Throttling check: allow background execution at most once every 45 seconds
$lockFile = sys_get_temp_dir() . '/sm_queue_public_last_run.txt';
$lastRun = 0;
if (file_exists($lockFile)) {
    $lastRun = (int)@file_get_contents($lockFile);
}

if ((time() - $lastRun) < 45) {
    echo json_encode(['status' => 'skipped', 'reason' => 'throttled']);
    exit;
}

// 2. Check if any schedules or queue items are actually due before heavy lifting
try {
    $dueSchedules = (int)$pdo->query("SELECT COUNT(*) FROM sm_schedules WHERE is_active = 1 AND (next_run_at IS NULL OR next_run_at <= '$now')")->fetchColumn();
    $dueQueue = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE (status IN ('scheduled', 'retry') OR (status = 'publishing' AND (updated_at <= NOW() - INTERVAL 2 MINUTE OR updated_at IS NULL))) AND (scheduled_at <= '$now' OR scheduled_at IS NULL)")->fetchColumn();

    if ($dueSchedules === 0 && $dueQueue === 0) {
        @file_put_contents($lockFile, (string)time());
        echo json_encode(['status' => 'skipped', 'reason' => 'nothing_due']);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Update lock file timestamp to prevent concurrent execution
@file_put_contents($lockFile, (string)time());

try {
    $schedSummary = ['schedules_run' => 0, 'posts_queued' => 0];

    // 3. Process active schedules (independent — don't let errors block queue processing)
    try {
        $scheduleRunner = new \Admin\SocialMedia\Services\ScheduleRunner();
        $schedSummary = $scheduleRunner->processActiveSchedules();
    } catch (Throwable $e) {
        error_log('Public Social Runner - ScheduleRunner error: ' . $e->getMessage());
    }

    // 4. Process due queue items (always runs, even if step 3 failed)
    $processor = new \Admin\SocialMedia\Services\QueueProcessor();
    $results = $processor->processBatch(10);

    echo json_encode([
        'status' => 'success',
        'schedules_run' => $schedSummary['schedules_run'] ?? 0,
        'posts_queued' => $schedSummary['posts_queued'] ?? 0,
        'processed' => count($results)
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
