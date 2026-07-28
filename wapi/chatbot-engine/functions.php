<?php
/**
 * WAPI SaaS - WhatsApp Chatbot Core Functions (V2 Refactored)
 * Dynamic Flow Engine and Messaging Helpers.
 */

require_once __DIR__ . '/config.php';

/**
 * 1. Meta API Request Helper (using cURL)
 */
function sendRequest($payload, $phoneId = null, $token = null) {
    if (!$payload) return false;

    // Use parameters or fall back to constants
    $targetPhoneId = $phoneId ?? (defined('PHONE_NUMBER_ID') ? PHONE_NUMBER_ID : '');
    $targetToken = $token ?? (defined('WHATSAPP_API_TOKEN') ? WHATSAPP_API_TOKEN : '');

    // Debug logging
    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Sending Payload: " . json_encode($payload) . "\n", FILE_APPEND);

    $url = "https://graph.facebook.com/" . WHATSAPP_API_VERSION . "/" . $targetPhoneId . "/messages";
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $targetToken
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] cURL Error: " . $error . "\n", FILE_APPEND);
        return false;
    }

    $result = json_decode($response, true);
    if ($httpCode >= 400) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Meta Error (HTTP $httpCode): " . json_encode($result) . "\n", FILE_APPEND);
        return false;
    }

    return $result;
}

/**
 * 2. Specialized Messaging Helpers
 */
