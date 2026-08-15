<?php
$current_page = 'social-media/templates.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT 1 FROM sm_templates LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}

$csrfToken = csrf_token();

// Fetch templates from DB
$stmtTemplates = $pdo->query("SELECT * FROM sm_templates ORDER BY is_default DESC, id DESC");
$templates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold m-0">Caption Templates</h2>
            <p class="text-muted small m-0">Create and manage reusable post caption templates with dynamic variables.</p>
        </div>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnCreateTemplate" data-mdb-toggle="modal" data-mdb-target="#templateModal" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="fas fa-plus me-2"></i> Create Template
        </button>
    </div>

    <?php if (empty($templates)): ?>
        <div class="card shadow border-0 rounded-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3 d-block"></i>
                <h4 class="fw-bold text-secondary">No caption templates created yet</h4>
                <p class="text-muted mb-4">Create your first template with dynamic tags for products, prices, and links.</p>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btnCreateTemplateEmpty" data-mdb-toggle="modal" data-mdb-target="#templateModal" data-bs-toggle="modal" data-bs-target="#templateModal">
                    <i class="fas fa-plus me-2"></i> Create Your First Template
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($templates as $tpl): 
                $isDefault = (int)$tpl['is_default'] === 1;
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card shadow border-0 rounded-4 h-100 sm-card">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-dark m-0"><?php echo htmlspecialchars($tpl['name']); ?></h5>
                                    <?php if ($isDefault): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2">
                                            <i class="fas fa-star me-1"></i> Default
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 bg-light rounded-3 mb-3 border font-monospace small" style="white-space: pre-wrap; max-height: 160px; overflow-y: auto;">
                                    <?php echo htmlspecialchars($tpl['template_body']); ?>
                                </div>
                            </div>
                            <div class="pt-3 border-top d-flex justify-content-between align-items-center gap-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill btn-edit-tpl" 
                                        data-template='<?php echo htmlspecialchars(json_encode($tpl), ENT_QUOTES); ?>'>
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <?php if (!$isDefault): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill btn-set-default" 
                                            data-id="<?php echo $tpl['id']; ?>">
                                        <i class="fas fa-check me-1"></i> Default
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill btn-clone-tpl" 
                                        data-id="<?php echo $tpl['id']; ?>">
                                    <i class="fas fa-copy me-1"></i> Clone
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill btn-delete-tpl" 
                                        title="Delete Template" data-id="<?php echo $tpl['id']; ?>">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Create / Edit Template -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form id="templateForm">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="templateId" value="">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="templateModalLabel">Create New Template</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="mb-3">
                                <label for="tplName" class="form-label fw-bold small text-muted">Template Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="tplName" name="name" 
                                       placeholder="e.g. Premium Promotion Template" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted d-block">
                                    Click Variables to Insert into Template <i class="fas fa-hand-pointer text-primary ms-1"></i>
                                </label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{product_name}">
                                        <i class="fas fa-plus me-1"></i>{product_name}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{price}">
                                        <i class="fas fa-plus me-1"></i>{price}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{sale_price}">
                                        <i class="fas fa-plus me-1"></i>{sale_price}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{regular_price}">
                                        <i class="fas fa-plus me-1"></i>{regular_price}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{product_url}">
                                        <i class="fas fa-plus me-1"></i>{product_url}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{cta}">
                                        <i class="fas fa-plus me-1"></i>{cta}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill var-btn" data-var="{hashtags}">
                                        <i class="fas fa-plus me-1"></i>{hashtags}
                                    </button>
                                </div>
                                <span class="extra-small text-muted">Clicking any button above inserts that tag directly where your cursor is inside the text box.</span>
                            </div>

                            <div class="mb-3">
                                <label for="tplBody" class="form-label fw-bold small text-muted">Template Body Content <span class="text-danger">*</span></label>
                                <textarea class="form-control font-monospace rounded-3" id="tplBody" name="template_body" rows="9" 
                                          placeholder="Write your template text with placeholders..." required></textarea>
                            </div>

                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="tplIsDefault" name="is_default" value="1">
                                <label class="form-check-label fw-bold small" for="tplIsDefault">Set as Default Template for Bulk Posts</label>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="card bg-light border-0 rounded-4 h-100">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3 text-dark">
                                        <i class="fas fa-eye text-primary me-2"></i>Live Post Preview
                                    </h6>
                                    <div class="preview-box p-3 bg-white border rounded-3 shadow-sm" id="livePreviewBox" style="white-space: pre-wrap; font-size: 0.9rem; min-height: 240px; color: #212529;">
                                        Preview will appear here as you type...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnSaveTemplate">
                        <i class="fas fa-save me-1"></i> Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateModalEl = document.getElementById('templateModal');
    const templateForm = document.getElementById('templateForm');
    const tplBody = document.getElementById('tplBody');
    const livePreviewBox = document.getElementById('livePreviewBox');

    function showModalInstance() {
        if (typeof mdb !== 'undefined' && mdb.Modal) {
            const inst = mdb.Modal.getInstance(templateModalEl) || new mdb.Modal(templateModalEl);
            inst.show();
        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(templateModalEl) || new bootstrap.Modal(templateModalEl);
            inst.show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $(templateModalEl).modal('show');
        }
    }

    // Live Preview Rendering Logic
    function updateLivePreview() {
        const text = tplBody.value;
        if (!text.trim()) {
            livePreviewBox.textContent = 'Preview will appear here as you type...';
            return;
        }

        let rendered = text
            .replace(/{product_name}/g, "1 Hp Automatic Digital Submersible Pump Starter")
            .replace(/{price}/g, "1639.00")
            .replace(/{sale_price}/g, "1639.00")
            .replace(/{regular_price}/g, "2499.00")
            .replace(/{product_url}/g, "https://www.sagarstarters.com/product/1-hp-automatic-digital-submersible-pump-starter")
            .replace(/{cta}/g, "Shop Now 🛒")
            .replace(/{hashtags}/g, "#SagarStarters #SubmersiblePump #OnlineShopping");

        livePreviewBox.textContent = rendered;
    }

    if (tplBody) {
        tplBody.addEventListener('input', updateLivePreview);
        tplBody.addEventListener('keyup', updateLivePreview);
    }

    // Insert Variable Tag into Textarea at Cursor Position
    document.querySelectorAll('.var-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const varTag = this.dataset.var;
            if (!tplBody) return;

            const startPos = tplBody.selectionStart;
            const endPos = tplBody.selectionEnd;
            const currentText = tplBody.value;

            // Insert tag at current cursor position
            tplBody.value = currentText.substring(0, startPos) + varTag + currentText.substring(endPos, currentText.length);

            // Move cursor right after inserted tag
            const newCursorPos = startPos + varTag.length;
            tplBody.focus();
            tplBody.setSelectionRange(newCursorPos, newCursorPos);

            // Update live preview
            updateLivePreview();
        });
    });

    // Open Modal Create
    function openCreateModal() {
        if (templateForm) templateForm.reset();
        document.getElementById('templateId').value = '';
        document.getElementById('templateModalLabel').textContent = 'Create New Template';
        document.getElementById('tplIsDefault').checked = false;

        // Default template sample text if empty
        if (!tplBody.value) {
            tplBody.value = "🔥 PREMIUM PRODUCT SPOTLIGHT 🔥\n\n✨ {product_name}\n\n💰 Best Price: ₹{price}\n✅ Guaranteed Quality & Heavy Duty Performance\n🚚 Express Shipping Across India\n\n🛒 Order Direct Here: {product_url}\n\n{cta}\n\n{hashtags}";
        }

        updateLivePreview();
        showModalInstance();
    }

    const btnCreate = document.getElementById('btnCreateTemplate');
    if (btnCreate) btnCreate.addEventListener('click', openCreateModal);

    const btnCreateEmpty = document.getElementById('btnCreateTemplateEmpty');
    if (btnCreateEmpty) btnCreateEmpty.addEventListener('click', openCreateModal);

    // Edit Template
    document.querySelectorAll('.btn-edit-tpl').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.dataset.template);
                document.getElementById('templateId').value = data.id;
                document.getElementById('tplName').value = data.name;
                document.getElementById('tplBody').value = data.template_body;
                document.getElementById('tplIsDefault').checked = parseInt(data.is_default) === 1;

                document.getElementById('templateModalLabel').textContent = 'Edit Template';
                updateLivePreview();
                showModalInstance();
            } catch (err) {
                console.error('Error opening edit template modal:', err);
            }
        });
    });

    // Save Template Form
    if (templateForm) {
        templateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('ajax/ajax_template_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to save template: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error saving template: ' + err.message));
        });
    }

    // Set Default Template
    document.querySelectorAll('.btn-set-default').forEach(btn => {
        btn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('action', 'set_default');
            formData.append('id', this.dataset.id);

            fetch('ajax/ajax_template_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Action failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err.message));
        });
    });

    // Clone Template
    document.querySelectorAll('.btn-clone-tpl').forEach(btn => {
        btn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('action', 'clone');
            formData.append('id', this.dataset.id);

            fetch('ajax/ajax_template_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Action failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err.message));
        });
    });

    // Delete Template
    document.querySelectorAll('.btn-delete-tpl').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this template?')) {
                const formData = new FormData();
                formData.append('_csrf_token', '<?php echo $csrfToken; ?>');
                formData.append('action', 'delete');
                formData.append('id', this.dataset.id);

                fetch('ajax/ajax_template_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Delete failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        });
    });
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>