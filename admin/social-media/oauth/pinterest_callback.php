<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/flash.php';
require_once BASE_PATH . '/config/DbConnection.php';

require_once BASE_PATH . '/admin/social-media/adapters/PinterestAdapter.php';
require_once BASE_PATH . '/admin/social-media/services/TokenEncryption.php';

use Admin\SocialMedia\Services\TokenEncryption;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error_description'] ?? $_GET['error'] ?? null;

    if ($error) {
        throw new Exception('Pinterest OAuth error: ' . $error);
    }

    if (!$code || !$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
        throw new Exception('Invalid state or authorization code');
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $callbackUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $adapter = new PinterestAdapter();
    $authResult = $adapter->authenticate($code, ['redirect_uri' => $callbackUrl]);

    if (isset($authResult['error'])) {
        throw new Exception($authResult['error']);
    }

    $accessToken = $authResult['access_token'] ?? '';
    $refreshToken = $authResult['refresh_token'] ?? '';

    if (!$accessToken) {
        throw new Exception('Failed to obtain Pinterest Access Token');
    }

    $userRes = $adapter->getUserProfile($accessToken);
    $accountId = $userRes['username'] ?? $userRes['id'] ?? ('pinterest_' . time());
    $accountName = !empty($userRes['username']) ? ('@' . $userRes['username']) : ($userRes['business_name'] ?? 'Pinterest Account');

    $encryptedToken = TokenEncryption::encrypt($accessToken);

    $stmtCheck = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE platform = 'pinterest' LIMIT 1");
    $stmtCheck->execute();
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE sm_connected_accounts 
            SET account_id = ?, access_token = ?, refresh_token = ?, account_name = ?, is_active = 1, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$accountId, $encryptedToken, $refreshToken, $accountName, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO sm_connected_accounts (platform, account_id, access_token, refresh_token, account_name, is_active) 
            VALUES ('pinterest', ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$accountId, $encryptedToken, $refreshToken, $accountName]);
    }

    set_flash('success', "Pinterest account ($accountName) connected successfully!");
} catch (Exception $e) {
    set_flash('error', 'Failed to connect Pinterest: ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