function sendText($phone, $message, $phoneId = null, $token = null) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $message]
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendImage($phone, $imageUrl, $caption = '', $phoneId = null, $token = null) {
    if (empty($imageUrl)) return false;
    $media = ['link' => $imageUrl];
    if (trim($caption) !== '') {
        $media['caption'] = $caption;
    }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone, 'type' => 'image',
        'image' => $media
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendAudio($phone, $audioUrl, $phoneId = null, $token = null) {
    if (empty($audioUrl)) return false;
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone, 'type' => 'audio',
        'audio' => ['link' => $audioUrl]
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendVideo($phone, $videoUrl, $caption = '', $phoneId = null, $token = null) {
    if (empty($videoUrl)) return false;
    $media = ['link' => $videoUrl];
    if (trim($caption) !== '') {
        $media['caption'] = $caption;
    }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone, 'type' => 'video',
        'video' => $media
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendDocument($phone, $docUrl, $filename = '', $caption = '', $phoneId = null, $token = null) {
    if (empty($docUrl)) return false;
    $media = ['link' => $docUrl];
    if (trim($filename) !== '') {
        $media['filename'] = $filename;
    }
    if (trim($caption) !== '') {
        $media['caption'] = $caption;
    }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone, 'type' => 'document',
        'document' => $media
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendButtons($phone, $text, $buttonsData, $nodeId, $flowId, $phoneId = null, $token = null) {
    if (empty(trim($text)) || empty($buttonsData)) return false;
    $buttons = [];
    $btnCounter = 0;
    foreach ($buttonsData as $key => $label) {
        if (trim($label) === '') continue;
        if ($btnCounter >= 3) break;
        
        // Extract port index from key like 'btn-0', 'btn-1'
        $portIndex = $btnCounter;
        if (strpos($key, 'btn-') === 0) {
            $portIndex = (int)explode('-', $key)[1];
        }
        
        $btnCounter++;
        
        error_log("[ENGINE] Assigning Button: title='$label', id='flow_btn_{$flowId}_{$nodeId}_{$portIndex}'");
        $buttons[] = [
            'type' => 'reply',
            'reply' => ['id' => "flow_btn_{$flowId}_{$nodeId}_{$portIndex}", 'title' => mb_substr(trim($label), 0, 20)]
        ];
    }

    if (empty($buttons)) return false;

    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone, 'type' => 'interactive',
        'interactive' => [
            'type' => 'button', 'body' => ['text' => $text], 'action' => ['buttons' => $buttons]
        ]
    ];
    return sendRequest($payload, $phoneId, $token);
}

function sendInteractiveButtons($phone, $bodyText, $footerText, $imageUrl, $buttonsData, $nodeId, $flowId, $phoneId = null, $token = null) {
    if (empty(trim($bodyText)) || empty($buttonsData)) return false;
    $buttons = [];
    $btnCounter = 0;
    foreach ($buttonsData as $key => $label) {
        if (trim($label) === '') continue;
        if ($btnCounter >= 3) break;
        
        // Parse port index from key name if possible (e.g., 'btn1' -> 0, 'btn2' -> 1)
        if (strpos($key, 'btn') === 0) {
            $portIndex = (int)substr($key, 3) - 1;
        } else {
            $portIndex = $btnCounter;
        }
        
        $btnCounter++;
        
        error_log("[ENGINE] Assigning Button: title='$label', id='flow_btn_{$flowId}_{$nodeId}_{$portIndex}'");
        $buttons[] = [
            'type' => 'reply',
            'reply' => ['id' => "flow_btn_{$flowId}_{$nodeId}_{$portIndex}", 'title' => mb_substr(trim($label), 0, 20)]
        ];
    }

    if (empty($buttons)) return false;

    $interactive = [
        'type' => 'button', 
        'body' => ['text' => $bodyText], 
        'action' => ['buttons' => $buttons]
    ];

    if (!empty($imageUrl)) {
        $interactive['header'] = ['type' => 'image', 'image' => ['link' => $imageUrl]];
    }
    if (!empty($footerText)) {
        $interactive['footer'] = ['text' => $footerText];
    }

    $payload = [
        'messaging_product' => 'whatsapp', 
        'recipient_type' => 'individual', 
        'to' => $phone, 
        'type' => 'interactive',
        'interactive' => $interactive
    ];
    return sendRequest($payload, $phoneId, $token);
}


function sendCtaUrl($phone, $text, $btnText, $url, $imageUrl = '', $footerText = '', $phoneId = null, $token = null) {
    if (empty(trim($btnText)) || empty(trim($url))) return false;
    
    $url = trim($url);
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $url = "https://" . $url;
    }
    
    $interactive = [
        'type'   => 'cta_url',
        'body'   => ['text' => (empty(trim($text)) ? 'Click below' : $text)],
        'action' => [
            'name' => 'cta_url',
            'parameters' => [
                'display_text' => mb_substr(trim($btnText), 0, 20),
                'url'          => trim($url)
            ]
        ]
    ];

    if (!empty($imageUrl)) {
        $interactive['header'] = ['type' => 'image', 'image' => ['link' => $imageUrl]];
    }
    if (!empty($footerText)) {
        $interactive['footer'] = ['text' => $footerText];
    }

    $payload = [
        'messaging_product' => 'whatsapp', 
        'recipient_type'    => 'individual', 
        'to'                => $phone, 
        'type'              => 'interactive',
        'interactive'       => $interactive
    ];
    return sendRequest($payload, $phoneId, $token);
}



/**
 * Replace string variables like #LEAD_USER_FIRST_NAME# with actual data
 */
function replaceDynamicVariables($text, $phone, $userId, $senderName = null) {
    if (empty(trim($text))) return $text;

    $name = !empty($senderName) ? $senderName : 'User';
    $firstName = !empty($senderName) ? explode(' ', trim($senderName))[0] : 'User';
    
    // Normalize phone for searching (remove +)
    $cleanPhone = ltrim($phone, '+');
    
    $db = Database::getInstance();
    try {
        // Try exact match or match with leading +
        $contact = $db->fetch("SELECT * FROM contacts WHERE (phone = ? OR phone = ?) AND user_id = ? LIMIT 1", [$cleanPhone, '+' . $cleanPhone, $userId]);
        
        if ($contact && !empty($contact['name'])) {
            $name = $contact['name'];
            $nameParts = explode(' ', trim($name));
            $firstName = $nameParts[0];
        }
    } catch (Exception $e) {
        // Log error if needed: error_log("Variable Replacement Error: " . $e->getMessage());
    }

    $replacements = [
        '#LEAD_USER_NAME#'       => $name,
        '#LEAD_USER_FIRST_NAME#' => $firstName,
        '#NAME#'                 => $name,
        '#FIRST_NAME#'           => $firstName,
        '#LEAD_USER_MOBILE#'     => $phone,
        '#USER_WHATSAPP_NUMBER#' => $phone,
        '#PHONE#'                => $phone
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

/**
 * 3. Dynamic Flow Engine (JSON Parser)
 */
function runFlow($phone, $userId, $flowId, $nodeId = null, $phoneId = null, $token = null, $senderName = null) {
    $db = Database::getInstance();

    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] runFlow: phone=$phone, userId=$userId, flowId=$flowId, nodeId=$nodeId\n", FILE_APPEND);

    // 1. Fetch the Flow JSON
    $flow = $db->fetch("SELECT flow_json FROM chatbot_flows WHERE id = ?", [$flowId]);
    if (!$flow) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Flow not found: $flowId\n", FILE_APPEND);
        return;
    }

    $data = json_decode($flow['flow_json'], true);
    $nodes = $data['drawflow']['Home']['data'] ?? $data['drawflow']['home']['data'] ?? [];

    if (empty($nodes)) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Flow $flowId has no nodes!\n", FILE_APPEND);
        return;
    }

    // 2. Identify Current Node (if null, find a start node)
    if ($nodeId === null) {
        foreach ($nodes as $nId => $nData) {
            if ($nData['name'] === 'start') {
                $nodeId = $nId;
                break;
            }
        }
        // Fallback to first node if no explicit start found
        if ($nodeId === null && !empty($nodes)) {
            $nodeIds = array_keys($nodes);
            $nodeId = $nodeIds[0];
        }
    }

    if ($nodeId === null || !isset($nodes[$nodeId])) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Target node $nodeId not found in flow $flowId\n", FILE_APPEND);
        return;
    }

    $currentNode = $nodes[$nodeId];
    $nodeType = $currentNode['name'];
    $nodeData = $currentNode['data'];

    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Executing node $nodeId (type: $nodeType), data: " . json_encode($nodeData) . "\n", FILE_APPEND);

    // Update Session State
    setSession($phone, $userId, $flowId, $nodeId, 'active');

    // 3. Execute Node Action
    $isInteractive = false;
    
    switch ($nodeType) {
        case 'start':
            // Start node sends no message — immediately follow to the next connected node
            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Start node triggered, following connection...\n", FILE_APPEND);
            
            // Apply configured start node delay
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }

            $startConns = $currentNode['outputs']['output_1']['connections'] ?? [];
            if (!empty($startConns)) {
                $nextNodeId = $startConns[0]['node'];
                runFlow($phone, $userId, $flowId, $nextNodeId, $phoneId, $token);
            }
            return; // exit this call — the recursive call handles everything

        case 'text':
            $textMsg = replaceDynamicVariables($nodeData['text'] ?? '', $phone, $userId, $senderName);
            if (empty($textMsg)) {
                file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] WARNING: text node $nodeId has empty message!\n", FILE_APPEND);
            }
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $res = sendText($phone, $textMsg, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'text', $textMsg, $res);
            break;
            
        case 'image':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $caption = replaceDynamicVariables($nodeData['caption'] ?? '', $phone, $userId, $senderName);
            $res = sendImage($phone, $nodeData['image-url'] ?? '', $caption, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'image', 'Image', $res, $nodeData['image-url'] ?? '');
            break;
            

            
        case 'audio':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $audioUrl = $nodeData['audio-url'] ?? '';
            $res = sendAudio($phone, $audioUrl, $phoneId, $token);
            
            // Critical Meta API Fallback: If Meta rejects the audio (e.g., Unsupported Media Type for some .mp3s),
            // we will forcefully send it as an attached document to ensure delivery!
            if (!$res && !empty($audioUrl)) {
                $ext = pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
                $fallbackName = !empty($ext) ? "voice_message.$ext" : "voice_message.mp3";
                file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('H:i') . "] sendAudio failed, attempting sendDocument fallback for: $audioUrl\n", FILE_APPEND);
                $res = sendDocument($phone, $audioUrl, $fallbackName, '', $phoneId, $token);
            }
            
            logChatbotMessage($userId, $phone, 'audio', 'Audio', $res, $audioUrl);
            break;
            
        case 'video':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $caption = replaceDynamicVariables($nodeData['caption'] ?? '', $phone, $userId, $senderName);
            $res = sendVideo($phone, $nodeData['video-url'] ?? '', $caption, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'video', 'Video', $res, $nodeData['video-url'] ?? '');
            break;
            
        case 'file':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $body = replaceDynamicVariables($nodeData['body_text'] ?? '', $phone, $userId, $senderName);
            $footer = replaceDynamicVariables($nodeData['footer_text'] ?? '', $phone, $userId, $senderName);
            $caption = trim($body . (!empty($footer) ? "\n\n" . $footer : ''));
            
            $res = sendDocument($phone, $nodeData['file-url'] ?? '', $nodeData['filename'] ?? 'document', $caption, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'document', 'Document', $res, $nodeData['file-url'] ?? '');
            break;
            
        case 'cta':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $textMsg = replaceDynamicVariables($nodeData['text'] ?? '', $phone, $userId, $senderName);
            $footerMsg = replaceDynamicVariables($nodeData['footer'] ?? '', $phone, $userId, $senderName);
            $btnText = replaceDynamicVariables($nodeData['btnText'] ?? '', $phone, $userId, $senderName);
            $imageUrl = $nodeData['image'] ?? '';
            
            $res = sendCtaUrl($phone, $textMsg, $btnText, $nodeData['url'] ?? '', $imageUrl, $footerMsg, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'interactive', $textMsg, $res);
            break;



        case 'text-cta':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $buttonsData = [];
            foreach ($nodeData as $key => $val) {
                if (strpos($key, 'btn-') === 0) $buttonsData[$key] = replaceDynamicVariables($val, $phone, $userId, $senderName);
            }
            ksort($buttonsData);
            $prompt = replaceDynamicVariables($nodeData['text'] ?? 'Select an option:', $phone, $userId, $senderName);
            $res = sendButtons($phone, $prompt, $buttonsData, $nodeId, $flowId, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'interactive', $prompt, $res);
            $isInteractive = true;
            break;

        case 'interactive':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $buttonsData = [];
            if (!empty(trim($nodeData['btn1_label'] ?? ''))) {
                $buttonsData['btn1'] = replaceDynamicVariables($nodeData['btn1_label'], $phone, $userId, $senderName);
            }
            if (!empty(trim($nodeData['btn2_label'] ?? ''))) {
                $buttonsData['btn2'] = replaceDynamicVariables($nodeData['btn2_label'], $phone, $userId, $senderName);
            }
            if (!empty(trim($nodeData['btn3_label'] ?? ''))) {
                $buttonsData['btn3'] = replaceDynamicVariables($nodeData['btn3_label'], $phone, $userId, $senderName);
            }
            
            $bodyText = replaceDynamicVariables($nodeData['body_text'] ?? 'Select an option:', $phone, $userId, $senderName);
            $footerText = replaceDynamicVariables($nodeData['footer_text'] ?? '', $phone, $userId, $senderName);
            $imageUrl = $nodeData['image'] ?? '';
            
            $res = sendInteractiveButtons($phone, $bodyText, $footerText, $imageUrl, $buttonsData, $nodeId, $flowId, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'interactive', $bodyText, $res);
            $isInteractive = true;
            break;

        case 'confirm':
            $delaySecs = (int)($nodeData['delay'] ?? 0);
            if ($delaySecs > 0 && $delaySecs <= 60) {
                sleep($delaySecs);
            }
            $buttonsData = [];
            $btnYes = replaceDynamicVariables($nodeData['btn_yes_label'] ?? 'Yes, Confirm', $phone, $userId, $senderName);
            $btnNo = replaceDynamicVariables($nodeData['btn_no_label'] ?? 'No, Cancel', $phone, $userId, $senderName);
            
            if ($btnYes !== '') $buttonsData['btn-0'] = $btnYes;
            if ($btnNo !== '') $buttonsData['btn-1'] = $btnNo;
            
            $prompt = replaceDynamicVariables($nodeData['body_text'] ?? 'Are you sure?', $phone, $userId, $senderName);
            $res = sendButtons($phone, $prompt, $buttonsData, $nodeId, $flowId, $phoneId, $token);
            logChatbotMessage($userId, $phone, 'interactive', $prompt, $res);
            $isInteractive = true;
            break;

        case 'condition':
            $var = replaceDynamicVariables($nodeData['variable'] ?? '', $phone, $userId, $senderName);
            $op = $nodeData['operator'] ?? 'equals';
            $valStr = strtolower(trim(replaceDynamicVariables($nodeData['value'] ?? '', $phone, $userId, $senderName)));
            $varStr = strtolower(trim($var));
            
            $conditionMet = false;
            switch ($op) {
                case 'equals':
                    $conditionMet = ($varStr === $valStr);
                    break;
                case 'contains':
                    if ($valStr !== '') {
                        $conditionMet = (strpos($varStr, $valStr) !== false);
                    }
                    break;
                case 'starts_with':
                    if ($valStr !== '') {
                        $conditionMet = (strpos($varStr, $valStr) === 0);
                    }
                    break;
                case 'not_empty':
                    $conditionMet = ($varStr !== '');
                    break;
            }
            
            $selectedOutput = $conditionMet ? 'output_1' : 'output_2';
            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Condition evaluated. Var: '$varStr', Target: '$valStr', Op: $op => Result: " . ($conditionMet ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);
            
            // Override outputs to ONLY process the matching one to dictate route branch
            $currentNode['outputs'] = [
                $selectedOutput => $currentNode['outputs'][$selectedOutput] ?? ['connections' => []]
            ];
            break;

        case 'delay':
            $secs = max(1, min(10, (int)($nodeData['delay-seconds'] ?? 2)));
            sleep($secs);
            break;

        default:
            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Unknown node type: $nodeType\n", FILE_APPEND);
    }

    // 4. Move to Next Node (if not interactive)
    if (!$isInteractive) {
        $outputs = $currentNode['outputs'] ?? [];
        $foundNext = false;
        
        foreach ($outputs as $outputKey => $outputData) {
            $connections = $outputData['connections'] ?? [];
            foreach ($connections as $conn) {
                $nextNodeId = $conn['node'];
                $foundNext = true;
                
                // Add a forced 1-second delay between sequential nodes to guarantee WhatsApp API delivery order (Media takes longer than Text)
                sleep(1);
                
                file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Moving to next node via $outputKey: $nextNodeId\n", FILE_APPEND);
                runFlow($phone, $userId, $flowId, $nextNodeId, $phoneId, $token, $senderName); 
            }
        }
        
        if (!$foundNext) {
            setSession($phone, $userId, $flowId, $nodeId, 'finished');
            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Flow finished at node $nodeId\n", FILE_APPEND);
        }
    }
}

