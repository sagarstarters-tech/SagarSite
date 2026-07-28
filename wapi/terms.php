<?php
/**
 * WAPI SaaS - Terms of Service
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'Terms of Service';
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <h1 class="fw-bold mb-4 text-center">Terms of Service</h1>
                    <p class="text-secondary text-center mb-5">Last modified: October 2026</p>
                    
                    <div class="card bg-light border-0 mb-5 text-center p-4">
                        <p class="mb-0">By using the WAPI platform, you agree to these terms. Please read them carefully.</p>
                    </div>

                    <h4 class="fw-bold mt-5 mb-3">1. Services Provided</h4>
                    <p class="text-secondary">WAPI provides a SaaS platform for accessing and managing WhatsApp APIs. You are responsible for any activity through your account and for keeping your login credentials secure.</p>

                    <h4 class="fw-bold mt-5 mb-3">2. User Conduct</h4>
                    <p class="text-secondary">You agree not to use the services for any unlawful purpose or to send any prohibited content, including spam, phishing, or malicious messages. You must comply with Meta's official WhatsApp Business Policy at all times.</p>

                    <h4 class="fw-bold mt-5 mb-3">3. Payments and Subscriptions</h4>
                    <p class="text-secondary">Certain services are available on a paid subscription basis. Fees are non-refundable except as required by law. We reserve the right to change our subscription fees upon reasonable notice.</p>

                    <h4 class="fw-bold mt-5 mb-3">4. Limitation of Liability</h4>
                    <p class="text-secondary">WAPI shall not be liable for any indirect, incidental, special, or consequential damages resulting from the use or inability to use our services.</p>

                    <h4 class="fw-bold mt-5 mb-3">5. Termination</h4>
                    <p class="text-secondary">We may terminate or suspend your access to our services immediately, without prior notice or liability, for any reason, including if you breach the Terms.</p>

                    <h4 class="fw-bold mt-5 mb-3">6. Governing Law</h4>
                    <p class="text-secondary">These Terms shall be governed and construed in accordance with the laws of India, without regard to its conflict of law provisions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
