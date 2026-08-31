<?php
declare(strict_types=1);

namespace CourierModule\Database;

use mysqli;
use PDO;
use Throwable;

/**
 * Class CourierSchemaBootstrap
 * Ensures that all 5 courier integration tables exist in the database.
 * Auto-creates them on first run if missing (e.g. after Git deployment to production/Hostinger).
 */
class CourierSchemaBootstrap
{
    private static bool $checked = false;

    public static function ensureTablesExist($db): void
    {
        if (self::$checked) {
            return;
        }

        try {
            $queries = [
                // 1. courier_integrations
                "CREATE TABLE IF NOT EXISTS `courier_integrations` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `provider_code` VARCHAR(50) NOT NULL UNIQUE,
                  `provider_name` VARCHAR(100) NOT NULL,
                  `api_base_url` VARCHAR(255) NOT NULL,
                  `api_token` TEXT NULL,
                  `api_key` TEXT NULL,
                  `api_secret` TEXT NULL,
                  `is_enabled` TINYINT(1) DEFAULT 0,
                  `is_default` TINYINT(1) DEFAULT 0,
                  `auto_sync_orders` TINYINT(1) DEFAULT 1,
                  `default_warehouse_code` VARCHAR(100) NULL,
                  `pickup_address_id` INT NULL,
                  `default_courier_ship_type` TINYINT DEFAULT 2,
                  `default_express` VARCHAR(10) DEFAULT 'surface',
                  `settings_json` JSON NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 2. courier_warehouses
                "CREATE TABLE IF NOT EXISTS `courier_warehouses` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `warehouse_id` INT NULL,
                  `integration_id` INT NOT NULL,
                  `warehouse_name` VARCHAR(100) NOT NULL,
                  `warehouse_code` VARCHAR(100) NOT NULL,
                  `contact_name` VARCHAR(100) NOT NULL,
                  `contact_phone` VARCHAR(20) NOT NULL,
                  `contact_email` VARCHAR(100) NULL,
                  `address_line1` VARCHAR(255) NOT NULL,
                  `address_line2` VARCHAR(255) NULL,
                  `city` VARCHAR(100) NOT NULL,
                  `state` VARCHAR(100) NOT NULL,
                  `pincode` VARCHAR(10) NOT NULL,
                  `is_default` TINYINT(1) DEFAULT 0,
                  `is_active` TINYINT(1) DEFAULT 1,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  UNIQUE KEY `unique_wh_code` (`integration_id`, `warehouse_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 3. courier_shipments
                "CREATE TABLE IF NOT EXISTS `courier_shipments` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `order_id` INT NOT NULL,
                  `integration_id` INT NOT NULL,
                  `courier_order_id` VARCHAR(100) NULL,
                  `shipment_id` VARCHAR(100) NULL,
                  `awb_number` VARCHAR(100) NOT NULL,
                  `courier_partner_name` VARCHAR(100) NULL,
                  `routing_code` VARCHAR(50) NULL,
                  `client_order_id` VARCHAR(50) NULL,
                  `label_url` TEXT NULL,
                  `manifest_url` TEXT NULL,
                  `pickup_scheduled_date` DATE NULL,
                  `pickup_token` VARCHAR(100) NULL,
                  `shipping_cost_estimated` DECIMAL(10,2) DEFAULT 0.00,
                  `shipping_cost_billed` DECIMAL(10,2) DEFAULT 0.00,
                  `charged_weight_kg` DECIMAL(10,2) DEFAULT 0.00,
                  `collectible_cod_amount` DECIMAL(10,2) DEFAULT 0.00,
                  `courier_status` VARCHAR(50) DEFAULT 'AWB_ASSIGNED',
                  `status_description` TEXT NULL,
                  `last_tracking_sync_at` DATETIME NULL,
                  `raw_creation_response` LONGTEXT NULL,
                  `tracking_history_json` JSON NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY `uniq_order_awb` (`order_id`, `awb_number`),
                  INDEX (`awb_number`),
                  INDEX (`courier_status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 4. courier_queue
                "CREATE TABLE IF NOT EXISTS `courier_queue` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `order_id` INT NOT NULL,
                  `integration_id` INT NOT NULL,
                  `action` VARCHAR(50) NOT NULL DEFAULT 'create_shipment',
                  `status` ENUM('pending', 'processing', 'completed', 'failed', 'failed_permanent') DEFAULT 'pending',
                  `attempts` INT DEFAULT 0,
                  `max_attempts` INT DEFAULT 4,
                  `next_attempt_at` DATETIME NOT NULL,
                  `last_error_message` TEXT NULL,
                  `locked_at` DATETIME NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  INDEX (`status`, `next_attempt_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 5. courier_api_logs
                "CREATE TABLE IF NOT EXISTS `courier_api_logs` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `order_id` INT NULL,
                  `integration_id` INT NULL,
                  `provider_code` VARCHAR(50) NULL,
                  `endpoint_url` VARCHAR(255) NOT NULL,
                  `http_method` VARCHAR(10) NOT NULL,
                  `http_status_code` INT NOT NULL,
                  `request_payload` LONGTEXT NULL,
                  `response_payload` LONGTEXT NULL,
                  `duration_ms` INT NOT NULL,
                  `ip_address` VARCHAR(45) NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  INDEX (`order_id`),
                  INDEX (`http_status_code`),
                  INDEX (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // Initial Seed
                "INSERT INTO `courier_integrations` 
                  (`provider_code`, `provider_name`, `api_base_url`, `is_enabled`, `is_default`, `auto_sync_orders`)
                VALUES 
                  ('bharatship', 'BharatShip', 'https://app.bharatship.com/', 0, 1, 1)
                ON DUPLICATE KEY UPDATE 
                  `provider_name` = VALUES(`provider_name`),
                  `api_base_url` = VALUES(`api_base_url`)"
            ];

            if ($db instanceof mysqli) {
                foreach ($queries as $sql) {
                    $db->query($sql);
                }
            } elseif ($db instanceof PDO) {
                foreach ($queries as $sql) {
                    $db->exec($sql);
                }
            }

            self::$checked = true;
        } catch (Throwable $e) {
            error_log('[CourierBootstrap] Auto-migration warning: ' . $e->getMessage());
        }
    }
}
