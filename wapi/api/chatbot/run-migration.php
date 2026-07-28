<?php
/**
 * DB Migration Script - Visual Chatbot V2
 * Run this on the server to update the database schema.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

// PUBLIC ACCESS FOR EMERGENCY FIX
/*
if (!Auth::isLoggedIn()) {
    die("Unauthorized access. Please login to your dashboard first.");
}
*/

$db = Database::getInstance();

try {
    echo "Starting Migration...<br>";

    echo "Creating/Updating chatbot_flows table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `chatbot_flows` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `flow_json` LONGTEXT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Add columns just in case it existed but old
    try { $db->query("ALTER TABLE `chatbot_flows` ADD COLUMN `flow_json` LONGTEXT AFTER `name` "); } catch (Exception $e) {}
    try { $db->query("ALTER TABLE `chatbot_flows` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `flow_json` "); } catch (Exception $e) {}

    echo "Creating/Updating chatbot_sessions table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `chatbot_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `phone` VARCHAR(20) NOT NULL,
        `user_id` INT NOT NULL DEFAULT 0,
        `flow_id` INT NULL,
        `current_node_id` VARCHAR(100) NULL,
        `state` VARCHAR(50) NOT NULL DEFAULT 'start',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_phone_user` (`phone`, `user_id`),
        INDEX `idx_phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add columns in case it existed
    try { $db->query("ALTER TABLE `chatbot_sessions` ADD COLUMN `flow_id` INT AFTER `phone` "); } catch (Exception $e) {}
    try { $db->query("ALTER TABLE `chatbot_sessions` ADD COLUMN `current_node_id` VARCHAR(100) AFTER `flow_id` "); } catch (Exception $e) {}
    try { $db->query("ALTER TABLE `chatbot_sessions` ADD COLUMN `state` VARCHAR(50) NOT NULL DEFAULT 'start' AFTER `current_node_id`"); } catch (Exception $e) {}

    echo "<br><b>Migration completed successfully!</b><br>";
    echo "You can now go back and save your flows.";

} catch (Exception $e) {
    echo "<br><b style='color:red'>Migration failed:</b> " . $e->getMessage();
}
