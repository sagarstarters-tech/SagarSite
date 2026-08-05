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

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $qty = $_SESSION['cart'][$row['id']];
        $total = $row['price'] * $qty;
        $subtotal += $total;
        $row['qty'] = $qty;
        $row['total'] = $total;
        $cart_items[] = $row;
    }
}
?>

<div class="container mt-5 pt-3 mb-5" style="min-height: 50vh;">
    <h1 class="montserrat fw-bold primary-blue mb-4">Shopping Cart</h1>
    
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
                                            <img src="<?php echo htmlspecialchars(resolve_product_image_url($item['image'] ?? '', $conn, $item['id'])); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="rounded" style="width: 60px; height: 60px; object-fit: contain; background-color: #fff; padding: 5px;">
                                            <div class="ms-3">
                                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $global_currency; ?><?php echo number_format($item['price'], 2); ?></td>
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

                                        <form action="includes/cart_actions.php" method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $item['qty']; ?>" class="form-control text-center me-2" style="width: 70px;" min="1" max="<?php echo $item['stock']; ?>" onchange="this.form.submit()">
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
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

