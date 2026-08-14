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
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $longResStr = curl_exec($ch);
    curl_close($ch);

    $longRes = json_decode($longResStr ?: '', true);
    if (isset($longRes['access_token'])) {
        $userToken = $longRes['access_token'];
    }

    // 3. Multi-tier Fetch of Facebook Pages and linked Instagram accounts
    $pages = [];

    // Approach A: /me/accounts
    $pagesUrl = "https://graph.facebook.com/v21.0/me/accounts?" . http_build_query([
        'fields' => 'name,id,access_token,tasks,instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
        'access_token' => $userToken
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pagesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $pagesStr = curl_exec($ch);
    curl_close($ch);

    $pagesRes = json_decode($pagesStr ?: '', true);
    if (!empty($pagesRes['data'])) {
        $pages = $pagesRes['data'];
    }

    // Approach B: If empty, try /me?fields=accounts
    if (empty($pages)) {
        $meAccountsUrl = "https://graph.facebook.com/v21.0/me?" . http_build_query([
            'fields' => 'id,name,accounts{id,name,access_token,instagram_business_account{id,username,name},connected_instagram_account{id,username,name}}',
            'access_token' => $userToken
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $meAccountsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $meAccountsStr = curl_exec($ch);
        curl_close($ch);
        $meAccountsRes = json_decode($meAccountsStr ?: '', true);
        if (!empty($meAccountsRes['accounts']['data'])) {
            $pages = $meAccountsRes['accounts']['data'];
        }
    }

    // Approach C: If still empty, check /me/businesses
    if (empty($pages)) {
        $bizUrl = "https://graph.facebook.com/v21.0/me/businesses?" . http_build_query([
            'fields' => 'id,name,owned_pages{id,name,access_token,instagram_business_account{id,username,name}},client_pages{id,name,access_token,instagram_business_account{id,username,name}}',
            'access_token' => $userToken
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $bizUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $bizStr = curl_exec($ch);
        curl_close($ch);
        $bizRes = json_decode($bizStr ?: '', true);
        if (!empty($bizRes['data'])) {
            foreach ($bizRes['data'] as $biz) {
                if (!empty($biz['owned_pages']['data'])) {
                    $pages = array_merge($pages, $biz['owned_pages']['data']);
                }
                if (!empty($biz['client_pages']['data'])) {
                    $pages = array_merge($pages, $biz['client_pages']['data']);
                }
            }
        }
    }

    $userId = $_SESSION['user_id'] ?? 1;
    $foundInstagram = false;
    $foundFacebook = false;

    if (empty($pages)) {
        // Check permissions to see if page permissions were declined
        $permUrl = "https://graph.facebook.com/v21.0/me/permissions?access_token=" . urlencode($userToken);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $permUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $permRes = json_decode(curl_exec($ch) ?: '', true);
        curl_close($ch);

        $declined = [];
        if (!empty($permRes['data'])) {
            foreach ($permRes['data'] as $p) {
                if (($p['status'] ?? '') === 'declined') {
                    $declined[] = $p['permission'];
                }
            }
        }

        if (!empty($declined)) {
            throw new Exception("Meta Permissions Declined (" . implode(', ', $declined) . "). Facebook login popup me permissions allow nahi ki gayi. Please re-click 'Connect Facebook' aur permission window me 'Opt in to all current and future Pages' ya apna Facebook Page check karein.");
        }

        throw new Exception("Aapke Facebook account se koi Facebook Page linked nahi mila. Meta auto-posting sirf Facebook Pages par allow karta hai (personal profile par nahi). Agar Facebook Page bana hua hai, toh login popup me 'Edit Settings' / 'Choose what you allow' par click karke apna Page select karein, ya 'Enter Facebook Token Manually' se Page Access Token dalein.");
    } else {
        $pdo->exec("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'facebook'");
        foreach ($pages as $page) {
            $pageId = $page['id'];
            $pageName = $page['name'];
            $pageAccessToken = $page['access_token'] ?? $userToken;
            $encryptedToken = TokenEncryption::encrypt($pageAccessToken);

            // Save Facebook Page
            $chkStmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'facebook' AND (page_id = ? OR account_id = ?)");
            $chkStmt->execute([$pageId, $pageId]);
            $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$pageName, $encryptedToken, $userId, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
                    VALUES ('facebook', ?, ?, ?, ?, 1, ?)");
                $stmt->execute([$pageName, $pageId, $pageId, $encryptedToken, $userId]);
            }
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $igCheckStr = curl_exec($ch);
                curl_close($ch);
                $igCheckRes = json_decode($igCheckStr ?: '', true);
                $igAccount = $igCheckRes['instagram_business_account'] ?? $igCheckRes['connected_instagram_account'] ?? null;
            }

            if (!empty($igAccount['id'])) {
                $foundInstagram = true;
                $igId = $igAccount['id'];
                $igHandle = !empty($igAccount['username']) ? '@' . $igAccount['username'] : (!empty($igAccount['name']) ? $igAccount['name'] : $pageName . ' (Instagram)');
                
                $pdo->exec("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'instagram'");
                $chkIg = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'instagram' AND (account_id = ? OR page_id = ?)");
                $chkIg->execute([$igId, $pageId]);
                $existingIg = $chkIg->fetch(PDO::FETCH_ASSOC);

                if ($existingIg) {
                    $stmtIg = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                    $stmtIg->execute([$igHandle, $encryptedToken, $userId, $existingIg['id']]);
                } else {
                    $stmtIg = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) 
                        VALUES ('instagram', ?, ?, ?, ?, 1, ?)");
                    $stmtIg->execute([$igHandle, $igId, $pageId, $encryptedToken, $userId]);
                }
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
    set_flash('error', $e->getMessage());
}

header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
exit;
