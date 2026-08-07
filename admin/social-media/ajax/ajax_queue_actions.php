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
        csrf_verify();
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
            $stmt = $pdo->prepare("UPDATE sm_queue SET status = 'scheduled', scheduled_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            break;
        case 'bulk_approve':
        case 'bulk_cancel':
        case 'bulk_delete':
        case 'bulk_retry':
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) throw new Exception('No IDs provided');
            $idList = implode(',', array_map('intval', $ids));
            if ($action === 'bulk_approve') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled' WHERE id IN ($idList) AND status = 'pending'");
            } elseif ($action === 'bulk_cancel' || $action === 'bulk_delete') {
                $pdo->query("DELETE FROM sm_queue WHERE id IN ($idList)");
            } elseif ($action === 'bulk_retry') {
                $pdo->query("UPDATE sm_queue SET status = 'scheduled', retry_count = 0 WHERE id IN ($idList)");
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
