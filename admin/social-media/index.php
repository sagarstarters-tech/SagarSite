<?php
$current_page = 'social-media/index.php';
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

// 1. Fetch Live Queue Statistics
$totalScheduled = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE status = 'scheduled'")->fetchColumn();
$totalPublished = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE status = 'posted'")->fetchColumn();
$totalFailed    = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE status = 'failed'")->fetchColumn();
$upcomingPosts  = (int)$pdo->query("SELECT COUNT(*) FROM sm_queue WHERE status IN ('scheduled', 'pending') AND (scheduled_at >= NOW() OR scheduled_at IS NULL)")->fetchColumn();

// 2. Fetch Platform Distribution Data for Chart
$platformCounts = [
    'facebook' => 0,
    'instagram' => 0,
    'twitter' => 0,
    'linkedin' => 0,
    'telegram' => 0,
    'pinterest' => 0
];
$stmtDist = $pdo->query("SELECT LOWER(platform) as p, COUNT(*) as c FROM sm_queue GROUP BY LOWER(platform)");
while ($row = $stmtDist->fetch(PDO::FETCH_ASSOC)) {
    $pKey = strtolower(trim($row['p']));
    if (isset($platformCounts[$pKey])) {
        $platformCounts[$pKey] = (int)$row['c'];
    }
}

// 3. Fetch Recent Activity / Posts
$stmtRecent = $pdo->query("SELECT q.*, p.name as product_name 
    FROM sm_queue q 
    LEFT JOIN products p ON q.product_id = p.id 
    ORDER BY q.updated_at DESC, q.id DESC 
    LIMIT 10");
$recentActivities = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

$platformIcons = [
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'name' => 'Facebook'],
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'name' => 'Instagram'],
    'twitter' => ['icon' => 'fab fa-x-twitter', 'color' => '#000000', 'name' => 'X (Twitter)'],
    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'name' => 'LinkedIn'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088CC', 'name' => 'Telegram'],
    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#E60023', 'name' => 'Pinterest']
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold m-0">Social Media Dashboard</h2>
            <p class="text-muted small m-0">Real-time overview of your automated social media posting activities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="queue.php" class="btn btn-outline-primary rounded-pill">
                <i class="fas fa-list me-1"></i> View Queue
            </a>
            <a href="bulk-schedule.php" class="btn btn-primary rounded-pill shadow-sm">
                <i class="fas fa-layer-group me-1"></i> Bulk Schedule
            </a>
        </div>
    </div>
    
    <!-- Top Stat Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="card text-white shadow-sm h-100 border-0 rounded-4" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 fw-bold opacity-75 small">Total Scheduled</h6>
                            <h2 class="mb-0 fw-bold display-6"><?php echo number_format($totalScheduled); ?></h2>
                        </div>
                        <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="card text-white shadow-sm h-100 border-0 rounded-4" style="background: linear-gradient(135deg, #198754, #20c997);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 fw-bold opacity-75 small">Total Published</h6>
                            <h2 class="mb-0 fw-bold display-6"><?php echo number_format($totalPublished); ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="card text-white shadow-sm h-100 border-0 rounded-4" style="background: linear-gradient(135deg, #dc3545, #fd7e14);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 fw-bold opacity-75 small">Failed Posts</h6>
                            <h2 class="mb-0 fw-bold display-6"><?php echo number_format($totalFailed); ?></h2>
                        </div>
                        <i class="fas fa-times-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="card text-white shadow-sm h-100 border-0 rounded-4" style="background: linear-gradient(135deg, #6f42c1, #d63384);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 fw-bold opacity-75 small">Upcoming Posts</h6>
                            <h2 class="mb-0 fw-bold display-6"><?php echo number_format($upcomingPosts); ?></h2>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Activity -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7 col-xl-8">
            <div class="card shadow border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Platform Distribution</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center p-4">
                    <div style="width: 100%; max-height: 320px; position: relative;">
                        <canvas id="platformChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-xl-4">
            <div class="card shadow border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-dark"><i class="fas fa-stream me-2 text-primary"></i>Recent Activity</h5>
                    <a href="queue.php" class="small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="card-body p-3" style="max-height: 340px; overflow-y: auto;">
                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 text-secondary d-block"></i>
                            <p class="small m-0">No recent activity found.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentActivities as $act): 
                                $pKey = strtolower($act['platform']);
                                $pMeta = $platformIcons[$pKey] ?? ['icon' => 'fas fa-share-alt', 'color' => '#0d6efd', 'name' => ucfirst($pKey)];
                                $prodName = !empty($act['product_name']) ? $act['product_name'] : 'Product #' . $act['product_id'];
                                $st = strtolower($act['status']);
                                $stBadge = $st === 'posted' ? 'bg-success text-white' : ($st === 'failed' ? 'bg-danger text-white' : 'bg-primary text-white');
                            ?>
                                <li class="list-group-item px-2 py-3 border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                                        <i class="<?php echo $pMeta['icon']; ?> fs-4" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                        <div class="text-truncate">
                                            <div class="fw-bold small text-truncate" title="<?php echo htmlspecialchars($prodName); ?>">
                                                <?php echo htmlspecialchars($prodName); ?>
                                            </div>
                                            <div class="extra-small text-muted">
                                                <?php echo htmlspecialchars($pMeta['name']); ?> • <?php echo date('M d, h:i A', strtotime($act['updated_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $stBadge; ?> rounded-pill px-2 py-1 extra-small">
                                        <?php echo ucfirst($st); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions Banner -->
    <div class="card shadow border-0 rounded-4">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold m-0 text-dark">Quick Management Actions</h5>
                <p class="text-muted small m-0">Easily manage your queue, schedule products in bulk, or connect social media platforms.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="queue.php" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-list me-2"></i> Post Queue
                </a>
                <a href="bulk-schedule.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fas fa-layer-group me-2"></i> Bulk Schedule
                </a>
                <a href="accounts.php" class="btn btn-dark rounded-pill px-4">
                    <i class="fas fa-link me-2"></i> Connect Accounts
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.extra-small { font-size: 0.75rem; }
.card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.card:hover { transform: translateY(-3px); }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('platformChart').getContext('2d');
    
    const platformData = [
        <?php echo $platformCounts['facebook']; ?>,
        <?php echo $platformCounts['instagram']; ?>,
        <?php echo $platformCounts['twitter']; ?>,
        <?php echo $platformCounts['linkedin']; ?>,
        <?php echo $platformCounts['telegram']; ?>,
        <?php echo $platformCounts['pinterest']; ?>
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Facebook', 'Instagram', 'X (Twitter)', 'LinkedIn', 'Telegram', 'Pinterest'],
            datasets: [{
                data: platformData,
                backgroundColor: ['#1877F2', '#E4405F', '#000000', '#0A66C2', '#0088CC', '#E60023'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'right',
                    labels: {
                        font: { size: 13, weight: '600' }
                    }
                }
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>