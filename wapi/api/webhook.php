<?php
/**
 * WAPI SaaS - WhatsApp Webhook Handler
 * Processes incoming messages, logs them, and triggers chatbot engine.
 */
require_once __DIR__ . '/../config/config.php';

// AT THE VERY TOP: DEBUG LOG
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/webhook_raw.log', "[" . date('Y-m-d H:i:s') . "] RAW PAYLOAD: " . $rawInput . "\n", FILE_APPEND);

header('Content-Type: application/json');

// -------------------------------------------------------
// Webhook verification (GET request from Meta)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = $_GET['hub_verify_token'] ?? '';
    $challenge   = $_GET['hub_challenge'] ?? '';
    $mode        = $_GET['hub_mode'] ?? '';

    $settings = new Settings();
    $expectedToken = $settings->get('webhook_verify_token', WEBHOOK_VERIFY_TOKEN);

    if ($mode === 'subscribe' && $verifyToken === $expectedToken) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Verification failed']);
    }
    exit;
}

// -------------------------------------------------------
// Process incoming webhook (POST request)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input   = $rawInput; // Reuse the raw input already read on line 9 (php://input can only be read once)
    $payload = json_decode($input, true);

    file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] PAYLOAD RECEIVED: " . substr($input, 0, 500) . "\n", FILE_APPEND);

    if (!$payload) {
        http_response_code(200); // Always 200 to Meta
        echo json_encode(['status' => 'ok']);
        exit;
    }

    try {
        // 1. Log via WhatsApp class (status updates + message logging)
        $wa = new WhatsApp();
        $wa->processWebhook($payload);

        // 2. Load chatbot engine
        require_once __DIR__ . '/../chatbot-engine/config.php';
        require_once __DIR__ . '/../chatbot-engine/functions.php';

        $entry         = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $messages      = $entry['messages'] ?? [];
        $phoneNumberId = $entry['metadata']['phone_number_id'] ?? '';

        if (empty($messages) || empty($phoneNumberId)) {
            // Status update only — nothing to do for chatbot
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // 3. Find the user account for this phone number ID
        $db      = Database::getInstance();
        $account = $db->fetch(
            "SELECT user_id, access_token FROM whatsapp_accounts WHERE phone_number_id = ? AND status IN ('active', 'pending') LIMIT 1",
            [$phoneNumberId]
        );

        if (!$account) {
            file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] No active account for phone_number_id: $phoneNumberId\n", FILE_APPEND);
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        $userId      = $account['user_id'];
        $accessToken = $account['access_token'];

        // Helper: parse drawflow nodes from JSON
        $getNodes = function($json) {
            $data = json_decode($json, true);
            return $data['drawflow']['Home']['data'] 
                ?? $data['drawflow']['home']['data'] 
                ?? [];
        };

        // 4. Extract Contact Name from WhatsApp Profile (if available)
        $profileName = $entry['contacts'][0]['profile']['name'] ?? '';

        // 5. Process each incoming message
        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $type = $msg['type'] ?? 'text';

            // Make sure logs dir exists for debugging
            if (!is_dir(__DIR__ . '/../logs')) {
                @mkdir(__DIR__ . '/../logs', 0755, true);
            }

            // Auto-sync contact to database so variables work
            if (!empty($profileName)) {
                $exists = $db->fetch("SELECT id FROM contacts WHERE phone = ? AND user_id = ?", [$from, $userId]);
                if ($exists) {
                    $db->update('contacts', ['name' => $profileName], 'id = ?', [$exists['id']]);
                } else {
                    $db->insert('contacts', [
                        'user_id' => $userId,
                        'name'    => $profileName,
                        'phone'   => $from
                    ]);
                }
            }

            // -----------------------------------------------
            // AI Bot Check — Route to AI before rule-based chatbot
            // -----------------------------------------------
            try {
                $waAccountId = $db->fetchColumn("SELECT id FROM whatsapp_accounts WHERE phone_number_id = ? AND user_id = ? LIMIT 1", [$phoneNumberId, $userId]);
                $aiBot = $waAccountId ? $db->fetch("SELECT id, status FROM ai_bots WHERE whatsapp_account_id = ? AND status = 'active' LIMIT 1", [$waAccountId]) : null;

                if ($aiBot) {
                    // Extract text from message for AI processing
                    $aiMessageText = '';
                    switch ($type) {
                        case 'text':
                            $aiMessageText = $msg['text']['body'] ?? '';
                            break;
                        case 'image':
                            $aiMessageText = $msg['image']['caption'] ?? '[Image received]';
                            break;
                        case 'document':
                            $aiMessageText = $msg['document']['caption'] ?? '[Document received]';
                            break;
                        case 'interactive':
                            if (isset($msg['interactive']['button_reply'])) {
                                $aiMessageText = $msg['interactive']['button_reply']['title'] ?? '';
                                // Don't intercept chatbot flow button clicks
                                $replyId = $msg['interactive']['button_reply']['id'] ?? '';
                                if (strpos($replyId, 'flow_btn_') === 0) {
                                    $aiMessageText = ''; // Let existing chatbot handle it
                                }
                            } elseif (isset($msg['interactive']['list_reply'])) {
                                $aiMessageText = $msg['interactive']['list_reply']['title'] ?? '';
                            }
                            break;
                        default:
                            $aiMessageText = "[{$type} message received]";
                            break;
                    }

                    if (!empty($aiMessageText)) {
                        // Route to AI Orchestrator
                        $aiResult = AIOrchestrator::processMessage(
                            $aiBot['id'],
                            $from,
                            $profileName,
                            $aiMessageText,
                            $phoneNumberId,
                            $accessToken
                        );

                        file_put_contents(__DIR__ . '/../logs/ai_webhook.log',
                            "[" . date('Y-m-d H:i:s') . "] AI Bot #{$aiBot['id']} processed message from $from: " . json_encode($aiResult) . "\n",
                            FILE_APPEND
                        );

                        // Skip the rule-based chatbot engine — AI handled it
                        continue;
                    }
                }
            } catch (Exception $aiEx) {
                file_put_contents(__DIR__ . '/../logs/ai_webhook.log',
                    "[" . date('Y-m-d H:i:s') . "] AI Error for $from: " . $aiEx->getMessage() . "\n",
                    FILE_APPEND
                );
                // Fall through to rule-based chatbot on AI error
            }

            // -----------------------------------------------
            // A. Interactive button reply (chatbot flow nav)
            // -----------------------------------------------
            if ($type === 'interactive' && isset($msg['interactive']['button_reply'])) {
                $replyId = $msg['interactive']['button_reply']['id'] ?? '';
                file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('Y-m-d H:i:s') . "] RAW BUTTON CLICK: '$replyId' (type: $type)\n", FILE_APPEND);

                if (strpos($replyId, 'flow_btn_') === 0) {
                    $parts = explode('_', $replyId); // ['flow', 'btn', flowId, nodeId, portIndex]
                    
                    if (count($parts) >= 5) {
                        $flowId     = $parts[2];
                        $flowNodeId = $parts[3];
                        $portIndex  = (int)$parts[4];
                    } else {
                        // Backward compatibility or legacy format: flow_btn_{nodeId}_{portIndex}
                        $lastUnderscore = strrpos($replyId, '_');
                        $portIndex = (int)substr($replyId, $lastUnderscore + 1);
                        $flowNodeId = substr($replyId, strlen('flow_btn_'), $lastUnderscore - strlen('flow_btn_'));
                        $flowId = null; // Will fallback to latest active flow
                    }

                    $outputName = 'output_' . ($portIndex + 1);
                    file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('Y-m-d H:i:s') . "] PARSED: flowId='$flowId', nodeId='$flowNodeId', portIndex=$portIndex -> $outputName\n", FILE_APPEND);

                    if ($flowId) {
                        $flow = $db->fetch(
                            "SELECT id, flow_json FROM chatbot_flows WHERE id = ? AND is_active = 1 LIMIT 1",
                            [$flowId]
                        );
                    } else {
                        $flow = $db->fetch(
                            "SELECT id, flow_json FROM chatbot_flows WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
                            [$userId]
                        );
                    }

                    if ($flow && $flowNodeId) {
                        $nodes       = $getNodes($flow['flow_json']);
                        $nodeData    = $nodes[$flowNodeId] ?? null;
                        
                        if (!$nodeData) {
                             file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('Y-m-d H:i:s') . "] ERROR: Node $flowNodeId not found in flow " . $flow['id'] . ". NodeIDs available: " . implode(', ', array_keys($nodes)) . "\n", FILE_APPEND);
                             continue;
                        }
                        
                        $connections = $nodeData['outputs'][$outputName]['connections'] ?? [];
                        
                        if (empty($connections)) {
                             $available = implode(', ', array_keys($nodeData['outputs'] ?? []));
                             file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('Y-m-d H:i:s') . "] ERROR: No connections on $outputName for node $flowNodeId. Available ports: $available\n", FILE_APPEND);
                        } else {
                            $nextNodeId = $connections[0]['node'];
                            file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Routing to next node: $nextNodeId via $outputName\n", FILE_APPEND);
                            runFlow($from, $userId, $flow['id'], $nextNodeId, $phoneNumberId, $accessToken, $profileName);
                        }
                    }
                }
                continue;
            }

            // -----------------------------------------------
            // B. Extract text body for keyword matching
            //    Works for text messages; non-text types get empty string
            // -----------------------------------------------
            $textBody = '';
            if ($type === 'text' || $type === 'template') {
                $textBody = strtolower(trim($msg['text']['body'] ?? ''));
            } elseif ($type === 'image') {
                $textBody = strtolower(trim($msg['image']['caption'] ?? ''));
            } elseif ($type === 'video') {
                $textBody = strtolower(trim($msg['video']['caption'] ?? ''));
            } elseif ($type === 'document') {
                $textBody = strtolower(trim($msg['document']['caption'] ?? ''));
            } elseif ($type === 'button') {
                $btnText  = $msg['button']['text'] ?? $msg['button']['payload'] ?? '';
                $bodyText = $msg['text']['body'] ?? '';
                $textBody = strtolower(trim(!empty($bodyText) ? $bodyText . ' ' . $btnText : $btnText));
            } elseif ($type === 'interactive') {
                $interactive = $msg['interactive'] ?? [];
                $bodyText    = $interactive['body']['text'] ?? '';
                $replyText   = '';
                if (isset($interactive['button_reply'])) {
                    $replyText = $interactive['button_reply']['title'] ?? '';
                } elseif (isset($interactive['list_reply'])) {
                    $replyText = $interactive['list_reply']['title'] ?? '';
                }
                $textBody = strtolower(trim(!empty($bodyText) ? $bodyText . ' ' . $replyText : $replyText));
            }
            // For sticker, audio, location, contacts etc. — textBody stays empty

            file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] Message from $from: type='$type', text='$textBody'\n", FILE_APPEND);

            // --- INTERCEPT TEXT replies for active Confirm nodes ---
            if (!empty($textBody)) {
                require_once __DIR__ . '/../chatbot-engine/functions.php';
                $session = function_exists('getSession') ? getSession($from, $userId) : null;
                if ($session && ($session['state'] ?? '') === 'active' && !empty($session['current_node_id'])) {
                    $sessionFlowId = $session['flow_id'];
                    $flow = $db->fetch("SELECT id, flow_json FROM chatbot_flows WHERE id = ?", [$sessionFlowId]);
                    if ($flow) {
                        $nodes = $getNodes($flow['flow_json']);
                        $activeNode = $nodes[$session['current_node_id']] ?? null;
                        if ($activeNode && $activeNode['name'] === 'confirm') {
                            $tb = $textBody;
                            $isYes = in_array($tb, ['yes', 'y', 'haan', 's', 'confirm', 'ok']);
                            $isNo = in_array($tb, ['no', 'n', 'nahin', 'cancel', 'abort']);
                            
                            // Also check if text matches the configured labels
                            if (!$isYes && !$isNo) {
                                $lblYes = strtolower(trim($activeNode['data']['btn_yes_label'] ?? ''));
                                $lblNo = strtolower(trim($activeNode['data']['btn_no_label'] ?? ''));
                                if ($lblYes !== '' && $tb === $lblYes) $isYes = true;
                                if ($lblNo !== '' && $tb === $lblNo) $isNo = true;
                            }
                            
                            if ($isYes || $isNo) {
                                $portName = $isYes ? 'output_1' : 'output_2';
                                $connections = $activeNode['outputs'][$portName]['connections'] ?? [];
                                if (!empty($connections)) {
                                    $nextNodeId = $connections[0]['node'];
                                    file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('H:i:s') . "] SUCCESS: Text '$tb' matched $portName. Routing to next node: $nextNodeId\n", FILE_APPEND);
                                    runFlow($from, $userId, $sessionFlowId, $nextNodeId, $phoneNumberId, $accessToken, $profileName);
                                    continue; // Skip the rest of the loop for this message
                                } else {
                                    // It matched but no connections exist. Still, we processed it, so don't hit trigger keywords.
                                    file_put_contents(__DIR__ . '/webhook_debug.txt', "[" . date('H:i:s') . "] SUCCESS: Text '$tb' matched $portName but no connection found.\n", FILE_APPEND);
                                    continue;
                                }
                            }
                        }
                    }
                }
            }

            // Load ALL active flows for this user
            $activeFlows = $db->fetchAll(
                "SELECT id, flow_json FROM chatbot_flows WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC",
                [$userId]
            );

            if (empty($activeFlows)) {
                file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] No active chatbot flows found for user $userId\n", FILE_APPEND);
                continue;
            }

            $matchedFlow = null;
            $startNodeId = null;

            // 1. Check for Trigger Keywords in ALL active flows
            if (!empty($textBody)) {
                foreach ($activeFlows as $f) {
                    $nodes = $getNodes($f['flow_json']);
                    if (empty($nodes)) continue;

                    foreach ($nodes as $nId => $nData) {
                        if (($nData['name'] ?? '') !== 'start') continue;

                        $rawKeywords = strtolower(trim($nData['data']['keywords'] ?? ''));
                        
                        // Build and sanitize keyword array
                        // Strip leading/trailing non-word characters (e.g. semicolons, commas, dots)
                        // from each individual keyword after splitting on comma
                        if (!empty($rawKeywords)) {
                            $keywordArr = array_values(array_filter(
                                array_map(function($kw) {
                                    return strtolower(trim($kw));
                                }, explode(',', $rawKeywords)),
                                function($kw) { return $kw !== ''; }
                            ));
                        } else {
                            $keywordArr = ['hi', 'hello', 'start', 'menu', 'hey', 'demo', 'helo', 'hai'];
                        }

                        $matchType = $nData['data']['match'] ?? 'exact';
                        $isMatch = false;

                        if ($matchType === 'contains') {
                            foreach ($keywordArr as $kw) {
                                if ($kw !== '' && strpos($textBody, $kw) !== false) {
                                    $isMatch = true;
                                    break;
                                }
                            }
                        } else {
                            // Exact match: incoming text must equal one of the keywords exactly
                            $isMatch = in_array($textBody, $keywordArr, true);
                            if (!$isMatch) {
                                file_put_contents(__DIR__ . '/../logs/webhook_root.log',
                                    "[" . date('H:i:s') . "] EXACT MATCH MISS: textBody='{$textBody}' vs keywords=[" . implode(', ', $keywordArr) . "]\n",
                                    FILE_APPEND
                                );
                            }
                        }

                        if ($isMatch) {
                            $matchedFlow = $f;
                            $startNodeId = $nId;
                            break 2; // Found a trigger, stop searching
                        }
                    }
                }
            }

            // 2. If a trigger was found, start that specific flow
            if ($matchedFlow && $startNodeId) {
                $nodes      = $getNodes($matchedFlow['flow_json']);
                $startNode  = $nodes[$startNodeId] ?? null;
                $firstConns = $startNode['outputs']['output_1']['connections'] ?? [];

                if (!empty($firstConns)) {
                    $firstNodeId = $firstConns[0]['node'];
                    file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] Flow '{$matchedFlow['id']}' triggered by keyword. Starting at node: $firstNodeId\n", FILE_APPEND);
                    runFlow($from, $userId, $matchedFlow['id'], $firstNodeId, $phoneNumberId, $accessToken, $profileName);
                } else {
                    file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] Flow '{$matchedFlow['id']}' triggered but start node has no connections.\n", FILE_APPEND);
                    runFlow($from, $userId, $matchedFlow['id'], null, $phoneNumberId, $accessToken, $profileName);
                }
            } else {
                // 3. No keyword trigger found: Is there an existing Active Session?
                require_once __DIR__ . '/../chatbot-engine/functions.php'; // Ensure functions are available
                if (function_exists('getSession')) {
                    $session = getSession($from, $userId);
                    if ($session && ($session['state'] ?? '') === 'active' && !empty($session['current_node_id'])) {
                        $sessionFlowId = $session['flow_id'];
                        
                        // Verify if the session flow is still in our active list
                        $activeFlowIds = array_column($activeFlows, 'id');
                        if (in_array($sessionFlowId, $activeFlowIds)) {
                            file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] Unmatched message received during active session for flow '$sessionFlowId' at node: " . $session['current_node_id'] . ". Logging only.\n", FILE_APPEND);
                        } else {
                            file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] Session exists but flow '$sessionFlowId' is no longer active.\n", FILE_APPEND);
                        }
                    } else {
                        // 4. No trigger and no session: Log and do not auto-start
                        file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i:s') . "] No trigger match and no active session for type '$type' with text '$textBody'\n", FILE_APPEND);
                    }
                }
            }
        }

    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/../logs/webhook_root.log', "[" . date('H:i') . "] EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    }

    // Always return 200 to Meta to prevent retries
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Non-GET/POST
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
