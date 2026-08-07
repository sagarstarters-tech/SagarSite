<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/flash.php';
require_once BASE_PATH . '/config/DbConnection.php';

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if (!$code || !$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
        throw new Exception('Invalid state or code parameter');
    }

    // Mock Pinterest save
    $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_id, access_token, account_name, is_active) VALUES ('pinterest', 'pi_mock_id', 'mock_token', 'Pinterest Account', 1) ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), is_active = 1");
    $stmt->execute();

    set_flash('success', 'Pinterest account connected successfully!');
} catch (Exception $e) {
    set_flash('error', 'Failed to connect Pinterest: ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
