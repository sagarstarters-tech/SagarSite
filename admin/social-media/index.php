<?php
$current_page = 'social-media/index.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="admin-content">
    <div class="container-fluid py-4">
        <h2 class="mb-4 fw-bold">Social Media Dashboard</h2>
        
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white shadow h-100" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0); border-radius: 15px; border: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 fw-bold">Total Scheduled</h6>
                                <h2 class="mb-0 fw-bold">0</h2>
                            </div>
                            <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white shadow h-100" style="background: linear-gradient(135deg, #198754, #20c997); border-radius: 15px; border: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 fw-bold">Total Published</h6>
                                <h2 class="mb-0 fw-bold">0</h2>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white shadow h-100" style="background: linear-gradient(135deg, #dc3545, #fd7e14); border-radius: 15px; border: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 fw-bold">Failed Posts</h6>
                                <h2 class="mb-0 fw-bold">0</h2>
                            </div>
                            <i class="fas fa-times-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white shadow h-100" style="background: linear-gradient(135deg, #6f42c1, #d63384); border-radius: 15px; border: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 fw-bold">Upcoming Posts</h6>
                                <h2 class="mb-0 fw-bold">0</h2>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card shadow h-100" style="border-radius: 15px; border: none;">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold text-primary">Platform Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; display: flex; justify-content: center;">
                            <canvas id="platformChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card shadow h-100" style="border-radius: 15px; border: none;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="m-0 fw-bold text-primary">Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <ul class="list-group list-group-flush" id="activity-feed">
                            <li class="list-group-item px-0 border-0 text-muted">No recent activity found.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow" style="border-radius: 15px; border: none;">
                    <div class="card-body d-flex flex-wrap gap-2">
                        <a href="queue.php" class="btn btn-primary mdb-ripple" style="border-radius: 30px;"><i class="fas fa-plus me-2"></i>New Post</a>
                        <a href="bulk-schedule.php" class="btn btn-info mdb-ripple text-white" style="border-radius: 30px;"><i class="fas fa-layer-group me-2"></i>Bulk Schedule</a>
                        <a href="accounts.php" class="btn btn-dark mdb-ripple" style="border-radius: 30px;"><i class="fas fa-link me-2"></i>Connect Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.card { transition: transform 0.2s ease; }
.card:hover { transform: translateY(-3px); }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('platformChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Facebook', 'Instagram', 'Twitter', 'LinkedIn', 'Telegram', 'Pinterest'],
            datasets: [{
                data: [1, 1, 1, 1, 1, 1],
                backgroundColor: ['#1877F2', '#E4405F', '#000000', '#0A66C2', '#0088CC', '#E60023'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
});
</script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>