<?php
/**
 * WAPI SaaS - WhatsApp Webhook (V2 Full Integration)
 * Processes incoming messages and routes them through the visual flow engine.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 1. Webhook Verification (GET method)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? null;
    $token = $_GET['hub_verify_token'] ?? null;
    $challenge = $_GET['hub_challenge'] ?? null;

    if ($mode === 'subscribe' && $token === WEBHOOK_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
        die();
    } else {
        http_response_code(403);
        die('Forbidden');
    }
}

// 2. Incoming Messages Handler (POST method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        die();
    }

    $entry = $data['entry'][0]['changes'][0]['value'] ?? [];
    $messages = $entry['messages'] ?? [];
    $phoneNumberId = $entry['metadata']['phone_number_id'] ?? '';

    // Find User / Account associated with this phone number
    $db = Database::getInstance();
    $account = $db->fetch("SELECT user_id, access_token FROM whatsapp_accounts WHERE phone_number_id = ? AND status = 'active'", [$phoneNumberId]);
    
    if (!$account) {
        error_log("No active WhatsApp account found for ID: " . $phoneNumberId);
        die();
    }
    
    $userId = $account['user_id'];
    $accessToken = $account['access_token'];

    if (!empty($messages)) {
        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $type = $msg['type'] ?? 'text';

            // Handle Interactive Replied (Flow Buttons)
            if ($type === 'interactive' && isset($msg['interactive']['button_reply'])) {
                $replyId = $msg['interactive']['button_reply']['id'] ?? '';
                error_log("[WEBHOOK] BUTTON CLICKED: '$replyId'");
                
                if (strpos($replyId, 'flow_btn_') === 0) {
                    $lastUnderscore = strrpos($replyId, '_');
                    $portIndex = (int)substr($replyId, $lastUnderscore + 1);
                    $nodeIdPart = substr($replyId, strlen('flow_btn_'), $lastUnderscore - strlen('flow_btn_'));
                    $flowNodeId = $nodeIdPart;
                    
                    error_log("[WEBHOOK] Parsed -> nodeId='$flowNodeId', portIndex=$portIndex");

                    // Find the user's active flow
                    $flow = $db->fetch("SELECT id, flow_json FROM chatbot_flows WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
                    if (!$flow) {
                        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: No flow found for user: $userId\n", FILE_APPEND);
                        continue;
                    }

                    $flowData = json_decode($flow['flow_json'], true);
                    $nodes = $flowData['drawflow']['Home']['data'] ?? $flowData['drawflow']['home']['data'] ?? [];

                    // Debug: log all available node IDs
                    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Available node IDs: " . implode(',', array_keys($nodes)) . "\n", FILE_APPEND);

                    if (!isset($nodes[$flowNodeId])) {
                        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: Node '$flowNodeId' not found in flow!\n", FILE_APPEND);
                        continue;
                    }

                    // Drawflow card/interactive nodes have outputs:
                    //   output_1 = Button 0 (btn-0)
                    //   output_2 = Button 1 (btn-1)
                    //   output_3 = Button 2 (btn-2)
                    // So: portIndex 0->output_1, 1->output_2, 2->output_3
                    $outputName = 'output_' . ($portIndex + 1);
                    error_log("[WEBHOOK] PortIndex $portIndex maps to OutputName $outputName (Node $flowNodeId)");
                    $nodeOutputs = $nodes[$flowNodeId]['outputs'] ?? [];

                    // Debug: log all output ports for this node
                    $outputDebug = [];
                    foreach ($nodeOutputs as $opKey => $opVal) {
                        $cCount = count($opVal['connections'] ?? []);
                        $outputDebug[] = "$opKey($cCount conn)";
                    }
                    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Node $flowNodeId outputs: " . implode(', ', $outputDebug) . "\n", FILE_APPEND);
                    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Targeting port: $outputName\n", FILE_APPEND);
                    
                    $connections = $nodeOutputs[$outputName]['connections'] ?? [];
                    
                    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Connections on $outputName: " . count($connections) . "\n", FILE_APPEND);

                    $logFile = dirname(__DIR__) . '/api/webhook_debug.txt';
                    if (!empty($connections)) {
                        $nextNodeId = $connections[0]['node'];
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] (ENGINE) SUCCESS: Routing to next node: $nextNodeId\n", FILE_APPEND);
                        runFlow($from, $userId, $flow['id'], $nextNodeId, $phoneNumberId, $accessToken);
                    } else {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] (ENGINE) ERROR: No connections on $outputName for node $flowNodeId. Available ports: " . implode(', ', array_keys($nodeOutputs)) . "\n", FILE_APPEND);
                    }
                }
            } 
            // 2. Handle All Other Incoming Messages (Text + Non-Text)
            else {
                $textBody = '';
                if ($type === 'text') {
                    $textBody = strtolower(trim($msg['text']['body'] ?? ''));
                } elseif ($type === 'image') {
                    $textBody = strtolower(trim($msg['image']['caption'] ?? ''));
                } elseif ($type === 'video') {
                    $textBody = strtolower(trim($msg['video']['caption'] ?? ''));
                } elseif ($type === 'document') {
                    $textBody = strtolower(trim($msg['document']['caption'] ?? ''));
                }
                
                file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Message type: '$type', text: '$textBody'\n", FILE_APPEND);

                    // Find User's active flow
                    $flow = $db->fetch("SELECT id, flow_json FROM chatbot_flows WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
                    if ($flow) {
                        $flowData = json_decode($flow['flow_json'], true);
                        $nodes = $flowData['drawflow']['Home']['data'] ?? $flowData['drawflow']['home']['data'] ?? [];
                    $isTrigger = false; $startNodeId = null;
                    
                    // Keyword matching only if we have text content
                    if (!empty($textBody)) {
                        foreach ($nodes as $nId => $nData) {
                            if ($nData['name'] === 'start') {
                                $keywords = strtolower($nData['data']['keywords'] ?? '');
                                    if (empty($keywords)) {
                                        if (in_array($textBody, ['hi', 'hello', 'start', 'menu', 'hey', 'demo'])) {
                                            $isTrigger = true; $startNodeId = $nId; break;
                                        }
                                    } else {
                                        $matchType = $nData['data']['match'] ?? 'exact';
                                        $keywordArr = array_map('trim', explode(',', $keywords));
                                        $keywordArr = array_map('strtolower', $keywordArr);
                                        
                                        if ($matchType === 'contains') {
                                            foreach ($keywordArr as $kw) {
                                                if (strpos($textBody, $kw) !== false) {
                                                    $isTrigger = true; $startNodeId = $nId; break 2;
                                                }
                                            }
                                        } else {
                                            if (in_array($textBody, $keywordArr)) {
                                                $isTrigger = true; $startNodeId = $nId; break;
                                            }
                                        }
                                    }
                                }
                        }
                    }

                    if ($isTrigger) {
                        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Trigger matched node: $startNodeId\n", FILE_APPEND);
                        $startNodeData = $nodes[$startNodeId] ?? null;
                        $startConns    = $startNodeData['outputs']['output_1']['connections'] ?? [];
                        if (!empty($startConns)) {
                            $firstNodeId = $startConns[0]['node'];
                            runFlow($from, $userId, $flow['id'], $firstNodeId, $phoneNumberId, $accessToken);
                        } else {
                            runFlow($from, $userId, $flow['id'], null, $phoneNumberId, $accessToken);
                        }
                        $session = getSession($from, $userId);
                        if ($session && ($session['state'] ?? '') === 'active' && ($session['flow_id'] ?? 0) == $flow['id']) {
                            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Unmatched message received during active session at node: " . ($session['current_node_id'] ?? 'null') . ". Logging only.\n", FILE_APPEND);
                        } else {
                            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] No trigger match and no active session for type '$type' with text '$textBody'\n", FILE_APPEND);
                        }
                    }
                }
            }
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);
}
?>
