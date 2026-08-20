<?php
include 'admin_header.php';
require_once '../includes/WebseoController.php';

$controller = new WebseoController($conn);
$repo = new SeoRepository($conn);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_global') {
        $settings = [
            'site_name' => $_POST['site_name'],
            'site_separator' => $_POST['site_separator'],
            'default_meta_title' => $_POST['default_meta_title'],
            'default_meta_description' => $_POST['default_meta_description'],
            'default_meta_keywords' => $_POST['default_meta_keywords'],
            'google_analytics_id' => $_POST['google_analytics_id'],
            'robots_default' => $_POST['robots_default']
        ];

        // Handle File Uploads
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
            $favicon = 'favicon.' . $ext;
            // Save to uploads/media/images/ — consistent with media library
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_target . $favicon)) {
                $settings['site_favicon'] = 'uploads/media/images/' . $favicon; // full relative path
            }
        } elseif (!empty($_POST['favicon_path'])) {
            $settings['site_favicon'] = $_POST['favicon_path'];
        }

        if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['og_image']['name'], PATHINFO_EXTENSION));
            $og_img = 'og_default.' . $ext;
            // Save to uploads/media/images/ — consistent with media library
            $upload_target = '../uploads/media/images/';
            if (!is_dir($upload_target)) mkdir($upload_target, 0755, true);
            if (move_uploaded_file($_FILES['og_image']['tmp_name'], $upload_target . $og_img)) {
                $settings['og_default_image'] = 'uploads/media/images/' . $og_img; // full relative path
            }
        } elseif (!empty($_POST['og_image_path'])) {
            $settings['og_default_image'] = $_POST['og_image_path'];
        }

        $res = $controller->saveGlobalSettings($settings);
        if ($res['success']) $success = "Global settings updated.";
        else $error = "Failed to update global settings.";
    } elseif ($action === 'save_entity_seo') {
        $data = [
            'entity_type' => $_POST['entity_type'] ?? 'home',
            'entity_id' => $_POST['entity_id'] ?? 0,
            'meta_title' => $_POST['meta_title'],
            'meta_description' => $_POST['meta_description'],
            'canonical_url' => $_POST['canonical_url']
        ];
        $res = $controller->saveMetadata($data);
        if ($res['success']) $success = "SEO metadata updated for " . ($data['entity_type'] ?? 'page');
        else $error = "Failed to update metadata: " . ($res['error'] ?? 'Unknown error');
    } elseif ($action === 'generate_sitemap') {
        $res = $controller->generateSitemap();
        if ($res['success']) $success = "Sitemap generated successfully at " . $res['path'];
        else $error = "Failed to generate sitemap: " . $res['error'];
    } elseif ($action === 'save_robots') {
        $res = $controller->saveRobotsTxt($_POST['robots_content']);
        if ($res['success']) $success = "robots.txt updated.";
        else $error = "Failed to update robots.txt.";
    }
}

$globalSettings = $repo->getGlobalSettings();
$robotsContent = $controller->getRobotsTxt();
$audit = $controller->getSeoAudit();

