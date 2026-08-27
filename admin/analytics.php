<?php
/**
 * ============================================================
 *  Executive Website Analytics Dashboard
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

$admin_name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin';
?>

<link href="<?php echo ASSETS_URL; ?>/css/admin-analytics.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/admin-analytics.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4 dash-wrapper">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  1. HERO WELCOME HEADER (Matching Index.php Design)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="dash-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-chart-line me-1"></i> Traffic & Growth Intelligence
                    </span>
                    <span class="text-white-50 small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?></span>
                </div>
                <h2 class="fw-bold mb-1 text-white fs-3">Website Analytics & Telemetry 📈</h2>
                <p class="text-white-50 mb-0 small">Real-time visitor tracking, catalog engagement & search intelligence.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Live Visitors Pulse Pill -->
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-white fw-bold shadow-sm" style="background: rgba(16, 185, 129, 0.22); border: 1px solid rgba(16, 185, 129, 0.45); backdrop-filter: blur(4px);">
                    <span class="an-live-dot"></span>
                    <span class="small">Live Visitors: <strong id="liveCount" class="text-white fs-6 ms-1"><?php echo $liveData['count']; ?></strong></span>
                </div>
                <!-- Export CSV -->
                <a href="ajax_analytics_export.php?type=visitors&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="btn dash-btn-white px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                    <i class="fas fa-download"></i>
                    <span>Export CSV</span>
                </a>
                <!-- Refresh Button -->
                <a href="analytics.php?filter=<?php echo urlencode($filter); ?><?php echo $filter === 'custom' ? '&start=' . urlencode($startDate) . '&end=' . urlencode($endDate) : ''; ?>" class="btn btn-primary px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold text-white">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  2. DATE FILTER & QUICK JUMP BAR                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="analytics-filter-card mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <!-- Filter Pills -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="small fw-bold text-muted me-1 text-uppercase" style="letter-spacing: 0.5px;"><i class="far fa-calendar-alt me-1 text-primary"></i> Timeframe:</span>
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
                       class="btn-filter-pill <?php echo $filter === $key ? 'active' : ''; ?>"
                       <?php if ($key === 'custom'): ?>onclick="event.preventDefault(); document.getElementById('customRange').classList.toggle('show');"<?php endif; ?>>
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
                
                <div id="customRange" class="custom-range-box align-items-center gap-2 ms-2 <?php echo $filter === 'custom' ? 'show' : ''; ?>">
                    <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                        <input type="hidden" name="filter" value="custom">
                        <input type="date" name="start" value="<?php echo htmlspecialchars($startDate); ?>" class="form-control form-control-sm" style="width:135px; border-radius: 8px;">
                        <span class="text-muted small">to</span>
                        <input type="date" name="end" value="<?php echo htmlspecialchars($endDate); ?>" class="form-control form-control-sm" style="width:135px; border-radius: 8px;">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Apply</button>
                    </form>
                </div>
            </div>

            <!-- Date Range Indicator -->
            <div class="small text-muted text-nowrap">
                <i class="fas fa-check-circle text-success me-1"></i>
                Date Range: <strong class="text-dark"><?php echo date('M d, Y', strtotime($startDate)); ?></strong> — <strong class="text-dark"><?php echo date('M d, Y', strtotime($endDate)); ?></strong>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  3. PRIMARY METRICS (Matching index.php stat cards)         -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Total Visitors -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Total Visitors</div>
                        <div class="dash-stat-val" style="color: #2563eb;"><?php echo number_format($stats['total_visitors']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-signal text-primary me-1"></i> Recorded store sessions</div>
                    </div>
                    <div class="dash-icon-box dash-icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique Visitors -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Unique Visitors</div>
                        <div class="dash-stat-val" style="color: #059669;"><?php echo number_format($stats['unique_visitors']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-user-check text-success me-1"></i> Distinct anonymous users</div>
                    </div>
                    <div class="dash-icon-box dash-icon-emerald">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Views -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Page Views</div>
                        <div class="dash-stat-val" style="color: #7c3aed;"><?php echo number_format($stats['page_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-eye text-purple me-1"></i> Total screen impressions</div>
                    </div>
                    <div class="dash-icon-box dash-icon-purple">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Views -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Product Views</div>
                        <div class="dash-stat-val" style="color: #d97706;"><?php echo number_format($stats['product_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-box-open text-warning me-1"></i> Product catalog inspected</div>
                    </div>
                    <div class="dash-icon-box dash-icon-amber">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  4. SECONDARY METRICS & TODAY'S ACTIVITY                    -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Searches -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Search Queries</div>
                        <div class="dash-stat-val" style="color: #0891b2;"><?php echo number_format($stats['searches']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-search text-info me-1"></i> Intent keywords queried</div>
                    </div>
                    <div class="dash-icon-box dash-icon-cyan">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Visitors -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Today's Visitors</div>
                        <div class="dash-stat-val" style="color: #4f46e5;"><?php echo number_format($stats['today_visitors']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-bolt text-indigo me-1"></i> Unique visitors today</div>
                    </div>
                    <div class="dash-icon-box dash-icon-indigo">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Product Views -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Today's Prod Views</div>
                        <div class="dash-stat-val" style="color: #ea580c;"><?php echo number_format($stats['today_product_views']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-fire text-orange me-1"></i> Products viewed today</div>
                    </div>
                    <div class="dash-icon-box dash-icon-orange">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Searches -->
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="dash-stat-lbl">Today's Searches</div>
                        <div class="dash-stat-val" style="color: #e11d48;"><?php echo number_format($stats['today_searches']); ?></div>
                        <div class="dash-stat-sub"><i class="fas fa-bullseye text-danger me-1"></i> Searches performed today</div>
                    </div>
                    <div class="dash-icon-box dash-icon-rose">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  5. REAL-TIME LIVE ACTIVE VISITORS & TELEMETRY              -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="an-live-card p-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 pb-3 border-bottom mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="an-radar-pulse"></div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h6 class="fw-bold mb-0 text-dark fs-6"><i class="fas fa-satellite-dish text-success me-2"></i> Real-Time Live Visitors Telemetry</h6>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold" id="liveCardCountBadge">
                                    <span class="an-live-dot me-1"></span> <span id="liveCardCount"><?php echo $liveData['count']; ?> Active Now</span>
                                </span>
                            </div>
                            <span class="small text-muted">Live visitors on your store with real-time location, device, and active browsing page (auto-syncs every 15s)</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted me-2" id="lastLiveSyncTime"><i class="far fa-clock me-1"></i> Synced: <?php echo date('h:i:s A'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-flex align-items-center gap-1 shadow-sm" id="btnRefreshLive">
                            <i class="fas fa-sync-alt" id="refreshLiveIcon"></i> <span>Refresh Live</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="an-table align-middle" id="liveVisitorsTable">
                        <thead>
                            <tr>
                                <th>Active Visitor</th>
                                <th>Location</th>
                                <th>Device & Platform</th>
                                <th>Current Page</th>
                                <th>Traffic Source</th>
                                <th class="text-end">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="liveVisitorsListBody">
                        <?php if (empty($liveData['visitors'])): ?>
                            <tr id="liveEmptyRow">
                                <td colspan="6">
                                    <div class="an-live-empty-state">
                                        <div class="an-radar-scanner">
                                            <i class="fas fa-satellite-dish text-success fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">No Visitors Active Right Now</h6>
                                        <p class="small text-muted mb-0">Real-time telemetry is monitoring your store. When visitors browse, their live location, device, and active page will appear here instantly.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($liveData['visitors'] as $lv): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="an-live-dot"></span>
                                        <div>
                                            <span class="fw-bold text-dark font-monospace small">#<?php echo substr($lv['visitor_uid'], 0, 8); ?></span>
                                            <div class="text-muted" style="font-size: 0.7rem;">Session #<?php echo $lv['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="an-location-badge">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                        <span class="fw-bold"><?php echo htmlspecialchars($lv['city'] ?: ($lv['country'] ? 'City in ' . $lv['country'] : 'Unknown City')); ?></span>
                                    </div>
                                    <div class="small text-muted ms-3"><?php echo htmlspecialchars(implode(', ', array_filter([$lv['region'], $lv['country']]))) ?: '—'; ?></div>
                                </td>
                                <td>
                                    <span class="an-device-badge">
                                        <i class="fas <?php echo ($lv['device_type'] === 'mobile' ? 'fa-mobile-alt text-primary' : ($lv['device_type'] === 'tablet' ? 'fa-tablet-alt text-warning' : 'fa-desktop text-success')); ?>"></i>
                                        <?php echo htmlspecialchars(($lv['browser'] ?: 'Browser') . ' on ' . ($lv['os'] ?: 'OS')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($lv['page_url'])): ?>
                                        <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? SITE_URL : '') . $lv['page_url']); ?>" target="_blank" class="an-page-link" title="<?php echo htmlspecialchars($lv['page_title'] ?: $lv['page_url']); ?>">
                                            <i class="fas fa-external-link-alt small text-muted me-1"></i>
                                            <?php echo htmlspecialchars($lv['page_title'] ? (mb_strlen($lv['page_title']) > 32 ? mb_substr($lv['page_title'], 0, 30) . '...' : $lv['page_title']) : $lv['page_url']); ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.7rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($lv['page_url']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="an-source-tag an-source-<?php echo htmlspecialchars($lv['traffic_source'] ?: 'direct'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $lv['traffic_source'] ?: 'direct')); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1 small">
                                        <i class="fas fa-circle text-success me-1" style="font-size: 6px;"></i>
                                        <?php
                                        $sec = (int)($lv['seconds_ago'] ?? 0);
                                        if ($sec <= 10) echo 'Just now';
                                        elseif ($sec < 60) echo $sec . 's ago';
                                        else echo round($sec / 60) . 'm ago';
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  6. CHARTS ROW 1: Visitors & Page Views                     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Visitors Trend Chart -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-primary me-2"></i> Visitor Traffic Trend</h6>
                        <span class="small text-muted">Unique visitors breakdown per day</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold"><?php echo array_sum($visitorTrend['data']); ?> Total</span>
                    </div>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="chartVisitors"></canvas>
                </div>
            </div>
        </div>

        <!-- Page Views Trend Chart -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line text-success me-2"></i> Page Views Volume</h6>
                        <span class="small text-muted">Daily page view impressions recorded</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold"><?php echo array_sum($pageViewTrend['data']); ?> Views</span>
                    </div>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="chartPageViews"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  6. CHARTS ROW 2: Product Views & Searches                  -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Product Views Trend -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-box-open text-warning me-2"></i> Product Engagement Trend</h6>
                        <span class="small text-muted">Daily product page visits</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 fw-bold"><?php echo array_sum($prodViewTrend['data']); ?> Views</span>
                    </div>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="chartProductViews"></canvas>
                </div>
            </div>
        </div>

        <!-- Search Queries Trend -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-search text-info me-2"></i> Search Velocity</h6>
                        <span class="small text-muted">Daily search volume across the shop</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-bold"><?php echo array_sum($searchTrend['data']); ?> Queries</span>
                    </div>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="chartSearches"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  7. TOP PRODUCTS & TOP SEARCHES                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Top Products -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-trophy text-warning me-2"></i> Top Viewed Products</h6>
                        <span class="small text-muted">Most popular items in your catalog</span>
                    </div>
                    <a href="ajax_analytics_export.php?type=product_views&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="btn-table-csv">
                        <i class="fas fa-file-csv text-success"></i> <span>CSV</span>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Product</th>
                                <th>Views</th>
                                <th>Unique</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($topProducts)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No product views recorded in this period</td></tr>
                        <?php else: foreach ($topProducts as $i => $p):
                            $rankClass = $i === 0 ? 'an-rank-gold' : ($i === 1 ? 'an-rank-silver' : ($i === 2 ? 'an-rank-bronze' : ''));
                        ?>
                            <tr>
                                <td><span class="an-rank <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['product_name'] ?: 'ID: ' . $p['product_id']); ?></td>
                                <td><strong class="text-dark"><?php echo number_format($p['views']); ?></strong></td>
                                <td class="text-muted"><?php echo number_format($p['unique_visitors']); ?></td>
                                <td class="text-end">
                                    <a href="analytics_product.php?id=<?php echo $p['product_id']; ?>" class="btn-analytics-action" title="Inspect Product Analytics">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Insights</span>
                                        <i class="fas fa-arrow-right small text-muted" style="font-size: 0.65rem;"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Searches -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-search text-info me-2"></i> Top Product Searches</h6>
                        <span class="small text-muted">What customers are typing in the search bar</span>
                    </div>
                    <a href="ajax_analytics_export.php?type=searches&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="btn-table-csv">
                        <i class="fas fa-file-csv text-success"></i> <span>CSV</span>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Keyword Query</th>
                                <th>Searches</th>
                                <th>Avg Results</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($topSearches)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No search queries recorded yet</td></tr>
                        <?php else: foreach ($topSearches as $i => $s):
                            $rankClass = $i === 0 ? 'an-rank-gold' : ($i === 1 ? 'an-rank-silver' : ($i === 2 ? 'an-rank-bronze' : ''));
                        ?>
                            <tr>
                                <td><span class="an-rank <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold text-dark"><i class="fas fa-search text-muted me-2 small"></i><?php echo htmlspecialchars($s['query']); ?></td>
                                <td><strong class="text-dark"><?php echo number_format($s['search_count']); ?></strong></td>
                                <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo $s['avg_results']; ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  8. NO-RESULT SEARCHES & SEARCH→PRODUCT CLICKS              -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Zero Result Searches -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Searches With No Results (0 Found)</h6>
                        <span class="small text-muted">Customer demand for missing products</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Searched Term</th>
                                <th>Times Attempted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($noResults)): ?>
                            <tr><td colspan="3" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i>All customer searches returned product results ✓</td></tr>
                        <?php else: foreach ($noResults as $i => $nr): ?>
                            <tr>
                                <td><span class="an-rank"><?php echo $i + 1; ?></span></td>
                                <td class="fw-semibold text-danger"><?php echo htmlspecialchars($nr['query']); ?></td>
                                <td><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 fw-bold"><?php echo number_format($nr['search_count']); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Search to Product -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-mouse-pointer text-primary me-2"></i> Search → Product Conversions</h6>
                        <span class="small text-muted">Products clicked directly from search results</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead>
                            <tr>
                                <th>Search Query</th>
                                <th>Product Clicked</th>
                                <th class="text-end">Clicks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($s2p)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No search-to-product clicks yet</td></tr>
                        <?php else: foreach ($s2p as $row): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><i class="fas fa-search text-muted me-1 small"></i> <?php echo htmlspecialchars($row['search_query']); ?></td>
                                <td><a href="analytics_product.php?id=<?php echo $row['product_id']; ?>" class="text-decoration-none fw-semibold text-primary"><?php echo htmlspecialchars($row['product_name']); ?></a></td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($row['clicks']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  9. TRAFFIC SOURCES, DEVICES, LOCATIONS                     -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Traffic Sources -->
        <div class="col-lg-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-globe text-primary me-2"></i> Traffic Sources</h6>
                    <a href="ajax_analytics_export.php?type=visitors&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="btn-table-csv">
                        <i class="fas fa-file-csv text-success"></i> <span>CSV</span>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>Channel</th><th class="text-end">Visitors</th></tr></thead>
                        <tbody>
                        <?php if (empty($trafficSources)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No data</td></tr>
                        <?php else: foreach ($trafficSources as $ts): ?>
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

        <!-- Devices & Technology -->
        <div class="col-lg-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-mobile-alt text-success me-2"></i> Devices & Platforms</h6>
                </div>
                <div class="table-responsive">
                    <table class="an-table mb-3">
                        <thead><tr><th>Device Type</th><th class="text-end">Sessions</th></tr></thead>
                        <tbody>
                        <?php if (empty($deviceData['devices'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No device data</td></tr>
                        <?php else: foreach ($deviceData['devices'] as $d): ?>
                            <tr>
                                <td>
                                    <i class="fas <?php echo $d['device_type'] === 'mobile' ? 'fa-mobile-alt text-primary' : ($d['device_type'] === 'tablet' ? 'fa-tablet-alt text-warning' : 'fa-desktop text-success'); ?> me-2"></i>
                                    <?php echo ucfirst($d['device_type'] ?: 'Unknown'); ?>
                                </td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($d['cnt']); ?></strong></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <table class="an-table">
                        <thead><tr><th>Top Browsers</th><th class="text-end">Sessions</th></tr></thead>
                        <tbody>
                        <?php if (empty($deviceData['browsers'])): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No browser data</td></tr>
                        <?php else: foreach (array_slice($deviceData['browsers'], 0, 4) as $b): ?>
                            <tr>
                                <td>
                                    <i class="far fa-window-maximize text-secondary me-2"></i>
                                    <?php echo htmlspecialchars($b['browser'] ?: 'Unknown'); ?>
                                </td>
                                <td class="text-end"><strong class="text-dark"><?php echo number_format($b['cnt']); ?></strong></td>
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
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-map-marker-alt text-danger me-2"></i> Visitor Locations</h6>
                    </div>
                    <span class="an-approx-badge"><i class="fas fa-info-circle"></i> Approximate</span>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th>City / Region</th><th>Visits</th><th class="text-end">%</th></tr></thead>
                        <tbody>
                        <?php if (empty($locations)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Location data logging in progress</td></tr>
                        <?php else: foreach ($locations as $loc): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($loc['city'] ?: 'Unknown'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars(($loc['region'] ? $loc['region'] . ', ' : '') . ($loc['country'] ?: '')); ?></div>
                                </td>
                                <td><strong class="text-dark"><?php echo number_format($loc['visitors']); ?></strong></td>
                                <td class="text-end"><span class="badge bg-light text-dark border"><?php echo $loc['percentage']; ?>%</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  11. TOP PAGES & RECENT STORE ACTIVITY LOGS                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- Top Pages -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-file-alt text-purple me-2"></i> Top Landing Pages</h6>
                        <span class="small text-muted">Most visited URLs on your store</span>
                    </div>
                    <a href="ajax_analytics_export.php?type=page_views&filter=<?php echo urlencode($filter); ?>&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>" class="btn-table-csv">
                        <i class="fas fa-file-csv text-success"></i> <span>CSV</span>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="an-table">
                        <thead><tr><th style="width: 50px;">#</th><th>Page URL</th><th>Views</th><th class="text-end">Unique</th></tr></thead>
                        <tbody>
                        <?php if (empty($topPages)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No page views yet</td></tr>
                        <?php else: foreach ($topPages as $i => $pg): ?>
                            <tr>
                                <td><span class="an-rank"><?php echo $i + 1; ?></span></td>
                                <td class="text-break fw-semibold text-dark" style="max-width:280px;"><?php echo htmlspecialchars($pg['page_url']); ?></td>
                                <td><strong class="text-dark"><?php echo number_format($pg['views']); ?></strong></td>
                                <td class="text-end text-muted"><?php echo number_format($pg['unique_visitors']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Store Activity Logs -->
        <div class="col-lg-6">
            <div class="dash-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-info me-2"></i> Recent Store Activity Logs</h6>
                        <span class="small text-muted">Historical visitor & page interaction stream</span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="an-table">
                        <thead><tr><th>Time</th><th>Location</th><th>Device</th><th>Page</th><th>Source</th></tr></thead>
                        <tbody>
                        <?php if (empty($recentActivity)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No recent activity</td></tr>
                        <?php else: foreach ($recentActivity as $ra): ?>
                            <tr>
                                <td class="small text-nowrap text-muted"><?php echo date('M d, H:i', strtotime($ra['last_activity'])); ?></td>
                                <td class="small">
                                    <?php
                                    $locParts = array_filter([$ra['city'], $ra['country']]);
                                    echo !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : '<span class="text-muted">—</span>';
                                    ?>
                                </td>
                                <td class="small">
                                    <i class="fas <?php echo ($ra['device_type'] === 'mobile' ? 'fa-mobile-alt text-primary' : ($ra['device_type'] === 'tablet' ? 'fa-tablet-alt text-warning' : 'fa-desktop text-success')); ?> me-1"></i>
                                    <?php echo htmlspecialchars($ra['browser'] ?: ''); ?>
                                </td>
                                <td class="small text-break" style="max-width:180px;"><?php echo htmlspecialchars($ra['page_url'] ?: '—'); ?></td>
                                <td><span class="an-source-tag an-source-<?php echo htmlspecialchars($ra['traffic_source'] ?: 'direct'); ?>"><?php echo ucfirst(str_replace('_', ' ', $ra['traffic_source'] ?: 'direct')); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  CHARTS & REAL-TIME TELEMETRY INITIALIZATION                 -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    function makeSplineChart(id, label, labels, data, fillColor, strokeColor) {
        var canvas = document.getElementById(id);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, fillColor);
        gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: strokeColor,
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: strokeColor,
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
    }

    makeSplineChart('chartVisitors', 'Visitors',
        <?php echo json_encode($visitorTrend['labels']); ?>,
        <?php echo json_encode($visitorTrend['data']); ?>,
        'rgba(59, 130, 246, 0.35)', '#2563eb');

    makeSplineChart('chartPageViews', 'Page Views',
        <?php echo json_encode($pageViewTrend['labels']); ?>,
        <?php echo json_encode($pageViewTrend['data']); ?>,
        'rgba(16, 185, 129, 0.35)', '#059669');

    makeSplineChart('chartProductViews', 'Product Views',
        <?php echo json_encode($prodViewTrend['labels']); ?>,
        <?php echo json_encode($prodViewTrend['data']); ?>,
        'rgba(245, 158, 11, 0.35)', '#d97706');

    makeSplineChart('chartSearches', 'Searches',
        <?php echo json_encode($searchTrend['labels']); ?>,
        <?php echo json_encode($searchTrend['data']); ?>,
        'rgba(6, 182, 212, 0.35)', '#0891b2');

    // ── Helper: Safe HTML Escape ──────────────────────────────
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Real-Time Live Telemetry Stream Updater ───────────────
    var isFetchingLive = false;
    function updateLiveTelemetry() {
        if (isFetchingLive) return;
        isFetchingLive = true;

        var icon = document.getElementById('refreshLiveIcon');
        if (icon) icon.classList.add('fa-spin');

        fetch('ajax_analytics.php?action=live_stream', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && d.success) {
                    var count = d.count !== undefined ? d.count : 0;

                    // 1. Update Hero Live Count
                    var elHero = document.getElementById('liveCount');
                    if (elHero) elHero.textContent = count;

                    // 2. Update Telemetry Card Header Count
                    var elCardCount = document.getElementById('liveCardCount');
                    if (elCardCount) elCardCount.textContent = count + (count === 1 ? ' Active Now' : ' Active Now');

                    // 3. Update Sync Timestamp
                    var elSync = document.getElementById('lastLiveSyncTime');
                    if (elSync && d.server_time) {
                        elSync.innerHTML = '<i class="far fa-clock me-1"></i> Synced: ' + escapeHtml(d.server_time);
                    }

                    // 4. Render Active Live Visitors List
                    var tbody = document.getElementById('liveVisitorsListBody');
                    if (tbody) {
                        if (!d.visitors || d.visitors.length === 0) {
                            tbody.innerHTML = `
                                <tr id="liveEmptyRow">
                                    <td colspan="6">
                                        <div class="an-live-empty-state">
                                            <div class="an-radar-scanner">
                                                <i class="fas fa-satellite-dish text-success fs-5"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">No Visitors Active Right Now</h6>
                                            <p class="small text-muted mb-0">Real-time telemetry is monitoring your store. When visitors browse, their live location, device, and active page will appear here instantly.</p>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        } else {
                            var rowsHtml = '';
                            var siteBase = '<?php echo defined("SITE_URL") ? rtrim(SITE_URL, "/") : ""; ?>';
                            d.visitors.forEach(function(v) {
                                var devIcon = 'fa-desktop text-success';
                                if (v.device_type === 'mobile') devIcon = 'fa-mobile-alt text-primary';
                                else if (v.device_type === 'tablet') devIcon = 'fa-tablet-alt text-warning';

                                var locCity = v.city || (v.country ? 'City in ' + v.country : 'Unknown City');
                                var locExtra = [v.region, v.country].filter(Boolean).join(', ') || '—';

                                var sec = parseInt(v.seconds_ago || 0, 10);
                                var timeText = 'Just now';
                                if (sec > 10 && sec < 60) timeText = sec + 's ago';
                                else if (sec >= 60) timeText = Math.round(sec / 60) + 'm ago';

                                var pageDisplay = v.page_url ? (v.page_title ? (v.page_title.length > 32 ? v.page_title.substring(0, 30) + '...' : v.page_title) : v.page_url) : '—';
                                var pageUrl = v.page_url ? (siteBase + v.page_url) : '#';

                                var src = v.traffic_source || 'direct';
                                var srcLabel = src.replace('_', ' ');
                                srcLabel = srcLabel.charAt(0).toUpperCase() + srcLabel.slice(1);

                                rowsHtml += `
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="an-live-dot"></span>
                                                <div>
                                                    <span class="fw-bold text-dark font-monospace small">#${v.visitor_uid ? escapeHtml(v.visitor_uid.substring(0, 8)) : 'anon'}</span>
                                                    <div class="text-muted" style="font-size: 0.7rem;">Session #${v.id}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="an-location-badge">
                                                <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                <span class="fw-bold">${escapeHtml(locCity)}</span>
                                            </div>
                                            <div class="small text-muted ms-3">${escapeHtml(locExtra)}</div>
                                        </td>
                                        <td>
                                            <span class="an-device-badge">
                                                <i class="fas ${devIcon}"></i>
                                                ${escapeHtml(v.browser || 'Browser')} on ${escapeHtml(v.os || 'OS')}
                                            </span>
                                        </td>
                                        <td>
                                            ${v.page_url ? `
                                                <a href="${escapeHtml(pageUrl)}" target="_blank" class="an-page-link" title="${escapeHtml(v.page_title || v.page_url)}">
                                                    <i class="fas fa-external-link-alt small text-muted me-1"></i>
                                                    ${escapeHtml(pageDisplay)}
                                                </a>
                                                <div class="text-muted" style="font-size: 0.7rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(v.page_url)}</div>
                                            ` : '<span class="text-muted small">—</span>'}
                                        </td>
                                        <td>
                                            <span class="an-source-tag an-source-${escapeHtml(src)}">${escapeHtml(srcLabel)}</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1 small">
                                                <i class="fas fa-circle text-success me-1" style="font-size: 6px;"></i>
                                                ${timeText}
                                            </span>
                                        </td>
                                    </tr>
                                `;
                            });
                            tbody.innerHTML = rowsHtml;
                        }
                    }
                }
            })
            .catch(function(){})
            .finally(function() {
                isFetchingLive = false;
                if (icon) icon.classList.remove('fa-spin');
            });
    }

    // Manual Refresh button
    var btnRefreshLive = document.getElementById('btnRefreshLive');
    if (btnRefreshLive) {
        btnRefreshLive.addEventListener('click', function(e) {
            e.preventDefault();
            updateLiveTelemetry();
        });
    }

    // Live visitor auto-refresh (every 15 seconds)
    setInterval(updateLiveTelemetry, 15000);
});
</script>

<?php include 'admin_footer.php'; ?>
