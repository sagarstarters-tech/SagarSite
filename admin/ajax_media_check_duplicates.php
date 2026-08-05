<?php
/**
 * AJAX Media Duplicate & Usage Checker
 * PHP 7.2+ compatible (no arrow functions)
 *
 * Actions:
 *   check_all          – Returns usage info for all media + duplicate groups
 *   delete_safe        – Safely deletes a single unused media file
 *   bulk_delete_unused – Safely bulk-delete unused media
 */

// Buffer output so any PHP warnings don't break JSON
ob_start();

header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/helpers/csrf.php';

// ── Auth ─────────────────────────────────────────────────────
try {
    AuthMiddleware::check($conn);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── CSRF (for mutating actions) ───────────────────────────────
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

$mutating = in_array($action, ['delete_safe', 'bulk_delete_unused']);
if ($mutating) {
    $submitted_token = isset($_POST['_csrf_token']) ? $_POST['_csrf_token'] : '';
    $stored_token    = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (empty($stored_token) || !hash_equals($stored_token, $submitted_token)) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token mismatch. Refresh the page.']);
        exit;
    }
}

// ════════════════════════════════════════════════════════════
//  HELPER: Build set of all used file basenames (bulk, fast)
//  Returns array: ['basename.jpg' => true, ...]
// ════════════════════════════════════════════════════════════
function get_all_used_basenames(mysqli $db)
{
    $used = [];

    // Core tables always present
    $core_tables = [
        ['table' => 'products',       'col' => 'image'],
        ['table' => 'product_images', 'col' => 'image'],
        ['table' => 'banners',        'col' => 'image'],
        ['table' => 'categories',     'col' => 'image'],
    ];

    // Optional tables – check existence + column before querying
    $optional = [
        ['table' => 'hero_slides',        'col' => 'image'],
        ['table' => 'sliders',            'col' => 'image'],
        ['table' => 'testimonials',       'col' => 'image'],
        ['table' => 'homepage_features',  'col' => 'image'],
        ['table' => 'slides',             'col' => 'image'],
    ];

    $all_tables = $core_tables;
    foreach ($optional as $ot) {
        $tbl  = $db->real_escape_string($ot['table']);
        $col  = $db->real_escape_string($ot['col']);
        $texists = $db->query("SHOW TABLES LIKE '$tbl'");
        if (!$texists || $texists->num_rows === 0) continue;
        $cexists = $db->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
        if (!$cexists || $cexists->num_rows === 0) continue;
        $all_tables[] = $ot;
    }

    // Also check site_settings (value column, LIKE search)
    $st = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($st && $st->num_rows > 0) {
        // We'll handle this separately via LIKE – too broad for exact match
        // Just collect values that look like filenames
        $sr = $db->query("SELECT `value` FROM site_settings WHERE `value` LIKE '%.%' AND `value` NOT LIKE 'http%'");
        if ($sr) {
            while ($row = $sr->fetch_assoc()) {
                $bn = basename(trim($row['value']));
                if ($bn && strpos($bn, '.') !== false) {
                    $used[$bn] = true;
                }
            }
        }
    }

    // Run bulk queries for each table
    foreach ($all_tables as $ut) {
        $tbl = $ut['table'];
        $col = $ut['col'];
        $r = $db->query("SELECT DISTINCT `$col` FROM `$tbl` WHERE `$col` IS NOT NULL AND `$col` != ''");
        if (!$r) continue;
        while ($row = $r->fetch_assoc()) {
            $bn = basename($row[$col]);
            if ($bn) $used[$bn] = true;
        }
    }

    return $used;
}

