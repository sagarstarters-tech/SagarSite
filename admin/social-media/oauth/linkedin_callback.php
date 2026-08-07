<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/flash.php';
require_once BASE_PATH . '/config/DbConnection.php';

require_once BASE_PATH . '/admin/social-media/adapters/LinkedInAdapter.php';
require_once BASE_PATH . '/admin/social-media/services/TokenEncryption.php';

use Admin\SocialMedia\Services\TokenEncryption;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error_description'] ?? $_GET['error'] ?? null;

    if ($error) {
        throw new Exception('LinkedIn OAuth error: ' . $error);
    }

    if (!$code || !$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
        throw new Exception('Invalid state or authorization code');
    }

    // Determine exact callback URL matching the OAuth authorization request
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $callbackUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $adapter = new LinkedInAdapter();
    $authResult = $adapter->authenticate($code, ['redirect_uri' => $callbackUrl]);

    if (isset($authResult['error'])) {
        throw new Exception($authResult['error']);
    }

    $accessToken = $authResult['access_token'];
    $personUrn   = $authResult['person_urn'];
    $accountId   = $authResult['account_id'];
    $accountName = $authResult['account_name'] ?? 'LinkedIn Profile';

    $encryptedToken = TokenEncryption::encrypt($accessToken);

    $stmt = $pdo->prepare("
        INSERT INTO sm_connected_accounts (platform, account_id, access_token, account_name, is_active) 
        VALUES ('linkedin', :account_id, :access_token, :account_name, 1) 
        ON DUPLICATE KEY UPDATE 
            access_token = VALUES(access_token), 
            account_name = VALUES(account_name), 
            is_active = 1
    ");
    $stmt->execute([
        ':account_id'   => $personUrn,
        ':access_token' => $encryptedToken,
        ':account_name' => $accountName
    ]);

    set_flash('success', 'LinkedIn account connected successfully!');
} catch (Exception $e) {
    set_flash('error', 'Failed to connect LinkedIn: ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
