<?php
/**
 * Admin Panel — Manage Professional Product Catalogue (PDF)
 * Location: /admin/manage_catalogue.php
 */
include 'admin_header.php';

// Helper function to update or insert a setting
function save_catalogue_setting(mysqli $conn, string $key, string $value): void {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    $stmt->execute();
    $stmt->close();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $enabled   = isset($_POST['catalogue_enabled']) ? '1' : '0';
        $source    = $_POST['catalogue_source'] ?? 'dynamic';
        $title     = trim($_POST['catalogue_title'] ?? "Sagar Starter's — Official Product Catalogue");
        $subtitle  = trim($_POST['catalogue_subtitle'] ?? "Agricultural & Industrial Motor Starters, Panels & Spares");
        $about     = trim($_POST['catalogue_about'] ?? '');
        $filter    = $_POST['catalogue_filter'] ?? 'all';

        // Display toggles
        $show_price    = isset($_POST['catalogue_show_price']) ? '1' : '0';
        $show_sku      = isset($_POST['catalogue_show_sku']) ? '1' : '0';
        $show_features = isset($_POST['catalogue_show_features']) ? '1' : '0';
        $show_specs    = isset($_POST['catalogue_show_specs']) ? '1' : '0';

        save_catalogue_setting($conn, 'catalogue_enabled', $enabled);
        save_catalogue_setting($conn, 'catalogue_source', $source);
        save_catalogue_setting($conn, 'catalogue_title', $title);
        save_catalogue_setting($conn, 'catalogue_subtitle', $subtitle);
        save_catalogue_setting($conn, 'catalogue_about', $about);
        save_catalogue_setting($conn, 'catalogue_filter', $filter);

        save_catalogue_setting($conn, 'catalogue_show_price', $show_price);
        save_catalogue_setting($conn, 'catalogue_show_sku', $show_sku);
        save_catalogue_setting($conn, 'catalogue_show_features', $show_features);
        save_catalogue_setting($conn, 'catalogue_show_specs', $show_specs);

        // Handle Custom PDF upload if provided
        if (isset($_FILES['custom_catalogue_file']) && $_FILES['custom_catalogue_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['custom_catalogue_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                set_flash('danger', 'Only PDF files (.pdf) are allowed for custom catalogue upload.');
                header('Location: manage_catalogue.php');
                exit;
            }

            if ($file['size'] > 50 * 1024 * 1024) { // 50MB max
                set_flash('danger', 'Uploaded PDF exceeds maximum allowed size of 50MB.');
                header('Location: manage_catalogue.php');
                exit;
            }

            $upload_dir = BASE_PATH . '/uploads/documents/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }

            $new_filename = 'catalogue_official_' . time() . '.pdf';
            $target_path = $upload_dir . $new_filename;

            // Remove previous custom catalogue if exists
            $existing_file = $global_settings['catalogue_custom_pdf'] ?? '';
            if (!empty($existing_file) && file_exists(BASE_PATH . '/' . $existing_file)) {
                @unlink(BASE_PATH . '/' . $existing_file);
            }

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $relative_path = 'uploads/documents/' . $new_filename;
                save_catalogue_setting($conn, 'catalogue_custom_pdf', $relative_path);
                set_flash('success', 'Custom PDF brochure/catalogue uploaded successfully and settings updated.');
            } else {
                set_flash('warning', 'Settings saved, but failed to upload custom PDF. Check directory permissions.');
            }
        } else {
            set_flash('success', 'Catalogue configuration updated successfully.');
        }

        header('Location: manage_catalogue.php');
        exit;
    } elseif ($action === 'delete_custom_catalogue') {
        $existing_file = $global_settings['catalogue_custom_pdf'] ?? '';
        if (!empty($existing_file) && file_exists(BASE_PATH . '/' . $existing_file)) {
            @unlink(BASE_PATH . '/' . $existing_file);
        }
        save_catalogue_setting($conn, 'catalogue_custom_pdf', '');
        set_flash('success', 'Custom PDF file removed. Catalogue will now use Live Dynamic Visual mode.');
        header('Location: manage_catalogue.php');
        exit;
    }
}

