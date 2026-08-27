<?php
/**
 * ============================================================
 *  AnalyticsService — Core Analytics Engine
 *  Location: /admin/modules/AnalyticsService.php
 * ============================================================
 *  Provides all aggregation queries for the admin analytics
 *  dashboard. Uses PDO with prepared statements throughout.
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/config/DbConnection.php';

class AnalyticsService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbConnection::getInstance();
    }

    // ── Settings ─────────────────────────────────────────────

    public function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM analytics_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    }

    public function setSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO analytics_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }

    // ── Date Helpers ─────────────────────────────────────────

    /**
     * Returns [start_date, end_date] as 'Y-m-d' strings based on the filter preset.
     */
    public function getDateRange(string $filter, ?string $customStart = null, ?string $customEnd = null): array
    {
        $today = date('Y-m-d');
        switch ($filter) {
            case 'today':
                return [$today, $today];
            case 'yesterday':
                $y = date('Y-m-d', strtotime('-1 day'));
                return [$y, $y];
            case '7days':
                return [date('Y-m-d', strtotime('-6 days')), $today];
            case '30days':
                return [date('Y-m-d', strtotime('-29 days')), $today];
            case 'this_month':
                return [date('Y-m-01'), $today];
            case 'last_month':
                return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))];
            case 'custom':
                $s = $customStart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStart) ? $customStart : $today;
                $e = $customEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEnd) ? $customEnd : $today;
                if ($s > $e) [$s, $e] = [$e, $s];
                return [$s, $e];
            default:
                return [date('Y-m-d', strtotime('-6 days')), $today];
        }
    }

    // ── Summary Statistics ───────────────────────────────────

    public function getSummaryStats(string $startDate, string $endDate): array
    {
        $start = $startDate . ' 00:00:00';
        $end   = $endDate . ' 23:59:59';

        // Total visitors (sessions)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_visitors WHERE first_visit BETWEEN ? AND ? AND is_bot = 0");
        $stmt->execute([$start, $end]);
        $totalVisitors = (int)$stmt->fetchColumn();

        // Unique visitors
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT visitor_uid) FROM analytics_visitors WHERE first_visit BETWEEN ? AND ? AND is_bot = 0");
        $stmt->execute([$start, $end]);
        $uniqueVisitors = (int)$stmt->fetchColumn();

        // Page views
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_page_views WHERE viewed_at BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        $pageViews = (int)$stmt->fetchColumn();

        // Product views
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE viewed_at BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        $productViews = (int)$stmt->fetchColumn();

        // Searches
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_searches WHERE searched_at BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        $searches = (int)$stmt->fetchColumn();

        // Today's numbers
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayEnd   = date('Y-m-d') . ' 23:59:59';

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT visitor_uid) FROM analytics_visitors WHERE first_visit BETWEEN ? AND ? AND is_bot = 0");
        $stmt->execute([$todayStart, $todayEnd]);
        $todayVisitors = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE viewed_at BETWEEN ? AND ?");
        $stmt->execute([$todayStart, $todayEnd]);
        $todayProductViews = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_searches WHERE searched_at BETWEEN ? AND ?");
        $stmt->execute([$todayStart, $todayEnd]);
        $todaySearches = (int)$stmt->fetchColumn();

        return [
            'total_visitors'      => $totalVisitors,
            'unique_visitors'     => $uniqueVisitors,
            'page_views'          => $pageViews,
            'product_views'       => $productViews,
            'searches'            => $searches,
            'today_visitors'      => $todayVisitors,
            'today_product_views' => $todayProductViews,
            'today_searches'      => $todaySearches,
        ];
    }

    // ── Trend Data (for charts) ──────────────────────────────

    public function getVisitorTrend(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(first_visit) as dt, COUNT(DISTINCT visitor_uid) as cnt
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            GROUP BY DATE(first_visit)
            ORDER BY dt ASC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $this->fillDateGaps($stmt->fetchAll(), $startDate, $endDate);
    }

    public function getPageViewTrend(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(viewed_at) as dt, COUNT(*) as cnt
            FROM analytics_page_views
            WHERE viewed_at BETWEEN ? AND ?
            GROUP BY DATE(viewed_at)
            ORDER BY dt ASC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $this->fillDateGaps($stmt->fetchAll(), $startDate, $endDate);
    }

    public function getProductViewTrend(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(viewed_at) as dt, COUNT(*) as cnt
            FROM analytics_product_views
            WHERE viewed_at BETWEEN ? AND ?
            GROUP BY DATE(viewed_at)
            ORDER BY dt ASC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $this->fillDateGaps($stmt->fetchAll(), $startDate, $endDate);
    }

    public function getSearchTrend(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(searched_at) as dt, COUNT(*) as cnt
            FROM analytics_searches
            WHERE searched_at BETWEEN ? AND ?
            GROUP BY DATE(searched_at)
            ORDER BY dt ASC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $this->fillDateGaps($stmt->fetchAll(), $startDate, $endDate);
    }

    private function fillDateGaps(array $rows, string $startDate, string $endDate): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r['dt']] = (int)$r['cnt'];
        }
        $labels = [];
        $data   = [];
        $current = new DateTime($startDate);
        $end     = new DateTime($endDate);
        while ($current <= $end) {
            $d = $current->format('Y-m-d');
            $labels[] = $current->format('M d');
            $data[]   = $map[$d] ?? 0;
            $current->modify('+1 day');
        }
        return ['labels' => $labels, 'data' => $data];
    }

    // ── Top Products ─────────────────────────────────────────

    public function getTopProducts(string $startDate, string $endDate, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT product_id, product_name,
                   COUNT(*) as views,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM analytics_product_views
            WHERE viewed_at BETWEEN ? AND ?
            GROUP BY product_id, product_name
            ORDER BY views DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    // ── Top Searches ─────────────────────────────────────────

    public function getTopSearches(string $startDate, string $endDate, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT LOWER(TRIM(search_query)) as query,
                   COUNT(*) as search_count,
                   ROUND(AVG(result_count)) as avg_results
            FROM analytics_searches
            WHERE searched_at BETWEEN ? AND ?
            GROUP BY LOWER(TRIM(search_query))
            ORDER BY search_count DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    public function getNoResultSearches(string $startDate, string $endDate, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT LOWER(TRIM(search_query)) as query,
                   COUNT(*) as search_count
            FROM analytics_searches
            WHERE searched_at BETWEEN ? AND ?
              AND result_count = 0
            GROUP BY LOWER(TRIM(search_query))
            ORDER BY search_count DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    // ── Traffic Sources ──────────────────────────────────────

    public function getTrafficSources(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT traffic_source, COUNT(*) as cnt
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            GROUP BY traffic_source
            ORDER BY cnt DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    // ── Device/Browser/OS ────────────────────────────────────

    public function getDeviceBreakdown(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT device_type, COUNT(*) as cnt
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            GROUP BY device_type
            ORDER BY cnt DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $devices = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT browser, COUNT(*) as cnt
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            GROUP BY browser
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $browsers = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT os, COUNT(*) as cnt
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            GROUP BY os
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $oss = $stmt->fetchAll();

        return ['devices' => $devices, 'browsers' => $browsers, 'os' => $oss];
    }

    // ── Location Report ──────────────────────────────────────

    public function getLocationReport(string $startDate, string $endDate, int $limit = 30): array
    {
        $start = $startDate . ' 00:00:00';
        $end   = $endDate . ' 23:59:59';

        $stmt = $this->pdo->prepare("
            SELECT country, region, city, COUNT(*) as visitors
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ?
              AND is_bot = 0
              AND country IS NOT NULL AND country != ''
            GROUP BY country, region, city
            ORDER BY visitors DESC
            LIMIT ?
        ");
        $stmt->execute([$start, $end, $limit]);
        $locations = $stmt->fetchAll();

        // Get total for percentage
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_visitors WHERE first_visit BETWEEN ? AND ? AND is_bot = 0");
        $stmt->execute([$start, $end]);
        $total = max((int)$stmt->fetchColumn(), 1);

        foreach ($locations as &$loc) {
            $loc['percentage'] = round(($loc['visitors'] / $total) * 100, 1);
        }

        return $locations;
    }

    // ── Top Pages ────────────────────────────────────────────

    public function getTopPages(string $startDate, string $endDate, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT page_url,
                   COUNT(*) as views,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM analytics_page_views
            WHERE viewed_at BETWEEN ? AND ?
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    // ── Recent Activity ──────────────────────────────────────

    public function getRecentActivity(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.last_activity, v.device_type, v.browser, v.traffic_source,
                   v.country, v.region, v.city,
                   pv.page_url, pv.page_title
            FROM analytics_visitors v
            LEFT JOIN analytics_page_views pv ON pv.visitor_id = v.id
                AND pv.id = (SELECT MAX(pv2.id) FROM analytics_page_views pv2 WHERE pv2.visitor_id = v.id)
            WHERE v.is_bot = 0
            ORDER BY v.last_activity DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ── Live Visitors ────────────────────────────────────────

    public function getLiveVisitors(): array
    {
        $threshold = (int)$this->getSetting('live_visitor_threshold', '300');
        $cutoff = date('Y-m-d H:i:s', time() - $threshold);

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.device_type, v.browser, v.country, v.city, v.last_activity,
                   pv.page_url, pv.page_title
            FROM analytics_visitors v
            LEFT JOIN analytics_page_views pv ON pv.visitor_id = v.id
                AND pv.id = (SELECT MAX(pv2.id) FROM analytics_page_views pv2 WHERE pv2.visitor_id = v.id)
            WHERE v.last_activity >= ? AND v.is_bot = 0
            ORDER BY v.last_activity DESC
            LIMIT 50
        ");
        $stmt->execute([$cutoff]);
        $visitors = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT id) FROM analytics_visitors WHERE last_activity >= ? AND is_bot = 0");
        $stmt->execute([$cutoff]);
        $count = (int)$stmt->fetchColumn();

        return ['count' => $count, 'visitors' => $visitors];
    }

    // ── Product Analytics Detail ─────────────────────────────

    public function getProductAnalytics(int $productId): array
    {
        $now = date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayEnd   = date('Y-m-d') . ' 23:59:59';
        $d7  = date('Y-m-d', strtotime('-6 days')) . ' 00:00:00';
        $d30 = date('Y-m-d', strtotime('-29 days')) . ' 00:00:00';

        // Total views
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE product_id = ?");
        $stmt->execute([$productId]);
        $totalViews = (int)$stmt->fetchColumn();

        // Unique visitors
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT visitor_id) FROM analytics_product_views WHERE product_id = ?");
        $stmt->execute([$productId]);
        $uniqueVisitors = (int)$stmt->fetchColumn();

        // Today
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE product_id = ? AND viewed_at BETWEEN ? AND ?");
        $stmt->execute([$productId, $todayStart, $todayEnd]);
        $todayViews = (int)$stmt->fetchColumn();

        // 7 day
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE product_id = ? AND viewed_at >= ?");
        $stmt->execute([$productId, $d7]);
        $d7Views = (int)$stmt->fetchColumn();

        // 30 day
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_product_views WHERE product_id = ? AND viewed_at >= ?");
        $stmt->execute([$productId, $d30]);
        $d30Views = (int)$stmt->fetchColumn();

        // Product name
        $stmt = $this->pdo->prepare("SELECT product_name FROM analytics_product_views WHERE product_id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $productName = $stmt->fetchColumn() ?: 'Unknown Product';

        // Daily views chart (30 days)
        $stmt = $this->pdo->prepare("
            SELECT DATE(viewed_at) as dt, COUNT(*) as cnt
            FROM analytics_product_views
            WHERE product_id = ? AND viewed_at >= ?
            GROUP BY DATE(viewed_at)
            ORDER BY dt ASC
        ");
        $stmt->execute([$productId, $d30]);
        $dailyChart = $this->fillDateGaps($stmt->fetchAll(), date('Y-m-d', strtotime('-29 days')), date('Y-m-d'));

        // Traffic sources
        $stmt = $this->pdo->prepare("
            SELECT v.traffic_source, COUNT(*) as cnt
            FROM analytics_product_views pv
            JOIN analytics_visitors v ON v.id = pv.visitor_id
            WHERE pv.product_id = ?
            GROUP BY v.traffic_source
            ORDER BY cnt DESC
        ");
        $stmt->execute([$productId]);
        $trafficSources = $stmt->fetchAll();

        // Locations
        $stmt = $this->pdo->prepare("
            SELECT v.country, v.region, v.city, COUNT(*) as cnt
            FROM analytics_product_views pv
            JOIN analytics_visitors v ON v.id = pv.visitor_id
            WHERE pv.product_id = ?
              AND v.country IS NOT NULL AND v.country != ''
            GROUP BY v.country, v.region, v.city
            ORDER BY cnt DESC
            LIMIT 20
        ");
        $stmt->execute([$productId]);
        $locations = $stmt->fetchAll();

        // Devices
        $stmt = $this->pdo->prepare("
            SELECT v.device_type, COUNT(*) as cnt
            FROM analytics_product_views pv
            JOIN analytics_visitors v ON v.id = pv.visitor_id
            WHERE pv.product_id = ?
            GROUP BY v.device_type
            ORDER BY cnt DESC
        ");
        $stmt->execute([$productId]);
        $devices = $stmt->fetchAll();

        // Related searches
        $stmt = $this->pdo->prepare("
            SELECT from_search as query, COUNT(*) as cnt
            FROM analytics_product_views
            WHERE product_id = ? AND from_search IS NOT NULL AND from_search != ''
            GROUP BY from_search
            ORDER BY cnt DESC
            LIMIT 15
        ");
        $stmt->execute([$productId]);
        $relatedSearches = $stmt->fetchAll();

        return [
            'product_id'      => $productId,
            'product_name'    => $productName,
            'total_views'     => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'today_views'     => $todayViews,
            '7day_views'      => $d7Views,
            '30day_views'     => $d30Views,
            'daily_chart'     => $dailyChart,
            'traffic_sources' => $trafficSources,
            'locations'       => $locations,
            'devices'         => $devices,
            'related_searches' => $relatedSearches,
        ];
    }

    // ── Search → Product Insights ────────────────────────────

    public function getSearchToProductInsights(string $startDate, string $endDate, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT from_search as search_query,
                   product_name,
                   product_id,
                   COUNT(*) as clicks
            FROM analytics_product_views
            WHERE viewed_at BETWEEN ? AND ?
              AND from_search IS NOT NULL AND from_search != ''
            GROUP BY from_search, product_name, product_id
            ORDER BY clicks DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    // ── CSV Export Generators ────────────────────────────────

    public function exportVisitors(string $startDate, string $endDate): \Generator
    {
        $stmt = $this->pdo->prepare("
            SELECT first_visit, last_activity, landing_page, referrer, traffic_source,
                   device_type, browser, os, country, region, city
            FROM analytics_visitors
            WHERE first_visit BETWEEN ? AND ? AND is_bot = 0
            ORDER BY first_visit DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    public function exportPageViews(string $startDate, string $endDate): \Generator
    {
        $stmt = $this->pdo->prepare("
            SELECT pv.viewed_at, pv.page_url, pv.page_title, pv.referrer,
                   v.device_type, v.browser, v.os, v.country, v.city
            FROM analytics_page_views pv
            LEFT JOIN analytics_visitors v ON v.id = pv.visitor_id
            WHERE pv.viewed_at BETWEEN ? AND ?
            ORDER BY pv.viewed_at DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    public function exportProductViews(string $startDate, string $endDate): \Generator
    {
        $stmt = $this->pdo->prepare("
            SELECT pv.viewed_at, pv.product_id, pv.product_name, pv.from_search,
                   v.device_type, v.browser, v.country, v.city
            FROM analytics_product_views pv
            LEFT JOIN analytics_visitors v ON v.id = pv.visitor_id
            WHERE pv.viewed_at BETWEEN ? AND ?
            ORDER BY pv.viewed_at DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    public function exportSearches(string $startDate, string $endDate): \Generator
    {
        $stmt = $this->pdo->prepare("
            SELECT s.searched_at, s.search_query, s.result_count,
                   v.device_type, v.browser, v.country, v.city
            FROM analytics_searches s
            LEFT JOIN analytics_visitors v ON v.id = s.visitor_id
            WHERE s.searched_at BETWEEN ? AND ?
            ORDER BY s.searched_at DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    // ── Data Retention Cleanup ───────────────────────────────

    public function cleanupOldData(): array
    {
        $months = (int)$this->getSetting('retention_months', '12');
        if ($months < 1) $months = 12;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$months} months"));
        $deleted = [];
        $batchSize = 5000;

        // Delete in batches to avoid long locks
        $tables = [
            'analytics_page_views'    => 'viewed_at',
            'analytics_product_views' => 'viewed_at',
            'analytics_searches'      => 'searched_at',
        ];

        foreach ($tables as $table => $dateCol) {
            $total = 0;
            do {
                $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE `{$dateCol}` < ? LIMIT {$batchSize}");
                $stmt->execute([$cutoff]);
                $count = $stmt->rowCount();
                $total += $count;
            } while ($count >= $batchSize);
            $deleted[$table] = $total;
        }

        // Clean orphaned visitors
        $total = 0;
        do {
            $stmt = $this->pdo->prepare("DELETE FROM analytics_visitors WHERE last_activity < ? LIMIT {$batchSize}");
            $stmt->execute([$cutoff]);
            $count = $stmt->rowCount();
            $total += $count;
        } while ($count >= $batchSize);
        $deleted['analytics_visitors'] = $total;

        $this->setSetting('last_cleanup_run', (string)time());

        return $deleted;
    }

    // ── Traffic Source Classifier ─────────────────────────────

    public static function classifyTrafficSource(?string $referrer): string
    {
        if (empty($referrer)) return 'direct';

        $ref = strtolower($referrer);

        if (strpos($ref, 'google.') !== false || strpos($ref, 'googleapis.') !== false) return 'google';
        if (strpos($ref, 'facebook.') !== false || strpos($ref, 'fb.') !== false || strpos($ref, 'fbclid') !== false) return 'facebook';
        if (strpos($ref, 'instagram.') !== false) return 'instagram';
        if (strpos($ref, 'youtube.') !== false || strpos($ref, 'youtu.be') !== false) return 'youtube';
        if (strpos($ref, 'bing.') !== false || strpos($ref, 'yahoo.') !== false || strpos($ref, 'duckduckgo.') !== false || strpos($ref, 'baidu.') !== false) return 'search_engine';
        if (strpos($ref, 'twitter.') !== false || strpos($ref, 't.co') !== false || strpos($ref, 'x.com') !== false) return 'social';
        if (strpos($ref, 'linkedin.') !== false) return 'social';
        if (strpos($ref, 'whatsapp.') !== false || strpos($ref, 'wa.me') !== false) return 'whatsapp';

        // Check if it's the same domain (self-referral = direct)
        $siteUrl = defined('SITE_URL') ? strtolower(SITE_URL) : '';
        if (!empty($siteUrl)) {
            $siteDomain = parse_url($siteUrl, PHP_URL_HOST);
            $refDomain  = parse_url($referrer, PHP_URL_HOST);
            if ($siteDomain && $refDomain && ($siteDomain === $refDomain || str_ends_with($refDomain, '.' . $siteDomain))) {
                return 'direct';
            }
        }

        return 'referral';
    }

    // ── Bot Detection ────────────────────────────────────────

    public static function isBot(string $userAgent): bool
    {
        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'lighthouse', 'pagespeed', 'headlesschrome', 'phantomjs',
            'python', 'java/', 'wget', 'curl', 'httpclient',
            'facebook', 'whatsapp', 'twitterbot', 'linkedinbot',
            'semrush', 'ahrefs', 'mj12bot', 'dotbot', 'yandex',
            'bingpreview', 'google-inspection',
        ];
        $ua = strtolower($userAgent);
        foreach ($botPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) return true;
        }
        return false;
    }

    // ── User Agent Parser ────────────────────────────────────

    public static function parseUserAgent(string $ua): array
    {
        $browser = 'Unknown';
        $os      = 'Unknown';
        $device  = 'desktop';

        // Device type
        $uaLower = strtolower($ua);
        if (preg_match('/mobile|android.*mobile|iphone|ipod|opera mini|iemobile|wpdesktop/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/tablet|ipad|playbook|silk|kindle/i', $ua)) {
            $device = 'tablet';
        }

        // Browser
        if (preg_match('/EdgA?\/[\d.]+|Edg\/[\d.]+/i', $ua))       $browser = 'Edge';
        elseif (preg_match('/OPR\/[\d.]+|Opera/i', $ua))            $browser = 'Opera';
        elseif (preg_match('/SamsungBrowser/i', $ua))                $browser = 'Samsung Browser';
        elseif (preg_match('/UCBrowser/i', $ua))                     $browser = 'UC Browser';
        elseif (preg_match('/Firefox\/[\d.]+/i', $ua))               $browser = 'Firefox';
        elseif (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Chromium/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome|Chromium/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/MSIE|Trident/i', $ua))                  $browser = 'Internet Explorer';

        // OS
        if (preg_match('/Windows NT 10/i', $ua))       $os = 'Windows 10/11';
        elseif (preg_match('/Windows NT/i', $ua))       $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua))         $os = 'macOS';
        elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) $os = 'Android ' . $m[1];
        elseif (preg_match('/Android/i', $ua))          $os = 'Android';
        elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) $os = 'iOS ' . str_replace('_', '.', $m[1]);
        elseif (preg_match('/iPad/i', $ua))             $os = 'iPadOS';
        elseif (preg_match('/Linux/i', $ua))            $os = 'Linux';
        elseif (preg_match('/CrOS/i', $ua))             $os = 'Chrome OS';

        return ['browser' => $browser, 'os' => $os, 'device_type' => $device];
    }

    // ── Geolocation (ip-api.com) ─────────────────────────────

    public function geolocate(string $ip): array
    {
        $default = ['country' => null, 'region' => null, 'city' => null];

        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return $default;
        }

        // Check cache: same ip_hash already has location
        $ipHash = hash('sha256', $ip);
        $stmt = $this->pdo->prepare("
            SELECT country, region, city FROM analytics_visitors
            WHERE ip_hash = ? AND country IS NOT NULL AND country != ''
            LIMIT 1
        ");
        $stmt->execute([$ipHash]);
        $cached = $stmt->fetch();
        if ($cached) {
            return $cached;
        }

        // Call ip-api.com (free, server-side, no API key)
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city';

        try {
            $response = @file_get_contents($url, false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? null,
                        'region'  => $data['regionName'] ?? null,
                        'city'    => $data['city'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Geolocation failure is non-critical
        }

        return $default;
    }
}
