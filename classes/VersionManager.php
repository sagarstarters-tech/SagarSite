<?php
/**
 * ============================================================
 *  VERSION MANAGER — Automated & Safe Release Versioning System
 * ============================================================
 *  Location: /classes/VersionManager.php
 * ============================================================
 *  Provides automated version tracking and increment logic (Auto Change Version).
 *  - Directly inspects Git HEAD without relying on shell commands
 *  - Works on local XAMPP and live hosting (Hostinger cPanel)
 *  - Automatically bumps patch version (v2.5.0 -> v2.5.1) on code updates
 *  - Supports manual override & toggle for auto-versioning
 * ============================================================
 */

class VersionManager
{
    private static $basePath = null;

    /**
     * Get the project base directory path
     */
    public static function getBasePath()
    {
        if (self::$basePath === null) {
            if (defined('BASE_PATH')) {
                self::$basePath = rtrim(BASE_PATH, '/\\');
            } else {
                self::$basePath = rtrim(dirname(__DIR__), '/\\');
            }
        }
        return self::$basePath;
    }

    /**
     * Parse a version string (e.g. "v2.5.0", "2.5.0", "v2.5") into structured components
     */
    public static function parseVersion($versionString)
    {
        $str = trim((string)$versionString);
        if (empty($str)) {
            $str = 'v2.5.0';
        }

        $hasV = (stripos($str, 'v') === 0);
        $clean = ltrim($str, 'vV');

        // Separate numeric segments from any metadata suffix (e.g. -beta, +build1)
        $suffix = '';
        if (preg_match('/^([0-9\.]+)(.*)$/', $clean, $matches)) {
            $clean = $matches[1];
            $suffix = $matches[2] ?? '';
        }

        $parts = explode('.', $clean);
        $major = (isset($parts[0]) && is_numeric($parts[0])) ? (int)$parts[0] : 2;
        $minor = (isset($parts[1]) && is_numeric($parts[1])) ? (int)$parts[1] : 5;
        $patch = (isset($parts[2]) && is_numeric($parts[2])) ? (int)$parts[2] : 0;

        return [
            'has_v'  => $hasV,
            'major'  => $major,
            'minor'  => $minor,
            'patch'  => $patch,
            'suffix' => $suffix
        ];
    }

    /**
     * Format parsed components back into a version string
     */
    public static function formatVersion(array $p)
    {
        $prefix = $p['has_v'] ? 'v' : '';
        return "{$prefix}{$p['major']}.{$p['minor']}.{$p['patch']}";
    }

    /**
     * Increment Patch release (e.g. v2.5.0 -> v2.5.1)
     */
    public static function bumpPatch($versionString)
    {
        $p = self::parseVersion($versionString);
        $p['patch']++;
        return self::formatVersion($p);
    }

    /**
     * Increment Minor release (e.g. v2.5.0 -> v2.6.0)
     */
    public static function bumpMinor($versionString)
    {
        $p = self::parseVersion($versionString);
        $p['minor']++;
        $p['patch'] = 0;
        return self::formatVersion($p);
    }

    /**
     * Increment Major release (e.g. v2.5.0 -> v3.0.0)
     */
    public static function bumpMajor($versionString)
    {
        $p = self::parseVersion($versionString);
        $p['major']++;
        $p['minor'] = 0;
        $p['patch'] = 0;
        return self::formatVersion($p);
    }

