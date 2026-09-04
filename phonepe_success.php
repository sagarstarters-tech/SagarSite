<?php
// phonepe_success.php
include 'includes/header.php';

// ── Extract Transaction IDs Robustly (Redirect Flow) ──────────
$merchantTransactionId = $_REQUEST['merchantTransactionId'] ?? $_REQUEST['transactionId'] ?? '';
$code = $_REQUEST['code'] ?? '';
$amount = $_REQUEST['amount'] ?? 0;

// PhonePe often sends a base64 encoded 'response' parameter containing the full status
if (isset($_REQUEST['response'])) {
    $res_payload = json_decode(base64_decode($_REQUEST['response']), true);
    if ($res_payload) {
        if (empty($code)) $code = $res_payload['code'] ?? '';
        if (empty($merchantTransactionId)) {
            $merchantTransactionId = $res_payload['data']['merchantTransactionId'] ?? $res_payload['data']['transactionId'] ?? '';
        }
        if (empty($amount)) $amount = $res_payload['data']['amount'] ?? 0;
    }
}

// Fallback to session if parameters are missing from URL (happens on some mobile redirects)
if (empty($merchantTransactionId) && isset($_SESSION['last_merchant_txn_id'])) {
    $merchantTransactionId = $_SESSION['last_merchant_txn_id'];
}

$merchantTransactionId = $conn->real_escape_string($merchantTransactionId);

// Log the redirect request for debugging
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
file_put_contents($log_dir . '/phonepe_redirect.log', "[" . date('Y-m-d H:i:s') . "] REDIRECT: " . json_encode($_REQUEST) . "\n", FILE_APPEND);

// If the redirect doesn't have a code but has a transaction ID, we should check our database
// The webhook might have already updated it to SUCCESS
if (empty($code) && !empty($merchantTransactionId)) {
    $txn_check = $conn->query("SELECT status FROM phonepe_transactions WHERE transaction_id = '$merchantTransactionId'");
    if ($txn_check && $txn_check->num_rows > 0) {
        $txn_row = $txn_check->fetch_assoc();
        if ($txn_row['status'] === 'SUCCESS') {
            $code = 'SUCCESS';
        }
    }
}

