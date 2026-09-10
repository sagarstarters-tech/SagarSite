<?php
/**
 * AbandonedCartController
 * Handles admin AJAX requests for cart abandonment recovery.
 */

require_once __DIR__ . '/AbandonedCartService.php';

class AbandonedCartController {
    private $conn;
    private $service;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->service = new AbandonedCartService($conn);
    }

    /**
     * Get abandoned carts list with stats for admin dashboard.
     */
    public function getDashboard($params = []) {
        try {
            $status = $params['status'] ?? 'all';
            $search = $params['search'] ?? '';
            $page   = max(1, intval($params['page'] ?? 1));

            $data = $this->service->getAdminDashboardData($status, $search, $page);

            return ['success' => true, 'data' => $data];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a manual WhatsApp reminder for a specific cart.
     */
    public function sendReminder($cartId, $level = 0, $overridePhone = '') {
        try {
            $cartId = intval($cartId);
            $level  = intval($level);
            if ($cartId <= 0) {
                return ['success' => false, 'error' => 'Invalid cart ID'];
            }

            $result = $this->service->sendManualReminder($cartId, $level, $overridePhone);
            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark or unmark a reminder stage manually.
     */
    public function markStageManual($cartId, $level, $action = 'mark_sent') {
        try {
            $cartId = intval($cartId);
            $level  = intval($level);
            if ($cartId <= 0 || !in_array($level, [1, 2, 3, 4])) {
                return ['success' => false, 'error' => 'Invalid cart ID or reminder level'];
            }

            $ok = $this->service->markReminderStageManual($cartId, $level, $action);
            return $ok
                ? ['success' => true, 'message' => ($action === 'mark_sent' ? "Stage {$level} marked as sent!" : "Stage {$level} reset to unsent!")]
                : ['success' => false, 'error' => 'Failed to update reminder stage status'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get preview of all reminder messages and links for a cart.
     */
    public function getCartPreview($cartId) {
        try {
            $cartId = intval($cartId);
            if ($cartId <= 0) return ['success' => false, 'error' => 'Invalid cart ID'];
            $preview = $this->service->getCartRemindersPreview($cartId);
            return $preview
                ? ['success' => true, 'data' => $preview]
                : ['success' => false, 'error' => 'Cart not found'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reset all reminder stages for a cart back to 0 (NULL).
     */
    public function resetReminders($cartId) {
        try {
            $cartId = intval($cartId);
            if ($cartId <= 0) {
                return ['success' => false, 'error' => 'Invalid cart ID'];
            }

            $result = $this->service->resetReminders($cartId);
            return $result
                ? ['success' => true, 'message' => 'Cart reminder stages reset to 0']
                : ['success' => false, 'error' => 'Failed to reset reminder stages'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    /**
     * Trigger auto reminders processing manually (for testing / admin trigger).
     */
    public function triggerAutoReminders() {
        try {
            $result = $this->service->processAutoReminders();
            return array_merge(['success' => true], $result);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark a specific abandoned cart as expired.
     */
    public function markExpired($cartId) {
        try {
            $cartId = intval($cartId);
            if ($cartId <= 0) {
                return ['success' => false, 'error' => 'Invalid cart ID'];
            }

            $repo = new AbandonedCartRepository($this->conn);
            $result = $repo->markExpired($cartId);

            return $result
                ? ['success' => true, 'message' => 'Cart marked as expired']
                : ['success' => false, 'error' => 'Failed to update cart status'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a specific abandoned cart.
     */
    public function deleteCart($cartId) {
        try {
            $cartId = intval($cartId);
            if ($cartId <= 0) {
                return ['success' => false, 'error' => 'Invalid cart ID'];
            }

            $repo = new AbandonedCartRepository($this->conn);
            $result = $repo->deleteCart($cartId);

            return $result
                ? ['success' => true, 'message' => 'Cart deleted successfully']
                : ['success' => false, 'error' => 'Failed to delete cart'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get settings for the admin settings panel.
     */
    public function getSettings() {
        try {
            return ['success' => true, 'settings' => $this->service->getSettings()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Save settings from the admin panel.
     */
    public function saveSettings($data) {
        try {
            $allowed = [
                'is_enabled', 'reminder_1_delay', 'reminder_2_delay', 'reminder_3_delay', 'reminder_4_delay',
                'reminder_1_message', 'reminder_2_message', 'reminder_3_message', 'reminder_4_message',
                'coupon_discount_percent', 'coupon_validity_hours', 'auto_expire_days',
                'meta_template_1', 'meta_template_2', 'meta_template_3', 'meta_template_4', 'meta_template_lang'
            ];

            $filtered = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $allowed)) {
                    $filtered[$key] = $value;
                }
            }

            if (empty($filtered)) {
                return ['success' => false, 'error' => 'No valid settings provided'];
            }

            $this->service->saveSettings($filtered);
            return ['success' => true, 'message' => 'Settings saved successfully'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get WhatsApp logs for a specific cart.
     */
    public function getCartLogs($cartId) {
        try {
            $cartId = intval($cartId);
            $logs = $this->service->getCartLogs($cartId);
            return ['success' => true, 'logs' => $logs, 'data' => $logs];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get live Meta API cURL log snippet.
     */
    public function getApiLog() {
        try {
            $logFile = dirname(__DIR__) . '/logs/cart_abandonment_whatsapp.log';
            $content = '';
            if (file_exists($logFile)) {
                $lines = file($logFile);
                $slice = array_slice($lines, -80);
                $content = implode('', $slice);
            } else {
                $content = 'No raw API log entries recorded yet.';
            }
            return ['success' => true, 'log' => $content];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
