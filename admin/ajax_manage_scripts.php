<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/ScriptController.php';

header('Content-Type: application/json');

// Security check: Only admins can access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $controller = new ScriptController($conn);

    try {
        if ($action === 'save_scripts') {
            $data = [
                'header_code'         => $_POST['header_code'] ?? '',
                'footer_code'         => $_POST['footer_code'] ?? '',
                'google_verification' => $_POST['google_verification'] ?? '',
                'bing_verification'   => $_POST['bing_verification'] ?? '',
                'custom_verification' => $_POST['custom_verification'] ?? '',
                'txt_instructions'    => $_POST['txt_instructions'] ?? ''
            ];
            $result = $controller->saveScripts($data);
            echo json_encode($result);
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
