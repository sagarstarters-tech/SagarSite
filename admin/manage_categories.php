<?php
include 'admin_header.php';
require_once '../includes/SeoRepository.php';
$seoRepo = new SeoRepository($conn);

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $slug = $conn->real_escape_string(strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9-]+/', '-', $_POST['slug'] ?? ''))));
        if (empty($slug)) $slug = time() . '-' . substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), 0, 50);
        
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = 'cat_' . uniqid() . '.' . $ext;
            // Save to uploads/media/images/ — consistent with media library
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_target . $fname)) {
                $image = 'uploads/media/images/' . $fname; // store full relative path
            }
        }

        $conn->query("INSERT INTO categories (name, slug, image) VALUES ('$name', '$slug', '$image')");
        $category_id = $conn->insert_id;
        
        // Save SEO Metadata
        $seoRepo->saveMetadata([
            'entity_type' => 'category',
            'entity_id' => $category_id,
            'meta_title' => $_POST['seo_title'] ?? '',
            'meta_description' => $_POST['seo_description'] ?? ''
        ]);
        
        $success = "Category added successfully.";
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $slug = $conn->real_escape_string(strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9-]+/', '-', $_POST['slug'] ?? ''))));
        
        $image_query = "";
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext   = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = 'cat_' . uniqid() . '.' . $ext;
            // Save to uploads/media/images/ — consistent with media library
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_target . $fname)) {
                $new_image_path = 'uploads/media/images/' . $fname;

                // Delete old category image safely
                $img_q = $conn->query("SELECT image FROM categories WHERE id=$id")->fetch_assoc();
                if ($img_q && !empty($img_q['image'])) {
                    $old_cat_img = $conn->real_escape_string($img_q['image']);
                    // Safety: only delete if not shared anywhere else
                    $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                    $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                    $ban_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                    $other_cat  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$old_cat_img' AND id!=$id")->fetch_assoc()['c'];
                    if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $other_cat === 0) {
                        // Try both old paths for backward compatibility
                        $old_path1 = '../' . ltrim($img_q['image'], '/');
                        $old_path2 = '../uploads/images/' . basename($img_q['image']);
                        $old_path3 = '../assets/images/' . basename($img_q['image']);
                        foreach ([$old_path1, $old_path2, $old_path3] as $try_path) {
                            if (file_exists($try_path)) { @unlink($try_path); break; }
                        }
                    }
                }
                $esc_new = $conn->real_escape_string($new_image_path);
                $image_query = ", image='$esc_new'";
            }
        }
        
        $conn->query("UPDATE categories SET name='$name', slug='$slug' $image_query WHERE id=$id");
        
        // Save SEO Metadata
        $seoRepo->saveMetadata([
            'entity_type' => 'category',
            'entity_id' => $id,
            'meta_title' => $_POST['seo_title'] ?? '',
            'meta_description' => $_POST['seo_description'] ?? ''
        ]);

        $success = "Category updated successfully.";
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Remove image (safety: only if not shared with any product, product_images, or banner)
        $img_q = $conn->query("SELECT image FROM categories WHERE id=$id")->fetch_assoc();
        if ($img_q && !empty($img_q['image'])) {
            $old_cat_img = $conn->real_escape_string($img_q['image']);
            $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $ban_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $other_cat  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$old_cat_img' AND id!=$id")->fetch_assoc()['c'];
            if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $other_cat === 0) {
                // Try all possible storage locations (backward compatible)
                $old_path1 = '../' . ltrim($img_q['image'], '/');
                $old_path2 = '../uploads/images/' . basename($img_q['image']);
                $old_path3 = '../assets/images/' . basename($img_q['image']);
                foreach ([$old_path1, $old_path2, $old_path3] as $try_path) {
                    if (file_exists($try_path)) { @unlink($try_path); break; }
                }
            }
        }
        
        $conn->query("DELETE FROM categories WHERE id=$id");
        $success = "Category deleted successfully.";
    }
}

$categories_res = $conn->query("SELECT * FROM categories ORDER BY id DESC");

// Fetch SEO metadata for categories
$category_seo = [];
$seo_q = $conn->query("SELECT * FROM seo_metadata WHERE entity_type='category'");
if ($seo_q) {
    while ($s = $seo_q->fetch_assoc()) {
        $category_seo[$s['entity_id']] = $s;
    }
}
?>

