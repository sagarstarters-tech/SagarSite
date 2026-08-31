<?php
include 'admin_header.php';
require_once '../includes/InvoiceService.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    echo "<div class='alert alert-danger'>Invalid Order ID</div>";
    include 'admin_footer.php';
    exit;
}

// Fetch order info
$order_q = $conn->query("
    SELECT o.*, 
           u.name as user_name, u.email as user_email, u.phone as user_phone,
           u.address as user_address, u.city as user_city, u.state as user_state, 
           u.country as user_country, u.zip_code as user_zip
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = $order_id
");
if (!$order_q || $order_q->num_rows === 0) {
    echo "<div class='alert alert-danger'>Order not found</div>";
    include 'admin_footer.php';
    exit;
}

$order = $order_q->fetch_assoc();

// Fetch order items
$items_q = $conn->query("
    SELECT oi.*, p.name as product_name, p.image as product_image, p.product_type, p.image_fit 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = $order_id
");

// Invoice status
$invoiceService = new InvoiceService($conn);
$invoice = $invoiceService->getInvoiceByOrder($order_id);

// Include tracking module logic
require_once __DIR__ . '/../tracking_module_src/src/Config/TrackingConfig.php';
require_once __DIR__ . '/../tracking_module_src/src/Repositories/TrackingRepository.php';
require_once __DIR__ . '/../tracking_module_src/src/Services/TrackingService.php';

$trackingConfig = new \TrackingModule\Config\TrackingConfig();
$trackingRepo = new \TrackingModule\Repositories\TrackingRepository($trackingConfig->getConnection());
$trackingService = new \TrackingModule\Services\TrackingService($trackingRepo);

$trackingData = $trackingService->getAdminTracking($order_id);
$info = $trackingData['order_info'];
$shipping = $trackingData['shipping'];
$timeline = $trackingData['timeline'];

$stages = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered'];
$stageIndex = $info['progress_stage_index'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Order Details - #<?php echo $order_id; ?></h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="invoice_view.php?order_id=<?php echo $order_id; ?>" target="_blank" class="btn btn-primary btn-custom px-3" title="View Invoice"><i class="fas fa-file-invoice me-2"></i>Invoice</a>
        <a href="manage_order_tracking.php?id=<?php echo $order_id; ?>" class="btn btn-info btn-custom text-white px-3"><i class="fas fa-truck-fast me-2"></i>Manage Tracking</a>
        <a href="manage_orders.php" class="btn btn-light btn-custom border"><i class="fas fa-arrow-left me-2"></i>Back to Orders</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Order Items</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Shipping</th>
                                <th class="pe-4 text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_item_price = 0;
                            $total_shipping = 0;
                            while($item = $items_q->fetch_assoc()): 
                                $item_total = $item['quantity'] * $item['price'];
                                $total_item_price += $item_total;
                                
                                $item_shipping = 0;
                                if ($item['product_type'] === 'physical') {
                                    $item_shipping = (float)$item['shipping_cost'];
                                }
                                $total_shipping += $item_shipping;
                            ?>
                            <?php
                                $item_img_src = resolve_product_image_url($item['product_image'] ?? '', $conn, $item['product_id'] ?? null, $item['product_name'] ?? '');
                                $item_fit = htmlspecialchars($item['image_fit'] ?? 'contain');
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item_img_src); ?>" 
                                             onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" 
                                             class="rounded me-3 border" 
                                             style="width: 50px; height: 50px; object-fit: <?php echo $item_fit; ?>; background-color: #fff; padding: 4px;" 
                                             alt="<?php echo htmlspecialchars($item['product_name'] ?? ''); ?>">
                                        <div>
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <small class="text-muted">ID: #<?php echo $item['product_id']; ?></small>
                                            <?php if ($item['product_type'] !== 'physical'): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info ms-1"><?php echo ucfirst($item['product_type']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
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
                                <td class="pe-4 text-end fw-bold"><?php echo $global_currency; ?><?php echo number_format($item_total + $item_shipping, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light border-0 p-4">
                <div class="row justify-content-end">
                    <div class="col-md-5 col-lg-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal (Items)</span>
                            <span class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($total_item_price, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Shipping Total</span>
                            <span class="fw-bold"><?php echo $total_shipping > 0 ? $global_currency . number_format($total_shipping, 2) : '<span class="text-success">Free</span>'; ?></span>
                        </div>
                        <?php 
                        $order_cod_charge = isset($order['cod_charge_amount']) ? (float)$order['cod_charge_amount'] : 0;
                        if ($order_cod_charge > 0): 
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i>COD Charges</span>
                            <span class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($order_cod_charge, 2); ?></span>
                        </div>
                        <?php elseif ($order['payment_method'] === 'cod' && $order_cod_charge == 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i>COD Charges</span>
                            <span class="fw-bold text-success"><span class="badge bg-success bg-opacity-10 text-success border border-success">Free COD</span></span>
                        </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Order Total</span>
                            <span class="text-primary"><?php echo $global_currency; ?><?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3">Customer Information</h5>
            <div class="mb-3">
                <p class="text-muted mb-0 small uppercase fw-bold">Name</p>
                <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_name'] ?? 'Unknown User'); ?></h6>
            </div>
            <div class="mb-3">
                <p class="text-muted mb-0 small uppercase fw-bold">Email</p>
                <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_email'] ?? 'N/A'); ?></h6>
            </div>
            <div class="mb-3">
                <p class="text-muted mb-0 small uppercase fw-bold">Phone</p>
                <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_phone'] ?? 'N/A'); ?></h6>
            </div>
            <hr>
            <h5 class="fw-bold mb-3">Shipping Address</h5>
            <div class="mb-3">
                <p class="text-muted mb-0 small uppercase fw-bold">Address</p>
                <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_address'] ?? 'N/A'); ?></h6>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <p class="text-muted mb-0 small uppercase fw-bold">City</p>
                    <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_city'] ?? 'N/A'); ?></h6>
                </div>
                <div class="col-6 mb-3">
                    <p class="text-muted mb-0 small uppercase fw-bold">Zip Code</p>
                    <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_zip'] ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <p class="text-muted mb-0 small uppercase fw-bold">State</p>
                    <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_state'] ?? 'N/A'); ?></h6>
                </div>
                <div class="col-6 mb-3">
                    <p class="text-muted mb-0 small uppercase fw-bold">Country</p>
                    <h6 class="fw-bold"><?php echo htmlspecialchars($order['user_country'] ?? 'N/A'); ?></h6>
                </div>
            </div>
            <hr>
            <div class="mb-3">
                <p class="text-muted mb-0 small uppercase fw-bold">Payment Method</p>
                <?php if (isset($order['payment_mode']) && $order['payment_mode'] === 'COD_PARTIAL'): ?>
                <h6 class="fw-bold">COD (Partial Advance)</h6>
                <?php else: ?>
                <h6 class="fw-bold"><?php echo strtoupper($order['payment_method']); ?></h6>
                <?php endif; ?>
            </div>

            <?php if (isset($order['payment_mode']) && $order['payment_mode'] === 'COD_PARTIAL'): ?>
            <!-- ── Partial COD Breakdown ──────────────────────────────── -->
            <div class="border rounded-3 p-3 mb-3" style="background:#f0fff4;">
                <p class="text-muted mb-2 small fw-bold text-uppercase">Payment Breakdown</p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i>Advance Paid (Online)</span>
                    <span class="fw-bold text-success">
                        <?php echo $global_currency; ?><?php echo number_format((float)($order['advance_amount'] ?? 0), 2); ?>
                        <i class="fas fa-check-circle text-success ms-1"></i>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><i class="fas fa-truck text-warning me-1"></i>Remaining COD (On Delivery)</span>
                    <span class="fw-bold text-warning">
                        <?php echo $global_currency; ?><?php echo number_format((float)($order['remaining_amount'] ?? 0), 2); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">Status</span>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-clock me-1"></i>Partial Paid &mdash; COD Pending
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-0">
                <p class="text-muted mb-0 small uppercase fw-bold">Order Date</p>
                <h6 class="fw-bold"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></h6>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3">Order Status</h5>
            <form method="POST" action="manage_orders.php">
    <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo $order_id; ?>">
                <select name="status" class="form-select mb-3" onchange="this.form.submit()">
                    <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>Pending</option>
                    <option value="processing" <?php echo $order['status']=='processing'?'selected':''; ?>>Processing</option>
                    <option value="shipped" <?php echo $order['status']=='shipped'?'selected':''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $order['status']=='delivered'?'selected':''; ?>>Delivered</option>
                    <option value="completed" <?php echo $order['status']=='completed'?'selected':''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $order['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
            </form>
        </div>
        
        <!-- Courier / Aggregator Shipment Card -->
        <?php
        require_once __DIR__ . '/../courier_module/Services/CourierManager.php';
        $courierManager = new \CourierModule\Services\CourierManager();
        $courierShipment = $courierManager->getShipmentByOrderId($order_id);
        ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0"><i class="fas fa-truck-moving text-primary me-2"></i>Courier &amp; Logistics</h5>
                <?php if ($courierShipment): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                        <i class="fas fa-check-circle me-1"></i>Synced
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                        Not Dispatched
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($courierShipment): ?>
                <div class="mb-2">
                    <p class="text-muted mb-0 small uppercase fw-bold">Assigned Courier</p>
                    <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($courierShipment['courier_partner_name'] ?? 'BharatShip Partner'); ?></h6>
                    <small class="text-muted">(Via <?php echo htmlspecialchars($courierShipment['provider_name']); ?>)</small>
                </div>
                <div class="mb-2 mt-2">
                    <p class="text-muted mb-0 small uppercase fw-bold">Waybill / AWB Number</p>
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="fw-bold text-primary font-monospace mb-0"><?php echo htmlspecialchars($courierShipment['awb_number']); ?></h6>
                    </div>
                </div>
                <?php if (!empty($courierShipment['routing_code'])): ?>
                    <div class="mb-2 mt-2">
                        <p class="text-muted mb-0 small uppercase fw-bold">Routing Code</p>
                        <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($courierShipment['routing_code']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="mb-3 mt-2">
                    <p class="text-muted mb-0 small uppercase fw-bold">Shipment Status</p>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info">
                        <?php echo htmlspecialchars($courierShipment['courier_status']); ?>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <?php if (!empty($courierShipment['label_url'])): ?>
                        <a href="<?php echo htmlspecialchars($courierShipment['label_url']); ?>" target="_blank" class="btn btn-primary btn-sm btn-custom px-3">
                            <i class="fas fa-print me-1"></i>Shipping Label (PDF)
                        </a>
                    <?php endif; ?>
                    <a href="https://app.bharatship.com/track/<?php echo urlencode($courierShipment['awb_number']); ?>" target="_blank" class="btn btn-light btn-sm border px-3">
                        <i class="fas fa-external-link-alt me-1"></i>Live Tracking
                    </a>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">Push order directly to BharatShip to generate AWB and shipping label in 1-click.</p>
                <button class="btn btn-primary btn-sm btn-custom px-4 shadow-sm" onclick="pushToBharatShip(<?php echo $order_id; ?>, this)" id="pushBharatShipBtn">
                    <i class="fas fa-paper-plane me-1"></i>Push to BharatShip (Auto AWB)
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Invoice Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-file-invoice text-primary me-2"></i>Invoice</h5>
            <?php if ($invoice): ?>
                <div class="mb-2">
                    <p class="text-muted mb-0 small uppercase fw-bold">Invoice Number</p>
                    <h6 class="fw-bold text-primary"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h6>
                </div>
                <div class="mb-2">
                    <p class="text-muted mb-0 small uppercase fw-bold">Invoice Date</p>
                    <h6 class="fw-bold"><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></h6>
                </div>
                <div class="mb-3">
                    <p class="text-muted mb-0 small uppercase fw-bold">WhatsApp Status</p>
                    <?php if ($invoice['whatsapp_sent']): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success">
                            <i class="fab fa-whatsapp me-1"></i>Sent <?php echo $invoice['whatsapp_sent_at'] ? date('M d, H:i', strtotime($invoice['whatsapp_sent_at'])) : ''; ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">
                            <i class="fas fa-clock me-1"></i>Not Sent
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="invoice_view.php?order_id=<?php echo $order_id; ?>" target="_blank" class="btn btn-primary btn-sm btn-custom px-3">
                        <i class="fas fa-eye me-1"></i>View
                    </a>
                    <button class="btn btn-success btn-sm btn-custom px-3 text-white" onclick="sendInvoiceWA(<?php echo $order_id; ?>, this)" id="invoiceWaBtn">
                        <i class="fab fa-whatsapp me-1"></i><?php echo $invoice['whatsapp_sent'] ? 'Resend' : 'Send'; ?>
                    </button>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">No invoice generated yet.</p>
                <button class="btn btn-primary btn-sm btn-custom px-3" onclick="generateInvoice(<?php echo $order_id; ?>, this)">
                    <i class="fas fa-magic me-1"></i>Generate Invoice
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function generateInvoice(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=generate&order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else { alert('Error: ' + data.message); btn.disabled = false; btn.innerHTML = '<i class="fas fa-magic me-1"></i>Generate Invoice'; }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-magic me-1"></i>Generate Invoice'; });
}

function sendInvoiceWA(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    const action = btn.textContent.includes('Resend') ? 'resend_whatsapp' : 'send_whatsapp';
    fetch('ajax_invoice_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.sending_mode === 'web') {
                window.open('https://wa.me/' + data.phone + '?text=' + encodeURIComponent(data.message), '_blank');
            }
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Sent!';
            btn.classList.replace('btn-success', 'btn-outline-success');
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i>Send';
        }
    })
function pushToBharatShip(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing with BharatShip...';
    fetch('../courier_module/Admin/ajax_courier_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=push_single_order&order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Order dispatched to BharatShip successfully!');
            location.reload();
        } else {
            alert('BharatShip Sync Failed: ' + (data.message || 'Please check API credentials'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Push to BharatShip (Auto AWB)';
        }
    })
    .catch(err => {
        alert('Network Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Push to BharatShip (Auto AWB)';
    });
}
</script>

<?php include 'admin_footer.php'; ?>
