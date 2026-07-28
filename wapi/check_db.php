<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $columns = $db->fetchAll("DESCRIBE messages");
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
