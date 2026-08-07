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

// Include SocialMediaService if it exists
// require_once BASE_PATH . '/services/SocialMediaService.php';

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    csrf_verify();

    $filter_type = $_POST['filter_type'] ?? '';
    $filter_value = $_POST['filter_value'] ?? '';
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $template_id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
    $platform_accounts = json_decode($_POST['platform_accounts'] ?? '[]', true);
    $hashtags = $_POST['hashtags'] ?? '';
    $cta = $_POST['cta'] ?? '';

    // Mock call to SocialMediaService::bulkSchedule
    // $job_id = SocialMediaService::bulkSchedule([...]);
    $job_id = rand(1000, 9999);
    $total_products = 5;
    $total_posts = 10;

    echo json_encode([
        'success' => true,
        'data' => [
            'total_products' => $total_products,
            'total_posts' => $total_posts,
            'job_id' => $job_id
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
