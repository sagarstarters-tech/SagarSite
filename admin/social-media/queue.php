<?php
$current_page = 'social-media/queue.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Queue Management</h2>
        
        <div class="card shadow mb-4" style="border-radius: 15px; border: none;">
            <div class="card-body">
                <ul class="nav nav-tabs nav-justified mb-3" id="queueTabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active fw-bold" data-mdb-toggle="tab" href="#all">All</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" data-mdb-toggle="tab" href="#pending">Pending <span class="badge bg-warning ms-1">0</span></a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" data-mdb-toggle="tab" href="#scheduled">Scheduled <span class="badge bg-info ms-1">0</span></a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" data-mdb-toggle="tab" href="#publishing">Publishing <span class="badge bg-primary ms-1">0</span></a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" data-mdb-toggle="tab" href="#posted">Posted <span class="badge bg-success ms-1">0</span></a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" data-mdb-toggle="tab" href="#failed">Failed <span class="badge bg-danger ms-1">0</span></a></li>
                </ul>
                
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex gap-2">
                        <select class="form-select" style="width: auto;">
                            <option>Bulk Actions</option>
                            <option>Approve Selected</option>
                            <option>Cancel Selected</option>
                            <option>Delete Selected</option>
                            <option>Retry Failed</option>
                        </select>
                        <button class="btn btn-primary mdb-ripple">Apply</button>
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select" style="width: auto;">
                            <option value="">All Platforms</option>
                        </select>
                        <input type="text" class="form-control" placeholder="Search product..." style="width: 200px;">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" class="form-check-input"></th>
                                <th>Product</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Scheduled At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="py-4 text-muted">No posts found in the queue.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.nav-tabs .nav-link.active { border-bottom: 3px solid #0d6efd; color: #0d6efd !important; }
.nav-tabs .nav-link { color: #4f4f4f; border: none; }
</style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>