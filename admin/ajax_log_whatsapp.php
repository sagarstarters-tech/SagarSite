<?php
ob_start();
@error_reporting(0);
@ini_set('display_errors', '0');

include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Re-assert error suppression in case config.php enabled display_errors
@error_reporting(0);
@ini_set('display_errors', '0');

function send_whatsapp_json(array $data, int $statusCode = 200): void {
    if (ob_get_level()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data);
    exit;
}

// ── Auth guard: must be logged-in admin ─────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    send_whatsapp_json(['success' => false, 'error' => 'Permission denied'], 403);
}

require_once __DIR__ . '/../includes/whatsapp_functions.php';

// Fetch Complete Settings
$set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
$settings = $set_q ? $set_q->fetch_assoc() : [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['test']) && $_GET['test'] == '1') && !(isset($_GET['test_admin']) && $_GET['test_admin'] == '1') && !(isset($_GET['test_order_confirm']) && $_GET['test_order_confirm'] == '1')) {
    send_whatsapp_json(['success' => false, 'error' => 'Invalid request method']);
}

$is_admin_test         = false;
$is_order_confirm_test = false;

if (isset($_GET['test_admin']) && $_GET['test_admin'] == '1') {
    // Test Admin Notification
    $admin_number = trim($_GET['number'] ?? '');
    if (empty($admin_number)) {
        send_whatsapp_json(['success' => false, 'error' => 'Please enter admin phone number.']);
    }
    
    // Fetch latest order for demo data
    $q = $conn->query("
        SELECT o.id, o.status, o.total_amount, o.payment_mode, o.payment_method, o.created_at,
               u.name AS customer_name, u.phone AS customer_phone,
               u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
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
        'payment_method' => 'cod',
        'status' => 'Pending',
        'created_at' => date('Y-m-d H:i:s'),
        'customer_address' => '123 Civil Lines',
        'customer_city' => 'Varanasi',
        'customer_state' => 'UP',
        'customer_zip' => '221001'
    ];

    $order_id        = (int)$order['id'];
    $customerName    = trim($order['customer_name'] ?? 'Demo Customer');
    $customerPhone   = trim($order['customer_phone'] ?? '+91 9876543210');
    $orderAmount     = number_format((float)($order['total_amount'] ?? 0), 2);
    $paymentMode     = strtoupper($order['payment_mode'] ?? ($order['payment_method'] ?? 'COD'));
    $orderDate       = date('d M Y', strtotime($order['created_at'] ?? 'now'));
    $orderTime       = date('h:i A', strtotime($order['created_at'] ?? 'now'));
    $orderDateTime   = date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now'));
    $orderStatus     = ucwords(str_replace('_', ' ', $order['status'] ?? 'Pending'));
    $deliveryAddress = trim(($order['customer_address'] ?? '') . ', ' . ($order['customer_city'] ?? ''));
    if (empty($deliveryAddress)) $deliveryAddress = 'Varanasi, UP - 221001';
    $itemsOrdered    = "• 1-Phase Submersible Starter Panel (1x)";
    $siteUrl         = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
    $orderLink       = $siteUrl . '/admin/order_details.php?id=' . $order_id;

    $default_admin_alert_tpl = "🛒 *New Order Alert!*\n\nOrder: *#{OrderID}*\nDate: {OrderDate} {OrderTime}\nCustomer: {CustomerName}\nPhone: {CustomerPhone}\nAmount: ₹{OrderAmount}\nPayment: {PaymentMethod}\nStatus: {OrderStatus}\nAddress: {DeliveryAddress}\n\nItems:\n{ItemsOrdered}\n\nView Order: {OrderLink}";
    $admin_bridge_tpl = !empty($settings['admin_message_template']) ? $settings['admin_message_template'] : $default_admin_alert_tpl;

    $adminData = [
        'order_id'         => $order_id,
        'order_date'       => $orderDate,
        'order_time'       => $orderTime,
        'order_datetime'   => $orderDateTime,
        'customer_name'    => $customerName,
        'customer_phone'   => $customerPhone,
        'order_amount'     => $orderAmount,
        'payment_method'   => $paymentMode,
        'order_status'     => $orderStatus,
        'delivery_address' => $deliveryAddress,
        'items_ordered'    => $itemsOrdered,
        'order_link'       => $orderLink
    ];

    $adminMessage = compileWhatsAppTemplate($admin_bridge_tpl, $adminData);

    $sending_mode    = 'api';
    $customer_number = $admin_number;
    $message         = $adminMessage;
    $is_admin_test   = true;

} elseif (isset($_GET['test_order_confirm']) && $_GET['test_order_confirm'] == '1') {
    $sending_mode = 'api';
    $customer_number = $_GET['number'] ?? '';
    $is_order_confirm_test = true;

    $q = $conn->query("
        SELECT o.id, o.status, o.total_amount, o.payment_mode, o.payment_method, o.created_at,
               u.name AS customer_name, u.phone AS customer_phone,
               u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.id DESC LIMIT 1
    ");
    $order = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : [
        'id' => 999,
        'customer_name' => 'Demo Customer',
        'customer_phone' => '+91 9876543210',
        'total_amount' => 1999.00,
        'payment_mode' => 'COD',
        'payment_method' => 'cod',
        'status' => 'Pending',
        'created_at' => date('Y-m-d H:i:s'),
        'customer_address' => '123 Civil Lines',
        'customer_city' => 'Varanasi',
        'customer_state' => 'UP',
        'customer_zip' => '221001'
    ];

    $order_id        = (int)$order['id'];
    $customerName    = trim($order['customer_name'] ?? 'Valued Customer');
    $customerPhone   = trim($order['customer_phone'] ?? '+91 9876543210');
    $orderAmount     = number_format((float)($order['total_amount'] ?? 0), 2);
    $paymentMode     = strtoupper($order['payment_mode'] ?? ($order['payment_method'] ?? 'COD'));
    $orderDate       = date('d M Y', strtotime($order['created_at'] ?? 'now'));
    $orderTime       = date('h:i A', strtotime($order['created_at'] ?? 'now'));
    $orderDateTime   = date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now'));
    $orderStatus     = ucwords(str_replace('_', ' ', $order['status'] ?? 'Confirmed'));
    $deliveryAddress = trim(($order['customer_address'] ?? '') . ', ' . ($order['customer_city'] ?? ''));
    if (empty($deliveryAddress)) $deliveryAddress = 'Varanasi, UP - 221001';
    $itemsOrdered    = "• 1-Phase Submersible Panel (1x)";
    $siteUrl         = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
    $orderLink       = $siteUrl . '/my-orders.php?order_id=' . (int)$order_id;

    $default_order_confirm_tpl = "Hello Dear {CustomerName},\n\nThank you for your order! Your Order #{OrderID} has been successfully placed.\n\nOrder Date: {OrderDate}\nTotal Amount: ₹{OrderAmount}\nPayment Method: {PaymentMethod}\n\nDelivery Address:\n{DeliveryAddress}\n\nThank you for shopping with Sagar Starter's!";
    $confirm_bridge_tpl = !empty($settings['order_confirmation_message_template']) ? $settings['order_confirmation_message_template'] : $default_order_confirm_tpl;

    $confirmData = [
        'customer_name'          => $customerName,
        'order_id'               => $order_id,
        'order_date'             => $orderDate,
        'order_time'             => $orderTime,
        'order_datetime'         => $orderDateTime,
        'order_amount'           => $orderAmount,
        'payment_method'         => $paymentMode,
        'order_status'           => $orderStatus,
        'delivery_address'       => $deliveryAddress,
        'items_ordered'          => $itemsOrdered,
        'order_link'             => $orderLink,
        'customer_phone'         => $customerPhone,
        'expected_delivery_date' => date('d M Y', strtotime('+4 days'))
    ];

    $message = compileWhatsAppTemplate($confirm_bridge_tpl, $confirmData);

} elseif (isset($_GET['test']) && $_GET['test'] == '1') {
    $sending_mode = 'api';
    $customer_number = $_GET['number'] ?? '';
    $is_admin_test = false;
    
    // Fetch latest order for variables
    $q = $conn->query("
        SELECT o.id, o.status, o.total_amount, o.payment_mode, o.created_at,
               u.name AS customer_name, u.phone AS customer_phone,
               (SELECT tracking_number FROM order_tracking WHERE order_id = o.id LIMIT 1) as tracking_number
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.id DESC LIMIT 1
    ");
    $order = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : [
        'id' => 999,
        'customer_name' => 'Demo Customer',
        'customer_phone' => '+91 9876543210',
        'total_amount' => 1999.00,
        'status' => 'processing',
        'created_at' => date('Y-m-d H:i:s'),
        'tracking_number' => 'TESTTRACKING123'
    ];

    $order_id      = (int)$order['id'];
    $customerName  = trim($order['customer_name'] ?? 'Customer');
    $customerPhone = trim($order['customer_phone'] ?? '+91 9876543210');
    $orderAmount   = number_format((float)($order['total_amount'] ?? 0), 2);
    $orderStatus   = ucwords(str_replace('_', ' ', $order['status'] ?? 'Processing'));
    $trackingID    = !empty($order['tracking_number']) ? $order['tracking_number'] : 'TESTTRACKING123';
    $orderDate     = date('d M Y', strtotime($order['created_at'] ?? 'now'));
    $orderTime     = date('h:i A', strtotime($order['created_at'] ?? 'now'));
    $orderDateTime = date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now'));
    $paymentMode   = strtoupper($order['payment_mode'] ?? 'COD');
    $deliveryAddress = '123 Civil Lines, Varanasi, UP - 221001';
    $siteUrl       = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
    $orderLink     = $siteUrl . '/my-orders.php?order_id=' . (int)$order_id;
    $statusMessage = "Your order #$order_id status has been updated to $orderStatus.";
    $itemsOrdered  = "• 1-Phase Submersible Panel (1x)";
    $expectedDelivery = date('d M Y', strtotime('+4 days'));

    $default_status_tpl = "Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\nThank you for shopping with us.";
    $status_bridge_tpl = !empty($settings['message_template']) ? $settings['message_template'] : $default_status_tpl;

    $statusData = [
        'customer_name'          => $customerName,
        'order_id'               => $order_id,
        'order_status'           => $orderStatus,
        'tracking_id'            => $trackingID,
        'order_amount'           => $orderAmount,
        'order_date'             => $orderDate,
        'order_time'             => $orderTime,
        'order_datetime'         => $orderDateTime,
        'payment_method'         => $paymentMode,
        'status_message'         => $statusMessage,
        'items_ordered'          => $itemsOrdered,
        'delivery_address'       => $deliveryAddress,
        'expected_delivery_date' => $expectedDelivery,
        'order_link'             => $orderLink,
        'customer_phone'         => $customerPhone
    ];

    $message = compileWhatsAppTemplate($status_bridge_tpl, $statusData);

} else {
    $order_id        = intval($_POST['order_id'] ?? 0);
    $customer_number = trim($_POST['customer_number'] ?? '');
    $message         = trim($_POST['message'] ?? '');
    $sending_mode    = trim($_POST['sending_mode'] ?? '');
    $is_admin_test   = false;

    // Failsafe: If the submitted message contains any unresolved {tag} or {{tag}}, compile it with order details
    if ($order_id > 0 && preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}|\{\s*[a-zA-Z0-9_]+\s*\}/', $message)) {
        $q = $conn->query("
            SELECT o.id, o.status, o.total_amount, o.payment_mode, o.payment_method, o.created_at,
                   u.name AS customer_name, u.phone AS customer_phone,
                   u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip,
                   (SELECT tracking_number FROM order_tracking WHERE order_id = o.id LIMIT 1) as tracking_number
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.id = $order_id LIMIT 1
        ");
        if ($q && $q->num_rows > 0) {
            $ord = $q->fetch_assoc();
            $cName = trim($ord['customer_name'] ?? 'Customer');
            $cPhone = trim($ord['customer_phone'] ?? '');
            $oAmount = number_format((float)($ord['total_amount'] ?? 0), 2);
            $oStatus = ucwords(str_replace('_', ' ', $ord['status'] ?? 'Processing'));
            $tId = $ord['tracking_number'] ?: 'N/A';
            $pMode = formatWhatsAppPaymentMethod($ord['payment_method'] ?? '', $ord['payment_mode'] ?? '');
            $oDate = date('d M Y', strtotime($ord['created_at'] ?? 'now'));
            $oTime = date('h:i A', strtotime($ord['created_at'] ?? 'now'));
            $oDateTime = date('d M Y, h:i A', strtotime($ord['created_at'] ?? 'now'));
            $addr = trim(($ord['customer_address'] ?? '') . ', ' . ($ord['customer_city'] ?? ''));
            if (empty($addr)) $addr = 'N/A';
            $sUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
            $oLink = $sUrl . '/my-orders.php?order_id=' . (int)$order_id;
            $expDel = date('d M Y', strtotime($ord['created_at'] . ' + 4 days'));
            $sMsg = "Your order #$order_id is currently $oStatus.";

            // Items
            $itms = [];
            $iq = $conn->query("SELECT oi.quantity, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
            if ($iq) {
                while ($ir = $iq->fetch_assoc()) {
                    $itms[] = "• " . ($ir['name'] ?? 'Product') . " (" . (int)$ir['quantity'] . "x)";
                }
            }
            $iList = !empty($itms) ? implode("\n", $itms) : "Order #$order_id";

            $message = compileWhatsAppTemplate($message, [
                'customer_name'          => $cName,
                'order_id'               => $order_id,
                'order_status'           => $oStatus,
                'tracking_id'            => $tId,
                'order_amount'           => $oAmount,
                'order_date'             => $oDate,
                'order_time'             => $oTime,
                'order_datetime'         => $oDateTime,
                'payment_method'         => $pMode,
                'status_message'         => $sMsg,
                'items_ordered'          => $iList,
                'delivery_address'       => $addr,
                'expected_delivery_date' => $expDel,
                'order_link'             => $oLink,
                'customer_phone'         => $cPhone
            ]);
        }
    }
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
    if (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        $status = "Failed: Missing API Token or Phone Number ID";
        try {
            $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {}
        send_whatsapp_json(['success' => false, 'error' => 'API Token or Phone Number ID is missing in settings.']);
    }

    $token = trim($settings['api_token']);
    $phone_id = trim($settings['phone_number_id']);
    
    // Check if testing admin template, order confirm template, or status template
    // If empty or left blank, DO NOT default to a template name! Keep empty so Fallback Template is used.
    $post_tpl_type = trim($_POST['template_type'] ?? '');
    if ($is_admin_test) {
        $meta_template_name = isset($_GET['admin_template_name']) ? trim($_GET['admin_template_name']) : trim($settings['admin_template_name'] ?? '');
        if (empty($meta_template_name)) {
            $meta_template_name = trim($settings['order_confirmation_template_name'] ?? '');
            if (empty($meta_template_name)) {
                $meta_template_name = 'order_confirmation';
            }
        }
    } elseif ($is_order_confirm_test || $post_tpl_type === 'confirmation') {
        $meta_template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : (isset($_GET['template_name']) ? trim($_GET['template_name']) : trim($settings['order_confirmation_template_name'] ?? ''));
    } else {
        $meta_template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : (isset($_GET['template_name']) ? trim($_GET['template_name']) : trim($settings['meta_template_name'] ?? ''));
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

        // 4. Legacy 4, 5, and 6 Parameters
        $params_4 = [
            ["type" => "text", "text" => (string)$order_id],
            ["type" => "text", "text" => (string)$customerName],
            ["type" => "text", "text" => (string)$orderAmount],
            ["type" => "text", "text" => (string)$paymentMode],
        ];

        // 5-parameter set (new_order_status - 100% verified working in Meta)
        $params_5 = [
            ["type" => "text", "text" => (string)$customerName], // {{1}} customer_name
            ["type" => "text", "text" => (string)$order_id],     // {{2}} order_id
            ["type" => "text", "text" => (string)$orderStatus], // {{3}} order_status
            ["type" => "text", "text" => (string)$trackingID],  // {{4}} tracking_id
            ["type" => "text", "text" => (string)$orderAmount], // {{5}} order_total
        ];

        // 6-parameter set (order_status_update)
        $params_6 = [
            ["type" => "text", "text" => (string)$customerName], // {{1}} customer_name
            ["type" => "text", "text" => (string)$order_id],     // {{2}} order_id
            ["type" => "text", "text" => (string)$orderStatus], // {{3}} order_status
            ["type" => "text", "text" => (string)$trackingID],  // {{4}} tracking_id
            ["type" => "text", "text" => (string)$orderAmount], // {{5}} order_total
            ["type" => "text", "text" => (string)$customerName], // {{6}} customer_name/store
        ];

        $get_params_by_count = function($c) use ($params_4, $params_5, $params_6, $params_9, $params_10, $params_11) {
            switch ((int)$c) {
                case 4:  return $params_4;
                case 5:  return $params_5;
                case 6:  return $params_6;
                case 9:  return $params_9;
                case 10: return $params_10;
                case 11: return $params_11;
                default: return [];
            }
        };

        // Select initial parameter set based on test type and template name
        $params = [];
        if ($is_order_confirm_test || stripos($meta_template_name, 'confirm') !== false) {
            $params = $params_9;
        } elseif ($meta_template_name === 'new_order_status') {
            $params = $params_5;
        } elseif ($meta_template_name === 'order_status_update') {
            $params = $params_6;
        } elseif ($meta_template_name === 'order_status_updated') {
            $params = $params_10;
        } elseif ($is_admin_test) {
            if ($meta_template_name === 'admin_new_order_alert') {
                $params = $params_11;
            } elseif ($meta_template_name === 'new_order_status') {
                $params = $params_5;
            } else {
                $params = $params_9; // order_confirmation 9 parameters
            }
        } elseif (stripos($meta_template_name, 'status') !== false || stripos($meta_template_name, 'update') !== false) {
            $params = $params_5; // Default status to tested working 5-param format
        } else {
            $params = $params_9;
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
            // First: check if Meta error message specifies the exact number of expected params
            if (preg_match('/expected (?:number of )?params? \(?(\d+)\)?/i', $fullErr, $pm)) {
                $matched_params = $get_params_by_count($pm[1]);
                if (!empty($matched_params)) {
                    $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                        return ($c['type'] ?? '') !== 'header';
                    }));
                    $payload['template']['components'][0] = [
                        "type" => "body",
                        "parameters" => $matched_params
                    ];
                    list($result, $http_code, $curl_error) = $send_to_meta($payload);
                    $meta_response = json_decode($result, true);
                }
            }

            // If still not delivered, systematically test verified candidates
            if ($http_code != 200) {
                $all_param_sets = [$params_5, $params_9, $params_6, $params_10, $params_11, $params_4];
                foreach ($all_param_sets as $try_set) {
                    if ($try_set === $params) continue; // Already tried
                    $payload['template']['components'] = array_values(array_filter($payload['template']['components'], function($c) {
                        return ($c['type'] ?? '') !== 'header'; // Strip header during retry
                    }));
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

        // Auto-Recovery D: Template does not exist (Error 132001) — Try verified approved templates
        if ($http_code != 200 && ($errCode == 132001 || stripos($fullErr, 'does not exist') !== false)) {
            $fallback_tpl_candidates = array_unique(array_filter([
                'new_order_status',
                trim($settings['order_confirmation_template_name'] ?? ''),
                'order_confirmation',
                'order_status_update'
            ]));
            foreach ($fallback_tpl_candidates as $fb_tpl) {
                if ($fb_tpl === $meta_template_name) continue;
                $payload['template']['name'] = $fb_tpl;
                if ($fb_tpl === 'new_order_status') {
                    $fb_params = $params_5;
                } elseif (stripos($fb_tpl, 'confirm') !== false) {
                    $fb_params = $params_9;
                } elseif ($fb_tpl === 'order_status_update') {
                    $fb_params = $params_6;
                } else {
                    $fb_params = $params_10;
                }
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
        try {
            $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {}
        send_whatsapp_json(['success' => false, 'error' => 'Network error: ' . $curl_error]);

    } elseif ($http_code == 200 && isset($meta_response['messages'])) {
        $msg_id     = $meta_response['messages'][0]['id'] ?? 'unknown';
        $msg_status = $meta_response['messages'][0]['message_status'] ?? 'accepted';

        $is_template_sent = isset($payload['template']['name']);
        $used_template    = $is_template_sent ? $payload['template']['name'] : '';

        if ($is_template_sent) {
            $status = 'Sent via Meta API (ID: ' . substr($msg_id, 0, 30) . ')';
        } else {
            $status = empty($meta_template_name)
                ? 'Sent via Fallback Template (ID: ' . substr($msg_id, 0, 30) . ')'
                : 'Sent via Meta API (ID: ' . substr($msg_id, 0, 30) . ')';
        }
        try {
            $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {}

        // Auto-save working template name into DB ONLY if user actually provided a template name
        if ($is_admin_test && $is_template_sent && !empty($used_template) && !empty($_GET['admin_template_name'])) {
            try {
                $conn->query("UPDATE whatsapp_settings SET admin_template_name = '" . $conn->real_escape_string($used_template) . "' WHERE id = 1");
            } catch (\Throwable $e) {}
        }

        $clean_sender = normalize_whatsapp_phone_number($settings['sender_number'] ?? '');
        $is_same_number = (!empty($clean_sender) && $clean_number === $clean_sender);

        $delivery_type = $is_template_sent ? 'template' : (empty($meta_template_name) ? 'fallback_text' : 'text');

        send_whatsapp_json([
            'success'             => true,
            'message_id'          => $msg_id,
            'message_status'      => $msg_status,
            'delivery_type'       => $delivery_type,
            'template_name'       => $used_template,
            'bypasses_24h'        => $is_template_sent,
            'same_number_warning' => $is_same_number,
            'sender_number'       => $clean_sender,
            'recipient_number'    => $clean_number,
        ]);

    } else {
        $error_desc = $meta_response['error']['message'] ?? 'Unknown Meta API Error';
        $error_code = $meta_response['error']['code'] ?? 'N/A';
        $error_data = $meta_response['error']['error_data']['details'] ?? '';
        $status     = "Failed API: (#{$error_code}) " . substr($error_desc, 0, 100);
        
        // Also log to error-specific file
        file_put_contents($log_dir . '/whatsapp_errors.log', $log_entry, FILE_APPEND);

        try {
            $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {}
        send_whatsapp_json([
            'success'    => false,
            'error'      => "Meta API Error (#{$error_code}): " . $error_desc,
            'error_code' => $error_code,
            'details'    => $error_data,
        ]);
    }
} else {
    // Web Mode
    $webSuccess = false;
    try {
        $stmt->bind_param("issss", $order_id, $customer_number, $message, $sending_mode, $status);
        $webSuccess = $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {}

    if ($webSuccess) {
        send_whatsapp_json(['success' => true]);
    } else {
        send_whatsapp_json(['success' => false, 'error' => 'Database error']);
    }
}
