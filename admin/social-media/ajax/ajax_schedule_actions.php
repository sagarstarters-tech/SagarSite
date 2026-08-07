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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $submittedToken = $_POST['_csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
        throw new Exception('Security token mismatch (CSRF). Please refresh the page.');
    }

    $action = trim($_POST['action'] ?? '');
    $userId = $_SESSION['user_id'] ?? 1;

    switch ($action) {
        case 'save':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $scheduleType = trim($_POST['schedule_type'] ?? 'every_1hr');
            $intervalMinutes = filter_input(INPUT_POST, 'interval_minutes', FILTER_VALIDATE_INT) ?: 60;
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $rawPlatforms = $_POST['platforms'] ?? [];

            if (empty($name)) {
                throw new Exception('Schedule name is required.');
            }

            if (is_array($rawPlatforms)) {
                $platformsJson = json_encode(array_values(array_filter($rawPlatforms)));
            } else {
                $platformsJson = json_encode([]);
            }

            if ($id) {
                // Update
                $stmt = $pdo->prepare("UPDATE sm_schedules 
                    SET name = ?, schedule_type = ?, interval_minutes = ?, platform_ids = ?, is_active = ? 
                    WHERE id = ?");
                $stmt->execute([$name, $scheduleType, $intervalMinutes, $platformsJson, $isActive, $id]);
                $msg = "Schedule updated successfully!";
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO sm_schedules 
                    (name, schedule_type, interval_minutes, platform_ids, is_active, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $scheduleType, $intervalMinutes, $platformsJson, $isActive, $userId]);
                $msg = "New schedule created successfully!";
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        case 'toggle_status':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid schedule ID');
            
            $stmt = $pdo->prepare("UPDATE sm_schedules SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Schedule status updated successfully!']);
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid schedule ID');

            $stmt = $pdo->prepare("DELETE FROM sm_schedules WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully!']);
            break;

        default:
            throw new Exception('Invalid action requested.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
