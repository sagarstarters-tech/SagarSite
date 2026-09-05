<?php
require_once 'admin_header.php';

// Fetch current settings
$settings_query = "SELECT * FROM product_share_settings WHERE id = 1";
$result = $conn->query($settings_query);
$settings = $result->fetch_assoc();

if (!$settings) {
    // Failsafe insert if missing
    $conn->query("INSERT IGNORE INTO product_share_settings (id) VALUES (1)");
    $settings = ['whatsapp_status'=>1, 'facebook_status'=>1, 'telegram_status'=>1, 'copylink_status'=>1, 'section_title'=>'Share Product', 'icon_style'=>'rounded'];
}

$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_title = $conn->real_escape_string($_POST['section_title']);
    $icon_style = $conn->real_escape_string($_POST['icon_style']);
    $whatsapp_status = isset($_POST['whatsapp_status']) ? 1 : 0;
    $facebook_status = isset($_POST['facebook_status']) ? 1 : 0;
    $telegram_status = isset($_POST['telegram_status']) ? 1 : 0;
    $copylink_status = isset($_POST['copylink_status']) ? 1 : 0;

    $update_query = "UPDATE product_share_settings SET 
        section_title = '$section_title',
        icon_style = '$icon_style',
        whatsapp_status = $whatsapp_status,
        facebook_status = $facebook_status,
        telegram_status = $telegram_status,
        copylink_status = $copylink_status
        WHERE id = 1";

    if ($conn->query($update_query)) {
        $success_msg = "Product Share Settings updated successfully.";
        // Refresh settings
        $result = $conn->query($settings_query);
        $settings = $result->fetch_assoc();
    }
}
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-share-alt"></i> Viral Marketing
            </div>
            <h1 class="adm-hero-title">Product Share Settings</h1>
            <p class="adm-hero-subtitle">Enable and configure 1-click viral social sharing channels on your store's product pages.</p>
        </div>
    </div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
    <i class="fas fa-check-circle fa-lg me-3 text-success"></i>
    <div>
        <strong>Success!</strong> <?php echo $success_msg; ?>
    </div>
    <button type="button" class="btn-close ms-auto" data-mdb-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST">
    <?php echo csrf_input(); ?>
    
    <!-- General Settings Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center">
                <i class="fas fa-sliders-h text-primary me-2"></i> General Settings
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Share Section Title</label>
                    <input type="text" name="section_title" class="form-control form-control-lg bg-light" 
                           value="<?php echo htmlspecialchars($settings['section_title']); ?>" required>
                    <small class="text-muted">The heading displayed above the share buttons on the product page.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Icon Style</label>
                    <select name="icon_style" class="form-select form-select-lg bg-light">
                        <option value="rounded" <?php echo ($settings['icon_style'] == 'rounded') ? 'selected' : ''; ?>>Rounded Corners (Modern)</option>
                        <option value="circle" <?php echo ($settings['icon_style'] == 'circle') ? 'selected' : ''; ?>>Circle (Classic)</option>
                        <option value="square" <?php echo ($settings['icon_style'] == 'square') ? 'selected' : ''; ?>>Square (Minimal)</option>
                    </select>
                    <small class="text-muted">Visual border style of the social icons.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Share Platforms Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center">
                <i class="fas fa-network-wired text-primary me-2"></i> Active Share Platforms
            </h5>
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                Instant Toggle
            </span>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-4">Toggle individual platforms on or off. Disabled platforms will be hidden immediately from product pages.</p>
            
            <!-- WhatsApp -->
            <div class="platform-share-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="platform-icon-box whatsapp">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0 text-dark">WhatsApp</h6>
                            <span class="badge rounded-pill <?php echo ($settings['whatsapp_status']) ? 'bg-success text-white' : 'bg-light text-muted border'; ?>" id="status-badge-whatsapp">
                                <?php echo ($settings['whatsapp_status']) ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <small class="text-muted">Allow customers to share product directly to WhatsApp contacts & groups</small>
                    </div>
                </div>
                <div class="ms-auto ps-3">
                    <label class="custom-switch-slider">
                        <input type="checkbox" name="whatsapp_status" id="whatsappSwitch" value="1" 
                               <?php echo ($settings['whatsapp_status']) ? 'checked' : ''; ?>
                               onchange="updateToggleBadge('whatsapp', this.checked)">
                        <span class="slider-track track-whatsapp"></span>
                    </label>
                </div>
            </div>

            <!-- Facebook -->
            <div class="platform-share-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="platform-icon-box facebook">
                        <i class="fab fa-facebook-f"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0 text-dark">Facebook</h6>
                            <span class="badge rounded-pill <?php echo ($settings['facebook_status']) ? 'bg-primary text-white' : 'bg-light text-muted border'; ?>" id="status-badge-facebook">
                                <?php echo ($settings['facebook_status']) ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <small class="text-muted">Allow customers to share and post products to Facebook Feeds & Stories</small>
                    </div>
                </div>
                <div class="ms-auto ps-3">
                    <label class="custom-switch-slider">
                        <input type="checkbox" name="facebook_status" id="fbSwitch" value="1" 
                               <?php echo ($settings['facebook_status']) ? 'checked' : ''; ?>
                               onchange="updateToggleBadge('facebook', this.checked)">
                        <span class="slider-track track-facebook"></span>
                    </label>
                </div>
            </div>

            <!-- Telegram -->
            <div class="platform-share-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="platform-icon-box telegram">
                        <i class="fab fa-telegram-plane"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0 text-dark">Telegram</h6>
                            <span class="badge rounded-pill <?php echo ($settings['telegram_status']) ? 'bg-info text-white' : 'bg-light text-muted border'; ?>" id="status-badge-telegram">
                                <?php echo ($settings['telegram_status']) ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <small class="text-muted">Allow customers to share products directly to Telegram channels & chats</small>
                    </div>
                </div>
                <div class="ms-auto ps-3">
                    <label class="custom-switch-slider">
                        <input type="checkbox" name="telegram_status" id="tgSwitch" value="1" 
                               <?php echo ($settings['telegram_status']) ? 'checked' : ''; ?>
                               onchange="updateToggleBadge('telegram', this.checked)">
                        <span class="slider-track track-telegram"></span>
                    </label>
                </div>
            </div>

            <!-- Copy Link -->
            <div class="platform-share-card mb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="platform-icon-box copylink">
                        <i class="fas fa-link"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0 text-dark">Copy Link</h6>
                            <span class="badge rounded-pill <?php echo ($settings['copylink_status']) ? 'bg-secondary text-white' : 'bg-light text-muted border'; ?>" id="status-badge-copylink">
                                <?php echo ($settings['copylink_status']) ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <small class="text-muted">Provide a 1-click button to copy the product URL to clipboard</small>
                    </div>
                </div>
                <div class="ms-auto ps-3">
                    <label class="custom-switch-slider">
                        <input type="checkbox" name="copylink_status" id="copySwitch" value="1" 
                               <?php echo ($settings['copylink_status']) ? 'checked' : ''; ?>
                               onchange="updateToggleBadge('copylink', this.checked)">
                        <span class="slider-track track-copylink"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-3 mb-5">
        <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm">
            <i class="fas fa-save me-2"></i> Save Settings
        </button>
    </div>
</form>

<script>
function updateToggleBadge(platform, isChecked) {
    const badge = document.getElementById('status-badge-' + platform);
    if (!badge) return;
    
    if (isChecked) {
        let bgClass = 'bg-primary';
        if (platform === 'whatsapp') bgClass = 'bg-success';
        if (platform === 'telegram') bgClass = 'bg-info';
        if (platform === 'copylink') bgClass = 'bg-secondary';
        
        badge.className = 'badge rounded-pill ' + bgClass + ' text-white';
        badge.textContent = 'Active';
    } else {
        badge.className = 'badge rounded-pill bg-light text-muted border';
        badge.textContent = 'Disabled';
    }
}
</script>
</div>
<?php require_once 'admin_footer.php'; ?>
