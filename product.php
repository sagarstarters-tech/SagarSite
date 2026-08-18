<?php
ob_start(); // Buffer output to allow setting variables before header
require_once 'includes/db_connect.php';
require_once 'includes/session_setup.php';

$where = "";
if (isset($_GET['slug'])) {
    $slug = $conn->real_escape_string($_GET['slug']);
    $where = "p.slug = '$slug'";
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $where = "p.id = $id";
} else {
    header("Location: shop.php");
    exit;
}

$result = $conn->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE $where");

// Fallback logic for various slug formats (Timestamped prefixes, ID suffixes, etc.)
if ($result->num_rows === 0 && isset($_GET['slug'])) {
    $clean_slug = $_GET['slug'];
    
    // 1. Try stripping a likely 10-digit timestamp prefix (e.g., 1773379418-slug)
    $stripped_slug = preg_replace('/^\d{5,12}-/', '', $clean_slug);
    
    // 2. Try extracting the ID part if the slug starts with it (e.g., URL has 1773379418-slug, DB has slug-79418)
    // We try to match by finding products whose slug contains the text part
    $text_part = $conn->real_escape_string($stripped_slug);
    
    $alt_query = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id 
                  WHERE p.slug = '$text_part' 
                  OR p.slug LIKE '%$text_part%' 
                  OR p.slug LIKE '" . substr($text_part, 0, 30) . "%'
                  LIMIT 1";
    $result = $conn->query($alt_query);
}

if ($result->num_rows === 0) {
    echo "<div class='container mt-5 py-5 text-center'><h2>Product not found</h2></div>";
    include 'includes/header.php';
    include 'includes/footer.php';
    exit;
}

$product = $result->fetch_assoc();
$id = $product['id'];

// SEO Meta Variables
$page_meta_title = $product['name'] . " - Sagar Starter's";
$page_meta_description = !empty($product['meta_description']) ? $product['meta_description'] : (!empty($product['short_description']) ? substr(strip_tags($product['short_description']), 0, 160) : substr(strip_tags($product['description']), 0, 160));

// Use the robust resolve_product_image_url function for social preview images
$page_meta_image = function_exists('resolve_product_image_url') ? resolve_product_image_url($product['image'] ?? '', $conn, $id) : ($product['image'] ?? 'og_default.jpg');

// Related Products
$cat_id = $product['category_id'];
$id = $product['id']; // Ensure $id is defined even if initialized earlier
$related = $conn->query("SELECT * FROM products WHERE category_id = $cat_id AND id != $id LIMIT 4");

include 'includes/header.php';

// Generate Product Schema
$productSchema = $seoService->generateProductSchema($product);
?>
<script type="application/ld+json">
<?php echo $productSchema; ?>
</script>

<?php 
$hero_bg_style = "";
$show_product_hero = false;
if (!empty($global_settings['hero_banner_product'])) {
    $img_url = htmlspecialchars(resolve_image_url($global_settings['hero_banner_product']));
    if (!empty($img_url) && strpos($img_url, 'placeholder') === false) {
        $hero_bg_style = "background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('{$img_url}') center/cover no-repeat !important;";
        $show_product_hero = true;
    }
}
?>

<style>
/* Micro-animations & Dynamic Effects */
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}

