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

// Handle Add/Edit Banner & Dimension Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update Slider & Banner Dimensions
    if ($action === 'update_dimensions') {
        $desktop_height = $conn->real_escape_string(trim($_POST['desktop_height'] ?? '560px'));
        $tablet_height = $conn->real_escape_string(trim($_POST['tablet_height'] ?? '460px'));
        $mobile_height = $conn->real_escape_string(trim($_POST['mobile_height'] ?? '380px'));
        $layout = $conn->real_escape_string($_POST['layout'] ?? 'full');
        $container_width = $conn->real_escape_string(trim($_POST['container_width'] ?? '100%'));
        $image_fit = $conn->real_escape_string($_POST['image_fit'] ?? 'cover');

        $conn->query("UPDATE hero_slider_settings SET 
            desktop_height='$desktop_height', 
            tablet_height='$tablet_height', 
            mobile_height='$mobile_height', 
            layout='$layout', 
            container_width='$container_width', 
            image_fit='$image_fit' 
            WHERE id=1");

        header("Location: manage_banners.php?success=Banner & Slider dimensions updated successfully");
        exit;
    }

    // Add new banner
    if ($action === 'add') {
        $heading = $conn->real_escape_string($_POST['heading']);
        $subheading = $conn->real_escape_string($_POST['subheading']);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = uniqid('banner_') . '.' . $ext;
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_target . $fname)) {
                $image = 'uploads/media/images/' . $fname;
            }
        }
        
        if ($image) {
            $esc_img = $conn->real_escape_string($image);
            $conn->query("INSERT INTO banners (image, heading, subheading, status) VALUES ('$esc_img', '$heading', '$subheading', '$status')");
        }
        
        header("Location: manage_banners.php?success=Banner added successfully");
        exit;
    }
    
    // Update banner
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $heading = $conn->real_escape_string($_POST['heading']);
        $subheading = $conn->real_escape_string($_POST['subheading']);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        
        $image_query = "";
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $old_q = $conn->query("SELECT image FROM banners WHERE id=$id");
            if ($old_img = $old_q->fetch_assoc()) {
                $old_banner_img = $conn->real_escape_string($old_img['image']);
                $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_banner_img'")->fetch_assoc()['c'];
                $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_banner_img'")->fetch_assoc()['c'];
                $other_ban  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_banner_img' AND id!=$id")->fetch_assoc()['c'];
                if ($prod_refs === 0 && $gal_refs === 0 && $other_ban === 0) {
                    $try1 = '../' . ltrim($old_img['image'], '/');
                    $try2 = '../uploads/images/' . basename($old_img['image']);
                    $try3 = '../assets/images/' . basename($old_img['image']);
                    foreach ([$try1, $try2, $try3] as $tp) {
                        if (file_exists($tp)) { @unlink($tp); break; }
                    }
                }
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = uniqid('banner_') . '.' . $ext;
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_target . $fname)) {
                $new_path = $conn->real_escape_string('uploads/media/images/' . $fname);
                $image_query = ", image='$new_path'";
            }
        }
        
        $conn->query("UPDATE banners SET heading='$heading', subheading='$subheading', status='$status' $image_query WHERE id=$id");
        
        header("Location: manage_banners.php?success=Banner updated successfully");
        exit;
    }
}

// Handle Delete Banner via GET
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $old_q = $conn->query("SELECT image FROM banners WHERE id=$id");
    if ($old_q && $old_img = $old_q->fetch_assoc()) {
        $old_banner_img = $conn->real_escape_string($old_img['image']);
        $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_banner_img'")->fetch_assoc()['c'];
        $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_banner_img'")->fetch_assoc()['c'];
        $other_ban  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_banner_img' AND id!=$id")->fetch_assoc()['c'];
        if ($prod_refs === 0 && $gal_refs === 0 && $other_ban === 0) {
            $try1 = '../' . ltrim($old_img['image'], '/');
            $try2 = '../uploads/images/' . basename($old_img['image']);
            $try3 = '../assets/images/' . basename($old_img['image']);
            foreach ([$try1, $try2, $try3] as $tp) {
                if (file_exists($tp)) { @unlink($tp); break; }
            }
        }
    }
    $conn->query("DELETE FROM banners WHERE id=$id");
    header("Location: manage_banners.php?success=Banner deleted successfully");
    exit;
}

// Fetch all banners & slider settings
$banners = $conn->query("SELECT * FROM banners ORDER BY created_at DESC");
$slider_settings_q = $conn->query("SELECT * FROM hero_slider_settings LIMIT 1");
$slider_settings = $slider_settings_q ? $slider_settings_q->fetch_assoc() : [];
?>