    /**
     * Safely resolve the current system commit hash or build signature.
     * Works on shared hosting without requiring exec() or external commands.
     */
    public static function getSystemCommitHash()
    {
        $base = self::getBasePath();
        $gitDir = $base . '/.git';

        // 1. Direct filesystem inspection of .git/HEAD
        if (is_dir($gitDir) && file_exists($gitDir . '/HEAD')) {
            $headContent = @file_get_contents($gitDir . '/HEAD');
            if ($headContent !== false) {
                $headContent = trim($headContent);

                if (strpos($headContent, 'ref: ') === 0) {
                    $refRelative = trim(substr($headContent, 5));
                    $refFile = $gitDir . '/' . $refRelative;

                    if (file_exists($refFile)) {
                        $hash = trim((string)@file_get_contents($refFile));
                        if (preg_match('/^[a-f0-9]{40}$/i', $hash)) {
                            return $hash;
                        }
                    }

                    // Check packed-refs if branch ref file was packed
                    $packedRefsFile = $gitDir . '/packed-refs';
                    if (file_exists($packedRefsFile)) {
                        $lines = @file($packedRefsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if (is_array($lines)) {
                            foreach ($lines as $line) {
                                if (strpos($line, '#') === 0 || strpos($line, '^') === 0) continue;
                                $parts = explode(' ', trim($line), 2);
                                if (count($parts) === 2 && $parts[1] === $refRelative) {
                                    if (preg_match('/^[a-f0-9]{40}$/i', $parts[0])) {
                                        return $parts[0];
                                    }
                                }
                            }
                        }
                    }
                } elseif (preg_match('/^[a-f0-9]{40}$/i', $headContent)) {
                    // Detached HEAD
                    return $headContent;
                }
            }
        }

        // 2. Try git command if shell exec is enabled
        if (function_exists('exec')) {
            $output = [];
            $ret = 0;
            @exec('git rev-parse HEAD 2>&1', $output, $ret);
            if ($ret === 0 && !empty($output[0]) && preg_match('/^[a-f0-9]{40}$/i', trim($output[0]))) {
                return trim($output[0]);
            }
        }

        // 3. Fallback to config/version.json
        $jsonFile = $base . '/config/version.json';
        if (file_exists($jsonFile)) {
            $data = @json_decode(@file_get_contents($jsonFile), true);
            if (!empty($data['commit'])) {
                return trim($data['commit']);
            }
            if (!empty($data['build'])) {
                return 'build_' . trim($data['build']);
            }
        }

        // 4. Fallback to modification timestamp signature of key files
        $coreFiles = [$base . '/config/app.php', $base . '/index.php', $base . '/includes/header.php'];
        $mtimes = '';
        foreach ($coreFiles as $f) {
            if (file_exists($f)) {
                $mtimes .= @filemtime($f) . '_';
            }
        }
        if (!empty($mtimes)) {
            return md5($mtimes);
        }

        return 'unknown_commit_hash';
    }

    /**
     * Get the active website version
     */
    public static function getCurrentVersion($conn = null)
    {
        global $global_settings;

        if ($conn instanceof mysqli) {
            $res = @$conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_version' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                if (!empty($row['setting_value'])) {
                    return trim($row['setting_value']);
                }
            }
        }

        if (!empty($global_settings['site_version'])) {
            return trim($global_settings['site_version']);
        }

        $jsonFile = self::getBasePath() . '/config/version.json';
        if (file_exists($jsonFile)) {
            $data = @json_decode(@file_get_contents($jsonFile), true);
            if (!empty($data['version'])) {
                return trim($data['version']);
            }
        }

        if (defined('APP_VERSION')) {
            return APP_VERSION;
        }

