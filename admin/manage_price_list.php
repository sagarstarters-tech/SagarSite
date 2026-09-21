<?php
/**
 * Admin Panel — Manage Product Price List (PDF)
 * Location: /admin/manage_price_list.php
 */
include 'admin_header.php';

// Helper function to update or insert a setting
function save_setting(mysqli $conn, string $key, string $value): void {
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
        $enabled = isset($_POST['price_list_enabled']) ? '1' : '0';
        $allowed_roles = $_POST['price_list_allowed_roles'] ?? 'admin,retailer';
        $source = $_POST['price_list_source'] ?? 'dynamic';
        $title = trim($_POST['price_list_title'] ?? "Sagar Starter's — Wholesale & Retailer Price List");
        $subtitle = trim($_POST['price_list_subtitle'] ?? 'Official Authorized Product Catalog');
        $note = trim($_POST['price_list_note'] ?? '');
        $filter = $_POST['price_list_filter'] ?? 'all';

        // Column toggles
        $show_image = isset($_POST['price_list_show_image']) ? '1' : '0';
        $show_sku = isset($_POST['price_list_show_sku']) ? '1' : '0';
        $show_category = isset($_POST['price_list_show_category']) ? '1' : '0';
        $show_regular_price = isset($_POST['price_list_show_regular_price']) ? '1' : '0';
        $show_sale_price = isset($_POST['price_list_show_sale_price']) ? '1' : '0';
        $show_bulk_price = isset($_POST['price_list_show_bulk_price']) ? '1' : '0';
        $show_moq = isset($_POST['price_list_show_moq']) ? '1' : '0';
        $show_stock = isset($_POST['price_list_show_stock']) ? '1' : '0';
        $show_share = isset($_POST['price_list_show_share']) ? '1' : '0';

        save_setting($conn, 'price_list_enabled', $enabled);
        save_setting($conn, 'price_list_allowed_roles', $allowed_roles);
        save_setting($conn, 'price_list_source', $source);
        save_setting($conn, 'price_list_title', $title);
        save_setting($conn, 'price_list_subtitle', $subtitle);
        save_setting($conn, 'price_list_note', $note);
        save_setting($conn, 'price_list_filter', $filter);

        // Save selected categories (multi-select checkboxes)
        if (isset($_POST['price_list_categories']) && is_array($_POST['price_list_categories'])) {
            $cat_ids = array_filter(array_map('intval', $_POST['price_list_categories']));
            $cat_str = !empty($cat_ids) ? implode(',', $cat_ids) : 'none';
        } else {
            $cat_str = 'none';
        }
        save_setting($conn, 'price_list_selected_categories', $cat_str);

        save_setting($conn, 'price_list_show_image', $show_image);
        save_setting($conn, 'price_list_show_sku', $show_sku);
        save_setting($conn, 'price_list_show_category', $show_category);
        save_setting($conn, 'price_list_show_regular_price', $show_regular_price);
        save_setting($conn, 'price_list_show_sale_price', $show_sale_price);
        save_setting($conn, 'price_list_show_bulk_price', $show_bulk_price);
        save_setting($conn, 'price_list_show_moq', $show_moq);
        save_setting($conn, 'price_list_show_stock', $show_stock);
        save_setting($conn, 'price_list_show_share', $show_share);

        // Handle Custom PDF upload if provided
        if (isset($_FILES['custom_pdf_file']) && $_FILES['custom_pdf_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['custom_pdf_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                set_flash('danger', 'Only PDF files (.pdf) are allowed for custom price lists.');
                header('Location: manage_price_list.php');
                exit;
            }

            if ($file['size'] > 25 * 1024 * 1024) { // 25MB max
                set_flash('danger', 'Uploaded PDF exceeds maximum allowed size of 25MB.');
                header('Location: manage_price_list.php');
                exit;
            }

            $upload_dir = BASE_PATH . '/uploads/documents/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }

            $new_filename = 'price_list_custom_' . time() . '.pdf';
            $target_path = $upload_dir . $new_filename;

            // Remove previous custom pdf if exists
            $existing_file = $global_settings['price_list_custom_pdf'] ?? '';
            if (!empty($existing_file) && file_exists(BASE_PATH . '/' . $existing_file)) {
                @unlink(BASE_PATH . '/' . $existing_file);
            }

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $relative_path = 'uploads/documents/' . $new_filename;
                save_setting($conn, 'price_list_custom_pdf', $relative_path);
                set_flash('success', 'Custom PDF price list uploaded successfully and settings updated.');
            } else {
                set_flash('warning', 'Settings saved, but failed to upload custom PDF file. Check directory permissions.');
            }
        } else {
            set_flash('success', 'Price list settings updated successfully.');
        }

        header('Location: manage_price_list.php');
        exit;
    } elseif ($action === 'delete_custom_pdf') {
        $existing_file = $global_settings['price_list_custom_pdf'] ?? '';
        if (!empty($existing_file) && file_exists(BASE_PATH . '/' . $existing_file)) {
            @unlink(BASE_PATH . '/' . $existing_file);
        }
        save_setting($conn, 'price_list_custom_pdf', '');
        set_flash('success', 'Custom PDF file removed successfully. Price list will use Dynamic Catalog mode.');
        header('Location: manage_price_list.php');
        exit;
    }
}

