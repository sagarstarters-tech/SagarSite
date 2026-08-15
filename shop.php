<?php
include 'includes/header.php';

$whereClauses = [];
$params = [];
$types = "";

// 1. Pagination Setup
$limit = 12; // Products per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 2. Build Filters
// Category filter logic
$cat_id = null;
$cat_name = '';
$is_cat_conflict = false;

if (isset($_GET['category_slug']) && trim($_GET['category_slug']) !== '') {
    $slug = trim($_GET['category_slug']);
    $cat_stmt = $conn->prepare("SELECT id, name FROM categories WHERE slug = ?");
    $cat_stmt->bind_param("s", $slug);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result();
    if ($cat_res->num_rows > 0) {
        $cat_data = $cat_res->fetch_assoc();
        $cat_id = (int)$cat_data['id'];
        $cat_name = $cat_data['name'];
    }
    $cat_stmt->close();
} elseif (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $cat_id = (int)$_GET['category'];
    $cat_stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $cat_stmt->bind_param("i", $cat_id);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result();
    if ($cat_res->num_rows > 0) {
        $cat_data = $cat_res->fetch_assoc();
        $cat_name = $cat_data['name'];
    }
    $cat_stmt->close();
}

// 3. Smart Product Finder / Starter Selector Filters
// Phase Filter
$phase_filter_applied = false;
$phase_label = '';
if (isset($_GET['phase']) && trim($_GET['phase']) !== '') {
    $phaseVal = trim($_GET['phase']);
    if (stripos($phaseVal, '1') !== false || stripos($phaseVal, 'single') !== false) {
        $whereClauses[] = "(name REGEXP '(^|[^0-9])(1[[:space:]]*-?[[:space:]]*Phase|Single[[:space:]]*-?[[:space:]]*Phase|1[[:space:]]*Ph|220[[:space:]]*V|230[[:space:]]*V)' OR description REGEXP '(^|[^0-9])(1[[:space:]]*-?[[:space:]]*Phase|Single[[:space:]]*-?[[:space:]]*Phase|1[[:space:]]*Ph|220[[:space:]]*V|230[[:space:]]*V)' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Single Phase%' OR name LIKE '%1 Hp%'))";
        $phase_filter_applied = true;
        $phase_label = '1-Phase (220V)';
        // If category contradicts 1-Phase (e.g. 3 phase category selected), bypass category_id constraint
        if ($cat_name !== '' && (stripos($cat_name, '3 phase') !== false || stripos($cat_name, 'three phase') !== false || stripos($cat_name, 'star delta') !== false)) {
            $is_cat_conflict = true;
        }
    } elseif (stripos($phaseVal, '3') !== false || stripos($phaseVal, 'three') !== false) {
        $whereClauses[] = "(name REGEXP '(^|[^0-9])(3[[:space:]]*-?[[:space:]]*Phase|Three[[:space:]]*-?[[:space:]]*Phase|3[[:space:]]*Ph|415[[:space:]]*V|440[[:space:]]*V|Star[[:space:]]*-?[[:space:]]*Delta|DOL)' OR description REGEXP '(^|[^0-9])(3[[:space:]]*-?[[:space:]]*Phase|Three[[:space:]]*-?[[:space:]]*Phase|3[[:space:]]*Ph|415[[:space:]]*V|440[[:space:]]*V|Star[[:space:]]*-?[[:space:]]*Delta)' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%3%Phase%' OR name LIKE '%Three Phase%' OR name LIKE '%Star Delta%'))";
        $phase_filter_applied = true;
        $phase_label = '3-Phase (415V)';
        // If category contradicts 3-Phase (e.g. Single Phase category selected), bypass category_id constraint
        if ($cat_name !== '' && stripos($cat_name, 'single phase') !== false) {
            $is_cat_conflict = true;
        }
    }
}

// Apply category filter only when not in direct contradiction with phase selection
if ($cat_id !== null && !$is_cat_conflict) {
    $whereClauses[] = "category_id = ?";
    $params[] = $cat_id;
    $types .= "i";
}

