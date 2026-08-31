<?php
include 'includes/header.php';

require_once 'shipping_module_src/src/Config/ShippingConfig.php';
require_once 'shipping_module_src/src/Repositories/ShippingRepository.php';
require_once 'shipping_module_src/src/Services/ShippingService.php';

$shippingConfig = new \ShippingModule\Config\ShippingConfig();
$shippingRepo = new \ShippingModule\Repositories\ShippingRepository($shippingConfig->getConnection());
$shippingService = new \ShippingModule\Services\ShippingService($shippingRepo);

$cart_items = [];
$subtotal = 0;

$is_retailer_user = (isset($_SESSION['role']) && $_SESSION['role'] === 'retailer');

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $qty = $_SESSION['cart'][$row['id']];
        $moq = !empty($row['min_order_qty']) ? max(1, intval($row['min_order_qty'])) : 1;
        $bulk_price = !empty($row['bulk_price']) ? floatval($row['bulk_price']) : 0;
        $bulk_min_qty = !empty($row['bulk_min_qty']) && (int)$row['bulk_min_qty'] > 0 ? (int)$row['bulk_min_qty'] : 12;
        
        // Retailer customers get bulk rate on > 1 unit (i.e. 2+ units)
        $effective_min_qty = $is_retailer_user ? 2 : $bulk_min_qty;

        $base_price = (float)$row['price'];
        $is_bulk_applied = false;
        $is_bulk_shipping_applied = false;
        if ($bulk_price > 0 && $qty >= $effective_min_qty && $bulk_price < $base_price) {
            $effective_price = $bulk_price;
            $is_bulk_applied = true;
            if ($row['bulk_shipping_cost'] !== null && $row['bulk_shipping_cost'] !== '') {
                $row['shipping_cost'] = floatval($row['bulk_shipping_cost']);
                $is_bulk_shipping_applied = true;
            }
        } else {
            $effective_price = $base_price;
        }

        $total = $effective_price * $qty;
        $subtotal += $total;
        $row['qty'] = $qty;
        $row['moq'] = $moq;
        $row['effective_price'] = $effective_price;
        $row['is_bulk_applied'] = $is_bulk_applied;
        $row['is_bulk_shipping_applied'] = $is_bulk_shipping_applied;
        $row['is_retailer_applied'] = ($is_retailer_user && $is_bulk_applied && $qty < $bulk_min_qty);
        $row['total'] = $total;
        $cart_items[] = $row;
    }
}
?>

