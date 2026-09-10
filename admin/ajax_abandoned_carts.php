<?php
/**
 * AJAX Handler for Cart Abandonment Recovery
 * Follows existing ajax_manage_scripts.php pattern.
 */

@ob_start();

include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartController.php';

@set_time_limit(45);

function send_json_clean($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    echo json_encode($data);
    exit;
}

// Security check: Only admins can access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    send_json_clean(['success' => false, 'error' => 'Permission denied']);
}

$controller = new AbandonedCartController($conn);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'get_carts':
            $result = $controller->getDashboard([
                'status' => $_GET['status'] ?? 'all',
                'search' => $_GET['search'] ?? '',
                'page'   => intval($_GET['page'] ?? 1),
            ]);
            send_json_clean($result);
            break;

        case 'send_reminder':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $level   = intval($_POST['level'] ?? 0);
            $overridePhone = trim($_POST['phone'] ?? '');
            $result  = $controller->sendReminder($cartId, $level, $overridePhone);
            send_json_clean($result);
            break;

        case 'mark_stage_sent':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $level  = intval($_POST['level'] ?? 0);
            $result = $controller->markStageManual($cartId, $level, 'mark_sent');
            send_json_clean($result);
            break;

        case 'unmark_stage_sent':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $level  = intval($_POST['level'] ?? 0);
            $result = $controller->markStageManual($cartId, $level, 'unmark_sent');
            send_json_clean($result);
            break;

        case 'get_cart_preview':
            $cartId = intval($_GET['cart_id'] ?? ($_POST['cart_id'] ?? 0));
            $result = $controller->getCartPreview($cartId);
            send_json_clean($result);
            break;

        case 'reset_reminders':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->resetReminders($cartId);
            send_json_clean($result);
            break;

        case 'trigger_cron':
            $result = $controller->triggerAutoReminders();
            send_json_clean($result);
            break;

        case 'mark_expired':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->markExpired($cartId);
            send_json_clean($result);
            break;

        case 'delete_cart':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->deleteCart($cartId);
            send_json_clean($result);
            break;

        case 'save_settings':
            $settingsData = [];
            $allowedKeys = [
                'is_enabled', 'reminder_1_delay', 'reminder_2_delay', 'reminder_3_delay', 'reminder_4_delay',
                'reminder_1_message', 'reminder_2_message', 'reminder_3_message', 'reminder_4_message',
                'coupon_discount_percent', 'coupon_validity_hours', 'auto_expire_days',
                'meta_template_1', 'meta_template_2', 'meta_template_3', 'meta_template_4', 'meta_template_lang'
            ];
            foreach ($allowedKeys as $key) {
                if (isset($_POST[$key])) {
                    $settingsData[$key] = $_POST[$key];
                }
            }
            $result = $controller->saveSettings($settingsData);
            send_json_clean($result);
            break;

        case 'get_settings':
            $result = $controller->getSettings();
            send_json_clean($result);
            break;

        case 'get_cart_logs':
            $cartId = intval($_GET['cart_id'] ?? 0);
            $result = $controller->getCartLogs($cartId);
            send_json_clean($result);
            break;

        case 'get_api_log':
            $result = $controller->getApiLog();
            send_json_clean($result);
            break;

        case 'get_stats':
            $result = $controller->getDashboard([
                'status' => 'all',
                'search' => '',
                'page'   => 1,
            ]);
            if ($result['success']) {
                send_json_clean(['success' => true, 'data' => $result['data']['stats']]);
            } else {
                send_json_clean($result);
            }
            break;

        default:
            send_json_clean(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    send_json_clean(['success' => false, 'error' => $e->getMessage()]);
}
exit;
