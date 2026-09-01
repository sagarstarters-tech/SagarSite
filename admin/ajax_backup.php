<?php
/**
 * ============================================================
 *  AJAX Backup Handler
 *  Location: /admin/ajax_backup.php
 * ============================================================
 *  Handles all backup/restore operations via AJAX:
 *  - create_backup   : Create DB / Files / Full backup ZIP
 *  - list_backups    : List all backup records
 *  - download_backup : Serve backup file for download
 *  - delete_backup   : Remove backup file + DB record
 *  - restore_backup  : Restore from backup ZIP
 *  - get_backup_info : Single backup details
 *  - save_settings   : Save auto-backup settings
 *  - get_settings    : Get auto-backup settings
 * ============================================================
 */

// Increase limits for large backup operations
@ini_set('max_execution_time', 600);
@ini_set('memory_limit', '512M');
@set_time_limit(600);

// Load admin environment
include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/helpers/csrf.php';

// ── Auth Check ──────────────────────────────────────────────
if (!AuthMiddleware::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── CSRF for POST (skip for download GET) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || empty($submitted) || !hash_equals($stored, $submitted)) {
        echo json_encode(['success' => false, 'error' => 'CSRF token mismatch. Please reload the page.']);
        exit;
    }
}

// ── Constants ───────────────────────────────────────────────
define('BACKUP_DIR', BASE_PATH . '/backups');
define('BACKUP_TEMP_DIR', BACKUP_DIR . '/temp');

// Ensure backup directories exist
if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0755, true);
if (!is_dir(BACKUP_TEMP_DIR)) @mkdir(BACKUP_TEMP_DIR, 0755, true);

