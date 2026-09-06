-- ============================================================
-- Clean E-Commerce Database Starter Schema & Demo Data
-- Generated for Clean Buyer Installation
-- Default Admin: admin@example.com / Admin@123
-- Total Tables: 77
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for `abandoned_cart_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `abandoned_cart_settings`;
CREATE TABLE `abandoned_cart_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `abandoned_cart_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES 
('1', 'is_enabled', '1', '2026-08-05 20:43:58'),
('2', 'reminder_1_delay', '30', '2026-08-05 20:43:58'),
('3', 'reminder_2_delay', '360', '2026-08-05 20:43:58'),
('4', 'reminder_3_delay', '1440', '2026-08-05 20:43:58'),
('5', 'reminder_4_delay', '4320', '2026-08-05 20:43:58'),
('6', 'reminder_1_message', 'Hi {CustomerName}! 👋\n\nAapne apna cart chhod diya hai. Aapke cart me hai:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nAbhi checkout karein:\n{RecoveryLink}\n\nAgar koi help chahiye to hume reply karein!', '2026-08-05 20:43:58'),
('7', 'reminder_2_message', 'Hi {CustomerName}! 🛒\n\nAapke cart me abhi bhi ye items wait kar rahe hain:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nYe items jaldi khatam ho sakte hain! Abhi order karein:\n{RecoveryLink}', '2026-08-05 20:43:58'),
('8', 'reminder_3_message', 'Hi {CustomerName}! ⏰\n\nLast reminder! Aapke cart items:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n\nStock limited hai — miss mat karein:\n{RecoveryLink}', '2026-08-05 20:43:58'),
('9', 'reminder_4_message', 'Hi {CustomerName}! 🎉\n\nHumne aapke liye ek special offer rakha hai!\n\nAapke cart items:\n{ProductNames}\n\n💰 Total: ₹{CartTotal}\n🎁 Coupon Code: {CouponCode} ({CouponDiscount}% OFF)\n\nAbhi redeem karein:\n{RecoveryLink}\n\nYe offer limited time ke liye hai!', '2026-08-05 20:43:58'),
('10', 'coupon_discount_percent', '10', '2026-08-05 20:43:58'),
('11', 'coupon_validity_hours', '48', '2026-08-05 20:43:58'),
('12', 'auto_expire_days', '7', '2026-08-05 20:43:58'),
('13', 'meta_template_1', '', '2026-08-05 20:43:58'),
('14', 'meta_template_2', '', '2026-08-05 20:43:58'),
('15', 'meta_template_3', '', '2026-08-05 20:43:58'),
('16', 'meta_template_4', '', '2026-08-05 20:43:58'),
('17', 'meta_template_lang', 'en', '2026-08-05 20:43:58'),
('18', 'cron_secret_key', 'sagar_cart_recovery_cron_secret', '2026-08-05 20:43:58'),
('145', 'last_auto_run', '1788668281', '2026-09-06 09:48:01');

-- --------------------------------------------------------
-- Table structure for `abandoned_cart_wa_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `abandoned_cart_wa_logs`;
CREATE TABLE `abandoned_cart_wa_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cart_id` int(11) NOT NULL,
  `customer_number` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sending_mode` varchar(10) DEFAULT NULL,
  `status` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_id` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `abandoned_carts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `abandoned_carts`;
CREATE TABLE `abandoned_carts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `cart_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Full cart snapshot with product details' CHECK (json_valid(`cart_data`)),
  `cart_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(10,2) DEFAULT 0.00,
  `restore_token` varchar(64) DEFAULT NULL,
  `restore_token_expiry` datetime DEFAULT NULL,
  `checkout_url` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `reminder_count` tinyint(4) NOT NULL DEFAULT 0,
  `last_reminder_at` datetime DEFAULT NULL,
  `recovered_order_id` int(11) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `product_names` text DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `reminder_1_sent` datetime DEFAULT NULL,
  `reminder_2_sent` datetime DEFAULT NULL,
  `reminder_3_sent` datetime DEFAULT NULL,
  `reminder_4_sent` datetime DEFAULT NULL,
  `recovery_token` varchar(64) DEFAULT NULL,
  `recovered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_restore_token` (`restore_token`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reminder_status` (`reminder_count`,`status`),
  KEY `idx_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `analytics_page_views`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `analytics_page_views`;
CREATE TABLE `analytics_page_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint(20) unsigned NOT NULL,
  `page_url` varchar(500) NOT NULL,
  `page_title` varchar(300) DEFAULT NULL,
  `referrer` varchar(1000) DEFAULT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `session_id` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_viewed_at` (`viewed_at`),
  KEY `idx_visitor_id` (`visitor_id`),
  KEY `idx_page_url_viewed` (`page_url`(191),`viewed_at`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `analytics_product_views`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `analytics_product_views`;
CREATE TABLE `analytics_product_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL COMMENT 'Denormalized for fast reporting',
  `referrer` varchar(1000) DEFAULT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `session_id` char(32) DEFAULT NULL,
  `from_search` varchar(255) DEFAULT NULL COMMENT 'Search query that led to this product view',
  PRIMARY KEY (`id`),
  KEY `idx_viewed_at` (`viewed_at`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_visitor_id` (`visitor_id`),
  KEY `idx_product_viewed` (`product_id`,`viewed_at`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `analytics_searches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `analytics_searches`;
CREATE TABLE `analytics_searches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint(20) unsigned NOT NULL,
  `search_query` varchar(255) NOT NULL,
  `result_count` int(11) DEFAULT 0,
  `searched_at` datetime NOT NULL DEFAULT current_timestamp(),
  `session_id` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_searched_at` (`searched_at`),
  KEY `idx_search_query_at` (`search_query`(191),`searched_at`),
  KEY `idx_visitor_id` (`visitor_id`),
  KEY `idx_result_count` (`result_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `analytics_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `analytics_settings`;
CREATE TABLE `analytics_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `analytics_visitors`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `analytics_visitors`;
CREATE TABLE `analytics_visitors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_uid` char(32) NOT NULL COMMENT 'First-party anonymous cookie ID',
  `session_id` char(32) NOT NULL COMMENT 'Per-session identifier',
  `first_visit` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp(),
  `landing_page` varchar(500) DEFAULT NULL,
  `referrer` varchar(1000) DEFAULT NULL,
  `traffic_source` varchar(50) DEFAULT 'direct' COMMENT 'direct/google/facebook/instagram/youtube/search_engine/referral/other',
  `device_type` varchar(20) DEFAULT NULL COMMENT 'mobile/desktop/tablet',
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 hash of IP, never raw IP',
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_uid` (`visitor_uid`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_last_activity` (`last_activity`),
  KEY `idx_first_visit` (`first_visit`),
  KEY `idx_country_region` (`country`,`region`),
  KEY `idx_traffic_source` (`traffic_source`),
  KEY `idx_device_type` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `backup_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `backup_settings`;
CREATE TABLE `backup_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `banners`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) NOT NULL,
  `heading` varchar(255) DEFAULT NULL,
  `subheading` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `cart_abandonment_reminders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cart_abandonment_reminders`;
CREATE TABLE `cart_abandonment_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `abandoned_cart_id` int(11) NOT NULL,
  `reminder_number` tinyint(4) NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `message_content` text DEFAULT NULL,
  `whatsapp_status` varchar(50) DEFAULT 'pending',
  `whatsapp_message_id` varchar(100) DEFAULT NULL,
  `api_response` text DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `retry_count` tinyint(4) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_id` (`abandoned_cart_id`),
  KEY `idx_status` (`whatsapp_status`),
  KEY `idx_sent_at` (`sent_at`),
  CONSTRAINT `cart_abandonment_reminders_ibfk_1` FOREIGN KEY (`abandoned_cart_id`) REFERENCES `abandoned_carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `cart_abandonment_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cart_abandonment_settings`;
CREATE TABLE `cart_abandonment_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `is_enabled` tinyint(4) NOT NULL DEFAULT 1,
  `abandonment_threshold_minutes` int(11) NOT NULL DEFAULT 30,
  `reminder_1_delay_minutes` int(11) NOT NULL DEFAULT 30,
  `reminder_2_delay_minutes` int(11) NOT NULL DEFAULT 360,
  `reminder_3_delay_minutes` int(11) NOT NULL DEFAULT 1440,
  `reminder_4_delay_minutes` int(11) NOT NULL DEFAULT 4320,
  `max_reminders` tinyint(4) NOT NULL DEFAULT 4,
  `token_expiry_days` int(11) NOT NULL DEFAULT 7,
  `recovery_coupon_code` varchar(50) DEFAULT NULL,
  `recovery_coupon_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `use_template_mode` tinyint(4) NOT NULL DEFAULT 0,
  `template_reminder_1` varchar(100) DEFAULT 'cart_reminder_1',
  `template_reminder_2` varchar(100) DEFAULT 'cart_reminder_2',
  `template_reminder_3` varchar(100) DEFAULT 'cart_reminder_3',
  `template_reminder_4` varchar(100) DEFAULT 'cart_reminder_coupon',
  `text_template_1` text DEFAULT NULL,
  `text_template_2` text DEFAULT NULL,
  `text_template_3` text DEFAULT NULL,
  `text_template_4` text DEFAULT NULL,
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `retry_failed_messages` tinyint(4) NOT NULL DEFAULT 1,
  `max_retries` tinyint(4) NOT NULL DEFAULT 3,
  `notify_admin_on_failure` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `created_at`) VALUES ('1', 'Rings', 'rings', '', '2026-08-06 10:16:36');
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `created_at`) VALUES ('2', 'Necklaces', 'necklaces', '', '2026-08-06 10:16:36');
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `created_at`) VALUES ('3', 'Earrings', 'earrings', '', '2026-08-06 10:16:36');
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `created_at`) VALUES ('4', 'Bracelets', 'bracelets', '', '2026-08-06 10:16:36');

-- --------------------------------------------------------
-- Table structure for `chatbot_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chatbot_logs`;
CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(100) NOT NULL,
  `user_ip` varchar(50) DEFAULT NULL,
  `user_message` text NOT NULL,
  `bot_response` longtext NOT NULL,
  `intent` varchar(50) DEFAULT 'general',
  `provider_used` varchar(50) DEFAULT 'hybrid',
  `response_time_ms` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `cod_blacklist`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cod_blacklist`;
CREATE TABLE `cod_blacklist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('phone','email','ip') NOT NULL COMMENT 'Blacklist entry type',
  `value` varchar(255) NOT NULL COMMENT 'Phone number, email, or IP address',
  `reason` varchar(500) DEFAULT NULL COMMENT 'Admin note / reason for blacklisting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_value` (`type`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='COD blacklist for fraud prevention';

-- --------------------------------------------------------
-- Table structure for `courier_api_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_api_logs`;
CREATE TABLE `courier_api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `integration_id` int(11) DEFAULT NULL,
  `provider_code` varchar(50) DEFAULT NULL,
  `endpoint_url` varchar(255) NOT NULL,
  `http_method` varchar(10) NOT NULL,
  `http_status_code` int(11) NOT NULL,
  `request_payload` longtext DEFAULT NULL,
  `response_payload` longtext DEFAULT NULL,
  `duration_ms` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `http_status_code` (`http_status_code`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `courier_companies`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_companies`;
CREATE TABLE `courier_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `tracking_url_base` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `courier_companies` (`id`, `name`, `tracking_url_base`, `is_active`) VALUES 
('1', 'Delhivery', 'https://www.delhivery.com/track/package/', '1'),
('2', 'Blue Dart', 'https://www.bluedart.com/tracking?track=', '1'),
('3', 'Ecom Express', 'https://ecomexpress.in/tracking/?awb=', '1'),
('4', 'Xpressbees', 'https://www.xpressbees.com/track?awb=', '1'),
('5', 'DTDC', 'https://www.dtdc.in/tracking/shipment-tracking.asp', '1'),
('6', 'India Post', 'https://www.indiapost.gov.in/', '1'),
('7', 'Ekart Logistics', 'https://ekartlogistics.com/track/', '1'),
('8', 'Shadowfax', 'https://track.shadowfax.in/track?order=', '1'),
('9', 'Safexpress', 'https://www.safexpress.com/', '1');

-- --------------------------------------------------------
-- Table structure for `courier_integrations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_integrations`;
CREATE TABLE `courier_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_code` varchar(50) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `api_base_url` varchar(255) NOT NULL,
  `api_token` text DEFAULT NULL,
  `api_key` text DEFAULT NULL,
  `api_secret` text DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `auto_sync_orders` tinyint(1) DEFAULT 1,
  `default_warehouse_code` varchar(100) DEFAULT NULL,
  `pickup_address_id` int(11) DEFAULT NULL,
  `default_courier_ship_type` tinyint(4) DEFAULT 2,
  `default_express` varchar(10) DEFAULT 'surface',
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_code` (`provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `courier_queue`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_queue`;
CREATE TABLE `courier_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `integration_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL DEFAULT 'create_shipment',
  `status` enum('pending','processing','completed','failed','failed_permanent') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 4,
  `next_attempt_at` datetime NOT NULL,
  `last_error_message` text DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `status` (`status`,`next_attempt_at`),
  CONSTRAINT `courier_queue_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `courier_shipments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_shipments`;
CREATE TABLE `courier_shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `integration_id` int(11) NOT NULL,
  `courier_order_id` varchar(100) DEFAULT NULL,
  `shipment_id` varchar(100) DEFAULT NULL,
  `awb_number` varchar(100) NOT NULL,
  `courier_partner_name` varchar(100) DEFAULT NULL,
  `routing_code` varchar(50) DEFAULT NULL,
  `client_order_id` varchar(50) DEFAULT NULL,
  `label_url` text DEFAULT NULL,
  `manifest_url` text DEFAULT NULL,
  `pickup_scheduled_date` date DEFAULT NULL,
  `pickup_token` varchar(100) DEFAULT NULL,
  `shipping_cost_estimated` decimal(10,2) DEFAULT 0.00,
  `shipping_cost_billed` decimal(10,2) DEFAULT 0.00,
  `charged_weight_kg` decimal(10,2) DEFAULT 0.00,
  `collectible_cod_amount` decimal(10,2) DEFAULT 0.00,
  `courier_status` varchar(50) DEFAULT 'AWB_ASSIGNED',
  `status_description` text DEFAULT NULL,
  `last_tracking_sync_at` datetime DEFAULT NULL,
  `raw_creation_response` longtext DEFAULT NULL,
  `tracking_history_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tracking_history_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_awb` (`order_id`,`awb_number`),
  KEY `integration_id` (`integration_id`),
  KEY `awb_number` (`awb_number`),
  KEY `courier_status` (`courier_status`),
  CONSTRAINT `courier_shipments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courier_shipments_ibfk_2` FOREIGN KEY (`integration_id`) REFERENCES `courier_integrations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `courier_warehouses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courier_warehouses`;
CREATE TABLE `courier_warehouses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) DEFAULT NULL,
  `integration_id` int(11) NOT NULL,
  `warehouse_name` varchar(100) NOT NULL,
  `warehouse_code` varchar(100) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wh_code` (`integration_id`,`warehouse_code`),
  CONSTRAINT `courier_warehouses_ibfk_1` FOREIGN KEY (`integration_id`) REFERENCES `courier_integrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `custom_scripts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `custom_scripts`;
CREATE TABLE `custom_scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_code` text DEFAULT NULL,
  `footer_code` text DEFAULT NULL,
  `google_verification` varchar(255) DEFAULT NULL,
  `bing_verification` varchar(255) DEFAULT NULL,
  `custom_verification` text DEFAULT NULL,
  `txt_instructions` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `documents`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `email_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `email_type` varchar(50) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `email_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tpl_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `placeholders` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tpl_key` (`tpl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `email_templates` (`id`, `tpl_key`, `label`, `subject`, `body`, `placeholders`, `updated_at`) VALUES 
('1', 'signup_verification', 'Signup Verification', 'Verify Your Account at Sagar Starter\'s', '<h2>Welcome to Sagar Starter\'s!</h2>\n<p>Dear {name},</p>\n<p>Thank you for registering. Please click the link below to verify your email address:</p>\n<p><a href=\'{verify_link}\'>{verify_link}</a></p>\n<br>\n<p>If you didn\'t request this, ignore this email.</p>', '{name}, {verify_link}', '2026-03-19 14:09:14'),
('2', 'password_reset', 'Password Reset Request', 'Password Reset Request', '<h3>Password Reset</h3>\n<p>Hi {name},</p>\n<p>You requested a password reset. Click the link below to set a new password. This link will expire in 1 hour.</p>\n<p><a href=\'{reset_link}\'>{reset_link}</a></p>\n<p>If you didn\'t request this, you can safely ignore this email.</p>', '{name}, {reset_link}', '2026-03-19 14:09:14'),
('3', 'order_confirmation_customer', 'Order Confirmation (Customer)', 'Your Order #{order_id} Has Been Confirmed', '<div style=\"background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;\">\n    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); line-height: 1.5;\">\n        <!-- Top Brand Bar -->\n        <div style=\"background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 20px 25px; text-align: left; border-bottom: 1px solid #334155;\">\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n                <tr>\n                    <td style=\"vertical-align: middle;\">\n                        <div style=\"font-size: 18px; font-weight: 800; letter-spacing: 0.5px; color: #ffffff;\">\n                            SAGAR <span style=\"color: #38bdf8;\">STARTER\'S</span>\n                        </div>\n                        <div style=\"font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;\">\n                            Industrial & Agricultural Starters\n                        </div>\n                    </td>\n                    <td style=\"text-align: right; vertical-align: middle;\">\n                        <span style=\"display: inline-block; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;\">\n                            ✓ Verified Order\n                        </span>\n                    </td>\n                </tr>\n            </table>\n        </div>\n\n        <!-- Hero Confirmation Banner -->\n        <div style=\"background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px 25px; text-align: center; color: #ffffff;\">\n            <div style=\"display: inline-block; width: 50px; height: 50px; line-height: 48px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 24px; margin-bottom: 10px; border: 2px solid rgba(255, 255, 255, 0.35);\">\n                ✓\n            </div>\n            <h2 style=\"margin: 0 0 6px; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;\">Order Confirmed!</h2>\n            <p style=\"margin: 0; font-size: 14px; color: #e0f2fe;\">Thank you for your purchase. We are preparing your order for dispatch.</p>\n        </div>\n\n        <!-- Main Content -->\n        <div style=\"padding: 26px;\">\n            <p style=\"font-size: 15px; color: #1e293b; margin: 0 0 12px;\">\n                Hello <strong>{customer_name}</strong>,\n            </p>\n            <p style=\"font-size: 14px; color: #475569; margin: 0 0 20px; line-height: 1.6;\">\n                We are pleased to confirm your order details below. Our technical team is inspecting and packing your unit with utmost care. You will receive live courier tracking as soon as it ships.\n            </p>\n\n            <!-- Order Highlights Grid -->\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 24px; overflow: hidden;\">\n                <tr>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Number</span>\n                        <strong style=\"font-size: 16px; color: #0284c7;\">#{order_id}</strong>\n                    </td>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Date</span>\n                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{date_str}</span>\n                    </td>\n                </tr>\n                <tr>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-right: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Payment Method</span>\n                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{payment_method}</span>\n                    </td>\n                    <td width=\"50%\" style=\"padding: 13px 16px;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order Status</span>\n                        <span style=\"display: inline-block; background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 12px;\">\n                            Pending / In Progress\n                        </span>\n                    </td>\n                </tr>\n            </table>\n\n            <!-- Order Items Section -->\n            <div style=\"margin-bottom: 24px;\">\n                <div style=\"font-size: 13px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;\">\n                    📦 Order Summary\n                </div>\n                {items_table}\n            </div>\n\n            <!-- Call To Actions -->\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin: 24px 0 10px;\">\n                <tr>\n                    <td align=\"center\">\n                        <a href=\"https://wa.me/918573934013?text=Hi%20Sagar%20Starters,%20I%20have%20a%20query%20about%20Order%20%23{order_id}\" style=\"display: inline-block; background-color: #25d366; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 50px; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);\">\n                            💬 WhatsApp Support\n                        </a>\n                    </td>\n                </tr>\n            </table>\n\n            <!-- Guarantee Box -->\n            <div style=\"background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-top: 20px;\">\n                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n                    <tr>\n                        <td width=\"28\" style=\"vertical-align: top; font-size: 18px;\">🛡️</td>\n                        <td style=\"padding-left: 8px; vertical-align: top;\">\n                            <div style=\"font-size: 13px; font-weight: 700; color: #166534;\">Genuine Manufacturer Assurance</div>\n                            <div style=\"font-size: 12px; color: #15803d; margin-top: 2px;\">All motor starters are 100% factory inspected and tested. Have questions? Reply directly to this email.</div>\n                        </td>\n                    </tr>\n                </table>\n            </div>\n        </div>\n\n        <!-- Footer -->\n        <div style=\"background-color: #0f172a; padding: 20px 25px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #1e293b;\">\n            <p style=\"margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #f8fafc;\">Sagar Starter\'s Support Team</p>\n            <p style=\"margin: 0 0 10px; color: #64748b;\">\n                Email: <a href=\"mailto:sagarstarters@gmail.com\" style=\"color: #38bdf8; text-decoration: none;\">sagarstarters@gmail.com</a> &nbsp;|&nbsp; Phone: <a href=\"tel:+918573934013\" style=\"color: #38bdf8; text-decoration: none;\">+91 85739 34013</a>\n            </p>\n            <p style=\"margin: 0; font-size: 11px; color: #475569;\">\n                &copy; {current_year} Sagar Starter\'s. All rights reserved.\n            </p>\n        </div>\n    </div>\n</div>', '{customer_name}, {order_id}, {date_str}, {payment_method}, {items_table}, {total_amount}, {site_url}, {current_year}', '2026-09-04 10:53:14'),
('4', 'order_confirmation_admin', 'Order Notification (Admin)', 'New Order Received – Order #{order_id}', '<div style=\"background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;\">\n    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); line-height: 1.5;\">\n        <!-- Top Admin Bar -->\n        <div style=\"background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); padding: 18px 25px; text-align: left; border-bottom: 1px solid #047857;\">\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n                <tr>\n                    <td style=\"vertical-align: middle;\">\n                        <div style=\"font-size: 17px; font-weight: 800; color: #ffffff;\">\n                            SAGAR <span style=\"color: #34d399;\">STARTER\'S</span> ADMIN\n                        </div>\n                        <div style=\"font-size: 11px; color: #a7f3d0; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;\">\n                            New Order Placement Alert\n                        </div>\n                    </td>\n                    <td style=\"text-align: right; vertical-align: middle;\">\n                        <span style=\"display: inline-block; background: rgba(52, 211, 153, 0.2); border: 1px solid #34d399; color: #ffffff; padding: 3px 11px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;\">\n                            ⚡ Action Required\n                        </span>\n                    </td>\n                </tr>\n            </table>\n        </div>\n\n        <!-- Alert Hero Banner -->\n        <div style=\"background: linear-gradient(135deg, #059669 0%, #047857 100%); padding: 26px 25px; text-align: center; color: #ffffff;\">\n            <div style=\"display: inline-block; width: 46px; height: 46px; line-height: 44px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); font-size: 22px; margin-bottom: 8px; border: 2px solid rgba(255, 255, 255, 0.35);\">\n                🛒\n            </div>\n            <h2 style=\"margin: 0 0 4px; font-size: 23px; font-weight: 800; color: #ffffff;\">New Order Received!</h2>\n            <p style=\"margin: 0; font-size: 14px; color: #d1fae5;\">Order #{order_id} has been placed and requires fulfillment.</p>\n        </div>\n\n        <!-- Main Content -->\n        <div style=\"padding: 26px;\">\n            <!-- Order Meta Grid -->\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 24px; overflow: hidden;\">\n                <tr>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Order ID</span>\n                        <strong style=\"font-size: 16px; color: #059669;\">#{order_id}</strong>\n                    </td>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Total Amount</span>\n                        <strong style=\"font-size: 16px; color: #0f172a;\">{total_amount}</strong>\n                    </td>\n                </tr>\n                <tr>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Customer Name</span>\n                        <strong style=\"font-size: 14px; color: #1e293b;\">{customer_name}</strong>\n                    </td>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-bottom: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Customer Email</span>\n                        <a href=\"mailto:{customer_email}\" style=\"font-size: 13px; color: #0284c7; text-decoration: none; font-weight: 600;\">{customer_email}</a>\n                    </td>\n                </tr>\n                <tr>\n                    <td width=\"50%\" style=\"padding: 13px 16px; border-right: 1px solid #e2e8f0;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Date & Time</span>\n                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 500;\">{date_str}</span>\n                    </td>\n                    <td width=\"50%\" style=\"padding: 13px 16px;\">\n                        <span style=\"font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px;\">Payment Method</span>\n                        <span style=\"font-size: 13px; color: #1e293b; font-weight: 600;\">{payment_method}</span>\n                    </td>\n                </tr>\n            </table>\n\n            <!-- Ordered Items -->\n            <div style=\"margin-bottom: 24px;\">\n                <div style=\"font-size: 13px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;\">\n                    📋 Ordered Products\n                </div>\n                {items_table}\n            </div>\n\n            <!-- Admin Action Buttons -->\n            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin-top: 24px;\">\n                <tr>\n                    <td align=\"center\">\n                        <a href=\"{admin_order_url}\" style=\"display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 50px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35); margin: 4px;\">\n                            ⚙️ View in Admin Panel &rarr;\n                        </a>\n                        <a href=\"mailto:{customer_email}?subject=Order%20%23{order_id}%20Update\" style=\"display: inline-block; background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 22px; border-radius: 50px; margin: 4px;\">\n                            ✉️ Email Customer\n                        </a>\n                    </td>\n                </tr>\n            </table>\n        </div>\n\n        <!-- Footer -->\n        <div style=\"background-color: #f8fafc; padding: 16px 25px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0;\">\n            Automated store notification for Sagar Starter\'s Administrators.\n        </div>\n    </div>\n</div>', '{order_id}, {customer_name}, {customer_email}, {date_str}, {payment_method}, {total_amount}, {items_table}, {admin_order_url}, {site_url}, {current_year}', '2026-09-04 10:53:14'),
('5', 'order_status_update', 'Order Status Update', 'Update on your Order #{order_id} - {display_status}', '\n<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333; border: 1px solid #eaeaea; border-radius: 8px; overflow: hidden;\">\n    <div style=\"background-color: {status_color}; padding: 20px; text-align: center; color: white;\">\n        <h2 style=\"margin: 0;\">Order Status Update</h2>\n    </div>\n    <div style=\"padding: 20px;\">\n        <p style=\"font-size: 16px;\">Hello <strong>{customer_name}</strong>,</p>\n        \n        <div style=\"background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {status_color};\">\n            <h3 style=\"margin-top: 0; color: {status_color};\">Status: {display_status}</h3>\n            <p style=\"margin-bottom: 0;\">{status_message}</p>\n        </div>\n        \n        <p><strong>Order ID:</strong> #{order_id}</p>\n        \n        <p style=\"margin-top: 30px; font-size: 14px; color: #6c757d; text-align: center;\">\n            If you have any questions about your order, please reply to this email or contact our support team.\n        </p>\n    </div>\n    <div style=\"background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; border-top: 1px solid #eaeaea;\">\n        &copy; {current_year} Sagar Starter\'s. All rights reserved.\n    </div>\n</div>', '{status_color}, {customer_name}, {display_status}, {status_message}, {order_id}, {current_year}', '2026-03-19 14:12:39'),
('6', 'contact_form', 'Contact Us Submission', 'New Contact Form Submission: {subject}', '<h2>New Contact Form Submission</h2>\n<p><strong>Name:</strong> {name}</p>\n<p><strong>Email:</strong> {email}</p>\n<p><strong>Phone:</strong> {phone}</p>\n<p><strong>Subject:</strong> {subject}</p>\n<hr>\n<p><strong>Message:</strong></p>\n<p>{message}</p>', '{name}, {email}, {phone}, {subject}, {message}', '2026-03-19 14:31:36'),
('7', 'google_profile_reminder', 'Google Profile Completion Reminder', 'Complete Your Profile at {site_name} – Quick 1-Minute Setup', '\n<div style=\"font-family: \'Segoe UI\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);\">\n    <!-- Header Banner -->\n    <div style=\"background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); padding: 32px 24px; text-align: center; color: #ffffff;\">\n        <div style=\"display: inline-block; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; padding: 12px; margin-bottom: 12px;\">\n            <img src=\"https://cdn-icons-png.flaticon.com/512/3135/3135715.png\" width=\"48\" height=\"48\" alt=\"Profile Icon\" style=\"vertical-align: middle;\">\n        </div>\n        <h2 style=\"margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;\">Welcome to {site_name}!</h2>\n        <p style=\"margin: 6px 0 0; font-size: 15px; color: rgba(255,255,255,0.9);\">You are just one step away from seamless shopping & fast delivery.</p>\n    </div>\n\n    <!-- Content Body -->\n    <div style=\"padding: 32px 28px; color: #334155; line-height: 1.6;\">\n        <p style=\"font-size: 16px; margin-top: 0;\">Hi <strong>{name}</strong>,</p>\n        \n        <p style=\"font-size: 15px; margin-bottom: 20px;\">\n            Thank you for signing in with Google! We noticed you moved away before finishing your shipping and contact details (Phone, Delivery Address, etc.).\n        </p>\n\n        <!-- Information Callout Box -->\n        <div style=\"background-color: #f8fafc; border-left: 4px solid #0d6efd; padding: 16px 20px; border-radius: 6px; margin: 24px 0;\">\n            <p style=\"margin: 0 0 8px; font-weight: 600; color: #1e293b; font-size: 15px;\">Why complete your profile?</p>\n            <ul style=\"margin: 0; padding-left: 20px; color: #475569; font-size: 14px;\">\n                <li style=\"margin-bottom: 6px;\">⚡ <strong>Fast Checkout:</strong> Auto-fill your delivery details instantly.</li>\n                <li style=\"margin-bottom: 6px;\">📦 <strong>Live Order Tracking:</strong> Receive WhatsApp & SMS shipment updates.</li>\n                <li>🎁 <strong>Exclusive Offers:</strong> Access special member discounts.</li>\n            </ul>\n        </div>\n\n        <!-- Action Button -->\n        <div style=\"text-align: center; margin: 32px 0 24px;\">\n            <a href=\"{profile_link}\" style=\"display: inline-block; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 14px 36px; border-radius: 50px; box-shadow: 0 4px 12px rgba(13,110,253,0.35);\">\n                Complete My Profile Now &rarr;\n            </a>\n        </div>\n\n        <p style=\"font-size: 13px; color: #64748b; text-align: center; margin-top: 20px;\">\n            Or copy and paste this link in your browser:<br>\n            <a href=\"{profile_link}\" style=\"color: #0d6efd; word-break: break-all;\">{profile_link}</a>\n        </p>\n    </div>\n\n    <!-- Footer -->\n    <div style=\"background-color: #f1f5f9; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;\">\n        <p style=\"margin: 0 0 6px;\">This reminder was sent to <strong>{email}</strong> because you signed in to {site_name}.</p>\n        <p style=\"margin: 0;\">&copy; {current_year} {site_name}. All rights reserved.</p>\n    </div>\n</div>', '{name}, {email}, {profile_link}, {site_name}, {site_url}, {current_year}', '2026-08-31 17:56:02');

-- --------------------------------------------------------
-- Table structure for `gateway_credentials`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gateway_credentials`;
CREATE TABLE `gateway_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_id` int(11) NOT NULL,
  `mode` enum('test','live') NOT NULL DEFAULT 'test',
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `merchant_id` varchar(255) DEFAULT NULL,
  `extra_data` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gateway_mode` (`gateway_id`,`mode`),
  CONSTRAINT `gateway_credentials_ibfk_1` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `google_profile_reminders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `google_profile_reminders`;
CREATE TABLE `google_profile_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `login_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reminder_status` enum('pending','sent','completed','cancelled') NOT NULL DEFAULT 'pending',
  `reminder_count` int(11) NOT NULL DEFAULT 0,
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status_activity` (`reminder_status`,`last_activity_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `header_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `header_settings`;
CREATE TABLE `header_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logo_main` varchar(255) DEFAULT 'logo.jpg',
  `logo_sticky` varchar(255) DEFAULT NULL,
  `logo_mobile` varchar(255) DEFAULT NULL,
  `logo_width` varchar(50) DEFAULT 'auto',
  `logo_height` varchar(50) DEFAULT '40px',
  `logo_alignment` enum('left','center') DEFAULT 'left',
  `logo_link_enabled` tinyint(1) DEFAULT 1,
  `cart_enabled` tinyint(1) DEFAULT 1,
  `cart_icon_class` varchar(100) DEFAULT 'fas fa-shopping-cart',
  `cart_custom_icon` varchar(255) DEFAULT NULL,
  `cart_icon_color` varchar(50) DEFAULT '#000000',
  `cart_hover_color` varchar(50) DEFAULT '#007bff',
  `cart_badge_enabled` tinyint(1) DEFAULT 1,
  `cart_badge_bg` varchar(50) DEFAULT '#dc3545',
  `cart_badge_text` varchar(50) DEFAULT '#ffffff',
  `cart_link` varchar(255) DEFAULT '/store/cart.php',
  `profile_enabled` tinyint(1) DEFAULT 1,
  `profile_icon_class` varchar(100) DEFAULT 'fas fa-user-circle',
  `profile_custom_icon` varchar(255) DEFAULT NULL,
  `profile_icon_color` varchar(50) DEFAULT '#000000',
  `profile_hover_color` varchar(50) DEFAULT '#007bff',
  `profile_login_link` varchar(255) DEFAULT '/store/user/login.php',
  `profile_dropdown_enabled` tinyint(1) DEFAULT 1,
  `profile_dropdown_bg` varchar(50) DEFAULT '#ffffff',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `header_settings` (`id`, `logo_main`, `logo_sticky`, `logo_mobile`, `logo_width`, `logo_height`, `logo_alignment`, `logo_link_enabled`, `cart_enabled`, `cart_icon_class`, `cart_custom_icon`, `cart_icon_color`, `cart_hover_color`, `cart_badge_enabled`, `cart_badge_bg`, `cart_badge_text`, `cart_link`, `profile_enabled`, `profile_icon_class`, `profile_custom_icon`, `profile_icon_color`, `profile_hover_color`, `profile_login_link`, `profile_dropdown_enabled`, `profile_dropdown_bg`) VALUES 
('1', 'logo.jpg', NULL, NULL, 'auto', '40px', 'left', '1', '1', 'fas fa-shopping-cart', NULL, '#000000', '#007bff', '1', '#dc3545', '#ffffff', '/store/cart.php', '1', 'fas fa-user-circle', NULL, '#000000', '#007bff', '/store/user/login.php', '1', '#ffffff');

-- --------------------------------------------------------
-- Table structure for `hero_slider_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hero_slider_settings`;
CREATE TABLE `hero_slider_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_active` tinyint(1) DEFAULT 1,
  `layout` varchar(50) DEFAULT 'full',
  `desktop_height` varchar(50) DEFAULT '600px',
  `mobile_height` varchar(50) DEFAULT '400px',
  `show_arrows` tinyint(1) DEFAULT 1,
  `show_dots` tinyint(1) DEFAULT 1,
  `arrow_style` varchar(50) DEFAULT 'light',
  `dot_style` varchar(50) DEFAULT 'light',
  `autoplay` tinyint(1) DEFAULT 1,
  `autoplay_delay` int(11) DEFAULT 5000,
  `transition_type` varchar(50) DEFAULT 'slide',
  `transition_speed` int(11) DEFAULT 800,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `container_width` varchar(50) NOT NULL DEFAULT '100%',
  `image_fit` varchar(50) NOT NULL DEFAULT 'cover',
  `tablet_height` varchar(50) NOT NULL DEFAULT '460px',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hero_slider_settings` (`id`, `is_active`, `layout`, `desktop_height`, `mobile_height`, `show_arrows`, `show_dots`, `arrow_style`, `dot_style`, `autoplay`, `autoplay_delay`, `transition_type`, `transition_speed`, `updated_at`, `container_width`, `image_fit`, `tablet_height`) VALUES 
('1', '1', 'full', '520px', '340px', '0', '0', 'light', 'light', '1', '5000', 'zoom-in', '800', '2026-08-15 23:42:45', '100%', 'cover', '420px');

-- --------------------------------------------------------
-- Table structure for `hero_slides`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hero_slides`;
CREATE TABLE `hero_slides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bg_type` varchar(50) DEFAULT 'image',
  `media_path` varchar(255) DEFAULT NULL,
  `bg_color` varchar(100) DEFAULT '#000000',
  `overlay_color` varchar(100) DEFAULT 'rgba(0,0,0,0.3)',
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `btn_primary_text` varchar(100) DEFAULT NULL,
  `btn_primary_link` varchar(255) DEFAULT NULL,
  `btn_primary_style` varchar(50) DEFAULT 'primary',
  `btn_secondary_text` varchar(100) DEFAULT NULL,
  `btn_secondary_link` varchar(255) DEFAULT NULL,
  `btn_secondary_style` varchar(50) DEFAULT 'outline-light',
  `content_alignment` varchar(50) DEFAULT 'center',
  `text_animation` varchar(50) DEFAULT 'fade',
  `animation_duration` int(11) DEFAULT 1000,
  `device_visibility` varchar(50) DEFAULT 'all',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `homepage_features`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `homepage_features`;
CREATE TABLE `homepage_features` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `icon_type` enum('image','font') NOT NULL DEFAULT 'font',
  `icon_value` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `invoices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `invoice_date` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cod_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `access_token` varchar(64) NOT NULL,
  `status` enum('generated','sent','viewed') DEFAULT 'generated',
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_number` (`invoice_number`),
  UNIQUE KEY `uk_invoice_order` (`order_id`),
  KEY `idx_access_token` (`access_token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `media_library`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `media_library`;
CREATE TABLE `media_library` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_type` enum('image','video','other') NOT NULL DEFAULT 'image',
  `mime_type` varchar(100) NOT NULL DEFAULT '',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT '',
  `caption` text DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `menus`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `menu_location` enum('header','footer1','footer2','footer3','both1','both2','both3') NOT NULL DEFAULT 'header',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `menus_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `merchant_feed_log`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `merchant_feed_log`;
CREATE TABLE `merchant_feed_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `product_count` int(11) NOT NULL DEFAULT 0,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `status` enum('success','error') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `triggered_by` enum('manual','auto','cron') DEFAULT 'auto',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `fk_order_item_order` (`order_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `order_shipping_details`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_shipping_details`;
CREATE TABLE `order_shipping_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `shipping_method_id` int(11) DEFAULT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_estimate` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `shipping_provider` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `order_status_history`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_status_history`;
CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `logged_by` enum('system','admin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `order_tracking`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_tracking`;
CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `courier_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `courier_id` (`courier_id`),
  CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_tracking_ibfk_2` FOREIGN KEY (`courier_id`) REFERENCES `courier_companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'card',
  `utr_number` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'Pending',
  `status` enum('pending','processing','partially_shipped','shipped','delivered','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tracking_number` varchar(100) DEFAULT NULL,
  `carrier` varchar(100) DEFAULT NULL,
  `advance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) DEFAULT NULL,
  `cod_charge` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'COD extra charge applied to this order',
  `cod_charge_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'COD charge applied to this order',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `pages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pages` (`id`, `slug`, `title`, `content`, `meta_title`, `meta_description`, `updated_at`, `created_at`) VALUES 
('1', 'about', 'About Us', '<h1>About Us</h1>\r\n<p>Company Name: Sagar Electricals<br>Tagline: Electrical Motor Starter Menufacturer, Powerful Performance<br>Mission: To be a global leader in the design and manufacturing of high-quality electrical motor starters, delivering reliable and efficient solutions to our customers.<br>Vision: To become the preferred partner for industries seeking innovative and sustainable motor control solutions.<br>Values: Quality, innovation, customer satisfaction, integrity, sustainability.<br>About Us:<br>Sagar Starter&rsquo;s is a leading manufacturer of electrical motor starters, with a strong focus on precision engineering and quality craftsmanship. We leverage our state-of-the-art manufacturing facilities to produce a wide range of motor starters, tailored to meet the diverse needs of various industries. Our commitment to innovation drives us to continuously develop new products that enhance efficiency, reliability, and energy savings. With a global presence and a dedicated team of experts, we strive to provide exceptional customer service and support.<br>[Call to Action]: Partner with us for reliable, efficient, and sustainable motor control solutions.</p>', 'About Us – Our Story, Mission & Values', 'Discover our story, mission, and values. Learn how we work to provide quality products and the best service to our customers.', '2026-03-08 13:04:49', '2026-02-23 16:43:16'),
('2', 'contact', 'Contact Us', '<h1 data-start=\"122\" data-end=\"152\">Contact Us &ndash; Sagar Starter&rsquo;s</h1>\r\n<h2 data-start=\"154\" data-end=\"177\">Get in Touch With Us</h2>\r\n<p data-start=\"179\" data-end=\"356\">We&rsquo;re here to help! If you have any questions, concerns, feedback, or business inquiries, feel free to contact us. Our team at <strong data-start=\"306\" data-end=\"325\">Sagar Starter&rsquo;s</strong> is always ready to assist you.</p>\r\n<hr data-start=\"358\" data-end=\"361\">\r\n<h2 data-start=\"363\" data-end=\"385\">📞 Customer Support</h2>\r\n<p data-start=\"387\" data-end=\"409\">If you need help with:</p>\r\n<ul data-start=\"410\" data-end=\"509\">\r\n<li data-start=\"410\" data-end=\"428\">\r\n<p data-start=\"412\" data-end=\"428\">Order tracking</p>\r\n</li>\r\n<li data-start=\"429\" data-end=\"452\">\r\n<p data-start=\"431\" data-end=\"452\">Product information</p>\r\n</li>\r\n<li data-start=\"453\" data-end=\"474\">\r\n<p data-start=\"455\" data-end=\"474\">Returns &amp; refunds</p>\r\n</li>\r\n<li data-start=\"475\" data-end=\"493\">\r\n<p data-start=\"477\" data-end=\"493\">Payment issues</p>\r\n</li>\r\n<li data-start=\"494\" data-end=\"509\">\r\n<p data-start=\"496\" data-end=\"509\">Bulk orders</p>\r\n</li>\r\n</ul>\r\n<p data-start=\"511\" data-end=\"548\">Please reach out to our support team.</p>\r\n<p data-start=\"550\" data-end=\"676\"><strong data-start=\"550\" data-end=\"560\">Email:</strong> <a class=\"decorated-link cursor-pointer\" rel=\"noopener\" data-start=\"1033\" data-end=\"1059\">info@sagarstarters.com</a><br data-start=\"586\" data-end=\"589\"><strong data-start=\"589\" data-end=\"599\">Phone:</strong> <a href=\"tel:8573934013\">+91-8573934013</a><br data-start=\"614\" data-end=\"617\"><strong data-start=\"617\" data-end=\"635\">Support Hours:</strong> Monday to Saturday, 10:00 AM &ndash; 6:00 PM</p>\r\n<hr data-start=\"678\" data-end=\"681\">\r\n<h2 data-start=\"683\" data-end=\"713\">📦 Order &amp; Delivery Support</h2>\r\n<p data-start=\"715\" data-end=\"756\">For order-related issues, please mention:</p>\r\n<ul data-start=\"757\" data-end=\"841\">\r\n<li data-start=\"757\" data-end=\"774\">\r\n<p data-start=\"759\" data-end=\"774\">Your Order ID</p>\r\n</li>\r\n<li data-start=\"775\" data-end=\"803\">\r\n<p data-start=\"777\" data-end=\"803\">Registered mobile number</p>\r\n</li>\r\n<li data-start=\"804\" data-end=\"841\">\r\n<p data-start=\"806\" data-end=\"841\">A brief description of your issue</p>\r\n</li>\r\n</ul>\r\n<p data-start=\"843\" data-end=\"885\">This helps us resolve your concern faster.</p>\r\n<hr data-start=\"887\" data-end=\"890\">\r\n<h2 data-start=\"892\" data-end=\"930\">🤝 Business &amp; Partnership Inquiries</h2>\r\n<p data-start=\"932\" data-end=\"1011\">Interested in bulk purchases, partnerships, or collaborations?<br data-start=\"994\" data-end=\"997\">Contact us at:</p>\r\n<p data-start=\"1013\" data-end=\"1061\"><strong data-start=\"1013\" data-end=\"1032\">Business Email:</strong> <a class=\"decorated-link cursor-pointer\" rel=\"noopener\" data-start=\"1033\" data-end=\"1059\">info@sagarstarters.com</a></p>\r\n<hr data-start=\"1063\" data-end=\"1066\">\r\n<h2 data-start=\"1068\" data-end=\"1085\">📍 Our Address</h2>\r\n<p data-start=\"1087\" data-end=\"1172\">Sagar Starter&rsquo;s<br data-start=\"1102\" data-end=\"1105\">Address Sagar Starter&rsquo;s H.No. 301 Alipur Madra Jkhanian Ghazipur Uttar Pradesh 275203 India</p>\r\n<hr data-start=\"1174\" data-end=\"1177\">\r\n<h2 data-start=\"1179\" data-end=\"1200\">💬 Connect With Us</h2>\r\n<p data-start=\"1202\" data-end=\"1239\">You can also connect with us through:</p>\r\n<ul data-start=\"1240\" data-end=\"1332\">\r\n<li data-start=\"1240\" data-end=\"1275\">\r\n<p data-start=\"1242\" data-end=\"1275\">WhatsApp Support (if available)</p>\r\n</li>\r\n<li data-start=\"1276\" data-end=\"1302\">\r\n<p data-start=\"1278\" data-end=\"1302\">Social Media Platforms</p>\r\n</li>\r\n<li data-start=\"1303\" data-end=\"1332\">\r\n<p data-start=\"1305\" data-end=\"1332\">Contact Form on this page</p>\r\n</li>\r\n</ul>\r\n<p data-start=\"1334\" data-end=\"1381\">We usually respond within 24&ndash;48 business hours.</p>\r\n<hr data-start=\"1383\" data-end=\"1386\">\r\n<h2 data-start=\"1388\" data-end=\"1429\">Thank You for Choosing Sagar Starter&rsquo;s</h2>\r\n<p data-start=\"1431\" data-end=\"1549\">Your trust means everything to us. We look forward to serving you and providing the best shopping experience possible.</p>', 'Contact Support | Help & Customer Assistance', 'Get in touch with our support team for assistance with orders, payments, shipping, returns, or general inquiries. We are ready to help you.', '2026-03-08 13:03:22', '2026-02-23 16:43:16'),
('4', 'privacy-policy', 'Privacy Policy', '<div class=\"container\">\r\n<h1>Privacy Policy</h1>\r\n<p class=\"last-updated\"><strong>Last updated:</strong> November 18, 2024</p>\r\n<p>This Privacy Policy describes Our policies and procedures on the collection, use and disclosure of Your information when You use the Service and tells You about Your privacy rights and how the law protects You.</p>\r\n<h2>Interpretation and Definitions</h2>\r\n<h3>Definitions</h3>\r\n<ul>\r\n<li><strong>Company:</strong> Sagar Electricals, Alipur Madra Jakhanian, Ghazipur, Uttar Pradesh, India.</li>\r\n<li><strong>Website:</strong> <a href=\"https://www.sagarstarters.com\" target=\"_blank\" rel=\"noopener\">www.sagarstarters.com</a></li>\r\n<li><strong>Personal Data:</strong> Information that identifies an individual.</li>\r\n<li><strong>Usage Data:</strong> Data collected automatically such as IP address, browser type, and visit time.</li>\r\n<li><strong>Cookies:</strong> Small files stored on your device to improve user experience.</li>\r\n</ul>\r\n<h2>Information We Collect</h2>\r\n<h4>Personal Information</h4>\r\n<ul>\r\n<li>Email address</li>\r\n<li>Full name</li>\r\n<li>Phone number</li>\r\n<li>Address details</li>\r\n</ul>\r\n<h4>Usage Information</h4>\r\n<p>We automatically collect technical data such as IP address, browser type, pages visited, and time spent on our website.</p>\r\n<h2>How We Use Your Information</h2>\r\n<ul>\r\n<li>To provide and maintain our services</li>\r\n<li>To manage your account</li>\r\n<li>To respond to inquiries</li>\r\n<li>To send updates and promotional offers</li>\r\n<li>To improve our website performance</li>\r\n</ul>\r\n<h2>Cookies Policy</h2>\r\n<p>We use essential and functional cookies to improve user experience. You may disable cookies through your browser settings.</p>\r\n<h2>Data Sharing</h2>\r\n<p>We may share your data with service providers, business partners, or as required by law.</p>\r\n<h2>Data Security</h2>\r\n<p>We use commercially acceptable security measures to protect your data, but no method of transmission over the Internet is 100% secure.</p>\r\n<h2>Your Rights</h2>\r\n<p>You may request access, correction, or deletion of your personal information by contacting us.</p>\r\n<h2>Children&rsquo;s Privacy</h2>\r\n<p>Our services are not directed to individuals under the age of 13.</p>\r\n<h2>Changes to This Policy</h2>\r\n<p>We may update this Privacy Policy from time to time. Changes will be posted on this page.</p>\r\n<h2>Contact Us</h2>\r\n<div class=\"contact-box\">\r\n<p><strong>Email:</strong> <a href=\"mailto:info@sagarstarters.com\">info@sagarstarters.com</a></p>\r\n<p><strong>Phone:</strong> +91-8573934013</p>\r\n<p><strong>Website:</strong> <a href=\"https://www.sagarstarters.com/contact-us/\" target=\"_blank\" rel=\"noopener\">Contact Us Page</a></p>\r\n</div>\r\n<footer>&copy; 2024 Sagar Starters. All Rights Reserved.</footer></div>', 'User Privacy Policy | Data Protection & Security', 'Learn how we collect, use, and safeguard your personal information. Read our privacy policy to understand your data protection and privacy rights.', '2026-03-08 13:01:45', '2026-02-23 17:29:45'),
('5', 'shipping-policy', 'Shipping Policy', '<h1>Shipping Policy</h1>\r\n<h2 class=\"wp-block-heading\">Shipping Policy For Sagar Starter&rsquo;s</h2>\r\n<p>&nbsp;</p>\r\n<p><strong>Sagar Starter&rsquo;s</strong>&nbsp;is committed to excellence, and the full satisfaction of our<br>customers. S<strong>agar Starters</strong>&nbsp;proudly offers shipping services. Be assured we are<br>doing everything in our power to get your order to you as soon as possible. Please consider any<br>holidays that might impact delivery times.<strong>&nbsp;Sagar Starter&rsquo;s</strong>&nbsp;also offers same day<br>dispatch.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>1 &ndash; SHIPPING</strong><br>All orders for our products are processed and shipped out in 7<strong>-10</strong>&nbsp;business days. Orders are not<br>shipped or delivered on weekends or holidays. If we are experiencing a high volume of orders,<br>shipments may be delayed by a few days. Please allow additional days in transit for delivery. If there<br>will be a significant delay in the shipment of your order, we will contact you via email.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>2 &ndash; WRONG ADDRESS DISCLAIMER</strong><br>It is the responsibility of the customers to make sure that the shipping address entered is correct.<br>We do our best to speed up processing and shipping time, so there is always a small window to<br>correct an incorrect shipping address. Please contact us immediately if you believe you have<br>provided an incorrect shipping address.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>3 &ndash; UNDELIVERABLE ORDERS</strong><br>Orders that are returned to us as undeliverable because of incorrect shipping information are<br>subject to a restocking fee to be determined by us.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>4 &ndash; LOST/STOLEN PACKAGES</strong><br><strong>Sagar Starter&rsquo;s</strong>&nbsp;is not responsible for lost or stolen packages. If your tracking<br>information states that your package was delivered to your address and you have not received it,<br>please report to the local authorities.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>5 &ndash; RETURN REQUEST DAYS</strong><br><strong>Sagar Starter&rsquo;s</strong>&nbsp;The product allows returns within 2 days of receipt. Kindly be<br>advised that the item should be returned unopened and unused. And Damaged product will not be accepted back.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>6 &ndash; OUT OF STOCK ITEM PROCESS</strong><br><strong>Sagar Starter&rsquo;s</strong>&nbsp;has the following options in the event there are items which are out<br>of stock&nbsp;<strong>Sagar Starter&rsquo;s</strong>&nbsp;Wait for all items to be in stock before dispatching.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>7 &ndash; IMPORT DUTY AND TAXES</strong><br>When dealing with&nbsp;<strong>Sagar Starter&rsquo;s</strong>&nbsp;you have the following options when it comes to<br>taxes as well as import duties, You will be required to settle the requisite fees when the items are<br>arriving in the destination country.</p>\r\n<p><strong>8 &ndash; CONDITION OF SHIPPING CHARGES</strong><br>Shipping is free only on the purchase of single-piece products. Otherwise , the customer will have to bear the shipping charges.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>9 &ndash; ACCEPTANCE</strong><br>By accessing our site and placing an order you have willingly accepted the terms of this Shipping<br>Policy.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>10 &ndash; CONTACT INFORMATION</strong><br>In the event you have any questions or comments please reach us via the following contacts:<br>Phone :&nbsp;<a href=\"tel:8573934013\">+91-8573934013</a>&nbsp;WhatsApp No. +91-<a href=\"whatsapp:9721083003\">9721083003</a></p>\r\n<p>&nbsp;</p>\r\n<p>Email:&nbsp;<a href=\"mailto:sales@sagarstarters.com\">sales@sagarstarters.com</a>&nbsp;,&nbsp;<a href=\"mailto:info@sagarstarters.com\">info@sagarstarters.com</a>&nbsp;,&nbsp;<a href=\"mailto:sagarstarters@gmail.com\">sagarstarters@gmail.com</a></p>\r\n<p>&nbsp;</p>\r\n<p>Address Sagar Starter&rsquo;s H.No. 301 Alipur Madra Jkhanian Ghazipur Uttar Pradesh 275203 India</p>', 'Shipping & Delivery Policy – Order Processing & Delivery Time', 'Learn about our shipping and delivery policy including order processing, shipping time, delivery charges, and tracking details.', '2026-03-08 13:00:13', '2026-02-23 17:29:45'),
('6', 'return-refund-policy', 'Return & Refund Policy', '<p>Return and Refund Policy &nbsp;<br>========================</p>\r\n<p>Last updated: February 26, 2026</p>\r\n<p>Thank you for shopping at Sagar Starter\'s.</p>\r\n<p>Interpretation and Definitions &nbsp;<br>------------------------------</p>\r\n<p>Interpretation &nbsp;<br>~~~~~~~~~~~~~~</p>\r\n<p>The words whose initial letters are capitalized have meanings defined under<br>the following conditions. The following definitions shall have the same<br>meaning regardless of whether they appear in singular or in plural.</p>\r\n<p>Definitions &nbsp;<br>~~~~~~~~~~~</p>\r\n<p>For the purposes of this Return and Refund Policy:</p>\r\n<p>&nbsp; * Company (referred to as either \"the Company\", \"We\", \"Us\" or \"Our\" in this<br>&nbsp; &nbsp; Policy) refers to Sagar Starters, Address Sagar Starter&rsquo;s H.No. 301 Alipur<br>&nbsp; &nbsp; Madra Jkhanian Ghazipur Uttar Pradesh 275203 India.</p>\r\n<p>&nbsp; * Goods refer to the items offered for sale on the Service.</p>\r\n<p>&nbsp; * Orders mean a request by You to purchase Goods from Us.</p>\r\n<p>&nbsp; * Service refers to the Website.</p>\r\n<p>&nbsp; * Website refers to Sagar Starter\'s, accessible from<br>&nbsp; &nbsp; &lt;https://www.sagarstarters.com/&gt;.</p>\r\n<p>&nbsp; * You means the individual accessing or using the Service, or the company,<br>&nbsp; &nbsp; or other legal entity on behalf of which such individual is accessing or<br>&nbsp; &nbsp; using the Service, as applicable.</p>\r\n<p><br>Your Order Cancellation Rights &nbsp;<br>------------------------------</p>\r\n<p>You are entitled to cancel Your Order within 7 days without giving any reason<br>for doing so.</p>\r\n<p>The deadline for cancelling an Order is 7 days from the date on which You<br>received the Goods or on which a third party you have appointed, who is not<br>the carrier, takes possession of the product delivered.</p>\r\n<p>In order to exercise Your right of cancellation, You must inform Us of your<br>decision by means of a clear statement. You can inform Us of your decision by:</p>\r\n<p>&nbsp; * By email: sagarstarters@gmail.com</p>\r\n<p>&nbsp; * By visiting this page on our website:<br>&nbsp; &nbsp; &lt;https://www.sagarstarters.com/contact-us/&gt;</p>\r\n<p>&nbsp; * By phone: 08573934013</p>\r\n<p><br>We will reimburse You no later than 14 days from the day on which We receive<br>the returned Goods. We will use the same means of payment as You used for the<br>Order, and You will not incur any fees for such reimbursement.</p>\r\n<p>Conditions for Returns &nbsp;<br>----------------------</p>\r\n<p>In order for the Goods to be eligible for a return, please make sure that:</p>\r\n<p>&nbsp; * The Goods were purchased in the last 7 days<br>&nbsp; * The Goods are in the original packaging</p>\r\n<p>The following Goods cannot be returned:</p>\r\n<p>&nbsp; * The supply of Goods made to Your specifications or clearly personalized.<br>&nbsp; * The supply of Goods which according to their nature are not suitable to be<br>&nbsp; &nbsp; returned, deteriorate rapidly or where the date of expiry is over.<br>&nbsp; * The supply of Goods which are not suitable for return due to health<br>&nbsp; &nbsp; protection or hygiene reasons and were unsealed after delivery.<br>&nbsp; * The supply of Goods which are, after delivery, according to their nature,<br>&nbsp; &nbsp; inseparably mixed with other items.</p>\r\n<p>We may refuse returns that do not meet the conditions above, to the extent<br>permitted by applicable law.</p>\r\n<p>Only regular priced Goods may be refunded. Unfortunately, Goods on sale cannot<br>be refunded. This exclusion may not apply to You if it is not permitted by<br>applicable law.</p>\r\n<p>Returning Goods &nbsp;<br>---------------</p>\r\n<p>You are responsible for the cost and risk of returning the Goods to Us.</p>\r\n<p>You should send the Goods at the following address:</p>\r\n<p>Address Sagar Starter&rsquo;s H.No. 301 Alipur Madra Jkhanian Ghazipur Uttar Pradesh<br>275203 India</p>\r\n<p>We cannot be held responsible for Goods damaged or lost in return shipment.<br>Therefore, We recommend an insured and trackable mail service. We are unable<br>to issue a refund without actual receipt of the Goods or proof of received<br>return delivery.</p>\r\n<p>Gifts &nbsp;<br>-----</p>\r\n<p>If the Goods were marked as a gift when purchased and then shipped directly to<br>you, You\'ll receive a gift credit for the value of your return. Once the<br>returned product is received, a gift certificate will be mailed to You.</p>\r\n<p>If the Goods weren\'t marked as a gift when purchased, or the gift giver had<br>the Order shipped to themselves to give it to You later, We will send the<br>refund to the gift giver.</p>\r\n<p>Contact Us &nbsp;<br>~~~~~~~~~~</p>\r\n<p>If you have any questions about our Returns and Refunds Policy, please contact<br>us:</p>\r\n<p>&nbsp; * By email: sagarstarters@gmail.com</p>\r\n<p>&nbsp; * By visiting this page on our website:<br>&nbsp; &nbsp; &lt;https://www.sagarstarters.com/contact-us/&gt;</p>\r\n<p>&nbsp; * By phone: 08573934013</p>\r\n<p>&nbsp;</p>', 'Return and Refund Policy – Easy Returns & Support', 'Agar tumhari eCommerce website hai to meta description me returns, refunds, exchange, order support jaise keywords add karna ranking improve karta hai.', '2026-03-08 12:58:35', '2026-02-23 17:29:45'),
('7', 'terms-conditions', 'Terms & Conditions', '<h1>Terms &amp; Conditions</h1>\r\n<h5><strong>Welcome to Sagar Starter&rsquo;s!</strong></h5>\r\n<p>These terms and conditions outline the rules and regulations for the use of Sagar Starter&rsquo;s Website, located at&nbsp;<a href=\"https://www.sagarstarters.com/\">https://www.sagarstarters.com/.</a></p>\r\n<p>By accessing this website we assume you accept these terms and conditions. Do not continue to use Sagar Starters if you do not agree to take all of the terms and conditions stated on this page.</p>\r\n<p>The following terminology applies to these Terms and Conditions, Privacy Statement and Disclaimer Notice and all Agreements: &ldquo;Client&rdquo;, &ldquo;You&rdquo; and &ldquo;Your&rdquo; refers to you, the person log on this website and compliant to the Company&rsquo;s terms and conditions. &ldquo;The Company&rdquo;, &ldquo;Ourselves&rdquo;, &ldquo;We&rdquo;, &ldquo;Our&rdquo; and &ldquo;Us&rdquo;, refers to our Company. &ldquo;Party&rdquo;, &ldquo;Parties&rdquo;, or &ldquo;Us&rdquo;, refers to both the Client and ourselves. All terms refer to the offer, acceptance and consideration of payment necessary to undertake the process of our assistance to the Client in the most appropriate manner for the express purpose of meeting the Client&rsquo;s needs in respect of provision of the Company&rsquo;s stated services, in accordance with and subject to, prevailing law of in. Any use of the above terminology or other words in the singular, plural, capitalization and/or he/she or they, are taken as interchangeable and therefore as referring to same.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Cookies</strong></h3>\r\n<p>We employ the use of cookies. By accessing Sagar Starters, you agreed to use cookies in agreement with the Sagar Starter&rsquo;s Privacy Policy.</p>\r\n<p>Most interactive websites use cookies to let us retrieve the user&rsquo;s details for each visit. Cookies are used by our website to enable the functionality of certain areas to make it easier for people visiting our website. Some of our affiliate/advertising partners may also use cookies.</p>\r\n<h3 class=\"wp-block-heading\"><strong>License</strong></h3>\r\n<p>Unless otherwise stated, Sagar Starter&rsquo;s and/or its licensors own the intellectual property rights for all material on Sagar Starters. All intellectual property rights are reserved. You may access this from Sagar Starters for your own personal use subjected to restrictions set in these terms and conditions.</p>\r\n<p>You must not:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>Republish material from Sagar Starters</li>\r\n<li>Sell, rent or sub-license material from Sagar Starters</li>\r\n<li>Reproduce, duplicate or copy material from Sagar Starters</li>\r\n<li>Redistribute content from Sagar Starters</li>\r\n</ul>\r\n<p>This Agreement shall begin on the date hereof.</p>\r\n<p>Parts of this website offer an opportunity for users to post and exchange opinions and information in certain areas of the website. Sagar Starter&rsquo;s does not filter, edit, publish or review Comments prior to their presence on the website. Comments do not reflect the views and opinions of Sagar Starter&rsquo;s, its agents and/or affiliates. Comments reflect the views and opinions of the person who post their views and opinions. To the extent permitted by applicable laws, Sagar Starter&rsquo;s shall not be liable for the Comments or for any liability, damages or expenses caused and/or suffered as a result of any use of and/or posting of and/or appearance of the Comments on this website.</p>\r\n<p>Sagar Starter&rsquo;s reserves the right to monitor all Comments and to remove any Comments which can be considered inappropriate, offensive or causes breach of these Terms and Conditions.</p>\r\n<p>You warrant and represent that:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>You are entitled to post the Comments on our website and have all necessary licenses and consents to do so;</li>\r\n<li>The Comments do not invade any intellectual property right, including without limitation copyright, patent or trademark of any third party;</li>\r\n<li>The Comments do not contain any defamatory, libelous, offensive, indecent or otherwise unlawful material which is an invasion of privacy</li>\r\n<li>The Comments will not be used to solicit or promote business or custom or present commercial activities or unlawful activity.</li>\r\n</ul>\r\n<p>You hereby grant Sagar Starter&rsquo;s a non-exclusive license to use, reproduce, edit and authorize others to use, reproduce and edit any of your Comments in any and all forms, formats or media.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Hyperlinking to our Content</strong></h3>\r\n<p>The following organizations may link to our Website without prior written approval:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>Government agencies;</li>\r\n<li>Search engines;</li>\r\n<li>News organizations;</li>\r\n<li>Online directory distributors may link to our Website in the same manner as they hyperlink to the Websites of other listed businesses; and</li>\r\n<li>System wide Accredited Businesses except soliciting non-profit organizations, charity shopping malls, and charity fundraising groups which may not hyperlink to our Web site.</li>\r\n</ul>\r\n<p>These organizations may link to our home page, to publications or to other Website information so long as the link: (a) is not in any way deceptive; (b) does not falsely imply sponsorship, endorsement or approval of the linking party and its products and/or services; and (c) fits within the context of the linking party&rsquo;s site.</p>\r\n<p>We may consider and approve other link requests from the following types of organizations:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>commonly-known consumer and/or business information sources;</li>\r\n<li>dot.com community sites;</li>\r\n<li>associations or other groups representing charities;</li>\r\n<li>online directory distributors;</li>\r\n<li>internet portals;</li>\r\n<li>accounting, law and consulting firms; and</li>\r\n<li>educational institutions and trade associations.</li>\r\n</ul>\r\n<p>We will approve link requests from these organizations if we decide that: (a) the link would not make us look unfavorably to ourselves or to our accredited businesses; (b) the organization does not have any negative records with us; (c) the benefit to us from the visibility of the hyperlink compensates the absence of Sagar Starter&rsquo;s; and (d) the link is in the context of general resource information.</p>\r\n<p>These organizations may link to our home page so long as the link: (a) is not in any way deceptive; (b) does not falsely imply sponsorship, endorsement or approval of the linking party and its products or services; and (c) fits within the context of the linking party&rsquo;s site.</p>\r\n<p>If you are one of the organizations listed in paragraph 2 above and are interested in linking to our website, you must inform us by sending an e-mail to Sagar Starter&rsquo;s. Please include your name, your organization name, contact information as well as the URL of your site, a list of any URLs from which you intend to link to our Website, and a list of the URLs on our site to which you would like to link. Wait 2-3 weeks for a response.</p>\r\n<p>Approved organizations may hyperlink to our Website as follows:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>By use of our corporate name; or</li>\r\n<li>By use of the uniform resource locator being linked to; or</li>\r\n<li>By use of any other description of our Website being linked to that makes sense within the context and format of content on the linking party&rsquo;s site.</li>\r\n</ul>\r\n<p>No use of Sagar Starter&rsquo;s logo or other artwork will be allowed for linking absent a trademark license agreement.</p>\r\n<h3 class=\"wp-block-heading\"><strong>i Frames</strong></h3>\r\n<p>Without prior approval and written permission, you may not create frames around our Web pages that alter in any way the visual presentation or appearance of our Website.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Content Liability</strong></h3>\r\n<p>We shall not be hold responsible for any content that appears on your Website. You agree to protect and defend us against all claims that is rising on your Website. No link(s) should appear on any Website that may be interpreted as libelous, obscene or criminal, or which infringes, otherwise violates, or advocates the infringement or other violation of, any third party rights.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Reservation of Rights</strong></h3>\r\n<p>We reserve the right to request that you remove all links or any particular link to our Website. You approve to immediately remove all links to our Website upon request. We also reserve the right to amen these terms and conditions and it&rsquo;s linking policy at any time. By continuously linking to our Website, you agree to be bound to and follow these linking terms and conditions.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Removal of links from our website</strong></h3>\r\n<p>If you find any link on our Website that is offensive for any reason, you are free to contact and inform us any moment. We will consider requests to remove links but we are not obligated to or so or to respond to you directly.</p>\r\n<p>We do not ensure that the information on this website is correct, we do not warrant its completeness or accuracy; nor do we promise to ensure that the website remains available or that the material on the website is kept up to date.</p>\r\n<h3 class=\"wp-block-heading\"><strong>Disclaimer</strong></h3>\r\n<p>To the maximum extent permitted by applicable law, we exclude all representations, warranties and conditions relating to our website and the use of this website. Nothing in this disclaimer will:</p>\r\n<ul class=\"wp-block-list\">\r\n<li>limit or exclude our or your liability for death or personal injury;</li>\r\n<li>limit or exclude our or your liability for fraud or fraudulent misrepresentation;</li>\r\n<li>limit any of our or your liabilities in any way that is not permitted under applicable law; or</li>\r\n<li>exclude any of our or your liabilities that may not be excluded under applicable law.</li>\r\n</ul>\r\n<p>The limitations and prohibitions of liability set in this Section and elsewhere in this disclaimer: (a) are subject to the preceding paragraph; and (b) govern all liabilities arising under the disclaimer, including liabilities arising in contract, in tort and for breach of statutory duty.</p>\r\n<p>As long as the website and the information and services on the website are provided free of charge, we will not be liable for any loss or damage of any nature.</p>', 'Website Terms & Conditions | User Agreement', 'Review our Terms and Conditions to learn about website usage rules, user responsibilities, and policies related to our services and products.', '2026-03-08 12:56:59', '2026-02-23 17:29:45'),
('8', 'disclaimer', 'Disclaimer', '<h1>Disclaimer</h1>\r\n<h1 class=\"wp-block-heading\">Disclaimer for Sagar Satrter&rsquo;s</h1>\r\n<p>If you require any more information or have any questions about our site&rsquo;s disclaimer, please feel free to contact us by email at info@sagarstarters.com.</p>\r\n<h2 class=\"wp-block-heading\">Disclaimers for Sagar Starters</h2>\r\n<p>All the information on this website &ndash; https://www.sagarstarters.com/ &ndash; is published in good faith and for general information purpose only. Sagar Starters does not make any warranties about the completeness, reliability and accuracy of this information. Any action you take upon the information you find on this website (Sagar Starters), is strictly at your own risk. Sagar Starters will not be liable for any losses and/or damages in connection with the use of our website.</p>\r\n<p>From our website, you can visit other websites by following hyperlinks to such external sites. While we strive to provide only quality links to useful and ethical websites, we have no control over the content and nature of these sites. These links to other websites do not imply a recommendation for all the content found on these sites. Site owners and content may change without notice and may occur before we have the opportunity to remove a link which may have gone &lsquo;bad&rsquo;.</p>\r\n<p>Please be also aware that when you leave our website, other sites may have different privacy policies and terms which are beyond our control. Please be sure to check the Privacy Policies of these sites as well as their &ldquo;Terms of Service&rdquo; before engaging in any business or uploading any information.</p>\r\n<h2 class=\"wp-block-heading\">Consent</h2>\r\n<p>By using our website, you hereby consent to our disclaimer and agree to its terms.</p>\r\n<h2 class=\"wp-block-heading\">Update</h2>\r\n<p>Should we update, amend or make any changes to this document, those changes will be prominently posted here.</p>', 'Legal Disclaimer | Website Usage & Information Policy', 'This disclaimer outlines important information about website usage, limitations of liability, and accuracy of content provided on our platform.', '2026-03-08 12:55:34', '2026-02-23 17:29:45'),
('9', 'f-q-44436', 'F&Q', '<h3 data-start=\"186\" data-end=\"217\">1. What is Sagar Starter&rsquo;s?</h3>\r\n<p data-start=\"218\" data-end=\"359\">Sagar Starter&rsquo;s is an online platform that provides quality products and services with a smooth and secure shopping experience for customers.</p>\r\n<hr data-start=\"361\" data-end=\"364\">\r\n<h3 data-start=\"366\" data-end=\"398\">2. How can I place an order?</h3>\r\n<p data-start=\"399\" data-end=\"425\">You can place an order by:</p>\r\n<ol data-start=\"426\" data-end=\"598\">\r\n<li data-start=\"426\" data-end=\"463\">\r\n<p data-start=\"429\" data-end=\"463\">Browsing products on our website</p>\r\n</li>\r\n<li data-start=\"464\" data-end=\"506\">\r\n<p data-start=\"467\" data-end=\"506\">Adding your desired items to the cart</p>\r\n</li>\r\n<li data-start=\"507\" data-end=\"534\">\r\n<p data-start=\"510\" data-end=\"534\">Proceeding to checkout</p>\r\n</li>\r\n<li data-start=\"535\" data-end=\"570\">\r\n<p data-start=\"538\" data-end=\"570\">Entering your delivery details</p>\r\n</li>\r\n<li data-start=\"571\" data-end=\"598\">\r\n<p data-start=\"574\" data-end=\"598\">Completing the payment</p>\r\n</li>\r\n</ol>\r\n<p data-start=\"600\" data-end=\"665\">After successful payment, you will receive an order confirmation.</p>\r\n<hr data-start=\"667\" data-end=\"670\">\r\n<h3 data-start=\"672\" data-end=\"714\">3. What payment methods do you accept?</h3>\r\n<p data-start=\"715\" data-end=\"763\">We accept secure online payment methods such as:</p>\r\n<ul data-start=\"764\" data-end=\"830\">\r\n<li data-start=\"764\" data-end=\"771\">\r\n<p data-start=\"766\" data-end=\"771\">UPI</p>\r\n</li>\r\n<li data-start=\"772\" data-end=\"794\">\r\n<p data-start=\"774\" data-end=\"794\">Debit/Credit Cards</p>\r\n</li>\r\n<li data-start=\"795\" data-end=\"810\">\r\n<p data-start=\"797\" data-end=\"810\">Net Banking</p>\r\n</li>\r\n<li data-start=\"811\" data-end=\"830\">\r\n<p data-start=\"813\" data-end=\"830\">Wallet Payments</p>\r\n</li>\r\n</ul>\r\n<p data-start=\"832\" data-end=\"873\">(Available options may vary at checkout.)</p>\r\n<hr data-start=\"875\" data-end=\"878\">\r\n<h3 data-start=\"880\" data-end=\"912\">4. How can I track my order?</h3>\r\n<p data-start=\"913\" data-end=\"1068\">Once your order is shipped, you will receive a tracking ID via SMS or email. You can use this tracking ID in the <strong data-start=\"1026\" data-end=\"1044\">Order Tracking</strong> section on our website.</p>\r\n<hr data-start=\"1070\" data-end=\"1073\">\r\n<h3 data-start=\"1075\" data-end=\"1110\">5. How long does delivery take?</h3>\r\n<p data-start=\"1111\" data-end=\"1218\">Delivery usually takes 3&ndash;7 business days depending on your location. Remote areas may take slightly longer.</p>\r\n<hr data-start=\"1220\" data-end=\"1223\">\r\n<h3 data-start=\"1225\" data-end=\"1254\">6. Can I cancel my order?</h3>\r\n<p data-start=\"1255\" data-end=\"1355\">Yes, you can cancel your order before it is shipped. Once shipped, cancellation may not be possible.</p>\r\n<hr data-start=\"1357\" data-end=\"1360\">\r\n<h3 data-start=\"1362\" data-end=\"1396\">7. What is your return policy?</h3>\r\n<p data-start=\"1397\" data-end=\"1543\">We accept returns for damaged or defective products within 7 days of delivery. Please contact our support team with product photos for assistance.</p>\r\n<hr data-start=\"1545\" data-end=\"1548\">\r\n<h3 data-start=\"1550\" data-end=\"1592\">8. How can I contact customer support?</h3>\r\n<p data-start=\"1593\" data-end=\"1620\">You can contact us through:</p>\r\n<ul data-start=\"1621\" data-end=\"1709\">\r\n<li data-start=\"1621\" data-end=\"1655\">\r\n<p data-start=\"1623\" data-end=\"1655\">Contact Us page on the website</p>\r\n</li>\r\n<li data-start=\"1656\" data-end=\"1673\">\r\n<p data-start=\"1658\" data-end=\"1673\">Email support</p>\r\n</li>\r\n<li data-start=\"1674\" data-end=\"1709\">\r\n<p data-start=\"1676\" data-end=\"1709\">WhatsApp support (if available)</p>\r\n</li>\r\n</ul>\r\n<p data-start=\"1711\" data-end=\"1752\">Our team will respond within 24&ndash;48 hours.</p>\r\n<hr data-start=\"1754\" data-end=\"1757\">\r\n<h3 data-start=\"1759\" data-end=\"1799\">9. Is my payment information secure?</h3>\r\n<p data-start=\"1800\" data-end=\"1901\">Yes, we use secure and encrypted payment gateways to ensure your payment details are completely safe.</p>\r\n<hr data-start=\"1903\" data-end=\"1906\">\r\n<h3 data-start=\"1908\" data-end=\"1962\">10. Do you offer bulk orders or special discounts?</h3>\r\n<p data-start=\"1963\" data-end=\"2070\">Yes, for bulk orders or business inquiries, please contact our support team for special pricing and offers.</p>', 'Help Center FAQ – Orders, Shipping, Returns & Payments', 'Find answers to frequently asked questions about orders, payments, shipping, returns, and account issues. Visit our FAQ page for quick help and support.', '2026-04-11 12:18:05', '2026-02-26 00:03:56'),
('10', 'support-07638', 'Support', '<h2 data-section-id=\"1a8co2j\" data-start=\"278\" data-end=\"297\">1. Order Support</h2>\r\n<p>Get help with your orders including order status, order confirmation, order changes, and order issues. Our support team is ready to assist you with any order-related questions.</p>\r\n<h2 data-section-id=\"1d7g982\" data-start=\"483\" data-end=\"501\">2. Payment Help</h2>\r\n<p data-section-id=\"1d7g982\" data-start=\"483\" data-end=\"501\">Need assistance with payments? Find help with payment methods, payment confirmation, transaction issues, and secure checkout support.</p>\r\n<h2 data-section-id=\"1d7g982\" data-start=\"483\" data-end=\"501\">3. Shipping &amp; Delivery Help</h2>\r\n<p>Learn about shipping times, delivery process, tracking information, and shipping charges. We are here to help you with any delivery-related questions.</p>\r\n<h2>4. Returns &amp; Refund Support</h2>\r\n<p>Need to return a product or request a refund? Find information about return eligibility, return process, refund timelines, and exchange options.</p>', 'Help Center – Support for Orders, Shipping, Payments & Returns', 'Get fast support for your orders, payments, shipping, returns, and account issues. Contact our support team or find answers to common questions in our help center.', '2026-04-11 12:18:05', '2026-03-04 12:30:38');

-- --------------------------------------------------------
-- Table structure for `payment_gateways`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_test_mode` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_name` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payment_gateways` (`id`, `gateway_name`, `display_name`, `is_active`, `is_test_mode`, `is_default`, `created_at`, `updated_at`) VALUES 
('1', 'phonepe', 'PhonePe', '1', '1', '1', '2026-02-25 15:04:24', '2026-02-25 15:11:42'),
('2', 'razorpay', 'Razorpay', '0', '1', '0', '2026-02-25 15:04:24', '2026-02-25 15:04:24'),
('3', 'paytm', 'Paytm', '0', '1', '0', '2026-02-25 15:04:24', '2026-02-25 15:04:24'),
('4', 'stripe', 'Stripe', '0', '1', '0', '2026-02-25 15:04:24', '2026-02-25 15:04:24'),
('5', 'cod', 'Cash on Delivery', '1', '0', '0', '2026-02-25 15:04:24', '2026-02-25 15:08:39');

-- --------------------------------------------------------
-- Table structure for `payment_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_logs`;
CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `gateway_id` int(11) DEFAULT NULL,
  `log_type` enum('webhook','api_request','api_response','error') NOT NULL,
  `payload` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `gateway_id` (`gateway_id`),
  CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_logs_ibfk_2` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `payment_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_settings`;
CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL,
  `merchant_id` varchar(255) DEFAULT NULL,
  `salt_key` varchar(255) DEFAULT NULL,
  `salt_index` varchar(10) DEFAULT '1',
  `mode` enum('TEST','LIVE') DEFAULT 'TEST',
  `is_active` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_name` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payment_settings` (`id`, `gateway_name`, `merchant_id`, `salt_key`, `salt_index`, `mode`, `is_active`, `updated_at`) VALUES 
('1', 'PHONEPE', 'PGTESTPAYUAT86', '96434309-7796-489d-8924-ab56988a6076', '1', 'TEST', '1', '2026-02-25 13:42:45');

-- --------------------------------------------------------
-- Table structure for `phonepe_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `phonepe_transactions`;
CREATE TABLE `phonepe_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `provider_reference_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','CANCELLED') DEFAULT 'PENDING',
  `payment_mode` varchar(50) DEFAULT NULL,
  `response_code` varchar(50) DEFAULT NULL,
  `response_msg` varchar(255) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_phonepe_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `product_images`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `position` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_product_image` (`product_id`),
  CONSTRAINT `fk_product_image` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `product_page_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_page_settings`;
CREATE TABLE `product_page_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `product_reviews`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_title` varchar(255) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `images` text DEFAULT NULL,
  `status` enum('approved','pending','rejected') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `product_share_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_share_settings`;
CREATE TABLE `product_share_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `whatsapp_status` int(11) DEFAULT 1,
  `facebook_status` int(11) DEFAULT 1,
  `telegram_status` int(11) DEFAULT 1,
  `copylink_status` int(11) DEFAULT 1,
  `section_title` varchar(255) DEFAULT 'Share Product',
  `icon_style` enum('rounded','square','circle') DEFAULT 'rounded',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_share_settings` (`id`, `whatsapp_status`, `facebook_status`, `telegram_status`, `copylink_status`, `section_title`, `icon_style`, `updated_at`) VALUES 
('1', '1', '1', '1', '1', 'Share Product', 'rounded', '2026-03-04 12:35:07');

-- --------------------------------------------------------
-- Table structure for `products`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `features` text DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `product_type` enum('physical','virtual','downloadable') DEFAULT 'physical',
  `download_file` varchar(255) DEFAULT NULL,
  `download_url` varchar(255) DEFAULT NULL,
  `download_limit` int(11) DEFAULT NULL,
  `download_expiry_days` int(11) DEFAULT NULL,
  `license_key` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `min_order_qty` int(11) NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_fit` enum('cover','contain') NOT NULL DEFAULT 'cover',
  `regular_price` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `bulk_price` decimal(10,2) DEFAULT NULL,
  `bulk_min_qty` int(11) DEFAULT NULL,
  `bulk_shipping_cost` decimal(10,2) DEFAULT NULL,
  `bulk_cod_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Allow COD for Bulk Orders (1=Yes, 0=No)',
  `sku` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `weight` decimal(10,2) DEFAULT 0.00,
  `length` decimal(10,2) DEFAULT 0.00,
  `width` decimal(10,2) DEFAULT 0.00,
  `height` decimal(10,2) DEFAULT 0.00,
  `cod_available` tinyint(1) DEFAULT 1,
  `is_trending` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `review_count` int(11) DEFAULT 0,
  `gtin` varchar(14) DEFAULT NULL,
  `mpn` varchar(70) DEFAULT NULL,
  `condition_type` enum('new','refurbished','used') DEFAULT 'new',
  `google_product_category` varchar(255) DEFAULT NULL,
  `cod_charge` decimal(10,2) DEFAULT NULL COMMENT 'Per-product COD charge (NULL = use global default)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`id`, `name`, `slug`, `short_description`, `description`, `meta_description`, `category_id`, `product_type`, `download_file`, `download_url`, `download_limit`, `download_expiry_days`, `regular_price`, `sale_price`, `price`, `sku`, `brand`, `stock`, `shipping_cost`, `weight`, `length`, `width`, `height`, `cod_available`, `image`, `image_fit`, `created_at`) VALUES ('1', 'Royal Diamond Ring', 'royal-diamond-ring', 'Exquisite handcrafted ring with brilliant cut diamond.', 'Exquisite handcrafted ring with brilliant cut diamond set in gold.', NULL, '4', 'physical', NULL, NULL, NULL, NULL, '14999.00', '12999.00', '12999.00', NULL, NULL, '10', '0.00', '0.00', '0.00', '0.00', '0.00', '1', NULL, 'cover', '2026-08-06 10:16:36');
INSERT INTO `products` (`id`, `name`, `slug`, `short_description`, `description`, `meta_description`, `category_id`, `product_type`, `download_file`, `download_url`, `download_limit`, `download_expiry_days`, `regular_price`, `sale_price`, `price`, `sku`, `brand`, `stock`, `shipping_cost`, `weight`, `length`, `width`, `height`, `cod_available`, `image`, `image_fit`, `created_at`) VALUES ('2', 'Gold Pearl Necklace', 'gold-pearl-necklace', 'Elegant freshwater pearl necklace with gold chain.', 'Elegant freshwater pearl necklace with gold chain.', NULL, '4', 'physical', NULL, NULL, NULL, NULL, '24999.00', '21999.00', '21999.00', NULL, NULL, '5', '0.00', '0.00', '0.00', '0.00', '0.00', '1', NULL, 'cover', '2026-08-06 10:16:36');
INSERT INTO `products` (`id`, `name`, `slug`, `short_description`, `description`, `meta_description`, `category_id`, `product_type`, `download_file`, `download_url`, `download_limit`, `download_expiry_days`, `regular_price`, `sale_price`, `price`, `sku`, `brand`, `stock`, `shipping_cost`, `weight`, `length`, `width`, `height`, `cod_available`, `image`, `image_fit`, `created_at`) VALUES ('3', 'Crystal Drop Earrings', 'crystal-drop-earrings', 'Stunning sparkling crystal drop earrings.', 'Stunning sparkling crystal drop earrings for special occasions.', NULL, '4', 'physical', NULL, NULL, NULL, NULL, '4999.00', '3999.00', '3999.00', NULL, NULL, '15', '0.00', '0.00', '0.00', '0.00', '0.00', '1', NULL, 'cover', '2026-08-06 10:16:36');
INSERT INTO `products` (`id`, `name`, `slug`, `short_description`, `description`, `meta_description`, `category_id`, `product_type`, `download_file`, `download_url`, `download_limit`, `download_expiry_days`, `regular_price`, `sale_price`, `price`, `sku`, `brand`, `stock`, `shipping_cost`, `weight`, `length`, `width`, `height`, `cod_available`, `image`, `image_fit`, `created_at`) VALUES ('4', 'Silver Classic Bracelet', 'silver-classic-bracelet', 'Timeless sterling silver charm bracelet.', 'Timeless sterling silver charm bracelet with secure clasp.', NULL, '4', 'physical', NULL, NULL, NULL, NULL, '6999.00', '5999.00', '5999.00', NULL, NULL, '8', '0.00', '0.00', '0.00', '0.00', '0.00', '1', NULL, 'cover', '2026-08-06 10:16:36');

-- --------------------------------------------------------
-- Table structure for `seo_metadata`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `seo_metadata`;
CREATE TABLE `seo_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('home','shop','product','category','page') NOT NULL,
  `entity_id` int(11) NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `twitter_title` varchar(255) DEFAULT NULL,
  `twitter_description` text DEFAULT NULL,
  `twitter_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots_tag` varchar(50) DEFAULT 'index, follow',
  `focus_keyword` varchar(255) DEFAULT NULL,
  `schema_markup` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_idx` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `seo_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `seo_settings`;
CREATE TABLE `seo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('about_docs_enabled', '1'),
('about_docs_title', 'Our Documents / Certifications'),
('about_f_desc1', 'We ensure your packages arrive on time, every time safely to your doorstep.'),
('about_f_desc2', 'Every item is carefully inspected to meet our strict quality and design standards.'),
('about_f_desc3', 'Our dedicated customer service team is always here to help you when needed.'),
('about_f_icon1', 'fas fa-truck'),
('about_f_icon2', 'fas fa-hand-holding-heart'),
('about_f_icon3', 'fas fa-headset'),
('about_f_title1', 'Fast Delivery'),
('about_f_title2', 'Quality Promise'),
('about_f_title3', '24/7 Support'),
('about_hero_subtitle', 'Learn more about our journey and values.'),
('about_hero_title', 'About Us'),
('about_team_enabled', '1'),
('about_team_title', 'Our Team'),
('about_who_desc1', 'Welcome to Sagar Starter\'s. We are dedicated to providing you the very best of products, with an emphasis on quality, customer service, and uniqueness.'),
('about_who_desc2', 'Founded with a passion for modern aesthetics and functional design, we have come a long way from our beginnings.'),
('about_who_image', 'about_who_1772962674.jpeg'),
('about_who_title', 'Who We Are'),
('admin_email', 'sagarstarters@gmail.com'),
('admin_last_seen_customer_id', '14'),
('admin_last_seen_order_id', '5'),
('auto_text_contrast', '1'),
('chatbot_enabled', '1'),
('chatbot_gemini_key', ''),
('chatbot_gemini_model', 'gemini-1.5-flash'),
('chatbot_groq_key', ''),
('chatbot_groq_model', 'llama-3.3-70b-versatile'),
('chatbot_name', 'Sagar Sahayak'),
('chatbot_openai_key', ''),
('chatbot_openai_model', 'gpt-4o-mini'),
('chatbot_position', 'bottom-right'),
('chatbot_provider', 'hybrid'),
('chatbot_quick_prompts', '5HP Submersible Starter,Single Phase vs 3 Phase,Track My Order,Bulk Purchase Discount,Talk to Expert on WhatsApp'),
('chatbot_response_delay', '800'),
('chatbot_system_prompt', 'You are Sagar Sahayak, the intelligent, friendly, and expert AI Assistant for Sagar Starters (sagarstarters.com) — an Indian eCommerce store specializing in premium motor starters, submersible pump control panels (1-Phase & 3-Phase), Star Delta starters, DOL starters, circuit breakers, and agricultural motor automation. Respond in a warm, professional, and helpful tone. Answer in the same language the customer speaks (Hindi, Hinglish, English, Gujarati, etc.). When customers ask about products, recommend matching items with their specs and prices. Always offer help with order tracking, bulk discounts, and technical advice.'),
('chatbot_theme_color', '#007aff'),
('chatbot_title', 'Sagar AI Assistant'),
('chatbot_welcome_msg', 'Namaste! 🙏 Main Sagar Starters ka AI Assistant hu. Main aapko Motor Starters, Submersible Panels, Price, Bulk Discounts aur Order Tracking me help kar sakta hu.

Aap niche diye gaye options chun sakte hain ya direct message likh sakte hain!'),
('chatbot_whatsapp_number', '918573934013'),
('cod_advance_enabled', '0'),
('cod_advance_min_order', '0'),
('cod_advance_percentage', '30'),
('cod_charge_amount', '50'),
('cod_charge_enabled', '0'),
('cod_charge_mode', 'highest'),
('cod_default_charge', '0'),
('cod_enabled', '1'),
('cod_free_threshold', '0'),
('contact_address', 'Alipur Madra
Jakhanian Ghazipur'),
('contact_email', 'support@example.com'),
('contact_form_btn', 'Send Message'),
('contact_form_title', 'Send us a Message'),
('contact_hero_gradient', 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)'),
('contact_hero_subtitle', 'We\'d love to hear from you. Get in touch with us!'),
('contact_hero_title', 'Contact Us'),
('contact_hours', 'Mon–Sat: 9am – 6pm'),
('contact_label_address', 'Our Location'),
('contact_label_email', 'Email Address'),
('contact_label_hours', 'Business Hours'),
('contact_label_phone', 'Phone Number'),
('contact_map_embed', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d224.61079585185587!2d83.38805019608058!3d25.74502820171901!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3991efb10206f5dd%3A0x6e8aa11c51cb3018!2sSAGAR%20STARTER\'S!5e0!3m2!1sen!2sin!4v1772701010248!5m2!1sen!2sin'),
('contact_map_show', '1'),
('contact_phone', '+918573934013'),
('contact_section_desc', 'Have a question about a product, your order, or just want to say hi? Contact us below and we will get back to you as soon as possible!'),
('contact_section_title', 'Get In Touch'),
('contact_success_msg', 'Thank you for reaching out! We will get back to you shortly.'),
('currency_symbol', '₹'),
('enable_email_notifications', '1'),
('enable_header_search', '1'),
('footer_col2_title', 'For Him'),
('footer_col3_title', 'Legal'),
('footer_col4_title', 'Help'),
('footer_copyright', 'Copyright © 2026 Sagar Starter\'s. Powered by Sagar Starter\'s.'),
('footer_logo_height', '30'),
('footer_logo_image', 'logo.jpg'),
('footer_text', 'The Best Quality Motor Starter\'s Anytime, Anywhere.'),
('free_cod_enabled', '0'),
('free_cod_min_order', '500'),
('google_client_id', ''),
('google_client_secret', ''),
('google_login_enabled', '0'),
('google_one_tap_enabled', '0'),
('header_announcement', 'GSTIN:09BWSPS4806F1ZZ'),
('header_logo_height', '30'),
('header_logo_image', 'logo_1772120128.jpeg'),
('hero_banner_about', 'hero_about_1772962495.jpeg'),
('hero_banner_category', 'hero_category_1773047892.png'),
('hero_banner_contact', 'hero_contact_1773047768.png'),
('hero_banner_faq', 'hero_faq_1773047867.png'),
('hero_banner_policy', 'hero_policy_1773047799.png'),
('hero_banner_product', 'hero_product_1773047844.png'),
('hero_banner_support', 'hero_support_1773047768.png'),
('homepage_features_enabled', '1'),
('invoice_auto_generate', '1'),
('invoice_auto_send_whatsapp', '0'),
('invoice_footer_text', 'Thank you for shopping with us!'),
('invoice_gst_number', ''),
('invoice_prefix', 'INV'),
('invoice_store_address', ''),
('invoice_store_email', ''),
('invoice_store_name', 'Sagar Starter\'s'),
('invoice_store_phone', ''),
('invoice_terms', 'Goods once sold are not returnable. Subject to local jurisdiction.'),
('last_system_optimize', '2026-03-09 21:14:07'),
('maintenance_mode', '0'),
('manual_upi_enabled', '1'),
('phonepe_enabled', '1'),
('phonepe_merchant_id', 'SAGARELEONLINE'),
('phonepe_mode', 'sandbox'),
('phonepe_salt_index', ''),
('phonepe_salt_key', ''),
('product_return_text', '7-Day Return Policy For Defected Products'),
('product_shipping_text', 'Free Shipping on orders over ₹1000'),
('product_title_size', 'h2'),
('product_warranty_text', '3 Months Warranty Included'),
('seo_global_description', 'Welcome to our premium eCommerce store. Shop the latest collections with fast shipping and secure checkout.'),
('seo_global_keywords', 'ecommerce, online shopping, electronics, fashion'),
('seo_global_title', 'Premium eCommerce Store'),
('seo_og_image', ''),
('seo_twitter_handle', '@yourstore'),
('show_footer_logo', '1'),
('show_header_logo', '1'),
('smtp_encryption', 'ssl'),
('smtp_host', 'smtp.hostinger.com'),
('smtp_password', 'amgvWlgrSlNML1BCK1l4QkRKVlIrdz09OjrODf4TLtGzU7J3KJab/7Qv'),
('smtp_port', '465'),
('smtp_provider', 'hostinger'),
('smtp_sender_email', 'info@sagarstarters.com'),
('smtp_sender_name', 'Sagar Starte\'s'),
('smtp_username', 'info@sagarstarters.com'),
('social_facebook', 'https://www.facebook.com/share/1HSqEPGVYE/'),
('social_instagram', 'https://www.instagram.com/sagarstarter?igsh=dngwdnlmbHhncW9q'),
('social_linkedin', 'https://www.linkedin.com/in/sagar-starter-s-b372a7238?utm_source=share_via&utm_content=profile&utm_medium=member_android'),
('social_twitter', 'https://x.com/starter_s20880'),
('theme_bg_color', '#f8f9fa'),
('theme_border_radius', '8'),
('theme_button_color', '#1b3c53'),
('theme_button_hover_color', '#456882'),
('theme_card_bg', '#ffffff'),
('theme_font_family', 'Poppins'),
('theme_font_size', '15'),
('theme_footer_bg', '#f7f1de'),
('theme_footer_layout', 'default'),
('theme_footer_link_color', '#333333'),
('theme_header_bg', '#f7f1de'),
('theme_header_style', 'default'),
('theme_link_color', '#1a3263'),
('theme_mode', 'light'),
('theme_primary_color', '#1b3c53'),
('theme_product_title_size', '16'),
('theme_secondary_color', '#6c757d'),
('theme_sticky_header', '1'),
('theme_text_color', '#333333'),
('timezone', 'Asia/Kolkata'),
('whatsapp_number', '918573934013');

-- --------------------------------------------------------
-- Table structure for `shipping_methods`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `shipping_methods`;
CREATE TABLE `shipping_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_delivery` varchar(50) DEFAULT NULL,
  `base_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `shipping_methods` (`id`, `name`, `description`, `estimated_delivery`, `base_cost`, `is_active`, `display_order`, `created_at`) VALUES 
('1', 'Standard Shipping', 'Regular delivery via common carriers.', '3-5 Business Days', '80.00', '1', '1', '2026-02-24 14:58:09'),
('2', 'Express Shipping', 'Priority fastest method available.', '1-2 Business Days', '150.00', '1', '2', '2026-02-24 14:58:09'),
('3', 'Same Day Delivery', 'Local delivery before 8PM.', 'Today', '250.00', '0', '3', '2026-02-24 14:58:09');

-- --------------------------------------------------------
-- Table structure for `shipping_rules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `shipping_rules`;
CREATE TABLE `shipping_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('weight_based','category_based','qty_based','pincode_based') NOT NULL,
  `condition_value` text NOT NULL COMMENT 'JSON or CSV representing threshold (e.g > 5kg)',
  `fee_modifier` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Value to add/subtract or multiply based on rule',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `shipping_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `shipping_settings`;
CREATE TABLE `shipping_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `shipping_settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES 
('1', 'free_shipping_enabled', '0', '1=Yes, 0=No'),
('2', 'free_shipping_min_amount', '1999.00', 'Minimum cart value required for free standard shipping'),
('3', 'default_flat_rate', '80.00', 'Standard flat rate shipping block'),
('4', 'tax_on_shipping_percentage', '0', 'Tax percentage applied strictly to shipping cost. (0 for none)'),
('5', 'cod_extra_charge', '50.00', 'Additional fee for Cash On Delivery selection');

-- --------------------------------------------------------
-- Table structure for `shipping_zones`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `shipping_zones`;
CREATE TABLE `shipping_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `site_backups`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `site_backups`;
CREATE TABLE `site_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` enum('full','db_only','files_only') NOT NULL DEFAULT 'full',
  `trigger_type` enum('manual','auto') NOT NULL DEFAULT 'manual',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT 0,
  `db_tables_count` int(10) unsigned DEFAULT 0,
  `files_count` int(10) unsigned DEFAULT 0,
  `status` enum('in_progress','completed','failed','restored') NOT NULL DEFAULT 'in_progress',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_trigger_type` (`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_analytics`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_analytics`;
CREATE TABLE `sm_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `account_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `posts_scheduled` int(11) DEFAULT 0,
  `posts_published` int(11) DEFAULT 0,
  `posts_failed` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform` (`platform`,`account_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_bulk_jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_bulk_jobs`;
CREATE TABLE `sm_bulk_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(255) DEFAULT NULL,
  `filter_type` enum('all','selected','category','brand') NOT NULL,
  `filter_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filter_value`)),
  `schedule_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `total_products` int(11) DEFAULT 0,
  `processed_products` int(11) DEFAULT 0,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_connected_accounts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_connected_accounts`;
CREATE TABLE `sm_connected_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `account_id` varchar(255) DEFAULT NULL,
  `page_id` varchar(255) DEFAULT NULL,
  `access_token_encrypted` text DEFAULT NULL,
  `refresh_token_encrypted` text DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `connected_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_hashtag_groups`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_hashtag_groups`;
CREATE TABLE `sm_hashtag_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `hashtags` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_logs`;
CREATE TABLE `sm_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error','debug') DEFAULT 'info',
  `category` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `queue_id` int(11) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `level` (`level`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_post_history`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_post_history`;
CREATE TABLE `sm_post_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `account_id` int(11) NOT NULL,
  `posted_at` datetime NOT NULL,
  `post_hash` varchar(64) NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`platform`,`account_id`,`post_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_queue`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_queue`;
CREATE TABLE `sm_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `account_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `status` enum('pending','scheduled','publishing','posted','failed','retry') DEFAULT 'pending',
  `post_content` text DEFAULT NULL,
  `post_image_url` varchar(500) DEFAULT NULL,
  `post_link` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `platform_post_id` varchar(255) DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `max_retries` int(11) DEFAULT 3,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `product_id` (`product_id`),
  KEY `platform` (`platform`),
  KEY `idx_status_scheduled` (`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_repost_rules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_repost_rules`;
CREATE TABLE `sm_repost_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `repost_interval_days` int(11) DEFAULT 30,
  `is_enabled` tinyint(1) DEFAULT 1,
  `max_reposts` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_schedules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_schedules`;
CREATE TABLE `sm_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `platform_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`platform_ids`)),
  `schedule_type` enum('every_5min','every_15min','every_30min','every_1hr','every_2hr','every_6hr','daily','weekly','monthly','custom') NOT NULL,
  `interval_minutes` int(11) DEFAULT 60,
  `custom_cron` varchar(100) DEFAULT NULL,
  `days_of_week` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`days_of_week`)),
  `time_slots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`time_slots`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `template_id` int(11) DEFAULT NULL,
  `cta` varchar(255) DEFAULT NULL,
  `hashtags` text DEFAULT NULL,
  `filter_type` varchar(50) DEFAULT 'all',
  `filter_value` text DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `start_mode` varchar(50) DEFAULT 'once_day',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sm_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_settings`;
CREATE TABLE `sm_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sm_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES 
('1', 'auto_queue_new_products', '1', '2026-08-07 08:11:20'),
('2', 'default_template_id', '1', '2026-08-07 08:11:20'),
('3', 'max_retries', '3', '2026-08-07 08:11:20'),
('4', 'retry_backoff', 'exponential', '2026-08-07 08:11:20'),
('5', 'batch_size', '10', '2026-08-07 08:11:20'),
('6', 'cron_secret_key', '96e9f6fa819a595ed5f24183a948aa5b', '2026-08-07 08:11:20'),
('7', 'duplicate_protection', '1', '2026-08-07 08:11:20'),
('8', 'default_repost_interval', '30', '2026-08-07 08:11:20');

-- --------------------------------------------------------
-- Table structure for `sm_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sm_templates`;
CREATE TABLE `sm_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `template_body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `platform_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`platform_tags`)),
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sm_templates` (`id`, `name`, `template_body`, `variables`, `platform_tags`, `is_default`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES 
('1', 'New Arrival', '🚀 New Arrival! ✨\n{product_name}\nOnly at {price}\n\nShop now: {product_url}\n\n{hashtags}', NULL, NULL, '1', '1', NULL, '2026-08-07 08:11:20', '2026-08-07 08:11:20'),
('2', 'Special Offer', '🔥 Special Offer! 💸\n{product_name}\nRegular: {regular_price} | Now: {sale_price} ({discount_percent}% OFF!)\n\nGrab it here: {product_url}\n\n{hashtags}', NULL, NULL, '0', '1', NULL, '2026-08-07 08:11:20', '2026-08-07 08:11:20'),
('3', 'Festival Special', '🎉 Festival Special! 🎊\nCelebrate with {product_name}!\n\nGet yours now: {product_url}\n\n{hashtags}', NULL, NULL, '0', '1', NULL, '2026-08-07 08:11:20', '2026-08-07 08:11:20'),
('4', 'Quick Share', 'Check out {product_name}: {product_url}', NULL, NULL, '0', '1', NULL, '2026-08-07 08:11:20', '2026-08-07 08:11:20'),
('5', 'Premium Spotlight', '🔥 PREMIUM PRODUCT SPOTLIGHT 🔥\n\n✨ {product_name}\n\n💰 Best Price: ₹{price}\n✅ Guaranteed Quality & Heavy Duty Performance\n🚚 Express Shipping Across India\n\n🛒 Order Direct Here: {product_url}\n\n{cta}\n\n{hashtags}', NULL, NULL, '1', '1', NULL, '2026-08-07 16:41:22', '2026-08-07 16:41:22');

-- --------------------------------------------------------
-- Table structure for `subscribers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subscribers`;
CREATE TABLE `subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `team_members`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `team_members`;
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `testimonials`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `testimonial` text NOT NULL,
  `rating` tinyint(1) DEFAULT 5,
  `image_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `gateway_id` int(11) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_txn` (`transaction_id`),
  KEY `order_id` (`order_id`),
  KEY `gateway_id` (`gateway_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `user_downloads`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_downloads`;
CREATE TABLE `user_downloads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `download_token` varchar(100) NOT NULL,
  `download_count` int(11) DEFAULT 0,
  `expiry_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `download_token` (`download_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `google_email` varchar(255) DEFAULT NULL,
  `google_avatar` text DEFAULT NULL,
  `auth_provider` varchar(50) DEFAULT 'email',
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip_code` varchar(15) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','retailer') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `cart_data` text DEFAULT NULL,
  `opt_out_cart_reminders` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_reset_token` (`reset_token`),
  KEY `idx_verification_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Admin User (admin@example.com / Admin@123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `is_verified`, `created_at`) VALUES (1, 'Store Administrator', 'admin@example.com', '$2y$10$SFxOrTdB9bDSixOH44052uDsQMkh8CzuAWaeg9PqjgDyl1sXzp/O.', '+919876543210', 'admin', 1, NOW());

-- --------------------------------------------------------
-- Table structure for `whatsapp_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_number` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sending_mode` enum('web','api') NOT NULL,
  `status` varchar(50) DEFAULT 'Sent via Web',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `whatsapp_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `whatsapp_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_settings`;
CREATE TABLE `whatsapp_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_enabled` int(11) DEFAULT 1,
  `sender_number` varchar(20) DEFAULT NULL,
  `api_token` text DEFAULT NULL,
  `sending_mode` enum('web','api') DEFAULT 'web',
  `message_template` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chat_widget_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `chat_widget_number` varchar(20) NOT NULL DEFAULT '',
  `chat_widget_message` varchar(255) NOT NULL DEFAULT 'Hello, I have a question about your products.',
  `phone_number_id` varchar(50) NOT NULL DEFAULT '',
  `meta_template_name` varchar(100) NOT NULL DEFAULT '',
  `meta_template_lang` varchar(10) NOT NULL DEFAULT 'en',
  `wa_header_image_url` varchar(500) NOT NULL DEFAULT '',
  `order_status_notify_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `waba_id` varchar(50) NOT NULL DEFAULT '',
  `admin_whatsapp_number` varchar(20) NOT NULL DEFAULT '',
  `admin_notify_on_new_order` tinyint(1) NOT NULL DEFAULT 1,
  `admin_template_name` varchar(100) NOT NULL DEFAULT '',
  `order_confirmation_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `order_confirmation_template_name` varchar(100) NOT NULL DEFAULT '',
  `order_confirmation_message_template` text NOT NULL DEFAULT '',
  `admin_message_template` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default WhatsApp Settings
INSERT INTO `whatsapp_settings` (`id`, `is_enabled`, `sender_number`, `api_token`, `sending_mode`, `message_template`, `updated_at`, `chat_widget_enabled`, `chat_widget_number`, `chat_widget_message`, `phone_number_id`, `meta_template_name`, `meta_template_lang`, `wa_header_image_url`, `order_status_notify_enabled`, `waba_id`, `admin_whatsapp_number`, `admin_notify_on_new_order`, `admin_template_name`, `order_confirmation_enabled`, `order_confirmation_template_name`, `order_confirmation_message_template`, `admin_message_template`) VALUES ('1', '1', '', '', 'web', 'Hello Dear {CustomerName},\n\nYour Order No. #{OrderID} status has been updated.\n\nCurrent Status: *{OrderStatus}*\nTracking ID: {TrackingID}\nTotal Amount: ₹{OrderAmount}\n\nThank you for shopping with us.', '2026-04-04 11:07:06', '1', '', 'Hello, I have a question about your products.', '', 'order_status_updates', 'en', '', '1', '', '', '1', '', '1', '', '', '');

SET FOREIGN_KEY_CHECKS = 1;
