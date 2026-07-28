<?php
/**
 * WAPI SaaS - CRM Database Migration
 * Safely adds missing columns to the contacts table
 */
require_once __DIR__ . '/../config/config.php';

// Only allow admins or run via CLI
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../config/session.php';
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        die("Access denied. Admin privileges required.");
    }
}

$db = Database::getInstance();

try {
    echo "Starting CRM migration...\n";

    // 1. Add 'status' column
    $columns = $db->fetchAll("SHOW COLUMNS FROM contacts LIKE 'status'");
    if (empty($columns)) {
        $db->run("ALTER TABLE contacts ADD COLUMN `status` VARCHAR(50) DEFAULT 'Lead' AFTER `tags` ");
        $db->run("ALTER TABLE contacts ADD INDEX `idx_status` (`status`) ");
        echo "Added 'status' column.\n";
    }

    // 2. Add 'estimated_value' column
    $columns = $db->fetchAll("SHOW COLUMNS FROM contacts LIKE 'estimated_value'");
    if (empty($columns)) {
        $db->run("ALTER TABLE contacts ADD COLUMN `estimated_value` DECIMAL(10,2) DEFAULT 0.00 AFTER `status` ");
        echo "Added 'estimated_value' column.\n";
    }

    // 3. Add 'source' column
    $columns = $db->fetchAll("SHOW COLUMNS FROM contacts LIKE 'source'");
    if (empty($columns)) {
        $db->run("ALTER TABLE contacts ADD COLUMN `source` VARCHAR(100) DEFAULT 'Direct' AFTER `estimated_value` ");
        echo "Added 'source' column.\n";
    }

    echo "Migration completed successfully!\n";
    
    if (php_sapi_name() !== 'cli') {
        echo "<br><br><a href='../dashboard/crm.php'>Go to CRM</a>";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
