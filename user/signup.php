<?php 
$page_title = "Register - Sagar Starters";
include '../includes/header.php'; 
?>

<div class="auth-page-bg">
    <div class="container py-3">
        <div class="auth-master-card">
            <div class="row g-0">
                <!-- ════════ LEFT PANE: VISUAL HERO ARTWORK ════════ -->
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="auth-hero-pane">
                        <div class="auth-hero-content">
                            <div class="auth-brand-badge">
                                <i class="fas fa-bolt"></i> Sagar Starters
                            </div>
                            <h2 class="auth-hero-title">Start Your Journey With Us</h2>
                            <p class="auth-hero-desc">
                                Create an account to unlock GST invoicing, quick warranty registration, live delivery tracking, and exclusive discounts.
                            </p>

                            <div class="auth-feature-pills">
                                <div class="auth-feature-pill">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Fast 1-Click Ordering</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span>Instant GST Invoices &amp; B2B Pricing</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-truck-ramp-box"></i>
                                    <span>Live WhatsApp &amp; SMS Tracking</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-hero-footer">
                            <span>&copy; <?php echo date('Y'); ?> Sagar Starters</span>
                            <div class="auth-social-icons">
                                <a href="https://facebook.com" target="_blank" class="auth-social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="https://instagram.com" target="_blank" class="auth-social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                                <a href="https://wa.me/919999999999" target="_blank" class="auth-social-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════ RIGHT PANE: REGISTRATION FORM ════════ -->
                <div class="col-lg-7">
                    <div class="auth-form-pane">
                        <!-- Mobile Brand Header (< 992px) -->
                        <div class="d-lg-none text-center mb-3">
                            <span class="auth-brand-badge mb-2 d-inline-flex bg-dark text-warning">
                                <i class="fas fa-bolt text-warning"></i> Sagar Starters
                            </span>
                        </div>

                        <!-- Top Switcher Tab -->
                        <div class="auth-tab-nav">
                            <a href="login.php" class="auth-tab-link">Sign In</a>
                            <a href="signup.php" class="auth-tab-link active">Register</a>
                        </div>

                        <div class="auth-form-header">
                            <h3>Create an Account 🚀</h3>
                            <p>Fill in the details below to register your new account.</p>
                        </div>

                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
                                <i class="fas fa-exclamation-circle text-danger flex-shrink-0"></i>
                                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                            </div>
                        <?php endif; ?>

                        <form action="../includes/auth.php" method="POST" autocomplete="on">
                            <input type="hidden" name="action" value="signup">
                            <?php echo csrf_field(); ?>

                            <!-- Full Name -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="signup_name">Full Name</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-user auth-input-icon"></i>
                                    <input type="text" name="name" id="signup_name" class="auth-input-control" placeholder="Enter your full name" autocomplete="name" required>
                                </div>
                            </div>

                            <!-- Email Address -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="signup_email">Email Address</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-envelope auth-input-icon"></i>
                                    <input type="email" name="email" id="signup_email" class="auth-input-control" placeholder="you@example.com" autocomplete="email" required>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="signup_password">Password</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-lock auth-input-icon"></i>
                                    <input type="password" name="password" id="signup_password" class="auth-input-control pe-5" placeholder="Minimum 8 characters" autocomplete="new-password" required>
                                    <button type="button" class="auth-pw-toggle-btn" onclick="togglePasswordVisibility('signup_password', this)" title="Show/Hide Password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Phone & Zip code in responsive cols -->
                            <div class="row g-2">
                                <div class="col-md-7 col-12">
                                    <div class="auth-field-wrapper">
                                        <label class="auth-field-label">Phone Number</label>
                                        <?php echo render_phone_input('phone', '', true); ?>
                                    </div>
                                </div>
                                <div class="col-md-5 col-12">
                                    <div class="auth-field-wrapper">
                                        <label class="auth-field-label" for="signup_zip">Pincode</label>
                                        <div class="auth-input-container">
                                            <i class="fas fa-map-pin auth-input-icon"></i>
                                            <input type="text" name="zip_code" id="signup_zip" class="auth-input-control" placeholder="e.g. 110001" autocomplete="postal-code" inputmode="numeric" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="signup_address">Street Address</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-home auth-input-icon"></i>
                                    <input type="text" name="address" id="signup_address" class="auth-input-control" placeholder="House/Shop no., street name, area" autocomplete="street-address" required>
                                </div>
                            </div>

                            <!-- City, State & Country -->
                            <div class="row g-2">
                                <div class="col-md-4 col-sm-6 col-12">
                                    <div class="auth-field-wrapper">
                                        <label class="auth-field-label" for="signup_city">City</label>
                                        <div class="auth-input-container">
                                            <i class="fas fa-city auth-input-icon"></i>
                                            <input type="text" name="city" id="signup_city" class="auth-input-control" placeholder="City" autocomplete="address-level2" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6 col-12">
                                    <div class="auth-field-wrapper">
                                        <label class="auth-field-label" for="signup_state">State</label>
                                        <div class="auth-input-container">
                                            <i class="fas fa-map auth-input-icon"></i>
                                            <input type="text" name="state" id="signup_state" class="auth-input-control" placeholder="State" autocomplete="address-level1" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="auth-field-wrapper">
                                        <label class="auth-field-label" for="signup_country">Country</label>
                                        <div class="auth-input-container">
                                            <i class="fas fa-globe auth-input-icon"></i>
                                            <input type="text" name="country" id="signup_country" class="auth-input-control" placeholder="India" autocomplete="country-name" value="India" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Agree terms checkbox -->
                            <div class="form-check mb-3 mt-1">
                                <input type="checkbox" class="form-check-input" id="agreeTerms" name="agree_terms" required>
                                <label class="form-check-label small text-muted" for="agreeTerms">
                                    I agree to the <a href="../page.php?slug=terms-conditions" class="text-primary fw-bold text-decoration-none" target="_blank">Terms of Service</a> &amp; <a href="../page.php?slug=privacy-policy" class="text-primary fw-bold text-decoration-none" target="_blank">Privacy Policy</a>
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-auth-submit">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <!-- Google Auth Option -->
                        <?php if (isset($global_settings['google_login_enabled']) && $global_settings['google_login_enabled'] == '1'): ?>
                        <div class="auth-divider">
                            <hr>
                            <span>or sign up with</span>
                            <hr>
                        </div>

                        <a href="../auth/google_redirect.php" class="btn btn-google-signin">
                            <svg width="20px" height="20px" viewBox="0 0 118 120" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <path d="M117.6,61.36 C117.6,57.1 117.2,53.01 116.5,49.09 L60,49.09 L60,72.3 L92.29,72.3 C90.9,79.8 86.67,86.15 80.31,90.4 L80.31,105.46 L99.7,105.46 C111.05,95.01 117.6,79.63 117.6,61.36 Z" fill="#4285F4"></path>
                                    <path d="M60,120 C76.2,120 89.78,114.62 99.7,105.46 L80.31,90.4 C74.94,94.0 68.07,96.13 60,96.13 C44.37,96.13 31.14,85.58 26.42,71.4 L6.38,71.4 L6.38,86.94 C16.25,106.55 36.54,120 60,120 Z" fill="#34A853"></path>
                                    <path d="M26.42,71.4 C25.22,67.8 24.54,63.95 24.54,60 C24.54,56.04 25.22,52.2 26.42,48.6 L26.42,33.05 L6.38,33.05 C2.31,41.15 0,50.31 0,60 C0,69.68 2.31,78.84 6.38,86.94 L26.42,71.4 Z" fill="#FBBC05"></path>
                                    <path d="M60,23.86 C68.8,23.86 76.71,26.89 82.93,32.83 L100.14,15.62 C89.75,5.94 76.17,0 60,0 C36.54,0 16.25,13.44 6.38,33.05 L26.42,48.6 C31.14,34.41 44.37,23.86 60,23.86 Z" fill="#EA4335"></path>
                                    <path d="M0,0 L120,0 L120,120 L0,120 L0,0 Z"></path>
                                </g>
                            </svg>
                            <span>Continue with Google</span>
                        </a>
                        <?php endif; ?>

                        <!-- Footer switch -->
                        <div class="text-center mt-4">
                            <span class="text-muted small">Already have an account?</span>
                            <a href="login.php" class="fw-bold text-primary ms-1 text-decoration-none">Sign In here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['error_popup'])): ?>
<script>
    alert("<?php echo $_SESSION['error_popup']; ?>");
</script>
<?php unset($_SESSION['error_popup']); ?>
<?php endif; ?>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
