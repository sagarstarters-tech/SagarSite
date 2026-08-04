<?php
include 'admin_header.php';

// Handle setting toggle for the whole section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_section') {
    $is_enabled = isset($_POST['homepage_features_enabled']) ? '1' : '0';
    $conn->query("UPDATE settings SET setting_value='$is_enabled' WHERE setting_key='homepage_features_enabled'");
    $success = "Homepage Features section status updated.";
    // Refresh settings
    $settings_query = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $settings_query->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle CRUD operations for features
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $icon_type = $conn->real_escape_string($_POST['icon_type']);
        $display_order = intval($_POST['display_order']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $icon_value = '';
        
        if ($icon_type === 'font') {
            $icon_value = $conn->real_escape_string($_POST['icon_value_font']);
        } else {
            // Handle image upload
            if (isset($_FILES['icon_value_img']) && $_FILES['icon_value_img']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['icon_value_img']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'])) {
                    $icon_value = 'feature_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['icon_value_img']['tmp_name'], '../assets/images/' . $icon_value);
                }
            } else {
                // Keep existing image if no new file is uploaded
                $icon_value = isset($_POST['existing_icon_val']) ? $conn->real_escape_string($_POST['existing_icon_val']) : '';
            }
        }
        
        if ($action === 'add') {
            $conn->query("INSERT INTO homepage_features (icon_type, icon_value, title, description, display_order, status) VALUES ('$icon_type', '$icon_value', '$title', '$description', $display_order, '$status')");
            $success = "Feature added successfully.";
        } else {
            $id = intval($_POST['id']);
            $conn->query("UPDATE homepage_features SET icon_type='$icon_type', icon_value='$icon_value', title='$title', description='$description', display_order=$display_order, status='$status' WHERE id=$id");
            $success = "Feature updated successfully.";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // delete image if exists (safety: only if not shared with products/gallery/banners/categories)
        $del_q = $conn->query("SELECT icon_type, icon_value FROM homepage_features WHERE id=$id");
        if ($del_item = $del_q->fetch_assoc()) {
            if ($del_item['icon_type'] === 'image' && !empty($del_item['icon_value'])) {
                $feat_img = $conn->real_escape_string($del_item['icon_value']);
                $prod_refs = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$feat_img'")->fetch_assoc()['c'];
                $gal_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$feat_img'")->fetch_assoc()['c'];
                $ban_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$feat_img'")->fetch_assoc()['c'];
                $cat_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$feat_img'")->fetch_assoc()['c'];
                if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $cat_refs === 0 && file_exists('../assets/images/' . $del_item['icon_value'])) {
                    unlink('../assets/images/' . $del_item['icon_value']);
                }
            }
        }
        
        $conn->query("DELETE FROM homepage_features WHERE id=$id");
        $success = "Feature deleted successfully.";
    }
}

$features = $conn->query("SELECT * FROM homepage_features ORDER BY display_order ASC, id DESC");
$section_enabled = $global_settings['homepage_features_enabled'] ?? '1';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manage Homepage Features</h4>
    <button class="btn btn-primary btn-custom px-4" data-mdb-toggle="modal" data-mdb-target="#addFeatureModal">
        <i class="fas fa-plus me-2"></i>Add Feature
    </button>
</div>

