<?php
/**
 * index.php — SagarStarters.com High-Converting eCommerce Home Page
 * 100% Dynamic, 100% Responsive & Fully Admin-Controllable via manage_homepage.php
 */

include 'includes/header.php';

// Helper function to read homepage configuration with default fallback
if (!function_exists('get_home_cfg')) {
    function get_home_cfg($key, $default = '') {
        global $global_settings;
        return (isset($global_settings[$key]) && $global_settings[$key] !== '') ? $global_settings[$key] : $default;
    }
}

// ── 1. Fetch Categories with Product Counts & Fallbacks ────────────────────────
$cats_sql = "SELECT c.*, 
             (SELECT p.image FROM products p WHERE p.category_id = c.id AND p.image != '' ORDER BY p.id DESC LIMIT 1) as product_fallback_image,
             (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count 
             FROM categories c 
             ORDER BY c.id ASC";
$cats = $conn->query($cats_sql);

// ── 2. Fetch Trending / Featured Products ──────────────────────────────────────
$prods_limit = intval(get_home_cfg('home_prods_count', '12'));
if ($prods_limit < 4 || $prods_limit > 36) $prods_limit = 12;

$prods_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_trending = 1 
              ORDER BY p.id DESC 
              LIMIT $prods_limit";
$prods = $conn->query($prods_sql);

// Fallback to recent products if no trending products exist
if (!$prods || $prods->num_rows === 0) {
    $prods_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.id DESC 
                  LIMIT $prods_limit";
    $prods = $conn->query($prods_sql);
}

// ── 3. Contact & Phone Info ───────────────────────────────────────────────────
$wa_phone = get_store_whatsapp_number();
$contact_phone = !empty($global_settings['contact_phone']) ? $global_settings['contact_phone'] : '+91 8573934013';
$clean_contact_phone = preg_replace('/[^0-9+]/', '', $contact_phone);
?>

<?php 
// ── SECTION 1: HERO BANNER SLIDER (Dynamic from Admin Panel) ──────────────────
include 'includes/hero-slider.php'; 
?>

<?php 
// ── SECTION 2: VALUE & TRUST BADGES STRIP ─────────────────────────────────────
$trust_enabled = get_home_cfg('home_trust_enabled', '1');
if ($trust_enabled == '1'): 
    $trust_items = [
        [
            'icon'  => get_home_cfg('home_trust1_icon', 'fas fa-truck-fast'),
            'title' => get_home_cfg('home_trust1_title', 'Pan-India Dispatch'),
            'desc'  => get_home_cfg('home_trust1_desc', 'Fast & insured doorstep delivery')
        ],
        [
            'icon'  => get_home_cfg('home_trust2_icon', 'fas fa-shield-halved'),
            'title' => get_home_cfg('home_trust2_title', '100% Genuine Copper'),
            'desc'  => get_home_cfg('home_trust2_desc', '1-Year replacement warranty')
        ],
        [
            'icon'  => get_home_cfg('home_trust3_icon', 'fas fa-bolt'),
            'title' => get_home_cfg('home_trust3_title', 'Complete Protection'),
            'desc'  => get_home_cfg('home_trust3_desc', 'Overload & dry run auto-switch')
        ],
        [
            'icon'  => get_home_cfg('home_trust4_icon', 'fab fa-whatsapp'),
            'title' => get_home_cfg('home_trust4_title', 'Expert Support'),
            'desc'  => get_home_cfg('home_trust4_desc', 'Direct engineer consultation')
        ],
        [
            'icon'  => get_home_cfg('home_trust5_icon', 'fas fa-lock'),
            'title' => get_home_cfg('home_trust5_title', 'Secure & COD'),
            'desc'  => get_home_cfg('home_trust5_desc', 'UPI, Cards, Netbanking & COD')
        ],
    ];
?>
<div class="container my-4" data-aos="fade-up" data-aos-duration="600">
    <div class="home-trust-strip">
        <div class="row g-3 align-items-center">
            <?php foreach ($trust_items as $tIdx => $t): ?>
            <div class="col-xl col-lg-4 col-md-6 col-12">
                <div class="trust-item-pro">
                    <div class="trust-icon-box">
                        <i class="<?php echo htmlspecialchars($t['icon']); ?>"></i>
                    </div>
                    <div>
                        <h4 class="trust-title"><?php echo htmlspecialchars($t['title']); ?></h4>
                        <p class="trust-subtitle"><?php echo htmlspecialchars($t['desc']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
// ── SECTION 3: SHOP BY CATEGORY CATALOG ───────────────────────────────────────
$cats_enabled = get_home_cfg('home_cats_enabled', '1');
if ($cats_enabled == '1'): 
?>
<section class="featured-categories-section py-4">
    <div class="container" data-aos="fade-up">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-badge-pill mb-2">
                    <i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_cats_badge', 'EXPLORE CATALOG')); ?>
                </span>
                <h2 class="section-title-pro montserrat fw-bold mb-1"><?php echo htmlspecialchars(get_home_cfg('home_cats_title', 'Shop by Category')); ?></h2>
                <p class="section-desc-pro mb-0"><?php echo htmlspecialchars(get_home_cfg('home_cats_subtitle', 'Industrial motor starters, submersible controllers, star delta panels & stabilizers')); ?></p>
            </div>
            <a href="<?php echo SITE_URL; ?>/shop.php" class="btn-view-all-link">
                <?php echo htmlspecialchars(get_home_cfg('home_cats_btn_text', 'View All Categories')); ?> <i class="fas fa-arrow-right"></i>
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
<?php endif; ?>

<?php 
// ── SECTION 4: SMART MOTOR STARTER FINDER / SELECTOR WIDGET ──────────────────
$selector_enabled = get_home_cfg('home_selector_enabled', '1');
if ($selector_enabled == '1'): 
    $init_phase = get_home_cfg('home_selector_phase1_val', '');
    $init_hp    = get_home_cfg('home_selector_hp1_val', '');
    $init_app   = get_home_cfg('home_selector_app1_val', 'submersible');
    $form_action = get_home_cfg('home_selector_action_url', 'shop.php');
    if (!str_starts_with($form_action, 'http') && !str_starts_with($form_action, '/')) {
        $form_action = SITE_URL . '/' . ltrim($form_action, '/');
    }
?>
<section class="container my-5" data-aos="fade-up">
    <div class="starter-selector-box">
        <div class="row align-items-center g-4">
            <div class="col-lg-4 col-12">
                <span class="section-badge-pill mb-2">
                    <i class="fas fa-sliders me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_badge', 'SMART PRODUCT FINDER')); ?>
                </span>
                <h3 class="montserrat fw-bold mb-2 text-dark"><?php echo htmlspecialchars(get_home_cfg('home_selector_title', 'Find the Right Starter for Your Motor')); ?></h3>
                <p class="text-muted mb-0 small"><?php echo htmlspecialchars(get_home_cfg('home_selector_subtitle', 'Select your motor specifications to get the exact matching starter panel instantly')); ?></p>
            </div>
            
            <div class="col-lg-8 col-12">
                <form action="<?php echo htmlspecialchars($form_action); ?>" method="GET" id="starterFinderForm">
                    <div class="row g-3">
                        <!-- Step 1: Phase -->
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2">
                                <?php echo htmlspecialchars(get_home_cfg('home_selector_step1_label', '1. Power Phase')); ?>
                            </label>
                            <div class="selector-pill-group" id="phasePills">
                                <span class="selector-pill-btn active" data-filter="phase" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase1_val', '')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase1_link', '')); ?>">
                                    <i class="fas fa-bolt"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_phase1_text', 'All Phases')); ?>
                                </span>
                                <span class="selector-pill-btn" data-filter="phase" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase2_val', '1-Phase')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase2_link', 'shop.php?phase=1-Phase')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_phase2_text', '1-Phase (220V)')); ?>
                                </span>
                                <span class="selector-pill-btn" data-filter="phase" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase3_val', '3-Phase')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_phase3_link', 'shop.php?phase=3-Phase')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_phase3_text', '3-Phase (415V)')); ?>
                                </span>
                            </div>
                            <input type="hidden" name="phase" id="filter_phase" value="<?php echo htmlspecialchars($init_phase); ?>">
                        </div>

                        <!-- Step 2: Motor HP -->
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2">
                                <?php echo htmlspecialchars(get_home_cfg('home_selector_step2_label', '2. Motor Rating (HP)')); ?>
                            </label>
                            <div class="selector-pill-group" id="hpPills">
                                <span class="selector-pill-btn active" data-filter="hp" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp1_val', '')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp1_link', '')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_hp1_text', 'All HP')); ?>
                                </span>
                                <span class="selector-pill-btn" data-filter="hp" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp2_val', '1-3 HP')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp2_link', '')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_hp2_text', '1 - 3 HP')); ?>
                                </span>
                                <span class="selector-pill-btn" data-filter="hp" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp3_val', '5-7.5 HP')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp3_link', '')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_hp3_text', '5 - 7.5 HP')); ?>
                                </span>
                                <span class="selector-pill-btn" data-filter="hp" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp4_val', '10-25 HP')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_hp4_link', '')); ?>">
                                    <?php echo htmlspecialchars(get_home_cfg('home_selector_hp4_text', '10 - 25+ HP')); ?>
                                </span>
                            </div>
                            <input type="hidden" name="hp" id="filter_hp" value="<?php echo htmlspecialchars($init_hp); ?>">
                        </div>

                        <!-- Step 3: Application & Search Button -->
                        <div class="col-12 mt-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8 col-12">
                                    <div class="selector-pill-group" id="appPills">
                                        <span class="selector-pill-btn active" data-filter="app" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_app1_val', 'submersible')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_app1_link', 'shop.php?app=submersible')); ?>">
                                            <i class="<?php echo htmlspecialchars(get_home_cfg('home_selector_app1_icon', 'fas fa-water')); ?>"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_app1_text', 'Submersible Pump')); ?>
                                        </span>
                                        <span class="selector-pill-btn" data-filter="app" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_app2_val', 'openwell')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_app2_link', 'shop.php?app=openwell')); ?>">
                                            <i class="<?php echo htmlspecialchars(get_home_cfg('home_selector_app2_icon', 'fas fa-industry')); ?>"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_app2_text', 'Openwell / Monoblock')); ?>
                                        </span>
                                        <span class="selector-pill-btn" data-filter="app" data-val="<?php echo htmlspecialchars(get_home_cfg('home_selector_app3_val', 'flourmill')); ?>" data-link="<?php echo htmlspecialchars(get_home_cfg('home_selector_app3_link', 'shop.php?app=flourmill')); ?>">
                                            <i class="<?php echo htmlspecialchars(get_home_cfg('home_selector_app3_icon', 'fas fa-cog')); ?>"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_app3_text', 'Flour Mill / Heavy Motor')); ?>
                                        </span>
                                    </div>
                                    <input type="hidden" name="app" id="filter_app" value="<?php echo htmlspecialchars($init_app); ?>">
                                </div>
                                <div class="col-md-4 col-12">
                                    <button type="submit" class="btn-finder-search">
                                        <i class="fas fa-search me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_selector_btn_text', 'Find Starters')); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php 
