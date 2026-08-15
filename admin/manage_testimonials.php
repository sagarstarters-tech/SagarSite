<?php
/**
 * Manage Testimonials
 */
include 'admin_header.php';

// Prepare DB Table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(255) NOT NULL,
    `designation` VARCHAR(255) DEFAULT NULL,
    `testimonial` TEXT NOT NULL,
    `rating` TINYINT(1) DEFAULT 5,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// ── Handle Add / Edit (POST) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = intval($_POST['testimonial_id'] ?? 0);
        $client_name = trim($_POST['client_name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $testimonial = trim($_POST['testimonial'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $image_url = $_POST['existing_image'] ?? '';
        
        // Handle File Upload
        if (!empty($_FILES['client_image']['name']) && $_FILES['client_image']['error'] === 0) {
            // Save to uploads/images/testimonials/ — deploy-safe (not wiped by git deployment)
            $upload_dir = '../uploads/images/testimonials/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['client_image']['name'], PATHINFO_EXTENSION);
            $filename = 'tst_' . time() . '_' . rand(100,999) . '.' . $ext;
            
            if (move_uploaded_file($_FILES['client_image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'uploads/images/testimonials/' . $filename;
            }
        }
        
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE testimonials SET client_name=?, designation=?, testimonial=?, rating=?, image_url=?, is_active=? WHERE id=?");
            $stmt->bind_param('sssissi', $client_name, $designation, $testimonial, $rating, $image_url, $is_active, $id);
            $stmt->execute();
            set_flash('success', 'Testimonial updated successfully.');
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (client_name, designation, testimonial, rating, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssisi', $client_name, $designation, $testimonial, $rating, $image_url, $is_active);
            $stmt->execute();
            set_flash('success', 'Testimonial added successfully.');
        }
        header("Location: manage_testimonials.php");
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['testimonial_id']);
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        set_flash('success', 'Testimonial deleted.');
        header("Location: manage_testimonials.php");
        exit;
    }
    
    if ($action === 'toggle_active') {
        $id = intval($_POST['testimonial_id']);
        $status = intval($_POST['status']);
        $stmt = $conn->prepare("UPDATE testimonials SET is_active=? WHERE id=?");
        $stmt->bind_param('ii', $status, $id);
        $stmt->execute();
        exit;
    }
}

// ── Fetch Testimonials ────────────────────────────
$res = $conn->query("SELECT * FROM testimonials ORDER BY id DESC");
?>

<style>
    .btn-outline-primary {
        background-color: #eff6ff !important;
        border: 1.5px solid #3b82f6 !important;
        color: #1d4ed8 !important;
        font-weight: 600 !important;
    }
    .btn-outline-primary:hover {
        background-color: #2563eb !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
    }
    .btn-outline-primary:hover i {
        color: #ffffff !important;
    }
    .btn-outline-danger {
        background-color: #fef2f2 !important;
        border: 1.5px solid #ef4444 !important;
        color: #dc2626 !important;
        font-weight: 600 !important;
    }
    .btn-outline-danger:hover {
        background-color: #dc2626 !important;
        color: #ffffff !important;
        border-color: #dc2626 !important;
    }
    .btn-outline-danger i {
        color: #dc2626 !important;
    }
    .btn-outline-danger:hover i {
        color: #ffffff !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 fw-bold"><i class="fas fa-comment-dots text-primary me-2"></i>Manage Testimonials</h2>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openTestimonialModal()"><i class="fas fa-plus me-2"></i>Add Testimonial</button>
</div>

<?php render_flash(); ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Client</th>
                        <th>Designation</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <?php if($row['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" class="rounded-circle me-3" style="width:40px; height:40px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" style="width:40px; height:40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['client_name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['designation'] ?: '-'); ?></td>
                            <td>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $row['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" onchange="toggleActive(<?php echo $row['id']; ?>, this.checked)" <?php echo $row['is_active'] ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-2 fw-semibold" title="Edit Testimonial" onclick='editTestimonial(<?php echo json_encode($row); ?>)'>
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this testimonial?');">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="testimonial_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" title="Delete Testimonial">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No testimonials found. Add some!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Testimonial Modal -->
<div class="modal fade" id="testimonialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="testimonial_id" id="t_id" value="0">
            <input type="hidden" name="existing_image" id="t_existing_image" value="">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Testimonial</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Client Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_name" id="t_name" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Designation / Company</label>
                    <input type="text" name="designation" id="t_designation" class="form-control rounded-3" placeholder="e.g. CEO, Example Corp">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Testimonial Message <span class="text-danger">*</span></label>
                    <textarea name="testimonial" id="t_message" rows="4" class="form-control rounded-3" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Rating (1-5)</label>
                        <select name="rating" id="t_rating" class="form-select rounded-3">
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Terrible</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Client Image / Avatar</label>
                        <input type="file" name="client_image" id="t_image" class="form-control rounded-3" accept="image/*">
                        <small class="text-muted d-block mt-1">Recommended: 150x150px square image.</small>
                    </div>
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="t_active" checked>
                    <label class="form-check-label" for="t_active">Active (Show on homepage)</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-mdb-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Testimonial</button>
            </div>
        </form>
    </div>
</div>

<script>
let tModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal instance using MDB
    const modalEl = document.getElementById('testimonialModal');
    if (modalEl) {
        tModal = new mdb.Modal(modalEl);
    }
});

function openTestimonialModal() {
    if (!tModal) {
        const modalEl = document.getElementById('testimonialModal');
        if (modalEl) tModal = new mdb.Modal(modalEl);
        else return;
    }
    document.getElementById('modalTitle').innerText = 'Add Testimonial';
    document.getElementById('t_id').value = '0';
    document.getElementById('t_existing_image').value = '';
    document.getElementById('t_name').value = '';
    document.getElementById('t_designation').value = '';
    document.getElementById('t_message').value = '';
    document.getElementById('t_rating').value = '5';
    document.getElementById('t_active').checked = true;
    tModal.show();
}

function editTestimonial(data) {
    if (!tModal) {
        const modalEl = document.getElementById('testimonialModal');
        if (modalEl) tModal = new mdb.Modal(modalEl);
        else return;
    }
    document.getElementById('modalTitle').innerText = 'Edit Testimonial';
    document.getElementById('t_id').value = data.id;
    document.getElementById('t_existing_image').value = data.image_url;
    document.getElementById('t_name').value = data.client_name;
    document.getElementById('t_designation').value = data.designation;
    document.getElementById('t_message').value = data.testimonial;
    document.getElementById('t_rating').value = data.rating;
    document.getElementById('t_active').checked = (data.is_active == 1);
    tModal.show();
}

function toggleActive(id, isChecked) {
    fetch('manage_testimonials.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'action': 'toggle_active',
            'testimonial_id': id,
            'status': isChecked ? 1 : 0,
            'csrf_token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
        })
    });
}
</script>

<?php include 'admin_footer.php'; ?>
