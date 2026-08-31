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

$action = $_POST['action'] ?? 'send_test';

try {
    $service = new GoogleProfileReminderService($conn);

    if ($action === 'run_now') {
        $sentCount = $service->processAutoReminders();
        
        // Count how many are still pending
        $pending_cnt = 0;
        $pq = $conn->query("SELECT COUNT(*) as cnt FROM google_profile_reminders WHERE reminder_status = 'pending'");
        if ($pq && $prow = $pq->fetch_assoc()) {
            $pending_cnt = intval($prow['cnt']);
        }

        echo json_encode([
            'success' => true,
            'sent_count' => $sentCount,
            'pending_count' => $pending_cnt,
            'message' => "Auto-reminder check completed. {$sentCount} reminder email(s) sent. ({$pending_cnt} pending user(s) currently waiting for delay timer)."
        ]);
        exit;
    }

    // Default: Send Test Email
    $test_email = trim($_POST['test_email'] ?? '');
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid test email address.']);
        exit;
    }

    $sent = $service->sendReminderEmail(0, 0, $test_email, 'Demo Customer (Test)');
    if ($sent) {
        echo json_encode(['success' => true, 'message' => "Test Profile Completion Reminder email successfully sent to {$test_email}!"]);
    } else {
        echo json_encode(['success' => false, 'message' => "Failed to send test email. Please check your SMTP settings in Settings -> General."]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
