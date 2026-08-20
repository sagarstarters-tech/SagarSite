<?php
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Fetch order info and verify ownership
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_q = $stmt->get_result();

if (!$order_q || $order_q->num_rows === 0) {
    ?>
    <div class="container my-5 text-center">
        <div class="alert alert-danger py-4">
            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
            <h4>Order Not Found</h4>
            <p>The order you are looking for does not exist or you do not have permission to view it.</p>
            <a href="orders.php" class="btn btn-primary btn-custom mt-2">Back to My Orders</a>
        </div>
    </div>
    <?php
    include '../includes/footer.php';
    exit;
}

$order = $order_q->fetch_assoc();

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image as product_image, p.product_type, p.image_fit 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_q = $stmt->get_result();


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

<style>
    .tracking-progress-container { width: 100%; position: relative; margin: 2rem 0; }
    .tracking-progress { display: flex; justify-content: space-between; list-style: none; padding: 0; margin: 0; position: relative; z-index: 1; }
    .tracking-progress::before { content: ''; position: absolute; top: 12px; left: 0; width: 100%; height: 4px; background: #e9ecef; z-index: -1; }
    .tracking-progress li { flex: 1; text-align: center; font-size: 0.85rem; font-weight: 600; color: #6c757d; position: relative; padding-top: 30px; }
    .tracking-progress li::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 28px; height: 28px; border-radius: 50%; background: #fff; border: 4px solid #dee2e6; z-index: 2; transition: all 0.3s; }
    .tracking-progress li.completed { color: #0d6efd; }
    .tracking-progress li.completed::before { background: #0d6efd; border-color: #0d6efd; }
    .tracking-progress li.active { color: #0d6efd; }
    .tracking-progress li.active::before { background: #fff; border-color: #0d6efd; box-shadow: 0 0 0 5px rgba(13, 110, 253, 0.15); }
    
    .timeline-item { position: relative; padding-left: 2rem; border-left: 2px solid #e9ecef; padding-bottom: 1.5rem; }
    .timeline-item:last-child { border-left-color: transparent; }
    .timeline-item::before { content: ''; position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: #dee2e6; border: 2px solid #fff; }
    .timeline-item.latest::before { background: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2); }
    
    .product-thumb { width: 60px; height: 60px; object-fit: contain; border-radius: 8px; background-color: #fff; padding: 5px; aspect-ratio: 1/1; }
</style>

<div class="container my-5 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="montserrat fw-bold primary-blue mb-0">Order Details</h2>
            <p class="text-muted">Order #<?php echo $order_id; ?> &bull; Placed on <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
        </div>
        <a href="orders.php" class="btn btn-outline-primary btn-custom rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Orders
        </a>
    </div>

    <div class="row">
        <!-- Order Items & Summary -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
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

                                    // Fetch download token if available
                                    $dl_token = null;
                                    $stmt = $conn->prepare("SELECT download_token FROM user_downloads WHERE order_id = ? AND product_id = ?");
                                    $stmt->bind_param("ii", $order_id, $item['product_id']);
                                    $stmt->execute();
                                    $dl_q = $stmt->get_result();
                                    
                                    if ($dl_q && $dl_q->num_rows > 0) {
                                        $dl_token = $dl_q->fetch_assoc()['download_token'];
                                    }
                                    $stmt->close();
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
                                             class="product-thumb me-3 border" 
                                             style="object-fit: <?php echo $item_fit; ?>;"
                                             alt="<?php echo htmlspecialchars($item['product_name'] ?? ''); ?>">
                                        <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <small class="text-muted">ID: #<?php echo $item['product_id']; ?></small>
                                                <?php if ($dl_token): ?>
                                                    <div class="mt-2 text-danger">
                                                        <a href="../download.php?token=<?php echo $dl_token; ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 scale-up">
                                                            <i class="fas fa-download me-1"></i> Download File
                                                        </a>
                                                    </div>
                                                <?php elseif ($item['product_type'] === 'virtual'): ?>
                                                    <div class="mt-1"><span class="badge bg-info bg-opacity-10 text-info border border-info">Virtual Product</span></div>
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
                                <span><?php echo $global_currency; ?><?php echo number_format($total_item_price, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping Cost</span>
                                <span><?php echo $total_shipping > 0 ? $global_currency . number_format($total_shipping, 2) : '<span class="text-success">Free Shipping</span>'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tax (0%)</span>
                                <span><?php echo $global_currency; ?>0.00</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Grand Total</span>
                                <span class="text-primary"><?php echo $global_currency; ?><?php echo number_format($total_item_price + $total_shipping, 2); ?></span>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking Logic Header -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <?php 
                    // Check if any item is virtual for a banner notice
                    $has_virtual = false;
                    $items_q->data_seek(0);
                    while($it = $items_q->fetch_assoc()) {
                        if($it['product_type'] === 'virtual') { $has_virtual = true; break; }
                    }
                    $items_q->data_seek(0); // Reset for the main table loop
                    
                    if ($has_virtual): 
                ?>
                    <div class="alert alert-info border-0 rounded-4 p-4 mb-4">
                        <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>Virtual Product Notice</h6>
                        <p class="mb-0 small">This order contains virtual items. These do not require physical shipping and will be processed/delivered digitally. Please check your email or contact support for delivery details.</p>
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold mb-4">Delivery Status</h5>

                
                <div class="tracking-progress-container mb-4">
                    <ul class="tracking-progress">
                        <?php if($stageIndex === -1): ?>
                            <li class="text-danger w-100 text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i><br>Order Cancelled
                            </li>
                        <?php else: ?>
                            <?php foreach($stages as $i => $s): 
                                $class = '';
                                if ($i < $stageIndex) $class = 'completed';
                                if ($i === $stageIndex) $class = 'active';
                                
                                $label = $s;
                                if ($i === 2 && $order['status'] === 'partially_shipped') $label = 'Partially Shipped';
                            ?>
                                <li class="<?php echo $class; ?>"><?php echo $label; ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 border-end">
                        <p class="text-muted mb-1 small uppercase fw-bold">Courier Partner</p>
                        <h6 class="fw-bold"><?php echo $shipping['courier_name'] ?: 'Not Assigned'; ?></h6>
                        
                        <p class="text-muted mb-1 mt-3 small uppercase fw-bold">Estimated Delivery</p>
                        <h6 class="fw-bold text-success"><?php echo $shipping['estimated_delivery'] ?: 'TBD'; ?></h6>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <p class="text-muted mb-1 small uppercase fw-bold">Tracking Number</p>
                        <h6 class="fw-bold">
                            <?php echo $shipping['tracking_number'] ?: 'Awaiting dispatch'; ?> 
                            <?php if($shipping['tracking_url']): ?>
                                <a href="<?php echo $shipping['tracking_url']; ?>" target="_blank" class="ms-2"><i class="fas fa-external-link-alt"></i></a>
                            <?php endif; ?>
                        </h6>
                        
                        <p class="text-muted mb-1 mt-3 small uppercase fw-bold">Payment Method</p>
                        <h6 class="fw-bold"><?php echo strtoupper($order['payment_method']); ?></h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline / Sidebar Info -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold mb-4">Tracking History</h5>
                <div class="tracking-timeline mt-3">
                    <?php if(!empty($timeline)): ?>
                        <?php foreach($timeline as $index => $log): ?>
                            <div class="timeline-item <?php echo $index === 0 ? 'latest' : ''; ?>">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-primary"><?php echo ucwords(str_replace('_', ' ', $log['status'])); ?></strong>
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 mt-1 text-muted small"><?php echo htmlspecialchars($log['notes']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="timeline-item latest">
                            <div class="d-flex justify-content-between">
                                <strong class="text-primary">Order Placed</strong>
                                <small class="text-muted"><?php echo date('M d, H:i', strtotime($order['created_at'])); ?></small>
                            </div>
                            <p class="mb-0 mt-1 text-muted small">Your order has been successfully placed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 bg-primary text-white">
                <h5 class="fw-bold mb-3">Need Help?</h5>
                <p class="small opacity-75 mb-4">If you have any questions regarding your order, our support team is ready to help 24/7.</p>
                <a href="../contact.php" class="btn btn-light btn-custom rounded-pill w-100 fw-bold">Contact Support</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
