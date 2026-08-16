<?php
ob_start();
include_once __DIR__ . '/includes/session_setup.php';
include 'includes/db_connect.php';
require_once 'includes/mail_functions.php';

require_once 'shipping_module_src/src/Config/ShippingConfig.php';
require_once 'shipping_module_src/src/Repositories/ShippingRepository.php';
require_once 'shipping_module_src/src/Services/ShippingService.php';
require_once 'includes/CodService.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user/login.php");
    exit;
}

if (empty($_SESSION['cart']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Smart Auto-Fill: Determine data source ──────────────────────────────────
$autofill_source = '';
$has_saved_address = !empty($user_data['address']) && !empty($user_data['city']);

if ($has_saved_address) {
    $autofill_source = 'profile'; // User has saved address in profile
} else {
    // Fallback: Fetch address from the user's most recent completed order
    $fallback_stmt = $conn->prepare(
        "SELECT u.name, u.phone, u.address, u.city, u.state, u.country, u.zip_code 
         FROM orders o 
         JOIN users u ON o.user_id = u.id 
         WHERE o.user_id = ? AND o.status NOT IN ('cancelled') 
         ORDER BY o.id DESC LIMIT 1"
    );
    $fallback_stmt->bind_param("i", $user_id);
    $fallback_stmt->execute();
    $fallback_data = $fallback_stmt->get_result()->fetch_assoc();
    $fallback_stmt->close();

    // Since checkout updates user record on submit, the user record itself 
    // may have been updated by a previous order. Re-check after query.
    if ($fallback_data && !empty($fallback_data['address']) && !empty($fallback_data['city'])) {
        // Merge fallback data into user_data for empty fields only
        foreach (['name', 'phone', 'address', 'city', 'state', 'country', 'zip_code'] as $field) {
            if (empty($user_data[$field]) && !empty($fallback_data[$field])) {
                $user_data[$field] = $fallback_data[$field];
            }
        }
        $autofill_source = 'previous_order';
    }
}
// ─────────────────────────────────────────────────────────────────────────────

$cart_items = [];
$subtotal = 0;
$is_retailer_user = (isset($_SESSION['role']) && $_SESSION['role'] === 'retailer') || (isset($user_data['role']) && $user_data['role'] === 'retailer');

if (!empty($_SESSION['cart'])) {
    // Sanitize IDs for the IN clause
    $safe_ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($safe_ids)");

    $is_all_digital = true;
    $cod_allowed_for_all = true;
    while ($row = $result->fetch_assoc()) {
        $qty = (int)$_SESSION['cart'][$row['id']];
        // Enforce stock limit at checkout to prevent race conditions
        if ($qty > $row['stock']) {
            $qty = $row['stock'];
            $_SESSION['cart'][$row['id']] = $qty; // Update cart
        }
        
        if ($qty > 0) {
            $bulk_price = !empty($row['bulk_price']) ? floatval($row['bulk_price']) : 0;
            $bulk_min_qty = !empty($row['bulk_min_qty']) && (int)$row['bulk_min_qty'] > 0 ? (int)$row['bulk_min_qty'] : 12;
            
            // Retailer customers get bulk rate on > 1 unit (i.e. 2+ units)
            $effective_min_qty = $is_retailer_user ? 2 : $bulk_min_qty;

            $base_price = (float)$row['price'];
            if ($bulk_price > 0 && $qty >= $effective_min_qty && $bulk_price < $base_price) {
                $effective_price = $bulk_price;
            } else {
                $effective_price = $base_price;
            }

            $total = $effective_price * $qty;
            $subtotal += $total;
            $row['qty'] = $qty;
            $row['price'] = $effective_price;
            $cart_items[] = $row;
            
            if ($row['product_type'] === 'physical') {
                $is_all_digital = false;
            }

            if ($row['cod_available'] == 0) {
                $cod_allowed_for_all = false;
            }
        } else {
            unset($_SESSION['cart'][$row['id']]); // Remove out of stock items
        }
    }
}

$shippingConfig = new \ShippingModule\Config\ShippingConfig();
$shippingRepo = new \ShippingModule\Repositories\ShippingRepository($shippingConfig->getConnection());
$shippingService = new \ShippingModule\Services\ShippingService($shippingRepo);

if (isset($is_all_digital) && $is_all_digital) {
    $shipping_cost = 0;
    $grand_total = $subtotal;
    $shippingCalc = ['shipping_metadata' => ['is_free' => true, 'message' => 'Digital products - No shipping required']];
} else {
    $shippingCalc = $shippingService->getFinalOrderTotals($subtotal, $cart_items);
    $shipping_cost = $shippingCalc['shipping_cost'];
    $grand_total = $shippingCalc['grand_total'];
}

// ── COD Charges Calculation ──────────────────────────────────────────────────
$codService = new CodService($conn, $global_settings);
$cod_charge_result = $codService->calculateCodCharge($cart_items, $subtotal);
$cod_charge_amount = $cod_charge_result['cod_charge'];
$cod_charge_is_free = $cod_charge_result['is_free'];
$cod_charge_message = $cod_charge_result['message'];

// COD Blacklist Check
$cod_blacklist_result = $codService->isBlacklisted(
    $user_data['phone'] ?? '',
    $user_data['email'] ?? '',
    $_SERVER['REMOTE_ADDR'] ?? ''
);
$cod_is_blacklisted = $cod_blacklist_result['is_blacklisted'];

// Grand total with COD charge (applied only when COD is selected — handled in POST)
$grand_total_with_cod = $grand_total + $cod_charge_amount;
// ────────────────────────────────────────────────────────────────────────────

// ── Partial COD Config ───────────────────────────────────────────────────────
$cod_enabled_global     = isset($global_settings['cod_enabled']) && $global_settings['cod_enabled'] == '1';
$cod_advance_enabled    = isset($global_settings['cod_advance_enabled']) && $global_settings['cod_advance_enabled'] == '1';
$cod_advance_pct        = max(1, min(99, (float)($global_settings['cod_advance_percentage'] ?? 30)));
$cod_advance_min_order  = (float)($global_settings['cod_advance_min_order'] ?? 0);

$is_partial_cod = $cod_enabled_global
               && $cod_advance_enabled
               && $cod_allowed_for_all
               && !$cod_is_blacklisted
               && ($cod_advance_min_order == 0 || $grand_total_with_cod >= $cod_advance_min_order);

$advance_amount   = $is_partial_cod ? round($grand_total_with_cod * $cod_advance_pct / 100, 2) : 0;
$remaining_amount = $is_partial_cod ? round($grand_total_with_cod - $advance_amount, 2) : 0;
// ────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security check failed. Please submit the form again.";
        header("Location: checkout.php");
        exit;
    }
    
    // Start Transaction for Order Integrity
    $conn->begin_transaction();
    try {
        $billing_name    = trim($_POST['billing_name'] ?? '');
        $billing_address = trim($_POST['billing_address'] ?? '');
        $billing_city    = trim($_POST['billing_city'] ?? '');
        $billing_state   = trim($_POST['billing_state'] ?? '');
        $billing_country = trim($_POST['billing_country'] ?? '');
        $billing_zip     = trim($_POST['billing_zip'] ?? '');
        $billing_phone   = trim($_POST['billing_phone'] ?? '');

        // Validation for mandatory fields
        if (empty($billing_name)) {
            throw new Exception("Full Name is required.");
        }
        if (!$is_all_digital) {
            if (empty($billing_address) || empty($billing_city) || empty($billing_state) || empty($billing_country) || empty($billing_zip)) {
                throw new Exception("Complete address details are required for physical products.");
            }
        }
        if (empty($billing_phone) || strlen(preg_replace('/[^0-9]/', '', $billing_phone)) < 10) {
            throw new Exception("Valid Phone Number is required.");
        }

        // Update user data if changed (optional but helpful)
        if ($billing_name !== ($user_data['name'] ?? '') || 
            $billing_address !== ($user_data['address'] ?? '') ||
            $billing_city !== ($user_data['city'] ?? '') ||
            $billing_state !== ($user_data['state'] ?? '') ||
            $billing_country !== ($user_data['country'] ?? '') ||
            $billing_zip !== ($user_data['zip_code'] ?? '') ||
            $billing_phone !== ($user_data['phone'] ?? '')) {
            
            $upd_stmt = $conn->prepare("UPDATE users SET name=?, address=?, city=?, state=?, country=?, zip_code=?, phone=? WHERE id=?");
            $upd_stmt->bind_param("sssssssi", $billing_name, $billing_address, $billing_city, $billing_state, $billing_country, $billing_zip, $billing_phone, $user_id);
            $upd_stmt->execute();
            $upd_stmt->close();
        }

        $status = 'pending';
        $payment_method = $_POST['payment_method'] ?? 'cod';
        
        // Recalculate COD charge server-side for security (prevents API manipulation)
        $final_cod_charge = 0;
        if ($payment_method === 'cod') {
            $server_cod = $codService->calculateCodCharge($cart_items, $subtotal);
            $final_cod_charge = $server_cod['cod_charge'];
            $grand_total += $final_cod_charge; // Add COD charge to grand total
            $grand_total_with_cod = $grand_total;
        }

        // Determine payment_mode for Partial COD
        $payment_mode = null;
        if ($payment_method === 'cod' && $is_partial_cod) {
            $payment_mode = 'COD_PARTIAL';
            // Recalculate advance/remaining with COD charge included
            $advance_amount   = round($grand_total * $cod_advance_pct / 100, 2);
            $remaining_amount = round($grand_total - $advance_amount, 2);
        }

        // 1. Insert order
        // Try with partial COD columns first; fall back to basic INSERT if they don't exist
        if ($is_partial_cod) {
            $stmt = null;
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO orders (user_id, total_amount, payment_method, status, advance_amount, remaining_amount, payment_mode, cod_charge_amount)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
            } catch (\Throwable $e) {
                // Ignore exception, $stmt remains null to trigger fallback
            }

            if ($stmt) {
                $stmt->bind_param("idssddsd", $user_id, $grand_total, $payment_method, $status, $advance_amount, $remaining_amount, $payment_mode, $final_cod_charge);
            } else {
                // Partial COD columns may not exist — fall back to basic insert and disable partial COD for this order
                $is_partial_cod = false;
                $stmt = $conn->prepare(
                    "INSERT INTO orders (user_id, total_amount, payment_method, status)
                     VALUES (?, ?, ?, ?)"
                );
                if (!$stmt) throw new Exception("Database error: " . $conn->error);
                $stmt->bind_param("idss", $user_id, $grand_total, $payment_method, $status);
            }
        } else {
            // Try with cod_charge_amount column
            $stmt = null;
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO orders (user_id, total_amount, payment_method, status, cod_charge_amount)
                     VALUES (?, ?, ?, ?, ?)"
                );
            } catch (\Throwable $e) {
                // fallback without cod_charge_amount
            }

            if ($stmt) {
                $stmt->bind_param("idssd", $user_id, $grand_total, $payment_method, $status, $final_cod_charge);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO orders (user_id, total_amount, payment_method, status)
                     VALUES (?, ?, ?, ?)"
                );
                if (!$stmt) throw new Exception("Database error: " . $conn->error);
                $stmt->bind_param("idss", $user_id, $grand_total, $payment_method, $status);
            }
        }
        if (!$stmt->execute()) throw new Exception("Failed to create order: " . $stmt->error);
        $order_id = $stmt->insert_id;
        $stmt->close();
        
        // 2. Insert order items and update stock
        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, shipping_cost) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt2) throw new Exception("Failed to prepare order_items insert: " . $conn->error);
        $stock_stmt = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ? AND stock >= ?");
        if (!$stock_stmt) throw new Exception("Failed to prepare stock update: " . $conn->error);
        
        foreach ($cart_items as $item) {
            $item_shipping = ($item['product_type'] === 'physical') ? (float)$item['shipping_cost'] : 0.00;
            $qty  = (int)$item['qty'];
            $p_id = (int)$item['id'];
            $price = (float)$item['price'];
            
            $stmt2->bind_param("iiidd", $order_id, $p_id, $qty, $price, $item_shipping);
            if (!$stmt2->execute()) throw new Exception("Failed to insert order item: " . $stmt2->error);

            // Update stock with safety check
            $stock_stmt->bind_param("iii", $qty, $p_id, $qty);
            $stock_stmt->execute();
            if ($stock_stmt->affected_rows === 0) {
                if ($item['product_type'] === 'physical') {
                    throw new Exception("Product '{$item['name']}' is out of stock.");
                }
            }
        }
        $stmt2->close();
        $stock_stmt->close();
        
        // Auto-promote Customer to 'Retailer' if purchasing 12 or more units of any item or total >= 12
        $is_retailer_qualifying = false;
        $total_order_qty = 0;
        foreach ($cart_items as $item) {
            $i_qty = (int)$item['qty'];
            $total_order_qty += $i_qty;
            if ($i_qty >= 12) {
                $is_retailer_qualifying = true;
            }
        }
        if ($total_order_qty >= 12) {
            $is_retailer_qualifying = true;
        }

        if ($is_retailer_qualifying && $user_id > 0) {
            $u_role_res = $conn->query("SELECT role FROM users WHERE id = $user_id");
            if ($u_role_res && $u_role_res->num_rows > 0) {
                $current_role = $u_role_res->fetch_assoc()['role'] ?? 'user';
                if ($current_role === 'user') {
                    $conn->query("UPDATE users SET role = 'retailer' WHERE id = $user_id");
                    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user_id) {
                        $_SESSION['role'] = 'retailer';
                    }
                }
            }
        }

        // If we reach here, commit everything
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        // Log detailed error for debugging
        $log_dir = __DIR__ . '/logs';
        if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
        $log_msg = '[' . date('Y-m-d H:i:s') . '] Checkout error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        file_put_contents($log_dir . '/checkout_errors.log', $log_msg, FILE_APPEND);
        $_SESSION['checkout_error'] = "Order could not be processed: " . $e->getMessage();
        header("Location: checkout.php");
        exit;
    }
    
    if ($payment_method === 'cod') {
        if ($is_partial_cod) {
            // ── Partial COD: Redirect to PhonePe for advance amount ──────
            $_SESSION['cod_partial_order'] = true;
            $payment_amount = $advance_amount; // override amount for phonepe_payment.php
            include 'phonepe_payment.php';
            exit;
        } else {
            // ── Standard COD ─────────────────────────────────────────────
            unset($_SESSION['cart']);
            require_once 'includes/cart_functions.php';
            sync_cart_to_db($conn);

            // Mark abandoned cart as recovered (fail-safe, non-blocking)
            try {
                require_once __DIR__ . '/includes/AbandonedCartService.php';
                (new AbandonedCartService($conn))->markRecovered($user_id);
            } catch (Throwable $e) {
                error_log('[AbandonedCart] Recovery mark error: ' . $e->getMessage());
            }

            $customer_email = $user_data['email'];
            $customer_name = $user_data['name'];
            sendOrderConfirmationEmail($conn, $order_id, $customer_email, $customer_name, $cart_items, $grand_total, $global_currency, $payment_method);
            
            require_once __DIR__ . '/includes/whatsapp_functions.php';
            sendAutomatedWhatsApp($conn, $order_id);
            sendAdminOrderNotification($conn, $order_id);
            
            require_once 'tracking_module_src/src/Config/TrackingConfig.php';
            require_once 'tracking_module_src/src/Repositories/TrackingRepository.php';
            $trackingConfig = new \TrackingModule\Config\TrackingConfig();
            $trackingRepo = new \TrackingModule\Repositories\TrackingRepository($trackingConfig->getConnection());
            $trackingRepo->logStatusChange($order_id, 'pending', 'Order placed successfully.', 'system');

            require_once 'includes/digital_product_functions.php';
            activateDigitalDownloads($conn, $order_id);

            $success = "Order placed successfully! Order ID: #$order_id";
        }
    } elseif ($payment_method === 'phonepe') {
        include 'phonepe_payment.php';
        exit;
    }
}

