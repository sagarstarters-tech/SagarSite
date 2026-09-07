<?php
/**
 * CLI Tool & Git Hook Handler: Version Bumper
 * Usage:
 *   php bin/bump_version.php          # Auto check & bump if update detected
 *   php bin/bump_version.php --check  # Check current status
 *   php bin/bump_version.php --patch  # Force bump patch release (+0.0.1)
 *   php bin/bump_version.php --minor  # Force bump minor release (+0.1.0)
 *   php bin/bump_version.php --major  # Force bump major release (+1.0.0)
 *   php bin/bump_version.php --silent # Silent mode for hooks
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/classes/VersionManager.php';

$silent = in_array('--silent', $argv ?? []);
$isCheck = in_array('--check', $argv ?? []);
$isPatch = in_array('--patch', $argv ?? []);
$isMinor = in_array('--minor', $argv ?? []);
$isMajor = in_array('--major', $argv ?? []);

// Optional DB connection if available
$conn = null;
if (file_exists(BASE_PATH . '/includes/db_connect.php')) {
    try {
        require_once BASE_PATH . '/includes/db_connect.php';
    } catch (\Throwable $e) {
        // DB not reachable in CLI; VersionManager can still update version.json
    }
}

if ($isCheck) {
    $meta = VersionManager::getVersionMetadata($conn);
    echo "===========================================\n";
    echo " Website Version Status\n";
    echo "===========================================\n";
    echo " Current Version: " . $meta['version'] . "\n";
    echo " Auto-Increment:  " . ($meta['auto_enabled'] ? 'ACTIVE (ON)' : 'DISABLED (OFF)') . "\n";
    echo " Commit SHA:      " . $meta['commit'] . " (" . $meta['short_hash'] . ")\n";
    echo " Last Bump At:    " . ($meta['last_bump_at'] ?: 'N/A') . "\n";
    echo " Next Patch:      " . $meta['next_patch'] . "\n";
    echo " Next Minor:      " . $meta['next_minor'] . "\n";
    echo " Next Major:      " . $meta['next_major'] . "\n";
    echo "===========================================\n";
    exit(0);
}

if ($isPatch || $isMinor || $isMajor) {
    $type = $isMajor ? 'major' : ($isMinor ? 'minor' : 'patch');
    $res = VersionManager::bumpVersionManually($conn, $type);
    if (!$silent) {
        echo "Successfully bumped {$type} version: {$res['old_version']} -> {$res['new_version']}\n";
    }
    exit(0);
}

$isSyncPackage = in_array('--sync-package', $argv ?? []);

if ($isSyncPackage) {
    $res = VersionManager::syncExportPackage($silent);
    if (!$silent) {
        if ($res['success']) {
            echo "[SUCCESS] Export package successfully updated! (Version: {$res['version']}, Files: {$res['files_count']}, Size: {$res['size_mb']} MB)\n";
        } else {
            echo "[ERROR] Failed to update export package: " . ($res['error'] ?? 'Unknown error') . "\n";
        }
    }
    exit(0);
}

// Default: Auto Check & Bump
$res = VersionManager::autoCheckAndBump($conn);
if (!$silent) {
    if (!empty($res['bumped'])) {
        echo "Update detected! Version auto-bumped: {$res['old_version']} -> {$res['new_version']} (Commit: {$res['commit']})\n";
    } else {
        echo "Version check complete: " . ($res['reason'] ?? 'up_to_date') . " (Version: " . ($res['version'] ?? VersionManager::getCurrentVersion($conn)) . ")\n";
    }
}

