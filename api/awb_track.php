<?php
/**
 * AWB Shipment Tracking Proxy Page
 * 
 * This securely proxies the courier tracking page for embedding.
 * Since most courier sites block iframe embedding (X-Frame-Options),
 * this page opens the tracking URL in a new tab or renders a
 * server-side status check via cURL if available.
 * 
 * Usage: awb_track.php?order_id=123&email=user@example.com
 *        (for customer access — validates ownership)
 * 
 *        awb_track.php?order_id=123&admin=1
 *        (for admin access — validates admin session)
 */

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$order_id = intval($_GET['order_id'] ?? 0);
$is_admin = isset($_GET['admin']) && $_GET['admin'] == '1';

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required.']);
    exit;
}

// --- Access Control ---
if ($is_admin) {
    // Admin session check
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }
} else {
    // Customer access: validate email ownership
    $email = trim($_GET['email'] ?? '');
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email is required for customer tracking.']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT o.id FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? AND u.email = ?");
    $stmt->bind_param("is", $order_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Order not found or email mismatch.']);
        exit;
    }
    $stmt->close();
}

// --- Fetch tracking and order data ---
$stmt = $conn->prepare("
    SELECT o.id, o.status, o.total_amount, o.created_at,
           u.name as customer_name, u.email as customer_email,
           t.tracking_number, t.estimated_delivery_date,
           c.name as courier_name, c.tracking_url_base 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_tracking t ON o.id = t.order_id
    LEFT JOIN courier_companies c ON t.courier_id = c.id 
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order_data || empty($order_data['tracking_number'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No AWB/tracking number assigned to this order yet.'
    ]);
    exit;
}

$awb = $order_data['tracking_number'];
$courier_name = $order_data['courier_name'] ?? 'Delhivery';
$tracking_url = null;

if (!empty($order_data['tracking_url_base'])) {
    $tracking_url = $order_data['tracking_url_base'] . urlencode($awb);
} else {
    // Default fallback to Delhivery or 17Track if no base URL
    $tracking_url = "https://www.delhivery.com/track/package/" . urlencode($awb);
}

// Fetch status history timeline
$history = [];
$h_stmt = $conn->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
if ($h_stmt) {
    $h_stmt->bind_param("i", $order_id);
    $h_stmt->execute();
    $h_res = $h_stmt->get_result();
    while ($row = $h_res->fetch_assoc()) {
        $history[] = [
            'status' => $row['status'],
            'status_formatted' => ucwords(str_replace('_', ' ', $row['status'])),
            'notes' => $row['notes'] ?? '',
            'created_at' => date('d M Y, h:i A', strtotime($row['created_at']))
        ];
    }
    $h_stmt->close();
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'awb'                     => $awb,
        'courier_name'            => $courier_name,
        'tracking_url'            => $tracking_url,
        'global_17track_url'      => "https://t.17track.net/en#nums=" . urlencode($awb),
        'shiprocket_url'          => "https://shiprocket.co/tracking/" . urlencode($awb),
        'order_id'                => $order_id,
        'order_status'            => $order_data['status'] ?? 'processing',
        'order_status_formatted'  => ucwords(str_replace('_', ' ', $order_data['status'] ?? 'Processing')),
        'total_amount'            => number_format((float)($order_data['total_amount'] ?? 0), 2),
        'estimated_delivery_date' => $order_data['estimated_delivery_date'] ? date('d M Y', strtotime($order_data['estimated_delivery_date'])) : 'In Transit',
        'customer_name'           => $order_data['customer_name'] ?? 'Customer',
        'customer_email'          => $order_data['customer_email'] ?? '',
        'history'                 => $history
    ]
]);
exit;
