<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;

/**
 * Class DuplicateGuard
 * Protects against duplicate social media posts based on repost rules.
 */
class DuplicateGuard {

    /**
     * Checks if a product can be posted to a specific platform account.
     *
     * @param int $productId
     * @param string $platform
     * @param int $accountId
     * @return bool
     */
    public function canPost(int $productId, string $platform, int $accountId): bool {
        $db = DbConnection::getInstance()->getConnection();
        
        // Fetch repost rules
        $stmt = $db->prepare("SELECT is_enabled, repost_interval_days, max_reposts FROM sm_repost_rules WHERE (platform = ? AND account_id = ?) OR (platform IS NULL AND account_id IS NULL) ORDER BY platform DESC LIMIT 1");
        $stmt->execute([$platform, $accountId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule || empty($rule['is_enabled'])) {
            return true; // No rules preventing it
        }

        $hash = $this->generateHash($productId, $platform, $accountId);

        // Check history
        $historyStmt = $db->prepare("SELECT COUNT(*) as total_posts, MAX(posted_at) as last_posted FROM sm_post_history WHERE product_id = ? AND platform = ? AND account_id = ?");
        $historyStmt->execute([$productId, $platform, $accountId]);
        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);

        if ($history['total_posts'] == 0) {
            return true;
        }

        if ($history['total_posts'] >= $rule['max_reposts']) {
            return false;
        }

        $lastPostedTime = strtotime($history['last_posted']);
        $currentTime = time();
        $daysSinceLastPost = ($currentTime - $lastPostedTime) / (60 * 60 * 24);

        if ($daysSinceLastPost < $rule['repost_interval_days']) {
            return false;
        }

        return true;
    }

    /**
     * Records a successful post to history.
     *
     * @param int $productId
     * @param string $platform
     * @param int $accountId
     * @param int $queueId
     * @return void
     */
    public function recordPost(int $productId, string $platform, int $accountId, int $queueId): void {
        $db = DbConnection::getInstance()->getConnection();
        $hash = $this->generateHash($productId, $platform, $accountId);
        $postedAt = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("INSERT INTO sm_post_history (product_id, platform, account_id, posted_at, post_hash, queue_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$productId, $platform, $accountId, $postedAt, $hash, $queueId]);
    }

    /**
     * Generates a unique hash for a post.
     *
     * @param int $productId
     * @param string $platform
     * @param int $accountId
     * @return string
     */
    public function generateHash(int $productId, string $platform, int $accountId): string {
        return hash('sha256', $productId . '_' . $platform . '_' . $accountId);
    }

    /**
     * Gets the repost interval in days for a given platform/account.
     *
     * @param string $platform
     * @param int $accountId
     * @return int
     */
    public function getRepostInterval(string $platform, int $accountId): int {
        $db = DbConnection::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT repost_interval_days FROM sm_repost_rules WHERE platform = ? AND account_id = ? LIMIT 1");
        $stmt->execute([$platform, $accountId]);
        $interval = $stmt->fetchColumn();

        if ($interval !== false) {
            return (int)$interval;
        }

        // Check default settings
        $stmtSettings = $db->prepare("SELECT setting_value FROM sm_settings WHERE setting_key = 'default_repost_interval' LIMIT 1");
        $stmtSettings->execute();
        $defaultInterval = $stmtSettings->fetchColumn();
        
        return $defaultInterval !== false ? (int)$defaultInterval : 30;
    }
}
