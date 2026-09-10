<?php
// session_start() is already called in includes/header.php

include 'includes/header.php'; // Include existing store header
?>
<style>
/* Custom Progress Bar CSS for Tracking */
.tracking-progress-container {
    width: 100%;
}
.tracking-progress {
    display: flex;
    justify-content: space-between;
    list-style-type: none;
    padding: 0;
    margin: 0;
    position: relative;
    z-index: 1;
}
.tracking-progress::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    width: 100%;
    height: 4px;
    background-color: #e9ecef;
    z-index: -1;
}
.tracking-progress li {
    text-align: center;
    position: relative;
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    padding-top: 35px;
}
.tracking-progress li::before {
    content: '';
    position: absolute;
    top: 5px;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #fff;
    border: 4px solid #dee2e6;
    z-index: 2;
    transition: all 0.3s ease;
}
.tracking-progress li.completed {
    color: #0d6efd;
}
.tracking-progress li.completed::before {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.tracking-progress li.active {
    color: #0d6efd;
}
.tracking-progress li.active::before {
    background-color: #fff;
    border-color: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
}
.tracking-progress li.completed + li::after,
.tracking-progress li.active + li::after {
    /* If we want a solid color bar fill behind, we'd style a separate element, 
       but for simplicity the circles work well. */
}

/* AWB Shipment Tracking Section Styles */
.awb-tracking-card {
    background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%);
    border: 2px solid #dee2e6;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.awb-tracking-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.12);
}
.awb-tracking-header {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.awb-tracking-header .icon-circle {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.awb-tracking-header h6 {
    color: white;
    margin: 0;
    font-weight: 700;
}
.awb-tracking-header small {
    color: rgba(255,255,255,0.7);
}
.awb-number-display {
    font-family: 'Courier New', Courier, monospace;
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: 2px;
    color: #1a1a2e;
    background: white;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 12px 20px;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
}
.awb-number-display:hover {
    border-color: #0d6efd;
    background: #f0f4ff;
}
.btn-track-awb {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    color: white;
    padding: 12px 28px;
    border-radius: 50rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}
.btn-track-awb:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    color: white;
}
.btn-copy-awb {
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    color: #495057;
    padding: 12px 28px;
    border-radius: 50rem;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-copy-awb:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    transform: translateY(-2px);
}
@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
}
.pulse-live {
    animation: pulseGlow 2s infinite;
}
</style>

<?php
$prefill_order_id = isset($_GET['order_id']) && is_numeric($_GET['order_id']) ? (int)$_GET['order_id'] : '';
$prefill_identifier = htmlspecialchars(trim($_GET['email'] ?? ($_GET['phone'] ?? ($_GET['identifier'] ?? ''))));
?>
<div class="container my-5 py-4" style="min-height: 60vh;">
    
    <div class="row justify-content-center mb-5">
        <div class="col-md-8 col-lg-6 text-center">
            <h2 class="fw-bold mb-3"><i class="fas fa-search-location text-primary me-2"></i>Track Your Order</h2>
            <p class="text-muted">Enter your Order ID and registered Mobile Number or Email below to view real-time shipping updates.</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <!-- Input Form -->
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <form id="trackingForm" class="row gx-3 gy-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Order Number (#) <span class="text-muted fw-normal small">(Optional)</span></label>
                            <input type="number" id="track_order_id" class="form-control form-control-lg" placeholder="e.g. 1024" value="<?php echo $prefill_order_id ?: ''; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Mobile Number or Email <span class="text-danger">*</span></label>
                            <input type="text" id="track_email" class="form-control form-control-lg" placeholder="e.g. 9876543210 or user@email.com" value="<?php echo $prefill_identifier; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm d-flex align-items-center justify-content-center gap-2" style="white-space: nowrap; height: calc(3.5rem + 2px);"><i class="fas fa-search"></i><span>Track Order</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tracking Output Container -->
            <div id="trackingResultContainer" class="d-none mt-4">
                <!-- Result populated by ajax_tracking.js -->
            </div>
        </div>
    </div>

</div>

<!-- Inject base URL for tracking API (handles subdirectory like /store on XAMPP) -->
<script>
    window.TRACKING_API_URL = '<?php echo rtrim(SITE_URL, '/'); ?>/tracking_module_src/TrackingAPI.php';
</script>
<!-- Include JS Script -->
<script src="<?php echo rtrim(SITE_URL, '/'); ?>/tracking_module_src/examples/ajax_tracking.js"></script>

<?php include 'includes/footer.php'; ?>
