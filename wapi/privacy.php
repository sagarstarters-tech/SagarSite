<?php
/**
 * WAPI SaaS - Privacy Policy
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <h1 class="fw-bold mb-4">Privacy Policy</h1>
                    <p class="text-secondary mb-4">Effective Date: October 2026</p>
                    
                    <h4 class="fw-bold mt-5 mb-3">1. Information We Collect</h4>
                    <p class="text-secondary">We collect personal information that you provide directly to us when you create an account, use our services, or communicate with us.</p>
                    <ul>
                        <li class="text-secondary mb-2">Account information like name, email, and password.</li>
                        <li class="text-secondary mb-2">Payment information processed through our secure payment providers.</li>
                        <li class="text-secondary mb-2">WhatsApp message data necessary for providing our API services.</li>
                    </ul>

                    <h4 class="fw-bold mt-5 mb-3">2. How We Use Your Information</h4>
                    <p class="text-secondary">We use the information we collect to operate, maintain, and provide the features and functionality of our WAPI platform, including sending alerts, managing subscriptions, and providing customer support.</p>

                    <h4 class="fw-bold mt-5 mb-3">3. Data Security</h4>
                    <p class="text-secondary">We implement a variety of security measures to maintain the safety of your personal information. Your sensitive data is encrypted using industry-standard protocols.</p>

                    <h4 class="fw-bold mt-5 mb-3">4. Cookies</h4>
                    <p class="text-secondary">We use cookies to improve your user experience and for analytical purposes. You can disable cookies in your browser settings if you wish.</p>

                    <h4 class="fw-bold mt-5 mb-3">5. Third-Party Services</h4>
                    <p class="text-secondary">We use third-party services for payments (Razorpay/Stripe) and infrastructure (WhatsApp Cloud API provided by Meta). These services have their own privacy policies.</p>

                    <h4 class="fw-bold mt-5 mb-3">6. Contact Us</h4>
                    <p class="text-secondary">If you have any questions about this Privacy Policy, please contact us at <?= e($settings->get('contact_email', 'support@wapi.com')); ?>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