<style>
.mb-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
}
.mb-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.mb-thumb {
    width: 140px;
    height: 70px;
    border-radius: 10px;
    object-fit: contain;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.preset-btn {
    font-size: 11px;
    padding: 2px 8px;
}
</style>

<div class="container-fluid py-3">

    <!-- Hero Header Banner -->
    <div class="mb-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-images me-1"></i> Promotional Banners & Slider
                    </span>
                    <span class="text-white-50 small"><?php echo $banners ? $banners->num_rows : 0; ?> active banners</span>
                </div>
                <h3 class="fw-bold mb-0 text-white">Banner & Slider Management</h3>
                <p class="text-white-50 small mb-0 mt-1">Set banner dimensions (lambai/chaudai), manage slides, and add promotional banners.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="manage-slides.php" class="btn btn-outline-light px-3 py-2 rounded-3 fw-bold d-flex align-items-center gap-2">
                    <i class="fas fa-sliders-h"></i>
                    <span>Manage Slides</span>
                </a>
                <button class="btn btn-primary px-3 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center gap-2" data-mdb-toggle="modal" data-mdb-target="#addBannerModal">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Banner</span>
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success rounded-3 py-2 px-3 mb-3 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Banner & Slider Dimensions Control Box -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-ruler-combined text-primary"></i> Banner & Slider Dimension Settings (Lambai & Chaudai)
                </h5>
                <p class="text-muted small mb-0 mt-1">Homepage hero banner aur slider ki height (lambai) aur width (chaudai) adjust karein.</p>
            </div>
            <a href="hero-slider-settings.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-cog me-1"></i> Full Slider Settings
            </a>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="manage_banners.php">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update_dimensions">

                <div class="row g-3 align-items-end">
                    <!-- Layout Style (Width Mode) -->
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label fw-bold small text-muted">Layout Width (Chaudai Mode)</label>
                        <select name="layout" id="mbLayoutSelect" class="form-select rounded-3" onchange="toggleMbContainerWidth()">
                            <option value="full" <?php echo (($slider_settings['layout'] ?? '') == 'full') ? 'selected' : ''; ?>>Full Width (100% Screen)</option>
                            <option value="boxed" <?php echo (($slider_settings['layout'] ?? '') == 'boxed') ? 'selected' : ''; ?>>Boxed (Container Width)</option>
                        </select>
                    </div>

                    <!-- Custom Width (If Boxed) -->
                    <div class="col-md-6 col-lg-3" id="mbContainerWidthGroup" style="<?php echo (($slider_settings['layout'] ?? '') == 'boxed') ? '' : 'display:none;'; ?>">
                        <label class="form-label fw-bold small text-muted">Max Width (Chaudai)</label>
                        <input type="text" name="container_width" id="mbContainerWidthInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($slider_settings['container_width'] ?? '100%'); ?>" placeholder="e.g. 1320px, 1200px">
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbContainerWidthInput', '100%')">100%</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbContainerWidthInput', '1320px')">1320px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbContainerWidthInput', '1200px')">1200px</button>
                        </div>
                    </div>

                    <!-- Desktop Height -->
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label fw-bold small text-muted">Desktop Height (Desktop Lambai)</label>
                        <input type="text" name="desktop_height" id="mbDesktopHeightInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($slider_settings['desktop_height'] ?? '560px'); ?>" placeholder="e.g. 560px">
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbDesktopHeightInput', '450px')">450px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbDesktopHeightInput', '500px')">500px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbDesktopHeightInput', '560px')">560px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbDesktopHeightInput', '600px')">600px</button>
                        </div>
                    </div>

                    <!-- Mobile Height -->
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label fw-bold small text-muted">Mobile Height (Mobile Lambai)</label>
                        <input type="text" name="mobile_height" id="mbMobileHeightInput" class="form-control rounded-3" value="<?php echo htmlspecialchars($slider_settings['mobile_height'] ?? '380px'); ?>" placeholder="e.g. 380px">
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbMobileHeightInput', '280px')">280px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbMobileHeightInput', '320px')">320px</button>
                            <button type="button" class="btn btn-sm btn-light border preset-btn" onclick="setMbVal('mbMobileHeightInput', '380px')">380px</button>
                        </div>
                    </div>

                    <!-- Image Fit Mode -->
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label fw-bold small text-muted">Image Fit Mode</label>
                        <select name="image_fit" class="form-select rounded-3">
                            <option value="cover" <?php echo (($slider_settings['image_fit'] ?? 'cover') == 'cover') ? 'selected' : ''; ?>>Cover (Fill Frame)</option>
                            <option value="contain" <?php echo (($slider_settings['image_fit'] ?? '') == 'contain') ? 'selected' : ''; ?>>Contain (Show Full Image)</option>
                            <option value="fill" <?php echo (($slider_settings['image_fit'] ?? '') == 'fill') ? 'selected' : ''; ?>>Fill (Stretch Exact)</option>
                        </select>
                    </div>

                    <!-- Tablet Height -->
                    <input type="hidden" name="tablet_height" value="<?php echo htmlspecialchars($slider_settings['tablet_height'] ?? '460px'); ?>">

                    <!-- Save Button -->
                    <div class="col-md-6 col-lg-3">
                        <button type="submit" class="btn btn-primary rounded-3 w-100 fw-bold py-2 shadow-sm">
                            <i class="fas fa-save me-1"></i> Save Dimensions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Container -->
    <div class="mb-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Banner Preview</th>
                        <th class="py-3">Heading & Details</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($banners && $banners->num_rows > 0): ?>
                        <?php while($banner = $banners->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <img src="<?php echo resolve_image_url($banner['image']); ?>" class="mb-thumb" alt="Banner" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($banner['heading'] ?: '(No Heading)'); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($banner['subheading'] ?: '(No Subheading)'); ?></small>
                            </td>
                            <td>
                                <?php if($banner['status'] === 'active'): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm btn-custom px-3 edit-banner-btn" 
                                        data-id="<?php echo $banner['id']; ?>"
                                        data-heading="<?php echo htmlspecialchars($banner['heading']); ?>"
                                        data-subheading="<?php echo htmlspecialchars($banner['subheading']); ?>"
                                        data-status="<?php echo $banner['status']; ?>"
                                        data-image="<?php echo ASSETS_URL; ?>/images/<?php echo htmlspecialchars($banner['image']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="manage_banners.php?delete=<?php echo $banner['id']; ?>" class="btn btn-danger btn-sm btn-custom px-3" onclick="return confirm('Are you sure you want to delete this banner? This cannot be undone.')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No banners found. Add a banner to display on the homepage slider.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Banner Modal -->
<div class="modal fade" id="addBannerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white text-center border-0 px-4 py-3">
                <h5 class="modal-title w-100 fw-bold"><i class="fas fa-image me-2"></i>Add New Banner</h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="manage_banners.php" method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Banner Image <span class="text-danger">*</span></label>
                        <input type="file" name="image" class="form-control form-control-lg bg-light" accept="image/*" required>
                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Recommended size: 1920x600px (16:9 ratio).</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Heading <small class="text-muted">(Optional)</small></label>
                        <input type="text" name="heading" class="form-control form-control-lg bg-light" placeholder="e.g. Summer Sale Active">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Subheading <small class="text-muted">(Optional)</small></label>
                        <textarea name="subheading" class="form-control form-control-lg bg-light" rows="2" placeholder="e.g. Up to 50% Off Top Brands"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Status</label>
                        <div class="form-check form-switch form-switch-lg mt-2 px-0 d-flex align-items-center">
                            <input class="form-check-input ms-0 me-3 mt-0" type="checkbox" name="status" value="active" id="flexSwitchCheckDefault" style="height: 25px; width: 50px;" checked>
                            <label class="form-check-label fw-bold" for="flexSwitchCheckDefault">Enabled (Visible on store)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light btn-lg flex-grow-1 btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1 btn-custom">Save Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Banner Modal -->
<div class="modal fade" id="editBannerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white text-center border-0 px-4 py-3">
                <h5 class="modal-title w-100 fw-bold"><i class="fas fa-edit me-2"></i>Edit Banner</h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="manage_banners.php" method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_banner_id">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <img id="edit_banner_preview" src="" class="img-fluid rounded shadow-sm mb-3" style="max-height: 150px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Replace Image <small class="text-muted">(Leave empty to keep current)</small></label>
                        <input type="file" name="image" class="form-control form-control-lg bg-light" accept="image/*">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Heading <small class="text-muted">(Optional)</small></label>
                        <input type="text" name="heading" id="edit_banner_heading" class="form-control form-control-lg bg-light">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Subheading <small class="text-muted">(Optional)</small></label>
                        <textarea name="subheading" id="edit_banner_subheading" class="form-control form-control-lg bg-light" rows="2"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Status</label>
                        <div class="form-check form-switch form-switch-lg mt-2 px-0 d-flex align-items-center">
                            <input class="form-check-input ms-0 me-3 mt-0" type="checkbox" name="status" value="active" id="edit_banner_status" style="height: 25px; width: 50px;">
                            <label class="form-check-label fw-bold" for="edit_banner_status">Enabled (Visible on store)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light btn-lg flex-grow-1 btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1 btn-custom">Update Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setMbVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}
function toggleMbContainerWidth() {
    const sel = document.getElementById('mbLayoutSelect');
    const grp = document.getElementById('mbContainerWidthGroup');
    if (sel && grp) {
        grp.style.display = (sel.value === 'boxed') ? 'block' : 'none';
    }
}

document.querySelectorAll('.edit-banner-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_banner_id').value = this.dataset.id;
        document.getElementById('edit_banner_heading').value = this.dataset.heading;
        document.getElementById('edit_banner_subheading').value = this.dataset.subheading;
        document.getElementById('edit_banner_preview').src = this.dataset.image;
        
        const statusCheckbox = document.getElementById('edit_banner_status');
        if (this.dataset.status === 'active') {
            statusCheckbox.checked = true;
        } else {
            statusCheckbox.checked = false;
        }
        
        var editModal = new mdb.Modal(document.getElementById('editBannerModal'));
        editModal.show();
    });
});
</script>
</div>

<?php include 'admin_footer.php'; ?>

