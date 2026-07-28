<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $flows = $db->fetchAll("SELECT id, name, is_active FROM chatbot_flows");
    echo json_encode($flows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