<div class="container mt-5 pt-3 mb-5" style="min-height: 50vh;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="montserrat fw-bold primary-blue mb-0">Shopping Cart</h1>
        <?php if($is_retailer_user): ?>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                <i class="fas fa-store me-1"></i> Retailer Account: Bulk rates active for 2+ units
            </span>
        <?php endif; ?>
    </div>
    
    <?php if(empty($cart_items)): ?>
    <div class="text-center py-5">
        <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
        <h3>Your cart is empty</h3>
        <p class="text-muted">Looks like you haven't added anything to your cart yet.</p>
        <a href="shop.php" class="btn btn-primary btn-custom mt-3 px-4">Start Shopping</a>
    </div>
    <?php else: ?>
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card product-card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="ps-4">Product</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Shipping</th>
                                    <th scope="col">Quantity</th>

                                    <th scope="col">Total</th>
                                    <th scope="col" class="pe-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cart_items as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars(resolve_product_image_url($item['image'] ?? '', $conn, $item['id'])); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" alt="<?php echo htmlspecialchars($item['name']); ?>" width="60" height="60" loading="lazy" decoding="async" class="rounded" style="width: 60px; height: 60px; object-fit: contain; background-color: #fff; padding: 5px;">
                                            <div class="ms-3">
                                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <?php if (!empty($item['moq']) && $item['moq'] > 1): ?>
                                                    <small class="text-muted"><span class="badge bg-light text-dark border">MOQ: <?php echo $item['moq']; ?></span></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo $global_currency; ?><?php echo number_format($item['effective_price'], 2); ?></div>
                                        <?php if (!empty($item['is_retailer_applied'])): ?>
                                            <small class="d-block text-success fw-bold" style="font-size: 0.72rem;"><i class="fas fa-store me-1"></i>Retailer Rate</small>
                                        <?php elseif (!empty($item['is_bulk_applied'])): ?>
                                            <small class="d-block text-success fw-bold" style="font-size: 0.72rem;"><i class="fas fa-layer-group me-1"></i>Bulk Price</small>
                                        <?php endif; ?>
                                        <?php if (!empty($item['is_bulk_applied']) && isset($item['bulk_cod_available']) && (int)$item['bulk_cod_available'] === 0): ?>
                                            <small class="d-block text-danger fw-semibold" style="font-size: 0.72rem;"><i class="fas fa-ban me-1"></i>Prepaid Only (Bulk)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($item['product_type'] !== 'physical'): ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php elseif($item['shipping_cost'] > 0): ?>
                                            <?php echo $global_currency; ?><?php echo number_format($item['shipping_cost'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-success small">Free</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>

                                        <form action="includes/cart_actions.php" method="POST" class="d-flex flex-column align-items-start">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $item['qty']; ?>" class="form-control text-center me-2" style="width: 75px;" min="<?php echo $item['moq']; ?>" max="<?php echo $item['stock']; ?>" onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($item['total'], 2); ?></td>
                                     <td class="pe-4 text-end">
                                         <form action="includes/cart_actions.php" method="POST">
                                             <input type="hidden" name="action" value="remove">
                                             <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                             <button type="submit" class="btn btn-link text-danger p-0" title="Delete Item"><i class="fas fa-trash-alt fs-5"></i></button>
                                         </form>
                                     </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <a href="shop.php" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
                    <i class="fas fa-plus me-2"></i>Add More Products
                </a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card product-card border-0 shadow-sm">
                <div class="card-body p-4 bg-light">
                    <h5 class="fw-bold mb-4">Order Summary</h5>
                    <?php
                        // Fetch dynamic totals from the new module
                        $shippingCalc = $shippingService->getFinalOrderTotals($subtotal, $cart_items);
                        $shipping_cost = $shippingCalc['shipping_cost'];
                        $grand_total = $shippingCalc['grand_total'];
                    ?>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Shipping</span>
                        <span class="fw-bold <?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'text-success' : 'text-dark'; ?>">
                            <?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'Free' : '+ ' . $global_currency . number_format($shipping_cost, 2); ?>
                        </span>
                    </div>
                    <div class="alert <?php echo $shippingCalc['shipping_metadata']['is_free'] ? 'alert-success' : 'alert-info'; ?> p-2 small mt-2 mb-3 text-center">
                        <?php echo htmlspecialchars($shippingCalc['shipping_metadata']['message']); ?>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <span class="fs-5 fw-bold">Total</span>
                        <span class="fs-4 fw-bold primary-blue"><?php echo $global_currency; ?><?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="checkout.php" class="btn btn-primary btn-lg btn-custom w-100 py-3">Proceed to Checkout</a>
                    <?php else: ?>
                        <a href="user/login.php" class="btn btn-secondary btn-lg btn-custom w-100 py-3">Login to Checkout</a>
                    <?php endif; ?>

                    <?php
                    // ── WhatsApp Cart Order Button ────────────────────────────
                    $wa_cart_phone = get_store_whatsapp_number();
                    $wa_cart_msg = "Hello Sagar Starter's! \xF0\x9F\x91\x8B\n\n";
                    $wa_cart_msg .= "I want to order the following products:\n\n";
                    $item_num = 1;
                    foreach ($cart_items as $ci) {
                        $ci_name     = $ci['name'];
                        $ci_qty      = (int)$ci['qty'];
                        $ci_price    = (float)$ci['effective_price'];
                        $ci_subtotal = (float)$ci['total'];
                        $wa_cart_msg .= $item_num . ". " . $ci_name . "\n";
                        $wa_cart_msg .= "   Quantity: " . $ci_qty . "\n";
                        $wa_cart_msg .= "   Price: " . $global_currency . number_format($ci_price, 2) . "\n";
                        $wa_cart_msg .= "   Subtotal: " . $global_currency . number_format($ci_subtotal, 2) . "\n\n";
                        $item_num++;
                    }
                    $wa_cart_msg .= "Total: " . $global_currency . number_format($grand_total, 2) . "\n\n";
                    $wa_cart_msg .= "Please confirm stock availability, delivery charges and final order amount.\n\n";
                    $wa_cart_msg .= "Thank you.";
                    $wa_cart_link = "https://wa.me/{$wa_cart_phone}?text=" . urlencode($wa_cart_msg);
                    ?>
                    <a href="<?php echo $wa_cart_link; ?>" target="_blank" rel="noopener noreferrer"
                       class="btn btn-success btn-lg btn-custom w-100 py-3 mt-3"
                       id="waCartOrderBtn"
                       aria-label="Order cart via WhatsApp"
                       onclick="if(window.trackWhatsAppClick){window.trackWhatsAppClick(0,'Cart Order','cart',<?php echo count($cart_items); ?>);}">
                        <i class="fab fa-whatsapp me-2"></i>Order via WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

