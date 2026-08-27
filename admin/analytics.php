<?php
/**
 * ============================================================
 *  Analytics Dashboard
 *  Location: /admin/analytics.php
 * ============================================================
 */
$current_page = 'analytics.php';
include 'admin_header.php';
require_once __DIR__ . '/modules/AnalyticsService.php';

$analytics = new AnalyticsService();

// Date filter
$filter      = $_GET['filter'] ?? '7days';
$customStart = $_GET['start'] ?? null;
$customEnd   = $_GET['end'] ?? null;
[$startDate, $endDate] = $analytics->getDateRange($filter, $customStart, $customEnd);

// Fetch all dashboard data
$stats          = $analytics->getSummaryStats($startDate, $endDate);
$visitorTrend   = $analytics->getVisitorTrend($startDate, $endDate);
$pageViewTrend  = $analytics->getPageViewTrend($startDate, $endDate);
$prodViewTrend  = $analytics->getProductViewTrend($startDate, $endDate);
$searchTrend    = $analytics->getSearchTrend($startDate, $endDate);
$topProducts    = $analytics->getTopProducts($startDate, $endDate, 10);
$topSearches    = $analytics->getTopSearches($startDate, $endDate, 10);
$noResults      = $analytics->getNoResultSearches($startDate, $endDate, 10);
$trafficSources = $analytics->getTrafficSources($startDate, $endDate);
$deviceData     = $analytics->getDeviceBreakdown($startDate, $endDate);
$locations      = $analytics->getLocationReport($startDate, $endDate, 15);
$topPages       = $analytics->getTopPages($startDate, $endDate, 10);
$recentActivity = $analytics->getRecentActivity(20);
$liveData       = $analytics->getLiveVisitors();
$s2p            = $analytics->getSearchToProductInsights($startDate, $endDate, 10);
?>

