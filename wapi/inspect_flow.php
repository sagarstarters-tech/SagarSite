<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $flow = $db->fetch("SELECT flow_json FROM chatbot_flows WHERE id = 2");
    echo $flow['flow_json'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
