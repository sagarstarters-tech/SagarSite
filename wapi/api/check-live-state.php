<?php
/**
 * WAPI SaaS - Live Webhook & Database Session Diagnostics
 * Usage: Access via browser: /api/check-live-state.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// Manual cleanup option
if (isset($_GET['clear_expired']) && $_GET['clear_expired'] == '1') {
    try {
        $stmt = $db->query(
            "UPDATE chatbot_sessions SET state = 'finished', updated_at = NOW() WHERE state = 'active' AND (updated_at < DATE_SUB(NOW(), INTERVAL 4 HOUR) OR current_node_id = '49')"
        );
        $count = $stmt->rowCount();
        echo "=== EXPIRED SESSIONS CLEARED: $count SESSIONS UPDATED TO FINISHED ===\n\n";
    } catch (Exception $e) {
        echo "=== CLEAR ERROR: " . $e->getMessage() . " ===\n\n";
    }
}

if (isset($_GET['clear_all']) && $_GET['clear_all'] == '1') {
    try {
        $stmt = $db->query(
            "UPDATE chatbot_sessions SET state = 'finished', updated_at = NOW() WHERE state = 'active'"
        );
        $count = $stmt->rowCount();
        echo "=== ALL ACTIVE SESSIONS FORCE CLEARED: $count SESSIONS UPDATED TO FINISHED ===\n\n";
    } catch (Exception $e) {
        echo "=== CLEAR ERROR: " . $e->getMessage() . " ===\n\n";
    }
}

echo "=== ACTIVE CHATBOT SESSIONS ===\n";
try {
    $sessions = $db->fetchAll("SELECT * FROM chatbot_sessions ORDER BY updated_at DESC LIMIT 10");
    echo json_encode($sessions, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Sessions Error: " . $e->getMessage() . "\n";
}

echo "\n=== LATEST WEBHOOK ROOT LOG LINES FOR 918573934013 ===\n";
$rootPath = __DIR__ . '/../logs/webhook_root.log';
if (file_exists($rootPath)) {
    $file = fopen($rootPath, 'r');
    $matchedLines = [];
    while (($line = fgets($file)) !== false) {
        if (strpos($line, '918573934013') !== false || strpos($line, 'Message from') !== false || strpos($line, 'Continuing session') !== false || strpos($line, 'Auto-starting') !== false) {
            $matchedLines[] = $line;
            if (count($matchedLines) > 50) {
                array_shift($matchedLines);
            }
        }
    }
    fclose($file);
    echo implode("", $matchedLines);
} else {
    echo "No root webhook log found.\n";
}

echo "\n=== LATEST MESSAGES FOR 918573934013 ===\n";
try {
    $messages = $db->fetchAll("SELECT id, to_number, type, content, status, direction, created_at FROM messages WHERE to_number = '918573934013' OR to_number = '+918573934013' ORDER BY id DESC LIMIT 20");
    echo json_encode($messages, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Messages Error: " . $e->getMessage() . "\n";
}

echo "\n=== RAW PAYLOADS OF LATEST UNSUPPORTED/UNKNOWN WEBHOOK LOGS ===\n";
try {
    $rawLogs = $db->fetchAll("SELECT id, payload, created_at FROM webhook_logs WHERE event_type = 'incoming' AND (payload LIKE '%unsupported%' OR payload LIKE '%unknown%' OR payload LIKE '%918573934013%') ORDER BY id DESC LIMIT 10");
    foreach ($rawLogs as $rLog) {
        echo "LOG ID: {$rLog['id']} | Created: {$rLog['created_at']}\n";
        echo json_encode(json_decode($rLog['payload'], true), JSON_PRETTY_PRINT) . "\n\n";
    }
} catch (Exception $e) {
    echo "Logs Error: " . $e->getMessage() . "\n";
}

echo "\n=== TARGET WEBHOOK LOG FOR MSG wamid.HBgMOTE4NTczOTM0MDEzFQIAEhggQTVEQzEyNEZCNjE4MjFBQTA1MzlBRjZBRUNCQkFBMUYA ===\n";
try {
    $targetLogs = $db->fetchAll("SELECT id, payload, created_at FROM webhook_logs WHERE payload LIKE '%QTVEQzEyNEZCNjE4MjFBQTA1MzlBRjZBRUNCQkFBMUYA%' ORDER BY id DESC");
    foreach ($targetLogs as $tLog) {
        echo "LOG ID: {$tLog['id']} | Created: {$tLog['created_at']}\n";
        echo json_encode(json_decode($tLog['payload'], true), JSON_PRETTY_PRINT) . "\n\n";
    }
} catch (Exception $e) {
    echo "Target Logs Error: " . $e->getMessage() . "\n";
}

exit;