// ── SECTION 5: TRENDING & FEATURED MOTOR STARTERS ─────────────────────────────
$prods_enabled = get_home_cfg('home_prods_enabled', '1');
if ($prods_enabled == '1'): 
?>
<section class="trending-products-section py-5 bg-light-subtle">
    <div class="container" data-aos="fade-up">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-badge-pill mb-2">
                    <i class="fas fa-fire text-danger me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_prods_badge', 'BESTSELLERS & TRENDING')); ?>
                </span>
                <h2 class="section-title-pro montserrat fw-bold mb-1"><?php echo htmlspecialchars(get_home_cfg('home_prods_title', 'Featured Motor Starters')); ?></h2>
                <p class="section-desc-pro mb-0"><?php echo htmlspecialchars(get_home_cfg('home_prods_subtitle', 'High-performance starters and panels engineered for agricultural and industrial pumps')); ?></p>
            </div>
            <a href="<?php echo SITE_URL; ?>/shop.php?trending=1" class="btn-view-all-link">
                <?php echo htmlspecialchars(get_home_cfg('home_prods_btn_text', 'View All Products')); ?> <i class="fas fa-arrow-right"></i>
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
                    
                    // WhatsApp Direct Order Text
                    $wa_msg = urlencode("Hello Sagar Starters! I am interested in ordering: *" . $p['name'] . "* (Price: " . $global_currency . number_format($display_price, 2) . "). Please confirm stock and delivery.");
                    $wa_link = "https://wa.me/{$wa_phone}?text={$wa_msg}";
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay+=50; ?>">
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

                            <span class="product-badge-stock">
                                <span class="stock-dot"></span> In Stock
                            </span>

                            <img src="<?php echo htmlspecialchars($main_img_src); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" 
                                 alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                 loading="lazy" 
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
                                <div class="d-flex align-items-baseline justify-content-between w-100 flex-wrap gap-1">
                                    <div class="d-flex align-items-baseline">
                                        <?php if ($has_discount): ?>
                                            <span class="price-regular-cut"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                            <span class="price-sale-bold text-danger"><?php echo $global_currency; ?><?php echo number_format($sale_price, 2); ?></span>
                                        <?php else: ?>
                                            <span class="price-sale-bold"><?php echo $global_currency; ?><?php echo number_format($reg_price, 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $p_bulk_price = !empty($p['bulk_price']) ? (float)$p['bulk_price'] : 0;
                                    $p_bulk_qty = !empty($p['bulk_min_qty']) && (int)$p['bulk_min_qty'] > 0 ? (int)$p['bulk_min_qty'] : 12;
                                    ?>
                                    <?php if ($p_bulk_price > 0): ?>
                                        <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.72rem; font-weight: 600;">
                                            <i class="fas fa-layer-group"></i>Bulk: <?php echo $global_currency . number_format($p_bulk_price, 2); ?> (<?php echo $p_bulk_qty; ?>+)
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
                    <p class="text-muted">No products available at the moment. Please visit our shop.</p>
                    <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary rounded-pill px-4">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php 
// ── SECTION 6: PROMOTIONAL SPOTLIGHT BANNERS ──────────────────────────────────
$promo_enabled = get_home_cfg('home_promo_enabled', '1');
if ($promo_enabled == '1'): 
?>
<section class="home-spotlight-section py-5">
    <div class="container" data-aos="fade-up">
        <div class="row g-4">
            <div class="col-lg-6 col-12">
                <div class="promo-spotlight-card variant-blue">
                    <div>
                        <span class="promo-badge-tag">
                            <i class="fas fa-water me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_promo1_badge', 'Agricultural & Submersible')); ?>
                        </span>
                        <h3 class="promo-card-title montserrat"><?php echo htmlspecialchars(get_home_cfg('home_promo1_title', 'Submersible Pump Starters & Panels')); ?></h3>
                        <p class="promo-card-desc"><?php echo htmlspecialchars(get_home_cfg('home_promo1_desc', 'Equipped with dry run auto cut, electronic overload relays, digital ammeter-voltmeter, and surge safety for borewell motors.')); ?></p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/<?php echo htmlspecialchars(get_home_cfg('home_promo1_btn_link', 'shop.php?category=4')); ?>" class="promo-cta-btn">
                        <?php echo htmlspecialchars(get_home_cfg('home_promo1_btn_text', 'Explore Submersible Starters')); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="promo-spotlight-card variant-dark">
                    <div>
                        <span class="promo-badge-tag">
                            <i class="fas fa-industry me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_promo2_badge', '3-Phase Industrial Range')); ?>
                        </span>
                        <h3 class="promo-card-title montserrat"><?php echo htmlspecialchars(get_home_cfg('home_promo2_title', 'Star Delta & Heavy Duty Panels')); ?></h3>
                        <p class="promo-card-desc"><?php echo htmlspecialchars(get_home_cfg('home_promo2_desc', 'Engineered for factories, flour mills, and heavy agricultural motors. 100% heavy copper coils with thermal overload trip mechanism.')); ?></p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/<?php echo htmlspecialchars(get_home_cfg('home_promo2_btn_link', 'shop.php?category=6')); ?>" class="promo-cta-btn">
                        <?php echo htmlspecialchars(get_home_cfg('home_promo2_btn_text', 'Explore Star Delta Starters')); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php 
// ── SECTION 7: INDUSTRIAL EXCELLENCE & TRUST STATS ────────────────────────────
$stats_enabled = get_home_cfg('home_stats_enabled', '1');
if ($stats_enabled == '1'): 
    $stats_items = [
        [
            'icon'  => get_home_cfg('home_stat1_icon', 'fas fa-calendar-check'),
            'num'   => get_home_cfg('home_stat1_num', '15+'),
            'label' => get_home_cfg('home_stat1_label', 'Years of Excellence')
        ],
        [
            'icon'  => get_home_cfg('home_stat2_icon', 'fas fa-shield-virus'),
            'num'   => get_home_cfg('home_stat2_num', '50,000+'),
            'label' => get_home_cfg('home_stat2_label', 'Motors Protected')
        ],
        [
            'icon'  => get_home_cfg('home_stat3_icon', 'fas fa-microchip'),
            'num'   => get_home_cfg('home_stat3_num', '100%'),
            'label' => get_home_cfg('home_stat3_label', 'Pre-Tested Relays')
        ],
        [
            'icon'  => get_home_cfg('home_stat4_icon', 'fas fa-star'),
            'num'   => get_home_cfg('home_stat4_num', '4.9 / 5'),
            'label' => get_home_cfg('home_stat4_label', 'Customer Rating')
        ],
    ];
?>
<section class="container my-5" data-aos="fade-up">
    <div class="home-stats-section">
        <div class="text-center mb-4">
            <span class="stats-badge-pill mb-2">
                <i class="fas fa-award text-warning me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_stats_badge', 'PROVEN RELIABILITY')); ?>
            </span>
            <h2 class="stats-main-heading montserrat fw-bold text-white mb-2"><?php echo htmlspecialchars(get_home_cfg('home_stats_title', "Why Farmers & Engineers Trust Sagar Starter's")); ?></h2>
            <p class="stats-sub-text text-white mx-auto" style="max-width: 600px;"><?php echo htmlspecialchars(get_home_cfg('home_stats_subtitle', 'Over a decade of manufacturing excellence in motor control systems and agricultural power protection.')); ?></p>
        </div>

        <div class="row g-4 justify-content-center mt-2">
            <?php foreach ($stats_items as $st): ?>
            <div class="col-lg-3 col-md-6 col-6">
                <div class="stat-item-box">
                    <div class="stat-icon-wrap">
                        <i class="<?php echo htmlspecialchars($st['icon']); ?>"></i>
                    </div>
                    <div class="stat-number-big"><?php echo htmlspecialchars($st['num']); ?></div>
                    <p class="stat-label-text"><?php echo htmlspecialchars($st['label']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php 
// ── SECTION 8: CUSTOMER REVIEWS & TESTIMONIALS SLIDER ─────────────────────────
include 'includes/homepage-testimonials.php'; 
?>

<?php 
// ── SECTION 9: TECHNICAL FAQ & MOTOR BUYER'S GUIDE ACCORDION ──────────────────
$faq_enabled = get_home_cfg('home_faq_enabled', '1');
if ($faq_enabled == '1'): 
    $faqs = [
        [
            'q' => get_home_cfg('home_faq_q1', 'Which starter is suitable for my submersible pump motor?'),
            'a' => get_home_cfg('home_faq_a1', 'For Single Phase (1 HP - 3 HP) submersible pumps, our Digital Submersible Starter with Dry Run & Voltage Protection is best. For Three Phase (3 HP - 25 HP) pumps, choose our Heavy Duty DOL or Star Delta Starter with phase failure prevention.')
        ],
        [
            'q' => get_home_cfg('home_faq_q2', 'What is the advantage of 100% Genuine Copper Coils in Sagar Starters?'),
            'a' => get_home_cfg('home_faq_a2', 'Pure copper coils operate at significantly lower temperatures, resist voltage fluctuations, prevent relay burnout, and provide long-lasting durability even in continuous rural farming environments.')
        ],
        [
            'q' => get_home_cfg('home_faq_q3', 'How does the Dry Run and Overload auto-cut feature protect motors?'),
            'a' => get_home_cfg('home_faq_a3', 'When water runs dry in your borewell or when motor current surges abnormally, our built-in sensor relay automatically cuts power within seconds, preventing expensive motor winding burnouts.')
        ],
        [
            'q' => get_home_cfg('home_faq_q4', 'Do you provide Pan-India delivery and warranty replacement?'),
            'a' => get_home_cfg('home_faq_a4', 'Yes! We deliver across India via fast insured courier services. All Sagar Starters come with 1-Year replacement warranty and lifetime engineer telephone support.')
        ],
    ];
?>
<section class="container my-5 py-3" data-aos="fade-up">
    <div class="text-center mb-4">
        <span class="section-badge-pill mb-2">
            <i class="fas fa-circle-question me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_faq_badge', 'HELP & BUYING GUIDE')); ?>
        </span>
        <h2 class="section-title-pro montserrat fw-bold mb-2"><?php echo htmlspecialchars(get_home_cfg('home_faq_title', 'Frequently Asked Questions')); ?></h2>
        <p class="section-desc-pro mx-auto" style="max-width: 600px;"><?php echo htmlspecialchars(get_home_cfg('home_faq_subtitle', 'Quick answers to help you choose, install, and protect your motor with Sagar Starters')); ?></p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-12">
            <div class="accordion home-faq-accordion" id="homeFaqAccordion">
                <?php foreach ($faqs as $fIdx => $faq): 
                    $collapseId = "faqCollapse" . $fIdx;
                    $isFirst = ($fIdx === 0);
                ?>
                <div class="accordion-item">
                    <h3 class="accordion-header" id="heading<?php echo $fIdx; ?>">
                        <button class="accordion-button <?php echo !$isFirst ? 'collapsed' : ''; ?>" type="button" data-mdb-toggle="collapse" data-mdb-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $isFirst ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                            <i class="fas fa-question-circle text-primary me-2"></i> <?php echo htmlspecialchars($faq['q']); ?>
                        </button>
                    </h3>
                    <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $isFirst ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $fIdx; ?>" data-mdb-parent="#homeFaqAccordion">
                        <div class="accordion-body">
                            <?php echo nl2br(htmlspecialchars($faq['a'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Google FAQPage JSON-LD Structured Data for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    <?php 
    $faqJson = [];
    foreach ($faqs as $faq) {
        $faqJson[] = json_encode([
            "@type" => "Question",
            "name" => $faq['q'],
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => $faq['a']
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    echo implode(",\n    ", $faqJson);
    ?>
  ]
}
</script>
<?php endif; ?>

<?php 
// ── SECTION 10: ENGINEERING CONSULTATION & CUSTOM PANEL CTA BANNER ────────────
$cta_enabled = get_home_cfg('home_cta_enabled', '1');
if ($cta_enabled == '1'): 
    $cta_wa_msg = urlencode("Hello Sagar Starters Engineer Team! I need technical consultation / custom panel design for my motor. Please assist.");
    $cta_wa_link = "https://wa.me/{$wa_phone}?text={$cta_wa_msg}";
?>
<section class="container my-5" data-aos="fade-up">
    <div class="home-cta-banner">
        <div class="row align-items-center g-4">
            <div class="col-lg-8 col-12">
                <span class="cta-badge-pill mb-2">
                    <i class="fas fa-headset text-success me-1"></i> <?php echo htmlspecialchars(get_home_cfg('home_cta_badge', 'ENGINEERING CONSULTATION')); ?>
                </span>
                <h2 class="cta-banner-heading montserrat fw-bold text-white mb-2"><?php echo htmlspecialchars(get_home_cfg('home_cta_title', 'Need a Custom Control Panel or Bulk Order?')); ?></h2>
                <p class="cta-banner-desc text-white mb-0" style="max-width: 650px;"><?php echo htmlspecialchars(get_home_cfg('home_cta_desc', 'Talk directly with our senior electrical engineers for custom DOL panels, automatic water level controllers, or commercial pricing.')); ?></p>
            </div>
            <div class="col-lg-4 col-12 text-lg-end text-start">
                <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 justify-content-lg-end">
                    <a href="<?php echo $cta_wa_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-cta-wa">
                        <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars(get_home_cfg('home_cta_btn1_text', 'Chat on WhatsApp')); ?>
                    </a>
                    <a href="tel:<?php echo htmlspecialchars($clean_contact_phone); ?>" class="btn-cta-call">
                        <i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars(get_home_cfg('home_cta_btn2_text', 'Call Technical Support')); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php 
// ── SECTION 11: HOMEPAGE FEATURES BAR (Admin Configurable from features table) ─
include 'includes/homepage-features.php'; 
?>

<!-- Starter Finder Interactive JS Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Interactive Selector Pills Logic
    const pillGroups = document.querySelectorAll('.selector-pill-group');
    pillGroups.forEach(group => {
        const pills = group.querySelectorAll('.selector-pill-btn');
        pills.forEach(pill => {
            pill.addEventListener('click', function(e) {
                e.preventDefault();
                pills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                
                const filterType = this.getAttribute('data-filter');
                const filterVal = this.getAttribute('data-val');
                const targetInput = document.getElementById('filter_' + filterType);
                if (targetInput) {
                    targetInput.value = filterVal;
                }
            });
        });
    });

    // Form Submission with Multi-Criteria Filter
    const finderForm = document.getElementById('starterFinderForm');
    if (finderForm) {
        finderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const activeApp = document.querySelector('#appPills .selector-pill-btn.active');
            const activePhase = document.querySelector('#phasePills .selector-pill-btn.active');
            const activeHp = document.querySelector('#hpPills .selector-pill-btn.active');

            const phaseVal = activePhase ? activePhase.getAttribute('data-val') : '';
            const hpVal = activeHp ? activeHp.getAttribute('data-val') : '';
            const appVal = activeApp ? activeApp.getAttribute('data-val') : '';

            let baseAction = finderForm.getAttribute('action') || 'shop.php';
            if (!baseAction.startsWith('http') && !baseAction.startsWith('/')) {
                baseAction = '<?php echo SITE_URL; ?>/' + baseAction;
            }

            const urlObj = new URL(baseAction, window.location.origin);
            
            // Clear any old/conflicting parameters
            urlObj.searchParams.delete('phase');
            urlObj.searchParams.delete('hp');
            urlObj.searchParams.delete('app');
            urlObj.searchParams.delete('category');
            urlObj.searchParams.delete('category_slug');

            if (phaseVal && phaseVal.trim() !== '') {
                urlObj.searchParams.set('phase', phaseVal.trim());
            }
            if (hpVal && hpVal.trim() !== '') {
                urlObj.searchParams.set('hp', hpVal.trim());
            }
            if (appVal && appVal.trim() !== '') {
                urlObj.searchParams.set('app', appVal.trim());
            }

            window.location.href = urlObj.toString();
        });
    }
});
</script>

<?php 
// ── SECTION 12: GLOBAL FOOTER ────────────────────────────────────────────────
include 'includes/footer.php'; 
?>
