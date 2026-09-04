<?php
// phonepe_payment.php
// This is included directly from checkout.php, so $conn, $order_id, $user_id, $grand_total, $global_settings, $user_data are available
if (!isset($order_id)) {
    header("Location: checkout.php");
    exit;
}

// Fetch PhonePe settings
$merchantId = trim($global_settings['phonepe_merchant_id'] ?? '');
$saltKey = trim($global_settings['phonepe_salt_key'] ?? '');
$saltIndex = trim($global_settings['phonepe_salt_index'] ?? '1');
$mode = $global_settings['phonepe_mode'] ?? 'sandbox';

// Endpoint & Environment Config
if ($mode === 'live') {
    $apiUrl = 'https://api.phonepe.com/apis/hermes/pg/v1/pay';
    $checksumPath = '/pg/v1/pay';
} else {
    // Standard PhonePe Sandbox/UAT Endpoint
    $apiUrl = 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay';
    $checksumPath = '/pg/v1/pay';
}

// Generate unique Merchant Transaction ID
$merchantTransactionId = 'ORDER_' . $order_id . '_' . time();

// Amount in Paisa (Multiply by 100)
// $payment_amount can be set by the caller (e.g. checkout.php for Partial COD advance).
// Falls back to $grand_total for standard PhonePe payments.
$charge_amount = isset($payment_amount) ? (float)$payment_amount : (float)$grand_total;
$amountInPaisa = (int)round($charge_amount * 100);

// Host URL for redirects and webhooks
$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
if (strpos($siteUrl, 'http') === 0) {
    // If SITE_URL is absolute (e.g. on Hostinger)
    $baseUrl = $siteUrl;
} else {
    // If SITE_URL is relative (e.g. /store in local) or empty
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . $siteUrl;
}

$mobile = !empty($user_data['phone']) ? preg_replace('/[^0-9]/', '', $user_data['phone']) : '9999999999';
if(strlen($mobile) > 10) $mobile = substr($mobile, -10);

// Payload structure
$payload = [
    "merchantId" => $merchantId,
    "merchantTransactionId" => $merchantTransactionId,
    "merchantUserId" => "U" . $user_id,
    "amount" => $amountInPaisa,
    "redirectUrl" => $baseUrl . "/phonepe_success.php",
    "redirectMode" => "GET",
    "callbackUrl" => $baseUrl . "/phonepe-webhook.php",
    "mobileNumber" => $mobile,
    "paymentInstrument" => [
        "type" => "PAY_PAGE"
    ]
];

// Encode Payload
$jsonPayload = json_encode($payload);
$base64Payload = base64_encode($jsonPayload);

// Generate Checksum (X-VERIFY)
$verifyHeader = hash('sha256', $base64Payload . $checksumPath . $saltKey) . "###" . $saltIndex;

// Insert pending transaction into phonepe_transactions
$stmt_trans = $conn->prepare("INSERT INTO phonepe_transactions (order_id, transaction_id, amount, status) VALUES (?, ?, ?, 'PENDING')");
$stmt_trans->bind_param("isd", $order_id, $merchantTransactionId, $charge_amount);
$stmt_trans->execute();

// Store in session for recovery if redirect parameters are lost
$_SESSION['last_order_id'] = $order_id;
$_SESSION['last_merchant_txn_id'] = $merchantTransactionId;

// Send POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-VERIFY: " . $verifyHeader,
    "accept: application/json"
]);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $base64Payload]));

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    die("cURL Error during PhonePe initialization: " . htmlspecialchars($err));
} else {
    $res = json_decode($response, true);
    
    // Log the full initial response
    $full_resp = $conn->real_escape_string($response);
    $conn->query("UPDATE phonepe_transactions SET raw_payload = '$full_resp' WHERE transaction_id = '$merchantTransactionId'");
    
// ── Payment Page Redirection ──────────────────────────────────
if (isset($res['success']) && $res['success'] && isset($res['data']['instrumentResponse']['redirectInfo']['url'])) {
    $paymentUrl = $res['data']['instrumentResponse']['redirectInfo']['url'];
    
    // ── Modern Processing Overlay (Amazon/Flipkart Style) ────────
    // We replace the header() with a UI that warns the user not to refresh
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Processing Payment...</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
        <style>
            body { background: #f8f9fa; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }
            .processing-card { background: #fff; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 90%; animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
            .spinner-container { position: relative; width: 100px; height: 100px; margin: 0 auto 30px; }
            .spinner-main { width: 100%; height: 100%; border: 4px solid rgba(13, 110, 253, 0.1); border-top: 4px solid #0d6efd; border-radius: 50%; animation: spin 1s linear infinite; }
            .spinner-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #0d6efd; font-size: 2rem; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .warning-box { background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; padding: 15px; margin-top: 25px; border-radius: 8px; text-align: left; }
            .warning-box i { color: #ff9800; margin-right: 10px; }
        </style>
    </head>
    <body>
        <div class="processing-card">
            <div class="spinner-container">
                <div class="spinner-main"></div>
                <i class="fas fa-shield-alt spinner-icon"></i>
            </div>
            <h3 class="fw-bold mb-2">Securely Connecting...</h3>
            <p class="text-muted">Redirecting you to the PhonePe payment gateway. Please wait a moment.</p>
            
            <div class="warning-box">
                <p class="mb-0 small fw-bold text-dark">
                    <i class="fas fa-exclamation-triangle"></i>
                    Do not refresh this page or click the Back button.
                </p>
            </div>
            
            <div class="mt-4">
                <div class="progress" style="height: 6px; border-radius: 10px;">
                    <div id="pBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <script>
            let width = 0;
            const bar = document.getElementById('pBar');
            const interval = setInterval(() => {
                width += 5;
                bar.style.width = width + '%';
                if (width >= 100) {
                    clearInterval(interval);
                    window.location.href = "<?php echo $paymentUrl; ?>";
                }
            }, 100);

            // Prevent back button
            history.pushState(null, null, location.href);
            window.onpopstate = function () {
                history.go(1);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    // Log Error or handle gracefully
    $errorMsg = isset($res['message']) ? $res['message'] : 'Unknown error from PhonePe';
    
    // Include header if it wasn't already included (it shouldn't be if we are in this flow)
    if (!defined('HEADER_INCLUDED')) {
        include 'includes/header.php';
    }
    
    echo "<div class='container mt-5 pt-5 text-center px-3'>";
    echo "<div class='card product-card p-4 p-md-5 shadow-sm border-0'>";
    echo "<i class='fas fa-exclamation-triangle fa-4x text-danger mb-4'></i>";
    echo "<h2 class='fw-bold mb-3'>Payment Initialization Failed</h2>";
    echo "<p class='text-muted mb-4'>" . htmlspecialchars($errorMsg) . "</p>";

    if ($mode === 'sandbox' && strpos($errorMsg, 'Key not found') !== false) {
        echo "<div class='alert alert-warning mb-4 py-2 small text-start mx-auto' style='max-width: 500px;'>";
        echo "<strong><i class='fas fa-info-circle me-1'></i> Tip for Admin:</strong> Your Merchant ID (<code>$merchantId</code>) seems to be a Live ID, but the mode is set to <strong>'Sandbox / Test'</strong>. PhonePe Sandbox usually requires the standard test ID: <code>PGTESTPAYUAT</code>. Please switch to <strong>'Live'</strong> mode in Admin Settings to use your own credentials.";
        echo "</div>";
    }
    echo "<a href='checkout.php' class='btn btn-primary btn-custom px-5'>Go back to checkout</a>";
    echo "</div>";
    echo "</div>";
    include 'includes/footer.php';
    exit;
}
}
?>
