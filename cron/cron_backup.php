<?php
/**
 * ============================================================
 *  CRON: Automated Website Backup
 *  Location: /cron/cron_backup.php
 * ============================================================
 *  Run via cron job or task scheduler:
 *    php /path/to/cron_backup.php [--force]
 *
 *  Or via browser / cURL (admin session or valid cron key):
 *    https://yoursite.com/cron/cron_backup.php?key=YOUR_CRON_KEY[&force=1]
 *
 *  Checks backup_settings for auto_backup_enabled, frequency,
 *  and creates backups accordingly. Also handles auto-cleanup.
 * ============================================================
 */

// Increase limits
@ini_set('max_execution_time', 600);
@ini_set('memory_limit', '512M');
@set_time_limit(600);

// ── Load environment ────────────────────────────────────────
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config/');
}
if (!defined('DB_HOST')) {
    require CONFIG_PATH . 'config.php';
}

$isCli = (php_sapi_name() === 'cli');

// ── Database connection ─────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    logCron('FATAL: DB connection failed: ' . $conn->connect_error);
    if ($isCli) {
        echo 'FATAL: DB connection failed: ' . $conn->connect_error . "\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $conn->connect_error]);
    }
    exit(1);
}
$conn->set_charset('utf8mb4');

// Ensure tables exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `site_backups` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $conn->query("CREATE TABLE IF NOT EXISTS `backup_settings` (
        `setting_key` VARCHAR(100) PRIMARY KEY,
        `setting_value` TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $conn->query("INSERT IGNORE INTO `backup_settings` (`setting_key`, `setting_value`) VALUES
        ('auto_backup_enabled', '0'),
        ('auto_backup_frequency', 'weekly'),
        ('auto_backup_type', 'full'),
        ('max_backups_keep', '5'),
        ('last_auto_backup', '0'),
        ('cron_secret_key', 'auto_backup_secure_key_2024');");
} catch (\Throwable $e) {}

// ── Load settings ───────────────────────────────────────────
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM backup_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $res->free();
}

// ── Authentication Check ────────────────────────────────────
$validKey = $settings['cron_secret_key'] ?? '';
if (empty($validKey)) {
    $validKey = function_exists('_env') ? _env('CRON_SECRET_KEY', 'auto_backup_secure_key_2024') : 'auto_backup_secure_key_2024';
}

$cliArgs = $argv ?? [];
$force = in_array('--force', $cliArgs, true);

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $cronKey = $_GET['key'] ?? $_GET['secret'] ?? '';
    $isAdminSession = false;

    if (file_exists(BASE_PATH . '/includes/session_setup.php')) {
        include_once BASE_PATH . '/includes/session_setup.php';
        if (isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin' || !empty($_SESSION['admin_logged_in']))) {
            $isAdminSession = true;
        }
    }

    if (!$isAdminSession && (empty($cronKey) || !hash_equals((string)$validKey, (string)$cronKey))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status' => 'access_denied',
            'error' => 'Access denied: invalid or missing cron secret key.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (isset($_GET['force']) && in_array(strtolower((string)$_GET['force']), ['1', 'true', 'yes'], true)) {
        $force = true;
    }
}

// ── Check if auto backup is enabled ─────────────────────────
$enabled = ($settings['auto_backup_enabled'] ?? '0') === '1';
if (!$enabled && !$force) {
    logCron('Auto backup is disabled. Exiting.');
    sendOutput(false, 'Auto backup is disabled in settings.', ['status' => 'disabled'], $isCli);
    exit(0);
}

$frequency = $settings['auto_backup_frequency'] ?? 'weekly';
$backupType = $settings['auto_backup_type'] ?? 'full';
$maxKeep = max(1, intval($settings['max_backups_keep'] ?? 5));
$lastRun = intval($settings['last_auto_backup'] ?? 0);

// ── Check if it's time to run ───────────────────────────────
$now = time();
$intervalSeconds = [
    'daily'   => 86400,    // 24 hours
    'weekly'  => 604800,   // 7 days
    'monthly' => 2592000,  // 30 days
];

$interval = $intervalSeconds[$frequency] ?? 86400;

if (!$force && ($now - $lastRun) < $interval) {
    $nextRun = date('Y-m-d H:i:s', $lastRun + $interval);
    logCron("Not yet time for backup. Next run: {$nextRun}");
    sendOutput(true, "Next auto backup scheduled for: {$nextRun}", [
        'status' => 'skipped',
        'reason' => 'not_due',
        'last_run' => $lastRun > 0 ? date('Y-m-d H:i:s', $lastRun) : 'never',
        'next_run' => $nextRun,
        'remaining_seconds' => ($lastRun + $interval) - $now
    ], $isCli);
    exit(0);
}