/**
 * 4. Improved Session Helpers
 */
function setSession($phone, $userId, $flowId, $nodeId, $state) {
    $db = Database::getInstance();
    // Use INSERT ... ON DUPLICATE KEY UPDATE with composite unique key (phone, user_id)
    $sql = "INSERT INTO chatbot_sessions (phone, user_id, flow_id, current_node_id, state) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                flow_id = VALUES(flow_id), 
                current_node_id = VALUES(current_node_id), 
                state = VALUES(state),
                updated_at = CURRENT_TIMESTAMP";
    try {
        return $db->query($sql, [$phone, $userId, $flowId, $nodeId, $state]);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] setSession Error: " . $e->getMessage() . "\n", FILE_APPEND);
        // Auto-fix: try creating the table with correct schema if missing
        try {
            $db->query(
                "UPDATE chatbot_sessions SET flow_id=?, current_node_id=?, state=?, updated_at=NOW() WHERE phone=? AND user_id=?",
                [$flowId, $nodeId, $state, $phone, $userId]
            );
        } catch (Exception $e2) {
            file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] setSession Fallback Error: " . $e2->getMessage() . "\n", FILE_APPEND);
        }
    }
}

function getSession($phone, $userId) {
    $db = Database::getInstance();
    try {
        $session = $db->fetch(
            "SELECT *, (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(updated_at)) as seconds_inactive FROM chatbot_sessions WHERE phone = ? AND user_id = ? LIMIT 1",
            [$phone, $userId]
        );
        if ($session && ($session['state'] ?? '') === 'active') {
            $sessionTimeout = 4 * 3600; // 4 hours
            if (isset($session['seconds_inactive']) && (int)$session['seconds_inactive'] > $sessionTimeout) {
                // Mark session as finished in the database
                setSession($phone, $userId, $session['flow_id'], $session['current_node_id'], 'finished');
                $session['state'] = 'finished'; // Update in-memory copy
            }
        }
        return $session;
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] getSession Error: " . $e->getMessage() . "\n", FILE_APPEND);
        return null;
    }
}

/**
 * Log automated chatbot message to the dashboard messages table
 */
function logChatbotMessage($userId, $to, $type, $content, $apiResponse, $mediaUrl = null) {
    if (!$apiResponse || !isset($apiResponse['messages'][0]['id'])) return;
    
    $db = Database::getInstance();
    try {
        $db->insert('messages', [
            'user_id' => $userId,
            'message_id' => $apiResponse['messages'][0]['id'],
            'to_number' => $to,
            'type' => $type,
            'content' => $content,
            'media_url' => $mediaUrl,
            'status' => 'sent',
            'direction' => 'outbound'
        ]);
        
        // SUPER ADMIN BYPASS: Don't deduct from admin
        $userRole = $db->fetchColumn("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($userRole !== 'admin') {
            $db->query("UPDATE credits SET used_credits = used_credits + 1 WHERE user_id = ?", [$userId]);
        }
        
        // Activity log
        $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => 'chatbot_reply',
            'description' => "Chatbot replied to $to (" . ucfirst($type) . ")",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'system'
        ]);
        
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Log Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?>
