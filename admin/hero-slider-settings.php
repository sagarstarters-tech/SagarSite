<?php
include 'admin_header.php';

// Auto-migration for new dimension columns
try {
    $checkCols = $conn->query("SHOW COLUMNS FROM hero_slider_settings LIKE 'container_width'");
    if ($checkCols && $checkCols->num_rows === 0) {
        $conn->query("ALTER TABLE hero_slider_settings ADD COLUMN container_width VARCHAR(50) NOT NULL DEFAULT '100%'");
        $conn->query("ALTER TABLE hero_slider_settings ADD COLUMN image_fit VARCHAR(50) NOT NULL DEFAULT 'cover'");
        $conn->query("ALTER TABLE hero_slider_settings ADD COLUMN tablet_height VARCHAR(50) NOT NULL DEFAULT '460px'");
    }
} catch (\Throwable $e) {}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $layout = $conn->real_escape_string($_POST['layout'] ?? 'full');
    $container_width = $conn->real_escape_string(trim($_POST['container_width'] ?? '100%'));
    $desktop_height = $conn->real_escape_string(trim($_POST['desktop_height'] ?? '560px'));
    $tablet_height = $conn->real_escape_string(trim($_POST['tablet_height'] ?? '460px'));
    $mobile_height = $conn->real_escape_string(trim($_POST['mobile_height'] ?? '380px'));
    $image_fit = $conn->real_escape_string($_POST['image_fit'] ?? 'cover');
    $show_arrows = isset($_POST['show_arrows']) ? 1 : 0;
    $show_dots = isset($_POST['show_dots']) ? 1 : 0;
    $arrow_style = $conn->real_escape_string($_POST['arrow_style'] ?? 'light');
    $dot_style = $conn->real_escape_string($_POST['dot_style'] ?? 'light');
    $autoplay = isset($_POST['autoplay']) ? 1 : 0;
    $autoplay_delay = intval($_POST['autoplay_delay'] ?? 5000);
    $transition_type = $conn->real_escape_string($_POST['transition_type'] ?? 'slide');
    $transition_speed = intval($_POST['transition_speed'] ?? 800);

    $sql = "UPDATE hero_slider_settings SET 
            is_active=$is_active, 
            layout='$layout', 
            container_width='$container_width',
            desktop_height='$desktop_height', 
            tablet_height='$tablet_height',
            mobile_height='$mobile_height', 
            image_fit='$image_fit',
            show_arrows=$show_arrows, 
            show_dots=$show_dots, 
            arrow_style='$arrow_style', 
            dot_style='$dot_style', 
            autoplay=$autoplay, 
            autoplay_delay=$autoplay_delay, 
            transition_type='$transition_type', 
            transition_speed=$transition_speed 
            WHERE id=1";

    if ($conn->query($sql)) {
        $success = "Slider & Banner dimensions updated successfully!";
    } else {
        $error = "Error updating settings: " . $conn->error;
    }
}

// Fetch current settings
$settings_q = $conn->query("SELECT * FROM hero_slider_settings LIMIT 1");
$settings = $settings_q->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-sliders-h text-primary me-2"></i>Hero Slider & Banner Settings</h2>
        <p class="text-muted small mb-0">Adjust slider height (lambai), width (chaudai), and display options.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="manage-slides.php" class="btn btn-outline-primary rounded-pill px-3"><i class="fas fa-images me-2"></i>Manage Slides</a>
        <a href="manage_banners.php" class="btn btn-outline-secondary rounded-pill px-3"><i class="fas fa-photo-film me-2"></i>Promotional Banners</a>
    </div>
</div>

