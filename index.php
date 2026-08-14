<?php
include 'includes/header.php';
// Fetch featured categories
$cats = $conn->query("SELECT * FROM categories LIMIT 3");
// Fetch trending products (those marked as trending in admin)
$prods = $conn->query("SELECT * FROM products WHERE is_trending = 1 ORDER BY id DESC LIMIT 12");
?>

<?php 
// Include the new Enterprise Hero Slider
include 'includes/hero-slider.php'; 
?>

<!-- Featured Categories Section -->
<section class="featured-categories-section">
    <div class="container mt-4" data-aos="fade-up">
        <div class="text-center mb-5">
            <span class="section-subtitle-badge mb-2">
                <i class="fas fa-bolt text-warning me-1"></i> TOP COLLECTIONS
            </span>
            <h2 class="section-main-title montserrat fw-bold mt-2 mb-2">Featured Categories</h2>
            <p class="section-desc mx-auto text-muted">Explore high-quality industrial motor starters, submersible controllers, and electrical panels</p>
            <div class="section-divider-line mx-auto"></div>
        </div>
        <div class="row g-4">
            <?php 
            $delay = 100; 
            while($c = $cats->fetch_assoc()): 
                $cat_img = !empty($c['image']) ? resolve_image_url($c['image']) : '';
                if (empty($cat_img) || strpos($cat_img, 'placeholder.svg') !== false) {
                    $cat_id_int = intval($c['id']);
                    $p_img_q = $conn->query("SELECT image FROM products WHERE category_id = $cat_id_int AND image != '' ORDER BY id DESC LIMIT 1");
                    if ($p_img_q && $p_img_row = $p_img_q->fetch_assoc()) {
                        $cat_img = resolve_image_url($p_img_row['image']);
                    }
                }
                if (empty($cat_img)) {
                    $cat_img = ASSETS_URL . '/images/placeholder.svg';
                }
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=100; ?>">
                <a href="shop.php?category=<?php echo $c['id']; ?>" class="category-card-pro text-decoration-none">
                    <div class="category-card-stage">
                        <div class="category-stage-backdrop"></div>
                        <span class="category-badge-chip">
                            <i class="fas fa-certificate text-primary me-1"></i> Heavy Duty
                        </span>
                        <div class="category-img-wrapper">
                            <img src="<?php echo htmlspecialchars($cat_img); ?>" class="category-pro-img" alt="<?php echo htmlspecialchars($c['name']); ?>" loading="lazy" width="400" height="300" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                        </div>
                    </div>
                    <div class="category-card-body">
                        <h3 class="category-pro-title montserrat"><?php echo htmlspecialchars($c['name']); ?></h3>
                        <p class="category-pro-subtitle text-muted">Industrial Grade Protection & Control</p>
                        <div class="category-cta-row">
                            <span class="category-cta-label">Explore Products</span>
                            <span class="category-cta-icon">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- Trending Products -->
<div class="container mt-5 pt-5 mb-5" data-aos="fade-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="montserrat fw-bold m-0">Trending Products</h2>
        <a href="shop.php?trending=1" class="text-decoration-none primary-blue fw-bold">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-4">
        <?php if($prods && $prods->num_rows > 0): ?>
            <?php $delay=100; while($p = $prods->fetch_assoc()): ?>
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=50; ?>">
                <div class="card product-card h-100">
                    <?php
                    $main_img_src = resolve_product_image_url($p['image'] ?? '', $conn, $p['id']);
                    ?>
                    <img src="<?php echo htmlspecialchars($main_img_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="card-img-top" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy" width="400" height="400" style="object-fit: <?php echo htmlspecialchars($p['image_fit'] ?? 'contain'); ?>; background-color:#fff;">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-bold text-truncate"><?php echo htmlspecialchars($p['name']); ?></h5>
                        <p class="card-text text-muted small text-truncate"><?php echo htmlspecialchars(!empty($p['short_description']) ? $p['short_description'] : $p['description']); ?></p>
                        <div class="mt-auto d-flex flex-wrap justify-content-between align-items-center pt-3 border-top gap-2">
                            <?php if ($p['sale_price'] > 0): ?>
                                <div class="d-flex flex-column">
                                    <span class="text-muted text-decoration-line-through small" style="line-height:1;"><?php echo $global_currency; ?><?php echo number_format($p['regular_price'], 2); ?></span>
                                    <span class="fs-5 fw-bold text-danger" style="line-height:1;"><?php echo $global_currency; ?><?php echo number_format($p['sale_price'], 2); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="fs-5 fw-bold primary-blue"><?php echo $global_currency; ?><?php echo number_format($p['regular_price'] > 0 ? $p['regular_price'] : $p['price'], 2); ?></span>
                            <?php endif; ?>
                            <a href="product.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary btn-custom btn-sm"><i class="fas fa-shopping-cart text-reset me-2"></i>View</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-muted col-12">No products found. Admin needs to add some.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/homepage-testimonials.php'; ?>
<?php include 'includes/homepage-features.php'; ?>
<?php include 'includes/footer.php'; ?>
