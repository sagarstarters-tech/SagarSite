<!-- Bottom Navigation UI Custom CSS -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/bottom-nav.css">

<!-- The Navigation Bar itself -->
<nav class="bottom-nav">
    <a href="<?php echo SITE_URL; ?>/index.php" class="bottom-nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/shop.php" class="bottom-nav-item">
        <i class="fas fa-th-large"></i>
        <span>Categories</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/cart.php" class="bottom-nav-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Cart</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/user/orders.php" class="bottom-nav-item">
        <i class="fas fa-box-open"></i>
        <span>Orders</span>
    </a>
    <a href="<?php echo SITE_URL; ?>/user/profile.php" class="bottom-nav-item">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<!-- JS for Active State and Android Ripple effect -->
<script src="<?php echo ASSETS_URL; ?>/js/bottom-nav.js"></script>
