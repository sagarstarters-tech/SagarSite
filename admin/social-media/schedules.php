<?php
$current_page = 'social-media/schedules.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">Posting Schedules</h2>
            <button class="btn btn-primary mdb-ripple fw-bold" style="border-radius: 30px;">
                <i class="fas fa-plus me-2"></i> Create Schedule
            </button>
        </div>
        <div class="card shadow" style="border-radius: 15px; border: none;">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No schedules defined yet.</h5>
                <p class="text-muted mb-0">Create a schedule to automate your posting across platforms.</p>
            </div>
        </div>
    </div>
</div>
<style></style>
<script></script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>