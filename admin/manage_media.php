<?php
/**
 * Media / Gallery Manager
 * WordPress-style media library for images & videos.
 */
include 'admin_header.php';

// ── Ensure media table exists ────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `media_library` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `file_name`     VARCHAR(255) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path`     VARCHAR(500) NOT NULL,
        `file_url`      VARCHAR(500) NOT NULL,
        `file_type`     ENUM('image','video','other') NOT NULL DEFAULT 'image',
        `mime_type`     VARCHAR(100) NOT NULL DEFAULT '',
        `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `width`         INT UNSIGNED DEFAULT NULL,
        `height`        INT UNSIGNED DEFAULT NULL,
        `alt_text`      VARCHAR(255) DEFAULT '',
        `caption`       TEXT DEFAULT NULL,
        `uploaded_by`   INT UNSIGNED DEFAULT NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_file_type` (`file_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Ensure upload directories exist ──────────────────────────
$media_base = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
$media_images_dir = $media_base . '/media/images';
$media_videos_dir = $media_base . '/media/videos';

if (!is_dir($media_images_dir)) mkdir($media_images_dir, 0755, true);
if (!is_dir($media_videos_dir)) mkdir($media_videos_dir, 0755, true);

// ── Security: .htaccess to prevent PHP execution inside uploads ──
$htaccess_path = $media_base . '/media/.htaccess';
if (!file_exists($htaccess_path)) {
    file_put_contents($htaccess_path, "# Prevent PHP execution\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps\nAddHandler default-handler .php .phtml .php3 .php4 .php5 .php7 .phps\n<FilesMatch \"\\.php$\">\n    deny from all\n</FilesMatch>\nOptions -Indexes\nOptions -ExecCGI\n");
}

// ── Handle DELETE (POST) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['media_action'] ?? '') === 'delete') {
    csrf_verify();
    $del_id = intval($_POST['media_id'] ?? 0);
    if ($del_id > 0) {
        $stmt = $conn->prepare("SELECT file_path, file_name FROM media_library WHERE id = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
            $file_basename = basename($row['file_path']);
            $safe_basename = $conn->real_escape_string($file_basename);
            // Safety: only delete physical file if NOT referenced by products/gallery/banners/categories
            $prod_refs = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $gal_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $ban_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $cat_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$safe_basename'")->fetch_assoc()['c'];
            if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $cat_refs === 0) {
                if ($full_path && file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
            $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id = ?");
            $del_stmt->bind_param('i', $del_id);
            $del_stmt->execute();
            $del_stmt->close();
            set_flash('success', 'Media file deleted successfully.');
        }
        $stmt->close();
    }
    header('Location: manage_media.php');
    exit;
}

// ── Handle BULK DELETE (POST) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['media_action'] ?? '') === 'bulk_delete') {
    csrf_verify();
    $raw_ids = $_POST['media_ids'] ?? '';
    $del_ids = array_filter(array_map('intval', is_array($raw_ids) ? $raw_ids : explode(',', $raw_ids)));
    
    if (!empty($del_ids)) {
        $placeholders = implode(',', array_fill(0, count($del_ids), '?'));
        $types = str_repeat('i', count($del_ids));

        $stmt = $conn->prepare("SELECT file_path, file_name FROM media_library WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$del_ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $full_path = realpath(__DIR__ . '/../' . $row['file_path']);
            $file_basename = basename($row['file_path']);
            $safe_basename = $conn->real_escape_string($file_basename);
            // Safety: only delete physical file if NOT referenced by products/gallery/banners/categories
            $prod_refs = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $gal_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $ban_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$safe_basename'")->fetch_assoc()['c'];
            $cat_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$safe_basename'")->fetch_assoc()['c'];
            if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $cat_refs === 0) {
                if ($full_path && file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
        }
        $stmt->close();

        $del_stmt = $conn->prepare("DELETE FROM media_library WHERE id IN ($placeholders)");
        $del_stmt->bind_param($types, ...$del_ids);
        $del_stmt->execute();
        $count = $del_stmt->affected_rows;
        $del_stmt->close();

        set_flash('success', $count . ' media file(s) deleted successfully.');
    } else {
        set_flash('warning', 'No media files selected for deletion.');
    }
    header('Location: manage_media.php');
    exit;
}

// ── Handle UPDATE metadata (POST) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['media_action'] ?? '') === 'update_meta') {
    csrf_verify();
    $upd_id   = intval($_POST['media_id'] ?? 0);
    $alt_text = trim($_POST['alt_text'] ?? '');
    $caption  = trim($_POST['caption'] ?? '');
    if ($upd_id > 0) {
        $stmt = $conn->prepare("UPDATE media_library SET alt_text = ?, caption = ? WHERE id = ?");
        $stmt->bind_param('ssi', $alt_text, $caption, $upd_id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Media details updated successfully.');
    }
    header('Location: manage_media.php');
    exit;
}

// ── Handle SYNC ASSETS (POST) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['media_action'] ?? '') === 'sync_assets') {
    csrf_verify();
    $assets_dir = realpath(__DIR__ . '/../assets/images');
    $count = 0; $skipped = 0;
    if ($assets_dir && is_dir($assets_dir)) {
        $files = scandir($assets_dir);
        $stmt = $conn->prepare("INSERT INTO media_library (file_name, original_name, file_path, file_url, file_type, mime_type, file_size, width, height) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $full_path = $assets_dir . '/' . $file;
            if (!is_file($full_path)) continue;
            
            $rel_path = 'assets/images/' . $file;
            $safe_rel = $conn->real_escape_string($rel_path);
            $safe_file = $conn->real_escape_string($file);
            $check = $conn->query("SELECT id FROM media_library WHERE file_url = '$safe_rel' OR file_name = '$safe_file'");
            if ($check && $check->num_rows > 0) { $skipped++; continue; }
            
            $size = filesize($full_path);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($full_path);
            if (strpos($mime, 'image/') !== 0) continue;
            
            $info = @getimagesize($full_path);
            $width = $info ? $info[0] : null;
            $height = $info ? $info[1] : null;
            $file_type = 'image';
            
            $stmt->bind_param('ssssssiii', $file, $file, $rel_path, $rel_path, $file_type, $mime, $size, $width, $height);
            if ($stmt->execute()) { $count++; }
        }
        set_flash('success', "Sync complete. Added: $count images. Skipped (already exist): $skipped images.");
    } else {
        set_flash('danger', 'Assets directory not found.');
    }
    header('Location: manage_media.php');
    exit;
}

// ── Fetch all media ──────────────────────────────────────────
$filter_type = $_GET['type'] ?? 'all';
$search_q    = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types  = '';

if ($filter_type === 'image') {
    $where[] = "file_type = 'image'";
} elseif ($filter_type === 'video') {
    $where[] = "file_type = 'video'";
}
if ($search_q !== '') {
    $where[] = "(original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ?)";
    $like = '%' . $search_q . '%';
    $params = [$like, $like, $like];
    $types  = 'sss';
}

$sql = "SELECT * FROM media_library";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$media_items = $stmt->get_result();

// Count totals
$total_all    = (int)($conn->query("SELECT COUNT(*) as c FROM media_library")->fetch_assoc()['c'] ?? 0);
$total_images = (int)($conn->query("SELECT COUNT(*) as c FROM media_library WHERE file_type='image'")->fetch_assoc()['c'] ?? 0);
$total_videos = (int)($conn->query("SELECT COUNT(*) as c FROM media_library WHERE file_type='video'")->fetch_assoc()['c'] ?? 0);
?>

<style>
/* ── Media Library Styles ──────────────────────────────── */
.media-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    padding: 16px 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
.media-toolbar .filter-tabs {
    display: flex;
    gap: 4px;
}
.media-toolbar .filter-tabs a {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    color: #6c757d;
    transition: all .2s;
}
.media-toolbar .filter-tabs a:hover {
    background: #f0f0f0;
    color: #333;
}
.media-toolbar .filter-tabs a.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
}
.media-toolbar .filter-tabs a .badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    margin-left: 4px;
    border-radius: 10px;
    background: rgba(255,255,255,.25);
    color: inherit;
}
.media-toolbar .filter-tabs a.active .badge {
    background: rgba(255,255,255,.3);
    color: #fff;
}
.media-search {
    flex: 1;
    min-width: 200px;
    max-width: 300px;
}
.media-search input {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 8px 16px 8px 40px;
    font-size: 0.85rem;
    width: 100%;
    transition: border .2s;
    background: #f8f9fa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.44.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E") no-repeat 12px center / 16px;
}
.media-search input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,.12);
}

/* ── Drop Zone ──────────────────────────────────────────── */
.media-dropzone {
    border: 2px dashed #c5cae9;
    border-radius: 16px;
    padding: 48px 24px;
    text-align: center;
    cursor: pointer;
    transition: all .3s;
    background: linear-gradient(135deg, #f5f7ff 0%, #faf5ff 100%);
    position: relative;
    margin-bottom: 24px;
}
.media-dropzone:hover,
.media-dropzone.dragover {
    border-color: #667eea;
    background: linear-gradient(135deg, #eef1ff 0%, #f3eaff 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(102,126,234,.15);
}
.media-dropzone .dropzone-icon {
    font-size: 3rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
}
.media-dropzone h5 {
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}
.media-dropzone p {
    color: #888;
    font-size: 0.85rem;
    margin-bottom: 0;
}
.media-dropzone .browse-btn {
    display: inline-block;
    margin-top: 16px;
    padding: 10px 28px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
}
.media-dropzone .browse-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102,126,234,.3);
}
.media-dropzone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

/* ── Upload Progress ────────────────────────────────────── */
.upload-progress-container {
    display: none;
    margin-bottom: 20px;
}
.upload-progress-container.active {
    display: block;
}
.upload-file-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: #fff;
    border-radius: 10px;
    margin-bottom: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.upload-file-item .file-thumb {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}
.upload-file-item .file-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.upload-file-item .file-thumb i {
    font-size: 1.4rem;
    color: #764ba2;
}
.upload-file-item .file-info {
    flex: 1;
    min-width: 0;
}
.upload-file-item .file-info .name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.upload-file-item .file-info .size {
    font-size: 0.75rem;
    color: #999;
}
.upload-file-item .progress {
    height: 6px;
    border-radius: 3px;
    margin-top: 4px;
}
.upload-file-item .progress-bar {
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: width .3s;
}
.upload-file-item .status-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}
.upload-file-item .status-icon.success { color: #28a745; }
.upload-file-item .status-icon.error { color: #dc3545; }

/* ── Media Grid ─────────────────────────────────────────── */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}
.media-card {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    transition: all .3s;
    cursor: pointer;
    border: 2px solid transparent;
}
.media-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,.1);
}
.media-card.selected {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,.2);
}
.media-card .media-thumb {
    width: 100%;
    aspect-ratio: 1;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.media-card .media-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .3s;
}
.media-card:hover .media-thumb img {
    transform: scale(1.05);
}
.media-card .media-thumb .video-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.35);
}
.media-card .media-thumb .video-overlay i {
    font-size: 2.5rem;
    color: #fff;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,.3));
}
.media-card .media-thumb .type-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.media-card .media-thumb .type-badge.image { background: rgba(102,126,234,.85); color: #fff; }
.media-card .media-thumb .type-badge.video { background: rgba(220,53,69,.85); color: #fff; }
.media-card .media-info {
    padding: 10px 12px;
}
.media-card .media-info .media-name {
    font-size: 0.78rem;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.media-card .media-info .media-meta {
    font-size: 0.7rem;
    color: #999;
    margin-top: 2px;
}
.media-card .select-check {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255,255,255,.9);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all .2s;
    z-index: 5;
    cursor: pointer;
}
.media-card:hover .select-check,
.media-card.selected .select-check {
    opacity: 1;
}
.media-card.selected .select-check {
    background: #667eea;
    color: #fff;
    transform: scale(1.1);
}
.select-check:hover {
    transform: scale(1.15);
}

/* ── Bulk Actions Floating Bar ────────────────────────────── */
.bulk-actions-bar {
    position: fixed;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%) translateY(120px);
    background: #1e293b;
    color: #fff;
    padding: 12px 24px;
    border-radius: 50px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.35);
    z-index: 1040;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s;
    opacity: 0;
    pointer-events: none;
}
.bulk-actions-bar.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.bulk-actions-bar .btn-bulk-delete {
    background: linear-gradient(135deg, #ff4d4d, #dc3545);
    color: #fff;
    border: none;
    border-radius: 30px;
    padding: 8px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(220,53,69,0.35);
}
.bulk-actions-bar .btn-bulk-delete:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(220,53,69,0.5);
}
.bulk-actions-bar .btn-bulk-cancel {
    background: rgba(255,255,255,0.1);
    color: #cbd5e1;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 30px;
    padding: 8px 16px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.bulk-actions-bar .btn-bulk-cancel:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

/* ── Detail Sidebar ─────────────────────────────────────── */
.media-detail-panel {
    position: fixed;
    top: 0;
    right: -450px;
    width: 420px;
    height: 100vh;
    background: #fff;
    box-shadow: -4px 0 24px rgba(0,0,0,.1);
    z-index: 1050;
    transition: right .35s cubic-bezier(.4,0,.2,1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.media-detail-panel.open {
    right: 0;
}
.media-detail-panel .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}
.media-detail-panel .panel-header h6 {
    margin: 0;
    font-weight: 700;
    color: #333;
}
.media-detail-panel .panel-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: #f0f0f0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s;
}
.media-detail-panel .panel-close:hover {
    background: #e0e0e0;
}
.media-detail-panel .panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}
.media-detail-panel .panel-preview {
    width: 100%;
    border-radius: 12px;
    overflow: hidden;
    background: #f5f5f5;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
}
.media-detail-panel .panel-preview img,
.media-detail-panel .panel-preview video {
    width: 100%;
    max-height: 300px;
    object-fit: contain;
}
.media-detail-panel .detail-row {
    margin-bottom: 16px;
}
.media-detail-panel .detail-row label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 4px;
    display: block;
}
.media-detail-panel .detail-row .value {
    font-size: 0.85rem;
    color: #333;
    word-break: break-all;
}
.media-detail-panel .detail-row input,
.media-detail-panel .detail-row textarea {
    width: 100%;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.85rem;
    transition: border .2s;
}
.media-detail-panel .detail-row input:focus,
.media-detail-panel .detail-row textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,.1);
}
.media-detail-panel .url-copy-group {
    display: flex;
    gap: 6px;
}
.media-detail-panel .url-copy-group input {
    flex: 1;
    font-size: 0.75rem;
    background: #f8f9fa;
}
.media-detail-panel .url-copy-group button {
    flex-shrink: 0;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #fff;
    cursor: pointer;
    transition: all .2s;
}
.media-detail-panel .url-copy-group button:hover {
    background: #667eea;
    border-color: #667eea;
    color: #fff;
}
.media-detail-panel .panel-actions {
    padding: 16px 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}
.media-detail-panel .panel-actions .btn-save {
    flex: 1;
    padding: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
}
.media-detail-panel .panel-actions .btn-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102,126,234,.3);
}
.media-detail-panel .panel-actions .btn-delete {
    padding: 10px 16px;
    background: #fff0f0;
    color: #dc3545;
    border: 1px solid #fcc;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.media-detail-panel .panel-actions .btn-delete:hover {
    background: #dc3545;
    color: #fff;
    border-color: #dc3545;
}

/* Backdrop */
.media-panel-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.25);
    z-index: 1049;
    display: none;
}
.media-panel-backdrop.show {
    display: block;
}

/* ── Empty State ────────────────────────────────────────── */
.media-empty {
    text-align: center;
    padding: 60px 20px;
}
.media-empty i {
    font-size: 4rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 16px;
}
.media-empty h5 {
    font-weight: 700;
    color: #333;
}
.media-empty p {
    color: #888;
    font-size: 0.9rem;
}

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }
    .media-detail-panel {
        width: 100%;
        right: -100%;
    }
    .media-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .media-search {
        max-width: 100%;
    }
}
</style>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PAGE HEADER                                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h3 mb-1 fw-bold" style="color:#333;">
            <i class="fas fa-photo-video me-2" style="background: linear-gradient(135deg,#667eea,#764ba2); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"></i>
            Media Library
        </h2>
        <p class="text-muted mb-0" style="font-size:0.85rem;">Upload, manage, and organize your images & videos — WordPress style.</p>
    </div>
    <button class="btn btn-primary btn-lg px-4" style="background:linear-gradient(135deg,#667eea,#764ba2); border:none; border-radius:10px; font-weight:600;" onclick="document.getElementById('mediaFileInput').click()">
        <i class="fas fa-cloud-upload-alt me-2"></i>Upload New
    </button>
</div>

<?php render_flash(); ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  DROP ZONE                                                -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="media-dropzone" id="mediaDropzone">
    <div class="dropzone-icon"><i class="fas fa-cloud-upload-alt"></i></div>
    <h5>Drop files to upload</h5>
    <p>or click the button below to browse</p>
    <button type="button" class="browse-btn" onclick="document.getElementById('mediaFileInput').click()">
        <i class="fas fa-folder-open me-2"></i>Browse Files
    </button>
    <input type="file" id="mediaFileInput" multiple
           accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,video/mp4,video/webm,video/ogg,video/quicktime">
    <p class="mt-2" style="font-size:.75rem; color:#aaa;">
        Allowed: JPG, PNG, GIF, WebP, SVG, MP4, WebM, OGG &nbsp;|&nbsp; Max per file: 50 MB
    </p>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  UPLOAD PROGRESS                                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="upload-progress-container" id="uploadProgress"></div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  TOOLBAR: FILTER + SEARCH                                 -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="media-toolbar">
    <div class="filter-tabs d-flex align-items-center flex-wrap gap-2">
        <a href="manage_media.php?type=all" class="<?php echo $filter_type === 'all' ? 'active' : ''; ?>">
            All <span class="badge"><?php echo $total_all; ?></span>
        </a>
        <a href="manage_media.php?type=image" class="<?php echo $filter_type === 'image' ? 'active' : ''; ?>">
            <i class="fas fa-image me-1"></i>Images <span class="badge"><?php echo $total_images; ?></span>
        </a>
        <a href="manage_media.php?type=video" class="<?php echo $filter_type === 'video' ? 'active' : ''; ?>">
            <i class="fas fa-video me-1"></i>Videos <span class="badge"><?php echo $total_videos; ?></span>
        </a>
        <form method="POST" class="m-0 p-0" onsubmit="return confirm('Sync all existing images from the assets/images folder into the Media Library?');">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="media_action" value="sync_assets">
            <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill"><i class="fas fa-sync-alt me-1"></i>Fetched Images</button>
        </form>
        <?php if ($total_all > 0): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill ms-1" id="btnSelectAll" onclick="toggleSelectAll()">
            <i class="fas fa-check-square me-1"></i>Select All
        </button>
        <?php endif; ?>
    </div>
    <div class="media-search ms-auto">
        <form method="GET" action="manage_media.php">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
            <input type="text" name="search" placeholder="Search files…" value="<?php echo htmlspecialchars($search_q); ?>">
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MEDIA GRID                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($media_items && $media_items->num_rows > 0): ?>
<div class="media-grid" id="mediaGrid">
    <?php while ($m = $media_items->fetch_assoc()):
        $is_video = ($m['file_type'] === 'video');
        $filesize_kb = round($m['file_size'] / 1024);
        $filesize_display = $filesize_kb >= 1024 ? round($filesize_kb / 1024, 1) . ' MB' : $filesize_kb . ' KB';
        $dims = ($m['width'] && $m['height']) ? $m['width'] . '×' . $m['height'] : '';
    ?>
    <?php 
        $display_url = (strpos($m['file_url'], 'http') === 0) ? $m['file_url'] : '../' . $m['file_url'];
        $absolute_url = (strpos($m['file_url'], 'http') === 0) ? $m['file_url'] : SITE_URL . '/' . $m['file_url'];
    ?>
    <div class="media-card"
         data-id="<?php echo $m['id']; ?>"
         data-filename="<?php echo htmlspecialchars($m['original_name']); ?>"
         data-filetype="<?php echo $m['file_type']; ?>"
         data-mimetype="<?php echo htmlspecialchars($m['mime_type']); ?>"
         data-filesize="<?php echo $filesize_display; ?>"
         data-dims="<?php echo $dims; ?>"
         data-url="<?php echo htmlspecialchars($m['file_url']); ?>"
         data-full-url="<?php echo htmlspecialchars($absolute_url); ?>"
         data-alt="<?php echo htmlspecialchars($m['alt_text']); ?>"
         data-caption="<?php echo htmlspecialchars($m['caption'] ?? ''); ?>"
         data-date="<?php echo date('M d, Y h:i A', strtotime($m['created_at'])); ?>"
         onclick="handleCardClick(event, this)">
        <div class="media-thumb">
            <?php if ($is_video): ?>
                <video preload="metadata" muted>
                    <source src="<?php echo $display_url; ?>" type="<?php echo htmlspecialchars($m['mime_type']); ?>">
                </video>
                <div class="video-overlay"><i class="fas fa-play-circle"></i></div>
                <span class="type-badge video">Video</span>
            <?php else: ?>
                <img src="<?php echo $display_url; ?>" alt="<?php echo htmlspecialchars($m['alt_text']); ?>" loading="lazy" onerror="this.onerror=null; this.src='../assets/images/placeholder.svg';">
                <span class="type-badge image">Image</span>
            <?php endif; ?>
        </div>
        <div class="media-info">
            <div class="media-name"><?php echo htmlspecialchars($m['original_name']); ?></div>
            <div class="media-meta">
                <?php echo $filesize_display; ?>
                <?php if ($dims): ?> &middot; <?php echo $dims; ?><?php endif; ?>
            </div>
        </div>
        <div class="select-check" title="Select item" onclick="toggleSelectMedia(event, this)">
            <i class="fas fa-check" style="font-size:.75rem;"></i>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
<div class="media-empty">
    <i class="fas fa-photo-video d-block"></i>
    <h5>No media files found</h5>
    <p>Upload your first image or video using the drop zone above.</p>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════ -->
<!--  DETAIL PANEL (Slides in from right)                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="media-panel-backdrop" id="mediaPanelBackdrop" onclick="closeMediaDetail()"></div>
<div class="media-detail-panel" id="mediaDetailPanel">
    <div class="panel-header">
        <h6><i class="fas fa-info-circle me-2"></i>Media Details</h6>
        <button class="panel-close" onclick="closeMediaDetail()"><i class="fas fa-times"></i></button>
    </div>
    <div class="panel-body">
        <div class="panel-preview" id="panelPreview"></div>

        <div class="detail-row">
            <label>File Name</label>
            <div class="value" id="detailFilename">—</div>
        </div>
        <div class="detail-row">
            <label>Type</label>
            <div class="value" id="detailType">—</div>
        </div>
        <div class="detail-row">
            <label>Size</label>
            <div class="value" id="detailSize">—</div>
        </div>
        <div class="detail-row" id="detailDimsRow">
            <label>Dimensions</label>
            <div class="value" id="detailDims">—</div>
        </div>
        <div class="detail-row">
            <label>Uploaded</label>
            <div class="value" id="detailDate">—</div>
        </div>

        <hr style="border-color:#eee;">

        <form id="mediaMetaForm" method="POST" action="manage_media.php">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="media_action" value="update_meta">
            <input type="hidden" name="media_id" id="detailId" value="">

            <div class="detail-row">
                <label>Alt Text</label>
                <input type="text" name="alt_text" id="detailAlt" placeholder="Describe this media…">
            </div>
            <div class="detail-row">
                <label>Caption</label>
                <textarea name="caption" id="detailCaption" rows="3" placeholder="Optional caption…"></textarea>
            </div>
        </form>

        <div class="detail-row">
            <label>File URL</label>
            <div class="url-copy-group">
                <input type="text" id="detailUrl" readonly>
                <button onclick="copyMediaUrl()" title="Copy URL"><i class="fas fa-copy"></i></button>
            </div>
        </div>
    </div>
    <div class="panel-actions">
        <button class="btn-save" onclick="document.getElementById('mediaMetaForm').submit()">
            <i class="fas fa-save me-1"></i>Save Changes
        </button>
        <button class="btn-delete" id="btnDeleteMedia" onclick="deleteMedia()">
            <i class="fas fa-trash-alt me-1"></i>Delete
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  BULK ACTIONS FLOATING BAR                                -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-check-circle text-primary" style="font-size:1.2rem;"></i>
        <span id="selectedCountText" style="font-weight:600; font-size:0.9rem;">0 file(s) selected</span>
    </div>
    <div class="d-flex align-items-center gap-2 ms-3">
        <button type="button" class="btn-bulk-cancel" onclick="clearSelection()">Cancel</button>
        <button type="button" class="btn-bulk-delete" onclick="deleteSelectedMedia()">
            <i class="fas fa-trash-alt me-1"></i>Delete Selected (<span id="selectedCountBadge">0</span>)
        </button>
    </div>
</div>

<!-- Hidden delete form -->
<form id="mediaDeleteForm" method="POST" action="manage_media.php" style="display:none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="media_action" value="delete">
    <input type="hidden" name="media_id" id="deleteMediaId" value="">
</form>

<!-- Hidden bulk delete form -->
<form id="bulkDeleteForm" method="POST" action="manage_media.php" style="display:none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="media_action" value="bulk_delete">
    <input type="hidden" name="media_ids" id="bulkDeleteIdsInput" value="">
</form>

<script>
// ═══════════════════════════════════════════════════════════
//  MULTI-SELECT & BULK DELETE
// ═══════════════════════════════════════════════════════════
let selectedMediaIds = new Set();

function toggleSelectMedia(event, element) {
    if (event) event.stopPropagation();
    const card = element.closest('.media-card');
    const mediaId = card.dataset.id;

    if (selectedMediaIds.has(mediaId)) {
        selectedMediaIds.delete(mediaId);
        card.classList.remove('selected');
    } else {
        selectedMediaIds.add(mediaId);
        card.classList.add('selected');
    }

    updateBulkActionBar();
}

function handleCardClick(event, card) {
    if (selectedMediaIds.size > 0) {
        const selectCheck = card.querySelector('.select-check');
        toggleSelectMedia(event, selectCheck);
    } else {
        openMediaDetail(card);
    }
}

function updateBulkActionBar() {
    const bar = document.getElementById('bulkActionsBar');
    const countText = document.getElementById('selectedCountText');
    const countBadge = document.getElementById('selectedCountBadge');
    const selectAllBtn = document.getElementById('btnSelectAll');
    const totalCards = document.querySelectorAll('.media-card').length;

    if (selectedMediaIds.size > 0) {
        bar.classList.add('show');
        if (countText) countText.textContent = `${selectedMediaIds.size} file(s) selected`;
        if (countBadge) countBadge.textContent = selectedMediaIds.size;
        
        if (selectAllBtn) {
            if (selectedMediaIds.size === totalCards) {
                selectAllBtn.innerHTML = '<i class="fas fa-minus-square me-1"></i>Deselect All';
            } else {
                selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i>Select All';
            }
        }
    } else {
        bar.classList.remove('show');
        if (selectAllBtn) {
            selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i>Select All';
        }
    }
}

function toggleSelectAll() {
    const allCards = document.querySelectorAll('.media-card');
    if (selectedMediaIds.size === allCards.length && allCards.length > 0) {
        clearSelection();
    } else {
        allCards.forEach(card => {
            const id = card.dataset.id;
            selectedMediaIds.add(id);
            card.classList.add('selected');
        });
        updateBulkActionBar();
    }
}

function clearSelection() {
    selectedMediaIds.clear();
    document.querySelectorAll('.media-card.selected').forEach(card => {
        card.classList.remove('selected');
    });
    updateBulkActionBar();
}

function deleteSelectedMedia() {
    if (selectedMediaIds.size === 0) return;
    
    const count = selectedMediaIds.size;
    if (confirm(`Are you sure you want to permanently delete ${count} selected media file(s)? This action cannot be undone.`)) {
        document.getElementById('bulkDeleteIdsInput').value = Array.from(selectedMediaIds).join(',');
        document.getElementById('bulkDeleteForm').submit();
    }
}

// ═══════════════════════════════════════════════════════════
//  DRAG & DROP + FILE UPLOAD
// ═══════════════════════════════════════════════════════════
const dropzone = document.getElementById('mediaDropzone');
const fileInput = document.getElementById('mediaFileInput');
const progressContainer = document.getElementById('uploadProgress');

const ALLOWED_TYPES = [
    'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
    'video/mp4','video/webm','video/ogg','video/quicktime'
];
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

['dragenter','dragover'].forEach(ev => {
    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragover'); });
});
['dragleave','drop'].forEach(ev => {
    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragover'); });
});
dropzone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length) handleFiles(files);
});
fileInput.addEventListener('change', () => {
    if (fileInput.files.length) handleFiles(fileInput.files);
    fileInput.value = ''; // reset
});

function handleFiles(files) {
    progressContainer.classList.add('active');
    Array.from(files).forEach(file => uploadFile(file));
}

function uploadFile(file) {
    // Validate type
    if (!ALLOWED_TYPES.includes(file.type)) {
        addProgressItem(file, 'error', 'Unsupported file type');
        return;
    }
    // Validate size
    if (file.size > MAX_FILE_SIZE) {
        addProgressItem(file, 'error', 'File exceeds 50 MB limit');
        return;
    }

    const item = addProgressItem(file, 'uploading');

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_csrf_token', '<?php echo csrf_token(); ?>');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_media_upload.php', true);

    xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            item.querySelector('.progress-bar').style.width = pct + '%';
        }
    });

    xhr.addEventListener('load', () => {
        try {
            const resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                updateProgressItem(item, 'success');
                // Reload after short delay for multiple uploads
                clearTimeout(window._reloadTimer);
                window._reloadTimer = setTimeout(() => location.reload(), 1200);
            } else {
                updateProgressItem(item, 'error', resp.message || 'Upload failed');
            }
        } catch(e) {
            updateProgressItem(item, 'error', 'Server error');
        }
    });

    xhr.addEventListener('error', () => {
        updateProgressItem(item, 'error', 'Network error');
    });

    xhr.send(formData);
}

function addProgressItem(file, status, errMsg) {
    const div = document.createElement('div');
    div.className = 'upload-file-item';
    
    const isImg = file.type.startsWith('image/');
    let thumbHTML = `<div class="file-thumb"><i class="fas ${isImg ? 'fa-image' : 'fa-video'}"></i></div>`;
    if (isImg) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = div.querySelector('.file-thumb');
            img.innerHTML = `<img src="${e.target.result}" alt="">`;
        };
        reader.readAsDataURL(file);
    }

    const sizeStr = file.size >= 1048576 
        ? (file.size / 1048576).toFixed(1) + ' MB' 
        : Math.round(file.size / 1024) + ' KB';

    div.innerHTML = `
        ${thumbHTML}
        <div class="file-info">
            <div class="name">${file.name}</div>
            <div class="size">${sizeStr}</div>
            ${status === 'uploading' ? '<div class="progress"><div class="progress-bar" style="width:0%"></div></div>' : ''}
            ${status === 'error' ? `<div class="size text-danger"><i class="fas fa-exclamation-circle me-1"></i>${errMsg}</div>` : ''}
        </div>
        <div class="status-icon ${status === 'error' ? 'error' : ''}">
            ${status === 'uploading' ? '<i class="fas fa-spinner fa-spin" style="color:#667eea;"></i>' : ''}
            ${status === 'error' ? '<i class="fas fa-times-circle"></i>' : ''}
        </div>
    `;

    progressContainer.prepend(div);
    return div;
}

function updateProgressItem(item, status, errMsg) {
    const icon = item.querySelector('.status-icon');
    if (status === 'success') {
        icon.className = 'status-icon success';
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        const progress = item.querySelector('.progress');
        if (progress) progress.remove();
    } else {
        icon.className = 'status-icon error';
        icon.innerHTML = '<i class="fas fa-times-circle"></i>';
        const info = item.querySelector('.file-info');
        const progress = item.querySelector('.progress');
        if (progress) progress.remove();
        const errDiv = document.createElement('div');
        errDiv.className = 'size text-danger';
        errDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${errMsg || 'Failed'}`;
        info.appendChild(errDiv);
    }
}

