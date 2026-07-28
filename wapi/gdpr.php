<?php
/**
 * WAPI SaaS - GDPR Compliance
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'GDPR Compliance';
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <h1 class="fw-bold mb-4">GDPR Compliance</h1>
                    <p class="text-secondary mb-5">Last modified: October 2026</p>
                    
                    <h4 class="fw-bold mt-5 mb-3">1. Our Commitment to GDPR</h4>
                    <p class="text-secondary">At WAPI, we are committed to upholding the General Data Protection Regulation (GDPR) standards for our users and customers. We take data protection and privacy seriously.</p>

                    <h4 class="fw-bold mt-5 mb-3">2. Data Processing Principles</h4>
                    <p class="text-secondary">WAPI processes all personal data fairly, lawfully, and in a transparent manner. We only collect data for specific, explicit, and legitimate purposes.</p>

                    <h4 class="fw-bold mt-5 mb-3">3. Your Rights Under GDPR</h4>
                    <ul>
                        <li class="text-secondary mb-3"><strong>Right of Access:</strong> You have the right to request access to the personal data we process about you.</li>
                        <li class="text-secondary mb-3"><strong>Right to Rectification:</strong> You have the right to request the correction of inaccurate or incomplete personal data.</li>
                        <li class="text-secondary mb-3"><strong>Right to Erasure (Right to be Forgotten):</strong> You may request the deletion of your personal data under certain conditions.</li>
                        <li class="text-secondary mb-3"><strong>Right to Restrict Processing:</strong> You have the right to object to or restrict our processing of your personal data.</li>
                        <li class="text-secondary mb-3"><strong>Right to Data Portability:</strong> You may request a copy of your personal data in a machine-readable format.</li>
                    </ul>

                    <h4 class="fw-bold mt-5 mb-3">4. International Data Transfers</h4>
                    <p class="text-secondary">When data is transferred outside the European Economic Area (EEA), WAPI ensures that appropriate safeguards are in place to maintain the security and privacy of the data.</p>

                    <h4 class="fw-bold mt-5 mb-3">5. Data Breach Notification</h4>
                    <p class="text-secondary">In the unlikely event of a data breach, WAPI has established internal procedures and will notify the relevant supervisory authority and affected data subjects without undue delay.</p>

                    <h4 class="fw-bold mt-5 mb-3">6. Contacting the DPO</h4>
                    <p class="text-secondary">If you have questions about your data or our GDPR compliance, please email our Data Protection Officer at <?= e($settings->get('contact_email', 'support@wapi.com')); ?>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
