<?php
include 'admin_header.php';

// Keys for About Us customization
$about_keys = [
    'about_hero_title' => 'About Us',
    'about_hero_subtitle' => 'Learn more about our journey and values.',
    'about_who_title' => 'Who We Are',
    'about_who_desc1' => 'Welcome to Sagar Starter\'s. We are dedicated to providing you the very best of products, with an emphasis on quality, customer service, and uniqueness.',
    'about_who_desc2' => 'Founded with a passion for modern aesthetics and functional design, we have come a long way from our beginnings.',
    'about_who_image' => 'about_who.jpg',
    'about_f_icon1' => 'fas fa-truck',
    'about_f_title1' => 'Fast Delivery',
    'about_f_desc1' => 'We ensure your packages arrive on time, every time safely to your doorstep.',
    'about_f_icon2' => 'fas fa-hand-holding-heart',
    'about_f_title2' => 'Quality Promise',
    'about_f_desc2' => 'Every item is carefully inspected to meet our strict quality and design standards.',
    'about_f_icon3' => 'fas fa-headset',
    'about_f_title3' => '24/7 Support',
    'about_f_desc3' => 'Our dedicated customer service team is always here to help you when needed.',

    // ── Colours & Typography (new) ──────────────────────────
    'about_heading_color'     => '#0d6efd',
    'about_heading_font_size' => '32',
    'about_body_font_size'    => '16',
    'about_icon_color'        => '#0d6efd',
    'about_card_bg'           => '#ffffff',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($about_keys as $key => $default) {
        if ($key === 'about_who_image') {
            $image_saved = false;
            if (isset($_FILES['about_who_image']) && $_FILES['about_who_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['about_who_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $filename = 'about_who_' . time() . '.' . $ext;
                    // Save to uploads/media/images/ — consistent with media library
                    $upload_target = '../uploads/media/images/';
                    if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
                    if (move_uploaded_file($_FILES['about_who_image']['tmp_name'], $upload_target . $filename)) {
                        $full_path = 'uploads/media/images/' . $filename;
                        $safe_val = $conn->real_escape_string($full_path);
                        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$safe_val') ON DUPLICATE KEY UPDATE setting_value='$safe_val'");
                        
                        // Also sync to media_library DB table
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
            // Fallback: If no file uploaded or move_uploaded_file failed, check gallery image path input
            if (!$image_saved && !empty($_POST['about_who_image_path'])) {
                $raw_path = trim($_POST['about_who_image_path']);
                // Clean absolute URL to relative path if needed
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
    // Refresh global settings
    $settings_query = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $settings_query->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$current_settings = $global_settings;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Customize About Us Page</h4>
    <a href="../about.php" target="_blank" class="btn btn-outline-primary btn-custom px-4">
        <i class="fas fa-external-link-alt me-2"></i>View Page
    </a>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
    <div class="row">
        <!-- Hero Section -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-image me-2"></i>Hero Section</h6>
                </div>
                <div class="card-body">
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
                <div class="card-body">
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
                <div class="card-body text-center">
                    <div class="mb-4">
                        <?php 
                        $who_img_val = $current_settings['about_who_image'] ?? $about_keys['about_who_image'];
                        $who_img_url = resolve_image_url($who_img_val);
                        ?>
                        <?php if (!empty($who_img_url) && strpos($who_img_url, 'placeholder') === false): ?>
                            <img src="<?php echo htmlspecialchars($who_img_url); ?>" class="img-fluid rounded-4 shadow-sm mb-3" style="max-height: 250px;" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
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
                <div class="card-body">
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
    </div>

    <!-- ── Colours & Typography ─────────────────────────────── -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-palette me-2"></i>Colours &amp; Typography</h6>
            </div>
            <div class="card-body">

                <!-- Color Pickers row -->
                <div class="row g-4 mb-4">
                    <!-- Heading Color -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-1">Heading Colour</label>
                        <small class="d-block text-muted mb-2">&ldquo;Who We Are&rdquo; title color.</small>
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
                        <small class="d-block text-muted mb-2">Color of the 3 feature icons below.</small>
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
                        <small class="d-block text-muted mb-2">Background color for the feature cards.</small>
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

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary btn-lg btn-custom px-5">
            <i class="fas fa-save me-2"></i>Save All Changes
        </button>
    </div>
</form>

<script>
// Sync hex text and color pickers
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
