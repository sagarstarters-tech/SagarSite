<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;
use Exception;
use DateTime;

require_once __DIR__ . '/TemplateEngine.php';
require_once __DIR__ . '/ScheduleResolver.php';

/**
 * Class ScheduleRunner
 * Evaluates active posting schedules and auto-queues posts into sm_queue.
 */
class ScheduleRunner {

    private TemplateEngine $templateEngine;
    private ScheduleResolver $scheduleResolver;

    public function __construct() {
        $this->templateEngine = new TemplateEngine();
        $this->scheduleResolver = new ScheduleResolver();
    }

    /**
     * Evaluates all active schedules and executes any that are due.
     *
     * @return array
     */
    public function processActiveSchedules(): array {
        $db = DbConnection::getInstance();
        $now = date('Y-m-d H:i:s');

        try {
            $db->query("SELECT last_run_at FROM sm_schedules LIMIT 1");
        } catch (\PDOException $e) {
            require_once dirname(__DIR__) . '/migrations/001_create_social_media_tables.php';
            ob_start();
            runMigration();
            ob_end_clean();
        }

        $stmt = $db->query("SELECT * FROM sm_schedules WHERE is_active = 1 AND (next_run_at IS NULL OR next_run_at <= '$now') ORDER BY id ASC");
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'schedules_checked' => count($schedules),
            'schedules_run' => 0,
            'posts_queued' => 0,
            'errors' => []
        ];

