<?php
$current_page = 'social-media/templates.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">Caption Templates</h2>
            <button class="btn btn-primary mdb-ripple fw-bold" style="border-radius: 30px;" data-mdb-toggle="modal" data-mdb-target="#createTemplateModal">
                <i class="fas fa-plus me-2"></i> Create Template
            </button>
        </div>
        
        <div class="row">
            <div class="col-md-6 col-xl-4 mb-4">
                <div class="card shadow h-100" style="border-radius: 15px; border: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold m-0">Default Promotion</h5>
                            <span class="badge bg-success rounded-pill">Default</span>
                        </div>
                        <div class="p-3 bg-light mb-3" style="border-radius: 10px; font-family: monospace; font-size: 14px;">
                            Check out {product_name}! Now only {sale_price}.<br>
                            Shop here: {product_url}<br>
                            {hashtags}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary mdb-ripple flex-fill" style="border-radius: 20px;"><i class="fas fa-edit me-1"></i>Edit</button>
                            <button class="btn btn-sm btn-outline-info mdb-ripple flex-fill" style="border-radius: 20px;"><i class="fas fa-copy me-1"></i>Clone</button>
                            <button class="btn btn-sm btn-outline-danger mdb-ripple flex-fill" style="border-radius: 20px;"><i class="fas fa-trash me-1"></i>Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Create New Template</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Template Name</label>
                            <input type="text" class="form-control" placeholder="e.g. Summer Sale Template">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Variables</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button class="btn btn-sm btn-light border mdb-ripple rounded-pill">{product_name}</button>
                                <button class="btn btn-sm btn-light border mdb-ripple rounded-pill">{price}</button>
                                <button class="btn btn-sm btn-light border mdb-ripple rounded-pill">{sale_price}</button>
                                <button class="btn btn-sm btn-light border mdb-ripple rounded-pill">{product_url}</button>
                                <button class="btn btn-sm btn-light border mdb-ripple rounded-pill">{hashtags}</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Template Body</label>
                            <textarea class="form-control font-monospace" rows="8" placeholder="Write your template here..."></textarea>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card bg-light border-0" style="border-radius: 15px;">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Live Preview</h6>
                                <div class="preview-box p-3 bg-white border" style="border-radius: 10px; min-height: 200px;">
                                    Preview will appear here...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary mdb-ripple rounded-pill" data-mdb-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary mdb-ripple rounded-pill px-4">Save Template</button>
            </div>
        </div>
    </div>
</div>
<style>
.card { transition: all 0.3s ease; }
.card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>