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
     * Executes a specific schedule immediately (e.g. via "Run Now" button or scheduler).
     *
     * @param int|array $scheduleOrId
     * @return int Number of queued posts
     * @throws Exception
     */
    public function executeSchedule($scheduleOrId): int {
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

        // 2. Fetch Eligible Product
        $product = $this->getNextProductForSchedule($schedule, array_keys($accounts));
        if (!$product) {
            $this->updateScheduleNextRun($schedule);
            throw new Exception("No eligible product found to post for this schedule.");
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

        // 4. Image & Product URL
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $rawImg = trim($product['image'] ?? '');
        $imgUrl = '';
        if (!empty($rawImg)) {
            if (strpos($rawImg, 'http://') === 0 || strpos($rawImg, 'https://') === 0) {
                $imgUrl = $rawImg;
            } else {
                $imgUrl = $siteUrl . '/uploads/media/images/' . ltrim($rawImg, '/');
            }
        }

        $prodUrl = $siteUrl . '/product.php?id=' . $product['id'];
        if (!empty($product['slug'])) {
            $prodUrl = $siteUrl . '/product/' . $product['slug'];
        }

        // 5. Render & Insert into Queue
        $cta = trim($schedule['cta'] ?? 'Shop Now 🛒');
        $hashtags = trim($schedule['hashtags'] ?? '#SagarStarters #Shopping');

        $renderedContent = $this->templateEngine->render($templateBody, $product, [
            'hashtags' => $hashtags,
            'cta' => $cta,
            'cta_text' => $cta
        ]);

        $queuedCount = 0;
        $now = date('Y-m-d H:i:s');

        $stmtQueue = $db->prepare("INSERT INTO sm_queue 
            (product_id, platform, account_id, schedule_id, template_id, status, post_content, post_image_url, post_link, scheduled_at, max_retries) 
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, 3)");

        foreach ($accounts as $acc) {
            // Skip if this product is already in active queue for this platform
            $chkStmt = $db->prepare("SELECT COUNT(*) FROM sm_queue 
                WHERE product_id = ? AND LOWER(platform) = ? AND status IN ('pending', 'scheduled', 'publishing')");
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
                $now
            ]);
            $queuedCount++;
        }

        // 6. Update Schedule execution timestamps
        $this->updateScheduleNextRun($schedule);

        return $queuedCount;
    }

    /**
     * Selects the next product for a schedule based on product filters and past posting activity.
     */
    private function getNextProductForSchedule(array $schedule, array $targetPlatforms): ?array {
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

        // Pick product that has been posted least recently (or never posted) for this schedule
        $sql = "SELECT p.* FROM products p 
            $where 
            ORDER BY (
                SELECT COALESCE(MAX(q.id), 0) FROM sm_queue q WHERE q.product_id = p.id AND q.schedule_id = ?
            ) ASC, p.id ASC 
            LIMIT 1";

        $stmt = $db->prepare($sql);
        $execParams = array_merge($params, [(int)$schedule['id']]);
        $stmt->execute($execParams);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ?: null;
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