// ════════════════════════════════════════════════════════════
//  HELPER: Count references to ONE file (for delete safety check)
// ════════════════════════════════════════════════════════════
function count_file_references(mysqli $db, $file_basename)
{
    $esc  = $db->real_escape_string($file_basename);
    $refs = [];

    $tables = [
        ['table' => 'products',       'col' => 'image'],
        ['table' => 'product_images', 'col' => 'image'],
        ['table' => 'banners',        'col' => 'image'],
        ['table' => 'categories',     'col' => 'image'],
    ];

    $optional = [
        ['table' => 'hero_slides',       'col' => 'image'],
        ['table' => 'sliders',           'col' => 'image'],
        ['table' => 'testimonials',      'col' => 'image'],
        ['table' => 'homepage_features', 'col' => 'image'],
        ['table' => 'slides',            'col' => 'image'],
    ];

    foreach ($optional as $ot) {
        $tbl = $db->real_escape_string($ot['table']);
        $col = $db->real_escape_string($ot['col']);
        $texists = $db->query("SHOW TABLES LIKE '$tbl'");
        if (!$texists || $texists->num_rows === 0) continue;
        $cexists = $db->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
        if (!$cexists || $cexists->num_rows === 0) continue;
        $tables[] = $ot;
    }

    foreach ($tables as $ut) {
        $tbl = $ut['table'];
        $col = $ut['col'];
        $r = $db->query("SELECT COUNT(*) AS c FROM `$tbl` WHERE `$col`='$esc'");
        $refs[$tbl] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    // pages content search
    $pt = $db->query("SHOW TABLES LIKE 'pages'");
    if ($pt && $pt->num_rows > 0) {
        $r = $db->query("SELECT COUNT(*) AS c FROM pages WHERE content LIKE '%$esc%'");
        $refs['pages'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    // site_settings
    $st = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($st && $st->num_rows > 0) {
        $r = $db->query("SELECT COUNT(*) AS c FROM site_settings WHERE `value` LIKE '%$esc%'");
        $refs['site_settings'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    $total = array_sum($refs);
    return ['total' => $total, 'breakdown' => $refs];
}

// ════════════════════════════════════════════════════════════
//  ACTION: check_all
// ════════════════════════════════════════════════════════════
if ($action === 'check_all') {
    // Build used basenames map (bulk, fast - one query per table)
    $used_basenames = get_all_used_basenames($conn);

    // Fetch all media
    $result = $conn->query(
        "SELECT id, file_name, original_name, file_path, file_url, file_type, file_size, mime_type
         FROM media_library ORDER BY created_at DESC"
    );
    if (!$result) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit;
    }

    $all_files   = [];
    $hash_groups = [];
    $name_groups = [];

    while ($row = $result->fetch_assoc()) {
        $id       = (int)$row['id'];
        $basename = basename($row['file_path']);
        $is_used  = isset($used_basenames[$basename]);

        $all_files[$id] = [
            'id'            => $id,
            'file_name'     => $row['file_name'],
            'original_name' => $row['original_name'],
            'file_path'     => $row['file_path'],
            'file_url'      => $row['file_url'],
            'file_type'     => $row['file_type'],
            'file_size'     => (int)$row['file_size'],
            'mime_type'     => $row['mime_type'],
            'usage_count'   => $is_used ? 1 : 0,
            'is_used'       => $is_used,
        ];

        // Group by original name (case-insensitive)
        $name_key = strtolower(trim($row['original_name']));
        $name_groups[$name_key][] = $id;

        // MD5 hash grouping – only if file exists on disk
        $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
        if ($full_path && file_exists($full_path)) {
            $hash = @md5_file($full_path);
            if ($hash) {
                $hash_groups[$hash][] = $id;
            }
        }
    }

    // Build duplicate groups (by content hash)
    $duplicates_by_hash = [];
    foreach ($hash_groups as $hash => $ids) {
        if (count($ids) > 1) {
            $files = [];
            foreach ($ids as $fid) {
                if (isset($all_files[$fid])) $files[] = $all_files[$fid];
            }
            $duplicates_by_hash[] = [
                'hash'  => $hash,
                'ids'   => $ids,
                'files' => $files,
            ];
        }
    }

    // Build duplicate groups (by original name)
    $duplicates_by_name = [];
    foreach ($name_groups as $name => $ids) {
        if (count($ids) > 1) {
            $files = [];
            foreach ($ids as $fid) {
                if (isset($all_files[$fid])) $files[] = $all_files[$fid];
            }
            $duplicates_by_name[] = [
                'name'  => $name,
                'ids'   => $ids,
                'files' => $files,
            ];
        }
    }

    // Unused files
    $unused_files = [];
    foreach ($all_files as $f) {
        if (!$f['is_used']) $unused_files[] = $f;
    }

    ob_end_clean();
    echo json_encode([
        'success'               => true,
        'total'                 => count($all_files),
        'unused_count'          => count($unused_files),
        'duplicate_hash_groups' => count($duplicates_by_hash),
        'duplicate_name_groups' => count($duplicates_by_name),
        'unused_files'          => array_values($unused_files),
        'duplicates_by_hash'    => $duplicates_by_hash,
        'duplicates_by_name'    => $duplicates_by_name,
        'all_files'             => array_values($all_files),
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
//  ACTION: delete_safe (single file)
// ════════════════════════════════════════════════════════════
if ($action === 'delete_safe') {
    $del_id = intval(isset($_POST['media_id']) ? $_POST['media_id'] : 0);
    if ($del_id <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT file_path, file_name FROM media_library WHERE id = ?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'File not found in database.']);
        exit;
    }

    $basename = basename($row['file_path']);
    $usage    = count_file_references($conn, $basename);

    if ($usage['total'] > 0) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => "File is currently in use ({$usage['total']} reference(s)). Cannot delete.",
            'usage'   => $usage,
        ]);
        exit;
    }

    // Safe to delete
    $rel_path     = ltrim($row['file_path'], '/\\');
    $full_path    = __DIR__ . '/../' . $rel_path;
    $file_deleted = false;
    if (file_exists($full_path)) {
        $file_deleted = @unlink($full_path);
    }

    $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id = ?");
    $del_stmt->bind_param('i', $del_id);
    $del_stmt->execute();
    $db_deleted = $del_stmt->affected_rows > 0;
    $del_stmt->close();

    ob_end_clean();
    echo json_encode([
        'success'      => $db_deleted,
        'message'      => $db_deleted ? 'File deleted successfully.' : 'DB delete failed.',
        'file_deleted' => $file_deleted,
        'db_deleted'   => $db_deleted,
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
//  ACTION: bulk_delete_unused
// ════════════════════════════════════════════════════════════
if ($action === 'bulk_delete_unused') {
    $raw_ids = isset($_POST['media_ids']) ? $_POST['media_ids'] : '';
    $ids = array_filter(array_map('intval',
        is_array($raw_ids) ? $raw_ids : explode(',', $raw_ids)
    ));

    if (empty($ids)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No IDs provided.']);
        exit;
    }

    $deleted_count   = 0;
    $skipped_count   = 0;
    $skipped_details = [];

    foreach ($ids as $del_id) {
        $stmt = $conn->prepare("SELECT file_path, file_name FROM media_library WHERE id = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) continue;

        $basename = basename($row['file_path']);
        $usage    = count_file_references($conn, $basename);

        if ($usage['total'] > 0) {
            $skipped_count++;
            $skipped_details[] = [
                'id'    => $del_id,
                'name'  => $basename,
                'usage' => $usage['total'],
            ];
            continue;
        }

        $rel_path  = ltrim($row['file_path'], '/\\');
        $full_path = __DIR__ . '/../' . $rel_path;
        if (file_exists($full_path)) {
            @unlink($full_path);
        }

        $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id = ?");
        $del_stmt->bind_param('i', $del_id);
        $del_stmt->execute();
        if ($del_stmt->affected_rows > 0) $deleted_count++;
        $del_stmt->close();
    }

    ob_end_clean();
    echo json_encode([
        'success'         => true,
        'deleted_count'   => $deleted_count,
        'skipped_count'   => $skipped_count,
        'skipped_details' => $skipped_details,
        'message'         => "$deleted_count file(s) deleted. $skipped_count skipped (in use).",
    ]);
    exit;
}

// ── Unknown action ────────────────────────────────────────────
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
