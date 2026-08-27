<?php
/**
 * ============================================================
 *  Executive Product Analytics Detail Page
 *  Location: /admin/analytics_product.php
 * ============================================================
 */
$current_page = 'analytics.php';
include 'admin_header.php';
require_once __DIR__ . '/modules/AnalyticsService.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId < 1) {
    echo '<div class="container py-5 text-center"><div class="dash-card p-5"><i class="fas fa-exclamation-circle text-warning fa-3x mb-3"></i><h4>Invalid Product ID</h4><p class="text-muted">The requested product could not be identified.</p><a href="analytics.php" class="btn btn-primary rounded-pill px-4 mt-2">Back to Analytics</a></div></div>';
    include 'admin_footer.php';
    exit;
}

$analytics = new AnalyticsService();
$data = $analytics->getProductAnalytics($productId);
?>

<link href="<?php echo ASSETS_URL; ?>/css/admin-analytics.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/admin-analytics.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4 dash-wrapper">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  1. PRODUCT ANALYTICS HERO                                  -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="dash-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <a href="analytics.php" class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 rounded-pill px-3 py-1 text-decoration-none small">
                        <i class="fas fa-arrow-left me-1"></i> Analytics Hub
                    </a>
                    <span class="text-white-50 small"><i class="fas fa-tag me-1"></i> Product ID: #<?php echo $productId; ?></span>
                </div>
                <h2 class="fw-bold mb-1 text-white fs-3"><?php echo htmlspecialchars($data['product_name']); ?> 📦</h2>
                <p class="text-white-50 mb-0 small">Catalog telemetry, buyer interest & conversion tracking.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="../product.php?id=<?php echo $productId; ?>" target="_blank" class="btn dash-btn-white px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                    <i class="fas fa-external-link-alt"></i>
                    <span>View in Store</span>
                </a>
                <a href="manage_products.php?action=edit&id=<?php echo $productId; ?>" class="btn btn-primary px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold text-white">
                    <i class="fas fa-edit"></i>
                    <span>Edit Product</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  2. CORE PRODUCT STAT CARDS                                 -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Total Views -->
        <div class="col-xl col-md-4 col-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Total Views</div>
                        <div class="dash-stat-val" style="color: #2563eb;"><?php echo number_format($data['total_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-eye text-primary me-1"></i> Lifetime views</div>
                    </div>
                    <div class="dash-icon-box dash-icon-blue">
                        <i class="fas fa-eye"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique Visitors -->
        <div class="col-xl col-md-4 col-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Unique Visitors</div>
                        <div class="dash-stat-val" style="color: #059669;"><?php echo number_format($data['unique_visitors']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-user-check text-success me-1"></i> Distinct buyers</div>
                    </div>
                    <div class="dash-icon-box dash-icon-emerald">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Views -->
        <div class="col-xl col-md-4 col-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Today's Views</div>
                        <div class="dash-stat-val" style="color: #d97706;"><?php echo number_format($data['today_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-bolt text-warning me-1"></i> Viewed today</div>
                    </div>
                    <div class="dash-icon-box dash-icon-amber">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7-Day Views -->
        <div class="col-xl col-md-6 col-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">7-Day Views</div>
                        <div class="dash-stat-val" style="color: #7c3aed;"><?php echo number_format($data['7day_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-chart-line text-purple me-1"></i> Last 7 days</div>
                    </div>
                    <div class="dash-icon-box dash-icon-purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 30-Day Views -->
        <div class="col-xl col-md-6 col-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">30-Day Views</div>
                        <div class="dash-stat-val" style="color: #0891b2;"><?php echo number_format($data['30day_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-history text-cyan me-1"></i> Last 30 days</div>
                    </div>
                    <div class="dash-icon-box dash-icon-cyan">
                        <i class="fas fa-chart-area"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  3. DAILY ENGAGEMENT TREND CHART                            -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-warning me-2"></i> 30-Day Product View Trajectory</h6>
                        <span class="small text-muted">Daily interest and catalog visits for this product</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 fw-bold"><?php echo array_sum($data['daily_chart']['data']); ?> Total Views</span>
                    </div>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="chartProductDaily"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  4. TRAFFIC SOURCES, LOCATIONS, DEVICES                     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Traffic Sources -->
        <div class="col-lg-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-globe text-primary me-2"></i> Traffic Inflow</h6>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Channel</th><th class="text-end">Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['traffic_sources'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No data recorded yet</td></tr>
                        <?php else: foreach ($data['traffic_sources'] as $ts): ?>
                            <tr>
                                <td><span class="an-source-tag an-source-<?php echo htmlspecialchars($ts['traffic_source']); ?>"><?php echo ucfirst(str_replace('_', ' ', $ts['traffic_source'])); ?></span></td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($ts['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Geographic Locations -->
        <div class="col-lg-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-map-marker-alt text-danger me-2"></i> Buyer Locations</h6>
                    <span class="an-approx-badge"><i class="fas fa-info-circle"></i> Approximate</span>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Location</th><th class="text-end">Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['locations'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">Location logging in progress</td></tr>
                        <?php else: foreach ($data['locations'] as $loc): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($loc['city'] ?: 'Unknown'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars(implode(', ', array_filter([$loc['region'], $loc['country']]))); ?></div>
                                </td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($loc['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Devices -->
        <div class="col-lg-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-mobile-alt text-success me-2"></i> Devices Used</h6>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Platform</th><th class="text-end">Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['devices'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No data</td></tr>
                        <?php else: foreach ($data['devices'] as $d): ?>
                            <tr>
                                <td>
                                    <i class="fas <?php echo ($d['device_type'] === 'mobile' ? 'fa-mobile-alt text-primary' : ($d['device_type'] === 'tablet' ? 'fa-tablet-alt text-warning' : 'fa-desktop text-success')); ?> me-2"></i>
                                    <?php echo ucfirst($d['device_type'] ?: 'Unknown'); ?>
                                </td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($d['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  5. SEARCH KEYWORDS LEADING TO THIS PRODUCT                 -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-search text-info me-2"></i> Search Terms That Led To This Product</h6>
                        <span class="small text-muted">Direct keyword searches by shoppers prior to viewing</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Customer Search Term</th><th class="text-end">Times Reached</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['related_searches'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No search attribution recorded yet</td></tr>
                        <?php else: foreach ($data['related_searches'] as $rs): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><i class="fas fa-search text-muted me-2 small"></i><?php echo htmlspecialchars($rs['query']); ?></td>
                                <td class="text-end"><strong class="text-primary"><?php echo number_format($rs['cnt']); ?></strong> times</td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('chartProductDaily');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(245, 158, 11, 0.35)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($data['daily_chart']['labels']); ?>,
            datasets: [{
                label: 'Views',
                data: <?php echo json_encode($data['daily_chart']['data']); ?>,
                borderColor: '#d97706',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#d97706',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 3.5,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.38
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#cbd5e1',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: { color: '#94a3b8', font: { size: 11, family: 'Inter' }, padding: 8 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 10, family: 'Inter' }, maxRotation: 45 }
                }
            }
        }
    });
});
</script>

<?php include 'admin_footer.php'; ?>