<style>
.mc-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 20px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
}
.mc-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.mc-thumb {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    flex-shrink: 0;
}
</style>

<div class="container-fluid py-3">

    <!-- Hero Header Banner -->
    <div class="mc-hero">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 rounded-pill px-3 py-1 small">
                        <i class="fas fa-tags me-1"></i> Catalog Structure
                    </span>
                    <span class="text-white-50 small"><?php echo $categories_res->num_rows; ?> active categories</span>
                </div>
                <h3 class="fw-bold mb-0 text-white">Category Management Hub</h3>
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success rounded-3 py-2 px-3 mb-3 shadow-sm"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add Category Form Card -->
        <div class="col-lg-4">
            <div class="mc-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Category</h5>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Category Name</label>
                        <input type="text" name="name" class="form-control rounded-3" required placeholder="e.g. Submersible Starters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Slug (URL)</label>
                        <input type="text" name="slug" class="form-control rounded-3" placeholder="auto-generated if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SEO Title</label>
                        <input type="text" name="seo_title" class="form-control rounded-3" placeholder="Page Title for Google">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SEO Description</label>
                        <textarea name="seo_description" class="form-control rounded-3" rows="2" placeholder="Meta description for search engines"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Category Image</label>
                        <input type="file" name="image" class="form-control rounded-3" accept="image/*">
                        <div class="mt-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 small"><i class="fas fa-crop-alt me-1"></i>Rec. Size: 800x800px (1:1 Ratio)</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100 rounded-3 py-2 shadow-sm"><i class="fas fa-plus me-2"></i>Add Category</button>
                </form>
            </div>
        </div>

        <!-- Categories List Table Card -->
        <div class="col-lg-8">
            <div class="mc-card">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i>Active Categories List</h6>
                    <span class="badge bg-secondary text-white"><?php echo $categories_res->num_rows; ?> Total</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Category Details</th>
                                <th>Slug / URL</th>
                                <th class="pe-4 text-end">Quick Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($categories_res && $categories_res->num_rows > 0): ?>
                                <?php 
                                // Re-query or seek to 0 if needed
                                $categories_res->data_seek(0);
                                while($c = $categories_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($c['image'])): ?>
                                                <img src="<?php echo resolve_image_url($c['image']); ?>" class="mc-thumb" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                            <?php else: ?>
                                                <div class="mc-thumb d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-folder text-secondary fs-5"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($c['name']); ?></div>
                                                <small class="text-muted">ID: #<?php echo $c['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1"><i class="fas fa-link me-1 text-muted"></i><?php echo htmlspecialchars($c['slug'] ?? ''); ?></span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <button class="btn btn-primary btn-sm rounded-3 px-2 py-1 edit-category-btn" 
                                                data-id="<?php echo $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-slug="<?php echo htmlspecialchars($c['slug'] ?? ''); ?>"
                                                data-seo-title="<?php echo htmlspecialchars($category_seo[$c['id']]['meta_title'] ?? ''); ?>"
                                                data-seo-description="<?php echo htmlspecialchars($category_seo[$c['id']]['meta_description'] ?? ''); ?>"
                                                title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this category? All related products will also be deleted!');">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm rounded-3 px-2 py-1" title="Delete Category"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">No categories created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="editCategoryModalLabel">Edit Category</h5>
        <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_c_id">
            <div class="mb-3">
                <label class="form-label fw-bold">Category Name</label>
                <input type="text" name="name" id="edit_c_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Slug (URL)</label>
                <input type="text" name="slug" id="edit_c_slug" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">SEO Title</label>
                <input type="text" name="seo_title" id="edit_c_seo_title" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">SEO Description</label>
                <textarea name="seo_description" id="edit_c_seo_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Category Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <div class="form-text mb-2">Leave empty to keep current image.</div>
                <div>
                    <span class="badge bg-primary px-3 py-2 shadow-sm rounded-pill"><i class="fas fa-crop-alt me-2"></i>Rec. Size: 800x800px (1:1 Ratio)</span>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 pb-4 pe-4">
            <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-custom px-4">Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-category-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_c_id').value = this.dataset.id;
            document.getElementById('edit_c_name').value = this.dataset.name;
            document.getElementById('edit_c_slug').value = this.dataset.slug;
            document.getElementById('edit_c_seo_title').value = this.dataset.seoTitle;
            document.getElementById('edit_c_seo_description').value = this.dataset.seoDescription;
            
            var modal = new mdb.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
