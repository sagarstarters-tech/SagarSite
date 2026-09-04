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

/**
 * Safely format payment method/mode label for WhatsApp notifications.
 *
 * @param string $method Value from orders.payment_method (e.g. 'phonepe', 'cod', 'card')
 * @param string $mode   Value from orders.payment_mode (e.g. 'COD_PARTIAL', 'phonepe')
 * @return string Clean formatted payment title (e.g. 'PhonePe UPI', 'Partial COD', 'COD')
 */
if (!function_exists('formatWhatsAppPaymentMethod')) {
    function formatWhatsAppPaymentMethod($method, $mode = '') {
        $m = strtolower(trim((string)$method));
        $mode = strtolower(trim((string)$mode));

        // Check Partial COD
        if ($mode === 'cod_partial' || $mode === 'partial_cod' || $m === 'partial_cod' || $m === 'cod_partial') {
            return 'Partial COD';
        }

        // Check PhonePe / UPI
        if ($mode === 'phonepe' || $mode === 'phonepe_upi' || $mode === 'upi' ||
            $m === 'phonepe' || $m === 'phonepe_upi' || $m === 'upi') {
            return 'PhonePe UPI';
        }

        // Check COD
        if ($mode === 'cod' || $m === 'cod') {
            return 'COD';
        }

        // Check Card / Net Banking / Razorpay
        if ($m === 'card' || $m === 'credit_card' || $m === 'debit_card' || $mode === 'card') {
            return 'Card';
        }
        if ($m === 'netbanking' || $mode === 'netbanking') {
            return 'Net Banking';
        }
        if ($m === 'razorpay' || $mode === 'razorpay') {
            return 'Razorpay';
        }

        $val = !empty($mode) ? $mode : $m;
        if (!empty($val)) {
            return ucwords(str_replace(['_', '-'], ' ', $val));
        }

        return 'COD';
    }
}

/**
 * Send WhatsApp notification to CUSTOMER when an order is placed/confirmed.
 *
 * @param mysqli $conn     Database connection
 * @param int    $order_id The order ID
 * @return bool  True if sent successfully
 */
