<?php
/**
 * AJAX Media Duplicate & Usage Checker
 *
 * Actions:
 *   check_all   – Returns usage info for all media + duplicate groups
 *   delete_safe – Safely deletes a single unused media file
 *   bulk_delete_unused – Safely bulk-delete unused media
 *
 * Safety Rules (Physical file delete):
 *   - Never delete if referenced in: products, product_images,
 *     banners, categories, hero_slides/sliders, pages, site_settings,
 *     testimonials, homepage_features, about_page_content
 *   - Only removes DB record + file if truly 0 references anywhere
 */

header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/helpers/csrf.php';

// ── Auth ─────────────────────────────────────────────────────
try {
    AuthMiddleware::check($conn);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── CSRF (for mutating actions) ───────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$mutating = in_array($action, ['delete_safe', 'bulk_delete_unused']);
if ($mutating) {
    $submitted_token = $_POST['_csrf_token'] ?? '';
    $stored_token    = $_SESSION['csrf_token'] ?? '';
    if (empty($stored_token) || !hash_equals($stored_token, $submitted_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token mismatch. Refresh the page.']);
        exit;
    }
}

// ════════════════════════════════════════════════════════════
//  HELPER: Count references to a file (by basename only)
//  Returns array with breakdown + total count
// ════════════════════════════════════════════════════════════
function count_file_references(mysqli $db, string $file_basename): array
{
    $esc = $db->real_escape_string($file_basename);

    $refs = [];

    // products.image
    $r = $db->query("SELECT COUNT(*) AS c FROM products WHERE image='$esc'");
    $refs['products'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // product_images.image
    $r = $db->query("SELECT COUNT(*) AS c FROM product_images WHERE image='$esc'");
    $refs['product_images'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // banners.image
    $r = $db->query("SELECT COUNT(*) AS c FROM banners WHERE image='$esc'");
    $refs['banners'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // categories.image
    $r = $db->query("SELECT COUNT(*) AS c FROM categories WHERE image='$esc'");
    $refs['categories'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // ---- Optional tables (check if they exist first) ----
    $optional_tables = [
        'hero_slides'           => 'image',
        'sliders'               => 'image',
        'testimonials'          => 'image',
        'homepage_features'     => 'image',
        'about_page_content'    => 'image',
    ];

    // Check pages & site_settings for any URL containing the filename
    foreach ($optional_tables as $tbl => $col) {
        $exists = $db->query("SHOW TABLES LIKE '$tbl'");
        if ($exists && $exists->num_rows > 0) {
            $r = $db->query("SELECT COUNT(*) AS c FROM `$tbl` WHERE `$col`='$esc'");
            $refs[$tbl] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        }
    }

    // pages – search body/content columns for filename occurrence
    $pt = $db->query("SHOW TABLES LIKE 'pages'");
    if ($pt && $pt->num_rows > 0) {
        $r = $db->query("SELECT COUNT(*) AS c FROM pages WHERE content LIKE '%$esc%'");
        $refs['pages'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    // site_settings – any value containing this filename
    $st = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($st && $st->num_rows > 0) {
        $r = $db->query("SELECT COUNT(*) AS c FROM site_settings WHERE `value` LIKE '%$esc%'");
        $refs['site_settings'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    // manage_slides / slides table
    $slt = $db->query("SHOW TABLES LIKE 'slides'");
    if ($slt && $slt->num_rows > 0) {
        $r = $db->query("SELECT COUNT(*) AS c FROM slides WHERE image LIKE '%$esc%'");
        $refs['slides'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    $total = array_sum($refs);
    return ['total' => $total, 'breakdown' => $refs];
}

// ════════════════════════════════════════════════════════════
//  ACTION: check_all
// ════════════════════════════════════════════════════════════
if ($action === 'check_all') {
    // Fetch all media
    $result = $conn->query("SELECT id, file_name, original_name, file_path, file_url, file_type, file_size, mime_type FROM media_library ORDER BY created_at DESC");
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit;
    }

    $all_files   = [];
    $hash_groups = []; // md5 => [ids]
    $name_groups = []; // original_name_lower => [ids]
    $usage_map   = []; // id => usage info

    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $basename = basename($row['file_path']);

        // Calculate usage
        $usage = count_file_references($conn, $basename);
        $usage_map[$id] = $usage;

        // Build all_files entry
        $all_files[$id] = [
            'id'            => $id,
            'file_name'     => $row['file_name'],
            'original_name' => $row['original_name'],
            'file_path'     => $row['file_path'],
            'file_url'      => $row['file_url'],
            'file_type'     => $row['file_type'],
            'file_size'     => (int)$row['file_size'],
            'mime_type'     => $row['mime_type'],
            'usage_count'   => $usage['total'],
            'usage_detail'  => $usage['breakdown'],
            'is_used'       => $usage['total'] > 0,
        ];

        // Group by original name (case-insensitive) for name duplicates
        $name_key = strtolower(trim($row['original_name']));
        $name_groups[$name_key][] = $id;

        // MD5 hash grouping (physical file)
        $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
        if ($full_path && file_exists($full_path)) {
            $hash = md5_file($full_path);
            if ($hash) {
                $hash_groups[$hash][] = $id;
            }
        }
    }

    // Build duplicate groups (by content hash)
    $duplicates_by_hash = [];
    foreach ($hash_groups as $hash => $ids) {
        if (count($ids) > 1) {
            $duplicates_by_hash[] = [
                'hash'  => $hash,
                'ids'   => $ids,
                'files' => array_map(fn($id) => $all_files[$id], $ids),
            ];
        }
    }

    // Build duplicate groups (by original name)
    $duplicates_by_name = [];
    foreach ($name_groups as $name => $ids) {
        if (count($ids) > 1) {
            $duplicates_by_name[] = [
                'name'  => $name,
                'ids'   => $ids,
                'files' => array_map(fn($id) => $all_files[$id], $ids),
            ];
        }
    }

    // Unused files
    $unused_files = array_values(array_filter($all_files, fn($f) => !$f['is_used']));

    echo json_encode([
        'success'             => true,
        'total'               => count($all_files),
        'unused_count'        => count($unused_files),
        'duplicate_hash_groups' => count($duplicates_by_hash),
        'duplicate_name_groups' => count($duplicates_by_name),
        'unused_files'        => $unused_files,
        'duplicates_by_hash'  => $duplicates_by_hash,
        'duplicates_by_name'  => $duplicates_by_name,
        'all_files'           => array_values($all_files),
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
//  ACTION: delete_safe (single file)
// ════════════════════════════════════════════════════════════
if ($action === 'delete_safe') {
    $del_id = intval($_POST['media_id'] ?? 0);
    if ($del_id <= 0) {
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
        echo json_encode(['success' => false, 'message' => 'File not found in database.']);
        exit;
    }

    $basename = basename($row['file_path']);
    $usage    = count_file_references($conn, $basename);

    if ($usage['total'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => "File is currently in use ({$usage['total']} reference(s)). Cannot delete.",
            'usage'   => $usage,
        ]);
        exit;
    }

    // Safe to delete
    $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
    $file_deleted = false;
    if ($full_path && file_exists($full_path)) {
        $file_deleted = @unlink($full_path);
    }

    $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id = ?");
    $del_stmt->bind_param('i', $del_id);
    $del_stmt->execute();
    $db_deleted = $del_stmt->affected_rows > 0;
    $del_stmt->close();

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
    $raw_ids = $_POST['media_ids'] ?? '';
    $ids = array_filter(array_map('intval',
        is_array($raw_ids) ? $raw_ids : explode(',', $raw_ids)
    ));

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No IDs provided.']);
        exit;
    }

    $deleted_count  = 0;
    $skipped_count  = 0;
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
                'id'       => $del_id,
                'name'     => $basename,
                'usage'    => $usage['total'],
            ];
            continue;
        }

        // Safe to delete
        $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
        if ($full_path && file_exists($full_path)) {
            @unlink($full_path);
        }

        $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id = ?");
        $del_stmt->bind_param('i', $del_id);
        $del_stmt->execute();
        if ($del_stmt->affected_rows > 0) $deleted_count++;
        $del_stmt->close();
    }

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
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
