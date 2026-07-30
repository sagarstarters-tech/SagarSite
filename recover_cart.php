<?php
// Include header for consistent look
include_once __DIR__ . '/includes/session_setup.php';
include_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/AbandonedCartRepository.php';
require_once __DIR__ . '/includes/cart_functions.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

if (empty($token)) {
    $error = 'Invalid recovery link.';
} else {
    $repo = new AbandonedCartRepository($conn);
    $cart = $repo->findByToken($token);
    
    if (!$cart) {
        $error = 'This recovery link has expired or is invalid.';
    } elseif ($cart['status'] !== 'active') {
        $error = 'This cart has already been recovered or expired.';
    } else {
        // Restore cart
        $userId = intval($cart['user_id']);
        $cartData = json_decode($cart['cart_data'], true);
        
        if (!is_array($cartData) || empty($cartData)) {
            $error = 'Cart data is empty or corrupted.';
        } else {
            // Set session
            $_SESSION['user_id'] = $userId;
            $_SESSION['cart'] = $cartData;
            
            // Load user info into session
            $userQ = $conn->query("SELECT name, email, phone, role, profile_photo FROM users WHERE id = {$userId}");
            if ($userQ && $row = $userQ->fetch_assoc()) {
                $_SESSION['name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['phone'] = $row['phone'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_photo'] = $row['profile_photo'] ?? '';
            }
            
            // Sync cart to DB
            sync_cart_to_db($conn);
            
            // Store coupon if exists
            if (!empty($cart['coupon_code'])) {
                $_SESSION['recovery_coupon'] = [
                    'code' => $cart['coupon_code'],
                    'discount' => floatval($cart['coupon_discount'] ?? 0)
                ];
            }
            
            // Mark cart as recovered
            $repo->markConverted($userId);
            
            $success = true;
            
            // Redirect to checkout
            $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            header('Location: ' . $siteUrl . '/checkout.php');
            exit;
        }
    }
}

// If we reach here, show error page
$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart Recovery</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center" style="max-width:500px;">
        <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
        <h4 class="fw-bold">Cart Recovery</h4>
        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
        <a href="<?php echo $siteUrl; ?>/shop.php" class="btn btn-primary btn-custom mt-3">
            <i class="fas fa-store me-2"></i>Continue Shopping
        </a>
    </div>
</body>
</html>
