<?php
// phonepe_failure.php
include 'includes/header.php';

$raw_transactionId = $_REQUEST['merchantTransactionId'] ?? $_REQUEST['transactionId'] ?? 'Unknown';

// Handle base64 response in failure redirect as well
if ($raw_transactionId === 'Unknown' && isset($_REQUEST['response'])) {
    $res_payload = json_decode(base64_decode($_REQUEST['response']), true);
    if ($res_payload) {
        $raw_transactionId = $res_payload['data']['merchantTransactionId'] ?? $res_payload['data']['transactionId'] ?? 'Unknown';
    }
}

$merchantTransactionId = ($raw_transactionId !== 'Unknown') ? $conn->real_escape_string($raw_transactionId) : 'Unknown';
if ($merchantTransactionId === 'Unknown' && isset($_SESSION['last_merchant_txn_id'])) {
    $merchantTransactionId = $conn->real_escape_string($_SESSION['last_merchant_txn_id']);
}

// Try to grab the order ID if possible to offer a retry link
$retry_link = "checkout.php"; // Default fallback
if ($merchantTransactionId !== 'Unknown') {
    $txn_q = $conn->query("SELECT order_id FROM phonepe_transactions WHERE transaction_id = '$merchantTransactionId'");
    if ($txn_q && $txn_q->num_rows > 0) {
        $order_id = $txn_q->fetch_assoc()['order_id'];
        
        // If the webhook hasn't fired yet (e.g. localhost), update the DB manually here
        $conn->query("UPDATE phonepe_transactions SET status = 'FAILED' WHERE transaction_id = '$merchantTransactionId' AND status != 'SUCCESS'");
        
        // Also cancel the order if it exists
        if ($order_id) {
            $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id AND status = 'pending'");
        }
    }
}
?>
<div class="container mt-5 pt-5 mb-5 text-center px-3">
    <div class="card shadow-sm border-0 p-4 p-md-5 mx-auto text-center" style="max-width: 600px;">
        <div class="mb-4 d-flex justify-content-center align-items-center">
            <i class="fas fa-times-circle fa-5x text-danger"></i>
        </div>
        <h2 class="fw-bold mb-3">Payment Failed</h2>
        <p class="text-muted fs-5 mb-4">We couldn't process your payment. Please try again or choose another payment method.</p>
        
        <?php if ($merchantTransactionId !== 'Unknown'): ?>
        <p class="small bg-light p-2 border rounded text-muted mb-4">Transaction ID: <strong><?php echo htmlspecialchars($merchantTransactionId); ?></strong></p>
        <?php endif; ?>

        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 gap-sm-3 mt-4">
            <a href="checkout.php" class="btn btn-outline-danger btn-lg btn-custom px-4"><i class="fas fa-redo me-2"></i>Retry Payment</a>
            <a href="cart.php" class="btn btn-primary btn-lg btn-custom px-4">Return to Cart</a>
        </div>
    </div>
</div>
<?php
include 'includes/footer.php';
?>