        foreach ($schedules as $sched) {
            try {
                $count = $this->executeSchedule($sched);
                $summary['schedules_run']++;
                $summary['posts_queued'] += $count;
            } catch (Exception $e) {
                $summary['errors'][] = "Schedule #{$sched['id']} ({$sched['name']}): " . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Executes a schedule and queues products into sm_queue.
     *
     * @param int|array $scheduleOrId
     * @param bool $allProducts If true, queues all matching products in scope staggered by interval
     * @return int Number of queued posts
     * @throws Exception
     */
    public function executeSchedule($scheduleOrId, bool $allProducts = true): int {
        $db = DbConnection::getInstance();

        try {
            $db->query("SELECT last_run_at FROM sm_schedules LIMIT 1");
        } catch (\PDOException $e) {
            require_once dirname(__DIR__) . '/migrations/001_create_social_media_tables.php';
            ob_start();
            runMigration();
            ob_end_clean();
        }

        if (is_numeric($scheduleOrId)) {
            $stmt = $db->prepare("SELECT * FROM sm_schedules WHERE id = ?");
            $stmt->execute([(int)$scheduleOrId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$schedule) {
                throw new Exception("Schedule ID {$scheduleOrId} not found.");
            }
        } else {
            $schedule = $scheduleOrId;
        }

        $scheduleId = (int)$schedule['id'];

        // 1. Resolve Target Platforms & Connected Accounts
        $pIds = json_decode($schedule['platform_ids'] ?? '[]', true) ?: [];
        $pIds = array_values(array_filter(array_map('strtolower', (array)$pIds)));

        if (!empty($pIds)) {
            $inClause = implode(',', array_map(fn($p) => $db->quote($p), $pIds));
            $stmtAcc = $db->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1 AND LOWER(platform) IN ($inClause)");
        } else {
            $stmtAcc = $db->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1");
        }
        $rawAccounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

        // Map 1 active account per platform
        $accounts = [];
        foreach ($rawAccounts as $acc) {
            $pKey = strtolower(trim($acc['platform']));
            if (!isset($accounts[$pKey])) {
                $accounts[$pKey] = $acc;
            }
        }

        if (empty($accounts)) {
            $this->updateScheduleNextRun($schedule);
            throw new Exception("No active connected accounts found for the schedule's target platforms.");
        }

        // 2. Fetch Matching Products for Schedule Scope
        $products = $this->getProductsForSchedule($schedule, $allProducts);
        if (empty($products)) {
            $this->updateScheduleNextRun($schedule);
            throw new Exception("No matching products found to post for this schedule.");
        }

        // 3. Resolve Template Body
        $templateBody = null;
        if (!empty($schedule['template_id'])) {
            $stmtTpl = $db->prepare("SELECT template_body FROM sm_templates WHERE id = ? AND is_active = 1");
            $stmtTpl->execute([(int)$schedule['template_id']]);
            $templateBody = $stmtTpl->fetchColumn();
        }
        if (!$templateBody) {
            // Default fallback template
            $stmtTpl = $db->query("SELECT template_body FROM sm_templates WHERE is_default = 1 AND is_active = 1 LIMIT 1");
            $templateBody = $stmtTpl->fetchColumn();
        }
        if (!$templateBody) {
            $templateBody = "🔥 {product_name} 🔥\n\n💰 Price: ₹{price}\n🛒 Link: {product_url}\n\n{cta}\n\n{hashtags}";
        }

        // 4. Calculate Stagger Interval — use the schedule's posting frequency
        // This is the gap between each PRODUCT in the queue.
        // All platforms for the same product are posted at the same time.
        $intervalMinutes = (int)($schedule['interval_minutes'] ?? 60);
        if ($intervalMinutes < 1) $intervalMinutes = 5;

        $typeIntervals = [
            'every_5min'  => 5,
            'every_15min' => 15,
            'every_30min' => 30,
            'every_1hr'   => 60,
            'every_2hr'   => 120,
            'every_6hr'   => 360,
            'daily'       => 1440,
            'weekly'      => 10080,
            'monthly'     => 43200
        ];
        if (isset($typeIntervals[$schedule['schedule_type']])) {
            $intervalMinutes = $typeIntervals[$schedule['schedule_type']];
        }

        // Resolve Base Start Time
        $baseStartTimestamp = time();
        if (!empty($schedule['next_run_at'])) {
            $nextTs = strtotime($schedule['next_run_at']);
            if ($nextTs !== false && $nextTs > time()) {
                $baseStartTimestamp = $nextTs;
            }
        } elseif (!empty($schedule['start_date']) || !empty($schedule['start_time'])) {
            $sDate = !empty($schedule['start_date']) ? $schedule['start_date'] : date('Y-m-d');
            $sTime = !empty($schedule['start_time']) ? $schedule['start_time'] : '09:00:00';
            $parsedStart = strtotime("$sDate $sTime");
            if ($parsedStart !== false && $parsedStart > time()) {
                $baseStartTimestamp = $parsedStart;
            }
        }

        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $cta = trim($schedule['cta'] ?? 'Shop Now 🛒');
        $hashtags = trim($schedule['hashtags'] ?? '#SagarStarters #Shopping');

        $queuedCount = 0;
        $staggerIndex = 0;

        $stmtQueue = $db->prepare("INSERT INTO sm_queue 
            (product_id, platform, account_id, schedule_id, template_id, status, post_content, post_image_url, post_link, scheduled_at, max_retries) 
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, 3)");

        foreach ($products as $product) {
            $rawImg = trim($product['image'] ?? '');
            $imgUrl = '';
            if (function_exists('resolve_product_image_url')) {
                $imgUrl = resolve_product_image_url($rawImg, null, (int)$product['id']);
            } else {
                if (!empty($rawImg)) {
                    if (strpos($rawImg, 'http://') === 0 || strpos($rawImg, 'https://') === 0) {
                        $imgUrl = $rawImg;
                    } elseif (strpos($rawImg, 'uploads/') === 0 || strpos($rawImg, 'assets/') === 0) {
                        $imgUrl = $siteUrl . '/' . ltrim($rawImg, '/');
                    } else {
                        $imgUrl = $siteUrl . '/assets/images/' . ltrim($rawImg, '/');
                    }
                }
            }

            $prodUrl = $siteUrl . '/product.php?id=' . $product['id'];
            if (!empty($product['slug'])) {
                $prodUrl = $siteUrl . '/product/' . $product['slug'];
            }

            $renderedContent = $this->templateEngine->render($templateBody, $product, [
                'hashtags' => $hashtags,
                'cta' => $cta,
                'cta_text' => $cta
            ]);

            // Calculate scheduled time for this product (same time for all platforms)
            $scheduledTime = $baseStartTimestamp + ($staggerIndex * $intervalMinutes * 60);
            $scheduledAt = date('Y-m-d H:i:s', $scheduledTime);
            $productQueued = false;

            foreach ($accounts as $acc) {
                // Strict duplicate check: skip if already scheduled/pending or posted in last 7 days for this platform
                $chkStmt = $db->prepare("SELECT COUNT(*) FROM sm_queue 
                    WHERE product_id = ? AND LOWER(platform) = ?
                    AND (
                        status IN ('pending', 'scheduled', 'publishing')
                        OR (status = 'posted' AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                    )");
                $chkStmt->execute([$product['id'], strtolower($acc['platform'])]);
                if ($chkStmt->fetchColumn() > 0) {
                    continue;
                }

                $stmtQueue->execute([
                    $product['id'],
                    strtolower($acc['platform']),
                    $acc['id'],
                    $scheduleId,
                    $schedule['template_id'] ?: null,
                    $renderedContent,
                    $imgUrl,
                    $prodUrl,
                    $scheduledAt
                ]);
                $queuedCount++;
                $productQueued = true;
            }

            // Stagger increments per PRODUCT (not per platform)
            // So all platforms of the same product post at the same time
            if ($productQueued) {
                $staggerIndex++;
            }
        }

        // 5. Update Schedule execution timestamps
        $this->updateScheduleNextRun($schedule);

        return $queuedCount;
    }

    /**
     * Fetches products matching schedule filters.
     */
    private function getProductsForSchedule(array $schedule, bool $allProducts = true): array {
        $db = DbConnection::getInstance();
        $filterType = $schedule['filter_type'] ?? 'all';
        $filterVal = $schedule['filter_value'] ?? null;

        $where = "WHERE 1=1";
        $params = [];

        if ($filterType === 'category' && !empty($filterVal)) {
            if (ctype_digit((string)$filterVal)) {
                $where .= " AND category_id = ?";
            } else {
                $where .= " AND category = ?";
            }
            $params[] = $filterVal;
        } elseif ($filterType === 'brand' && !empty($filterVal)) {
            $where .= " AND brand = ?";
            $params[] = $filterVal;
        } elseif ($filterType === 'selected') {
            $selectedIds = is_array($filterVal) ? $filterVal : json_decode($filterVal ?: '[]', true);
            if (!empty($selectedIds)) {
                $inClause = implode(',', array_map('intval', $selectedIds));
                $where .= " AND id IN ($inClause)";
            }
        }

        $limitClause = $allProducts ? "" : "LIMIT 1";

        $sql = "SELECT p.* FROM products p 
            $where 
            ORDER BY (
                SELECT COALESCE(MAX(q.id), 0) FROM sm_queue q WHERE q.product_id = p.id AND q.schedule_id = ?
            ) ASC, p.id ASC 
            $limitClause";

        $stmt = $db->prepare($sql);
        $execParams = array_merge($params, [(int)$schedule['id']]);
        $stmt->execute($execParams);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Updates schedule's last_run_at and calculates next_run_at.
     */
    private function updateScheduleNextRun(array $schedule): void {
        $db = DbConnection::getInstance();
        $now = new DateTime();
        $lastRunAt = $now->format('Y-m-d H:i:s');

        $nextDateTime = $this->scheduleResolver->getNextPostTime($schedule);
        $nextRunAt = $nextDateTime ? $nextDateTime->format('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $db->prepare("UPDATE sm_schedules SET last_run_at = ?, next_run_at = ? WHERE id = ?");
        $stmt->execute([$lastRunAt, $nextRunAt, (int)$schedule['id']]);
    }
}
