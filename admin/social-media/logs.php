<?php
$current_page = 'social-media/logs.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Activity Logs</h2>
        <div class="card shadow" style="border-radius: 15px; border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex gap-2">
                        <select class="form-select" style="width: auto;">
                            <option value="">All Levels</option>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                        <select class="form-select" style="width: auto;">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" placeholder="Search logs..." style="width: 250px;">
                        <button class="btn btn-outline-danger mdb-ripple"><i class="fas fa-trash me-1"></i> Clear Old</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Level</th>
                                <th>Platform</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No logs recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<style></style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>