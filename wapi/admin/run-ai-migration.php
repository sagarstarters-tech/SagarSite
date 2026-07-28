<?php
/**
 * AI ChatBot Builder - Database Migration Runner
 * Run this ONCE to create all required AI tables
 * DELETE this file after successful migration!
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireAdmin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$results = [];
$hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {

    $statements = [

        // 1. AI Bots
        "CREATE TABLE IF NOT EXISTS `ai_bots` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `uuid` VARCHAR(36) NOT NULL UNIQUE,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `status` ENUM('active','inactive','suspended') DEFAULT 'inactive',
            `whatsapp_account_id` INT DEFAULT NULL,
            `ai_model` ENUM('gpt-4o','gpt-4.1','gemini','claude','custom') DEFAULT 'gpt-4o',
            `custom_api_endpoint` VARCHAR(500) DEFAULT NULL,
            `custom_api_key_encrypted` TEXT DEFAULT NULL,
            `bot_role` VARCHAR(100) DEFAULT 'Customer Support Agent',
            `business_type` VARCHAR(100) DEFAULT 'General',
            `response_tone` ENUM('professional','friendly','sales','support','healthcare','real_estate','custom') DEFAULT 'professional',
            `response_length` ENUM('concise','moderate','detailed') DEFAULT 'moderate',
            `language` VARCHAR(50) DEFAULT 'English',
            `system_prompt` TEXT DEFAULT NULL,
            `handover_enabled` TINYINT(1) DEFAULT 0,
            `handover_keywords` TEXT DEFAULT 'talk to human,human support,agent,representative',
            `handover_confidence_threshold` DECIMAL(3,2) DEFAULT 0.30,
            `crm_capture_enabled` TINYINT(1) DEFAULT 1,
            `rate_limit_per_minute` INT DEFAULT 100,
            `total_conversations` INT DEFAULT 0,
            `total_messages_processed` INT DEFAULT 0,
            `total_leads_captured` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`whatsapp_account_id`) REFERENCES `whatsapp_accounts`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_status` (`user_id`, `status`),
            INDEX `idx_uuid` (`uuid`),
            INDEX `idx_wa_account` (`whatsapp_account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2. AI Knowledge Bases
        "CREATE TABLE IF NOT EXISTS `ai_knowledge_bases` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL DEFAULT 'Default Knowledge Base',
            `description` TEXT DEFAULT NULL,
            `status` ENUM('active','inactive','processing') DEFAULT 'active',
            `total_chunks` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_bot` (`bot_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 3. AI KB Documents
        "CREATE TABLE IF NOT EXISTS `ai_kb_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kb_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_type` ENUM('pdf','docx','txt','csv') NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` INT DEFAULT 0,
            `chunks_count` INT DEFAULT 0,
            `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending',
            `error_message` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`kb_id`) REFERENCES `ai_knowledge_bases`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_kb` (`kb_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 4. AI KB URLs
        "CREATE TABLE IF NOT EXISTS `ai_kb_urls` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kb_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `url` VARCHAR(2000) NOT NULL,
            `title` VARCHAR(500) DEFAULT NULL,
            `chunks_count` INT DEFAULT 0,
            `status` ENUM('pending','crawling','completed','failed') DEFAULT 'pending',
            `error_message` TEXT DEFAULT NULL,
            `last_crawled_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`kb_id`) REFERENCES `ai_knowledge_bases`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_kb` (`kb_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 5. AI KB Q&A Pairs
        "CREATE TABLE IF NOT EXISTS `ai_kb_qa_pairs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kb_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `question` TEXT NOT NULL,
            `answer` TEXT NOT NULL,
            `category` VARCHAR(100) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`kb_id`) REFERENCES `ai_knowledge_bases`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_kb` (`kb_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 6. AI KB Chunks
        "CREATE TABLE IF NOT EXISTS `ai_kb_chunks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kb_id` INT NOT NULL,
            `source_type` ENUM('document','url','qa','manual') NOT NULL,
            `source_id` INT DEFAULT NULL,
            `content` TEXT NOT NULL,
            `content_hash` VARCHAR(64) DEFAULT NULL,
            `word_count` INT DEFAULT 0,
            `metadata` JSON DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`kb_id`) REFERENCES `ai_knowledge_bases`(`id`) ON DELETE CASCADE,
            INDEX `idx_kb_source` (`kb_id`, `source_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 7. AI Conversations
        "CREATE TABLE IF NOT EXISTS `ai_conversations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `customer_phone` VARCHAR(20) NOT NULL,
            `customer_name` VARCHAR(100) DEFAULT NULL,
            `status` ENUM('active','resolved','handed_over','expired') DEFAULT 'active',
            `messages_count` INT DEFAULT 0,
            `ai_messages_count` INT DEFAULT 0,
            `human_messages_count` INT DEFAULT 0,
            `tokens_used` INT DEFAULT 0,
            `resolved_by` ENUM('ai','human','expired') DEFAULT NULL,
            `handed_over_at` DATETIME DEFAULT NULL,
            `last_message_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_bot_status` (`bot_id`, `status`),
            INDEX `idx_phone` (`customer_phone`),
            UNIQUE KEY `uk_bot_phone` (`bot_id`, `customer_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 8. AI Messages
        "CREATE TABLE IF NOT EXISTS `ai_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `conversation_id` INT NOT NULL,
            `bot_id` INT NOT NULL,
            `direction` ENUM('inbound','outbound') NOT NULL,
            `sender_type` ENUM('customer','ai','human') NOT NULL,
            `content` TEXT NOT NULL,
            `tokens_used` INT DEFAULT 0,
            `confidence_score` DECIMAL(3,2) DEFAULT NULL,
            `ai_model_used` VARCHAR(50) DEFAULT NULL,
            `response_time_ms` INT DEFAULT NULL,
            `metadata` JSON DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            INDEX `idx_conversation` (`conversation_id`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 9. AI Leads
        "CREATE TABLE IF NOT EXISTS `ai_leads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `conversation_id` INT DEFAULT NULL,
            `customer_phone` VARCHAR(20) NOT NULL,
            `customer_name` VARCHAR(100) DEFAULT NULL,
            `customer_email` VARCHAR(150) DEFAULT NULL,
            `customer_company` VARCHAR(150) DEFAULT NULL,
            `requirement` TEXT DEFAULT NULL,
            `tags` VARCHAR(255) DEFAULT 'AI Generated Lead',
            `status` ENUM('new','contacted','qualified','converted','lost') DEFAULT 'new',
            `synced_to_contacts` TINYINT(1) DEFAULT 0,
            `contact_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_bot` (`bot_id`),
            INDEX `idx_phone` (`customer_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 10. AI Handovers
        "CREATE TABLE IF NOT EXISTS `ai_handovers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `conversation_id` INT NOT NULL,
            `customer_phone` VARCHAR(20) NOT NULL,
            `trigger_type` ENUM('keyword','low_confidence','manual','error') NOT NULL,
            `trigger_message` TEXT DEFAULT NULL,
            `status` ENUM('pending','accepted','resolved','expired') DEFAULT 'pending',
            `assigned_to` INT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `resolved_at` DATETIME DEFAULT NULL,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations`(`id`) ON DELETE CASCADE,
            INDEX `idx_bot_status` (`bot_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 11. AI Analytics Daily
        "CREATE TABLE IF NOT EXISTS `ai_analytics_daily` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `date` DATE NOT NULL,
            `total_conversations` INT DEFAULT 0,
            `new_conversations` INT DEFAULT 0,
            `resolved_by_ai` INT DEFAULT 0,
            `transferred_to_human` INT DEFAULT 0,
            `leads_generated` INT DEFAULT 0,
            `total_messages` INT DEFAULT 0,
            `ai_messages` INT DEFAULT 0,
            `total_tokens_used` INT DEFAULT 0,
            `avg_response_time_ms` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`bot_id`) REFERENCES `ai_bots`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `uk_bot_date` (`bot_id`, `date`),
            INDEX `idx_user_date` (`user_id`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 12. AI Credits
        "CREATE TABLE IF NOT EXISTS `ai_credits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `total_tokens` BIGINT DEFAULT 0,
            `used_tokens` BIGINT DEFAULT 0,
            `total_messages` INT DEFAULT 0,
            `used_messages` INT DEFAULT 0,
            `last_reset_at` DATETIME DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `uk_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 13. Add AI columns to plans (ignore errors if already exist)
        "ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `ai_enabled` TINYINT(1) DEFAULT 0 AFTER `priority_support`",
        "ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `ai_bots_limit` INT DEFAULT 0 AFTER `ai_enabled`",
        "ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `ai_messages_limit` INT DEFAULT 0 AFTER `ai_bots_limit`",
        "ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `ai_kb_limit` INT DEFAULT 0 AFTER `ai_messages_limit`",

        // 14. Seed AI settings
        "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`) VALUES
            ('ai_openai_api_key', '', 'ai', 'text'),
            ('ai_openai_enabled', '1', 'ai', 'boolean'),
            ('ai_gemini_api_key', '', 'ai', 'text'),
            ('ai_gemini_enabled', '1', 'ai', 'boolean'),
            ('ai_claude_api_key', '', 'ai', 'text'),
            ('ai_claude_enabled', '0', 'ai', 'boolean'),
            ('ai_custom_enabled', '1', 'ai', 'boolean'),
            ('ai_default_model', 'gpt-4o', 'ai', 'text'),
            ('ai_max_tokens_per_response', '1024', 'ai', 'number'),
            ('ai_max_context_messages', '10', 'ai', 'number'),
            ('ai_rate_limit_default', '100', 'ai', 'number'),
            ('ai_default_system_prompt', 'You are a helpful customer support assistant. Answer questions based on the provided knowledge base. If you cannot find the answer, politely let the customer know and offer to connect them with a human agent.', 'ai', 'textarea')",

        // 15. Update plan limits
        "UPDATE `plans` SET `ai_enabled` = 1, `ai_bots_limit` = 2, `ai_messages_limit` = 5000, `ai_kb_limit` = 2 WHERE `slug` = 'starter'",
        "UPDATE `plans` SET `ai_enabled` = 1, `ai_bots_limit` = 10, `ai_messages_limit` = 50000, `ai_kb_limit` = 10 WHERE `slug` = 'professional'",
        "UPDATE `plans` SET `ai_enabled` = 1, `ai_bots_limit` = 999, `ai_messages_limit` = 999999, `ai_kb_limit` = 999 WHERE `slug` = 'business'",
    ];

    foreach ($statements as $i => $sql) {
        $label = trim(explode("\n", $sql)[0]);
        $label = substr(preg_replace('/\s+/', ' ', $label), 0, 80);
        try {
            $pdo->exec($sql);
            $results[] = ['status' => 'ok', 'label' => $label];
        } catch (PDOException $e) {
            // "Duplicate column" is fine when re-running migration
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
                $results[] = ['status' => 'skip', 'label' => $label, 'msg' => 'Already exists — skipped'];
            } else {
                $results[] = ['status' => 'error', 'label' => $label, 'msg' => $msg];
                $hasError = true;
            }
        }
    }
}

// Check which tables already exist
$existingTables = [];
try {
    $rows = $pdo->query("SHOW TABLES LIKE 'ai_%'")->fetchAll(PDO::FETCH_COLUMN);
    $existingTables = $rows;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Module - Database Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .migration-card { max-width: 800px; margin: 2rem auto; }
        .step-item { padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 0.375rem; font-size: 0.8125rem; font-family: monospace; }
        .step-item.ok { background: #d1fae5; color: #065f46; }
        .step-item.error { background: #fee2e2; color: #991b1b; }
        .step-item.skip { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
<div class="container migration-card">
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="width:52px;height:52px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-robot text-white" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">AI ChatBot Builder — Database Migration</h4>
                    <div class="text-muted" style="font-size:0.875rem;">Creates all required tables for the AI module</div>
                </div>
            </div>

            <?php if (empty($results)): ?>
            <!-- Pre-run status -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <?php if (count($existingTables) > 0): ?>
                    <strong><?= count($existingTables); ?> AI tables already exist:</strong> <?= implode(', ', $existingTables); ?>. Re-running migration will skip existing tables safely.
                <?php else: ?>
                    <strong>No AI tables found.</strong> Click the button below to create all 12 AI database tables.
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold mb-2">This migration will create:</h6>
                <div class="row g-2">
                    <?php foreach (['ai_bots', 'ai_knowledge_bases', 'ai_kb_documents', 'ai_kb_urls', 'ai_kb_qa_pairs', 'ai_kb_chunks', 'ai_conversations', 'ai_messages', 'ai_leads', 'ai_handovers', 'ai_analytics_daily', 'ai_credits'] as $table): ?>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2" style="font-size:0.8125rem;">
                            <i class="bi bi-<?= in_array($table, $existingTables) ? 'check-circle-fill text-success' : 'circle text-muted'; ?>"></i>
                            <code><?= $table; ?></code>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="run_migration" class="btn btn-primary px-4">
                    <i class="bi bi-play-fill me-2"></i>Run Migration Now
                </button>
                <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to AI ChatBot Builder
                </a>
            </form>

            <?php else: ?>
            <!-- Results -->
            <div class="alert alert-<?= $hasError ? 'danger' : 'success'; ?> mb-4">
                <i class="bi bi-<?= $hasError ? 'x-circle' : 'check-circle'; ?>-fill me-2"></i>
                <?php if ($hasError): ?>
                    <strong>Migration completed with errors.</strong> Check the items below.
                <?php else: ?>
                    <strong>Migration completed successfully!</strong> All AI tables are ready.
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <?php foreach ($results as $r): ?>
                <div class="step-item <?= $r['status']; ?>">
                    <i class="bi bi-<?= $r['status'] === 'ok' ? 'check-circle-fill' : ($r['status'] === 'skip' ? 'skip-forward-fill' : 'x-circle-fill'); ?> me-2"></i>
                    <?= htmlspecialchars($r['label']); ?>
                    <?php if (!empty($r['msg'])): ?> — <em><?= htmlspecialchars($r['msg']); ?></em><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$hasError): ?>
            <div class="alert alert-warning">
                <i class="bi bi-shield-exclamation me-2"></i>
                <strong>Security:</strong> Please delete this file from your server after migration:<br>
                <code>admin/run-ai-migration.php</code>
            </div>
            <a href="<?= baseUrl('dashboard/ai-chatbot.php'); ?>" class="btn btn-primary">
                <i class="bi bi-arrow-right me-1"></i>Go to AI ChatBot Builder
            </a>
            <a href="<?= baseUrl('admin/ai-settings.php'); ?>" class="btn btn-outline-primary ms-2">
                <i class="bi bi-gear me-1"></i>Configure AI Settings
            </a>
            <?php else: ?>
            <form method="POST">
                <button type="submit" name="run_migration" class="btn btn-warning">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry Migration
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-center text-muted mt-3" style="font-size:0.75rem;">
        Admin only • WAPI AI Module Migration Tool
    </div>
</div>
</body>
</html>
