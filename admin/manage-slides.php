<?php
include 'admin_header.php';

// Create slides directory if not exists (deploy-safe location)
$upload_dir = '../uploads/images/slider/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
// Also ensure old location exists for backward compat reads
$old_upload_dir = '../assets/images/slider/';
if (!file_exists($old_upload_dir)) {
    mkdir($old_upload_dir, 0777, true);
}

// Handle Slide Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD SLIDE
    if ($action === 'add') {
        $bg_type = $conn->real_escape_string($_POST['bg_type']);
        $bg_color = $conn->real_escape_string($_POST['bg_color']);
        $overlay_color = $conn->real_escape_string($_POST['overlay_color']);
        $title = $conn->real_escape_string($_POST['title']);
        $subtitle = $conn->real_escape_string($_POST['subtitle']);
        $description = $conn->real_escape_string($_POST['description']);
        $btn_primary_text = $conn->real_escape_string($_POST['btn_primary_text']);
        $btn_primary_link = $conn->real_escape_string($_POST['btn_primary_link']);
        $btn_primary_style = $conn->real_escape_string($_POST['btn_primary_style']);
        $btn_secondary_text = $conn->real_escape_string($_POST['btn_secondary_text']);
        $btn_secondary_link = $conn->real_escape_string($_POST['btn_secondary_link']);
        $btn_secondary_style = $conn->real_escape_string($_POST['btn_secondary_style']);
        $content_alignment = $conn->real_escape_string($_POST['content_alignment']);
        $text_animation = $conn->real_escape_string($_POST['text_animation']);
        $animation_duration = intval($_POST['animation_duration']);
        $device_visibility = $conn->real_escape_string($_POST['device_visibility']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Get max order
        $order_q = $conn->query("SELECT MAX(display_order) as max_order FROM hero_slides");
        $max_order = $order_q->fetch_assoc()['max_order'] ?? 0;
        $display_order = $max_order + 1;

        $media_path = '';
        if (($bg_type === 'image' || $bg_type === 'video') && isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
            $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
            $media_path = uniqid('slide_') . '.' . $ext;
            move_uploaded_file($_FILES['media']['tmp_name'], $upload_dir . $media_path);
        }

        $sql = "INSERT INTO hero_slides (bg_type, media_path, bg_color, overlay_color, title, subtitle, description, 
                btn_primary_text, btn_primary_link, btn_primary_style, btn_secondary_text, btn_secondary_link, 
                btn_secondary_style, content_alignment, text_animation, animation_duration, device_visibility, 
                display_order, is_active) 
                VALUES ('$bg_type', '$media_path', '$bg_color', '$overlay_color', '$title', '$subtitle', '$description', 
                '$btn_primary_text', '$btn_primary_link', '$btn_primary_style', '$btn_secondary_text', '$btn_secondary_link', 
                '$btn_secondary_style', '$content_alignment', '$text_animation', $animation_duration, '$device_visibility', 
                $display_order, $is_active)";

        if ($conn->query($sql)) {
            $success = "Slide added successfully!";
        } else {
            $error = "Error adding slide: " . $conn->error;
        }
    }

    // EDIT SLIDE
    elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $bg_type = $conn->real_escape_string($_POST['bg_type']);
        $bg_color = $conn->real_escape_string($_POST['bg_color']);
        $overlay_color = $conn->real_escape_string($_POST['overlay_color']);
        $title = $conn->real_escape_string($_POST['title']);
        $subtitle = $conn->real_escape_string($_POST['subtitle']);
        $description = $conn->real_escape_string($_POST['description']);
        $btn_primary_text = $conn->real_escape_string($_POST['btn_primary_text']);
        $btn_primary_link = $conn->real_escape_string($_POST['btn_primary_link']);
        $btn_primary_style = $conn->real_escape_string($_POST['btn_primary_style']);
        $btn_secondary_text = $conn->real_escape_string($_POST['btn_secondary_text']);
        $btn_secondary_link = $conn->real_escape_string($_POST['btn_secondary_link']);
        $btn_secondary_style = $conn->real_escape_string($_POST['btn_secondary_style']);
        $content_alignment = $conn->real_escape_string($_POST['content_alignment']);
        $text_animation = $conn->real_escape_string($_POST['text_animation']);
        $animation_duration = intval($_POST['animation_duration']);
        $device_visibility = $conn->real_escape_string($_POST['device_visibility']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $media_query = "";
        if (($bg_type === 'image' || $bg_type === 'video') && isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
            // Delete old media — check both possible locations
            $old_q = $conn->query("SELECT media_path FROM hero_slides WHERE id=$id");
            if ($old_q && $old = $old_q->fetch_assoc()) {
                if ($old['media_path']) {
                    if (file_exists($upload_dir . $old['media_path'])) unlink($upload_dir . $old['media_path']);
                    elseif (file_exists($old_upload_dir . $old['media_path'])) unlink($old_upload_dir . $old['media_path']);
                }
            }

            $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
            $media_path = uniqid('slide_') . '.' . $ext;
            move_uploaded_file($_FILES['media']['tmp_name'], $upload_dir . $media_path);
            $media_query = ", media_path='$media_path'";
        }

        $sql = "UPDATE hero_slides SET 
                bg_type='$bg_type', bg_color='$bg_color', overlay_color='$overlay_color', title='$title', subtitle='$subtitle', 
                description='$description', btn_primary_text='$btn_primary_text', btn_primary_link='$btn_primary_link', 
                btn_primary_style='$btn_primary_style', btn_secondary_text='$btn_secondary_text', btn_secondary_link='$btn_secondary_link', 
                btn_secondary_style='$btn_secondary_style', content_alignment='$content_alignment', text_animation='$text_animation', 
                animation_duration=$animation_duration, device_visibility='$device_visibility', is_active=$is_active
                $media_query WHERE id=$id";

        if ($conn->query($sql)) {
            $success = "Slide updated successfully!";
        } else {
            $error = "Error updating slide: " . $conn->error;
        }
    }

    // DELETE SLIDE
    elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $old_q = $conn->query("SELECT media_path FROM hero_slides WHERE id=$id");
        if ($old_q && $old = $old_q->fetch_assoc()) {
            if ($old['media_path']) {
                // Check both possible storage locations (backward compatible)
                if (file_exists($upload_dir . $old['media_path'])) unlink($upload_dir . $old['media_path']);
                elseif (file_exists($old_upload_dir . $old['media_path'])) unlink($old_upload_dir . $old['media_path']);
            }
        }
        $conn->query("DELETE FROM hero_slides WHERE id=$id");
        $success = "Slide deleted successfully!";
    }

    // REORDER SLIDES
    elseif ($action === 'reorder') {
        $order_list = explode(',', $_POST['order_data']);
        foreach ($order_list as $index => $id) {
            $id = intval($id);
            $order = $index + 1;
            $conn->query("UPDATE hero_slides SET display_order=$order WHERE id=$id");
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Fetch all slides
$slides = $conn->query("SELECT * FROM hero_slides ORDER BY display_order ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800"><i class="fas fa-images me-2"></i>Manage Slides</h2>
    <div>
        <a href="hero-slider-settings.php" class="btn btn-secondary btn-custom me-2"><i class="fas fa-cog me-2"></i>Global Settings</a>
        <button class="btn btn-primary btn-custom px-4" data-mdb-toggle="modal" data-mdb-target="#slideModal" onclick="resetSlideForm()"><i class="fas fa-plus me-2"></i>Add Slide</button>
    </div>
</div>

<?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info py-2"><i class="fas fa-info-circle me-2"></i>Drag and drop the rows to reorder how slides appear on the frontend.</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="slidesTable">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3" style="width: 50px;"></th>
                        <th class="py-3">Background</th>
                        <th class="py-3">Content Preview</th>
                        <th class="py-3 text-center">Visibility</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="sortable-slides">
                    <?php if($slides && $slides->num_rows > 0): ?>
                        <?php while($slide = $slides->fetch_assoc()): ?>
                        <tr data-id="<?php echo $slide['id']; ?>" class="slide-row" style="cursor: move;">
                            <td class="px-4 text-muted"><i class="fas fa-grip-vertical"></i></td>
                            <td>
                                <?php if($slide['bg_type'] === 'image' && $slide['media_path']): ?>
                                    <img src="<?php echo resolve_image_url($slide['media_path']); ?>" style="width: 80px; height: 50px; object-fit: cover;" class="rounded" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                <?php elseif($slide['bg_type'] === 'video' && $slide['media_path']): ?>
                                    <div class="bg-dark text-white rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 50px;"><i class="fas fa-video"></i></div>
                                <?php else: ?>
                                    <div class="rounded border" style="width: 80px; height: 50px; background: <?php echo htmlspecialchars($slide['bg_color']); ?>;"></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($slide['title']) ?: '(No Title)'; ?></strong><br>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo htmlspecialchars($slide['subtitle']); ?></small>
                            </td>
                            <td class="text-center">
                                <?php if($slide['device_visibility'] === 'desktop'): ?> <i class="fas fa-desktop text-primary" title="Desktop Only"></i>
                                <?php elseif($slide['device_visibility'] === 'mobile'): ?> <i class="fas fa-mobile-alt text-primary" title="Mobile Only"></i>
                                <?php else: ?> <i class="fas fa-laptop text-primary me-1"></i><i class="fas fa-mobile-alt text-primary" title="All Devices"></i> <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($slide['is_active']): ?>
                                    <span class="badge bg-success rounded-pill">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm btn-custom px-3 edit-slide-btn" data-slide="<?php echo htmlspecialchars(json_encode($slide)); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this slide?');">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-custom px-3"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No slides found. Click "Add Slide" to begin.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide Modal (Add/Edit) -->
<div class="modal fade" id="slideModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 px-4 py-3">
                <h5 class="modal-title fw-bold" id="slideModalTitle"><i class="fas fa-image me-2"></i>Add Slide</h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
            </div>
            <form id="slideForm" method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="slideId">
                <div class="modal-body p-4 bg-light">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="slideTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active fw-bold" id="bg-tab" data-mdb-toggle="tab" href="#bg-content" role="tab">Background</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link fw-bold" id="text-tab" data-mdb-toggle="tab" href="#text-content" role="tab">Content & Text</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link fw-bold" id="btn-tab" data-mdb-toggle="tab" href="#btn-content" role="tab">Buttons</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link fw-bold" id="adv-tab" data-mdb-toggle="tab" href="#adv-content" role="tab">Settings</a>
                        </li>
                    </ul><!-- Tab Content -->
                    <div class="tab-content bg-white p-4 rounded border shadow-sm" id="slideTabContent">
                        
                        <!-- Background Tab -->
                        <div class="tab-pane fade show active" id="bg-content" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Background Type</label>
                                    <select name="bg_type" id="bg_type" class="form-select bg-light" required onchange="toggleBgInputs()">
                                        <option value="image">Image Media</option>
                                        <option value="video">Video (MP4)</option>
                                        <option value="color">Solid Color</option>
                                        <option value="gradient">Gradient CSS</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Overlay Color (RGBA)</label>
                                    <input type="text" name="overlay_color" id="overlay_color" class="form-control bg-light" placeholder="rgba(0,0,0,0.5)" value="rgba(0,0,0,0.4)">
                                    <div class="form-text">Used to darken media to make text readable.</div>
                                </div>
                            </div>
                            <div class="row" id="media_input_row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Upload Media <small class="text-muted">(Image or Video)</small></label>
                                    <input type="file" name="media" id="media_file" class="form-control bg-light" accept="image/*,video/mp4">
                                    <div class="form-text mt-1" id="media_help_text">Recommended size: 1920x800px. Leave empty during edit to keep current media.</div>
                                </div>
                            </div>
                            <div class="row" id="color_input_row" style="display:none;">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Background Color / Gradient</label>
                                    <input type="text" name="bg_color" id="bg_color" class="form-control bg-light" placeholder="#123456 or linear-gradient(135deg, #f6d365, #fda085)">
                                </div>
                            </div>
                        </div>

                        <!-- Content Tab -->
                        <div class="tab-pane fade" id="text-content" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Main Title (H1)</label>
                                <input type="text" name="title" id="title" class="form-control form-control-lg bg-light" placeholder="Premium Hero Title">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Subtitle</label>
                                <input type="text" name="subtitle" id="subtitle" class="form-control bg-light" placeholder="Catchy subtitle here">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" id="description" class="form-control bg-light" rows="3" placeholder="A short descriptive paragraph supporting the hero..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Content Alignment</label>
                                <select name="content_alignment" id="content_alignment" class="form-select bg-light">
                                    <option value="left" selected>Left Aligned</option>
                                    <option value="center">Center Aligned</option>
                                    <option value="right">Right Aligned</option>
                                </select>
                            </div>
                        </div>

                        <!-- Buttons Tab -->
                        <div class="tab-pane fade" id="btn-content" role="tabpanel">
                            <div class="row border-bottom pb-4 mb-4">
                                <div class="col-12"><h6 class="fw-bold text-primary">Primary Button</h6></div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Text</label>
                                    <input type="text" name="btn_primary_text" id="btn_primary_text" class="form-control bg-light" placeholder="Shop Now">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Link (URL)</label>
                                    <input type="text" name="btn_primary_link" id="btn_primary_link" class="form-control bg-light" placeholder="/products.php">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Style</label>
                                    <select name="btn_primary_style" id="btn_primary_style" class="form-select bg-light">
                                        <option value="primary">Solid Primary</option>
                                        <option value="light">Solid Light</option>
                                        <option value="dark">Solid Dark</option>
                                        <option value="outline-light">Outline Light</option>
                                        <option value="outline-dark">Outline Dark</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12"><h6 class="fw-bold text-secondary">Secondary Button <small>(Optional)</small></h6></div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Text</label>
                                    <input type="text" name="btn_secondary_text" id="btn_secondary_text" class="form-control bg-light" placeholder="Learn More">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Link (URL)</label>
                                    <input type="text" name="btn_secondary_link" id="btn_secondary_link" class="form-control bg-light" placeholder="#about">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold">Style</label>
                                    <select name="btn_secondary_style" id="btn_secondary_style" class="form-select bg-light">
                                        <option value="outline-light" selected>Outline Light</option>
                                        <option value="primary">Solid Primary</option>
                                        <option value="light">Solid Light</option>
                                        <option value="dark">Solid Dark</option>
                                        <option value="outline-dark">Outline Dark</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Tab -->
                        <div class="tab-pane fade" id="adv-content" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Text Entrance Animation</label>
                                    <select name="text_animation" id="text_animation" class="form-select bg-light">
                                        <option value="fade">Fade In</option>
                                        <option value="slide_up">Slide Up</option>
                                        <option value="zoom_in">Zoom In</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Animation Duration (ms)</label>
                                    <input type="number" name="animation_duration" id="animation_duration" class="form-control bg-light" value="1000">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Device Visibility</label>
                                    <select name="device_visibility" id="device_visibility" class="form-select bg-light">
                                        <option value="all">Show on All Devices</option>
                                        <option value="desktop">Desktop & Tablet Only</option>
                                        <option value="mobile">Mobile Phones Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3 d-flex align-items-center pt-4">
                                    <div class="form-check form-switch form-switch-lg">
                                        <input class="form-check-input mt-0" type="checkbox" name="is_active" id="is_active" style="height: 25px; width: 50px;" checked>
                                        <label class="form-check-label fw-bold ms-3" for="is_active">Active Slide</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light btn-lg flex-grow-1" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1" id="saveBtn">Save Slide</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SortableJS for Drag and Drop Reordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function toggleBgInputs() {
    const type = document.getElementById('bg_type').value;
    if(type === 'image' || type === 'video') {
        document.getElementById('media_input_row').style.display = 'flex';
        document.getElementById('color_input_row').style.display = 'none';
        document.getElementById('media_help_text').innerText = type === 'video' ? 'Select an MP4 video file. (Max 10MB recommended)' : 'Recommended size: 1920x800px. Optimize images for fast loading.';
    } else {
        document.getElementById('media_input_row').style.display = 'none';
        document.getElementById('color_input_row').style.display = 'flex';
    }
}

function resetSlideForm() {
    document.getElementById('slideForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('slideId').value = '';
    document.getElementById('slideModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add Slide';
    document.getElementById('saveBtn').innerText = 'Add Slide';
    document.getElementById('is_active').checked = true;
    toggleBgInputs();
}

document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal Binding
    document.querySelectorAll('.edit-slide-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.slide);
            resetSlideForm();
            
            document.getElementById('formAction').value = 'edit';
            document.getElementById('slideId').value = data.id;
            document.getElementById('slideModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Slide';
            document.getElementById('saveBtn').innerText = 'Update Slide';
            
            // Map Data
            document.getElementById('bg_type').value = data.bg_type;
            document.getElementById('bg_color').value = data.bg_color;
            document.getElementById('overlay_color').value = data.overlay_color;
            document.getElementById('title').value = data.title;
            document.getElementById('subtitle').value = data.subtitle;
            document.getElementById('description').value = data.description;
            // Buttons
            document.getElementById('btn_primary_text').value = data.btn_primary_text;
            document.getElementById('btn_primary_link').value = data.btn_primary_link;
            document.getElementById('btn_primary_style').value = data.btn_primary_style;
            document.getElementById('btn_secondary_text').value = data.btn_secondary_text;
            document.getElementById('btn_secondary_link').value = data.btn_secondary_link;
            document.getElementById('btn_secondary_style').value = data.btn_secondary_style;
            // Advanced
            document.getElementById('content_alignment').value = data.content_alignment;
            document.getElementById('text_animation').value = data.text_animation;
            document.getElementById('animation_duration').value = data.animation_duration;
            document.getElementById('device_visibility').value = data.device_visibility;
            document.getElementById('is_active').checked = parseInt(data.is_active) === 1;
            
            toggleBgInputs();
            new mdb.Modal(document.getElementById('slideModal')).show();
        });
    });

    // Sortable JS Init
    const tbody = document.getElementById('sortable-slides');
    if (tbody) {
        new Sortable(tbody, {
            animation: 150,
            handle: '.slide-row',
            ghostClass: 'bg-light',
            onEnd: function() {
                const orderData = Array.from(tbody.querySelectorAll('.slide-row')).map(row => row.dataset.id).join(',');
                fetch('manage-slides.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reorder&order_data=' + orderData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // Toast or small notification can go here
                        console.log('Order updated');
                    }
                });
            }
        });
    }
});
</script>

<?php include 'admin_footer.php'; ?>