function sendCustomerOrderConfirmationWhatsApp($conn, $order_id) {
    try {
        // Ensure settings columns exist
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1");
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_template_name VARCHAR(100) NOT NULL DEFAULT ''");
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_confirmation_message_template TEXT NOT NULL DEFAULT ''");

        // Fetch settings
        $set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
        if (!$set_q || $set_q->num_rows === 0) return false;
        
        $settings = $set_q->fetch_assoc();
        
        if ($settings['is_enabled'] != 1 || $settings['sending_mode'] !== 'api') {
            return false;
        }
        if (isset($settings['order_confirmation_enabled']) && $settings['order_confirmation_enabled'] == 0) {
            return false;
        }
        if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
            error_log("[WhatsApp] Customer Order Confirmation Failed: Missing API token or Phone ID");
            return false;
        }

        // Fetch order details
        $order_id = intval($order_id);
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_method, o.payment_mode, o.created_at,
                   u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                   u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = $order_id
        ");

        if (!$q || $q->num_rows === 0) return false;
        $order = $q->fetch_assoc();

        $customerName  = trim($order['customer_name'] ?? 'Customer');
        $customerPhone = trim($order['customer_phone'] ?? '');
        $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
        $paymentMode   = formatWhatsAppPaymentMethod($order['payment_method'] ?? '', $order['payment_mode'] ?? '');
        $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Pending'));
        $orderDate     = date('d M Y', strtotime($order['created_at']));
        $orderTime     = date('h:i A', strtotime($order['created_at']));

        $clean_number = normalize_whatsapp_phone_number($customerPhone);
        if (empty($clean_number)) return false;

        // ── Deduplication Guard: Prevent duplicate customer order confirmations ──
        $cust_lock_name = "wa_cust_confirm_" . intval($order_id);
        $conn->query("SELECT GET_LOCK('$cust_lock_name', 5)");

        $already_sent_cust = false;
        $chk_cust = $conn->query("
            SELECT id FROM whatsapp_logs 
            WHERE order_id = $order_id 
              AND customer_number = '$clean_number'
              AND (status LIKE '%Customer Order Confirmation Sent%' OR status LIKE '%Order Confirmation Sent%' OR status LIKE '%Sent via Meta API%')
            LIMIT 1
        ");
        if ($chk_cust && $chk_cust->num_rows > 0) {
            $already_sent_cust = true;
        }

        if ($already_sent_cust) {
            $conn->query("SELECT RELEASE_LOCK('$cust_lock_name')");
            error_log("[WhatsApp] Customer Order Confirmation already sent for Order #$order_id. Skipping duplicate dispatch.");
            return true;
        }
        $addressParts = array_filter([
            trim($order['customer_address'] ?? ''),
            trim($order['customer_city'] ?? ''),
            trim($order['customer_state'] ?? ''),
            trim($order['customer_zip'] ?? '')
        ]);
        $deliveryAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';

        // Order Items List
        $itemsList = [];
        $items_res = $conn->query("
            SELECT oi.quantity, oi.price, p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = $order_id
        ");
        if ($items_res && $items_res->num_rows > 0) {
            while ($itm = $items_res->fetch_assoc()) {
                $pName = trim($itm['product_name'] ?? 'Product');
                $qty   = (int)($itm['quantity'] ?? 1);
                $itemsList[] = "• {$pName} ({$qty}x)";
            }
        }
        $itemsOrdered = !empty($itemsList) ? implode("\n", $itemsList) : "Order #$order_id";

        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
        $orderLink = $siteUrl . '/my-orders.php';

        // Bridge & Text Message Template
        $bridge_template = !empty($settings['order_confirmation_message_template']) 
            ? $settings['order_confirmation_message_template']
            : "Hello Dear {CustomerName},\n\nThank you for your order! Your Order #{OrderID} has been successfully placed.\n\nOrder Date: {OrderDate}\nTotal Amount: ₹{OrderAmount}\nPayment: {PaymentMethod}\n\nDelivery Address:\n{DeliveryAddress}\n\nThank you for shopping with Sagar Starter's!";

        $replacementValues = [
            '{CustomerName}'  => $customerName,
            '{OrderID}'       => $order_id,
            '{OrderDate}'     => $orderDate,
            '{OrderTime}'     => $orderTime,
            '{OrderAmount}'   => $orderAmount,
            '{PaymentMethod}' => $paymentMode,
            '{OrderStatus}'   => $orderStatus,
            '{DeliveryAddress}' => $deliveryAddress,
            '{ItemsOrdered}'  => $itemsOrdered,
            '{OrderLink}'     => $orderLink
        ];

        $message = $bridge_template;
        foreach ($replacementValues as $k => $v) {
            $message = str_replace($k, $v, $message);
        }

        $token    = trim($settings['api_token']);
        $phone_id = trim($settings['phone_number_id']);
        $url      = "https://graph.facebook.com/v21.0/{$phone_id}/messages";

        $send_meta_curl = function($pay) use ($url, $token) {
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

        $tpl_name = trim($settings['order_confirmation_template_name'] ?? '');
        $header_image_url = trim($settings['wa_header_image_url'] ?? '');
        $lang_code = trim($settings['meta_template_lang'] ?? 'en');
        if (empty($lang_code)) $lang_code = 'en';

        $result = '';
        $http_code = 0;
        $curl_error = '';
        $payload = [];
        $sent_successfully = false;

        if (!empty($tpl_name)) {
            $build_payload = function($tName, $lang, $paramList, $includeHeaderImg) use ($clean_number, $header_image_url) {
                $components = [
                    [
                        "type" => "body",
                        "parameters" => $paramList
                    ]
                ];
                if ($includeHeaderImg && !empty($header_image_url)) {
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
                return [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $clean_number,
                    "type"              => "template",
                    "template"          => [
                        "name"       => $tName,
                        "language"   => ["code" => $lang],
                        "components" => $components
                    ]
                ];
            };

            // Standard parameter sets for order confirmation:
            $params_bridge = [];
            preg_match_all('/\{(CustomerName|OrderID|OrderDate|OrderTime|OrderAmount|PaymentMethod|OrderStatus|DeliveryAddress|ItemsOrdered|OrderLink)\}/', $bridge_template, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $varKey) {
                    $params_bridge[] = ["type" => "text", "text" => (string)($replacementValues[$varKey] ?? '')];
                }
            }

            $safe_cust_name  = !empty($customerName) ? $customerName : 'Valued Customer';
            $safe_order_id   = (string)$order_id;
            $safe_order_date = !empty($orderDate) ? $orderDate : date('d M Y');
            $safe_order_time = !empty($orderTime) ? $orderTime : date('h:i A');
            $safe_amount     = !empty($orderAmount) ? $orderAmount : '0.00';
            $safe_payment    = !empty($paymentMode) ? $paymentMode : 'COD';
            $safe_status     = !empty($orderStatus) ? $orderStatus : 'Pending';
            $safe_items      = !empty($itemsOrdered) ? $itemsOrdered : "Order #$order_id";
            $safe_addr       = (!empty($deliveryAddress) && $deliveryAddress !== 'N/A') ? $deliveryAddress : 'Customer Delivery Address';
            $safe_link       = !empty($orderLink) ? $orderLink : 'https://sagarstarters.com/my-orders.php';

            $params_9 = [
                ["type" => "text", "text" => $safe_cust_name],  // {{1}} customer_name
                ["type" => "text", "text" => $safe_order_id],   // {{2}} order_id
                ["type" => "text", "text" => $safe_order_date], // {{3}} order_date
                ["type" => "text", "text" => $safe_amount],     // {{4}} order_total
                ["type" => "text", "text" => $safe_payment],    // {{5}} payment_method
                ["type" => "text", "text" => $safe_status],     // {{6}} order_status
                ["type" => "text", "text" => $safe_items],      // {{7}} order_items
                ["type" => "text", "text" => $safe_addr],       // {{8}} customer_address
                ["type" => "text", "text" => $safe_link],       // {{9}} order_link
            ];

            $params_11 = [
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_order_date],
                ["type" => "text", "text" => $safe_order_time],
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => (string)$customerPhone],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
                ["type" => "text", "text" => $safe_status],
                ["type" => "text", "text" => $safe_addr],
                ["type" => "text", "text" => $safe_items],
                ["type" => "text", "text" => $safe_link],
            ];

            $params_4 = [
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
            ];

            $params_5 = [
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => (string)$customerPhone],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
            ];

            $candidate_sets = [
                ['params' => $params_9,        'header' => false,                     'lang' => $lang_code],
                ['params' => $params_9,        'header' => !empty($header_image_url), 'lang' => $lang_code],
                ['params' => $params_11,       'header' => false,                     'lang' => $lang_code],
                ['params' => $params_4,        'header' => false,                     'lang' => $lang_code],
                ['params' => $params_5,        'header' => false,                     'lang' => $lang_code],
                ['params' => $params_bridge,   'header' => false,                     'lang' => $lang_code],
                ['params' => $params_9,        'header' => false,                     'lang' => ($lang_code === 'en' ? 'en_US' : 'en')],
                ['params' => $params_4,        'header' => false,                     'lang' => ($lang_code === 'en' ? 'en_US' : 'en')],
            ];

            $tpl_names_to_try = array_unique(array_filter([
                $tpl_name,
                'order_confirmation',
                'order_confirmation_notification',
                'new_order_confirmation',
                'order_confirm'
            ]));

            foreach ($tpl_names_to_try as $current_tpl_name) {
                foreach ($candidate_sets as $candidate) {
                    if (empty($candidate['params'])) continue;
                    $payload = $build_payload($current_tpl_name, $candidate['lang'], $candidate['params'], $candidate['header']);
                    list($result, $http_code, $curl_error) = $send_meta_curl($payload);
                    $meta_response = json_decode($result, true);

                    if ($http_code == 200 && isset($meta_response['messages'])) {
                        $sent_successfully = true;
                        if ($current_tpl_name !== $tpl_name && !empty($current_tpl_name)) {
                            $conn->query("UPDATE whatsapp_settings SET order_confirmation_template_name = '" . $conn->real_escape_string($current_tpl_name) . "' WHERE id = 1");
                        }
                        break 2;
                    }
                }
            }
        }

        // Final Fallback: Direct text message
        if (!$sent_successfully) {
            $text_payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_number,
                "type"              => "text",
                "text"              => ["preview_url" => false, "body" => $message]
            ];
            list($text_result, $text_code, $text_err) = $send_meta_curl($text_payload);
            $text_meta = json_decode($text_result, true);
            if ($text_code == 200 && isset($text_meta['messages'])) {
                $payload   = $text_payload;
                $result    = $text_result;
                $http_code = $text_code;
                $sent_successfully = true;
            }
        }

        // Log to file
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
        $log_entry  = '[' . date('Y-m-d H:i:s') . "] Customer-OrderConfirm#$order_id HTTP:{$http_code} To:{$clean_number}" . PHP_EOL;
        $log_entry .= "Payload: " . json_encode($payload) . PHP_EOL;
        $log_entry .= "Response: " . $result . PHP_EOL;
        $log_entry .= str_repeat('-', 60) . PHP_EOL;
        file_put_contents($log_dir . '/whatsapp_api.log', $log_entry, FILE_APPEND);

        $status_msg = '';
        if ($curl_error) {
            error_log("[WhatsApp] Customer Order Confirm Error Order#$order_id: $curl_error");
            $status_msg = 'Order Confirm Failed: cURL - ' . substr($curl_error, 0, 80);
        } else {
            $meta_response = json_decode($result, true);
            if ($http_code == 200 && isset($meta_response['messages'])) {
                $msg_id     = $meta_response['messages'][0]['id'] ?? 'unknown';
                $status_msg = 'Customer Order Confirmation Sent (ID: ' . substr($msg_id, 0, 20) . ')';
            } else {
                $error_desc = $meta_response['error']['message'] ?? 'Unknown Meta API Error';
                $error_code = $meta_response['error']['code'] ?? 'N/A';
                $status_msg = "Order Confirm Failed: (#{$error_code}) " . substr($error_desc, 0, 80);
                file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);
            }
        }

        $conn->query("INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status) 
            VALUES ($order_id, '$clean_number', '" . $conn->real_escape_string($message) . "', 'api', '" . $conn->real_escape_string($status_msg) . "')");

        if (isset($cust_lock_name)) {
            $conn->query("SELECT RELEASE_LOCK('$cust_lock_name')");
        }

        return $sent_successfully;

    } catch (Exception $e) {
        if (isset($cust_lock_name)) {
            $conn->query("SELECT RELEASE_LOCK('$cust_lock_name')");
        }
        error_log("[WhatsApp] Customer Order Confirmation Exception Order#$order_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp notification to CUSTOMER when an order status is updated (Shipped, Delivered, etc.).
 *
 * @param mysqli $conn     Database connection
 * @param int    $order_id The order ID
 * @return bool  True if sent successfully
 */
function sendCustomerOrderStatusWhatsApp($conn, $order_id) {
    try {
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS order_status_notify_enabled TINYINT(1) NOT NULL DEFAULT 1");

        $set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
        if (!$set_q || $set_q->num_rows === 0) return false;
        
        $settings = $set_q->fetch_assoc();
        
        if ($settings['is_enabled'] != 1 || $settings['sending_mode'] !== 'api') {
            return false;
        }
        if (isset($settings['order_status_notify_enabled']) && $settings['order_status_notify_enabled'] == 0) {
            return false;
        }
        if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
            error_log("WhatsApp Status-Send Failed: Missing API token or Phone ID");
            return false;
        }

        // Fetch Order details safely
        $order_id = intval($order_id);
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.created_at, o.payment_method, o.payment_mode,
                   o.tracking_number AS order_tracking_num, o.carrier AS order_carrier,
                   u.name AS customer_name, u.phone AS customer_phone,
                   u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.id = $order_id
        ");

        if (!$q || $q->num_rows === 0) return false;
        $order = $q->fetch_assoc();

        // Safely fetch tracking info from order_tracking table if exists
        $tracking_q = $conn->query("
            SELECT ot.tracking_number, ot.estimated_delivery_date, cc.name AS courier_name
            FROM order_tracking ot
            LEFT JOIN courier_companies cc ON ot.courier_id = cc.id
            WHERE ot.order_id = $order_id
            LIMIT 1
        ");
        $track = ($tracking_q && $tracking_q->num_rows > 0) ? $tracking_q->fetch_assoc() : [];

        $customerName  = trim($order['customer_name'] ?? 'Customer');
        $customerPhone = trim($order['customer_phone'] ?? '');
        $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
        $trackingID    = !empty($track['tracking_number']) ? $track['tracking_number'] : (!empty($order['order_tracking_num']) ? $order['order_tracking_num'] : 'N/A');
        $courierName   = !empty($track['courier_name']) ? $track['courier_name'] : (!empty($order['order_carrier']) ? $order['order_carrier'] : 'Courier');
        $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
        $orderDate     = date('d M Y', strtotime($order['created_at']));
        $orderTime     = date('h:i A', strtotime($order['created_at']));
        $expectedDelivery = !empty($track['estimated_delivery_date']) 
            ? date('d M Y', strtotime($track['estimated_delivery_date'])) 
            : date('d M Y', strtotime($order['created_at'] . ' + 4 days'));

        $clean_number = normalize_whatsapp_phone_number($customerPhone);
        if (empty($clean_number)) return false;

        // Status description / message
        $statusMessage = "Your order #$order_id is currently $orderStatus.";
        if (strtolower($order['status']) === 'shipped') {
            $statusMessage = "Your order has been dispatched via $courierName. Tracking ID: $trackingID";
        } elseif (strtolower($order['status']) === 'delivered') {
            $statusMessage = "Your order has been successfully delivered. Thank you for shopping with us!";
        } elseif (strtolower($order['status']) === 'cancelled') {
            $statusMessage = "Your order has been cancelled. Please contact support for assistance.";
        }

        // Address
        $addressParts = array_filter([
            trim($order['customer_address'] ?? ''),
            trim($order['customer_city'] ?? ''),
            trim($order['customer_state'] ?? ''),
            trim($order['customer_zip'] ?? '')
        ]);
        $deliveryAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';

        // Order Items List
        $itemsList = [];
        $items_res = $conn->query("
            SELECT oi.quantity, oi.price, p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = $order_id
        ");
        if ($items_res && $items_res->num_rows > 0) {
            while ($itm = $items_res->fetch_assoc()) {
                $pName = trim($itm['product_name'] ?? 'Product');
                $qty   = (int)($itm['quantity'] ?? 1);
                $itemsList[] = "• {$pName} ({$qty}x)";
            }
        }
        $itemsOrdered = !empty($itemsList) ? implode("\n", $itemsList) : "Order #$order_id";

        // Expected Delivery Date
        $expectedDelivery = !empty($order['estimated_delivery']) 
            ? date('d M Y', strtotime($order['estimated_delivery'])) 
            : date('d M Y', strtotime($order['created_at'] . ' + 4 days'));

        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
        $orderLink = $siteUrl . '/my-orders.php';

        $paymentMode = formatWhatsAppPaymentMethod($order['payment_method'] ?? '', $order['payment_mode'] ?? '');

        $bridge_template = !empty($settings['message_template']) 
            ? $settings['message_template'] 
            : "Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\nThank you for shopping with us.";

        $replacementValues = [
            '{CustomerName}'     => $customerName,
            '{OrderID}'          => $order_id,
            '{OrderStatus}'      => $orderStatus,
            '{TrackingID}'       => $trackingID,
            '{OrderAmount}'      => $orderAmount,
            '{OrderDate}'        => $orderDate,
            '{PaymentMethod}'    => $paymentMode,
            '{StatusMessage}'    => $statusMessage,
            '{ItemsOrdered}'     => $itemsOrdered,
            '{DeliveryAddress}'  => $deliveryAddress,
            '{ExpectedDelivery}' => $expectedDelivery,
            '{OrderLink}'        => $orderLink
        ];
        
        $message = $bridge_template;
        foreach ($replacementValues as $search => $replace) {
            $message = str_replace($search, $replace, $message);
        }
        
        $token    = trim($settings['api_token']);
        $phone_id = trim($settings['phone_number_id']);
        $url      = "https://graph.facebook.com/v21.0/{$phone_id}/messages";

        $send_meta_curl = function($pay) use ($url, $token) {
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

        $meta_template_name = trim($settings['meta_template_name'] ?? '');
        $header_image_url   = trim($settings['wa_header_image_url'] ?? '');
        $lang_code          = trim($settings['meta_template_lang'] ?? 'en');
        if (empty($lang_code)) $lang_code = 'en';

        $result = '';
        $http_code = 0;
        $curl_error = '';
        $payload = [];
        $sent_successfully = false;

        if (!empty($meta_template_name)) {
            $build_payload = function($tName, $lang, $paramList, $includeHeaderImg) use ($clean_number, $header_image_url) {
                $components = [
                    [
                        "type" => "body",
                        "parameters" => $paramList
                    ]
                ];
                if ($includeHeaderImg && !empty($header_image_url)) {
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
                return [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $clean_number,
                    "type"              => "template",
                    "template"          => [
                        "name"       => $tName,
                        "language"   => ["code" => $lang],
                        "components" => $components
                    ]
                ];
            };

            $safe_cust_name  = !empty($customerName) ? $customerName : 'Valued Customer';
            $safe_order_id   = (string)$order_id;
            $safe_order_date = !empty($orderDate) ? $orderDate : date('d M Y');
            $safe_order_time = !empty($orderTime) ? $orderTime : date('h:i A');
            $safe_status     = !empty($orderStatus) ? $orderStatus : 'Processing';
            $safe_amount     = !empty($orderAmount) ? $orderAmount : '0.00';
            $safe_payment    = !empty($paymentMode) ? $paymentMode : 'COD';
            $safe_tracking   = (!empty($trackingID) && $trackingID !== 'N/A') ? $trackingID : 'TRACKING123';
            $safe_msg        = !empty($statusMessage) ? $statusMessage : "Your order #$order_id status is currently $safe_status.";
            $safe_items      = !empty($itemsOrdered) ? $itemsOrdered : "Order #$order_id";
            $safe_addr       = (!empty($deliveryAddress) && $deliveryAddress !== 'N/A') ? $deliveryAddress : 'Customer Delivery Address';
            $safe_est_date   = !empty($expectedDelivery) ? $expectedDelivery : date('d M Y', strtotime('+4 days'));
            $safe_link       = !empty($orderLink) ? $orderLink : 'https://sagarstarters.com/my-orders.php';

            // Standard Sequential 10-Parameter set (order_status_updated):
            $params_10 = [
                ["type" => "text", "text" => $safe_cust_name], // {{1}} customer_name
                ["type" => "text", "text" => $safe_order_id],   // {{2}} order_id
                ["type" => "text", "text" => $safe_order_date], // {{3}} order_date
                ["type" => "text", "text" => $safe_status],     // {{4}} order_status
                ["type" => "text", "text" => $safe_msg],        // {{5}} status_message
                ["type" => "text", "text" => $safe_items],      // {{6}} order_items
                ["type" => "text", "text" => $safe_amount],     // {{7}} order_total
                ["type" => "text", "text" => $safe_addr],       // {{8}} customer_address
                ["type" => "text", "text" => $safe_est_date],   // {{9}} expected_delivery_date
                ["type" => "text", "text" => $safe_link],       // {{10}} order_link
            ];

            // 5-parameter legacy status set (new_order_status):
            $params_5 = [
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_status],
                ["type" => "text", "text" => $safe_tracking],
                ["type" => "text", "text" => $safe_amount],
            ];

            // Mixed parameter set:
            $params_mixed_10 = [
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_status],
                ["type" => "text", "text" => $safe_items],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_addr],
                ["type" => "text", "text" => $safe_est_date],
                ["type" => "text", "text" => $safe_link],
                ["type" => "text", "text" => $safe_order_date],
                ["type" => "text", "text" => $safe_msg],
            ];

            // 9-parameter set:
            $params_9 = [
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_order_date],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
                ["type" => "text", "text" => $safe_status],
                ["type" => "text", "text" => $safe_items],
                ["type" => "text", "text" => $safe_addr],
                ["type" => "text", "text" => $safe_link],
            ];

            // 11-parameter set:
            $params_11 = [
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_order_date],
                ["type" => "text", "text" => $safe_order_time],
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => (string)$customerPhone],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
                ["type" => "text", "text" => $safe_status],
                ["type" => "text", "text" => $safe_addr],
                ["type" => "text", "text" => $safe_items],
                ["type" => "text", "text" => $safe_link],
            ];

            // 4-parameter set:
            $params_4 = [
                ["type" => "text", "text" => $safe_order_id],
                ["type" => "text", "text" => $safe_cust_name],
                ["type" => "text", "text" => $safe_amount],
                ["type" => "text", "text" => $safe_payment],
            ];

            // Dynamic bridge parameters
            preg_match_all('/\{(CustomerName|OrderID|OrderStatus|TrackingID|OrderAmount|OrderDate|StatusMessage|ItemsOrdered|DeliveryAddress|ExpectedDelivery|OrderLink)\}/', $bridge_template, $matches);
            $params_bridge = [];
            if (!empty($matches[0])) {
                foreach ($matches[0] as $varKey) {
                    $val = (string)($replacementValues[$varKey] ?? '');
                    $params_bridge[] = ["type" => "text", "text" => ($val !== '') ? $val : 'N/A'];
                }
            }

            $candidate_sets = [
                ['params' => $params_10,       'header' => false,                     'lang' => $lang_code],
                ['params' => $params_10,       'header' => !empty($header_image_url), 'lang' => $lang_code],
                ['params' => $params_5,        'header' => false,                     'lang' => $lang_code],
                ['params' => $params_5,        'header' => !empty($header_image_url), 'lang' => $lang_code],
                ['params' => $params_mixed_10, 'header' => false,                     'lang' => $lang_code],
                ['params' => $params_9,        'header' => false,                     'lang' => $lang_code],
                ['params' => $params_11,       'header' => false,                     'lang' => $lang_code],
                ['params' => $params_bridge,   'header' => false,                     'lang' => $lang_code],
                ['params' => $params_10,       'header' => false,                     'lang' => ($lang_code === 'en' ? 'en_US' : 'en')],
                ['params' => $params_5,        'header' => false,                     'lang' => ($lang_code === 'en' ? 'en_US' : 'en')],
            ];

            $tpl_names_to_try = array_unique(array_filter([
                $meta_template_name,
                'order_status_updated',
                'new_order_status',
                'order_status_updates',
                'order_status_update'
            ]));

            foreach ($tpl_names_to_try as $current_tpl_name) {
                foreach ($candidate_sets as $candidate) {
                    if (empty($candidate['params'])) continue;
                    $payload = $build_payload($current_tpl_name, $candidate['lang'], $candidate['params'], $candidate['header']);
                    list($result, $http_code, $curl_error) = $send_meta_curl($payload);
                    $meta_response = json_decode($result, true);

                    if ($http_code == 200 && isset($meta_response['messages'])) {
                        $sent_successfully = true;
                        if ($current_tpl_name !== $meta_template_name && !empty($current_tpl_name)) {
                            $conn->query("UPDATE whatsapp_settings SET meta_template_name = '" . $conn->real_escape_string($current_tpl_name) . "' WHERE id = 1");
                        }
                        break 2;
                    }
                }
            }
        }

        // Final Fallback: Direct text message
        if (!$sent_successfully) {
            $text_payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_number,
                "type"              => "text",
                "text"              => ["preview_url" => false, "body" => $message]
            ];
            list($text_result, $text_code, $text_err) = $send_meta_curl($text_payload);
            $text_meta = json_decode($text_result, true);
            if ($text_code == 200 && isset($text_meta['messages'])) {
                $payload   = $text_payload;
                $result    = $text_result;
                $http_code = $text_code;
                $sent_successfully = true;
            }
        }

        // Always log every API call for diagnosis
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
        $log_entry  = '[' . date('Y-m-d H:i:s') . "] Status-Update Order#$order_id HTTP:{$http_code} To:{$clean_number}" . PHP_EOL;
        $log_entry .= "Payload: " . json_encode($payload) . PHP_EOL;
        $log_entry .= "Response: " . $result . PHP_EOL;
        $log_entry .= str_repeat('-', 60) . PHP_EOL;
        file_put_contents($log_dir . '/whatsapp_api.log', $log_entry, FILE_APPEND);
        
        $status_msg = "";
        if ($curl_error) {
            error_log("[WhatsApp] Status-Send cURL Error Order#$order_id: $curl_error");
            $status_msg = 'Status Failed: cURL - ' . substr($curl_error, 0, 80);
        } else {
            $meta_response = json_decode($result, true);
            if ($http_code == 200 && isset($meta_response['messages'])) {
                $msg_id     = $meta_response['messages'][0]['id'] ?? 'unknown';
                $status_msg = "Sent Status Update: {$orderStatus} (ID: " . substr($msg_id, 0, 20) . ')';
            } else {
                $error_desc = $meta_response['error']['message'] ?? 'Unknown Meta API Error';
                $error_code = $meta_response['error']['code'] ?? 'N/A';
                $status_msg = "Status Failed: (#{$error_code}) " . substr($error_desc, 0, 80);
                file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);
            }
        }
        
        // Log to Database
        $conn->query("INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status) 
            VALUES ($order_id, '$clean_number', '" . $conn->real_escape_string($message) . "', 'api', '" . $conn->real_escape_string($status_msg) . "')");
        
        return $sent_successfully;

    } catch (Exception $e) {
        error_log("[WhatsApp] Status-Send Exception Order#$order_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Unified Automated WhatsApp dispatcher.
 * Backwards compatible with all existing call sites.
 *
 * @param mysqli $conn     Database connection
 * @param int    $order_id The order ID
 * @param string $type     'order_confirmation' or 'status_update'
 * @return bool  True if sent successfully
 */
function sendAutomatedWhatsApp($conn, $order_id, $type = 'status_update') {
    if ($type === 'order_confirmation') {
        return sendCustomerOrderConfirmationWhatsApp($conn, $order_id);
    }
    return sendCustomerOrderStatusWhatsApp($conn, $order_id);
}

/**
 * Send WhatsApp notification to ADMIN when a new order is placed.
 * Fail-safe: errors are logged but never block order completion.
 *
 * @param mysqli $conn    Database connection
 * @param int    $order_id The order ID
 * @return bool  True if sent successfully
 */
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
        $conn->query("ALTER TABLE whatsapp_settings ADD COLUMN IF NOT EXISTS admin_template_name VARCHAR(100) NOT NULL DEFAULT ''");

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

        // ── Deduplication Guard: Prevent duplicate admin new order alerts ──
        $admin_lock_name = "wa_admin_alert_" . intval($order_id);
        $conn->query("SELECT GET_LOCK('$admin_lock_name', 5)");

        $already_sent_admin = false;
        $chk_admin = $conn->query("
            SELECT id FROM whatsapp_logs 
            WHERE order_id = $order_id 
              AND customer_number = '$clean_admin'
              AND (status LIKE '%Admin Alert Sent%' OR status LIKE '%Admin Sent%')
            LIMIT 1
        ");
        if ($chk_admin && $chk_admin->num_rows > 0) {
            $already_sent_admin = true;
        }

        if ($already_sent_admin) {
            $conn->query("SELECT RELEASE_LOCK('$admin_lock_name')");
            error_log("[WhatsApp Admin] Admin order alert already sent for Order #$order_id. Skipping duplicate dispatch.");
            return true;
        }

        // Fetch order details for admin message
        $order_id = intval($order_id);
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_method, o.payment_mode, o.created_at,
                   u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                   u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = $order_id
        ");

        if (!$q || $q->num_rows === 0) return false;
        $order = $q->fetch_assoc();

        // Build variables
        $customerName  = trim($order['customer_name'] ?? 'Customer');
        $customerPhone = trim($order['customer_phone'] ?? 'N/A');
        $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
        $paymentMode   = formatWhatsAppPaymentMethod($order['payment_method'] ?? '', $order['payment_mode'] ?? '');
        $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Pending'));
        $orderDate     = date('d M Y', strtotime($order['created_at']));
        $orderTime     = date('h:i A', strtotime($order['created_at']));
        $orderDateTime = date('d M Y, h:i A', strtotime($order['created_at']));

        // Customer Delivery Address
        $addressParts = array_filter([
            trim($order['customer_address'] ?? ''),
            trim($order['customer_city'] ?? ''),
            trim($order['customer_state'] ?? ''),
            trim($order['customer_zip'] ?? '')
        ]);
        $deliveryAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';

        // Order Items List
        $itemsList = [];
        $items_res = $conn->query("
            SELECT oi.quantity, oi.price, p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = $order_id
        ");
        if ($items_res && $items_res->num_rows > 0) {
            while ($itm = $items_res->fetch_assoc()) {
                $pName = trim($itm['product_name'] ?? 'Product');
                $qty   = (int)($itm['quantity'] ?? 1);
                $itemsList[] = "• {$pName} ({$qty}x)";
            }
        }
        $itemsOrdered = !empty($itemsList) ? implode("\n", $itemsList) : "Order #$order_id";

        // Admin Link
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
        $orderLink = $siteUrl . '/admin/order_details.php?id=' . $order_id;

        $adminMessage  = "🛒 *New Order Alert!*\n\n";
        $adminMessage .= "Order: *#$order_id*\n";
        $adminMessage .= "Date: $orderDate $orderTime\n";
        $adminMessage .= "Customer: $customerName\n";
        $adminMessage .= "Phone: $customerPhone\n";
        $adminMessage .= "Amount: ₹$orderAmount\n";
        $adminMessage .= "Payment: $paymentMode\n";
        $adminMessage .= "Status: $orderStatus\n";
        $adminMessage .= "Address: $deliveryAddress\n\n";
        $adminMessage .= "Items:\n$itemsOrdered\n\n";
        $adminMessage .= "View Order: $orderLink";

        $token    = trim($settings['api_token']);
        $phone_id = trim($settings['phone_number_id']);
        $url      = "https://graph.facebook.com/v21.0/{$phone_id}/messages";

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

        $admin_tpl_name = trim($settings['admin_template_name'] ?? '');
        if (empty($admin_tpl_name)) {
            $admin_tpl_name = trim($settings['order_confirmation_template_name'] ?? '');
        }
        if (empty($admin_tpl_name)) {
            $admin_tpl_name = trim($settings['meta_template_name'] ?? '');
        }
        if (empty($admin_tpl_name)) {
            $admin_tpl_name = 'order_confirmation';
        }

        $header_image_url = trim($settings['wa_header_image_url'] ?? '');
        $lang_code = trim($settings['meta_template_lang'] ?? 'en');
        if (empty($lang_code)) $lang_code = 'en';

        $result = '';
        $http_code = 0;
        $curl_error = '';
        $payload = [];
        $sent_successfully = false;

        if (!empty($admin_tpl_name)) {
            // Helper to build template payload
            $build_tpl_payload = function($tplName, $lang, $paramList, $includeHeaderImg) use ($clean_admin, $header_image_url) {
                $components = [
                    [
                        "type" => "body",
                        "parameters" => $paramList
                    ]
                ];
                if ($includeHeaderImg && !empty($header_image_url)) {
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
                return [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $clean_admin,
                    "type"              => "template",
                    "template"          => [
                        "name"       => $tplName,
                        "language"   => ["code" => $lang],
                        "components" => $components
                    ]
                ];
            };

            // Clean items representation (both newline-separated and comma-separated without newlines for strict Meta templates)
            $clean_items_inline = !empty($itemsList) ? implode(", ", $itemsList) : $itemsOrdered;

            // 11-param format (Default for admin_new_order_alert template)
            $params_11 = [
                ["type" => "text", "text" => (string)$order_id],        // {{1}} order_id
                ["type" => "text", "text" => (string)$orderDate],       // {{2}} order_date
                ["type" => "text", "text" => (string)$orderTime],       // {{3}} order_time
                ["type" => "text", "text" => (string)$customerName],    // {{4}} customer_name
                ["type" => "text", "text" => (string)$customerPhone],   // {{5}} customer_phone
                ["type" => "text", "text" => (string)$orderAmount],     // {{6}} order_total
                ["type" => "text", "text" => (string)$paymentMode],     // {{7}} payment_method
                ["type" => "text", "text" => (string)$orderStatus],     // {{8}} order_status
                ["type" => "text", "text" => (string)$deliveryAddress], // {{9}} customer_address
                ["type" => "text", "text" => (string)$clean_items_inline], // {{10}} order_items (clean inline)
                ["type" => "text", "text" => (string)$orderLink],       // {{11}} order_link
            ];

            // 9-param format (Proven working order_confirmation template)
            $params_9 = [
                ["type" => "text", "text" => (string)$customerName],    // {{1}} customer_name
                ["type" => "text", "text" => (string)$order_id],        // {{2}} order_id
                ["type" => "text", "text" => (string)$orderDate],       // {{3}} order_date
                ["type" => "text", "text" => (string)$orderAmount],     // {{4}} order_total
                ["type" => "text", "text" => (string)$paymentMode],     // {{5}} payment_method
                ["type" => "text", "text" => (string)$orderStatus],     // {{6}} order_status
                ["type" => "text", "text" => (string)$clean_items_inline], // {{7}} order_items
                ["type" => "text", "text" => (string)$deliveryAddress], // {{8}} customer_address
                ["type" => "text", "text" => (string)$orderLink],       // {{9}} order_link
            ];

            // 10-param format (order_status_updates template)
            $params_10 = [
                ["type" => "text", "text" => (string)$customerName],    // {{1}}
                ["type" => "text", "text" => (string)$order_id],        // {{2}}
                ["type" => "text", "text" => (string)$orderDate],       // {{3}}
                ["type" => "text", "text" => (string)$orderStatus],     // {{4}}
                ["type" => "text", "text" => "New order received! Total: ₹{$orderAmount} via {$paymentMode}"], // {{5}}
                ["type" => "text", "text" => (string)$clean_items_inline], // {{6}}
                ["type" => "text", "text" => (string)$orderAmount],     // {{7}}
                ["type" => "text", "text" => (string)$deliveryAddress], // {{8}}
                ["type" => "text", "text" => (string)$orderDate],       // {{9}}
                ["type" => "text", "text" => (string)$orderLink],       // {{10}}
            ];

            // 4-param format (Legacy simple format)
            $params_4 = [
                ["type" => "text", "text" => (string)$order_id],
                ["type" => "text", "text" => (string)$customerName],
                ["type" => "text", "text" => (string)$orderAmount],
                ["type" => "text", "text" => (string)$paymentMode],
            ];
            // 5-param format (Legacy format with phone)
            $params_5 = [
                ["type" => "text", "text" => (string)$order_id],
                ["type" => "text", "text" => (string)$customerName],
                ["type" => "text", "text" => (string)$customerPhone],
                ["type" => "text", "text" => (string)$orderAmount],
                ["type" => "text", "text" => (string)$paymentMode],
            ];

            // List of candidate template names to try in order of relevance
            $tpl_names_to_try = array_unique(array_filter([
                $admin_tpl_name,
                trim($settings['order_confirmation_template_name'] ?? ''),
                'order_confirmation',
                'order_confirmation_notification',
                'new_order_confirmation',
                trim($settings['meta_template_name'] ?? ''),
                'order_status_updates',
                'new_order_status',
                'admin_new_order_alert'
            ]));

            // Build smart candidate payload variations
            foreach ($tpl_names_to_try as $current_tpl_name) {
                // Determine best parameter priority for current template
                if (stripos($current_tpl_name, 'confirm') !== false) {
                    $try_param_sets = [$params_9, $params_11, $params_4, $params_5];
                } elseif (stripos($current_tpl_name, 'status') !== false || stripos($current_tpl_name, 'update') !== false) {
                    $try_param_sets = [$params_10, $params_9, $params_11, $params_4, $params_5];
                } else {
                    $try_param_sets = [$params_11, $params_9, $params_4, $params_5];
                }

                $languages_to_try = array_unique([$lang_code, ($lang_code === 'en' ? 'en_US' : 'en')]);

                foreach ($languages_to_try as $current_lang) {
                    foreach ($try_param_sets as $current_params) {
                        // Try without header first (most compatible), then with header if configured
                        $header_options = [false];
                        if (!empty($header_image_url)) $header_options[] = true;

                        foreach ($header_options as $with_header) {
                            $payload = $build_tpl_payload($current_tpl_name, $current_lang, $current_params, $with_header);
                            list($result, $http_code, $curl_error) = $send_admin_meta($payload);
                            $meta_response = json_decode($result, true);

                            if ($http_code == 200 && isset($meta_response['messages'])) {
                                $sent_successfully = true;
                                if ($current_tpl_name !== $admin_tpl_name && empty($admin_tpl_name)) {
                                    $conn->query("UPDATE whatsapp_settings SET admin_template_name = '" . $conn->real_escape_string($current_tpl_name) . "' WHERE id = 1");
                                }
                                break 4; // Successfully delivered! Break all loops.
                            }
                        }
                    }
                }
            }
        }

        // Final Fallback: Direct text message if template mode was disabled or failed
        if (!$sent_successfully) {
            $text_payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_admin,
                "type"              => "text",
                "text"              => ["preview_url" => false, "body" => $adminMessage]
            ];
            list($text_result, $text_code, $text_err) = $send_admin_meta($text_payload);
            $text_meta = json_decode($text_result, true);
            if ($text_code == 200 && isset($text_meta['messages'])) {
                $payload   = $text_payload;
                $result    = $text_result;
                $http_code = $text_code;
                $sent_successfully = true;
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

        if (isset($admin_lock_name)) {
            $conn->query("SELECT RELEASE_LOCK('$admin_lock_name')");
        }

        return $sent_successfully;

    } catch (Exception $e) {
        if (isset($admin_lock_name)) {
            $conn->query("SELECT RELEASE_LOCK('$admin_lock_name')");
        }
        error_log("[WhatsApp Admin] Exception Order#$order_id: " . $e->getMessage());
        return false;
    }
}
