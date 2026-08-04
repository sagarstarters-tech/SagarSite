<?php
include 'admin_header.php';

// Handle Add/Edit Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new banner
    if ($action === 'add') {
        $heading = $conn->real_escape_string($_POST['heading']);
        $subheading = $conn->real_escape_string($_POST['subheading']);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid('banner_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image);
        }
        
        if ($image) {
            $conn->query("INSERT INTO banners (image, heading, subheading, status) VALUES ('$image', '$heading', '$subheading', '$status')");
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
            // Get old image to delete (only if not shared with products/gallery)
            $old_q = $conn->query("SELECT image FROM banners WHERE id=$id");
            if ($old_img = $old_q->fetch_assoc()) {
                $old_banner_img = $conn->real_escape_string($old_img['image']);
                $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_banner_img'")->fetch_assoc()['c'];
                $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_banner_img'")->fetch_assoc()['c'];
                $other_ban  = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_banner_img' AND id!=$id")->fetch_assoc()['c'];
                if ($prod_refs === 0 && $gal_refs === 0 && $other_ban === 0 && file_exists('../assets/images/' . $old_img['image'])) {
                    unlink('../assets/images/' . $old_img['image']);
                }
            }
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid('banner_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image);
            $image_query = ", image='$image'";
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
        if ($prod_refs === 0 && $gal_refs === 0 && $other_ban === 0 && file_exists('../assets/images/' . $old_img['image'])) {
            unlink('../assets/images/' . $old_img['image']);
        }
    }
    $conn->query("DELETE FROM banners WHERE id=$id");
    header("Location: manage_banners.php?success=Banner deleted successfully");
    exit;
}

// Fetch all banners
$banners = $conn->query("SELECT * FROM banners ORDER BY created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Banner & Slider Management</h2>
    <button class="btn btn-primary btn-custom" data-mdb-toggle="modal" data-mdb-target="#addBannerModal"><i class="fas fa-plus me-2"></i>Add New Banner</button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Image</th>
                        <th class="py-3">Details</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($banners && $banners->num_rows > 0): ?>
                        <?php while($banner = $banners->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4">
                                <img src="<?php echo ASSETS_URL; ?>/images/<?php echo htmlspecialchars($banner['image']); ?>" class="img-thumbnail" style="width: 120px; height: 60px; object-fit: contain; background-color: #fff;" alt="Banner">
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

<?php include 'admin_footer.php'; ?>

