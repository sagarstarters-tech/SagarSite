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
        
        // Past carts for user
        echo "=== PAST CARTS FOR CART 91 & 93 ===" . PHP_EOL;
        $resPast = $conn->query("SELECT ac.id, ac.user_id, ac.status, ac.created_at, ac.reminder_1_sent, u.name, u.phone 
                                 FROM abandoned_carts ac 
                                 LEFT JOIN users u ON ac.user_id = u.id 
                                 WHERE ac.id IN (91, 92, 93) OR u.phone LIKE '%8808714918%'
                                 ORDER BY ac.id DESC");
        if ($resPast) {
            while ($p = $resPast->fetch_assoc()) {
                echo "  Cart #{$p['id']} (User: {$p['name']}, Phone: {$p['phone']}, Status: {$p['status']}, Created: {$p['created_at']}, R1: {$p['reminder_1_sent']})" . PHP_EOL;
            }
        }
        echo PHP_EOL;
    
        // Calculate eligibility
        $delays = [
            1 => intval($settings['reminder_1_delay'] ?? 30),
            2 => intval($settings['reminder_2_delay'] ?? 360),
            3 => intval($settings['reminder_3_delay'] ?? 1440),
            4 => intval($settings['reminder_4_delay'] ?? 4320)
        ];
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
$ws = $conn->query("SELECT is_enabled, sending_mode, phone_number_id, sender_number, waba_id, api_token, LENGTH(api_token) as token_len FROM whatsapp_settings LIMIT 1");
if ($ws && $ws->num_rows > 0) {
    $ws = $ws->fetch_assoc();
    echo "  is_enabled: " . $ws['is_enabled'] . PHP_EOL;
    echo "  sending_mode: " . $ws['sending_mode'] . PHP_EOL;
    echo "  phone_number_id: " . ($ws['phone_number_id'] ?: '(empty)') . PHP_EOL;
    echo "  sender_number: " . ($ws['sender_number'] ?: '(empty)') . PHP_EOL;
    echo "  waba_id: " . ($ws['waba_id'] ?: '(empty)') . PHP_EOL;
    echo "  api_token length: " . $ws['token_len'] . PHP_EOL;

    // Check live phone number info from Meta
    $token = trim($ws['api_token'] ?? '');
    $phone_id = trim($ws['phone_number_id'] ?? '');
    $waba_id = trim($ws['waba_id'] ?? '');
    if (!empty($token) && !empty($phone_id)) {
        $ch = curl_init("https://graph.facebook.com/v21.0/{$phone_id}?fields=id,display_phone_number,verified_name,code_verification_status,quality_rating,name_status");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $m_res = curl_exec($ch);
        $m_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "  Meta Phone Lookup (HTTP {$m_code}): " . $m_res . PHP_EOL;
    }

    // Query WABA templates
    if (!empty($token) && !empty($waba_id)) {
        $chT = curl_init("https://graph.facebook.com/v21.0/{$waba_id}/message_templates?fields=name,status,category,language,components&limit=100");
        curl_setopt_array($chT, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $t_res = curl_exec($chT);
        $t_code = curl_getinfo($chT, CURLINFO_HTTP_CODE);
        curl_close($chT);
        $t_json = json_decode($t_res, true);
        echo PHP_EOL . "=== EXACT JSON FOR CART/REMINDER TEMPLATES ===" . PHP_EOL;
        if (!empty($t_json['data'])) {
            foreach ($t_json['data'] as $tpl) {
                if (strpos($tpl['name'], 'reminder') !== false || strpos($tpl['name'], 'cart') !== false) {
                    echo json_encode($tpl, JSON_PRETTY_PRINT) . PHP_EOL;
                }
            }
        }


        if (!empty($t_json['data'])) {
            foreach ($t_json['data'] as $tpl) {
                echo "  Template: {$tpl['name']} | Lang: {$tpl['language']} | Status: {$tpl['status']} | Category: {$tpl['category']}" . PHP_EOL;
                if (!empty($tpl['components'])) {
                    foreach ($tpl['components'] as $cmp) {
                        $ctype = $cmp['type'] ?? '';
                        $cformat = $cmp['format'] ?? '';
                        $ctext = str_replace(["\r\n", "\r"], "\n", $cmp['text'] ?? '');
                        echo "    [{$ctype}" . ($cformat ? ":$cformat" : "") . "] " . PHP_EOL . "      " . str_replace("\n", "\n      ", $ctext) . PHP_EOL;
                        if (!empty($cmp['buttons'])) {
                            foreach ($cmp['buttons'] as $b) {
                                echo "      Button: " . ($b['type'] ?? '') . " | " . ($b['text'] ?? '') . " | " . ($b['url'] ?? '') . PHP_EOL;
                            }
                        }
                    }
                }

            }
        } else {
            echo "  Templates Response: " . substr($t_res, 0, 300) . PHP_EOL;
        }
    }
} else {
    echo "  No WhatsApp settings found!" . PHP_EOL;
}
echo PHP_EOL;

// Check general whatsapp_logs (orders etc.)
echo "=== RECENT WHATSAPP_LOGS (ORDERS/GLOBAL) ===" . PHP_EOL;
try {
    $resWl = $conn->query("SELECT * FROM whatsapp_logs ORDER BY id DESC LIMIT 5");
    if ($resWl && $resWl->num_rows > 0) {
        while ($wl = $resWl->fetch_assoc()) {
            echo "  [{$wl['sent_at']}] Order#{$wl['order_id']} Phone:{$wl['customer_number']} Mode:{$wl['sending_mode']} Status:{$wl['status']}" . PHP_EOL;
            echo "    Msg: " . substr($wl['message'], 0, 80) . PHP_EOL;
        }
    } else {
        echo "  (No rows in whatsapp_logs)" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "  Error querying whatsapp_logs: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;


// Check recent wa logs
echo "=== RECENT ABANDONED CART WA LOGS ===" . PHP_EOL;
try {
    $resLogs = $conn->query("SELECT * FROM abandoned_cart_wa_logs ORDER BY id DESC LIMIT 10");
    if ($resLogs && $resLogs->num_rows > 0) {
        while ($l = $resLogs->fetch_assoc()) {
            echo "  [{$l['created_at']}] Cart#{$l['cart_id']} Phone:{$l['customer_number']} Status: {$l['status']}" . PHP_EOL;
        }
    } else {
        echo "  (No logs in abandoned_cart_wa_logs)" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "  Error querying wa logs: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;


// Check logs directory
echo "=== RECENT CART_ABANDONMENT_WHATSAPP.LOG ===" . PHP_EOL;
$cLog = __DIR__ . '/logs/cart_abandonment_whatsapp.log';
if (file_exists($cLog)) {
    $fp = fopen($cLog, 'r');
    fseek($fp, -2500, SEEK_END);
    $tail = fread($fp, 2500);
    fclose($fp);
    echo $tail . PHP_EOL;
} else {
    echo "  Log file does not exist." . PHP_EOL;
}
echo PHP_EOL;


// Check debug_token from Meta
if (!empty($token)) {
    $chD = curl_init("https://graph.facebook.com/debug_token?input_token=" . urlencode($token) . "&access_token=" . urlencode($token));
    curl_setopt_array($chD, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $d_res = curl_exec($chD);
    $d_code = curl_getinfo($chD, CURLINFO_HTTP_CODE);
    curl_close($chD);
    echo "=== META DEBUG_TOKEN (HTTP {$d_code}) ===" . PHP_EOL;
    echo "  " . $d_res . PHP_EOL;
}
echo PHP_EOL;


// Force run if requested
if (isset($_GET['run']) && $_GET['run'] === '1') {
    echo "=== FORCE RUNNING REMINDERS ===" . PHP_EOL;
    $conn->query("UPDATE abandoned_cart_settings SET setting_value = '0' WHERE setting_key = 'last_auto_run'");
    $result = $service->processAutoReminders();
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} elseif (isset($_GET['test_cart'])) {
    $cId = intval($_GET['test_cart']);
    $tPhone = $_GET['test_phone'] ?? '';
    $tLevel = intval($_GET['level'] ?? 1);
    echo "=== TESTING REMINDER SEND FOR CART #{$cId} (Level {$tLevel}, Phone: {$tPhone}) ===" . PHP_EOL;
    $result = $service->sendReminder($cId, $tLevel, true, $tPhone);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo "Add ?run=1 to URL to force-run reminders, or ?test_cart=93[&test_phone=91...&level=1] to test send" . PHP_EOL;
}


