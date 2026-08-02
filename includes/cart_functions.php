<?php
/**
 * Cart Persistence Functions
 * Syncs session cart with DB (users.cart_data column)
 */

function sync_cart_to_db($conn) {
    // Only sync if user is logged in (no role check needed - admin check is unnecessary overhead)
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $user_id = intval($_SESSION['user_id']);
    
    // Build clean cart JSON — filter out invalid entries
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $clean_cart = [];
    foreach ($cart as $pid => $qty) {
        $pid = intval($pid);
        $qty = intval($qty);
        if ($pid > 0 && $qty > 0) {
            $clean_cart[$pid] = $qty;
        }
    }
    
    $cart_json = !empty($clean_cart) ? json_encode($clean_cart) : null;
    
    $stmt = $conn->prepare("UPDATE users SET cart_data = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $cart_json, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Track abandoned cart ONLY when cart content is modified (Add, Update, Remove)
 */
function track_abandoned_cart($conn) {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    try {
        require_once __DIR__ . '/AbandonedCartService.php';
        $abandonedCartService = new AbandonedCartService($conn);
        $abandonedCartService->trackCart(intval($_SESSION['user_id']));
    } catch (\Throwable $e) {
        error_log('[AbandonedCart] Track error: ' . $e->getMessage());
    }
}

function load_cart_from_db($conn, $user_id) {
    $user_id = intval($user_id);
    
    $stmt = $conn->prepare("SELECT cart_data FROM users WHERE id = ?");
    if (!$stmt) return;
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $db_cart = !empty($row['cart_data']) ? json_decode($row['cart_data'], true) : [];
        if (!is_array($db_cart)) $db_cart = [];
        
        // DB cart is the source of truth.
        // Start with DB cart, then add only genuinely NEW guest session items.
        $session_cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
        
        $final_cart = $db_cart;
        foreach ($session_cart as $product_id => $qty) {
            $product_id = intval($product_id);
            $qty = intval($qty);
            if ($product_id > 0 && $qty > 0 && !isset($final_cart[$product_id])) {
                $final_cart[$product_id] = $qty;
            }
        }
        
        $_SESSION['cart'] = $final_cart;
        
        // Persist the final cart back to DB
        $cart_json = !empty($final_cart) ? json_encode($final_cart) : null;
        $upd = $conn->prepare("UPDATE users SET cart_data = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $cart_json, $user_id);
            $upd->execute();
            $upd->close();
        }
    }
    // If no DB record found, keep session cart as-is (shouldn't happen for valid users)
    
    $stmt->close();
}
?>

