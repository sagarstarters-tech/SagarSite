<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $accounts = $db->fetchAll("SELECT user_id, phone_number_id, status FROM whatsapp_accounts");
    echo json_encode($accounts, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
