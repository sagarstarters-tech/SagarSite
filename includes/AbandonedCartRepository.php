<?php
/**
 * AbandonedCartRepository
 * Database layer for cart abandonment recovery system.
 * Follows existing ScriptRepository pattern with self-healing table creation.
 */

class AbandonedCartRepository {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->ensureTables();
    }

    /**
     * Create tables if they don't exist (safe to run every time).
     */
    private function ensureTables() {
        if (!$this->conn) return;

        try {
            // Main abandoned carts table
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_carts (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                cart_data TEXT NOT NULL,
                cart_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                product_names TEXT,
                product_image VARCHAR(255) DEFAULT NULL,
                status ENUM('active','recovered','expired','converted') NOT NULL DEFAULT 'active',
                reminder_1_sent DATETIME DEFAULT NULL,
                reminder_2_sent DATETIME DEFAULT NULL,
                reminder_3_sent DATETIME DEFAULT NULL,
                reminder_4_sent DATETIME DEFAULT NULL,
                coupon_code VARCHAR(50) DEFAULT NULL,
                coupon_discount DECIMAL(5,2) DEFAULT NULL,
                recovery_token VARCHAR(64) DEFAULT NULL,
                recovered_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_recovery_token (recovery_token),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Settings table for abandonment configuration
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_cart_settings (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Logs table for abandoned cart WhatsApp notifications
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_cart_wa_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cart_id INT NOT NULL,
                customer_number VARCHAR(20),
                message TEXT,
                sending_mode VARCHAR(10),
                status TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cart_id (cart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Insert default settings if not exist
            // Insert default settings if not exist
            $defaults = [
                'is_enabled'              => '1',
                'reminder_1_delay'        => '30',
                'reminder_2_delay'        => '360',
                'reminder_3_delay'        => '1440',
                'reminder_4_delay'        => '4320',
                'reminder_1_message'      => "Hi {CustomerName}! 👋\n\nAapne apna cart chhod diya hai. Aapke cart me hai:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nAbhi checkout karein:\n{RecoveryLink}\n\nAgar koi help chahiye to hume reply karein!",
                'reminder_2_message'      => "Hi {CustomerName}! 🛒\n\nAapke cart me abhi bhi ye items wait kar rahe hain:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nYe items jaldi khatam ho sakte hain! Abhi order karein:\n{RecoveryLink}",
                'reminder_3_message'      => "Hi {CustomerName}! ⏰\n\nLast reminder! Aapke cart items:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nStock limited hai — miss mat karein:\n{RecoveryLink}",
                'reminder_4_message'      => "Hi {CustomerName}! 🎉\n\nHumne aapke liye ek special offer rakha hai!\n\nAapke cart items:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n🎁 Coupon Code: {CouponCode} ({CouponDiscount}% OFF)\n\nAbhi redeem karein:\n{RecoveryLink}\n\nYe offer limited time ke liye hai!",
                'coupon_discount_percent' => '10',
                'coupon_validity_hours'   => '48',
                'auto_expire_days'        => '7',
                'meta_template_1'         => '',
                'meta_template_2'         => '',
                'meta_template_3'         => '',
                'meta_template_4'         => '',
                'meta_template_lang'      => 'en',
                'cron_secret_key'         => 'sagar_cart_recovery_cron_secret',
            ];

            $stmt = $this->conn->prepare("INSERT IGNORE INTO abandoned_cart_settings (setting_key, setting_value) VALUES (?, ?)");
            if ($stmt) {
                foreach ($defaults as $key => $value) {
                    $stmt->bind_param("ss", $key, $value);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Ensure all required columns exist in abandoned_carts table (for existing installations)
            $columnsToEnsure = [
                'product_names'   => "TEXT DEFAULT NULL",
                'product_image'   => "VARCHAR(255) DEFAULT NULL",
                'reminder_1_sent' => "DATETIME DEFAULT NULL",
                'reminder_2_sent' => "DATETIME DEFAULT NULL",
                'reminder_3_sent' => "DATETIME DEFAULT NULL",
                'reminder_4_sent' => "DATETIME DEFAULT NULL",
                'coupon_code'     => "VARCHAR(50) DEFAULT NULL",
                'coupon_discount' => "DECIMAL(5,2) DEFAULT NULL",
                'recovery_token'  => "VARCHAR(64) DEFAULT NULL",
                'recovered_at'    => "DATETIME DEFAULT NULL",
            ];

            foreach ($columnsToEnsure as $colName => $colDef) {
                try {
                    $check = $this->conn->query("SHOW COLUMNS FROM abandoned_carts LIKE '{$colName}'");
                    if ($check && $check->num_rows === 0) {
                        $this->conn->query("ALTER TABLE abandoned_carts ADD COLUMN {$colName} {$colDef}");
                    }
                } catch (\Throwable $e) {}
            }

            // Ensure status column allows all modern status values
            try {
                $this->conn->query("ALTER TABLE abandoned_carts MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            error_log('[AbandonedCartRepository] Table setup warning: ' . $e->getMessage());
        }
    }

    /**
     * Get all settings as key-value array.
     */
    public function getSettings() {
        $settings = [];
        try {
            $res = $this->conn->query("SELECT setting_key, setting_value FROM abandoned_cart_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[AbandonedCartRepository] getSettings error: ' . $e->getMessage());
        }
        return $settings;
    }

    /**
     * Update a single setting.
     */
    public function updateSetting($key, $value) {
        $allowed = [
            'is_enabled', 'reminder_1_delay', 'reminder_2_delay', 'reminder_3_delay', 'reminder_4_delay',
            'reminder_1_message', 'reminder_2_message', 'reminder_3_message', 'reminder_4_message',
            'coupon_discount_percent', 'coupon_validity_hours', 'auto_expire_days',
            'meta_template_1', 'meta_template_2', 'meta_template_3', 'meta_template_4', 'meta_template_lang',
            'cron_secret_key'
        ];
        if (!in_array($key, $allowed)) return false;

        $stmt = $this->conn->prepare("INSERT INTO abandoned_cart_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if (!$stmt) return false;
        $stmt->bind_param("ss", $key, $value);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Bulk update settings.
     */
    public function updateSettings($data) {
        foreach ($data as $key => $value) {
            $this->updateSetting($key, $value);
        }
        return true;
    }

    /**
     * Create or update an abandoned cart record for a user.
     * If user already has an active abandoned cart, update it.
     */
    public function createOrUpdate($userId, $cartData, $cartTotal, $productNames, $productImage = null) {
        $userId = intval($userId);

        if (is_array($cartData)) {
            $cartJson = json_encode($cartData);
        } else {
            $decoded = json_decode((string)$cartData, true);
            $cartJson = is_array($decoded) && !empty($decoded) ? json_encode($decoded) : (string)$cartData;
        }
        $token = bin2hex(random_bytes(32));

        // Check if active record exists
        $stmt = $this->conn->prepare("SELECT id, cart_data FROM abandoned_carts WHERE user_id = ? AND status = 'active' LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // 1. If cart contents have NOT changed, do NOT touch database or update timestamp
            if ($existing['cart_data'] === $cartJson) {
                return $existing['id'];
            }

            // 2. Cart content changed (user added/removed items) -> Update record & reset reminder timers
            $stmt = $this->conn->prepare("UPDATE abandoned_carts SET cart_data = ?, cart_total = ?, product_names = ?, product_image = ?, recovery_token = ?, reminder_1_sent = NULL, reminder_2_sent = NULL, reminder_3_sent = NULL, reminder_4_sent = NULL, updated_at = NOW() WHERE id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("sdsssi", $cartJson, $cartTotal, $productNames, $productImage, $token, $existing['id']);
            $result = $stmt->execute();
            $stmt->close();
            return $result ? $existing['id'] : false;
        } else {
            // Create new active abandoned cart
            $stmt = $this->conn->prepare("INSERT INTO abandoned_carts (user_id, cart_data, cart_total, product_names, product_image, status, recovery_token) VALUES (?, ?, ?, ?, ?, 'active', ?)");
            if (!$stmt) return false;
            $stmt->bind_param("idssss", $userId, $cartJson, $cartTotal, $productNames, $productImage, $token);
            $result = $stmt->execute();
            $id = $this->conn->insert_id;
            $stmt->close();
            return $result ? $id : false;
        }
    }

    /**
     * Remove active abandoned cart when cart is emptied.
     */
    public function removeActive($userId) {
        $userId = intval($userId);
        $stmt = $this->conn->prepare("UPDATE abandoned_carts SET status = 'expired' WHERE user_id = ? AND status = 'active'");
        if (!$stmt) return false;
        $stmt->bind_param("i", $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Mark an abandoned cart as converted (order placed).
     */
    public function markConverted($userId) {
        $userId = intval($userId);
        $stmt = $this->conn->prepare("UPDATE abandoned_carts SET status = 'converted', recovered_at = NOW() WHERE user_id = ? AND status = 'active'");
        if (!$stmt) return false;
        $stmt->bind_param("i", $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Mark a specific cart as expired.
     */
    public function markExpired($cartId) {
        $cartId = intval($cartId);
        $stmt = $this->conn->prepare("UPDATE abandoned_carts SET status = 'expired' WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $cartId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Delete an abandoned cart record.
     */
    public function deleteCart($cartId) {
        $cartId = intval($cartId);
        $stmt = $this->conn->prepare("DELETE FROM abandoned_carts WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $cartId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get abandoned carts due for a specific reminder level.
     * @param int $level Reminder level (1-4)
     * @param int $delayMinutes Delay in minutes
     */
    public function getDueReminders($level, $delayMinutes) {
        $col = "reminder_{$level}_sent";
        if (!in_array($level, [1, 2, 3, 4])) return [];

        $prevColCheck = "";
        $timeBase = "ac.updated_at"; // Level 1: delay from when cart was last updated/abandoned

        if ($level > 1) {
            $prevCol = "reminder_" . ($level - 1) . "_sent";
            $prevColCheck = " AND ac.{$prevCol} IS NOT NULL";
            $timeBase = "ac.{$prevCol}"; // Level 2, 3, 4: delay from when PREVIOUS reminder was sent
        }

        $sql = "SELECT ac.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
                FROM abandoned_carts ac
                LEFT JOIN users u ON ac.user_id = u.id
                WHERE ac.status = 'active'
                  AND ac.{$col} IS NULL
                  {$prevColCheck}
                  AND {$timeBase} <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY ac.created_at ASC
                LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $delayMinutes);
        $stmt->execute();
        $res = $stmt->get_result();
        $carts = [];
        while ($row = $res->fetch_assoc()) {
            $carts[] = $row;
        }
        $stmt->close();
        return $carts;
    }


    /**
     * Mark a specific reminder as sent.
     */

    public function markReminderSent($cartId, $level) {
        if (!in_array($level, [1, 2, 3, 4])) return false;
        $col = "reminder_{$level}_sent";
        $cartId = intval($cartId);

        $stmt = $this->conn->prepare("UPDATE abandoned_carts SET {$col} = NOW() WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $cartId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Set coupon code for a cart (4th reminder).
     */
    public function setCoupon($cartId, $couponCode, $discount) {
        $cartId = intval($cartId);
        $stmt = $this->conn->prepare("UPDATE abandoned_carts SET coupon_code = ?, coupon_discount = ? WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("sdi", $couponCode, $discount, $cartId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Find abandoned cart by recovery token.
     */
    public function findByToken($token) {
        $stmt = $this->conn->prepare("SELECT ac.*, u.name AS customer_name, u.phone AS customer_phone
                FROM abandoned_carts ac
                LEFT JOIN users u ON ac.user_id = u.id
                WHERE ac.recovery_token = ?
                LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $cart = $res->fetch_assoc();
        $stmt->close();
        return $cart ?: null;
    }

    /**
     * Get a single abandoned cart by ID.
     */
    public function getById($cartId) {
        $cartId = intval($cartId);
        $stmt = $this->conn->prepare("SELECT ac.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
                FROM abandoned_carts ac
                LEFT JOIN users u ON ac.user_id = u.id
                WHERE ac.id = ?
                LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param("i", $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cart = $res->fetch_assoc();
        $stmt->close();
        return $cart ?: null;
    }

    /**
     * Get paginated list of abandoned carts for admin.
     */
    public function getAdminList($status = 'all', $search = '', $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $where = "1=1";
        $params = [];
        $types = "";

        if ($status !== 'all' && in_array($status, ['active', 'recovered', 'expired', 'converted'])) {
            $where .= " AND ac.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if (!empty($search)) {
            $where .= " AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM abandoned_carts ac LEFT JOIN users u ON ac.user_id = u.id WHERE {$where}";
        $countStmt = $this->conn->prepare($countSql);
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Fetch records
        $sql = "SELECT ac.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
                FROM abandoned_carts ac
                LEFT JOIN users u ON ac.user_id = u.id
                WHERE {$where}
                ORDER BY ac.created_at DESC
                LIMIT ?, ?";

        $params[] = $offset;
        $params[] = $perPage;
        $types .= "ii";

        $stmt = $this->conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $carts = [];
        while ($row = $res->fetch_assoc()) {
            $step = 0;
            if (!empty($row['reminder_1_sent'])) $step = 1;
            if (!empty($row['reminder_2_sent'])) $step = 2;
            if (!empty($row['reminder_3_sent'])) $step = 3;
            if (!empty($row['reminder_4_sent'])) $step = 4;
            $row['reminder_step'] = $step;
            $carts[] = $row;
        }
        $stmt->close();

        return [
            'data'       => $carts,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get dashboard statistics.
     */
    public function getStats() {
        $stats = [
            'total_abandoned'    => 0,
            'active_carts'       => 0,
            'converted'          => 0,
            'expired'            => 0,
            'recovery_rate'      => 0,
            'total_lost_value'   => 0,
            'total_recovered_value' => 0,
            'pending_reminders'  => 0,
            'today_abandoned'    => 0,
        ];

        try {
            // Total counts
            $res = $this->conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_count,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_count,
                SUM(CASE WHEN status = 'active' THEN cart_total ELSE 0 END) as lost_value,
                SUM(CASE WHEN status = 'converted' THEN cart_total ELSE 0 END) as recovered_value,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
            FROM abandoned_carts");

            if ($res) {
                $row = $res->fetch_assoc();
                $stats['total_abandoned']       = intval($row['total']);
                $stats['active_carts']          = intval($row['active_count']);
                $stats['converted']             = intval($row['converted_count']);
                $stats['expired']               = intval($row['expired_count']);
                $stats['total_lost_value']      = floatval($row['lost_value']);
                $stats['total_recovered_value'] = floatval($row['recovered_value']);
                $stats['today_abandoned']       = intval($row['today_count']);

                $totalFinished = $stats['converted'] + $stats['expired'];
                if ($totalFinished > 0) {
                    $stats['recovery_rate'] = round(($stats['converted'] / $totalFinished) * 100, 1);
                }
            }

            // Pending reminders (active carts with unsent reminders)
            $res2 = $this->conn->query("SELECT COUNT(*) as pending FROM abandoned_carts 
                WHERE status = 'active' 
                  AND (reminder_1_sent IS NULL OR reminder_2_sent IS NULL OR reminder_3_sent IS NULL OR reminder_4_sent IS NULL)");
            if ($res2) {
                $stats['pending_reminders'] = intval($res2->fetch_assoc()['pending']);
            }

        } catch (\Throwable $e) {
            error_log('[AbandonedCartRepository] getStats error: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Auto-expire old abandoned carts.
     */
    public function autoExpire($days = 7) {
        $days = intval($days);
        try {
            $this->conn->query("UPDATE abandoned_carts SET status = 'expired' WHERE status = 'active' AND created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
        } catch (\Throwable $e) {
            error_log('[AbandonedCartRepository] autoExpire error: ' . $e->getMessage());
        }
    }
}
