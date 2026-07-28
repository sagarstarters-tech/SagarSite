<?php
/**
 * WAPI SaaS - Data Deletion Instructions
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$pageTitle = 'Data Deletion';
$settings = new Settings();
include __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <div class="text-center mb-5">
                        <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle p-3 mb-4" style="width: 80px; height: 80px;">
                            <i class="bi bi-trash3-fill fs-1"></i>
                        </div>
                        <h1 class="fw-bold">Data Deletion Instructions</h1>
                        <p class="text-secondary">Learn how to manage and delete your data from WAPI</p>
                    </div>
                    
                    <div class="mb-5">
                        <h4 class="fw-bold mb-3">Overview</h4>
                        <p class="text-secondary">
                            At WAPI, we value your privacy and provide you with full control over your personal data. 
                            Users can request the deletion of their accounts and associated data at any time. 
                            If you use our services through third-party platforms like Facebook, you can also request data removal through these instructions.
                        </p>
                    </div>

                    <div class="mb-5">
                        <h4 class="fw-bold mb-3">1. How to Delete Your Account</h4>
                        <p class="text-secondary">The fastest way to delete your data is through your dashboard:</p>
                        <ol class="text-secondary">
                            <li class="mb-2">Log in to your <strong>WAPI Dashboard</strong>.</li>
                            <li class="mb-2">Navigate to <strong>Account Settings</strong>.</li>
                            <li class="mb-2">Click on <strong>Security</strong> or <strong>Subscription</strong> tab.</li>
                            <li class="mb-2">Select the <strong>Delete Account</strong> option at the bottom of the page.</li>
                            <li class="mb-2">Confirm your password and click <strong>Permanently Delete</strong>.</li>
                        </ol>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Warning:</strong> Account deletion is permanent. All your contacts, message history, and API configurations will be immediately and irrevocably removed from our servers.
                        </div>
                    </div>

                    <div class="mb-5">
                        <h4 class="fw-bold mb-3">2. Request via Email</h4>
                        <p class="text-secondary">
                            If you cannot access your account or wish to request data deletion manually, please send an email to our support team from your registered email address:
                        </p>
                        <div class="bg-light p-4 rounded-3 border-start border-primary border-4">
                            <p class="mb-1 fw-bold">Email to:</p>
                            <a href="mailto:<?= e($settings->get('contact_email', 'support@wapi.com')); ?>" class="text-primary text-decoration-none fs-5">
                                <?= e($settings->get('contact_email', 'support@wapi.com')); ?>
                            </a>
                            <p class="mt-3 mb-1 fw-bold">Subject:</p>
                            <p class="text-secondary mb-0">Data Deletion Request - [Your Full Name]</p>
                        </div>
                        <p class="text-secondary mt-3">
                            Our team will process your request within 48-72 business hours and confirm via email once the deletion is complete.
                        </p>
                    </div>

                    <div class="mb-5">
                        <h4 class="fw-bold mb-3">3. Facebook Data Deletion</h4>
                        <p class="text-secondary">
                            If you have connected our WAPI Facebook App to your Meta Business account and wish to remove the app data:
                        </p>
                        <ol class="text-secondary">
                            <li class="mb-2">Go to your Facebook Profile's <strong>Settings & Privacy > Settings</strong>.</li>
                            <li class="mb-2">Click <strong>Apps and Websites</strong> and you will see all of your Apps activities.</li>
                            <li class="mb-2">Select the checkbox of <strong>WAPI</strong>.</li>
                            <li class="mb-2">Click <strong>Remove</strong> button.</li>
                        </ol>
                    </div>

                    <div class="pt-4 border-top">
                        <p class="text-secondary small mb-0 font-monospace">
                            Last Updated: <?= date('F d, Y'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