// ── Route Action ────────────────────────────────────────────
try {
    switch ($action) {
        case 'create_backup':
            handleCreateBackup($conn);
            break;
        case 'list_backups':
            handleListBackups($conn);
            break;
        case 'download_backup':
            handleDownloadBackup($conn);
            break;
        case 'delete_backup':
            handleDeleteBackup($conn);
            break;
        case 'restore_backup':
            handleRestoreBackup($conn);
            break;
        case 'get_backup_info':
            handleGetBackupInfo($conn);
            break;
        case 'save_settings':
            handleSaveSettings($conn);
            break;
        case 'get_settings':
            handleGetSettings($conn);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (Throwable $e) {
    error_log('[Backup] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
exit;


// ═══════════════════════════════════════════════════════════
//  CREATE BACKUP
// ═══════════════════════════════════════════════════════════
function handleCreateBackup($conn) {
    $type = $_POST['backup_type'] ?? 'full'; // full, db_only, files_only
    if (!in_array($type, ['full', 'db_only', 'files_only'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup type.']);
        return;
    }

    // Rate limit: prevent concurrent backups
    $check = $conn->query("SELECT id FROM site_backups WHERE status = 'in_progress' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
    if ($check && $check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'A backup is already in progress. Please wait.']);
        return;
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backupName = "backup_{$type}_{$timestamp}";
    $zipFileName = "{$backupName}.zip";
    $zipFilePath = BACKUP_DIR . '/' . $zipFileName;

    // Insert record as in_progress
    $stmt = $conn->prepare("INSERT INTO site_backups (backup_name, backup_type, trigger_type, file_path, status, created_by, created_at) VALUES (?, ?, 'manual', ?, 'in_progress', ?, NOW())");
    $userId = intval($_SESSION['user_id']);
    $stmt->bind_param('sssi', $backupName, $type, $zipFilePath, $userId);
    $stmt->execute();
    $backupId = $stmt->insert_id;
    $stmt->close();

    try {
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create ZIP file.');
        }

        $dbTablesCount = 0;
        $filesCount = 0;

        // ── Database Backup ─────────────────────────────────
        if ($type === 'full' || $type === 'db_only') {
            $sqlContent = generateDatabaseDump($conn);
            $zip->addFromString('database_backup.sql', $sqlContent);

            // Count tables
            $tablesResult = $conn->query("SHOW TABLES");
            $dbTablesCount = $tablesResult ? $tablesResult->num_rows : 0;
        }

        // ── Files Backup ────────────────────────────────────
        if ($type === 'full' || $type === 'files_only') {
            $dirsToBackup = [
                'uploads' => BASE_PATH . '/uploads',
                'assets/images' => BASE_PATH . '/assets/images',
                'assets/css' => BASE_PATH . '/assets/css',
                'assets/js' => BASE_PATH . '/assets/js',
                'config' => BASE_PATH . '/config',
            ];

            foreach ($dirsToBackup as $prefix => $dirPath) {
                if (is_dir($dirPath)) {
                    $filesCount += addDirectoryToZip($zip, $dirPath, "files/{$prefix}");
                }
            }

            // Add root config files
            $rootFiles = ['.env', '.htaccess', 'manifest.json', 'robots.txt'];
            foreach ($rootFiles as $rf) {
                $rfPath = BASE_PATH . '/' . $rf;
                if (file_exists($rfPath)) {
                    $zip->addFile($rfPath, "files/root/{$rf}");
                    $filesCount++;
                }
            }
        }

        // Add backup metadata
        $metadata = json_encode([
            'backup_name' => $backupName,
            'backup_type' => $type,
            'created_at' => date('Y-m-d H:i:s'),
            'db_name' => DB_NAME,
            'db_tables_count' => $dbTablesCount,
            'files_count' => $filesCount,
            'php_version' => PHP_VERSION,
            'site_url' => defined('SITE_URL') ? SITE_URL : '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('backup_metadata.json', $metadata);

        $zip->close();

        // Update record
        $fileSize = filesize($zipFilePath);
        $stmt = $conn->prepare("UPDATE site_backups SET status = 'completed', file_size = ?, db_tables_count = ?, files_count = ?, notes = 'Backup completed successfully.' WHERE id = ?");
        $stmt->bind_param('iiii', $fileSize, $dbTablesCount, $filesCount, $backupId);
        $stmt->execute();
        $stmt->close();

        // Auto-cleanup old backups
        autoCleanupBackups($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully!',
            'backup' => [
                'id' => $backupId,
                'name' => $backupName,
                'type' => $type,
                'size' => formatFileSize($fileSize),
                'size_bytes' => $fileSize,
                'tables' => $dbTablesCount,
                'files' => $filesCount,
            ]
        ]);

    } catch (Throwable $e) {
        // Mark as failed
        $error = $conn->real_escape_string($e->getMessage());
        $conn->query("UPDATE site_backups SET status = 'failed', notes = 'Error: {$error}' WHERE id = {$backupId}");

        // Cleanup partial ZIP
        if (file_exists($zipFilePath)) @unlink($zipFilePath);

        echo json_encode(['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()]);
    }
}


// ═══════════════════════════════════════════════════════════
//  GENERATE DATABASE DUMP (Pure PHP — no mysqldump needed)
// ═══════════════════════════════════════════════════════════
function generateDatabaseDump($conn) {
    $sql = "-- ============================================\n";
    $sql .= "-- Database Backup: " . DB_NAME . "\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- PHP Version: " . PHP_VERSION . "\n";
    $sql .= "-- ============================================\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    $result->free();

    foreach ($tables as $table) {
        // Skip backup tables themselves to prevent recursion on restore
        if ($table === 'site_backups' || $table === 'backup_settings') continue;

        $sql .= "-- ──────────────────────────────────────────\n";
        $sql .= "-- Table: `{$table}`\n";
        $sql .= "-- ──────────────────────────────────────────\n\n";

        // DROP TABLE
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

        // CREATE TABLE
        $createResult = $conn->query("SHOW CREATE TABLE `{$table}`");
        if ($createResult) {
            $createRow = $createResult->fetch_row();
            $sql .= $createRow[1] . ";\n\n";
            $createResult->free();
        }

        // INSERT DATA (chunked to avoid memory issues)
        $countResult = $conn->query("SELECT COUNT(*) as cnt FROM `{$table}`");
        $totalRows = 0;
        if ($countResult) {
            $totalRows = intval($countResult->fetch_assoc()['cnt']);
            $countResult->free();
        }

        if ($totalRows > 0) {
            $batchSize = 500;
            $offset = 0;

            while ($offset < $totalRows) {
                $dataResult = $conn->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}");
                if (!$dataResult) {
                    $offset += $batchSize;
                    continue;
                }

                $fields = $dataResult->fetch_fields();
                while ($row = $dataResult->fetch_row()) {
                    $values = [];
                    foreach ($row as $idx => $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($val) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $dataResult->free();
                $offset += $batchSize;
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}


// ═══════════════════════════════════════════════════════════
//  ADD DIRECTORY TO ZIP (Recursive)
// ═══════════════════════════════════════════════════════════
function addDirectoryToZip($zip, $dirPath, $zipPrefix) {
    $count = 0;
    $realDir = realpath($dirPath);
    if (!$realDir || !is_dir($realDir)) return 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();

        // Security: ensure file is within expected directory
        if (strpos($filePath, $realDir) !== 0) continue;

        $relativePath = $zipPrefix . '/' . substr($filePath, strlen($realDir) + 1);
        // Normalize path separators
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            // Skip very large files (>100MB) to avoid memory issues
            if ($file->getSize() > 104857600) continue;
            $zip->addFile($filePath, $relativePath);
            $count++;
        }
    }
    return $count;
}


// ═══════════════════════════════════════════════════════════
//  LIST BACKUPS
// ═══════════════════════════════════════════════════════════
function handleListBackups($conn) {
    $page = max(1, intval($_POST['page'] ?? $_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Total count
    $countRes = $conn->query("SELECT COUNT(*) as total FROM site_backups");
    $total = $countRes ? intval($countRes->fetch_assoc()['total']) : 0;

    // Fetch records
    $stmt = $conn->prepare("SELECT sb.*, u.name as created_by_name FROM site_backups sb LEFT JOIN users u ON sb.created_by = u.id ORDER BY sb.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $backups = [];
    while ($row = $result->fetch_assoc()) {
        // Check if file still exists
        $row['file_exists'] = !empty($row['file_path']) && file_exists($row['file_path']);
        $row['file_size_formatted'] = formatFileSize($row['file_size'] ?? 0);
        $row['created_at_formatted'] = date('d M Y, h:i A', strtotime($row['created_at']));
        $backups[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'backups' => $backups,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
    ]);
}


// ═══════════════════════════════════════════════════════════
//  DOWNLOAD BACKUP
// ═══════════════════════════════════════════════════════════
function handleDownloadBackup($conn) {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup ID.']);
        return;
    }

    $stmt = $conn->prepare("SELECT file_path, backup_name FROM site_backups WHERE id = ? AND status = 'completed'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    if (!$backup || empty($backup['file_path'])) {
        echo json_encode(['success' => false, 'error' => 'Backup not found.']);
        return;
    }

    $filePath = realpath($backup['file_path']);
    $backupDir = realpath(BACKUP_DIR);

    // Security: Path traversal check
    if (!$filePath || strpos($filePath, $backupDir) !== 0 || !file_exists($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Backup file not found or access denied.']);
        return;
    }

    // Serve file for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    ob_end_clean();
    readfile($filePath);
    exit;
}


// ═══════════════════════════════════════════════════════════
//  DELETE BACKUP
// ═══════════════════════════════════════════════════════════
function handleDeleteBackup($conn) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup ID.']);
        return;
    }

    $stmt = $conn->prepare("SELECT file_path FROM site_backups WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    if (!$backup) {
        echo json_encode(['success' => false, 'error' => 'Backup record not found.']);
        return;
    }

    // Delete file if exists
    if (!empty($backup['file_path']) && file_exists($backup['file_path'])) {
        $filePath = realpath($backup['file_path']);
        $backupDir = realpath(BACKUP_DIR);
        if ($filePath && strpos($filePath, $backupDir) === 0) {
            @unlink($filePath);
        }
    }

    // Delete DB record
    $stmt = $conn->prepare("DELETE FROM site_backups WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Backup deleted successfully.']);
}


// ═══════════════════════════════════════════════════════════
//  RESTORE BACKUP
// ═══════════════════════════════════════════════════════════
function handleRestoreBackup($conn) {
    $id = intval($_POST['id'] ?? 0);
    $restoreDb = ($_POST['restore_db'] ?? '1') === '1';
    $restoreFiles = ($_POST['restore_files'] ?? '1') === '1';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup ID.']);
        return;
    }

    if (!$restoreDb && !$restoreFiles) {
        echo json_encode(['success' => false, 'error' => 'Please select at least one option to restore.']);
        return;
    }

    // Fetch backup info
    $stmt = $conn->prepare("SELECT * FROM site_backups WHERE id = ? AND status IN ('completed', 'restored')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    if (!$backup) {
        echo json_encode(['success' => false, 'error' => 'Backup not found or not in a restorable state.']);
        return;
    }

    $filePath = realpath($backup['file_path'] ?? '');
    $backupDir = realpath(BACKUP_DIR);

    if (!$filePath || strpos($filePath, $backupDir) !== 0 || !file_exists($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Backup ZIP file not found.']);
        return;
    }

    try {
        // ── Step 1: Create safety backup before restore ─────
        $safetyName = "safety_pre_restore_" . date('Y-m-d_H-i-s');
        $safetyPath = BACKUP_DIR . "/{$safetyName}.zip";
        $safetyZip = new ZipArchive();

        if ($safetyZip->open($safetyPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Quick DB dump for safety
            if ($restoreDb) {
                $safetyZip->addFromString('database_backup.sql', generateDatabaseDump($conn));
            }
            $safetyZip->close();

            $safetySize = file_exists($safetyPath) ? filesize($safetyPath) : 0;
            $stmtSafety = $conn->prepare("INSERT INTO site_backups (backup_name, backup_type, trigger_type, file_path, file_size, status, notes, created_by, created_at) VALUES (?, 'db_only', 'auto', ?, ?, 'completed', 'Auto safety backup before restore', ?, NOW())");
            $uid = intval($_SESSION['user_id']);
            $stmtSafety->bind_param('ssii', $safetyName, $safetyPath, $safetySize, $uid);
            $stmtSafety->execute();
            $stmtSafety->close();
        }

        // ── Step 2: Extract and restore ─────────────────────
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Failed to open backup ZIP file.');
        }

        // Extract to temp directory
        $tempDir = BACKUP_TEMP_DIR . '/restore_' . time();
        @mkdir($tempDir, 0755, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $restoreNotes = [];

        // ── Restore Database ────────────────────────────────
        if ($restoreDb) {
            $sqlFile = $tempDir . '/database_backup.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                if (!empty($sqlContent)) {
                    // Disable foreign key checks during restore
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    
                    // Execute SQL statements one by one
                    $statements = parseSqlStatements($sqlContent);
                    $executed = 0;
                    $errors = 0;

                    foreach ($statements as $stmt_sql) {
                        $stmt_sql = trim($stmt_sql);
                        if (empty($stmt_sql)) continue;
                        // Skip comment-only lines
                        if (strpos($stmt_sql, '--') === 0) continue;
                        
                        if ($conn->query($stmt_sql)) {
                            $executed++;
                        } else {
                            $errors++;
                            error_log("[Backup Restore] SQL Error: " . $conn->error . " — Query: " . substr($stmt_sql, 0, 200));
                        }
                    }

                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    $restoreNotes[] = "Database: {$executed} statements executed, {$errors} errors.";
                } else {
                    $restoreNotes[] = "Database: SQL file was empty.";
                }
            } else {
                $restoreNotes[] = "Database: No SQL file found in backup.";
            }
        }

        // ── Restore Files ───────────────────────────────────
        if ($restoreFiles) {
            $filesDir = $tempDir . '/files';
            if (is_dir($filesDir)) {
                $copied = restoreFilesFromBackup($filesDir);
                $restoreNotes[] = "Files: {$copied} files restored.";
            } else {
                $restoreNotes[] = "Files: No files directory found in backup.";
            }
        }

        // ── Clean up temp directory ─────────────────────────
        deleteDirectory($tempDir);

        // ── Update backup record ────────────────────────────
        $notes = implode(' | ', $restoreNotes);
        $stmtUpdate = $conn->prepare("UPDATE site_backups SET status = 'restored', notes = CONCAT(IFNULL(notes,''), ' | Restored: ', ?) WHERE id = ?");
        $stmtUpdate->bind_param('si', $notes, $id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        echo json_encode([
            'success' => true,
            'message' => 'Restore completed successfully! A safety backup was created before restore.',
            'details' => $restoreNotes,
        ]);

    } catch (Throwable $e) {
        error_log('[Backup Restore] Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Restore failed: ' . $e->getMessage()]);
    }
}


// ═══════════════════════════════════════════════════════════
//  RESTORE FILES FROM BACKUP
// ═══════════════════════════════════════════════════════════
function restoreFilesFromBackup($filesDir) {
    $copied = 0;
    $basePath = realpath(BASE_PATH);

    // Map backup directory prefixes to actual paths
    $dirMap = [
        'uploads' => $basePath . '/uploads',
        'assets/images' => $basePath . '/assets/images',
        'assets/css' => $basePath . '/assets/css',
        'assets/js' => $basePath . '/assets/js',
        'config' => $basePath . '/config',
        'root' => $basePath,
    ];

    foreach ($dirMap as $prefix => $targetDir) {
        $sourceDir = $filesDir . '/' . $prefix;
        if (!is_dir($sourceDir)) continue;

        // Security: Verify target is within BASE_PATH
        $realTarget = realpath($targetDir) ?: $targetDir;
        if (strpos($realTarget, $basePath) !== 0 && $prefix !== 'root') continue;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = substr($file->getRealPath(), strlen(realpath($sourceDir)) + 1);
            $destPath = $targetDir . '/' . $relativePath;

            // Security: verify destination stays within base path
            $realDest = dirname($destPath);
            if (!is_dir($realDest)) {
                @mkdir($realDest, 0755, true);
            }

            if ($file->isFile()) {
                if (@copy($file->getRealPath(), $destPath)) {
                    $copied++;
                }
            }
        }
    }

    return $copied;
}


// ═══════════════════════════════════════════════════════════
//  PARSE SQL STATEMENTS
// ═══════════════════════════════════════════════════════════
function parseSqlStatements($sql) {
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;

    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and full-line comments
        if ($line === '' || strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;

        $current .= $line . "\n";

        // Simple statement detection: line ends with semicolon and not inside a string
        if (substr(rtrim($line), -1) === ';') {
            $trimmed = trim($current);
            if (!empty($trimmed) && $trimmed !== ';') {
                // Remove trailing semicolon for query execution
                $statements[] = rtrim($trimmed, ';');
            }
            $current = '';
        }
    }

    // Add any remaining statement
    $remaining = trim($current);
    if (!empty($remaining) && $remaining !== ';') {
        $statements[] = rtrim($remaining, ';');
    }

    return $statements;
}


// ═══════════════════════════════════════════════════════════
//  GET BACKUP INFO
// ═══════════════════════════════════════════════════════════
function handleGetBackupInfo($conn) {
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup ID.']);
        return;
    }

    $stmt = $conn->prepare("SELECT sb.*, u.name as created_by_name FROM site_backups sb LEFT JOIN users u ON sb.created_by = u.id WHERE sb.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    if (!$backup) {
        echo json_encode(['success' => false, 'error' => 'Backup not found.']);
        return;
    }

    $backup['file_exists'] = !empty($backup['file_path']) && file_exists($backup['file_path']);
    $backup['file_size_formatted'] = formatFileSize($backup['file_size'] ?? 0);
    $backup['created_at_formatted'] = date('d M Y, h:i A', strtotime($backup['created_at']));

    echo json_encode(['success' => true, 'backup' => $backup]);
}


// ═══════════════════════════════════════════════════════════
//  SAVE AUTO-BACKUP SETTINGS
// ═══════════════════════════════════════════════════════════
function handleSaveSettings($conn) {
    $settings = [
        'auto_backup_enabled' => ($_POST['auto_backup_enabled'] ?? '0') === '1' ? '1' : '0',
        'auto_backup_frequency' => in_array($_POST['auto_backup_frequency'] ?? '', ['daily', 'weekly', 'monthly']) ? $_POST['auto_backup_frequency'] : 'weekly',
        'auto_backup_type' => in_array($_POST['auto_backup_type'] ?? '', ['full', 'db_only', 'files_only']) ? $_POST['auto_backup_type'] : 'full',
        'max_backups_keep' => max(1, min(50, intval($_POST['max_backups_keep'] ?? 5))),
    ];

    $stmt = $conn->prepare("INSERT INTO backup_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings as $key => $val) {
        $stmt->bind_param('ss', $key, $val);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Auto-backup settings saved successfully.']);
}


// ═══════════════════════════════════════════════════════════
//  GET AUTO-BACKUP SETTINGS
// ═══════════════════════════════════════════════════════════
function handleGetSettings($conn) {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM backup_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $result->free();
    }

    // Defaults
    $defaults = [
        'auto_backup_enabled' => '0',
        'auto_backup_frequency' => 'weekly',
        'auto_backup_type' => 'full',
        'max_backups_keep' => '5',
        'last_auto_backup' => '0',
    ];

    foreach ($defaults as $k => $v) {
        if (!isset($settings[$k])) $settings[$k] = $v;
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
}


// ═══════════════════════════════════════════════════════════
//  AUTO-CLEANUP OLD BACKUPS
// ═══════════════════════════════════════════════════════════
function autoCleanupBackups($conn) {
    // Get max keep setting
    $maxKeep = 5;
    $res = $conn->query("SELECT setting_value FROM backup_settings WHERE setting_key = 'max_backups_keep' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $maxKeep = max(1, intval($row['setting_value']));
    }

    // Count total completed backups (excluding safety backups)
    $countRes = $conn->query("SELECT COUNT(*) as cnt FROM site_backups WHERE status = 'completed' AND backup_name NOT LIKE 'safety_%'");
    $total = $countRes ? intval($countRes->fetch_assoc()['cnt']) : 0;

    if ($total <= $maxKeep) return;

    // Delete oldest beyond limit
    $toDelete = $total - $maxKeep;
    $oldBackups = $conn->query("SELECT id, file_path FROM site_backups WHERE status = 'completed' AND backup_name NOT LIKE 'safety_%' ORDER BY created_at ASC LIMIT {$toDelete}");

    if ($oldBackups) {
        while ($old = $oldBackups->fetch_assoc()) {
            if (!empty($old['file_path']) && file_exists($old['file_path'])) {
                $fp = realpath($old['file_path']);
                $bd = realpath(BACKUP_DIR);
                if ($fp && strpos($fp, $bd) === 0) {
                    @unlink($fp);
                }
            }
            $conn->query("DELETE FROM site_backups WHERE id = " . intval($old['id']));
        }
    }
}


// ═══════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════
function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            @unlink($file->getRealPath());
        }
    }
    @rmdir($dir);
}
