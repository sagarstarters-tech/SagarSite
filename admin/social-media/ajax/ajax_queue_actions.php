<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ob_start();
header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/csrf.php';
require_once BASE_PATH . '/config/DbConnection.php';

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (!isset($_GET['action']) || $_GET['action'] !== 'get_preview')) {
        throw new Exception('Invalid request method');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['_csrf_token'] ?? '';
        $storedToken = $_SESSION['csrf_token'] ?? '';
        if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
            throw new Exception('Security token mismatch (CSRF). Please refresh the page.');
        }
        $action = $_POST['action'] ?? '';
    } else {
        $action = $_GET['action'] ?? '';
    }

    $response = ['success' => false, 'data' => null, 'error' => null];

    switch ($action) {
        case 'approve':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'scheduled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'pause':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'pending' WHERE id = ? AND status = 'scheduled'");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'cancel':
        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("DELETE FROM sm_queue WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'retry':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'scheduled', retry_count = 0, last_error = NULL, scheduled_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'post_now':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            
            require_once BASE_PATH . '/admin/social-media/services/QueueProcessor.php';
            $processor = new \Admin\SocialMedia\Services\QueueProcessor();
            
            // Fetch queue item
            $stmtItem = $pdo->prepare("SELECT * FROM sm_queue WHERE id = ?");
            $stmtItem->execute([$id]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception('Queue item not found');

            $isRateLimit = false;
            $success = $processor->processPost($item, $isRateLimit);

            if ($success) {
                $response['success'] = true;
                $response['message'] = "Post published to " . ucfirst($item['platform']) . " successfully!";
            } else {
                $stmtErr = $pdo->prepare("SELECT last_error FROM sm_queue WHERE id = ?");
                $stmtErr->execute([$id]);
                $errRow = $stmtErr->fetch(PDO::FETCH_ASSOC);
                $errMsg = $errRow['last_error'] ?? 'Publishing failed';
                if (!empty($isRateLimit) || $processor->isRateLimitError($errMsg)) {
                    throw new Exception("Meta Action Block Active: Facebook has temporarily restricted API posting on this Page (Meta blocks usually last 24 hours).\n\n💡 QUICK UNBLOCK TIP: Open facebook.com in your browser, go to your Page 'Sagar starter\'s', and publish 1 post manually to clear Facebook\'s security check.");
                }
                throw new Exception("Failed to publish to " . ucfirst($item['platform']) . ": " . $errMsg);
            }
            break;
        case 'bulk_post_now':
        case 'bulk_approve':
        case 'bulk_cancel':
        case 'bulk_delete':
        case 'bulk_retry':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('No queue items selected');
            }
            $cleanIds = array_map('intval', array_filter($ids));
            if (empty($cleanIds)) throw new Exception('Invalid selection');

            $idList = implode(',', $cleanIds);

            if ($action === 'bulk_delete') {
                $pdo->query("DELETE FROM sm_queue WHERE id IN ($idList)");
                $response['message'] = 'Selected items deleted.';
            } elseif ($action === 'bulk_approve') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled' WHERE id IN ($idList) AND status IN ('pending','failed')");
                $response['message'] = 'Selected items approved and scheduled.';
            } elseif ($action === 'bulk_cancel') {
                // Cancel moves scheduled back to pending
                $pdo->query("UPDATE sm_queue SET status = 'pending' WHERE id IN ($idList) AND status IN ('scheduled','publishing')");
                $response['message'] = 'Selected items moved back to pending.';
            } elseif ($action === 'bulk_retry') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled', retry_count = 0, last_error = NULL, scheduled_at = NOW() WHERE id IN ($idList)");
                $response['message'] = 'Selected failed items queued for retry.';
            } elseif ($action === 'bulk_post_now') {
                // Post each selected item immediately with throttling
                require_once BASE_PATH . '/admin/social-media/services/QueueProcessor.php';
                $processor = new \Admin\SocialMedia\Services\QueueProcessor();
                $okCount = 0;
                $failCount = 0;
                foreach ($cleanIds as $idx => $itemId) {
                    if ($idx > 0) {
                        sleep(3);
                    }
                    $stmtItem = $pdo->prepare("SELECT * FROM sm_queue WHERE id = ?");
                    $stmtItem->execute([$itemId]);
                    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
                    if ($item && $processor->processPost($item)) {
                        $okCount++;
                    } else {
                        $failCount++;
                    }
                }
                $response['message'] = "Posted {$okCount} item(s) successfully" . ($failCount > 0 ? ", {$failCount} failed." : '.');
            }
            $response['success'] = true;
            break;
        case 'delete_all':
            $status = trim($_POST['status'] ?? 'all');
            if ($status !== 'all' && !empty($status)) {
                $stmt = $pdo->prepare("DELETE FROM sm_queue WHERE status = ?");
                $stmt->execute([$status]);
                $deletedCount = $stmt->rowCount();
                $response['message'] = "All '{$status}' posts ({$deletedCount}) permanently deleted from database.";
            } else {
                $pdo->query("DELETE FROM sm_queue");
                try {
                    $pdo->query("ALTER TABLE sm_queue AUTO_INCREMENT = 1");
                } catch (\Throwable $e) {}
                $response['message'] = "All queue posts completely and permanently deleted from database.";
            }
            $response['success'] = true;
            break;
        case 'get_preview':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            $stmtPrev = $pdo->prepare("SELECT post_content, post_image_url, post_link, platform FROM sm_queue WHERE id = ?");
            $stmtPrev->execute([$id]);
            $prevItem = $stmtPrev->fetch(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['data'] = $prevItem ?: ['rendered' => "No content for post ID: $id"];
            break;
        case 'reset_stuck_publishing':
            // Reset posts stuck in 'publishing' state for more than 5 minutes
            $pdo->query("UPDATE sm_queue SET status = 'scheduled', retry_count = 0 WHERE status = 'publishing' AND updated_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
            $response['success'] = true;
            $response['message'] = "Reset {$affected} stuck publishing item(s) to scheduled.";
            break;
        case 'stop_posting':
            // 1. Pause all active schedules
            $schedPaused = $pdo->query("UPDATE sm_schedules SET is_active = 0 WHERE is_active = 1");
            $schedCount = (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();

            // 2. Cancel all scheduled/pending/retry queue items
            $pdo->query("UPDATE sm_queue SET status = 'failed', last_error = 'Posting stopped by user' WHERE status IN ('scheduled', 'pending', 'retry')");
            $queueCount = (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();

            $response['success'] = true;
            $response['message'] = "Posting stopped! {$schedCount} schedule(s) paused, {$queueCount} queued post(s) cancelled.";
            break;
        case 'start_posting':
            // 1. Re-activate all paused schedules
            $pdo->query("UPDATE sm_schedules SET is_active = 1 WHERE is_active = 0");
            $schedCount = (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();

            // 2. Re-schedule items that were stopped by user
            $pdo->query("UPDATE sm_queue SET status = 'scheduled', last_error = NULL WHERE status = 'failed' AND last_error = 'Posting stopped by user'");
            $queueCount = (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();

            $response['success'] = true;
            $response['message'] = "Posting started! {$schedCount} schedule(s) activated, {$queueCount} post(s) re-scheduled.";
            break;
        default:
            throw new Exception('Invalid action');
    }

    if (ob_get_length()) ob_clean();
    echo json_encode($response);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
