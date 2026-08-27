-- ============================================================
--  ANALYTICS TABLES MIGRATION
--  Location: /admin/migrations/analytics_tables.sql
-- ============================================================
--  Creates all tables required for the first-party analytics system.
--  Safe to re-run: uses CREATE TABLE IF NOT EXISTS.
--  Does NOT modify any existing ecommerce tables.
-- ============================================================

-- 1. Analytics Visitors (anonymous visitor/session registry)
CREATE TABLE IF NOT EXISTS `analytics_visitors` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_uid`    CHAR(32)        NOT NULL COMMENT 'First-party anonymous cookie ID',
    `session_id`     CHAR(32)        NOT NULL COMMENT 'Per-session identifier',
    `first_visit`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `landing_page`   VARCHAR(500)    DEFAULT NULL,
    `referrer`       VARCHAR(1000)   DEFAULT NULL,
    `traffic_source` VARCHAR(50)     DEFAULT 'direct' COMMENT 'direct/google/facebook/instagram/youtube/search_engine/referral/other',
    `device_type`    VARCHAR(20)     DEFAULT NULL COMMENT 'mobile/desktop/tablet',
    `browser`        VARCHAR(100)    DEFAULT NULL,
    `os`             VARCHAR(100)    DEFAULT NULL,
    `country`        VARCHAR(100)    DEFAULT NULL,
    `region`         VARCHAR(100)    DEFAULT NULL,
    `city`           VARCHAR(100)    DEFAULT NULL,
    `ip_hash`        CHAR(64)        DEFAULT NULL COMMENT 'SHA-256 hash of IP, never raw IP',
    `is_bot`         TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_visitor_uid` (`visitor_uid`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_last_activity` (`last_activity`),
    INDEX `idx_first_visit` (`first_visit`),
    INDEX `idx_country_region` (`country`, `region`),
    INDEX `idx_traffic_source` (`traffic_source`),
    INDEX `idx_device_type` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Analytics Page Views
CREATE TABLE IF NOT EXISTS `analytics_page_views` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_id`  BIGINT UNSIGNED NOT NULL,
    `page_url`    VARCHAR(500)    NOT NULL,
    `page_title`  VARCHAR(300)    DEFAULT NULL,
    `referrer`    VARCHAR(1000)   DEFAULT NULL,
    `viewed_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_id`  CHAR(32)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_viewed_at` (`viewed_at`),
    INDEX `idx_visitor_id` (`visitor_id`),
    INDEX `idx_page_url_viewed` (`page_url`(191), `viewed_at`),
    INDEX `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Analytics Product Views
CREATE TABLE IF NOT EXISTS `analytics_product_views` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_id`   BIGINT UNSIGNED NOT NULL,
    `product_id`   INT(11)         NOT NULL,
    `product_name` VARCHAR(255)    DEFAULT NULL COMMENT 'Denormalized for fast reporting',
    `referrer`     VARCHAR(1000)   DEFAULT NULL,
    `viewed_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_id`   CHAR(32)        DEFAULT NULL,
    `from_search`  VARCHAR(255)    DEFAULT NULL COMMENT 'Search query that led to this product view',
    PRIMARY KEY (`id`),
    INDEX `idx_viewed_at` (`viewed_at`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_visitor_id` (`visitor_id`),
    INDEX `idx_product_viewed` (`product_id`, `viewed_at`),
    INDEX `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Analytics Searches
CREATE TABLE IF NOT EXISTS `analytics_searches` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_id`    BIGINT UNSIGNED NOT NULL,
    `search_query`  VARCHAR(255)    NOT NULL,
    `result_count`  INT             DEFAULT 0,
    `searched_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_id`    CHAR(32)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_searched_at` (`searched_at`),
    INDEX `idx_search_query_at` (`search_query`(191), `searched_at`),
    INDEX `idx_visitor_id` (`visitor_id`),
    INDEX `idx_result_count` (`result_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Analytics Settings
CREATE TABLE IF NOT EXISTS `analytics_settings` (
    `setting_key`   VARCHAR(100)  NOT NULL,
    `setting_value` TEXT          DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT IGNORE INTO `analytics_settings` (`setting_key`, `setting_value`) VALUES
('retention_months', '12'),
('heartbeat_interval', '60'),
('tracking_enabled', '1'),
('live_visitor_threshold', '300'),
('last_cleanup_run', '0');
