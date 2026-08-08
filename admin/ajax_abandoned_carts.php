<?php
/**
 * AJAX Handler for Cart Abandonment Recovery
 * Follows existing ajax_manage_scripts.php pattern.
 */

include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/AbandonedCartController.php';

header('Content-Type: application/json');

// Security check: Only admins can access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
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
            echo json_encode($result);
            break;

        case 'send_reminder':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $level   = intval($_POST['level'] ?? 0);
            $result  = $controller->sendReminder($cartId, $level);
            echo json_encode($result);
            break;

        case 'reset_reminders':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->resetReminders($cartId);
            echo json_encode($result);
            break;

        case 'trigger_cron':
            $result = $controller->triggerAutoReminders();
            echo json_encode($result);
            break;


        case 'mark_expired':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->markExpired($cartId);
            echo json_encode($result);
            break;

        case 'delete_cart':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $result = $controller->deleteCart($cartId);
            echo json_encode($result);
            break;

        case 'save_settings':
            error_log('[AbandonedCart] save_settings POST: ' . print_r($_POST, true));
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
            error_log('[AbandonedCart] save_settings data to save: ' . print_r($settingsData, true));
            $result = $controller->saveSettings($settingsData);
            echo json_encode($result);
            break;

        case 'get_settings':
            $result = $controller->getSettings();
            echo json_encode($result);
            break;

        case 'get_cart_logs':
            $cartId = intval($_GET['cart_id'] ?? 0);
            $result = $controller->getCartLogs($cartId);
            echo json_encode($result);
            break;

        case 'get_stats':
            $result = $controller->getDashboard([
                'status' => 'all',
                'search' => '',
                'page'   => 1,
            ]);
            if ($result['success']) {
                echo json_encode(['success' => true, 'data' => $result['data']['stats']]);
            } else {
                echo json_encode($result);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
