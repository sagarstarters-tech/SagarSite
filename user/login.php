<?php 
$page_title = "Login - Sagar Starters";
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
                            <h2 class="auth-hero-title">Smart Power &amp; Motor Control</h2>
                            <p class="auth-hero-desc">
                                Access your orders, fast tracking, and exclusive pricing on India's most trusted industrial &amp; agricultural motor starters.
                            </p>

                            <div class="auth-feature-pills">
                                <div class="auth-feature-pill">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>100% Genuine &amp; Tested Starters</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-truck-fast"></i>
                                    <span>Fast Delivery Pan-India</span>
                                </div>
                                <div class="auth-feature-pill">
                                    <i class="fas fa-headset"></i>
                                    <span>24/7 Expert Technical Support</span>
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

                <!-- ════════ RIGHT PANE: LOGIN FORM ════════ -->
                <div class="col-lg-7">
                    <div class="auth-form-pane">
                        <!-- Top Switcher Tab -->
                        <div class="auth-tab-nav">
                            <a href="login.php" class="auth-tab-link active">Sign In</a>
                            <a href="signup.php" class="auth-tab-link">Register</a>
                        </div>

                        <div class="auth-form-header">
                            <h3>Welcome Back! 👋</h3>
                            <p>Enter your registered email and password to access your account.</p>
                        </div>

                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
                                <i class="fas fa-exclamation-circle text-danger flex-shrink-0"></i>
                                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
                                <i class="fas fa-check-circle text-success flex-shrink-0"></i>
                                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                            </div>
                        <?php endif; ?>

                        <form action="../includes/auth.php" method="POST" autocomplete="on">
                            <input type="hidden" name="action" value="login">
                            <?php echo csrf_field(); ?>

                            <!-- Email Field -->
                            <div class="auth-field-wrapper">
                                <label class="auth-field-label" for="login_email">Email Address</label>
                                <div class="auth-input-container">
                                    <i class="fas fa-envelope auth-input-icon"></i>
                                    <input type="email" name="email" id="login_email" class="auth-input-control" placeholder="name@example.com" autocomplete="email" required>
                                </div>
                            </div>

                            <!-- Password Field -->
                            <div class="auth-field-wrapper">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="auth-field-label mb-0" for="login_password">Password</label>
                                    <a href="forgot_password.php" class="text-primary small fw-bold text-decoration-none">Forgot Password?</a>
                                </div>
                                <div class="auth-input-container">
                                    <i class="fas fa-lock auth-input-icon"></i>
                                    <input type="password" name="password" id="login_password" class="auth-input-control pe-5" placeholder="Enter your password" autocomplete="current-password" required>
                                    <button type="button" class="auth-pw-toggle-btn" onclick="togglePasswordVisibility('login_password', this)" title="Show/Hide Password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-auth-submit">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In to Account
                            </button>
                        </form>

                        <!-- Google Auth Option -->
                        <?php if (isset($global_settings['google_login_enabled']) && $global_settings['google_login_enabled'] == '1'): ?>
                        <div class="auth-divider">
                            <hr>
                            <span>or continue with</span>
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
                            <span class="text-muted small">Don't have an account yet?</span>
                            <a href="signup.php" class="fw-bold text-primary ms-1 text-decoration-none">Create an Account</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