// Refresh settings from database
$set_q = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'catalogue_%'");
$cfg = [];
if ($set_q) {
    while ($r = $set_q->fetch_assoc()) {
        $cfg[$r['setting_key']] = $r['setting_value'];
    }
}

$cat_enabled    = ($cfg['catalogue_enabled'] ?? '1') === '1';
$cat_source     = $cfg['catalogue_source'] ?? 'dynamic';
$cat_custom_pdf = $cfg['catalogue_custom_pdf'] ?? '';
$cat_title      = $cfg['catalogue_title'] ?? "Sagar Starter's — Official Product Catalogue";
$cat_subtitle   = $cfg['catalogue_subtitle'] ?? "Agricultural & Industrial Motor Starters, Panels & Spares";
$cat_about      = $cfg['catalogue_about'] ?? "Sagar Starter's is a trusted Indian manufacturer specializing in heavy-duty single phase and three phase motor starters, submersible pump control panels, and industrial switchgear. Engineered with 100% electrolytic grade copper contacts, precision thermal overload relays, and weather-resistant powder-coated sheet metal enclosures, our products ensure unmatched motor protection and longevity across agricultural and industrial applications.";
$cat_filter     = $cfg['catalogue_filter'] ?? 'all';

$cat_show_price    = ($cfg['catalogue_show_price'] ?? '1') === '1';
$cat_show_sku      = ($cfg['catalogue_show_sku'] ?? '1') === '1';
$cat_show_features = ($cfg['catalogue_show_features'] ?? '1') === '1';
$cat_show_specs    = ($cfg['catalogue_show_specs'] ?? '1') === '1';

// Count products
$prod_count_q = $conn->query("SELECT COUNT(*) as total FROM products");
$total_products = $prod_count_q ? (int)($prod_count_q->fetch_assoc()['total'] ?? 0) : 0;