// HP Rating Filter
$hp_label = '';
if (isset($_GET['hp']) && trim($_GET['hp']) !== '') {
    $hpVal = strtolower(trim($_GET['hp']));
    if (strpos($hpVal, '10') !== false || strpos($hpVal, '15') !== false || strpos($hpVal, '20') !== false || strpos($hpVal, '25') !== false || strpos($hpVal, '30') !== false) {
        $whereClauses[] = "(name REGEXP '(^|[^0-9.])(10|12[.]5|15|20|25|30)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR description REGEXP '(^|[^0-9.])(10|12[.]5|15|20|25|30)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR name LIKE '%10-25%' OR name LIKE '%10 to 25%' OR name LIKE '%up to 20%' OR name LIKE '%up to 25%' OR description LIKE '%up to 20%' OR description LIKE '%up to 25%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Star Delta%'))";
        $hp_label = '10 - 25+ HP';
    } elseif (strpos($hpVal, '5-7.5') !== false || strpos($hpVal, '5') !== false || strpos($hpVal, '7.5') !== false || strpos($hpVal, '6') !== false || strpos($hpVal, '7') !== false) {
        $whereClauses[] = "(name REGEXP '(^|[^0-9.])(5([.]5)?|6|7([.]5)?)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR description REGEXP '(^|[^0-9.])(5([.]5)?|6|7([.]5)?)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR name LIKE '%5-7.5%' OR name LIKE '%5 to 7.5%')";
        $hp_label = '5 - 7.5 HP';
    } elseif (strpos($hpVal, '1-3') !== false || strpos($hpVal, '1') !== false || strpos($hpVal, '2') !== false || strpos($hpVal, '3') !== false || strpos($hpVal, '0.5') !== false || strpos($hpVal, '1.5') !== false) {
        $whereClauses[] = "(name REGEXP '(^|[^0-9.])(0?[.][5-9]|1([.]5)?|2|3)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR description REGEXP '(^|[^0-9.])(0?[.][5-9]|1([.]5)?|2|3)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR name LIKE '%1-3%' OR name LIKE '%1 to 3%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%1 Hp%'))";
        $hp_label = '1 - 3 HP';
    }
}

// Application Filter
$app_label = '';
if (isset($_GET['app']) && trim($_GET['app']) !== '') {
    $appVal = strtolower(trim($_GET['app']));
    if (strpos($appVal, 'submersible') !== false) {
        $whereClauses[] = "(name LIKE '%Submersible%' OR name LIKE '%Pump%' OR description LIKE '%Submersible%' OR description LIKE '%Pump%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Submersible%' OR name LIKE '%3%Phase%' OR name LIKE '%Star Delta%'))";
        $app_label = 'Submersible Pump';
    } elseif (strpos($appVal, 'openwell') !== false || strpos($appVal, 'monoblock') !== false) {
        $whereClauses[] = "(name LIKE '%Openwell%' OR name LIKE '%Monoblock%' OR name LIKE '%Pump%' OR description LIKE '%Openwell%' OR description LIKE '%Monoblock%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%3%Phase%' OR name LIKE '%Submersible%'))";
        $app_label = 'Openwell / Monoblock';
    } elseif (strpos($appVal, 'flourmill') !== false || strpos($appVal, 'heavy') !== false || strpos($appVal, 'star delta') !== false) {
        $whereClauses[] = "(name LIKE '%Flour Mill%' OR name LIKE '%Heavy%' OR name LIKE '%Star Delta%' OR name LIKE '%Chakki%' OR description LIKE '%Flour Mill%' OR description LIKE '%Star Delta%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Star Delta%' OR name LIKE '%3%Phase%'))";
        $app_label = 'Flour Mill / Heavy Motor';
    }
}

