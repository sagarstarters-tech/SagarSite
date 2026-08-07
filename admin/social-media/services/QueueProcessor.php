<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;

/**
 * Interface PlatformAdapterInterface
 */
if (!interface_exists('Admin\SocialMedia\Adapters\PlatformAdapterInterface')) {
    require_once dirname(__DIR__) . '/adapters/PlatformAdapterInterface.php';
}
use Admin\SocialMedia\Adapters\PlatformAdapterInterface;

/**
 * Class QueueProcessor
 * Processes the social media post queue.
 */
class QueueProcessor {

    /**
     * Processes a batch of pending/scheduled posts.
     *
     * @param int $batchSize
     * @return array
     */
    public function processBatch(int $batchSize = 10): array {
        $db = DbConnection::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("SELECT * FROM sm_queue WHERE status IN ('scheduled', 'retry') AND scheduled_at <= ? LIMIT ?");
        $stmt->bindValue(1, $now);
        $stmt->bindValue(2, $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        
        $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        
        foreach ($queueItems as $item) {
            $results[] = [
                'id' => $item['id'],
                'success' => $this->processPost($item)
            ];
        }
        
        return $results;
    }

    /**
     * Processes a single post.
     *
     * @param array $queueItem
     * @return bool
     */
    public function processPost(array $queueItem): bool {
        $db = DbConnection::getInstance()->getConnection();
        $id = (int)$queueItem['id'];
        
        try {
            $this->updateStatus($id, 'publishing');
            
            $adapter = $this->getAdapterForPlatform($queueItem['platform']);
            
            // Post logic using adapter goes here
            // Mocking success
            $success = true; 
            $platformPostId = "mock_" . time();
            
            if ($success) {
                $this->updateStatus($id, 'posted', null, $platformPostId);
                
                // Update analytics
                $date = date('Y-m-d');
                $stmt = $db->prepare("INSERT INTO sm_analytics (platform, account_id, date, posts_published) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE posts_published = posts_published + 1");
                $stmt->execute([$queueItem['platform'], $queueItem['account_id'], $date]);
                
                return true;
            } else {
                $this->updateStatus($id, 'failed', "Adapter reported failure");
                $this->retryPost($id);
                return false;
            }
            
        } catch (\Exception $e) {
            $this->updateStatus($id, 'failed', $e->getMessage());
            $this->retryPost($id);
            
            // Log error
            $logStmt = $db->prepare("INSERT INTO sm_logs (level, message, queue_id, platform) VALUES ('error', ?, ?, ?)");
            $logStmt->execute([$e->getMessage(), $id, $queueItem['platform']]);
            
            return false;
        }
    }

    /**
     * Retries a failed post with exponential backoff.
     *
     * @param int $queueId
     * @return bool
     */
    public function retryPost(int $queueId): bool {
        $db = DbConnection::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM sm_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) return false;
        
        $retries = (int)$item['retry_count'] + 1;
        $maxRetries = (int)$item['max_retries'];
        
        if ($retries > $maxRetries) {
            $this->updateStatus($queueId, 'failed', "Max retries exceeded");
            return false;
        }
        
        // Exponential backoff: 5min, 15min, 1hr, 6hr
        $delays = [5, 15, 60, 360];
        $delayMinutes = $delays[min($retries - 1, count($delays) - 1)];
        $nextAttempt = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
        
        $updateStmt = $db->prepare("UPDATE sm_queue SET status = 'retry', retry_count = ?, scheduled_at = ? WHERE id = ?");
        $updateStmt->execute([$retries, $nextAttempt, $queueId]);
        
        return true;
    }

    /**
     * Updates queue item status.
     *
     * @param int $queueId
     * @param string $status
     * @param string|null $error
     * @param string|null $platformPostId
     * @return void
     */
    public function updateStatus(int $queueId, string $status, ?string $error = null, ?string $platformPostId = null): void {
        $db = DbConnection::getInstance()->getConnection();
        $publishedAt = $status === 'posted' ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE sm_queue SET status = ?";
        $params = [$status];
        
        if ($error !== null) {
            $sql .= ", last_error = ?";
            $params[] = $error;
        }
        if ($platformPostId !== null) {
            $sql .= ", platform_post_id = ?";
            $params[] = $platformPostId;
        }
        if ($publishedAt !== null) {
            $sql .= ", published_at = ?";
            $params[] = $publishedAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $queueId;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Factory method for adapters.
     *
     * @param string $platform
     * @return PlatformAdapterInterface
     * @throws \Exception
     */
    public function getAdapterForPlatform(string $platform): PlatformAdapterInterface {
        // Since we don't have the real adapters yet, we throw exception or return a mock.
        // For Phase 1 we will define a mock or just throw if not implemented.
        throw new \Exception("Adapter for platform $platform not found.");
    }
}