// Rate limit: prevent concurrent backups within 10 minutes
$check = $conn->query("SELECT id FROM site_backups WHERE status = 'in_progress' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
if ($check && $check->num_rows > 0) {
    logCron('A backup is already in progress. Exiting.');
    sendOutput(false, 'A backup is already in progress. Please wait.', ['status' => 'in_progress'], $isCli);
    exit(0);
}

// ── Include backup functions ────────────────────────────────
define('BACKUP_DIR', BASE_PATH . '/backups');
define('BACKUP_TEMP_DIR', BACKUP_DIR . '/temp');
if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0755, true);
if (!is_dir(BACKUP_TEMP_DIR)) @mkdir(BACKUP_TEMP_DIR, 0755, true);

logCron("Starting auto backup: type={$backupType}" . ($force ? " (forced)" : ""));

try {
    $timestamp = date('Y-m-d_H-i-s');
    $backupName = "auto_{$backupType}_{$timestamp}";
    $zipFileName = "{$backupName}.zip";
    $zipFilePath = BACKUP_DIR . '/' . $zipFileName;

    // Insert record
    $stmt = $conn->prepare("INSERT INTO site_backups (backup_name, backup_type, trigger_type, file_path, status, created_by, created_at) VALUES (?, ?, 'auto', ?, 'in_progress', NULL, NOW())");
    $stmt->bind_param('sss', $backupName, $backupType, $zipFilePath);
    $stmt->execute();
    $backupId = $stmt->insert_id;
    $stmt->close();

    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Failed to create ZIP file.');
    }

    $dbTablesCount = 0;
    $filesCount = 0;

    // Database backup
    if ($backupType === 'full' || $backupType === 'db_only') {
        $sqlContent = generateCronDatabaseDump($conn);
        $zip->addFromString('database_backup.sql', $sqlContent);
        $tablesResult = $conn->query("SHOW TABLES");
        $dbTablesCount = $tablesResult ? $tablesResult->num_rows : 0;
    }

    // Files backup
    if ($backupType === 'full' || $backupType === 'files_only') {
        $dirsToBackup = [
            'uploads' => BASE_PATH . '/uploads',
            'assets/images' => BASE_PATH . '/assets/images',
            'assets/css' => BASE_PATH . '/assets/css',
            'assets/js' => BASE_PATH . '/assets/js',
            'config' => BASE_PATH . '/config',
        ];
        foreach ($dirsToBackup as $prefix => $dirPath) {
            if (is_dir($dirPath)) {
                $filesCount += addCronDirectoryToZip($zip, $dirPath, "files/{$prefix}");
            }
        }
        $rootFiles = ['.env', '.htaccess', 'manifest.json', 'robots.txt'];
        foreach ($rootFiles as $rf) {
            $rfPath = BASE_PATH . '/' . $rf;
            if (file_exists($rfPath)) {
                $zip->addFile($rfPath, "files/root/{$rf}");
                $filesCount++;
            }
        }
    }

    // Metadata
    $metadata = json_encode([
        'backup_name' => $backupName,
        'backup_type' => $backupType,
        'trigger' => 'auto_cron',
        'created_at' => date('Y-m-d H:i:s'),
        'db_name' => DB_NAME,
        'db_tables_count' => $dbTablesCount,
        'files_count' => $filesCount,
        'php_version' => PHP_VERSION,
        'site_url' => defined('SITE_URL') ? SITE_URL : '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $zip->addFromString('backup_metadata.json', $metadata);
    $zip->close();

    $fileSize = file_exists($zipFilePath) ? filesize($zipFilePath) : 0;

    // Update record
    $stmt = $conn->prepare("UPDATE site_backups SET status = 'completed', file_size = ?, db_tables_count = ?, files_count = ?, notes = 'Auto backup completed successfully.' WHERE id = ?");
    $stmt->bind_param('iiii', $fileSize, $dbTablesCount, $filesCount, $backupId);
    $stmt->execute();
    $stmt->close();

    // Update last run timestamp
    $conn->query("INSERT INTO backup_settings (setting_key, setting_value) VALUES ('last_auto_backup', '{$now}') ON DUPLICATE KEY UPDATE setting_value = '{$now}'");

    // Auto-cleanup old backups
    cronAutoCleanup($conn, $maxKeep);

    $formattedSize = round($fileSize / (1024 * 1024), 2) . ' MB';
    logCron("Auto backup completed: {$backupName} ({$fileSize} bytes, {$dbTablesCount} tables, {$filesCount} files)");

    sendOutput(true, "Auto backup completed successfully: {$backupName}", [
        'status' => 'completed',
        'backup_id' => $backupId,
        'backup_name' => $backupName,
        'file_size' => $fileSize,
        'file_size_formatted' => $formattedSize,
        'db_tables_count' => $dbTablesCount,
        'files_count' => $filesCount,
        'created_at' => date('Y-m-d H:i:s', $now),
        'next_run' => date('Y-m-d H:i:s', $now + $interval)
    ], $isCli);

} catch (Throwable $e) {
    if (isset($backupId) && $backupId > 0) {
        $error = $conn->real_escape_string($e->getMessage());
        $conn->query("UPDATE site_backups SET status = 'failed', notes = 'Auto backup error: {$error}' WHERE id = {$backupId}");
    }
    if (isset($zipFilePath) && file_exists($zipFilePath)) @unlink($zipFilePath);

    logCron('Auto backup FAILED: ' . $e->getMessage());
    if ($isCli) {
        echo "Auto backup failed: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit(1);
}

$conn->close();
exit(0);


// ═══════════════════════════════════════════════════════════
//  HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════

function generateCronDatabaseDump($conn) {
    $sql = "-- Auto Backup: " . DB_NAME . "\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) $tables[] = $row[0];
    $result->free();

    foreach ($tables as $table) {
        if ($table === 'site_backups' || $table === 'backup_settings') continue;
        
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $cr = $conn->query("SHOW CREATE TABLE `{$table}`");
        if ($cr) { $sql .= $cr->fetch_row()[1] . ";\n\n"; $cr->free(); }

        $cnt = $conn->query("SELECT COUNT(*) as c FROM `{$table}`");
        $total = $cnt ? intval($cnt->fetch_assoc()['c']) : 0;
        if ($cnt) $cnt->free();

        if ($total > 0) {
            $offset = 0;
            while ($offset < $total) {
                $dr = $conn->query("SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}");
                if ($dr) {
                    while ($row = $dr->fetch_row()) {
                        $vals = [];
                        foreach ($row as $v) $vals[] = ($v === null) ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                        $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    $dr->free();
                }
                $offset += 500;
            }
            $sql .= "\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

function addCronDirectoryToZip($zip, $dirPath, $zipPrefix) {
    $count = 0;
    $realDir = realpath($dirPath);
    if (!$realDir || !is_dir($realDir)) return 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $file) {
        $fp = $file->getRealPath();
        if (strpos($fp, $realDir) !== 0) continue;
        $rp = str_replace('\\', '/', $zipPrefix . '/' . substr($fp, strlen($realDir) + 1));
        if ($file->isDir()) {
            $zip->addEmptyDir($rp);
        } else {
            if ($file->getSize() > 104857600) continue; // Skip >100MB
            $zip->addFile($fp, $rp);
            $count++;
        }
    }
    return $count;
}

function cronAutoCleanup($conn, $maxKeep) {
    $cnt = $conn->query("SELECT COUNT(*) as c FROM site_backups WHERE status = 'completed' AND backup_name NOT LIKE 'safety_%'");
    $total = $cnt ? intval($cnt->fetch_assoc()['c']) : 0;
    if ($total <= $maxKeep) return;

    $toDelete = $total - $maxKeep;
    $old = $conn->query("SELECT id, file_path FROM site_backups WHERE status = 'completed' AND backup_name NOT LIKE 'safety_%' ORDER BY created_at ASC LIMIT {$toDelete}");
    if ($old) {
        $backupDir = realpath(BACKUP_DIR);
        while ($row = $old->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                $fp = realpath($row['file_path']);
                if ($fp && strpos($fp, $backupDir) === 0) @unlink($fp);
            }
            $conn->query("DELETE FROM site_backups WHERE id = " . intval($row['id']));
        }
        logCron("Auto-cleanup: removed {$toDelete} old backups.");
    }
}

function logCron($msg) {
    $logFile = BASE_PATH . '/logs/cron_backup.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function sendOutput($success, $message, $data = [], $isCli = false) {
    if ($isCli) {
        echo ($success ? '[SUCCESS] ' : '[NOTICE] ') . $message . "\n";
    } else {
        $response = array_merge(['success' => $success, 'message' => $message], $data);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
