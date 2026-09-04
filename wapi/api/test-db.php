<?php
require_once dirname(__DIR__) . '/config/config.php';
$db = Database::getInstance();
$res = $db->fetchAll("SELECT to_number, type, error_message, content, template_name, media_url, created_at FROM messages WHERE status = 'failed' AND type = 'template' ORDER BY id DESC LIMIT 20");
echo json_encode($res, JSON_PRETTY_PRINT);
