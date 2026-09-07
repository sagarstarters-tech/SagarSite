<?php
include 'admin_header.php';

// Safe document image upload helper
function handle_legal_doc_upload($file, &$err = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $err = 'Please select a valid image file.';
        return false;
    }
    
    // Size check (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        $err = 'File size exceeds maximum limit of 10MB.';
        return false;
    }
    
    // Extension whitelist
    $orig_name = basename($file['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed_exts, true)) {
        $err = 'Invalid extension. Only JPG, JPEG, PNG, and WEBP image formats are permitted.';
        return false;
    }
    
    // MIME check via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($real_mime, $allowed_mimes, true)) {
        $err = 'Invalid file MIME type. Only genuine image files are allowed.';
        return false;
    }
    
    // Verify image data integrity with getimagesize
    $img_info = @getimagesize($file['tmp_name']);
    if (!$img_info || empty($img_info[0]) || empty($img_info[1])) {
        $err = 'Corrupted or unreadable image file.';
        return false;
    }
    
    // Generate cryptographically unique safe filename
    $target_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $unique_name = 'legal_doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest_path = $target_dir . $unique_name;
    
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        $err = 'Failed to save uploaded document to server directory.';
        return false;
    }
    
    return [
        'filename'  => $unique_name,
        'rel_path'  => 'uploads/documents/' . $unique_name,
        'mime'      => $real_mime,
        'size'      => filesize($dest_path),
        'width'     => $img_info[0],
        'height'    => $img_info[1],
        'orig_name' => $orig_name
    ];
}

// Safe document file deletion helper
function safe_delete_doc_file($rel_path) {
    if (empty($rel_path)) return;
    $base_dir = realpath(__DIR__ . '/../uploads/documents');
    $full = realpath(__DIR__ . '/../' . ltrim($rel_path, '/'));
    if ($full && $base_dir && strpos($full, $base_dir) === 0 && file_exists($full)) {
        @unlink($full);
    }
}

