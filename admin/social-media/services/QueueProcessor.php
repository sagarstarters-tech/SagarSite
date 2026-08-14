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
    /**
     * Processes a batch of pending/scheduled posts where scheduled_at <= NOW().
     * Automatically filters and expires old stale backlog posts (>30m ago) so they never auto-fire unexpectedly.
     *
     * @param int $batchSize
     * @param bool $allowStale
     * @return array
     */
    public function processBatch(int $batchSize = 10, bool $allowStale = false): array {
        $db = DbConnection::getInstance();
        $now = date('Y-m-d H:i:s');
        $isCli = (php_sapi_name() === 'cli');
        
        // 1. Anti-Backlog Guard: Expire old stale backlog posts (>30 mins in the past)
        // Prevents past failed/pending items from dumping onto social media automatically
        if (!$allowStale) {
            $staleThreshold = date('Y-m-d H:i:s', strtotime('-30 minutes'));
            $db->prepare("UPDATE sm_queue 
                SET status = 'failed', 
                    last_error = 'Auto-post skipped: Scheduled time expired (>30m ago). Click Post Now to publish manually.' 
                WHERE status IN ('scheduled', 'retry') 
                AND scheduled_at IS NOT NULL 
                AND scheduled_at < ?")
               ->execute([$staleThreshold]);
        }
        
        // 2. Fetch only real-time timely due posts
        $stmt = $db->prepare("SELECT * FROM sm_queue 
            WHERE (status IN ('scheduled', 'retry') OR (status = 'publishing' AND (updated_at <= NOW() - INTERVAL 2 MINUTE OR updated_at IS NULL))) 
            AND (scheduled_at <= ? OR scheduled_at IS NULL) 
            ORDER BY scheduled_at ASC, id ASC 
            LIMIT ?");
        $stmt->bindValue(1, $now);
        $stmt->bindValue(2, $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        
        $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        $pausedPlatforms = [];
        $processedCount = 0;
        
        foreach ($queueItems as $item) {
            $platform = strtolower(trim($item['platform']));
            
            // Skip if this platform hit Meta rate limit or auth failure in current batch run
            if (isset($pausedPlatforms[$platform])) {
                continue;
            }

            // Throttle: only sleep in CLI cron mode, never block web/AJAX threads
            if ($processedCount > 0 && $isCli) {
                sleep(2);
            }

            $isRateLimit = false;
            $isAuthError = false;
            $lastError = '';
            $success = $this->processPost($item, $isRateLimit, $isAuthError, $lastError);
            $processedCount++;

            $results[] = [
                'id' => $item['id'],
                'platform' => $item['platform'],
                'success' => $success,
                'is_rate_limit' => $isRateLimit,
                'is_auth_error' => $isAuthError
            ];

            if ($isAuthError) {
                $pausedPlatforms[$platform] = true;
                // Fast batch fail: mark all other scheduled items for this platform as failed in 1 instant query
                $db->prepare("UPDATE sm_queue SET status = 'failed', last_error = ? WHERE LOWER(platform) = ? AND status IN ('scheduled', 'retry')")
                   ->execute([$lastError, $platform]);
            } elseif ($isRateLimit) {
                $pausedPlatforms[$platform] = true;
                // Log platform pause
                $logStmt = $db->prepare("INSERT INTO sm_logs (level, message, queue_id, platform) VALUES ('warning', ?, ?, ?)");
                $logStmt->execute(["Rate limit hit on {$item['platform']}. Pausing further posts for this platform in current batch run.", $item['id'], $item['platform']]);
            }
        }
        
        return $results;
    }

    /**
     * Processes a single post using its platform adapter.
     *
     * @param array $queueItem
     * @param bool|null $outIsRateLimit Optional output reference parameter for rate limit status
     * @param bool|null $outIsAuthError Optional output reference parameter for auth error status
     * @param string|null $outLastError Optional output reference parameter for error message
     * @return bool
     */
    public function processPost(array $queueItem, ?bool &$outIsRateLimit = false, ?bool &$outIsAuthError = false, ?string &$outLastError = ''): bool {
        $outIsRateLimit = false;
        $outIsAuthError = false;
        $outLastError = '';
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
                'board_id' => $acc['page_id'] ?? $acc['account_id'] ?? '',
                'message' => $queueItem['post_content'] ?? '',
                'caption' => $queueItem['post_content'] ?? '',
                'title' => $queueItem['post_content'] ?? '',
                'image_url' => $fullImgUrl,
                'link' => $queueItem['post_link'] ?? '',
                'product_id' => $queueItem['product_id'] ?? 0
            ];

            $pubRes = $adapter->publishPost($postData);
            $outIsRateLimit = !empty($pubRes['is_rate_limit']) || $this->isRateLimitError($pubRes['error'] ?? '');

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
                $outLastError = $errorMsg;
                $outIsAuthError = $this->isAuthTokenError($errorMsg);
                $this->updateStatus($id, 'failed', $errorMsg);
                if (!$outIsAuthError) {
                    $this->retryPost($id, $outIsRateLimit);
                }
                return false;
            }
            
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $outLastError = $errorMsg;
            $outIsRateLimit = $this->isRateLimitError($errorMsg);
            $outIsAuthError = $this->isAuthTokenError($errorMsg);
            $this->updateStatus($id, 'failed', $errorMsg);
            if (!$outIsAuthError) {
                $this->retryPost($id, $outIsRateLimit);
            }
            
            // Log error
            $logStmt = $db->prepare("INSERT INTO sm_logs (level, message, queue_id, platform) VALUES ('error', ?, ?, ?)");
            $logStmt->execute([$errorMsg, $id, $queueItem['platform']]);
            
            return false;
        }
    }

    /**
     * Retries a failed post with exponential backoff.
     *
     * @param int $queueId
     * @param bool $isRateLimit
     * @return bool
     */
    public function retryPost(int $queueId, bool $isRateLimit = false): bool {
        $db = DbConnection::getInstance();
        $stmt = $db->prepare("SELECT * FROM sm_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) return false;
        
        $retries = (int)$item['retry_count'] + 1;
        $maxRetries = (int)$item['max_retries'];
        
        if ($retries > $maxRetries) {
            $this->updateStatus($queueId, 'failed', "Max retries exceeded: " . ($item['last_error'] ?? ''));
            return false;
        }
        
        // Exponential backoff: 30min, 60min, 120min, 240min if rate limited; otherwise 5min, 15min, 60min, 360min
        $delays = $isRateLimit ? [30, 60, 120, 240] : [5, 15, 60, 360];
        $delayMinutes = $delays[min($retries - 1, count($delays) - 1)];
        $nextAttempt = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
        
        $updateStmt = $db->prepare("UPDATE sm_queue SET status = 'retry', retry_count = ?, scheduled_at = ? WHERE id = ?");
        $updateStmt->execute([$retries, $nextAttempt, $queueId]);
        
        return true;
    }

    public function isAuthTokenError(?string $error): bool {
        if (empty($error)) return false;
        $errLower = strtolower($error);
        $authPhrases = [
            'error validating access token',
            'session has expired',
            'invalid access token',
            'the access token could not be decrypted',
            'token is expired',
            'invalid oauth access token',
            'user has not authorized application',
            'missing or invalid api access token',
            'no active connected account found',
            'session has been invalidated'
        ];
        foreach ($authPhrases as $phrase) {
            if (strpos($errLower, $phrase) !== false) {
                return true;
            }
        }
        return false;
    }

    public function isRateLimitError(?string $error): bool {
        if (empty($error)) return false;
        $errLower = strtolower($error);
        $phrases = [
            'limit how often',
            'too many actions',
            'action block',
            'rate limit',
            'please try again later',
            'protect the community from spam',
            'request limit reached',
            'throttle',
            'application_and_member_day',
            'limit for calls to this resource is reached'
        ];
        foreach ($phrases as $phrase) {
            if (strpos($errLower, $phrase) !== false) {
                return true;
            }
        }
        return false;
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
        
        if ($status === 'posted') {
            $sql .= ", last_error = NULL";
        } elseif ($error !== null) {
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
