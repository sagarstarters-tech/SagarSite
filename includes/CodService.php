<?php
/**
 * ============================================================
 *  CodService — Advanced Cash on Delivery Charge Engine
 *  Location: /includes/CodService.php
 * ============================================================
 *  Handles:
 *  - Product-level COD charge calculation
 *  - Cart-level charge aggregation (highest / sum)
 *  - Free COD threshold check
 *  - Blacklist verification (phone, email, IP)
 *  - Auto-migration of required DB columns/tables
 * ============================================================
 */

class CodService
{
    private $conn;
    private $settings;

    /**
     * @param mysqli $conn      Active MySQLi connection
     * @param array  $settings  Global settings (setting_key => setting_value)
     */
    public function __construct(mysqli $conn, array $settings = [])
    {
        $this->conn = $conn;
        $this->settings = $settings;
        $this->ensureMigration();
    }

    // ── Auto-Migration ────────────────────────────────────────────
    private function ensureMigration(): void
    {
        // products.cod_charge
        $check = $this->conn->query("SHOW COLUMNS FROM products LIKE 'cod_charge'");
        if ($check && $check->num_rows == 0) {
            $this->conn->query("ALTER TABLE products ADD COLUMN cod_charge DECIMAL(10,2) DEFAULT NULL COMMENT 'Per-product COD charge (NULL = use global default)'");
        }

        // orders.cod_charge_amount
        $check = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'cod_charge_amount'");
        if ($check && $check->num_rows == 0) {
            $this->conn->query("ALTER TABLE orders ADD COLUMN cod_charge_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'COD charge applied to this order'");
        }

        // cod_blacklist table
        $check = $this->conn->query("SHOW TABLES LIKE 'cod_blacklist'");
        if ($check && $check->num_rows == 0) {
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS cod_blacklist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type ENUM('phone','email','ip') NOT NULL,
                    value VARCHAR(255) NOT NULL,
                    reason VARCHAR(500) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_type_value (type, value)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Settings keys
        $defaults = [
            'cod_default_charge' => '0',
            'cod_charge_mode'    => 'highest',
            'cod_free_threshold' => '0',
            'cod_force_global'   => '0',
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($this->settings[$key])) {
                $esc_key = $this->conn->real_escape_string($key);
                $esc_val = $this->conn->real_escape_string($value);
                $this->conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$esc_key', '$esc_val') ON DUPLICATE KEY UPDATE setting_key=setting_key");
                $this->settings[$key] = $value;
            }
        }
    }

    // ── Get effective COD charge for a single product ─────────────
    /**
     * Returns the COD charge for a product.
     * Uses the product-specific charge if set, else falls back to the global default.
     * If cod_force_global is enabled, the store default always overrides product charges.
     *
     * @param  array $product  Product row (must include 'cod_charge' key)
     * @return float
     */
    public function getProductCodCharge(array $product): float
    {
        $force_global = isset($this->settings['cod_force_global']) && $this->settings['cod_force_global'] == '1';
        if (!$force_global && isset($product['cod_charge']) && $product['cod_charge'] !== null && $product['cod_charge'] !== '') {
            return (float) $product['cod_charge'];
        }
        return (float) ($this->settings['cod_default_charge'] ?? 0);
    }