// ═══════════════════════════════════════════════════════════
//  DETAIL PANEL
// ═══════════════════════════════════════════════════════════
function openMediaDetail(card) {
    // Remove multi-selection when opening detail panel
    clearSelection();
    card.classList.add('selected');

    const d = card.dataset;
    document.getElementById('detailId').value = d.id;
    document.getElementById('detailFilename').textContent = d.filename;
    document.getElementById('detailType').textContent = d.mimetype;
    document.getElementById('detailSize').textContent = d.filesize;
    document.getElementById('detailDate').textContent = d.date;
    document.getElementById('detailAlt').value = d.alt || '';
    document.getElementById('detailCaption').value = d.caption || '';
    document.getElementById('detailUrl').value = d.fullUrl;
    document.getElementById('deleteMediaId').value = d.id;

    // Dimensions
    if (d.dims) {
        document.getElementById('detailDims').textContent = d.dims;
        document.getElementById('detailDimsRow').style.display = '';
    } else {
        document.getElementById('detailDimsRow').style.display = 'none';
    }

    // Preview
    const preview = document.getElementById('panelPreview');
    if (d.filetype === 'video') {
        preview.innerHTML = `<video controls style="width:100%; max-height:300px;"><source src="${d.url}" type="${d.mimetype}">Your browser does not support this video.</video>`;
    } else {
        preview.innerHTML = `<img src="${d.url}" alt="${d.alt || ''}" style="max-height:300px;">`;
    }

    document.getElementById('mediaDetailPanel').classList.add('open');
    document.getElementById('mediaPanelBackdrop').classList.add('show');
}

function closeMediaDetail() {
    document.getElementById('mediaDetailPanel').classList.remove('open');
    document.getElementById('mediaPanelBackdrop').classList.remove('show');
    document.querySelectorAll('.media-card.selected').forEach(c => c.classList.remove('selected'));
}

function copyMediaUrl() {
    const input = document.getElementById('detailUrl');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 1500);
    });
}

function deleteMedia() {
    if (confirm('Are you sure you want to permanently delete this file? This action cannot be undone.')) {
        document.getElementById('mediaDeleteForm').submit();
    }
}

// Close panel on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeMediaDetail();
});
</script>

<?php include 'admin_footer.php'; ?>
