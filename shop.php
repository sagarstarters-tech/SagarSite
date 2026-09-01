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
        $whereClauses[] = "(name REGEXP '(^|[^0-9])(1[[:space:]]*-?[[:space:]]*Phase|Single[[:space:]]*-?[[:space:]]*Phase|1[[:space:]]*Ph|220[[:space:]]*V|230[[:space:]]*V)' OR description REGEXP '(^|[^0-9])(1[[:space:]]*-?[[:space:]]*Phase|Single[[:space:]]*-?[[:space:]]*Phase|1[[:space:]]*Ph|220[[:space:]]*V|230[[:space:]]*V)' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Single Phase%' OR name LIKE '%Submersible Pump%'))";
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
        $whereClauses[] = "(name REGEXP '(^|[^0-9.])(0?[.][5-9]|1([.]5)?|2|3)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR description REGEXP '(^|[^0-9.])(0?[.][5-9]|1([.]5)?|2|3)[[:space:]]*(HP|H[.]P|H\\\\.P|hp|H\\\\.P\\\\.)' OR name LIKE '%1-3%' OR name LIKE '%1 to 3%')";
        $hp_label = '1 - 3 HP';
    }
}

// Application Filter
$app_label = '';
if (isset($_GET['app']) && trim($_GET['app']) !== '') {
    $appVal = strtolower(trim($_GET['app']));
    if (strpos($appVal, 'submersible') !== false) {
        $whereClauses[] = "(name LIKE '%Submersible%' OR description LIKE '%Submersible%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Submersible%' OR name LIKE '%3%Phase%' OR name LIKE '%Star Delta%'))";
        $app_label = 'Submersible Pump';
    } elseif (strpos($appVal, 'openwell') !== false || strpos($appVal, 'monoblock') !== false) {
        $whereClauses[] = "((name LIKE '%Openwell%' OR name LIKE '%Monoblock%' OR description LIKE '%Openwell%' OR description LIKE '%Monoblock%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Openwell%' OR name LIKE '%Monoblock%')) AND name NOT LIKE '%Submersible%' AND category_id NOT IN (SELECT id FROM categories WHERE name LIKE '%Submersible%'))";
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
$sql = "SELECT products.*, (SELECT name FROM categories WHERE id = products.category_id) as category_name FROM products $whereSql $orderSql LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt_types = $types . "ii";
$stmt_params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($stmt_types, ...$stmt_params);
$stmt->execute();
$prods = $stmt->get_result();

$cats = $conn->query("SELECT * FROM categories ORDER BY id ASC");
?>

<div class="container mt-5 mb-5"<?php
// Analytics: expose search query and result count as data attributes (read-only by tracker JS)
if (!empty($_GET['search'])) {
    echo ' data-analytics-search="' . htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') . '"';
    echo ' data-analytics-results="' . (int)$total_results . '"';
}
?>>
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
                <h5 class="fw-bold mb-3"><i class="fas fa-search me-2"></i>Search</h5>
                <div class="input-group mb-4">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                </div>

                <h5 class="fw-bold mb-3"><i class="fas fa-list me-2"></i>Categories</h5>
                <div class="list-group list-group-flush mb-4">
                    <a href="<?php echo SITE_URL; ?>/shop.php" class="list-group-item list-group-item-action border-0 px-0 <?php echo empty($_GET['category']) ? 'fw-bold text-primary' : ''; ?>">All Categories</a>
                    <?php while($cat = $cats->fetch_assoc()): ?>
                        <a href="<?php echo SITE_URL; ?>/shop.php?category=<?php echo $cat['id']; ?>" class="list-group-item list-group-item-action border-0 px-0 <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'fw-bold text-primary' : ''; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>

                <h5 class="fw-bold mb-3"><i class="fas fa-sort me-2"></i>Sort By</h5>
                <select name="sort" class="form-select mb-3" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sort_key === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
                    <option value="price_asc" <?php echo $sort_key === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort_key === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>

                <!-- Hidden inputs to preserve other filters on submit -->
                <?php if (isset($_GET['category'])): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['phase'])): ?>
                    <input type="hidden" name="phase" value="<?php echo htmlspecialchars($_GET['phase']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['hp'])): ?>
                    <input type="hidden" name="hp" value="<?php echo htmlspecialchars($_GET['hp']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['app'])): ?>
                    <input type="hidden" name="app" value="<?php echo htmlspecialchars($_GET['app']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['trending'])): ?>
                    <input type="hidden" name="trending" value="<?php echo htmlspecialchars($_GET['trending']); ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <?php 
            $has_active_filters = (!empty($_GET['category']) && !$is_cat_conflict) || !empty($_GET['phase']) || !empty($_GET['hp']) || !empty($_GET['app']) || !empty($_GET['search']);
            if ($has_active_filters): 
            ?>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-4 p-3 bg-light rounded-3 border">
                <span class="text-muted small fw-bold"><i class="fas fa-filter me-1 text-primary"></i> Active Filters:</span>
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
                    <?php 
                    $wa_phone = get_store_whatsapp_number();
                    $delay=100; 
                    while($p = $prods->fetch_assoc()): 
                        $main_img_src = resolve_product_image_url($p['image'] ?? '', $conn, $p['id']);
                        $p_url = !empty($p['slug']) ? SITE_URL . "/product/" . $p['slug'] : SITE_URL . "/product.php?id=" . $p['id'];
                        $reg_price = (float)($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                        $sale_price = (float)($p['sale_price'] ?? 0);
                        $has_discount = ($sale_price > 0 && $sale_price < $reg_price);
                        $display_price = $has_discount ? $sale_price : $reg_price;
                        $discount_percent = $has_discount ? round((($reg_price - $sale_price) / $reg_price) * 100) : 0;
                        
                        // WhatsApp Direct Order Text — upgraded rich format
                        $wa_msg = rawurlencode(
                            "Hello Sagar Starters!" . "\n\n" .
                            "I am interested in ordering:" . "\n\n" .
                            "*Product:* " . $p['name'] . "\n\n" .
                            "*Price:* " . $global_currency . number_format($display_price, 2) . "\n\n" .
                            "*Quantity:* 1" . "\n\n" .
                            "*Product Link:*" . "\n" . $p_url . "\n\n" .
                            "Please confirm stock availability and delivery charges." . "\n\n" .
                            "Thank you."
                        );
                        $wa_link = "https://wa.me/{$wa_phone}?text={$wa_msg}";
                        
                        $p_moq = !empty($p['min_order_qty']) ? (int)$p['min_order_qty'] : 1;
                        $p_bulk_price = !empty($p['bulk_price']) ? (float)$p['bulk_price'] : 0;
                        $p_bulk_qty = !empty($p['bulk_min_qty']) && (int)$p['bulk_min_qty'] > 0 ? (int)$p['bulk_min_qty'] : 12;
                        $is_retailer_user = (isset($_SESSION['role']) && $_SESSION['role'] === 'retailer');

                        $avg_rating = isset($p['average_rating']) ? (float)$p['average_rating'] : 0.0;
                        $reviews_cnt = isset($p['review_count']) ? (int)$p['review_count'] : 0;
                        $has_reviews = ($reviews_cnt > 0 && $avg_rating > 0);
                    ?>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=50; ?>">
                        <div class="product-card-pro">
                            <!-- Media Stage -->
                            <div class="product-media-stage">
                                <?php if ($has_discount): ?>
                                    <span class="product-badge-discount">
                                        <i class="fas fa-tag me-1"></i><?php echo $discount_percent; ?>% OFF
                                    </span>
                                <?php elseif (!empty($p['is_trending'])): ?>
                                    <span class="product-badge-trending">
                                        <i class="fas fa-fire me-1"></i>TOP PICK
                                    </span>
                                <?php endif; ?>

                                <?php if (isset($p['stock']) && (int)$p['stock'] <= 0): ?>
                                    <span class="product-badge-stock" style="background-color: rgba(239, 68, 68, 0.12); color: #dc2626; border-color: rgba(239, 68, 68, 0.25);">
                                        <span class="stock-dot" style="background-color: #dc2626;"></span> Out of Stock
                                    </span>
                                <?php else: ?>
                                    <span class="product-badge-stock">
                                        <span class="stock-dot"></span> In Stock
                                    </span>
                                <?php endif; ?>

                                <img src="<?php echo htmlspecialchars($main_img_src); ?>" 
                                     onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" 
                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                     loading="lazy" 
                                     decoding="async" 
                                     width="300" 
                                     height="300">
                            </div>

                            <!-- Details Body -->
                            <div class="product-card-pro-body">
                                <?php if (!empty($p['category_name'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/shop.php?category=<?php echo (int)$p['category_id']; ?>" class="product-category-tag">
                                        <?php echo htmlspecialchars($p['category_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="product-category-tag">Motor Starter</span>
                                <?php endif; ?>

                                <a href="<?php echo $p_url; ?>" class="product-pro-title" title="<?php echo htmlspecialchars($p['name']); ?>">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>

                                <!-- Rating Row (100% Genuine, Matching Homepage) -->
                                <div class="product-rating-row">
                                    <?php if ($has_reviews): ?>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($avg_rating >= $i): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php elseif ($avg_rating >= $i - 0.5): ?>
                                                    <i class="fas fa-star-half-alt"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star" style="color: #cbd5e1 !important;"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-score-text"><?php echo number_format($avg_rating, 1); ?> (<?php echo $reviews_cnt; ?>)</span>
                                    <?php else: ?>
                                        <div class="rating-stars" style="color: #cbd5e1 !important;">
                                            <i class="far fa-star"></i>
                                            <i class="far fa-star"></i>
                                            <i class="far fa-star"></i>
                                            <i class="far fa-star"></i>
                                            <i class="far fa-star"></i>
                                        </div>
                                        <span class="rating-score-text text-muted" style="font-weight: 500; font-size: 0.72rem;">No reviews yet</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Pricing Wrap -->
                                <div class="product-pricing-wrap">
                                    <div class="d-flex align-items-baseline justify-content-between w-100 flex-wrap gap-1">
                                        <div class="d-flex align-items-baseline">
                                            <?php if ($has_discount): ?>
                                                <span class="price-regular-cut"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                                <span class="price-sale-bold text-danger"><?php echo $global_currency; ?><?php echo number_format($sale_price, 2); ?></span>
                                            <?php else: ?>
                                                <span class="price-sale-bold"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($p_bulk_price > 0): ?>
                                            <?php if ($is_retailer_user): ?>
                                                <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; font-size: 0.72rem; font-weight: 600;">
                                                    <i class="fas fa-store"></i>Retailer: <?php echo $global_currency . number_format($p_bulk_price, 2); ?> (2+)
                                                </span>
                                            <?php else: ?>
                                                <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.72rem; font-weight: 600;">
                                                    <i class="fas fa-layer-group"></i>Bulk: <?php echo $global_currency . number_format($p_bulk_price, 2); ?> (<?php echo $p_bulk_qty; ?>+)
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($p_moq > 1): ?>
                                            <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 0.72rem; font-weight: 600;">
                                                <i class="fas fa-boxes"></i>MOQ: <?php echo $p_moq; ?> Units
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Actions Footer -->
                                <div class="product-actions-footer">
                                    <a href="<?php echo $p_url; ?>" class="btn-pro-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <a href="<?php echo $wa_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-pro-wa" title="Order / Enquire on WhatsApp" aria-label="Order on WhatsApp">
                                        <i class="fab fa-whatsapp" style="color: #ffffff !important; font-size: 1.3rem;"></i>
                                    </a>
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

