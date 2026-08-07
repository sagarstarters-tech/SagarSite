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

    $userId = $_SESSION['user_id'] ?? null;

    $stmtCheck = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE platform = 'linkedin' LIMIT 1");
    $stmtCheck->execute();
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE sm_connected_accounts 
            SET account_name = :account_name, 
                account_id = :account_id, 
                access_token_encrypted = :token, 
                is_active = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':account_name' => $accountName,
            ':account_id'   => $personUrn,
            ':token'        => $encryptedToken,
            ':id'           => $existing['id']
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO sm_connected_accounts 
            (platform, account_name, account_id, access_token_encrypted, is_active, connected_by) 
            VALUES ('linkedin', :account_name, :account_id, :token, 1, :connected_by)
        ");
        $stmt->execute([
            ':account_name' => $accountName,
            ':account_id'   => $personUrn,
            ':token'        => $encryptedToken,
            ':connected_by' => $userId
        ]);
    }

    set_flash('success', 'LinkedIn account connected successfully!');
} catch (Exception $e) {
    set_flash('error', 'Failed to connect LinkedIn: ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
