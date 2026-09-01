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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['test']) && $_GET['test'] == '1') && !(isset($_GET['test_admin']) && $_GET['test_admin'] == '1')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

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
} elseif (isset($_GET['test']) && $_GET['test'] == '1') {
    $sending_mode = 'api';
    $customer_number = $_GET['number'] ?? '';
    $message = "Test message from settings panel.";
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
    
    // Check if testing admin template or customer template
    $admin_tpl_override = trim($_GET['admin_template_name'] ?? ($settings['admin_template_name'] ?? ''));
    if ($is_admin_test && !empty($admin_tpl_override)) {
        $meta_template_name = $admin_tpl_override;
    } else {
        $meta_template_name = $settings['meta_template_name'] ?? '';
    }
    
    // Normalize customer/admin number
    $clean_number = normalize_whatsapp_phone_number($customer_number);
    $url = "https://graph.facebook.com/v21.0/{$phone_id}/messages";
    
    if (!empty($meta_template_name)) {
        // --- TEMPLATE MODE (24/7 Delivery Bypassing 24h Restriction) ---
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_mode, o.created_at, u.name, u.phone,
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
                'tracking_number' => 'TEST123456789'
            ];
        }
        
        $customerName  = trim($order['name'] ?? 'Customer');
        $customerPhone = trim($order['phone'] ?? '+91 9876543210');
        $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
        $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
        $trackingID    = $order['tracking_number'] ?: 'TESTTRACKING123';
        $paymentMode   = strtoupper($order['payment_mode'] ?? 'COD');

        $params = [];
        // If it's the admin template (or admin test), pass the 5 standard admin parameters
        if ($is_admin_test || $meta_template_name === $admin_tpl_override) {
            $params = [
                ["type" => "text", "text" => (string)$order_id],
                ["type" => "text", "text" => (string)$customerName],
                ["type" => "text", "text" => (string)$customerPhone],
                ["type" => "text", "text" => (string)$orderAmount],
                ["type" => "text", "text" => (string)$paymentMode],
            ];
        } else {
            // Customer template parameters mapped from bridge
            $replacementValues = [
                '{CustomerName}' => $customerName,
                '{OrderID}'      => $order['id'] ?? $order_id,
                '{OrderStatus}'  => $orderStatus,
                '{TrackingID}'   => $trackingID,
                '{OrderAmount}'  => $orderAmount
            ];

            preg_match_all('/\{(CustomerName|OrderID|OrderStatus|TrackingID|OrderAmount)\}/', $settings['message_template'], $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $varKey) {
                    $params[] = ["type" => "text", "text" => (string)($replacementValues[$varKey] ?? '')];
                }
            }
        }

        // Build components array (body is always included)
        $components = [
            [
                "type" => "body",
                "parameters" => $params
            ]
        ];
        
        // Add header image component if configured or if admin template
        $header_image_url = trim($settings['wa_header_image_url'] ?? '');
        if (empty($header_image_url) && ($is_admin_test || $meta_template_name === 'admin_new_order_alert')) {
            $header_image_url = 'https://sagarstarters.com/assets/images/auth_banner.jpg';
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

    // Smart Auto-Recovery: if Meta failed because header was expected or unexpected
    if ($http_code != 200 && isset($payload['template'])) {
        $errMsg = $meta_response['error']['message'] ?? '';
        $errDetails = $meta_response['error']['error_data']['details'] ?? '';
        $fullErr = $errMsg . ' ' . $errDetails;

        if (stripos($fullErr, 'expected IMAGE') !== false) {
            // Add image header and retry
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
                list($result, $http_code, $curl_error) = $send_to_meta($payload);
                $meta_response = json_decode($result, true);
            }
        } elseif (stripos($fullErr, 'expected NO_HEADER') !== false || stripos($fullErr, 'unexpected header') !== false) {
            // Remove header and retry
            $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                return ($c['type'] ?? '') !== 'header';
            }));
            list($result, $http_code, $curl_error) = $send_to_meta($payload);
            $meta_response = json_decode($result, true);
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
