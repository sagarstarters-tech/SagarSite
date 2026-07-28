<?php
/**
 * WAPI SaaS - Webhook Logs Viewer (Diagnostic - Last 100 Lines Only)
 * Usage: Access via browser: /api/view-logs.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');

function printTail($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        echo "No log file found at $filepath\n";
        return;
    }
    $file = fopen($filepath, 'r');
    $lineArr = [];
    while (($line = fgets($file)) !== false) {
        $lineArr[] = $line;
        if (count($lineArr) > $lines) {
            array_shift($lineArr);
        }
    }
    fclose($file);
    echo implode("", $lineArr);
}

echo "=== webhook_raw.log (LAST 50 LINES) ===\n";
printTail(__DIR__ . '/webhook_raw.log', 50);

echo "\n\n=== webhook_root.log (LAST 50 LINES) ===\n";
printTail(__DIR__ . '/../logs/webhook_root.log', 50);
exit;
