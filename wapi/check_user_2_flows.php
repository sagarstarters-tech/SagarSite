<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
$flows = $db->fetchAll("SELECT id, name, user_id, is_active FROM chatbot_flows WHERE user_id = 2");
echo json_encode($flows, JSON_PRETTY_PRINT);
