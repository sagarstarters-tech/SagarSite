<?php
/**
 * WAPI SaaS - About Us
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'About Us';
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Empowering Modern Business Communication</h1>
                <p class="lead text-secondary mb-4">
                    WAPI is the world's most reliable and scalable WhatsApp Business API platform, designed to help businesses of all sizes grow and succeed.
                </p>
                <p class="text-secondary">
                    Founded in 2026, we've helped thousands of businesses automate their communication, improve customer engagement, and drive sales through the power of WhatsApp.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="p-5 bg-white shadow-sm rounded-4">
                    <img src="<?= asset('assets/img/hero-image.png'); ?>" alt="About Us" class="img-fluid rounded" onerror="this.src='https://placehold.co/600x400/6366f1/white?text=WAPI+Team'">
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 text-center">
                    <div class="feature-icon mb-3 mx-auto" style="width: 60px; height: 60px;">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <h4>Our Vision</h4>
                    <p class="text-secondary">To become the global standard for business-to-customer messaging and engagement.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 text-center">
                    <div class="feature-icon mb-3 mx-auto" style="width: 60px; height: 60px;">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <h4>Our Mission</h4>
                    <p class="text-secondary">To provide powerful, easy-to-use tools that bridge the gap between businesses and their customers.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 shadow-sm h-100 text-center">
                    <div class="feature-icon mb-3 mx-auto" style="width: 60px; height: 60px;">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <h4>Our Values</h4>
                    <p class="text-secondary">Transparency, innovation, and customer-first thinking in everything we build.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