<?php if(isset($success)): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="POST" class="d-flex justify-content-between align-items-center">
    <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="toggle_section">
            <h5 class="m-0 fw-bold">Section Status <small class="text-muted fw-normal ms-2">Enable or disable the feature section on the homepage.</small></h5>
            <div class="form-check form-switch fs-5 m-0 d-flex align-items-center">
                <input class="form-check-input mt-0 me-3" type="checkbox" role="switch" name="homepage_features_enabled" id="enableFeatures" <?php echo ($section_enabled == '1') ? 'checked' : ''; ?> onchange="this.form.submit()">
                <label class="form-check-label fs-6 fw-bold m-0" for="enableFeatures"><?php echo ($section_enabled == '1') ? 'Enabled' : 'Disabled'; ?></label>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Order</th>
                        <th>Icon</th>
                        <th>Title & Description</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($features && $features->num_rows > 0): ?>
                        <?php while($f = $features->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $f['display_order']; ?></td>
                            <td>
                                <?php if($f['icon_type'] === 'font'): ?>
                                    <i class="<?php echo htmlspecialchars($f['icon_value']); ?> fa-2x text-primary p-2 bg-light rounded"></i>
                                <?php else: ?>
                                    <img src="<?php echo resolve_image_url($f['icon_value']); ?>" alt="icon" style="height: 40px; width: 40px; object-fit: contain;" class="bg-light p-1 rounded" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($f['title']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($f['description']); ?></small>
                            </td>
                            <td>
                                <?php if($f['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm btn-custom px-3 edit-feature-btn" 
                                        data-id="<?php echo $f['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($f['title']); ?>"
                                        data-desc="<?php echo htmlspecialchars($f['description']); ?>"
                                        data-icontype="<?php echo $f['icon_type']; ?>"
                                        data-iconval="<?php echo htmlspecialchars($f['icon_value']); ?>"
                                        data-order="<?php echo $f['display_order']; ?>"
                                        data-status="<?php echo $f['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this feature?');">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No features added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addFeatureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Add Feature</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title</label>
                        <input type="text" name="title" class="form-control bg-light" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control bg-light" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Icon Type</label>
                        <select name="icon_type" id="add_icon_type" class="form-select bg-light" onchange="toggleIconType('add')">
                            <option value="font">Font Awesome Icon (e.g. fas fa-globe)</option>
                            <option value="image">Image Upload</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="add_font_div">
                        <label class="form-label fw-bold">Font Awesome Class</label>
                        <input type="text" name="icon_value_font" class="form-control bg-light" placeholder="fas fa-truck" value="fas fa-star">
                        <small class="text-muted"><a href="https://fontawesome.com/v6/search?m=free" target="_blank">Search Free Icons</a></small>
                    </div>
                    
                    <div class="mb-3 d-none" id="add_img_div">
                        <label class="form-label fw-bold">Upload Icon Image</label>
                        <input type="file" name="icon_value_img" class="form-control bg-light" accept="image/*">
                        <small class="text-muted">Recommended size: 60x60px (SVG or PNG)</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" name="display_order" class="form-control bg-light" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select bg-light">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom px-4">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editFeatureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Edit Feature</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="existing_icon_val" id="edit_existing_val">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control bg-light" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" id="edit_desc" class="form-control bg-light" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Icon Type</label>
                        <select name="icon_type" id="edit_icon_type" class="form-select bg-light" onchange="toggleIconType('edit')">
                            <option value="font">Font Awesome Icon (e.g. fas fa-globe)</option>
                            <option value="image">Image Upload</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="edit_font_div">
                        <label class="form-label fw-bold">Font Awesome Class</label>
                        <input type="text" name="icon_value_font" id="edit_font_val" class="form-control bg-light" placeholder="fas fa-truck">
                        <small class="text-muted"><a href="https://fontawesome.com/v6/search?m=free" target="_blank">Search Free Icons</a></small>
                    </div>
                    
                    <div class="mb-3 d-none" id="edit_img_div">
                        <label class="form-label fw-bold">Upload New Image (Optional)</label>
                        <input type="file" name="icon_value_img" class="form-control bg-light" accept="image/*">
                        <div id="edit_img_preview" class="mt-2"></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Display Order</label>
                            <input type="number" name="display_order" id="edit_order" class="form-control bg-light">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" id="edit_status" class="form-select bg-light">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-custom px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleIconType(mode) {
    const val = document.getElementById(mode + '_icon_type').value;
    if (val === 'font') {
        document.getElementById(mode + '_font_div').classList.remove('d-none');
        document.getElementById(mode + '_img_div').classList.add('d-none');
    } else {
        document.getElementById(mode + '_font_div').classList.add('d-none');
        document.getElementById(mode + '_img_div').classList.remove('d-none');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-feature-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const btnTgt = e.currentTarget;
            document.getElementById('edit_id').value = btnTgt.dataset.id;
            document.getElementById('edit_title').value = btnTgt.dataset.title;
            document.getElementById('edit_desc').value = btnTgt.dataset.desc;
            document.getElementById('edit_icon_type').value = btnTgt.dataset.icontype;
            document.getElementById('edit_order').value = btnTgt.dataset.order;
            document.getElementById('edit_status').value = btnTgt.dataset.status;
            
            document.getElementById('edit_existing_val').value = btnTgt.dataset.iconval;
            
            if (btnTgt.dataset.icontype === 'font') {
                document.getElementById('edit_font_val').value = btnTgt.dataset.iconval;
                document.getElementById('edit_img_preview').innerHTML = '';
            } else {
                document.getElementById('edit_font_val').value = '';
                document.getElementById('edit_img_preview').innerHTML = `<img src="<?php echo ASSETS_URL; ?>/images/${btnTgt.dataset.iconval}" style="height:40px; width:40px; object-fit:contain;" class="bg-light p-1 rounded">`;
            }
            
            toggleIconType('edit');
            new mdb.Modal(document.getElementById('editFeatureModal')).show();
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>

