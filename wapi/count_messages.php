<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $count = $db->fetchColumn("SELECT COUNT(*) FROM messages");
    echo "Total Messages: " . $count;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
