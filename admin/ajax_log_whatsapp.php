<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// ── Auth guard: must be logged-in admin ─────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

require_once '../includes/whatsapp_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['test']) && $_GET['test'] == '1') && !(isset($_GET['test_admin']) && $_GET['test_admin'] == '1') && !(isset($_GET['test_order_confirm']) && $_GET['test_order_confirm'] == '1')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$is_admin_test         = false;
$is_order_confirm_test = false;

if (isset($_GET['test_admin']) && $_GET['test_admin'] == '1') {
    // Test Admin Notification
    $admin_number = trim($_GET['number'] ?? '');
    if (empty($admin_number)) {
        echo json_encode(['success' => false, 'error' => 'Please enter admin phone number.']);
        exit;
    }
    
    // Fetch latest order for demo data
    $q = $conn->query("
        SELECT o.id, o.status, o.total_amount, o.payment_mode, o.created_at,
               u.name AS customer_name, u.phone AS customer_phone
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.id DESC LIMIT 1
    ");
    $order = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : [
        'id' => 999,
        'customer_name' => 'Demo Customer',
        'customer_phone' => '+91 9876543210',
        'total_amount' => 1555.00,
        'payment_mode' => 'COD',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $order_id      = (int)$order['id'];
    $customerName  = trim($order['customer_name']);
    $customerPhone = trim($order['customer_phone']);
    $orderAmount   = number_format((float)$order['total_amount'], 2);
    $paymentMode   = strtoupper($order['payment_mode'] ?? 'COD');
    $orderTime     = date('d M Y, h:i A', strtotime($order['created_at']));

    $adminMessage  = "🛒 *TEST Admin New Order Alert!*\n\n";
    $adminMessage .= "Order: *#$order_id*\n";
    $adminMessage .= "Customer: $customerName\n";
    $adminMessage .= "Phone: $customerPhone\n";
    $adminMessage .= "Amount: ₹$orderAmount\n";
    $adminMessage .= "Payment: $paymentMode\n";
    $adminMessage .= "Time: $orderTime\n\n";
    $adminMessage .= "✅ This is a test notification verifying your WhatsApp Admin Alert system.";

    $sending_mode    = 'api';
    $customer_number = $admin_number;
    $message         = $adminMessage;
    $is_admin_test   = true;

} elseif (isset($_GET['test_order_confirm']) && $_GET['test_order_confirm'] == '1') {
    $sending_mode = 'api';
    $customer_number = $_GET['number'] ?? '';
    $message = "Test Customer Order Confirmation notification.";
    $is_order_confirm_test = true;

    $q = $conn->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $order_data = $q ? $q->fetch_assoc() : null;
    $order_id = $order_data['id'] ?? 1;

} elseif (isset($_GET['test']) && $_GET['test'] == '1') {
    $sending_mode = 'api';
    $customer_number = $_GET['number'] ?? '';
    $message = "Test Customer Order Status notification.";
    $is_admin_test = false;
    
    // Fetch latest order for variables
    $q = $conn->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $order_data = $q ? $q->fetch_assoc() : null;
    $order_id = $order_data['id'] ?? 1;

} else {
    $order_id        = intval($_POST['order_id'] ?? 0);
    $customer_number = trim($_POST['customer_number'] ?? '');
    $message         = trim($_POST['message'] ?? '');
    $sending_mode    = trim($_POST['sending_mode'] ?? '');
    $is_admin_test   = false;
}

// Whitelist sending_mode to avoid arbitrary data
$allowed_modes = ['web', 'api'];
if (!in_array($sending_mode, $allowed_modes, true)) {
    $sending_mode = 'web';
}

$status = ($sending_mode === 'api') ? 'Sent via API' : 'Sent via Web';

// ── Prepared statement — prevents SQL injection ──────────────
$stmt = $conn->prepare(
    "INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status)
     VALUES (?, ?, ?, ?, ?)"
);

if ($sending_mode === 'api') {
    // Fetch Complete Settings
    $set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
    $settings = $set_q->fetch_assoc();
    
    if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        $status = "Failed: Missing API Token or Phone Number ID";
        $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'API Token or Phone Number ID is missing in settings.']);
        exit;
    }

    $token = trim($settings['api_token']);
    $phone_id = trim($settings['phone_number_id']);
    
    // Check if testing admin template, order confirm template, or status template
    $post_tpl_type = trim($_POST['template_type'] ?? '');
    if ($is_admin_test) {
        $meta_template_name = trim($_GET['admin_template_name'] ?? ($settings['admin_template_name'] ?? ''));
    } elseif ($is_order_confirm_test || $post_tpl_type === 'confirmation') {
        $meta_template_name = trim($_POST['template_name'] ?? ($_GET['template_name'] ?? ($settings['order_confirmation_template_name'] ?? 'order_confirmation')));
    } else {
        $meta_template_name = trim($_POST['template_name'] ?? ($_GET['template_name'] ?? ($settings['meta_template_name'] ?? 'order_status_updated')));
    }
    
    // Normalize customer/admin number
    $clean_number = normalize_whatsapp_phone_number($customer_number);
    $url = "https://graph.facebook.com/v21.0/{$phone_id}/messages";
    
    if (!empty($meta_template_name)) {
        // --- TEMPLATE MODE (24/7 Delivery Bypassing 24h Restriction) ---
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_mode, o.created_at, u.name, u.phone,
                   u.address as customer_address, u.city as customer_city, u.state as customer_state, u.zip_code as customer_zip,
                   (SELECT tracking_number FROM order_tracking WHERE order_id = o.id LIMIT 1) as tracking_number
            FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $order_id
        ");
        $order = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : null;
        
        // Failsafe for test mode (if order doesn't exist)
        if (!$order) {
            $order = [
                'id' => $order_id,
                'status' => 'processing',
                'total_amount' => 1999.00,
                'payment_mode' => 'COD',
                'created_at' => date('Y-m-d H:i:s'),
                'name' => 'Demo Customer',
                'phone' => '+91 9876543210',
                'customer_address' => '123 Civil Lines',
                'customer_city' => 'Varanasi',
                'customer_state' => 'Uttar Pradesh',
                'customer_zip' => '221001',
                'tracking_number' => 'TEST123456789'
            ];
        }
        
        $customerName  = trim($order['name'] ?? 'Customer');
        $customerPhone = trim($order['phone'] ?? '+91 9876543210');
        $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
        $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
        $trackingID    = $order['tracking_number'] ?: 'TESTTRACKING123';
        $paymentMode   = strtoupper($order['payment_mode'] ?? 'COD');
        $orderDate     = date('d M Y', strtotime($order['created_at'] ?? 'now'));
        $orderTime     = date('h:i A', strtotime($order['created_at'] ?? 'now'));

        // Address
        $addressParts = array_filter([
            trim($order['customer_address'] ?? ''),
            trim($order['customer_city'] ?? ''),
            trim($order['customer_state'] ?? ''),
            trim($order['customer_zip'] ?? '')
        ]);
        $deliveryAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Varanasi, UP - 221001';

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
        $itemsOrdered = !empty($itemsList) ? implode("\n", $itemsList) : "• 1-Phase Submersible Panel (1x)";

        // Admin Link
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
        $orderLink = $siteUrl . '/admin/order_details.php?id=' . $order_id;

        $statusMessage = "Your order #$order_id is currently $orderStatus.";
        if (strtolower($order['status'] ?? '') === 'shipped') {
            $statusMessage = "Your order has been dispatched via Courier. Tracking ID: $trackingID";
        } elseif (strtolower($order['status'] ?? '') === 'delivered') {
            $statusMessage = "Your order has been successfully delivered. Thank you for shopping with us!";
        }

        $expectedDelivery = date('d M Y', strtotime($order['created_at'] . ' + 4 days'));

        // Standard Parameter Sets:
        // 1. Order Confirmation (9 Parameters)
        $params_9 = [
            ["type" => "text", "text" => (string)$customerName],    // {{1}} customer_name
            ["type" => "text", "text" => (string)$order_id],        // {{2}} order_id
            ["type" => "text", "text" => (string)$orderDate],       // {{3}} order_date
            ["type" => "text", "text" => (string)$orderAmount],     // {{4}} order_total
            ["type" => "text", "text" => (string)$paymentMode],     // {{5}} payment_method
            ["type" => "text", "text" => (string)$orderStatus],     // {{6}} order_status
            ["type" => "text", "text" => (string)$itemsOrdered],    // {{7}} order_items
            ["type" => "text", "text" => (string)$deliveryAddress], // {{8}} customer_address
            ["type" => "text", "text" => (string)$orderLink],       // {{9}} order_link
        ];

        // 2. Order Status Update (10 Parameters)
        $params_10 = [
            ["type" => "text", "text" => (string)$customerName],    // {{1}} customer_name
            ["type" => "text", "text" => (string)$order_id],        // {{2}} order_id
            ["type" => "text", "text" => (string)$orderDate],       // {{3}} order_date
            ["type" => "text", "text" => (string)$orderStatus],     // {{4}} order_status
            ["type" => "text", "text" => (string)$statusMessage],   // {{5}} status_message
            ["type" => "text", "text" => (string)$itemsOrdered],    // {{6}} order_items
            ["type" => "text", "text" => (string)$orderAmount],     // {{7}} order_total
            ["type" => "text", "text" => (string)$deliveryAddress], // {{8}} customer_address
            ["type" => "text", "text" => (string)$expectedDelivery],// {{9}} expected_delivery_date
            ["type" => "text", "text" => (string)$orderLink],       // {{10}} order_link
        ];

        // 3. Admin New Order Alert (11 Parameters)
        $params_11 = [
            ["type" => "text", "text" => (string)$order_id],        // {{1}}
            ["type" => "text", "text" => (string)$orderDate],       // {{2}}
            ["type" => "text", "text" => (string)$orderTime],       // {{3}}
            ["type" => "text", "text" => (string)$customerName],    // {{4}}
            ["type" => "text", "text" => (string)$customerPhone],   // {{5}}
            ["type" => "text", "text" => (string)$orderAmount],     // {{6}}
            ["type" => "text", "text" => (string)$paymentMode],     // {{7}}
            ["type" => "text", "text" => (string)$orderStatus],     // {{8}}
            ["type" => "text", "text" => (string)$deliveryAddress], // {{9}}
            ["type" => "text", "text" => (string)$itemsOrdered],    // {{10}}
            ["type" => "text", "text" => (string)$orderLink],       // {{11}}
        ];

        // 4. Legacy 4 and 5 Parameters
        $params_4 = [
            ["type" => "text", "text" => (string)$order_id],
            ["type" => "text", "text" => (string)$customerName],
            ["type" => "text", "text" => (string)$orderAmount],
            ["type" => "text", "text" => (string)$paymentMode],
        ];

        $params_5 = [
            ["type" => "text", "text" => (string)$customerName],
            ["type" => "text", "text" => (string)$order_id],
            ["type" => "text", "text" => (string)$orderStatus],
            ["type" => "text", "text" => (string)$trackingID],
            ["type" => "text", "text" => (string)$orderAmount],
        ];

        // Select initial parameter set based on test type and template name
        $params = [];
        if ($is_admin_test || (!empty($admin_tpl_override) && $meta_template_name === $admin_tpl_override)) {
            $params = $params_11;
        } elseif ($is_order_confirm_test || stripos($meta_template_name, 'confirm') !== false) {
            $params = $params_9;
        } elseif (stripos($meta_template_name, 'status') !== false || stripos($meta_template_name, 'update') !== false) {
            $params = $params_10;
        } else {
            // Customer template parameters mapped from bridge
            $replacementValues = [
                '{CustomerName}'     => $customerName,
                '{OrderID}'          => $order['id'] ?? $order_id,
                '{OrderStatus}'      => $orderStatus,
                '{TrackingID}'       => $trackingID,
                '{OrderAmount}'      => $orderAmount,
                '{OrderDate}'        => $orderDate,
                '{PaymentMethod}'    => $paymentMode,
                '{DeliveryAddress}'  => $deliveryAddress,
                '{ItemsOrdered}'     => $itemsOrdered,
                '{ExpectedDelivery}' => $expectedDelivery,
                '{OrderLink}'        => $orderLink
            ];

            preg_match_all('/\{(CustomerName|OrderID|OrderStatus|TrackingID|OrderAmount|OrderDate|PaymentMethod|DeliveryAddress|ItemsOrdered|ExpectedDelivery|OrderLink)\}/', $settings['message_template'], $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $varKey) {
                    $params[] = ["type" => "text", "text" => (string)($replacementValues[$varKey] ?? '')];
                }
            } else {
                $params = $params_10;
            }
        }

        // Build components array (body is always included)
        $components = [
            [
                "type" => "body",
                "parameters" => $params
            ]
        ];
        
        // Add header image component ONLY if configured in settings
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
                "name"       => trim($meta_template_name),
                "language"   => ["code" => trim($settings['meta_template_lang'] ?? 'en')],
                "components" => $components,
            ]
        ];
    } else {
        // --- PLAIN TEXT MODE ---
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $clean_number,
            "type"              => "text",
            "text"              => ["preview_url" => false, "body" => $message]
        ];
    }

    $send_to_meta = function($pay) use ($url, $token) {
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

    list($result, $http_code, $curl_error) = $send_to_meta($payload);
    $meta_response = json_decode($result, true);

    // Smart Multi-Tier Auto-Recovery
    if ($http_code != 200 && isset($payload['template'])) {
        $errMsg     = $meta_response['error']['message'] ?? '';
        $errDetails = $meta_response['error']['error_data']['details'] ?? '';
        $errCode    = (int)($meta_response['error']['code'] ?? 0);
        $fullErr    = $errMsg . ' ' . $errDetails;

        // Auto-Recovery A: Parameter count mismatch
        if ($errCode == 132000 || stripos($fullErr, 'parameter') !== false || stripos($fullErr, 'placeholder') !== false) {
            $all_param_sets = [$params_9, $params_10, $params_11, $params_5, $params_4];
            foreach ($all_param_sets as $try_set) {
                if ($try_set === $params) continue; // Already tried
                $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                    return ($c['type'] ?? '') !== 'header'; // Strip header during retry to avoid header conflict
                }));
                // Set body parameters
                $payload['template']['components'][0] = [
                    "type" => "body",
                    "parameters" => $try_set
                ];
                list($result, $http_code, $curl_error) = $send_to_meta($payload);
                $meta_response = json_decode($result, true);
                if ($http_code == 200 && isset($meta_response['messages'])) {
                    break;
                }
            }
        }

        // Auto-Recovery B: Header expected or unexpected
        if ($http_code != 200) {
            if (stripos($fullErr, 'expected IMAGE') !== false || (stripos($fullErr, 'header') !== false && stripos($fullErr, 'expected') !== false)) {
                $fallback_img = !empty($header_image_url) ? $header_image_url : 'https://sagarstarters.com/assets/images/auth_banner.jpg';
                $has_header = false;
                foreach ($payload['template']['components'] as $c) {
                    if (($c['type'] ?? '') === 'header') { $has_header = true; break; }
                }
                if (!$has_header) {
                    array_unshift($payload['template']['components'], [
                        "type" => "header",
                        "parameters" => [["type" => "image", "image" => ["link" => $fallback_img]]]
                    ]);
                    list($result, $http_code, $curl_error) = $send_to_meta($payload);
                    $meta_response = json_decode($result, true);
                }
            } elseif (stripos($fullErr, 'expected NO_HEADER') !== false || stripos($fullErr, 'unexpected header') !== false || stripos($fullErr, 'format: TEXT') !== false || stripos($fullErr, 'components[0]') !== false) {
                $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                    return ($c['type'] ?? '') !== 'header';
                }));
                list($result, $http_code, $curl_error) = $send_to_meta($payload);
                $meta_response = json_decode($result, true);
            }
        }

        // Auto-Recovery C: Language code mismatch (en vs en_US)
        if ($http_code != 200 && ($errCode == 132001 || stripos($fullErr, 'does not exist') !== false || stripos($fullErr, 'language') !== false)) {
            $curr_lang = $payload['template']['language']['code'] ?? 'en';
            $payload['template']['language']['code'] = ($curr_lang === 'en') ? 'en_US' : 'en';
            list($result, $http_code, $curl_error) = $send_to_meta($payload);
            $meta_response = json_decode($result, true);
        }

        // Auto-Recovery D: Template does not exist (Error 132001) — Try approved order_confirmation or status template fallback
        if ($http_code != 200 && ($errCode == 132001 || stripos($fullErr, 'does not exist') !== false)) {
            $fallback_tpl_candidates = array_unique(array_filter([
                trim($settings['order_confirmation_template_name'] ?? ''),
                'order_confirmation',
                trim($settings['meta_template_name'] ?? ''),
                'order_status_updates'
            ]));
            foreach ($fallback_tpl_candidates as $fb_tpl) {
                if ($fb_tpl === $meta_template_name) continue;
                $payload['template']['name'] = $fb_tpl;
                $fb_params = (stripos($fb_tpl, 'confirm') !== false) ? $params_9 : $params_10;
                $payload['template']['components'] = [
                    [
                        "type" => "body",
                        "parameters" => $fb_params
                    ]
                ];
                list($result, $http_code, $curl_error) = $send_to_meta($payload);
                $meta_response = json_decode($result, true);
                if ($http_code == 200 && isset($meta_response['messages'])) {
                    $meta_template_name = $fb_tpl;
                    break;
                }
            }
        }
    }

    // Always log every API call for diagnosis
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_entry = '[' . date('Y-m-d H:i:s') . "] Manual/Test WhatsApp to:{$customer_number} HTTP:{$http_code}" . PHP_EOL;
    $log_entry .= "Payload: " . json_encode($payload) . PHP_EOL;
    $log_entry .= "Response: " . $result . PHP_EOL;
    $log_entry .= str_repeat('-', 60) . PHP_EOL;
    file_put_contents($log_dir . '/whatsapp_api.log', $log_entry, FILE_APPEND);

    if ($curl_error) {
        $status = "Failed: cURL error - " . substr($curl_error, 0, 100);
        $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
        $stmt->execute(); $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Network error: ' . $curl_error]);

    } elseif ($http_code == 200 && isset($meta_response['messages'])) {
        $msg_id     = $meta_response['messages'][0]['id'] ?? 'unknown';
        $msg_status = $meta_response['messages'][0]['message_status'] ?? 'accepted';
        $status     = 'Sent via Meta API (ID: ' . substr($msg_id, 0, 30) . ')';
        $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
        $stmt->execute(); $stmt->close();
        echo json_encode([
            'success'        => true,
            'message_id'     => $msg_id,
            'message_status' => $msg_status,
        ]);

    } else {
        $error_desc = $meta_response['error']['message'] ?? 'Unknown Meta API Error';
        $error_code = $meta_response['error']['code'] ?? 'N/A';
        $error_data = $meta_response['error']['error_data']['details'] ?? '';
        $status     = "Failed API: (#{$error_code}) " . substr($error_desc, 0, 100);
        
        // Also log to error-specific file
        file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);

        $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
        $stmt->execute(); $stmt->close();
        echo json_encode([
            'success'    => false,
            'error'      => "Meta API Error (#{$error_code}): " . $error_desc,
            'error_code' => $error_code,
            'details'    => $error_data,
        ]);
    }
} else {
    // Web Mode
    $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    $stmt->close();
}