// Fetch entities for selection
$pages = $conn->query("SELECT id, title FROM pages ORDER BY title ASC");
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 px-4 pt-4">
            <h4 class="fw-bold text-primary mb-4"><i class="fas fa-search me-2"></i>WEBSEO Module</h4>
            
            <?php if ($success): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 py-2 mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 py-2 mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Tabs Navs -->
            <ul class="nav nav-pills mb-4" id="seoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 me-2" id="global-tab" data-mdb-toggle="pill" data-mdb-target="#global-panel" type="button" role="tab">Global Settings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 me-2" id="page-tab" data-mdb-toggle="pill" data-mdb-target="#page-panel" type="button" role="tab">Page Specific SEO</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 me-2" id="audit-tab" data-mdb-toggle="pill" data-mdb-target="#audit-panel" type="button" role="tab">SEO Audit</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 me-2" id="tools-tab" data-mdb-toggle="pill" data-mdb-target="#tools-panel" type="button" role="tab">Tools & Sitemap</button>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="tab-content" id="seoTabsContent">
                <!-- Global Settings Panel -->
                <div class="tab-pane fade show active" id="global-panel" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="save_global">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Site Name</label>
                                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($globalSettings['site_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Site Name Separator</label>
                                        <select name="site_separator" class="form-select">
                                            <option value="|" <?php echo ($globalSettings['site_separator'] ?? '') == '|' ? 'selected' : ''; ?>>| (Pipe)</option>
                                            <option value="-" <?php echo ($globalSettings['site_separator'] ?? '') == '-' ? 'selected' : ''; ?>>- (Dash)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Default Meta Title</label>
                                    <input type="text" name="default_meta_title" class="form-control" value="<?php echo htmlspecialchars($globalSettings['default_meta_title'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Default Meta Description</label>
                                    <textarea name="default_meta_description" class="form-control" rows="3"><?php echo htmlspecialchars($globalSettings['default_meta_description'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Default Meta Keywords</label>
                                    <input type="text" name="default_meta_keywords" class="form-control" value="<?php echo htmlspecialchars($globalSettings['default_meta_keywords'] ?? ''); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Favicon (Square .png/.ico)</label>
                                        <input type="file" name="favicon" id="favicon" class="form-control" accept="image/*">
                                        <input type="hidden" name="favicon_path" id="favicon_path" value="<?php echo htmlspecialchars($globalSettings['site_favicon'] ?? ''); ?>">
                                        <div class="mt-2" id="favicon_preview_box">
                                            <?php if (!empty($globalSettings['site_favicon'])): 
                                                 $fav_preview = resolve_image_url($globalSettings['site_favicon']);
                                             ?>
                                                 <img src="<?php echo htmlspecialchars($fav_preview); ?>" style="height: 32px;" onerror="this.src='../assets/images/favicon.png';">
                                             <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Open Graph Default Image</label>
                                        <input type="file" name="og_image" id="og_image" class="form-control" accept="image/*">
                                        <input type="hidden" name="og_image_path" id="og_image_path" value="<?php echo htmlspecialchars($globalSettings['og_default_image'] ?? ''); ?>">
                                        <div class="mt-2" id="og_image_preview_box">
                                            <?php if (!empty($globalSettings['og_default_image'])): 
                                                 $og_preview = resolve_image_url($globalSettings['og_default_image']);
                                             ?>
                                                 <img src="<?php echo htmlspecialchars($og_preview); ?>" style="max-height: 50px;" onerror="this.style.display='none';">
                                             <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Google Analytics ID</label>
                                        <input type="text" name="google_analytics_id" class="form-control" value="<?php echo htmlspecialchars($globalSettings['google_analytics_id'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-bold">Default Robots Tag</label>
                                        <select name="robots_default" class="form-select">
                                            <option value="index, follow" <?php echo ($globalSettings['robots_default'] ?? '') == 'index, follow' ? 'selected' : ''; ?>>Index, Follow</option>
                                            <option value="noindex, nofollow" <?php echo ($globalSettings['robots_default'] ?? '') == 'noindex, nofollow' ? 'selected' : ''; ?>>Noindex, Nofollow</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-custom px-5 rounded-pill shadow-sm">Save Global SEO</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Page Specific SEO Panel -->
                <div class="tab-pane fade" id="page-panel" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-4">Manage SEO Overrides</h6>
                            <form method="POST">
    <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="save_entity_seo">
                                <div class="row align-items-end mb-4 bg-light p-3 rounded-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Select Page Type</label>
                                        <select name="entity_type" id="entityType" class="form-select" onchange="toggleEntityId()">
                                            <option value="home">Homepage</option>
                                            <option value="shop">Shop Page</option>
                                            <option value="page">Static Page</option>
                                            <option value="category">Category Page</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4" id="entityIdWrapper" style="display: none;">
                                        <label class="form-label fw-bold">Select Item</label>
                                        <select name="entity_id" id="entityId" class="form-select">
                                            <!-- Dynamic via JS -->
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-info btn-custom w-100" id="loadMetaBtn" onclick="loadMetadata()">Load Existing Data</button>
                                    </div>
                                </div>
                                
                                <div id="metadataFields">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Custom Meta Title</label>
                                        <input type="text" name="meta_title" id="meta_title" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Custom Meta Description</label>
                                        <textarea name="meta_description" id="meta_description" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Canonical URL</label>
                                        <input type="text" name="canonical_url" id="canonical_url" class="form-control" placeholder="https://example.com/custom-url">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-custom px-5 rounded-pill shadow-sm">Update SEO for Selected Page</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- SEO Audit Panel -->
                <div class="tab-pane fade" id="audit-panel" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card border-0 shadow-sm rounded-4 border-start border-primary border-5">
                                <div class="card-body p-4 text-center">
                                    <h3 class="fw-bold text-primary mb-1"><?php echo $audit['total_indexed']; ?></h3>
                                    <p class="text-muted small mb-0">Total Pages Indexed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card border-0 shadow-sm rounded-4 border-start border-warning border-5">
                                <div class="card-body p-4 text-center">
                                    <h3 class="fw-bold text-warning mb-1"><?php echo count($audit['missing_title']); ?></h3>
                                    <p class="text-muted small mb-0">Missing Meta Titles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card border-0 shadow-sm rounded-4 border-start border-danger border-5">
                                <div class="card-body p-4 text-center">
                                    <h3 class="fw-bold text-danger mb-1"><?php echo count($audit['missing_description']); ?></h3>
                                    <p class="text-muted small mb-0">Missing Descriptions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">SEO Health Recommendations</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Issue</th>
                                            <th>Affecting</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($audit['missing_title']) && empty($audit['missing_description'])): ?>
                                            <tr><td colspan="2" class="text-success text-center">Your site SEO looks healthy!</td></tr>
                                        <?php endif; ?>
                                        <?php foreach($audit['missing_title'] as $item): ?>
                                            <tr>
                                                <td class="text-warning">Missing Title Tag</td>
                                                <td><?php echo htmlspecialchars($item); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php foreach($audit['missing_description'] as $item): ?>
                                            <tr>
                                                <td class="text-danger">Missing Meta Description</td>
                                                <td><?php echo htmlspecialchars($item); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tools Panel -->
                <div class="tab-pane fade" id="tools-panel" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-sitemap me-2 text-primary"></i>XML Sitemap</h6>
                                    <p class="text-muted small">Generate a fresh sitemap including all your products, categories, and custom pages.</p>
                                    <form method="POST">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="generate_sitemap">
                                        <button type="submit" class="btn btn-outline-primary btn-custom w-100 mt-2">Generate sitemap.xml</button>
                                    </form>
                                    <div class="mt-3 text-center">
                                        <a href="/sitemap.xml" target="_blank" class="text-decoration-none small fw-bold">View Current Sitemap <i class="fas fa-external-link-alt ms-1"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-robot me-2 text-primary"></i>Robots.txt Editor</h6>
                                    <form method="POST">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="save_robots">
                                        <textarea name="robots_content" class="form-control font-monospace mb-3" rows="7"><?php echo htmlspecialchars($robotsContent); ?></textarea>
                                        <button type="submit" class="btn btn-outline-primary btn-custom w-100">Save robots.txt</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const pages = <?php echo json_encode(($pages && $pages instanceof mysqli_result) ? $pages->fetch_all(MYSQLI_ASSOC) : []); ?>;
const categories = <?php echo json_encode(($categories && $categories instanceof mysqli_result) ? $categories->fetch_all(MYSQLI_ASSOC) : []); ?>;

function toggleEntityId() {
    const type = document.getElementById('entityType').value;
    const wrapper = document.getElementById('entityIdWrapper');
    const select = document.getElementById('entityId');
    
    select.innerHTML = '';
    
    if (type === 'page') {
        pages.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.title}</option>`;
        });
        wrapper.style.display = 'block';
    } else if (type === 'category') {
        categories.forEach(c => {
            select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
        });
        wrapper.style.display = 'block';
    } else {
        wrapper.style.display = 'none';
        select.innerHTML = '<option value="0">Default</option>';
    }
}

function loadMetadata() {
    const type = document.getElementById('entityType').value;
    const id = document.getElementById('entityId').value;
    const btn = document.getElementById('loadMetaBtn');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    
    fetch(`ajax_seo_metadata.php?type=${type}&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('meta_title').value = data.meta_title || '';
            document.getElementById('meta_description').value = data.meta_description || '';
            document.getElementById('canonical_url').value = data.canonical_url || '';
            btn.disabled = false;
            btn.innerHTML = 'Load Existing Data';
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = 'Load Existing Data';
            alert('Error loading metadata.');
        });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', toggleEntityId);
</script>

<?php include 'admin_footer.php'; ?>
