<?php
include 'includes/header.php';

// Fetch all active categories with product counts and fallback images
$cats_sql = "SELECT c.*, 
             (SELECT p.image FROM products p WHERE p.category_id = c.id AND p.image != '' ORDER BY p.id DESC LIMIT 1) as product_fallback_image,
             (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count 
             FROM categories c 
             ORDER BY c.id ASC";
$cats = $conn->query($cats_sql);

// Fetch trending products with category info
$prods_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_trending = 1 
              ORDER BY p.id DESC 
              LIMIT 12";
$prods = $conn->query($prods_sql);

// Fallback to recent products if no trending products exist
if (!$prods || $prods->num_rows === 0) {
    $prods_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.id DESC 
                  LIMIT 12";
    $prods = $conn->query($prods_sql);
}

// Fetch WhatsApp number for quick order buttons
$wa_phone = !empty($global_settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', $global_settings['whatsapp_number']) : '919837248000';
?>

<?php 
// 1. Hero Slider (Dynamic from Admin Panel)
include 'includes/hero-slider.php'; 
?>

<!-- 2. Value Trust Strip (Below Hero) -->
<div class="container my-4" data-aos="fade-up" data-aos-duration="600">
    <div class="home-trust-strip">
        <div class="row g-3 align-items-center">
            <div class="col-xl col-lg-4 col-md-6 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <div>
                        <h4 class="trust-title">Pan-India Dispatch</h4>
                        <p class="trust-subtitle">Fast & insured doorstep delivery</p>
                    </div>
                </div>
            </div>
            <div class="col-xl col-lg-4 col-md-6 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <h4 class="trust-title">100% Genuine Copper</h4>
                        <p class="trust-subtitle">1-Year replacement warranty</p>
                    </div>
                </div>
            </div>
            <div class="col-xl col-lg-4 col-md-6 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <h4 class="trust-title">Complete Protection</h4>
                        <p class="trust-subtitle">Overload & dry run auto-switch</p>
                    </div>
                </div>
            </div>
            <div class="col-xl col-lg-6 col-md-6 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h4 class="trust-title">Expert Support</h4>
                        <p class="trust-subtitle">Direct engineer consultation</p>
                    </div>
                </div>
            </div>
            <div class="col-xl col-lg-6 col-md-12 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <h4 class="trust-title">Secure & COD</h4>
                        <p class="trust-subtitle">UPI, Cards, Netbanking & COD</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Featured Categories Showcase (All Categories) -->
<section class="featured-categories-section py-4">
    <div class="container" data-aos="fade-up">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-badge-pill mb-2">
                    <i class="fas fa-layer-group me-1"></i> EXPLORE CATALOG
                </span>
                <h2 class="section-title-pro montserrat fw-bold mb-1">Shop by Category</h2>
                <p class="section-desc-pro mb-0">Industrial motor starters, submersible controllers, star delta panels & stabilizers</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/shop.php" class="btn-view-all-link">
                View All Categories <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="row g-4">
            <?php 
            if ($cats && $cats->num_rows > 0):
                $delay = 50; 
                // Category image smart mapping fallback dictionary
                $cat_fallbacks = [
                    'single phase' => 'single hp dgt.webp',
                    '3 phase'      => '3ph.jpeg',
                    'star delta'   => 'star delta gi.webp',
                    'switch'       => 'pvt gi.webp',
                    'pump'         => 'pump set gi.webp',
                    'stabilizer'   => 'stabilizer gi.webp',
                    'software'     => 'AhaConvert_billibgsoftware.webp',
                ];

                while($c = $cats->fetch_assoc()): 
                    $cNameLower = strtolower($c['name']);
                    $matchedAsset = 'AhaConvert_3ph.webp';
                    foreach ($cat_fallbacks as $k => $fImg) {
                        if (strpos($cNameLower, $k) !== false) {
                            $matchedAsset = $fImg;
                            break;
                        }
                    }

                    $rawImg = !empty($c['image']) ? $c['image'] : (!empty($c['product_fallback_image']) ? $c['product_fallback_image'] : 'assets/images/' . $matchedAsset);
                    $cat_img = resolve_image_url($rawImg);
                    $cat_url = !empty($c['slug']) ? SITE_URL . "/shop.php?category_slug=" . urlencode($c['slug']) : SITE_URL . "/shop.php?category=" . (int)$c['id'];
                    $p_count = (int)($c['product_count'] ?? 0);
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=50; ?>">
                <a href="<?php echo $cat_url; ?>" class="category-card-modern">
                    <div class="category-stage">
                        <span class="category-badge-pill-tag">
                            <i class="fas fa-bolt text-warning me-1"></i> Heavy Duty
                        </span>
                        <img src="<?php echo htmlspecialchars($cat_img); ?>" 
                             class="category-stage-img" 
                             alt="<?php echo htmlspecialchars($c['name']); ?>" 
                             loading="lazy" 
                             width="260" 
                             height="180" 
                             onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                    </div>
                    <div class="category-content-wrap">
                        <h3 class="category-card-title montserrat"><?php echo htmlspecialchars($c['name']); ?></h3>
                        <div class="category-meta-row">
                            <span class="category-explore-text">
                                <?php echo $p_count > 0 ? $p_count . ' Products' : 'Explore Range'; ?>
                            </span>
                            <span class="category-arrow-icon">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php 
                endwhile; 
            endif; 
            ?>
        </div>
    </div>
</section>

<!-- 4. Trending & Best Selling Products (Ultra-Pro Cards) -->
<section class="trending-products-section py-5 bg-light-subtle">
    <div class="container" data-aos="fade-up">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-badge-pill mb-2">
                    <i class="fas fa-fire text-danger me-1"></i> BESTSELLERS & TRENDING
                </span>
                <h2 class="section-title-pro montserrat fw-bold mb-1">Featured Motor Starters</h2>
                <p class="section-desc-pro mb-0">High-performance starters and panels engineered for agricultural and industrial pumps</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/shop.php?trending=1" class="btn-view-all-link">
                View All Products <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if ($prods && $prods->num_rows > 0): ?>
                <?php 
                $delay = 50; 
                while($p = $prods->fetch_assoc()): 
                    $main_img_src = resolve_product_image_url($p['image'] ?? '', $conn, $p['id']);
                    $p_url = !empty($p['slug']) ? SITE_URL . "/product/" . $p['slug'] : SITE_URL . "/product.php?id=" . $p['id'];
                    $reg_price = (float)($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                    $sale_price = (float)($p['sale_price'] ?? 0);
                    $has_discount = ($sale_price > 0 && $sale_price < $reg_price);
                    $display_price = $has_discount ? $sale_price : $reg_price;
                    $discount_percent = $has_discount ? round((($reg_price - $sale_price) / $reg_price) * 100) : 0;
                    $rating_val = !empty($p['average_rating']) ? number_format((float)$p['average_rating'], 1) : '4.8';
                    $reviews_cnt = !empty($p['review_count']) ? (int)$p['review_count'] : 24;
                    
                    // WhatsApp Inquiry Text
                    $wa_msg = urlencode("Hello Sagar Starters! I am interested in: *" . $p['name'] . "* (Price: " . $global_currency . number_format($display_price, 2) . "). Please share details.");
                    $wa_link = "https://wa.me/{$wa_phone}?text={$wa_msg}";
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=50; ?>">
                    <div class="product-card-pro">
                        <!-- Media Stage -->
                        <a href="<?php echo $p_url; ?>" class="product-media-stage" title="<?php echo htmlspecialchars($p['name']); ?>">
                            <?php if ($has_discount): ?>
                                <span class="product-badge-discount">
                                    <i class="fas fa-tag me-1"></i><?php echo $discount_percent; ?>% OFF
                                </span>
                            <?php elseif (!empty($p['is_trending'])): ?>
                                <span class="product-badge-trending">
                                    <i class="fas fa-fire me-1"></i>TOP PICK
                                </span>
                            <?php endif; ?>

                            <span class="product-badge-stock">
                                <span class="stock-dot"></span> In Stock
                            </span>

                            <img src="<?php echo htmlspecialchars($main_img_src); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" 
                                 alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                 loading="lazy" 
                                 width="300" 
                                 height="300">
                        </a>

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

                            <!-- Rating Row -->
                            <div class="product-rating-row">
                                <div class="rating-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                                <span class="rating-score-text"><?php echo $rating_val; ?> (<?php echo $reviews_cnt; ?>)</span>
                            </div>

                            <!-- Pricing Wrap -->
                            <div class="product-pricing-wrap">
                                <div class="d-flex align-items-baseline">
                                    <?php if ($has_discount): ?>
                                        <span class="price-regular-cut"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                        <span class="price-sale-bold text-danger"><?php echo $global_currency; ?><?php echo number_format($sale_price, 2); ?></span>
                                    <?php else: ?>
                                        <span class="price-sale-bold"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions Footer -->
                            <div class="product-actions-footer">
                                <a href="<?php echo $p_url; ?>" class="btn-pro-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <a href="<?php echo $wa_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-pro-wa" title="Order / Enquire on WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No products available at the moment. Please visit our shop.</p>
                    <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary rounded-pill px-4">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- 5. Promotional Spotlight Banners -->
<section class="home-spotlight-section py-5">
    <div class="container" data-aos="fade-up">
        <div class="row g-4">
            <div class="col-lg-6 col-12">
                <div class="promo-spotlight-card variant-blue">
                    <div>
                        <span class="promo-badge-tag">
                            <i class="fas fa-water me-1"></i> Agricultural & Submersible
                        </span>
                        <h3 class="promo-card-title montserrat">Submersible Pump Starters & Panels</h3>
                        <p class="promo-card-desc">Equipped with dry run auto cut, electronic overload relays, digital ammeter-voltmeter, and surge safety for borewell motors.</p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/shop.php?category=4" class="promo-cta-btn">
                        Explore Submersible Starters <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="promo-spotlight-card variant-dark">
                    <div>
                        <span class="promo-badge-tag">
                            <i class="fas fa-industry me-1"></i> 3-Phase Industrial Range
                        </span>
                        <h3 class="promo-card-title montserrat">Star Delta & Heavy Duty Panels</h3>
                        <p class="promo-card-desc">Engineered for factories, flour mills, and heavy agricultural motors. 100% heavy copper coils with thermal overload trip mechanism.</p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/shop.php?category=6" class="promo-cta-btn">
                        Explore Star Delta Starters <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 6. Industrial Excellence & Trust Stats Section -->
<section class="container my-5" data-aos="fade-up">
    <div class="home-stats-section">
        <div class="text-center mb-4">
            <span class="section-badge-pill mb-2 text-white bg-white bg-opacity-10 border-white border-opacity-25">
                <i class="fas fa-award text-warning me-1"></i> PROVEN RELIABILITY
            </span>
            <h2 class="montserrat fw-bold text-white mb-2">Why Farmers & Engineers Trust Sagar Starter's</h2>
            <p class="text-white text-opacity-75 mx-auto" style="max-width: 600px;">Over a decade of manufacturing excellence in motor control systems and agricultural power protection.</p>
        </div>

        <div class="row g-4 justify-content-center mt-2">
            <div class="col-lg-3 col-md-6 col-6">
                <div class="stat-item-box">
                    <div class="stat-icon-wrap">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number-big">15+</div>
                    <p class="stat-label-text">Years of Excellence</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-6">
                <div class="stat-item-box">
                    <div class="stat-icon-wrap">
                        <i class="fas fa-shield-virus"></i>
                    </div>
                    <div class="stat-number-big">50,000+</div>
                    <p class="stat-label-text">Motors Protected</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-6">
                <div class="stat-item-box">
                    <div class="stat-icon-wrap">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="stat-number-big">100%</div>
                    <p class="stat-label-text">Pre-Tested Relays</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-6">
                <div class="stat-item-box">
                    <div class="stat-icon-wrap">
                        <i class="fas fa-star text-warning"></i>
                    </div>
                    <div class="stat-number-big">4.9 / 5</div>
                    <p class="stat-label-text">Customer Rating</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php 
// 7. Customer Reviews & Social Proof Slider
include 'includes/homepage-testimonials.php'; 
?>

<?php 
// 8. Homepage Features Bar (Admin Configurable)
include 'includes/homepage-features.php'; 
?>

<?php 
// 9. Global Footer
include 'includes/footer.php'; 
?>
