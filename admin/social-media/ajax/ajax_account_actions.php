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

require_once BASE_PATH . '/admin/social-media/services/TokenEncryption.php';
require_once BASE_PATH . '/admin/social-media/adapters/PlatformAdapterInterface.php';
require_once BASE_PATH . '/admin/social-media/adapters/TelegramAdapter.php';
require_once BASE_PATH . '/admin/social-media/adapters/FacebookAdapter.php';

use Admin\SocialMedia\Services\TokenEncryption;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $submittedToken = $_POST['_csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
        throw new Exception('Security token mismatch (CSRF). Please refresh the page and try again.');
    }
    $action = $_POST['action'] ?? '';
    $account_id = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);

    $response = ['success' => false, 'data' => null, 'error' => null];

    switch ($action) {
        case 'test':
            if (!$account_id) throw new Exception('Invalid Account ID');
            $stmt = $pdo->prepare("SELECT * FROM sm_connected_accounts WHERE id = ?");
            $stmt->execute([$account_id]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$acc) throw new Exception('Account not found');

            $platform = strtolower($acc['platform']);
            if ($platform === 'telegram') {
                $decryptedToken = TokenEncryption::decrypt($acc['access_token_encrypted'] ?? '');
                $telegram = new TelegramAdapter();
                $isValid = $telegram->validateConnection(['bot_token' => $decryptedToken]);
                if (!$isValid) {
                    throw new Exception('Failed to connect to Telegram API with saved bot token.');
                }
                $response['success'] = true;
                $response['data'] = 'Telegram bot token verified successfully!';
            } elseif ($platform === 'facebook') {
                if (empty($acc['access_token_encrypted']) || empty($acc['page_id'])) {
                    throw new Exception("Facebook Page ID or Access Token is missing. Please click 'Configure Token' to enter your Facebook Page Access Token.");
                }
                $decryptedToken = TokenEncryption::decrypt($acc['access_token_encrypted'] ?? '');
                $fb = new FacebookAdapter();
                $isValid = $fb->validateConnection(['access_token' => $decryptedToken]);
                if (!$isValid) {
                    throw new Exception('Facebook Page Access Token is invalid or expired. Please update your token.');
                }
                $response['success'] = true;
                $response['data'] = 'Facebook Page Token verified successfully! Page ID: ' . $acc['page_id'];
            } elseif ($platform === 'instagram') {
                if (empty($acc['access_token_encrypted']) || (empty($acc['account_id']) && empty($acc['page_id']))) {
                    throw new Exception("Instagram Account ID or Access Token is missing.");
                }
                $decryptedToken = TokenEncryption::decrypt($acc['access_token_encrypted'] ?? '');
                require_once BASE_PATH . '/admin/social-media/adapters/InstagramAdapter.php';
                $ig = new InstagramAdapter();
                $isValid = $ig->validateConnection([
                    'ig_user_id' => $acc['account_id'] ?? $acc['page_id'] ?? '',
                    'access_token' => $decryptedToken
                ]);
                if (!$isValid) {
                    throw new Exception('Instagram Access Token or Account ID is invalid or expired.');
                }
                $response['success'] = true;
                $response['data'] = 'Instagram Account verified successfully! IG User ID: ' . ($acc['account_id'] ?? $acc['page_id']);
            } elseif ($platform === 'pinterest') {
                if (empty($acc['access_token_encrypted'])) {
                    throw new Exception("Pinterest Access Token is missing.");
                }
                $decryptedToken = TokenEncryption::decrypt($acc['access_token_encrypted'] ?? '');
                require_once BASE_PATH . '/admin/social-media/adapters/PinterestAdapter.php';
                $pin = new PinterestAdapter();
                $isValid = $pin->validateConnection(['access_token' => $decryptedToken]);
                if (!$isValid) {
                    throw new Exception('Pinterest Access Token is invalid or expired.');
                }
                $response['success'] = true;
                $response['data'] = 'Pinterest Account verified successfully! Account: ' . ($acc['account_name'] ?? $acc['account_id']);
            } else {
                // Generic test for OAuth platforms
                $response['success'] = true;
                $response['data'] = 'Account status active';
            }
            break;

        case 'disconnect':
            $platform = trim($_POST['platform'] ?? '');
            if (!empty($platform)) {
                $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = LOWER(?)");
                $stmt->execute([$platform]);
            }
            if ($account_id) {
                $stmtFetch = $pdo->prepare("SELECT platform FROM sm_connected_accounts WHERE id = ?");
                $stmtFetch->execute([$account_id]);
                $plat = $stmtFetch->fetchColumn();
                if ($plat) {
                    $stmtAll = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = LOWER(?)");
                    $stmtAll->execute([$plat]);
                }
                $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE id = ?");
                $stmt->execute([$account_id]);
            }
            if (empty($platform) && !$account_id) {
                throw new Exception('Invalid Account ID or Platform');
            }
            $response['success'] = true;
            break;

        case 'save_telegram':
            $bot_token = trim($_POST['bot_token'] ?? '');
            $channel_id = trim($_POST['channel_id'] ?? '');
            
            if (empty($bot_token) || empty($channel_id)) {
                throw new Exception('Bot token and Channel ID are required');
            }

            // Test token with Telegram API first
            $telegram = new TelegramAdapter();
            $authRes = $telegram->authenticate($bot_token, ['channel_id' => $channel_id]);

            if (isset($authRes['error'])) {
                throw new Exception('Telegram Error: ' . $authRes['error']);
            }

            $botUsername = $authRes['bot_username'] ?? 'Telegram Channel';
            $encryptedToken = TokenEncryption::encrypt($bot_token);
            $userId = $_SESSION['user_id'] ?? 1;

            // Deactivate any duplicate active telegram accounts
            $deactStmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'telegram'");
            $deactStmt->execute();

            // Check if telegram record exists
            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'telegram' AND account_id = ?");
            $stmt->execute([$channel_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute(['@' . $botUsername . ' (' . $channel_id . ')', $encryptedToken, $userId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('telegram', ?, ?, ?, ?, 1, ?)");
                $insertStmt->execute(['@' . $botUsername . ' (' . $channel_id . ')', $channel_id, $channel_id, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = 'Telegram connected successfully';
            break;

        case 'save_facebook':
            $page_id = trim($_POST['page_id'] ?? '');
            $access_token = trim($_POST['access_token'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');

            if (empty($page_id) || empty($access_token)) {
                throw new Exception('Facebook Page ID and Page Access Token are required');
            }

            // Verify with Meta Graph API
            $apiUrl = "https://graph.facebook.com/v21.0/{$page_id}?" . http_build_query([
                'fields' => 'name,id,access_token',
                'access_token' => $access_token
            ]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $pageResStr = curl_exec($ch);
            curl_close($ch);

            $pageRes = json_decode($pageResStr ?: '', true);

            $finalToken = $access_token;
            $finalPageName = $account_name;

            if (!empty($pageRes['access_token'])) {
                // Resolved page-specific token
                $finalToken = $pageRes['access_token'];
                if (!empty($pageRes['name'])) {
                    $finalPageName = $pageRes['name'];
                }
            } elseif (!empty($pageRes['name']) && empty($pageRes['error'])) {
                // Token worked for reading page
                $finalPageName = $pageRes['name'];
            } else {
                // Check /me/accounts in case user provided a User Token
                $meAccountsUrl = "https://graph.facebook.com/v21.0/me/accounts?" . http_build_query([
                    'fields' => 'name,id,access_token',
                    'access_token' => $access_token
                ]);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $meAccountsUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $meResStr = curl_exec($ch);
                curl_close($ch);
                $meAccountsRes = json_decode($meResStr ?: '', true);

                $matchedPage = null;
                if (!empty($meAccountsRes['data'])) {
                    foreach ($meAccountsRes['data'] as $p) {
                        if ((string)$p['id'] === (string)$page_id) {
                            $matchedPage = $p;
                            break;
                        }
                    }
                    if (!$matchedPage && count($meAccountsRes['data']) === 1) {
                        $matchedPage = $meAccountsRes['data'][0];
                        $page_id = $matchedPage['id'];
                    }
                }

                if ($matchedPage) {
                    $finalToken = $matchedPage['access_token'] ?? $access_token;
                    $finalPageName = $matchedPage['name'] ?? $account_name;
                } else {
                    // Check /me to see if they provided a personal user token
                    $meUrl = "https://graph.facebook.com/v21.0/me?fields=name,id&access_token=" . urlencode($access_token);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $meUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $userRes = json_decode(curl_exec($ch) ?: '', true);
                    curl_close($ch);

                    if (!empty($userRes['name']) && empty($meAccountsRes['data'])) {
                        throw new Exception("⚠️ Meta Error: Aapne User Profile ({$userRes['name']}) ka Token daala hai. Meta personal profile par posting allow nahi karta.\n\n👉 Fix karne ke liye: Graph API Explorer me 'User or Page' dropdown se apna 'Facebook Page' select karein aur 'pages_manage_posts' & 'pages_read_engagement' permissions ke sath Page Access Token generate karein.");
                    }

                    $errDetail = $pageRes['error']['message'] ?? ($meAccountsRes['error']['message'] ?? 'Invalid Page ID or Access Token');
                    throw new Exception("Meta API Error: {$errDetail}. Please verify the Facebook Page ID and ensure the token has 'pages_manage_posts' permission.");
                }
            }

            if (empty($finalPageName)) {
                $finalPageName = 'Facebook Page';
            }

            $encryptedToken = TokenEncryption::encrypt($finalToken);
            $userId = $_SESSION['user_id'] ?? 1;

            // Deactivate any existing active facebook accounts first
            $deactStmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'facebook'");
            $deactStmt->execute();

            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'facebook' AND page_id = ?");
            $stmt->execute([$page_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$finalPageName, $encryptedToken, $userId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('facebook', ?, ?, ?, ?, 1, ?)");
                $insertStmt->execute([$finalPageName, $page_id, $page_id, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = "Facebook Page '{$finalPageName}' connected successfully!";
            break;

        case 'sync_instagram_from_facebook':
            $stmtFb = $pdo->prepare("SELECT * FROM sm_connected_accounts WHERE LOWER(platform) = 'facebook' AND is_active = 1 LIMIT 1");
            $stmtFb->execute();
            $fbAcc = $stmtFb->fetch(PDO::FETCH_ASSOC);

            if (!$fbAcc) {
                throw new Exception('Please connect your Facebook Page (Sagar Starters) first.');
            }

            $fbToken = TokenEncryption::decrypt($fbAcc['access_token_encrypted'] ?? '');
            $pageId = $fbAcc['page_id'] ?? '';

            if (empty($fbToken) || empty($pageId)) {
                throw new Exception('Facebook Page ID or Access Token is missing.');
            }

            // 1. Check /{page_id}?fields=instagram_business_account{id,username,name}
            $igUrl = "https://graph.facebook.com/v21.0/{$pageId}?" . http_build_query([
                'fields' => 'instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
                'access_token' => $fbToken
            ]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $igUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $igResStr = curl_exec($ch);
            curl_close($ch);
            $igRes = json_decode($igResStr ?: '', true);

            $igAccount = $igRes['instagram_business_account'] ?? ($igRes['connected_instagram_account'] ?? null);

            // 2. If not found on page, check /me/accounts
            if (empty($igAccount['id'])) {
                $meUrl = "https://graph.facebook.com/v21.0/me/accounts?" . http_build_query([
                    'fields' => 'name,id,access_token,instagram_business_account{id,username,name}',
                    'access_token' => $fbToken
                ]);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $meUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $meAccountsRes = json_decode(curl_exec($ch) ?: '', true);
                curl_close($ch);

                if (!empty($meAccountsRes['data'])) {
                    foreach ($meAccountsRes['data'] as $p) {
                        if (!empty($p['instagram_business_account']['id'])) {
                            $igAccount = $p['instagram_business_account'];
                            if (!empty($p['access_token'])) {
                                $fbToken = $p['access_token'];
                            }
                            break;
                        }
                    }
                }
            }

            if (empty($igAccount['id'])) {
                $errHint = $igRes['error']['message'] ?? '';
                if ($errHint) {
                    throw new Exception("Meta API Error: {$errHint}. Ensure your Facebook Page Access Token has permissions: 'instagram_basic', 'instagram_content_publish', 'pages_read_engagement'.");
                }
                throw new Exception("Meta Graph API returned no linked Instagram Business Account for Facebook Page ID {$pageId}.\n\n👉 Please ensure that:\n1. In Instagram App: Account is set to 'Professional / Business' (not Personal).\n2. In Facebook Page Settings ➔ Linked Accounts ➔ Instagram is confirmed and permissions are allowed.\n3. In Graph API Explorer: Permissions 'instagram_basic' and 'instagram_content_publish' are added to the Page Token.");
            }

            $igId = $igAccount['id'];
            $igHandle = !empty($igAccount['username']) ? '@' . $igAccount['username'] : (!empty($igAccount['name']) ? $igAccount['name'] : '@sagarstarter');
            $encryptedToken = TokenEncryption::encrypt($fbToken);
            $userId = $_SESSION['user_id'] ?? 1;

            $deactIg = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'instagram'");
            $deactIg->execute();

            $stmtIg = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'instagram' LIMIT 1");
            $stmtIg->execute();
            $existingIg = $stmtIg->fetch(PDO::FETCH_ASSOC);

            if ($existingIg) {
                $upIg = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, account_id = ?, page_id = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $upIg->execute([$igHandle, $igId, $pageId, $encryptedToken, $userId, $existingIg['id']]);
            } else {
                $inIg = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('instagram', ?, ?, ?, ?, 1, ?)");
                $inIg->execute([$igHandle, $igId, $pageId, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = "Instagram Account '{$igHandle}' (ID: {$igId}) connected successfully from Facebook Page!";
            break;

        case 'save_pinterest':
            $access_token = trim($_POST['access_token'] ?? '');
            $board_id = trim($_POST['board_id'] ?? '');
            $account_name = trim($_POST['account_name'] ?? 'Pinterest Account');

            if (empty($access_token)) {
                throw new Exception('Pinterest Access Token is required');
            }

            $encryptedToken = TokenEncryption::encrypt($access_token);
            $userId = $_SESSION['user_id'] ?? 1;

            $deactStmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'pinterest'");
            $deactStmt->execute();

            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'pinterest' LIMIT 1");
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, page_id = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$account_name, $board_id, $encryptedToken, $userId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('pinterest', ?, 'pinterest', ?, ?, 1, ?)");
                $insertStmt->execute([$account_name, $board_id, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = 'Pinterest Access Token saved successfully!';
            break;

        case 'save_instagram':
            $ig_user_id = trim($_POST['ig_user_id'] ?? '');
            $access_token = trim($_POST['access_token'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');

            if (empty($access_token)) {
                throw new Exception('Instagram Access Token is required');
            }

            $finalIgId = $ig_user_id;
            $finalHandle = $account_name;
            $finalToken = $access_token;
            $res = null;

            if (!empty($finalIgId)) {
                $chkUrl = "https://graph.facebook.com/v21.0/{$finalIgId}?" . http_build_query([
                    'fields' => 'id,username,name',
                    'access_token' => $access_token
                ]);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $chkUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $resStr = curl_exec($ch);
                curl_close($ch);
                $res = json_decode($resStr ?: '', true);

                if (!empty($res['username'])) {
                    $finalHandle = '@' . $res['username'];
                }
            }

            // If verification failed or ig_user_id was empty, inspect /me/accounts for instagram_business_account
            if (empty($finalIgId) || isset($res['error'])) {
                $meAccountsUrl = "https://graph.facebook.com/v21.0/me/accounts?" . http_build_query([
                    'fields' => 'name,id,access_token,instagram_business_account{id,username,name}',
                    'access_token' => $access_token
                ]);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $meAccountsUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $meAccountsRes = json_decode(curl_exec($ch) ?: '', true);
                curl_close($ch);

                $foundIg = null;
                $foundPageToken = $access_token;
                if (!empty($meAccountsRes['data'])) {
                    foreach ($meAccountsRes['data'] as $p) {
                        if (!empty($p['instagram_business_account']['id'])) {
                            $foundIg = $p['instagram_business_account'];
                            $foundPageToken = $p['access_token'] ?? $access_token;
                            break;
                        }
                    }
                }

                if ($foundIg) {
                    $finalIgId = $foundIg['id'];
                    $finalHandle = !empty($foundIg['username']) ? '@' . $foundIg['username'] : ($foundIg['name'] ?? '@instagram');
                    $finalToken = $foundPageToken;
                } else {
                    if (empty($finalIgId)) {
                        throw new Exception("⚠️ No Instagram Business Account found linked to your Facebook Page.\n\n👉 Fix karne ke liye:\n1. Instagram app me account ko 'Professional / Business Account' me switch karein.\n2. Facebook Page Settings ➔ Linked Accounts ➔ Instagram connect karein.");
                    }
                }
            }

            if (empty($finalIgId)) {
                throw new Exception('Instagram Business Account ID is required');
            }
            if (empty($finalHandle)) {
                $finalHandle = '@instagram';
            }

            $encryptedToken = TokenEncryption::encrypt($finalToken);
            $userId = $_SESSION['user_id'] ?? 1;

            $deactStmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'instagram'");
            $deactStmt->execute();

            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'instagram' LIMIT 1");
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, account_id = ?, page_id = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$finalHandle, $finalIgId, $finalIgId, $encryptedToken, $userId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('instagram', ?, ?, ?, ?, 1, ?)");
                $insertStmt->execute([$finalHandle, $finalIgId, $finalIgId, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = "Instagram Account '{$finalHandle}' connected successfully!";
            break;

        case 'save_linkedin':
            $person_urn = trim($_POST['person_urn'] ?? '');
            $access_token = trim($_POST['access_token'] ?? '');
            $account_name = trim($_POST['account_name'] ?? 'LinkedIn Account');

            if (empty($access_token)) {
                throw new Exception('LinkedIn Access Token is required');
            }

            $encryptedToken = TokenEncryption::encrypt($access_token);
            $userId = $_SESSION['user_id'] ?? 1;

            $deactStmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = 'linkedin'");
            $deactStmt->execute();

            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE LOWER(platform) = 'linkedin' LIMIT 1");
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("UPDATE sm_connected_accounts SET account_name = ?, account_id = ?, page_id = ?, access_token_encrypted = ?, is_active = 1, connected_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$account_name, $person_urn, $person_urn, $encryptedToken, $userId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sm_connected_accounts (platform, account_name, account_id, page_id, access_token_encrypted, is_active, connected_by) VALUES ('linkedin', ?, ?, ?, ?, 1, ?)");
                $insertStmt->execute([$account_name, $person_urn, $person_urn, $encryptedToken, $userId]);
            }

            $response['success'] = true;
            $response['data'] = 'LinkedIn Access Token saved successfully!';
            break;

        case 'save_app_keys':
            $app_id_key = trim($_POST['app_id_key'] ?? '');
            $app_secret_key = trim($_POST['app_secret_key'] ?? '');
            $app_id_val = trim($_POST['app_id_val'] ?? '');
            $app_secret_val = trim($_POST['app_secret_val'] ?? '');

            if (empty($app_id_key) || empty($app_secret_key) || empty($app_id_val) || empty($app_secret_val)) {
                throw new Exception('App ID and App Secret values are required');
            }

            $stmtSave = $pdo->prepare("INSERT INTO sm_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
            $stmtSave->execute([$app_id_key, $app_id_val]);
            $stmtSave->execute([$app_secret_key, $app_secret_val]);

            $response['success'] = true;
            $response['data'] = 'API credentials saved successfully!';
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
