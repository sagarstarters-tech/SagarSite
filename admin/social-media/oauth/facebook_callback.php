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

use Admin\SocialMedia\Services\TokenEncryption;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    $code = $_GET['code'] ?? null;
    $error = $_GET['error_description'] ?? $_GET['error_message'] ?? null;

    if ($error) {
        throw new Exception('Meta Authorization Error: ' . $error);
    }

    if (!$code) {
        throw new Exception('No authorization code received from Meta.');
    }

    $appId = _env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID');
    $appSecret = _env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET');

    if (empty($appId) || empty($appSecret)) {
        throw new Exception('FB_APP_ID or FB_APP_SECRET missing in .env');
    }

    // Determine current callback URL dynamically to match OAuth request exactly
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $callbackUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    // 1. Exchange authorization code for short-lived user access token
    $tokenUrl = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'redirect_uri' => $callbackUrl,
        'code' => $code
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resStr = curl_exec($ch);
    curl_close($ch);

    $tokenRes = json_decode($resStr ?: '', true);
    if (isset($tokenRes['error'])) {
        throw new Exception($tokenRes['error']['message'] ?? 'Failed to exchange token with Meta.');
    }

    $userToken = $tokenRes['access_token'] ?? '';
    if (!$userToken) {
        throw new Exception('Failed to retrieve user access token from Meta.');
    }

    // 2. Exchange short-lived user token for long-lived user token (lasts 60 days)
    $longLivedUrl = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
        'grant_type' => 'fb_exchange_token',
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'fb_exchange_token' => $userToken
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $longLivedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $longResStr = curl_exec($ch);
    curl_close($ch);

    $longRes = json_decode($longResStr ?: '', true);
    if (isset($longRes['access_token'])) {
        $userToken = $longRes['access_token'];
    }

    // 3. Fetch user's Facebook Pages and linked Instagram accounts
    $pagesUrl = "https://graph.facebook.com/v21.0/me/accounts?" . http_build_query([
        'fields' => 'name,id,access_token,instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
        'access_token' => $userToken
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pagesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $pagesStr = curl_exec($ch);
    curl_close($ch);

    $pagesRes = json_decode($pagesStr ?: '', true);
    $pages = $pagesRes['data'] ?? [];

    $userId = $_SESSION['user_id'] ?? 1;
    $foundInstagram = false;
    $foundFacebook = false;

    if (empty($pages)) {
        // Fallback: If no specific Pages returned, store the user profile account
        $meUrl = "https://graph.facebook.com/v21.0/me?fields=name,id&access_token=" . urlencode($userToken);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $meUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $meStr = curl_exec($ch);
        curl_close($ch);
        $meRes = json_decode($meStr ?: '', true);

        $fbId = $meRes['id'] ?? 'fb_' . time();
        $fbName = $meRes['name'] ?? 'Facebook Account';
        $encryptedToken = TokenEncryption::encrypt($userToken);

        $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
            VALUES ('facebook', ?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), access_token_encrypted = VALUES(access_token_encrypted), is_active = 1");
        $stmt->execute([$fbName, $fbId, $fbId, $encryptedToken, $userId]);
        $foundFacebook = true;
    } else {
        foreach ($pages as $page) {
            $pageId = $page['id'];
            $pageName = $page['name'];
            $pageAccessToken = $page['access_token'] ?? $userToken;
            $encryptedToken = TokenEncryption::encrypt($pageAccessToken);

            // Save Facebook Page
            $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
                VALUES ('facebook', ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), access_token_encrypted = VALUES(access_token_encrypted), is_active = 1");
            $stmt->execute([$pageName, $pageId, $pageId, $encryptedToken, $userId]);
            $foundFacebook = true;

            // Check linked Instagram Business Account (try both fields)
            $igAccount = $page['instagram_business_account'] ?? $page['connected_instagram_account'] ?? null;
            
            // Fallback direct page check if nested field didn't return
            if (!$igAccount) {
                $igCheckUrl = "https://graph.facebook.com/v21.0/{$pageId}?" . http_build_query([
                    'fields' => 'instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
                    'access_token' => $pageAccessToken
                ]);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $igCheckUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $igCheckStr = curl_exec($ch);
                curl_close($ch);
                $igCheckRes = json_decode($igCheckStr ?: '', true);
                $igAccount = $igCheckRes['instagram_business_account'] ?? $igCheckRes['connected_instagram_account'] ?? null;
            }

            if (!empty($igAccount['id'])) {
                $foundInstagram = true;
                $igId = $igAccount['id'];
                $igHandle = !empty($igAccount['username']) ? '@' . $igAccount['username'] : (!empty($igAccount['name']) ? $igAccount['name'] : $pageName . ' (Instagram)');
                $stmtIg = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
                    VALUES ('instagram', ?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), access_token_encrypted = VALUES(access_token_encrypted), is_active = 1");
                $stmtIg->execute([$igHandle, $igId, $pageId, $encryptedToken, $userId]);
            }
        }
    }

    if ($foundInstagram) {
        set_flash('success', '✅ Facebook Page & Instagram Business Account connected successfully!');
    } elseif ($foundFacebook) {
        set_flash('warning', '✅ Facebook Page connected! ⚠️ Instagram Note: No Instagram Business Account is currently linked to your Facebook Page in Meta. To connect Instagram: Open your Facebook Page Settings → Linked Accounts → Connect your Instagram Business Account, then click Connect Instagram again.');
    } else {
        set_flash('success', 'Facebook account connected successfully!');
    }
} catch (Exception $e) {
    error_log('[Facebook Callback Error] ' . $e->getMessage());
    set_flash('error', 'Failed to connect Facebook: ' . $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
