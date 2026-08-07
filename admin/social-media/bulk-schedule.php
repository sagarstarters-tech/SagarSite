<?php
$current_page = 'social-media/bulk-schedule.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Bulk Schedule Posts</h2>
        <div class="card shadow" style="border-radius: 15px; border: none;">
            <div class="card-body p-5">
                <h4 class="mb-4 text-primary fw-bold"><i class="fas fa-box me-2"></i>Step 1: Select Products</h4>
                <div class="mb-4">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="productSelection" id="selectAllProds" value="all" checked>
                        <label class="form-check-label" for="selectAllProds">All Products</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="productSelection" id="selectManual" value="manual">
                        <label class="form-check-label" for="selectManual">Manual Selection</label>
                    </div>
                </div>
                
                <hr class="my-4">
                <h4 class="mb-4 text-primary fw-bold"><i class="fas fa-cogs me-2"></i>Step 2: Configure Platforms & Templates</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Platforms</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="platFB" value="facebook">
                                <label class="form-check-label" for="platFB"><i class="fab fa-facebook text-primary"></i> Facebook</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="platIG" value="instagram">
                                <label class="form-check-label" for="platIG"><i class="fab fa-instagram text-danger"></i> Instagram</label>
                            </div>
                            <!-- ... other platforms ... -->
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Template</label>
                        <select class="form-select mb-3">
                            <option>Default Template</option>
                        </select>
                        <label class="form-label fw-bold">Call to Action (CTA)</label>
                        <select class="form-select mb-3">
                            <option>Buy Now</option>
                            <option>Shop Now</option>
                        </select>
                        <label class="form-label fw-bold">Custom Hashtags</label>
                        <textarea class="form-control" rows="2" placeholder="#sale #new"></textarea>
                    </div>
                </div>
                
                <hr class="my-4">
                <h4 class="mb-4 text-primary fw-bold"><i class="fas fa-check-circle me-2"></i>Step 3: Review & Confirm</h4>
                <div class="alert alert-info" style="border-radius: 10px;">
                    <strong>Summary:</strong> You are about to schedule posts for 0 products across 0 platforms.
                </div>
                
                <div class="mt-4 text-end">
                    <button class="btn btn-primary mdb-ripple px-5 py-2 fw-bold" style="border-radius: 30px;"><i class="fas fa-paper-plane me-2"></i>Confirm & Schedule</button>
                </div>
            </div>
        </div>
    </div>
<style></style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>