<?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_settings">
            
            <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary d-flex align-items-center gap-2">
                <i class="fas fa-toggle-on"></i> General Settings
            </h5>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="form-check form-switch form-switch-lg mt-1 px-0 d-flex align-items-center">
                        <input class="form-check-input ms-0 me-3 mt-0" type="checkbox" name="is_active" id="is_active" style="height: 25px; width: 50px;" <?php echo (!empty($settings['is_active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="is_active">Enable Hero Slider on Homepage</label>
                    </div>
                </div>
            </div>

            <!-- Dimensions Section -->
            <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary d-flex align-items-center gap-2">
                <i class="fas fa-ruler-combined"></i> Dimensions & Layout (Lambai & Chaudai)
            </h5>

            <div class="row g-3 mb-4">
                <!-- Layout & Width -->
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold small text-muted">Layout Style (Chaudai Mode)</label>
                    <select name="layout" id="layoutSelect" class="form-select rounded-3" onchange="toggleContainerWidthInput()">
                        <option value="full" <?php echo (($settings['layout'] ?? '') == 'full') ? 'selected' : ''; ?>>Full Width (100% Screen)</option>
                        <option value="boxed" <?php echo (($settings['layout'] ?? '') == 'boxed') ? 'selected' : ''; ?>>Boxed (Container Width)</option>
                    </select>
                    <div class="form-text small">Edge-to-edge full width or container.</div>
                </div>

                <!-- Custom Width (If Boxed) -->
                <div class="col-md-6 col-lg-3" id="containerWidthGroup" style="<?php echo (($settings['layout'] ?? '') == 'boxed') ? '' : 'display:none;'; ?>">
                    <label class="form-label fw-bold small text-muted">Max Width (Chaudai)</label>
                    <input type="text" name="container_width" id="containerWidthInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($settings['container_width'] ?? '100%'); ?>" placeholder="e.g. 1320px, 1200px, 90%">
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('containerWidthInput', '100%')">100%</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('containerWidthInput', '1320px')">1320px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('containerWidthInput', '1200px')">1200px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('containerWidthInput', '1140px')">1140px</button>
                    </div>
                </div>

                <!-- Desktop Height -->
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold small text-muted">Desktop Height (Lambai)</label>
                    <input type="text" name="desktop_height" id="desktopHeightInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($settings['desktop_height'] ?? '560px'); ?>" placeholder="e.g. 560px or 600px">
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('desktopHeightInput', '450px')">450px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('desktopHeightInput', '500px')">500px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('desktopHeightInput', '560px')">560px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('desktopHeightInput', '600px')">600px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('desktopHeightInput', '650px')">650px</button>
                    </div>
                </div>

                <!-- Tablet Height -->
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold small text-muted">Tablet Height (Lambai)</label>
                    <input type="text" name="tablet_height" id="tabletHeightInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($settings['tablet_height'] ?? '460px'); ?>" placeholder="e.g. 460px">
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('tabletHeightInput', '380px')">380px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('tabletHeightInput', '420px')">420px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('tabletHeightInput', '460px')">460px</button>
                    </div>
                </div>

                <!-- Mobile Height -->
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold small text-muted">Mobile Height (Lambai)</label>
                    <input type="text" name="mobile_height" id="mobileHeightInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($settings['mobile_height'] ?? '380px'); ?>" placeholder="e.g. 380px or 320px">
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('mobileHeightInput', '280px')">280px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('mobileHeightInput', '320px')">320px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('mobileHeightInput', '360px')">360px</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2 extra-small" onclick="setVal('mobileHeightInput', '400px')">400px</button>
                    </div>
                </div>

                <!-- Image Fit Mode -->
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold small text-muted">Banner Image Fit Mode</label>
                    <select name="image_fit" class="form-select rounded-3">
                        <option value="cover" <?php echo (($settings['image_fit'] ?? 'cover') == 'cover') ? 'selected' : ''; ?>>Cover (Fill & Crop - Hero Style)</option>
                        <option value="contain" <?php echo (($settings['image_fit'] ?? '') == 'contain') ? 'selected' : ''; ?>>Contain (Full Image - No Crop)</option>
                        <option value="fill" <?php echo (($settings['image_fit'] ?? '') == 'fill') ? 'selected' : ''; ?>>Fill (Exact Dimensions)</option>
                    </select>
                    <div class="form-text small">How banner image scales inside the frame.</div>
                </div>
            </div>

            <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary">Navigation & Controls</h5>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="show_arrows" id="show_arrows" <?php echo $settings['show_arrows'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="show_arrows">Show Prev/Next Arrows</label>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Arrow Style</label>
                    <select name="arrow_style" class="form-select bg-light">
                        <option value="light" <?php echo ($settings['arrow_style'] == 'light') ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo ($settings['arrow_style'] == 'dark') ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="show_dots" id="show_dots" <?php echo $settings['show_dots'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="show_dots">Show Pagination Dots</label>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Dots Style</label>
                    <select name="dot_style" class="form-select bg-light">
                        <option value="light" <?php echo ($settings['dot_style'] == 'light') ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo ($settings['dot_style'] == 'dark') ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>
            </div>

            <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary">Autoplay & Transitions</h5>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="form-check form-switch mt-2 d-flex align-items-center">
                        <input class="form-check-input me-2 mt-0" type="checkbox" name="autoplay" id="autoplay" <?php echo $settings['autoplay'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="autoplay">Auto Play Slides</label>
                    </div>
                    <div class="form-text">Slides will pause on hover automatically</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Autoplay Delay (ms)</label>
                    <input type="number" name="autoplay_delay" class="form-control bg-light" value="<?php echo intval($settings['autoplay_delay']); ?>" min="1000">
                    <div class="form-text">1000ms = 1 second</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Transition Type</label>
                    <select name="transition_type" class="form-select bg-light">
                        <option value="slide" <?php echo ($settings['transition_type'] == 'slide') ? 'selected' : ''; ?>>Slide Horizontal</option>
                        <option value="fade" <?php echo ($settings['transition_type'] == 'fade') ? 'selected' : ''; ?>>Fade</option>
                        <option value="zoom" <?php echo ($settings['transition_type'] == 'zoom') ? 'selected' : ''; ?>>Zoom</option>
                        <option value="zoom-in" <?php echo ($settings['transition_type'] == 'zoom-in') ? 'selected' : ''; ?>>Zoom In</option>
                        <option value="zoom-out" <?php echo ($settings['transition_type'] == 'zoom-out') ? 'selected' : ''; ?>>Zoom Out</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Transition Duration (ms)</label>
                    <input type="number" name="transition_speed" class="form-control bg-light" value="<?php echo intval($settings['transition_speed']); ?>" min="100">
                    <div class="form-text">Speed of the animation itself</div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary btn-custom btn-lg px-5 shadow-sm"><i class="fas fa-save me-2"></i>Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}
function toggleContainerWidthInput() {
    const sel = document.getElementById('layoutSelect');
    const grp = document.getElementById('containerWidthGroup');
    if (sel && grp) {
        grp.style.display = (sel.value === 'boxed') ? 'block' : 'none';
    }
}
</script>

<?php include 'admin_footer.php'; ?>
