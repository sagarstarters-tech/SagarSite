<?php
require_once __DIR__ . '/../../config/config.php';
$id = 2; // Demo_bot
$db = Database::getInstance();
$flow = $db->fetch("SELECT * FROM chatbot_flows WHERE id = ?", [$id]);
echo json_encode($flow, JSON_PRETTY_PRINT);