// Search Keyword
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $whereClauses[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

// Trending
if (isset($_GET['trending']) && $_GET['trending'] == 1) {
    $whereClauses[] = "is_trending = 1";
}

$whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Sorting — whitelist allowed values only
$allowed_sorts = ['price_asc' => 'ORDER BY price ASC', 'price_desc' => 'ORDER BY price DESC', 'newest' => 'ORDER BY created_at DESC'];
$sort_key = isset($_GET['sort']) && isset($allowed_sorts[$_GET['sort']]) ? $_GET['sort'] : 'newest';
$orderSql = $allowed_sorts[$sort_key];

// 3. Get Total for Pagination
$count_query = "SELECT COUNT(*) as total FROM products $whereSql";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$count_stmt->close();

// 4. Fetch Products
$sql = "SELECT * FROM products $whereSql $orderSql LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt_types = $types . "ii";
$stmt_params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($stmt_types, ...$stmt_params);
$stmt->execute();
$prods = $stmt->get_result();

$cats = $conn->query("SELECT * FROM categories ORDER BY id ASC");
?>

<div class="container mt-5 mb-5">
<?php 
$setting_key = 'hero_banner_category';
$hero_bg_class = "bg-light border";
$hero_style = "";
$text_color = "primary-blue";
$text_muted = "text-muted";

if (!empty($global_settings[$setting_key])) {
    $img_url = htmlspecialchars(resolve_image_url($global_settings[$setting_key]));
    if (!empty($img_url) && strpos($img_url, 'placeholder') === false) {
        $hero_bg_class = "bg-dark text-white border-0";
        $hero_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
        $text_color = "text-white";
        $text_muted = "text-light";
    }
}
?>
    <div class="row mb-5">
        <div class="col-12 text-center <?php echo $hero_bg_class; ?> p-4 p-md-5 rounded-3" style="<?php echo $hero_style; ?>" data-aos="fade-down">
            <h1 class="display-5 fw-bold montserrat <?php echo $text_color; ?>">Our Shop</h1>
            <p class="lead <?php echo $text_muted; ?>">Browse our amazing collection</p>
        </div>
    </div>
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4" data-aos="fade-right">
            <form method="GET" action="<?php echo SITE_URL; ?>/shop.php" class="card product-card p-3 shadow-sm border-0">
                <h5 class="fw-bold mb-3">Search</h5>
                <div class="input-group mb-4">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                </div>

                <h5 class="fw-bold mb-3">Categories</h5>
                <ul class="list-unstyled mb-4">
                    <li class="mb-1">
                        <a href="<?php echo SITE_URL; ?>/shop.php" class="category-link <?php echo (!$cat_id || $is_cat_conflict) ? 'active fw-bold' : ''; ?>">All Categories</a>
                    </li>
                    <?php while($c = $cats->fetch_assoc()): 
                        $is_active_cat = ($cat_id == $c['id'] && !$is_cat_conflict);
                    ?>
                    <li class="mb-1">
                        <a href="<?php echo SITE_URL; ?>/shop.php?category=<?php echo $c['id']; ?>" class="category-link <?php echo $is_active_cat ? 'active fw-bold' : ''; ?>">
                            <?php echo htmlspecialchars($c['name']); ?>
                        </a>
                    </li>
                    <?php endwhile; ?>
                </ul>

                <h5 class="fw-bold mb-3">Sort By</h5>
                <select name="sort" class="form-select form-control mb-3" onchange="this.form.submit()">
                    <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
                
                <?php if(isset($_GET['phase'])): ?>
                    <input type="hidden" name="phase" value="<?php echo htmlspecialchars($_GET['phase']); ?>">
                <?php endif; ?>
                <?php if(isset($_GET['hp'])): ?>
                    <input type="hidden" name="hp" value="<?php echo htmlspecialchars($_GET['hp']); ?>">
                <?php endif; ?>
                <?php if(isset($_GET['app'])): ?>
                    <input type="hidden" name="app" value="<?php echo htmlspecialchars($_GET['app']); ?>">
                <?php endif; ?>
                <?php if($cat_id && !$is_cat_conflict): ?>
                    <input type="hidden" name="category" value="<?php echo $cat_id; ?>">
                <?php endif; ?>
                <?php if(isset($_GET['category_slug']) && !$is_cat_conflict): ?>
                    <input type="hidden" name="category_slug" value="<?php echo htmlspecialchars($_GET['category_slug']); ?>">
                <?php endif; ?>
                
                <?php if(isset($_GET['search']) || isset($_GET['phase']) || isset($_GET['hp']) || isset($_GET['app']) || ($cat_id && !$is_cat_conflict) || (isset($_GET['sort']) && $_GET['sort'] != 'newest')): ?>
                    <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Product Grid -->
        <div class="col-lg-9">
            <?php 
            $has_active_filters = (!empty($_GET['search']) || $phase_label !== '' || $hp_label !== '' || $app_label !== '' || ($cat_id && !$is_cat_conflict) || (isset($_GET['trending']) && $_GET['trending'] == 1));
            if ($has_active_filters): 
            ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-3 bg-white rounded-3 shadow-sm border">
                <span class="text-muted small fw-bold"><i class="fas fa-filter text-primary me-1"></i> Active Filters:</span>
                <?php if ($phase_label): ?>
                    <span class="badge bg-primary text-white px-3 py-2 rounded-pill">
                        <i class="fas fa-bolt me-1"></i> <?php echo htmlspecialchars($phase_label); ?>
                    </span>
                <?php endif; ?>
                <?php if ($hp_label): ?>
                    <span class="badge bg-info text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-gauge-high me-1"></i> <?php echo htmlspecialchars($hp_label); ?>
                    </span>
                <?php endif; ?>
                <?php if ($app_label): ?>
                    <span class="badge bg-secondary text-white px-3 py-2 rounded-pill">
                        <i class="fas fa-cog me-1"></i> <?php echo htmlspecialchars($app_label); ?>
                    </span>
                <?php endif; ?>
                <?php if ($cat_id && !$is_cat_conflict && !empty($cat_name)): ?>
                    <span class="badge bg-dark text-white px-3 py-2 rounded-pill">
                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($cat_name); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($_GET['search'])): ?>
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-search me-1"></i> "<?php echo htmlspecialchars($_GET['search']); ?>"
                    </span>
                <?php endif; ?>
                <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-sm btn-outline-danger rounded-pill ms-auto">
                    <i class="fas fa-times me-1"></i> Clear All
                </a>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <?php if($prods && $prods->num_rows > 0): ?>
                    <?php $delay=100; while($p = $prods->fetch_assoc()): ?>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=100; ?>">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <?php
                            $main_img_src = resolve_product_image_url($p['image'] ?? '', $conn, $p['id']);
                            ?>
                            <img src="<?php echo htmlspecialchars($main_img_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="card-img-top" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy" style="object-fit: <?php echo htmlspecialchars($p['image_fit'] ?? 'contain'); ?>; background-color:#fff;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($p['name']); ?></h5>
                                <p class="card-text text-muted small text-truncate mb-3"><?php echo htmlspecialchars(!empty($p['short_description']) ? $p['short_description'] : $p['description']); ?></p>
                                <div class="mt-auto d-flex flex-wrap justify-content-between align-items-center pt-3 border-top gap-2">
                                    <?php if ($p['sale_price'] > 0): ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-muted text-decoration-line-through small" style="line-height:1;"><?php echo $global_currency; ?><?php echo number_format($p['regular_price'], 2); ?></span>
                                            <span class="fs-5 fw-bold text-danger" style="line-height:1;"><?php echo $global_currency; ?><?php echo number_format($p['sale_price'], 2); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="fs-5 fw-bold primary-blue"><?php echo $global_currency; ?><?php echo number_format($p['regular_price'] > 0 ? $p['regular_price'] : $p['price'], 2); ?></span>
                                    <?php endif; ?>
                                    <?php 
                                        $p_url = !empty($p['slug']) ? SITE_URL . "/product/" . $p['slug'] : SITE_URL . "/product.php?id=" . $p['id'];
                                    ?>
                                    <a href="<?php echo $p_url; ?>" class="btn btn-outline-primary btn-custom btn-sm">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted">Try adjusting your filters or search query.</p>
                        <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary btn-custom mt-2">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination UI -->
            <?php if($total_pages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination pagination-circle justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

