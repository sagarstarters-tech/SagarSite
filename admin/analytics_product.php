<?php
/**
 * ============================================================
 *  Product Analytics Detail Page
 *  Location: /admin/analytics_product.php
 * ============================================================
 */
$current_page = 'analytics.php';
include 'admin_header.php';
require_once __DIR__ . '/modules/AnalyticsService.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId < 1) {
    echo '<div class="container py-5 text-center"><h4>Invalid product ID</h4><a href="analytics.php" class="btn btn-primary mt-3">Back to Analytics</a></div>';
    include 'admin_footer.php';
    exit;
}

$analytics = new AnalyticsService();
$data = $analytics->getProductAnalytics($productId);
?>

<link href="<?php echo ASSETS_URL; ?>/css/admin-analytics.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/admin-analytics.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4 analytics-wrapper">

    <!-- Back Link -->
    <a href="analytics.php" class="btn btn-light border rounded-pill px-3 mb-3 small fw-semibold">
        <i class="fas fa-arrow-left me-2"></i>Back to Analytics
    </a>

    <!-- Header -->
    <div class="mb-4">
        <h4 class="fw-bold mb-1"><i class="fas fa-box-open text-warning me-2"></i>Product Analytics</h4>
        <h5 class="text-muted"><?php echo htmlspecialchars($data['product_name']); ?></h5>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <?php
        $pCards = [
            ['label' => 'Total Views',    'value' => number_format($data['total_views']),     'icon' => 'fa-eye',        'color' => 'blue'],
            ['label' => 'Unique Visitors', 'value' => number_format($data['unique_visitors']), 'icon' => 'fa-user-check', 'color' => 'emerald'],
            ['label' => "Today's Views",   'value' => number_format($data['today_views']),     'icon' => 'fa-calendar-day','color' => 'amber'],
            ['label' => '7-Day Views',     'value' => number_format($data['7day_views']),      'icon' => 'fa-chart-line', 'color' => 'purple'],
            ['label' => '30-Day Views',    'value' => number_format($data['30day_views']),     'icon' => 'fa-chart-area', 'color' => 'cyan'],
        ];
        foreach ($pCards as $c): ?>
            <div class="col-xl col-md-4 col-6">
                <div class="an-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="an-stat-label"><?php echo $c['label']; ?></div>
                            <div class="an-stat-value"><?php echo $c['value']; ?></div>
                        </div>
                        <div class="an-stat-icon an-icon-<?php echo $c['color']; ?>">
                            <i class="fas <?php echo $c['icon']; ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Daily Views Chart -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="an-chart-card">
                <div class="an-chart-header">
                    <h6><i class="fas fa-chart-area text-primary me-2"></i>Daily Views (Last 30 Days)</h6>
                </div>
                <div class="an-chart-body" style="height:300px;"><canvas id="chartProductDaily"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Traffic Sources, Locations, Devices -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header"><h6><i class="fas fa-globe text-primary me-2"></i>Traffic Sources</h6></div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Source</th><th>Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['traffic_sources'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No data</td></tr>
                        <?php else: foreach ($data['traffic_sources'] as $ts): ?>
                            <tr>
                                <td><span class="an-source-tag an-source-<?php echo htmlspecialchars($ts['traffic_source']); ?>"><?php echo ucfirst(str_replace('_', ' ', $ts['traffic_source'])); ?></span></td>
                                <td><strong><?php echo number_format($ts['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header">
                    <h6><i class="fas fa-map-marker-alt text-danger me-2"></i>Locations</h6>
                    <span class="an-approx-badge"><i class="fas fa-info-circle"></i> Approximate</span>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Location</th><th>Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['locations'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">Unavailable</td></tr>
                        <?php else: foreach ($data['locations'] as $loc): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($loc['city'] ?: 'Unknown'); ?></div>
                                    <div class="text-muted" style="font-size:0.7rem;"><?php echo htmlspecialchars(implode(', ', array_filter([$loc['region'], $loc['country']]))); ?></div>
                                </td>
                                <td><strong><?php echo number_format($loc['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header"><h6><i class="fas fa-mobile-alt text-success me-2"></i>Devices</h6></div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Device</th><th>Views</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['devices'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No data</td></tr>
                        <?php else: foreach ($data['devices'] as $d): ?>
                            <tr>
                                <td><i class="fas <?php echo ($d['device_type'] === 'mobile' ? 'fa-mobile-alt' : ($d['device_type'] === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop')); ?> me-2 text-muted"></i><?php echo ucfirst($d['device_type'] ?: 'Unknown'); ?></td>
                                <td><strong><?php echo number_format($d['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Searches -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="an-table-card">
                <div class="an-table-header"><h6><i class="fas fa-search text-info me-2"></i>Related Searches</h6></div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Search Query</th><th>Led to This Product</th></tr></thead>
                        <tbody>
                        <?php if (empty($data['related_searches'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No search-to-product data yet</td></tr>
                        <?php else: foreach ($data['related_searches'] as $rs): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($rs['query']); ?></td>
                                <td><strong><?php echo number_format($rs['cnt']); ?></strong> times</td>
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
    var ctx = document.getElementById('chartProductDaily');
    if (!ctx) return;
    var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(245,158,11,0.25)');
    gradient.addColorStop(1, 'rgba(255,255,255,0)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($data['daily_chart']['labels']); ?>,
            datasets: [{
                label: 'Views',
                data: <?php echo json_encode($data['daily_chart']['data']); ?>,
                borderColor: '#f59e0b',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#cbd5e1', padding: 10, cornerRadius: 8, displayColors: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 } }
            }
        }
    });
});
</script>

<?php include 'admin_footer.php'; ?>
