<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $flows = $db->fetchAll("SELECT id, name, user_id, is_active, updated_at FROM chatbot_flows ORDER BY updated_at DESC LIMIT 5");
    echo json_encode($flows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