        return 'v2.5.0';
    }

    /**
     * Check if Auto Change Version is enabled in settings (default: 1 / true)
     */
    public static function isAutoVersionEnabled($conn = null)
    {
        global $global_settings;

        if ($conn instanceof mysqli) {
            $res = @$conn->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_version_enabled' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                return ($row['setting_value'] === '1');
            }
        }

        if (isset($global_settings['auto_version_enabled'])) {
            return ($global_settings['auto_version_enabled'] === '1');
        }

        return true; // Default ON
    }

    /**
     * Core detection and auto-increment logic (Auto Change Version).
     * Compares the latest Git commit/build hash with the recorded hash in DB.
     * If changed, automatically increments the Patch version.
     */
    public static function autoCheckAndBump($conn)
    {
        if (!($conn instanceof mysqli)) {
            return ['bumped' => false, 'reason' => 'invalid_database_connection'];
        }

        // 1. Check if auto versioning is active
        if (!self::isAutoVersionEnabled($conn)) {
            return ['bumped' => false, 'reason' => 'auto_version_disabled'];
        }

        // 2. Fetch current system commit hash
        $currentHash = self::getSystemCommitHash();
        if (empty($currentHash) || $currentHash === 'unknown_commit_hash') {
            return ['bumped' => false, 'reason' => 'could_not_detect_commit_hash'];
        }

        // 3. Fetch recorded version & last commit hash from DB
        $lastHash = null;
        $currentVersion = 'v2.5.0';

        $res = @$conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('last_version_hash', 'site_version', 'auto_version_enabled')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($row['setting_key'] === 'last_version_hash') {
                    $lastHash = trim($row['setting_value']);
                } elseif ($row['setting_key'] === 'site_version') {
                    if (!empty($row['setting_value'])) {
                        $currentVersion = trim($row['setting_value']);
                    }
                }
            }
            $res->free();
        }

        // 4. Initial baseline setup: if last_version_hash hasn't been saved yet
        if ($lastHash === null || $lastHash === '') {
            $safeHash = $conn->real_escape_string($currentHash);
            $safeVersion = $conn->real_escape_string($currentVersion);
            $now = date('Y-m-d H:i:s');

            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_hash', '$safeHash') ON DUPLICATE KEY UPDATE setting_value='$safeHash'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_version', '$safeVersion') ON DUPLICATE KEY UPDATE setting_value='$safeVersion'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_version_enabled', '1') ON DUPLICATE KEY UPDATE setting_value='1'");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_bump_at', '$now') ON DUPLICATE KEY UPDATE setting_value='$now'");

            self::syncVersionJsonFile($currentVersion, $currentHash);

            return [
                'bumped'  => false,
                'reason'  => 'baseline_initialized',
                'version' => $currentVersion,
                'commit'  => $currentHash
            ];
        }

        // 5. If commit hash matches, no new update detected
        if ($currentHash === $lastHash) {
            return [
                'bumped'  => false,
                'reason'  => 'up_to_date',
                'version' => $currentVersion,
                'commit'  => $currentHash
            ];
        }

        // 6. NEW UPDATE DETECTED! Automatically increment patch version
        $oldVersion = $currentVersion;
        $newVersion = self::bumpPatch($oldVersion);

        $safeNewVersion = $conn->real_escape_string($newVersion);
        $safeHash = $conn->real_escape_string($currentHash);
        $now = date('Y-m-d H:i:s');

        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_version', '$safeNewVersion') ON DUPLICATE KEY UPDATE setting_value='$safeNewVersion'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_hash', '$safeHash') ON DUPLICATE KEY UPDATE setting_value='$safeHash'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_bump_at', '$now') ON DUPLICATE KEY UPDATE setting_value='$now'");

        // Synchronize in-memory global settings
        global $global_settings;
        if (is_array($global_settings)) {
            $global_settings['site_version'] = $newVersion;
            $global_settings['last_version_hash'] = $currentHash;
            $global_settings['last_version_bump_at'] = $now;
        }

        // Synchronize config/version.json
        self::syncVersionJsonFile($newVersion, $currentHash);

        // Auto-update export_ready_to_sell package locally if folder exists
        self::syncExportPackage(true);

        return [
            'bumped'      => true,
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'commit'      => $currentHash,
            'bumped_at'   => $now
        ];
    }

    /**
     * Manually set the version string and auto-version toggle setting
     */
    public static function setVersion($conn, $newVersion, $autoEnabled = null)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $newVersion = trim($newVersion);
        if (empty($newVersion)) {
            return false;
        }

        $safeVersion = $conn->real_escape_string($newVersion);
        $currentHash = self::getSystemCommitHash();
        $safeHash = $conn->real_escape_string($currentHash);
        $now = date('Y-m-d H:i:s');

        // Save new version and lock current commit hash so it won't immediate re-bump on this commit
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_version', '$safeVersion') ON DUPLICATE KEY UPDATE setting_value='$safeVersion'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_hash', '$safeHash') ON DUPLICATE KEY UPDATE setting_value='$safeHash'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_version_bump_at', '$now') ON DUPLICATE KEY UPDATE setting_value='$now'");

        if ($autoEnabled !== null) {
            $autoVal = ($autoEnabled == '1' || $autoEnabled === true) ? '1' : '0';
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_version_enabled', '$autoVal') ON DUPLICATE KEY UPDATE setting_value='$autoVal'");
        }

        global $global_settings;
        if (is_array($global_settings)) {
            $global_settings['site_version'] = $newVersion;
            $global_settings['last_version_hash'] = $currentHash;
            if ($autoEnabled !== null) {
                $global_settings['auto_version_enabled'] = ($autoEnabled == '1' || $autoEnabled === true) ? '1' : '0';
            }
        }

        self::syncVersionJsonFile($newVersion, $currentHash);

        // Auto-update export_ready_to_sell package locally if folder exists
        self::syncExportPackage(true);

        return true;
    }

    /**
     * Manually perform a version bump (+Patch, +Minor, or +Major)
     */
    public static function bumpVersionManually($conn, $type = 'patch')
    {
        $current = self::getCurrentVersion($conn);
        switch (strtolower($type)) {
            case 'minor':
                $next = self::bumpMinor($current);
                break;
            case 'major':
                $next = self::bumpMajor($current);
                break;
            case 'patch':
            default:
                $next = self::bumpPatch($current);
                break;
        }

        $success = self::setVersion($conn, $next);
        return [
            'success'     => $success,
            'old_version' => $current,
            'new_version' => $next,
            'type'        => $type
        ];
    }

    /**
     * Synchronize config/version.json file
     */
    public static function syncVersionJsonFile($version, $commitHash = null, $force = false)
    {
        $base = self::getBasePath();
        // In local git development, avoid constantly dirtying tracked version.json on auto-bump
        if (!$force && is_dir($base . '/.git') && file_exists($base . '/config/version.json')) {
            return true;
        }

        if ($commitHash === null) {
            $commitHash = self::getSystemCommitHash();
        }

        $file = $base . '/config/version.json';
        $data = [
            'version'    => $version,
            'commit'     => $commitHash,
            'short_hash' => substr($commitHash, 0, 7),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return true;
    }

    /**
     * Return comprehensive version metadata for UI and AJAX endpoints
     */
    public static function getVersionMetadata($conn = null)
    {
        $version = self::getCurrentVersion($conn);
        $autoEnabled = self::isAutoVersionEnabled($conn);
        $commit = self::getSystemCommitHash();
        $shortHash = substr($commit, 0, 7);

        $lastBumpAt = null;
        if ($conn instanceof mysqli) {
            $res = @$conn->query("SELECT setting_value FROM settings WHERE setting_key = 'last_version_bump_at' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $lastBumpAt = $row['setting_value'];
            }
        }

        return [
            'version'       => $version,
            'auto_enabled'  => $autoEnabled,
            'commit'        => $commit,
            'short_hash'    => $shortHash,
            'last_bump_at'  => $lastBumpAt,
            'next_patch'    => self::bumpPatch($version),
            'next_minor'    => self::bumpMinor($version),
            'next_major'    => self::bumpMajor($version)
        ];
    }

    /**
     * Trigger auto-update of export_ready_to_sell package if the directory exists locally.
     */
    public static function syncExportPackage($silent = true)
    {
        $base = self::getBasePath();
        $builderScript = $base . '/export_ready_to_sell/build_zip.php';
        if (file_exists($builderScript)) {
            require_once $builderScript;
            if (class_exists('PackageBuilder')) {
                return PackageBuilder::build($silent);
            }
        }
        return ['success' => false, 'error' => 'Export package builder not found or folder omitted.'];
    }

    /**
     * Get export package metadata if available
     */
    public static function getExportPackageMetadata()
    {
        $base = self::getBasePath();
        $metaFile = $base . '/export_ready_to_sell/package_meta.json';
        if (file_exists($metaFile)) {
            $data = @json_decode(@file_get_contents($metaFile), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }
}
