<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance();
    
    // 1. Update templates table: check if variables column exists
    $columns = $db->fetchAll("SHOW COLUMNS FROM templates LIKE 'variables'");
    if (empty($columns)) {
        $db->query("ALTER TABLE templates ADD COLUMN variables LONGTEXT NULL AFTER body");
        echo "Database updated: Added 'variables' column to templates table.\n";
    } else {
        echo "Database check: 'variables' column already exists in templates table.\n";
    }

    // 2. Update messages table: check if type enum includes 'unsupported'
    $typeCol = $db->fetch("SHOW COLUMNS FROM messages LIKE 'type'");
    if ($typeCol) {
        $typeDef = $typeCol['Type'] ?? $typeCol['type'] ?? '';
        if (strpos($typeDef, 'unsupported') === false) {
            $db->query("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'image', 'video', 'document', 'audio', 'voice', 'sticker', 'location', 'contacts', 'interactive', 'button', 'template', 'reaction', 'system', 'identity', 'unsupported') DEFAULT 'text'");
            echo "Database updated: Added 'unsupported' value to type ENUM in messages table.\n";
        } else {
            echo "Database check: 'unsupported' value already exists in messages table type ENUM.\n";
        }
    }
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
