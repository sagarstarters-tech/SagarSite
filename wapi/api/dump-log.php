<?php
/**
 * WAPI SaaS - Webhook Raw Payload Dumper
 * Dumps the full raw payload for a specific webhook log ID
 * Usage: Access via browser: /api/dump-log.php?id=XXXX
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die("Error: Please provide a valid log ID, e.g., ?id=4156");
}

$log = $db->fetch("SELECT * FROM webhook_logs WHERE id = ?", [$id]);

if (!$log) {
    die("Error: Webhook log not found with ID $id.");
}

$payload = json_decode($log['payload'], true);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
