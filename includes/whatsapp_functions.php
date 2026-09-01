<?php
/**
 * WhatsApp Integration Functions
 * Handles automated notifications via Meta Cloud API
 */

/**
 * Clean and normalize phone number into standard international format (e.g., 919876543210 for India)
 */
function normalize_whatsapp_phone_number($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string)$phone);
    $digits = ltrim($digits, '0');
    if (empty($digits)) return '';

    // If 14 digits starting with 9191 (e.g. user selected +91 and also typed 91 in number)
    if (strlen($digits) == 14 && substr($digits, 0, 4) === '9191') {
        $digits = substr($digits, 2);
    }
    // If 12 digits starting with 91, return it
    if (strlen($digits) == 12 && substr($digits, 0, 2) === '91') {
        return $digits;
    }
    // If standard 10 digits Indian mobile, prepend 91
    if (strlen($digits) == 10) {
        return '91' . $digits;
    }
    return $digits;
}

function sendAutomatedWhatsApp($conn, $order_id) {
    // Check if feature is globally enabled
    $set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
    if (!$set_q || $set_q->num_rows === 0) return false;
    
    $settings = $set_q->fetch_assoc();
    
    // Auto-notifications ONLY for API mode, and must be enabled
    if ($settings['is_enabled'] != 1 || $settings['sending_mode'] !== 'api') {
        return false;
    }
    
    if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        error_log("WhatsApp Auto-Send Failed: Missing API token or Phone ID");
        return false;
    }

    // Fetch Order details
    $order_id = intval($order_id);
    $q = $conn->query("
        SELECT o.status, o.total_amount, u.name, u.phone, 
               (SELECT tracking_number FROM order_tracking WHERE order_id = o.id LIMIT 1) as tracking_number
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = $order_id
    ");

    if (!$q || $q->num_rows === 0) return false;
    $order = $q->fetch_assoc();

    // Prepare variables
    $customerName = trim($order['name']);
    $customerPhone = trim($order['phone']);
    $orderStatus = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
    $trackingID = !empty($order['tracking_number']) ? $order['tracking_number'] : 'N/A';
    $orderAmount = number_format($order['total_amount'] ?? 0, 2);

    $clean_number = normalize_whatsapp_phone_number($customerPhone);
    if (empty($clean_number)) return false;

    // Parse Template variables for payload and default text message
    $message = $settings['message_template'];
    $replacementValues = [
        '{CustomerName}' => $customerName,
        '{OrderID}'      => $order_id,
        '{OrderStatus}'  => $orderStatus,
        '{TrackingID}'   => $trackingID,
        '{OrderAmount}'  => $orderAmount
    ];
    
    // Create old style text message for fallback and logging
    foreach ($replacementValues as $search => $replace) {
        $message = str_replace($search, $replace, $message);
    }
    
    // Prepare Meta API Payload
    $token = trim($settings['api_token']);
    $phone_id = trim($settings['phone_number_id']);
    $url = "https://graph.facebook.com/v21.0/{$phone_id}/messages";
    
    $meta_template_name = trim($settings['meta_template_name'] ?? '');
    if (!empty($meta_template_name)) {
        // --- TEMPLATE MODE ---
        preg_match_all('/\{(CustomerName|OrderID|OrderStatus|TrackingID|OrderAmount)\}/', $settings['message_template'], $matches);
        
        $params = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $varKey) {
                $params[] = ["type" => "text", "text" => (string)$replacementValues[$varKey]];
            }
        }
        
        // Build components array (body is always included)
        $components = [
            [
                "type" => "body",
                "parameters" => $params
            ]
        ];
        
        // Add header image component if configured
        $header_image_url = trim($settings['wa_header_image_url'] ?? '');
        if (!empty($header_image_url)) {
            array_unshift($components, [
                "type" => "header",
                "parameters" => [
                    [
                        "type" => "image",
                        "image" => ["link" => $header_image_url]
                    ]
                ]
            ]);
        }
        
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $clean_number,
            "type"              => "template",
            "template"          => [
                "name"     => $meta_template_name,
                "language" => ["code" => trim($settings['meta_template_lang'] ?? 'en')],
                "components" => $components
            ]
        ];
    } else {
        // --- TEXT MODE ---
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $clean_number,
            "type"              => "text",
            "text"              => ["preview_url" => false, "body" => $message]
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    
    $result     = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Always log every API call for diagnosis
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_entry = '[' . date('Y-m-d H:i:s') . "] Auto-Send Order#$order_id HTTP:{$http_code} To:{$clean_number}" . PHP_EOL;
    $log_entry .= "Payload: " . json_encode($payload) . PHP_EOL;
    $log_entry .= "Response: " . $result . PHP_EOL;
    $log_entry .= str_repeat('-', 60) . PHP_EOL;
    file_put_contents($log_dir . '/whatsapp_api.log', $log_entry, FILE_APPEND);
    
    if ($curl_error) {
        error_log("[WhatsApp] cURL Error Order#$order_id: $curl_error");
        $conn->query("INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status) VALUES ($order_id, '$clean_number', '" . $conn->real_escape_string($message) . "', 'api', 'Failed: cURL - " . $conn->real_escape_string(substr($curl_error,0,80)) . "')");
        return false;
    }
    
    $meta_response = json_decode($result, true);
    $status_msg = "";

    if ($http_code == 200 && isset($meta_response['messages'])) {
        $msg_id  = $meta_response['messages'][0]['id'] ?? 'unknown';
        $status_msg = 'Sent via Meta API (Auto) ID:' . substr($msg_id, 0, 20);
    } else {
        $error_desc = $meta_response['error']['message'] ?? 'Connection error or unknown Meta API Error';
        $error_code = $meta_response['error']['code'] ?? 'N/A';
        $status_msg = "Failed API (Auto): (#{$error_code}) " . substr($error_desc, 0, 100);
        
        // Log deep error for admin
        file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);
    }
    
    // Log to Database
    $conn->query("INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status) VALUES ($order_id, '$clean_number', '" . $conn->real_escape_string($message) . "', 'api', '$status_msg')");
    
    return $http_code == 200;
}

