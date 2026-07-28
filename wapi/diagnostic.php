<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/chatbot-engine/functions.php';

echo "--- Chatbot Diagnostic ---\n";

$db = Database::getInstance();

// 1. Check Accounts
echo "\n[Accounts Check]\n";
$accounts = $db->fetchAll("SELECT user_id, phone_number_id, status FROM whatsapp_accounts");
foreach($accounts as $acc) {
    echo "User: {$acc['user_id']} | PhoneID: {$acc['phone_number_id']} | Status: {$acc['status']}\n";
}

// 2. Check Flows
echo "\n[Flows Check]\n";
$flows = $db->fetchAll("SELECT id, user_id, name FROM chatbot_flows ORDER BY id DESC");
foreach($flows as $f) {
    echo "ID: {$f['id']} | User: {$f['user_id']} | Name: {$f['name']}\n";
}

// 3. Simulate Flow for latest flow
if(!empty($flows)) {
    $f = $flows[0];
    $userId = $f['user_id'];
    $flowId = $f['id'];
    
    // Find account for this user
    $acc = $db->fetch("SELECT access_token, phone_number_id FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);
    
    if($acc) {
        echo "\n[Simulating 'hi' for Flow ID $flowId...]\n";
        // runFlow($phone, $userId, $flowId, $nodeId = null, $phoneId = null, $token = null)
        // I won't actually call sendRequest (I'll mock it if I could, but let's just see trace)
        
        $flow = $db->fetch("SELECT flow_json FROM chatbot_flows WHERE id = ?", [$flowId]);
        $data = json_decode($flow['flow_json'], true);
        $nodes = $data['drawflow']['Home']['data'] ?? [];
        
        echo "Nodes Found: " . count($nodes) . "\n";
        foreach($nodes as $id => $node) {
            echo " - Node $id: {$node['name']}\n";
        }
        
        // Check start node
        $startNodeId = null;
        foreach ($nodes as $nId => $nData) {
            $hasInputs = !empty($nData['inputs']);
            if (!$hasInputs || (isset($nData['inputs']['input_1']) && empty($nData['inputs']['input_1']['connections']))) {
                if ($nData['name'] === 'start') {
                    $startNodeId = $nId;
                    echo "Found Start Node: $startNodeId\n";
                    break;
                }
            }
        }
    } else {
        echo "No active account for user $userId\n";
    }
} else {
    echo "No flows found.\n";
}
?>
