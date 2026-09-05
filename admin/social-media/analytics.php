<?php
$current_page = 'social-media/analytics.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT 1 FROM sm_connected_accounts LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">
<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-chart-line"></i> Performance & Reach
            </div>
            <h1 class="adm-hero-title">Social Media Analytics & Reach</h1>
            <p class="adm-hero-subtitle">Measure automated post volume, delivery success rates, and omnichannel campaign performance.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="queue.php" class="adm-btn-white me-2">
                <i class="fas fa-list me-2"></i>Post Queue
            </a>
            <button class="adm-btn-primary" onclick="alert('Exporting report...')"><i class="fas fa-download me-2"></i>Export CSV</button>
        </div>
    </div>
        
    <div class="row mb-4">
        <div class="col-12">
            <div class="adm-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h5 class="fw-bold m-0 text-dark"><i class="fas fa-chart-area me-2 text-primary"></i>Performance Over Time</h5>
                    <select class="form-select w-auto rounded-pill shadow-sm">
                        <option>Last 7 Days</option>
                        <option selected>Last 30 Days</option>
                        <option>Last 90 Days</option>
                    </select>
                </div>
                <div style="height: 350px;">
                    <canvas id="analyticsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<style></style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('analyticsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['1st', '5th', '10th', '15th', '20th', '25th', '30th'],
            datasets: [{
                label: 'Published Posts',
                data: [5, 12, 8, 15, 20, 10, 25],
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Failed Posts',
                data: [1, 0, 2, 0, 1, 0, 0],
                borderColor: '#dc3545',
                backgroundColor: 'transparent',
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>
<?php include_once __DIR__ . '/../admin_footer.php'; ?>