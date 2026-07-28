<?php
/**
 * WAPI SaaS - Webhook Payload Diagnostic Tool
 * Helps debug [UNSUPPORTED message] and unknown message types
 * Usage: Access via browser when logged in
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

header('Content-Type: text/html; charset=utf-8');

// 1. Fetch latest messages that are unsupported or inbound
$unsupportedMessages = $db->fetchAll(
    "SELECT * FROM messages WHERE user_id = ? AND (type = 'unsupported' OR content LIKE '%unsupported%') ORDER BY id DESC LIMIT 20",
    [$userId]
);

// 2. Fetch latest incoming webhooks to see raw JSON payloads
$webhookLogs = $db->fetchAll(
    "SELECT id, payload, created_at FROM webhook_logs WHERE event_type = 'incoming' ORDER BY id DESC LIMIT 30"
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Webhook Diagnostic - WAPI</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 20px; background: #f5f5f5; color: #333; }
        .card { background: #fff; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #6C63FF; }
        h2 { color: #555; border-bottom: 2px solid #6C63FF; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 10px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        pre { background: #1a1a2e; color: #e2e8f0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .btn { display: inline-block; background: #6C63FF; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; font-weight: bold; margin-bottom: 15px; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; background: #1e293b; }
        .btn:hover { background: #5a52d5; }
    </style>
</head>
<body>
    <h1>🔍 Webhook & Message Payload Diagnostic</h1>
    <p>Use this tool to find out exactly what Meta sent when you received an <strong>[UNSUPPORTED message]</strong>.</p>
    
    <a href="<?= baseUrl('dashboard/live-chat.php') ?>" class="btn">← Back to Live Chat</a>

    <div class="card">
        <h2>1. Saved Unsupported Messages in Database</h2>
        <?php if (empty($unsupportedMessages)): ?>
            <p style="color: #666; font-style: italic;">No unsupported messages saved in the database for your user account yet.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>To / From</th><th>Type</th><th>Content</th><th>Direction</th><th>Status</th><th>Created At</th></tr>
            <?php foreach ($unsupportedMessages as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><?= htmlspecialchars($m['to_number']) ?></td>
                <td><span class="badge badge-red"><?= strtoupper(htmlspecialchars($m['type'])) ?></span></td>
                <td><code><?= htmlspecialchars($m['content']) ?></code></td>
                <td><?= htmlspecialchars($m['direction']) ?></td>
                <td><?= htmlspecialchars($m['status']) ?></td>
                <td><?= htmlspecialchars($m['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>2. Raw Webhook Logs (Last 30 Received)</h2>
        <p>Look through these payloads to find the one matching the phone number <strong>447974905007</strong> or matching the time you received the unsupported message.</p>
        <?php if (empty($webhookLogs)): ?>
            <p style="color: #ef4444; font-weight: bold;">❌ No webhook logs found in the database. Make sure your webhook is receiving events.</p>
        <?php else: ?>
            <?php foreach ($webhookLogs as $log): ?>
                <?php 
                $payload = json_decode($log['payload'], true);
                $isInteresting = false;
                // Highlight payloads containing our target phone or "unsupported"
                $payloadStr = json_encode($payload);
                if (strpos($payloadStr, '447974905007') !== false || strpos($payloadStr, 'unsupported') !== false || strpos($payloadStr, 'button') !== false || strpos($payloadStr, 'interactive') !== false) {
                    $isInteresting = true;
                }
                ?>
                <div style="border: 1px solid <?= $isInteresting ? '#6C63FF' : '#ddd' ?>; border-radius: 6px; padding: 12px; margin-bottom: 15px; background: <?= $isInteresting ? '#f3f2ff' : '#fff' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <strong>Log ID: <?= $log['id'] ?></strong>
                            <a href="<?= baseUrl('api/dump-log.php?id=' . $log['id']) ?>" target="_blank" class="btn btn-sm" style="margin-left: 10px;">View Full Raw JSON</a>
                        </div>
                        <span style="font-size: 0.8rem; color: #666;"><?= htmlspecialchars($log['created_at']) ?></span>
                        <?php if ($isInteresting): ?>
                            <span class="badge badge-blue">🎯 Matches Phone/Keyword</span>
                        <?php endif; ?>
                    </div>
                    <pre><?= htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
