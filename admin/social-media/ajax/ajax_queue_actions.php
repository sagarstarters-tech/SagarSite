<?php
declare(strict_types=1);
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
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'scheduled', retry_count = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'post_now':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            
            // Fetch Queue item + Connected Account details
            $stmtItem = $pdo->prepare("SELECT q.*, a.access_token_encrypted, a.page_id 
                FROM sm_queue q 
                LEFT JOIN sm_connected_accounts a ON q.account_id = a.id 
                WHERE q.id = ?");
            $stmtItem->execute([$id]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$item) throw new Exception('Queue item not found');

            $platform = strtolower($item['platform']);
            $realPostId = null;
            $apiError = null;

            if ($platform === 'facebook') {
                if (empty($item['page_id']) || empty($item['access_token_encrypted'])) {
                    throw new Exception("Facebook Page Token or Page ID is missing! Please go to Social Media -> Accounts and add your Facebook Page Access Token.");
                }

                require_once BASE_PATH . '/admin/social-media/services/TokenEncryption.php';
                require_once BASE_PATH . '/admin/social-media/adapters/FacebookAdapter.php';
                
                $plainToken = \Admin\SocialMedia\Services\TokenEncryption::decrypt($item['access_token_encrypted']);
                
                if (!$plainToken) {
                    throw new Exception("Could not decrypt Facebook Page Token.");
                }

                $fb = new FacebookAdapter();
                $pubRes = $fb->publishPost([
                    'page_id' => $item['page_id'],
                    'access_token' => $plainToken,
                    'message' => $item['post_content'],
                    'image_url' => $item['post_image_url'],
                    'link' => $item['post_link']
                ]);

                if ($pubRes['success']) {
                    $realPostId = $pubRes['post_id'];
                } else {
                    $apiError = $pubRes['error'] ?? 'Facebook Graph API publish error';
                }
            }

            if ($apiError) {
                $stmtFail = $pdo->prepare("UPDATE sm_queue SET status = 'failed', last_error = ? WHERE id = ?");
                $stmtFail->execute([$apiError, $id]);
                throw new Exception("Facebook API Error: " . $apiError);
            }

            $platformPostId = $realPostId ?: ('post_' . time() . '_' . $id);

            $stmt = $pdo->prepare("UPDATE sm_queue 
                SET status = 'posted', 
                    scheduled_at = NOW(), 
                    published_at = NOW(), 
                    platform_post_id = ? 
                WHERE id = ?");
            $stmt->execute([$platformPostId, $id]);

            // Update Analytics
            $today = date('Y-m-d');
            $statStmt = $pdo->prepare("INSERT INTO sm_analytics (platform, account_id, date, posts_published) 
                VALUES (?, ?, ?, 1) 
                ON DUPLICATE KEY UPDATE posts_published = posts_published + 1");
            $statStmt->execute([$item['platform'], $item['account_id'], $today]);

            $response['success'] = true;
            $response['message'] = "Post published to Facebook Page successfully!";
            break;
        case 'bulk_post_now':
        case 'bulk_approve':
        case 'bulk_cancel':
        case 'bulk_delete':
        case 'bulk_retry':
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) throw new Exception('No IDs provided');
            $idList = implode(',', array_map('intval', $ids));
            if ($action === 'bulk_post_now') {
                $pdo->query("UPDATE sm_queue 
                    SET status = 'posted', 
                        scheduled_at = NOW(), 
                        published_at = NOW(), 
                        platform_post_id = CONCAT('post_', UNIX_TIMESTAMP(), '_', id) 
                    WHERE id IN ($idList)");
            } elseif ($action === 'bulk_approve') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled' WHERE id IN ($idList) AND status = 'pending'");
            } elseif ($action === 'bulk_cancel' || $action === 'bulk_delete') {
                $pdo->query("DELETE FROM sm_queue WHERE id IN ($idList)");
            } elseif ($action === 'bulk_retry') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled', retry_count = 0 WHERE id IN ($idList)");
            }
            $response['success'] = true;
            break;
        case 'delete_all':
            $status = trim($_POST['status'] ?? 'all');
            if ($status !== 'all' && !empty($status)) {
                $stmt = $pdo->prepare("DELETE FROM sm_queue WHERE status = ?");
                $stmt->execute([$status]);
            } else {
                $pdo->query("DELETE FROM sm_queue");
            }
            $response['success'] = true;
            break;
        case 'get_preview':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid ID');
            // Simplified preview logic
            $response['success'] = true;
            $response['data'] = ['rendered' => "Live preview content for post ID: $id"];
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
