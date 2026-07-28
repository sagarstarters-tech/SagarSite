<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $users = $db->fetchAll("SELECT id, name, last_login FROM users ORDER BY last_login DESC");
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
