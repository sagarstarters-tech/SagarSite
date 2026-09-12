<?php
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>

<div class="container mt-5 mb-5" style="min-height: 50vh;">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card product-card">
                <div class="list-group list-group-flush rounded border-0">
                    <a href="profile.php" class="list-group-item list-group-item-action py-3 fw-bold text-muted"><i class="fas fa-user me-2"></i> My Profile</a>
                    <a href="orders.php" class="list-group-item list-group-item-action active py-3 bg-primary-blue border-0 fw-bold"><i class="fas fa-box me-2"></i> My Orders</a>
                    <a href="../includes/auth.php?action=logout" class="list-group-item list-group-item-action text-danger py-3 fw-bold"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card product-card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="montserrat primary-blue fw-bold mb-4">My Orders</h4>
                    <?php if($orders && $orders->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($o = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold">#<?php echo $o['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                        <td>
                                            <?php if($o['payment_method'] === 'cod'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fas fa-money-bill-wave me-1"></i> COD</span>
                                            <?php elseif($o['payment_method'] === 'phonepe'): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info"><i class="fas fa-mobile-alt me-1"></i> PhonePe</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="fas fa-credit-card me-1"></i> Card</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($o['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">Pending</span>
                                            <?php elseif($o['status'] == 'shipped'): ?>
                                                <span class="badge bg-info px-2 py-1">Shipped</span>
                                            <?php elseif($o['status'] == 'delivered'): ?>
                                                <span class="badge bg-success px-2 py-1">Delivered</span>
                                            <?php elseif($o['status'] == 'cancelled'): ?>
                                                <span class="badge bg-danger px-2 py-1">Cancelled</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-2 py-1"><?php echo ucfirst($o['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?php echo (string)$global_currency; ?><?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-primary btn-custom px-3">Details</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 border rounded bg-light border-light">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h5 class="fw-bold">No orders found</h5>
                            <p class="text-muted">You haven't placed any orders yet.</p>
                            <a href="../shop.php" class="btn btn-primary btn-custom mt-2 px-4">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
