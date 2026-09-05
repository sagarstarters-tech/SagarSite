<?php
require_once '../includes/db_connect.php';
require_once '../includes/whatsapp_functions.php';

header('Content-Type: application/json');

// ── Auth guard: must be logged-in admin ─────────────────────
include_once __DIR__ . '/../includes/session_setup.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if (!isset($_GET['order_id'])) {
    echo json_encode(['error' => 'Missing Order ID']);
    exit;
}

$order_id = intval($_GET['order_id']);

// Get settings
$set_q = $conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
$settings = $set_q ? $set_q->fetch_assoc() : [];

if (empty($settings) || empty($settings['is_enabled'])) {
    echo json_encode(['error' => 'WhatsApp Notifications are disabled in settings.']);
    exit;
}

// Get order details safely
$q = $conn->query("
    SELECT o.id, o.status, o.total_amount, o.created_at, o.payment_method, o.payment_mode,
           o.tracking_number AS order_tracking_num, o.carrier AS order_carrier,
           u.name AS customer_name, u.phone AS customer_phone,
           u.address AS customer_address, u.city AS customer_city, u.state AS customer_state, u.zip_code AS customer_zip
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = $order_id
");

if (!$q || $q->num_rows === 0) {
    echo json_encode(['error' => 'Order not found.']);
    exit;
}

$order = $q->fetch_assoc();

// Tracking details
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
$paymentMode   = formatWhatsAppPaymentMethod($order['payment_method'] ?? '', $order['payment_mode'] ?? '');
$orderDate     = date('d M Y', strtotime($order['created_at']));
$orderTime     = date('h:i A', strtotime($order['created_at']));
$expectedDelivery = !empty($track['estimated_delivery_date']) 
    ? date('d M Y', strtotime($track['estimated_delivery_date'])) 
    : date('d M Y', strtotime($order['created_at'] . ' + 4 days'));

// Address
$addressParts = array_filter([
    trim($order['customer_address'] ?? ''),
    trim($order['customer_city'] ?? ''),
    trim($order['customer_state'] ?? ''),
    trim($order['customer_zip'] ?? '')
]);
$deliveryAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Customer Delivery Address';

// Order items
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

$statusMessage = "Your order #$order_id is currently $orderStatus.";
if (strtolower($order['status']) === 'shipped') {
    $statusMessage = "Your order has been dispatched via $courierName. Tracking ID: $trackingID";
} elseif (strtolower($order['status']) === 'delivered') {
    $statusMessage = "Your order has been successfully delivered. Thank you for shopping with us!";
} elseif (strtolower($order['status']) === 'cancelled') {
    $statusMessage = "Your order has been cancelled. Please contact support for assistance.";
}

$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://sagarstarters.com';
$orderLink = $siteUrl . '/my-orders.php';

// Construct preview for Status Template (order_status_updated)
$status_preview = "Hello Dear {$customerName},\n\nYour Order No. #{$order_id} status has been updated.\n\nOrder Date: {$orderDate}\nCurrent Status: *{$orderStatus}*\nStatus Message: {$statusMessage}\nItems Ordered:\n{$itemsOrdered}\nTotal Amount: ₹{$orderAmount}\nDelivery Address:\n{$deliveryAddress}\nExpected Delivery: {$expectedDelivery}\nOrder Link: {$orderLink}\n\nThank you for shopping with Sagar Starter's!";

// Construct preview for Confirmation Template (order_confirmation)
$confirm_preview = "Hello Dear {$customerName},\n\nThank you for your order! Your Order #{$order_id} has been successfully placed.\n\nOrder Date: {$orderDate}\nTotal Amount: ₹{$orderAmount}\nPayment: {$paymentMode}\nOrder Status: {$orderStatus}\nItems:\n{$itemsOrdered}\nDelivery Address:\n{$deliveryAddress}\nTrack Order: {$orderLink}\n\nThank you for shopping with Sagar Starter's!";

$custom_template = !empty($settings['message_template']) ? $settings['message_template'] : $status_preview;
$replacementValues = [
    '{CustomerName}'     => $customerName,
    '{OrderID}'          => $order_id,
    '{OrderStatus}'      => $orderStatus,
    '{TrackingID}'       => $trackingID,
    '{OrderAmount}'      => $orderAmount,
    '{OrderDate}'        => $orderDate,
    '{OrderTime}'        => $orderTime,
    '{PaymentMethod}'    => $paymentMode,
    '{StatusMessage}'    => $statusMessage,
    '{ItemsOrdered}'     => $itemsOrdered,
    '{DeliveryAddress}'  => $deliveryAddress,
    '{ExpectedDelivery}' => $expectedDelivery,
    '{OrderLink}'        => $orderLink
];
$custom_preview = $custom_template;
foreach ($replacementValues as $k => $v) {
    $custom_preview = str_replace($k, $v, $custom_preview);
}

// Meta template names
$status_tpl_name  = !empty($settings['meta_template_name']) ? $settings['meta_template_name'] : 'new_order_status';
$confirm_tpl_name = !empty($settings['order_confirmation_template_name']) ? $settings['order_confirmation_template_name'] : 'order_confirmation';

echo json_encode([
    'success'               => true,
    'customer_phone'        => normalize_whatsapp_phone_number($customerPhone),
    'sending_mode'          => $settings['sending_mode'] ?? 'api',
    'status_template_name'  => $status_tpl_name,
    'confirm_template_name' => $confirm_tpl_name,
    'status_preview'        => $status_preview,
    'confirm_preview'       => $confirm_preview,
    'message'               => $status_preview, // default message
]);