// Refresh settings from database
$set_q = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'price_list_%'");
$cfg = [];
if ($set_q) {
    while ($r = $set_q->fetch_assoc()) {
        $cfg[$r['setting_key']] = $r['setting_value'];
    }
}

$pl_enabled = ($cfg['price_list_enabled'] ?? '1') === '1';
$pl_roles = $cfg['price_list_allowed_roles'] ?? 'admin,retailer';
$pl_source = $cfg['price_list_source'] ?? 'dynamic';
$pl_custom_pdf = $cfg['price_list_custom_pdf'] ?? '';
$pl_title = $cfg['price_list_title'] ?? "Sagar Starter's — Wholesale & Retailer Price List";
$pl_subtitle = $cfg['price_list_subtitle'] ?? "Official Authorized Product Catalog";
$pl_note = $cfg['price_list_note'] ?? "Note: All prices are in Indian Rupees (INR) and subject to change without prior notice. GST, transportation, and loading charges extra as applicable. For dealership inquiries, custom specifications, or bulk dispatches, contact our sales desk directly via phone or WhatsApp.";
$pl_filter = $cfg['price_list_filter'] ?? 'all';

$pl_show_image = ($cfg['price_list_show_image'] ?? '1') === '1';
$pl_show_sku = ($cfg['price_list_show_sku'] ?? '1') === '1';
$pl_show_category = ($cfg['price_list_show_category'] ?? '1') === '1';
$pl_show_regular = ($cfg['price_list_show_regular_price'] ?? '1') === '1';
$pl_show_sale_price = ($cfg['price_list_show_sale_price'] ?? '1') === '1';
$pl_show_bulk = ($cfg['price_list_show_bulk_price'] ?? '1') === '1';
$pl_show_moq = ($cfg['price_list_show_moq'] ?? '1') === '1';
$pl_show_stock = ($cfg['price_list_show_stock'] ?? '1') === '1';
$pl_show_share = ($cfg['price_list_show_share'] ?? '1') === '1';

// Count available products
$prod_count_q = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN bulk_price > 0 THEN 1 ELSE 0 END) as bulk_total FROM products");
$prod_stats = $prod_count_q ? $prod_count_q->fetch_assoc() : ['total' => 0, 'bulk_total' => 0];