// Ensure legal documents table exists
$conn->query("CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `doc_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Keys for About Us customization
$about_keys = [
    'about_hero_title'        => 'About Us',
    'about_hero_subtitle'     => 'Learn more about our journey and values.',
    'about_who_title'         => 'Who We Are',
    'about_who_desc1'         => 'Welcome to Sagar Starter\'s. We are dedicated to providing you the very best of products, with an emphasis on quality, customer service, and uniqueness.',
    'about_who_desc2'         => 'Founded with a passion for modern aesthetics and functional design, we have come a long way from our beginnings.',
    'about_who_image'         => 'about_who.jpg',
    'about_f_icon1'           => 'fas fa-truck',
    'about_f_title1'          => 'Fast Delivery',
    'about_f_desc1'           => 'We ensure your packages arrive on time, every time safely to your doorstep.',
    'about_f_icon2'           => 'fas fa-hand-holding-heart',
    'about_f_title2'          => 'Quality Promise',
    'about_f_desc2'           => 'Every item is carefully inspected to meet our strict quality and design standards.',
    'about_f_icon3'           => 'fas fa-headset',
    'about_f_title3'          => '24/7 Support',
    'about_f_desc3'           => 'Our dedicated customer service team is always here to help you when needed.',

    // Legal Documents Section Keys
    'about_docs_enabled'      => '1',
    'about_docs_title'        => 'Government Certifications & Legal Documents',
    'about_docs_subtitle'     => 'Official compliance, registration certificates, and quality standards of Sagar Starters.',

    // Colours & Typography
    'about_heading_color'     => '#0d6efd',
    'about_heading_font_size' => '32',
    'about_body_font_size'    => '16',
    'about_icon_color'        => '#0d6efd',
    'about_card_bg'           => '#ffffff',
];

$success = null;
$error_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    // ── 1. Action: Add Document ──────────────────────────────────
    if ($action === 'add_document') {
        csrf_verify();
        $title       = trim($_POST['doc_title'] ?? '');
        $doc_number  = trim($_POST['doc_number'] ?? '');
        $description = trim($_POST['doc_description'] ?? '');
        $status      = isset($_POST['doc_status']) ? 1 : 0;
        $sort_order  = intval($_POST['doc_sort_order'] ?? 0);

        if (empty($title)) {
            $error_msg = "Please provide a document title (e.g., GST Registration Certificate).";
        } elseif (empty($_FILES['doc_image']['name'])) {
            $error_msg = "Please upload an image for the legal document.";
        } else {
            $upload_err = '';
            $upload_res = handle_legal_doc_upload($_FILES['doc_image'], $upload_err);
            if (!$upload_res) {
                $error_msg = $upload_err;
            } else {
                $stmt = $conn->prepare("INSERT INTO documents (title, doc_number, description, image, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssii', $title, $doc_number, $description, $upload_res['rel_path'], $status, $sort_order);
                if ($stmt->execute()) {
                    $success = "Legal document \"{$title}\" uploaded safely and added successfully.";

                    // Sync to media_library DB table for tracking
                    $m_check = $conn->query("SELECT id FROM media_library WHERE file_url = '" . $conn->real_escape_string($upload_res['rel_path']) . "'");
                    if (!$m_check || $m_check->num_rows == 0) {
                        $m_stmt = $conn->prepare("INSERT INTO media_library (file_name, original_name, file_path, file_url, file_type, mime_type, file_size, width, height) VALUES (?, ?, ?, ?, 'image', ?, ?, ?, ?)");
                        if ($m_stmt) {
                            $m_stmt->bind_param('sssssiii', $upload_res['filename'], $upload_res['orig_name'], $upload_res['rel_path'], $upload_res['rel_path'], $upload_res['mime'], $upload_res['size'], $upload_res['width'], $upload_res['height']);
                            $m_stmt->execute();
                            $m_stmt->close();
                        }
                    }
                } else {
                    $error_msg = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }

    // ── 2. Action: Edit Document ─────────────────────────────────
    elseif ($action === 'edit_document') {
        csrf_verify();
        $doc_id      = intval($_POST['doc_id'] ?? 0);
        $title       = trim($_POST['doc_title'] ?? '');
        $doc_number  = trim($_POST['doc_number'] ?? '');
        $description = trim($_POST['doc_description'] ?? '');
        $status      = isset($_POST['doc_status']) ? 1 : 0;
        $sort_order  = intval($_POST['doc_sort_order'] ?? 0);

        if ($doc_id <= 0 || empty($title)) {
            $error_msg = "Invalid document data. Title is required.";
        } else {
            // Check if existing document exists
            $existing_q = $conn->query("SELECT image FROM documents WHERE id = {$doc_id}");
            if ($existing_q && $existing_row = $existing_q->fetch_assoc()) {
                $current_img = $existing_row['image'];

                if (!empty($_FILES['doc_image']['name'])) {
                    // New image uploaded
                    $upload_err = '';
                    $upload_res = handle_legal_doc_upload($_FILES['doc_image'], $upload_err);
                    if (!$upload_res) {
                        $error_msg = $upload_err;
                    } else {
                        // Delete old file safely
                        safe_delete_doc_file($current_img);

                        $stmt = $conn->prepare("UPDATE documents SET title=?, doc_number=?, description=?, image=?, status=?, sort_order=? WHERE id=?");
                        $stmt->bind_param('ssssiii', $title, $doc_number, $description, $upload_res['rel_path'], $status, $sort_order, $doc_id);
                        $stmt->execute();
                        $stmt->close();
                        $success = "Document updated successfully with new certificate image.";
                    }
                } else {
                    // Update text fields only
                    $stmt = $conn->prepare("UPDATE documents SET title=?, doc_number=?, description=?, status=?, sort_order=? WHERE id=?");
                    $stmt->bind_param('sssiii', $title, $doc_number, $description, $status, $sort_order, $doc_id);
                    $stmt->execute();
                    $stmt->close();
                    $success = "Document updated successfully.";
                }
            } else {
                $error_msg = "Document not found.";
            }
        }
    }

    // ── 3. Action: Delete Document ───────────────────────────────
    elseif ($action === 'delete_document') {
        csrf_verify();
        $doc_id = intval($_POST['doc_id'] ?? 0);
        if ($doc_id > 0) {
            $existing_q = $conn->query("SELECT image, title FROM documents WHERE id = {$doc_id}");
            if ($existing_q && $row = $existing_q->fetch_assoc()) {
                safe_delete_doc_file($row['image']);
                $del_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
                $del_stmt->bind_param('i', $doc_id);
                $del_stmt->execute();
                $del_stmt->close();
                $success = "Document \"{$row['title']}\" deleted successfully.";
            }
        }
    }

    // ── 4. Action: Toggle Status ─────────────────────────────────
    elseif ($action === 'toggle_doc_status') {
        csrf_verify();
        $doc_id = intval($_POST['doc_id'] ?? 0);
        if ($doc_id > 0) {
            $conn->query("UPDATE documents SET status = IF(status=1, 0, 1) WHERE id = {$doc_id}");
            $success = "Document status updated.";
        }
    }

    // ── 5. Action: Save General Settings ─────────────────────────
    elseif ($action === 'save_settings') {
        csrf_verify();

        // Handle checkbox for documents enabled
        $about_docs_enabled = isset($_POST['about_docs_enabled']) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('about_docs_enabled', '$about_docs_enabled') ON DUPLICATE KEY UPDATE setting_value='$about_docs_enabled'");

        foreach ($about_keys as $key => $default) {
            if ($key === 'about_docs_enabled') continue;

            if ($key === 'about_who_image') {
                $image_saved = false;
                if (isset($_FILES['about_who_image']) && $_FILES['about_who_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['about_who_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $filename = 'about_who_' . time() . '.' . $ext;
                        $upload_target = '../uploads/media/images/';
                        if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
                        if (move_uploaded_file($_FILES['about_who_image']['tmp_name'], $upload_target . $filename)) {
                            $full_path = 'uploads/media/images/' . $filename;
                            $safe_val = $conn->real_escape_string($full_path);
                            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$safe_val') ON DUPLICATE KEY UPDATE setting_value='$safe_val'");

                            $rel_path = $full_path;
                            $safe_rel = $conn->real_escape_string($rel_path);
                            $check = $conn->query("SELECT id FROM media_library WHERE file_url = '$safe_rel' OR file_name = '$filename'");
                            if (!$check || $check->num_rows == 0) {
                                $size = filesize($upload_target . $filename);
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $mime = $finfo->file($upload_target . $filename) ?: 'image/jpeg';
                                $info = @getimagesize($upload_target . $filename);
                                $w = $info ? $info[0] : null;
                                $h = $info ? $info[1] : null;
                                $m_stmt = $conn->prepare("INSERT INTO media_library (file_name, original_name, file_path, file_url, file_type, mime_type, file_size, width, height) VALUES (?, ?, ?, ?, 'image', ?, ?, ?, ?)");
                                $orig_name = $_FILES['about_who_image']['name'];
                                $m_stmt->bind_param('sssssiii', $filename, $orig_name, $rel_path, $rel_path, $mime, $size, $w, $h);
                                $m_stmt->execute();
                                $m_stmt->close();
                            }
                            $image_saved = true;
                        }
                    }
                }
                if (!$image_saved && !empty($_POST['about_who_image_path'])) {
                    $raw_path = trim($_POST['about_who_image_path']);
                    $clean_path = preg_replace('#^https?://[^/]+(/SagarSite)?/#i', '', $raw_path);
                    $safe_val = $conn->real_escape_string($clean_path);
                    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$safe_val') ON DUPLICATE KEY UPDATE setting_value='$safe_val'");
                }
                continue;
            }

            if (isset($_POST[$key])) {
                $value = $conn->real_escape_string($_POST[$key]);
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE setting_value='$value'");
            }
        }
        $success = "About Us page updated successfully.";
    }

    // Refresh global settings after updates
    $settings_query = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $settings_query->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$current_settings = $global_settings;

// Fetch all uploaded legal documents
$docs_list = [];
$docs_res = $conn->query("SELECT * FROM documents ORDER BY sort_order ASC, id ASC");
if ($docs_res) {
    while ($d = $docs_res->fetch_assoc()) {
        $docs_list[] = $d;
    }
}
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-info-circle"></i> Brand Identity
            </div>
            <h1 class="adm-hero-title">About Us Page Customizer</h1>
            <p class="adm-hero-subtitle">Customize story headlines, mission statements, corporate photography, legal documents, and value pillars.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="../about.php" target="_blank" class="adm-btn-white">
                <i class="fas fa-external-link-alt me-2"></i>View Live Page
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-check-circle fa-lg me-3"></i>
            <div><?php echo htmlspecialchars($success); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
            <div><?php echo htmlspecialchars($error_msg); ?></div>
        </div>
    <?php endif; ?>

    <!-- Main About Page Customizer Form -->
    <form method="POST" enctype="multipart/form-data" id="aboutSettingsForm">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="row">
            <!-- Hero Section -->
            <div class="col-md-12 mb-4">
                <div class="adm-card shadow-sm border-0 rounded-4">
                    <div class="p-3 border-bottom bg-light d-flex align-items-center">
                        <i class="fas fa-image me-2 text-primary"></i>
                        <h6 class="m-0 fw-bold text-dark">Hero Section</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Hero Title</label>
                                <input type="text" name="about_hero_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_hero_title'] ?? $about_keys['about_hero_title']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Hero Subtitle</label>
                                <input type="text" name="about_hero_subtitle" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_hero_subtitle'] ?? $about_keys['about_hero_subtitle']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Who We Are Section -->
            <div class="col-md-7 mb-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-info-circle me-2"></i>Who We Are Content</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Section Title</label>
                            <input type="text" name="about_who_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_who_title'] ?? $about_keys['about_who_title']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description Paragraph 1</label>
                            <textarea name="about_who_desc1" class="form-control" rows="4"><?php echo htmlspecialchars($current_settings['about_who_desc1'] ?? $about_keys['about_who_desc1']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description Paragraph 2</label>
                            <textarea name="about_who_desc2" class="form-control" rows="4"><?php echo htmlspecialchars($current_settings['about_who_desc2'] ?? $about_keys['about_who_desc2']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Who We Are Image -->
            <div class="col-md-5 mb-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-camera me-2"></i>Side Image</h6>
                    </div>
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <?php 
                            $who_img_val = $current_settings['about_who_image'] ?? $about_keys['about_who_image'];
                            $who_img_url = resolve_image_url($who_img_val);
                            ?>
                            <?php if (!empty($who_img_url) && strpos($who_img_url, 'placeholder') === false): ?>
                                <img src="<?php echo htmlspecialchars($who_img_url); ?>" class="img-fluid rounded-4 shadow-sm mb-3" style="max-height: 250px; object-fit: cover;" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                            <?php else: ?>
                                <div class="bg-light py-5 rounded-4 mb-3 border border-dashed">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="text-muted mt-2">No image uploaded</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block fw-bold">Upload New Image</label>
                            <input type="file" name="about_who_image" id="about_who_image" class="form-control" accept="image/*">
                            <input type="hidden" name="about_who_image_path" id="about_who_image_path" value="<?php echo htmlspecialchars($who_img_val); ?>">
                            <small class="text-muted">Recommended: 800x600px landscape image.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-star me-2"></i>Core Values / Features</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <!-- Feature 1 -->
                            <div class="col-md-4 border-end">
                                <h6 class="fw-bold text-muted mb-3">Feature 1</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Icon Class (FontAwesome)</label>
                                    <input type="text" name="about_f_icon1" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_icon1'] ?? $about_keys['about_f_icon1']); ?>">
                                    <small class="text-muted">e.g., fas fa-truck</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="about_f_title1" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_title1'] ?? $about_keys['about_f_title1']); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea name="about_f_desc1" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($current_settings['about_f_desc1'] ?? $about_keys['about_f_desc1']); ?></textarea>
                                </div>
                            </div>
                            <!-- Feature 2 -->
                            <div class="col-md-4 border-end">
                                <h6 class="fw-bold text-muted mb-3">Feature 2</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Icon Class (FontAwesome)</label>
                                    <input type="text" name="about_f_icon2" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_icon2'] ?? $about_keys['about_f_icon2']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="about_f_title2" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_title2'] ?? $about_keys['about_f_title2']); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea name="about_f_desc2" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($current_settings['about_f_desc2'] ?? $about_keys['about_f_desc2']); ?></textarea>
                                </div>
                            </div>
                            <!-- Feature 3 -->
                            <div class="col-md-4">
                                <h6 class="fw-bold text-muted mb-3">Feature 3</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Icon Class (FontAwesome)</label>
                                    <input type="text" name="about_f_icon3" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_icon3'] ?? $about_keys['about_f_icon3']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="about_f_title3" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_f_title3'] ?? $about_keys['about_f_title3']); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea name="about_f_desc3" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($current_settings['about_f_desc3'] ?? $about_keys['about_f_desc3']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Company Legal Documents & Certifications ────────── -->
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-primary-subtle text-primary rounded-3">
                                <i class="fas fa-file-contract"></i>
                            </span>
                            <div>
                                <h6 class="m-0 fw-bold text-dark">Company Legal Documents &amp; Certifications</h6>
                                <small class="text-muted">Safely manage GST, MSME, ISO, Trade License, and compliance documents.</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-mdb-toggle="modal" data-bs-toggle="modal" data-mdb-target="#addDocModal" data-bs-target="#addDocModal" onclick="showModalSafely('addDocModal')">
                            <i class="fas fa-plus me-1"></i> Upload New Document
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <!-- Section Display Controls -->
                        <div class="bg-light p-3 rounded-4 mb-4 border">
                            <div class="row align-items-center g-3">
                                <div class="col-lg-3">
                                    <div class="form-check form-switch ps-0">
                                        <div class="d-flex align-items-center gap-2">
                                            <input class="form-check-input ms-0" type="checkbox" role="switch" id="about_docs_enabled" name="about_docs_enabled" value="1" <?php echo ($current_settings['about_docs_enabled'] ?? '1') === '1' ? 'checked' : ''; ?> style="width: 2.4em; height: 1.2em; cursor: pointer;">
                                            <label class="form-check-label fw-bold mb-0 cursor-pointer" for="about_docs_enabled">
                                                Enable on About Us Page
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">Controls visibility on the customer-facing About page.</small>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label small fw-bold mb-1">Section Title</label>
                                    <input type="text" name="about_docs_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_docs_title'] ?? $about_keys['about_docs_title']); ?>">
                                </div>
                                <div class="col-lg-5">
                                    <label class="form-label small fw-bold mb-1">Section Subtitle</label>
                                    <input type="text" name="about_docs_subtitle" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_settings['about_docs_subtitle'] ?? $about_keys['about_docs_subtitle']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Documents List -->
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Preview</th>
                                        <th>Document Details</th>
                                        <th>Registration / Doc No.</th>
                                        <th style="width: 120px;" class="text-center">Status</th>
                                        <th style="width: 90px;" class="text-center">Order</th>
                                        <th style="width: 150px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($docs_list)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <div class="py-3">
                                                    <i class="fas fa-file-invoice fa-3x text-muted opacity-50 mb-3"></i>
                                                    <h6 class="fw-bold text-dark">No Legal Documents Uploaded Yet</h6>
                                                    <p class="small text-muted mb-3">Upload your GST certificate, MSME Udyam, ISO certification, or trade license to build high customer trust.</p>
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-mdb-toggle="modal" data-bs-toggle="modal" data-mdb-target="#addDocModal" data-bs-target="#addDocModal" onclick="showModalSafely('addDocModal')">
                                                        <i class="fas fa-upload me-1"></i> Upload First Document
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($docs_list as $doc): 
                                            $doc_img_url = resolve_image_url($doc['image']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="rounded border p-1 bg-white cursor-pointer" 
                                                     onclick="viewDoc(<?php echo htmlspecialchars(json_encode($doc)); ?>)"
                                                     title="Click to preview full certificate" 
                                                     style="width: 60px; height: 60px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                                    <img src="<?php echo htmlspecialchars($doc_img_url); ?>" alt="<?php echo htmlspecialchars($doc['title']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($doc['title']); ?></div>
                                                <?php if (!empty($doc['description'])): ?>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 320px;"><?php echo htmlspecialchars($doc['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($doc['doc_number'])): ?>
                                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                                        <i class="fas fa-hashtag text-primary me-1"></i><?php echo htmlspecialchars($doc['doc_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm rounded-pill px-2 py-1 <?php echo $doc['status'] ? 'btn-success-subtle text-success border border-success-subtle' : 'btn-secondary-subtle text-muted border'; ?>" 
                                                        onclick="toggleDocStatus(<?php echo $doc['id']; ?>)" 
                                                        title="Click to toggle active/hidden status">
                                                    <i class="fas fa-<?php echo $doc['status'] ? 'check-circle' : 'eye-slash'; ?> me-1"></i>
                                                    <?php echo $doc['status'] ? 'Active' : 'Hidden'; ?>
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-secondary border"><?php echo intval($doc['sort_order']); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="viewDoc(<?php echo htmlspecialchars(json_encode($doc)); ?>)" title="View Document">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="openEditDocModal(<?php echo htmlspecialchars(json_encode($doc)); ?>)" title="Edit Document">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteDoc(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>')" title="Delete Document">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colours & Typography -->
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-palette me-2"></i>Colours &amp; Typography</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Color Pickers row -->
                        <div class="row g-4 mb-4">
                            <!-- Heading Color -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold mb-1">Heading Colour</label>
                                <small class="d-block text-muted mb-2">&ldquo;Who We Are&rdquo; &amp; &ldquo;Legal Documents&rdquo; title color.</small>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color"
                                           id="about_heading_color"
                                           name="about_heading_color"
                                           value="<?php echo htmlspecialchars($current_settings['about_heading_color'] ?? $about_keys['about_heading_color']); ?>"
                                           style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;"
                                           class="about-color-picker">
                                    <input type="text"
                                           class="form-control form-control-sm about-hex-input"
                                           data-target="about_heading_color"
                                           value="<?php echo htmlspecialchars($current_settings['about_heading_color'] ?? $about_keys['about_heading_color']); ?>"
                                           maxlength="7" style="max-width:110px;font-family:monospace;">
                                </div>
                            </div>

                            <!-- Icon Color -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold mb-1">Icon Colour</label>
                                <small class="d-block text-muted mb-2">Color of feature icons and accents.</small>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color"
                                           id="about_icon_color"
                                           name="about_icon_color"
                                           value="<?php echo htmlspecialchars($current_settings['about_icon_color'] ?? $about_keys['about_icon_color']); ?>"
                                           style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;"
                                           class="about-color-picker">
                                    <input type="text"
                                           class="form-control form-control-sm about-hex-input"
                                           data-target="about_icon_color"
                                           value="<?php echo htmlspecialchars($current_settings['about_icon_color'] ?? $about_keys['about_icon_color']); ?>"
                                           maxlength="7" style="max-width:110px;font-family:monospace;">
                                </div>
                            </div>

                            <!-- Card BG Color -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold mb-1">Feature Card Background</label>
                                <small class="d-block text-muted mb-2">Background color for feature cards.</small>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color"
                                           id="about_card_bg"
                                           name="about_card_bg"
                                           value="<?php echo htmlspecialchars($current_settings['about_card_bg'] ?? $about_keys['about_card_bg']); ?>"
                                           style="width:48px;height:48px;border:3px solid #dee2e6;border-radius:10px;cursor:pointer;padding:2px;"
                                           class="about-color-picker">
                                    <input type="text"
                                           class="form-control form-control-sm about-hex-input"
                                           data-target="about_card_bg"
                                           value="<?php echo htmlspecialchars($current_settings['about_card_bg'] ?? $about_keys['about_card_bg']); ?>"
                                           maxlength="7" style="max-width:110px;font-family:monospace;">
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-text-height me-2"></i>Typography</h6>

                        <!-- Sliders row -->
                        <div class="row g-4">
                            <!-- Heading Size -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Section Heading Font Size
                                    <span class="badge bg-primary ms-2" id="aboutHeadingFsBadge"><?php echo intval($current_settings['about_heading_font_size'] ?? 32); ?>px</span>
                                </label>
                                <input type="range" class="form-range" name="about_heading_font_size" 
                                       min="16" max="64" step="1"
                                       value="<?php echo intval($current_settings['about_heading_font_size'] ?? 32); ?>"
                                       oninput="document.getElementById('aboutHeadingFsBadge').textContent=this.value+'px'">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>16px</span><span>32px</span><span>64px</span>
                                </div>
                            </div>

                            <!-- Body Size -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Body / Description Font Size
                                    <span class="badge bg-primary ms-2" id="aboutBodyFsBadge"><?php echo intval($current_settings['about_body_font_size'] ?? 16); ?>px</span>
                                </label>
                                <input type="range" class="form-range" name="about_body_font_size" 
                                       min="12" max="28" step="1"
                                       value="<?php echo intval($current_settings['about_body_font_size'] ?? 16); ?>"
                                       oninput="document.getElementById('aboutBodyFsBadge').textContent=this.value+'px'">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>12px</span><span>16px</span><span>28px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="col-12 text-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg btn-custom px-5 shadow">
                    <i class="fas fa-save me-2"></i>Save All Changes
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- MODALS (Placed outside the main form to avoid nested forms)        -->
<!-- ════════════════════════════════════════════════════════════════════ -->

<!-- 1. Add Legal Document Modal -->
<div class="modal fade" id="addDocModal" tabindex="-1" aria-labelledby="addDocModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" id="formAddDoc">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_document">

                <div class="modal-header bg-light py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 bg-primary text-white rounded-3">
                            <i class="fas fa-file-upload"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="addDocModalTitle">Upload Company Legal Document</h5>
                            <small class="text-muted">GST, MSME, ISO, Trade License, or Regulatory Approval</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('addDocModal')" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Document Title <span class="text-danger">*</span></label>
                            <input type="text" name="doc_title" class="form-control" placeholder="e.g. GST Registration Certificate" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Document / Registration No.</label>
                            <input type="text" name="doc_number" class="form-control font-monospace" placeholder="e.g. 09AABCU9603R1ZM">
                            <small class="text-muted">Optional: Displayed on the certificate badge.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Issuing Authority / Description</label>
                            <input type="text" name="doc_description" class="form-control" placeholder="e.g. Government of India - Ministry of Micro, Small and Medium Enterprises">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Document Image <span class="text-danger">*</span></label>
                            <div class="p-3 border border-2 border-dashed rounded-4 bg-light text-center" id="addDropZone">
                                <div id="addDocPreviewContainer" class="mb-2 d-none">
                                    <img id="addDocPreviewImg" src="" class="img-fluid rounded-3 border shadow-sm" style="max-height: 200px; object-fit: contain;">
                                </div>
                                <div id="addDocPlaceholder">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-2"></i>
                                    <p class="mb-1 fw-bold text-dark">Click or drag &amp; drop document image here</p>
                                    <small class="text-muted d-block">Supported Formats: JPG, JPEG, PNG, WEBP (Max 10MB)</small>
                                </div>
                                <input type="file" name="doc_image" id="addDocFileInput" class="form-control mt-3" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" name="doc_sort_order" class="form-control" value="0" min="0">
                            <small class="text-muted">Lower numbers appear first on the page.</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="doc_status" id="add_doc_status" value="1" checked style="width: 2.2em; height: 1.1em; cursor: pointer;">
                                <label class="form-check-label fw-bold ms-2 cursor-pointer" for="add_doc_status">Active (Show on Frontend)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('addDocModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="fas fa-upload me-1"></i> Upload Safely
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Legal Document Modal -->
<div class="modal fade" id="editDocModal" tabindex="-1" aria-labelledby="editDocModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" id="formEditDoc">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="edit_document">
                <input type="hidden" name="doc_id" id="edit_doc_id">

                <div class="modal-header bg-light py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 bg-primary text-white rounded-3">
                            <i class="fas fa-edit"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="editDocModalTitle">Edit Legal Document</h5>
                            <small class="text-muted">Update title, number, or replace certificate image</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('editDocModal')" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Document Title <span class="text-danger">*</span></label>
                            <input type="text" name="doc_title" id="edit_doc_title" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Document / Registration No.</label>
                            <input type="text" name="doc_number" id="edit_doc_number" class="form-control font-monospace">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Issuing Authority / Description</label>
                            <input type="text" name="doc_description" id="edit_doc_description" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Current / New Document Image</label>
                            <div class="p-3 border border-2 border-dashed rounded-4 bg-light text-center">
                                <div class="mb-3">
                                    <img id="editDocPreviewImg" src="" class="img-fluid rounded-3 border shadow-sm" style="max-height: 180px; object-fit: contain;">
                                </div>
                                <label class="form-label small text-muted d-block">Choose a new file below ONLY if you wish to replace the current image:</label>
                                <input type="file" name="doc_image" id="editDocFileInput" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <small class="text-muted">Allowed: JPG, JPEG, PNG, WEBP (Max 10MB)</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" name="doc_sort_order" id="edit_doc_sort_order" class="form-control" min="0">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="doc_status" id="edit_doc_status" value="1" style="width: 2.2em; height: 1.1em; cursor: pointer;">
                                <label class="form-check-label fw-bold ms-2 cursor-pointer" for="edit_doc_status">Active (Show on Frontend)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('editDocModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Preview Document Lightbox Modal -->
<div class="modal fade" id="viewDocModal" tabindex="-1" aria-labelledby="viewDocModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="viewDocModalTitle"></h5>
                    <div id="viewDocModalSub" class="small text-muted font-monospace mt-1"></div>
                </div>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('viewDocModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4 text-center bg-light">
                <div class="p-2 bg-white rounded-3 border shadow-sm d-inline-block w-100" style="max-height: 70vh; overflow: auto;">
                    <img src="" id="viewDocModalImg" class="img-fluid rounded" style="max-height: 65vh; object-fit: contain;">
                </div>
                <div id="viewDocModalDesc" class="mt-3 text-muted small text-start px-2"></div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-4 d-flex justify-content-between">
                <span class="small text-muted"><i class="fas fa-shield-alt text-success me-1"></i> Secured Document</span>
                <div class="d-flex gap-2">
                    <a href="#" id="viewDocModalFull" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="fas fa-external-link-alt me-1"></i> Open Full Image
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-mdb-dismiss="modal" data-bs-dismiss="modal" onclick="hideModalSafely('viewDocModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Action Forms for Toggle & Delete -->
<form method="POST" id="actionDocForm" style="display: none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" id="actionFormAction" value="">
    <input type="hidden" name="doc_id" id="actionFormDocId" value="">
</form>

<script>
// Universal Safe Modal Helpers
function showModalSafely(modalId) {
    var el = document.getElementById(modalId);
    if (!el) return;
    // Try MDB (the modal library loaded on this site)
    if (typeof mdb !== 'undefined' && mdb.Modal) {
        try {
            var inst = mdb.Modal.getInstance(el) || new mdb.Modal(el);
            inst.show();
            return;
        } catch(e) {}
    }
    // Pure vanilla fallback — no external library dependency
    el.classList.add('show');
    el.style.display = 'block';
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('role', 'dialog');
    var bd = document.getElementById(modalId + '_bd');
    if (!bd) {
        bd = document.createElement('div');
        bd.className = 'modal-backdrop fade show';
        bd.id = modalId + '_bd';
        bd.onclick = function() { hideModalSafely(modalId); };
        document.body.appendChild(bd);
    }
    document.body.classList.add('modal-open');
}

function hideModalSafely(modalId) {
    var el = document.getElementById(modalId);
    if (!el) return;
    // Try MDB first
    if (typeof mdb !== 'undefined' && mdb.Modal) {
        try {
            var inst = mdb.Modal.getInstance(el);
            if (inst) { inst.hide(); return; }
        } catch(e) {}
    }
    // Pure vanilla fallback
    el.classList.remove('show');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    el.removeAttribute('aria-modal');
    var bd = document.getElementById(modalId + '_bd');
    if (bd) bd.remove();
    document.body.classList.remove('modal-open');
}

// Live Image Preview for Add Document Modal
document.getElementById('addDocFileInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(evt) {
            document.getElementById('addDocPreviewImg').src = evt.target.result;
            document.getElementById('addDocPreviewContainer').classList.remove('d-none');
            document.getElementById('addDocPlaceholder').classList.add('d-none');
        };
        reader.readAsDataURL(file);
    }
});

// Live Image Preview for Edit Document Modal
document.getElementById('editDocFileInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(evt) {
            document.getElementById('editDocPreviewImg').src = evt.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Populate Edit Modal
function openEditDocModal(doc) {
    document.getElementById('edit_doc_id').value = doc.id;
    document.getElementById('edit_doc_title').value = doc.title || '';
    document.getElementById('edit_doc_number').value = doc.doc_number || '';
    document.getElementById('edit_doc_description').value = doc.description || '';
    document.getElementById('edit_doc_sort_order').value = doc.sort_order || 0;
    document.getElementById('edit_doc_status').checked = (parseInt(doc.status) === 1);
    
    // Resolve image URL
    var imgUrl = doc.image;
    if (imgUrl && !imgUrl.startsWith('http')) {
        imgUrl = '../' + imgUrl.replace(/^\/+/, '');
    }
    document.getElementById('editDocPreviewImg').src = imgUrl;

    showModalSafely('editDocModal');
}

// View Document Lightbox
function viewDoc(doc) {
    document.getElementById('viewDocModalTitle').textContent = doc.title;
    document.getElementById('viewDocModalSub').textContent = doc.doc_number ? ('Reg/License No: ' + doc.doc_number) : '';
    document.getElementById('viewDocModalDesc').textContent = doc.description || '';

    var imgUrl = doc.image;
    if (imgUrl && !imgUrl.startsWith('http')) {
        imgUrl = '../' + imgUrl.replace(/^\/+/, '');
    }
    document.getElementById('viewDocModalImg').src = imgUrl;
    document.getElementById('viewDocModalFull').href = imgUrl;

    showModalSafely('viewDocModal');
}

// Toggle Status
function toggleDocStatus(id) {
    document.getElementById('actionFormAction').value = 'toggle_doc_status';
    document.getElementById('actionFormDocId').value = id;
    document.getElementById('actionDocForm').submit();
}

// Confirm Delete
function confirmDeleteDoc(id, title) {
    if (confirm('Are you sure you want to delete "' + title + '"? This will also remove the image file from the server.')) {
        document.getElementById('actionFormAction').value = 'delete_document';
        document.getElementById('actionFormDocId').value = id;
        document.getElementById('actionDocForm').submit();
    }
}

// Color picker and hex sync
(function () {
    document.querySelectorAll('.about-color-picker').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var input = document.querySelector('.about-hex-input[data-target="' + picker.id + '"]');
            if (input) input.value = picker.value;
        });
    });
    document.querySelectorAll('.about-hex-input').forEach(function (input) {
        input.addEventListener('input', function () {
            var picker = document.getElementById(input.dataset.target);
            if (picker && /^#[0-9a-fA-F]{6}$/.test(input.value)) {
                picker.value = input.value;
            }
        });
    });
})();
</script>

<?php include 'admin_footer.php'; ?>
