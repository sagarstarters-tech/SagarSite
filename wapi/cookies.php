<?php
/**
 * WAPI SaaS - Cookie Policy
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'Cookie Policy';
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <h1 class="fw-bold mb-4">Cookie Policy</h1>
                    <p class="text-secondary mb-5">October 2026</p>
                    
                    <h4 class="fw-bold mt-5 mb-3">1. What are Cookies?</h4>
                    <p class="text-secondary">Cookies are small data files that are placed on your computer or mobile device when you visit a website. They are used to make websites work, or work more efficiently, as well as to provide reporting information.</p>

                    <h4 class="fw-bold mt-5 mb-3">2. How We Use Cookies</h4>
                    <p class="text-secondary">We use cookies for several reasons, including:</p>
                    <ul>
                        <li class="text-secondary mb-2"><strong>Essential Cookies:</strong> Required for the session management and authentication of your WAPI account.</li>
                        <li class="text-secondary mb-2"><strong>Analytical/Performance Cookies:</strong> Allow us to recognize and count the number of visitors and see how visitors move around our website.</li>
                        <li class="text-secondary mb-2"><strong>Functionality Cookies:</strong> Used to recognize you when you return to our website and remember your preferences.</li>
                    </ul>

                    <h4 class="fw-bold mt-5 mb-3">3. How Can I Control Cookies?</h4>
                    <p class="text-secondary">You have the right to decide whether to accept or reject cookies. You can set or amend your web browser controls to accept or refuse cookies. If you choose to reject cookies, you may still use our website though your access to some functionality and areas of our website may be restricted.</p>

                    <h4 class="fw-bold mt-5 mb-3">4. Cookies Used by Our Partners</h4>
                    <p class="text-secondary">Third parties (including, for example, advertising networks and providers of external services like web traffic analysis services) may also use cookies, over which we have no control. These cookies are likely to be analytical/performance cookies or targeting cookies.</p>

                    <h4 class="fw-bold mt-5 mb-3">5. More Information</h4>
                    <p class="text-secondary">If you have any questions about our use of cookies or other technologies, please contact us at <?= e($settings->get('contact_email', 'support@wapi.com')); ?>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
