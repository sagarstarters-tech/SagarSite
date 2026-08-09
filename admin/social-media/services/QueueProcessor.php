<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;

if (!interface_exists('PlatformAdapterInterface')) {
    require_once dirname(__DIR__) . '/adapters/PlatformAdapterInterface.php';
}

/**
 * Class QueueProcessor
 * Processes the social media post queue automatically.
 */
class QueueProcessor {

    /**
     * Processes a batch of pending/scheduled posts where scheduled_at <= NOW().
     *
     * @param int $batchSize
     * @return array
     */
    public function processBatch(int $batchSize = 10): array {
        $db = DbConnection::getInstance();
        $now = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("SELECT * FROM sm_queue 
            WHERE (status IN ('scheduled', 'pending', 'retry') OR (status = 'publishing' AND (updated_at <= NOW() - INTERVAL 2 MINUTE OR updated_at IS NULL))) 
            AND (scheduled_at <= ? OR scheduled_at IS NULL) 
            ORDER BY scheduled_at ASC 
            LIMIT ?");
        $stmt->bindValue(1, $now);
        $stmt->bindValue(2, $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        
        $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        
        foreach ($queueItems as $item) {
            $results[] = [
                'id' => $item['id'],
                'platform' => $item['platform'],
                'success' => $this->processPost($item)
            ];
        }
        
        return $results;
    }

    /**
     * Processes a single post using its platform adapter.
     *
     * @param array $queueItem
     * @return bool
     */
    public function processPost(array $queueItem): bool {
        $db = DbConnection::getInstance();
        $id = (int)$queueItem['id'];
        
        try {
            $this->updateStatus($id, 'publishing');
            
            $platform = strtolower(trim($queueItem['platform']));
            
            // Fetch connected account details
            $stmtAcc = $db->prepare("SELECT * FROM sm_connected_accounts WHERE id = ? OR (LOWER(platform) = ? AND is_active = 1) LIMIT 1");
            $stmtAcc->execute([(int)$queueItem['account_id'], $platform]);
            $acc = $stmtAcc->fetch(PDO::FETCH_ASSOC);

            if (!$acc) {
                throw new \Exception("No active connected account found for platform $platform.");
            }

            require_once __DIR__ . '/TokenEncryption.php';
            $plainToken = TokenEncryption::decrypt($acc['access_token_encrypted'] ?? '');

            if (!$plainToken && $platform !== 'telegram') {
                throw new \Exception("Missing or invalid API Access Token for platform $platform.");
            }

            $adapter = $this->getAdapterForPlatform($platform);

            $rawImg = trim($queueItem['post_image_url'] ?? '');
            
            // Fallback: If post_image_url is empty but product_id is set, fetch product's image from products table
            if (empty($rawImg) && !empty($queueItem['product_id'])) {
                $stmtProdImg = $db->prepare("SELECT image FROM products WHERE id = ?");
                $stmtProdImg->execute([(int)$queueItem['product_id']]);
                $rawImg = trim((string)$stmtProdImg->fetchColumn());
            }

            $siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
            $fullImgUrl = '';
            if (function_exists('resolve_product_image_url')) {
                $fullImgUrl = resolve_product_image_url($rawImg, null, (int)($queueItem['product_id'] ?? 0));
            }
            
            if (empty($fullImgUrl) && !empty($rawImg)) {
                $rawImg = str_replace('\\', '/', $rawImg);
                if (strpos($rawImg, 'http://') === 0 || strpos($rawImg, 'https://') === 0) {
                    $fullImgUrl = $rawImg;
                } else {
                    $cleanImg = ltrim($rawImg, '/');
                    if (strpos($cleanImg, 'uploads/') === 0 || strpos($cleanImg, 'assets/') === 0) {
                        $fullImgUrl = $siteUrl . '/' . $cleanImg;
                    } else {
                        $fullImgUrl = $siteUrl . '/uploads/images/' . $cleanImg;
                    }
                }
            }

            if (!empty($fullImgUrl)) {
                $fullImgUrl = str_replace('\\', '/', $fullImgUrl);
                // Encode spaces in URL path so Meta Graph API can download without HTTP 400
                $urlParts = parse_url($fullImgUrl);
                if (!empty($urlParts['scheme']) && !empty($urlParts['host']) && !empty($urlParts['path'])) {
                    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $urlParts['path'])));
                    $port = !empty($urlParts['port']) ? ':' . $urlParts['port'] : '';
                    $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
                    $fullImgUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $port . $encodedPath . $query;
                } else {
                    $fullImgUrl = str_replace(' ', '%20', $fullImgUrl);
                }
            }

            $postData = [
                'ig_user_id' => $acc['account_id'] ?? $acc['page_id'] ?? '',
                'page_id' => $acc['page_id'] ?? $acc['account_id'] ?? '',
                'account_id' => $acc['account_id'] ?? '',
                'person_urn' => $acc['account_id'] ?? '',
                'access_token' => $plainToken,
                'bot_token' => $plainToken,
                'channel_id' => $acc['page_id'] ?? $acc['account_id'] ?? '',
                'message' => $queueItem['post_content'] ?? '',
                'caption' => $queueItem['post_content'] ?? '',
                'image_url' => $fullImgUrl,
                'link' => $queueItem['post_link'] ?? ''
            ];

            $pubRes = $adapter->publishPost($postData);

            if (!empty($pubRes['success'])) {
                $platformPostId = (string)(!empty($pubRes['post_url']) ? $pubRes['post_url'] : ($pubRes['post_id'] ?? ('post_' . time() . '_' . $id)));
                $this->updateStatus($id, 'posted', null, $platformPostId);
                
                // Update analytics
                $date = date('Y-m-d');
                $stmt = $db->prepare("INSERT INTO sm_analytics (platform, account_id, date, posts_published) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE posts_published = posts_published + 1");
                $stmt->execute([$queueItem['platform'], $queueItem['account_id'], $date]);
                
                return true;
            } else {
                $errorMsg = $pubRes['error'] ?? 'Platform adapter reported failure';
                $this->updateStatus($id, 'failed', $errorMsg);
                $this->retryPost($id);
                return false;
            }
            
        } catch (\Throwable $e) {
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
        $db = DbConnection::getInstance();
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
        $db = DbConnection::getInstance();
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
    public function getAdapterForPlatform(string $platform): \PlatformAdapterInterface {
        $platform = strtolower(trim($platform));
        $map = [
            'facebook'  => 'FacebookAdapter',
            'instagram' => 'InstagramAdapter',
            'twitter'   => 'TwitterAdapter',
            'linkedin'  => 'LinkedInAdapter',
            'telegram'  => 'TelegramAdapter',
            'pinterest' => 'PinterestAdapter'
        ];
        $className = $map[$platform] ?? (ucfirst($platform) . 'Adapter');

        $adapterFile = dirname(__DIR__) . '/adapters/' . $className . '.php';
        if (file_exists($adapterFile)) {
            require_once $adapterFile;
            if (class_exists($className)) {
                return new $className();
            }
        }
        throw new \Exception("Adapter for platform $platform not found.");
    }
}
