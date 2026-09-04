<?php
/**
 * Migration: Create site_backups table
 * Location: /admin/migrations/backup_migration.php
 * 
 * Run once to create the backup tracking table.
 */

include_once __DIR__ . '/../admin_header.php';

// Only allow admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied.');
}

$queries = [];
$results = [];

// ── 1. Create site_backups table ────────────────────────────
$queries[] = "CREATE TABLE IF NOT EXISTS `site_backups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `backup_name` VARCHAR(255) NOT NULL,
    `backup_type` ENUM('full','db_only','files_only') NOT NULL DEFAULT 'full',
    `trigger_type` ENUM('manual','auto') NOT NULL DEFAULT 'manual',
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT UNSIGNED DEFAULT 0,
    `db_tables_count` INT UNSIGNED DEFAULT 0,
    `files_count` INT UNSIGNED DEFAULT 0,
    `status` ENUM('in_progress','completed','failed','restored') NOT NULL DEFAULT 'in_progress',
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_trigger_type` (`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// ── 2. Create backup_settings table ─────────────────────────
$queries[] = "CREATE TABLE IF NOT EXISTS `backup_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// ── 3. Insert default settings ──────────────────────────────
$queries[] = "INSERT IGNORE INTO `backup_settings` (`setting_key`, `setting_value`) VALUES
    ('auto_backup_enabled', '0'),
    ('auto_backup_frequency', 'weekly'),
    ('auto_backup_type', 'full'),
    ('max_backups_keep', '5'),
    ('last_auto_backup', '0');";

// Execute all queries
foreach ($queries as $i => $sql) {
    if ($conn->query($sql)) {
        $results[] = ['success' => true, 'query' => $i + 1, 'msg' => 'OK'];
    } else {
        $results[] = ['success' => false, 'query' => $i + 1, 'msg' => $conn->error];
    }
}
?>

<div class="container-fluid">
    <div class="row justify-content-center mt-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold mb-0 text-primary">
                        <i class="fas fa-database me-2"></i>Backup System Migration
                    </h4>
                    <p class="text-muted small mt-1">Creating backup tracking tables...</p>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($results as $r): ?>
                        <div class="alert alert-<?php echo $r['success'] ? 'success' : 'danger'; ?> py-2 rounded-3">
                            <i class="fas fa-<?php echo $r['success'] ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                            Query #<?php echo $r['query']; ?>: <?php echo htmlspecialchars($r['msg']); ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="../manage_backups.php" class="btn btn-primary btn-custom rounded-pill px-4">
                            <i class="fas fa-shield-alt me-2"></i>Go to Backup Manager
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>
