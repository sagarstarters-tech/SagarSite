<?php
require_once 'core/AuthMiddleware.php';
if (!AuthMiddleware::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Session Role: ' . ($_SESSION['role'] ?? 'NONE')]);
    exit;
}
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/GoogleProfileReminderService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$test_email = trim($_POST['test_email'] ?? '');
if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid test email address.']);
    exit;
}

try {
    $service = new GoogleProfileReminderService($conn);
    $sent = $service->sendReminderEmail(0, 0, $test_email, 'Demo Customer (Test)');
    if ($sent) {
        echo json_encode(['success' => true, 'message' => "Test Profile Completion Reminder email successfully sent to {$test_email}!"]);
    } else {
        echo json_encode(['success' => false, 'message' => "Failed to send test email. Please check your SMTP settings in Settings -> General."]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
