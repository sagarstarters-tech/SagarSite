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
// Filter by category slug
if (isset($_GET['category_slug'])) {
    $slug = $_GET['category_slug'];
    $cat_stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
    $cat_stmt->bind_param("s", $slug);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result();
    if ($cat_res->num_rows > 0) {
        $cat_data = $cat_res->fetch_assoc();
        $whereClauses[] = "category_id = ?";
        $params[] = $cat_data['id'];
        $types .= "i";
    }
    $cat_stmt->close();
} elseif (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $whereClauses[] = "category_id = ?";
    $params[] = (int)$_GET['category'];
    $types .= "i";
}

// Search
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

$cats = $conn->query("SELECT * FROM categories");
?>

<div class="container mt-5 mb-5">
<?php 
$setting_key = 'hero_banner_category';
$hero_bg_class = "bg-light border";
$hero_style = "";
$text_color = "primary-blue";
$text_muted = "text-muted";

if (!empty($global_settings[$setting_key]) && file_exists(__DIR__ . '/assets/images/' . $global_settings[$setting_key])) {
    $img_url = htmlspecialchars(ASSETS_URL . '/images/' . $global_settings[$setting_key]);
    $hero_bg_class = "bg-dark text-white border-0";
    $hero_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
    $text_color = "text-white";
    $text_muted = "text-light";
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
                        <a href="<?php echo SITE_URL; ?>/shop.php" class="category-link <?php echo !isset($_GET['category']) && !isset($_GET['category_slug']) ? 'active fw-bold' : ''; ?>">All Categories</a>
                    </li>
                    <?php while($c = $cats->fetch_assoc()): ?>
                    <li class="mb-1">
                        <a href="<?php echo SITE_URL; ?>/shop.php?category=<?php echo $c['id']; ?>" class="category-link <?php echo (isset($_GET['category']) && $_GET['category'] == $c['id']) ? 'active fw-bold' : ''; ?>">
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
                
                <?php if(isset($_GET['category'])): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
                <?php endif; ?>
                <?php if(isset($_GET['category_slug'])): ?>
                    <input type="hidden" name="category_slug" value="<?php echo htmlspecialchars($_GET['category_slug']); ?>">
                <?php endif; ?>
                
                <?php if(isset($_GET['search']) || isset($_GET['category']) || isset($_GET['category_slug']) || (isset($_GET['sort']) && $_GET['sort'] != 'newest')): ?>
                    <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Product Grid -->
        <div class="col-lg-9">
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

