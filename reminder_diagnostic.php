<?php
/**
 * Cart Abandonment Reminder Diagnostic Tool
 * Run this on production to check reminder status and force-run reminders
 * URL: /scratch/reminder_diagnostic.php?key=sagar_cart_recovery_cron_secret
 */

require_once __DIR__ . '/includes/session_setup.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/AbandonedCartService.php';

// Secret key check
$secretKey = 'sagar_cart_recovery_cron_secret';
try {
    $res = $conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['setting_value'])) $secretKey = trim($row['setting_value']);
    }
} catch (\Throwable $e) {}

$key = $_GET['key'] ?? '';
if ($key !== $secretKey) {
    http_response_code(403);
    die('Access denied.');
}

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Cart Abandonment Reminder Diagnostic ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;

// MySQL time
$rt = $conn->query('SELECT NOW() as t, @@global.time_zone as tz, @@session.time_zone as stz');
if ($rt) {
    $rt = $rt->fetch_assoc();
    echo "MySQL NOW(): " . $rt['t'] . " (tz: " . $rt['tz'] . "/" . $rt['stz'] . ")" . PHP_EOL;
}
echo PHP_EOL;

// Settings
echo "=== SETTINGS ===" . PHP_EOL;
$service = new AbandonedCartService($conn);
$settings = $service->getSettings();
$keys = ['is_enabled','reminder_1_delay','reminder_2_delay','reminder_3_delay','reminder_4_delay','meta_template_1','last_auto_run'];
foreach ($keys as $k) {
    $v = $settings[$k] ?? '(not set)';
    if ($k === 'last_auto_run' && !empty($v)) {
        $v .= " (" . round((time() - intval($v)) / 60, 1) . " min ago)";
    }
    echo "  $k = $v" . PHP_EOL;
}
echo PHP_EOL;

// All active carts
echo "=== ALL ACTIVE CARTS ===" . PHP_EOL;
$res = $conn->query("SELECT ac.*, u.name as uname, u.phone as uphon 
                     FROM abandoned_carts ac 
                     LEFT JOIN users u ON ac.user_id = u.id 
                     WHERE ac.status = 'active'
                     ORDER BY ac.id DESC");
$count = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $count++;
        echo "Cart #{$row['id']}: user={$row['uname']} ({$row['uphon']})" . PHP_EOL;
        echo "  created={$row['created_at']}" . PHP_EOL;
        echo "  R1=" . ($row['reminder_1_sent'] ?: 'NOT SENT') . PHP_EOL;
        echo "  R2=" . ($row['reminder_2_sent'] ?: 'NOT SENT') . PHP_EOL;
        echo "  R3=" . ($row['reminder_3_sent'] ?: 'NOT SENT') . PHP_EOL;
        echo "  R4=" . ($row['reminder_4_sent'] ?: 'NOT SENT') . PHP_EOL;
        
        // Calculate eligibility
        $delays = [1=>30, 2=>360, 3=>1440, 4=>4320];
        foreach ($delays as $lvl => $delay) {
            $col = "reminder_{$lvl}_sent";
            if (!empty($row[$col])) {
                echo "  L$lvl: Already sent at {$row[$col]}" . PHP_EOL;
                continue;
            }
            // Check if previous was sent
            if ($lvl > 1) {
                $prevCol = "reminder_" . ($lvl-1) . "_sent";
                if (empty($row[$prevCol])) {
                    echo "  L$lvl: Waiting for L" . ($lvl-1) . " first" . PHP_EOL;
                    continue;
                }
                $baseTime = strtotime($row[$prevCol]);
            } else {
                $baseTime = strtotime($row['created_at']);
            }
            $elapsed = round((time() - $baseTime) / 60, 1);
            $ready = $elapsed >= $delay;
            echo "  L$lvl: " . ($ready ? "DUE NOW" : "Not due") . " (elapsed={$elapsed}min, need={$delay}min)" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}
if ($count === 0) echo "  No active carts found." . PHP_EOL;
echo PHP_EOL;

// WhatsApp settings
echo "=== WHATSAPP SETTINGS ===" . PHP_EOL;
$ws = $conn->query("SELECT is_enabled, sending_mode, phone_number_id, LENGTH(api_token) as token_len FROM whatsapp_settings LIMIT 1");
if ($ws && $ws->num_rows > 0) {
    $ws = $ws->fetch_assoc();
    echo "  is_enabled: " . $ws['is_enabled'] . PHP_EOL;
    echo "  sending_mode: " . $ws['sending_mode'] . PHP_EOL;
    echo "  phone_number_id: " . ($ws['phone_number_id'] ?: '(empty)') . PHP_EOL;
    echo "  api_token length: " . $ws['token_len'] . PHP_EOL;
    
    if ($ws['sending_mode'] === 'web') {
        echo "  ⚠️  WARNING: sending_mode=web — Auto reminders CANNOT be sent automatically in Web mode!" . PHP_EOL;
        echo "     Go to Admin > WhatsApp Notifs > change to API mode and add Meta API credentials." . PHP_EOL;
    }
} else {
    echo "  No WhatsApp settings found!" . PHP_EOL;
}
echo PHP_EOL;

// Force run if requested
if (isset($_GET['run']) && $_GET['run'] === '1') {
    echo "=== FORCE RUNNING REMINDERS ===" . PHP_EOL;
    // Reset last_auto_run to force execution
    $conn->query("UPDATE abandoned_cart_settings SET setting_value = '0' WHERE setting_key = 'last_auto_run'");
    $result = $service->processAutoReminders();
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo "Add ?run=1 to URL to force-run reminders" . PHP_EOL;
}