<link href="<?php echo ASSETS_URL; ?>/css/admin-analytics.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/admin-analytics.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4 analytics-wrapper">

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  HEADER + LIVE VISITORS                                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Website Analytics</h4>
            <p class="text-muted small mb-0">First-party visitor analytics for <?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's"); ?></p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="an-live-badge" id="liveVisitorBadge">
                <span class="an-live-dot"></span>
                Live: <span id="liveCount"><?php echo $liveData['count']; ?></span>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  DATE FILTER BAR                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="analytics-filter-bar mb-4">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small fw-bold text-muted me-2"><i class="far fa-calendar-alt me-1"></i>Period:</span>
            <?php
            $filters = [
                'today'      => 'Today',
                'yesterday'  => 'Yesterday',
                '7days'      => 'Last 7 Days',
                '30days'     => 'Last 30 Days',
                'this_month' => 'This Month',
                'last_month' => 'Last Month',
                'custom'     => 'Custom',
            ];
            foreach ($filters as $key => $label): ?>
                <a href="?filter=<?php echo $key; ?><?php echo $key === 'custom' ? '&start=' . urlencode($startDate) . '&end=' . urlencode($endDate) : ''; ?>"
                   class="btn-filter <?php echo $filter === $key ? 'active' : ''; ?>"
                   <?php if ($key === 'custom'): ?>onclick="event.preventDefault(); document.getElementById('customRange').classList.toggle('show');"<?php endif; ?>>
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
            <div id="customRange" class="custom-range-inputs align-items-center gap-2 ms-2 <?php echo $filter === 'custom' ? 'show' : ''; ?>">
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="filter" value="custom">
                    <input type="date" name="start" value="<?php echo htmlspecialchars($startDate); ?>" class="form-control form-control-sm" style="width:140px;">
                    <span class="text-muted small">to</span>
                    <input type="date" name="end" value="<?php echo htmlspecialchars($endDate); ?>" class="form-control form-control-sm" style="width:140px;">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Apply</button>
                </form>
            </div>
        </div>
        <div class="small text-muted mt-2">
            Showing: <strong><?php echo date('M d, Y', strtotime($startDate)); ?></strong> — <strong><?php echo date('M d, Y', strtotime($endDate)); ?></strong>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  SUMMARY STAT CARDS                                     -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['label' => 'Total Visitors',  'value' => number_format($stats['total_visitors']),  'icon' => 'fa-users',        'color' => 'blue',    'sub' => 'Sessions in period'],
            ['label' => 'Unique Visitors', 'value' => number_format($stats['unique_visitors']), 'icon' => 'fa-user-check',   'color' => 'emerald', 'sub' => 'Distinct anonymous visitors'],
            ['label' => 'Page Views',      'value' => number_format($stats['page_views']),      'icon' => 'fa-eye',          'color' => 'purple',  'sub' => 'Total pages viewed'],
            ['label' => 'Product Views',   'value' => number_format($stats['product_views']),   'icon' => 'fa-box-open',     'color' => 'amber',   'sub' => 'Product detail views'],
            ['label' => 'Searches',        'value' => number_format($stats['searches']),        'icon' => 'fa-search',       'color' => 'cyan',    'sub' => 'Product searches'],
            ['label' => "Today's Visitors",'value' => number_format($stats['today_visitors']),  'icon' => 'fa-user-clock',   'color' => 'indigo',  'sub' => 'Unique visitors today'],
            ['label' => "Today's Prod Views",'value'=> number_format($stats['today_product_views']),'icon'=>'fa-shopping-bag','color' => 'orange',  'sub' => 'Product views today'],
            ['label' => "Today's Searches",'value' => number_format($stats['today_searches']),  'icon' => 'fa-search-plus',  'color' => 'rose',    'sub' => 'Searches today'],
        ];
        foreach ($cards as $c): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <div class="an-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="an-stat-label"><?php echo $c['label']; ?></div>
                            <div class="an-stat-value"><?php echo $c['value']; ?></div>
                            <div class="an-stat-sub"><?php echo $c['sub']; ?></div>
                        </div>
                        <div class="an-stat-icon an-icon-<?php echo $c['color']; ?>">
                            <i class="fas <?php echo $c['icon']; ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  CHARTS ROW 1: Visitors & Page Views                    -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="an-chart-card">
                <div class="an-chart-header">
                    <h6><i class="fas fa-chart-area text-primary me-2"></i>Visitor Trend</h6>
                </div>
                <div class="an-chart-body" style="height:280px;"><canvas id="chartVisitors"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="an-chart-card">
                <div class="an-chart-header">
                    <h6><i class="fas fa-chart-bar text-success me-2"></i>Page Views Trend</h6>
                </div>
                <div class="an-chart-body" style="height:280px;"><canvas id="chartPageViews"></canvas></div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: Product Views & Searches -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="an-chart-card">
                <div class="an-chart-header">
                    <h6><i class="fas fa-chart-line text-warning me-2"></i>Product Views Trend</h6>
                </div>
                <div class="an-chart-body" style="height:280px;"><canvas id="chartProductViews"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="an-chart-card">
                <div class="an-chart-header">
                    <h6><i class="fas fa-chart-line text-info me-2"></i>Search Trend</h6>
                </div>
                <div class="an-chart-body" style="height:280px;"><canvas id="chartSearches"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  TOP PRODUCTS & TOP SEARCHES                            -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Top Products -->
        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-trophy text-warning me-2"></i>Top Viewed Products</h6>
                    <a href="ajax_analytics_export.php?type=product_views&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="an-btn-export"><i class="fas fa-download"></i> CSV</a>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>#</th><th>Product</th><th>Views</th><th>Unique</th><th></th></tr></thead>
                        <tbody>
                        <?php if (empty($topProducts)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No product views yet</td></tr>
                        <?php else: foreach ($topProducts as $i => $p):
                            $rankClass = $i === 0 ? 'an-rank-gold' : ($i === 1 ? 'an-rank-silver' : ($i === 2 ? 'an-rank-bronze' : ''));
                        ?>
                            <tr>
                                <td><span class="an-rank <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($p['product_name'] ?: 'ID: ' . $p['product_id']); ?></td>
                                <td><strong><?php echo number_format($p['views']); ?></strong></td>
                                <td><?php echo number_format($p['unique_visitors']); ?></td>
                                <td><a href="analytics_product.php?id=<?php echo $p['product_id']; ?>" class="btn btn-sm btn-light border rounded-pill px-2" title="View Details"><i class="fas fa-chart-bar"></i></a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Searches -->
        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-search text-info me-2"></i>Top Product Searches</h6>
                    <a href="ajax_analytics_export.php?type=searches&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="an-btn-export"><i class="fas fa-download"></i> CSV</a>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>#</th><th>Search Query</th><th>Searches</th><th>Avg Results</th></tr></thead>
                        <tbody>
                        <?php if (empty($topSearches)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No searches yet</td></tr>
                        <?php else: foreach ($topSearches as $i => $s):
                            $rankClass = $i === 0 ? 'an-rank-gold' : ($i === 1 ? 'an-rank-silver' : ($i === 2 ? 'an-rank-bronze' : ''));
                        ?>
                            <tr>
                                <td><span class="an-rank <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($s['query']); ?></td>
                                <td><strong><?php echo number_format($s['search_count']); ?></strong></td>
                                <td><?php echo $s['avg_results']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  NO-RESULT SEARCHES & SEARCH→PRODUCT                   -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-exclamation-triangle text-danger me-2"></i>Searches With No Results</h6>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>#</th><th>Search Query</th><th>Searches</th></tr></thead>
                        <tbody>
                        <?php if (empty($noResults)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">All searches returned results ✓</td></tr>
                        <?php else: foreach ($noResults as $i => $nr): ?>
                            <tr>
                                <td><span class="an-rank"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold text-danger"><?php echo htmlspecialchars($nr['query']); ?></td>
                                <td><strong><?php echo number_format($nr['search_count']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-arrow-right text-primary me-2"></i>Search → Product Clicks</h6>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Search</th><th>Product Clicked</th><th>Clicks</th></tr></thead>
                        <tbody>
                        <?php if (empty($s2p)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No data yet</td></tr>
                        <?php else: foreach ($s2p as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['search_query']); ?></td>
                                <td><a href="analytics_product.php?id=<?php echo $row['product_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($row['product_name']); ?></a></td>
                                <td><strong><?php echo number_format($row['clicks']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  TRAFFIC SOURCES, DEVICES, LOCATIONS                    -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Traffic Sources -->
        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header">
                    <h6><i class="fas fa-globe text-primary me-2"></i>Traffic Sources</h6>
                    <a href="ajax_analytics_export.php?type=visitors&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="an-btn-export"><i class="fas fa-download"></i> CSV</a>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Source</th><th>Visitors</th></tr></thead>
                        <tbody>
                        <?php if (empty($trafficSources)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No data</td></tr>
                        <?php else: foreach ($trafficSources as $ts): ?>
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

        <!-- Devices -->
        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header">
                    <h6><i class="fas fa-mobile-alt text-success me-2"></i>Devices</h6>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Type</th><th>Count</th></tr></thead>
                        <tbody>
                        <?php foreach ($deviceData['devices'] as $d): ?>
                            <tr>
                                <td>
                                    <i class="fas <?php echo $d['device_type'] === 'mobile' ? 'fa-mobile-alt' : ($d['device_type'] === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop'); ?> me-2 text-muted"></i>
                                    <?php echo ucfirst($d['device_type'] ?: 'Unknown'); ?>
                                </td>
                                <td><strong><?php echo number_format($d['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <table class="an-table mt-2">
                        <thead><tr><th>Browser</th><th>Count</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($deviceData['browsers'], 0, 5) as $b): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($b['browser'] ?: 'Unknown'); ?></td>
                                <td><strong><?php echo number_format($b['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <table class="an-table mt-2">
                        <thead><tr><th>OS</th><th>Count</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($deviceData['os'], 0, 5) as $o): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($o['os'] ?: 'Unknown'); ?></td>
                                <td><strong><?php echo number_format($o['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Locations -->
        <div class="col-lg-4">
            <div class="an-table-card h-100">
                <div class="an-table-header">
                    <h6><i class="fas fa-map-marker-alt text-danger me-2"></i>Visitor Locations</h6>
                    <span class="an-approx-badge"><i class="fas fa-info-circle"></i> Approximate</span>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Location</th><th>Visitors</th><th>%</th></tr></thead>
                        <tbody>
                        <?php if (empty($locations)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Location data unavailable</td></tr>
                        <?php else: foreach ($locations as $loc): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($loc['city'] ?: 'Unknown'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars(($loc['region'] ? $loc['region'] . ', ' : '') . ($loc['country'] ?: '')); ?></div>
                                </td>
                                <td><strong><?php echo number_format($loc['visitors']); ?></strong></td>
                                <td><?php echo $loc['percentage']; ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  TOP PAGES & RECENT ACTIVITY                            -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Top Pages -->
        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-file-alt text-purple me-2"></i>Top Pages</h6>
                    <a href="ajax_analytics_export.php?type=page_views&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="an-btn-export"><i class="fas fa-download"></i> CSV</a>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>#</th><th>Page</th><th>Views</th><th>Unique</th></tr></thead>
                        <tbody>
                        <?php if (empty($topPages)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No page views yet</td></tr>
                        <?php else: foreach ($topPages as $i => $pg): ?>
                            <tr>
                                <td><span class="an-rank"><?php echo $i + 1; ?></span></td>
                                <td class="text-break" style="max-width:300px;"><?php echo htmlspecialchars($pg['page_url']); ?></td>
                                <td><strong><?php echo number_format($pg['views']); ?></strong></td>
                                <td><?php echo number_format($pg['unique_visitors']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><i class="fas fa-stream text-info me-2"></i>Recent Visitor Activity</h6>
                </div>
                <div class="an-table-responsive" style="max-height:420px; overflow-y:auto;">
                    <table class="an-table">
                        <thead><tr><th>Time</th><th>Location</th><th>Device</th><th>Page</th><th>Source</th></tr></thead>
                        <tbody>
                        <?php if (empty($recentActivity)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No recent activity</td></tr>
                        <?php else: foreach ($recentActivity as $ra): ?>
                            <tr>
                                <td class="small text-nowrap"><?php echo date('M d, H:i', strtotime($ra['last_activity'])); ?></td>
                                <td class="small">
                                    <?php
                                    $locParts = array_filter([$ra['city'], $ra['country']]);
                                    echo !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : '<span class="text-muted">—</span>';
                                    ?>
                                </td>
                                <td class="small">
                                    <i class="fas <?php echo ($ra['device_type'] === 'mobile' ? 'fa-mobile-alt' : ($ra['device_type'] === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop')); ?> text-muted me-1"></i>
                                    <?php echo htmlspecialchars($ra['browser'] ?: ''); ?>
                                </td>
                                <td class="small text-break" style="max-width:200px;"><?php echo htmlspecialchars($ra['page_url'] ?: '—'); ?></td>
                                <td><span class="an-source-tag an-source-<?php echo htmlspecialchars($ra['traffic_source'] ?: 'direct'); ?>"><?php echo ucfirst(str_replace('_', ' ', $ra['traffic_source'] ?: 'direct')); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  LIVE VISITORS DETAIL                                   -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <?php if (!empty($liveData['visitors'])): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="an-table-card">
                <div class="an-table-header">
                    <h6><span class="an-live-dot me-2"></span>Live Visitors (Active within <?php echo round((int)$analytics->getSetting('live_visitor_threshold', '300') / 60); ?> min)</h6>
                </div>
                <div class="an-table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Current Page</th><th>Location</th><th>Device</th><th>Browser</th><th>Last Active</th></tr></thead>
                        <tbody>
                        <?php foreach ($liveData['visitors'] as $lv): ?>
                            <tr>
                                <td class="small text-break" style="max-width:300px;"><?php echo htmlspecialchars($lv['page_url'] ?: '—'); ?></td>
                                <td class="small"><?php echo htmlspecialchars(implode(', ', array_filter([$lv['city'], $lv['country']])) ?: '—'); ?></td>
                                <td><i class="fas <?php echo ($lv['device_type'] === 'mobile' ? 'fa-mobile-alt' : ($lv['device_type'] === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop')); ?> text-muted"></i></td>
                                <td class="small"><?php echo htmlspecialchars($lv['browser'] ?: '—'); ?></td>
                                <td class="small"><?php echo date('H:i:s', strtotime($lv['last_activity'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  CHARTS JS                                                  -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    function makeChart(id, label, labels, data, color, borderColor) {
        var ctx = document.getElementById(id);
        if (!ctx) return;
        var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, 'rgba(255,255,255,0)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: borderColor,
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: borderColor,
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
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#fff',
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
                        ticks: { color: '#94a3b8', font: { size: 11 }, padding: 8 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 }
                    }
                }
            }
        });
    }

    makeChart('chartVisitors', 'Visitors',
        <?php echo json_encode($visitorTrend['labels']); ?>,
        <?php echo json_encode($visitorTrend['data']); ?>,
        'rgba(59,130,246,0.2)', '#3b82f6');

    makeChart('chartPageViews', 'Page Views',
        <?php echo json_encode($pageViewTrend['labels']); ?>,
        <?php echo json_encode($pageViewTrend['data']); ?>,
        'rgba(16,185,129,0.2)', '#10b981');

    makeChart('chartProductViews', 'Product Views',
        <?php echo json_encode($prodViewTrend['labels']); ?>,
        <?php echo json_encode($prodViewTrend['data']); ?>,
        'rgba(245,158,11,0.2)', '#f59e0b');

    makeChart('chartSearches', 'Searches',
        <?php echo json_encode($searchTrend['labels']); ?>,
        <?php echo json_encode($searchTrend['data']); ?>,
        'rgba(6,182,212,0.2)', '#06b6d4');

    // Live visitor count refresh every 60s
    setInterval(function() {
        fetch('ajax_analytics.php?action=live_count', {credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.count !== undefined) {
                    document.getElementById('liveCount').textContent = d.count;
                }
            }).catch(function(){});
    }, 60000);
});
</script>

<?php include 'admin_footer.php'; ?>
