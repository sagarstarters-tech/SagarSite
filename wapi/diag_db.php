<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
echo "--- DB Structure ---\n";
$tables = $db->fetchAll("SHOW TABLES");
foreach($tables as $t) {
    echo array_values($t)[0] . "\n";
}

echo "\n--- Whatsapp Accounts ---\n";
$accs = $db->fetchAll("SELECT * FROM whatsapp_accounts");
print_r($accs);

echo "\n--- Chatbot Flows ---\n";
$flows = $db->fetchAll("SELECT * FROM chatbot_flows");
print_r($flows);
?>
