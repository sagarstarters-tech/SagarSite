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
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image);
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
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image)) {
                 $img_q = $conn->query("SELECT image FROM categories WHERE id=$id")->fetch_assoc();
                 if ($img_q && $img_q['image']) {
                     $old_cat_img = $conn->real_escape_string($img_q['image']);
                     // Safety: only delete if not shared with any product, product_images, or banner
                     $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                     $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                     $ban_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_cat_img'")->fetch_assoc()['c'];
                     $other_cat  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$old_cat_img' AND id!=$id")->fetch_assoc()['c'];
                     if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $other_cat === 0 && file_exists('../assets/images/'.$img_q['image'])) {
                         unlink('../assets/images/'.$img_q['image']);
                     }
                 }
                 $image_query = ", image='$image'";
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
        if ($img_q && $img_q['image']) {
            $old_cat_img = $conn->real_escape_string($img_q['image']);
            $prod_refs  = (int)$conn->query("SELECT COUNT(*) as c FROM products WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $gal_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM product_images WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $ban_refs   = (int)$conn->query("SELECT COUNT(*) as c FROM banners WHERE image='$old_cat_img'")->fetch_assoc()['c'];
            $other_cat  = (int)$conn->query("SELECT COUNT(*) as c FROM categories WHERE image='$old_cat_img' AND id!=$id")->fetch_assoc()['c'];
            if ($prod_refs === 0 && $gal_refs === 0 && $ban_refs === 0 && $other_cat === 0 && file_exists('../assets/images/'.$img_q['image'])) {
                unlink('../assets/images/'.$img_q['image']);
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

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Add New Category</h5>
                <?php if(isset($success)): ?>
                    <div class="alert alert-success py-2"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Slug (URL)</label>
                        <input type="text" name="slug" class="form-control" placeholder="auto-generated if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">SEO Title</label>
                        <input type="text" name="seo_title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">SEO Description</label>
                        <textarea name="seo_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Category Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="mt-2">
                            <span class="badge bg-primary px-3 py-2 shadow-sm rounded-pill"><i class="fas fa-crop-alt me-2"></i>Rec. Size: 800x800px (1:1 Ratio)</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-custom w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Manage Categories</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($c = $categories_res->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($c['image'])): ?>
                                            <img src="../assets/images/<?php echo htmlspecialchars($c['image']); ?>" class="rounded me-3 object-fit-cover" style="width: 40px; height: 40px;">
                                        <?php else: ?>
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-folder text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span>#<?php echo $c['id']; ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td class="text-end pe-4">
                                    <div class="action-btns">
                                        <button class="btn btn-primary btn-sm btn-custom px-3 edit-category-btn" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                            data-slug="<?php echo htmlspecialchars($c['slug'] ?? ''); ?>"
                                            data-seo-title="<?php echo htmlspecialchars($category_seo[$c['id']]['meta_title'] ?? ''); ?>"
                                            data-seo-description="<?php echo htmlspecialchars($category_seo[$c['id']]['meta_description'] ?? ''); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this category? All related products will also be deleted!');">
    <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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
