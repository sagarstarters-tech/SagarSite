<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $msgs = $db->fetchAll("SELECT id, to_number, direction, content, created_at FROM messages ORDER BY id DESC LIMIT 10");
    echo json_encode($msgs, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
