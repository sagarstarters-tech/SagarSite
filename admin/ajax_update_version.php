<?php
/**
 * AJAX Endpoint: Update Website / Release Version (Auto Change Version System)
 */
include_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once BASE_PATH . '/classes/VersionManager.php';

header('Content-Type: application/json');

// Security: Only logged-in admin can change version
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'save_version');

    // 1. Action: Quick Bump (patch, minor, major)
    if ($action === 'bump') {
        $type = trim($_POST['type'] ?? 'patch');
        $res = VersionManager::bumpVersionManually($conn, $type);
        if ($res['success']) {
            $meta = VersionManager::getVersionMetadata($conn);
            echo json_encode([
                'success'  => true,
                'version'  => $res['new_version'],
                'metadata' => $meta,
                'message'  => ucfirst($type) . ' version successfully bumped to ' . $res['new_version'] . '!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to bump version.']);
        }
        exit;
    }

    // 2. Action: Save Version & Auto-Version Toggle
    $version = trim($_POST['version'] ?? '');
    if (empty($version)) {
        echo json_encode(['success' => false, 'message' => 'Version string cannot be empty.']);
        exit;
    }

    $autoEnabled = null;
    if (isset($_POST['auto_version'])) {
        $autoEnabled = ($_POST['auto_version'] == '1' || $_POST['auto_version'] === 'true') ? '1' : '0';
    }

    $saved = VersionManager::setVersion($conn, $version, $autoEnabled);

    if ($saved) {
        $meta = VersionManager::getVersionMetadata($conn);
        echo json_encode([
            'success'      => true,
            'version'      => htmlspecialchars($version),
            'auto_enabled' => $meta['auto_enabled'],
            'metadata'     => $meta,
            'message'      => 'Website version successfully saved as ' . htmlspecialchars($version) . '!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error while saving version.'
        ]);
        exit;
    }

    // 3. Action: Sync Export Ready-to-Sell Package
    if ($action === 'sync_export_package') {
        $res = VersionManager::syncExportPackage(false);
        if ($res['success']) {
            echo json_encode([
                'success'        => true,
                'message'        => "Export package successfully updated to {$res['version']}! ({$res['files_count']} files, {$res['size_mb']} MB)",
                'export_package' => $res
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to build package: ' . ($res['error'] ?? 'Unknown error')
            ]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_meta') {
    $meta = VersionManager::getVersionMetadata($conn);
    $exportMeta = VersionManager::getExportPackageMetadata();
    echo json_encode([
        'success'        => true,
        'metadata'       => $meta,
        'export_package' => $exportMeta
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);

