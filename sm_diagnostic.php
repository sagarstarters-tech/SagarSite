<?php
/**
 * Social Media System Diagnostic
 * Checks: DB tables, schedule state, queue state, adapters
 */
define('BASE_PATH', dirname(__DIR__));
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
echo "=== STUCK PUBLISHING ITEMS (>5 min) ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, platform, status, scheduled_at, updated_at, last_error FROM sm_queue WHERE status='publishing' ORDER BY updated_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  None found." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  ID={$r['id']} platform={$r['platform']} updated={$r['updated_at']} err=" . ($r['last_error'] ?? 'none') . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 6. Recent failed items with errors
echo "=== RECENT FAILED ITEMS ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, platform, last_error, retry_count, max_retries FROM sm_queue WHERE status='failed' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  None." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  ID={$r['id']} platform={$r['platform']} retries={$r['retry_count']}/{$r['max_retries']}" . PHP_EOL;
            echo "    Error: " . ($r['last_error'] ?? 'none') . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 7. Connected accounts
echo "=== CONNECTED ACCOUNTS ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, platform, account_name, account_id, page_id, is_active, LENGTH(access_token_encrypted) as tok_len FROM sm_connected_accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  No accounts connected." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  ID={$r['id']} platform={$r['platform']} name={$r['account_name']} active={$r['is_active']} token_len={$r['tok_len']}" . PHP_EOL;
            echo "    account_id={$r['account_id']} page_id={$r['page_id']}" . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 8. sm_schedules - check if platform_ids column is JSON or TEXT
echo "=== SCHEDULE SAVE TEST ===" . PHP_EOL;
try {
    // Test what happens when we try to save
    $testPlatforms = json_encode(['facebook','linkedin']);
    $result = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='sm_schedules' AND COLUMN_NAME='platform_ids' AND TABLE_SCHEMA=DATABASE()")->fetchColumn();
    echo "  platform_ids column type: " . ($result ?: 'not found') . PHP_EOL;
    
    // Check if created_by has FK constraint
    $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='sm_schedules' AND COLUMN_NAME='created_by' AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($fk)) {
        echo "  WARN: created_by has FK constraint: " . json_encode($fk) . PHP_EOL;
    } else {
        echo "  created_by: no FK constraint (OK)" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 9. Adapters check
echo "=== ADAPTERS CHECK ===" . PHP_EOL;
$adapterDir = BASE_PATH . '/admin/social-media/adapters/';
$adapters = ['FacebookAdapter', 'LinkedInAdapter', 'InstagramAdapter', 'TwitterAdapter', 'TelegramAdapter'];
foreach ($adapters as $a) {
    $f = $adapterDir . $a . '.php';
    echo "  $a: " . (file_exists($f) ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
echo PHP_EOL;

// 10. Recent sm_logs
echo "=== RECENT SM_LOGS (last 10) ===" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT level, message, platform, created_at FROM sm_logs ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  No logs." . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo "  [{$r['level']}] {$r['created_at']} [{$r['platform']}] " . substr($r['message'],0,100) . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== DIAGNOSTIC COMPLETE ===" . PHP_EOL;
