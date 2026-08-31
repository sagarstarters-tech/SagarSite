<?php
declare(strict_types=1);

namespace CourierModule\Services;

use DbConnection;
use PDO;
use Throwable;

/**
 * Class CourierQueueService
 * Handles asynchronous background queuing, concurrency locking,
 * exponential retry backoff, and batch dispatching for courier shipments.
 */
class CourierQueueService
{
    private PDO $pdo;
    private CourierManager $manager;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            require_once dirname(__DIR__, 2) . '/config/DbConnection.php';
            $this->pdo = DbConnection::getInstance();
        } else {
            $this->pdo = $pdo;
        }

        require_once __DIR__ . '/CourierManager.php';
        $this->manager = new CourierManager($this->pdo);
    }

    /**
     * Non-blocking: Enqueue an order for background courier sync.
     * Safe to call anywhere in checkout or payment callbacks.
     */
    public function enqueueOrder(int $orderId, ?int $integrationId = null): bool
    {
        try {
            // If integration not provided, resolve active default
            if ($integrationId === null) {
                $stmt = $this->pdo->query("SELECT id FROM courier_integrations WHERE is_enabled = 1 AND is_default = 1 LIMIT 1");
                $integrationId = (int)($stmt->fetchColumn() ?: 1);
            }

            // Check if already queued or completed
            $checkStmt = $this->pdo->prepare("
                SELECT id, status FROM courier_queue 
                WHERE order_id = ? AND status IN ('pending', 'processing', 'completed')
                LIMIT 1
            ");
            $checkStmt->execute([$orderId]);
            if ($checkStmt->fetch()) {
                return true; // Already safely in queue or done
            }

            $insStmt = $this->pdo->prepare("
                INSERT INTO courier_queue (order_id, integration_id, action, status, attempts, next_attempt_at)
                VALUES (?, ?, 'create_shipment', 'pending', 0, NOW())
            ");
            return $insStmt->execute([$orderId, $integrationId]);
        } catch (Throwable $e) {
            error_log('[CourierQueue] Failed to enqueue order #' . $orderId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process batch of pending queue items (called by cron)
     */
    public function processBatch(int $limit = 10): array
    {
        $summary = [
            'total_picked' => 0,
            'succeeded'    => 0,
            'failed'       => 0,
            'permanent'    => 0,
            'details'      => []
        ];

        try {
            // 1. Fetch pending/failed items ready for execution
            $stmt = $this->pdo->prepare("
                SELECT q.*, i.provider_code 
                FROM courier_queue q
                JOIN courier_integrations i ON q.integration_id = i.id
                WHERE q.status IN ('pending', 'failed')
                  AND q.next_attempt_at <= NOW()
                  AND (q.locked_at IS NULL OR q.locked_at < NOW() - INTERVAL 10 MINUTE)
                  AND i.is_enabled = 1
                ORDER BY q.id ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();

            $summary['total_picked'] = count($items);

            foreach ($items as $item) {
                $queueId = (int)$item['id'];
                $orderId = (int)$item['order_id'];
                $attempts = (int)$item['attempts'] + 1;
                $maxAttempts = (int)$item['max_attempts'];

                // 2. Lock item
                $lockStmt = $this->pdo->prepare("UPDATE courier_queue SET status = 'processing', locked_at = NOW(), attempts = ? WHERE id = ?");
                $lockStmt->execute([$attempts, $queueId]);

                // 3. Execute courier push
                $result = $this->manager->pushOrderToCourier($orderId, $item['provider_code']);

                if ($result['success']) {
                    // Success!
                    $doneStmt = $this->pdo->prepare("
                        UPDATE courier_queue 
                        SET status = 'completed', locked_at = NULL, last_error_message = NULL, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $doneStmt->execute([$queueId]);

                    $summary['succeeded']++;
                    $summary['details'][] = ['order_id' => $orderId, 'status' => 'success', 'awb' => $result['awb_number'] ?? ''];
                } else {
                    // Failed
                    $errorMsg = (string)($result['message'] ?? 'Unknown courier API failure');

                    if ($attempts >= $maxAttempts) {
                        // Max retries reached -> Mark permanently failed
                        $failStmt = $this->pdo->prepare("
                            UPDATE courier_queue 
                            SET status = 'failed_permanent', locked_at = NULL, last_error_message = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $failStmt->execute([$errorMsg, $queueId]);
                        $summary['permanent']++;
                    } else {
                        // Calculate exponential backoff: attempt 1 -> +5m, attempt 2 -> +15m, attempt 3 -> +60m
                        $delays = [1 => 5, 2 => 15, 3 => 60];
                        $delayMinutes = $delays[$attempts] ?? 60;

                        $retryStmt = $this->pdo->prepare("
                            UPDATE courier_queue 
                            SET status = 'failed', locked_at = NULL, last_error_message = ?, next_attempt_at = NOW() + INTERVAL ? MINUTE, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $retryStmt->execute([$errorMsg, $delayMinutes, $queueId]);
                        $summary['failed']++;
                    }

                    $summary['details'][] = ['order_id' => $orderId, 'status' => 'failed', 'error' => $errorMsg, 'attempt' => $attempts];
                }
            }
        } catch (Throwable $e) {
            error_log('[CourierQueue] Batch processing error: ' . $e->getMessage());
        }

        return $summary;
    }
}
