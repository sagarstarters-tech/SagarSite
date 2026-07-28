<?php
/**
 * WhatsApp Cloud API Integration Class
 * Handles all communication with Meta's WhatsApp Business API
 */
class WhatsApp {
    private $db;
    private $apiUrl;
    private $apiVersion;

    public function __construct() {
        $this->db = Database::getInstance();
        $settings = new Settings();
        $this->apiVersion = $settings->get('whatsapp_api_version', 'v18.0');
        $this->apiUrl = $settings->get('whatsapp_api_url', 'https://graph.facebook.com') . '/' . $this->apiVersion;
    }

    /**
     * Send a text message
     */
    public function sendText($userId, $phoneNumberId, $accessToken, $to, $message) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $message]
        ];

        return $this->sendMessage($userId, $phoneNumberId, $accessToken, $to, 'text', $message, $payload);
    }

    /**
     * Send an image message
     */
    public function sendImage($userId, $phoneNumberId, $accessToken, $to, $imageUrl, $caption = '') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'image',
            'image' => ['link' => $imageUrl, 'caption' => $caption]
        ];

        return $this->sendMessage($userId, $phoneNumberId, $accessToken, $to, 'image', $caption, $payload, $imageUrl);
    }

    /**
     * Send a video message
     */
    public function sendVideo($userId, $phoneNumberId, $accessToken, $to, $videoUrl, $caption = '') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'video',
            'video' => ['link' => $videoUrl, 'caption' => $caption]
        ];

        return $this->sendMessage($userId, $phoneNumberId, $accessToken, $to, 'video', $caption, $payload, $videoUrl);
    }

    /**
     * Send a document message
     */
    public function sendDocument($userId, $phoneNumberId, $accessToken, $to, $docUrl, $filename = '', $caption = '') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'document',
            'document' => ['link' => $docUrl, 'caption' => $caption, 'filename' => $filename]
        ];

        return $this->sendMessage($userId, $phoneNumberId, $accessToken, $to, 'document', $caption, $payload, $docUrl);
    }

    /**
     * Send a template message
     */
    public function sendTemplate($userId, $phoneNumberId, $accessToken, $to, $templateName, $language = 'en', $components = []) {
        $template = ['name' => $templateName, 'language' => ['code' => $language]];
        if (!empty($components)) {
            $template['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'template',
            'template' => $template
        ];

        // Log template payload for debugging
        $logDir = APP_ROOT . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/api_payload.log',
            '[' . date('Y-m-d H:i:s') . "] TEMPLATE SEND\n" .
            '  TO: ' . $this->formatPhone($to) . "\n" .
            '  TEMPLATE: ' . $templateName . ' (lang: ' . $language . ")\n" .
            '  COMPONENTS: ' . json_encode($components) . "\n" .
            '  FULL PAYLOAD: ' . json_encode($payload) . "\n" .
            '  API URL: ' . $this->apiUrl . '/' . $phoneNumberId . "/messages\n" .
            "---\n",
            FILE_APPEND
        );

        return $this->sendMessage($userId, $phoneNumberId, $accessToken, $to, 'template', $templateName, $payload);
    }

    /**
     * Core message sending method
     */
    private function sendMessage($userId, $phoneNumberId, $accessToken, $to, $type, $content, $payload, $mediaUrl = null, $skipChecks = false) {
        $url = "{$this->apiUrl}/{$phoneNumberId}/messages";

        if (!$skipChecks) {
            // SUPER ADMIN BYPASS: Admins get free unlimited access
            $userRole = $this->db->fetchColumn("SELECT role FROM users WHERE id = ?", [$userId]);
            if ($userRole !== 'admin') {
                // Check subscription
                $sub = $this->db->fetch("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() LIMIT 1", [$userId]);
                if (!$sub) {
                    return ['success' => false, 'message' => 'Subscription expired or inactive. Please renew your plan.'];
                }

                // Check credits
                $credits = $this->db->fetch("SELECT total_credits, used_credits FROM credits WHERE user_id = ?", [$userId]);
                if ($credits && ($credits['total_credits'] - $credits['used_credits']) <= 0) {
                    return ['success' => false, 'message' => 'Insufficient credits. Please upgrade your plan.'];
                }
            }
        }

        // Log message
        $contactId = $this->db->fetchColumn("SELECT id FROM contacts WHERE user_id = ? AND phone = ?", [$userId, $this->formatPhone($to)]) ?: null;
        $waAccount = $this->db->fetchColumn("SELECT id FROM whatsapp_accounts WHERE user_id = ? AND phone_number_id = ?", [$userId, $phoneNumberId]) ?: null;

        $messageId = $this->db->insert('messages', [
            'user_id' => $userId,
            'whatsapp_account_id' => $waAccount,
            'contact_id' => $contactId,
            'to_number' => $this->formatPhone($to),
            'type' => $type,
            'content' => $content,
            'media_url' => $mediaUrl,
            'template_name' => $type === 'template' ? $content : null,
            'status' => 'queued',
            'direction' => 'outbound'
        ]);

        // Make API call
        $response = $this->makeApiCall($url, $payload, $accessToken);

        // Log full API response for debugging (especially for template messages)
        $logDir = APP_ROOT . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/api_response.log',
            '[' . date('Y-m-d H:i:s') . "] TYPE: {$type} | TO: " . $this->formatPhone($to) .
            ' | HTTP_SUCCESS: ' . ($response['success'] ? 'YES' : 'NO') .
            ' | RESPONSE: ' . json_encode($response['data'] ?? $response['message']) . "\n",
            FILE_APPEND
        );

        if ($response['success']) {
            // Validate that Meta actually accepted the message
            $waMessageId = $response['data']['messages'][0]['id'] ?? null;
            $messageStatus = $response['data']['messages'][0]['message_status'] ?? null;

            if (!$waMessageId) {
                // Meta returned 200 but no message ID — something is wrong
                $this->db->update('messages', [
                    'status' => 'failed',
                    'error_message' => 'Meta API returned success but no message ID. Response: ' . json_encode($response['data'])
                ], 'id = ?', [$messageId]);

                $this->logApiError('NO_MESSAGE_ID', $url, $payload, 'HTTP 200 but no messages[0].id in response: ' . json_encode($response['data']));

                return ['success' => false, 'message' => 'Message was not accepted by WhatsApp. Please check your template status and business verification.'];
            }

            // Check if Meta flagged the message as "accepted" but not deliverable
            // message_status can be 'accepted' (queued for delivery) or absent
            $this->db->update('messages', [
                'message_id' => $waMessageId,
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$messageId]);

            // Deduct credits
            $this->deductCredit($userId, $messageId);

            return ['success' => true, 'message' => 'Message sent successfully!', 'message_id' => $waMessageId];
        } else {
            $this->db->update('messages', [
                'status' => 'failed',
                'error_message' => $response['message']
            ], 'id = ?', [$messageId]);

            return ['success' => false, 'message' => $response['message']];
        }
    }

    /**
     * Send bulk messages
     */
    public function sendBulk($userId, $phoneNumberId, $accessToken, $contacts, $type, $content, $mediaUrl = null, $templateComponents = [], $templateLanguage = 'en') {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($contacts as $contact) {
            $phone = is_array($contact) ? $contact['phone'] : $contact;
            
            if ($type === 'text') {
                $result = $this->sendText($userId, $phoneNumberId, $accessToken, $phone, $content);
            } elseif ($type === 'image') {
                $result = $this->sendImage($userId, $phoneNumberId, $accessToken, $phone, $mediaUrl, $content);
            } elseif ($type === 'template') {
                $result = $this->sendTemplate($userId, $phoneNumberId, $accessToken, $phone, $content, $templateLanguage, $templateComponents);
            } else {
                $result = $this->sendText($userId, $phoneNumberId, $accessToken, $phone, $content);
            }

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "$phone: " . $result['message'];
            }

            // Rate limiting: 80 messages per second (Meta limit)
            usleep(15000); // 15ms delay
        }

        return $results;
    }

    /**
     * Process incoming webhook
     */
    public function processWebhook($payload, $userId = null) {
        // Log webhook
        $this->db->insert('webhook_logs', [
            'user_id' => $userId,
            'event_type' => 'incoming',
            'payload' => json_encode($payload),
            'status' => 'received'
        ]);

        if (!isset($payload['entry'])) return;

        foreach ($payload['entry'] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Status updates
                if (isset($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $this->updateMessageStatus($status);
                    }
                }

                // Incoming messages
                if (isset($value['messages'])) {
                    foreach ($value['messages'] as $msg) {
                        $this->handleIncomingMessage($msg, $value['metadata']['phone_number_id'] ?? '', $userId);
                    }
                }
            }
        }
    }

    /**
     * Update message delivery status
     */
    private function updateMessageStatus($status) {
        $waMessageId = $status['id'] ?? '';
        $newStatus = $status['status'] ?? '';

        if ($waMessageId && in_array($newStatus, ['sent', 'delivered', 'read', 'failed'])) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'delivered') $updateData['delivered_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'read') $updateData['read_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'failed') $updateData['error_message'] = json_encode($status['errors'] ?? []);

            $this->db->update('messages', $updateData, 'message_id = ?', [$waMessageId]);
        }
    }

    /**
     * Handle incoming message
     */
    private function handleIncomingMessage($msg, $phoneNumberId, $userId = null) {
        $from    = $msg['from'] ?? '';
        $msgType = $msg['type'] ?? 'text';

        // Log raw payload for debugging OTP/unknown message types
        $logDir = APP_ROOT . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/incoming_messages.log',
            '[' . date('Y-m-d H:i:s') . '] TYPE: ' . $msgType . ' | FROM: ' . $from . ' | RAW: ' . json_encode($msg) . "\n",
            FILE_APPEND
        );

        // Extract text content based on message type
        $text = '';
        switch ($msgType) {
            case 'text':
                $text = $msg['text']['body'] ?? '';
                break;

            case 'button':
                // OTP quick-reply / template button tap
                $bodyText = $msg['text']['body'] ?? '';
                $btnText  = $msg['button']['text'] ?? $msg['button']['payload'] ?? '';
                if (!empty($bodyText)) {
                    $text = $bodyText . ($btnText ? "\n[" . $btnText . "]" : "");
                } else {
                    $text = $btnText ?: "[Button message]";
                }
                break;

            case 'interactive':
                $interactive = $msg['interactive'] ?? [];
                $header = $interactive['header']['text'] ?? '';
                $body   = $interactive['body']['text']   ?? '';
                $footer = $interactive['footer']['text'] ?? '';
                
                $reply  = '';
                if (isset($interactive['button_reply'])) {
                    $reply = $interactive['button_reply']['title'] ?? '';
                } elseif (isset($interactive['list_reply'])) {
                    $reply = $interactive['list_reply']['title'] ?? '';
                }
                
                $parts = array_filter([$header, $body, $footer, $reply ? "[$reply]" : '']);
                $text = implode("\n", $parts);
                if (empty($text)) $text = '[Interactive message]';
                break;

            case 'system':
                $text = $msg['system']['body'] ?? '[System message]';
                break;

            case 'identity':
                $text = '[Identity changed: ' . ($msg['identity']['customer_identity_changed'] ?? 'true') . ']';
                break;

            case 'image':
                $text = $msg['image']['caption'] ?? '[Image]';
                break;

            case 'video':
                $text = $msg['video']['caption'] ?? '[Video]';
                break;

            case 'document':
                $text = $msg['document']['caption'] ?? $msg['document']['filename'] ?? '[Document]';
                break;

            case 'audio':
                $text = '[Audio message]';
                break;

            case 'voice':
                $text = '[Voice message]';
                break;

            case 'sticker':
                $text = '[Sticker]';
                break;

            case 'location':
                $lat  = $msg['location']['latitude']  ?? '';
                $lng  = $msg['location']['longitude'] ?? '';
                $name = $msg['location']['name']      ?? '';
                $text = '[Location' . (!empty($name) ? ': ' . $name : '') . ']'
                      . (!empty($lat) ? " ({$lat}, {$lng})" : '');
                break;

            case 'contacts':
                $names = [];
                foreach ($msg['contacts'] ?? [] as $c) {
                    $names[] = $c['name']['formatted_name'] ?? '';
                }
                $text = '[Contact: ' . implode(', ', array_filter($names)) . ']';
                break;

            case 'reaction':
                $emoji = $msg['reaction']['emoji'] ?? '';
                $text  = "[Reaction: {$emoji}]";
                break;

            case 'unsupported':
                $unsupportedType = $msg['unsupported']['type'] ?? '';
                $errorMsg = $msg['errors'][0]['message'] ?? $msg['errors'][0]['error_data']['details'] ?? '';
                $text = '[UNSUPPORTED message' . ($unsupportedType ? ' type: ' . $unsupportedType : '') . ']';
                if (!empty($errorMsg)) {
                    $text .= ' (Reason: ' . $errorMsg . ')';
                }
                break;

            case 'template':
                $templateName = $msg['template']['name'] ?? 'Unknown';
                $text = '[Template: ' . $templateName . ']';
                break;

            default:
                // Fallback: try common sub-fields, then dump raw
                $text = $msg[$msgType]['body']    ??
                        $msg[$msgType]['caption']  ??
                        $msg[$msgType]['text']     ??
                        '[' . strtoupper($msgType) . ' message]';
                break;
        }

        // Find user by phone_number_id if not provided
        if (!$userId) {
            $account = $this->db->fetch(
                "SELECT user_id FROM whatsapp_accounts WHERE phone_number_id = ? AND status IN ('active', 'pending')",
                [$phoneNumberId]
            );
            if (!$account) return;
            $userId = $account['user_id'];
        }


        // Resolve media URL for media messages (image, video, document, audio, sticker, voice)
        $mediaUrl = null;
        $mediaTypes = ['image', 'video', 'document', 'audio', 'sticker', 'voice'];
        if (in_array($msgType, $mediaTypes) && isset($msg[$msgType]['id'])) {
            $mediaId = $msg[$msgType]['id'];
            $mediaUrl = $this->getMediaUrl($mediaId, $phoneNumberId, $userId);
        }

        // Save incoming message
        $this->db->insert('messages', [
            'user_id'    => $userId,
            'message_id' => $msg['id'] ?? null,
            'to_number'  => $from,
            'type'       => $msgType,
            'content'    => $text,
            'media_url'  => $mediaUrl,
            'status'     => 'delivered',
            'direction'  => 'inbound'
        ]);
    }




    /**
     * Deduct credit for a message
     */
    private function deductCredit($userId, $messageId) {
        // SUPER ADMIN BYPASS: Don't deduct from admin
        $userRole = $this->db->fetchColumn("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($userRole === 'admin') return;

        $this->db->query("UPDATE credits SET used_credits = used_credits + 1 WHERE user_id = ?", [$userId]);

        $credits = $this->db->fetch("SELECT total_credits, used_credits FROM credits WHERE user_id = ?", [$userId]);
        $balance = $credits ? ($credits['total_credits'] - $credits['used_credits']) : 0;

        $this->db->insert('credit_transactions', [
            'user_id' => $userId,
            'type' => 'debit',
            'amount' => 1,
            'balance_after' => $balance,
            'description' => 'Message sent',
            'reference_id' => $messageId
        ]);

        // Low credit warning
        if ($balance <= 50 && $balance > 0) {
            $this->db->insert('notifications', [
                'user_id' => $userId,
                'type' => 'warning',
                'title' => 'Low Credits',
                'message' => "You have only {$balance} credits remaining. Please upgrade your plan to continue sending messages.",
                'link' => '/dashboard/subscription.php'
            ]);
        }
    }

    /**
     * Sync templates from Meta
     */
    public function syncTemplates($userId) {
        $waAccount = $this->db->fetch("SELECT waba_id, access_token FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);
        if (!$waAccount || empty($waAccount['waba_id']) || empty($waAccount['access_token'])) {
            return ['success' => false, 'message' => 'WhatsApp API is not connected or WABA ID is missing. Setup your API credentials first.'];
        }

        $wabaId = $waAccount['waba_id'];
        $accessToken = $waAccount['access_token'];
        $url = "{$this->apiUrl}/{$wabaId}/message_templates?limit=200";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['data']) && is_array($data['data'])) {
                $syncedCount = 0;
                foreach ($data['data'] as $tpl) {
                    $name = $tpl['name'];
                    $language = $tpl['language'];
                    $category = strtolower($tpl['category'] ?? '');
                    $status = strtolower($tpl['status'] ?? 'pending');
                    
                    $headerContent = '';
                    $headerType = 'none';
                    $bodyContent = '';
                    $footerContent = '';
                    $buttonsContent = null;
                    
                    if (!empty($tpl['components'])) {
                        foreach ($tpl['components'] as $comp) {
                            if ($comp['type'] === 'HEADER') {
                                $headerType = strtolower($comp['format'] ?? 'text');
                                $headerContent = $comp['text'] ?? '';
                            } elseif ($comp['type'] === 'BODY') {
                                $bodyContent = $comp['text'] ?? '';
                            } elseif ($comp['type'] === 'FOOTER') {
                                $footerContent = $comp['text'] ?? '';
                            } elseif ($comp['type'] === 'BUTTONS') {
                                $buttonsContent = json_encode($comp['buttons'] ?? []);
                            }
                        }
                    }
                    
                    $existing = $this->db->fetch("SELECT id FROM templates WHERE user_id = ? AND name = ? AND language = ?", [$userId, $name, $language]);
                    
                    $tplData = [
                        'user_id' => $userId,
                        'name' => $name,
                        'category' => $category,
                        'language' => $language,
                        'header_type' => $headerType,
                        'header_content' => $headerContent,
                        'body' => $bodyContent,
                        'footer' => $footerContent,
                        'buttons' => $buttonsContent,
                        'status' => $status
                    ];
                    
                    if ($existing) {
                        $this->db->update('templates', $tplData, 'id = ?', [$existing['id']]);
                    } else {
                        $this->db->insert('templates', $tplData);
                    }
                    $syncedCount++;
                }
                return ['success' => true, 'message' => "Successfully synced $syncedCount templates from Meta.", 'count' => $syncedCount];
            }
        }
        
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown Meta API error';
        return ['success' => false, 'message' => "Failed to sync templates: $errorMsg"];
    }

    /**
     * Format phone number (remove spaces, dashes, add country code)
     */
    private function formatPhone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '91' . substr($phone, 1); // Default India
        }
        $phone = ltrim($phone, '+');
        return $phone;
    }

    /**
     * Make API call to Meta's WhatsApp API
     */
    private function makeApiCall($url, $data, $accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Always log the full API exchange for debugging
        $logDir = APP_ROOT . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/whatsapp_api.log',
            '[' . date('Y-m-d H:i:s') . "] HTTP {$httpCode} | URL: {$url}\n" .
            '  REQUEST: ' . json_encode($data) . "\n" .
            '  RESPONSE: ' . ($response ?: '(empty)') . "\n" .
            ($error ? '  CURL_ERROR: ' . $error . "\n" : '') .
            "---\n",
            FILE_APPEND
        );

        if ($error) {
            $this->logApiError('CURL_ERROR', $url, $data, $error);
            return ['success' => false, 'message' => 'API connection error: ' . $error, 'data' => null];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Success', 'data' => $result];
        } else {
            $errorMsg = $result['error']['message'] ?? 'Unknown API error (HTTP ' . $httpCode . ')';
            $errorCode = $result['error']['code'] ?? 0;
            $errorSubcode = $result['error']['error_subcode'] ?? 0;
            $fullError = "HTTP {$httpCode} | Code: {$errorCode} | Subcode: {$errorSubcode} | {$errorMsg}";
            $this->logApiError('API_ERROR', $url, $data, $fullError);
            return ['success' => false, 'message' => $errorMsg, 'data' => $result];
        }
    }

    /**
     * Log API errors for debugging
     */
    private function logApiError($type, $url, $requestData, $error) {
        $logDir = APP_ROOT . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $to = $requestData['to'] ?? 'unknown';
        $templateName = $requestData['template']['name'] ?? ($requestData['type'] ?? 'N/A');
        file_put_contents(
            $logDir . '/whatsapp_api.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $type . ' | TO: ' . $to . ' | TEMPLATE: ' . $templateName . ' | ERROR: ' . $error . "\n",
            FILE_APPEND
        );
    }

    /**
     * Get media download URL from WhatsApp media ID
     * Meta requires a two-step process: first get the URL, then download with auth header
     */
    private function getMediaUrl($mediaId, $phoneNumberId, $userId) {
        try {
            // Get access token for this phone number ID
            $account = $this->db->fetch(
                "SELECT access_token FROM whatsapp_accounts WHERE phone_number_id = ? AND user_id = ? AND status IN ('active', 'pending') LIMIT 1",
                [$phoneNumberId, $userId]
            );
            if (!$account) {
                // Try without user_id (webhook might not always have it)
                $account = $this->db->fetch(
                    "SELECT access_token FROM whatsapp_accounts WHERE phone_number_id = ? AND status IN ('active', 'pending') LIMIT 1",
                    [$phoneNumberId]
                );
            }
            if (!$account || empty($account['access_token'])) {
                return null;
            }

            $accessToken = $account['access_token'];
            $url = "{$this->apiUrl}/{$mediaId}";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['url'] ?? null;
            }

            $logDir = APP_ROOT . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            file_put_contents(
                $logDir . '/media_fetch.log',
                '[' . date('Y-m-d H:i:s') . "] Failed to get media URL for ID: {$mediaId} | HTTP: {$httpCode} | Response: {$response}\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            $logDir = APP_ROOT . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            file_put_contents(
                $logDir . '/media_fetch.log',
                '[' . date('Y-m-d H:i:s') . "] Exception fetching media URL: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }
        return null;
    }
}
