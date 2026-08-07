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

    csrf_verify();
    $action = $_POST['action'] ?? '';
    $account_id = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);

    $response = ['success' => false, 'data' => null, 'error' => null];

    switch ($action) {
        case 'test':
            if (!$account_id) throw new Exception('Invalid Account ID');
            // Mock connection test
            $response['success'] = true;
            $response['data'] = 'Connection successful';
            break;
        case 'disconnect':
            if (!$account_id) throw new Exception('Invalid Account ID');
            $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0, access_token = NULL, refresh_token = NULL WHERE id = ?");
            $stmt->execute([$account_id]);
            $response['success'] = true;
            break;
        case 'refresh':
            if (!$account_id) throw new Exception('Invalid Account ID');
            // Mock token refresh
            $response['success'] = true;
            break;
        case 'save_telegram':
            $bot_token = $_POST['bot_token'] ?? '';
            $channel_id = $_POST['channel_id'] ?? '';
            if (empty($bot_token) || empty($channel_id)) {
                throw new Exception('Bot token and Channel ID are required');
            }
            // Mock insert/update
            $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_id, access_token, is_active) VALUES ('telegram', ?, ?, 1) ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), is_active = 1");
            $stmt->execute([$channel_id, $bot_token]);
            $response['success'] = true;
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
