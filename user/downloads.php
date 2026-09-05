<?php
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$page_title = "My Downloads";
include '../includes/header.php';

$query = "SELECT ud.*, p.name as product_name, p.image, p.license_key 
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
            <li class="breadcrumb-item"><a href="orders.php">My Account</a></li>
            <li class="breadcrumb-item active">My Downloads</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card product-card">
                <div class="list-group list-group-flush rounded border-0">
                    <a href="profile.php" class="list-group-item list-group-item-action py-3 fw-bold text-muted"><i class="fas fa-user me-2"></i> My Profile</a>
                    <a href="orders.php" class="list-group-item list-group-item-action py-3 fw-bold text-muted"><i class="fas fa-box me-2"></i> My Orders</a>
                    <a href="downloads.php" class="list-group-item list-group-item-action active py-3 bg-primary-blue border-0 fw-bold"><i class="fas fa-download me-2"></i> My Downloads</a>
                    <a href="../includes/auth.php?action=logout" class="list-group-item list-group-item-action text-danger py-3 fw-bold"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </div>
            </div>
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
                                        <th class="text-end">Downloads & License</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($d = $downloads->fetch_assoc()): ?>
                                        <?php 
                                            $is_expired = ($d['expiry_date'] && strtotime($d['expiry_date']) < time());
                                            $has_license = !empty(trim($d['license_key'] ?? ''));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars(resolve_product_image_url($d['image'] ?? '')); ?>" class="rounded" style="width: 44px; height: 44px; object-fit: cover;" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                                    <div class="ms-3">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($d['product_name']); ?></div>
                                                        <?php if ($has_license): ?>
                                                            <div class="mt-1"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small"><i class="fas fa-key me-1"></i>Includes License Key</span></div>
                                                        <?php endif; ?>
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
                                            <td class="text-end">
                                                <?php if(!$is_expired): ?>
                                                    <div class="d-flex flex-column align-items-end gap-1">
                                                        <a href="../download.php?token=<?php echo $d['download_token']; ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                                            <i class="fas fa-download me-1"></i> Download File
                                                        </a>
                                                        <?php if($has_license): ?>
                                                            <a href="../download.php?token=<?php echo $d['download_token']; ?>&type=license" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                                                <i class="fas fa-key me-1"></i> Download License Key
                                                            </a>
                                                            <button type="button" class="btn btn-light btn-sm rounded-pill px-3 text-muted border copy-lic-btn" data-key="<?php echo htmlspecialchars($d['license_key']); ?>">
                                                                <i class="fas fa-copy me-1"></i> Copy Key
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-lic-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const key = this.getAttribute('data-key');
            if (!key) return;
            navigator.clipboard.writeText(key).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-success me-1"></i> Copied!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            }).catch(() => {
                prompt('Copy your license key manually:', key);
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