// Now include header (no output sent before this except if redirected)
include 'includes/header.php';
?>

<div class="container mt-5 pt-3 mb-5">
    <h1 class="montserrat fw-bold primary-blue mb-4">Checkout</h1>
    
    <?php if(isset($_SESSION['checkout_error'])): ?>
        <div class="alert alert-danger py-3 mb-4 rounded-3 border-0 bg-danger bg-opacity-10 text-danger fw-bold">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($_SESSION['checkout_error']); ?>
        </div>
        <?php unset($_SESSION['checkout_error']); ?>
    <?php endif; ?>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success text-center py-4 bg-light border-success">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h3><?php echo $success; ?></h3>
            <p>Thank you for your purchase. We are processing your order.</p>
            <a href="user/orders.php" class="btn btn-primary btn-custom mt-3">View My Orders</a>
        </div>
    <?php else: ?>
    <div class="row">
        <!-- Billing Details Form -->
        <div class="col-lg-8 mb-4">
            <div class="card product-card border-0 shadow-sm p-4">
                <h4 class="mb-4 fw-bold">Billing Details</h4>

                <?php if ($autofill_source): ?>
                <div class="alert py-2 px-3 mb-4 rounded-3 border-0 d-flex align-items-center" style="background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); color: #2e7d32;">
                    <i class="fas fa-magic me-2"></i>
                    <small class="fw-semibold">
                        <?php if ($autofill_source === 'profile'): ?>
                            Auto-filled from your saved details
                        <?php elseif ($autofill_source === 'previous_order'): ?>
                            Auto-filled from your previous order
                        <?php endif; ?>
                        <span class="text-muted fw-normal ms-1">— you can edit any field</span>
                    </small>
                </div>
                <?php endif; ?>

                <form method="POST" id="checkoutForm" autocomplete="on">
                    <?php echo csrf_field(); ?>
                    <?php if ($is_all_digital): ?>
                        <div class="alert alert-info py-3 mb-4 rounded-3 border-0 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-info-circle me-2"></i> This order contains only digital products. Shipping address is not required.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="billing_name">Full Name</label>
                        <input type="text" name="billing_name" id="billing_name" class="form-control" autocomplete="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                    </div>

                    <div id="address_fields" class="<?php echo $is_all_digital ? 'd-none' : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label" for="billing_address">Address</label>
                            <input type="text" name="billing_address" id="billing_address" class="form-control" autocomplete="street-address" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" <?php echo $is_all_digital ? '' : 'required'; ?>>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="billing_city">City</label>
                            <input type="text" name="billing_city" id="billing_city" class="form-control" autocomplete="address-level2" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>" <?php echo $is_all_digital ? '' : 'required'; ?>>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="billing_state">State/Province</label>
                                <input type="text" name="billing_state" id="billing_state" class="form-control" autocomplete="address-level1" value="<?php echo htmlspecialchars($user_data['state'] ?? ''); ?>" <?php echo $is_all_digital ? '' : 'required'; ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="billing_country">Country</label>
                                <input type="text" name="billing_country" id="billing_country" class="form-control" autocomplete="country-name" value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>" <?php echo $is_all_digital ? '' : 'required'; ?>>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="billing_zip">Zip / Pincode</label>
                                <input type="text" name="billing_zip" id="billing_zip" class="form-control" autocomplete="postal-code" inputmode="numeric" value="<?php echo htmlspecialchars($user_data['zip_code'] ?? ''); ?>" <?php echo $is_all_digital ? '' : 'required'; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="billing_phone">Phone</label>
                        <?php echo render_phone_input('billing_phone', $user_data['phone'] ?? '', true); ?>
                    </div>

                    
                    <hr class="my-4">
                    <h4 class="mb-3 fw-bold">Payment Method</h4>
                    
                    <?php 
                        $phonepe_enabled = isset($global_settings['phonepe_enabled']) && $global_settings['phonepe_enabled'] == '1';
                        $cod_enabled = isset($global_settings['cod_enabled']) && $global_settings['cod_enabled'] == '1';
                    ?>
                    
                    <?php if ($phonepe_enabled): ?>
                    <div class="mb-3">
                        <div class="form-check border rounded p-3 bg-light">
                            <input class="form-check-input ms-1" type="radio" name="payment_method" id="pay_phonepe" value="phonepe" required <?php echo !$cod_enabled ? 'checked' : ''; ?> onchange="updatePaymentUI()">
                            <label class="form-check-label ms-2 fw-bold" for="pay_phonepe">
                                Pay Online via PhonePe
                            </label>
                            <small class="d-block text-success mt-1 ms-2"><i class="fas fa-shield-alt me-1"></i> Secure payment via UPI, Cards, NetBanking.</small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($cod_enabled): ?>
                        <?php if ($cod_allowed_for_all && !$cod_is_blacklisted): ?>
                        <div class="mb-3">
                            <div class="form-check border rounded p-3 bg-light">
                                <input class="form-check-input ms-1" type="radio" name="payment_method" id="pay_cod" value="cod" required <?php echo !$phonepe_enabled ? 'checked' : ''; ?> onchange="updatePaymentUI()">
                                <label class="form-check-label ms-2 fw-bold" for="pay_cod">
                                    Cash On Delivery (COD)
                                    <?php if ($is_partial_cod): ?>
                                        <span class="badge bg-warning text-dark ms-2 small fw-semibold">Advance Required</span>
                                    <?php endif; ?>
                                    <?php if ($cod_charge_amount > 0 && !$cod_charge_is_free): ?>
                                        <span class="badge bg-info text-white ms-2 small fw-semibold">+<?php echo $global_currency; ?><?php echo number_format($cod_charge_amount, 2); ?> charge</span>
                                    <?php elseif ($cod_charge_is_free): ?>
                                        <span class="badge bg-success text-white ms-2 small fw-semibold">Free COD</span>
                                    <?php endif; ?>
                                </label>
                                <small class="d-block text-success mt-1 ms-2"><i class="fas fa-check-circle me-1"></i> Pay with cash upon delivery.</small>
                            </div>
                        </div>

                        <?php if ($is_partial_cod): ?>
                        <!-- ── Partial COD Info Box ──────────────────────────────── -->
                        <div id="partial_cod_info" class="mb-4" style="display:none;">
                            <div class="border border-warning rounded-3 p-4" style="background: linear-gradient(135deg, #fffbf0 0%, #fff8e1 100%);">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-mobile-alt fa-lg text-warning me-2"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Advance Payment Required</h6>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                            <span class="text-muted small fw-semibold">Order Total</span>
                                            <span class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($grand_total_with_cod, 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                            <span class="text-muted small fw-semibold">
                                                <i class="fas fa-mobile-alt me-1 text-primary"></i>
                                                Advance via PhonePe (<?php echo $cod_advance_pct; ?>%)
                                            </span>
                                            <span class="fw-bold text-primary"><?php echo $global_currency; ?><?php echo number_format($advance_amount, 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small fw-semibold">
                                                <i class="fas fa-truck me-1 text-success"></i>
                                                Remaining COD (on delivery)
                                            </span>
                                            <span class="fw-bold text-success"><?php echo $global_currency; ?><?php echo number_format($remaining_amount, 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-warning py-2 mb-0 small fw-semibold border-0 bg-warning bg-opacity-25 rounded-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You need to pay <strong><?php echo $global_currency; ?><?php echo number_format($advance_amount, 2); ?></strong> in advance via PhonePe to confirm your COD order. The remaining <strong><?php echo $global_currency; ?><?php echo number_format($remaining_amount, 2); ?></strong> will be collected at delivery.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php elseif ($cod_is_blacklisted): ?>
                        <div class="mb-4">
                            <div class="alert alert-danger py-3 border-0 border-start border-danger border-4">
                                <i class="fas fa-ban me-2"></i>
                                <strong>COD Restricted:</strong> Cash on Delivery is not available for your account. Please use an online payment method.
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-4">
                            <div class="alert alert-warning py-3 border-0 border-start border-warning border-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>COD Not Available:</strong> Cash on Delivery is not available for one or more items in your cart.
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    
                    <?php if (!$phonepe_enabled && !$cod_enabled): ?>
                        <div class="alert alert-danger px-4 py-3 border-0 border-start border-danger border-4 fw-bold">No payment methods are currently available. Please contact support.</div>
                    <?php else: ?>
                        <button type="submit" id="placeOrderBtn" class="btn btn-primary btn-lg btn-custom w-100 mt-4 py-3">Place Order</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card product-card border-0 shadow-sm">
                <div class="card-body p-4 bg-light">
                    <h5 class="fw-bold mb-4">Your Order</h5>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach($cart_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 border-bottom border-light">
                            <div>
                                <h6 class="my-0 text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <small class="text-muted">Qty: <?php echo $item['qty']; ?></small>
                            </div>
                            <span class="text-muted"><?php echo $global_currency; ?><?php echo number_format($item['qty'] * $item['price'], 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Cart Subtotal</span>
                        <span><?php echo $global_currency; ?><?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping Cost</span>
                        <span class="<?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'text-success fw-bold' : ''; ?>">
                            <?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'Free' : '+ ' . $global_currency . number_format($shipping_cost, 2); ?>
                        </span>
                    </div>
                    <div class="alert <?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'alert-success' : 'alert-info'; ?> p-2 small mt-2 mb-3 text-center">
                        <?php echo htmlspecialchars($shippingCalc['shipping_metadata']['message']); ?>
                    </div>
                    <!-- COD Charges Line (shown only when COD selected) -->
                    <div id="cod_charge_sidebar" class="d-flex justify-content-between mb-2" style="display:none;">
                        <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i>COD Charges</span>
                        <span id="cod_charge_value" class="<?php echo $cod_charge_is_free ? 'text-success fw-bold' : ''; ?>">
                            <?php if ($cod_charge_is_free): ?>
                                <span class="badge bg-success text-white">Free COD</span>
                            <?php elseif ($cod_charge_amount > 0): ?>
                                + <?php echo $global_currency; ?><?php echo number_format($cod_charge_amount, 2); ?>
                            <?php else: ?>
                                <?php echo $global_currency; ?>0.00
                            <?php endif; ?>
                        </span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mt-3">
                        <span>Total Amount</span>
                        <strong class="primary-blue" id="grand_total_display"><?php echo $global_currency; ?><?php echo number_format($grand_total, 2); ?></strong>
                    </div>

                    <?php if ($is_partial_cod): ?>
                    <!-- Partial COD summary lines (always visible in sidebar) -->
                    <div id="partial_cod_sidebar" class="mt-3 pt-3 border-top" style="display:none;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted"><i class="fas fa-mobile-alt me-1"></i>Advance (PhonePe)</span>
                            <span class="fw-bold text-primary"><?php echo $global_currency; ?><?php echo number_format($advance_amount, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted"><i class="fas fa-truck me-1"></i>Pay on Delivery</span>
                            <span class="fw-bold text-success"><?php echo $global_currency; ?><?php echo number_format($remaining_amount, 2); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function updatePaymentUI() {
    var codRadio     = document.getElementById('pay_cod');
    var partialInfo  = document.getElementById('partial_cod_info');
    var sidebarInfo  = document.getElementById('partial_cod_sidebar');
    var codChargeSb  = document.getElementById('cod_charge_sidebar');
    var grandDisplay = document.getElementById('grand_total_display');
    var placeBtn     = document.getElementById('placeOrderBtn');

    var isCodSelected = codRadio ? codRadio.checked : false;

    // COD charge line visibility
    if (codChargeSb) codChargeSb.style.display = isCodSelected ? '' : 'none';

    // Update grand total display
    if (grandDisplay) {
        if (isCodSelected) {
            grandDisplay.textContent = '<?php echo $global_currency; ?><?php echo number_format($grand_total_with_cod, 2); ?>';
        } else {
            grandDisplay.textContent = '<?php echo $global_currency; ?><?php echo number_format($grand_total, 2); ?>';
        }
    }

    <?php if ($is_partial_cod): ?>
    if (partialInfo) partialInfo.style.display = isCodSelected ? '' : 'none';
    if (sidebarInfo) sidebarInfo.style.display = isCodSelected ? '' : 'none';
    if (placeBtn) {
        placeBtn.innerHTML = isCodSelected
            ? '<i class="fas fa-mobile-alt me-2"></i>Pay Advance &amp; Confirm COD Order'
            : '<i class="fas fa-lock me-2"></i>Place Order';
    }
    <?php endif; ?>
}

// Run on page load to set initial state
document.addEventListener('DOMContentLoaded', function() {
    updatePaymentUI();
});
</script>

<!-- Smart Checkout: localStorage Auto-Fill & Real-time Sync -->
<script>
(function() {
    'use strict';

    var FIELDS = [
        {id: 'billing_name',    key: 'checkout_name'},
        {id: 'billing_address', key: 'checkout_address'},
        {id: 'billing_city',    key: 'checkout_city'},
        {id: 'billing_state',   key: 'checkout_state'},
        {id: 'billing_country', key: 'checkout_country'},
        {id: 'billing_zip',     key: 'checkout_pincode'}
    ];

    // Check if this is a successful order (clear localStorage)
    var successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        FIELDS.forEach(function(f) { localStorage.removeItem(f.key); });
        localStorage.removeItem('checkout_phone');
        return; // No need to bind events on success page
    }

    document.addEventListener('DOMContentLoaded', function() {
        var hasServerData = <?php echo json_encode(!empty($autofill_source)); ?>;

        // ── Restore from localStorage (only for empty fields) ──
        FIELDS.forEach(function(f) {
            var el = document.getElementById(f.id);
            if (el && !el.value.trim()) {
                var saved = localStorage.getItem(f.key);
                if (saved) {
                    el.value = saved;
                }
            }
        });

        // Restore phone from localStorage if empty
        var phoneInput = document.querySelector('#checkoutForm .phone-main-input');
        if (phoneInput && !phoneInput.value.trim()) {
            var savedPhone = localStorage.getItem('checkout_phone');
            if (savedPhone) {
                phoneInput.value = savedPhone;
                // Trigger the hidden field update
                var group = phoneInput.closest('.phone-group');
                if (group) {
                    var code = group.querySelector('.country-code-select').value;
                    var hidden = group.querySelector('.phone-hidden-final');
                    if (hidden) hidden.value = code + ' ' + savedPhone;
                }
            }
        }

        // Show localStorage indicator if we had no server data but did restore from localStorage
        if (!hasServerData) {
            var anyRestored = false;
            FIELDS.forEach(function(f) {
                var el = document.getElementById(f.id);
                if (el && el.value.trim() && localStorage.getItem(f.key)) {
                    anyRestored = true;
                }
            });
            if (anyRestored) {
                var indicator = document.createElement('div');
                indicator.className = 'alert py-2 px-3 mb-3 rounded-3 border-0 d-flex align-items-center';
                indicator.style.cssText = 'background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); color: #1565c0;';
                indicator.innerHTML = '<i class="fas fa-history me-2"></i><small class="fw-semibold">Restored from your previous visit <span class="text-muted fw-normal ms-1">— you can edit any field</span></small>';
                var form = document.getElementById('checkoutForm');
                if (form) form.insertBefore(indicator, form.firstChild.nextSibling);
            }
        }

        // ── Real-time Save to localStorage ──
        FIELDS.forEach(function(f) {
            var el = document.getElementById(f.id);
            if (el) {
                el.addEventListener('input', function() {
                    localStorage.setItem(f.key, this.value);
                });
            }
        });

        // Save phone on input
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                localStorage.setItem('checkout_phone', this.value);
            });
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
