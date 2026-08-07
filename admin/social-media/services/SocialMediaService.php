<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;

/**
 * Class SocialMediaService
 * Central orchestrator for the Social Media Automation Module.
 */
class SocialMediaService {

    private TemplateEngine $templateEngine;
    private DuplicateGuard $duplicateGuard;
    private ScheduleResolver $scheduleResolver;

    public function __construct() {
        $this->templateEngine = new TemplateEngine();
        $this->duplicateGuard = new DuplicateGuard();
        $this->scheduleResolver = new ScheduleResolver();
    }

    /**
     * Adds a single post to the queue.
     *
     * @param int $productId
     * @param string $platform
     * @param int $accountId
     * @param int|null $scheduleId
     * @param int|null $templateId
     * @return int
     */
    public function addToQueue(int $productId, string $platform, int $accountId, ?int $scheduleId, ?int $templateId): int {
        $db = DbConnection::getInstance();
        
        if (!$this->duplicateGuard->canPost($productId, $platform, $accountId)) {
            throw new \Exception("Duplicate guard prevented adding post to queue.");
        }

        $scheduledAt = null;
        if ($scheduleId) {
            $stmt = $db->prepare("SELECT * FROM sm_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($schedule) {
                $nextTime = $this->scheduleResolver->getNextPostTime($schedule);
                if ($nextTime) {
                    $scheduledAt = $nextTime->format('Y-m-d H:i:s');
                }
            }
        }

        if (!$scheduledAt) {
            $scheduledAt = date('Y-m-d H:i:s'); // Post now
        }

        $stmt = $db->prepare("INSERT INTO sm_queue (product_id, platform, account_id, schedule_id, template_id, scheduled_at, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
        $stmt->execute([$productId, $platform, $accountId, $scheduleId, $templateId, $scheduledAt]);
        
        return (int)$db->lastInsertId();
    }

    /**
     * Bulk schedules products.
     *
     * @param string $filterType
     * @param mixed $filterValue
     * @param int $scheduleId
     * @param int $templateId
     * @param array $platformAccountIds
     * @return array
     */
    public function bulkSchedule(string $filterType, $filterValue, int $scheduleId, int $templateId, array $platformAccountIds): array {
        // Implementation for bulk scheduling based on filters
        // For Phase 1, just returning an empty result skeleton
        return ['status' => 'queued', 'count' => 0];
    }

    /**
     * Event hook when a new product is created.
     *
     * @param int $productId
     * @return void
     */
    public function onProductCreated(int $productId): void {
        $autoQueue = $this->getSettings('auto_queue_new_products');
        if ($autoQueue !== '1') {
            return;
        }

        $defaultTemplate = (int)$this->getSettings('default_template_id');
        $accounts = $this->getConnectedAccounts(true);

        foreach ($accounts as $acc) {
            try {
                $this->addToQueue($productId, $acc['platform'], (int)$acc['id'], null, $defaultTemplate);
            } catch (\Exception $e) {
                // Log and continue
                $this->logError("Failed auto-queue for product $productId on {$acc['platform']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Gets queue statistics.
     *
     * @return array
     */
    public function getQueueStats(): array {
        $db = DbConnection::getInstance();
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM sm_queue GROUP BY status");
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }
        return $stats;
    }

    /**
     * Gets a setting value.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getSettings(string $key = null) {
        $db = DbConnection::getInstance();
        if ($key) {
            $stmt = $db->prepare("SELECT setting_value FROM sm_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn();
        } else {
            $stmt = $db->query("SELECT setting_key, setting_value FROM sm_settings");
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }

    /**
     * Updates a setting value.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function updateSetting(string $key, string $value): void {
        $db = DbConnection::getInstance();
        $stmt = $db->prepare("INSERT INTO sm_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }

    /**
     * Gets connected accounts.
     *
     * @param bool $activeOnly
     * @return array
     */
    public function getConnectedAccounts(bool $activeOnly = true): array {
        $db = DbConnection::getInstance();
        $sql = "SELECT * FROM sm_connected_accounts";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function logError(string $message): void {
        $db = DbConnection::getInstance();
        $stmt = $db->prepare("INSERT INTO sm_logs (level, message, category) VALUES ('error', ?, 'service')");
        $stmt->execute([$message]);
    }
}
