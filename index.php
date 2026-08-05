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

<!-- Featured Categories -->
<div class="container mt-5 pt-3" data-aos="fade-up">
    <h2 class="text-center montserrat fw-bold mb-5">Featured Categories</h2>
    <div class="row g-4">
        <?php $delay=100; while($c = $cats->fetch_assoc()): ?>
        <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; $delay+=100; ?>">
            <div class="card product-card text-white">
                <img src="<?php echo !empty($c['image']) ? htmlspecialchars(resolve_image_url($c['image'])) : 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&q=80&w=600'; ?>" class="card-img" alt="<?php echo htmlspecialchars($c['name']); ?>" loading="lazy" style="aspect-ratio: 1 / 1; width: 100%; object-fit: cover; filter: brightness(0.85);" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&q=80&w=600';">
                <div class="card-img-overlay d-flex flex-column justify-content-end align-items-start p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.5) 40%, transparent 100%); z-index: 1;">
                    <h3 class="card-title fw-bold montserrat mb-3" style="color: #ffffff !important; text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 15px rgba(0,0,0,0.6); font-size: 1.4rem; z-index: 2;"><?php echo htmlspecialchars($c['name']); ?></h3>
                    <a href="shop.php?category=<?php echo $c['id']; ?>" class="btn btn-light btn-custom">View Products</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

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
                    <img src="<?php echo htmlspecialchars($main_img_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="card-img-top" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy" style="object-fit: <?php echo htmlspecialchars($p['image_fit'] ?? 'contain'); ?>; background-color:#fff;">
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
