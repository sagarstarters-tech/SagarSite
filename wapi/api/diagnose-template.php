<?php
/**
 * WAPI SaaS - Template Diagnostic Tool
 * Diagnoses why template messages show "sent" but aren't delivered
 * Usage: Access via browser when logged in as admin
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

header('Content-Type: text/html; charset=utf-8');

$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);
$settings = new Settings();
$apiVersion = $settings->get('whatsapp_api_version', 'v18.0');
$apiUrl = $settings->get('whatsapp_api_url', 'https://graph.facebook.com');

// Get all approved templates
$templates = $db->fetchAll("SELECT id, name, language, status, header_type, body, buttons FROM templates WHERE user_id = ? ORDER BY name", [$userId]);

// Check recent failed messages
$failedMessages = $db->fetchAll("SELECT * FROM messages WHERE user_id = ? AND type = 'template' AND status IN ('failed', 'queued') ORDER BY created_at DESC LIMIT 10", [$userId]);
$sentMessages = $db->fetchAll("SELECT * FROM messages WHERE user_id = ? AND type = 'template' AND status = 'sent' ORDER BY created_at DESC LIMIT 10", [$userId]);

// Check webhook logs for failed statuses
$webhookFails = $db->fetchAll("SELECT * FROM webhook_logs WHERE user_id = ? AND payload LIKE '%failed%' ORDER BY created_at DESC LIMIT 10", [$userId]);

// Check message delivery statuses from webhook updates
$deliveryIssues = $db->fetchAll("SELECT id, message_id, to_number, template_name, status, error_message, created_at, sent_at, delivered_at, read_at FROM messages WHERE user_id = ? AND type = 'template' AND direction = 'outbound' ORDER BY created_at DESC LIMIT 20", [$userId]);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Template Diagnostic - WAPI</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 20px; background: #f5f5f5; }
        .card { background: #fff; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        h2 { color: #555; border-bottom: 2px solid #6C63FF; padding-bottom: 8px; }
        .status-ok { color: #22c55e; font-weight: bold; }
        .status-warn { color: #f59e0b; font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        pre { background: #1a1a2e; color: #e2e8f0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <h1>🔍 Template Message Diagnostic</h1>

    <div class="card">
        <h2>1. API Configuration</h2>
        <table>
            <tr><td><strong>API Version</strong></td><td><?= htmlspecialchars($apiVersion) ?></td>
                <td><?= version_compare(str_replace('v','',$apiVersion), '18.0', '>=') ? '<span class="status-ok">✅ OK</span>' : '<span class="status-warn">⚠️ Consider upgrading</span>' ?></td></tr>
            <tr><td><strong>API URL</strong></td><td><?= htmlspecialchars($apiUrl) ?></td>
                <td><?= strpos($apiUrl, 'graph.facebook.com') !== false ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ Wrong URL</span>' ?></td></tr>
            <tr><td><strong>Phone Number ID</strong></td><td><?= htmlspecialchars($waAccount['phone_number_id'] ?? 'NOT SET') ?></td>
                <td><?= !empty($waAccount['phone_number_id']) ? '<span class="status-ok">✅ Set</span>' : '<span class="status-error">❌ Missing</span>' ?></td></tr>
            <tr><td><strong>WABA ID</strong></td><td><?= htmlspecialchars($waAccount['waba_id'] ?? 'NOT SET') ?></td>
                <td><?= !empty($waAccount['waba_id']) ? '<span class="status-ok">✅ Set</span>' : '<span class="status-warn">⚠️ Missing</span>' ?></td></tr>
            <tr><td><strong>Access Token</strong></td><td><?= !empty($waAccount['access_token']) ? substr($waAccount['access_token'], 0, 20) . '...' : 'NOT SET' ?></td>
                <td><?= !empty($waAccount['access_token']) ? '<span class="status-ok">✅ Set (' . strlen($waAccount['access_token']) . ' chars)</span>' : '<span class="status-error">❌ Missing</span>' ?></td></tr>
            <tr><td><strong>Full API Endpoint</strong></td><td colspan="2"><code><?= htmlspecialchars($apiUrl . '/' . $apiVersion . '/' . ($waAccount['phone_number_id'] ?? '???') . '/messages') ?></code></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>2. Templates in Database (<?= count($templates) ?> total)</h2>
        <?php if (empty($templates)): ?>
            <p class="status-error">❌ No templates found! Please sync templates from Meta first.</p>
        <?php else: ?>
        <table>
            <tr><th>Name</th><th>Language</th><th>Status</th><th>Header</th><th>Has Variables</th><th>Has Buttons</th></tr>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                <td><?= htmlspecialchars($t['language']) ?></td>
                <td>
                    <?php if ($t['status'] === 'approved'): ?>
                        <span class="badge badge-green">APPROVED</span>
                    <?php elseif ($t['status'] === 'pending'): ?>
                        <span class="badge badge-yellow">PENDING</span>
                    <?php elseif ($t['status'] === 'rejected'): ?>
                        <span class="badge badge-red">REJECTED</span>
                    <?php else: ?>
                        <span class="badge badge-yellow"><?= strtoupper(htmlspecialchars($t['status'])) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($t['header_type'] ?? 'none') ?></td>
                <td><?= preg_match('/\{\{\d+\}\}/', $t['body'] ?? '') ? '✅ Yes' : '❌ No' ?></td>
                <td><?= !empty($t['buttons']) ? '✅ Yes' : '❌ No' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>3. Recent Template Messages - Delivery Status (Last 20)</h2>
        <?php if (empty($deliveryIssues)): ?>
            <p>No template messages found.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>WA Message ID</th><th>To</th><th>Template</th><th>Status</th><th>Error</th><th>Sent</th><th>Delivered</th><th>Read</th></tr>
            <?php foreach ($deliveryIssues as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><small><?= htmlspecialchars($m['message_id'] ?? '-') ?></small></td>
                <td><?= htmlspecialchars($m['to_number']) ?></td>
                <td><?= htmlspecialchars($m['template_name'] ?? '-') ?></td>
                <td>
                    <?php
                    $statusBadge = match($m['status']) {
                        'sent' => 'badge-blue',
                        'delivered' => 'badge-green',
                        'read' => 'badge-green',
                        'failed' => 'badge-red',
                        'queued' => 'badge-yellow',
                        default => 'badge-yellow'
                    };
                    ?>
                    <span class="badge <?= $statusBadge ?>"><?= strtoupper($m['status']) ?></span>
                </td>
                <td><small><?= htmlspecialchars($m['error_message'] ?? '-') ?></small></td>
                <td><small><?= $m['sent_at'] ?? '-' ?></small></td>
                <td><small><?= $m['delivered_at'] ?? '<span class="status-warn">❌ Not delivered</span>' ?></small></td>
                <td><small><?= $m['read_at'] ?? '-' ?></small></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p><strong>Key insight:</strong> If status shows "sent" but "Delivered" column shows ❌, it means Meta accepted the API call but did NOT deliver the message to the customer's phone.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>4. Webhook Failure Logs (Last 10)</h2>
        <?php if (empty($webhookFails)): ?>
            <p>No webhook failure logs found.</p>
        <?php else: ?>
        <?php foreach ($webhookFails as $wh): ?>
            <div style="margin-bottom: 10px;">
                <small class="text-muted"><?= $wh['created_at'] ?></small>
                <pre><?= htmlspecialchars(json_encode(json_decode($wh['payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>5. Log Files Check</h2>
        <?php
        $logFiles = [
            'api_payload.log' => APP_ROOT . '/logs/api_payload.log',
            'api_response.log' => APP_ROOT . '/logs/api_response.log', 
            'whatsapp_api.log' => APP_ROOT . '/logs/whatsapp_api.log',
            'error.log' => APP_ROOT . '/logs/error.log',
        ];
        ?>
        <table>
            <tr><th>Log File</th><th>Exists</th><th>Size</th><th>Last Modified</th><th>Last 3 Lines</th></tr>
            <?php foreach ($logFiles as $name => $path): ?>
            <tr>
                <td><strong><?= $name ?></strong></td>
                <td><?= file_exists($path) ? '<span class="status-ok">✅</span>' : '<span class="status-error">❌</span>' ?></td>
                <td><?= file_exists($path) ? number_format(filesize($path)) . ' bytes' : '-' ?></td>
                <td><?= file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : '-' ?></td>
                <td><small><?php
                    if (file_exists($path)) {
                        $lines = array_slice(file($path), -3);
                        echo htmlspecialchars(implode('', $lines));
                    } else {
                        echo 'File not found';
                    }
                ?></small></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p><strong>APP_ROOT:</strong> <code><?= APP_ROOT ?></code></p>
    </div>

    <div class="card">
        <h2>6. Common Reasons Templates Are "Sent" But Not Received</h2>
        <ol>
            <li><strong>Template is PAUSED by Meta</strong> — Check Meta Business Manager → WhatsApp Manager → Message Templates. If quality rating dropped, Meta pauses the template.</li>
            <li><strong>Business not fully verified</strong> — Unverified businesses have lower messaging limits and can only message numbers that have messaged you in the last 24 hours.</li>
            <li><strong>24-hour conversation window</strong> — For marketing templates, Meta may restrict delivery if there's no active conversation window with the customer.</li>
            <li><strong>Template language mismatch</strong> — Template language code in your system must exactly match what's registered in Meta (e.g., <code>en_US</code> vs <code>en</code>).</li>
            <li><strong>Phone number quality</strong> — If your phone number's quality rating is "Low", Meta throttles message delivery.</li>
            <li><strong>Customer blocked you</strong> — If the customer has blocked your business number.</li>
            <li><strong>Incorrect phone format</strong> — Phone must be in international format without + (e.g., <code>919876543210</code>).</li>
            <li><strong>Access token expired</strong> — Meta access tokens expire. Generate a permanent System User token.</li>
        </ol>
    </div>

    <div class="card" style="background: #fef3c7; border: 1px solid #f59e0b;">
        <h2>⚡ Quick Action: Test Send a Template Now</h2>
        <p>After deploying this update, send a template message from the <a href="<?= baseUrl('dashboard/messages.php') ?>">Send Message</a> page. Then come back here and check:</p>
        <ol>
            <li>Section 3 (Delivery Status) — see if the message status changes from "sent" to "delivered"</li>
            <li>Section 5 (Log Files) — check <code>api_response.log</code> and <code>whatsapp_api.log</code> for the full Meta response</li>
        </ol>
        <p><strong>The logs will now show you EXACTLY what Meta's API returned, which will reveal the root cause.</strong></p>
    </div>
</body>
</html>
