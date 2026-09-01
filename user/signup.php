<?php include '../includes/header.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card product-card">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center montserrat primary-blue mb-4">Sign Up</h2>
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <form action="../includes/auth.php" method="POST" autocomplete="on">
                        <input type="hidden" name="action" value="signup">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label" for="signup_name">Full Name</label>
                            <input type="text" name="name" id="signup_name" class="form-control" autocomplete="name" placeholder="Enter your full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="signup_email">Email address</label>
                            <input type="email" name="email" id="signup_email" class="form-control" autocomplete="email" placeholder="you@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="signup_password">Password</label>
                            <input type="password" name="password" id="signup_password" class="form-control" autocomplete="new-password" placeholder="Minimum 8 characters" required>
                            <div class="form-check mt-1">
                                <input class="form-check-input show-password-toggle" type="checkbox" id="showPwSignup">
                                <label class="form-check-label small text-muted" for="showPwSignup">Show password</label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <?php echo render_phone_input('phone', '', true); ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="signup_zip">Zip / Pincode</label>
                                <input type="text" name="zip_code" id="signup_zip" class="form-control" autocomplete="postal-code" inputmode="numeric" placeholder="e.g. 110001" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="signup_address">Address</label>
                            <input type="text" name="address" id="signup_address" class="form-control" autocomplete="street-address" placeholder="Street address, house no." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="signup_city">City</label>
                            <input type="text" name="city" id="signup_city" class="form-control" autocomplete="address-level2" placeholder="City" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="signup_state">State/Province</label>
                                <input type="text" name="state" id="signup_state" class="form-control" autocomplete="address-level1" placeholder="State" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="signup_country">Country</label>
                                <input type="text" name="country" id="signup_country" class="form-control" autocomplete="country-name" placeholder="Country" required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agreeTerms" name="agree_terms" required>
                            <label class="form-check-label small text-muted" for="agreeTerms">
                                I agree to the <a href="../page.php?slug=terms-conditions" class="text-success text-decoration-none">Terms of Service</a> and <a href="../page.php?slug=privacy-policy" class="text-success text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-custom w-100 mt-3">Create Account</button>
                    </form>

                    <?php if (isset($global_settings['google_login_enabled']) && $global_settings['google_login_enabled'] == '1'): ?>
                    <div class="mt-4 text-center">
                        <div class="d-flex align-items-center mb-3">
                            <hr class="flex-grow-1 opacity-25">
                            <span class="mx-3 text-muted small text-uppercase">or</span>
                            <hr class="flex-grow-1 opacity-25">
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
                    </div>
                    <?php endif; ?>
                    <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
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

<?php include '../includes/footer.php'; ?>
