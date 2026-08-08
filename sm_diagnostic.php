<?php
/**
 * Social Media System Diagnostic
 * Checks: DB tables, schedule state, queue state, adapters
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/config/DbConnection.php';


header('Content-Type: text/plain; charset=UTF-8');

$pdo = DbConnection::getInstance();

echo "=== SOCIAL MEDIA SYSTEM DIAGNOSTIC ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// 1. Tables
echo "=== TABLES ===" . PHP_EOL;
$tables = ['sm_connected_accounts','sm_schedules','sm_queue','sm_templates','sm_bulk_jobs','sm_analytics','sm_logs'];
foreach ($tables as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "  $t: OK (rows=$c)" . PHP_EOL;
    } catch (Exception $e) {
        echo "  $t: MISSING or ERROR - " . $e->getMessage() . PHP_EOL;
    }
}
echo PHP_EOL;

// 2. sm_schedules - structure check
echo "=== SM_SCHEDULES TABLE COLUMNS ===" . PHP_EOL;
try {
    $cols = $pdo->query("DESCRIBE sm_schedules")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['Field']}: {$c['Type']} " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " default=" . ($c['Default'] ?? 'NULL') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 3. Existing schedules
echo "=== EXISTING SCHEDULES ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT * FROM sm_schedules ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  No schedules found." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  ID={$r['id']} name={$r['name']} type={$r['schedule_type']} active={$r['is_active']}" . PHP_EOL;
            echo "    platform_ids={$r['platform_ids']}" . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 4. sm_queue - check status breakdown and 'publishing' stuck items
echo "=== QUEUE STATUS BREAKDOWN ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT status, COUNT(*) as c FROM sm_queue GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "  {$r['status']}: {$r['c']}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 5. Stuck 'publishing' items
echo "=== STUCK PUBLISHING ITEMS ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, product_id, platform, account_id, status, scheduled_at, updated_at, last_error FROM sm_queue WHERE status='publishing' OR status='failed' ORDER BY updated_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  None found." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  ID={$r['id']} platform={$r['platform']} status={$r['status']} updated={$r['updated_at']}" . PHP_EOL;
            echo "    last_error: " . ($r['last_error'] ?? 'NULL') . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 6. Recent sm_logs
echo "=== RECENT SM_LOGS (last 20) ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, level, message, queue_id, platform, created_at FROM sm_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  No logs found." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  [{$r['created_at']}] [{$r['level']}] queue_id={$r['queue_id']} platform={$r['platform']}" . PHP_EOL;
            echo "    Message: " . $r['message'] . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL . "=== DIAGNOSTIC COMPLETE ===" . PHP_EOL;