    // ── Calculate COD charge for the entire cart ──────────────────
    /**
     * @param  array $cart_items    Array of product rows (each must include 'cod_charge', 'qty', 'price')
     * @param  float $order_subtotal  Cart subtotal (product prices × qty, before shipping)
     * @return array {
     *   'cod_charge'       => float,
     *   'is_free'          => bool,
     *   'free_threshold'   => float,
     *   'charge_mode'      => string,
     *   'product_charges'  => array,
     *   'message'          => string,
     * }
     */
    public function calculateCodCharge(array $cart_items, float $order_subtotal): array
    {
        $charge_mode    = $this->settings['cod_charge_mode'] ?? 'highest';
        $free_threshold = (float) ($this->settings['cod_free_threshold'] ?? 0);

        $product_charges = [];
        foreach ($cart_items as $item) {
            $pc = $this->getProductCodCharge($item);
            $product_charges[] = [
                'product_id' => $item['id'] ?? 0,
                'name'       => $item['name'] ?? '',
                'cod_charge' => $pc,
            ];
        }

        // Aggregate
        if ($charge_mode === 'sum') {
            $total_charge = array_sum(array_column($product_charges, 'cod_charge'));
        } else {
            // 'highest' — default
            $total_charge = !empty($product_charges)
                ? max(array_column($product_charges, 'cod_charge'))
                : 0;
        }

        // Free COD check
        $is_free = false;
        $message = '';
        if ($free_threshold > 0 && $order_subtotal >= $free_threshold) {
            $total_charge = 0;
            $is_free = true;
            $message = 'Free COD Applied';
        } elseif ($total_charge > 0) {
            $message = 'COD Charges: ' . ($this->settings['currency_symbol'] ?? '₹') . number_format($total_charge, 2);
        } else {
            $message = 'No COD charges';
        }

        return [
            'cod_charge'      => round($total_charge, 2),
            'is_free'         => $is_free,
            'free_threshold'  => $free_threshold,
            'charge_mode'     => $charge_mode,
            'product_charges' => $product_charges,
            'message'         => $message,
        ];
    }

    // ── Blacklist Check ──────────────────────────────────────────
    /**
     * Check if any identifier matches the blacklist.
     *
     * @param  string $phone  User phone number
     * @param  string $email  User email
     * @param  string $ip     User IP address
     * @return array { 'is_blacklisted' => bool, 'reason' => string|null, 'matched_type' => string|null }
     */
    public function isBlacklisted(string $phone = '', string $email = '', string $ip = ''): array
    {
        $result = ['is_blacklisted' => false, 'reason' => null, 'matched_type' => null];

        $checks = [];
        if (!empty($phone)) {
            // Normalise phone — strip spaces, dashes, country code prefix
            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            // Check last 10 digits (Indian mobile) and full number
            $checks[] = ['type' => 'phone', 'value' => $clean_phone];
            if (strlen($clean_phone) > 10) {
                $checks[] = ['type' => 'phone', 'value' => substr($clean_phone, -10)];
            }
        }
        if (!empty($email)) {
            $checks[] = ['type' => 'email', 'value' => strtolower(trim($email))];
        }
        if (!empty($ip)) {
            $checks[] = ['type' => 'ip', 'value' => $ip];
        }

        foreach ($checks as $c) {
            $type  = $this->conn->real_escape_string($c['type']);
            $value = $this->conn->real_escape_string($c['value']);
            $q = $this->conn->query("SELECT reason FROM cod_blacklist WHERE type='$type' AND value='$value' LIMIT 1");
            if ($q && $q->num_rows > 0) {
                $row = $q->fetch_assoc();
                return [
                    'is_blacklisted' => true,
                    'reason'         => $row['reason'],
                    'matched_type'   => $c['type'],
                ];
            }
        }

        return $result;
    }

    // ── Convenience: Is COD globally enabled? ────────────────────
    public function isCodEnabled(): bool
    {
        return isset($this->settings['cod_enabled']) && $this->settings['cod_enabled'] == '1';
    }

    // ── Get global settings (read-only) ──────────────────────────
    public function getSettings(): array
    {
        return [
            'cod_default_charge' => (float) ($this->settings['cod_default_charge'] ?? 0),
            'cod_charge_mode'    => $this->settings['cod_charge_mode'] ?? 'highest',
            'cod_free_threshold' => (float) ($this->settings['cod_free_threshold'] ?? 0),
            'cod_force_global'   => ($this->settings['cod_force_global'] ?? '0') === '1',
            'cod_enabled'        => $this->isCodEnabled(),
        ];
    }
}
