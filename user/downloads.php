<?php
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = "My Downloads";
include '../includes/header.php';

$query = "SELECT ud.*, p.name as product_name, p.image 
          FROM user_downloads ud 
          JOIN products p ON ud.product_id = p.id 
          WHERE ud.user_id = $user_id 
          ORDER BY ud.created_at DESC";
$downloads = $conn->query($query);
?>

<div class="container my-5" style="min-height: 60vh;">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="order_history.php">My Account</a></li>
            <li class="breadcrumb-item active">My Downloads</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-3">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4"><i class="fas fa-download me-2 text-primary"></i>My Downloads</h4>
                    
                    <?php if($downloads && $downloads->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Purchased On</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($d = $downloads->fetch_assoc()): ?>
                                        <?php 
                                            $is_expired = ($d['expiry_date'] && strtotime($d['expiry_date']) < time());
                                            // We get limit from product table in a real scenario or passed to user_downloads
                                            // Let's assume we can check if it's downloadble
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars(resolve_product_image_url($d['image'] ?? '')); ?>" class="rounded" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                                    <div class="ms-3">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($d['product_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                                            <td>
                                                <?php if($is_expired): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if(!$is_expired): ?>
                                                    <a href="../download.php?token=<?php echo $d['download_token']; ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                                                        <i class="fas fa-download me-1"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm rounded-pill px-3" disabled>Expired</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-cloud-download-alt fa-3x text-light mb-3"></i>
                            <h5 class="text-muted">You haven't purchased any downloadable products yet.</h5>
                            <a href="../index.php" class="btn btn-primary mt-3">Browse Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
