<?php
include 'admin_header.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    ?>
    <div class="container-fluid px-4 py-4 adm-wrapper">
        <!-- Hero / Header Banner -->
        <div class="adm-hero">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="adm-hero-badge">
                            <i class="fas fa-map-marker-alt"></i> Live Tracking &amp; Delivery
                        </span>
                    </div>
                    <h2 class="adm-hero-title">Order Tracking Hub 📍</h2>
                    <p class="adm-hero-subtitle">Look up any customer order to inspect courier progress, update status, and manage AWB numbers.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="manage_orders.php" class="btn adm-btn-white">
                        <i class="fas fa-list text-primary"></i>
                        <span>All Orders</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="adm-card shadow-sm border-0 rounded-4">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <div class="adm-icon-box adm-icon-blue mx-auto" style="width: 70px; height: 70px; font-size: 2rem;">
                        <i class="fas fa-search-location"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2">Enter Order ID to Track</h4>
                <p class="text-muted mb-4 mx-auto" style="max-width: 440px;">
                    Enter a specific Order ID below to view real-time delivery milestones, carrier information, and tracking details.
                </p>
                
                <form action="manage_order_tracking.php" method="GET" class="d-flex justify-content-center mx-auto" style="max-width: 440px;">
                    <?php echo csrf_input(); ?>
                    <div class="input-group shadow-sm rounded-pill overflow-hidden p-1 border bg-white">
                        <span class="input-group-text bg-white border-0 ps-3 text-muted"><i class="fas fa-hashtag"></i></span>
                        <input type="number" name="id" class="form-control border-0 ps-1" placeholder="Enter Order ID (e.g. 31)..." required>
                        <button class="btn btn-primary px-4 fw-bold rounded-pill" type="submit">Track Now</button>
                    </div>
                </form>
                
                <div class="mt-4 pt-2">
                    <p class="small text-muted mb-0">Alternatively, you can select any order directly from the <a href="manage_orders.php" class="text-decoration-underline text-primary fw-bold">Manage Orders</a> dashboard.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'admin_footer.php';
    exit;
}
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <!-- Hero / Header Banner -->
    <div class="adm-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="adm-hero-badge">
                        <i class="fas fa-shipping-fast"></i> Order Telemetry
                    </span>
                    <span class="adm-hero-badge badge-success">
                        <i class="fas fa-box"></i> Order #<?php echo $order_id; ?>
                    </span>
                </div>
                <h2 class="adm-hero-title">Order Tracking - #<?php echo $order_id; ?> 📍</h2>
                <p class="adm-hero-subtitle">Manage courier carrier, live tracking number, milestone updates, and estimated delivery dates.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn adm-btn-white">
                    <i class="fas fa-eye text-primary"></i>
                    <span>Order Details</span>
                </a>
                <a href="manage_orders.php" class="btn btn-primary px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold text-white">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Orders</span>
                </a>
            </div>
        </div>
    </div>

    <?php 
    // Inject the modular tracking panel
    include __DIR__ . '/../tracking_module_src/examples/admin_tracking_panel.php'; 
    ?>
</div>

<?php include 'admin_footer.php'; ?>
