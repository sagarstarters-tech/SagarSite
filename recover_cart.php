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
    } else {
        $userId = intval($cart['user_id']);
        
        // 1. Decode cart_data from abandoned_carts record
        $cartData = !empty($cart['cart_data']) ? json_decode($cart['cart_data'], true) : null;
        if (is_string($cartData)) {
            $cartData = json_decode($cartData, true);
        }
        
        // 2. Fallback: Check users table cart_data column if abandoned cart_data was empty
        if (!is_array($cartData) || empty($cartData)) {
            $userCartQ = $conn->query("SELECT cart_data FROM users WHERE id = {$userId}");
            if ($userCartQ && $userRow = $userCartQ->fetch_assoc()) {
                $userCart = !empty($userRow['cart_data']) ? json_decode($userRow['cart_data'], true) : null;
                if (is_string($userCart)) $userCart = json_decode($userCart, true);
                if (is_array($userCart) && !empty($userCart)) {
                    $cartData = $userCart;
                }
            }
        }
        
        // 3. Fallback: Parse product_names column if cartData is still empty
        if (!is_array($cartData) || empty($cartData)) {
            $pNames = $cart['product_names'] ?? '';
            if (!empty($pNames)) {
                // Extract first product name (e.g. "3 Hp 3 Phase Automatic Motor Starter x1")
                $rawFirstName = strtok($pNames, ',');
                $cleanName = trim(preg_replace('/\s*x\d+$/i', '', $rawFirstName));
                if (!empty($cleanName)) {
                    $escName = $conn->real_escape_string($cleanName);
                    $pRes = $conn->query("SELECT id FROM products WHERE name LIKE '%{$escName}%' OR name = '{$escName}' LIMIT 1");
                    if ($pRes && $pRow = $pRes->fetch_assoc()) {
                        $cartData = [$pRow['id'] => 1];
                    }
                }
            }
        }
        
        if (!is_array($cartData)) {
            $cartData = [];
        }
        
        // Restore user session
        $_SESSION['user_id'] = $userId;
        
        $userQ = $conn->query("SELECT name, email, phone, role, profile_photo FROM users WHERE id = {$userId}");
        if ($userQ && $row = $userQ->fetch_assoc()) {
            $_SESSION['name'] = $row['name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['phone'] = $row['phone'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_photo'] = $row['profile_photo'] ?? '';
        }

        if (!empty($cartData)) {
            $_SESSION['cart'] = $cartData;
            sync_cart_to_db($conn);
        } else {
            load_cart_from_db($conn, $userId);
        }

        // Store coupon if exists
        if (!empty($cart['coupon_code'])) {
            $_SESSION['recovery_coupon'] = [
                'code' => $cart['coupon_code'],
                'discount' => floatval($cart['coupon_discount'] ?? 0)
            ];
        }

        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if (!empty($_SESSION['cart'])) {
            header('Location: ' . $siteUrl . '/checkout.php');
        } else {
            header('Location: ' . $siteUrl . '/shop.php');
        }
        exit;
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