// Fetch store categories with product counts
$categories_res = $conn->query("
    SELECT c.id, c.name, c.slug, 
           COUNT(p.id) as product_count,
           SUM(CASE WHEN p.bulk_price > 0 THEN 1 ELSE 0 END) as bulk_count
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.slug 
    ORDER BY c.name ASC
");
$all_categories = [];
if ($categories_res) {
    while ($crow = $categories_res->fetch_assoc()) {
        $all_categories[] = $crow;
    }
}

// Selected categories for price list
$raw_pl_cat_selection = $cfg['price_list_selected_categories'] ?? '';
if ($raw_pl_cat_selection === 'none') {
    $selected_pl_cat_ids = [];
} elseif (!empty($raw_pl_cat_selection) && $raw_pl_cat_selection !== 'all') {
    $selected_pl_cat_ids = array_filter(array_map('intval', explode(',', $raw_pl_cat_selection)));
} else {
    // Default: all categories selected
    $selected_pl_cat_ids = array_map(function($c) { return (int)$c['id']; }, $all_categories);
}

$preview_url = SITE_URL . '/price_list.php';
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <!-- Header Hero Banner -->
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-file-pdf"></i> Wholesale & Retailer Portal
            </div>
            <h1 class="adm-hero-title">Product Price List (PDF) Settings</h1>
            <p class="adm-hero-subtitle">
                Configure frontend PDF price list downloads, restrict access to verified Retailers/Wholesalers and Administrators, and customize the live catalog layout.
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
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid <?php echo $pl_enabled ? '#10b981' : '#ef4444'; ?> !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Download Status</span>
                        <h5 class="fw-bold mb-0 mt-1 <?php echo $pl_enabled ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas <?php echo $pl_enabled ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                            <?php echo $pl_enabled ? 'ENABLED' : 'DISABLED'; ?>
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 <?php echo $pl_enabled ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?>">
                        <i class="fas fa-power-off fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <?php echo $pl_enabled ? 'Visible to authorized users on frontend' : 'Hidden completely on frontend'; ?>
                </small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid #3b82f6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Allowed Roles</span>
                        <h5 class="fw-bold mb-0 mt-1 text-primary">
                            <?php echo ($pl_roles === 'admin') ? 'Admin Only' : 'Admin & Retailer'; ?>
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-user-shield fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <?php echo ($pl_roles === 'admin') ? 'Wholesalers blocked from downloading' : 'Wholesalers/Retailers + Admins can download'; ?>
                </small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Catalog Source</span>
                        <h5 class="fw-bold mb-0 mt-1 text-purple" style="color: #8b5cf6;">
                            <?php echo ($pl_source === 'custom_pdf' && !empty($pl_custom_pdf)) ? 'Custom PDF' : 'Live Dynamic'; ?>
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 bg-opacity-10 text-purple" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        <i class="fas fa-layer-group fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <?php echo ($pl_source === 'custom_pdf' && !empty($pl_custom_pdf)) ? 'Serving uploaded PDF document' : 'Auto-generating from DB products'; ?>
                </small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="border-left: 4px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Catalog Database</span>
                        <h5 class="fw-bold mb-0 mt-1 text-warning">
                            <?php echo (int)($prod_stats['total'] ?? 0); ?> Products
                        </h5>
                    </div>
                    <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-boxes fs-4"></i>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <span class="text-success fw-bold"><?php echo (int)($prod_stats['bulk_total'] ?? 0); ?></span> have wholesale bulk rates
                </small>
            </div>
        </div>
    </div>

    <!-- Main Configuration Form -->
    <form method="POST" action="manage_price_list.php" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="row g-4">
            <!-- Left Column: Core Access & Controls -->
            <div class="col-lg-7">
                <!-- 1. Master Control Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-sliders-h text-primary"></i> Access & Permission Controls
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Control who can access the price list and whether the feature is live.</p>
                    </div>
                    <div class="card-body p-4">
                        <!-- Toggle Switch -->
                        <div class="p-3 rounded-3 mb-4 border d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: #f8fafc;">
                            <div>
                                <label class="form-check-label fw-bold text-dark d-block mb-1" for="priceListEnabledSwitch">
                                    Enable Price List Download on Frontend
                                </label>
                                <span class="text-muted small">
                                    When enabled, authorized users see the download option in the navbar dropdown, shop header, and user dashboard.
                                </span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="priceListEnabledSwitch" name="price_list_enabled" value="1" <?php echo $pl_enabled ? 'checked' : ''; ?> style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                        </div>

                        <!-- Role Restriction -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">
                                <i class="fas fa-users-cog me-1 text-primary"></i> Allowed User Roles
                            </label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($pl_roles === 'admin,retailer') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="price_list_allowed_roles" id="role_both" value="admin,retailer" <?php echo ($pl_roles === 'admin,retailer') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="role_both">
                                            Admin & Retailer (Wholesaler)
                                            <span class="badge bg-success ms-1 small">Recommended</span>
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Both verified wholesale retailers and administrators can download the price list.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($pl_roles === 'admin') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="price_list_allowed_roles" id="role_admin" value="admin" <?php echo ($pl_roles === 'admin') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="role_admin">
                                            Administrator Only
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Only store administrators can view or download. Retailers will be denied access.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price List Source Mode -->
                        <div class="mb-2">
                            <label class="form-label fw-bold text-dark">
                                <i class="fas fa-file-invoice me-1 text-primary"></i> Price List Format / Source
                            </label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($pl_source === 'dynamic') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="price_list_source" id="source_dynamic" value="dynamic" <?php echo ($pl_source === 'dynamic') ? 'checked' : ''; ?> onchange="toggleSourceSections()">
                                        <label class="form-check-label fw-bold" for="source_dynamic">
                                            Live Dynamic PDF
                                            <span class="badge bg-primary ms-1 small">Real-time</span>
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Automatically generates high-res PDF catalog directly from current active store products & rates.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 rounded-3 border h-100 <?php echo ($pl_source === 'custom_pdf') ? 'border-primary bg-primary bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input ms-0 me-2" type="radio" name="price_list_source" id="source_custom" value="custom_pdf" <?php echo ($pl_source === 'custom_pdf') ? 'checked' : ''; ?> onchange="toggleSourceSections()">
                                        <label class="form-check-label fw-bold" for="source_custom">
                                            Custom Uploaded PDF
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Upload your own official company PDF brochure/price-list file to serve for download.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Custom PDF Upload Card (Shown when Custom PDF is selected) -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" id="customPdfCard" style="<?php echo ($pl_source === 'custom_pdf') ? '' : 'display: none;'; ?>">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-file-upload text-purple" style="color: #8b5cf6;"></i> Custom PDF Document File
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Upload or replace the PDF document that users will download.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($pl_custom_pdf) && file_exists(BASE_PATH . '/' . $pl_custom_pdf)): ?>
                            <?php
                            $filesize_bytes = filesize(BASE_PATH . '/' . $pl_custom_pdf);
                            $filesize_mb = number_format($filesize_bytes / (1024 * 1024), 2);
                            $file_date = date('d M Y, h:i A', filemtime(BASE_PATH . '/' . $pl_custom_pdf));
                            ?>
                            <div class="p-3 rounded-3 mb-3 border d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: #fdf4ff; border-color: #f0abfc !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars(basename($pl_custom_pdf)); ?></span>
                                        <span class="text-muted small">Size: <?php echo $filesize_mb; ?> MB &bull; Uploaded: <?php echo $file_date; ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?php echo SITE_URL . '/' . htmlspecialchars($pl_custom_pdf); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="if(confirm('Are you sure you want to remove this custom PDF?')) document.getElementById('deletePdfForm').submit();">
                                        <i class="fas fa-trash me-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 small mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i> No custom PDF uploaded yet. Please upload a .pdf file below.
                            </div>
                        <?php endif; ?>

                        <label class="form-label fw-bold text-dark">Upload New / Replace PDF</label>
                        <input type="file" name="custom_pdf_file" class="form-control" accept=".pdf,application/pdf">
                        <small class="text-muted d-block mt-1">Supported format: PDF only (.pdf). Maximum file size: 25MB.</small>
                    </div>
                </div>

                <!-- 3. Dynamic PDF Customization Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" id="dynamicPdfCard">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="fas fa-magic text-info"></i> Dynamic PDF Content & Layout
                        </h5>
                        <p class="text-muted small mt-1 mb-0">Customize header, footer terms, and columns in the auto-generated live PDF.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Document Title</label>
                            <input type="text" name="price_list_title" class="form-control" value="<?php echo htmlspecialchars($pl_title); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Document Subtitle</label>
                            <input type="text" name="price_list_subtitle" class="form-control" value="<?php echo htmlspecialchars($pl_subtitle); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Wholesale Pricing Filter</label>
                            <select name="price_list_filter" class="form-select">
                                <option value="all" <?php echo ($pl_filter === 'all' || strpos($pl_filter, 'category:') === 0) ? 'selected' : ''; ?>>All Products in Selected Categories (Show MRP & Wholesale rates)</option>
                                <option value="bulk_only" <?php echo ($pl_filter === 'bulk_only') ? 'selected' : ''; ?>>Wholesale Products Only (Only products that have a Bulk/Retailer price set)</option>
                            </select>
                        </div>

                        <!-- Select Categories to Include in Price List (Checkbox Cards) -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold text-dark mb-0">
                                    <i class="fas fa-layer-group text-primary me-1"></i> Select Categories to Include in Price List
                                </label>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="setAllPlCatCheckboxes(true)">Select All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="setAllPlCatCheckboxes(false)">Clear All</button>
                                </div>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($all_categories as $citem): ?>
                                    <?php 
                                    $cid = (int)$citem['id'];
                                    $is_checked = in_array($cid, $selected_pl_cat_ids);
                                    ?>
                                    <div class="col-6">
                                        <div class="form-check p-2 border rounded-2 d-flex align-items-center justify-content-between bg-white h-100">
                                            <div class="d-flex align-items-center">
                                                <input class="form-check-input pl-cat-item-chk ms-0 me-2" type="checkbox" name="price_list_categories[]" id="pl_cat_chk_<?php echo $cid; ?>" value="<?php echo $cid; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                <label class="form-check-label small fw-semibold text-truncate" for="pl_cat_chk_<?php echo $cid; ?>" style="max-width: 170px;" title="<?php echo htmlspecialchars($citem['name']); ?>">
                                                    <?php echo htmlspecialchars($citem['name']); ?>
                                                </label>
                                            </div>
                                            <span class="badge bg-light text-muted border ms-1" style="font-size: 0.7rem;"><?php echo (int)$citem['product_count']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted d-block mt-1">Jis-jis category par tick laga hoga, Price List me sirf wahi categories aur unke products show honge.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Wholesale Terms & Notes (Footer)</label>
                            <textarea name="price_list_note" class="form-control" rows="3" placeholder="Enter terms, payment instructions, delivery terms..."><?php echo htmlspecialchars($pl_note); ?></textarea>
                            <small class="text-muted">Displayed at the bottom of the generated PDF catalog.</small>
                        </div>

                        <!-- Column Visibility Checkboxes -->
                        <label class="form-label fw-bold text-dark mb-2">Visible Table Columns in PDF</label>
                        <div class="row g-2">
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_image" id="col_img" value="1" <?php echo $pl_show_image ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_img">Product Image</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_sku" id="col_sku" value="1" <?php echo $pl_show_sku ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_sku">SKU Code</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_category" id="col_cat" value="1" <?php echo $pl_show_category ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_cat">Category Name</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_regular_price" id="col_reg" value="1" <?php echo $pl_show_regular ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_reg">Regular MRP</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_bulk_price" id="col_bulk" value="1" <?php echo $pl_show_bulk ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_bulk">Wholesale Rate</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_moq" id="col_moq" value="1" <?php echo $pl_show_moq ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_moq">Bulk Min Qty (MOQ)</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_stock" id="col_stock" value="1" <?php echo $pl_show_stock ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_stock">Stock Status</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_sale_price" id="col_sale_price" value="1" <?php echo $pl_show_sale_price ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_sale_price">Sale Price (₹)</label>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="form-check p-2 border rounded-2">
                                    <input class="form-check-input" type="checkbox" name="price_list_show_share" id="col_share" value="1" <?php echo $pl_show_share ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="col_share">Social Share / Copy Link</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i> Save Price List Configuration
                    </button>
                </div>
            </div>

            <!-- Right Column: Information & Live Preview Assistant -->
            <div class="col-lg-5">
                <!-- Instructions Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-shield-alt text-warning fs-4"></i>
                            <h5 class="fw-bold mb-0 text-white">How This Feature Works</h5>
                        </div>
                        <p class="small text-light text-opacity-75 mb-3">
                            This feature provides a dedicated, protected channel for wholesale buyers and store admins:
                        </p>
                        <ul class="small text-light text-opacity-90 ps-3 mb-3" style="line-height: 1.8;">
                            <li><strong>Security First:</strong> Only users logged in as <code>admin</code> or <code>retailer</code> can view or download. Guests and regular customers will see an Access Denied notice.</li>
                            <li><strong>Frontend Visibility:</strong> When enabled, download buttons automatically appear in the user profile menu, shop page, and dashboard.</li>
                            <li><strong>Instant 1-Click PDF:</strong> Buyers can click "Download PDF" to automatically save a clean A4 PDF file to their device or print high-res copies.</li>
                            <li><strong>Real-time Accuracy:</strong> Whenever you change product bulk prices in <a href="manage_products.php" class="text-warning text-decoration-underline">Manage Products</a>, the dynamic price list updates automatically.</li>
                        </ul>

                        <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); border: 1px dashed rgba(255,255,255,0.25);">
                            <span class="small fw-bold d-block text-warning mb-2">
                                <i class="fas fa-share-alt me-1"></i> Direct Price List URL & Share:
                            </span>
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="adminShareUrlPl" class="form-control form-control-sm bg-dark text-white border-secondary" readonly value="<?php echo htmlspecialchars($preview_url); ?>">
                                <button class="btn btn-warning fw-bold text-dark btn-sm" type="button" onclick="copyAdminShareUrl('adminShareUrlPl', this)">
                                    <i class="fas fa-copy me-1"></i> Copy Link
                                </button>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="https://api.whatsapp.com/send?text=<?php echo urlencode("Sagar Starter's Wholesale & Retailer Price List (Official PDF): " . $preview_url); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3 py-1">
                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                </a>
                                <a href="https://t.me/share/url?url=<?php echo urlencode($preview_url); ?>&text=<?php echo urlencode("Sagar Starter's Wholesale Price List"); ?>" target="_blank" class="btn btn-sm btn-info text-white rounded-pill px-3 py-1">
                                    <i class="fab fa-telegram-plane me-1"></i> Telegram
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Quick Action Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h6 class="fw-bold m-0 text-dark">
                            <i class="fas fa-desktop text-primary me-2"></i> Quick Verification
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">Test how the price list looks right now from the frontend perspective:</p>
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars($preview_url); ?>" target="_blank" class="btn btn-outline-primary rounded-pill py-2 fw-semibold">
                                <i class="fas fa-eye me-2"></i> Open Frontend Price List View
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
<form id="deletePdfForm" method="POST" action="manage_price_list.php" style="display: none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="delete_custom_pdf">
</form>

<script>
function setAllPlCatCheckboxes(val) {
    document.querySelectorAll('.pl-cat-item-chk').forEach(function(cb) {
        cb.checked = val;
    });
}

function toggleSourceSections() {
    var isCustom = document.getElementById('source_custom').checked;
    var customCard = document.getElementById('customPdfCard');
    var dynamicCard = document.getElementById('dynamicPdfCard');
    if (customCard) customCard.style.display = isCustom ? 'block' : 'none';
    if (dynamicCard) dynamicCard.style.display = isCustom ? 'none' : 'block';
}

function copyAdminShareUrl(inputId, btn) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.select();
    inp.setSelectionRange(0, 99999);
    var origHtml = btn.innerHTML;
    
    function done() {
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-success', 'text-white');
        setTimeout(function() {
            btn.innerHTML = origHtml;
            btn.classList.remove('btn-success', 'text-white');
            btn.classList.add('btn-warning');
        }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inp.value).then(done).catch(function() {
            document.execCommand('copy');
            done();
        });
    } else {
        document.execCommand('copy');
        done();
    }
}
</script>

<?php include 'admin_footer.php'; ?>
