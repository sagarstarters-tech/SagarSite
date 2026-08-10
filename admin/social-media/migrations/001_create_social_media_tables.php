<?php
declare(strict_types=1);

/**
 * Migration: Create Social Media Automation Tables
 */

$baseDir = dirname(__DIR__, 3);
if (file_exists($baseDir . '/config/DbConnection.php')) {
    require_once $baseDir . '/config/DbConnection.php';
}

function runMigration() {
    $db = DbConnection::getInstance();
    
    $tables = [
        'sm_connected_accounts' => "CREATE TABLE IF NOT EXISTS sm_connected_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            account_name VARCHAR(255),
            account_id VARCHAR(255),
            page_id VARCHAR(255),
            access_token_encrypted TEXT,
            refresh_token_encrypted TEXT,
            token_expires_at DATETIME NULL,
            scopes TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            connected_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_schedules' => "CREATE TABLE IF NOT EXISTS sm_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            platform_ids JSON,
            schedule_type ENUM('every_5min','every_15min','every_30min','every_1hr','every_2hr','every_6hr','daily','weekly','monthly','custom') NOT NULL,
            interval_minutes INT DEFAULT 60,
            custom_cron VARCHAR(100) NULL,
            days_of_week JSON NULL,
            time_slots JSON NULL,
            template_id INT NULL,
            cta VARCHAR(255) NULL,
            hashtags TEXT NULL,
            filter_type VARCHAR(50) DEFAULT 'all',
            filter_value TEXT NULL,
            last_run_at DATETIME NULL,
            next_run_at DATETIME NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_templates' => "CREATE TABLE IF NOT EXISTS sm_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            template_body TEXT NOT NULL,
            variables JSON NULL,
            platform_tags JSON NULL,
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_queue' => "CREATE TABLE IF NOT EXISTS sm_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            platform VARCHAR(50) NOT NULL,
            account_id INT NOT NULL,
            schedule_id INT NULL,
            template_id INT NULL,
            status ENUM('pending','scheduled','publishing','posted','failed','retry') DEFAULT 'pending',
            post_content TEXT,
            post_image_url VARCHAR(500),
            post_link VARCHAR(500),
            scheduled_at DATETIME NULL,
            published_at DATETIME NULL,
            platform_post_id VARCHAR(255) NULL,
            retry_count INT DEFAULT 0,
            max_retries INT DEFAULT 3,
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (status),
            INDEX (scheduled_at),
            INDEX (product_id),
            INDEX (platform),
            INDEX (status, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_hashtag_groups' => "CREATE TABLE IF NOT EXISTS sm_hashtag_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            hashtags TEXT NOT NULL,
            category_id INT NULL,
            is_global TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_post_history' => "CREATE TABLE IF NOT EXISTS sm_post_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            platform VARCHAR(50) NOT NULL,
            account_id INT NOT NULL,
            posted_at DATETIME NOT NULL,
            post_hash VARCHAR(64) NOT NULL,
            queue_id INT NULL,
            UNIQUE INDEX (product_id, platform, account_id, post_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_analytics' => "CREATE TABLE IF NOT EXISTS sm_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            account_id INT NOT NULL,
            date DATE NOT NULL,
            posts_scheduled INT DEFAULT 0,
            posts_published INT DEFAULT 0,
            posts_failed INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX (platform, account_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_logs' => "CREATE TABLE IF NOT EXISTS sm_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level ENUM('info','warning','error','debug') DEFAULT 'info',
            category VARCHAR(100) NULL,
            message TEXT NOT NULL,
            context JSON NULL,
            queue_id INT NULL,
            platform VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (level, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_settings' => "CREATE TABLE IF NOT EXISTS sm_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_bulk_jobs' => "CREATE TABLE IF NOT EXISTS sm_bulk_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_name VARCHAR(255),
            filter_type ENUM('all','selected','category','brand') NOT NULL,
            filter_value JSON NULL,
            schedule_id INT NULL,
            template_id INT NULL,
            total_products INT DEFAULT 0,
            processed_products INT DEFAULT 0,
            status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'sm_repost_rules' => "CREATE TABLE IF NOT EXISTS sm_repost_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NULL,
            account_id INT NULL,
            repost_interval_days INT DEFAULT 30,
            is_enabled TINYINT(1) DEFAULT 1,
            max_reposts INT DEFAULT 3,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables as $name => $sql) {
        try {
            $db->exec($sql);
            echo "Table {$name} created or already exists.<br>\n";
        } catch (PDOException $e) {
            echo "Error creating table {$name}: " . $e->getMessage() . "<br>\n";
        }
    }

    // Upgrade sm_schedules table if missing new columns
    $scheduleColumnsToAdd = [
        'template_id'  => "INT NULL",
        'cta'          => "VARCHAR(255) NULL",
        'hashtags'     => "TEXT NULL",
        'filter_type'  => "VARCHAR(50) DEFAULT 'all'",
        'filter_value' => "TEXT NULL",
        'start_mode'   => "VARCHAR(50) DEFAULT 'once_day'",
        'start_date'   => "DATE NULL",
        'start_time'   => "TIME NULL",
        'last_run_at'  => "DATETIME NULL",
        'next_run_at'  => "DATETIME NULL"
    ];
    try {
        $existingCols = $db->query("DESCRIBE sm_schedules")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($scheduleColumnsToAdd as $colName => $colDef) {
            if (!in_array($colName, $existingCols, true)) {
                $db->exec("ALTER TABLE sm_schedules ADD COLUMN {$colName} {$colDef}");
                echo "Added column {$colName} to sm_schedules.<br>\n";
            }
        }
        $db->exec("ALTER TABLE sm_schedules MODIFY COLUMN schedule_type ENUM('every_5min','every_15min','every_30min','every_1hr','every_2hr','every_6hr','daily','weekly','monthly','custom') NOT NULL");
    } catch (PDOException $e) {
        echo "Error upgrading sm_schedules columns: " . $e->getMessage() . "<br>\n";
    }

    try {
        $db->exec("UPDATE sm_queue SET post_image_url = REPLACE(post_image_url, '/uploads/media/images/', '/uploads/images/') WHERE post_image_url LIKE '%/uploads/media/images/%'");
        $db->exec("UPDATE sm_queue SET last_error = NULL WHERE status = 'posted'");
    } catch (PDOException $e) {}

    // Insert Default Templates
    $templates = [
        [
            'name' => 'Premium Spotlight',
            'body' => "🔥 PREMIUM PRODUCT SPOTLIGHT 🔥\n\n✨ {product_name}\n\n💰 Best Price: ₹{price}\n✅ Guaranteed Quality & Heavy Duty Performance\n🚚 Express Shipping Across India\n\n🛒 Order Direct Here: {product_url}\n\n{cta}\n\n{hashtags}",
            'is_default' => 1
        ],
        [
            'name' => 'Special Offer',
            'body' => "⚡ SPECIAL LIMITED OFFER ⚡\n\n🛍️ {product_name}\n\n💸 Special Price: ₹{price}\n🔥 Discount: Up to {discount_percent}% OFF!\n\n👇 Claim Offer Now:\n{product_url}\n\n{cta}\n\n{hashtags}",
            'is_default' => 0
        ],
        [
            'name' => 'New Arrival',
            'body' => "🚀 NEW ARRIVAL IN STORE ✨\n\n⭐ {product_name}\n\n🏷️ Price: ₹{price}\n\n👇 Check details & order online:\n{product_url}\n\n{cta}\n\n{hashtags}",
            'is_default' => 0
        ]
    ];

    $stmt = $db->prepare("SELECT COUNT(*) FROM sm_templates WHERE name = ?");
    $insertStmt = $db->prepare("INSERT INTO sm_templates (name, template_body, is_default) VALUES (?, ?, ?)");
    foreach ($templates as $t) {
        $stmt->execute([$t['name']]);
        if ($stmt->fetchColumn() == 0) {
            $insertStmt->execute([$t['name'], $t['body'], $t['is_default']]);
            echo "Inserted template: {$t['name']}<br>\n";
        }
    }

    // Insert Default Settings
    $settings = [
        'auto_queue_new_products' => '1',
        'default_template_id' => '1',
        'max_retries' => '3',
        'retry_backoff' => 'exponential',
        'batch_size' => '10',
        'cron_secret_key' => bin2hex(random_bytes(16)),
        'duplicate_protection' => '1',
        'default_repost_interval' => '30'
    ];

    $stmt = $db->prepare("SELECT COUNT(*) FROM sm_settings WHERE setting_key = ?");
    $insertStmt = $db->prepare("INSERT INTO sm_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $key => $value) {
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $insertStmt->execute([$key, $value]);
            echo "Inserted setting: {$key}<br>\n";
        }
    }

    echo "Migration completed successfully.<br>\n";
}

if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    runMigration();
}