/**
 * Send WhatsApp notification to ADMIN when a new order is placed.
 * Fail-safe: errors are logged but never block order completion.
 *
 * @param mysqli $conn    Database connection
 * @param int    $order_id The order ID
 * @return bool  True if sent successfully
 */
function sendAdminOrderNotification($conn, $order_id) {
    try {
        // Ensure admin notification columns exist
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_whatsapp_number VARCHAR(20) NOT NULL DEFAULT ''");
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_notify_on_new_order TINYINT(1) NOT NULL DEFAULT 1");

        // Check if feature is enabled and admin number is configured
        $set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
        if (!$set_q || $set_q->num_rows === 0) return false;
        
        $settings = $set_q->fetch_assoc();
        
        // Must have: global enabled + API mode + admin notification enabled + admin number set
        if ($settings['is_enabled'] != 1 || $settings['sending_mode'] !== 'api') {
            return false;
        }
        if (empty($settings['admin_notify_on_new_order']) || $settings['admin_notify_on_new_order'] != 1) {
            return false;
        }
        $admin_number = trim($settings['admin_whatsapp_number'] ?? '');
        if (empty($admin_number)) {
            return false;
        }
        if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
            error_log("[WhatsApp Admin] Failed: Missing API token or Phone ID");
            return false;
        }

        // Clean admin number with standard normalizer
        $clean_admin = normalize_whatsapp_phone_number($admin_number);
        if (empty($clean_admin)) return false;

        // Fetch order details for admin message
        $order_id = intval($order_id);
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_mode, o.created_at,
                   u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = $order_id
        ");

        if (!$q || $q->num_rows === 0) return false;
        $order = $q->fetch_assoc();

        // Build admin notification message
        $customerName  = trim($order['customer_name']);
        $customerPhone = trim($order['customer_phone']);
        $orderAmount   = number_format($order['total_amount'] ?? 0, 2);
        $paymentMode   = strtoupper($order['payment_mode'] ?? 'N/A');
        $orderTime     = date('d M Y, h:i A', strtotime($order['created_at']));

        $adminMessage  = "🛒 *New Order Alert!*\n\n";
        $adminMessage .= "Order: *#$order_id*\n";
        $adminMessage .= "Customer: $customerName\n";
        $adminMessage .= "Phone: $customerPhone\n";
        $adminMessage .= "Amount: ₹$orderAmount\n";
        $adminMessage .= "Payment: $paymentMode\n";
        $adminMessage .= "Time: $orderTime\n\n";
        $adminMessage .= "Login to admin panel to process this order.";

        // Check if Meta Template is configured to enable 24/7 delivery without requiring "Hi"
        $admin_tpl_name = trim($settings['admin_template_name'] ?? '');
        if (empty($admin_tpl_name)) {
            $admin_tpl_name = trim($settings['meta_template_name'] ?? '');
        }

        if (!empty($admin_tpl_name)) {
            // ── 24/7 TEMPLATE MODE (Bypasses 24-hour restriction) ──
            $params = [];
            
            // If it's a dedicated admin template (e.g. admin_new_order_alert)
            if ($admin_tpl_name !== ($settings['meta_template_name'] ?? '')) {
                $params = [
                    ["type" => "text", "text" => (string)$order_id],
                    ["type" => "text", "text" => (string)$customerName],
                    ["type" => "text", "text" => (string)$customerPhone],
                    ["type" => "text", "text" => (string)$orderAmount],
                    ["type" => "text", "text" => (string)$paymentMode],
                ];
            } else {
                // Fallback mapping if using customer template
                $orderStatus = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
                $trackingID  = 'N/A';
                
                $replacementValues = [
                    '{CustomerName}' => $customerName,
                    '{OrderID}'      => $order_id,
                    '{OrderStatus}'  => $orderStatus,
                    '{TrackingID}'   => $trackingID,
                    '{OrderAmount}'  => $orderAmount
                ];

                preg_match_all('/\{(CustomerName|OrderID|OrderStatus|TrackingID|OrderAmount)\}/', $settings['message_template'], $matches);
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $varKey) {
                        $params[] = ["type" => "text", "text" => (string)$replacementValues[$varKey]];
                    }
                }
            }
            
            $components = [
                [
                    "type" => "body",
                    "parameters" => $params
                ]
            ];

            $header_image_url = trim($settings['wa_header_image_url'] ?? '');
            if (empty($header_image_url) && $admin_tpl_name === 'admin_new_order_alert') {
                $header_image_url = 'https://sagarstarters.com/assets/images/admin_order_alert_banner.jpg';
            }

            if (!empty($header_image_url)) {
                array_unshift($components, [
                    "type" => "header",
                    "parameters" => [
                        [
                            "type" => "image",
                            "image" => ["link" => $header_image_url]
                        ]
                    ]
                ]);
            }

            $payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_admin,
                "type"              => "template",
                "template"          => [
                    "name"       => $admin_tpl_name,
                    "language"   => ["code" => trim($settings['meta_template_lang'] ?? 'en')],
                    "components" => $components
                ]
            ];
        } else {
            // ── TEXT MODE (Requires 24-hour conversation window) ──
            $payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_admin,
                "type"              => "text",
                "text"              => ["preview_url" => false, "body" => $adminMessage]
            ];
        }

        $send_admin_meta = function($pay) use ($url, $token) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS     => json_encode($pay),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            return [$res, $code, $err];
        };

        list($result, $http_code, $curl_error) = $send_admin_meta($payload);
        $meta_response = json_decode($result, true);

        // Smart Auto-Recovery for header mismatch
        if ($http_code != 200 && isset($payload['template'])) {
            $errMsg = $meta_response['error']['message'] ?? '';
            $errDetails = $meta_response['error']['error_data']['details'] ?? '';
            $fullErr = $errMsg . ' ' . $errDetails;

            if (stripos($fullErr, 'expected IMAGE') !== false) {
                $fallback_img = 'https://sagarstarters.com/assets/images/auth_banner.jpg';
                $has_header = false;
                foreach ($payload['template']['components'] as $c) {
                    if (($c['type'] ?? '') === 'header') { $has_header = true; break; }
                }
                if (!$has_header) {
                    array_unshift($payload['template']['components'], [
                        "type" => "header",
                        "parameters" => [["type" => "image", "image" => ["link" => $fallback_img]]]
                    ]);
                    list($result, $http_code, $curl_error) = $send_admin_meta($payload);
                    $meta_response = json_decode($result, true);
                }
            } elseif (stripos($fullErr, 'expected NO_HEADER') !== false || stripos($fullErr, 'unexpected header') !== false) {
                $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                    return ($c['type'] ?? '') !== 'header';
                }));
                list($result, $http_code, $curl_error) = $send_admin_meta($payload);
                $meta_response = json_decode($result, true);
            }
        }

        // Log to file
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
        $log_entry  = '[' . date('Y-m-d H:i:s') . "] ADMIN-Notify Order#$order_id HTTP:{$http_code} To:{$clean_admin}" . PHP_EOL;
        $log_entry .= "Payload: " . json_encode($payload) . PHP_EOL;
        $log_entry .= "Response: " . $result . PHP_EOL;
        $log_entry .= str_repeat('-', 60) . PHP_EOL;
        file_put_contents($log_dir . '/whatsapp_api.log', $log_entry, FILE_APPEND);

        // Determine status
        $status_msg = '';
        if ($curl_error) {
            error_log("[WhatsApp Admin] cURL Error Order#$order_id: $curl_error");
            $status_msg = 'Admin Failed: cURL - ' . substr($curl_error, 0, 80);
        } else {
            $meta_response = json_decode($result, true);
            if ($http_code == 200 && isset($meta_response['messages'])) {
                $msg_id     = $meta_response['messages'][0]['id'] ?? 'unknown';
                $status_msg = 'Admin Alert Sent (ID: ' . substr($msg_id, 0, 20) . ')';
            } else {
                $error_desc = $meta_response['error']['message'] ?? 'Unknown Meta API Error';
                $error_code = $meta_response['error']['code'] ?? 'N/A';
                
                // Detailed diagnostic message for 24h window
                if ($error_code == 131047 || $error_code == 131026) {
                    $status_msg = "Admin Alert Failed: 24h Window Expired (Admin must send 'Hi' to bot once)";
                } else {
                    $status_msg = "Admin Alert Failed: (#{$error_code}) " . substr($error_desc, 0, 80);
                }
                file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);
            }
        }

        // Log to whatsapp_logs table
        $conn->query("INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status) 
            VALUES ($order_id, '$clean_admin', '" . $conn->real_escape_string($adminMessage) . "', 'api', '" . $conn->real_escape_string($status_msg) . "')");

        return $http_code == 200;

    } catch (Exception $e) {
        error_log("[WhatsApp Admin] Exception Order#$order_id: " . $e->getMessage());
        return false;
    }
}
