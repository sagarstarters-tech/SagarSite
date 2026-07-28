<?php
require_once __DIR__ . '/config/config.php';
$db = Database::getInstance();
try {
    $count = $db->fetchColumn("SELECT COUNT(*) FROM whatsapp_accounts");
    echo "WhatsApp Accounts Count: " . $count . "\n";
    $count2 = $db->fetchColumn("SELECT COUNT(*) FROM messages");
    echo "Messages Count: " . $count2 . "\n";
    
    $acc = $db->fetchAll("SELECT * FROM whatsapp_accounts");
    print_r($acc);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
