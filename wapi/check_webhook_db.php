<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $logs = $db->fetchAll("SELECT id, event_type, status, created_at FROM webhook_logs ORDER BY id DESC LIMIT 5");
    echo json_encode($logs, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
