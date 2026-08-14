<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/flash.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/social-media/services/TokenEncryption.php';
require_once BASE_PATH . '/admin/social-media/adapters/TwitterAdapter.php';

use Admin\SocialMedia\Services\TokenEncryption;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error_description'] ?? $_GET['error'] ?? null;

    if ($error) {
        throw new Exception('Twitter Authorization Error: ' . $error);
    }

    if (!$code) {
        throw new Exception('No authorization code received from Twitter.');
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $callbackUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $adapter = new TwitterAdapter();
    $authRes = $adapter->authenticate($code, ['redirect_uri' => $callbackUrl]);

    if (isset($authRes['error'])) {
        throw new Exception($authRes['error']);
    }

    $accessToken = $authRes['access_token'] ?? '';
    $refreshToken = $authRes['refresh_token'] ?? '';
    $accountId = $authRes['account_id'] ?? 'twitter_user';
    $accountName = $authRes['account_name'] ?? 'Twitter Account';

    if (empty($accessToken)) {
        throw new Exception('Failed to obtain Twitter Access Token.');
    }

    $encryptedToken = TokenEncryption::encrypt($accessToken);
    $userId = $_SESSION['user_id'] ?? 1;

    $pdo->exec("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'twitter'");

    $chkStmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'twitter' AND account_id = ?");
    $chkStmt->execute([$accountId]);
    $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$accountName, $encryptedToken, $userId, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
            VALUES ('twitter', ?, ?, ?, ?, 1, ?)");
        $stmt->execute([$accountName, $accountId, $accountId, $encryptedToken, $userId]);
    }

    set_flash('success', "X (Twitter) Account '{$accountName}' connected successfully!");
} catch (Exception $e) {
    error_log('[Twitter Callback Error] ' . $e->getMessage());
    set_flash('error', 'Failed to connect X (Twitter): ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
