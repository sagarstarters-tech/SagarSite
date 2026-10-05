<?php
// Ensure $cart_count is available (calculated in session/header, fallback safely)
if (!isset($cart_count)) {
    $cart_count = 0;
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += intval($item['quantity'] ?? 1);
        }
    }
}
?>
<!-- Flipkart & Amazon Style Mobile Bottom Navigation Bar -->
<nav class="bottom-nav" id="bottomNav" aria-label="Mobile Navigation">
    <a href="<?php echo SITE_URL; ?>/index.php" class="bottom-nav-item" data-tab="home" aria-label="Home">
        <div class="bottom-nav-icon-wrap">
            <i class="fas fa-home"></i>
        </div>
        <span>Home</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/shop.php" class="bottom-nav-item" data-tab="categories" aria-label="Categories">
        <div class="bottom-nav-icon-wrap">
            <i class="fas fa-th-large"></i>
        </div>
        <span>Categories</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/cart.php" class="bottom-nav-item" data-tab="cart" aria-label="Cart">
        <div class="bottom-nav-icon-wrap">
            <i class="fas fa-shopping-cart"></i>
            <span class="bottom-nav-badge" id="bottomNavCartBadge" <?php echo ($cart_count > 0) ? '' : 'style="display:none;"'; ?>>
                <?php echo $cart_count > 99 ? '99+' : $cart_count; ?>
            </span>
        </div>
        <span>Cart</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/user/orders.php" class="bottom-nav-item" data-tab="orders" aria-label="Orders">
        <div class="bottom-nav-icon-wrap">
            <i class="fas fa-box-open"></i>
        </div>
        <span>Orders</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/user/profile.php" class="bottom-nav-item" data-tab="profile" aria-label="Profile">
        <div class="bottom-nav-icon-wrap">
            <i class="fas fa-user"></i>
        </div>
        <span>Profile</span>
    </a>
</nav>

<!-- Mobile Bottom Navigation JavaScript -->
<script src="<?php echo ASSETS_URL; ?>/js/bottom-nav.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/bottom-nav.js') ? filemtime(__DIR__ . '/../assets/js/bottom-nav.js') : '2.0'; ?>" defer></script>
