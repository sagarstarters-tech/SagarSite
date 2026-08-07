<?php
declare(strict_types=1);
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
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'scheduled', retry_count = 0 WHERE id = ?");
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

            $success = $processor->processPost($item);

            if ($success) {
                $response['success'] = true;
                $response['message'] = "Post published to " . ucfirst($item['platform']) . " successfully!";
            } else {
                $stmtErr = $pdo->prepare("SELECT last_error FROM sm_queue WHERE id = ?");
                $stmtErr->execute([$id]);
                $errRow = $stmtErr->fetch(PDO::FETCH_ASSOC);
                $errMsg = $errRow['last_error'] ?? 'Publishing failed';
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
            } elseif ($action === 'bulk_approve') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled' WHERE id IN ($idList) AND status = 'pending'");
            } elseif ($action === 'bulk_cancel') {
                $pdo->query("UPDATE sm_queue SET status = 'pending' WHERE id IN ($idList) AND status = 'scheduled'");
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

    if (ob_get_length()) ob_clean();
    echo json_encode($response);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
