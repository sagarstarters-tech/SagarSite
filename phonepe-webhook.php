<?php
// phonepe-webhook.php
include 'includes/db_connect.php';
require_once 'includes/mail_functions.php';

// Get request headers
$headers = getallheaders();
$xVerify = isset($headers['X-VERIFY']) ? $headers['X-VERIFY'] : (isset($headers['x-verify']) ? $headers['x-verify'] : null);

// Get the raw POST data
$inputPayload = file_get_contents('php://input');
$response = json_decode($inputPayload, true);

if (!isset($response['response'])) {
    http_response_code(400);
    die('Invalid payload');
}

$base64Response = $response['response'];

// Fetch salt keys from DB
$saltKey = $global_settings['phonepe_salt_key'] ?? '';
$saltIndex = $global_settings['phonepe_salt_index'] ?? '1';

// Verify checksum
$calculatedChecksum = hash('sha256', $base64Response . $saltKey) . "###" . $saltIndex;

if ($calculatedChecksum !== $xVerify) {
    http_response_code(400);
    die('Invalid Checksum');
}

// Decode actual response
$decodedResponse = json_decode(base64_decode($base64Response), true);

$merchantTransactionId = isset($decodedResponse['data']['merchantTransactionId']) ? $conn->real_escape_string($decodedResponse['data']['merchantTransactionId']) : null;
$phonepeTransactionId = isset($decodedResponse['data']['transactionId']) ? $conn->real_escape_string($decodedResponse['data']['transactionId']) : null;
$code = $decodedResponse['code'] ?? null;

if (!$merchantTransactionId) {
    die('No Transaction ID');
}

// Check transaction in DB
$txn_q = $conn->query("SELECT * FROM phonepe_transactions WHERE transaction_id = '$merchantTransactionId'");
if ($txn_q->num_rows === 0) {
    die('Transaction not found');
}

$txn = $txn_q->fetch_assoc();

if ($txn['status'] === 'SUCCESS') {
    die('Already processed'); // Prevent duplicates
}

$statusMap = [
    'PAYMENT_SUCCESS' => 'SUCCESS',
    'PAYMENT_ERROR' => 'FAILED',
    'PAYMENT_PENDING' => 'PENDING',
    'PAYMENT_DECLINED' => 'FAILED' ,
    'INTERNAL_SERVER_ERROR' => 'FAILED'
];

$finalStatus = $statusMap[$code] ?? 'FAILED';
$full_resp = $conn->real_escape_string(json_encode($decodedResponse));

// Update transaction table
$conn->query("UPDATE phonepe_transactions SET 
    provider_reference_id = '$phonepeTransactionId', 
    status = '$finalStatus', 
    raw_payload = '$full_resp' 
    WHERE transaction_id = '$merchantTransactionId'");

$order_id = $txn['order_id'];

if ($finalStatus === 'SUCCESS') {
    // Fetch order to check payment_mode
    $ord_q = $conn->query("SELECT * FROM orders WHERE id=$order_id");
    $ord = ($ord_q && $ord_q->num_rows > 0) ? $ord_q->fetch_assoc() : [];
    $is_partial_cod = isset($ord['payment_mode']) && $ord['payment_mode'] === 'COD_PARTIAL';

    if ($is_partial_cod) {
        // Partial COD: advance paid. Keep as pending COD (delivery will collect remainder).
        $conn->query("UPDATE orders SET status='pending', payment_method='cod' WHERE id=$order_id");
    } else {
        // Standard PhonePe: mark as processing
        $conn->query("UPDATE orders SET status='processing', payment_method='phonepe' WHERE id=$order_id");
    }

    if (!empty($ord)) {
        $user_id    = $ord['user_id'];
        $grand_total = $ord['total_amount'];

        $usr_q = $conn->query("SELECT * FROM users WHERE id=$user_id");
        $usr = $usr_q ? $usr_q->fetch_assoc() : [];
        
        // Fetch order items
        $cart_items = [];
        $item_q = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
        while($itm = $item_q->fetch_assoc()) {
            $cart_items[] = [
                'name'  => $itm['name'],
                'qty'   => $itm['quantity'],
                'price' => $itm['price']
            ];
        }
        
        // Send Confirmation Email
        $email_method = $is_partial_cod ? 'cod' : 'phonepe';
        sendOrderConfirmationEmail($conn, $order_id, $usr['email'] ?? '', $usr['name'] ?? 'Customer', $cart_items, $grand_total, $global_currency ?? '₹', $email_method);

        // Activate Digital Downloads
        require_once 'includes/digital_product_functions.php';
        activateDigitalDownloads($conn, $order_id);

        // WhatsApp Notifications (Customer & Admin)
        require_once 'includes/whatsapp_functions.php';
        sendAutomatedWhatsApp($conn, $order_id);
        sendAdminOrderNotification($conn, $order_id);

        // Queue for background courier sync (fail-safe)
        try {
            require_once __DIR__ . '/courier_module/Services/CourierQueueService.php';
            (new \CourierModule\Services\CourierQueueService())->enqueueOrder((int)$order_id);
        } catch (Throwable $e) {
            error_log('[CourierQueue] Webhook enqueue error: ' . $e->getMessage());
        }
    }
} else if ($finalStatus === 'FAILED') {
    // Mark the order as cancelled
    $conn->query("UPDATE orders SET status='cancelled' WHERE id=$order_id");
}

echo "OK";
?>
