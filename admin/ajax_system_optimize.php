<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/SystemController.php';

// Security check: Only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new SystemController($conn);

    header('Content-Type: application/json');

    try {
        if ($action === 'full_optimize') {
            $clear_logs = isset($_POST['clear_logs']) && $_POST['clear_logs'] === 'true';
            $report = $controller->runFullMaintenance(['clear_logs' => $clear_logs]);
            echo json_encode(['success' => true, 'report' => $report]);
        } elseif ($action === 'boost_speed') {
            $report = $controller->boostWebsiteSpeed();
            echo json_encode(['success' => true, 'report' => $report]);
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