$preview_url = SITE_URL . '/catalogue.php';
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <!-- Header Hero Banner -->
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-book-open"></i> Marketing & Brochures
            </div>
            <h1 class="adm-hero-title">Professional Product Catalogue (PDF)</h1>
            <p class="adm-hero-subtitle">
                Manage your public company brochure and product catalogue. Upload an official printed PDF brochure or auto-generate a magazine-grade visual catalogue from your active database products.
            </p>
        </div>
        <div class="adm-hero-actions d-flex gap-2 flex-wrap">
            <a href="<?php echo htmlspecialchars($preview_url); ?>" target="_blank" class="adm-btn-white">
                <i class="fas fa-external-link-alt me-2 text-primary"></i>Live Preview
            </a>
            <a href="<?php echo htmlspecialchars($preview_url); ?>?download=1" target="_blank" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold">
                <i class="fas fa-download me-2"></i>Test Download
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php render_flash(); ?>

    <!-- Status Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid <?php echo $cat_enabled ? '#10b981' : '#ef4444'; ?> !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Public Availability</span>
                        <h5 class="fw-bold mb-0 mt-1 <?php echo $cat_enabled ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas <?php echo $cat_enabled ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                            <?php echo $cat_enabled ? 'LIVE (PUBLIC)' : 'DISABLED'; ?>
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 <?php echo $cat_enabled ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?>">
                        <i class="fas fa-globe fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <?php echo $cat_enabled ? 'Visible in Header, Shop & Footer to all visitors' : 'Hidden from public visitors'; ?>
                </small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid #3b82f6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Catalogue Source</span>
                        <h5 class="fw-bold mb-0 mt-1 text-primary">
                            <?php echo ($cat_source === 'custom_pdf' && !empty($cat_custom_pdf)) ? 'Uploaded PDF Brochure' : 'Live Dynamic Catalogue'; ?>
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fas <?php echo ($cat_source === 'custom_pdf' && !empty($cat_custom_pdf)) ? 'fa-file-pdf' : 'fa-magic'; ?> fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <?php echo ($cat_source === 'custom_pdf' && !empty($cat_custom_pdf)) ? 'Serving uploaded PDF file' : 'Generated live from product database'; ?>
                </small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Featured Products</span>
                        <h5 class="fw-bold mb-0 mt-1 text-purple" style="color: #8b5cf6;">
                            <?php echo $total_products; ?> Available
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 bg-opacity-10 text-purple" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        <i class="fas fa-boxes fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    Full product specs, photos and categories included
                </small>
            </div>
        </div>
    </div>

    <!-- Main Configuration Form -->
    <form method="POST" action="manage_catalogue.php" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-7">
                <!-- 1. Master Control Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-sliders-h text-primary"></i> Core Display Controls
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Enable or disable catalogue downloads for all website visitors.</p>
                    </div>
                    <div class="card-body p-4">
                        <!-- Toggle Switch -->
                        <div class="p-3 rounded-3 mb-4 border d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: #f8fafc;">
                            <div>
                                <label class="form-check-label fw-bold text-dark d-block mb-1" for="catalogueEnabledSwitch">
                                    Enable Product Catalogue on Website Frontend
                                </label>
                                <span class="text-muted small">
                                    When enabled, a "Catalogue" link is shown in the top navbar, on the shop page, and in the footer.
                                </span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="catalogueEnabledSwitch" name="catalogue_enabled" value="1" <?php echo $cat_enabled ? 'checked' : ''; ?> style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                        </div>

                        <!-- Source Mode Selection -->
                        <div class="mb-2">
                            <label class="form-label fw-bold text-dark">
                                <i class="fas fa-layer-group me-1 text-primary"></i> Catalogue Delivery Mode
                            </label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($cat_source === 'dynamic') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="catalogue_source" id="src_dynamic" value="dynamic" <?php echo ($cat_source === 'dynamic') ? 'checked' : ''; ?> onchange="toggleCatSource()">
                                        <label class="form-check-label fw-bold" for="src_dynamic">
                                            Live Visual Catalogue
                                            <span class="badge bg-primary ms-1 small">Real-time</span>
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Magazine-style A4 catalogue auto-generated with company branding, specs, high-res photos, and features.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($cat_source === 'custom_pdf') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="catalogue_source" id="src_custom" value="custom_pdf" <?php echo ($cat_source === 'custom_pdf') ? 'checked' : ''; ?> onchange="toggleCatSource()">
                                        <label class="form-check-label fw-bold" for="src_custom">
                                            Custom Uploaded PDF Brochure
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Upload your official professionally designed print brochure/catalogue PDF file to serve for download.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Custom PDF Upload Card (Shown when custom_pdf is selected) -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" id="customCatCard" style="<?php echo ($cat_source === 'custom_pdf') ? '' : 'display: none;'; ?>">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-file-upload text-purple" style="color: #8b5cf6;"></i> Upload Company Brochure / Catalogue PDF
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Upload or replace your official PDF file.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($cat_custom_pdf) && file_exists(BASE_PATH . '/' . $cat_custom_pdf)): ?>
                            <?php
                            $filesize_bytes = filesize(BASE_PATH . '/' . $cat_custom_pdf);
                            $filesize_mb = number_format($filesize_bytes / (1024 * 1024), 2);
                            $file_date = date('d M Y, h:i A', filemtime(BASE_PATH . '/' . $cat_custom_pdf));
                            ?>
                            <div class="p-3 rounded-3 mb-3 border d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: #fdf4ff; border-color: #f0abfc !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars(basename($cat_custom_pdf)); ?></span>
                                        <span class="text-muted small">Size: <?php echo $filesize_mb; ?> MB &bull; Uploaded: <?php echo $file_date; ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?php echo SITE_URL . '/' . htmlspecialchars($cat_custom_pdf); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="if(confirm('Are you sure you want to remove this custom catalogue PDF?')) document.getElementById('deleteCatForm').submit();">
                                        <i class="fas fa-trash me-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 small mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i> No custom PDF uploaded yet. Upload a .pdf file below.
                            </div>
                        <?php endif; ?>

                        <label class="form-label fw-bold text-dark">Select PDF File</label>
                        <input type="file" name="custom_catalogue_file" class="form-control" accept=".pdf,application/pdf">
                        <small class="text-muted d-block mt-1">Supported format: PDF only (.pdf). Maximum file size: 50MB.</small>
                    </div>
                </div>

                <!-- 3. Dynamic Visual Catalogue Customizer -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" id="dynamicCatCard">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-palette text-info"></i> Dynamic Catalogue Customizer
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Customize the cover page, company intro, and displayed fields in the auto-generated catalogue.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Catalogue Title</label>
                            <input type="text" name="catalogue_title" class="form-control" value="<?php echo htmlspecialchars($cat_title); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Tagline / Subtitle</label>
                            <input type="text" name="catalogue_subtitle" class="form-control" value="<?php echo htmlspecialchars($cat_subtitle); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Company Introduction / Profile</label>
                            <textarea name="catalogue_about" class="form-control" rows="4" placeholder="Enter company introduction, manufacturing standards, warranty terms..."><?php echo htmlspecialchars($cat_about); ?></textarea>
                            <small class="text-muted">Featured prominently on the catalogue front section.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Product Inclusion Filter</label>
                            <select name="catalogue_filter" class="form-select">
                                <option value="all" <?php echo ($cat_filter === 'all') ? 'selected' : ''; ?>>All Active Products (Complete Product Range)</option>
                                <option value="trending_only" <?php echo ($cat_filter === 'trending_only') ? 'selected' : ''; ?>>Featured / Trending Products Only</option>
                            </select>
                        </div>

                        <label class="form-label fw-bold text-dark mb-2">Display Options in Product Cards</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="catalogue_show_price" id="fld_price" value="1" <?php echo $cat_show_price ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="fld_price">Show Retail Price / MRP</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="catalogue_show_sku" id="fld_sku" value="1" <?php echo $cat_show_sku ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="fld_sku">Show Model / SKU Code</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="catalogue_show_features" id="fld_feat" value="1" <?php echo $cat_show_features ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="fld_feat">Show Product Highlights & Description</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="catalogue_show_specs" id="fld_spec" value="1" <?php echo $cat_show_specs ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="fld_spec">Show Technical Specs (Phase, HP, Relay)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i> Save Catalogue Configuration
                    </button>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-5">
                <!-- Info Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-bullhorn text-warning fs-4"></i>
                            <h5 class="fw-bold mb-0 text-white">Public Marketing Brochure</h5>
                        </div>
                        <p class="small text-light text-opacity-75 mb-3">
                            Unlike the confidential Wholesale Price List, this Product Catalogue is designed to be shared openly with prospective buyers, dealers, and electricians:
                        </p>
                        <ul class="small text-light text-opacity-90 ps-3 mb-3" style="line-height: 1.8;">
                            <li><strong>Open Access:</strong> Any visitor can view or download it without requiring login credentials.</li>
                            <li><strong>Prominent Navigation:</strong> Links appear automatically in the top navigation bar, on the shop page, and in the website footer.</li>
                            <li><strong>Instant 1-Click PDF:</strong> Uses client-side vector formatting so visitors can download a clean PDF file or print directly.</li>
                            <li><strong>Inquiry Driven:</strong> Includes dealership inquiry info, official WhatsApp chat links, and contact details on every page.</li>
                        </ul>

                        <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); border: 1px dashed rgba(255,255,255,0.2);">
                            <span class="small fw-bold d-block text-warning mb-1">
                                <i class="fas fa-link me-1"></i> Public Catalogue URL:
                            </span>
                            <code class="text-white small" style="word-break: break-all;"><?php echo htmlspecialchars($preview_url); ?></code>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h6 class="fw-bold m-0 text-dark">
                            <i class="fas fa-desktop text-primary me-2"></i> Quick Verification
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">Test how your catalogue appears to website visitors:</p>
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars($preview_url); ?>" target="_blank" class="btn btn-outline-primary rounded-pill py-2 fw-semibold">
                                <i class="fas fa-eye me-2"></i> Open Public Catalogue View
                            </a>
                            <a href="<?php echo SITE_URL; ?>/shop.php" target="_blank" class="btn btn-outline-secondary rounded-pill py-2 fw-semibold">
                                <i class="fas fa-store me-2"></i> Visit Shop Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Hidden Form for Deleting Custom PDF -->
<form id="deleteCatForm" method="POST" action="manage_catalogue.php" style="display: none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="delete_custom_catalogue">
</form>

<script>
function toggleCatSource() {
    var isCustom = document.getElementById('src_custom').checked;
    var customCard = document.getElementById('customCatCard');
    if (customCard) {
        customCard.style.display = isCustom ? 'block' : 'none';
    }
}
</script>

<?php include 'admin_footer.php'; ?>
