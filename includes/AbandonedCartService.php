<?php
/**
 * AbandonedCartService
 * Business logic for cart abandonment recovery.
 * Handles cart tracking, reminder scheduling, WhatsApp sending, and recovery.
 */

require_once __DIR__ . '/AbandonedCartRepository.php';

class AbandonedCartService {
    private $conn;
    private $repo;
    private $settings;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->repo = new AbandonedCartRepository($conn);
        $this->settings = $this->repo->getSettings();
    }

    /**
     * Track current user's cart for abandonment.
     * Called from sync_cart_to_db() — must be fast and fail-safe.
     */
    public function trackCart($userId) {
        if (!($this->settings['is_enabled'] ?? '1')) return;

        $userId = intval($userId);
        if ($userId <= 0) return;

        // Get current cart from session
        $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

        // If cart is empty, remove any active abandoned cart
        if (empty($cart)) {
            $this->repo->removeActive($userId);
            return;
        }

        // Calculate cart total and product names
        $productIds = array_keys($cart);
        $ids = implode(',', array_map('intval', $productIds));
        if (empty($ids)) return;

        $res = $this->conn->query("SELECT id, name, price, image FROM products WHERE id IN ({$ids})");
        if (!$res) return;

        $total = 0;
        $names = [];
        $firstImage = null;

        while ($row = $res->fetch_assoc()) {
            $qty = intval($cart[$row['id']] ?? 0);
            if ($qty > 0) {
                $total += $row['price'] * $qty;
                $names[] = $row['name'] . ' x' . $qty;
                if (!$firstImage && !empty($row['image'])) {
                    $firstImage = $row['image'];
                }
            }
        }

        if (empty($names)) return;

        $productNames = implode(', ', $names);

        $this->repo->createOrUpdate($userId, $cart, $total, $productNames, $firstImage);
    }

    /**
     * Mark abandoned cart as converted when order is placed.
     */
    public function markRecovered($userId) {
        return $this->repo->markConverted(intval($userId));
    }

    /**
     * Process all due automatic reminders (called by cron).
     */
    public function processAutoReminders() {
        if (!($this->settings['is_enabled'] ?? '1')) {
            return ['processed' => 0, 'message' => 'Cart abandonment feature is disabled'];
        }

        $processed = 0;
        $errors = 0;

        // Auto-expire old carts first
        $expireDays = intval($this->settings['auto_expire_days'] ?? 7);
        $this->repo->autoExpire($expireDays);

        // Process each reminder level
        for ($level = 1; $level <= 4; $level++) {
            $delay = intval($this->settings["reminder_{$level}_delay"] ?? 30);
            $dueCarts = $this->repo->getDueReminders($level, $delay);

            foreach ($dueCarts as $cart) {
                try {
                    $result = $this->sendReminder($cart['id'], $level);
                    if ($result) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    error_log("[AbandonedCart] Reminder L{$level} error for cart #{$cart['id']}: " . $e->getMessage());
                }
            }
        }

        return [
            'processed' => $processed,
            'errors'    => $errors,
            'message'   => "Processed {$processed} reminders with {$errors} errors"
        ];
    }

    /**
     * Send a WhatsApp reminder for a specific abandoned cart.
     * @param int $cartId
     * @param int $level Reminder level (1-4)
     * @return bool
     */
    public function sendReminder($cartId, $level = 0) {
        $cart = $this->repo->getById($cartId);
        if (!$cart || $cart['status'] !== 'active') return false;

        // Auto-detect level if not specified
        if ($level <= 0) {
            if (empty($cart['reminder_1_sent'])) $level = 1;
            elseif (empty($cart['reminder_2_sent'])) $level = 2;
            elseif (empty($cart['reminder_3_sent'])) $level = 3;
            elseif (empty($cart['reminder_4_sent'])) $level = 4;
            else return false; // All reminders already sent
        }

        // Generate coupon for level 4
        $couponCode = '';
        $couponDiscount = 0;
        if ($level == 4) {
            $couponDiscount = floatval($this->settings['coupon_discount_percent'] ?? 10);
            $couponCode = $this->generateCouponCode($cartId);
            $this->repo->setCoupon($cartId, $couponCode, $couponDiscount);
        }

        // Build recovery link
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if (strpos($siteUrl, 'http') !== 0) {
            // Fallback: try to build from global_settings
            $res = $this->conn->query("SELECT setting_value FROM global_settings WHERE setting_key = 'site_url' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $siteUrl = rtrim($row['setting_value'], '/');
            }
        }
        $recoveryLink = $siteUrl . '/recover_cart.php?token=' . urlencode($cart['recovery_token']);

        // Build message from template
        $messageTemplate = $this->settings["reminder_{$level}_message"] ?? "Hi {CustomerName}, you left items in your cart. Complete your purchase: {RecoveryLink}";

        $variables = [
            '{CustomerName}' => $cart['customer_name'] ?? 'Customer',
            '{ProductNames}' => $cart['product_names'] ?? 'Your items',
            '{CartTotal}'    => number_format($cart['cart_total'], 2),
            '{RecoveryLink}' => $recoveryLink,
            '{CouponCode}'   => $couponCode,
            '{CouponDiscount}' => $couponDiscount . '%',
        ];

        $message = str_replace(array_keys($variables), array_values($variables), $messageTemplate);

        // Send via WhatsApp API (reuse existing infrastructure)
        $sent = $this->sendWhatsAppMessage($cart['customer_phone'], $message, $cartId, $level, $variables, $messageTemplate);

        if (!empty($sent['success'])) {
            $this->repo->markReminderSent($cartId, $level);
        }

        return $sent;
    }

    /**
     * Send manual reminder from admin panel.
     */
    public function sendManualReminder($cartId) {
        return $this->sendReminder($cartId, 0);
    }

    /**
     * Generate a unique coupon code for cart recovery.
     */
    private function generateCouponCode($cartId) {
        $prefix = 'RECOVER';
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return $prefix . $random;
    }

    /**
     * Send WhatsApp message using the existing Meta Cloud API setup.
     */
    private function sendWhatsAppMessage($phone, $message, $cartId = 0, $level = 0, $variables = [], $messageTemplate = '') {
        // Get WhatsApp settings
        $waSettings = null;
        try {
            $res = $this->conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
            if ($res && $res->num_rows > 0) {
                $waSettings = $res->fetch_assoc();
            }
        } catch (\Throwable $e) {
            error_log("[AbandonedCart] WhatsApp settings fetch error: " . $e->getMessage());
            return false;
        }

        if (!$waSettings || $waSettings['is_enabled'] != 1) {
            error_log("[AbandonedCart] WhatsApp is disabled or not configured");
            return false;
        }

        // Clean phone number (same logic as existing whatsapp_functions.php)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($cleanNumber, '0') === 0) $cleanNumber = ltrim($cleanNumber, '0');
        if (strlen($cleanNumber) == 10) $cleanNumber = '91' . $cleanNumber;

        if (empty($cleanNumber)) {
            error_log("[AbandonedCart] Empty phone for cart #{$cartId}");
            return false;
        }

        // API mode
        if ($waSettings['sending_mode'] === 'api' && !empty($waSettings['api_token']) && !empty($waSettings['phone_number_id'])) {
            $token = trim($waSettings['api_token']);
            $phoneId = trim($waSettings['phone_number_id']);
            $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

            // Check if cart abandonment template is configured for this level
            // Level 0 is manual, default to level 1 template if manual doesn't have one? We use level 1 for manual.
            $tplLevel = $level > 0 ? $level : 1;
            $abandonTemplate = trim($this->settings["meta_template_{$tplLevel}"] ?? '');

            if (!empty($abandonTemplate)) {
                // Template mode
                preg_match_all('/\{(CustomerName|ProductNames|CartTotal|RecoveryLink|CouponCode|CouponDiscount)\}/', $messageTemplate, $matches);
                $params = [];
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $varKey) {
                        $val = (string)($variables[$varKey] ?? '');
                        if ($val === '') $val = ' '; // Meta API doesn't like empty strings for parameters
                        $params[] = ["type" => "text", "text" => $val];
                    }
                }

                $payload = [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $cleanNumber,
                    "type"              => "template",
                    "template"          => [
                        "name"     => $abandonTemplate,
                        "language" => ["code" => trim($this->settings['meta_template_lang'] ?? 'en')],
                        "components" => []
                    ]
                ];
                
                if (!empty($params)) {
                    $payload["template"]["components"][] = [
                        "type" => "body",
                        "parameters" => $params
                    ];
                }
            } else {
                // Text mode fallback
                $payload = [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $cleanNumber,
                    "type"              => "text",
                    "text"              => ["preview_url" => true, "body" => $message]
                ];
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Log the API call
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logEntry = '[' . date('Y-m-d H:i:s') . "] Cart-Abandon Cart#{$cartId} L{$level} HTTP:{$httpCode} To:{$cleanNumber}" . PHP_EOL;
            $logEntry .= "Response: " . $result . PHP_EOL;
            $logEntry .= str_repeat('-', 60) . PHP_EOL;
            @file_put_contents($logDir . '/cart_abandonment_whatsapp.log', $logEntry, FILE_APPEND);

            if ($curlError) {
                error_log("[AbandonedCart] cURL error cart #{$cartId}: {$curlError}");
                $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', "Failed: cURL - " . substr($curlError, 0, 80));
                return ['success' => false, 'error' => "cURL Error: " . $curlError];
            }

            $success = ($httpCode == 200);
            $metaResponse = json_decode($result, true);
            $statusMsg = $success
                ? 'Sent via Meta API (Cart Recovery) ID:' . substr($metaResponse['messages'][0]['id'] ?? 'unknown', 0, 20)
                : 'Failed API: ' . substr($metaResponse['error']['message'] ?? 'Unknown error', 0, 100);

            $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', $statusMsg);

            return ['success' => $success, 'error' => $success ? null : $statusMsg];
        }

        // Web mode fallback — generate wa.me link
        $waLink = 'https://wa.me/' . $cleanNumber . '?text=' . urlencode($message);
        $this->logWhatsApp($cartId, $cleanNumber, $message, 'web', 'Generated wa.me link (manual send)');
        error_log("[AbandonedCart] Web mode link generated for cart #{$cartId}: {$waLink}");

        return ['success' => true, 'link' => $waLink];
    }

    /**
     * Log WhatsApp message to abandoned_cart_logs table.
     */
    private function logWhatsApp($cartId, $phone, $message, $mode, $status) {
        try {
            // Ensure log table exists
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_cart_wa_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cart_id INT NOT NULL,
                customer_number VARCHAR(20),
                message TEXT,
                sending_mode VARCHAR(10),
                status VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cart_id (cart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $this->conn->prepare("INSERT INTO abandoned_cart_wa_logs (cart_id, customer_number, message, sending_mode, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $cartId, $phone, $message, $mode, $status);
                $stmt->execute();
                $stmt->close();
            }
        } catch (\Throwable $e) {
            error_log("[AbandonedCart] Log error: " . $e->getMessage());
        }
    }

    /**
     * Get settings for admin panel.
     */
    public function getSettings() {
        return $this->settings;
    }

    /**
     * Save settings from admin panel.
     */
    public function saveSettings($data) {
        return $this->repo->updateSettings($data);
    }

    /**
     * Get admin dashboard data.
     */
    public function getAdminDashboardData($status = 'all', $search = '', $page = 1) {
        return [
            'stats' => $this->repo->getStats(),
            'carts' => $this->repo->getAdminList($status, $search, $page),
            'settings' => $this->settings,
        ];
    }

    /**
     * Get the WhatsApp logs for a specific abandoned cart.
     */
    public function getCartLogs($cartId) {
        $cartId = intval($cartId);
        $logs = [];
        try {
            $stmt = $this->conn->prepare("SELECT * FROM abandoned_cart_wa_logs WHERE cart_id = ? ORDER BY created_at DESC");
            if ($stmt) {
                $stmt->bind_param("i", $cartId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $logs[] = $row;
                }
                $stmt->close();
            }
        } catch (\Throwable $e) {
            // Table might not exist yet
        }
        return $logs;
    }
}