@keyframes pulse-soft {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(76, 175, 80, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
}

@keyframes shine {
    100% { left: 125%; }
}

.product-card {
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.product-card:hover {
    transform: translateY(-8px);
}

.gallery-thumbnail {
    transition: transform 0.3s ease, border-color 0.3s ease;
}
.gallery-thumbnail:hover {
    transform: scale(1.1);
}

.btn-custom {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.btn-custom::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -60%;
    width: 20%;
    height: 200%;
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(30deg);
    transition: none;
    animation: shine 3s infinite;
}

.badge-pulse {
    animation: pulse-soft 2s infinite;
}

.share-btn {
    transition: all 0.3s ease;
}
.share-btn:hover {
    transform: translateY(-3px) scale(1.1);
}

.features-list li {
    transition: transform 0.2s ease;
}
.features-list li:hover {
    transform: translateX(5px);
}

/* Breadcrumb Animation */
.breadcrumb-item {
    transition: transform 0.3s ease;
}
.breadcrumb-item:hover {
    transform: translateX(2px);
}
</style>

<?php if($show_product_hero): ?>
<!-- Product Hero Section -->
<div class="bg-primary text-white py-5 mb-4" style="<?php echo $hero_bg_style; ?>">
    <div class="container py-4 text-center">
        <h1 class="display-4 fw-bold mb-0 montserrat"><?php echo htmlspecialchars($product['name']); ?></h1>
    </div>
</div>
<?php endif; ?>

<div class="container <?php echo $show_product_hero ? 'mt-4' : 'mt-5 pt-4'; ?> mb-5">
    <nav aria-label="breadcrumb" class="mb-4" data-aos="fade-right">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item"><a href="shop.php?category=<?php echo $cat_id; ?>" class="text-decoration-none"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Product Image & Gallery -->
        <div class="col-md-6 mb-4" data-aos="fade-right">
            <?php
            // Determine main image source with fallback to first gallery image
            $main_image_src = resolve_product_image_url($product['image'] ?? '', $conn, $id);
            ?>
            <div class="card product-card shadow-sm border-0 bg-light d-flex align-items-center justify-content-center p-3 mb-3" style="position: relative; overflow: hidden;" id="imageZoomContainer">
                <img id="mainProductImage" src="<?php echo htmlspecialchars($main_image_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>" width="500" height="500" fetchpriority="high" decoding="async" style="width: 100%; aspect-ratio: 1 / 1; object-fit: <?php echo htmlspecialchars($product['image_fit'] ?? 'contain'); ?>; transition: opacity 0.3s ease;">
            </div>
            
            <style>
                #imageZoomContainer {
                    cursor: crosshair;
                }
                #imageZoomContainer img {
                    transition: opacity 0.3s ease, transform 0.1s ease;
                    transform-origin: center center;
                }
            </style>
            
            <script>
                const zoomContainer = document.getElementById('imageZoomContainer');
                const mainImg = document.getElementById('mainProductImage');
                
                zoomContainer.addEventListener('mousemove', function(e) {
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const xPercent = x / rect.width * 100;
                    const yPercent = y / rect.height * 100;
                    
                    mainImg.style.transformOrigin = `${xPercent}% ${yPercent}%`;
                    mainImg.style.transform = 'scale(2)'; // 2x zoom
                });
                
                zoomContainer.addEventListener('mouseleave', function() {
                    mainImg.style.transformOrigin = 'center center';
                    mainImg.style.transform = 'scale(1)';
                });
            </script>
            
            <?php
            // Fetch gallery images
            $gallery = $conn->query("SELECT image FROM product_images WHERE product_id = $id ORDER BY position ASC, id ASC");
            if ($gallery && $gallery->num_rows > 0):
            ?>
            <div class="d-flex gap-2 overflow-auto py-2" style="white-space: nowrap;">
                <!-- Include main image as first thumbnail -->
                <div class="gallery-thumbnail active-thumbnail" style="width: 80px; height: 80px; cursor: pointer; flex-shrink: 0; border: 2px solid var(--primary-color); border-radius: 8px; overflow: hidden; padding: 2px;" onclick="changeMainImage(this, '<?php echo htmlspecialchars($main_image_src); ?>')">
                    <img src="<?php echo htmlspecialchars($main_image_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="w-100 h-100 object-fit-cover rounded" width="80" height="80" loading="lazy" decoding="async">
                </div>
                
                <!-- Output extra gallery images -->
                <?php while($g = $gallery->fetch_assoc()): 
                    $g_src = resolve_product_image_url($g['image'] ?? '');
                    if (empty($g_src) || strpos($g_src, 'placeholder.svg') !== false || $g_src === $main_image_src) {
                        continue; // Skip dummy or duplicate gallery thumbnails
                    }
                ?>
                <div class="gallery-thumbnail" style="width: 80px; height: 80px; cursor: pointer; flex-shrink: 0; border: 2px solid transparent; border-radius: 8px; overflow: hidden; padding: 2px;" onclick="changeMainImage(this, '<?php echo htmlspecialchars($g_src); ?>')">
                    <img src="<?php echo htmlspecialchars($g_src); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="w-100 h-100 object-fit-cover rounded" width="80" height="80" loading="lazy" decoding="async">
                </div>
                <?php endwhile; ?>
            </div>
            
            <script>
            function changeMainImage(element, newSrc) {
                // Update main image source
                const mainImg = document.getElementById('mainProductImage');
                mainImg.style.opacity = 0.5;
                setTimeout(() => {
                    mainImg.src = newSrc;
                    mainImg.style.opacity = 1;
                }, 150);
                
                // Update active borders
                document.querySelectorAll('.gallery-thumbnail').forEach(el => {
                    el.style.borderColor = 'transparent';
                });
                element.style.borderColor = 'var(--primary-color)';
            }
            </script>
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div class="col-md-6 px-lg-5" data-aos="fade-left" data-aos-delay="200">
            <h1 class="display-5 fw-bold montserrat product-main-title" data-aos="fade-down" data-aos-delay="300"><?php echo htmlspecialchars($product['name']); ?></h1>
            <?php if(isset($product['review_count']) && $product['review_count'] > 0): ?>
            <div class="mb-3 d-flex align-items-center" data-aos="fade-in" data-aos-delay="400">
                <div class="text-warning me-2">
                    <?php 
                    $rating = floatval($product['average_rating']);
                    for($i=1; $i<=5; $i++) {
                        if($rating >= $i) echo '<i class="fas fa-star"></i>';
                        elseif($rating >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                        else echo '<i class="far fa-star"></i>';
                    }
                    ?>
                </div>
                <span class="text-muted small"><a href="#product-reviews" class="text-decoration-none text-muted">(<?php echo $product['review_count']; ?> customer reviews)</a></span>
            </div>
            <?php else: ?>
            <div class="mb-3 d-flex align-items-center">
                <div class="text-warning me-2">
                    <i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i>
                </div>
                <span class="text-muted small"><a href="#product-reviews" class="text-decoration-none text-muted">Be the first to review!</a></span>
            </div>
            <?php endif; ?>
            <div class="mb-3 d-flex align-items-center flex-wrap">
                <?php 
                $moq = !empty($product['min_order_qty']) ? max(1, intval($product['min_order_qty'])) : 1;
                $bulk_price = !empty($product['bulk_price']) ? floatval($product['bulk_price']) : 0;
                $bulk_min_qty = !empty($product['bulk_min_qty']) && (int)$product['bulk_min_qty'] > 0 ? (int)$product['bulk_min_qty'] : 12;
                $current_unit_price = ($product['sale_price'] > 0) ? floatval($product['sale_price']) : (floatval($product['regular_price']) > 0 ? floatval($product['regular_price']) : floatval($product['price']));
                $has_bulk_discount = ($bulk_price > 0 && $bulk_price < $current_unit_price);
                ?>
                <?php if ($product['sale_price'] > 0): ?>
                    <span class="fs-4 text-muted text-decoration-line-through me-2"><?php echo $global_currency; ?><?php echo number_format($product['regular_price'], 2); ?></span>
                    <span class="fs-3 d-inline-block fw-bold text-danger me-2"><?php echo $global_currency; ?><?php echo number_format($product['sale_price'], 2); ?></span>
                <?php else: ?>
                    <span class="fs-3 d-inline-block fw-bold primary-blue me-2"><?php echo $global_currency; ?><?php echo number_format($product['regular_price'] > 0 ? $product['regular_price'] : $product['price'], 2); ?></span>
                <?php endif; ?>
                <?php if($product['stock'] > 0): ?>
                    <span class="badge bg-success ms-2 fs-6 align-middle badge-pulse">In Stock (<?php echo $product['stock']; ?>)</span>
                <?php else: ?>
                    <span class="badge bg-danger ms-2 fs-6 align-middle">Out of Stock</span>
                <?php endif; ?>
                <?php if ($moq > 1): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2 fs-6 align-middle"><i class="fas fa-boxes me-1"></i>Min. Order: <?php echo $moq; ?> Units</span>
                <?php endif; ?>
            </div>
            
            <div class="mb-4" data-aos="fade-up" data-aos-delay="500">
                <?php if (!empty($product['sku'])): ?>
                    <span class="badge bg-secondary me-2">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                <?php endif; ?>
                <?php if (!empty($product['brand'])): ?>
                    <span class="badge bg-info text-dark">Brand: <?php echo htmlspecialchars($product['brand']); ?></span>
                <?php endif; ?>
                <?php if ($product['weight'] > 0): ?>
                    <span class="badge bg-light text-dark border ms-2"><i class="fas fa-weight-hanging me-1"></i> <?php echo number_format($product['weight'], 2); ?> kg</span>
                <?php endif; ?>
                <?php if ($product['length'] > 0 || $product['width'] > 0 || $product['height'] > 0): ?>
                    <span class="badge bg-light text-dark border ms-2"><i class="fas fa-ruler-combined me-1"></i> <?php echo number_format($product['length'], 2); ?> × <?php echo number_format($product['width'], 2); ?> × <?php echo number_format($product['height'], 2); ?> cm</span>
                <?php endif; ?>
            </div>

            <?php if ($has_bulk_discount): ?>
                <?php 
                $is_retailer_user = (isset($_SESSION['role']) && $_SESSION['role'] === 'retailer');
                $bulk_shipping_badge = '';
                if (isset($product['bulk_shipping_cost']) && $product['bulk_shipping_cost'] !== null && $product['bulk_shipping_cost'] !== '') {
                    $b_ship = (float)$product['bulk_shipping_cost'];
                    if ($b_ship == 0) {
                        $bulk_shipping_badge = '<span class="badge rounded-pill px-2 py-1 small fw-bold ms-1" style="background-color: #16a34a; color: #ffffff;"><i class="fas fa-truck me-1"></i>Free Bulk Shipping</span>';
                    } else {
                        $bulk_shipping_badge = '<span class="badge rounded-pill px-2 py-1 small fw-bold ms-1" style="background-color: #0284c7; color: #ffffff;"><i class="fas fa-truck me-1"></i>Shipping: ' . $global_currency . number_format($b_ship, 2) . '</span>';
                    }
                }
                ?>
                <?php if ($is_retailer_user): ?>
                <div id="bulkOfferCard" class="card rounded-3 mb-4 p-3 shadow-none transition-all" style="background-color: #f0fdf4; border: 1.5px solid #86efac;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge rounded-pill px-3 py-2 fw-bold" style="background-color: #16a34a; color: #ffffff;"><i class="fas fa-store me-1"></i> Retailer Privilege</span>
                            <span class="fw-bold" style="color: #1e293b;">Buy <span class="fw-bolder" style="color: #15803d;">2+ units</span> for only <span class="fs-5 fw-bolder px-2 py-1 rounded" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;"><?php echo $global_currency . number_format($bulk_price, 2); ?></span> / unit</span>
                            <?php echo $bulk_shipping_badge; ?>
                        </div>
                        <span class="badge rounded-pill px-2 py-1 small fw-bold" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;"><i class="fas fa-arrow-down me-1"></i>Save <?php echo $global_currency . number_format($current_unit_price - $bulk_price, 2); ?>/unit</span>
                    </div>
                </div>
                <?php else: ?>
                <div id="bulkOfferCard" class="card rounded-3 mb-4 p-3 shadow-none transition-all" style="background-color: #f0f9ff; border: 1.5px solid #bae6fd;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge rounded-pill px-3 py-2 fw-bold" style="background-color: #0284c7; color: #ffffff;"><i class="fas fa-layer-group me-1"></i> Bulk Wholesale Offer</span>
                            <span class="fw-bold" style="color: #1e293b;">Buy <span class="fw-bolder" style="color: #0369a1;"><?php echo $bulk_min_qty; ?>+ units</span> for only <span class="fs-5 fw-bolder px-2 py-1 rounded" style="background-color: #e0f2fe; color: #075985; border: 1px solid #bae6fd;"><?php echo $global_currency . number_format($bulk_price, 2); ?></span> / unit</span>
                            <?php echo $bulk_shipping_badge; ?>
                        </div>
                        <span class="badge rounded-pill px-2 py-1 small fw-bold" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;"><i class="fas fa-arrow-down me-1"></i>Save <?php echo $global_currency . number_format($current_unit_price - $bulk_price, 2); ?>/unit</span>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if($product['stock'] > 0): ?>
            <form id="addToCartForm" action="<?php echo SITE_URL; ?>/includes/cart_actions.php" method="POST" class="d-flex flex-wrap align-items-center mb-4 bg-light p-3 rounded gap-3">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <label class="fw-bold me-3">Quantity:<?php if ($moq > 1): ?> <small class="text-muted fw-normal">(MOQ: <?php echo $moq; ?>)</small><?php endif; ?></label>
                <div class="me-3" style="width: 110px;">
                    <input type="number" name="quantity" id="productQtyInput" class="form-control text-center fw-bold" value="<?php echo $moq; ?>" min="<?php echo $moq; ?>" max="<?php echo $product['stock']; ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-custom px-4"><i class="fas fa-shopping-cart me-2"></i>Add to Cart</button>
            </form>
            <script>
                document.getElementById('addToCartForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = this.querySelector('button[type="submit"]');
                    btn.classList.add('disabled');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
                    window.flyToCartAnimation(document.getElementById('mainProductImage'));
                    const form = this;
                    setTimeout(function() {
                        HTMLFormElement.prototype.submit.call(form);
                    }, 800);
                });
            </script>
            <?php if ($has_bulk_discount): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const qtyInput = document.getElementById('productQtyInput');
                const bulkCard = document.getElementById('bulkOfferCard');
                const bulkMinQty = <?php echo (!empty($is_retailer_user)) ? 2 : $bulk_min_qty; ?>;
                const defaultBorder = '<?php echo (!empty($is_retailer_user)) ? '#86efac' : '#bae6fd'; ?>';
                if (qtyInput && bulkCard) {
                    function checkBulkActive() {
                        const val = parseInt(qtyInput.value) || 1;
                        if (val >= bulkMinQty) {
                            bulkCard.style.borderColor = '#16a34a';
                            bulkCard.style.boxShadow = '0 4px 14px rgba(22, 163, 74, 0.2)';
                        } else {
                            bulkCard.style.borderColor = defaultBorder;
                            bulkCard.style.boxShadow = 'none';
                        }
                    }
                    qtyInput.addEventListener('input', checkBulkActive);
                    qtyInput.addEventListener('change', checkBulkActive);
                    checkBulkActive();
                }
            });
            </script>
            <?php endif; ?>
            <?php else: ?>
            <button class="btn btn-secondary btn-lg btn-custom mb-4 px-4 w-100" disabled>Out of Stock</button>
            <?php endif; ?>
            
            <hr>
            <div class="mt-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-truck text-muted me-2" style="width: 25px;"></i> <?php echo htmlspecialchars($global_settings['product_shipping_text'] ?? 'Free Shipping on orders over ₹1000'); ?></h6>
                <h6 class="fw-bold mb-3"><i class="fas fa-undo text-muted me-2" style="width: 25px;"></i> <?php echo htmlspecialchars($global_settings['product_return_text'] ?? '7-Day Return Policy'); ?></h6>
                <h6 class="fw-bold"><i class="fas fa-shield-alt text-muted me-2" style="width: 25px;"></i> <?php echo htmlspecialchars($global_settings['product_warranty_text'] ?? '1 Year Warranty Included'); ?></h6>
            </div>
            
            <?php include 'includes/product-share-section.php'; ?>
        </div>
    </div>


    <!-- Product Description & Features Section -->
    <div class="row mt-5" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 p-lg-5">
                    <div class="row">
                        <div class="col-lg-<?php echo !empty($product['features']) ? '7' : '12'; ?> mb-4 mb-lg-0">
                            <h4 class="montserrat fw-bold mb-4 pb-2 border-bottom" data-aos="fade-right">Product Overview</h4>
                            <?php if (!empty($product['short_description'])): ?>
                                <p class="lead text-muted mb-4" style="line-height: 1.7; font-weight: 500;" data-aos="fade-up" data-aos-delay="100"><?php echo nl2br(htmlspecialchars($product['short_description'])); ?></p>
                            <?php endif; ?>
                            <div class="description-content text-muted" style="line-height: 1.8; font-size: 1.05rem;" data-aos="fade-up" data-aos-delay="200">
                                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($product['features'])): ?>
                        <div class="col-lg-5 ps-lg-5">
                            <h4 class="montserrat fw-bold mb-4 pb-2 border-bottom" data-aos="fade-left">Key Features</h4>
                            <ul class="list-unstyled features-list">
                                <?php 
                                $features_arr = explode("\n", str_replace(["\r\n", "\r"], "\n", $product['features']));
                                $f_delay = 100;
                                foreach ($features_arr as $feature): 
                                    $feature = trim($feature);
                                    if (empty($feature)) continue;
                                ?>
                                    <li class="mb-3 d-flex align-items-start" data-aos="fade-left" data-aos-delay="<?php echo $f_delay; $f_delay+=50; ?>">
                                        <i class="fas fa-check-circle text-primary mt-1 me-3" style="font-size: 1.2rem;"></i>
                                        <span class="text-muted" style="line-height: 1.6;"><?php echo htmlspecialchars($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Reviews Section -->
    <div id="product-reviews" class="mt-5 pt-5 border-top" data-aos="fade-up">
        <h3 class="montserrat fw-bold text-center mb-5">Customer Reviews</h3>
        <div class="row">
            <!-- Review Summary & Form -->
            <div class="col-lg-4 col-md-5 mb-4 mb-md-0 pe-lg-5">
                <div class="card bg-light border-0 shadow-sm p-4 text-center mb-4">
                    <h2 class="display-4 fw-bold mb-0 text-dark"><?php echo isset($product['average_rating']) && $product['average_rating'] > 0 ? number_format($product['average_rating'], 1) : '0.0'; ?> <span class="fs-4 text-muted">/ 5</span></h2>
                    <div class="text-warning fs-4 mb-2">
                        <?php 
                        $rating = isset($product['average_rating']) ? floatval($product['average_rating']) : 0;
                        for($i=1; $i<=5; $i++) {
                            if($rating >= $i) echo '<i class="fas fa-star"></i>';
                            elseif($rating >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                            else echo '<i class="far fa-star"></i>';
                        }
                        ?>
                    </div>
                    <p class="text-muted mb-0">Based on <?php echo isset($product['review_count']) ? $product['review_count'] : 0; ?> reviews</p>
                </div>
                
                <h5 class="fw-bold mb-3">Write a Review</h5>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <form id="reviewForm" class="bg-white p-4 border rounded shadow-sm">
                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                        <div class="mb-3 text-center">
                            <label class="form-label d-block text-start fw-bold">Your Rating</label>
                            <div class="rating-stars" style="direction: rtl; unicode-bidi: bidi-override; font-size: 1.5rem; display: inline-block;">
                                <input type="radio" name="rating" value="5" id="star5" class="d-none"><label for="star5" class="star" style="cursor: pointer; color: #ccc; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="4" id="star4" class="d-none"><label for="star4" class="star" style="cursor: pointer; color: #ccc; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="3" id="star3" class="d-none"><label for="star3" class="star" style="cursor: pointer; color: #ccc; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="2" id="star2" class="d-none"><label for="star2" class="star" style="cursor: pointer; color: #ccc; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="1" id="star1" class="d-none"><label for="star1" class="star" style="cursor: pointer; color: #ccc; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                            </div>
                            <style>
                                .rating-stars > input:checked ~ label,
                                .rating-stars:not(:checked) > label:hover,
                                .rating-stars:not(:checked) > label:hover ~ label { color: #ffeb3b !important; }
                            </style>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Review Title <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="text" class="form-control bg-light" name="review_title" placeholder="Summary of your review">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Review</label>
                            <textarea class="form-control bg-light" name="review_text" rows="4" placeholder="What did you like or dislike? What did you use this product for?" required></textarea>
                        </div>
                        <div id="reviewAlert" class="alert d-none small"></div>
                        <button type="submit" class="btn btn-primary btn-custom w-100 text-white" id="btnSubmitReview">Submit Review</button>
                    </form>
                    
                    <script>
                        document.getElementById('reviewForm').addEventListener('submit', function(e){
                            e.preventDefault();
                            let rating = document.querySelector('input[name="rating"]:checked');
                            const alertBox = document.getElementById('reviewAlert');
                            
                            if(!rating) {
                                alertBox.className = 'alert alert-danger small';
                                alertBox.innerHTML = 'Please select a star rating.';
                                alertBox.classList.remove('d-none');
                                return;
                            }
                            
                            const btn = document.getElementById('btnSubmitReview');
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                            btn.disabled = true;
                            
                            let formData = new FormData(this);
                            
                            fetch('<?php echo SITE_URL; ?>/includes/submit_review.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                alertBox.classList.remove('d-none');
                                if(data.success) {
                                    alertBox.className = 'alert alert-success small';
                                    alertBox.innerHTML = data.message;
                                    this.reset();
                                    setTimeout(() => window.location.reload(), 1500);
                                } else {
                                    alertBox.className = 'alert alert-danger small';
                                    alertBox.innerHTML = data.message;
                                    btn.innerHTML = 'Submit Review';
                                    btn.disabled = false;
                                }
                            })
                            .catch(error => {
                                alertBox.className = 'alert alert-danger small';
                                alertBox.innerHTML = 'An unexpected error occurred. Please try again.';
                                btn.innerHTML = 'Submit Review';
                                btn.disabled = false;
                            });
                        });
                    </script>
                <?php else: ?>
                    <div class="card p-4 text-center border-0 shadow-sm bg-light">
                        <p class="mb-3">You must be logged in to write a review. Share your thoughts with other customers!</p>
                        <a href="<?php echo SITE_URL; ?>/user/login.php" class="btn btn-primary btn-custom text-white">Login to Review</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Reviews List -->
            <div class="col-lg-8 col-md-7 ps-lg-5">
                <?php
                $reviews_exist = false;
                $reviews_res = null;
                $rev_stmt = null;
                try {
                    $rev_stmt = $conn->prepare("SELECT pr.*, u.name as user_name FROM product_reviews pr JOIN users u ON pr.user_id = u.id WHERE pr.product_id = ? AND pr.status = 'approved' ORDER BY pr.created_at DESC");
                    
                    if($rev_stmt) {
                        $rev_stmt->bind_param("i", $id);
                        $rev_stmt->execute();
                        $reviews_res = $rev_stmt->get_result();
                        if($reviews_res && $reviews_res->num_rows > 0) {
                            $reviews_exist = true;
                        }
                    }
                } catch (Exception $e) {
                    // Suppress error if table doesn't exist yet
                }
                
                if($reviews_exist && $reviews_res):
                    while($rev = $reviews_res->fetch_assoc()):
                ?>
                <div class="card border-0 border-bottom mb-4 pb-4 bg-transparent shadow-none rounded-0">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: bold; font-size: 1.2rem;">
                            <?php echo strtoupper(substr($rev['user_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($rev['user_name']); ?></h6>
                            <span class="text-muted small"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-2 d-flex align-items-center mb-3">
                        <div class="text-warning me-2" style="font-size: 0.9rem;">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $rev['rating'] >= $i ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>
                        <?php if(!empty($rev['review_title'])): ?>
                            <span class="fw-bold fs-6"><?php echo htmlspecialchars($rev['review_title']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-dark mb-0" style="letter-spacing: 0.2px; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></p>
                </div>
                <?php 
                    endwhile;
                else: 
                ?>
                <div class="text-center py-5 rounded bg-light border-0 shadow-sm mt-md-0 mt-4">
                    <i class="far fa-comments fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="text-muted fw-bold">No reviews yet</h5>
                    <p class="text-muted mb-0">Be the first to review this product!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if($related && $related->num_rows > 0): ?>
    <div class="mt-5 pt-5 border-top" data-aos="fade-up">
        <h3 class="montserrat fw-bold text-center mb-5">Related Products</h3>
        <div class="row g-4">
            <?php 
            $wa_phone = get_store_whatsapp_number();
            $delay=100; 
            while($p = $related->fetch_assoc()): 
                $rel_reg_price = (float)($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $rel_sale_price = (float)($p['sale_price'] ?? 0);
                $rel_has_discount = ($rel_sale_price > 0 && $rel_sale_price < $rel_reg_price);
                $rel_display_price = $rel_has_discount ? $rel_sale_price : $rel_reg_price;
                $rel_url = !empty($p['slug']) ? SITE_URL . "/product/" . $p['slug'] : SITE_URL . "/product.php?id=" . $p['id'];
                
                $rel_wa_msg = urlencode("Hello Sagar Starters! I am interested in ordering: *" . $p['name'] . "* (Price: " . $global_currency . number_format($rel_display_price, 2) . "). Please confirm stock and delivery.");
                $rel_wa_link = "https://wa.me/{$wa_phone}?text={$rel_wa_msg}";
            ?>
            <div class="col-md-3" data-aos="zoom-in" data-aos-delay="<?php echo $delay; $delay+=100; ?>">
                <div class="card product-card h-100 border-0 shadow-sm">
                    <img src="<?php echo htmlspecialchars(resolve_product_image_url($p['image'] ?? '', $conn, $p['id'])); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="card-img-top" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy" width="260" height="180" decoding="async" style="height: 180px; object-fit: <?php echo htmlspecialchars($p['image_fit'] ?? 'contain'); ?>; background-color:#fff;">
                    <div class="card-body d-flex flex-column p-3">
                        <h6 class="card-title fw-bold text-truncate mb-2" title="<?php echo htmlspecialchars($p['name']); ?>">
                            <a href="<?php echo $rel_url; ?>" class="text-reset text-decoration-none"><?php echo htmlspecialchars($p['name']); ?></a>
                        </h6>
                        <div class="mt-auto pt-2 border-top">
                            <div class="d-flex align-items-baseline mb-2">
                                <?php if ($rel_has_discount): ?>
                                    <span class="text-muted text-decoration-line-through small me-1"><?php echo $global_currency; ?><?php echo number_format($rel_reg_price, 2); ?></span>
                                    <span class="fw-bold text-danger"><?php echo $global_currency; ?><?php echo number_format($rel_sale_price, 2); ?></span>
                                <?php else: ?>
                                    <span class="fw-bold primary-blue"><?php echo $global_currency; ?><?php echo number_format($rel_reg_price, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-actions-footer d-flex gap-2 align-items-center">
                                <a href="<?php echo $rel_url; ?>" class="btn-pro-view flex-grow-1" style="font-size: 0.8rem; padding: 7px 10px;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="<?php echo $rel_wa_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-pro-wa" title="Order / Enquire on WhatsApp" aria-label="Order on WhatsApp" style="width: 36px; height: 36px; min-width: 36px;">
                                    <i class="fab fa-whatsapp" style="color: #ffffff !important; font-size: 1.15rem;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="<?php echo ASSETS_URL; ?>/js/share-script.js" defer></script>
<?php include 'includes/footer.php'; ?>

