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
            } else {
                // Generic test for OAuth platforms
                $response['success'] = true;
                $response['data'] = 'Account status active';
            }
            break;

        case 'disconnect':
            $platform = trim($_POST['platform'] ?? '');
            if ($account_id) {
                $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE id = ?");
                $stmt->execute([$account_id]);
            } elseif (!empty($platform)) {
                $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE platform = ?");
                $stmt->execute([$platform]);
            } else {
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

            // Check if telegram record exists
            $stmt = $pdo->prepare("SELECT id FROM sm_connected_accounts WHERE platform = 'telegram' AND account_id = ?");
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

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
