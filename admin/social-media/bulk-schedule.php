<?php
$current_page = 'social-media/bulk-schedule.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

$pdo = DbConnection::getInstance();
try {
    $pdo->query("SELECT 1 FROM sm_connected_accounts LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}

$csrfToken = csrf_token();

// 1. Total Products Count
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// 2. Fetch Categories
$categories = [];
try {
    $stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback if categories table varies
    $stmtCat = $pdo->query("SELECT DISTINCT category as name FROM products WHERE category IS NOT NULL AND category != ''");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Fetch Brands
$brands = [];
try {
    $stmtBrand = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC");
    $brands = $stmtBrand->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// 4. Fetch Products for Manual Selection
$productList = $pdo->query("SELECT id, name, price FROM products ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Active Connected Accounts & Map by Platform
$stmtAccounts = $pdo->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1 ORDER BY id DESC");
$dbAccounts = $stmtAccounts->fetchAll(PDO::FETCH_ASSOC);
$connectedMap = [];
foreach ($dbAccounts as $acc) {
    $pKey = strtolower($acc['platform']);
    if (!isset($connectedMap[$pKey])) {
        $connectedMap[$pKey] = $acc;
    }
}

// 6. Fetch Templates
$stmtTemplates = $pdo->query("SELECT id, name, is_default FROM sm_templates WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
$templates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);

$platformIcons = [
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'name' => 'Facebook'],
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'name' => 'Instagram'],
    'twitter' => ['icon' => 'fab fa-x-twitter', 'color' => '#000000', 'name' => 'X (Twitter)'],
    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'name' => 'LinkedIn'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088CC', 'name' => 'Telegram'],
    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#E60023', 'name' => 'Pinterest']
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold m-0">Bulk Schedule Posts</h2>
            <p class="text-muted small m-0">Automatically add existing products to the social media posting queue in bulk.</p>
        </div>
        <a href="queue.php" class="btn btn-outline-secondary rounded-pill">
            <i class="fas fa-list me-1"></i> View Queue
        </a>
    </div>

    <form id="bulkScheduleForm">
        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="card shadow border-0 rounded-4">
            <div class="card-body p-4 p-md-5">
                
                <!-- Step 1: Product Selection -->
                <h4 class="mb-3 text-primary fw-bold"><i class="fas fa-box me-2"></i>Step 1: Select Products</h4>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="form-check card-select p-3 border rounded-3 h-100">
                            <input class="form-check-input" type="radio" name="filter_type" id="filterAll" value="all" checked>
                            <label class="form-check-label fw-bold d-block cursor-pointer" for="filterAll">
                                All Products
                                <span class="d-block text-muted small mt-1"><?php echo $totalProducts; ?> Products Total</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check card-select p-3 border rounded-3 h-100">
                            <input class="form-check-input" type="radio" name="filter_type" id="filterCategory" value="category">
                            <label class="form-check-label fw-bold d-block cursor-pointer" for="filterCategory">
                                Category-wise
                                <span class="d-block text-muted small mt-1">Select specific category</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check card-select p-3 border rounded-3 h-100">
                            <input class="form-check-input" type="radio" name="filter_type" id="filterBrand" value="brand">
                            <label class="form-check-label fw-bold d-block cursor-pointer" for="filterBrand">
                                Brand-wise
                                <span class="d-block text-muted small mt-1">Select specific brand</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check card-select p-3 border rounded-3 h-100">
                            <input class="form-check-input" type="radio" name="filter_type" id="filterSelected" value="selected">
                            <label class="form-check-label fw-bold d-block cursor-pointer" for="filterSelected">
                                Manual Selection
                                <span class="d-block text-muted small mt-1">Choose specific products</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Conditional Inputs for Step 1 -->
                <div id="categoryContainer" class="mb-4 d-none">
                    <label class="form-label fw-bold">Select Category</label>
                    <select name="category_id" id="categorySelect" class="form-select rounded-3">
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars((string)($cat['id'] ?? $cat['name'])); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="brandContainer" class="mb-4 d-none">
                    <label class="form-label fw-bold">Select Brand</label>
                    <select name="brand_name" id="brandSelect" class="form-select rounded-3">
                        <option value="">-- Choose Brand --</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="manualContainer" class="mb-4 d-none">
                    <label class="form-label fw-bold">Select Products</label>
                    <div class="border rounded-3 p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                        <?php foreach ($productList as $pItem): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input manual-product-check" type="checkbox" 
                                       name="selected_products[]" value="<?php echo $pItem['id']; ?>" 
                                       id="prod_check_<?php echo $pItem['id']; ?>">
                                <label class="form-check-label small" for="prod_check_<?php echo $pItem['id']; ?>">
                                    <strong><?php echo htmlspecialchars($pItem['name']); ?></strong> (₹<?php echo htmlspecialchars((string)$pItem['price']); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Step 2: Configure Platforms & Templates -->
                <h4 class="mb-3 text-primary fw-bold"><i class="fas fa-cogs me-2"></i>Step 2: Configure Platforms & Templates</h4>
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <label class="form-label fw-bold">Target Platforms <span class="text-danger">*</span></label>
                        
                        <div class="d-flex flex-column gap-2 border p-3 rounded-3 bg-light">
                            <?php foreach ($platformIcons as $pKey => $pMeta): 
                                $isConnected = isset($connectedMap[$pKey]);
                                $accInfo = $isConnected ? $connectedMap[$pKey] : null;
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input platform-check" type="checkbox" 
                                           name="platforms[]" value="<?php echo htmlspecialchars($pKey); ?>" 
                                           id="plat_<?php echo $pKey; ?>" 
                                           <?php echo $isConnected ? 'checked' : 'disabled'; ?>>
                                    <label class="form-check-label fw-semibold" for="plat_<?php echo $pKey; ?>">
                                        <i class="<?php echo $pMeta['icon']; ?> me-2" style="color: <?php echo $pMeta['color']; ?>;"></i>
                                        <?php echo htmlspecialchars($pMeta['name']); ?>
                                        
                                        <?php if ($isConnected): ?>
                                            <span class="badge bg-success ms-2 font-normal">
                                                <i class="fas fa-check-circle me-1"></i> Connected (<?php echo htmlspecialchars($accInfo['account_name'] ?? 'Active'); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border ms-2 font-normal">
                                                Not Connected — <a href="accounts.php" class="text-decoration-underline text-muted">Connect</a>
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-bold">Post Stagger Interval</label>
                            <select name="interval_minutes" id="intervalSelect" class="form-select rounded-3">
                                <option value="5">Every 5 Minutes</option>
                                <option value="15" selected>Every 15 Minutes (Recommended)</option>
                                <option value="30">Every 30 Minutes</option>
                                <option value="60">Every 1 Hour</option>
                            </select>
                            <div class="form-text">Space out bulk posts to avoid rate limits on platforms.</div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Caption Template</label>
                            <select name="template_id" id="templateSelect" class="form-select rounded-3">
                                <option value="">Default Promotion Template</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo $tpl['id']; ?>" <?php echo $tpl['is_default'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tpl['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Call to Action (CTA)</label>
                            <select name="cta" id="ctaSelect" class="form-select rounded-3">
                                <option value="Shop Now 🛒">Shop Now 🛒</option>
                                <option value="Buy Now 🛍️">Buy Now 🛍️</option>
                                <option value="Order Today 📦">Order Today 📦</option>
                                <option value="Limited Time Offer 🔥">Limited Time Offer 🔥</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Custom Hashtags</label>
                            <textarea name="hashtags" id="hashtagsInput" class="form-control rounded-3" rows="2" 
                                      placeholder="#SagarStarters #Sale #Shopping #Trending"></textarea>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Step 3: Review & Confirm -->
                <h4 class="mb-3 text-primary fw-bold"><i class="fas fa-check-circle me-2"></i>Step 3: Review & Confirm</h4>
                
                <div class="alert alert-info rounded-3 p-3 shadow-sm border-0" style="background-color: #e8f4ff;">
                    <i class="fas fa-info-circle text-primary me-2 fs-5"></i>
                    <span id="summaryText" class="fw-semibold text-dark">
                        Summary: Calculating bulk schedule details...
                    </span>
                </div>

                <div id="formAlert"></div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary mdb-ripple px-5 py-3 fw-bold rounded-pill shadow" id="btnConfirmSchedule">
                        <i class="fas fa-paper-plane me-2"></i> Confirm & Schedule Posts
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.card-select:hover { background-color: #f8f9fa; border-color: #0d6efd !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const totalProductsCount = <?php echo $totalProducts; ?>;
    
    // UI elements
    const filterAll = document.getElementById('filterAll');
    const filterCategory = document.getElementById('filterCategory');
    const filterBrand = document.getElementById('filterBrand');
    const filterSelected = document.getElementById('filterSelected');

    const categoryContainer = document.getElementById('categoryContainer');
    const brandContainer = document.getElementById('brandContainer');
    const manualContainer = document.getElementById('manualContainer');

    const summaryText = document.getElementById('summaryText');

    function updateSummary() {
        let prodCount = totalProductsCount;
        let filterName = "All Products";

        if (filterCategory.checked) {
            filterName = "Category Products";
        } else if (filterBrand.checked) {
            filterName = "Brand Products";
        } else if (filterSelected.checked) {
            const checkedManual = document.querySelectorAll('.manual-product-check:checked').length;
            prodCount = checkedManual;
            filterName = "Selected Products";
        }

        const checkedPlatforms = document.querySelectorAll('.platform-check:checked').length;
        const totalPosts = prodCount * checkedPlatforms;

        summaryText.innerHTML = `You are about to schedule <strong>${totalPosts} post(s)</strong> (${prodCount} products × ${checkedPlatforms} platforms) starting now.`;
    }

    // Toggle filter inputs visibility
    document.querySelectorAll('input[name="filter_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            categoryContainer.classList.add('d-none');
            brandContainer.classList.add('d-none');
            manualContainer.classList.add('d-none');

            if (this.value === 'category') categoryContainer.classList.remove('d-none');
            if (this.value === 'brand') brandContainer.classList.remove('d-none');
            if (this.value === 'selected') manualContainer.classList.remove('d-none');

            updateSummary();
        });
    });

    document.querySelectorAll('.platform-check, .manual-product-check').forEach(chk => {
        chk.addEventListener('change', updateSummary);
    });

    updateSummary();

    // Form Submit AJAX
    const form = document.getElementById('bulkScheduleForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnConfirmSchedule');
        const alertDiv = document.getElementById('formAlert');

        const checkedPlatforms = document.querySelectorAll('.platform-check:checked');
        if (checkedPlatforms.length === 0) {
            alertDiv.innerHTML = '<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-circle me-1"></i> Please select at least one target social media platform.</div>';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scheduling Posts...';
        alertDiv.innerHTML = '';

        const formData = new FormData(this);

        // Gather platforms array
        const selectedPlatforms = Array.from(checkedPlatforms).map(c => c.value);
        formData.append('platforms', JSON.stringify(selectedPlatforms));

        // Gather filter_value
        const filterVal = document.querySelector('input[name="filter_type"]:checked').value;
        if (filterVal === 'category') {
            formData.append('filter_value', document.getElementById('categorySelect').value);
        } else if (filterVal === 'brand') {
            formData.append('filter_value', document.getElementById('brandSelect').value);
        } else if (filterVal === 'selected') {
            const manualIds = Array.from(document.querySelectorAll('.manual-product-check:checked')).map(c => c.value);
            formData.append('filter_value', JSON.stringify(manualIds));
        }

        fetch('ajax/ajax_bulk_schedule.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Confirm & Schedule Posts';

            if (data.success) {
                alertDiv.innerHTML = `<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> ${data.message || 'Posts scheduled successfully! Redirecting to queue...'}</div>`;
                setTimeout(() => {
                    window.location.href = 'queue.php';
                }, 1500);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Failed to bulk schedule posts.'}</div>`;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Confirm & Schedule Posts';
            alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Error: ${err.message}</div>`;
        });
    });
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>