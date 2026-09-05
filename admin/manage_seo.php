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

<div class="container-fluid px-4 py-4 adm-wrapper">
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-search"></i> Search Engine Optimization
            </div>
            <h1 class="adm-hero-title">WEBSEO Search Engine Suite</h1>
            <p class="adm-hero-subtitle">Configure global search meta, OpenGraph tags, page-level keyword overrides, XML sitemaps, and robots directives.</p>
        </div>
        <div class="adm-hero-actions">
            <a href="/sitemap.xml" target="_blank" class="adm-btn-white me-2">
                <i class="fas fa-sitemap me-2"></i>Live Sitemap
            </a>
            <a href="/robots.txt" target="_blank" class="adm-btn-white">
                <i class="fas fa-robot me-2"></i>Robots.txt
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-check-circle fs-4 me-3 text-success"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 py-3 mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fs-4 me-3 text-danger"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <!-- Tabs Navs -->
    <ul class="nav adm-filter-tabs mb-4" id="seoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="adm-filter-tab active" id="global-tab" data-mdb-toggle="pill" data-mdb-target="#global-panel" type="button" role="tab">
                <i class="fas fa-globe me-2"></i>Global Settings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="adm-filter-tab" id="page-tab" data-mdb-toggle="pill" data-mdb-target="#page-panel" type="button" role="tab">
                <i class="fas fa-file-alt me-2"></i>Page Specific SEO
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="adm-filter-tab" id="audit-tab" data-mdb-toggle="pill" data-mdb-target="#audit-panel" type="button" role="tab">
                <i class="fas fa-chart-line me-2"></i>SEO Audit
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="adm-filter-tab" id="tools-tab" data-mdb-toggle="pill" data-mdb-target="#tools-panel" type="button" role="tab">
                <i class="fas fa-tools me-2"></i>Tools & Sitemap
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="seoTabsContent">
        <!-- Global Settings Panel -->
        <div class="tab-pane fade show active" id="global-panel" role="tabpanel">
            <div class="adm-card">
                <div class="p-4 border-bottom bg-light">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-globe me-2 text-primary"></i>Global Store Meta & Social Sharing</h5>
                    <p class="text-muted small mb-0">Default metadata and verification tags applied across the entire storefront.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_global">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">Site Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($globalSettings['site_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">Site Name Separator</label>
                                <select name="site_separator" class="form-select">
                                    <option value="|" <?php echo ($globalSettings['site_separator'] ?? '') == '|' ? 'selected' : ''; ?>>| (Pipe)</option>
                                    <option value="-" <?php echo ($globalSettings['site_separator'] ?? '') == '-' ? 'selected' : ''; ?>>- (Dash)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Default Meta Title</label>
                            <input type="text" name="default_meta_title" class="form-control" value="<?php echo htmlspecialchars($globalSettings['default_meta_title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Default Meta Description</label>
                            <textarea name="default_meta_description" class="form-control" rows="3"><?php echo htmlspecialchars($globalSettings['default_meta_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Default Meta Keywords</label>
                            <input type="text" name="default_meta_keywords" class="form-control" value="<?php echo htmlspecialchars($globalSettings['default_meta_keywords'] ?? ''); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">Favicon (Square .png/.ico)</label>
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
                                <label class="form-label fw-bold small text-uppercase">Open Graph Default Image</label>
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
                                <label class="form-label fw-bold small text-uppercase">Google Analytics ID</label>
                                <input type="text" name="google_analytics_id" class="form-control" value="<?php echo htmlspecialchars($globalSettings['google_analytics_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold small text-uppercase">Default Robots Tag</label>
                                <select name="robots_default" class="form-select">
                                    <option value="index, follow" <?php echo ($globalSettings['robots_default'] ?? '') == 'index, follow' ? 'selected' : ''; ?>>Index, Follow</option>
                                    <option value="noindex, nofollow" <?php echo ($globalSettings['robots_default'] ?? '') == 'noindex, nofollow' ? 'selected' : ''; ?>>Noindex, Nofollow</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                            <i class="fas fa-save me-2"></i>Save Global SEO
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Page Specific SEO Panel -->
        <div class="tab-pane fade" id="page-panel" role="tabpanel">
            <div class="adm-card">
                <div class="p-4 border-bottom bg-light">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-file-alt me-2 text-primary"></i>Manage SEO Overrides</h5>
                    <p class="text-muted small mb-0">Fine-tune custom meta titles and descriptions for specific pages and categories.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_entity_seo">
                        <div class="row align-items-end mb-4 bg-light p-3 rounded-4 border">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase">Select Page Type</label>
                                <select name="entity_type" id="entityType" class="form-select" onchange="toggleEntityId()">
                                    <option value="home">Homepage</option>
                                    <option value="shop">Shop Page</option>
                                    <option value="page">Static Page</option>
                                    <option value="category">Category Page</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="entityIdWrapper" style="display: none;">
                                <label class="form-label fw-bold small text-uppercase">Select Item</label>
                                <select name="entity_id" id="entityId" class="form-select">
                                    <!-- Dynamic via JS -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-info w-100 rounded-pill fw-bold text-white shadow-sm" id="loadMetaBtn" onclick="loadMetadata()">
                                    <i class="fas fa-sync me-2"></i>Load Existing Data
                                </button>
                            </div>
                        </div>
                        
                        <div id="metadataFields">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Custom Meta Title</label>
                                <input type="text" name="meta_title" id="meta_title" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Custom Meta Description</label>
                                <textarea name="meta_description" id="meta_description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase">Canonical URL</label>
                                <input type="text" name="canonical_url" id="canonical_url" class="form-control" placeholder="https://example.com/custom-url">
                            </div>
                            <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                                <i class="fas fa-check me-2"></i>Update SEO for Selected Page
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- SEO Audit Panel -->
        <div class="tab-pane fade" id="audit-panel" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="adm-stat-card">
                        <div>
                            <div class="adm-stat-label">Total Pages Indexed</div>
                            <div class="adm-stat-value text-primary"><?php echo $audit['total_indexed']; ?></div>
                            <div class="adm-stat-trend text-muted"><i class="fas fa-check-double me-1"></i>Active store URLs</div>
                        </div>
                        <div class="adm-icon-box bg-blue">
                            <i class="fas fa-sitemap"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="adm-stat-card">
                        <div>
                            <div class="adm-stat-label">Missing Meta Titles</div>
                            <div class="adm-stat-value text-warning"><?php echo count($audit['missing_title']); ?></div>
                            <div class="adm-stat-trend text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Needs optimization</div>
                        </div>
                        <div class="adm-icon-box bg-yellow">
                            <i class="fas fa-heading"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="adm-stat-card">
                        <div>
                            <div class="adm-stat-label">Missing Descriptions</div>
                            <div class="adm-stat-value text-danger"><?php echo count($audit['missing_description']); ?></div>
                            <div class="adm-stat-trend text-danger"><i class="fas fa-times-circle me-1"></i>Snippet missing</div>
                        </div>
                        <div class="adm-icon-box bg-pink">
                            <i class="fas fa-align-left"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="adm-table-container">
                <div class="p-4 border-bottom bg-light">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-notes-medical me-2 text-primary"></i>SEO Health Recommendations</h5>
                </div>
                <div class="table-responsive">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Issue Type</th>
                                <th>Affected URL / Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audit['missing_title']) && empty($audit['missing_description'])): ?>
                                <tr>
                                    <td colspan="2" class="text-success text-center py-4">
                                        <i class="fas fa-check-circle fs-3 mb-2 d-block text-success"></i>
                                        <strong>Awesome! Your site SEO looks healthy and complete.</strong>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach($audit['missing_title'] as $item): ?>
                                <tr>
                                    <td><span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Missing Title Tag</span></td>
                                    <td class="font-monospace small"><?php echo htmlspecialchars($item); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach($audit['missing_description'] as $item): ?>
                                <tr>
                                    <td><span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Missing Meta Description</span></td>
                                    <td class="font-monospace small"><?php echo htmlspecialchars($item); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tools Panel -->
        <div class="tab-pane fade" id="tools-panel" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="adm-card h-100">
                        <div class="p-4 border-bottom bg-light">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-sitemap me-2 text-primary"></i>XML Sitemap Engine</h5>
                            <p class="text-muted small mb-0">Generate a fresh sitemap including all products, categories, and custom pages.</p>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <form method="POST">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="generate_sitemap">
                                <button type="submit" class="btn btn-primary rounded-pill w-100 py-3 fw-bold shadow-sm">
                                    <i class="fas fa-sync-alt me-2"></i>Generate sitemap.xml Now
                                </button>
                            </form>
                            <div class="mt-4 pt-3 border-top text-center">
                                <a href="/sitemap.xml" target="_blank" class="btn btn-light rounded-pill px-4 fw-bold">
                                    View Current Sitemap <i class="fas fa-external-link-alt ms-1 text-primary"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="adm-card h-100">
                        <div class="p-4 border-bottom bg-light">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-robot me-2 text-primary"></i>Robots.txt Directives</h5>
                            <p class="text-muted small mb-0">Instruct search crawlers on allowed and disallowed paths.</p>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="save_robots">
                                <textarea name="robots_content" class="form-control font-monospace mb-3" rows="8"><?php echo htmlspecialchars($robotsContent); ?></textarea>
                                <button type="submit" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm">
                                    <i class="fas fa-save me-2"></i>Save robots.txt
                                </button>
                            </form>
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