if (strtoupper($code) === 'PAYMENT_SUCCESS' || strtoupper($code) === 'SUCCESS') {
    // Use a robust extraction 
    $txn_val = $conn->real_escape_string($merchantTransactionId);
    $txn_q = $conn->query("SELECT order_id, amount, status FROM phonepe_transactions WHERE transaction_id = '$txn_val'");
    
    $order_id = 0;
    $txn = [];
    if ($txn_q && $txn_q->num_rows > 0) {
        $txn = $txn_q->fetch_assoc();
        $order_id = (int)$txn['order_id'];
    }

    // If still no order_id, try to fallback to session if possible (less reliable but better than nothing)
    if ($order_id === 0 && isset($_SESSION['last_order_id'])) {
        $order_id = (int)$_SESSION['last_order_id'];
    }

    if ($order_id > 0) {
        // Clear cart now that we have a confirmed order
        if (isset($_SESSION['cart'])) { unset($_SESSION['cart']); }
        require_once 'includes/cart_functions.php';
        sync_cart_to_db($conn);

        // Mark abandoned cart as recovered (fail-safe)
        try {
            require_once __DIR__ . '/includes/AbandonedCartService.php';
            if (isset($_SESSION['user_id'])) {
                (new AbandonedCartService($conn))->markRecovered(intval($_SESSION['user_id']));
            }
        } catch (Throwable $e) {
            error_log('[AbandonedCart] PhonePe recovery mark error: ' . $e->getMessage());
        }

        // Clear partial COD session flag
        if (isset($_SESSION['cod_partial_order'])) { unset($_SESSION['cod_partial_order']); }

        // Manually update transaction status if not done by webhook yet
        $conn->query("UPDATE phonepe_transactions SET status = 'SUCCESS' WHERE transaction_id = '$txn_val' AND status != 'SUCCESS'");

        // ── Fetch order (to check payment_mode) ──────────────────────
        require_once 'includes/mail_functions.php';
        $ord_q = $conn->query("SELECT * FROM orders WHERE id=$order_id");
        $ord = ($ord_q && $ord_q->num_rows > 0) ? $ord_q->fetch_assoc() : [];
        $is_partial_cod_order = isset($ord['payment_mode']) && $ord['payment_mode'] === 'COD_PARTIAL';

        $just_transitioned = false;
        if ($is_partial_cod_order) {
            // Partial COD: advance paid. Keep status=pending, payment_method=cod, payment_mode=COD_PARTIAL
            $conn->query("UPDATE orders SET status='pending', payment_method='cod', payment_mode='COD_PARTIAL' WHERE id=$order_id AND status='pending'");
            $just_transitioned = ($conn->affected_rows > 0);
        } else {
            // Standard PhonePe full payment
            $conn->query("UPDATE orders SET status='processing', payment_method='phonepe', payment_mode='phonepe' WHERE id=$order_id AND status='pending'");
            $just_transitioned = ($conn->affected_rows > 0);
        }
        
        // ── Order Confirmation Email & WhatsApp (Trigger only on initial transition) ──
        if ($just_transitioned && !empty($ord)) {
            $user_id   = $ord['user_id'];
            $grand_total = $ord['total_amount'];
    
            $usr_q = $conn->query("SELECT * FROM users WHERE id=$user_id");
            $usr = $usr_q ? $usr_q->fetch_assoc() : [];
            
            $cart_items = [];
            $item_q = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
            while($itm = $item_q->fetch_assoc()) {
                $cart_items[] = [
                    'name'  => $itm['name'],
                    'qty'   => $itm['quantity'],
                    'price' => $itm['price']
                ];
            }

            $email_method = $is_partial_cod_order ? 'cod' : 'phonepe';
            sendOrderConfirmationEmail($conn, $order_id, $usr['email'] ?? '', $usr['name'] ?? 'Customer', $cart_items, $grand_total, $global_currency ?? '₹', $email_method);
            
            // Activate Digital Downloads
            require_once 'includes/digital_product_functions.php';
            activateDigitalDownloads($conn, $order_id);

            // WhatsApp Hook
            require_once 'includes/whatsapp_functions.php';
            sendCustomerOrderConfirmationWhatsApp($conn, $order_id);
            sendAdminOrderNotification($conn, $order_id);

            // Queue for background courier sync (fail-safe)
            try {
                require_once __DIR__ . '/courier_module/Services/CourierQueueService.php';
                (new \CourierModule\Services\CourierQueueService())->enqueueOrder((int)$order_id);
            } catch (Throwable $e) {
                error_log('[CourierQueue] PhonePe success enqueue error: ' . $e->getMessage());
            }
        }
    }
    // Re-fetch order details if not already fetched above
    if (empty($ord) && $order_id > 0) {
        $ord_q2 = $conn->query("SELECT * FROM orders WHERE id=$order_id");
        $ord = ($ord_q2 && $ord_q2->num_rows > 0) ? $ord_q2->fetch_assoc() : [];
    }
    $is_partial_cod_order = isset($ord['payment_mode']) && $ord['payment_mode'] === 'COD_PARTIAL';
    $remaining_cod = isset($ord['remaining_amount']) ? (float)$ord['remaining_amount'] : 0;
    $currency_sym = $global_settings['currency_symbol'] ?? '₹';

    // Calculate actual display amount paid (in Rupees)
    $paid_amount_val = 0.0;
    if ($is_partial_cod_order && isset($ord['advance_amount']) && (float)$ord['advance_amount'] > 0) {
        $paid_amount_val = (float)$ord['advance_amount'];
    } elseif (!empty($ord['total_amount']) && (float)$ord['total_amount'] > 0) {
        $paid_amount_val = (float)$ord['total_amount'];
    } elseif (!empty($txn['amount']) && (float)$txn['amount'] > 0) {
        $paid_amount_val = (float)$txn['amount'];
    } elseif (!empty($amount) && (float)$amount > 0) {
        // Fallback: If amount came from PhonePe API payload in paisa
        $paid_amount_val = (float)$amount / 100;
    }
    $displayAmount = number_format($paid_amount_val, 2);

    ?>
    <div class="container mt-5 pt-5 mb-5 text-center px-3">
    <div class="card shadow-sm border-0 p-4 p-md-5 mx-auto text-center" style="max-width: 600px;">
            <div class="mb-4 d-flex justify-content-center align-items-center">
                <i class="fas fa-check-circle fa-5x text-success"></i>
            </div>
            <?php if ($is_partial_cod_order): ?>
            <h2 class="fw-bold mb-2">Advance Payment Successful!</h2>
            <p class="text-muted fs-6 mb-4">Your advance payment was received. Your COD order is now confirmed.</p>
            <?php else: ?>
            <h2 class="fw-bold mb-3">Payment Successful</h2>
            <p class="text-muted fs-5 mb-4">Your payment has been processed successfully.</p>
            <?php endif; ?>
            
            <div class="bg-light p-4 rounded text-start mb-4 border">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary fw-bold">Order ID:</span>
                    <span class="fw-bold text-dark">#<?php echo htmlspecialchars($order_id); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary fw-bold">Transaction ID:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($merchantTransactionId); ?></span>
                </div>
                <div class="d-flex justify-content-between <?php echo $is_partial_cod_order ? 'mb-2' : ''; ?>">
                    <span class="text-secondary fw-bold"><?php echo $is_partial_cod_order ? 'Advance Paid:' : 'Amount Paid:'; ?></span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($currency_sym); ?><?php echo $displayAmount; ?></span>
                </div>
                <?php if ($is_partial_cod_order && $remaining_cod > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary fw-bold">Remaining COD:</span>
                    <span class="fw-bold text-success"><?php echo htmlspecialchars($currency_sym); ?><?php echo number_format($remaining_cod, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_partial_cod_order && $remaining_cod > 0): ?>
            <div class="alert alert-info py-3 text-start mb-4 small border-0" style="background:#e8f4ff;">
                <i class="fas fa-truck me-2 text-primary"></i>
                <strong>COD Order Confirmed.</strong> Please pay the remaining
                <strong><?php echo htmlspecialchars($currency_sym); ?><?php echo number_format($remaining_cod, 2); ?></strong>
                in cash when your order is delivered.
            </div>
            <?php endif; ?>
            
            <a href="user/orders.php" class="btn btn-primary btn-lg btn-custom px-5">View My Orders</a>
        </div>
    </div>
    
    <!-- PhonePe Requested Success Overlay (Amazon/Flipkart Style) -->
    <style>
        .success-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%;
            background: #fff; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.5s ease;
        }
        .success-overlay.show { opacity: 1; visibility: visible; }
        .success-content { text-align: center; transform: scale(0.8); transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .success-overlay.show .success-content { transform: scale(1); }

        /* SVG Tick Animation */
        .ft-green-tick { display: block; margin: 0 auto; }
        .ft-green-tick .circle { stroke-dasharray: 140; stroke-dashoffset: 140; animation: circle 0.8s ease-in-out forwards; }
        .ft-green-tick .tick { stroke-dasharray: 30; stroke-dashoffset: 30; animation: tick 0.5s ease-out 0.8s forwards; }
        @keyframes circle { to { stroke-dashoffset: 0; } }
        @keyframes tick { to { stroke-dashoffset: 0; } }
    </style>

    <div class="success-overlay" id="paymentSuccessOverlay">
        <div class="success-content">
            <div class="svg-container mb-4">
                <svg class="ft-green-tick" xmlns="http://www.w3.org/2000/svg" height="150" width="150" viewBox="0 0 48 48" aria-hidden="true">
                    <circle class="circle" fill="#5bb543" cx="24" cy="24" r="22"/>
                    <path class="tick" fill="none" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" d="M14 27l5.917 4.917L34 17"/>
                </svg>
            </div>
            <h1 class="display-3 fw-bold mt-4" style="color: #45c05c;">Success!</h1>
            <p class="fs-4 text-muted">Your payment was received successfully.</p>
            <div class="mt-4 p-3 bg-light rounded shadow-sm border mx-auto" style="max-width: 350px;">
                <p class="mb-1 text-secondary">Order ID: <span class="fw-bold text-dark">#<?php echo htmlspecialchars($order_id); ?></span></p>
                <p class="mb-0 text-secondary">Amount: <span class="fw-bold text-dark"><?php echo htmlspecialchars($global_settings['currency_symbol'] ?? '₹'); ?><?php echo $displayAmount; ?></span></p>
            </div>
            <p class="mt-5 text-muted small">Redirecting you to your orders in a moment...</p>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const overlay = document.getElementById('paymentSuccessOverlay');
            setTimeout(() => {
                overlay.classList.add('show');
                // Auto redirect after 4 seconds
                setTimeout(() => {
                    window.location.href = 'user/orders.php';
                }, 4000);
            }, 300);
        });
    </script>
    <?php
} else {
    // If no transaction ID, redirect to cart with error
    if (empty($merchantTransactionId)) {
        $_SESSION['error'] = "Payment failed or was cancelled. No transaction ID received.";
        echo "<script>window.location.href = 'cart.php';</script>";
        exit;
    }
    // It's a failure code
    echo "<script>window.location.href = 'phonepe_failure.php?transactionId=" . urlencode($merchantTransactionId) . "';</script>";
}

include 'includes/footer.php';
?>
