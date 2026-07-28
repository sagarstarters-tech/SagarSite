<?php
/**
 * WAPI SaaS - Dynamic Footer
 * Included on all public pages
 */
$settings = $settings ?? new Settings();
$chatWidgetEnabled = $settings->get('chat_widget_enabled', '1');
$chatWidgetNumber = $settings->get('chat_widget_number', '');
$chatWidgetMessage = $settings->get('chat_widget_message', 'Hi! I need help.');
$footerText = $settings->get('footer_text', '© 2026 WAPI. All rights reserved.');
$contactEmail = $settings->get('contact_email', 'support@wapi.com');
$contactPhone = $settings->get('contact_phone', '');
?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="navbar-brand mb-3" style="font-size: 1.75rem;">
                        <?= e($settings->get('site_name', 'WAPI')); ?>
                    </div>
                    <p class="text-secondary mb-3" style="font-size: 0.9375rem;">
                        <?= e($settings->get('site_tagline', 'Powerful WhatsApp Business API for your business')); ?>
                    </p>
                    <div class="footer-social">
                        <?php if ($settings->get('social_facebook')): ?>
                            <a href="<?= e($settings->get('social_facebook')); ?>" target="_blank" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_twitter')): ?>
                            <a href="<?= e($settings->get('social_twitter')); ?>" target="_blank" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_instagram')): ?>
                            <a href="<?= e($settings->get('social_instagram')); ?>" target="_blank" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_linkedin')): ?>
                            <a href="<?= e($settings->get('social_linkedin')); ?>" target="_blank" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_github')): ?>
                            <a href="<?= e($settings->get('social_github')); ?>" target="_blank" aria-label="GitHub"><i class="bi bi-github"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_youtube')): ?>
                            <a href="<?= e($settings->get('social_youtube')); ?>" target="_blank" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_telegram')): ?>
                            <a href="<?= e($settings->get('social_telegram')); ?>" target="_blank" aria-label="Telegram"><i class="bi bi-telegram"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Product</h5>
                    <ul class="footer-links">
                        <li><a href="<?= baseUrl('index.php#features'); ?>">Features</a></li>
                        <li><a href="<?= baseUrl('index.php#pricing'); ?>">Pricing</a></li>
                        <li><a href="<?= baseUrl('index.php#demo'); ?>">Demo</a></li>
                        <li><a href="<?= baseUrl('docs/'); ?>">API Docs</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Company</h5>
                    <ul class="footer-links">
                        <li><a href="<?= baseUrl('about.php'); ?>">About Us</a></li>
                        <li><a href="<?= baseUrl('contact.php'); ?>">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Legal</h5>
                    <ul class="footer-links">
                        <li><a href="<?= baseUrl('privacy.php'); ?>">Privacy Policy</a></li>
                        <li><a href="<?= baseUrl('terms.php'); ?>">Terms of Service</a></li>
                        <li><a href="<?= baseUrl('cookies.php'); ?>">Cookie Policy</a></li>
                        <li><a href="<?= baseUrl('gdpr.php'); ?>">GDPR</a></li>
                        <li><a href="<?= baseUrl('data-deletion.php'); ?>">Data Deletion</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Support</h5>
                    <ul class="footer-links">
                        <li><a href="mailto:<?= e($contactEmail); ?>"><?= e($contactEmail); ?></a></li>
                        <?php if ($contactPhone): ?>
                        <li><a href="tel:<?= e($contactPhone); ?>"><?= e($contactPhone); ?></a></li>
                        <?php endif; ?>
                        <li><a href="<?= baseUrl('index.php#faq'); ?>">FAQ</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= $footerText; ?></p>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Chat Widget (Home Page Only) -->
    <?php if ($chatWidgetEnabled === '1' && $chatWidgetNumber && basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
    <div class="chat-widget">
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $chatWidgetNumber); ?>?text=<?= urlencode($chatWidgetMessage); ?>" 
           target="_blank" class="chat-widget-btn" aria-label="Chat on WhatsApp">
            <i class="bi bi-whatsapp"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Main JS -->
    <script src="<?= asset('assets/js/app.js'); ?>"></script>
    
    <?php if (isset($extraJs)): ?>
        <?php foreach ((array)$extraJs as $js): ?>
            <script src="<?= $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
